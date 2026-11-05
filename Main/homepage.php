<?php
require_once __DIR__ . '/../assets/backend/auth.php';
require_once __DIR__ . '/../assets/backend/db.php';

if (!function_exists('kumo_initial_char')) {
    function kumo_initial_char(string $value): string {
        if ($value === '') return 'K';
        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) return mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8');
        return strtoupper(substr($value, 0, 1));
    }
}

$userId = (int)$_SESSION['user_id'];

// Resolve the signed-in account only by its stable primary key. A cached username
// or a request-supplied email must never decide which profile is displayed.
$userColumns = [];
try {
    $columnsResult = $conn->query("SHOW COLUMNS FROM userdata");
    while ($column = $columnsResult->fetch_assoc()) $userColumns[(string)$column['Field']] = true;
    $columnsResult->free();
} catch (Throwable $columnError) {
    error_log('Kumo could not inspect available profile fields.');
}
$userSelect = ['username', 'email'];
foreach (['full_name', 'avatar_path', 'created_at', 'setup_completed', 'email_verified_at'] as $optionalColumn) {
    if (isset($userColumns[$optionalColumn])) $userSelect[] = $optionalColumn;
}
$stmt = $conn->prepare('SELECT ' . implode(', ', $userSelect) . ' FROM userdata WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();
if (!$user) {
    $_SESSION = [];
    session_regenerate_id(true);
    header('Location: ../auth/login.html?error=session_expired');
    exit;
}
$displayName = trim((string)($user['full_name'] ?? ''));
if ($displayName === '') $displayName = trim((string)($user['username'] ?? ''));
if ($displayName === '') $displayName = 'there';
$hourOfDay = (int)date('G');
$greeting = $hourOfDay < 12 ? 'Good morning' : ($hourOfDay < 18 ? 'Good afternoon' : 'Good evening');
$_SESSION['display_name'] = $displayName;
$isFirstTimeUser = (int)($user['setup_completed'] ?? 1) === 0;

$avatarPath = (string)($user['avatar_path'] ?? '');
if (!preg_match('~^assets/uploads/avatars/[a-f0-9]{40}\.(?:jpg|png|webp)$~', $avatarPath)) $avatarPath = '';
$avatarUrl = $avatarPath !== '' ? '../' . $avatarPath : '';

$preferences = [
    'theme' => 'light', 'accent_color' => 'sage', 'density' => 'comfortable',
    'reduce_motion' => 0, 'show_clouds' => 1, 'show_upcoming' => 1,
    'show_recent_activity' => 1, 'show_progress' => 1,
    'first_section' => 'clouds',
];
try {
    $prefStmt = $conn->prepare('SELECT theme, accent_color, density, reduce_motion, show_clouds, show_upcoming, show_recent_activity, show_progress, first_section FROM user_preferences WHERE user_id = ? LIMIT 1');
    $prefStmt->bind_param('i', $userId);
    $prefStmt->execute();
    $storedPreferences = $prefStmt->get_result()->fetch_assoc();
    $prefStmt->close();
    if (is_array($storedPreferences)) $preferences = array_merge($preferences, $storedPreferences);
} catch (Throwable $preferencesNotMigrated) {
    // The dashboard remains usable with safe defaults until the additive preferences migration is applied.
}
$allowedThemes = ['light', 'dark', 'system'];
if (!in_array((string)$preferences['theme'], $allowedThemes, true)) $preferences['theme'] = 'light';
$allowedAccents = ['sage', 'forest', 'ocean', 'lavender'];
if (!in_array((string)$preferences['accent_color'], $allowedAccents, true)) $preferences['accent_color'] = 'sage';
$preferences['reduce_motion'] = (int)$preferences['reduce_motion'] === 1 ? 1 : 0;
foreach (['show_clouds', 'show_upcoming', 'show_recent_activity', 'show_progress'] as $flag) {
    $preferences[$flag] = (int)$preferences[$flag] === 1 ? 1 : 0;
}

$currentCloudId = isset($_GET['cloud_id']) ? (int)$_GET['cloud_id'] : null;
$currentCloudName = "Your Clouds";
$parentCloudId = null;

if ($currentCloudId) {
    $infoStmt = $conn->prepare("SELECT name, parent_id FROM clouds WHERE id = ? AND user_id = ?");
    $infoStmt->bind_param("ii", $currentCloudId, $userId);
    $infoStmt->execute();
    $infoResult = $infoStmt->get_result();
    if ($cloudInfo = $infoResult->fetch_assoc()) {
        $currentCloudName = $cloudInfo['name'];
        $parentCloudId = $cloudInfo['parent_id'];
    } else {
        $infoStmt->close();
        header('Location: homepage.php?error=cloud_not_found');
        exit;
    }
    $infoStmt->close();
}

if ($currentCloudId) {
    $gridStmt = $conn->prepare("SELECT id, name, cloud_type, updated_at FROM clouds WHERE user_id = ? AND parent_id = ? ORDER BY updated_at DESC, id DESC");
    $gridStmt->bind_param("ii", $userId, $currentCloudId);
} else {
    $gridStmt = $conn->prepare("SELECT id, name, cloud_type, updated_at FROM clouds WHERE user_id = ? AND parent_id IS NULL ORDER BY updated_at DESC, id DESC");
    $gridStmt->bind_param("i", $userId);
}
$gridStmt->execute();
$gridResult = $gridStmt->get_result();
$displayClouds = [];
while ($row = $gridResult->fetch_assoc()) {
    $displayClouds[] = $row;
}
$gridStmt->close();

$allCloudsStmt = $conn->prepare("SELECT id, name, parent_id, cloud_type, updated_at FROM clouds WHERE user_id = ? ORDER BY id ASC");
$allCloudsStmt->bind_param("i", $userId);
$allCloudsStmt->execute();
$allCloudsResult = $allCloudsStmt->get_result();
$allUserClouds = [];
while ($row = $allCloudsResult->fetch_assoc()) {
    $allUserClouds[] = $row;
}

$tasksStmt = $conn->prepare("SELECT id, title, due_date, is_completed FROM tasks WHERE user_id = ? AND cloud_id = ? ORDER BY due_date ASC");
$tasksStmt->bind_param("ii", $userId, $currentCloudId);
$tasksStmt->execute();
$tasksResult = $tasksStmt->get_result();
$currentTasks = [];
while ($row = $tasksResult->fetch_assoc()) {
    $currentTasks[] = $row;
}
$tasksStmt->close();

$noteStmt = $conn->prepare("SELECT content FROM notes WHERE user_id = ? AND cloud_id = ?");
$noteStmt->bind_param("ii", $userId, $currentCloudId);
$noteStmt->execute();
$noteResult = $noteStmt->get_result();
$currentNote = '';
if ($row = $noteResult->fetch_assoc()) {
    $currentNote = $row['content'];
}
$noteStmt->close();
$allCloudsStmt->close();

$currentDepth = 0;
$tempPId = $parentCloudId;
while ($tempPId) {
    $currentDepth++;
    $nextPId = null;
    foreach ($allUserClouds as $c) {
        if ($c['id'] == $tempPId) {
            $nextPId = $c['parent_id'];
            break;
        }
    }
    $tempPId = $nextPId;
}

// Summary and upcoming items are derived exclusively from this user's records.
$totalClouds = 0;
$pendingTasks = 0;
$recentClouds = [];
$recentNotes = [];
$upcomingTasks = [];
$taskPreview = [];
$taskProgress = 0;
$dashboardDataFailed = false;
try {
    $countStmt = $conn->prepare('SELECT COUNT(*) AS n FROM clouds WHERE user_id = ?');
    $countStmt->bind_param('i', $userId); $countStmt->execute();
    $totalClouds = (int)($countStmt->get_result()->fetch_assoc()['n'] ?? 0); $countStmt->close();
    $countStmt = $conn->prepare('SELECT COUNT(*) AS n FROM tasks WHERE user_id = ? AND is_completed = 0');
    $countStmt->bind_param('i', $userId); $countStmt->execute();
    $pendingTasks = (int)($countStmt->get_result()->fetch_assoc()['n'] ?? 0); $countStmt->close();
    $taskCountStmt = $conn->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(is_completed = 1), 0) AS completed FROM tasks WHERE user_id = ?');
    $taskCountStmt->bind_param('i', $userId); $taskCountStmt->execute();
    $taskCounts = $taskCountStmt->get_result()->fetch_assoc() ?: ['total' => 0, 'completed' => 0];
    $taskCountStmt->close();
    $taskProgress = (int)$taskCounts['total'] > 0 ? (int)round(((int)$taskCounts['completed'] / (int)$taskCounts['total']) * 100) : 0;
    $recentStmt = $conn->prepare('SELECT id, name, cloud_type, updated_at FROM clouds WHERE user_id = ? ORDER BY updated_at DESC, id DESC LIMIT 4');
    $recentStmt->bind_param('i', $userId); $recentStmt->execute();
    $recentResult = $recentStmt->get_result();
    while ($recent = $recentResult->fetch_assoc()) $recentClouds[] = $recent;
    $recentStmt->close();
    $upcomingStmt = $conn->prepare('SELECT t.id, t.title, t.due_date, c.name AS cloud_name FROM tasks t INNER JOIN clouds c ON c.id = t.cloud_id AND c.user_id = t.user_id WHERE t.user_id = ? AND t.is_completed = 0 AND t.due_date >= CURDATE() ORDER BY t.due_date ASC, t.id ASC LIMIT 4');
    $upcomingStmt->bind_param('i', $userId); $upcomingStmt->execute();
    $upcomingResult = $upcomingStmt->get_result();
    while ($upcoming = $upcomingResult->fetch_assoc()) $upcomingTasks[] = $upcoming;
    $upcomingStmt->close();
    $previewStmt = $conn->prepare('SELECT t.id, t.title, t.due_date, t.is_completed, c.id AS cloud_id, c.name AS cloud_name FROM tasks t INNER JOIN clouds c ON c.id = t.cloud_id AND c.user_id = t.user_id WHERE t.user_id = ? AND t.is_completed = 0 ORDER BY (t.due_date IS NULL), t.due_date ASC, t.id DESC LIMIT 3');
    $previewStmt->bind_param('i', $userId); $previewStmt->execute();
    $previewResult = $previewStmt->get_result();
    while ($task = $previewResult->fetch_assoc()) $taskPreview[] = $task;
    $previewStmt->close();
    $recentNotesStmt = $conn->prepare("SELECT n.cloud_id, n.last_updated, c.name AS cloud_name, LEFT(REPLACE(REPLACE(n.content, '<', ' '), '>', ' '), 180) AS note_preview FROM notes n INNER JOIN clouds c ON c.id = n.cloud_id AND c.user_id = n.user_id WHERE n.user_id = ? AND n.content <> '' ORDER BY n.last_updated DESC LIMIT 3");
    $recentNotesStmt->bind_param('i', $userId); $recentNotesStmt->execute();
    $recentNotesResult = $recentNotesStmt->get_result();
    while ($note = $recentNotesResult->fetch_assoc()) $recentNotes[] = $note;
    $recentNotesStmt->close();
} catch (Throwable $dashboardDataError) {
    $dashboardDataFailed = true;
    error_log('Kumo dashboard summary query failed: ' . $dashboardDataError->getMessage());
}

