<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_guest();

$errors = [];
$old    = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $username         = trim($_POST['username'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $password         = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $old              = ['username' => $username, 'email' => $email];

        if (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = 'Username must be 3–50 characters.';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, and underscores.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $password_confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $stmt = db()->prepare(
                'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1'
            );
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = 'Username or email is already in use.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = db()->prepare(
                    'INSERT INTO users (username, email, password_hash)
                     VALUES (?, ?, ?)'
                );
                $stmt->execute([$username, $email, $hash]);
                login_user((int) db()->lastInsertId());
                header('Location: ' . BASE_URL . '/dashboard.php');
                exit;
            }
        }
    }
}

$_title = 'Sign up';
$_page  = 'register';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-label">JOIN THE BOX</div>
        <h1 class="auth-title">Create your account</h1>
        <p class="auth-subtitle">Track films, write reviews, build your watchlist.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input class="form-input" type="text" id="username" name="username"
                       value="<?= e($old['username']) ?>"
                       required minlength="3" maxlength="50"
                       pattern="[a-zA-Z0-9_]+"
                       autocomplete="username">
                <div class="form-help">3–50 chars. Letters, numbers, underscore.</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input class="form-input" type="email" id="email" name="email"
                       value="<?= e($old['email']) ?>"
                       required autocomplete="email">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input class="form-input" type="password" id="password" name="password"
                       required minlength="8" autocomplete="new-password">
                <div class="form-help">Minimum 8 characters.</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirm">Confirm password</label>
                <input class="form-input" type="password" id="password_confirm" name="password_confirm"
                       required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit" class="btn-primary">CREATE ACCOUNT</button>
        </form>

        <div class="auth-foot">
            Already have an account? <a href="<?= e(BASE_URL) ?>/login.php">Log in</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
