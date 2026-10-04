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
        ORDER BY d.driver_id ASC
    ");

    $stmt->execute();

    $drivers = $stmt->fetchAll();

    jsonResponse(
        true,
        'Drivers retrieved successfully.',
        $drivers,
        200
    );

} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to retrieve drivers.',
        null,
        500
    );
}