<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/clinic_management.php';

requireRole('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method Not Allowed', null, 405);
}

try {
    $statement = $pdo->query('
        SELECT
            ' . clinicManagementSelect() . '
        FROM clinics c
        ORDER BY c.clinic_name ASC, c.clinic_id ASC
    ');
    $clinics = $statement->fetchAll();

    foreach ($clinics as &$clinic) {
        $clinic['clinic_id'] = (int) $clinic['clinic_id'];
        $clinic['latitude'] = $clinic['latitude'] !== null ? (float) $clinic['latitude'] : null;
        $clinic['longitude'] = $clinic['longitude'] !== null ? (float) $clinic['longitude'] : null;
    }
    unset($clinic);

    jsonResponse(true, 'Clinics retrieved successfully.', $clinics, 200);
} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Unable to retrieve clinics.', null, 500);
}
