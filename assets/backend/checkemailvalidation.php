<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit('method not allowed'); }
$email = strtolower(trim((string)($_POST['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(422); exit('invalid'); }
$stmt = $conn->prepare('SELECT id FROM userdata WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email); $stmt->execute(); $stmt->store_result();
echo $stmt->num_rows > 0 ? 'exists' : 'not exists';
$stmt->close(); $conn->close();
