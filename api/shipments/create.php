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

$requestId = filter_var(
    $data['request_id'] ?? null,
    FILTER_VALIDATE_INT
);

$vehicleId = filter_var(
    $data['vehicle_id'] ?? null,
    FILTER_VALIDATE_INT
);

$driverId = filter_var(
    $data['driver_id'] ?? null,
    FILTER_VALIDATE_INT
);

$estimatedDelivery = trim(
    $data['estimated_delivery'] ?? ''
);


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if ($requestId === false || $requestId <= 0) {
    jsonResponse(
        false,
        'Invalid request ID.',
        null,
        400
    );
}

if ($vehicleId === false || $vehicleId <= 0) {
    jsonResponse(
        false,
        'Invalid vehicle ID.',
        null,
        400
    );
}

if ($driverId === false || $driverId <= 0) {
    jsonResponse(
        false,
        'Invalid driver ID.',
        null,
        400
    );
}


/*
|--------------------------------------------------------------------------
| ESTIMATED DELIVERY VALIDATION
|--------------------------------------------------------------------------
*/

if ($estimatedDelivery !== '') {

    $timestamp = strtotime($estimatedDelivery);

    if ($timestamp === false) {
        jsonResponse(
            false,
            'Invalid estimated delivery date.',
            null,
            400
        );
    }

    if ($timestamp < time()) {
        jsonResponse(
            false,
            'Estimated delivery cannot be in the past.',
            null,
            400
        );
    }

    $estimatedDelivery = date(
        'Y-m-d H:i:s',
        $timestamp
    );

} else {

    $estimatedDelivery = null;
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | CHECK SUPPLY REQUEST
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            request_id,
            status,
            urgency_score
        FROM supply_requests
        WHERE request_id = :request_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':request_id' => $requestId
    ]);

    $request = $stmt->fetch();

    if (!$request) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Supply request not found.',
            null,
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | REQUEST MUST BE APPROVED
    |--------------------------------------------------------------------------
    */

    if ($request['status'] !== 'Approved') {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Only approved supply requests can be assigned to a shipment.',
            null,
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | REQUEST MUST HAVE BEEN TRIAGED
    |--------------------------------------------------------------------------
    */

    if ($request['urgency_score'] === null) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Supply request must be triaged before shipment creation.',
            null,
            422
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK DUPLICATE SHIPMENT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            shipment_id,
            status
        FROM shipments
        WHERE request_id = :request_id
        LIMIT 1
    ");

    $stmt->execute([
        ':request_id' => $requestId
    ]);

    $existingShipment = $stmt->fetch();

    if ($existingShipment) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'A shipment already exists for this supply request.',
            null,
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK VEHICLE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            vehicle_id,
            vehicle_number,
            status,
            refrigerated
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
            'Vehicle not found.',
            null,
            404
        );
    }

    if ($vehicle['status'] !== 'Available') {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Vehicle is not available.',
            null,
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK VEHICLE ACTIVE SHIPMENT CONFLICT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            shipment_id,
            status
        FROM shipments
        WHERE vehicle_id = :vehicle_id
        AND status IN ('Pending', 'Dispatched', 'In Transit')
        LIMIT 1
    ");

    $stmt->execute([
        ':vehicle_id' => $vehicleId
    ]);

    $activeVehicleShipment = $stmt->fetch();

    if ($activeVehicleShipment) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Selected vehicle is already assigned to an active shipment.',
            null,
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK COLD-CHAIN REQUIREMENT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            MAX(i.cold_chain_required) AS requires_cold_chain
        FROM request_items ri
        INNER JOIN items i ON ri.item_id = i.item_id
        WHERE ri.request_id = :request_id
    ");

    $stmt->execute([
        ':request_id' => $requestId
    ]);

    $coldChainCheck = $stmt->fetch();
    $requiresColdChain = $coldChainCheck['requires_cold_chain'] ?? 0;

    if ($requiresColdChain) {

        if (!$vehicle['refrigerated']) {

            $pdo->rollBack();

            jsonResponse(
                false,
                'Selected vehicle is not refrigerated and cannot be assigned to this cold-chain shipment.',
                null,
                409
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK DRIVER
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            driver_id,
            user_id,
            license_number,
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
            'Driver not found.',
            null,
            404
        );
    }

    if ($driver['status'] !== 'Available') {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Driver is not available.',
            null,
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK DRIVER ACTIVE SHIPMENT CONFLICT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            shipment_id,
            status
        FROM shipments
        WHERE driver_id = :driver_id
        AND status IN ('Pending', 'Dispatched', 'In Transit')
        LIMIT 1
    ");

    $stmt->execute([
        ':driver_id' => $driverId
    ]);

    $activeDriverShipment = $stmt->fetch();

    if ($activeDriverShipment) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Selected driver is already assigned to an active shipment.',
            null,
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE SHIPMENT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO shipments
        (
            request_id,
            vehicle_id,
            driver_id,
            status,
            estimated_delivery
        )
        VALUES
        (
            :request_id,
            :vehicle_id,
            :driver_id,
            'Pending',
            :estimated_delivery
        )
    ");

    $stmt->execute([
        ':request_id' => $requestId,
        ':vehicle_id' => $vehicleId,
        ':driver_id' => $driverId,
        ':estimated_delivery' => $estimatedDelivery
    ]);

    $shipmentId = (int)$pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Shipment created successfully.',
        [
            'shipment_id' => $shipmentId,
            'request_id' => $requestId,
            'vehicle_id' => $vehicleId,
            'driver_id' => $driverId,
            'status' => 'Pending',
            'estimated_delivery' => $estimatedDelivery
        ],
        201
    );


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to create shipment.',
        null,
        500
    );
}