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

$alertId = filter_var(
    $data['alert_id'] ?? null,
    FILTER_VALIDATE_INT
);

$newStatus = trim(
    (string) ($data['status'] ?? '')
);


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if ($alertId === false || $alertId <= 0) {
    jsonResponse(
        false,
        'Invalid alert ID.',
        null,
        400
    );
}

$allowedStatuses = [
    'Acknowledged',
    'Resolved'
];

if (!in_array($newStatus, $allowedStatuses, true)) {
    jsonResponse(
        false,
        'Invalid alert status.',
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

    /*
    |--------------------------------------------------------------------------
    | START TRANSACTION
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
     * Get existing alert with row lock
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
        WHERE alert_id = :alert_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':alert_id' => $alertId
    ]);

    $alert = $stmt->fetch();

    if (!$alert) {
        $pdo->rollBack();

        jsonResponse(
            false,
            'Telemetry alert not found.',
            null,
            404
        );
    }


    /*
     * Current status
     */

    $currentStatus = $alert['status'];


    /*
     * Allowed status transitions
     */

    $validTransitions = [
        'Open' => ['Acknowledged'],
        'Acknowledged' => ['Resolved'],
        'Resolved' => []
    ];


    if (
        !in_array(
            $newStatus,
            $validTransitions[$currentStatus] ?? [],
            true
        )
    ) {
        jsonResponse(
            false,
            "Invalid status transition from $currentStatus to $newStatus.",
            null,
            400
        );
    }


    /*
     * Resolve alert
     */

    if ($newStatus === 'Resolved') {

        $stmt = $pdo->prepare("
            UPDATE telemetry_alerts
            SET
                status = 'Resolved',
                resolved_at = CURRENT_TIMESTAMP
            WHERE alert_id = :alert_id
        ");

        $stmt->execute([
            ':alert_id' => $alertId
        ]);

    } else {

        /*
         * Acknowledge alert
         */

        $stmt = $pdo->prepare("
            UPDATE telemetry_alerts
            SET
                status = 'Acknowledged'
            WHERE alert_id = :alert_id
        ");

        $stmt->execute([
            ':alert_id' => $alertId
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT TRANSACTION
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
     * Response
     */

    jsonResponse(
        true,
        'Telemetry alert status updated successfully.',
        [
            'alert_id' => $alertId,
            'status' => $newStatus
        ],
        200
    );


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to update telemetry alert.',
        null,
        500
    );
}