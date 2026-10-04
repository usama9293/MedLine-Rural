<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';

requireRole('Clinic Staff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(
        false,
        'Method Not Allowed',
        null,
        405
    );
}

$data = getJsonInput();

$itemId = filter_var(
    $data['item_id'] ?? null,
    FILTER_VALIDATE_INT
);

$quantity = $data['quantity'] ?? null;

$requiredBy = trim(
    $data['required_by'] ?? ''
);

$notes = trim(
    $data['notes'] ?? ''
);


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if ($itemId === false || $itemId <= 0) {
    jsonResponse(
        false,
        'Invalid item ID.',
        null,
        400
    );
}

if (
    !is_numeric($quantity) ||
    (float)$quantity <= 0
) {
    jsonResponse(
        false,
        'Quantity must be greater than zero.',
        null,
        400
    );
}

$quantity = (float)$quantity;

if ($quantity > 1000000) {
    jsonResponse(
        false,
        'Requested quantity is too large.',
        null,
        400
    );
}


/*
|--------------------------------------------------------------------------
| REQUIRED-BY DATE VALIDATION
|--------------------------------------------------------------------------
*/

if ($requiredBy !== '') {

    $requiredByTimestamp = strtotime($requiredBy);

    if ($requiredByTimestamp === false) {
        jsonResponse(
            false,
            'Invalid required-by date.',
            null,
            400
        );
    }

    if ($requiredByTimestamp < time()) {
        jsonResponse(
            false,
            'Required-by date cannot be in the past.',
            null,
            400
        );
    }

    $requiredBy = date(
        'Y-m-d H:i:s',
        $requiredByTimestamp
    );

} else {

    $requiredBy = null;
}


/*
|--------------------------------------------------------------------------
| GET CLINIC AND USER FROM SESSION
|--------------------------------------------------------------------------
*/

$clinicId = $_SESSION['clinic_id'] ?? null;

$userId = $_SESSION['user_id'] ?? null;

if ($clinicId === null || $userId === null) {
    jsonResponse(
        false,
        'Clinic information is not available.',
        null,
        403
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

try {

    /*
     * Check medical item
     */

    $stmt = $pdo->prepare("
        SELECT
            item_id,
            item_name,
            status
        FROM items
        WHERE item_id = :item_id
        LIMIT 1
    ");

    $stmt->execute([
        ':item_id' => $itemId
    ]);

    $item = $stmt->fetch();

    if (!$item) {
        jsonResponse(
            false,
            'Medical item not found.',
            null,
            404
        );
    }

    if ($item['status'] !== 'Active') {
        jsonResponse(
            false,
            'This medical item is not currently available.',
            null,
            400
        );
    }


    /*
     * Start transaction
     */

    $pdo->beginTransaction();


    /*
     * Create supply request
     */

    $stmt = $pdo->prepare("
        INSERT INTO supply_requests
        (
            clinic_id,
            requested_by,
            status,
            urgency_score,
            required_by,
            notes
        )
        VALUES
        (
            :clinic_id,
            :requested_by,
            'Pending',
            NULL,
            :required_by,
            :notes
        )
    ");

    $stmt->execute([
        ':clinic_id' => $clinicId,
        ':requested_by' => $userId,
        ':required_by' => $requiredBy,
        ':notes' => $notes !== ''
            ? $notes
            : null
    ]);

    $requestId = (int)$pdo->lastInsertId();


    /*
     * Create request item
     */

    $stmt = $pdo->prepare("
        INSERT INTO request_items
        (
            request_id,
            item_id,
            quantity_requested
        )
        VALUES
        (
            :request_id,
            :item_id,
            :quantity_requested
        )
    ");

    $stmt->execute([
        ':request_id' => $requestId,
        ':item_id' => $itemId,
        ':quantity_requested' => $quantity
    ]);


    /*
     * Commit
     */

    $pdo->commit();


    /*
     * Success response
     */

    jsonResponse(
        true,
        'Supply request created successfully.',
        [
            'request_id' => $requestId,
            'clinic_id' => (int)$clinicId,
            'item_id' => $itemId,
            'quantity_requested' => $quantity,
            'status' => 'Pending'
        ],
        201
    );


} catch (PDOException $e) {

    /*
     * Rollback if something failed
     */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    /*
     * Store technical error in server log.
     * Do not expose it to the user.
     */

    error_log($e->getMessage());

    jsonResponse(
        false,
        'Unable to create supply request.',
        null,
        500
    );
}