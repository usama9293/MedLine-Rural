<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';

requireRole('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method Not Allowed', null, 405);
}

try {
    $stmt = $pdo->prepare('
        SELECT
            u.user_id,
            u.full_name,
            u.email
        FROM users u
        INNER JOIN roles r
            ON r.role_id = u.role_id
        LEFT JOIN drivers d
            ON d.user_id = u.user_id
        WHERE r.role_name = :role_name
          AND d.user_id IS NULL
        ORDER BY u.full_name ASC, u.user_id ASC
    ');

    $stmt->execute([
        ':role_name' => 'Driver'
    ]);

    $users = $stmt->fetchAll();

    foreach ($users as &$user) {
        $user['user_id'] = (int) $user['user_id'];
    }
    unset($user);

    jsonResponse(true, 'Driver users retrieved successfully.', $users, 200);

} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Unable to retrieve available driver users.', null, 500);
}
