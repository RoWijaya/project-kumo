<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $parentCloudId = (int)$_POST['cloud_id'];
    $classDay = $_POST['class_day'];
    $midtermWeek = (int)$_POST['midterm_week'];
    $finalWeek = (int)$_POST['final_week'];

    $stmt = $conn->prepare("INSERT INTO clouds (user_id, parent_id, name) VALUES (?, ?, ?)");

    for ($i = 1; $i <= $finalWeek; $i++) {
        if ($i === $midtermWeek) {
            $name = "Midterm Week";
        } elseif ($i === $finalWeek) {
            $name = "Finals Week";
        } else {
            $name = "Week " . $i;
        }
        $stmt->bind_param("iis", $userId, $parentCloudId, $name);
        $stmt->execute();
    }
    
    $stmt->close();

    header("Location: ../../Main/homepage.php?cloud_id=" . $parentCloudId);
    exit();
}