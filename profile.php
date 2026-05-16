<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/tmdb.php';

$username = trim($_GET['user'] ?? '');
if ($username === '') {
    http_response_code(400);
    die('No user specified.');
}

$stmt = db()->prepare(
    'SELECT id, username, email, bio, created_at FROM users WHERE username = ?'
);
$stmt->execute([$username]);
$profile = $stmt->fetch();
if (!$profile) {
    http_response_code(404);
    die('User not found.');
}

$me      = current_user();
$is_self = $me && (int) $me['id'] === (int) $profile['id'];

// --- Stats ---
$stmt = db()->prepare("SELECT COUNT(*) FROM user_movies WHERE user_id = ? AND status='watched'");
$stmt->execute([$profile['id']]);
$watched_count = (int) $stmt->fetchColumn();

$stmt = db()->prepare('SELECT COUNT(*) FROM reviews WHERE user_id = ?');
$stmt->execute([$profile['id']]);
$reviews_count = (int) $stmt->fetchColumn();

$stmt = db()->prepare('SELECT COUNT(*) FROM custom_lists WHERE user_id = ? AND is_public = 1');
$stmt->execute([$profile['id']]);
$lists_count = (int) $stmt->fetchColumn();

$stmt = db()->prepare('SELECT AVG(rating) FROM user_movies WHERE user_id = ? AND rating IS NOT NULL');
$stmt->execute([$profile['id']]);
$avg_rating = (float) $stmt->fetchColumn();

// --- Followers / Following (requires migration_001.sql to be run) ---
$followers_count = $following_count = 0;
$is_following    = false;
try {
    $followers_count = (int) db()->query(
        'SELECT COUNT(*) FROM follows WHERE followed_id = ' . (int) $profile['id']
    )->fetchColumn();
    $following_count = (int) db()->query(
        'SELECT COUNT(*) FROM follows WHERE follower_id = ' . (int) $profile['id']
    )->fetchColumn();
    if ($me && !$is_self) {
        $stmt = db()->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?');
        $stmt->execute([$me['id'], $profile['id']]);
        $is_following = (bool) $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    // Most likely the follows table doesn't exist yet (migration not run).
    // Silently degrade — counts stay at 0.
}

// --- Recent reviews ---
$stmt = db()->prepare(
    "SELECT r.id, r.content, r.rating, r.created_at, r.tmdb_id,
            m.title, m.poster_path
     FROM reviews r
     LEFT JOIN movies_cache m ON m.tmdb_id = r.tmdb_id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC LIMIT 6"
);
$stmt->execute([$profile['id']]);
$recent_reviews = $stmt->fetchAll();

// --- Public lists ---
$stmt = db()->prepare(
    "SELECT cl.id, cl.name, cl.description, cl.created_at,
            (SELECT COUNT(*) FROM list_items li WHERE li.list_id = cl.id) AS film_count,
            (SELECT m.poster_path
             FROM list_items li2
             JOIN movies_cache m ON m.tmdb_id = li2.tmdb_id
             WHERE li2.list_id = cl.id
             ORDER BY li2.added_at DESC LIMIT 1) AS preview_poster
     FROM custom_lists cl
     WHERE cl.user_id = ? AND cl.is_public = 1
     ORDER BY cl.created_at DESC"
);
$stmt->execute([$profile['id']]);
$public_lists = $stmt->fetchAll();

// --- Recent activity (films) ---
$stmt = db()->prepare(
    "SELECT um.tmdb_id, um.status, um.rating, m.title, m.poster_path
     FROM user_movies um
     LEFT JOIN movies_cache m ON m.tmdb_id = um.tmdb_id
     WHERE um.user_id = ?
     ORDER BY um.updated_at DESC LIMIT 12"
);
$stmt->execute([$profile['id']]);
$recent_films = $stmt->fetchAll();

// Avatar color seeded from username for deterministic look
$hue        = crc32($profile['username']) % 360;
$avatar_bg  = "linear-gradient(135deg, hsl({$hue}, 45%, 30%), hsl(" . (($hue + 30) % 360) . ", 55%, 22%))";
$initials   = strtoupper(substr($profile['username'], 0, 2));

$_title = '@' . $profile['username'];
$_page  = 'profile';
include __DIR__ . '/includes/header.php';
?>

<style>
.profile-hero {
    padding: 3.5rem 3rem 3rem;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(180deg, var(--deep) 0%, var(--black) 100%);
}
.profile-top {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 2rem;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
}
.profile-avatar {
    width: 130px; height: 130px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-display);
    font-size: 2.6rem;
    color: var(--ivory);
    letter-spacing: 0.1em;
    box-shadow: 0 8px 30px rgba(0,0,0,0.5);
    border: 2px solid var(--border);
}
.profile-meta h1 {
    font-family: var(--font-serif);
    font-size: clamp(2rem, 4vw, 3rem);
    color: var(--ivory);
    margin-bottom: 0.3rem;
}
.profile-handle { color: var(--amber); font-size: 0.9rem; letter-spacing: 0.1em; }
.profile-since  { color: var(--muted); font-size: 0.85rem; margin-top: 0.4rem; }
.profile-bio    { color: var(--text); margin-top: 1rem; line-height: 1.7; max-width: 540px; }

