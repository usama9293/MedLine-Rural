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


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$userId = filter_var(
    $data['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

$notificationType = trim(
    (string) ($data['notification_type'] ?? '')
);

$message = trim(
    (string) ($data['message'] ?? '')
);


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if ($userId === false || $userId <= 0) {
    jsonResponse(
        false,
        'Invalid user ID.',
        null,
        400
    );
}

if ($notificationType === '') {
    jsonResponse(
        false,
        'Notification type is required.',
        null,
        400
    );
}

if (strlen($notificationType) > 50) {
    jsonResponse(
        false,
        'Notification type must not exceed 50 characters.',
        null,
        400
    );
}

if ($message === '') {
    jsonResponse(
        false,
        'Notification message is required.',
        null,
        400
    );
}


/*
|--------------------------------------------------------------------------
| CREATE NOTIFICATION
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | VERIFY USER
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT user_id
        FROM users
        WHERE user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(
            false,
            'User not found.',
            null,
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | INSERT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
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

    $stmt->execute([
        ':user_id' => $userId,
        ':notification_type' => $notificationType,
        ':message' => $message
    ]);

    $notificationId = (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | GET CREATED NOTIFICATION
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
        LIMIT 1
    ");

    $stmt->execute([
        ':notification_id' => $notificationId
    ]);

    $notification = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Notification created successfully.',
        [
            'notification_id' =>
                (int) $notification['notification_id'],

            'user_id' =>
                (int) $notification['user_id'],

            'notification_type' =>
                $notification['notification_type'],

            'message' =>
                $notification['message'],

            'is_read' =>
                (int) $notification['is_read'],

            'created_at' =>
                $notification['created_at']
        ],
        201
    );


} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to create notification.',
        null,
        500
    );
}