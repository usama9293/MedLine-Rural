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
| OPTIONAL FILTERS
|--------------------------------------------------------------------------
*/

$vehicleId = null;
$shipmentId = null;

if (isset($_GET['vehicle_id'])) {

    $vehicleId = filter_var(
        $_GET['vehicle_id'],
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
}

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

    if ($vehicleId !== null) {

        $where .= "
            AND vehicle_id = :vehicle_id
        ";

        $params[':vehicle_id'] = $vehicleId;
    }

    if ($shipmentId !== null) {

        $where .= "
            AND shipment_id = :shipment_id
        ";

        $params[':shipment_id'] = $shipmentId;
    }


    /*
    |--------------------------------------------------------------------------
    | TELEMETRY SUMMARY
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_events,

            AVG(temperature) AS average_temperature,

            MIN(temperature) AS minimum_temperature,

            MAX(temperature) AS maximum_temperature,

            SUM(
                temperature < 2
                OR temperature > 8
            ) AS temperature_violations

        FROM telemetry_events

        $where
    ");

    $stmt->execute($params);

    $summary = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | LATEST TELEMETRY EVENT
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

        $where

        ORDER BY recorded_at DESC, event_id DESC

        LIMIT 1
    ");

    $stmt->execute($params);

    $latestEvent = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | RECENT TELEMETRY
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

        $where

        ORDER BY recorded_at DESC, event_id DESC

        LIMIT 10
    ");

    $stmt->execute($params);

    $recentEvents = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Dashboard telemetry retrieved successfully.',
        [

            'summary' => [
                'total_events' =>
                    (int) $summary['total_events'],

                'average_temperature' =>
                    $summary['average_temperature'] !== null
                        ? round(
                            (float) $summary['average_temperature'],
                            2
                        )
                        : null,

                'minimum_temperature' =>
                    $summary['minimum_temperature'] !== null
                        ? (float) $summary['minimum_temperature']
                        : null,

                'maximum_temperature' =>
                    $summary['maximum_temperature'] !== null
                        ? (float) $summary['maximum_temperature']
                        : null,

                'temperature_violations' =>
                    (int) $summary['temperature_violations']
            ],

            'latest_event' => $latestEvent
                ? [
                    'event_id' =>
                        (int) $latestEvent['event_id'],

                    'vehicle_id' =>
                        (int) $latestEvent['vehicle_id'],

                    'shipment_id' =>
                        $latestEvent['shipment_id'] !== null
                            ? (int) $latestEvent['shipment_id']
                            : null,

                    'event_type' =>
                        $latestEvent['event_type'],

                    'temperature' =>
                        $latestEvent['temperature'] !== null
                            ? (float) $latestEvent['temperature']
                            : null,

                    'latitude' =>
                        $latestEvent['latitude'] !== null
                            ? (float) $latestEvent['latitude']
                            : null,

                    'longitude' =>
                        $latestEvent['longitude'] !== null
                            ? (float) $latestEvent['longitude']
                            : null,

                    'recorded_at' =>
                        $latestEvent['recorded_at']
                ]
                : null,

            'recent_events' => $recentEvents
        ],
        200
    );


} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to retrieve dashboard telemetry.',
        null,
        500
    );
}