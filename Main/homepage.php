<?php

require_once __DIR__ . '/../Assets/backend/auth.php';
require_once __DIR__ . '/../assets/backend/db.php';

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT username, email FROM userdata WHERE id = ?"
);

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();
$conn->close();
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Kumo</title>
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
    />
  </head>

  <body>
    <div class="app">
      <aside class="sidebar">
        <img src="../Assets/icons/Kumo.svg" alt="Kumo" class="kumo-logo" />

        <nav class="nav">
          <a href="#" class="nav-link active">
            <span class="icon">⌂</span>
            My Sky
          </a>

          <a href="#" class="nav-link">
            <span class="icon">⌕</span>
            Search
          </a>

          <a href="#" class="nav-link">
            <span class="icon">☆</span>
            Favorites
          </a>

          <div class="divider"></div>

          <div class="section-title">
            <span class="icon">☁</span>
            YOUR CLOUDS
          </div>

          <div id="cloud-tree"></div>

          <button class="create-cloud-btn" id="create-cloud-sidebar">
            + Create Cloud
          </button>

          <div class="divider"></div>

          <div class="section-title">
            <span class="icon">♧</span>
            COMMUNITY
          </div>

          <details>
            <summary class="cloud-summary">
              <span class="cloud-arrow">›</span>
              <span class="icon">○</span>
              Study Hub
            </summary>

            <div class="cloud-children">
              <a href="#" class="child-link">My Classes</a>
              <a href="#" class="child-link">Discover</a>
            </div>
          </details>
        </nav>

        <div class="user-area">
          <button class="user">
            <span class="avatar">○</span>
            Roki
          </button>

          <button class="settings">⚙</button>
        </div>
      </aside>

      <main class="main">
        <div class="search">
          <input
            type="search"
            placeholder="Search your Sky..."
            id="search-input"
          />

          <span class="shortcut"> Ctrl + K </span>
        </div>

        <section class="welcome">
          <div>
            <h1>Welcome back, <?= htmlspecialchars($user['username']) ?>.</h1>

            <p>Your clouds are waiting.</p>
          </div>

          <button class="arrange">✦ Arrange Your Sky</button>
        </section>

        <section class="dashboard-card clouds-section">
          <div class="section-heading">
            <div>
              <h2>Your Clouds</h2>

              <p>Everything in your Sky, organized your way.</p>
            </div>
          </div>

          <div class="cloud-preview-grid" id="cloud-preview-grid"></div>

          <div class="empty-cloud-state" id="empty-cloud-state">
            <div class="empty-icon">☁</div>

            <h3>Your Sky is still quiet.</h3>

            <p>Create your first cloud and start building your space.</p>

            <button class="create-cloud-btn" id="empty-create-cloud">
              + Create your first cloud
            </button>
          </div>
        </section>

        <div class="dashboard-columns">
          <section class="dashboard-card">
            <div class="card-heading">
              <h2>Upcoming</h2>
            </div>

            <div class="empty-small">
              <span>○</span>

              <div>
                <strong>Nothing coming up</strong>

                <p>Reminders and upcoming tasks will appear here.</p>
              </div>
            </div>
          </section>

          <section class="dashboard-card">
            <div class="card-heading">
              <h2>Today</h2>
            </div>

            <div class="empty-small">
              <span>✓</span>

              <div>
                <strong>You're all clear.</strong>

                <p>Tasks for today will appear here.</p>
              </div>
            </div>
          </section>
        </div>

        <section class="dashboard-card recently-opened">
          <div class="card-heading">
            <div>
              <h2>Recently opened</h2>

              <p>Your latest notes, tasks and files.</p>
            </div>
          </div>

          <div class="recent-empty">
            <span class="recent-icon">◌</span>

            <div>
              <strong>Nothing here yet</strong>

              <p>
                Start creating inside your clouds and your recent items will
                show up here.
              </p>
            </div>
          </div>
        </section>
      </main>
    </div>

    <div class="modal-overlay" id="cloud-modal">
      <div class="modal">
        <button class="modal-close" id="close-cloud-modal">×</button>

        <div class="modal-icon">☁</div>

        <h2>Create a Cloud</h2>

        <p class="modal-description">Give this part of your Sky a name.</p>

        <label for="cloud-name"> Cloud name </label>

        <input
          type="text"
          id="cloud-name"
          placeholder="e.g. University, Work, Personal..."
          maxlength="40"
        />

        <button class="primary-button" id="create-cloud-confirm">
          Create Cloud
        </button>
      </div>
    </div>

    <div class="modal-overlay" id="category-modal">
      <div class="modal category-modal">
        <button class="modal-close" id="close-category-modal">×</button>

        <div class="modal-icon">✦</div>

        <h2 id="category-title">What's inside?</h2>

        <p class="modal-description">
          Choose what you want to keep inside this cloud.
        </p>

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

          <button class="category-option" data-category="reminders">
            <span>⏰</span>
            <strong>Reminders</strong>
            <small>Keep track of important dates</small>
          </button>

          <button class="category-option" data-category="files">
            <span>📎</span>
            <strong>Files</strong>
            <small>Store useful documents</small>
          </button>
        </div>

        <button class="primary-button" id="save-categories">Save Cloud</button>
      </div>
    </div>

    <script src="../Assets/Js/main.js"></script>
  </body>
</html>
