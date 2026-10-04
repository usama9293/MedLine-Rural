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
$status = $data['status'] ?? null;

if (!is_string($status) || !in_array($status, ['Active', 'Inactive'], true)) {
    jsonResponse(false, 'Invalid user status.', null, 422);
}

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare('
        SELECT user_id, status
        FROM users
        WHERE user_id = :user_id
        LIMIT 1
        FOR UPDATE
    ');
    $statement->execute([':user_id' => $userId]);
    $user = $statement->fetch();

    if (!$user) {
        $pdo->rollBack();
        jsonResponse(false, 'User not found.', null, 404);
    }

    if ($userId === (int) getCurrentUserId() && $status === 'Inactive') {
        $pdo->rollBack();
        jsonResponse(false, 'You cannot deactivate your own account.', null, 422);
    }

    if ($user['status'] === $status) {
        $pdo->rollBack();
        jsonResponse(false, 'User already has that status.', null, 409);
    }

    $statement = $pdo->prepare('
        UPDATE users
        SET status = :status
        WHERE user_id = :user_id
    ');
    $statement->execute([
        ':status' => $status,
        ':user_id' => $userId
    ]);
    $pdo->commit();

    jsonResponse(true, 'User status updated successfully.', [
        'user_id' => $userId,
        'previous_status' => $user['status'],
        'status' => $status
    ], 200);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());
    jsonResponse(false, 'Unable to update user status.', null, 500);
}
