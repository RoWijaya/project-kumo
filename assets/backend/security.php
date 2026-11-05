<?php
declare(strict_types=1);

/** Shared security helpers for Kumo. Keep secrets outside source control. */
function kumo_is_https(): bool {
    // Some shared hosts/proxies terminate TLS before PHP, so HTTPS may be
    // indicated only by X-Forwarded-Proto. Trust this header only as a hint
    // for cookie security; the public site is configured to use HTTPS.
    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || $forwardedProto === 'https';
}

function kumo_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    // Force a site-wide cookie path. This prevents stale /Kumo or /auth-only
    // cookie paths from breaking sessions after deployment to the domain root.
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => kumo_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function kumo_csrf_valid($candidate): bool {
    return is_string($candidate) && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $candidate);
}

/** Returns a stable 32-byte key. Prefer KUMO_APP_KEY in production; local key is protected by storage/.htaccess. */
function kumo_app_key(): string {
    $envKey = getenv('KUMO_APP_KEY');
    if (is_string($envKey) && strlen($envKey) >= 32) return hash('sha256', $envKey, true);

    $root = dirname(__DIR__, 2);
    $storage = $root . DIRECTORY_SEPARATOR . 'storage';
    if (!is_dir($storage) && !@mkdir($storage, 0700, true) && !is_dir($storage)) {
        throw new RuntimeException('Kumo storage directory is not writable.');
    }
    $htaccess = $storage . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($htaccess)) {
        $written = @file_put_contents($htaccess, "Require all denied\n", LOCK_EX);
        if ($written === false) throw new RuntimeException('Could not protect the Kumo storage directory.');
    }
    $index = $storage . DIRECTORY_SEPARATOR . 'index.html';
    if (!is_file($index)) @file_put_contents($index, '', LOCK_EX);

    $path = $storage . DIRECTORY_SEPARATOR . 'kumo_app.key';
    if (!is_file($path)) {
        $handle = @fopen($path, 'x+b');
        if ($handle !== false) {
            fwrite($handle, random_bytes(32));
            fflush($handle);
            if (function_exists('fsync')) @fsync($handle);
            fclose($handle);
            @chmod($path, 0600);
        }
    }
    $key = @file_get_contents($path);
    if (!is_string($key) || strlen($key) !== 32) {
        throw new RuntimeException('Kumo encryption key is missing or invalid. Set KUMO_APP_KEY or make storage/ writable.');
    }
    return $key;
}

function kumo_encrypt_secret(string $plain): string {
    if (!function_exists('openssl_encrypt')) throw new RuntimeException('PHP OpenSSL extension is required for two-factor authentication.');
    $key = kumo_app_key();
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'Kumo/TOTP/v1', 16);
    if (!is_string($ciphertext)) throw new RuntimeException('Could not encrypt two-factor secret.');
    return 'v1:' . base64_encode($iv . $tag . $ciphertext);
}

function kumo_decrypt_secret(string $stored): ?string {
    if (!str_starts_with($stored, 'v1:') || !function_exists('openssl_decrypt')) return null;
    $binary = base64_decode(substr($stored, 3), true);
    if (!is_string($binary) || strlen($binary) < 29) return null;
    $iv = substr($binary, 0, 12);
    $tag = substr($binary, 12, 16);
    $ciphertext = substr($binary, 28);
    $result = openssl_decrypt($ciphertext, 'aes-256-gcm', kumo_app_key(), OPENSSL_RAW_DATA, $iv, $tag, 'Kumo/TOTP/v1');
    return is_string($result) ? $result : null;
}

function kumo_base32_encode(string $data): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $char) $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $out .= $alphabet[bindec($chunk)];
    }
    return $out;
}

function kumo_base32_decode(string $encoded): string|false {
    $encoded = strtoupper(preg_replace('/[^A-Z2-7]/', '', $encoded) ?? '');
    if ($encoded === '') return false;
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($encoded) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) return false;
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) $out .= chr(bindec($chunk));
    }
    return $out;
}

function kumo_generate_totp_secret(): string { return kumo_base32_encode(random_bytes(20)); }

