<?php

function userManagementPositiveId(mixed $value, string $label): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT);

    if ($id === false || $id <= 0) {
        jsonResponse(false, "Invalid {$label}.", null, 400);
    }

    return $id;
}

function userManagementEmail(mixed $value): string
{
    if (!is_string($value)) {
        jsonResponse(false, 'Email is required.', null, 400);
    }

    $email = trim($value);

    if ($email === '' || strlen($email) > 150 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        jsonResponse(false, 'A valid email address is required.', null, 422);
    }

    return $email;
}

function userManagementName(mixed $value): string
{
    if (!is_string($value)) {
        jsonResponse(false, 'Full name is required.', null, 400);
    }

    $name = trim($value);

    if ($name === '') {
        jsonResponse(false, 'Full name is required.', null, 400);
    }

    if (strlen($name) > 150) {
        jsonResponse(false, 'Full name must not exceed 150 characters.', null, 422);
    }

    return $name;
}

function userManagementPassword(mixed $value): string
{
    if (!is_string($value) || strlen($value) < 8) {
        jsonResponse(false, 'Password must be at least 8 characters.', null, 422);
    }

    return $value;
}

function userManagementRoleAndClinic(PDO $pdo, int $roleId, ?int $clinicId): array
{
    $roleStatement = $pdo->prepare('
        SELECT role_id, role_name
        FROM roles
        WHERE role_id = :role_id
        LIMIT 1
    ');
    $roleStatement->execute([':role_id' => $roleId]);
    $role = $roleStatement->fetch();

    if (!$role || !in_array($role['role_name'], ['Admin', 'Dispatcher', 'Driver', 'Clinic Staff'], true)) {
        jsonResponse(false, 'Invalid role.', null, 422);
    }

    if ($role['role_name'] === 'Clinic Staff' && $clinicId === null) {
        jsonResponse(false, 'Clinic Staff must be associated with a clinic.', null, 422);
    }

    if ($role['role_name'] !== 'Clinic Staff' && $clinicId !== null) {
        jsonResponse(false, 'Only Clinic Staff can be associated with a clinic.', null, 422);
    }

    if ($clinicId !== null) {
        $clinicStatement = $pdo->prepare('
            SELECT clinic_id
            FROM clinics
            WHERE clinic_id = :clinic_id
            LIMIT 1
        ');
        $clinicStatement->execute([':clinic_id' => $clinicId]);

        if (!$clinicStatement->fetch()) {
            jsonResponse(false, 'Clinic not found.', null, 404);
        }
    }

    return $role;
}

function userManagementDuplicateEmail(PDO $pdo, string $email, ?int $excludeUserId = null): bool
{
    $query = '
        SELECT user_id
        FROM users
        WHERE email = :email
    ';
    $parameters = [':email' => $email];

    if ($excludeUserId === null) {
        $query .= ' LIMIT 1';
    } else {
        $query .= ' AND user_id <> :exclude_user_id LIMIT 1';
        $parameters[':exclude_user_id'] = $excludeUserId;
    }

    $statement = $pdo->prepare($query);
    $statement->execute($parameters);

    return (bool) $statement->fetch();
}

function userManagementSafeUserSelect(): string
{
    return '
        u.user_id,
        u.full_name,
        u.email,
        u.role_id,
        r.role_name,
        u.clinic_id,
        c.clinic_name,
        u.status,
        u.created_at,
        u.updated_at
    ';
}
