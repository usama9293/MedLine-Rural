<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';

requireRole('Clinic Staff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method Not Allowed', null, 405);
}

// Strictly derive clinic_id from session - ignore any client-supplied clinic_id
$clinicId = $_SESSION['clinic_id'] ?? null;
if ($clinicId === null || $clinicId <= 0) {
    jsonResponse(false, 'No clinic is associated with this account.', null, 403);
}
$clinicId = (int) $clinicId;

$data = getJsonInput();

$itemId = filter_var($data['item_id'] ?? null, FILTER_VALIDATE_INT);
if ($itemId === false || $itemId <= 0) {
    jsonResponse(false, 'Valid item ID is required.', null, 400);
}

if (!array_key_exists('quantity_used', $data) || !is_numeric($data['quantity_used'])) {
    jsonResponse(false, 'Quantity used must be a numeric value.', null, 400);
}

$quantityUsed = (float) $data['quantity_used'];
if ($quantityUsed <= 0) {
    jsonResponse(false, 'Quantity used must be greater than zero.', null, 400);
}

try {
    // Verify item exists
    $stmtItem = $pdo->prepare('
        SELECT item_id, item_name, status
        FROM items
        WHERE item_id = :item_id
        LIMIT 1
    ');
    $stmtItem->execute([':item_id' => $itemId]);
    $item = $stmtItem->fetch();

    if (!$item) {
        jsonResponse(false, 'The specified item was not found.', null, 400);
    }

    $pdo->beginTransaction();

    // Select inventory row for the staff member's clinic with row lock
    $stmtInv = $pdo->prepare('
        SELECT
            inventory_id,
            clinic_id,
            item_id,
            quantity_available,
            average_daily_usage
        FROM inventory
        WHERE clinic_id = :clinic_id AND item_id = :item_id
        FOR UPDATE
    ');
    $stmtInv->execute([
        ':clinic_id' => $clinicId,
        ':item_id'   => $itemId
    ]);
    $invRow = $stmtInv->fetch();

    if (!$invRow) {
        $pdo->rollBack();
        jsonResponse(false, 'No inventory record exists for this item at your clinic.', null, 400);
    }

    $currentStock = (float) $invRow['quantity_available'];

    if ($quantityUsed > $currentStock) {
        $pdo->rollBack();
        jsonResponse(
            false,
            sprintf(
                'Insufficient stock available. Requested: %s, Current stock: %s.',
                $quantityUsed,
                $currentStock
            ),
            [
                'item_id'            => $itemId,
                'quantity_available' => $currentStock,
                'quantity_requested' => $quantityUsed
            ],
            400
        );
    }

    $newStock = round($currentStock - $quantityUsed, 2);
    if ($newStock < 0) {
        $pdo->rollBack();
        jsonResponse(false, 'Stock cannot be reduced below zero.', null, 400);
    }

    // Update quantity_available while setting average_daily_usage
    // to the most recently recorded usage amount for this item.
    $stmtUpdate = $pdo->prepare('
        UPDATE inventory
        SET
            quantity_available = :new_stock,
            average_daily_usage = :quantity_used,
            last_updated = CURRENT_TIMESTAMP
        WHERE inventory_id = :inventory_id
    ');
    $stmtUpdate->execute([
        ':new_stock'     => $newStock,
        ':quantity_used' => $quantityUsed,
        ':inventory_id'  => (int) $invRow['inventory_id']
    ]);

    // Fetch updated record
    $stmtResult = $pdo->prepare('
        SELECT
            i.inventory_id,
            i.clinic_id,
            c.clinic_name,
            i.item_id,
            it.item_name,
            i.quantity_available,
            i.average_daily_usage,
            i.last_updated
        FROM inventory i
        INNER JOIN clinics c ON i.clinic_id = c.clinic_id
        INNER JOIN items it ON i.item_id = it.item_id
        WHERE i.inventory_id = :inventory_id
        LIMIT 1
    ');
    $stmtResult->execute([':inventory_id' => (int) $invRow['inventory_id']]);
    $updatedRow = $stmtResult->fetch();

    $pdo->commit();

    if ($updatedRow) {
        $updatedRow['inventory_id'] = (int) $updatedRow['inventory_id'];
        $updatedRow['clinic_id'] = (int) $updatedRow['clinic_id'];
        $updatedRow['item_id'] = (int) $updatedRow['item_id'];
        $updatedRow['quantity_available'] = (float) $updatedRow['quantity_available'];
        $updatedRow['quantity_used'] = $quantityUsed;
        $updatedRow['average_daily_usage'] = (float) $updatedRow['average_daily_usage'];
    }

    jsonResponse(true, 'Usage recorded successfully.', $updatedRow, 200);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    jsonResponse(false, 'Unable to record inventory usage.', null, 500);
}
