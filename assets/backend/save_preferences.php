<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

function kumo_preferences_json(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') kumo_preferences_json(405, ['success' => false, 'error' => 'Method not allowed.']);
if (!kumo_csrf_valid($_POST['csrf_token'] ?? null)) kumo_preferences_json(403, ['success' => false, 'error' => 'Your session expired. Refresh and try again.']);

$theme = (string)($_POST['theme'] ?? 'light');
$accent = (string)($_POST['accent_color'] ?? 'sage');
$density = (string)($_POST['density'] ?? 'comfortable');
$firstSection = (string)($_POST['first_section'] ?? 'clouds');
if (!in_array($theme, ['light', 'dark', 'system'], true)) kumo_preferences_json(422, ['success' => false, 'error' => 'Choose Light, Dark, or System appearance.']);
if (!in_array($accent, ['sage', 'forest', 'ocean', 'lavender'], true)) kumo_preferences_json(422, ['success' => false, 'error' => 'Choose a supported accent color.']);
if (!in_array($density, ['comfortable', 'compact'], true)) kumo_preferences_json(422, ['success' => false, 'error' => 'Choose a supported layout density.']);
if (!in_array($firstSection, ['clouds', 'upcoming', 'recent'], true)) kumo_preferences_json(422, ['success' => false, 'error' => 'Choose a supported dashboard section.']);
$flags = [];
foreach (['reduce_motion', 'show_clouds', 'show_upcoming', 'show_recent_activity', 'show_progress'] as $flag) {
    $raw = (string)($_POST[$flag] ?? '0');
    if (!in_array($raw, ['0', '1'], true)) kumo_preferences_json(422, ['success' => false, 'error' => 'One of the settings values is invalid.']);
    $flags[$flag] = (int)$raw;
}
$userId = (int)$_SESSION['user_id'];
try {
    $stmt = $conn->prepare('INSERT INTO user_preferences (user_id, theme, accent_color, density, reduce_motion, show_clouds, show_upcoming, show_recent_activity, show_progress, first_section) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE theme=VALUES(theme), accent_color=VALUES(accent_color), density=VALUES(density), reduce_motion=VALUES(reduce_motion), show_clouds=VALUES(show_clouds), show_upcoming=VALUES(show_upcoming), show_recent_activity=VALUES(show_recent_activity), show_progress=VALUES(show_progress), first_section=VALUES(first_section)');
    $types = 'isss' . 'iiiiis';
    $stmt->bind_param($types, $userId, $theme, $accent, $density, $flags['reduce_motion'], $flags['show_clouds'], $flags['show_upcoming'], $flags['show_recent_activity'], $flags['show_progress'], $firstSection);
    $stmt->execute();
    $stmt->close();
    kumo_preferences_json(200, ['success' => true, 'message' => 'Your preferences are saved.', 'preferences' => array_merge(['theme' => $theme, 'accent_color' => $accent, 'density' => $density, 'first_section' => $firstSection], $flags)]);
} catch (Throwable $e) {
    error_log('Kumo preference update failed: ' . $e->getMessage());
    kumo_preferences_json(500, ['success' => false, 'error' => 'Settings could not be saved. Apply the preferences migration and try again.']);
}
