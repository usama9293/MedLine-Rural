<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/user_management.php';

requireRole('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method Not Allowed', null, 405);
}

$userId = userManagementPositiveId($_GET['user_id'] ?? null, 'user ID');

try {
    $statement = $pdo->prepare('
        SELECT
            ' . userManagementSafeUserSelect() . '
        FROM users u
        INNER JOIN roles r
            ON r.role_id = u.role_id
        LEFT JOIN clinics c
            ON c.clinic_id = u.clinic_id
        WHERE u.user_id = :user_id
        LIMIT 1
    ');
    $statement->execute([':user_id' => $userId]);
    $user = $statement->fetch();

    if (!$user) {
        jsonResponse(false, 'User not found.', null, 404);
    }

    $user['user_id'] = (int) $user['user_id'];
    $user['role_id'] = (int) $user['role_id'];
    $user['clinic_id'] = $user['clinic_id'] !== null ? (int) $user['clinic_id'] : null;

    jsonResponse(true, 'User retrieved successfully.', $user, 200);
} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Unable to retrieve user.', null, 500);
}
