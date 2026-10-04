<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';

requireRole('Driver', 'Dispatcher', 'Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(
        false,
        'Method Not Allowed',
        null,
        405
    );
}

$data = getJsonInput();


/*
|--------------------------------------------------------------------------
| CURRENT USER / ROLE
|--------------------------------------------------------------------------
*/

$currentUserId = $_SESSION['user_id'] ?? null;
$currentRole = $_SESSION['role_name'] ?? null;

if (!$currentUserId) {
    jsonResponse(
        false,
        'Unauthorised.',
        null,
        401
    );
}


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$vehicleId = filter_var(
    $data['vehicle_id'] ?? null,
    FILTER_VALIDATE_INT
);

$shipmentId = $data['shipment_id'] ?? null;

if ($shipmentId !== null && $shipmentId !== '') {

    $shipmentId = filter_var(
        $shipmentId,
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

} else {

    $shipmentId = null;
}

$eventType = trim(
    (string) ($data['event_type'] ?? 'Telemetry')
);

$temperature = $data['temperature'] ?? null;
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;

$recordedAt = trim(
    (string) ($data['recorded_at'] ?? '')
);


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if ($vehicleId === false || $vehicleId <= 0) {

    jsonResponse(
        false,
        'Invalid vehicle ID.',
        null,
        400
    );
}

if ($eventType === '') {
    $eventType = 'Telemetry';
}

if (strlen($eventType) > 50) {

    jsonResponse(
        false,
        'Event type is too long.',
        null,
        400
    );
}


/*
|--------------------------------------------------------------------------
| TEMPERATURE VALIDATION
|--------------------------------------------------------------------------
*/

if ($temperature !== null && $temperature !== '') {

    if (!is_numeric($temperature)) {

        jsonResponse(
            false,
            'Temperature must be numeric.',
            null,
            400
        );
    }

    $temperature = (float) $temperature;

    if ($temperature < -100 || $temperature > 100) {

        jsonResponse(
            false,
            'Invalid temperature value.',
            null,
            400
        );
    }

} else {

    $temperature = null;
}


/*
|--------------------------------------------------------------------------
| GPS VALIDATION
|--------------------------------------------------------------------------
*/

if ($latitude !== null && $latitude !== '') {

    if (!is_numeric($latitude)) {

        jsonResponse(
            false,
            'Latitude must be numeric.',
            null,
            400
        );
    }

    $latitude = (float) $latitude;

    if ($latitude < -90 || $latitude > 90) {

        jsonResponse(
            false,
            'Invalid latitude.',
            null,
            400
        );
    }

} else {

    $latitude = null;
}


if ($longitude !== null && $longitude !== '') {

    if (!is_numeric($longitude)) {

        jsonResponse(
            false,
            'Longitude must be numeric.',
            null,
            400
        );
    }

    $longitude = (float) $longitude;

    if ($longitude < -180 || $longitude > 180) {

        jsonResponse(
            false,
            'Invalid longitude.',
            null,
            400
        );
    }

} else {

    $longitude = null;
}


/*
|--------------------------------------------------------------------------
| RECORDED TIME
|--------------------------------------------------------------------------
*/

if ($recordedAt === '') {

    $recordedAt = date('Y-m-d H:i:s');

} else {

    $timestamp = strtotime($recordedAt);

    if ($timestamp === false) {

        jsonResponse(
            false,
            'Invalid recorded-at date.',
            null,
            400
        );
    }

    $recordedAt = date(
        'Y-m-d H:i:s',
        $timestamp
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | CHECK VEHICLE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            vehicle_id,
            status
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

    /*
     * Drivers can only submit telemetry for their assigned shipment.
     *
     * Admin and Dispatcher are allowed to submit telemetry for
     * operational/testing purposes.
     */
    if ($currentRole === 'Driver') {

        /*
        |--------------------------------------------------------------------------
        | SHIPMENT REQUIRED FOR DRIVER
        |--------------------------------------------------------------------------
        */

        if ($shipmentId === null) {

            jsonResponse(
                false,
                'Shipment ID is required for drivers.',
                null,
                400
            );
        }


        /*
        |--------------------------------------------------------------------------
        | VERIFY DRIVER ASSIGNMENT
        |--------------------------------------------------------------------------
        |
        | Current logged-in user
        |       ↓
        | drivers.user_id
        |       ↓
        | driver_id
        |       ↓
        | shipments.driver_id
        |
        */

        $stmt = $pdo->prepare("
            SELECT
                s.shipment_id,
                s.vehicle_id,
                s.driver_id,
                s.status
            FROM shipments s
            INNER JOIN drivers d
                ON d.driver_id = s.driver_id
            WHERE s.shipment_id = :shipment_id
              AND d.user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':shipment_id' => $shipmentId,
            ':user_id' => $currentUserId
        ]);

        $assignedShipment = $stmt->fetch();

        if (!$assignedShipment) {

            jsonResponse(
                false,
                'You are not assigned to this shipment.',
                null,
                403
            );
        }


        /*
        |--------------------------------------------------------------------------
        | VERIFY VEHICLE ASSIGNMENT
        |--------------------------------------------------------------------------
        */

        if (
            $assignedShipment['vehicle_id'] === null ||
            (int) $assignedShipment['vehicle_id']
            !==
            (int) $vehicleId
        ) {

            jsonResponse(
                false,
                'This vehicle is not assigned to your shipment.',
                null,
                403
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK SHIPMENT
    |--------------------------------------------------------------------------
    */

    if ($shipmentId !== null) {

        $stmt = $pdo->prepare("
            SELECT
                shipment_id,
                vehicle_id,
                driver_id,
                status
            FROM shipments
            WHERE shipment_id = :shipment_id
            LIMIT 1
        ");

        $stmt->execute([
            ':shipment_id' => $shipmentId
        ]);

        $shipment = $stmt->fetch();

        if (!$shipment) {

            jsonResponse(
                false,
                'Shipment not found.',
                null,
                404
            );
        }


        /*
        |--------------------------------------------------------------------------
        | VEHICLE MUST MATCH SHIPMENT
        |--------------------------------------------------------------------------
        */

        if (
            $shipment['vehicle_id'] === null ||
            (int) $shipment['vehicle_id']
            !==
            (int) $vehicleId
        ) {

            jsonResponse(
                false,
                'Shipment is not assigned to this vehicle.',
                null,
                400
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | INSERT TELEMETRY EVENT
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO telemetry_events
        (
            vehicle_id,
            shipment_id,
            event_type,
            temperature,
            latitude,
            longitude,
            recorded_at
        )
        VALUES
        (
            :vehicle_id,
            :shipment_id,
            :event_type,
            :temperature,
            :latitude,
            :longitude,
            :recorded_at
        )
    ");

    $stmt->execute([
        ':vehicle_id' => $vehicleId,
        ':shipment_id' => $shipmentId,
        ':event_type' => $eventType,
        ':temperature' => $temperature,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':recorded_at' => $recordedAt
    ]);

    $eventId = (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | ALERT VARIABLES
    |--------------------------------------------------------------------------
    */

    $alertCreated = false;
    $alertId = null;
    $notificationsCreated = 0;


    /*
    |--------------------------------------------------------------------------
    | TEMPERATURE ALERT CHECK
    |--------------------------------------------------------------------------
    */

    $minimumTemperature = 2.00;
    $maximumTemperature = 8.00;

    if ($temperature !== null) {

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
            | CHECK EXISTING OPEN ALERT
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
                    ':event_id' => $eventId,
                    ':vehicle_id' => $vehicleId,
                    ':shipment_id' => $shipmentId,
                    ':alert_type' => $alertType,
                    ':alert_message' => $alertMessage,
                    ':alert_value' => $temperature,
                    ':threshold_value' => $maximumTemperature
                ]);

                $alertId = (int) $pdo->lastInsertId();

                $alertCreated = true;


                /*
                |--------------------------------------------------------------------------
                | CREATE NOTIFICATIONS
                |--------------------------------------------------------------------------
                */

                $notificationType = 'Telemetry Alert';

                $notificationMessage =
                    "Cold-chain temperature alert: "
                    . $temperature
                    . "°C detected for vehicle "
                    . $vehicleId;

                if ($shipmentId !== null) {

                    $notificationMessage .=
                        " and shipment "
                        . $shipmentId;
                }

                $notificationMessage .=
                    ". Safe range is "
                    . $minimumTemperature
                    . "°C to "
                    . $maximumTemperature
                    . "°C.";


                /*
                |--------------------------------------------------------------------------
                | GET ADMIN + DISPATCHER USERS
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->query("
                    SELECT
                        u.user_id
                    FROM users u
                    INNER JOIN roles r
                        ON r.role_id = u.role_id
                    WHERE r.role_name IN
                        ('Admin', 'Dispatcher')
                      AND u.status = 'Active'
                ");

                $users = $stmt->fetchAll();


                /*
                |--------------------------------------------------------------------------
                | INSERT NOTIFICATIONS
                |--------------------------------------------------------------------------
                */

                if ($users) {

                    $notificationStmt = $pdo->prepare("
                        INSERT INTO notifications
                        (
                            user_id,
                            notification_type,
                            message
                        )
                        VALUES
                        (
                            :user_id,
                            :notification_type,
                            :message
                        )
                    ");

                    foreach ($users as $user) {

                        $notificationStmt->execute([
                            ':user_id' =>
                                (int) $user['user_id'],

                            ':notification_type' =>
                                $notificationType,

                            ':message' =>
                                $notificationMessage
                        ]);

                        $notificationsCreated++;
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT TRANSACTION
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Telemetry event recorded successfully.',
        [
            'event_id' => $eventId,
            'vehicle_id' => $vehicleId,
            'shipment_id' => $shipmentId,
            'event_type' => $eventType,
            'temperature' => $temperature,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'recorded_at' => $recordedAt,
            'alert_created' => $alertCreated,
            'alert_id' => $alertId,
            'notifications_created' => $notificationsCreated
        ],
        201
    );


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to record telemetry event.',
        null,
        500
    );
}