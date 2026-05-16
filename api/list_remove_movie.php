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

// Ensure ownership via JOIN
$stmt = db()->prepare(
    'DELETE li FROM list_items li
     JOIN custom_lists cl ON cl.id = li.list_id
     WHERE li.list_id = ? AND li.tmdb_id = ? AND cl.user_id = ?'
);
$stmt->execute([$list_id, $tmdb_id, $user['id']]);

echo json_encode(['success' => true, 'removed' => $stmt->rowCount()]);
