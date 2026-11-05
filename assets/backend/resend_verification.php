<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !kumo_csrf_valid($_POST['csrf_token'] ?? null)) {
    header('Location: ../../auth/verify-email.html?error=csrf'); exit;
}
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$redirect = '../../auth/verify-email.html?resent=1';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) { header('Location: ' . $redirect); exit; }
try {
    require_once __DIR__ . '/db.php';
    $rateHash = kumo_hash_rate_key($email . '|' . (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    try { if (kumo_rate_count($conn, 'email_verify', $rateHash, 30) >= 5) { $conn->close(); header('Location: ' . $redirect); exit; } } catch (Throwable $ignored) {}
    try { kumo_rate_record($conn, 'email_verify', $rateHash); } catch (Throwable $ignored) {}
    $stmt = $conn->prepare('SELECT id, email, email_verified_at FROM userdata WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($user && empty($user['email_verified_at'])) {
        require_once __DIR__ . '/email_verification.php';
        if (!kumo_create_email_verification($conn, (int)$user['id'], (string)$user['email'])) {
            error_log('Kumo could not send an email verification message.');
        }
    }
    $conn->close();
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    error_log('Kumo resend verification request failed: ' . $e->getMessage());
}
header('Location: ' . $redirect); exit;
