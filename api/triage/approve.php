<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';

requireRole('Dispatcher', 'Admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    | Get request ID
    |--------------------------------------------------------------------------
    */

    $requestId = $_GET['request_id'] ?? null;

    if (!$requestId || !ctype_digit((string)$requestId)) {

        jsonResponse(
            false,
            'A valid request_id is required.',
            null,
            400
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Find the request
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            request_id,
            status,
            urgency_score
        FROM supply_requests
        WHERE request_id = :request_id
        LIMIT 1
    ");

    $stmt->execute([
        ':request_id' => $requestId
    ]);

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
    | Request must already have a triage score
    |--------------------------------------------------------------------------
    */

    if ($request['urgency_score'] === null) {

        jsonResponse(
            false,
            'Request must be triaged before approval.',
            null,
            422
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Only Pending requests can be approved
    |--------------------------------------------------------------------------
    */

    if ($request['status'] !== 'Pending') {

        jsonResponse(
            false,
            'Only Pending requests can be approved.',
            null,
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Approve request
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE supply_requests

        SET status = 'Approved'

        WHERE request_id = :request_id
        AND status = 'Pending'
        AND urgency_score IS NOT NULL
    ");

    $stmt->execute([
        ':request_id' => $requestId
    ]);

    if ($stmt->rowCount() !== 1) {

        jsonResponse(
            false,
            'Request could not be approved.',
            null,
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Return result
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Supply request approved successfully.',
        [
            'request_id' => (int)$requestId,
            'status' => 'Approved',
            'urgency_score' => (float)$request['urgency_score']
        ]
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