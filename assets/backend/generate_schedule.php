<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../../Main/homepage.php'); exit; }
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid request token. Refresh the page and try again.'); }
$userId = (int)$_SESSION['user_id'];
$parentCloudId = filter_var($_POST['cloud_id'] ?? null, FILTER_VALIDATE_INT);
$classDay = strtolower(trim((string)($_POST['class_day'] ?? '')));
$midtermWeek = filter_var($_POST['midterm_week'] ?? null, FILTER_VALIDATE_INT);
$finalWeek = filter_var($_POST['final_week'] ?? null, FILTER_VALIDATE_INT);
$allowedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
if (!$parentCloudId || !in_array($classDay, $allowedDays, true) || !$midtermWeek || !$finalWeek
    || $midtermWeek < 1 || $finalWeek > 30 || $midtermWeek >= $finalWeek) {
    header('Location: ../../Main/homepage.php?cloud_id=' . max(0, (int)$parentCloudId) . '&error=schedule_invalid'); exit;
}

$check = $conn->prepare('SELECT id FROM clouds WHERE id = ? AND user_id = ? LIMIT 1');
$check->bind_param('ii', $parentCloudId, $userId); $check->execute(); $ownedClass = $check->get_result()->fetch_assoc(); $check->close();
if (!$ownedClass) { http_response_code(403); exit('This Cloud is not available to your account.'); }

try {
    $conn->begin_transaction();
    $find = $conn->prepare('SELECT id FROM class_schedules WHERE cloud_id = ? AND user_id = ? LIMIT 1');
    $find->bind_param('ii', $parentCloudId, $userId); $find->execute();
    $exists = $find->get_result()->fetch_assoc(); $find->close();
    if ($exists) {
        $conn->rollback(); $conn->close();
        header('Location: ../../Main/homepage.php?cloud_id=' . $parentCloudId . '&error=schedule_exists'); exit;
    }
    $schedule = $conn->prepare('INSERT INTO class_schedules (user_id, cloud_id, class_day, midterm_week, final_week) VALUES (?, ?, ?, ?, ?)');
    $schedule->bind_param('iisii', $userId, $parentCloudId, $classDay, $midtermWeek, $finalWeek); $schedule->execute(); $schedule->close();

    $insert = $conn->prepare("INSERT INTO clouds (user_id, parent_id, name, cloud_type, icon, color_theme) VALUES (?, ?, ?, 'week', '📘', 'blue')");
    for ($i = 1; $i <= $finalWeek; $i++) {
        $name = $i === $midtermWeek ? 'Midterm Week' : ($i === $finalWeek ? 'Finals Week' : 'Week ' . $i);
        $insert->bind_param('iis', $userId, $parentCloudId, $name);
        $insert->execute();
    }
    $insert->close();
    $conn->commit(); $conn->close();
    header('Location: ../../Main/homepage.php?cloud_id=' . $parentCloudId . '&schedule=created'); exit;
} catch (Throwable $e) {
    $conn->rollback(); $conn->close();
    error_log('Kumo schedule generation failed: ' . $e->getMessage());
    header('Location: ../../Main/homepage.php?cloud_id=' . $parentCloudId . '&error=schedule_failed'); exit;
}
