<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

requireRole('Clinic Staff', 'Admin');

header('Content-Type: application/json');

try {

    $clinicId = $_SESSION['clinic_id'] ?? null;

    // Admin can optionally request a specific clinic.
    // Clinic Staff must always use their own clinic.
    if ($_SESSION['role_name'] === 'Admin') {

        $requestedClinicId = filter_input(
            INPUT_GET,
            'clinic_id',
            FILTER_VALIDATE_INT
        );

        if ($requestedClinicId !== false && $requestedClinicId !== null) {
            $clinicId = $requestedClinicId;
        }

    }

    if ($clinicId === null) {
        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'No clinic is associated with this account.'
        ]);

        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            i.inventory_id,
            i.clinic_id,
            i.item_id,
            items.item_name,
            items.category,
            items.criticality_score,
            items.cold_chain_required,
            i.quantity_available,
            i.average_daily_usage,
            CASE
                WHEN i.average_daily_usage > 0
                THEN ROUND(
                    i.quantity_available / i.average_daily_usage,
                    2
                )
                ELSE NULL
            END AS days_remaining,
            i.last_updated
        FROM inventory i
        INNER JOIN items
            ON i.item_id = items.item_id
        WHERE i.clinic_id = :clinic_id
        ORDER BY items.item_name ASC
    ");

    $stmt->execute([
        ':clinic_id' => $clinicId
    ]);

    $inventory = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'clinic_id' => $clinicId,
        'data' => $inventory
    ]);

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Internal server error.'
    ]);
}