<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/tmdb.php';

$list_id = (int) ($_GET['id'] ?? 0);
if ($list_id <= 0) {
    http_response_code(404);
    die('Invalid list ID');
}

$stmt = db()->prepare(
    'SELECT cl.*, u.username AS owner
     FROM custom_lists cl
     JOIN users u ON u.id = cl.user_id
     WHERE cl.id = ?'
);
$stmt->execute([$list_id]);
$list = $stmt->fetch();
if (!$list) {
    http_response_code(404);
    die('List not found');
}

$user    = current_user();
$is_owner = $user && (int) $user['id'] === (int) $list['user_id'];

if (!$list['is_public'] && !$is_owner) {
    http_response_code(403);
    die('This list is private');
}

$stmt = db()->prepare(
    "SELECT li.tmdb_id, li.added_at,
            m.title, m.poster_path, m.release_date, m.vote_average
     FROM list_items li
     LEFT JOIN movies_cache m ON m.tmdb_id = li.tmdb_id
     WHERE li.list_id = ?
     ORDER BY li.added_at DESC"
);
$stmt->execute([$list_id]);
$items = $stmt->fetchAll();

$_title = $list['name'];
$_page  = 'lists';
include __DIR__ . '/includes/header.php';
?>

<style>
.list-page { padding: 3rem; }
.list-page-header {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}
.list-page-header h1 {
    font-family: var(--font-serif);
    font-size: clamp(2rem, 4vw, 3rem);
    color: var(--ivory);
}
.list-page-meta {
    color: var(--muted);
    font-size: 0.9rem;
    margin-top: 0.4rem;
}
.list-page-meta a { color: var(--amber); }
.list-page-desc {
    color: var(--text);
    margin-top: 1rem;
    max-width: 720px;
    line-height: 1.7;
}
.list-actions {
    display: flex;
    gap: 0.6rem;
    margin-top: 1.2rem;
}
.remove-btn {
    position: absolute;
    top: 0.6rem; right: 0.6rem;
    width: 28px; height: 28px;
    background: rgba(10,10,10,0.85);
    color: var(--text);
    border: 1px solid var(--border);
    cursor: pointer;
    z-index: 3;
    font-size: 0.9rem;
    transition: all 0.2s;
}
.remove-btn:hover {
    color: var(--error);
    border-color: var(--error);
}
.empty-list {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--surface);
    border: 1px dashed var(--border);
}
.empty-list h3 {
    font-family: var(--font-serif);
    color: var(--ivory);
    margin-bottom: 0.5rem;
}
</style>

<div class="list-page">
    <div class="list-page-header">
        <h1><?= e($list['name']) ?></h1>
        <div class="list-page-meta">
            <?= count($items) ?> film<?= count($items) == 1 ? '' : 's' ?> ·
            by <a href="<?= e(BASE_URL) ?>/profile.php?user=<?= urlencode($list['owner']) ?>"><?= e($list['owner']) ?></a> ·
            <?= $list['is_public'] ? 'Public' : 'Private' ?>
        </div>
        <?php if (!empty($list['description'])): ?>
            <p class="list-page-desc"><?= e($list['description']) ?></p>
        <?php endif; ?>
        <?php if ($is_owner): ?>
            <div class="list-actions">
                <a href="<?= e(BASE_URL) ?>/lists.php" class="btn-outline">← BACK TO LISTS</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($items)): ?>
        <div class="empty-list">
            <h3>No films in this list yet.</h3>
            <p style="color:var(--muted); margin-bottom:1.2rem;">
                <?php if ($is_owner): ?>
                    Open any film and click "Add to list" to start filling it.
                <?php else: ?>
                    Check back later.
                <?php endif; ?>
            </p>
            <a href="<?= e(BASE_URL) ?>/search.php" class="btn-outline">DISCOVER FILMS</a>
        </div>
    <?php else: ?>
        <div class="movies-grid">
            <?php foreach ($items as $f): ?>
                <a href="<?= e(BASE_URL) ?>/movie.php?id=<?= (int) $f['tmdb_id'] ?>" class="movie-card" style="position:relative;">
                    <?php if ($is_owner): ?>
                        <button class="remove-btn" title="Remove from list"
                                data-id="<?= (int) $f['tmdb_id'] ?>">×</button>
                    <?php endif; ?>
                    <div class="movie-poster">
                        <img src="<?= e(tmdb_poster_url($f['poster_path'] ?? null)) ?>"
                             alt="<?= e($f['title'] ?? '') ?>" loading="lazy">
                        <?php if (!empty($f['vote_average'])): ?>
                            <div class="movie-score"><?= number_format((float) $f['vote_average'], 1) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="movie-info">
                        <h3 class="movie-title"><?= e($f['title'] ?? 'Film #' . $f['tmdb_id']) ?></h3>
                        <div class="movie-year">
                            <?= !empty($f['release_date']) ? substr($f['release_date'], 0, 4) : '—' ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($is_owner): ?>
<script>
const BASE = '<?= e(BASE_URL) ?>';
const CSRF = '<?= e(csrf_token()) ?>';
const LIST_ID = <?= (int) $list_id ?>;

document.querySelectorAll('.remove-btn').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (!confirm('Remove this film from the list?')) return;
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('list_id', LIST_ID);
        fd.append('tmdb_id', btn.dataset.id);
        const res = await fetch(BASE + '/api/list_remove_movie.php', { method: 'POST', body: fd });
        if (res.ok) btn.closest('.movie-card').remove();
        else alert('Failed to remove');
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
