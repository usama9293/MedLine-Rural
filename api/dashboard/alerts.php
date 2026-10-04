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


/*
|--------------------------------------------------------------------------
| OPTIONAL FILTER
|--------------------------------------------------------------------------
*/

$shipmentId = null;

if (isset($_GET['shipment_id'])) {

    $shipmentId = filter_var(
        $_GET['shipment_id'],
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
}


try {

    /*
    |--------------------------------------------------------------------------
    | BUILD FILTER
    |--------------------------------------------------------------------------
    */

    $where = "WHERE 1 = 1";
    $params = [];

    if ($shipmentId !== null) {

        $where .= "
            AND shipment_id = :shipment_id
        ";

        $params[':shipment_id'] = $shipmentId;
    }


    /*
    |--------------------------------------------------------------------------
    | ALERT SUMMARY
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_alerts,

            SUM(status = 'Open') AS open_alerts,

            SUM(status = 'Acknowledged') AS acknowledged_alerts,

            SUM(status = 'Resolved') AS resolved_alerts,

            SUM(alert_type = 'Temperature') AS temperature_alerts

        FROM telemetry_alerts

        $where
    ");

    $stmt->execute($params);

    $summary = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | RECENT ALERTS
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

        $where

        ORDER BY created_at DESC, alert_id DESC

        LIMIT 10
    ");

    $stmt->execute($params);

    $recentAlerts = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | OPEN ALERTS
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
            created_at

        FROM telemetry_alerts

        $where

        AND status = 'Open'

        ORDER BY created_at DESC, alert_id DESC
    ");

    $stmt->execute($params);

    $openAlerts = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Dashboard alerts retrieved successfully.',
        [

            'summary' => [

                'total_alerts' =>
                    (int) $summary['total_alerts'],

                'open_alerts' =>
                    (int) $summary['open_alerts'],

                'acknowledged_alerts' =>
                    (int) $summary['acknowledged_alerts'],

                'resolved_alerts' =>
                    (int) $summary['resolved_alerts'],

                'temperature_alerts' =>
                    (int) $summary['temperature_alerts']
            ],

            'open_alerts' => $openAlerts,

            'recent_alerts' => $recentAlerts
        ],
        200
    );


} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to retrieve dashboard alerts.',
        null,
        500
    );
}