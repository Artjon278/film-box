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
$status  = $_POST['status'] ?? '';
$allowed = ['want', 'watching', 'watched', 'dropped'];

if ($tmdb_id <= 0 || !in_array($status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$stmt = db()->prepare(
    'INSERT INTO user_movies (user_id, tmdb_id, status)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE status = VALUES(status)'
);
$stmt->execute([$user['id'], $tmdb_id, $status]);

echo json_encode(['success' => true, 'status' => $status]);
