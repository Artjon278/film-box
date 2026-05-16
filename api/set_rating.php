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
$rating  = (int) ($_POST['rating'] ?? 0);

if ($tmdb_id <= 0 || $rating < 1 || $rating > 10) {
    http_response_code(400);
    echo json_encode(['error' => 'Rating must be 1–10']);
    exit;
}

// Upsert: also default status to 'watched' if no row yet (you usually only rate watched films)
$stmt = db()->prepare(
    "INSERT INTO user_movies (user_id, tmdb_id, status, rating)
     VALUES (?, ?, 'watched', ?)
     ON DUPLICATE KEY UPDATE rating = VALUES(rating)"
);
$stmt->execute([$user['id'], $tmdb_id, $rating]);

echo json_encode(['success' => true, 'rating' => $rating]);
