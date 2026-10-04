<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/validation.php';

requireRole('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method Not Allowed', null, 405);
}

$data = getJsonInput();

$clinicId = filter_var($data['clinic_id'] ?? null, FILTER_VALIDATE_INT);
if ($clinicId === false || $clinicId <= 0) {
    jsonResponse(false, 'Valid clinic ID is required.', null, 400);
}

$itemId = filter_var($data['item_id'] ?? null, FILTER_VALIDATE_INT);
if ($itemId === false || $itemId <= 0) {
    jsonResponse(false, 'Valid item ID is required.', null, 400);
}

if (!array_key_exists('quantity_to_add', $data) || !is_numeric($data['quantity_to_add'])) {
    jsonResponse(false, 'Quantity to add must be a numeric value.', null, 400);
}

$quantityToAdd = (float) $data['quantity_to_add'];
if ($quantityToAdd <= 0) {
    jsonResponse(false, 'Quantity to add must be greater than zero.', null, 400);
}

try {
    // Verify clinic exists
    $stmtClinic = $pdo->prepare('
        SELECT clinic_id, clinic_name, status
        FROM clinics
        WHERE clinic_id = :clinic_id
        LIMIT 1
    ');
    $stmtClinic->execute([':clinic_id' => $clinicId]);
    $clinic = $stmtClinic->fetch();

    if (!$clinic) {
        jsonResponse(false, 'The specified clinic was not found.', null, 400);
    }

    // Verify item exists and is Active
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

    if ($item['status'] !== 'Active') {
        jsonResponse(false, 'The specified item is not active.', null, 400);
    }

    $pdo->beginTransaction();

    // Upsert into inventory using unique (clinic_id, item_id) constraint.
    // Preserves average_daily_usage on update.
    $stmtUpsert = $pdo->prepare('
        INSERT INTO inventory (
            clinic_id,
            item_id,
            quantity_available,
            average_daily_usage
        ) VALUES (
            :clinic_id,
            :item_id,
            :quantity_to_add,
            0.00
        )
        ON DUPLICATE KEY UPDATE
            quantity_available = quantity_available + VALUES(quantity_available),
            last_updated = CURRENT_TIMESTAMP
    ');

    $stmtUpsert->execute([
        ':clinic_id'        => $clinicId,
        ':item_id'          => $itemId,
        ':quantity_to_add'  => $quantityToAdd
    ]);

    // Fetch the updated inventory row
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
        WHERE i.clinic_id = :clinic_id AND i.item_id = :item_id
        LIMIT 1
    ');
    $stmtResult->execute([
        ':clinic_id' => $clinicId,
        ':item_id'   => $itemId
    ]);
    $updatedRow = $stmtResult->fetch();

    $pdo->commit();

    if ($updatedRow) {
        $updatedRow['inventory_id'] = (int) $updatedRow['inventory_id'];
        $updatedRow['clinic_id'] = (int) $updatedRow['clinic_id'];
        $updatedRow['item_id'] = (int) $updatedRow['item_id'];
        $updatedRow['quantity_available'] = (float) $updatedRow['quantity_available'];
        $updatedRow['average_daily_usage'] = (float) $updatedRow['average_daily_usage'];
    }

    jsonResponse(true, 'Stock added successfully.', $updatedRow, 200);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    jsonResponse(false, 'Unable to add stock to inventory.', null, 500);
}
