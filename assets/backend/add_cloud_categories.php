<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

function kumo_categories_response(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') kumo_categories_response(405, ['success' => false, 'error' => 'Method not allowed.']);
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) kumo_categories_response(403, ['success' => false, 'error' => 'Your session expired. Refresh and try again.']);
$userId = (int)$_SESSION['user_id'];
$parentId = filter_var($_POST['cloud_id'] ?? null, FILTER_VALIDATE_INT);
$requested = $_POST['categories'] ?? [];
if (!$parentId || $parentId < 1 || !is_array($requested)) kumo_categories_response(422, ['success' => false, 'error' => 'Select a valid Cloud and at least one content type.']);
$catalog = [
    'notes' => ['Notes', 'notes', '📝', 'blue'],
    'tasks' => ['Tasks', 'tasks', '✓', 'orange'],
    'files' => ['Files', 'files', '📎', 'green'],
];
$keys = array_values(array_unique(array_filter($requested, static fn($key): bool => is_string($key) && isset($catalog[$key]))));
if (!$keys) kumo_categories_response(422, ['success' => false, 'error' => 'Select at least one content type.']);
$check = $conn->prepare('SELECT id FROM clouds WHERE id = ? AND user_id = ? LIMIT 1');
$check->bind_param('ii', $parentId, $userId); $check->execute(); $owned = $check->get_result()->fetch_assoc(); $check->close();
if (!$owned) kumo_categories_response(403, ['success' => false, 'error' => 'This Cloud is not available to your account.']);
try {
    $conn->begin_transaction();
    foreach ($keys as $key) {
        [$name, $type, $icon, $theme] = $catalog[$key];
        $find = $conn->prepare('SELECT id FROM clouds WHERE user_id = ? AND parent_id = ? AND name = ? LIMIT 1');
        $find->bind_param('iis', $userId, $parentId, $name); $find->execute(); $existing = $find->get_result()->fetch_assoc(); $find->close();
        if ($existing) continue;
        $insert = $conn->prepare('INSERT INTO clouds (user_id, parent_id, name, cloud_type, icon, color_theme) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->bind_param('iissss', $userId, $parentId, $name, $type, $icon, $theme); $insert->execute(); $insert->close();
    }
    $conn->commit(); $conn->close();
    kumo_categories_response(200, ['success' => true, 'redirect' => 'homepage.php?cloud_id=' . $parentId]);
} catch (Throwable $e) {
    $conn->rollback(); $conn->close();
    error_log('Kumo add categories failed: ' . $e->getMessage());
    kumo_categories_response(500, ['success' => false, 'error' => 'Could not add these content types. Please try again.']);
}
