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

$vehicleNumber = trim((string) ($data['vehicle_number'] ?? ''));
$vehicleType = trim((string) ($data['vehicle_type'] ?? ''));
$capacity = $data['capacity'] ?? null;
$status = trim((string) ($data['status'] ?? 'Available'));

$allowedStatuses = [
    'Available',
    'In Transit',
    'Maintenance',
    'Inactive'
];

if ($vehicleNumber === '') {
    jsonResponse(false, 'Vehicle number is required.', null, 400);
}

if (mb_strlen($vehicleNumber) > 50) {
    jsonResponse(false, 'Vehicle number must not exceed 50 characters.', null, 400);
}

if ($vehicleType === '') {
    jsonResponse(false, 'Vehicle type is required.', null, 400);
}

if (mb_strlen($vehicleType) > 100) {
    jsonResponse(false, 'Vehicle type must not exceed 100 characters.', null, 400);
}

if ($capacity === null || $capacity === '' || !is_numeric($capacity)) {
    jsonResponse(false, 'Capacity must be a valid numeric value.', null, 400);
}

$capacityValue = (float) $capacity;

if ($capacityValue < 0) {
    jsonResponse(false, 'Capacity cannot be less than zero.', null, 400);
}

if (!array_key_exists('refrigerated', $data)) {
    $refrigerated = false;
} else {
    $rawRefrigerated = $data['refrigerated'];

    if (is_bool($rawRefrigerated)) {
        $refrigerated = $rawRefrigerated;
    } elseif ($rawRefrigerated === 1 || $rawRefrigerated === '1' || $rawRefrigerated === 'true') {
        $refrigerated = true;
    } elseif ($rawRefrigerated === 0 || $rawRefrigerated === '0' || $rawRefrigerated === 'false') {
        $refrigerated = false;
    } else {
        jsonResponse(false, 'Refrigerated must be a boolean value.', null, 400);
    }
}

if (!in_array($status, $allowedStatuses, true)) {
    jsonResponse(false, 'Invalid vehicle status.', ['allowed_statuses' => $allowedStatuses], 400);
}

try {
    $duplicateStmt = $pdo->prepare('
        SELECT vehicle_id
        FROM vehicles
        WHERE LOWER(vehicle_number) = LOWER(:vehicle_number)
        LIMIT 1
    ');
    $duplicateStmt->execute([
        ':vehicle_number' => $vehicleNumber
    ]);

    if ($duplicateStmt->fetch()) {
        jsonResponse(false, 'A vehicle with that number already exists.', null, 409);
    }

    $stmt = $pdo->prepare('
        INSERT INTO vehicles (
            vehicle_number,
            vehicle_type,
            capacity,
            refrigerated,
            status
        ) VALUES (
            :vehicle_number,
            :vehicle_type,
            :capacity,
            :refrigerated,
            :status
        )
    ');

    $stmt->execute([
        ':vehicle_number' => $vehicleNumber,
        ':vehicle_type' => $vehicleType,
        ':capacity' => $capacityValue,
        ':refrigerated' => $refrigerated ? 1 : 0,
        ':status' => $status
    ]);

    $vehicleId = (int) $pdo->lastInsertId();

    $fetchStmt = $pdo->prepare('
        SELECT
            vehicle_id,
            vehicle_number,
            vehicle_type,
            capacity,
            refrigerated,
            status,
            created_at
        FROM vehicles
        WHERE vehicle_id = :vehicle_id
        LIMIT 1
    ');
    $fetchStmt->execute([
        ':vehicle_id' => $vehicleId
    ]);

    $vehicle = $fetchStmt->fetch();

    if ($vehicle) {
        $vehicle['vehicle_id'] = (int) $vehicle['vehicle_id'];
        $vehicle['capacity'] = $vehicle['capacity'] !== null ? (float) $vehicle['capacity'] : null;
        $vehicle['refrigerated'] = (bool) $vehicle['refrigerated'];
    }

    jsonResponse(true, 'Vehicle created successfully.', $vehicle, 201);

} catch (PDOException $e) {
    error_log($e->getMessage());

    if ($e->getCode() === '23000') {
        jsonResponse(false, 'A vehicle with that number already exists.', null, 409);
    }

    jsonResponse(false, 'Unable to create vehicle.', null, 500);
}
