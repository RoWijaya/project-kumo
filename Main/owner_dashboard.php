<?php
declare(strict_types=1);

$requiredRoles = ['owner'];
require_once __DIR__ . '/../assets/backend/require_role.php';
require_once __DIR__ . '/../assets/backend/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$stats = ['user' => 0, 'admin' => 0, 'owner' => 0];
$res = $conn->query("SELECT role, COUNT(*) AS total FROM userdata GROUP BY role");
while ($row = $res->fetch_assoc()) {
    if (array_key_exists($row['role'], $stats)) $stats[$row['role']] = (int)$row['total'];
}
$list = $conn->query("SELECT id, username, email, role FROM userdata ORDER BY id DESC LIMIT 150");
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
<title>Owner &middot; Kumo</title>
<script src="../assets/JS/theme.js?v=20261029-v3"></script>
<link rel="stylesheet" href="../assets/css/role-dashboard.css?v=20261029-v3">
</head>
<body>
<div class="shell">
  <header class="topbar">
    <a class="brand" href="homepage.php"><img src="../assets/icons/Kumo.svg" alt="" width="30" height="30"> <span>Kumo</span></a>
    <div class="top-meta"><span class="role-pill owner-pill">Owner access</span><form action="../assets/backend/logout.php" method="post" class="logout-inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><button type="submit">Log out</button></form></div>
  </header>
  <main class="content">
    <p class="eyebrow">THE STEWARD'S DESK</p>
    <h1>Everything in its place.</h1>
    <p class="intro">The owner has the widest access in Kumo. Review the account landscape and assign roles carefully.</p>

    <?php if (isset($_GET['updated'])): ?><div class="notice">Role updated successfully.</div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div class="notice warning">That role change was blocked. Refresh and try again.</div><?php endif; ?>

    <section class="stats">
      <article class="stat"><span>Users</span><strong><?= $stats['user'] ?></strong><small>Personal workspaces</small></article>
          <article class="stat"><span>Admins</span><strong><?= $stats['admin'] ?></strong><small>Can manage regular accounts</small></article>
      <article class="stat"><span>Owners</span><strong><?= $stats['owner'] ?></strong><small>Highest access level</small></article>
    </section>

    <section class="panel">
      <div class="panel-heading"><div><p class="eyebrow">PEOPLE & PERMISSIONS</p><h2>Account directory</h2></div><span class="count"><?= count($users) ?> shown</span></div>
      <p class="muted">You can assign user or admin. Owner accounts are protected from changes in this form.</p>
      <div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Current role</th><th>Change role</th></tr></thead><tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><span class="role-tag"><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></span></td>
          <td>
          <?php if ($u['role'] !== 'owner' && (int)$u['id'] !== (int)$_SESSION['user_id']): ?>
            <form class="role-form" method="post" action="../assets/backend/update_user_role.php">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <select name="role" aria-label="New role for <?= htmlspecialchars($u['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <?php foreach (['user','admin'] as $r): ?>
                  <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit">Save</button>
            </form>
          <?php else: ?><span class="muted">Protected</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="4" class="empty">No accounts found.</td></tr><?php endif; ?>
      </tbody></table></div>
    </section>
    <footer>You're keeping the clouds in order. <span>Kumo / Owner</span></footer>
  </main>
</div>
    <script src="../assets/JS/vendor/gsap.min.js?v=3.15.0"></script>
    <script src="../assets/JS/vendor/ScrollTrigger.min.js?v=3.12.5"></script>
    <script src="../assets/JS/gsap-animations.js?v=20261105-v2"></script>
</body>
</html>
