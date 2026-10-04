<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/response.php';

requireRole('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method Not Allowed', null, 405);
}

try {
    $stmt = $pdo->query("
        SELECT
            item_id,
            item_name,
            category,
            criticality_score,
            cold_chain_required,
            minimum_temperature,
            maximum_temperature,
            status
        FROM items
        WHERE status = 'Active'
        ORDER BY item_name ASC, item_id ASC
    ");

    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        $item['item_id'] = (int) $item['item_id'];
        $item['criticality_score'] = (float) $item['criticality_score'];
        $item['cold_chain_required'] = (bool) $item['cold_chain_required'];
        $item['minimum_temperature'] = $item['minimum_temperature'] !== null ? (float) $item['minimum_temperature'] : null;
        $item['maximum_temperature'] = $item['maximum_temperature'] !== null ? (float) $item['maximum_temperature'] : null;
    }
    unset($item);

    jsonResponse(true, 'Items retrieved successfully.', $items, 200);

} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Unable to retrieve items.', null, 500);
}
