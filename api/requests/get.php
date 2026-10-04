<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';

requireRole('Clinic Staff', 'Dispatcher', 'Admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(
        false,
        'Method Not Allowed',
        null,
        405
    );
}

try {

    $role = $_SESSION['role_name'];

    /*
    |--------------------------------------------------------------------------
    | CLINIC STAFF
    |--------------------------------------------------------------------------
    |
    | Clinic Staff can only see requests belonging to their own clinic.
    |
    */

    if ($role === 'Clinic Staff') {

        $clinicId = $_SESSION['clinic_id'] ?? null;

        if ($clinicId === null) {

            jsonResponse(
                false,
                'No clinic is associated with this account.',
                null,
                403
            );
        }

        $stmt = $pdo->prepare("
            SELECT
                sr.request_id,
                sr.clinic_id,
                c.clinic_name,
                sr.requested_by,
                sr.status,
                sr.urgency_score,
                sr.requested_at,
                sr.required_by,
                sr.notes
            FROM supply_requests sr

            INNER JOIN clinics c
                ON sr.clinic_id = c.clinic_id

            WHERE sr.clinic_id = :clinic_id

            ORDER BY
                sr.urgency_score DESC,
                sr.requested_at ASC
        ");

        $stmt->execute([
            ':clinic_id' => $clinicId
        ]);

    }

    /*
    |--------------------------------------------------------------------------
    | DISPATCHER / ADMIN
    |--------------------------------------------------------------------------
    |
    | These roles can see requests from all clinics.
    |
    */

    elseif (
        $role === 'Dispatcher' ||
        $role === 'Admin'
    ) {

        $stmt = $pdo->prepare("
            SELECT
                sr.request_id,
                sr.clinic_id,
                c.clinic_name,
                sr.requested_by,
                sr.status,
                sr.urgency_score,
                sr.requested_at,
                sr.required_by,
                sr.notes
            FROM supply_requests sr

            INNER JOIN clinics c
                ON sr.clinic_id = c.clinic_id

            ORDER BY
                sr.urgency_score DESC,
                sr.requested_at ASC
        ");

        $stmt->execute();

    }

    else {

        jsonResponse(
            false,
            'You are not authorised to view supply requests.',
            null,
            403
        );
    }

    $requests = $stmt->fetchAll();

    jsonResponse(
        true,
        'Supply requests retrieved successfully.',
        $requests
    );

} catch (PDOException $e) {

    /*
     * Log technical error internally.
     * Do not expose database details.
     */

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Internal server error.',
        null,
        500
    );
}