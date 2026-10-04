<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';
require_once __DIR__ . '/../../config/clinic_management.php';

requireRole('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method Not Allowed', null, 405);
}

$data = getJsonInput();
$clinicName = clinicManagementName($data['clinic_name'] ?? null);
$address = clinicManagementOptionalText($data['address'] ?? null, 'Address', 255);
$region = clinicManagementOptionalText($data['region'] ?? null, 'Region', 100);
$latitude = clinicManagementCoordinate($data['latitude'] ?? null, 'latitude', -90, 90);
$longitude = clinicManagementCoordinate($data['longitude'] ?? null, 'longitude', -180, 180);
$status = array_key_exists('status', $data) ? clinicManagementStatus($data['status']) : 'Active';

try {
    if (clinicManagementDuplicateName($pdo, $clinicName)) {
        jsonResponse(false, 'A clinic with that name already exists.', null, 409);
    }

    $statement = $pdo->prepare('
        INSERT INTO clinics
        (
            clinic_name,
            address,
            region,
            latitude,
            longitude,
            status
        )
        VALUES
        (
            :clinic_name,
            :address,
            :region,
            :latitude,
            :longitude,
            :status
        )
    ');
    $statement->execute([
        ':clinic_name' => $clinicName,
        ':address' => $address,
        ':region' => $region,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':status' => $status
    ]);

    $clinicId = (int) $pdo->lastInsertId();
    $statement = $pdo->prepare('
        SELECT ' . clinicManagementSelect() . '
        FROM clinics c
        WHERE c.clinic_id = :clinic_id
        LIMIT 1
    ');
    $statement->execute([':clinic_id' => $clinicId]);
    $clinic = $statement->fetch();
    $clinic['clinic_id'] = (int) $clinic['clinic_id'];
    $clinic['latitude'] = $clinic['latitude'] !== null ? (float) $clinic['latitude'] : null;
    $clinic['longitude'] = $clinic['longitude'] !== null ? (float) $clinic['longitude'] : null;

    jsonResponse(true, 'Clinic created successfully.', $clinic, 201);
} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Unable to create clinic.', null, 500);
}