.profile-actions { display: flex; flex-direction: column; gap: 0.6rem; align-items: stretch; }

.follow-btn {
    padding: 0.7rem 1.6rem;
    font-family: var(--font-display);
    font-size: 0.8rem;
    letter-spacing: 0.2em;
    color: var(--black);
    background: var(--amber);
    border: 1px solid var(--amber);
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.follow-btn:hover { background: var(--amber-dim); box-shadow: 0 0 20px var(--amber-glow); }
.follow-btn.following {
    color: var(--text);
    background: transparent;
    border-color: var(--border);
}
.follow-btn.following:hover {
    color: var(--error);
    border-color: var(--error);
    background: transparent;
    box-shadow: none;
}
.follow-btn.following:hover::after { content: ' UNFOLLOW'; }

.profile-stats-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1px;
    background: var(--border);
    margin: 2rem auto 0;
    max-width: 1200px;
    border: 1px solid var(--border);
}
.profile-stat {
    padding: 1.2rem;
    text-align: center;
    background: var(--surface);
}
.profile-stat .n {
    font-family: var(--font-display);
    font-size: 1.8rem;
    color: var(--amber);
    line-height: 1;
}
.profile-stat .l {
    font-size: 0.7rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--muted);
    margin-top: 0.4rem;
}

.profile-section {
    padding: 3rem;
    border-top: 1px solid var(--border);
}
.profile-section.alt { background: var(--deep); }
.profile-section-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}
.profile-section-header h2 {
    font-family: var(--font-serif);
    font-size: 1.6rem;
    color: var(--ivory);
}

