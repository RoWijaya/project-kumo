<?php
declare(strict_types=1);

/*
 * Kumo database bootstrap.
 * Preferred configuration: environment variables KUMO_DB_HOST, KUMO_DB_USER,
 * KUMO_DB_PASSWORD and KUMO_DB_NAME, or a private config.local.php file that
 * returns ['database' => ['host' => ..., 'username' => ..., 'password' => ..., 'name' => ...]].
 * For local XAMPP only, the defaults are 127.0.0.1 / root / empty password / users.
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$kumoLocalConfig = [];
$kumoLocalConfigPath = __DIR__ . '/config.local.php';
if (is_file($kumoLocalConfigPath)) {
    $loadedConfig = require $kumoLocalConfigPath;
    if (is_array($loadedConfig)) {
        $kumoLocalConfig = $loadedConfig;
    }
}

$kumoDatabaseConfig = is_array($kumoLocalConfig['database'] ?? null)
    ? $kumoLocalConfig['database']
    : [];
$kumoHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$kumoLocalRequest = PHP_SAPI === 'cli'
    || in_array(preg_replace('/:\\d+$/', '', $kumoHost), ['localhost', '127.0.0.1', '[::1]'], true);

$activeDb = $kumoDatabaseConfig;
if (isset($kumoDatabaseConfig['local']) || isset($kumoDatabaseConfig['production'])) {
    $activeDb = $kumoLocalRequest ? ($kumoDatabaseConfig['local'] ?? []) : ($kumoDatabaseConfig['production'] ?? []);
} elseif ($kumoLocalRequest && isset($activeDb['host']) && !in_array($activeDb['host'], ['localhost', '127.0.0.1', '::1'], true)) {
    $activeDb = ['host' => '127.0.0.1', 'username' => 'root', 'password' => '', 'name' => 'users'];
}

$servername = (string)(getenv('KUMO_DB_HOST') ?: ($activeDb['host'] ?? ($kumoLocalRequest ? '127.0.0.1' : '')));
$dbUsername = (string)(getenv('KUMO_DB_USER') ?: ($activeDb['username'] ?? ($kumoLocalRequest ? 'root' : '')));
$dbPassword = (string)(getenv('KUMO_DB_PASSWORD') !== false
    ? getenv('KUMO_DB_PASSWORD')
    : ($activeDb['password'] ?? ($kumoLocalRequest ? '' : '')));
$dbname = (string)(getenv('KUMO_DB_NAME') ?: ($activeDb['name'] ?? ($kumoLocalRequest ? 'users' : '')));

if ($servername === '' || $dbUsername === '' || $dbname === '') {
    $message = 'Kumo database is not configured. Set KUMO_DB_* variables or create assets/backend/config.local.php.';
    error_log($message);
    if (defined('KUMO_DB_THROW_ON_FAILURE') && KUMO_DB_THROW_ON_FAILURE) {
        throw new RuntimeException($message);
    }
    http_response_code(503);
    exit('Kumo is not connected to its database yet. Please configure the database settings.');
}

try {
    $conn = new mysqli($servername, $dbUsername, $dbPassword, $dbname);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    error_log('Kumo database connection failed: ' . $e->getMessage());
    if (defined('KUMO_DB_THROW_ON_FAILURE') && KUMO_DB_THROW_ON_FAILURE) {
        throw new RuntimeException('Kumo database connection failed.', 0, $e);
    }
    http_response_code(503);
    exit('Kumo could not connect to the database. Please check the database settings and try again.');
}
