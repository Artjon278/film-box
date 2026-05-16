<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');

$user = current_user();
if (!$user) { http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit; }
if (!csrf_check()) { http_response_code(403); echo json_encode(['error' => 'Invalid CSRF']); exit; }

$name        = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$is_public   = !empty($_POST['is_public']) && $_POST['is_public'] !== '0' ? 1 : 0;

if ($name === '' || strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'List name is required (max 100 chars)']);
    exit;
}
if (strlen($description) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Description too long (max 500 chars)']);
    exit;
}

$stmt = db()->prepare(
    'INSERT INTO custom_lists (user_id, name, description, is_public) VALUES (?, ?, ?, ?)'
);
$stmt->execute([$user['id'], $name, $description ?: null, $is_public]);

echo json_encode(['success' => true, 'list_id' => (int) db()->lastInsertId()]);