function kumo_verify_totp(string $secret, string $code, ?int $time = null): bool {
    if (!preg_match('/^\d{6}$/', $code)) return false;
    $key = kumo_base32_decode($secret);
    if (!is_string($key) || $key === '') return false;
    $counter = intdiv($time ?? time(), 30);
    for ($window = -1; $window <= 1; $window++) {
        $value = $counter + $window;
        if ($value < 0) continue;
        $message = pack('N2', ($value >> 32) & 0xffffffff, $value & 0xffffffff);
        $hash = hash_hmac('sha1', $message, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $number = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        $expected = str_pad((string)($number % 1000000), 6, '0', STR_PAD_LEFT);
        if (hash_equals($expected, $code)) return true;
    }
    return false;
}

function kumo_local_request(): bool {
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    if (str_starts_with($host, '[')) {
        $closing = strpos($host, ']');
        if ($closing !== false) $host = substr($host, 1, $closing - 1);
    } else {
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    }
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function kumo_project_base_url(): string {
    // In production, use a configured canonical URL so a hostile Host header
    // cannot be used to poison password-reset or verification links.
    $localConfig = [];
    $localConfigPath = __DIR__ . '/config.local.php';
    if (is_file($localConfigPath)) {
        $loadedConfig = require $localConfigPath;
        if (is_array($loadedConfig)) $localConfig = $loadedConfig;
    }
    $isLocal = kumo_local_request();
    $configured = getenv('KUMO_BASE_URL');
    if ($configured === false || trim((string)$configured) === '') {
        $baseCfg = $localConfig['base_url'] ?? '';
        if (is_array($baseCfg)) {
            $configured = $isLocal ? ($baseCfg['local'] ?? '') : ($baseCfg['production'] ?? '');
        } else {
            $configured = (string)$baseCfg;
            if ($isLocal && (str_contains($configured, 'kumo.ct.ws') || (!str_contains($configured, 'localhost') && !str_contains($configured, '127.0.0.1')))) {
                $configured = '';
            }
        }
    }
    $configured = trim((string)$configured);
    if ($configured !== '') {
        // Automatically ensure scheme if missing (e.g., "kumo.ct.ws" -> "https://kumo.ct.ws")
        if (!preg_match('#^https?://#i', $configured)) {
            $configured = ($isLocal ? 'http://' : 'https://') . $configured;
        }
        $parts = parse_url($configured);
        $configuredScheme = strtolower((string)($parts['scheme'] ?? ''));
        if (is_array($parts) && in_array($configuredScheme, ['http', 'https'], true)
            && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['query']) && !isset($parts['fragment'])
            && ($isLocal || $configuredScheme === 'https')) {
            return rtrim($configured, '/');
        }
    }

    // Local XAMPP development fallback
    if ($isLocal) {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/Kumo/assets/backend/forgot_password.php'));
        $pos = stripos($script, '/kumo');
        $base = ($pos !== false) ? substr($script, 0, $pos + 5) : '';
        if ($base === '' || $base === '.') {
            $dir = dirname(dirname(dirname($script)));
            $base = ($dir !== '/' && $dir !== '\\' && $dir !== '.') ? $dir : '';
        }
        $base = '/' . trim($base, '/');
        if ($base === '/') $base = '';
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        if (!preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(?::[0-9]{1,5})?$/', $host)) $host = 'localhost';
        $scheme = kumo_is_https() ? 'https' : 'http';
        return $scheme . '://' . $host . $base;
    }

    error_log('Kumo base URL not generated: set KUMO_BASE_URL to the canonical application URL.');
    return '';
}

function kumo_hash_rate_key(string $value): string {
    try { return hash_hmac('sha256', strtolower(trim($value)), kumo_app_key()); }
    catch (Throwable $e) { return hash('sha256', strtolower(trim($value))); }
}

function kumo_rate_count(mysqli $conn, string $scope, string $hash, int $minutes): int {
    $since = date('Y-m-d H:i:s', time() - ($minutes * 60));
    $stmt = $conn->prepare('SELECT COUNT(*) AS amount FROM auth_rate_limits WHERE scope = ? AND key_hash = ? AND created_at >= ?');
    $stmt->bind_param('sss', $scope, $hash, $since);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['amount'] ?? 0);
    $stmt->close();
    return $count;
}

function kumo_rate_record(mysqli $conn, string $scope, string $hash): void {
    $stmt = $conn->prepare('INSERT INTO auth_rate_limits (scope, key_hash) VALUES (?, ?)');
    $stmt->bind_param('ss', $scope, $hash);
    $stmt->execute();
    $stmt->close();
}

function kumo_remember_cookie_options(int $expires): array {
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => kumo_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function kumo_clear_remember_cookie(): void {
    setcookie('kumo_remember', '', kumo_remember_cookie_options(time() - 3600));
    unset($_COOKIE['kumo_remember']);
}

function kumo_create_remember_token(mysqli $conn, int $userId, int $authVersion): void {
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $validatorHash = hash('sha256', $validator);
    $expiry = time() + (30 * 24 * 60 * 60);
    $expiresAt = date('Y-m-d H:i:s', $expiry);
    $stmt = $conn->prepare('INSERT INTO remember_tokens (user_id, selector, validator_hash, auth_version, expires_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('issis', $userId, $selector, $validatorHash, $authVersion, $expiresAt);
    $stmt->execute();
    $stmt->close();
    // Cap active remembered browsers per account to ten, deleting the oldest tokens first.
    $trim = $conn->prepare('DELETE FROM remember_tokens WHERE user_id = ? AND id NOT IN (SELECT id FROM (SELECT id FROM remember_tokens WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 10) AS newest)');
    $trim->bind_param('ii', $userId, $userId);
    $trim->execute();
    $trim->close();
    setcookie('kumo_remember', $selector . ':' . $validator, kumo_remember_cookie_options($expiry));
}

function kumo_revoke_remember_tokens(mysqli $conn, int $userId): void {
    $stmt = $conn->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
    kumo_clear_remember_cookie();
}

function kumo_revoke_current_remember_token(mysqli $conn): void {
    $cookie = (string)($_COOKIE['kumo_remember'] ?? '');
    $parts = explode(':', $cookie, 2);
    if (count($parts) === 2 && preg_match('/^[a-f0-9]{24}$/', $parts[0])) {
        $stmt = $conn->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $stmt->bind_param('s', $parts[0]);
        $stmt->execute();
        $stmt->close();
    }
    kumo_clear_remember_cookie();
}

function kumo_restore_remember_session(mysqli $conn): bool {
    $cookie = (string)($_COOKIE['kumo_remember'] ?? '');
    if ($cookie === '') return false;
    $parts = explode(':', $cookie, 2);
    if (count($parts) !== 2 || !preg_match('/^[a-f0-9]{24}$/', $parts[0]) || !preg_match('/^[a-f0-9]{64}$/', $parts[1])) {
        kumo_clear_remember_cookie();
        return false;
    }
    [$selector, $validator] = $parts;
    $stmt = $conn->prepare('SELECT id, user_id, validator_hash, auth_version, expires_at FROM remember_tokens WHERE selector = ? AND expires_at > NOW() LIMIT 1');
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$record || !hash_equals((string)$record['validator_hash'], hash('sha256', $validator))) {
        if ($record) {
            $delete = $conn->prepare('DELETE FROM remember_tokens WHERE id = ?');
            $id = (int)$record['id']; $delete->bind_param('i', $id); $delete->execute(); $delete->close();
        }
        kumo_clear_remember_cookie();
        return false;
    }
    $userId = (int)$record['user_id'];
    $userStmt = $conn->prepare('SELECT role, auth_version, two_factor_enabled, email_verified_at FROM userdata WHERE id = ? LIMIT 1');
    $userStmt->bind_param('i', $userId); $userStmt->execute(); $user = $userStmt->get_result()->fetch_assoc(); $userStmt->close();
    if (!$user || empty($user['email_verified_at']) || (int)$user['auth_version'] !== (int)$record['auth_version'] || (int)($user['two_factor_enabled'] ?? 0) === 1) {
        $delete = $conn->prepare('DELETE FROM remember_tokens WHERE id = ?');
        $id = (int)$record['id']; $delete->bind_param('i', $id); $delete->execute(); $delete->close();
        kumo_clear_remember_cookie();
        return false;
    }
    // Keep the browser cookie expiry aligned with the fixed server-side token expiry.
    $cookieExpiry = strtotime((string)$record['expires_at']);
    if ($cookieExpiry === false || $cookieExpiry <= time()) {
        $delete = $conn->prepare('DELETE FROM remember_tokens WHERE id = ?');
        $id = (int)$record['id']; $delete->bind_param('i', $id); $delete->execute(); $delete->close();
        kumo_clear_remember_cookie();
        return false;
    }
    $newValidator = bin2hex(random_bytes(32));
    $newValidatorHash = hash('sha256', $newValidator);
    $recordId = (int)$record['id'];
    $rotate = $conn->prepare('UPDATE remember_tokens SET validator_hash = ?, last_used_at = NOW() WHERE id = ?');
    $rotate->bind_param('si', $newValidatorHash, $recordId); $rotate->execute(); $rotate->close();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = in_array($user['role'] ?? 'user', ['user', 'admin', 'owner'], true) ? (string)$user['role'] : 'user';
    $_SESSION['auth_version'] = (int)$user['auth_version'];
    setcookie('kumo_remember', $selector . ':' . $newValidator, kumo_remember_cookie_options($cookieExpiry));
    return true;
}

function kumo_valid_password(string $password): bool {
    // Enforce at least 12 Unicode characters and at most 72 bytes so bcrypt fallback
    // builds cannot silently truncate passwords.
    $characters = preg_match_all('/./us', $password);
    return $characters !== false && $characters >= 12 && strlen($password) <= 72;
}

function kumo_password_hash(string $password): string|false {
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    return password_hash($password, $algo);
}

function kumo_send_email(string $to, string $subject, string $html): bool {
    require_once __DIR__ . '/email_verification.php';
    if (function_exists('kumo_smtp_send')) {
        return kumo_smtp_send($to, $subject, $html);
    }
    return false;
}

kumo_start_session();
