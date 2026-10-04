<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';

requireRole('Admin', 'Dispatcher');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(
        false,
        'Method Not Allowed',
        null,
        405
    );
}

$data = getJsonInput();

$driverId = filter_var(
    $data['driver_id'] ?? null,
    FILTER_VALIDATE_INT
);

$status = trim($data['status'] ?? '');

if ($driverId === false || $driverId <= 0) {
    jsonResponse(
        false,
        'Invalid driver ID.',
        null,
        400
    );
}

$allowedStatuses = [
    'Available',
    'On Trip',
    'Inactive'
];

if (!in_array($status, $allowedStatuses, true)) {
    jsonResponse(
        false,
        'Invalid driver status.',
        null,
        400
    );
}

try {

    /*
    |--------------------------------------------------------------------------
    | START TRANSACTION
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | GET DRIVER WITH ROW LOCK
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
            'Driver not found.',
            null,
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE STATUS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE drivers
        SET status = :status
        WHERE driver_id = :driver_id
    ");

    $stmt->execute([
        ':status' => $status,
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
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Driver status updated successfully.',
        [
            'driver_id' => (int) $driver['driver_id'],
            'previous_status' => $driver['status'],
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
        'Unable to update driver status.',
        null,
        500
    );
}