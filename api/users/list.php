<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/user_management.php';

requireRole('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method Not Allowed', null, 405);
}

try {
    $statement = $pdo->query('
        SELECT
            ' . userManagementSafeUserSelect() . '
        FROM users u
        INNER JOIN roles r
            ON r.role_id = u.role_id
        LEFT JOIN clinics c
            ON c.clinic_id = u.clinic_id
        ORDER BY u.full_name ASC, u.user_id ASC
    ');

    $users = $statement->fetchAll();

    foreach ($users as &$user) {
        $user['user_id'] = (int) $user['user_id'];
        $user['role_id'] = (int) $user['role_id'];
        $user['clinic_id'] = $user['clinic_id'] !== null ? (int) $user['clinic_id'] : null;
    }
    unset($user);

    jsonResponse(true, 'Users retrieved successfully.', $users, 200);
} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Unable to retrieve users.', null, 500);
}
