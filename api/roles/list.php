<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';

requireRole('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method Not Allowed', null, 405);
}

try {
    $statement = $pdo->query('
        SELECT role_id, role_name
        FROM roles
        ORDER BY role_name ASC, role_id ASC
    ');
    $roles = $statement->fetchAll();

    foreach ($roles as &$role) {
        $role['role_id'] = (int) $role['role_id'];
    }
    unset($role);

    jsonResponse(true, 'Roles retrieved successfully.', $roles, 200);
} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Unable to retrieve roles.', null, 500);
}
