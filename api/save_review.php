<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}
if (!csrf_check()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$tmdb_id = (int) ($_POST['tmdb_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if ($tmdb_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid movie']);
    exit;
}
if (strlen($content) < 3) {
    http_response_code(400);
    echo json_encode(['error' => 'Review is too short']);
    exit;
}
if (strlen($content) > 5000) {
    http_response_code(400);
    echo json_encode(['error' => 'Review is too long (max 5000 chars)']);
    exit;
}

$stmt = db()->prepare(
    'INSERT INTO reviews (user_id, tmdb_id, content) VALUES (?, ?, ?)'
);
$stmt->execute([$user['id'], $tmdb_id, $content]);

echo json_encode(['success' => true, 'review_id' => (int) db()->lastInsertId()]);
