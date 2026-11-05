<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

function kumo_avatar_json(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function kumo_is_owned_avatar_path(string $path): bool {
    return (bool)preg_match('~^assets/uploads/avatars/[a-f0-9]{40}\.(?:jpg|png|webp)$~', $path);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') kumo_avatar_json(405, ['success' => false, 'error' => 'Method not allowed.']);
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) kumo_avatar_json(403, ['success' => false, 'error' => 'Your session expired. Refresh and try again.']);
$userId = (int)$_SESSION['user_id'];
$uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';

try {
    $find = $conn->prepare('SELECT avatar_path FROM userdata WHERE id = ? LIMIT 1');
    $find->bind_param('i', $userId); $find->execute();
    $account = $find->get_result()->fetch_assoc(); $find->close();
    if (!$account) kumo_avatar_json(404, ['success' => false, 'error' => 'Account not found.']);
    $oldPath = (string)($account['avatar_path'] ?? '');

    if ((string)($_POST['action'] ?? '') === 'remove') {
        $update = $conn->prepare('UPDATE userdata SET avatar_path = NULL WHERE id = ?');
        $update->bind_param('i', $userId); $update->execute(); $update->close();
        if (kumo_is_owned_avatar_path($oldPath)) {
            $oldFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $oldPath);
            if (is_file($oldFile)) @unlink($oldFile);
        }
        kumo_avatar_json(200, ['success' => true, 'message' => 'Your profile picture was removed.']);
    }

    if (!isset($_FILES['avatar']) || !is_array($_FILES['avatar'])) kumo_avatar_json(422, ['success' => false, 'error' => 'Choose an image first.']);
    $file = $_FILES['avatar'];
    if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        kumo_avatar_json(422, ['success' => false, 'error' => ((int)($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || (int)($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) ? 'The image must be 2 MB or smaller.' : 'The image could not be uploaded. Try another file.']);
    }
    if (!is_uploaded_file((string)$file['tmp_name']) || (int)$file['size'] < 1 || (int)$file['size'] > 2 * 1024 * 1024) kumo_avatar_json(422, ['success' => false, 'error' => 'Choose a valid image no larger than 2 MB.']);
    $imageInfo = @getimagesize((string)$file['tmp_name']);
    if (!is_array($imageInfo) || empty($imageInfo['mime'])) kumo_avatar_json(422, ['success' => false, 'error' => 'That file is not a valid raster image.']);
    $mime = strtolower((string)$imageInfo['mime']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) kumo_avatar_json(422, ['success' => false, 'error' => 'Use a JPEG, PNG, or WebP image. SVG is not supported.']);
    $width = (int)($imageInfo[0] ?? 0); $height = (int)($imageInfo[1] ?? 0);
    if ($width < 1 || $height < 1 || $width > 2500 || $height > 2500 || $width * $height > 8000000) kumo_avatar_json(422, ['success' => false, 'error' => 'The image dimensions are too large. Choose an image up to 2500 × 2500 pixels.']);
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if (!isset($extensions[$finfo->file((string)$file['tmp_name'])])) kumo_avatar_json(422, ['success' => false, 'error' => 'The file contents do not match a supported image type.']);
    }
    // Decode the full image when GD is available. Hosts without GD still get MIME, structure and dimension validation.
    if (function_exists('imagecreatefromstring')) {
        $bytes = file_get_contents((string)$file['tmp_name']);
        $decoded = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
        if ($decoded === false) kumo_avatar_json(422, ['success' => false, 'error' => 'The image could not be decoded. Choose a different file.']);
        imagedestroy($decoded);
    }
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) throw new RuntimeException('Avatar storage is unavailable.');
    $randomName = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    $target = $uploadDir . DIRECTORY_SEPARATOR . $randomName;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) throw new RuntimeException('Avatar storage failed.');
    @chmod($target, 0644);
    $relativePath = 'assets/uploads/avatars/' . $randomName;
    try {
        $update = $conn->prepare('UPDATE userdata SET avatar_path = ? WHERE id = ?');
        $update->bind_param('si', $relativePath, $userId); $update->execute(); $update->close();
    } catch (Throwable $e) {
        @unlink($target);
        throw $e;
    }
    if (kumo_is_owned_avatar_path($oldPath)) {
        $oldFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $oldPath);
        if (is_file($oldFile)) @unlink($oldFile);
    }
    kumo_avatar_json(200, ['success' => true, 'message' => 'Your profile picture was updated.']);
} catch (Throwable $e) {
    error_log('Kumo avatar update failed: ' . $e->getMessage());
    kumo_avatar_json(500, ['success' => false, 'error' => 'The profile picture could not be saved. Apply the profile migration and try again.']);
}
