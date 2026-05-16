<?php
require_once __DIR__ . '/db.php';

function start_session_once(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function current_user(): ?array {
    start_session_once();
    if (empty($_SESSION['user_id'])) return null;

    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare(
            'SELECT id, username, email, avatar, bio, created_at
             FROM users WHERE id = ?'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;

        // If the row was deleted while the session lived on, clean up.
        if ($user === null) {
            $_SESSION = [];
            session_destroy();
        }
    }
    return $user;
}

function require_login(): void {
    if (!current_user()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function require_guest(): void {
    if (current_user()) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

function login_user(int $user_id): void {
    start_session_once();
    session_regenerate_id(true); // prevent session fixation
    $_SESSION['user_id'] = $user_id;
}

function logout_user(): void {
    start_session_once();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Simple session-based rate limiter.
 * Returns false when the caller has exceeded $max_attempts in $window_seconds.
 * Good enough for protecting login forms from casual brute force.
 */
function rate_limit_check(string $key, int $max_attempts, int $window_seconds): bool {
    start_session_once();
    $now      = time();
    $attempts = $_SESSION['rate_limit'][$key] ?? [];
    $attempts = array_values(array_filter($attempts, fn($t) => $t > $now - $window_seconds));

    if (count($attempts) >= $max_attempts) {
        $_SESSION['rate_limit'][$key] = $attempts;
        return false;
    }
    $attempts[] = $now;
    $_SESSION['rate_limit'][$key] = $attempts;
    return true;
}

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
