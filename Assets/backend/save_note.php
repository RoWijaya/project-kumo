<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $cloudId = (int)$_POST['cloud_id'];
    $content = $_POST['content'] ?? '';

    if ($cloudId > 0) {
        $checkStmt = $conn->prepare("SELECT id FROM notes WHERE user_id = ? AND cloud_id = ?");
        $checkStmt->bind_param("ii", $userId, $cloudId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $updateStmt = $conn->prepare("UPDATE notes SET content = ? WHERE user_id = ? AND cloud_id = ?");
            $updateStmt->bind_param("sii", $content, $userId, $cloudId);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            $insertStmt = $conn->prepare("INSERT INTO notes (user_id, cloud_id, content) VALUES (?, ?, ?)");
            $insertStmt->bind_param("iis", $userId, $cloudId, $content);
            $insertStmt->execute();
            $insertStmt->close();
        }
        $checkStmt->close();
    }
    
    header("Location: ../../Main/note_editor.php?cloud_id=" . $cloudId);
    exit();
}