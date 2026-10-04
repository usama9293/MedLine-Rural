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
        ORDER BY vehicle_id ASC
    ");

    $stmt->execute();

    $vehicles = $stmt->fetchAll();

    jsonResponse(
        true,
        'Vehicles retrieved successfully.',
        $vehicles,
        200
    );

} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to retrieve vehicles.',
        null,
        500
    );
}