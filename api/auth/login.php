<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: ../../frontend/login.php?error=1');
    exit;
}

try {

    $stmt = $pdo->prepare("
        SELECT
            u.user_id,
            u.full_name,
            u.email,
            u.password_hash,
            u.status,
            u.clinic_id,
            r.role_id,
            r.role_name
        FROM users u
        INNER JOIN roles r
            ON u.role_id = r.role_id
        WHERE u.email = :email
        LIMIT 1
    ");

    $stmt->execute([
        ':email' => $email
    ]);

    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'Active') {
        header('Location: ../../frontend/login.php?error=1');
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        header('Location: ../../frontend/login.php?error=1');
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role_id'] = (int) $user['role_id'];
    $_SESSION['role_name'] = $user['role_name'];
    $_SESSION['clinic_id'] = $user['clinic_id'] !== null
        ? (int) $user['clinic_id']
        : null;

    switch ($user['role_name']) {

        case 'Clinic Staff':
            header('Location: ../../clinic/dashboard.php');
            break;

        case 'Dispatcher':
            header('Location: ../../dispatcher/dashboard.php');
            break;

        case 'Driver':
            header('Location: ../../driver/dashboard.php');
            break;

        case 'Admin':
            header('Location: ../../admin/dashboard.php');
            break;

        default:
            logoutUser();
            header('Location: ../../frontend/login.php?error=1');
            break;
    }

    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit('Internal server error.');
}