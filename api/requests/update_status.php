<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';

requireRole('Dispatcher', 'Driver', 'Admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(
        false,
        'Method Not Allowed',
        null,
        405
    );
}

$data = getJsonInput();

$requestId = filter_var(
    $data['request_id'] ?? null,
    FILTER_VALIDATE_INT
);

$newStatus = trim(
    $data['status'] ?? ''
);

if ($requestId === false || $requestId <= 0) {
    jsonResponse(
        false,
        'Invalid request ID.',
        null,
        400
    );
}

if ($newStatus === '') {
    jsonResponse(
        false,
        'Status is required.',
        null,
        400
    );
}


/*
|--------------------------------------------------------------------------
| Allowed statuses
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'Pending',
    'Approved',
    'Completed',
    'Cancelled'
];

if (!in_array($newStatus, $allowedStatuses, true)) {
    jsonResponse(
        false,
        'Invalid request status.',
        null,
        400
    );
}


/*
|--------------------------------------------------------------------------
| Get current request
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
    |--------------------------------------------------------------------------
    | GET REQUEST WITH ROW LOCK
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            request_id,
            clinic_id,
            status
        FROM supply_requests
        WHERE request_id = :request_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':request_id' => $requestId
    ]);

    $request = $stmt->fetch();

    if (!$request) {
        $pdo->rollBack();

        jsonResponse(
            false,
            'Supply request not found.',
            null,
            404
        );
    }

    $currentStatus = $request['status'];
    $role = $_SESSION['role_name'];


/*
|--------------------------------------------------------------------------
| Define allowed transitions
|--------------------------------------------------------------------------
*/

    $allowedTransitions = [

        'Pending' => [
            'Approved',
            'Cancelled'
        ],

        'Approved' => [
            'Completed',
            'Cancelled'
        ],

        'Completed' => [],

        'Cancelled' => []
    ];


/*
|--------------------------------------------------------------------------
| Check transition
|--------------------------------------------------------------------------
*/

    if (
        !isset($allowedTransitions[$currentStatus]) ||
        !in_array(
            $newStatus,
            $allowedTransitions[$currentStatus],
            true
        )
    ) {

        $pdo->rollBack();

        jsonResponse(
            false,
            "Cannot change status from {$currentStatus} to {$newStatus}.",
            null,
            400
        );
    }


/*
|--------------------------------------------------------------------------
| Role-specific authorization
|--------------------------------------------------------------------------
*/

    if ($role === 'Driver') {

        $driverAllowed = [
            'Assigned' => ['Dispatched'],
            'Dispatched' => ['In Transit'],
            'In Transit' => ['Delivered']
        ];

        if (
            !isset($driverAllowed[$currentStatus]) ||
            !in_array(
                $newStatus,
                $driverAllowed[$currentStatus],
                true
            )
        ) {

            $pdo->rollBack();

            jsonResponse(
                false,
                'Driver is not authorised for this status change.',
                null,
                403
            );
        }
    }


/*
|--------------------------------------------------------------------------
| Update request
|--------------------------------------------------------------------------
*/

    $stmt = $pdo->prepare("
        UPDATE supply_requests
        SET status = :status
        WHERE request_id = :request_id
    ");

    $stmt->execute([
        ':status' => $newStatus,
        ':request_id' => $requestId
    ]);


/*
|--------------------------------------------------------------------------
| COMMIT TRANSACTION
|--------------------------------------------------------------------------
*/

    $pdo->commit();


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

    jsonResponse(
        true,
        'Supply request status updated successfully.',
        [
            'request_id' => $requestId,
            'previous_status' => $currentStatus,
            'new_status' => $newStatus
        ]
    );

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Internal server error.',
        null,
        500
    );
}