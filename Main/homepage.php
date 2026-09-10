<?php
require_once __DIR__ . '/../Assets/backend/auth.php';
require_once __DIR__ . '/../assets/backend/db.php';

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT username, email FROM userdata WHERE id = ?");
$stmt->bind_param("i", $userId); 
$stmt->execute(); 
$result = $stmt->get_result(); 
$user = $result->fetch_assoc(); 
$stmt->close();

$setupStmt = $conn->prepare("SELECT setup_completed FROM userdata WHERE id = ?");
$setupStmt->bind_param("i", $userId);
$setupStmt->execute();
$setupResult = $setupStmt->get_result();
$userData = $setupResult->fetch_assoc();
$isFirstTimeUser = ($userData['setup_completed'] == 0);
$setupStmt->close();

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
    }
    $infoStmt->close();
}

if ($currentCloudId) {
    $gridStmt = $conn->prepare("SELECT id, name FROM clouds WHERE user_id = ? AND parent_id = ? ORDER BY id ASC");
    $gridStmt->bind_param("ii", $userId, $currentCloudId);
} else {
    $gridStmt = $conn->prepare("SELECT id, name FROM clouds WHERE user_id = ? AND parent_id IS NULL ORDER BY id ASC");
    $gridStmt->bind_param("i", $userId);
}
$gridStmt->execute();
$gridResult = $gridStmt->get_result();
$displayClouds = [];
while ($row = $gridResult->fetch_assoc()) {
    $displayClouds[] = $row;
}
$gridStmt->close();

