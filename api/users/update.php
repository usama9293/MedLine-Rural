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
$allowedFields = ['full_name', 'email', 'role_id', 'clinic_id'];
$hasUpdate = count(array_intersect(array_keys($data), $allowedFields)) > 0;

if (!$hasUpdate) {
    jsonResponse(false, 'At least one supported user field is required.', null, 400);
}

try {
    $statement = $pdo->prepare('
        SELECT user_id, full_name, email, role_id, clinic_id
        FROM users
        WHERE user_id = :user_id
        LIMIT 1
    ');
    $statement->execute([':user_id' => $userId]);
    $currentUser = $statement->fetch();

    if (!$currentUser) {
        jsonResponse(false, 'User not found.', null, 404);
    }

    $name = array_key_exists('full_name', $data)
        ? userManagementName($data['full_name'])
        : $currentUser['full_name'];
    $email = array_key_exists('email', $data)
        ? userManagementEmail($data['email'])
        : $currentUser['email'];
    $roleId = array_key_exists('role_id', $data)
        ? userManagementPositiveId($data['role_id'], 'role ID')
        : (int) $currentUser['role_id'];
    $clinicId = $currentUser['clinic_id'] !== null ? (int) $currentUser['clinic_id'] : null;

    if (array_key_exists('clinic_id', $data)) {
        $clinicId = $data['clinic_id'] === null || $data['clinic_id'] === ''
            ? null
            : userManagementPositiveId($data['clinic_id'], 'clinic ID');
    }

    userManagementRoleAndClinic($pdo, $roleId, $clinicId);

    if ($userId === (int) getCurrentUserId() && $roleId !== (int) $currentUser['role_id']) {
        jsonResponse(false, 'You cannot change your own role.', null, 422);
    }

    if (userManagementDuplicateEmail($pdo, $email, $userId)) {
        jsonResponse(false, 'Email address is already in use.', null, 409);
    }

    $updates = [
        'full_name = :full_name',
        'email = :email',
        'role_id = :role_id',
        'clinic_id = :clinic_id'
    ];
    $parameters = [
        ':full_name' => $name,
        ':email' => $email,
        ':role_id' => $roleId,
        ':clinic_id' => $clinicId,
        ':user_id' => $userId
    ];

    $statement = $pdo->prepare('
        UPDATE users
        SET ' . implode(', ', $updates) . '
        WHERE user_id = :user_id
    ');
    $statement->execute($parameters);

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

    jsonResponse(true, 'User updated successfully.', $user, 200);
} catch (PDOException $e) {
    error_log($e->getMessage());

    if ($e->getCode() === '23000') {
        jsonResponse(false, 'Email address is already in use.', null, 409);
    }

    jsonResponse(false, 'Unable to update user.', null, 500);
}
