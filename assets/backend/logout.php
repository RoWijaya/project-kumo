<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Use the sign-out form to log out.');
}
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Your session expired. Refresh the page and try again.');
}

// Revoke the persistent login token if possible; logout itself must still work
// if the database is temporarily unavailable.
if (!empty($_COOKIE['kumo_remember'])) {
    try {
        // Let logout reach its cleanup/redirect path even when database config is
        // temporarily invalid; db.php throws instead of exiting in this context.
        if (!defined('KUMO_DB_THROW_ON_FAILURE')) define('KUMO_DB_THROW_ON_FAILURE', true);
        require_once __DIR__ . '/db.php';
        kumo_revoke_current_remember_token($conn);
        $conn->close();
    } catch (Throwable $e) {
        error_log('Kumo logout token revocation failed: ' . $e->getMessage());
        kumo_clear_remember_cookie();
    }
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?: '/',
        'domain' => $params['domain'] ?? '',
        'secure' => (bool)($params['secure'] ?? false),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
session_destroy();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: ../../auth/login.html?logged_out=1');
exit;
