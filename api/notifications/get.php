<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';

requireRole(
    'Admin',
    'Dispatcher',
    'Driver',
    'Clinic Staff'
);

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
| CURRENT USER
|--------------------------------------------------------------------------
*/

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    jsonResponse(
        false,
        'Unauthorised.',
        null,
        401
    );
}


/*
|--------------------------------------------------------------------------
| OPTIONAL FILTER
|--------------------------------------------------------------------------
|
| Example:
|
| GET /notifications/get.php
|
| GET /notifications/get.php?unread_only=1
|
*/

$unreadOnly = false;

if (isset($_GET['unread_only'])) {

    $unreadOnlyValue = filter_var(
        $_GET['unread_only'],
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    );

    if ($unreadOnlyValue === null) {
        jsonResponse(
            false,
            'Invalid unread_only value.',
            null,
            400
        );
    }

    $unreadOnly = $unreadOnlyValue;
}


/*
|--------------------------------------------------------------------------
| GET NOTIFICATIONS
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT
            notification_id,
            user_id,
            notification_type,
            message,
            is_read,
            created_at
        FROM notifications
        WHERE user_id = :user_id
    ";

    $params = [
        ':user_id' => $userId
    ];


    /*
    |--------------------------------------------------------------------------
    | UNREAD FILTER
    |--------------------------------------------------------------------------
    */

    if ($unreadOnly) {

        $sql .= "
            AND is_read = 0
        ";
    }


    /*
    |--------------------------------------------------------------------------
    | LATEST FIRST
    |--------------------------------------------------------------------------
    */

    $sql .= "
        ORDER BY created_at DESC, notification_id DESC
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $notifications = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Notifications retrieved successfully.',
        $notifications,
        200
    );


} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to retrieve notifications.',
        null,
        500
    );
}