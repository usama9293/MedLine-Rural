<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';

requireRole('Admin', 'Dispatcher');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(
        false,
        'Method Not Allowed',
        null,
        405
    );
}

$driverId = filter_input(
    INPUT_GET,
    'driver_id',
    FILTER_VALIDATE_INT
);

if ($driverId === false || $driverId === null || $driverId <= 0) {
    jsonResponse(
        false,
        'Invalid driver ID.',
        null,
        400
    );
}

try {

    $stmt = $pdo->prepare("
        SELECT
            d.driver_id,
            d.user_id,
            u.full_name AS driver_name,
            u.email,
            d.license_number,
            d.status,
            d.created_at
        FROM drivers d
        INNER JOIN users u
            ON u.user_id = d.user_id
        WHERE d.driver_id = :driver_id
        LIMIT 1
    ");

    $stmt->execute([
        ':driver_id' => $driverId
    ]);

    $driver = $stmt->fetch();

    if (!$driver) {
        jsonResponse(
            false,
            'Driver not found.',
            null,
            404
        );
    }

    jsonResponse(
        true,
        'Driver retrieved successfully.',
        $driver,
        200
    );

} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to retrieve driver.',
        null,
        500
    );
}