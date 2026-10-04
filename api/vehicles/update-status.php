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

$vehicleId = filter_var(
    $data['vehicle_id'] ?? null,
    FILTER_VALIDATE_INT
);

$status = trim($data['status'] ?? '');


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if ($vehicleId === false || $vehicleId <= 0) {
    jsonResponse(
        false,
        'Invalid vehicle ID.',
        null,
        400
    );
}


/*
|--------------------------------------------------------------------------
| STATUS VALIDATION
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'Available',
    'In Transit',
    'Maintenance',
    'Inactive'
];

if (!in_array($status, $allowedStatuses, true)) {
    jsonResponse(
        false,
        'Invalid vehicle status.',
        [
            'allowed_statuses' => $allowedStatuses
        ],
        400
    );
}


try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | GET VEHICLE
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
            'Vehicle not found.',
            null,
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK SAME STATUS
    |--------------------------------------------------------------------------
    */

    if ($vehicle['status'] === $status) {

        $pdo->rollBack();

        jsonResponse(
            false,
            'Vehicle is already in this status.',
            [
                'vehicle_id' => (int) $vehicle['vehicle_id'],
                'status' => $vehicle['status']
            ],
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE STATUS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE vehicles
        SET status = :status
        WHERE vehicle_id = :vehicle_id
    ");

    $stmt->execute([
        ':status' => $status,
        ':vehicle_id' => $vehicleId
    ]);


    $previousStatus = $vehicle['status'];


    /*
    |--------------------------------------------------------------------------
    | COMMIT
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
        'Vehicle status updated successfully.',
        [
            'vehicle_id' => (int) $vehicle['vehicle_id'],
            'vehicle_number' => $vehicle['vehicle_number'],
            'previous_status' => $previousStatus,
            'status' => $status
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
        'Unable to update vehicle status.',
        null,
        500
    );
}