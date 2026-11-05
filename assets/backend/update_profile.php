<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

function kumo_profile_json(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') kumo_profile_json(405, ['success' => false, 'error' => 'Method not allowed.']);
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) kumo_profile_json(403, ['success' => false, 'error' => 'Your session expired. Refresh and try again.']);

$fullName = trim((string)($_POST['full_name'] ?? ''));
$nameLength = function_exists('mb_strlen') ? mb_strlen($fullName, 'UTF-8') : strlen($fullName);
if ($fullName === '' || $nameLength > 100 || preg_match('/[\x00-\x1F\x7F]/', $fullName)) {
    kumo_profile_json(422, ['success' => false, 'error' => 'Enter a display name of up to 100 characters.']);
}
$userId = (int)$_SESSION['user_id'];
try {
    $stmt = $conn->prepare('UPDATE userdata SET full_name = ? WHERE id = ?');
    $stmt->bind_param('si', $fullName, $userId);
    $stmt->execute();
    if ($stmt->affected_rows < 0) throw new RuntimeException('Profile update failed.');
    $stmt->close();
    $_SESSION['display_name'] = $fullName;
    kumo_profile_json(200, ['success' => true, 'message' => 'Your display name has been saved.', 'display_name' => $fullName]);
} catch (Throwable $e) {
    error_log('Kumo profile update failed: ' . $e->getMessage());
    kumo_profile_json(500, ['success' => false, 'error' => 'Your profile could not be saved. Apply the profile migration and try again.']);
}
