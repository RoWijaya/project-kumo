<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

if (empty($_SESSION['user_id']) && !empty($_COOKIE['kumo_remember'])) {
    require_once __DIR__ . '/db.php';
    try {
        kumo_restore_remember_session($conn);
    } catch (Throwable $e) {
        error_log('Kumo remember-me restore failed: ' . $e->getMessage());
    }
}

$kumoAuthScriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$kumoAuthIsBackendRequest = str_contains($kumoAuthScriptPath, '/assets/backend/');
$kumoAuthLoginPath = $kumoAuthIsBackendRequest ? '../../auth/login.html' : '../auth/login.html';
$kumoAuthWantsJson = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;

if (empty($_SESSION['user_id'])) {
    if ($kumoAuthWantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Your session expired. Sign in again.']);
        exit;
    }
    header('Location: ' . $kumoAuthLoginPath);
    exit;
}

// Protected pages and AJAX responses should not be served from the browser cache after logout.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Check account/version on each protected page so password resets and role changes
// invalidate older sessions rather than leaving them privileged indefinitely.
require_once __DIR__ . '/db.php';
try {
    $userId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT role, auth_version, email_verified_at FROM userdata WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$account || (isset($_SESSION['auth_version']) && (int)$_SESSION['auth_version'] !== (int)$account['auth_version'])) {
        $_SESSION = [];
        session_regenerate_id(true);
        if ($kumoAuthWantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Your session expired. Sign in again.']);
            exit;
        }
        header('Location: ' . $kumoAuthLoginPath . '?error=session_expired');
        exit;
    }
    if (empty($account['email_verified_at'])) {
        $_SESSION = [];
        session_regenerate_id(true);
        kumo_clear_remember_cookie();
        header('Location: ' . ($kumoAuthIsBackendRequest ? '../../auth/verify-email.html' : '../auth/verify-email.html') . '?error=email_unverified');
        exit;
    }
    $allowedRoles = ['user', 'admin', 'owner'];
    $_SESSION['role'] = in_array((string)($account['role'] ?? 'user'), $allowedRoles, true)
        ? (string)$account['role'] : 'user';
    $_SESSION['auth_version'] = (int)$account['auth_version'];
} catch (Throwable $e) {
    error_log('Kumo session validation failed: ' . $e->getMessage());
    http_response_code(503);
    exit('Kumo could not verify your session. Please try again shortly.');
}
