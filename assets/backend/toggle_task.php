<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../../Main/homepage.php'); exit; }
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid request token. Refresh the page and try again.'); }
$userId = (int)$_SESSION['user_id'];
$taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);
$cloudId = filter_var($_POST['cloud_id'] ?? null, FILTER_VALIDATE_INT);
if (!$taskId || !$cloudId) { header('Location: ../../Main/homepage.php'); exit; }
$stmt = $conn->prepare('UPDATE tasks SET is_completed = CASE WHEN is_completed = 1 THEN 0 ELSE 1 END WHERE id = ? AND user_id = ? AND cloud_id = ?');
$stmt->bind_param('iii', $taskId, $userId, $cloudId); $stmt->execute(); $stmt->close(); $conn->close();
header('Location: ../../Main/homepage.php?cloud_id=' . $cloudId); exit;
