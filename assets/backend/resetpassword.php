<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../auth/reset-password.html');
    exit;
}

if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid request token. Refresh the reset page and try again.');
}

$token = trim((string)($_POST['token'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    header('Location: ../../auth/login.html?error=reset_invalid');
    exit;
}
if ($password !== $confirmPassword) {
    header('Location: ../../auth/new-password.php?token=' . rawurlencode($token) . '&error=password_mismatch');
    exit;
}
if (strlen($password) < 8 || strlen($password) > 20) {
    header('Location: ../../auth/new-password.php?token=' . rawurlencode($token) . '&error=password_length');
    exit;
}
if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password)
    || !preg_match('/[0-9]/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)) {
    header('Location: ../../auth/new-password.php?token=' . rawurlencode($token) . '&error=password_complexity');
    exit;
}

try {
    require_once __DIR__ . '/db.php';
    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare('SELECT id, user_id FROM password_reset_tokens WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1');
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$reset) {
        $conn->close();
        header('Location: ../../auth/login.html?error=reset_invalid');
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $userId = (int)$reset['user_id'];
    $update = $conn->prepare('UPDATE userdata SET password = ?, auth_version = auth_version + 1 WHERE id = ?');
    $update->bind_param('si', $hash, $userId);
    $update->execute();
    $update->close();

    $deleteTokens = $conn->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
    $deleteTokens->bind_param('i', $userId);
    $deleteTokens->execute();
    $deleteTokens->close();

    $deleteRemember = $conn->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
    $deleteRemember->bind_param('i', $userId);
    $deleteRemember->execute();
    $deleteRemember->close();
    $conn->close();

    header('Location: ../../auth/login.html?reset=success');
    exit;
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    error_log('Kumo password update failed: ' . $e->getMessage());
    header('Location: ../../auth/login.html?error=database');
    exit;
}
