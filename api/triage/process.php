<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';

requireRole('Dispatcher', 'Admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(
        false,
        'Method Not Allowed',
        null,
        405
    );
}

try {

    /*
    |--------------------------------------------------------------------------
    | Find pending requests that do not have an urgency score
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            sr.request_id
        FROM supply_requests sr

        WHERE sr.status = 'Pending'
        AND sr.urgency_score IS NULL

        ORDER BY sr.requested_at ASC
    ");

    $stmt->execute();

    $requests = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $processed = [];
    $failed = [];

    /*
    |--------------------------------------------------------------------------
    | Process each unscored request
    |--------------------------------------------------------------------------
    */

    foreach ($requests as $requestId) {

        try {

            /*
            | Get request + requested item + inventory
            */

            $stmt = $pdo->prepare("
                SELECT
                    sr.request_id,
                    sr.clinic_id,

                    ri.item_id,

                    i.criticality_score,
                    i.cold_chain_required,

                    inv.quantity_available,
                    inv.average_daily_usage

                FROM supply_requests sr

                INNER JOIN request_items ri
                    ON sr.request_id = ri.request_id

                INNER JOIN items i
                    ON ri.item_id = i.item_id

                LEFT JOIN inventory inv
                    ON inv.clinic_id = sr.clinic_id
                    AND inv.item_id = ri.item_id

                WHERE sr.request_id = :request_id
                LIMIT 1
            ");

            $stmt->execute([
                ':request_id' => $requestId
            ]);

            $data = $stmt->fetch();

            /*
            |--------------------------------------------------------------------------
            | Validate required data
            |--------------------------------------------------------------------------
            */

            if (!$data) {

                $failed[] = [
                    'request_id' => $requestId,
                    'reason' => 'Request item or medical item not found.'
                ];

                continue;
            }

            if (
                $data['quantity_available'] === null ||
                $data['average_daily_usage'] === null
            ) {

                $failed[] = [
                    'request_id' => $requestId,
                    'reason' => 'Inventory information not found.'
                ];

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Calculate days remaining
            |--------------------------------------------------------------------------
            */

            $quantityAvailable = (float)$data['quantity_available'];
            $averageDailyUsage = (float)$data['average_daily_usage'];

            if ($averageDailyUsage > 0) {

                $daysRemaining =
                    $quantityAvailable / $averageDailyUsage;

            } else {

                $daysRemaining = 999;
            }

            /*
            |--------------------------------------------------------------------------
            | Calculate stock-out risk
            |--------------------------------------------------------------------------
            */

            if ($daysRemaining <= 1) {

                $stockOutRisk = 100;

            } elseif ($daysRemaining <= 3) {

                $stockOutRisk = 80;

            } elseif ($daysRemaining <= 7) {

                $stockOutRisk = 60;

            } elseif ($daysRemaining <= 14) {

                $stockOutRisk = 30;

            } else {

                $stockOutRisk = 10;
            }

            /*
            |--------------------------------------------------------------------------
            | Cold-chain risk
            |--------------------------------------------------------------------------
            */

            $coldChainRisk =
                ((int)$data['cold_chain_required'] === 1)
                ? 20
                : 0;

            /*
            |--------------------------------------------------------------------------
            | Transit risk
            |--------------------------------------------------------------------------
            |
            | For now we use the same baseline transit risk
            | used by calculate.php.
            |
            */

            $transitRisk = 10;

            /*
            |--------------------------------------------------------------------------
            | Calculate urgency score
            |--------------------------------------------------------------------------
            */

            $clinicalCriticality =
                (float)$data['criticality_score'];

            $urgencyScore =
                ($clinicalCriticality * 0.40) +
                ($stockOutRisk * 0.35) +
                ($coldChainRisk * 0.15) +
                ($transitRisk * 0.10);

            $urgencyScore = round($urgencyScore, 2);

            /*
            |--------------------------------------------------------------------------
            | Save urgency score
            |--------------------------------------------------------------------------
            */

            $update = $pdo->prepare("
                UPDATE supply_requests

                SET urgency_score = :urgency_score

                WHERE request_id = :request_id
                AND urgency_score IS NULL
            ");

            $update->execute([
                ':urgency_score' => $urgencyScore,
                ':request_id' => $requestId
            ]);

            $processed[] = [
                'request_id' => $requestId,
                'urgency_score' => $urgencyScore,
                'days_remaining' => round($daysRemaining, 2)
            ];

        } catch (Exception $e) {

            error_log($e->getMessage());

            $failed[] = [
                'request_id' => $requestId,
                'reason' => 'Unable to calculate urgency score.'
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Return processing result
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Triage processing completed.',
        [
            'processed_count' => count($processed),
            'failed_count' => count($failed),
            'processed' => $processed,
            'failed' => $failed
        ]
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