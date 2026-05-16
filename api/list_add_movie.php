<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) { http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit; }
if (!csrf_check()) { http_response_code(403); echo json_encode(['error' => 'Invalid CSRF']); exit; }

$list_id = (int) ($_POST['list_id'] ?? 0);
$tmdb_id = (int) ($_POST['tmdb_id'] ?? 0);

if ($list_id <= 0 || $tmdb_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Verify the list belongs to this user
$stmt = db()->prepare('SELECT id FROM custom_lists WHERE id = ? AND user_id = ?');
$stmt->execute([$list_id, $user['id']]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'List not found or not yours']);
    exit;
}

$stmt = db()->prepare(
    'INSERT INTO list_items (list_id, tmdb_id) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE added_at = added_at'
);
$stmt->execute([$list_id, $tmdb_id]);

echo json_encode(['success' => true]);
