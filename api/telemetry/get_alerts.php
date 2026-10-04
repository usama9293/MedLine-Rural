<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';

requireRole('Admin', 'Dispatcher');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(
        false,
        'Method Not Allowed',
        null,
        405
    );
}

$shipmentId = filter_var(
    $_GET['shipment_id'] ?? null,
    FILTER_VALIDATE_INT
);

try {

    $sql = "
        SELECT
            alert_id,
            event_id,
            vehicle_id,
            shipment_id,
            alert_type,
            alert_message,
            alert_value,
            threshold_value,
            status,
            created_at,
            resolved_at
        FROM telemetry_alerts
        WHERE 1 = 1
    ";

    $params = [];

    if ($shipmentId !== false && $shipmentId !== null) {

        if ($shipmentId <= 0) {
            jsonResponse(
                false,
                'Invalid shipment ID.',
                null,
                400
            );
        }

        $sql .= "
            AND shipment_id = :shipment_id
        ";

        $params[':shipment_id'] = $shipmentId;
    }

    $sql .= "
        ORDER BY created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $alerts = $stmt->fetchAll();

    jsonResponse(
        true,
        'Telemetry alerts retrieved successfully.',
        $alerts
    );

} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to retrieve telemetry alerts.',
        null,
        500
    );
}