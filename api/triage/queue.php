<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';

requireRole('Dispatcher', 'Admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(
        false,
        'Method Not Allowed',
        null,
        405
    );
}

try {

    /*
    |--------------------------------------------------------------------------
    | Get all requests with calculated urgency scores
    |--------------------------------------------------------------------------
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
            sr.notes,

            ri.item_id,
            ri.quantity_requested,

            i.item_name,
            i.category,
            i.criticality_score,
            i.cold_chain_required

        FROM supply_requests sr

        INNER JOIN clinics c
            ON sr.clinic_id = c.clinic_id

        INNER JOIN request_items ri
            ON sr.request_id = ri.request_id

        INNER JOIN items i
            ON ri.item_id = i.item_id

        WHERE sr.status NOT IN (
    'Completed',
    'Cancelled'
)

ORDER BY
    CASE
        WHEN sr.urgency_score IS NULL THEN 1
        ELSE 0
    END ASC,

    sr.urgency_score DESC,

    sr.requested_at ASC
    ");

    $stmt->execute();

    $requests = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Add priority label
    |--------------------------------------------------------------------------
    */

    foreach ($requests as &$request) {

    if ($request['urgency_score'] === null) {

        $request['priority'] = 'Needs Triage';

    } else {

        $score = (float)$request['urgency_score'];

        if ($score >= 80) {

            $request['priority'] = 'Critical';

        } elseif ($score >= 60) {

            $request['priority'] = 'High';

        } elseif ($score >= 40) {

            $request['priority'] = 'Medium';

        } else {

            $request['priority'] = 'Low';
        }
    }
}

unset($request);
    


    /*
    |--------------------------------------------------------------------------
    | Return triage queue
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Triage queue retrieved successfully.',
        $requests
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