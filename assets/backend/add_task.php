<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../../Main/homepage.php'); exit; }
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid request token. Refresh the page and try again.'); }
$userId = (int)$_SESSION['user_id'];
$cloudId = filter_var($_POST['cloud_id'] ?? null, FILTER_VALIDATE_INT);
$title = trim((string)($_POST['title'] ?? ''));
$dueDate = trim((string)($_POST['due_date'] ?? ''));
if (!$cloudId || $cloudId < 1 || $title === '' || strlen($title) > 255) { header('Location: ../../Main/homepage.php?error=task_invalid'); exit; }
if ($dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) { header('Location: ../../Main/homepage.php?error=task_invalid'); exit; }
$check = $conn->prepare('SELECT id FROM clouds WHERE id = ? AND user_id = ? LIMIT 1');
$check->bind_param('ii', $cloudId, $userId); $check->execute(); $owned = $check->get_result()->fetch_assoc(); $check->close();
if (!$owned) { http_response_code(403); exit('This Cloud is not available to your account.'); }
if ($dueDate === '') {
    $stmt = $conn->prepare('INSERT INTO tasks (user_id, cloud_id, title, due_date) VALUES (?, ?, ?, NULL)');
    $stmt->bind_param('iis', $userId, $cloudId, $title);
} else {
    $stmt = $conn->prepare('INSERT INTO tasks (user_id, cloud_id, title, due_date) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('iiss', $userId, $cloudId, $title, $dueDate);
}
$stmt->execute(); $stmt->close(); $conn->close();
header('Location: ../../Main/homepage.php?cloud_id=' . $cloudId); exit;
