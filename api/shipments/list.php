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

    $stmt = $pdo->query("
        SELECT
            s.shipment_id,
            s.request_id,
            s.status,
            s.estimated_delivery,
            s.dispatched_at,
            s.delivered_at,

            v.vehicle_id,
            v.vehicle_number,

            d.driver_id,
            u.full_name AS driver_name

        FROM shipments s

        LEFT JOIN vehicles v
            ON s.vehicle_id = v.vehicle_id

        LEFT JOIN drivers d
            ON s.driver_id = d.driver_id

        LEFT JOIN users u
            ON d.user_id = u.user_id

        ORDER BY s.shipment_id DESC
    ");

    $shipments = $stmt->fetchAll();

    foreach ($shipments as &$shipment) {

        $shipment['shipment_id'] = (int) $shipment['shipment_id'];
        $shipment['request_id'] = (int) $shipment['request_id'];

        if ($shipment['vehicle_id'] !== null) {
            $shipment['vehicle_id'] = (int) $shipment['vehicle_id'];
        }

        if ($shipment['driver_id'] !== null) {
            $shipment['driver_id'] = (int) $shipment['driver_id'];
        }
    }

    jsonResponse(
        true,
        'Shipments retrieved successfully.',
        $shipments
    );

} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to retrieve shipments.',
        null,
        500
    );
}