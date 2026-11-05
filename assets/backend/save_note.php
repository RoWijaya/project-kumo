<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/note_sanitizer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../../Main/homepage.php'); exit; }
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid request token. Refresh the editor and try again.'); }
$userId = (int)$_SESSION['user_id'];
$cloudId = filter_var($_POST['cloud_id'] ?? null, FILTER_VALIDATE_INT);
$content = kumo_sanitize_note_html((string)($_POST['content'] ?? ''));
if (!$cloudId || $cloudId < 1 || strlen($content) > 5000000) { header('Location: ../../Main/homepage.php?error=note_invalid'); exit; }

$cloud = $conn->prepare('SELECT id FROM clouds WHERE id = ? AND user_id = ? LIMIT 1');
$cloud->bind_param('ii', $cloudId, $userId); $cloud->execute(); $ownedCloud = $cloud->get_result()->fetch_assoc(); $cloud->close();
if (!$ownedCloud) { http_response_code(403); exit('This Cloud is not available to your account.'); }

$stmt = $conn->prepare('INSERT INTO notes (user_id, cloud_id, content) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content), last_updated = CURRENT_TIMESTAMP');
$stmt->bind_param('iis', $userId, $cloudId, $content); $stmt->execute(); $stmt->close(); $conn->close();
header('Location: ../../Main/note_editor.php?cloud_id=' . $cloudId . '&saved=1'); exit;
