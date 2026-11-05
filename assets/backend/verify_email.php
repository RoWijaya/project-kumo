<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$token = trim((string)($method === 'POST' ? ($_POST['token'] ?? '') : ($_GET['token'] ?? '')));
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    header('Location: ../../auth/verify-email.html?error=invalid_token'); exit;
}

/**
 * Render the inline confirmation page (GET: confirm button; POST: result).
 * Uses auth.css for consistent branding instead of bare inline styles.
 */
function kumo_render_verify_page(string $token, string $state, string $heading, string $body, string $csrfHtml = ''): void {
    $stateClass = match($state) {
        'success' => 'success', 'error' => 'error', 'warning' => 'warning', default => ''
    };
    $icon = match($state) {
        'success' => 'mark_email_read', 'error' => 'error', 'warning' => 'schedule', default => 'mail'
    };
    $safeHeading = htmlspecialchars($heading, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
    $safeBody    = htmlspecialchars($body,    ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
    header('Content-Type: text/html; charset=UTF-8');
    // Inline action button only shown on GET (confirm step).
    $actionHtml = '';
    if ($csrfHtml !== '' && $state === 'pending') {
        $safeToken  = htmlspecialchars($token, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        $actionHtml = '<form method="post" action="verify_email.php" style="margin-top:24px">'
            . $csrfHtml
            . '<input type="hidden" name="token" value="' . $safeToken . '">'
            . '<button class="primary-btn" type="submit">Verify my email address</button>'
            . '</form>';
    }
    $loginLink = $state === 'success'
        ? '<div class="footer" style="margin-top:20px"><a href="../../auth/login.html" style="color:var(--auth-accent-dark);font-weight:600">Sign in to Kumo &rarr;</a></div>'
        : '<div class="footer" style="margin-top:20px"><a href="../../auth/verify-email.html" style="color:var(--auth-accent-dark);font-weight:600">Request a new link</a></div>';
    if ($state === 'success') $loginLink = '<div class="footer" style="margin-top:20px"><p><a href="../../auth/login.html" style="color:var(--auth-accent-dark);font-weight:600">Sign in to Kumo &rarr;</a></p></div>';
    echo <<<HTML
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <script src="../../assets/JS/theme.js?v=20261029-v3"></script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Verify your Kumo email</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@400&display=block">
  <link rel="stylesheet" href="../../assets/css/auth.css?v=20261029-v3">
</head>
<body style="display:grid;place-items:center;min-height:100vh;padding:24px">
  <main style="width:min(100%,440px);padding:36px;border:1px solid var(--auth-line);border-radius:24px;background:var(--auth-surface);box-shadow:var(--auth-shadow);animation:authEnter .32s cubic-bezier(.2,.8,.2,1) both">
    <a href="../../index.html" style="display:block;width:88px;margin-bottom:28px"><img src="../../assets/icons/Kumo.svg" alt="Kumo" style="width:88px;max-height:26px;object-fit:contain;object-position:left center"></a>
    <div class="icon-circle {$stateClass}"><span class="material-symbols-outlined">{$icon}</span></div>
    <div class="title"><h1>{$safeHeading}</h1><p>{$safeBody}</p></div>
    {$actionHtml}
    {$loginLink}
  </main>
</body></html>
HTML;
}

try {
    require_once __DIR__ . '/db.php';
    $tokenHash = hash('sha256', $token);

    if ($method === 'GET') {
        // Confirm the token exists and is valid before showing the button.
        $stmt = $conn->prepare('SELECT user_id FROM email_verification_tokens WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1');
        $stmt->bind_param('s', $tokenHash); $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close(); $conn->close();
        if (!$row) {
            header('Location: ../../auth/verify-email.html?error=invalid_token'); exit;
        }
        $csrf = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . '">';
        kumo_render_verify_page($token, 'pending', 'Confirm your email.', 'Press the button below to verify your email address and activate your Kumo account.', $csrf);
        exit;
    }

    if ($method !== 'POST' || !kumo_csrf_valid($_POST['csrf_token'] ?? null)) {
        header('Location: ../../auth/verify-email.html?error=csrf'); exit;
    }

    $conn->begin_transaction();
    $stmt = $conn->prepare('SELECT user_id FROM email_verification_tokens WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1 FOR UPDATE');
    $stmt->bind_param('s', $tokenHash); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) {
        $conn->rollback(); $conn->close();
        header('Location: ../../auth/verify-email.html?error=invalid_token'); exit;
    }
    $userId = (int)$row['user_id'];

    // Check if already verified
    $checkStmt = $conn->prepare('SELECT email_verified_at FROM userdata WHERE id = ? LIMIT 1');
    $checkStmt->bind_param('i', $userId); $checkStmt->execute();
    $checkRow = $checkStmt->get_result()->fetch_assoc(); $checkStmt->close();

    if ($checkRow && !empty($checkRow['email_verified_at'])) {
        // Already verified — clean up token but don't change account state
        $delete = $conn->prepare('DELETE FROM email_verification_tokens WHERE user_id = ?');
        $delete->bind_param('i', $userId); $delete->execute(); $delete->close();
        $conn->commit(); $conn->close();
        header('Location: ../../auth/login.html?verified=1'); exit;
    }

    $update = $conn->prepare('UPDATE userdata SET email_verified_at = UTC_TIMESTAMP() WHERE id = ? AND email_verified_at IS NULL');
    $update->bind_param('i', $userId); $update->execute();
    $affectedRows = $conn->affected_rows;
    $update->close();

    $delete = $conn->prepare('DELETE FROM email_verification_tokens WHERE user_id = ?');
    $delete->bind_param('i', $userId); $delete->execute(); $delete->close();

    $conn->commit(); $conn->close();

    if ($affectedRows > 0) {
        header('Location: ../../auth/login.html?verified=1'); exit;
    } else {
        // No rows affected means it was already verified between our check and update
        header('Location: ../../auth/login.html?verified=1'); exit;
    }
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        $conn->close();
    }
    error_log('Kumo email verification failed: ' . $e->getMessage());
    header('Location: ../../auth/verify-email.html?error=server'); exit;
}