$allCloudsStmt = $conn->prepare("SELECT id, name, parent_id FROM clouds WHERE user_id = ? ORDER BY id ASC");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Kumo</title>
    <link rel="stylesheet" href="../Assets/Css/main.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <style>
      details.cloud-folder > summary {
        list-style: none;
      }
      details.cloud-folder > summary::-webkit-details-marker {
        display: none;
      }
      details.cloud-folder .folder-content {
        animation: dropDown 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        transform-origin: top;
      }
      details.cloud-folder.preload-open > .folder-content {
        animation: none;
      }
      @keyframes dropDown {
        0% { opacity: 0; transform: translateY(-8px); }
        100% { opacity: 1; transform: translateY(0); }
      }
      .sidebar .nav-link {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 16px;
        margin: 4px 12px;
        border-radius: 12px;
        color: #4b5563;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s ease;
        line-height: 1.4;
        word-break: break-word;
      }
      .sidebar .nav-link .icon {
        flex-shrink: 0;
        color: #9ca3af;
        font-size: 1.1rem;
        margin-top: 1px;
      }
      .sidebar .nav-link.active {
        background-color: #ffffff;
        color: #2c3e2e;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
      }
      .sidebar .nav-link.active .icon {
        color: #6b8c7a;
      }
      .sidebar .nav-link:hover:not(.active) {
        background-color: rgba(255,255,255,0.5);
      }
    </style>
  </head>

  <body>
    <div class="app">
      <aside class="sidebar">
        <a href="homepage.php">
          <img src="../Assets/icons/Kumo.svg" alt="Kumo" class="kumo-logo" />
        </a>

        <nav class="nav">
          <a href="homepage.php" class="nav-link <?= !$currentCloudId ? 'active' : '' ?>">
            <span class="icon">⌂</span>
            <span style="flex: 1;">My Sky</span>
          </a>

          <a href="#" class="nav-link">
            <span class="icon">⌕</span>
            <span style="flex: 1;">Search</span>
          </a>

          <a href="#" class="nav-link">
            <span class="icon">☆</span>
            <span style="flex: 1;">Favorites</span>
          </a>

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
          <input type="search" placeholder="Search your Sky..." id="search-input" />
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
              <h2><?= htmlspecialchars($currentCloudName) ?></h2>

              <?php if ($currentCloudId): ?>
                <p>
                  <a href="<?= $parentCloudId ? 'homepage.php?cloud_id='.$parentCloudId : 'homepage.php' ?>" class="text-link" style="font-weight: 600; color: #6b8c7a; text-decoration: none;">
                    ← Go Back
                  </a>
                </p>
              <?php else: ?>
                <p>Everything in your Sky, organized your way.</p>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($currentDepth >= 3 && $currentCloudId): ?>
            <div style="margin-bottom: 24px; display: flex; gap: 10px;">
              <form action="../Assets/backend/add_task.php" method="POST" style="display: flex; gap: 10px; width: 100%;">
                <input type="hidden" name="cloud_id" value="<?= $currentCloudId ?>">
                <input type="text" name="title" placeholder="Add a new task..." required style="flex: 1; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 8px; font-family: 'Poppins', sans-serif;" />
                <input type="date" name="due_date" style="padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px; font-family: 'Poppins', sans-serif;" />
                <button type="submit" class="primary-button" style="width: auto; padding: 10px 20px; border-radius: 8px;">Add Task</button>
              </form>
            </div>

            <?php if (count($currentTasks) > 0): ?>
              <div class="task-list" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 30px;">
                <?php foreach ($currentTasks as $task): ?>
                  <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                      <form action="../Assets/backend/toggle_task.php" method="POST" style="margin: 0;">
                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                        <input type="hidden" name="cloud_id" value="<?= $currentCloudId ?>">
                        <input type="checkbox" onChange="this.form.submit()" <?= $task['is_completed'] ? 'checked' : '' ?> style="cursor: pointer; width: 16px; height: 16px;" />
                      </form>
                      <span style="font-weight: 500; color: <?= $task['is_completed'] ? '#9ca3af' : '#2c3e2e' ?>; text-decoration: <?= $task['is_completed'] ? 'line-through' : 'none' ?>;">
                        <?= htmlspecialchars($task['title']) ?>
                      </span>
                    </div>
                    <?php if ($task['due_date']): ?>
                      <span style="font-size: 0.85rem; color: #6b7280; background: #e5e7eb; padding: 4px 8px; border-radius: 6px;">
                        Due: <?= htmlspecialchars($task['due_date']) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div style="margin-top: 30px; margin-bottom: 30px;">
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                 <h3 style="font-size: 1.1rem; margin: 0; color: #2c3e2e;">Class Notes</h3>
              </div>
              
              <a href="note_editor.php?cloud_id=<?= $currentCloudId ?>" style="text-decoration: none; display: block;">
                <div style="padding: 16px 20px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s ease;" onmouseover="this.style.borderColor='#6b8c7a'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)';" onmouseout="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none';">
                  
                  <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #f4f7f5; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                      📝
                    </div>
                    <div>
                      <strong style="display: block; color: #2c3e2e; font-size: 0.95rem; margin-bottom: 2px;">Distraction-Free Notebook</strong>
                      <span style="color: #6b7280; font-size: 0.85rem;">Click to open the rich text editor</span>
                    </div>
                  </div>
                  
                  <div style="color: #6b8c7a; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 4px;">
                    Open <span style="font-size: 1.1rem; line-height: 1;">→</span>
                  </div>
                  
                </div>
              </a>
            </div>

          <?php endif; ?>

          <?php if (count($displayClouds) > 0): ?>
            <div class="cloud-preview-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; padding: 10px 0;">
              <?php foreach ($displayClouds as $cloud): ?>
                <a href="homepage.php?cloud_id=<?= $cloud['id'] ?>" style="text-decoration: none; color: inherit; display: block;">
                  <div class="cloud-card-item" style="cursor: pointer; padding: 24px; text-align: center; border: 1px solid #e5e7eb; border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s; background: #fff; height: 100%; box-sizing: border-box;">
                    <div style="font-size: 2.5rem; margin-bottom: 12px; color: #6b8c7a;">☁</div>
                    <h3 style="margin: 0 0 8px 0; font-size: 1.1rem; color: #2c3e2e;"><?= htmlspecialchars($cloud['name']) ?></h3>
                    <p style="margin: 0; font-size: 0.85rem; color: #6b7280;">Click to dive in</p>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php elseif (!$currentCloudId || ($currentDepth < 3 && count($displayClouds) === 0)): ?>
            <div class="empty-cloud-state" style="padding: 40px; text-align: center;">
              <div class="empty-icon" style="font-size: 3rem; color: #ccc;">☁</div>
              <h3 style="margin-top: 10px;">It's empty in here.</h3>
              <p style="color: #6b7280;">Start adding things to <?= htmlspecialchars($currentCloudName) ?>.</p>
              
              <?php if ($currentDepth == 2): ?>
                <button class="primary-button" id="btn-open-schedule" style="margin-top: 24px; width: auto; padding: 10px 24px; border-radius: 8px;">
                  + Set Class Schedule
                </button>
              <?php elseif ($currentDepth < 3): ?>
                <button class="primary-button" id="btn-open-category" style="margin-top: 24px; width: auto; padding: 10px 24px; border-radius: 8px;">
                  + Add Content
                </button>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </section>

        <div class="dashboard-columns">
          <section class="dashboard-card">
            <div class="card-heading">
              <h2>Upcoming</h2>
            </div>
            <div class="empty-small">
              <span>○</span>
              <div>
                <strong>No clouds coming up.</strong>
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
                <strong>Your sky seems clear!</strong>
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
              <p>Start creating inside your clouds and your recent items will show up here.</p>
            </div>
          </div>
        </section>
      </main>
    </div>

    <div class="modal-overlay" id="schedule-modal">
      <div class="modal schedule-modal">
        <button type="button" class="modal-close" id="close-schedule-modal">×</button>
        <div class="modal-icon">📅</div>
        <h2 id="schedule-title">Set Class Schedule</h2>
        <p class="modal-description">When is this class and when are your exams?</p>
        
        <form action="../Assets/backend/generate_schedule.php" method="POST">
          <input type="hidden" name="cloud_id" value="<?= $currentCloudId ?>">
          
          <div class="settings-field">
            <label for="class-day" style="display: block; margin-top: 16px;">Day of the class</label>
            <select name="class_day" id="class-day" class="modal-input" style="width: 100%; margin: 8px 0 16px 0; padding: 12px; border-radius: 8px; border: 1px solid #e5e7eb;">
              <option value="monday">Monday</option>
              <option value="tuesday">Tuesday</option>
              <option value="wednesday">Wednesday</option>
              <option value="thursday">Thursday</option>
              <option value="friday">Friday</option>
              <option value="saturday">Saturday</option>
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

    <div class="modal-overlay" id="category-modal">
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
        <button class="primary-button" id="save-categories">Save Cloud</button>
      </div>
    </div>

    <div class="modal-overlay" id="settings-modal">
    </div>

    <div class="modal-overlay" id="cloud-modal">
    </div>

   <div class="modal-overlay" id="onboarding-modal">
    </div>

    <div class="info-popover" id="info-popover-template">
      <strong><span>✦</span> <span id="info-popover-title"></span></strong>
      <span id="info-popover-body"></span>
    </div>

    <script src="../Assets/Js/main.js"></script>
    
    <script>
        const isFirstTimeUser = <?php echo json_encode($isFirstTimeUser); ?>;
        document.addEventListener("DOMContentLoaded", () => {
          if (isFirstTimeUser) {
            const tour = new KumoTour();
            tour.start();
          }
        });

        // Sidebar State Persistence 
        const sidebarDetails = document.querySelectorAll('.cloud-folder');
        const openFolders = JSON.parse(localStorage.getItem('kumoSidebarState')) || [];

        sidebarDetails.forEach(details => {
          const id = details.getAttribute('data-cloud-id');
          
          if (openFolders.includes(id)) {
            details.classList.add('preload-open');
            details.setAttribute('open', '');
          }

          details.addEventListener('toggle', () => {
            let currentOpen = JSON.parse(localStorage.getItem('kumoSidebarState')) || [];
            if (details.open) {
              if (!currentOpen.includes(id)) currentOpen.push(id);
              details.classList.remove('preload-open');
            } else {
              currentOpen = currentOpen.filter(item => item !== id);
            }
            localStorage.setItem('kumoSidebarState', JSON.stringify(currentOpen));
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
          btnOpenSchedule.addEventListener('click', () => {
            scheduleModal.classList.add('active');
            document.body.style.overflow = 'hidden';
          });
        }

        if (closeScheduleModal) {
          closeScheduleModal.addEventListener('click', () => {
            scheduleModal.classList.remove('active');
            document.body.style.overflow = '';
          });
        }

        if (btnOpenCategory) {
          btnOpenCategory.addEventListener('click', () => {
            categoryModal.classList.add('active');
            document.body.style.overflow = 'hidden';
          });
        }

        if (closeCategoryModal) {
          closeCategoryModal.addEventListener('click', () => {
            categoryModal.classList.remove('active');
            document.body.style.overflow = '';
          });
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
              alert("Ready to build: " + selectedCategories.join(", ") + " inside <?= htmlspecialchars($currentCloudName) ?>!");
              categoryModal.classList.remove('active');
              document.body.style.overflow = '';
            });
        }
    </script>
  </body>
</html>