<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
echo json_encode(['csrf_token' => (string)($_SESSION['csrf_token'] ?? '')], JSON_UNESCAPED_SLASHES);
