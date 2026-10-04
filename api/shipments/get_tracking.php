<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';

requireRole('Admin', 'Dispatcher', 'Driver');

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

if ($shipmentId === false || $shipmentId <= 0) {
    jsonResponse(
        false,
        'Invalid shipment ID.',
        null,
        400
    );
}

try {

    /*
    |--------------------------------------------------------------------------
    | GET SHIPMENT
    |--------------------------------------------------------------------------
    */

    if ($_SESSION['role_name'] === 'Driver') {

        $stmt = $pdo->prepare("
            SELECT
                s.shipment_id,
                s.request_id,
                s.vehicle_id,
                s.driver_id,
                s.status,
                s.estimated_delivery,
                s.dispatched_at,
                s.delivered_at
            FROM shipments s
            INNER JOIN drivers d
                ON s.driver_id = d.driver_id
            WHERE s.shipment_id = :shipment_id
              AND d.user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':shipment_id' => $shipmentId,
            ':user_id' => $_SESSION['user_id']
        ]);

    } else {

        $stmt = $pdo->prepare("
            SELECT
                shipment_id,
                request_id,
                vehicle_id,
                driver_id,
                status,
                estimated_delivery,
                dispatched_at,
                delivered_at
            FROM shipments
            WHERE shipment_id = :shipment_id
            LIMIT 1
        ");

        $stmt->execute([
            ':shipment_id' => $shipmentId
        ]);
    }

    $shipment = $stmt->fetch();

    if (!$shipment) {
        jsonResponse(
            false,
            'Shipment not found or you are not authorised to view it.',
            null,
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | GET TELEMETRY
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            event_id,
            vehicle_id,
            shipment_id,
            event_type,
            temperature,
            latitude,
            longitude,
            recorded_at
        FROM telemetry_events
        WHERE shipment_id = :shipment_id
        ORDER BY recorded_at DESC
    ");

    $stmt->execute([
        ':shipment_id' => $shipmentId
    ]);

    $telemetry = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | GET ALERTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
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
        WHERE shipment_id = :shipment_id
        ORDER BY created_at DESC
    ");

    $stmt->execute([
        ':shipment_id' => $shipmentId
    ]);

    $alerts = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Shipment tracking retrieved successfully.',
        [
            'shipment' => $shipment,
            'telemetry' => $telemetry,
            'alerts' => $alerts
        ],
        200
    );

} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to retrieve shipment tracking.',
        null,
        500
    );
}