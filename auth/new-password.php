<?php
declare(strict_types=1);
require_once __DIR__ . '/../assets/backend/security.php';
$token = trim((string)($_GET['token'] ?? ''));
$error = (string)($_GET['error'] ?? '');
$validToken = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$messages = [
    'password_mismatch' => "Your passwords don't match. Please try again.",
    'password_length' => 'Your password must be between 8 and 20 characters long.',
    'password_complexity' => 'Use uppercase and lowercase letters, a number, and a special character.',
];
?>
<!doctype html>
<html lang="en">
<head>
  <script src="../assets/JS/theme.js?v=20261029-v3"></script>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@400&display=block" />
  <title>Choose a new password · Kumo</title>
  
    
    
    <link rel="stylesheet" href="../assets/css/auth.css?v=20261029-v4">
  <link rel="stylesheet" href="../assets/css/kumo-background.css?v=20261029-v3">
  

</head>
<body>
  <a href="../index.html" class="kumo-btn" aria-label="Back to Kumo home"><img src="../assets/icons/kumo_fluffy_cloud_symbol.svg" alt="Kumo" /></a>
  <nav class="auth-nav" aria-label="Account navigation"><a href="register.html" class="auth-nav-link">Sign Up</a><a href="login.html" class="auth-nav-pill">Login</a></nav>
  <div class="container"><div class="left-panel"><div class="auth-story"><p class="auth-story-kicker">A fresh start</p><h2>Let&rsquo;s get you back.</h2><p>Choose a strong new password and get back to the work you were keeping safe in Kumo.</p><div class="auth-story-notes"><div><strong>Secure by design</strong><span>Use a password only you know.</span></div></div><p class="auth-story-quote">Your ideas will be right where you left them.</p></div></div><div class="right-panel"><div class="auth-card">
    <a href="../index.html" class="brand" aria-label="Kumo home"><img src="../assets/icons/kumo_fluffy_cloud_symbol.svg" alt="Kumo" /></a>
    <div class="icon-circle"><span class="material-symbols-outlined">lock_reset</span></div>
    <div class="title"><h1>Choose a new password.</h1><p>Use a password you have not used before.</p></div>
    <?php if (!$validToken): ?>
      <div class="alert-box alert-error"><span class="alert-text">This reset link is missing or invalid. Request a new one from the login page.</span></div>
      <a class="primary-btn" href="reset-password.html" style="display:block;text-align:center;text-decoration:none">Request a new link</a>
    <?php else: ?>
      <?php if (isset($messages[$error])): ?><div class="alert-box alert-error"><span class="alert-text"><?= htmlspecialchars($messages[$error], ENT_QUOTES, 'UTF-8') ?></span></div><?php endif; ?>
      <form class="auth-form" action="../assets/backend/resetpassword.php" method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="input-group"><span class="material-symbols-outlined input-icon">lock</span><input type="password" name="password" placeholder="New password" minlength="8" maxlength="20" autocomplete="new-password" required></div>
        <div class="input-group"><span class="material-symbols-outlined input-icon">lock</span><input type="password" name="confirm_password" placeholder="Confirm new password" minlength="8" maxlength="20" autocomplete="new-password" required></div>
        <button class="primary-btn" type="submit">Update Password</button>
      </form>
    <?php endif; ?>
    <div class="footer"><a href="login.html">← Back to Login</a></div>
  </div></div></div>
    <script src="../assets/JS/vendor/gsap.min.js?v=3.15.0"></script>
    <script src="../assets/JS/vendor/ScrollTrigger.min.js?v=3.12.5"></script>
    <script src="../assets/JS/gsap-animations.js?v=20261105-v2"></script>
</body></html>
