<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';
require_once __DIR__ . '/../../config/user_management.php';

requireRole('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method Not Allowed', null, 405);
}

$data = getJsonInput();
$userId = userManagementPositiveId($data['user_id'] ?? null, 'user ID');
$password = userManagementPassword($data['new_password'] ?? null);

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare('
        SELECT user_id
        FROM users
        WHERE user_id = :user_id
        LIMIT 1
        FOR UPDATE
    ');
    $statement->execute([':user_id' => $userId]);

    if (!$statement->fetch()) {
        $pdo->rollBack();
        jsonResponse(false, 'User not found.', null, 404);
    }

    $statement = $pdo->prepare('
        UPDATE users
        SET password_hash = :password_hash
        WHERE user_id = :user_id
    ');
    $statement->execute([
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':user_id' => $userId
    ]);
    $pdo->commit();

    jsonResponse(true, 'User password reset successfully.', [
        'user_id' => $userId
    ], 200);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());
    jsonResponse(false, 'Unable to reset user password.', null, 500);
}
