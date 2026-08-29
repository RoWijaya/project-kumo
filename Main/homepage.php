<?php

require_once __DIR__ . '/../Assets/backend/auth.php';
require_once __DIR__ . '/../assets/backend/db.php';

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare( "SELECT username, email FROM userdata WHERE id = ?" );
$stmt->bind_param("i", $userId); $stmt->execute(); $result =
$stmt->get_result(); $user = $result->fetch_assoc(); $stmt->close();
$conn->close(); ?>

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
              <span class="icon">+</span>
              Join an Atmosphere.
            </summary>
          </details>
        </nav>

        <div class="user-area">
          <button class="user">
            <span class="avatar">○</span>
            <?= htmlspecialchars($user['username']) ?>
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


   <div class="modal-overlay" id="onboarding-modal">
      <div class="modal onboarding-modal">
        <button class="modal-close" id="close-onboarding-modal">×</button>

        <div class="onboarding-progress" id="onboarding-progress"></div>

        <div class="onboarding-viewport" id="onboarding-viewport">
          
          <!-- STEP 1: Life Stage -->
          <div id="step-1" class="onboarding-step">
            <h2>What are you focusing on right now?</h2>
            <p>This helps Kumo set up your first Clouds.</p>
            
            <div class="options-grid">
              <button id="btn-university" class="option-card">
                <span class="icon">🎓</span> University
              </button>
              <button class="option-card"><span class="icon">🏫</span> High School</button>
              <button class="option-card"><span class="icon">🎒</span> Middle School</button>
              <button class="option-card"><span class="icon">🖍️</span> Elementary</button>
              <button class="option-card"><span class="icon">💼</span> Work</button>
            </div>
            
            <button id="btn-skip" class="text-link">Customize on my own →</button>
          </div>

          <div id="step-2-uni" class="onboarding-step" style="display: none;">
            <h2>What semester are you in?</h2>
            <p>We'll create a dedicated Cloud for this semester.</p>
            
            <input type="text" id="uni-semester" placeholder="e.g. Fall 2026, Semester 3..." class="modal-input">
            
            <div class="modal-actions">
              <button class="back-btn" onclick="goBack('step-2-uni', 'step-1')">← Back</button>
              <button class="primary-btn" onclick="goToStep('step-2-uni', 'step-3-uni')">Next Step</button>
            </div>
          </div>

          <div id="step-3-uni" class="onboarding-step" style="display: none;">
            <h2>What classes are you taking?</h2>
            <p>List your classes below to set up your workspace.</p>
            
            <textarea id="uni-classes" placeholder="e.g. Calculus, Operating Systems..." class="modal-input"></textarea>
            
            <div class="modal-actions">
              <button class="back-btn" onclick="goBack('step-3-uni', 'step-2-uni')">← Back</button>
              <button class="primary-btn" onclick="finishSetup()">Arrange my Sky ✨</button>
            </div>
          </div>

          <div id="step-success" class="onboarding-step" style="display: none;">
            <div class="success-icon">☁️</div>
            <h2>You're all set!</h2>
            <p>Your clouds have been arranged.</p>
            <button class="primary-btn" id="enter-kumo-btn">Enter Kumo</button>
          </div>

        </div>
      </div>
    </div>

    <div class="modal-overlay" id="settings-modal">
      <div class="modal settings-modal">
        <button class="modal-close" id="close-settings-modal">×</button>

        <div class="settings-layout">
          <aside class="settings-tabs">
            <h3>Settings</h3>
            <button class="settings-tab active" data-tab="account">
              Account
            </button>
            <button class="settings-tab" data-tab="appearance">
              Appearance
            </button>
            <button class="settings-tab" data-tab="silver-lining">
              Silver Lining AI
            </button>
            <button class="settings-tab danger-tab" data-tab="danger">
              Danger Zone
            </button>
          </aside>

          <div class="settings-panels">
            <div class="settings-panel active" data-panel="account">
              <h2>Account</h2>
              <p class="panel-subtitle">
                Update the basics people use to find you.
              </p>

              <div class="settings-field">
                <label for="settings-username">Username</label>
                <input
                  type="text"
                  id="settings-username"
                  placeholder="Your username"
                />
              </div>

              <div class="settings-field">
                <label for="settings-email">Email</label>
                <input
                  type="email"
                  id="settings-email"
                  placeholder="you@example.com"
                />
              </div>

              <button class="primary-button settings-save" data-save="account">
                Save changes
              </button>
            </div>

            <div class="settings-panel" data-panel="appearance">
              <h2>Appearance</h2>
              <p class="panel-subtitle">Choose how your Sky looks.</p>

              <div class="sky-toggle-row">
                <div class="sky-toggle-label">
                  <span class="sky-icon">☀</span>
                  <div>
                    <strong id="sky-mode-label">Day Sky</strong>
                    <span>Light, airy, bright</span>
                  </div>
                </div>
                <label class="switch">
                  <input type="checkbox" id="sky-mode-toggle" />
                  <span class="switch-track"></span>
                </label>
              </div>
            </div>

            <div class="settings-panel" data-panel="silver-lining">
              <h2>Silver Lining AI</h2>
              <p class="panel-subtitle">
                Your calm assistant for organizing your Sky.
              </p>

              <div class="sky-toggle-row">
                <div class="sky-toggle-label">
                  <span class="sky-icon">✦</span>
                  <div>
                    <strong>Enable Silver Lining</strong>
                    <span>Suggests clouds and tidies your workspace</span>
                  </div>
                </div>
                <label class="switch">
                  <input type="checkbox" id="silver-lining-toggle" checked />
                  <span class="switch-track"></span>
                </label>
              </div>
            </div>

            <div class="settings-panel" data-panel="danger">
              <h2>Danger Zone</h2>
              <p class="panel-subtitle">These actions can't be undone.</p>

              <div class="danger-card">
                <div>
                  <strong>Clear all Clouds</strong>
                  <p>Remove every Cloud and everything inside them.</p>
                </div>
                <button class="danger-button" id="clear-clouds-btn">
                  Clear
                </button>
              </div>

              <div class="danger-card">
                <div>
                  <strong>Delete account</strong>
                  <p>Permanently delete your Kumo account and data.</p>
                </div>
                <button class="danger-button" id="delete-account-btn">
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal-overlay" id="cloud-modal">
      <div class="modal">
        <button class="modal-close" id="close-cloud-modal">×</button>

        <div class="modal-icon">☁</div>
        <h2>Create a Cloud</h2>
        <p class="modal-description">Give this part of your Sky a name.</p>

        <div class="cloud-explainer">
          <span class="explainer-icon">💡</span>
          <p>
            A Cloud holds your notes, tasks, and files for a specific part of
            your life — like "University" or "Freelance Work."
          </p>
        </div>

        <label for="cloud-name">Cloud name</label>
        <input
          type="text"
          id="cloud-name"
          placeholder="e.g. University, Work, Personal..."
          maxlength="40"
        />

        <label for="cloud-location">Where should this float?</label>
        <select id="cloud-location">
          <option value="sky">Main Sky (top level)</option>
          <option value="education">Nested under — Education</option>
          <option value="work">Nested under — Work</option>
          <option value="personal">Nested under — Personal</option>
        </select>

        <button class="primary-button" id="create-cloud-confirm">
          Create Cloud
        </button>
      </div>
    </div>

    <div class="info-popover" id="info-popover-template">
      <strong><span>✦</span> <span id="info-popover-title"></span></strong>
      <span id="info-popover-body"></span>
    </div>

    <script src="../Assets/Js/main.js"></script>
    <script src="../Assets/Js/kumo-features.js"></script>
  </body>
</html>
