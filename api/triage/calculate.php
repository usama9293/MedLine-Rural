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


/*
|--------------------------------------------------------------------------
| Get Request ID
|--------------------------------------------------------------------------
*/

$requestId = filter_input(
    INPUT_GET,
    'request_id',
    FILTER_VALIDATE_INT
);

if (
    $requestId === false ||
    $requestId === null ||
    $requestId <= 0
) {
    jsonResponse(
        false,
        'Invalid request ID.',
        null,
        400
    );
}


try {

    /*
    |--------------------------------------------------------------------------
    | Get Request + Item + Inventory
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            sr.request_id,
            sr.clinic_id,

            ri.item_id,
            ri.quantity_requested,

            it.item_name,
            it.criticality_score,
            it.cold_chain_required,

            inv.quantity_available,
            inv.average_daily_usage

        FROM supply_requests sr

        INNER JOIN request_items ri
            ON sr.request_id = ri.request_id

        INNER JOIN items it
            ON ri.item_id = it.item_id

        LEFT JOIN inventory inv
            ON inv.clinic_id = sr.clinic_id
            AND inv.item_id = ri.item_id

        WHERE sr.request_id = :request_id

        LIMIT 1
    ");

    $stmt->execute([
        ':request_id' => $requestId
    ]);

    $request = $stmt->fetch();

    if (!$request) {

        jsonResponse(
            false,
            'Supply request or requested item not found.',
            null,
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 1. Clinical Criticality
    |--------------------------------------------------------------------------
    */

    $criticalityScore =
        (float)$request['criticality_score'];

    /*
     * Keep the value between 0 and 100.
     */

    $criticalityScore = max(
        0,
        min(100, $criticalityScore)
    );


    /*
    |--------------------------------------------------------------------------
    | 2. Stock-Out Risk
    |--------------------------------------------------------------------------
    */

    $quantityAvailable =
        $request['quantity_available'];

    $averageDailyUsage =
        $request['average_daily_usage'];

    $daysRemaining = null;
    $stockOutRisk = 0;

    if (
        $quantityAvailable !== null &&
        $averageDailyUsage !== null &&
        (float)$averageDailyUsage > 0
    ) {

        $daysRemaining =
            (float)$quantityAvailable /
            (float)$averageDailyUsage;


        /*
        | Stock-out risk scale
        |
        | <= 1 day       = 100
        | <= 3 days      = 80
        | <= 7 days      = 60
        | <= 14 days     = 30
        | > 14 days      = 10
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

    } elseif (
        $quantityAvailable !== null &&
        (float)$quantityAvailable <= 0
    ) {

        /*
         * No stock available.
         */

        $daysRemaining = 0;
        $stockOutRisk = 100;

    } else {

        /*
         * No usage information.
         *
         * We use a neutral value rather than
         * assuming a critical stock-out.
         */

        $stockOutRisk = 50;
    }


    /*
    |--------------------------------------------------------------------------
    | 3. Cold-Chain Risk
    |--------------------------------------------------------------------------
    */

    $coldChainRequired =
        (int)$request['cold_chain_required'];

    if ($coldChainRequired === 1) {

        $coldChainRisk = 100;

    } else {

        $coldChainRisk = 20;
    }


    /*
    |--------------------------------------------------------------------------
    | 4. Transit Risk
    |--------------------------------------------------------------------------
    |
    | Phase 8 initial version:
    | use a neutral baseline value.
    |
    | Later this will be connected to
    | actual environmental/transport data.
    |
    */

    $transitRisk = 10;


    /*
    |--------------------------------------------------------------------------
    | 5. Calculate Final Urgency Score
    |--------------------------------------------------------------------------
    */

    $urgencyScore =
        ($criticalityScore * 0.40) +
        ($stockOutRisk * 0.35) +
        ($coldChainRisk * 0.15) +
        ($transitRisk * 0.10);


    /*
    | Ensure score remains between 1 and 100.
    */

    $urgencyScore = max(
        1,
        min(100, $urgencyScore)
    );

    $urgencyScore = round(
        $urgencyScore,
        2
    );


    /*
    |--------------------------------------------------------------------------
    | 6. Save Score
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE supply_requests
        SET urgency_score = :urgency_score
        WHERE request_id = :request_id
    ");

    $stmt->execute([
        ':urgency_score' => $urgencyScore,
        ':request_id' => $requestId
    ]);


    /*
    |--------------------------------------------------------------------------
    | 7. Return Calculation Details
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Urgency score calculated successfully.',
        [
            'request_id' => (int)$requestId,

            'item' => [
                'item_id' => (int)$request['item_id'],
                'item_name' => $request['item_name']
            ],

            'factors' => [
                'clinical_criticality' => $criticalityScore,
                'stock_out_risk' => $stockOutRisk,
                'cold_chain_risk' => $coldChainRisk,
                'transit_risk' => $transitRisk
            ],

            'stock' => [
                'quantity_available' =>
                    $quantityAvailable !== null
                        ? (float)$quantityAvailable
                        : null,

                'average_daily_usage' =>
                    $averageDailyUsage !== null
                        ? (float)$averageDailyUsage
                        : null,

                'days_remaining' =>
                    $daysRemaining !== null
                        ? round($daysRemaining, 2)
                        : null
            ],

            'urgency_score' => $urgencyScore
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