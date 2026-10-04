<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/clinic_management.php';

requireRole('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method Not Allowed', null, 405);
}

$clinicId = clinicManagementPositiveId($_GET['clinic_id'] ?? null);

try {
    $statement = $pdo->prepare('
        SELECT
            ' . clinicManagementSelect() . '
        FROM clinics c
        WHERE c.clinic_id = :clinic_id
        LIMIT 1
    ');
    $statement->execute([':clinic_id' => $clinicId]);
    $clinic = $statement->fetch();

    if (!$clinic) {
        jsonResponse(false, 'Clinic not found.', null, 404);
    }

    $staffStatement = $pdo->prepare('
        SELECT
            u.user_id,
            u.full_name,
            u.email,
            u.status
        FROM users u
        INNER JOIN roles r
            ON r.role_id = u.role_id
        WHERE u.clinic_id = :clinic_id
          AND r.role_name = \'Clinic Staff\'
        ORDER BY u.full_name ASC, u.user_id ASC
    ');
    $staffStatement->execute([':clinic_id' => $clinicId]);
    $clinic['staff'] = $staffStatement->fetchAll();

    $clinic['clinic_id'] = (int) $clinic['clinic_id'];
    $clinic['latitude'] = $clinic['latitude'] !== null ? (float) $clinic['latitude'] : null;
    $clinic['longitude'] = $clinic['longitude'] !== null ? (float) $clinic['longitude'] : null;
    foreach ($clinic['staff'] as &$staffMember) {
        $staffMember['user_id'] = (int) $staffMember['user_id'];
    }
    unset($staffMember);

    jsonResponse(true, 'Clinic retrieved successfully.', $clinic, 200);
} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Unable to retrieve clinic.', null, 500);
}
