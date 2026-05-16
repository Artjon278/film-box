<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_guest();

$errors = [];
$old    = ['identifier' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';
        $old        = ['identifier' => $identifier];

        // Rate limit: 5 attempts per 15 minutes per session
        $rl_key = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!rate_limit_check($rl_key, 5, 900)) {
            $errors[] = 'Too many login attempts. Please wait a few minutes and try again.';
        } elseif ($identifier === '' || $password === '') {
            $errors[] = 'Please fill in both fields.';
        } else {
            $stmt = db()->prepare(
                'SELECT id, password_hash FROM users
                 WHERE username = ? OR email = ?
                 LIMIT 1'
            );
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                login_user((int) $user['id']);
                header('Location: ' . BASE_URL . '/dashboard.php');
                exit;
            }
            // Same generic message whether the user exists or not — don't leak.
            $errors[] = 'Invalid credentials.';
        }
    }
}

$_title = 'Log in';
$_page  = 'login';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-label">WELCOME BACK</div>
        <h1 class="auth-title">Log in</h1>
        <p class="auth-subtitle">Pick up where you left off.</p>

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
                <label class="form-label" for="identifier">Username or email</label>
                <input class="form-input" type="text" id="identifier" name="identifier"
                       value="<?= e($old['identifier']) ?>"
                       required autocomplete="username" autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input class="form-input" type="password" id="password" name="password"
                       required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-primary">LOG IN</button>
        </form>

        <div class="auth-foot">
            New here? <a href="<?= e(BASE_URL) ?>/register.php">Create an account</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
