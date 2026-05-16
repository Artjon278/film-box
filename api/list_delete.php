<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) { http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit; }
if (!csrf_check()) { http_response_code(403); echo json_encode(['error' => 'Invalid CSRF']); exit; }

$list_id = (int) ($_POST['list_id'] ?? 0);
if ($list_id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid list']); exit; }

// Only owner can delete
$stmt = db()->prepare('DELETE FROM custom_lists WHERE id = ? AND user_id = ?');
$stmt->execute([$list_id, $user['id']]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'List not found or not yours']);
    exit;
}

echo json_encode(['success' => true]);
