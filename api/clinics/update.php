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
$clinicId = clinicManagementPositiveId($data['clinic_id'] ?? null);
$supportedFields = ['clinic_name', 'address', 'region', 'latitude', 'longitude', 'status'];
if (!array_intersect(array_keys($data), $supportedFields)) {
    jsonResponse(false, 'At least one supported clinic field is required.', null, 400);
}

try {
    $statement = $pdo->prepare('SELECT * FROM clinics WHERE clinic_id = :clinic_id LIMIT 1');
    $statement->execute([':clinic_id' => $clinicId]);
    $current = $statement->fetch();

    if (!$current) {
        jsonResponse(false, 'Clinic not found.', null, 404);
    }

    $clinicName = array_key_exists('clinic_name', $data)
        ? clinicManagementName($data['clinic_name'])
        : $current['clinic_name'];
    $address = array_key_exists('address', $data)
        ? clinicManagementOptionalText($data['address'], 'Address', 255)
        : $current['address'];
    $region = array_key_exists('region', $data)
        ? clinicManagementOptionalText($data['region'], 'Region', 100)
        : $current['region'];
    $latitude = array_key_exists('latitude', $data)
        ? clinicManagementCoordinate($data['latitude'], 'latitude', -90, 90)
        : ($current['latitude'] !== null ? (float) $current['latitude'] : null);
    $longitude = array_key_exists('longitude', $data)
        ? clinicManagementCoordinate($data['longitude'], 'longitude', -180, 180)
        : ($current['longitude'] !== null ? (float) $current['longitude'] : null);
    $status = array_key_exists('status', $data)
        ? clinicManagementStatus($data['status'])
        : $current['status'];

    if (clinicManagementDuplicateName($pdo, $clinicName, $clinicId)) {
        jsonResponse(false, 'A clinic with that name already exists.', null, 409);
    }

    $statement = $pdo->prepare('
        UPDATE clinics
        SET clinic_name = :clinic_name,
            address = :address,
            region = :region,
            latitude = :latitude,
            longitude = :longitude,
            status = :status
        WHERE clinic_id = :clinic_id
    ');
    $statement->execute([
        ':clinic_name' => $clinicName,
        ':address' => $address,
        ':region' => $region,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':status' => $status,
        ':clinic_id' => $clinicId
    ]);

    $statement = $pdo->prepare('SELECT ' . clinicManagementSelect() . ' FROM clinics c WHERE c.clinic_id = :clinic_id LIMIT 1');
    $statement->execute([':clinic_id' => $clinicId]);
    $clinic = $statement->fetch();
    $clinic['clinic_id'] = (int) $clinic['clinic_id'];
    $clinic['latitude'] = $clinic['latitude'] !== null ? (float) $clinic['latitude'] : null;
    $clinic['longitude'] = $clinic['longitude'] !== null ? (float) $clinic['longitude'] : null;

    jsonResponse(true, 'Clinic updated successfully.', $clinic, 200);
} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Unable to update clinic.', null, 500);
}
