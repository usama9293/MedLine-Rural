<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';


/*
|--------------------------------------------------------------------------
| AUTHORIZATION
|--------------------------------------------------------------------------
*/

requireRole(
    'Admin',
    'Dispatcher'
);


/*
|--------------------------------------------------------------------------
| METHOD
|--------------------------------------------------------------------------
*/

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
| INPUT
|--------------------------------------------------------------------------
*/

$vehicleId = null;
$shipmentId = null;

if (isset($_GET['vehicle_id'])) {
    $vehicleId = filter_var(
        $_GET['vehicle_id'],
        FILTER_VALIDATE_INT
    );
}

if (isset($_GET['shipment_id'])) {
    $shipmentId = filter_var(
        $_GET['shipment_id'],
        FILTER_VALIDATE_INT
    );
}

/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $vehicleId !== null &&
    ($vehicleId === false || $vehicleId <= 0)
) {
    jsonResponse(
        false,
        'Invalid vehicle ID.',
        null,
        400
    );
}

if (
    $shipmentId !== null &&
    ($shipmentId === false || $shipmentId <= 0)
) {
    jsonResponse(
        false,
        'Invalid shipment ID.',
        null,
        400
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

try {

    $sql = "
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
        WHERE 1 = 1
    ";

    $params = [];


    /*
     * Filter by vehicle
     */

    if ($vehicleId !== null) {

        $sql .= "
            AND vehicle_id = :vehicle_id
        ";

        $params[':vehicle_id'] = $vehicleId;
    }


    /*
     * Filter by shipment
     */

    if ($shipmentId !== null) {

        $sql .= "
            AND shipment_id = :shipment_id
        ";

        $params[':shipment_id'] = $shipmentId;
    }


    /*
     * Latest events first
     */

    $sql .= "
        ORDER BY recorded_at DESC, event_id DESC
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $telemetry = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Telemetry retrieved successfully.',
        $telemetry,
        200
    );


} catch (PDOException $e) {

    /*
     * Log technical error
     * Do not expose database details
     */

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to retrieve telemetry.',
        null,
        500
    );
}