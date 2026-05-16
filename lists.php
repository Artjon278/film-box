<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/tmdb.php';
require_login();
$user = current_user();

// Load my lists + a sample poster + total count
$stmt = db()->prepare(
    "SELECT cl.id, cl.name, cl.description, cl.is_public, cl.created_at,
            (SELECT COUNT(*) FROM list_items li WHERE li.list_id = cl.id) AS film_count,
            (SELECT m.poster_path
             FROM list_items li2
             JOIN movies_cache m ON m.tmdb_id = li2.tmdb_id
             WHERE li2.list_id = cl.id
             ORDER BY li2.added_at DESC LIMIT 1) AS preview_poster
     FROM custom_lists cl
     WHERE cl.user_id = ?
     ORDER BY cl.created_at DESC"
);
$stmt->execute([$user['id']]);
$lists = $stmt->fetchAll();

$_title = 'My Lists';
$_page  = 'lists';
include __DIR__ . '/includes/header.php';
?>

<style>
.lists-page { padding: 3rem; }

.lists-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.lists-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}

.list-card {
    background: var(--surface);
    border: 1px solid var(--border);
    text-decoration: none;
    color: inherit;
    transition: all 0.3s;
    overflow: hidden;
    display: block;
}
.list-card:hover {
    border-color: var(--amber-dim);
    transform: translateY(-3px);
    box-shadow: 0 14px 40px rgba(0,0,0,0.35);
    color: inherit;
}
.list-card-preview {
    aspect-ratio: 16 / 9;
    background: var(--deep);
    overflow: hidden;
    position: relative;
}
.list-card-preview img {
    width: 100%; height: 100%; object-fit: cover;
    object-position: center 30%;
    opacity: 0.7;
}
.list-card-preview::after {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(180deg, transparent 40%, var(--surface) 100%);
}
.list-card-empty-preview {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    color: var(--muted);
    font-family: var(--font-display);
    font-size: 0.85rem;
    letter-spacing: 0.2em;
}
.list-card-body { padding: 1.4rem; }
.list-card h3 {
    font-family: var(--font-serif);
    font-size: 1.4rem;
    color: var(--ivory);
    margin-bottom: 0.3rem;
}
.list-card-meta {
    font-size: 0.75rem;
    color: var(--amber);
    letter-spacing: 0.15em;
    margin-bottom: 0.7rem;
    text-transform: uppercase;
}
.list-card-desc {
    color: var(--muted);
    font-size: 0.88rem;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* New list form (inline) */
.new-list-card {
    background: var(--deep);
    border: 1px dashed var(--border);
    padding: 1.5rem;
}
.new-list-card form {
    display: grid;
    gap: 0.7rem;
}
.new-list-card .row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.7rem;
}

.list-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem 2rem;
    background: var(--surface);
    border: 1px dashed var(--border);
}
.list-empty p { color: var(--muted); margin-top: 0.5rem; }

.list-card-delete {
    position: absolute;
    top: 0.7rem; right: 0.7rem;
    width: 32px; height: 32px;
    background: rgba(10,10,10,0.85);
    color: var(--text);
    border: 1px solid var(--border);
    cursor: pointer;
    z-index: 3;
    font-size: 1rem;
    transition: all 0.2s;
}
.list-card-delete:hover {
    color: var(--error);
    border-color: var(--error);
}
</style>

<div class="lists-page">
    <div class="lists-header">
        <div>
            <div class="section-label" style="font-family:var(--font-display); color:var(--amber); letter-spacing:0.3em; font-size:0.75rem;">YOUR COLLECTIONS</div>
            <h1 style="font-family:var(--font-serif); color:var(--ivory); font-size:2.2rem;">My Lists</h1>
        </div>
        <a href="<?= e(BASE_URL) ?>/dashboard.php" class="section-link" style="font-family:var(--font-display); font-size:0.75rem; letter-spacing:0.2em; color:var(--muted);">← BACK TO DASHBOARD</a>
    </div>

    <div class="lists-grid">
        <!-- New list creation card -->
        <div class="new-list-card">
            <h3 style="font-family:var(--font-serif); color:var(--ivory); margin-bottom:0.7rem;">+ New list</h3>
            <form id="newListForm">
                <?= csrf_field() ?>
                <input type="text" name="name" class="form-input" placeholder="List name (e.g. Top 10 Horror)" required maxlength="100">
                <input type="text" name="description" class="form-input" placeholder="Description (optional)" maxlength="500">
                <div class="row">
                    <label style="display:flex; align-items:center; gap:0.5rem; color:var(--muted); font-size:0.85rem;">
                        <input type="checkbox" name="is_public" checked> Public
                    </label>
                    <button type="submit" class="btn-primary" style="width:auto;">CREATE</button>
                </div>
            </form>
        </div>

        <?php if (empty($lists)): ?>
            <div class="list-empty">
                <h3 style="font-family:var(--font-serif); color:var(--ivory);">No lists yet.</h3>
                <p>Create your first list using the form on the left.</p>
            </div>
        <?php else: ?>
            <?php foreach ($lists as $l): ?>
                <a href="<?= e(BASE_URL) ?>/list.php?id=<?= (int) $l['id'] ?>" class="list-card" style="position:relative;">
                    <button class="list-card-delete" title="Delete"
                            data-id="<?= (int) $l['id'] ?>" data-name="<?= e($l['name']) ?>">×</button>
                    <div class="list-card-preview">
                        <?php if (!empty($l['preview_poster'])): ?>
                            <img src="<?= e(tmdb_poster_url($l['preview_poster'], 'w780')) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <div class="list-card-empty-preview">EMPTY LIST</div>
                        <?php endif; ?>
                    </div>
                    <div class="list-card-body">
                        <h3><?= e($l['name']) ?></h3>
                        <div class="list-card-meta">
                            <?= (int) $l['film_count'] ?> film<?= $l['film_count'] == 1 ? '' : 's' ?> ·
                            <?= $l['is_public'] ? 'Public' : 'Private' ?>
                        </div>
                        <p class="list-card-desc"><?= e($l['description'] ?: 'No description.') ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
const BASE = '<?= e(BASE_URL) ?>';
const CSRF = '<?= e(csrf_token()) ?>';

// Create new list
document.getElementById('newListForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    if (!fd.has('is_public')) fd.append('is_public', '0');
    const res = await fetch(BASE + '/api/list_create.php', { method: 'POST', body: fd });
    const data = await res.json().catch(() => ({}));
    if (res.ok && data.list_id) {
        location.href = BASE + '/list.php?id=' + data.list_id;
    } else {
        alert(data.error || 'Failed to create list');
    }
});

// Delete a list
document.querySelectorAll('.list-card-delete').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        const id = btn.dataset.id;
        const name = btn.dataset.name;
        if (!confirm(`Delete the list "${name}"? This cannot be undone.`)) return;
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('list_id', id);
        const res = await fetch(BASE + '/api/list_delete.php', { method: 'POST', body: fd });
        if (res.ok) {
            btn.closest('.list-card').remove();
        } else {
            alert('Failed to delete list');
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
