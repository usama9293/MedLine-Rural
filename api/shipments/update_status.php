<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';

requireRole('Driver', 'Dispatcher', 'Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(
        false,
        'Method Not Allowed',
        null,
        405
    );
}

$data = getJsonInput();

$shipmentId = filter_var(
    $data['shipment_id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($shipmentId === false || $shipmentId <= 0) {
    jsonResponse(
        false,
        'Invalid shipment ID.',
        null,
        400
    );
}

$newStatus = trim((string) ($data['status'] ?? ''));

if ($newStatus === '') {
    jsonResponse(
        false,
        'Shipment ID and status are required.',
        null,
        400
    );
}

$allowedStatuses = [
    'Pending',
    'Dispatched',
    'In Transit',
    'Delivered'
];

if (!in_array($newStatus, $allowedStatuses, true)) {
    jsonResponse(
        false,
        'Invalid shipment status.',
        null,
        400
    );
}

try {

    /*
    |--------------------------------------------------------------------------
    | AUTHORIZE SHIPMENT ACCESS
    |--------------------------------------------------------------------------
    |
    | For Driver role, verify that the shipment belongs to this driver.
    | This check happens before the transaction to avoid locking unnecessarily.
    |
    */

    if ($_SESSION['role_name'] === 'Driver') {

        $userId = $_SESSION['user_id'];

        $stmt = $pdo->prepare("
            SELECT
                s.shipment_id
            FROM shipments s
            INNER JOIN drivers d
                ON s.driver_id = d.driver_id
            WHERE s.shipment_id = :shipment_id
              AND d.user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':shipment_id' => $shipmentId,
            ':user_id' => $userId
        ]);

        $authCheck = $stmt->fetch();

        if (!$authCheck) {
            jsonResponse(
                false,
                'Shipment not found or you are not authorised to update this shipment.',
                null,
                404
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | START TRANSACTION
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | LOAD SHIPMENT WITH ROW LOCK
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            shipment_id,
            request_id,
            vehicle_id,
            driver_id,
            status
        FROM shipments
        WHERE shipment_id = :shipment_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':shipment_id' => $shipmentId
    ]);

    $shipment = $stmt->fetch();

    if (!$shipment) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Shipment not found.',
            null,
            404
        );
    }

    $currentStatus = $shipment['status'];
    $requestId = (int) $shipment['request_id'];
    $vehicleId = $shipment['vehicle_id'] !== null ? (int) $shipment['vehicle_id'] : null;
    $driverId = $shipment['driver_id'] !== null ? (int) $shipment['driver_id'] : null;


    /*
    |--------------------------------------------------------------------------
    | VALIDATE STATUS TRANSITION
    |--------------------------------------------------------------------------
    */

    $validTransitions = [
        'Pending' => ['Dispatched'],
        'Dispatched' => ['In Transit'],
        'In Transit' => ['Delivered'],
        'Delivered' => []
    ];

    if (
        $currentStatus === 'Delivered' ||
        ($currentStatus !== $newStatus &&
        !in_array(
            $newStatus,
            $validTransitions[$currentStatus] ?? [],
            true
        ))
    ) {

        $pdo->rollBack();

        jsonResponse(
            false,
            $currentStatus === 'Delivered'
                ? 'Shipment has already been delivered.'
                : "Invalid status transition from $currentStatus to $newStatus.",
            null,
            400
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE SHIPMENT
    |--------------------------------------------------------------------------
    */

    $dispatchedAt = null;
    $deliveredAt = null;

    if ($newStatus === 'Dispatched') {
        $dispatchedAt = date('Y-m-d H:i:s');
    }

    if ($newStatus === 'Delivered') {
        $deliveredAt = date('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare("
        UPDATE shipments
        SET
            status = :status,
            dispatched_at = COALESCE(dispatched_at, :dispatched_at),
            delivered_at = COALESCE(delivered_at, :delivered_at)
        WHERE shipment_id = :shipment_id
    ");

    $stmt->execute([
        ':status' => $newStatus,
        ':dispatched_at' => $dispatchedAt,
        ':delivered_at' => $deliveredAt,
        ':shipment_id' => $shipmentId
    ]);


    /*
    |--------------------------------------------------------------------------
    | FINALISE SUPPLY REQUEST & REPLENISH CLINIC INVENTORY
    |--------------------------------------------------------------------------
    |
    | When shipment is delivered, the related supply request becomes Completed,
    | and its requested quantities are added to the destination clinic's inventory.
    |
    */

    $requestCompleted = false;

    if ($newStatus === 'Delivered') {

        $stmt = $pdo->prepare("
            UPDATE supply_requests
            SET status = 'Completed'
            WHERE request_id = :request_id
              AND status = 'Approved'
        ");

        $stmt->execute([
            ':request_id' => $requestId
        ]);

        if ($stmt->rowCount() > 0) {
            $requestCompleted = true;
        }


        /*
        |--------------------------------------------------------------------------
        | REPLENISH INVENTORY
        |--------------------------------------------------------------------------
        |
        | Only replenish when transitioning from In Transit to Delivered and
        | the related supply request was transitioned to Completed.
        |
        */

        if ($currentStatus === 'In Transit' && $requestCompleted) {

            $stmtClinic = $pdo->prepare("
                SELECT clinic_id
                FROM supply_requests
                WHERE request_id = :request_id
                LIMIT 1
            ");

            $stmtClinic->execute([
                ':request_id' => $requestId
            ]);

            $destinationClinicId = $stmtClinic->fetchColumn();

            if ($destinationClinicId) {

                $stmtItems = $pdo->prepare("
                    SELECT
                        item_id,
                        quantity_requested
                    FROM request_items
                    WHERE request_id = :request_id
                ");

                $stmtItems->execute([
                    ':request_id' => $requestId
                ]);

                $requestedItems = $stmtItems->fetchAll();

                if (!empty($requestedItems)) {

                    $stmtInventory = $pdo->prepare("
                        INSERT INTO inventory
                            (clinic_id, item_id, quantity_available, average_daily_usage)
                        VALUES
                            (:clinic_id, :item_id, :quantity, 0)
                        ON DUPLICATE KEY UPDATE
                            quantity_available = quantity_available + VALUES(quantity_available),
                            last_updated = CURRENT_TIMESTAMP
                    ");

                    foreach ($requestedItems as $item) {

                        $stmtInventory->execute([
                            ':clinic_id' => (int) $destinationClinicId,
                            ':item_id'   => (int) $item['item_id'],
                            ':quantity'  => (float) $item['quantity_requested']
                        ]);
                    }
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | RESTORE VEHICLE STATUS
        |--------------------------------------------------------------------------
        |
        | When shipment is delivered, make the assigned vehicle Available again
        | for future assignments.
        |
        */

        if ($vehicleId !== null) {

            $stmt = $pdo->prepare("
                UPDATE vehicles
                SET status = 'Available'
                WHERE vehicle_id = :vehicle_id
            ");

            $stmt->execute([
                ':vehicle_id' => $vehicleId
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | RESTORE DRIVER STATUS
        |--------------------------------------------------------------------------
        |
        | When shipment is delivered, make the assigned driver Available again
        | for future assignments.
        |
        */

        if ($driverId !== null) {

            $stmt = $pdo->prepare("
                UPDATE drivers
                SET status = 'Available'
                WHERE driver_id = :driver_id
            ");

            $stmt->execute([
                ':driver_id' => $driverId
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT TRANSACTION
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Shipment status updated successfully.',
        [
            'shipment_id' => $shipmentId,
            'request_id' => $requestId,
            'status' => $newStatus,
            'request_status' =>
                $newStatus === 'Delivered'
                    ? 'Completed'
                    : null
        ]
    );

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Internal server error.',
        null,
        500
    );
}