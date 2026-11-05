<?php
require_once __DIR__ . '/../assets/backend/auth.php';
require_once __DIR__ . '/../assets/backend/db.php';
require_once __DIR__ . '/../assets/backend/note_sanitizer.php';

$userId = $_SESSION['user_id'];
$cloudId = isset($_GET['cloud_id']) ? (int)$_GET['cloud_id'] : 0;

if ($cloudId === 0) {
    header("Location: homepage.php");
    exit();
}

$nameStmt = $conn->prepare("SELECT name, parent_id FROM clouds WHERE id = ? AND user_id = ?");
$nameStmt->bind_param("ii", $cloudId, $userId);
$nameStmt->execute();
$nameResult = $nameStmt->get_result();
$cloudName = "Notes";
if ($row = $nameResult->fetch_assoc()) {
    $cloudName = $row['name'];
}
$nameStmt->close();

$noteStmt = $conn->prepare("SELECT content, last_updated FROM notes WHERE user_id = ? AND cloud_id = ?");
$noteStmt->bind_param("ii", $userId, $cloudId);
$noteStmt->execute();
$noteResult = $noteStmt->get_result();
$currentNote = '';
$lastUpdated = '';
if ($row = $noteResult->fetch_assoc()) {
    $currentNote = kumo_sanitize_note_html((string)$row['content']);
    $lastUpdated = date("M j, Y - g:i A", strtotime($row['last_updated']));
}
$noteStmt->close();
$conn->close();
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/svg+xml" href="../assets/icons/kumo_fluffy_cloud_icon.svg" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($cloudName) ?> Notes - Kumo</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:ital,wght@0,400;0,500;0,600;1,400&family=Poppins:wght@300;400;500;600;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet" />
    
    <script src="../assets/JS/theme.js?v=20261029-v4"></script>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/editor.css?v=20261029-v5" />
  </head>
  <body>
    <form action="../assets/backend/save_note.php" method="POST" id="note-form" class="note-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="cloud_id" value="<?= $cloudId ?>">
      <input type="hidden" name="content" id="hidden-content">
      
      <header class="editor-header">
        <div class="header-left">
          <a href="homepage.php?cloud_id=<?= $cloudId ?>" class="back-link" aria-label="Back to <?= htmlspecialchars($cloudName, ENT_QUOTES, 'UTF-8') ?>">
            <span class="back-icon" aria-hidden="true">&larr;</span><span class="back-copy">Cloud</span>
          </a>
          <span class="divider" aria-hidden="true"></span>
          <div class="header-context">
            <span class="header-eyebrow">NOTEBOOK</span>
            <strong><?= htmlspecialchars($cloudName, ENT_QUOTES, 'UTF-8') ?></strong>
          </div>
          <div class="last-saved" role="status" aria-live="polite">
            <span class="save-indicator" aria-hidden="true"></span>
            <?php if (isset($_GET['saved'])): ?>
              Saved just now
            <?php elseif ($lastUpdated): ?>
              Last saved <?= htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8') ?>
            <?php else: ?>
              New notebook
            <?php endif; ?>
          </div>
        </div>
        
        <button type="submit" class="primary-button" id="save-btn" aria-label="Save note">
          <span aria-hidden="true">&#10003;</span> Save note
        </button>
      </header>

      <div class="editor-container">
        <div class="editor-heading">
          <p class="editor-eyebrow">A QUIET PLACE TO THINK</p>
          <h1>Notes for <?= htmlspecialchars($cloudName, ENT_QUOTES, 'UTF-8') ?></h1>
          <p>Collect the details, questions, and ideas you want to keep close.</p>
        </div>
        <div id="editor"><?= $currentNote ?></div>
      </div>
    </form>

    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    
    <script>window.Quill = Quill;</script>
    
    <script src="https://cdn.jsdelivr.net/npm/quill-image-resize-module@3.0.0/image-resize.min.js"></script>
    
    <script>
      const Font = Quill.import('formats/font');
      Font.whitelist = ['poppins', 'lora', 'roboto-mono'];
      Quill.register(Font, true);

      const quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Start typing your class notes here...',
        modules: {
          imageResize: {
            displaySize: true 
          },
          toolbar: [
            [{ 'font': ['poppins', 'lora', 'roboto-mono'] }, { 'size': ['small', false, 'large', 'huge'] }],
            [{ 'header': [1, 2, 3, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }, { 'align': [] }],
            ['blockquote', 'code-block'],
            ['link', 'image'],
            ['clean'] // remove formatting button
          ]
        }
      });

      document.getElementById('note-form').addEventListener('submit', function() {
        document.getElementById('hidden-content').value = quill.root.innerHTML;
        
        const btn = document.getElementById('save-btn');
        btn.innerHTML = '<span aria-hidden="true">&#8226;</span> Saving...';
        btn.style.opacity = '0.7';
        btn.style.cursor = 'not-allowed';
      });
    </script>
      <script src="../assets/JS/vendor/gsap.min.js?v=3.15.0"></script>
      <script src="../assets/JS/vendor/ScrollTrigger.min.js?v=3.12.5"></script>
    <script src="../assets/JS/gsap-animations.js?v=20261105-v2"></script>
</body>
</html>