if (!function_exists('renderCloudTree')) {
    function renderCloudTree($clouds, $parentId = null, $currentCloudId = null, $level = 0) {
        $html = '';
        foreach ($clouds as $cloud) {
            if ($cloud['parent_id'] == $parentId) {
                $hasChildren = false;
                foreach ($clouds as $c) {
                    if ($c['parent_id'] == $cloud['id']) {
                        $hasChildren = true;
                        break;
                    }
                }

                $pad = 1 + ($level * 1.5); 
                $isActive = ($currentCloudId == $cloud['id']) ? 'active' : '';

                if ($hasChildren) {
                    $html .= '<details class="cloud-folder" data-cloud-id="' . $cloud['id'] . '">'; 
                    $html .= '<summary class="nav-link ' . $isActive . '" style="padding-left: ' . $pad . 'rem; cursor: pointer;">';
                    $html .= '<span class="icon">☁</span> <span style="flex: 1;">' . htmlspecialchars($cloud['name']) . '</span>';
                    $html .= '</summary>';
                    $html .= '<div class="folder-content">';
                    $html .= renderCloudTree($clouds, $cloud['id'], $currentCloudId, $level + 1);
                    $html .= '</div>';
                    $html .= '</details>';
                } else {
                    $html .= '<a href="homepage.php?cloud_id=' . $cloud['id'] . '" class="nav-link ' . $isActive . '" style="padding-left: ' . $pad . 'rem;">';
                    $html .= '<span class="icon">☁</span> <span style="flex: 1;">' . htmlspecialchars($cloud['name']) . '</span>';
                    $html .= '</a>';
                }
            }
        }
        return $html;
    }
}

