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
$status = clinicManagementStatus($data['status'] ?? null);

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare('
        SELECT clinic_id, status
        FROM clinics
        WHERE clinic_id = :clinic_id
        LIMIT 1
        FOR UPDATE
    ');
    $statement->execute([':clinic_id' => $clinicId]);
    $clinic = $statement->fetch();

    if (!$clinic) {
        $pdo->rollBack();
        jsonResponse(false, 'Clinic not found.', null, 404);
    }

    if ($clinic['status'] === $status) {
        $pdo->rollBack();
        jsonResponse(false, 'Clinic already has that status.', null, 409);
    }

    $statement = $pdo->prepare('
        UPDATE clinics
        SET status = :status
        WHERE clinic_id = :clinic_id
    ');
    $statement->execute([
        ':status' => $status,
        ':clinic_id' => $clinicId
    ]);
    $pdo->commit();

    jsonResponse(true, 'Clinic status updated successfully.', [
        'clinic_id' => $clinicId,
        'previous_status' => $clinic['status'],
        'status' => $status
    ], 200);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());
    jsonResponse(false, 'Unable to update clinic status.', null, 500);
}
