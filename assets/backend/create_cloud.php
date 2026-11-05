<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

function kumo_cloud_json(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    kumo_cloud_json(405, ['success' => false, 'error' => 'Method not allowed.']);
}
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) {
    kumo_cloud_json(403, ['success' => false, 'error' => 'Your session expired. Refresh the page and try again.']);
}

$userId = (int)$_SESSION['user_id'];
$name = trim((string)($_POST['name'] ?? ''));
$location = (string)($_POST['location'] ?? 'root');
$currentCloudId = filter_var($_POST['current_cloud_id'] ?? '0', FILTER_VALIDATE_INT);
if ($name === '' || strlen($name) > 100 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
    kumo_cloud_json(422, ['success' => false, 'error' => 'Give your Cloud a name (up to 100 characters).']);
}
if (!in_array($location, ['root', 'current'], true) || $currentCloudId === false || $currentCloudId < 0) {
    kumo_cloud_json(422, ['success' => false, 'error' => 'Choose a valid location for this Cloud.']);
}

$parentId = null;
if ($location === 'current') {
    if ($currentCloudId < 1) {
        kumo_cloud_json(422, ['success' => false, 'error' => 'Open a Cloud before creating a nested Cloud.']);
    }
    $check = $conn->prepare('SELECT id FROM clouds WHERE id = ? AND user_id = ? LIMIT 1');
    $check->bind_param('ii', $currentCloudId, $userId);
    $check->execute();
    $ownedParent = $check->get_result()->fetch_assoc();
    $check->close();
    if (!$ownedParent) {
        kumo_cloud_json(403, ['success' => false, 'error' => 'That parent Cloud is not available to your account.']);
    }
    $parentId = (int)$ownedParent['id'];
}

try {
    $type = 'cloud';
    $icon = '☁️';
    $theme = 'green';
    if ($parentId === null) {
        $stmt = $conn->prepare('INSERT INTO clouds (user_id, parent_id, name, cloud_type, icon, color_theme) VALUES (?, NULL, ?, ?, ?, ?)');
        $stmt->bind_param('issss', $userId, $name, $type, $icon, $theme);
    } else {
        $stmt = $conn->prepare('INSERT INTO clouds (user_id, parent_id, name, cloud_type, icon, color_theme) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iissss', $userId, $parentId, $name, $type, $icon, $theme);
    }
    $stmt->execute();
    $newId = (int)$conn->insert_id;
    $stmt->close();
    $conn->close();
    $redirect = 'homepage.php?cloud_id=' . $newId;
    kumo_cloud_json(201, ['success' => true, 'id' => $newId, 'redirect' => $redirect]);
} catch (Throwable $e) {
    error_log('Kumo create cloud failed: ' . $e->getMessage());
    $conn->close();
    kumo_cloud_json(500, ['success' => false, 'error' => 'The Cloud could not be created. Please try again.']);
}