$conn->close();
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/svg+xml" href="../assets/icons/kumo_fluffy_cloud_icon.svg" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
    <meta name="current-cloud-id" content="<?= (int)($currentCloudId ?? 0) ?>" />
    <meta name="kumo-theme" content="<?= htmlspecialchars((string)$preferences['theme'], ENT_QUOTES, 'UTF-8') ?>" />
    <meta name="kumo-accent" content="<?= htmlspecialchars((string)$preferences['accent_color'], ENT_QUOTES, 'UTF-8') ?>" />
    <meta name="kumo-density" content="<?= htmlspecialchars((string)$preferences['density'], ENT_QUOTES, 'UTF-8') ?>" />
    <meta name="kumo-reduce-motion" content="<?= (int)$preferences['reduce_motion'] ?>" />
    <title>Kumo</title>
    <script src="../assets/JS/theme.js?v=20261029-v4"></script>
    <link rel="stylesheet" href="../assets/css/main.css?v=20261029-v11" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />

  </head>

  <body>
    <div class="app sidebar-open" id="kumo-app">
      <button class="sidebar-peek" id="sidebar-peek" type="button" aria-label="Collapse navigation" aria-expanded="true" title="Collapse navigation">
        <span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
      </button>
      <aside class="sidebar" id="kumo-sidebar" aria-label="Main navigation">
        <a href="homepage.php" class="sidebar-brand">
          <img src="../assets/icons/kumo_fluffy_cloud_symbol.svg" alt="" class="sidebar-brand-icon" aria-hidden="true" />
          <span class="kumo-logo">Kumo</span>
        </a>

        <nav class="nav">
          <a href="homepage.php" class="nav-link <?= !$currentCloudId ? 'active' : '' ?>">
            <span class="icon">⌂</span>
            <span style="flex: 1;">My Sky</span>
          </a>

          <a href="#search-input" class="nav-link" id="sidebar-search">
            <span class="icon">⌕</span>
            <span style="flex: 1;">Search</span>
          </a>

          <span class="nav-link" aria-disabled="true" title="Favorites are coming soon" style="opacity:.62;cursor:default">
            <span class="icon">☆</span>
            <span style="flex: 1;">Favorites · soon</span>
          </span>

          <div class="divider"></div>

          <div class="section-title">
            <span class="icon">☁</span>
            YOUR CLOUDS
          </div>

          <div id="cloud-tree">
            <?= renderCloudTree($allUserClouds, null, $currentCloudId, 0) ?>
          </div>

          <button class="create-cloud-btn" id="create-cloud-sidebar">
            + Create Cloud
          </button>

          <div class="divider"></div>

          <div class="section-title">
            <span class="icon">♧</span>
            COMMUNITY
          </div>

          <button type="button" class="nav-link atmosphere-trigger" id="join-atmosphere-trigger">
            <span class="icon">＋</span><span style="flex:1">Join an Atmosphere</span><small class="coming-soon-pill">Soon</small>
          </button>
        </nav>

        <div class="user-area">
          <button class="user" id="profile-menu-button" type="button" title="Open profile and settings">
            <?php if ($avatarUrl !== ''): ?><img class="avatar avatar-image" src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" /><?php else: ?><span class="avatar avatar-fallback" aria-hidden="true"><?= htmlspecialchars(kumo_initial_char($displayName), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
            <span class="user-display-name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
          </button>
          <button class="settings" type="button" aria-label="Open settings" title="Settings">⚙</button>
          <form class="logout-form" action="../assets/backend/logout.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
            <button type="submit" class="logout-button"><span aria-hidden="true">↗</span> Log out</button>
          </form>
        </div>
      </aside>

      <main class="main <?= !$currentCloudId ? 'dashboard-home' : 'cloud-view' ?><?= $dashboardDataFailed ? ' has-dashboard-error' : '' ?>" data-first-section="<?= htmlspecialchars((string)($preferences['first_section'] ?? 'clouds'), ENT_QUOTES, 'UTF-8') ?>">
        <div class="search">
          <input type="search" placeholder="Search your Clouds..." id="search-input" aria-label="Search your Clouds" />
          <span class="shortcut"> Ctrl + K </span>
          <button type="button" id="theme-toggle" class="theme-toggle-top" aria-label="Switch to dark theme" title="Switch to dark theme" aria-pressed="false"><span class="material-symbols-outlined" aria-hidden="true">dark_mode</span></button>
        </div>

        <section class="welcome">
          <div>
            <p class="eyebrow welcome-date"><time id="local-date" datetime="<?= date('Y-m-d') ?>"><?= htmlspecialchars(strtoupper(date('l, F j, Y')), ENT_QUOTES, 'UTF-8') ?></time></p>
            <h1><?= htmlspecialchars($greeting, ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?> <span class="welcome-sparkle" aria-hidden="true">✳</span></h1>
            <p>Your clouds are waiting. Let&rsquo;s find your next little step.</p>
            <div class="welcome-meta"><span class="welcome-note">A little progress is still progress.</span></div>
          </div>
          <div class="welcome-actions"><button class="btn btn-secondary" type="button" id="welcome-create-cloud">＋ Add Content</button><button class="arrange arrange-secondary" type="button" id="arrange-your-sky">Arrange Your Sky</button></div>
        </section>

        <?php if ($dashboardDataFailed): ?>
          <div class="dashboard-error" role="alert"><strong>Some workspace data could not be loaded.</strong><span>Your saved content has not been changed. Refresh the page and check the database connection if this continues.</span></div>
        <?php endif; ?>

        <?php if (!$currentCloudId): ?>
            <section class="dashboard-stats" data-dashboard-section="progress" aria-label="Your workspace at a glance" <?= (int)$preferences['show_progress'] === 0 ? 'hidden' : '' ?>>
              <article class="stat-card"><span class="stat-icon">☁</span><div><span class="stat-label">Your Clouds</span><strong><?= $totalClouds ?></strong><small><?= $totalClouds === 1 ? 'space to organize' : 'spaces to organize' ?></small></div></article>
              <article class="stat-card"><span class="stat-icon">✓</span><div><span class="stat-label">Open tasks</span><strong><?= $pendingTasks ?></strong><small>still on your list</small></div></article>
              <article class="stat-card"><span class="stat-icon">✧</span><div><span class="stat-label">Task progress</span><strong><?= $taskProgress ?>%</strong><small><?= $taskProgress === 100 && $pendingTasks === 0 ? 'Everything is checked off' : 'One step at a time' ?></small></div></article>
            </section>
            <section class="dashboard-columns dashboard-overview">
                <article class="dashboard-card overview-card" data-dashboard-section="upcoming" <?= (int)$preferences['show_upcoming'] === 0 ? 'hidden' : '' ?>>
                  <div class="section-heading"><div><p class="eyebrow">TODAY&rsquo;S PLAN</p><h2>Today&rsquo;s Plan</h2></div><span class="mini-icon" aria-hidden="true">📅</span></div>
                  <?php if ($upcomingTasks): ?><div class="upcoming-list"><?php foreach ($upcomingTasks as $item): ?><div class="upcoming-item"><span class="upcoming-mark"></span><div><strong><?= htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string)$item['cloud_name'], ENT_QUOTES, 'UTF-8') ?></small></div><time datetime="<?= htmlspecialchars((string)$item['due_date'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(date('M j', strtotime((string)$item['due_date'])), ENT_QUOTES, 'UTF-8') ?></time></div><?php endforeach; ?></div><?php else: ?><div class="overview-empty"><span aria-hidden="true">📅</span><p>No tasks scheduled</p><a href="#task-overview-panel" class="text-action">View Calendar</a></div><?php endif; ?>
                </article>
                <article class="dashboard-card overview-card" data-dashboard-section="recent" <?= (int)$preferences['show_recent_activity'] === 0 ? 'hidden' : '' ?>>
                  <div class="section-heading"><div><p class="eyebrow">RECENTLY OPENED</p><h2>Recently Opened</h2></div><span class="mini-icon" aria-hidden="true">↗</span></div>
                  <?php if ($recentNotes): ?><div class="recent-cloud-list"><?php foreach ($recentNotes as $recent): ?><a href="note_editor.php?cloud_id=<?= (int)$recent['cloud_id'] ?>" class="recent-cloud-item"><span class="recent-cloud-icon">▤</span><span><strong><?= htmlspecialchars((string)$recent['cloud_name'], ENT_QUOTES, 'UTF-8') ?></strong><small>Note saved <?= htmlspecialchars(date('M j', strtotime((string)$recent['last_updated'])), ENT_QUOTES, 'UTF-8') ?></small></span><span class="recent-arrow">↗</span></a><?php endforeach; ?></div><?php else: ?><div class="overview-empty"><span aria-hidden="true">✎</span><p>Nothing opened yet</p><button type="button" class="text-action" data-open-cloud>Start with a Cloud</button></div><?php endif; ?>
                </article>
            </section>
        <?php endif; ?>

        <section class="dashboard-card clouds-section" data-dashboard-section="clouds" <?= (!$currentCloudId && (int)$preferences['show_clouds'] === 0) ? 'hidden' : '' ?>>
          <div class="section-heading<?= $currentCloudId ? ' cloudspace-heading' : '' ?>">
            <div class="cloudspace-title">
              <p class="eyebrow"><?= $currentCloudId ? 'YOUR WORKSPACE / CLOUD' : 'YOUR SKY' ?></p>
              <h2><?= htmlspecialchars($currentCloudName, ENT_QUOTES, 'UTF-8') ?></h2>
              <?php if ($currentCloudId): ?>
                <p class="cloudspace-summary"><?php if ($displayClouds): ?><?= count($displayClouds) ?> <?= count($displayClouds) === 1 ? 'space is' : 'spaces are' ?> organized inside this Cloud.<?php elseif ($currentDepth >= 3): ?>Keep the next task and your notes close to this Cloud.<?php else: ?>A quiet place for related spaces, notes, and next steps.<?php endif; ?></p>
              <?php else: ?>
                <p>Everything in your Sky, organized your way.</p>
              <?php endif; ?>
            </div>
            <?php if ($currentCloudId): ?>
              <a href="<?= $parentCloudId ? 'homepage.php?cloud_id='.(int)$parentCloudId : 'homepage.php' ?>" class="cloud-back-link">
                <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span> Back to parent
              </a>
            <?php endif; ?>
          </div>

          <?php if ($currentDepth >= 3 && $currentCloudId): ?>
            <div class="cloud-task-composer">
              <form action="../assets/backend/add_task.php" method="POST" class="task-add-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="cloud_id" value="<?= (int)$currentCloudId ?>">
                <input type="text" name="title" placeholder="Add a task to this cloud..." required />
                <input type="date" name="due_date" aria-label="Task due date" />
                <button type="submit" class="primary-button">Add Task</button>
              </form>
            </div>

            <?php if (count($currentTasks) > 0): ?>
              <div class="task-list">
                <?php foreach ($currentTasks as $task): ?>
                  <div class="task-row">
                    <div class="task-row-main">
                      <form action="../assets/backend/toggle_task.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                        <input type="hidden" name="cloud_id" value="<?= $currentCloudId ?>">
                        <input type="checkbox" onChange="this.form.submit()" <?= $task['is_completed'] ? 'checked' : '' ?> aria-label="Mark task complete" />
                      </form>
                      <span class="task-label <?= $task['is_completed'] ? 'is-complete' : '' ?>">
                        <?= htmlspecialchars($task['title']) ?>
                      </span>
                    </div>
                    <?php if ($task['due_date']): ?>
                      <span class="task-due">
                        Due: <?= htmlspecialchars($task['due_date']) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="cloud-task-empty">
                <span class="eyebrow">A SMALL STEP</span>
                <p>No tasks in this Cloud yet. Add one above whenever you&rsquo;re ready.</p>
              </div>
            <?php endif; ?>

            <div class="notebook-section">
              <div class="card-heading"><h2>Notebook</h2><p>Keep the notes and ideas for this Cloud together.</p></div>
              <a href="note_editor.php?cloud_id=<?= $currentCloudId ?>" class="notebook-link">
                <span class="notebook-leading">
                  <span class="notebook-icon" aria-hidden="true">📝</span>
                  <span class="notebook-copy">
                    <strong>Distraction-Free Notebook</strong>
                    <span>Open the rich-text editor for this cloud</span>
                  </span>
                </span>
                <span class="notebook-action">Open notebook <span aria-hidden="true">→</span></span>
              </a>
            </div>

          <?php endif; ?>

          <?php if (count($displayClouds) > 0): ?>
            <div class="cloud-preview-grid">
              <?php foreach ($displayClouds as $cloud): ?>
                <a href="homepage.php?cloud_id=<?= $cloud['id'] ?>" class="cloud-card-link">
                  <div class="cloud-card-item">
                    <div aria-hidden="true">☁</div>
                    <h3><?= htmlspecialchars($cloud['name']) ?></h3>
                    <p>Open this cloud</p>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php elseif (!$currentCloudId || ($currentDepth < 3 && count($displayClouds) === 0)): ?>
            <div class="empty-cloud-state">
              <div class="empty-icon" aria-hidden="true">☁</div>
              <?php if (!$currentCloudId): ?>
                <h3>No clouds yet</h3>
                <p>Create your first cloud to give notes and tasks a calm place to land.</p>
                <button class="primary-button inline-primary" type="button" id="btn-open-category">+ Add Your First Cloud</button>
              <?php else: ?>
                <h3>It&rsquo;s empty in here.</h3>
                <p>Start adding things to <?= htmlspecialchars($currentCloudName) ?>.</p>
                <?php if ($currentDepth == 2): ?>
                  <button class="primary-button inline-primary" type="button" id="btn-open-schedule">+ Set Class Schedule</button>
                <?php elseif ($currentDepth < 3): ?>
                  <button class="primary-button inline-primary" type="button" id="btn-open-category">+ Add Content</button>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </section>

        <?php if (!$currentCloudId): ?>
          <section class="dashboard-card task-overview" id="task-overview-panel" aria-label="Your next tasks">
            <div class="section-heading"><div><p class="eyebrow">SMALL STEPS COUNT</p><h2>Small Steps Count</h2></div><span class="mini-icon" aria-hidden="true">✓</span></div>
            <?php if ($taskPreview): ?>
              <div class="task-list">
                <?php foreach ($taskPreview as $task): ?>
                  <div class="task-row">
                    <div class="task-row-main">
                      <form action="../assets/backend/toggle_task.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                        <input type="hidden" name="cloud_id" value="<?= (int)$task['cloud_id'] ?>">
                        <input type="checkbox" onChange="this.form.submit()" aria-label="Mark <?= htmlspecialchars((string)$task['title'], ENT_QUOTES, 'UTF-8') ?> complete">
                      </form>
                      <span class="task-label"><?= htmlspecialchars((string)$task['title'], ENT_QUOTES, 'UTF-8') ?><small class="task-context"><?= htmlspecialchars((string)$task['cloud_name'], ENT_QUOTES, 'UTF-8') ?></small></span>
                    </div>
                    <span class="task-due"><?= !empty($task['due_date']) ? htmlspecialchars(date('M j', strtotime((string)$task['due_date'])), ENT_QUOTES, 'UTF-8') : 'Whenever' ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="overview-empty"><span aria-hidden="true">✓</span><p>No open tasks right now</p><button type="button" class="text-action" data-open-cloud>+ Add Task</button></div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

      </main>
    </div>

    <div class="modal-overlay" id="schedule-modal" aria-hidden="true">
      <div class="modal schedule-modal">
        <button type="button" class="modal-close" id="close-schedule-modal">×</button>
        <div class="modal-icon">📅</div>
        <h2 id="schedule-title">Set Class Schedule</h2>
        <p class="modal-description">When is this class and when are your exams?</p>
        
        <form action="../assets/backend/generate_schedule.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="cloud_id" value="<?= (int)$currentCloudId ?>">
          
          <div class="settings-field">
            <label for="class-day" style="display: block; margin-top: 16px;">Day of the class</label>
            <select name="class_day" id="class-day" class="modal-input" style="width: 100%; margin: 8px 0 16px 0; padding: 12px; border-radius: 8px; border: 1px solid #e5e7eb;">
              <option value="monday">Monday</option>
              <option value="tuesday">Tuesday</option>
              <option value="wednesday">Wednesday</option>
              <option value="thursday">Thursday</option>
              <option value="friday">Friday</option>
              <option value="saturday">Saturday</option>
              <option value="sunday">Sunday</option>
            </select>
          </div>

          <div class="settings-field">
            <label for="midterm-week" style="display: block;">Midterm Week</label>
            <input type="number" name="midterm_week" id="midterm-week" value="8" min="1" max="20" class="modal-input" style="width: 100%; margin: 8px 0 16px 0; padding: 12px; border-radius: 8px; border: 1px solid #e5e7eb;" />
          </div>

          <div class="settings-field">
            <label for="final-week" style="display: block;">Final Test Week</label>
            <input type="number" name="final_week" id="final-week" value="16" min="1" max="30" class="modal-input" style="width: 100%; margin: 8px 0 24px 0; padding: 12px; border-radius: 8px; border: 1px solid #e5e7eb;" />
          </div>

          <button type="submit" class="primary-button">Generate Weeks</button>
        </form>
      </div>
    </div>

    <div class="modal-overlay" id="category-modal" aria-hidden="true">
      <div class="modal category-modal">
        <button class="modal-close" id="close-category-modal">×</button>
        <div class="modal-icon">✦</div>
        <h2 id="category-title">What's inside?</h2>
        <p class="modal-description">Choose what you want to keep inside this cloud.</p>
        <div class="category-grid">
          <button class="category-option" data-category="notes">
            <span>📝</span>
            <strong>Notes</strong>
            <small>Write and organize notes</small>
          </button>
          <button class="category-option" data-category="tasks">
            <span>✓</span>
            <strong>Tasks</strong>
            <small>Things you need to get done</small>
          </button>
          <button class="category-option" data-category="files">
            <span>📎</span>
            <strong>Files</strong>
            <small>Store useful documents</small>
          </button>
        </div>
        <p id="category-save-message" class="panel-subtitle" role="status" aria-live="polite" hidden></p>
        <button type="button" class="primary-button" id="save-categories">Add Selected Content</button>
      </div>
    </div>

    <div class="modal-overlay" id="settings-modal" aria-hidden="true">
      <section class="modal settings-modal" role="dialog" aria-modal="true" aria-labelledby="settings-heading" aria-describedby="settings-intro">
        <button type="button" class="modal-close" id="close-settings-modal" aria-label="Close settings">×</button>
        <div class="settings-layout">
          <aside class="settings-tabs" aria-label="Settings categories">
            <p class="settings-eyebrow">YOUR WORKSPACE</p>
            <h3>Settings</h3>
            <button type="button" class="settings-tab active" data-tab="profile"><span>◉</span> Profile</button>
            <button type="button" class="settings-tab" data-tab="appearance"><span>◐</span> Appearance</button>
            <button type="button" class="settings-tab" data-tab="dashboard"><span>▦</span> Dashboard</button>
            <button type="button" class="settings-tab" data-tab="notifications"><span>♧</span> Notifications</button>
            <button type="button" class="settings-tab" data-tab="security"><span>◇</span> Privacy &amp; Security</button>
            <button type="button" class="settings-tab" data-tab="account"><span>○</span> Account</button>
            <button type="button" class="settings-tab" data-tab="help"><span>?</span> Help &amp; tour</button>
          </aside>
          <div class="settings-panels">
            <p id="settings-intro" class="panel-subtitle">Tune Kumo to the way you think, work, and focus.</p>
            <p id="settings-message" class="settings-feedback" role="status" aria-live="polite" hidden></p>

            <section class="settings-panel active" data-panel="profile">
              <h2 id="settings-heading">Your profile</h2>
              <p class="panel-subtitle">Keep the name and profile picture shown across your workspace up to date.</p>
              <div class="profile-edit-head">
                <?php if ($avatarUrl !== ''): ?><img class="profile-avatar-preview" id="avatar-preview" src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Current profile picture" /><?php else: ?><span class="profile-avatar-preview avatar-fallback" id="avatar-preview-fallback" aria-hidden="true"><?= htmlspecialchars(kumo_initial_char($displayName), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                <div><strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></strong><small>Profile picture · JPEG, PNG, or WebP, up to 2 MB</small></div>
              </div>
              <form id="avatar-upload-form" class="avatar-upload-form" action="../assets/backend/upload_avatar.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
                <label for="avatar-file">Choose a profile picture</label>
                <input type="file" name="avatar" id="avatar-file" accept="image/jpeg,image/png,image/webp" />
                <button class="secondary-button" type="submit" id="avatar-upload-button">Upload picture</button>
                <button class="text-action" type="button" id="remove-avatar-button">Remove picture</button>
              </form>
              <form id="profile-settings-form" action="../assets/backend/update_profile.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
                <div class="settings-field"><label for="profile-full-name">Display name</label><input type="text" id="profile-full-name" name="full_name" maxlength="100" value="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>" required /></div>
                <div class="settings-field"><label for="profile-username">Username</label><input type="text" id="profile-username" value="<?= htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly /><small>Your username remains your sign-in identifier.</small></div>
                <div class="settings-field"><label for="profile-email">Email address</label><input type="email" id="profile-email" value="<?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly /><small>Email is read-only here. Changing it requires a separate re-verification step.</small></div>
                <button type="submit" class="primary-button settings-save">Save profile</button>
              </form>
            </section>

            <section class="settings-panel" data-panel="appearance">
              <h2>Appearance</h2><p class="panel-subtitle">A comfortable visual environment for long study sessions.</p>
              <form id="appearance-settings-form">
                <div class="settings-field"><label for="theme-choice">Theme</label><select id="theme-choice" name="theme"><option value="light" <?= $preferences['theme'] === 'light' ? 'selected' : '' ?>>Light</option><option value="dark" <?= $preferences['theme'] === 'dark' ? 'selected' : '' ?>>Dark</option><option value="system" <?= $preferences['theme'] === 'system' ? 'selected' : '' ?>>System</option></select><small>New accounts default to Light. System only applies after you choose it.</small></div>
                <div class="settings-field"><label for="accent-choice">Accent color</label><select id="accent-choice" name="accent_color"><option value="sage" <?= $preferences['accent_color'] === 'sage' ? 'selected' : '' ?>>Sage (Kumo green)</option><option value="forest" <?= $preferences['accent_color'] === 'forest' ? 'selected' : '' ?>>Forest</option><option value="ocean" <?= $preferences['accent_color'] === 'ocean' ? 'selected' : '' ?>>Ocean</option><option value="lavender" <?= $preferences['accent_color'] === 'lavender' ? 'selected' : '' ?>>Lavender</option></select></div>
                <div class="settings-field"><label for="density-choice">Layout density</label><select id="density-choice" name="density"><option value="comfortable" <?= $preferences['density'] === 'comfortable' ? 'selected' : '' ?>>Comfortable</option><option value="compact" <?= $preferences['density'] === 'compact' ? 'selected' : '' ?>>Compact</option></select></div>
                <label class="setting-check"><input type="checkbox" name="reduce_motion" value="1" <?= $preferences['reduce_motion'] ? 'checked' : '' ?> /><span><strong>Reduce motion</strong><small>Use calmer transitions and respect accessibility preferences.</small></span></label>
                <button type="submit" class="primary-button settings-save">Save appearance</button>
              </form>
            </section>

            <section class="settings-panel" data-panel="dashboard">
              <h2>Dashboard</h2><p class="panel-subtitle">Choose which sections help you stay oriented. Counts and lists use your actual saved records.</p>
              <form id="dashboard-settings-form">
                <label class="setting-check"><input type="checkbox" name="show_clouds" value="1" <?= $preferences['show_clouds'] ? 'checked' : '' ?> /><span><strong>Your Clouds</strong><small>Show your cloud list on the main dashboard.</small></span></label>
                <label class="setting-check"><input type="checkbox" name="show_upcoming" value="1" <?= $preferences['show_upcoming'] ? 'checked' : '' ?> /><span><strong>Coming up</strong><small>Show upcoming tasks with dates.</small></span></label>
                <label class="setting-check"><input type="checkbox" name="show_recent_activity" value="1" <?= $preferences['show_recent_activity'] ? 'checked' : '' ?> /><span><strong>Recent spaces</strong><small>Show recently updated Clouds.</small></span></label>
                <label class="setting-check"><input type="checkbox" name="show_progress" value="1" <?= $preferences['show_progress'] ? 'checked' : '' ?> /><span><strong>Workspace summary</strong><small>Show live counts for Clouds, open tasks, and saved notes.</small></span></label>
                <div class="settings-field"><label for="first-section-choice">Show first</label><select id="first-section-choice" name="first_section"><option value="clouds" <?= $preferences['first_section'] === 'clouds' ? 'selected' : '' ?>>Your Clouds</option><option value="upcoming" <?= $preferences['first_section'] === 'upcoming' ? 'selected' : '' ?>>Coming up</option><option value="recent" <?= $preferences['first_section'] === 'recent' ? 'selected' : '' ?>>Recent spaces</option></select><small>This changes the order of dashboard sections, without removing any Cloud data.</small></div>
                <button type="submit" class="primary-button settings-save">Save dashboard</button>
                <button type="button" id="reset-dashboard-settings" class="text-action">Reset dashboard to defaults</button>
              </form>
            </section>

            <section class="settings-panel" data-panel="notifications">
              <h2>Notifications</h2><p class="panel-subtitle">See what Kumo can notify you about today.</p>
              <div class="unavailable-setting"><strong>Upcoming task list</strong><span>Available in Dashboard</span><p>Kumo shows real upcoming tasks with due dates on your dashboard. Use Dashboard settings to show or hide this section.</p></div>
              <div class="unavailable-setting"><strong>Separate in-app alerts</strong><span>Not implemented</span><p>Persistent reminder alerts and push notifications are not implemented, so there is no toggle that would pretend to control them.</p></div>
              <div class="unavailable-setting"><strong>Email notifications</strong><span>Unavailable</span><p>Email delivery is not configured and tested in this build, so Kumo will not claim to send emails.</p></div>
            </section>

            <section class="settings-panel" data-panel="security">
              <h2>Privacy &amp; Security</h2><p class="panel-subtitle">Change your password after confirming your current password.</p>
              <form id="change-password-form" action="../assets/backend/change_password.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
                <div class="settings-field"><label for="current-password">Current password</label><input type="password" id="current-password" name="current_password" autocomplete="current-password" required /></div>
                <div class="settings-field"><label for="new-password">New password</label><input type="password" id="new-password" name="new_password" minlength="8" maxlength="20" autocomplete="new-password" required /><small>8&ndash;20 characters, including uppercase, lowercase, number, and special character.</small></div>
                <div class="settings-field"><label for="confirm-new-password">Confirm new password</label><input type="password" id="confirm-new-password" name="confirm_password" minlength="8" maxlength="20" autocomplete="new-password" required /></div>
                <button type="submit" class="primary-button settings-save">Change password</button>
              </form>
              <div class="unavailable-setting"><strong>Sign out all sessions</strong><span>Not available</span><p>This build supports secure sign-out on this device. A session-management screen is not implemented.</p></div>
            </section>

            <section class="settings-panel" data-panel="account">
              <h2>Account</h2><p class="panel-subtitle">Your account details, in one place.</p>
              <dl class="account-details"><div><dt>Username</dt><dd><?= htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd></div><div><dt>Email</dt><dd><?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd></div><div><dt>Created</dt><dd><?= !empty($user['created_at']) ? htmlspecialchars(date('F j, Y', strtotime((string)$user['created_at'])), ENT_QUOTES, 'UTF-8') : 'Not available in the current account record' ?></dd></div><div><dt>Email verification</dt><dd><?= !empty($user['email_verified_at']) ? 'Verified' : 'Not verified' ?></dd></div></dl>
              <p class="panel-subtitle">Account deletion and email changes are intentionally unavailable here because they require additional reauthentication and verification workflows.</p>
            </section>

            <section class="settings-panel" data-panel="help">
              <h2>Help &amp; tour</h2>
              <p class="panel-subtitle">Take a quick guided look at your Kumo workspace. You can replay the tour whenever you need it.</p>
              <button type="button" class="primary-button" id="start-kumo-tour">Start the Kumo tour</button>
            </section>
          </div>
        </div>
      </section>
    </div>

    <div class="modal-overlay" id="cloud-modal" aria-hidden="true">
      <section class="modal" role="dialog" aria-modal="true" aria-labelledby="cloud-modal-title">
        <button type="button" class="modal-close" id="close-cloud-modal" aria-label="Close">×</button>
        <div class="modal-icon">☁</div>
        <h2 id="cloud-modal-title">Create a Cloud</h2>
        <p class="modal-description">Give this space a name. You can keep it in My Sky or nest it inside the Cloud you are viewing.</p>
        <form id="create-cloud-form" novalidate>
          <div class="settings-field" style="text-align:left">
            <label for="cloud-name">Cloud name</label>
            <input type="text" id="cloud-name" maxlength="100" class="modal-input" placeholder="e.g. Calculus, Personal, Kumo project" autocomplete="off" required>
          </div>
          <div class="settings-field" style="text-align:left">
            <label for="cloud-location">Place it in</label>
            <select id="cloud-location" class="modal-input" style="width:100%">
              <option value="root">My Sky (top level)</option>
              <option value="current" <?= $currentCloudId ? '' : 'disabled' ?>>Inside &ldquo;<?= htmlspecialchars($currentCloudName, ENT_QUOTES, 'UTF-8') ?>&rdquo;</option>
            </select>
          </div>
          <p id="cloud-create-message" class="panel-subtitle" role="status" aria-live="polite" hidden></p>
          <button type="submit" class="primary-button" id="create-cloud-confirm">Create Cloud</button>
        </form>
      </section>
    </div>

    <div class="modal-overlay" id="atmosphere-modal" aria-hidden="true">
      <section class="modal coming-soon-modal" role="dialog" aria-modal="true" aria-labelledby="atmosphere-modal-title">
        <button type="button" class="modal-close" id="close-atmosphere-modal" aria-label="Close">×</button>
        <div class="modal-icon">♧</div>
        <span class="coming-soon-label">COMING SOON</span>
        <h2 id="atmosphere-modal-title">A wider sky is on the way.</h2>
        <p class="modal-description">Join Atmosphere is planned as a way to share ideas and work alongside others. It is not available yet, and nothing will be sent or created from this preview.</p>
        <button type="button" class="primary-button" id="dismiss-atmosphere-modal">Sounds good</button>
      </section>
    </div>

    <div class="modal-overlay" id="onboarding-modal" aria-hidden="true">
      <section class="modal onboarding-modal" role="dialog" aria-modal="true" aria-labelledby="onboarding-title">
        <button type="button" class="modal-close" id="close-onboarding-modal" aria-label="Skip setup">×</button>
        <div id="onboarding-progress" class="onboarding-progress" aria-hidden="true"></div>
        <div id="onboarding-viewport" class="onboarding-viewport"></div>
        <p id="onboarding-error" class="panel-subtitle" role="alert" hidden></p>
      </section>
    </div>

    <div class="info-popover" id="info-popover-template">
      <strong><span>✦</span> <span id="info-popover-title"></span></strong>
      <span id="info-popover-body"></span>
    </div>

    <script src="../assets/JS/vendor/gsap.min.js?v=3.15.0"></script>
    <script src="../assets/JS/vendor/ScrollTrigger.min.js?v=3.12.5"></script>
    <script src="../assets/JS/gsap-animations.js?v=20261105-v2"></script>
    <script src="../assets/JS/main.js?v=20261029-v4"></script>

    
    <script>
        const isFirstTimeUser = <?php echo json_encode($isFirstTimeUser); ?>;
        if (isFirstTimeUser) {
          let onboardingWasDismissed = false;
          try { onboardingWasDismissed = localStorage.getItem('kumoOnboardingComplete') === '1'; } catch (e) {}
          if (!onboardingWasDismissed && typeof window.kumoStartOnboarding === 'function') {
            window.kumoStartOnboarding();
          }
        }

        // Sidebar State Persistence 
        const sidebarDetails = document.querySelectorAll('.cloud-folder');
        let openFolders = [];
        try {
          const storedFolders = localStorage.getItem('kumoSidebarState');
          openFolders = storedFolders ? JSON.parse(storedFolders) : [];
          if (!Array.isArray(openFolders)) openFolders = [];
        } catch (e) { openFolders = []; }

        sidebarDetails.forEach(details => {
          const id = details.getAttribute('data-cloud-id');
          
          if (openFolders.includes(id)) {
            details.classList.add('preload-open');
            details.setAttribute('open', '');
          }

          details.addEventListener('toggle', () => {
            let currentOpen = [];
            try {
              const stored = localStorage.getItem('kumoSidebarState');
              currentOpen = stored ? JSON.parse(stored) : [];
              if (!Array.isArray(currentOpen)) currentOpen = [];
            } catch (e) { currentOpen = []; }
            if (details.open) {
              if (!currentOpen.includes(id)) currentOpen.push(id);
              details.classList.remove('preload-open');
            } else {
              currentOpen = currentOpen.filter(item => item !== id);
            }
            try { localStorage.setItem('kumoSidebarState', JSON.stringify(currentOpen)); } catch (e) {}
          });
        });

        const btnOpenSchedule = document.getElementById('btn-open-schedule');
        const btnOpenCategory = document.getElementById('btn-open-category');
        const scheduleModal = document.getElementById('schedule-modal');
        const closeScheduleModal = document.getElementById('close-schedule-modal');
        const categoryModal = document.getElementById('category-modal');
        const closeCategoryModal = document.getElementById('close-category-modal');
        const saveCategoriesBtn = document.getElementById('save-categories');

        if (btnOpenSchedule) {
          btnOpenSchedule.addEventListener('click', () => window.kumoOpenModal ? window.kumoOpenModal(scheduleModal) : scheduleModal.classList.add('active'));
        }

        if (closeScheduleModal) {
          closeScheduleModal.addEventListener('click', () => window.kumoCloseModal ? window.kumoCloseModal(scheduleModal) : scheduleModal.classList.remove('active'));
        }

        if (closeCategoryModal) {
          closeCategoryModal.addEventListener('click', () => window.kumoCloseModal ? window.kumoCloseModal(categoryModal) : categoryModal.classList.remove('active'));
        }

        const categoryOptions = document.querySelectorAll('.category-option');
        categoryOptions.forEach(option => {
          option.addEventListener('click', () => {
            option.classList.toggle('selected');
            if (option.classList.contains('selected')) {
               option.style.borderColor = '#6b8c7a';
               option.style.backgroundColor = '#f4f7f5';
            } else {
               option.style.borderColor = '#e5e7eb';
               option.style.backgroundColor = '#ffffff';
            }
          });
        });

        if (saveCategoriesBtn) {
            saveCategoriesBtn.addEventListener('click', () => {
              const selectedCategories = Array.from(document.querySelectorAll('.category-option.selected'))
                                             .map(btn => btn.dataset.category);
              if (selectedCategories.length === 0) {
                  alert("Please select at least one type of content.");
                  return;
              }
              const message = document.getElementById('category-save-message');
              const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
              const currentCloudId = document.querySelector('meta[name="current-cloud-id"]')?.content || '0';
              const body = new URLSearchParams({ cloud_id: currentCloudId, csrf_token: token });
              selectedCategories.forEach(category => body.append('categories[]', category));
              saveCategoriesBtn.disabled = true;
              saveCategoriesBtn.textContent = 'Adding…';
              if (message) { message.hidden = true; message.textContent = ''; }
              fetch('../assets/backend/add_cloud_categories.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json' },
                body
              }).then(async response => {
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.error || 'Could not add this content.');
                window.location.assign(result.redirect || 'homepage.php');
              }).catch(error => {
                if (message) { message.hidden = false; message.textContent = error.message || 'Please try again.'; }
              }).finally(() => {
                saveCategoriesBtn.disabled = false;
                saveCategoriesBtn.textContent = 'Add Selected Content';
              });
            });
        }
    </script>
  </body>
</html>
