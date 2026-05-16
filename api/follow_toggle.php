<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');

$me = current_user();
if (!$me)             { http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit; }
if (!csrf_check())    { http_response_code(403); echo json_encode(['error' => 'Invalid CSRF']);  exit; }

$target_id = (int) ($_POST['target_user_id'] ?? 0);
if ($target_id <= 0 || $target_id === (int) $me['id']) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid target']);
    exit;
}

// Verify target exists
$stmt = db()->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$target_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

// Is there already a row?
$stmt = db()->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?');
$stmt->execute([$me['id'], $target_id]);
$already = (bool) $stmt->fetchColumn();

if ($already) {
    $stmt = db()->prepare('DELETE FROM follows WHERE follower_id = ? AND followed_id = ?');
    $stmt->execute([$me['id'], $target_id]);
    $now_following = false;
} else {
    $stmt = db()->prepare('INSERT INTO follows (follower_id, followed_id) VALUES (?, ?)');
    $stmt->execute([$me['id'], $target_id]);
    $now_following = true;
}

$count = (int) db()->query(
    'SELECT COUNT(*) FROM follows WHERE followed_id = ' . $target_id
)->fetchColumn();

echo json_encode([
    'success'   => true,
    'following' => $now_following,
    'followers' => $count,
]);
