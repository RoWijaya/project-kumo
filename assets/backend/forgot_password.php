<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../auth/reset-password.html');
    exit;
}
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) {
    header('Location: ../../auth/reset-link-sent.html');
    exit;
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
// Always use the same response to avoid exposing whether an account exists.
$redirect = '../../auth/reset-link-sent.html';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    header('Location: ' . $redirect);
    exit;
}

try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/email_verification.php';
    $stmt = $conn->prepare('SELECT id, username, email FROM userdata WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $baseUrl = kumo_project_base_url();
        if ($baseUrl !== '') {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $userId = (int)$user['id'];

            $delete = $conn->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
            $delete->bind_param('i', $userId);
            $delete->execute();
            $delete->close();

            $insert = $conn->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))');
            $insert->bind_param('is', $userId, $tokenHash);
            $insert->execute();
            $insert->close();

            $link = $baseUrl . '/auth/new-password.php?token=' . rawurlencode($token);
            $safeName = htmlspecialchars((string)$user['username'], ENT_QUOTES, 'UTF-8');
            $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
            $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#24352b;line-height:1.6"><div style="max-width:560px;margin:32px auto;padding:28px;border:1px solid #e3e9e3;border-radius:18px">'
                . '<h1 style="margin-top:0">Reset your Kumo password</h1>'
                . '<p>Hello ' . $safeName . ',</p>'
                . '<p>A password reset was requested for your Kumo account. This link expires in 30 minutes.</p>'
                . '<p><a href="' . $safeLink . '" style="display:inline-block;background:#355b43;color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none">Reset your password</a></p>'
                . '<p>If you did not request this, you can ignore this email. Your password will not change.</p>'
                . '</div></body></html>';
            $sent = kumo_smtp_send((string)$user['email'], 'Reset your Kumo password', $html);
            if (!$sent) {
                error_log('Kumo could not send a password reset email. Check SMTP configuration in config.local.php.');
                // Delete the unsent token so a token that was never delivered cannot linger.
                $deleteToken = $conn->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
                $deleteToken->bind_param('i', $userId);
                $deleteToken->execute();
                $deleteToken->close();
            }
        } else {
            error_log('Kumo password reset not sent: set base_url to an absolute https URL in config.local.php.');
        }
    }
    $conn->close();
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    error_log('Kumo password reset request failed: ' . $e->getMessage());
}

header('Location: ' . $redirect);
exit;
