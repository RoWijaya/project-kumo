<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

function kumo_login_redirect(string $error): never {
    header('Location: ../../auth/login.html?error=' . rawurlencode($error));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../auth/login.html');
    exit;
}
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) {
    kumo_login_redirect('csrf');
}

$identity = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
if ($identity === '' || $password === '') {
    kumo_login_redirect('invalid');
}

try {
    require_once __DIR__ . '/db.php';
    $rateHash = kumo_hash_rate_key($identity . '|' . (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    try {
        if (kumo_rate_count($conn, 'login', $rateHash, 15) >= 10) {
            $conn->close();
            kumo_login_redirect('rate_limited');
        }
    } catch (Throwable $rateError) {
        // Older databases may not have the rate-limit table until the migration is imported.
        error_log('Kumo login rate limit is temporarily unavailable: ' . $rateError->getMessage());
    }

    try {
        $stmt = $conn->prepare('SELECT id, username, full_name, email, password, role, auth_version, two_factor_enabled, email_verified_at FROM userdata WHERE email = ? OR username = ? LIMIT 1');
    } catch (mysqli_sql_exception $missingDisplayColumn) {
        // Lets the current deployment continue to accept logins until the additive migration is applied.
        $stmt = $conn->prepare('SELECT id, username, email, password, role, auth_version, two_factor_enabled, email_verified_at FROM userdata WHERE email = ? OR username = ? LIMIT 1');
    }
    $stmt->bind_param('ss', $identity, $identity);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, (string)$user['password'])) {
        try { kumo_rate_record($conn, 'login', $rateHash); } catch (Throwable $ignored) {}
        $conn->close();
        kumo_login_redirect('invalid');
    }

    if (empty($user['email_verified_at'])) {
        $conn->close();
        kumo_login_redirect('email_unverified');
    }

    if ((int)($user['two_factor_enabled'] ?? 0) === 1) {
        $conn->close();
        kumo_login_redirect('two_factor_unavailable');
    }

    $allowedRoles = ['user', 'admin', 'owner'];
    $role = in_array((string)($user['role'] ?? 'user'), $allowedRoles, true)
        ? (string)$user['role']
        : 'user';

    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role'] = $role;
    $_SESSION['auth_version'] = (int)($user['auth_version'] ?? 0);
    $displayName = trim((string)($user['full_name'] ?? ''));
    $_SESSION['display_name'] = $displayName !== '' ? $displayName : (trim((string)($user['username'] ?? '')) ?: 'there');

    if (!empty($_POST['remember'])) {
        try {
            kumo_create_remember_token($conn, (int)$user['id'], (int)($_SESSION['auth_version']));
        } catch (Throwable $rememberError) {
            error_log('Kumo remember-me could not be enabled: ' . $rememberError->getMessage());
        }
    }
    $uid = (int)$user['id'];
    try {
        $update = $conn->prepare('UPDATE userdata SET last_login_at = NOW() WHERE id = ?');
        $update->bind_param('i', $uid);
        $update->execute();
        $update->close();
    } catch (Throwable $ignored) {
        // Some older installations do not yet have last_login_at; login should
        // still succeed until the database migration has been applied.
    }
    $conn->close();

    switch ($role) {
        case 'owner':
            header('Location: ../../Main/owner_dashboard.php');
            break;
        case 'admin':
            header('Location: ../../Main/admin_dashboard.php');
            break;
        default:
            header('Location: ../../Main/homepage.php');
            break;
    }
    exit;
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    error_log('Kumo login failed: ' . $e->getMessage());
    kumo_login_redirect('database');
}
