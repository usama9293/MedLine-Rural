<?php

$localConfigPath = __DIR__ . '/db.local.php';
$localConfig = [];

if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;

    if (!is_array($localConfig)) {
        throw new RuntimeException('The local database configuration must return an array.');
    }
}

$readSetting = static function (string $environmentVariable, string $configKey) use ($localConfig): ?string {
    $value = getenv($environmentVariable);

    if ($value === false) {
        if (!array_key_exists($configKey, $localConfig)) {
            return null;
        }

        $value = $localConfig[$configKey];
    }

    if (!is_string($value) && !is_int($value)) {
        throw new RuntimeException("Invalid database configuration for {$environmentVariable}.");
    }

    return (string) $value;
};

$host = $readSetting('DB_HOST', 'host');
$port = $readSetting('DB_PORT', 'port') ?? '3306';
$dbname = $readSetting('DB_NAME', 'name');
$username = $readSetting('DB_USER', 'user');
$password = $readSetting('DB_PASSWORD', 'password');

if ($host === null || $host === '' || $dbname === null || $dbname === '' || $username === null || $password === null) {
    throw new RuntimeException(
        'Database configuration is incomplete. Set DB_HOST, DB_NAME, DB_USER, and DB_PASSWORD '
        . 'in the environment or config/db.local.php.'
    );
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please contact administrator.");
}