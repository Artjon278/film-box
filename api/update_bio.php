<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');

$me = current_user();
if (!$me)          { http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit; }
if (!csrf_check()) { http_response_code(403); echo json_encode(['error' => 'Invalid CSRF']);  exit; }

$bio = trim($_POST['bio'] ?? '');
if (mb_strlen($bio) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Bio too long (max 500 chars)']);
    exit;
}

$stmt = db()->prepare('UPDATE users SET bio = ? WHERE id = ?');
$stmt->execute([$bio === '' ? null : $bio, $me['id']]);

echo json_encode(['success' => true]);
