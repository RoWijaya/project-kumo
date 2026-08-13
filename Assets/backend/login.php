<?php

require_once __DIR__ . '/db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../../auth/login.html");
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare(
    "SELECT id, password FROM userdata WHERE email = ?"
);

$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 1) {

    $userId = 0;
    $dbPassword = "";

    $stmt->bind_result($userId, $dbPassword);
    $stmt->fetch();

    if (password_verify($password, $dbPassword)) {

        session_start();
        session_regenerate_id(true);

        $_SESSION['user_id'] = $userId;

        $stmt->close();
        $conn->close();

        header("Location: ../../Main/homepage.php");
        exit();
    }
}

$stmt->close();
$conn->close();

header("Location: ../../auth/login.html?error=invalid");
exit();