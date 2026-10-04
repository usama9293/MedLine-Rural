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
    | Determine which clinic data can be accessed
    |--------------------------------------------------------------------------
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
                i.inventory_id,
                i.clinic_id,
                c.clinic_name,
                i.item_id,
                it.item_name,
                it.category,
                it.criticality_score,
                it.cold_chain_required,
                i.quantity_available,
                i.average_daily_usage,
                CASE
                    WHEN i.average_daily_usage > 0
                    THEN
                        ROUND(
                            i.quantity_available /
                            i.average_daily_usage,
                            2
                        )
                    ELSE NULL
                END AS days_remaining

            FROM inventory i

            INNER JOIN clinics c
                ON i.clinic_id = c.clinic_id

            INNER JOIN items it
                ON i.item_id = it.item_id

            WHERE i.clinic_id = :clinic_id

            ORDER BY days_remaining ASC
        ");

        $stmt->execute([
            ':clinic_id' => $clinicId
        ]);

    } else {

        /*
         * Dispatcher and Admin can see
         * stock risk across all clinics.
         */

        $stmt = $pdo->prepare("
            SELECT
                i.inventory_id,
                i.clinic_id,
                c.clinic_name,
                i.item_id,
                it.item_name,
                it.category,
                it.criticality_score,
                it.cold_chain_required,
                i.quantity_available,
                i.average_daily_usage,
                CASE
                    WHEN i.average_daily_usage > 0
                    THEN
                        ROUND(
                            i.quantity_available /
                            i.average_daily_usage,
                            2
                        )
                    ELSE NULL
                END AS days_remaining

            FROM inventory i

            INNER JOIN clinics c
                ON i.clinic_id = c.clinic_id

            INNER JOIN items it
                ON i.item_id = it.item_id

            ORDER BY days_remaining ASC
        ");

        $stmt->execute();
    }

    $inventory = $stmt->fetchAll();

    /*
    |--------------------------------------------------------------------------
    | Calculate stock-out risk level
    |--------------------------------------------------------------------------
    */

    foreach ($inventory as &$record) {

        $days = $record['days_remaining'];

        if ($days === null) {

            $record['stock_out_risk'] = 'Unknown';

        } elseif ($days <= 1) {

            $record['stock_out_risk'] = 'Critical';

        } elseif ($days <= 3) {

            $record['stock_out_risk'] = 'High';

        } elseif ($days <= 7) {

            $record['stock_out_risk'] = 'Medium';

        } else {

            $record['stock_out_risk'] = 'Low';
        }
    }

    unset($record);

    jsonResponse(
        true,
        'Stock-out risk calculated successfully.',
        $inventory
    );

} catch (PDOException $e) {

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Internal server error.',
        null,
        500
    );
}