<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

function kumo_password_json(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') kumo_password_json(405, ['success' => false, 'error' => 'Method not allowed.']);
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) kumo_password_json(403, ['success' => false, 'error' => 'Your session expired. Refresh and try again.']);
$currentPassword = (string)($_POST['current_password'] ?? '');
$newPassword = (string)($_POST['new_password'] ?? '');
$confirmation = (string)($_POST['confirm_password'] ?? '');
if ($currentPassword === '' || $newPassword === '') kumo_password_json(422, ['success' => false, 'error' => 'Fill in all password fields.']);
if ($newPassword !== $confirmation) kumo_password_json(422, ['success' => false, 'error' => 'The new passwords do not match.']);
if (strlen($newPassword) < 8 || strlen($newPassword) > 20 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword) || !preg_match('/[^a-zA-Z0-9]/', $newPassword)) {
    kumo_password_json(422, ['success' => false, 'error' => 'Use 8–20 characters with uppercase, lowercase, a number, and a special character.']);
}
if ($newPassword === $currentPassword) kumo_password_json(422, ['success' => false, 'error' => 'Choose a new password that differs from your current one.']);
$userId = (int)$_SESSION['user_id'];
try {
    $stmt = $conn->prepare('SELECT password, auth_version FROM userdata WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId); $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$account || !password_verify($currentPassword, (string)$account['password'])) kumo_password_json(403, ['success' => false, 'error' => 'Your current password was not accepted.']);
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    if (!is_string($hash)) throw new RuntimeException('Could not hash new password.');
    $update = $conn->prepare('UPDATE userdata SET password = ?, auth_version = auth_version + 1 WHERE id = ?');
    $update->bind_param('si', $hash, $userId); $update->execute(); $update->close();
    try { $delete = $conn->prepare('DELETE FROM remember_tokens WHERE user_id = ?'); $delete->bind_param('i', $userId); $delete->execute(); $delete->close(); } catch (Throwable $ignored) {}
    $_SESSION['auth_version'] = (int)$account['auth_version'] + 1;
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    if (function_exists('kumo_clear_remember_cookie')) kumo_clear_remember_cookie();
    kumo_password_json(200, ['success' => true, 'message' => 'Your password has been changed. Other remembered sign-ins have been revoked.', 'csrf_token' => (string)$_SESSION['csrf_token']]);
} catch (Throwable $e) {
    error_log('Kumo password change failed: ' . $e->getMessage());
    kumo_password_json(500, ['success' => false, 'error' => 'Your password could not be changed. Please try again.']);
}
