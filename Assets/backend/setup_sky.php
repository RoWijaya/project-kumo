<?php
session_start();
require_once __DIR__ . '/db.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data || !isset($data['stage'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid payload']);
    exit;
}

try {
    $conn->begin_transaction();

    if ($data['stage'] === 'university') {
        $semester = $data['semester'];
        $classes = $data['classes'];

        $stmt = $conn->prepare("INSERT INTO clouds (user_id, parent_id, name, cloud_type, icon, color_theme) VALUES (?, NULL, 'Education', 'category', '🎓', 'blue')");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $educationId = $conn->insert_id; 
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO clouds (user_id, parent_id, name, cloud_type, icon, color_theme) VALUES (?, ?, ?, 'semester', '📅', 'orange')");
        $stmt->bind_param("iis", $userId, $educationId, $semester);
        $stmt->execute();
        $semesterId = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO clouds (user_id, parent_id, name, cloud_type, icon, color_theme) VALUES (?, ?, ?, 'class', '☁️', 'green')");
        foreach ($classes as $className) {
            $stmt->bind_param("iis", $userId, $semesterId, $className);
            $stmt->execute();
        }
        $stmt->close();

        $updateUser = $conn->prepare("UPDATE userdata SET setup_completed = 1 WHERE id = ?");
        $updateUser->bind_param("i", $userId);
        $updateUser->execute();
        $updateUser->close();
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>