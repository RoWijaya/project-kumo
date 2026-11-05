<?php
declare(strict_types=1);

// Reuse the same session freshness checks as the rest of the protected app.
// This prevents a demoted admin/owner from retaining access through a stale session.
$requiredRoles = $requiredRoles ?? [];
require_once __DIR__ . '/auth.php';

$currentRole = (string)($_SESSION['role'] ?? 'user');
if (!in_array($currentRole, $requiredRoles, true)) {
    http_response_code(403);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Access denied</title>';
    echo '<body style="font-family:system-ui;padding:3rem;background:#f3f6f1;color:#263b32">';
    echo '<h1>That space is not yours to enter.</h1><p>Your account does not have permission to view this page.</p>';
    echo '<p><a href="../../Main/homepage.php">Return to Kumo</a></p></body></html>';
    exit;
}
