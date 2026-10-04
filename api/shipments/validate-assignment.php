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

$vehicleId = filter_var(
    $data['vehicle_id'] ?? null,
    FILTER_VALIDATE_INT
);

$driverId = filter_var(
    $data['driver_id'] ?? null,
    FILTER_VALIDATE_INT
);


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if ($shipmentId === false || $shipmentId <= 0) {
    jsonResponse(
        false,
        'Invalid shipment ID.',
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


try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | CHECK SHIPMENT
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


    /*
    |--------------------------------------------------------------------------
    | SHIPMENT STATUS VALIDATION
    |--------------------------------------------------------------------------
    */

    $assignableStatuses = [
        'Pending'
    ];

    if (!in_array($shipment['status'], $assignableStatuses, true)) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Driver and vehicle can only be assigned to a Pending shipment.',
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
            vehicle_type,
            capacity,
            refrigerated,
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
    | CHECK VEHICLE ACTIVE SHIPMENT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            shipment_id,
            status
        FROM shipments
        WHERE vehicle_id = :vehicle_id
          AND shipment_id <> :shipment_id
          AND status IN ('Pending', 'Dispatched', 'In Transit')
        LIMIT 1
    ");

    $stmt->execute([
        ':vehicle_id' => $vehicleId,
        ':shipment_id' => $shipmentId
    ]);

    $vehicleShipment = $stmt->fetch();

    if ($vehicleShipment) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Vehicle is already assigned to another active shipment.',
            null,
            409
        );
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
    | CHECK DRIVER ACTIVE SHIPMENT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            shipment_id,
            status
        FROM shipments
        WHERE driver_id = :driver_id
          AND shipment_id <> :shipment_id
          AND status IN ('Pending', 'Dispatched', 'In Transit')
        LIMIT 1
    ");

    $stmt->execute([
        ':driver_id' => $driverId,
        ':shipment_id' => $shipmentId
    ]);

    $driverShipment = $stmt->fetch();

    if ($driverShipment) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Driver is already assigned to another active shipment.',
            null,
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    jsonResponse(
        true,
        'Driver and vehicle are available for assignment.',
        [
            'shipment_id' => (int) $shipment['shipment_id'],
            'vehicle_id' => (int) $vehicle['vehicle_id'],
            'vehicle_number' => $vehicle['vehicle_number'],
            'vehicle_available' => true,
            'driver_id' => (int) $driver['driver_id'],
            'driver_available' => true,
            'assignment_valid' => true
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
        'Unable to validate driver and vehicle assignment.',
        null,
        500
    );
}