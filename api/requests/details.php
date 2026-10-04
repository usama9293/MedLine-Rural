<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';

requireRole('Clinic Staff', 'Dispatcher', 'Admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(
        false,
        'Method Not Allowed',
        null,
        405
    );
}

$requestId = filter_input(
    INPUT_GET,
    'request_id',
    FILTER_VALIDATE_INT
);

if ($requestId === false || $requestId === null || $requestId <= 0) {
    jsonResponse(
        false,
        'Invalid request ID.',
        null,
        400
    );
}

try {

    $role = $_SESSION['role_name'];

    /*
    |--------------------------------------------------------------------------
    | Get request
    |--------------------------------------------------------------------------
    */

    if ($role === 'Clinic Staff') {

        $clinicId = $_SESSION['clinic_id'] ?? null;

        if ($clinicId === null) {
            jsonResponse(
                false,
                'No clinic is associated with this account.',
                null,
                403
            );
        }

        $stmt = $pdo->prepare("
            SELECT
                sr.request_id,
                sr.clinic_id,
                c.clinic_name,
                sr.requested_by,
                sr.status,
                sr.urgency_score,
                sr.requested_at,
                sr.required_by,
                sr.notes
            FROM supply_requests sr

            INNER JOIN clinics c
                ON sr.clinic_id = c.clinic_id

            WHERE sr.request_id = :request_id
              AND sr.clinic_id = :clinic_id

            LIMIT 1
        ");

        $stmt->execute([
            ':request_id' => $requestId,
            ':clinic_id' => $clinicId
        ]);

    } else {

        /*
         * Dispatcher and Admin can access requests
         * from all clinics.
         */

        $stmt = $pdo->prepare("
            SELECT
                sr.request_id,
                sr.clinic_id,
                c.clinic_name,
                sr.requested_by,
                sr.status,
                sr.urgency_score,
                sr.requested_at,
                sr.required_by,
                sr.notes
            FROM supply_requests sr

            INNER JOIN clinics c
                ON sr.clinic_id = c.clinic_id

            WHERE sr.request_id = :request_id

            LIMIT 1
        ");

        $stmt->execute([
            ':request_id' => $requestId
        ]);
    }

    $request = $stmt->fetch();

    if (!$request) {
        jsonResponse(
            false,
            'Supply request not found.',
            null,
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get requested items
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            ri.request_item_id,
            ri.item_id,
            i.item_name,
            i.category,
            i.criticality_score,
            i.cold_chain_required,
            i.minimum_temperature,
            i.maximum_temperature,
            ri.quantity_requested
        FROM request_items ri

        INNER JOIN items i
            ON ri.item_id = i.item_id

        WHERE ri.request_id = :request_id

        ORDER BY ri.request_item_id ASC
    ");

    $stmt->execute([
        ':request_id' => $requestId
    ]);

    $items = $stmt->fetchAll();

    /*
    |--------------------------------------------------------------------------
    | Return request + items
    |--------------------------------------------------------------------------
    */

    $request['items'] = $items;

    jsonResponse(
        true,
        'Supply request details retrieved successfully.',
        $request
    );

} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Internal server error.',
        null,
        500
    );
}