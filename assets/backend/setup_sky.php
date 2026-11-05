<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

function kumo_setup_response(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    kumo_setup_response(405, ['success' => false, 'error' => 'Method not allowed']);
}
if (!kumo_csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    kumo_setup_response(403, ['success' => false, 'error' => 'Your session expired. Refresh and try again.']);
}

$userId = (int)$_SESSION['user_id'];
$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data) || !isset($data['stage']) || !is_string($data['stage'])) {
    kumo_setup_response(422, ['success' => false, 'error' => 'Invalid setup payload']);
}
$stage = $data['stage'];
if ($stage === 'skip') {
    $stmt = $conn->prepare('UPDATE userdata SET setup_completed = 1 WHERE id = ?');
    $stmt->bind_param('i', $userId); $stmt->execute(); $stmt->close(); $conn->close();
    kumo_setup_response(200, ['success' => true]);
}

$stageConfig = [
    'university' => ['root' => 'Education', 'icon' => '🎓', 'group_label' => 'semester', 'group_icon' => '📅', 'group_theme' => 'orange', 'items_type' => 'class'],
    'highschool' => ['root' => 'High School', 'icon' => '🏫', 'group_label' => 'grade', 'group_icon' => '📘', 'group_theme' => 'blue', 'items_type' => 'subject'],
    'middleschool' => ['root' => 'Middle School', 'icon' => '📘', 'group_label' => 'grade', 'group_icon' => '📘', 'group_theme' => 'blue', 'items_type' => 'subject'],
    'elementary' => ['root' => 'Elementary School', 'icon' => '✏️', 'group_label' => 'grade', 'group_icon' => '📘', 'group_theme' => 'blue', 'items_type' => 'subject'],
    'work' => ['root' => 'Work', 'icon' => '💼', 'group_label' => 'role', 'group_icon' => '🗂', 'group_theme' => 'green', 'items_type' => 'responsibility'],
];
if (!isset($stageConfig[$stage])) {
    kumo_setup_response(422, ['success' => false, 'error' => 'Unsupported setup type']);
}
$config = $stageConfig[$stage];
$groupNameField = $config['group_label'];
$groupName = trim((string)($data[$groupNameField] ?? ''));
$items = $data['items'] ?? $data['classes'] ?? [];
if (!is_array($items)) {
    kumo_setup_response(422, ['success' => false, 'error' => 'Add at least one item']);
}
$items = array_values(array_unique(array_filter(array_map(static function ($item): string {
    return is_string($item) ? trim($item) : '';
}, $items), static fn(string $item): bool => $item !== '')));
if ($groupName === '' || strlen($groupName) > 100 || count($items) < 1 || count($items) > 50) {
    kumo_setup_response(422, ['success' => false, 'error' => 'Enter a name and between 1 and 50 classes, subjects, or responsibilities']);
}
foreach ($items as $item) {
    if (strlen($item) > 100) kumo_setup_response(422, ['success' => false, 'error' => 'Each item must be 100 characters or fewer']);
}

try {
    $conn->begin_transaction();
    $existing = $conn->prepare('SELECT id FROM clouds WHERE user_id = ? AND parent_id IS NULL LIMIT 1');
    $existing->bind_param('i', $userId); $existing->execute();
    $alreadyHasClouds = (bool)$existing->get_result()->fetch_assoc(); $existing->close();
    if ($alreadyHasClouds) {
        $done = $conn->prepare('UPDATE userdata SET setup_completed = 1 WHERE id = ?');
        $done->bind_param('i', $userId); $done->execute(); $done->close();
        $conn->commit(); $conn->close();
        kumo_setup_response(200, ['success' => true, 'already_setup' => true]);
    }

    $rootType = 'category'; $rootColor = 'blue';
    $rootName = (string)$config['root'];
    $rootIcon = (string)$config['icon'];
    $root = $conn->prepare('INSERT INTO clouds (user_id, parent_id, name, cloud_type, icon, color_theme) VALUES (?, NULL, ?, ?, ?, ?)');
    $root->bind_param('issss', $userId, $rootName, $rootType, $rootIcon, $rootColor);
    $root->execute(); $rootId = (int)$conn->insert_id; $root->close();

    $groupType = (string)$config['group_label'];
    $groupIcon = (string)$config['group_icon'];
    $groupTheme = (string)$config['group_theme'];
    $group = $conn->prepare('INSERT INTO clouds (user_id, parent_id, name, cloud_type, icon, color_theme) VALUES (?, ?, ?, ?, ?, ?)');
    $group->bind_param('iissss', $userId, $rootId, $groupName, $groupType, $groupIcon, $groupTheme);
    $group->execute(); $groupId = (int)$conn->insert_id; $group->close();

    $itemStmt = $conn->prepare('INSERT INTO clouds (user_id, parent_id, name, cloud_type, icon, color_theme) VALUES (?, ?, ?, ?, ?, ?)');
    $itemType = $config['items_type']; $itemIcon = '☁️'; $itemTheme = 'green';
    foreach ($items as $itemName) {
        $itemStmt->bind_param('iissss', $userId, $groupId, $itemName, $itemType, $itemIcon, $itemTheme);
        $itemStmt->execute();
    }
    $itemStmt->close();

    $done = $conn->prepare('UPDATE userdata SET setup_completed = 1 WHERE id = ?');
    $done->bind_param('i', $userId); $done->execute(); $done->close();
    $conn->commit(); $conn->close();
    kumo_setup_response(200, ['success' => true]);
} catch (Throwable $e) {
    $conn->rollback(); $conn->close();
    error_log('Kumo onboarding setup failed: ' . $e->getMessage());
    kumo_setup_response(500, ['success' => false, 'error' => 'Kumo could not finish setup. Please try again.']);
}
