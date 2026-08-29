<?php

require_once __DIR__ . '/db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../auth/register.html");
    exit();
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($password !== $confirmPassword) {
    header("Location: ../../auth/register.html?error=password_mismatch");
    exit();
}

$checkStmt = $conn->prepare(
    "SELECT id FROM userdata WHERE email = ?"
);

$checkStmt->bind_param("s", $email);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    $checkStmt->close();
    $conn->close();

    header("Location: ../../auth/register.html?error=email_exists");
    exit();
}

$checkStmt->close();

$length = strlen($password);
if ($length < 8 || $length > 20) {
    header("Location: ../../auth/register.html?error=password_length");
    exit();
}

if (!preg_match('/[A-Z]/', $password) ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[0-9]/', $password) ||
    !preg_match('/[\W_]/', $password)
) {
    header("Location: ../../auth/register.html?error=password_complexity");
    exit();
}   

$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);


$stmt = $conn->prepare(
    "INSERT INTO userdata (username, email, password)
     VALUES (?, ?, ?)"
);

$stmt->bind_param(
    "sss",
    $username,
    $email,
    $hashedPassword
);

if ($stmt->execute()) {

    $stmt->close();
    $conn->close();

    header("Location: ../../auth/login.html?registered=1");
    exit();
}

$stmt->close();
$conn->close();

header("Location: ../../auth/register.html?error=database");
exit();