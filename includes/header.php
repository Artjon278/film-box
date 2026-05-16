<?php
require_once __DIR__ . '/auth.php';
start_session_once();
$_current = current_user();
$_page    = $_page ?? '';
$_title   = $_title ?? APP_NAME;
?><!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($_title) ?> — <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body>

<nav class="nav">
    <a href="<?= e(BASE_URL) ?>/" class="nav-logo">
        <span class="dot"></span>
        <?= e(APP_NAME) ?>
    </a>
    <ul class="nav-links">
        <li><a href="<?= e(BASE_URL) ?>/" class="<?= $_page === 'home' ? 'active' : '' ?>">Home</a></li>
        <li><a href="<?= e(BASE_URL) ?>/search.php" class="<?= $_page === 'search' ? 'active' : '' ?>">Search</a></li>
        <?php if ($_current): ?>
            <li><a href="<?= e(BASE_URL) ?>/dashboard.php" class="<?= $_page === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
            <li><a href="<?= e(BASE_URL) ?>/lists.php" class="<?= $_page === 'lists' ? 'active' : '' ?>">Lists</a></li>
            <li><a href="<?= e(BASE_URL) ?>/recommendations.php" class="<?= $_page === 'recs' ? 'active' : '' ?>">For You</a></li>
        <?php endif; ?>
    </ul>
    <div class="nav-user">
        <?php if ($_current): ?>
            <a href="<?= e(BASE_URL) ?>/profile.php?user=<?= urlencode($_current['username']) ?>" style="text-decoration:none; color:inherit;">
                <span>Hello, <span class="username"><?= e($_current['username']) ?></span></span>
            </a>
            <a href="<?= e(BASE_URL) ?>/logout.php" class="btn-outline" style="padding:0.45rem 1rem; font-size:0.7rem;">Logout</a>
        <?php else: ?>
            <a href="<?= e(BASE_URL) ?>/login.php" style="font-size:0.85rem; color:var(--muted);">Log in</a>
            <a href="<?= e(BASE_URL) ?>/register.php" class="btn-outline" style="padding:0.45rem 1rem; font-size:0.7rem;">Sign up</a>
        <?php endif; ?>
    </div>
</nav>
