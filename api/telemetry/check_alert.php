<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';

requireRole('Admin', 'Dispatcher');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(
        false,
        'Method Not Allowed',
        null,
        405
    );
}

$data = getJsonInput();

$eventId = filter_var(
    $data['event_id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($eventId === false || $eventId <= 0) {
    jsonResponse(
        false,
        'Invalid event ID.',
        null,
        400
    );
}

try {

    /*
    |--------------------------------------------------------------------------
    | GET TELEMETRY EVENT
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
        WHERE event_id = :event_id
        LIMIT 1
    ");

    $stmt->execute([
        ':event_id' => $eventId
    ]);

    $event = $stmt->fetch();

    if (!$event) {
        jsonResponse(
            false,
            'Telemetry event not found.',
            null,
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TEMPERATURE CHECK
    |--------------------------------------------------------------------------
    |
    | Example safe range:
    | Minimum = 2°C
    | Maximum = 8°C
    |
    */

    $minimumTemperature = 2.00;
    $maximumTemperature = 8.00;

    $alertCreated = false;
    $alertId = null;


    if ($event['temperature'] !== null) {

        $temperature = (float) $event['temperature'];

        if (
            $temperature < $minimumTemperature ||
            $temperature > $maximumTemperature
        ) {

            $alertType = 'Temperature';

            $alertMessage =
                "Temperature is outside the safe range of "
                . $minimumTemperature
                . "°C to "
                . $maximumTemperature
                . "°C.";

            /*
            |--------------------------------------------------------------------------
            | CHECK FOR EXISTING OPEN ALERT
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT alert_id
                FROM telemetry_alerts
                WHERE event_id = :event_id
                  AND alert_type = :alert_type
                  AND status = 'Open'
                LIMIT 1
            ");

            $stmt->execute([
                ':event_id' => $eventId,
                ':alert_type' => $alertType
            ]);

            $existingAlert = $stmt->fetch();


            /*
            |--------------------------------------------------------------------------
            | CREATE ALERT
            |--------------------------------------------------------------------------
            */

            if (!$existingAlert) {

                $stmt = $pdo->prepare("
                    INSERT INTO telemetry_alerts
                    (
                        event_id,
                        vehicle_id,
                        shipment_id,
                        alert_type,
                        alert_message,
                        alert_value,
                        threshold_value,
                        status
                    )
                    VALUES
                    (
                        :event_id,
                        :vehicle_id,
                        :shipment_id,
                        :alert_type,
                        :alert_message,
                        :alert_value,
                        :threshold_value,
                        'Open'
                    )
                ");

                $stmt->execute([
                    ':event_id' => $event['event_id'],
                    ':vehicle_id' => $event['vehicle_id'],
                    ':shipment_id' => $event['shipment_id'],
                    ':alert_type' => $alertType,
                    ':alert_message' => $alertMessage,
                    ':alert_value' => $temperature,
                    ':threshold_value' => $maximumTemperature
                ]);

                $alertId = (int) $pdo->lastInsertId();

                $alertCreated = true;

            } else {

                $alertId = (int) $existingAlert['alert_id'];
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    if ($alertCreated) {

        jsonResponse(
            true,
            'Telemetry alert created successfully.',
            [
                'alert_id' => $alertId,
                'event_id' => (int) $event['event_id'],
                'vehicle_id' => (int) $event['vehicle_id'],
                'shipment_id' => $event['shipment_id']
                    !== null
                    ? (int) $event['shipment_id']
                    : null,
                'alert_type' => 'Temperature',
                'status' => 'Open'
            ],
            201
        );

    } else {

        jsonResponse(
            true,
            'Telemetry checked. No new alert required.',
            [
                'event_id' => (int) $event['event_id'],
                'alert_created' => false
            ],
            200
        );
    }


} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to process telemetry alert.',
        null,
        500
    );
} 