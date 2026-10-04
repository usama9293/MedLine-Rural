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

$vehicleId = filter_var(
    $_GET['vehicle_id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($vehicleId === false || $vehicleId <= 0) {
    jsonResponse(
        false,
        'Invalid vehicle ID.',
        null,
        400
    );
}

try {

    $stmt = $pdo->prepare("
        SELECT
            vehicle_id,
            vehicle_number,
            vehicle_type,
            capacity,
            refrigerated,
            status,
            created_at
        FROM vehicles
        WHERE vehicle_id = :vehicle_id
        LIMIT 1
    ");

    $stmt->execute([
        ':vehicle_id' => $vehicleId
    ]);

    $vehicle = $stmt->fetch();

    if (!$vehicle) {
        jsonResponse(
            false,
            'Vehicle not found.',
            null,
            404
        );
    }

    jsonResponse(
        true,
        'Vehicle retrieved successfully.',
        $vehicle,
        200
    );

} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to retrieve vehicle.',
        null,
        500
    );
}