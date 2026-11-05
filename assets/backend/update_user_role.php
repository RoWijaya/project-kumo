<?php
declare(strict_types=1);

$requiredRoles = ['owner'];
require_once __DIR__ . '/require_role.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// CSRF token is required by the owner dashboard form.
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid request token. Refresh the owner dashboard and try again.');
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$newRole = $_POST['role'] ?? '';
$allowedRoles = ['user', 'admin'];

if (!$userId || !in_array($newRole, $allowedRoles, true)) {
    header('Location: ../../Main/owner_dashboard.php?error=invalid');
    exit;
}

// Owner accounts are deliberately not editable through this form.
$check = $conn->prepare('SELECT role FROM userdata WHERE id = ?');
$check->bind_param('i', $userId);
$check->execute();
$target = $check->get_result()->fetch_assoc();
$check->close();

if (!$target || $target['role'] === 'owner' || $userId === (int)$_SESSION['user_id']) {
    header('Location: ../../Main/owner_dashboard.php?error=protected');
    exit;
}

$update = $conn->prepare('UPDATE userdata SET role = ? WHERE id = ?');
$update->bind_param('si', $newRole, $userId);
$update->execute();
$update->close();
$conn->close();

header('Location: ../../Main/owner_dashboard.php?updated=1');
exit;
