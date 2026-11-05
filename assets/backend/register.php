<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

function kumo_register_redirect(string $error): never {
    header('Location: ../../auth/register.html?error=' . rawurlencode($error));
    exit;
}

function kumo_register_is_ajax(): bool {
    return str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')
        || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function kumo_register_reply(string $error, string $message, string $field = '', int $status = 422): never {
    if (kumo_register_is_ajax()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => false, 'error' => $error, 'message' => $message, 'field' => $field], JSON_UNESCAPED_SLASHES);
        exit;
    }
    kumo_register_redirect($error);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../auth/register.html');
    exit;
}
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) {
    kumo_register_reply('csrf', 'Your secure form session expired. Refresh the page and try again.', '', 403);
}

$fullName = trim((string)($_POST['full_name'] ?? ''));
$username = trim((string)($_POST['username'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$password = (string)($_POST['password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

$fullNameLength = function_exists('mb_strlen') ? mb_strlen($fullName, 'UTF-8') : strlen($fullName);
if ($fullName === '' || $fullNameLength > 100 || preg_match('/[\x00-\x1F\x7F]/', $fullName)) {
    kumo_register_reply('invalid_name', 'Please enter your full name (up to 100 characters).', 'full_name');
}
if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
    kumo_register_reply('invalid_username', 'Use 3–50 characters: letters, numbers, dots, underscores, or hyphens.', 'username');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    kumo_register_reply('invalid_email', 'Please enter a valid email address.', 'email');
}
if ($password !== $confirmPassword) {
    kumo_register_reply('password_mismatch', 'Your passwords do not match. Please re-enter the confirmation.', 'confirm_password');
}
if (strlen($password) < 8 || strlen($password) > 20) {
    kumo_register_reply('password_length', 'Your password must be between 8 and 20 characters long.', 'password');
}
if (!preg_match('/[A-Z]/', $password)
    || !preg_match('/[a-z]/', $password)
    || !preg_match('/[0-9]/', $password)
    || !preg_match('/[^a-zA-Z0-9]/', $password)) {
    kumo_register_reply('password_complexity', 'Use at least one uppercase letter, one lowercase letter, one number, and one special character.', 'password');
}

try {
    require_once __DIR__ . '/db.php';

    $checkStmt = $conn->prepare('SELECT username, email FROM userdata WHERE email = ? OR username = ? LIMIT 1');
    $checkStmt->bind_param('ss', $email, $username);
    $checkStmt->execute();
    $existingAccount = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($existingAccount) {
        $duplicateField = strcasecmp((string)$existingAccount['email'], $email) === 0 ? 'email_exists' : 'username_exists';
        $conn->close();
        kumo_register_reply($duplicateField, $duplicateField === 'email_exists' ? 'An account with this email already exists.' : 'That username is already in use. Choose another one.', $duplicateField === 'email_exists' ? 'email' : 'username', 409);
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hashedPassword)) throw new RuntimeException('Password hashing failed.');

    $stmt = $conn->prepare('INSERT INTO userdata (username, full_name, email, password) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $username, $fullName, $email, $hashedPassword);
    $stmt->execute();
    $newUserId = (int)$conn->insert_id;
    $stmt->close();

    require_once __DIR__ . '/email_verification.php';
    $sent = kumo_create_email_verification($conn, $newUserId, $email);
    $conn->close();

    if (kumo_register_is_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if ($sent) {
            echo json_encode(['ok' => true, 'redirect' => '../../auth/verify-email.html?sent=1']);
        } else {
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => 'send_failed', 'message' => 'Your account was created, but Kumo could not send the verification email. Use the verification page to request another link.', 'field' => '', 'redirect' => '../../auth/verify-email.html?error=send_failed']);
        }
        exit;
    }
    header('Location: ../../auth/verify-email.html?' . ($sent ? 'sent=1' : 'error=send_failed'));
    exit;
} catch (mysqli_sql_exception $e) {
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    if ((int)$e->getCode() === 1062) {
        $field = stripos($e->getMessage(), 'username') !== false ? 'username_exists' : 'email_exists';
        kumo_register_reply($field, $field === 'username_exists' ? 'That username is already in use. Choose another one.' : 'An account with this email already exists.', $field === 'username_exists' ? 'username' : 'email', 409);
    }
    error_log('Kumo registration database error: ' . $e->getMessage());
    kumo_register_reply('database', 'A server error occurred. Please try again later.', '', 500);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    error_log('Kumo registration failed: ' . $e->getMessage());
    kumo_register_reply('database', 'A server error occurred. Please try again later.', '', 500);
}
