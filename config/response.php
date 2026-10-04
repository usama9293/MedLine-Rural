<?php

function jsonResponse(
    bool $success,
    string $message,
    mixed $data = null,
    int $statusCode = 200
): never {

    http_response_code($statusCode);

    header('Content-Type: application/json');

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);

    exit;
}
