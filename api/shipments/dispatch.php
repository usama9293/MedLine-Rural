<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';

requireRole('Dispatcher', 'Admin');

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


/*
|--------------------------------------------------------------------------
| DATABASE TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | LOAD SHIPMENT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            shipment_id,
            request_id,
            vehicle_id,
            driver_id,
            status,
            estimated_delivery
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


    /*
    |--------------------------------------------------------------------------
    | VALIDATE SHIPMENT STATUS
    |--------------------------------------------------------------------------
    */

    if ($shipment['status'] !== 'Pending') {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Only shipments with Pending status can be dispatched.',
            null,
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE VEHICLE AND DRIVER ARE ASSIGNED
    |--------------------------------------------------------------------------
    */

    if ($shipment['vehicle_id'] === null) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'No vehicle assigned to this shipment.',
            null,
            422
        );
    }

    if ($shipment['driver_id'] === null) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'No driver assigned to this shipment.',
            null,
            422
        );
    }

    $vehicleId = (int)$shipment['vehicle_id'];
    $driverId = (int)$shipment['driver_id'];


    /*
    |--------------------------------------------------------------------------
    | LOAD VEHICLE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            vehicle_id,
            vehicle_number,
            status
        FROM vehicles
        WHERE vehicle_id = :vehicle_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':vehicle_id' => $vehicleId
    ]);

    $vehicle = $stmt->fetch();

    if (!$vehicle) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Assigned vehicle not found.',
            null,
            404
        );
    }

    if ($vehicle['status'] !== 'Available') {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Assigned vehicle is not available for dispatch.',
            null,
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD DRIVER
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            driver_id,
            user_id,
            status
        FROM drivers
        WHERE driver_id = :driver_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':driver_id' => $driverId
    ]);

    $driver = $stmt->fetch();

    if (!$driver) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Assigned driver not found.',
            null,
            404
        );
    }

    if ($driver['status'] !== 'Available') {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Assigned driver is not available for dispatch.',
            null,
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE SHIPMENT
    |--------------------------------------------------------------------------
    */

    $dispatchedAt = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        UPDATE shipments
        SET
            status = 'Dispatched',
            dispatched_at = :dispatched_at
        WHERE shipment_id = :shipment_id
    ");

    $stmt->execute([
        ':dispatched_at' => $dispatchedAt,
        ':shipment_id' => $shipmentId
    ]);


    /*
    |--------------------------------------------------------------------------
    | UPDATE VEHICLE STATUS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE vehicles
        SET status = 'In Transit'
        WHERE vehicle_id = :vehicle_id
    ");

    $stmt->execute([
        ':vehicle_id' => $vehicleId
    ]);


    /*
    |--------------------------------------------------------------------------
    | UPDATE DRIVER STATUS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE drivers
        SET status = 'On Trip'
        WHERE driver_id = :driver_id
    ");

    $stmt->execute([
        ':driver_id' => $driverId
    ]);


    /*
    |--------------------------------------------------------------------------
    | COMMIT TRANSACTION
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Shipment dispatched successfully.',
        [
            'shipment_id' => (int)$shipment['shipment_id'],
            'request_id' => (int)$shipment['request_id'],
            'vehicle_id' => $vehicleId,
            'driver_id' => $driverId,
            'status' => 'Dispatched',
            'dispatched_at' => $dispatchedAt
        ],
        200
    );


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to dispatch shipment.',
        null,
        500
    );
}
