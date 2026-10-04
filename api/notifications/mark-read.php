<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';

requireRole(
    'Admin',
    'Dispatcher',
    'Driver',
    'Clinic Staff'
);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
| INPUT
|--------------------------------------------------------------------------
*/

$data = getJsonInput();

$notificationId = filter_var(
    $data['notification_id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($notificationId === false || $notificationId <= 0) {
    jsonResponse(
        false,
        'Invalid notification ID.',
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
    | VERIFY NOTIFICATION BELONGS TO CURRENT USER
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            notification_id,
            user_id,
            notification_type,
            message,
            is_read,
            created_at
        FROM notifications
        WHERE notification_id = :notification_id
          AND user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':notification_id' => $notificationId,
        ':user_id' => $userId
    ]);

    $notification = $stmt->fetch();

    if (!$notification) {
        jsonResponse(
            false,
            'Notification not found.',
            null,
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ALREADY READ
    |--------------------------------------------------------------------------
    */

    if ((int) $notification['is_read'] === 1) {
        jsonResponse(
            true,
            'Notification is already marked as read.',
            [
                'notification_id' => (int) $notification['notification_id'],
                'is_read' => 1
            ],
            200
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MARK AS READ
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE notification_id = :notification_id
          AND user_id = :user_id
    ");

    $stmt->execute([
        ':notification_id' => $notificationId,
        ':user_id' => $userId
    ]);


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Notification marked as read successfully.',
        [
            'notification_id' => (int) $notificationId,
            'is_read' => 1
        ],
        200
    );


} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to mark notification as read.',
        null,
        500
    );
}