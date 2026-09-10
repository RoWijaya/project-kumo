<?php
require_once __DIR__ . '/../Assets/backend/auth.php';
require_once __DIR__ . '/../assets/backend/db.php';

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
    $currentNote = $row['content'];
    $lastUpdated = date("M j, Y - g:i A", strtotime($row['last_updated']));
}
$noteStmt->close();
$conn->close();
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($cloudName) ?> Notes - Kumo</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;1,400&family=Poppins:wght@300;400;500;600;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet" />
    
    <link rel="stylesheet" href="../Assets/Css/editor.css" />
    
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet" />
  </head>
  <body>
    <form action="../Assets/backend/save_note.php" method="POST" id="note-form" style="display: flex; flex-direction: column; height: 100%;">
      <input type="hidden" name="cloud_id" value="<?= $cloudId ?>">
      <input type="hidden" name="content" id="hidden-content">
      
      <header class="editor-header">
        <div class="header-left">
          <a href="homepage.php?cloud_id=<?= $cloudId ?>" class="back-link">
            <span style="font-size: 1.2rem;">←</span> Back to <?= htmlspecialchars($cloudName) ?>
          </a>
          <div class="divider"></div>
          <span class="last-saved">
            <?php if ($lastUpdated): ?>
              Last saved: <?= $lastUpdated ?>
            <?php else: ?>
              Unsaved notebook
            <?php endif; ?>
          </span>
        </div>
        
        <button type="submit" class="primary-button" id="save-btn">
          <span>✓</span> Save Notes
        </button>
      </header>

      <div class="editor-container">
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
        btn.innerHTML = 'Saving...';
        btn.style.opacity = '0.7';
        btn.style.cursor = 'not-allowed';
      });
    </script>
  </body>
</html>