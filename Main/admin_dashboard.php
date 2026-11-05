<?php
declare(strict_types=1);

$requiredRoles = ['admin'];
require_once __DIR__ . '/../assets/backend/require_role.php';
require_once __DIR__ . '/../assets/backend/db.php';

$stats = ['users' => 0, 'admins' => 0];
$res = $conn->query("SELECT role, COUNT(*) AS total FROM userdata GROUP BY role");
while ($row = $res->fetch_assoc()) {
    if ($row['role'] === 'user') $stats['users'] = (int)$row['total'];
    if ($row['role'] === 'admin') $stats['admins'] = (int)$row['total'];
}

$list = $conn->query("SELECT id, username, email, role FROM userdata WHERE role = 'user' ORDER BY id DESC LIMIT 100");
$users = $list->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<title>Admin &middot; Kumo</title>
<script src="../assets/JS/theme.js?v=20261029-v3"></script>
<link rel="stylesheet" href="../assets/css/role-dashboard.css?v=20261029-v3">
</head>
<body>
<div class="shell">
  <header class="topbar">
    <a class="brand" href="homepage.php"><img src="../assets/icons/Kumo.svg" alt="" width="30" height="30"> <span>Kumo</span></a>
    <div class="top-meta"><span class="role-pill">Admin workspace</span><form action="../assets/backend/logout.php" method="post" class="logout-inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><button type="submit">Log out</button></form></div>
  </header>
  <main class="content">
    <p class="eyebrow">THE CONTROL ROOM</p>
    <h1>Good to see you, Admin.</h1>
    <p class="intro">A quiet overview of the people using Kumo. Admins can review ordinary user accounts.</p>

    <section class="stats">
      <article class="stat"><span>Users</span><strong><?= $stats['users'] ?></strong><small>Personal workspaces</small></article>
      
      <article class="stat"><span>Admins</span><strong><?= $stats['admins'] ?></strong><small>Administrative accounts</small></article>
    </section>

    <section class="panel">
      <div class="panel-heading"><div><p class="eyebrow">ACCOUNT DIRECTORY</p><h2>User accounts</h2></div><span class="count"><?= count($users) ?> shown</span></div>
      <p class="muted">Admin can review accounts here. Role escalation to admin or owner is reserved for the owner.</p>
      <div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead><tbody>
      <?php foreach ($users as $u): ?>
        <tr><td><?= htmlspecialchars($u['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><span class="role-tag"><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></span></td></tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="3" class="empty">No user accounts yet.</td></tr><?php endif; ?>
      </tbody></table></div>
    </section>
    <footer>Keep things tidy. Keep things kind. <span>Kumo / Admin</span></footer>
  </main>
</div>
    <script src="../assets/JS/vendor/gsap.min.js?v=3.15.0"></script>
    <script src="../assets/JS/vendor/ScrollTrigger.min.js?v=3.12.5"></script>
    <script src="../assets/JS/gsap-animations.js?v=20261105-v2"></script>
</body>
</html>
