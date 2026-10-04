<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';

requireRole('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method Not Allowed', null, 405);
}

$data = getJsonInput();

$userId = filter_var(
    $data['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

$licenseNumber = trim((string) ($data['license_number'] ?? ''));
$status = trim((string) ($data['status'] ?? 'Available'));

$allowedStatuses = [
    'Available',
    'On Trip',
    'Inactive'
];

if ($userId === false || $userId <= 0) {
    jsonResponse(false, 'Invalid driver user selection.', null, 400);
}

if ($licenseNumber === '') {
    jsonResponse(false, 'License number is required.', null, 400);
}

if (mb_strlen($licenseNumber) > 100) {
    jsonResponse(false, 'License number must not exceed 100 characters.', null, 400);
}

if (!in_array($status, $allowedStatuses, true)) {
    jsonResponse(false, 'Invalid driver status.', ['allowed_statuses' => $allowedStatuses], 400);
}

try {
    $userStmt = $pdo->prepare('
        SELECT
            u.user_id,
            u.full_name,
            u.email,
            r.role_name
        FROM users u
        INNER JOIN roles r
            ON r.role_id = u.role_id
        WHERE u.user_id = :user_id
        LIMIT 1
    ');
    $userStmt->execute([
        ':user_id' => $userId
    ]);

    $driverUser = $userStmt->fetch();

    if (!$driverUser) {
        jsonResponse(false, 'Selected driver user was not found.', null, 404);
    }

    if ($driverUser['role_name'] !== 'Driver') {
        jsonResponse(false, 'Only users with the Driver role can be assigned driver profiles.', null, 400);
    }

    $existingDriverStmt = $pdo->prepare('
        SELECT driver_id
        FROM drivers
        WHERE user_id = :user_id
        LIMIT 1
    ');
    $existingDriverStmt->execute([
        ':user_id' => $userId
    ]);

    if ($existingDriverStmt->fetch()) {
        jsonResponse(false, 'This user already has a driver profile.', null, 409);
    }

    $duplicateLicenseStmt = $pdo->prepare('
        SELECT driver_id
        FROM drivers
        WHERE license_number = :license_number
        LIMIT 1
    ');
    $duplicateLicenseStmt->execute([
        ':license_number' => $licenseNumber
    ]);

    if ($duplicateLicenseStmt->fetch()) {
        jsonResponse(false, 'A driver profile with that license number already exists.', null, 409);
    }

    $insertStmt = $pdo->prepare('
        INSERT INTO drivers (
            user_id,
            license_number,
            status
        ) VALUES (
            :user_id,
            :license_number,
            :status
        )
    ');

    $insertStmt->execute([
        ':user_id' => $userId,
        ':license_number' => $licenseNumber,
        ':status' => $status
    ]);

    $driverId = (int) $pdo->lastInsertId();

    $fetchStmt = $pdo->prepare('
        SELECT
            d.driver_id,
            d.user_id,
            u.full_name AS driver_name,
            u.email,
            d.license_number,
            d.status,
            d.created_at
        FROM drivers d
        INNER JOIN users u
            ON u.user_id = d.user_id
        WHERE d.driver_id = :driver_id
        LIMIT 1
    ');
    $fetchStmt->execute([
        ':driver_id' => $driverId
    ]);

    $driver = $fetchStmt->fetch();

    if ($driver) {
        $driver['driver_id'] = (int) $driver['driver_id'];
        $driver['user_id'] = (int) $driver['user_id'];
    }

    jsonResponse(true, 'Driver created successfully.', $driver, 201);

} catch (PDOException $e) {
    error_log($e->getMessage());

    if ($e->getCode() === '23000') {
        if (stripos($e->getMessage(), 'user_id') !== false) {
            jsonResponse(false, 'This user already has a driver profile.', null, 409);
        }

        if (stripos($e->getMessage(), 'license_number') !== false) {
            jsonResponse(false, 'A driver profile with that license number already exists.', null, 409);
        }
    }

    jsonResponse(false, 'Unable to create driver profile.', null, 500);
}
