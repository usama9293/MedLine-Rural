<?php

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        jsonResponse(
            false,
            'Request body is required.',
            null,
            400
        );
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        jsonResponse(
            false,
            'Invalid JSON request body.',
            null,
            400
        );
    }

    return $data;
}

