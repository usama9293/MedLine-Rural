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

$itemName = isset($data['item_name']) ? trim((string) $data['item_name']) : '';
$category = isset($data['category']) ? trim((string) $data['category']) : '';
$category = $category !== '' ? $category : null;

if ($itemName === '') {
    jsonResponse(false, 'Item name is required.', null, 400);
}

if (mb_strlen($itemName) > 150) {
    jsonResponse(false, 'Item name must not exceed 150 characters.', null, 400);
}

if ($category !== null && mb_strlen($category) > 100) {
    jsonResponse(false, 'Category must not exceed 100 characters.', null, 400);
}

// Criticality score validation (0 to 100, defaults to 50.00)
$criticalityScore = 50.00;
if (array_key_exists('criticality_score', $data) && $data['criticality_score'] !== null && $data['criticality_score'] !== '') {
    if (!is_numeric($data['criticality_score'])) {
        jsonResponse(false, 'Criticality score must be a number between 0 and 100.', null, 400);
    }
    $criticalityScore = (float) $data['criticality_score'];
    if ($criticalityScore < 0 || $criticalityScore > 100) {
        jsonResponse(false, 'Criticality score must be between 0 and 100.', null, 400);
    }
}

// Cold chain requirement validation
$coldChainRequired = false;
if (array_key_exists('cold_chain_required', $data)) {
    $rawColdChain = $data['cold_chain_required'];
    if (is_bool($rawColdChain)) {
        $coldChainRequired = $rawColdChain;
    } elseif ($rawColdChain === 1 || $rawColdChain === '1' || $rawColdChain === 'true') {
        $coldChainRequired = true;
    } elseif ($rawColdChain === 0 || $rawColdChain === '0' || $rawColdChain === 'false') {
        $coldChainRequired = false;
    } else {
        jsonResponse(false, 'Cold chain required must be a boolean value.', null, 400);
    }
}

$minimumTemperature = null;
$maximumTemperature = null;

if ($coldChainRequired) {
    if (!array_key_exists('minimum_temperature', $data) || $data['minimum_temperature'] === null || $data['minimum_temperature'] === '' ||
        !array_key_exists('maximum_temperature', $data) || $data['maximum_temperature'] === null || $data['maximum_temperature'] === '') {
        jsonResponse(false, 'Minimum and maximum temperatures are required when cold chain is required.', null, 400);
    }

    if (!is_numeric($data['minimum_temperature']) || !is_numeric($data['maximum_temperature'])) {
        jsonResponse(false, 'Minimum and maximum temperatures must be valid numbers.', null, 400);
    }

    $minimumTemperature = (float) $data['minimum_temperature'];
    $maximumTemperature = (float) $data['maximum_temperature'];

    if ($minimumTemperature >= $maximumTemperature) {
        jsonResponse(false, 'Minimum temperature must be less than maximum temperature.', null, 400);
    }
}

try {
    // Check if an item with the same name already exists
    $checkStmt = $pdo->prepare('
        SELECT item_id
        FROM items
        WHERE LOWER(item_name) = LOWER(:item_name)
        LIMIT 1
    ');
    $checkStmt->execute([':item_name' => $itemName]);

    if ($checkStmt->fetch()) {
        jsonResponse(false, 'An item with that name already exists in the catalog.', null, 409);
    }

    $stmt = $pdo->prepare('
        INSERT INTO items (
            item_name,
            category,
            criticality_score,
            cold_chain_required,
            minimum_temperature,
            maximum_temperature,
            status
        ) VALUES (
            :item_name,
            :category,
            :criticality_score,
            :cold_chain_required,
            :minimum_temperature,
            :maximum_temperature,
            \'Active\'
        )
    ');

    $stmt->execute([
        ':item_name'            => $itemName,
        ':category'             => $category,
        ':criticality_score'    => $criticalityScore,
        ':cold_chain_required'  => $coldChainRequired ? 1 : 0,
        ':minimum_temperature'  => $minimumTemperature,
        ':maximum_temperature'  => $maximumTemperature
    ]);

    $itemId = (int) $pdo->lastInsertId();

    $fetchStmt = $pdo->prepare('
        SELECT
            item_id,
            item_name,
            category,
            criticality_score,
            cold_chain_required,
            minimum_temperature,
            maximum_temperature,
            status,
            created_at
        FROM items
        WHERE item_id = :item_id
        LIMIT 1
    ');
    $fetchStmt->execute([':item_id' => $itemId]);
    $createdItem = $fetchStmt->fetch();

    if ($createdItem) {
        $createdItem['item_id'] = (int) $createdItem['item_id'];
        $createdItem['criticality_score'] = (float) $createdItem['criticality_score'];
        $createdItem['cold_chain_required'] = (bool) $createdItem['cold_chain_required'];
        $createdItem['minimum_temperature'] = $createdItem['minimum_temperature'] !== null ? (float) $createdItem['minimum_temperature'] : null;
        $createdItem['maximum_temperature'] = $createdItem['maximum_temperature'] !== null ? (float) $createdItem['maximum_temperature'] : null;
    }

    jsonResponse(true, 'Item created successfully.', $createdItem, 201);

} catch (PDOException $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Unable to create medical item.', null, 500);
}
