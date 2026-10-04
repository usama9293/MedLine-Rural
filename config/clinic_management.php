<?php

function clinicManagementName(mixed $value): string
{
    if (!is_string($value)) {
        jsonResponse(false, 'Clinic name is required.', null, 400);
    }

    $name = trim($value);

    if ($name === '') {
        jsonResponse(false, 'Clinic name is required.', null, 400);
    }

    if (strlen($name) > 150) {
        jsonResponse(false, 'Clinic name must not exceed 150 characters.', null, 422);
    }

    return $name;
}

function clinicManagementOptionalText(mixed $value, string $field, int $maxLength): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_string($value)) {
        jsonResponse(false, "Invalid {$field}.", null, 422);
    }

    $text = trim($value);

    if ($text === '') {
        return null;
    }

    if (strlen($text) > $maxLength) {
        jsonResponse(false, "{$field} must not exceed {$maxLength} characters.", null, 422);
    }

    return $text;
}

function clinicManagementCoordinate(mixed $value, string $field, float $minimum, float $maximum): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_array($value) || is_object($value) || !is_numeric($value)) {
        jsonResponse(false, "Invalid {$field}.", null, 422);
    }

    $coordinate = (float) $value;

    if ($coordinate < $minimum || $coordinate > $maximum) {
        jsonResponse(false, "Invalid {$field}.", null, 422);
    }

    return $coordinate;
}

function clinicManagementPositiveId(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT);

    if ($id === false || $id <= 0) {
        jsonResponse(false, 'Invalid clinic ID.', null, 400);
    }

    return $id;
}

function clinicManagementStatus(mixed $value): string
{
    if (!is_string($value) || !in_array($value, ['Active', 'Inactive'], true)) {
        jsonResponse(false, 'Invalid clinic status.', null, 422);
    }

    return $value;
}

function clinicManagementDuplicateName(PDO $pdo, string $name, ?int $excludeClinicId = null): bool
{
    $query = 'SELECT clinic_id FROM clinics WHERE LOWER(clinic_name) = LOWER(:clinic_name)';
    $parameters = [':clinic_name' => $name];

    if ($excludeClinicId !== null) {
        $query .= ' AND clinic_id <> :exclude_clinic_id';
        $parameters[':exclude_clinic_id'] = $excludeClinicId;
    }

    $query .= ' LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->execute($parameters);

    return (bool) $statement->fetch();
}

function clinicManagementSelect(): string
{
    return '
        c.clinic_id,
        c.clinic_name,
        c.address,
        c.region,
        c.latitude,
        c.longitude,
        c.status,
        c.created_at
    ';
}
