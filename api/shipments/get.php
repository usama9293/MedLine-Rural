<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

requireRole('Driver', 'Dispatcher', 'Admin');

header('Content-Type: application/json');

try {

    $role = $_SESSION['role_name'];

    if ($role === 'Driver') {

        $userId = $_SESSION['user_id'];

        $stmt = $pdo->prepare("
            SELECT
                s.shipment_id,
                s.request_id,
                s.vehicle_id,
                s.driver_id,
                s.status,
                s.dispatched_at,
                s.estimated_delivery,
                s.delivered_at,
                c.clinic_name,
                sr.urgency_score
            FROM shipments s

            INNER JOIN drivers d
                ON s.driver_id = d.driver_id

            INNER JOIN supply_requests sr
                ON s.request_id = sr.request_id

            INNER JOIN clinics c
                ON sr.clinic_id = c.clinic_id

            WHERE d.user_id = :user_id

            ORDER BY s.created_at DESC
        ");

        $stmt->execute([
            ':user_id' => $userId
        ]);

    } else {

        // Dispatcher and Admin can view all shipments.

        $stmt = $pdo->query("
            SELECT
                s.shipment_id,
                s.request_id,
                s.vehicle_id,
                s.driver_id,
                s.status,
                s.dispatched_at,
                s.estimated_delivery,
                s.delivered_at,
                c.clinic_name,
                sr.urgency_score
            FROM shipments s

            INNER JOIN supply_requests sr
                ON s.request_id = sr.request_id

            INNER JOIN clinics c
                ON sr.clinic_id = c.clinic_id

            ORDER BY
                sr.urgency_score DESC,
                s.created_at DESC
        ");
    }

    $shipments = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $shipments
    ]);

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Internal server error.'
    ]);
}