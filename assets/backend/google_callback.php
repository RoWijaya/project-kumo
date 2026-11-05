<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/google_config.php';

function kumo_google_fail(string $reason = 'google_failed'): never {
    unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_started']);
    header('Location: ../../auth/login.html?error=' . rawurlencode($reason));
    exit;
}

$expectedState = (string)($_SESSION['google_oauth_state'] ?? '');
$receivedState = (string)($_GET['state'] ?? '');
$startedAt = (int)($_SESSION['google_oauth_started'] ?? 0);
unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_started']);
if ($expectedState === '' || $receivedState === '' || !hash_equals($expectedState, $receivedState) || $startedAt < time() - 600) kumo_google_fail();
if (isset($_GET['error']) || !isset($_GET['code']) || !is_string($_GET['code'])) kumo_google_fail();
if (!function_exists('curl_init')) { error_log('Kumo Google OAuth requires PHP cURL.'); kumo_google_fail(); }
if (KUMO_GOOGLE_CLIENT_ID === 'PASTE_GOOGLE_OAUTH_CLIENT_ID_HERE' || KUMO_GOOGLE_CLIENT_SECRET === 'PASTE_GOOGLE_OAUTH_CLIENT_SECRET_HERE') kumo_google_fail('google_not_configured');

function kumo_google_http(string $url, array $fields = [], ?string $bearer = null): ?array {
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($bearer !== null) $headers[] = 'Authorization: Bearer ' . $bearer;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($fields) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&', PHP_QUERY_RFC3986));
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) error_log('Kumo Google OAuth cURL error: ' . curl_error($ch));
    curl_close($ch);
    if (!is_string($body) || $status < 200 || $status >= 300) {
        error_log('Kumo Google OAuth HTTP request failed with status ' . $status);
        return null;
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

$token = kumo_google_http(KUMO_GOOGLE_TOKEN_URL, [
    'code' => (string)$_GET['code'],
    'client_id' => KUMO_GOOGLE_CLIENT_ID,
    'client_secret' => KUMO_GOOGLE_CLIENT_SECRET,
    'redirect_uri' => KUMO_GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code',
]);
if (!$token || empty($token['access_token'])) kumo_google_fail();
$profile = kumo_google_http(KUMO_GOOGLE_USERINFO_URL, [], (string)$token['access_token']);
if (!$profile || empty($profile['email']) || empty($profile['sub']) || empty($profile['email_verified'])) kumo_google_fail('google_email');
$email = strtolower(trim((string)$profile['email']));
$displayName = trim((string)($profile['name'] ?? 'Google User'));
if (function_exists('mb_substr')) $displayName = mb_substr($displayName, 0, 100, 'UTF-8');
else $displayName = substr($displayName, 0, 100);
if ($displayName === '') $displayName = 'Google User';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) kumo_google_fail('google_email');

try {
    try {
        $stmt = $conn->prepare('SELECT id, username, full_name, email, role, two_factor_enabled, auth_version, email_verified_at FROM userdata WHERE email = ? LIMIT 1');
    } catch (mysqli_sql_exception $missingDisplayColumn) {
        $stmt = $conn->prepare('SELECT id, username, email, role, two_factor_enabled, auth_version, email_verified_at FROM userdata WHERE email = ? LIMIT 1');
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $base = preg_replace('/[^\p{L}\p{N}._-]+/u', '', str_replace(' ', '.', $displayName)) ?: 'GoogleUser';
        $base = substr($base, 0, 75);
        $username = $base;
        for ($i = 0; $i < 8; $i++) {
            $check = $conn->prepare('SELECT id FROM userdata WHERE username = ? LIMIT 1');
            $check->bind_param('s', $username);
            $check->execute();
            $exists = (bool)$check->get_result()->fetch_assoc();
            $check->close();
            if (!$exists) break;
            $username = substr($base, 0, 65) . '.' . bin2hex(random_bytes(4));
        }
        $randomPassword = bin2hex(random_bytes(32));
        $passwordHash = password_hash($randomPassword, PASSWORD_DEFAULT);
        $role = 'user';
        try {
            $insert = $conn->prepare("INSERT INTO userdata (username, full_name, email, password, role, setup_completed, two_factor_enabled, auth_version) VALUES (?, ?, ?, ?, ?, 0, 0, 0)");
            $insert->bind_param('sssss', $username, $displayName, $email, $passwordHash, $role);
        } catch (mysqli_sql_exception $missingDisplayColumn) {
            $insert = $conn->prepare("INSERT INTO userdata (username, email, password, role, setup_completed, two_factor_enabled, auth_version) VALUES (?, ?, ?, ?, 0, 0, 0)");
            $insert->bind_param('ssss', $username, $email, $passwordHash, $role);
        }
        $insert->execute();
        $userId = (int)$conn->insert_id;
        $insert->close();
        $user = ['id' => $userId, 'username' => $username, 'full_name' => $displayName, 'email' => $email, 'role' => 'user', 'two_factor_enabled' => 0, 'auth_version' => 0];
    }

    // Google has already asserted email_verified=true above, so a successful Google OAuth
    // identity check also proves ownership for this email address.
    $verifiedUserId = (int)$user['id'];
    $markVerified = $conn->prepare('UPDATE userdata SET email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = ?');
    $markVerified->bind_param('i', $verifiedUserId);
    $markVerified->execute();
    $markVerified->close();

    // The project has no complete 2FA enrollment/challenge UI in this build.
    // Fail closed instead of redirecting to the missing two-factor.php page.
    if ((int)($user['two_factor_enabled'] ?? 0) === 1) {
        $conn->close();
        kumo_google_fail('two_factor_unavailable');
    }

    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role'] = in_array($user['role'] ?? 'user', ['user', 'admin', 'owner'], true) ? (string)$user['role'] : 'user';
    $_SESSION['auth_version'] = (int)($user['auth_version'] ?? 0);
    $sessionName = trim((string)($user['full_name'] ?? ''));
    $_SESSION['display_name'] = $sessionName !== '' ? $sessionName : (trim((string)($user['username'] ?? '')) ?: 'there');
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_role'], $_SESSION['pending_2fa_auth_version'], $_SESSION['pending_2fa_attempts'], $_SESSION['pending_2fa_started_at'], $_SESSION['pending_2fa_remember']);
    $uid = (int)$user['id'];
    $update = $conn->prepare('UPDATE userdata SET last_login_at = NOW() WHERE id = ?');
    $update->bind_param('i', $uid);
    $update->execute();
    $update->close();
    $conn->close();
    $role = (string)($_SESSION['role'] ?? 'user');
    if ($role === 'owner') {
        header('Location: ../../Main/owner_dashboard.php');
    } elseif ($role === 'admin') {
        header('Location: ../../Main/admin_dashboard.php');
    } else {
        header('Location: ../../Main/homepage.php');
    }
    exit;
} catch (Throwable $e) {
    error_log('Kumo Google OAuth callback failed: ' . $e->getMessage());
    if ($conn instanceof mysqli) $conn->close();
    kumo_google_fail();
}
