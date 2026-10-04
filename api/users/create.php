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
$name = userManagementName($data['full_name'] ?? null);
$email = userManagementEmail($data['email'] ?? null);
$password = userManagementPassword($data['password'] ?? null);
$roleId = userManagementPositiveId($data['role_id'] ?? null, 'role ID');
$clinicId = null;

if (array_key_exists('clinic_id', $data) && $data['clinic_id'] !== null && $data['clinic_id'] !== '') {
    $clinicId = userManagementPositiveId($data['clinic_id'], 'clinic ID');
}

try {
    userManagementRoleAndClinic($pdo, $roleId, $clinicId);

    if (userManagementDuplicateEmail($pdo, $email)) {
        jsonResponse(false, 'Email address is already in use.', null, 409);
    }

    $statement = $pdo->prepare('
        INSERT INTO users
        (
            role_id,
            clinic_id,
            full_name,
            email,
            password_hash,
            status
        )
        VALUES
        (
            :role_id,
            :clinic_id,
            :full_name,
            :email,
            :password_hash,
            \'Active\'
        )
    ');
    $statement->execute([
        ':role_id' => $roleId,
        ':clinic_id' => $clinicId,
        ':full_name' => $name,
        ':email' => $email,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT)
    ]);

    $userId = (int) $pdo->lastInsertId();
    $statement = $pdo->prepare('
        SELECT
            ' . userManagementSafeUserSelect() . '
        FROM users u
        INNER JOIN roles r ON r.role_id = u.role_id
        LEFT JOIN clinics c ON c.clinic_id = u.clinic_id
        WHERE u.user_id = :user_id
        LIMIT 1
    ');
    $statement->execute([':user_id' => $userId]);
    $user = $statement->fetch();
    $user['user_id'] = (int) $user['user_id'];
    $user['role_id'] = (int) $user['role_id'];
    $user['clinic_id'] = $user['clinic_id'] !== null ? (int) $user['clinic_id'] : null;

    jsonResponse(true, 'User created successfully.', $user, 201);
} catch (PDOException $e) {
    error_log($e->getMessage());

    if ($e->getCode() === '23000') {
        jsonResponse(false, 'Email address is already in use.', null, 409);
    }

    jsonResponse(false, 'Unable to create user.', null, 500);
}