.bio-editor {
    margin-top: 0.8rem;
    display: none;
}
.bio-editor.active { display: block; }
.bio-editor textarea {
    width: 100%;
    background: var(--deep);
    border: 1px solid var(--border);
    color: var(--text-bright);
    padding: 0.8rem;
    font-family: var(--font-body);
    font-size: 0.9rem;
    resize: vertical;
    min-height: 80px;
}
.bio-editor .row {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.empty-mini {
    color: var(--muted);
    text-align: center;
    padding: 2rem 1rem;
    border: 1px dashed var(--border);
    font-size: 0.9rem;
}

@media (max-width: 800px) {
    .profile-top { grid-template-columns: 1fr; text-align: center; gap: 1rem; }
    .profile-avatar { margin: 0 auto; width: 100px; height: 100px; font-size: 2rem; }
    .profile-actions { align-items: center; }
    .profile-bio { margin-left: auto; margin-right: auto; }
    .profile-stats-row { grid-template-columns: repeat(2, 1fr); }
    .profile-section { padding: 2rem 1.5rem; }
    .profile-hero { padding: 2.5rem 1.5rem; }
}
</style>

<section class="profile-hero">
    <div class="profile-top">
        <div class="profile-avatar" style="background: <?= $avatar_bg ?>;"><?= e($initials) ?></div>

        <div class="profile-meta">
            <h1><?= e($profile['username']) ?></h1>
            <div class="profile-handle">@<?= e($profile['username']) ?></div>
            <div class="profile-since">
                Member since <?= e(date('F Y', strtotime($profile['created_at']))) ?>
            </div>
            <?php if (!empty($profile['bio'])): ?>
                <p class="profile-bio" id="bioDisplay"><?= e($profile['bio']) ?></p>
            <?php elseif ($is_self): ?>
                <p class="profile-bio" id="bioDisplay" style="color:var(--muted); font-style:italic;">
                    No bio yet. Tell people about your taste.
                </p>
            <?php endif; ?>

            <?php if ($is_self): ?>
                <div class="bio-editor" id="bioEditor">
                    <textarea id="bioText" maxlength="500" placeholder="What do you watch? What do you love?"><?= e($profile['bio'] ?? '') ?></textarea>
                    <div class="row">
                        <button class="btn-primary" id="bioSave" style="width:auto; padding:0.5rem 1.2rem; font-size:0.75rem;">SAVE</button>
                        <button class="btn-outline" id="bioCancel" style="padding:0.5rem 1.2rem; font-size:0.75rem;">CANCEL</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="profile-actions">
            <?php if ($is_self): ?>
                <button class="btn-outline" id="editBioBtn">EDIT BIO</button>
                <a href="<?= e(BASE_URL) ?>/dashboard.php" class="btn-outline">DASHBOARD</a>
            <?php elseif ($me): ?>
                <button class="follow-btn <?= $is_following ? 'following' : '' ?>" id="followBtn"
                        data-following="<?= $is_following ? '1' : '0' ?>">
                    <?= $is_following ? 'FOLLOWING' : 'FOLLOW' ?>
                </button>
            <?php else: ?>
                <a href="<?= e(BASE_URL) ?>/login.php" class="btn-outline">LOG IN TO FOLLOW</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="profile-stats-row">
        <div class="profile-stat">
            <div class="n"><?= number_format($watched_count) ?></div>
            <div class="l">Watched</div>
        </div>
        <div class="profile-stat">
            <div class="n"><?= $avg_rating > 0 ? number_format($avg_rating, 1) : '—' ?></div>
            <div class="l">Avg rating</div>
        </div>
        <div class="profile-stat">
            <div class="n"><?= number_format($reviews_count) ?></div>
            <div class="l">Reviews</div>
        </div>
        <div class="profile-stat">
            <div class="n" id="followersN"><?= number_format($followers_count) ?></div>
            <div class="l">Followers</div>
        </div>
        <div class="profile-stat">
            <div class="n"><?= number_format($following_count) ?></div>
            <div class="l">Following</div>
        </div>
    </div>
</section>

<section class="profile-section">
    <div class="profile-section-header">
        <h2>Recent activity</h2>
    </div>
    <?php if (empty($recent_films)): ?>
        <div class="empty-mini">No films tracked yet.</div>
    <?php else: ?>
        <div class="movies-grid">
            <?php foreach ($recent_films as $f): ?>
                <a href="<?= e(BASE_URL) ?>/movie.php?id=<?= (int) $f['tmdb_id'] ?>" class="movie-card">
                    <div class="movie-poster">
                        <img src="<?= e(tmdb_poster_url($f['poster_path'] ?? null)) ?>"
                             alt="<?= e($f['title'] ?? '') ?>" loading="lazy">
                        <?php if (!empty($f['rating'])): ?>
                            <div class="film-rating" style="position:absolute; bottom:0.5rem; left:0.5rem; background:var(--amber); color:var(--black); padding:0.15rem 0.5rem; font-family:var(--font-display); font-size:0.8rem;">
                                ★ <?= (int) $f['rating'] ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="movie-info">
                        <h3 class="movie-title"><?= e($f['title'] ?? 'Film #' . $f['tmdb_id']) ?></h3>
                        <div class="movie-year"><?= e(strtoupper($f['status'])) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="profile-section alt">
    <div class="profile-section-header">
        <h2>Reviews</h2>
        <span style="color:var(--muted); font-size:0.85rem;"><?= $reviews_count ?> total</span>
    </div>
    <?php if (empty($recent_reviews)): ?>
        <div class="empty-mini">No reviews yet.</div>
    <?php else: ?>
        <div class="reviews-list">
            <?php foreach ($recent_reviews as $r): ?>
                <a href="<?= e(BASE_URL) ?>/movie.php?id=<?= (int) $r['tmdb_id'] ?>"
                   class="review-item" style="text-decoration:none; color:inherit; display:block;">
                    <div class="review-header">
                        <div>
                            <strong><?= e($r['title'] ?? 'Film #' . $r['tmdb_id']) ?></strong>
                            <?php if (!empty($r['rating'])): ?>
                                <span class="review-stars">★ <?= (int) $r['rating'] ?>/10</span>
                            <?php endif; ?>
                        </div>
                        <span class="review-date"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></span>
                    </div>
                    <p class="review-content"><?= nl2br(e(mb_substr($r['content'], 0, 400))) ?><?= mb_strlen($r['content']) > 400 ? '…' : '' ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="profile-section">
    <div class="profile-section-header">
        <h2>Public lists</h2>
        <span style="color:var(--muted); font-size:0.85rem;"><?= $lists_count ?> total</span>
    </div>
    <?php if (empty($public_lists)): ?>
        <div class="empty-mini">No public lists.</div>
    <?php else: ?>
        <div class="lists-grid" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.5rem;">
            <?php foreach ($public_lists as $l): ?>
                <a href="<?= e(BASE_URL) ?>/list.php?id=<?= (int) $l['id'] ?>" class="list-card"
                   style="background:var(--surface); border:1px solid var(--border); text-decoration:none; color:inherit; overflow:hidden; display:block;">
                    <div style="aspect-ratio:16/9; background:var(--deep); position:relative; overflow:hidden;">
                        <?php if (!empty($l['preview_poster'])): ?>
                            <img src="<?= e(tmdb_poster_url($l['preview_poster'], 'w780')) ?>"
                                 style="width:100%; height:100%; object-fit:cover; object-position:center 30%; opacity:0.7;" alt="" loading="lazy">
                        <?php endif; ?>
                    </div>
                    <div style="padding:1.2rem;">
                        <h3 style="font-family:var(--font-serif); color:var(--ivory); font-size:1.2rem; margin-bottom:0.3rem;">
                            <?= e($l['name']) ?>
                        </h3>
                        <div style="font-size:0.75rem; color:var(--amber); letter-spacing:0.1em;">
                            <?= (int) $l['film_count'] ?> film<?= $l['film_count'] == 1 ? '' : 's' ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
const BASE       = '<?= e(BASE_URL) ?>';
const CSRF       = '<?= e(csrf_token()) ?>';
const PROFILE_ID = <?= (int) $profile['id'] ?>;

<?php if ($me && !$is_self): ?>
const followBtn = document.getElementById('followBtn');
if (followBtn) {
    followBtn.addEventListener('click', async () => {
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('target_user_id', PROFILE_ID);
        const res = await fetch(BASE + '/api/follow_toggle.php', { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) { alert(data.error || 'Failed'); return; }
        followBtn.dataset.following = data.following ? '1' : '0';
        followBtn.classList.toggle('following', data.following);
        followBtn.textContent = data.following ? 'FOLLOWING' : 'FOLLOW';
        document.getElementById('followersN').textContent = data.followers;
    });
}
<?php endif; ?>

<?php if ($is_self): ?>
const editBtn = document.getElementById('editBioBtn');
const editor  = document.getElementById('bioEditor');
const display = document.getElementById('bioDisplay');
editBtn?.addEventListener('click', () => {
    editor.classList.add('active');
    if (display) display.style.display = 'none';
});
document.getElementById('bioCancel').addEventListener('click', () => {
    editor.classList.remove('active');
    if (display) display.style.display = '';
});
document.getElementById('bioSave').addEventListener('click', async () => {
    const text = document.getElementById('bioText').value.trim();
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('bio', text);
    const res = await fetch(BASE + '/api/update_bio.php', { method: 'POST', body: fd });
    if (res.ok) location.reload();
    else alert('Failed to save bio');
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
