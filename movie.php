<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/tmdb.php';

$movie_id = (int) ($_GET['id'] ?? 0);
if ($movie_id <= 0) {
    http_response_code(404);
    die('Invalid movie ID.');
}

$api_key_missing = (TMDB_API_KEY === 'PUT_YOUR_TMDB_API_KEY_HERE' || TMDB_API_KEY === '');
if ($api_key_missing) {
    $_title = 'Setup needed';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="alert alert-error">Set your TMDb API key in <code>includes/config.php</code> first.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$movie = tmdb_movie_details($movie_id);
if (!$movie) {
    http_response_code(404);
    die('Movie not found.');
}

// Cache to movies_cache (upsert)
$genre_names = array_map(fn($g) => $g['name'], $movie['genres'] ?? []);
$stmt = db()->prepare(
    'INSERT INTO movies_cache
        (tmdb_id, title, original_title, poster_path, backdrop_path,
         overview, release_date, runtime, vote_average, genres)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        title = VALUES(title), original_title = VALUES(original_title),
        poster_path = VALUES(poster_path), backdrop_path = VALUES(backdrop_path),
        overview = VALUES(overview), release_date = VALUES(release_date),
        runtime = VALUES(runtime), vote_average = VALUES(vote_average),
        genres = VALUES(genres)'
);
$stmt->execute([
    $movie['id'],
    $movie['title'] ?? '',
    $movie['original_title'] ?? null,
    $movie['poster_path'] ?? null,
    $movie['backdrop_path'] ?? null,
    $movie['overview'] ?? null,
    !empty($movie['release_date']) ? $movie['release_date'] : null,
    $movie['runtime'] ?? null,
    $movie['vote_average'] ?? null,
    implode(', ', $genre_names),
]);

$user       = current_user();
$user_movie = null;
$my_lists   = [];
$in_lists   = []; // list_ids that already contain this film
if ($user) {
    $stmt = db()->prepare('SELECT status, rating FROM user_movies WHERE user_id = ? AND tmdb_id = ?');
    $stmt->execute([$user['id'], $movie_id]);
    $user_movie = $stmt->fetch() ?: null;

    $stmt = db()->prepare('SELECT id, name FROM custom_lists WHERE user_id = ? ORDER BY name');
    $stmt->execute([$user['id']]);
    $my_lists = $stmt->fetchAll();

    if (!empty($my_lists)) {
        $list_ids = array_column($my_lists, 'id');
        $place    = implode(',', array_fill(0, count($list_ids), '?'));
        $stmt = db()->prepare(
            "SELECT list_id FROM list_items WHERE tmdb_id = ? AND list_id IN ({$place})"
        );
        $stmt->execute(array_merge([$movie_id], $list_ids));
        $in_lists = array_column($stmt->fetchAll(), 'list_id');
    }
}

// Find best trailer (prefer official Trailer over Teaser)
$trailer = null;
foreach ($movie['videos']['results'] ?? [] as $v) {
    if (($v['site'] ?? '') !== 'YouTube') continue;
    if (!in_array($v['type'] ?? '', ['Trailer', 'Teaser'], true)) continue;
    if ($trailer === null) $trailer = $v['key'];
    if (($v['type'] === 'Trailer') && !empty($v['official'])) {
        $trailer = $v['key'];
        break;
    }
}

$cast      = array_slice($movie['credits']['cast'] ?? [], 0, 10);
$directors = array_values(array_filter(
    $movie['credits']['crew'] ?? [],
    fn($c) => ($c['job'] ?? '') === 'Director'
));

// Watch providers: TMDb groups by country. Default to US.
$wp_region = 'US';
$providers = $movie['watch/providers']['results'][$wp_region]['flatrate'] ?? [];

$stmt = db()->prepare(
    'SELECT r.id, r.content, r.rating, r.created_at, u.username
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     WHERE r.tmdb_id = ?
     ORDER BY r.created_at DESC
     LIMIT 50'
);
$stmt->execute([$movie_id]);
$reviews = $stmt->fetchAll();

// Pull up to 5 community reviews from TMDb so the section is never empty
$tmdb_reviews = array_slice($movie['reviews']['results'] ?? [], 0, 5);

$total_review_count = count($reviews) + count($tmdb_reviews);

$_title = $movie['title'];
include __DIR__ . '/includes/header.php';
?>

<?php if (!empty($movie['backdrop_path'])): ?>
<div class="movie-hero" style="background-image: url('<?= e(tmdb_backdrop_url($movie['backdrop_path'])) ?>');">
    <div class="movie-hero-overlay"></div>
</div>
<?php endif; ?>

<div class="container movie-detail">
    <div class="movie-top">
        <img src="<?= e(tmdb_poster_url($movie['poster_path'] ?? null)) ?>"
             alt="<?= e($movie['title']) ?>" class="movie-detail-poster">

        <div class="movie-detail-info">
            <h1 class="movie-detail-title"><?= e($movie['title']) ?></h1>

            <?php if (!empty($movie['original_title']) && $movie['original_title'] !== $movie['title']): ?>
                <div class="movie-original-title"><?= e($movie['original_title']) ?></div>
            <?php endif; ?>

            <div class="movie-meta">
                <span><?= !empty($movie['release_date']) ? substr($movie['release_date'], 0, 4) : '—' ?></span>
                <?php if (!empty($movie['runtime'])): ?>
                    <span class="movie-meta-sep"></span>
                    <span><?= (int) $movie['runtime'] ?> min</span>
                <?php endif; ?>
                <?php foreach ($movie['genres'] ?? [] as $g): ?>
                    <span class="genre-pill"><?= e($g['name']) ?></span>
                <?php endforeach; ?>
            </div>

            <div class="movie-tmdb-score">
                <span class="score-num"><?= number_format((float) $movie['vote_average'], 1) ?></span>
                <span class="score-out">/ 10</span>
                <span class="score-votes"><?= number_format((int) $movie['vote_count']) ?> votes</span>
            </div>

            <?php if (!empty($directors)): ?>
                <p class="movie-director">
                    Directed by <?= e(implode(', ', array_column($directors, 'name'))) ?>
                </p>
            <?php endif; ?>

            <p class="movie-overview"><?= e($movie['overview'] ?? '') ?></p>

            <?php if ($user): ?>
                <div class="user-actions">
                    <div class="status-buttons">
                        <?php
                        $statuses = [
                            'want'     => 'Want to watch',
                            'watching' => 'Watching',
                            'watched'  => 'Watched',
                            'dropped'  => 'Dropped',
                        ];
                        foreach ($statuses as $key => $label):
                            $active = $user_movie && $user_movie['status'] === $key;
                        ?>
                            <button class="status-btn <?= $active ? 'active' : '' ?>"
                                    data-status="<?= e($key) ?>"><?= e($label) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="rating-picker">
                        <span class="rating-label">Your rating:</span>
                        <?php for ($i = 1; $i <= 10; $i++):
                            $filled = $user_movie && (int) $user_movie['rating'] >= $i;
                        ?>
                            <button class="rating-star-btn <?= $filled ? 'filled' : '' ?>"
                                    data-rating="<?= $i ?>" title="Rate <?= $i ?>/10">★</button>
                        <?php endfor; ?>
                        <span class="rating-current">
                            <?= $user_movie && $user_movie['rating'] ? (int) $user_movie['rating'] . '/10' : '' ?>
                        </span>
                    </div>

                    <!-- Add to custom list -->
                    <div class="addlist-wrap" style="margin-top:1.2rem;">
                        <span class="rating-label" style="display:block; margin-bottom:0.4rem;">Add to a list:</span>
                        <?php if (empty($my_lists)): ?>
                            <p style="color:var(--muted); font-size:0.85rem;">
                                You have no lists yet. <a href="<?= e(BASE_URL) ?>/lists.php">Create one</a>.
                            </p>
                        <?php else: ?>
                            <div class="addlist-chips">
                                <?php foreach ($my_lists as $l):
                                    $in = in_array($l['id'], $in_lists);
                                ?>
                                    <button class="addlist-chip <?= $in ? 'in' : '' ?>"
                                            data-list-id="<?= (int) $l['id'] ?>">
                                        <span class="check"><?= $in ? '✓' : '+' ?></span>
                                        <?= e($l['name']) ?>
                                    </button>
                                <?php endforeach; ?>
                                <a href="<?= e(BASE_URL) ?>/lists.php" class="addlist-chip" style="border-style:dashed;">
                                    <span class="check">+</span> New list
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <p class="login-prompt">
                    <a href="<?= e(BASE_URL) ?>/login.php">Log in</a> to track this film and write a review.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($trailer): ?>
        <section class="movie-section">
            <h2 class="movie-section-title">Trailer</h2>
            <div class="trailer-wrap">
                <iframe src="https://www.youtube.com/embed/<?= e($trailer) ?>"
                        title="Trailer" allowfullscreen
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($cast)): ?>
        <section class="movie-section">
            <h2 class="movie-section-title">Cast</h2>
            <div class="cast-grid">
                <?php foreach ($cast as $c): ?>
                    <div class="cast-card">
                        <img src="<?= e(tmdb_poster_url($c['profile_path'] ?? null, 'w185')) ?>"
                             alt="<?= e($c['name'] ?? '') ?>" loading="lazy">
                        <div class="cast-name"><?= e($c['name'] ?? '') ?></div>
                        <div class="cast-char"><?= e($c['character'] ?? '') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($providers)): ?>
        <section class="movie-section">
            <h2 class="movie-section-title">
                Where to watch
                <span class="region-note">(<?= e($wp_region) ?>)</span>
            </h2>
            <div class="providers-grid">
                <?php foreach ($providers as $p): ?>
                    <div class="provider-card" title="<?= e($p['provider_name']) ?>">
                        <img src="<?= e(TMDB_IMG_URL) ?>/w92<?= e($p['logo_path']) ?>"
                             alt="<?= e($p['provider_name']) ?>">
                        <div class="provider-name"><?= e($p['provider_name']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="movie-section">
        <h2 class="movie-section-title">Reviews <span class="region-note">(<?= (int) $total_review_count ?>)</span></h2>

        <?php if ($user): ?>
            <form class="review-form" id="reviewForm">
                <?= csrf_field() ?>
                <input type="hidden" name="tmdb_id" value="<?= (int) $movie_id ?>">
                <textarea name="content" class="form-input" rows="4"
                          placeholder="Share your thoughts..." required maxlength="5000"></textarea>
                <button type="submit" class="btn-primary review-submit">POST REVIEW</button>
            </form>
        <?php else: ?>
            <p style="color: var(--muted); margin-bottom: 1.5rem;">
                <a href="<?= e(BASE_URL) ?>/login.php">Log in</a> to write a review.
            </p>
        <?php endif; ?>

        <div class="reviews-list">
            <?php if (empty($reviews) && empty($tmdb_reviews)): ?>
                <p style="color: var(--muted);">No reviews yet. Be the first.</p>
            <?php else: ?>
                <?php /* FilmBox users first */ ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div>
                                <strong><?= e($r['username']) ?></strong>
                                <span class="review-badge filmbox">FILMBOX</span>
                            </div>
                            <span class="review-date"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></span>
                        </div>
                        <p class="review-content"><?= nl2br(e($r['content'])) ?></p>
                    </div>
                <?php endforeach; ?>

                <?php /* TMDb community */ ?>
                <?php foreach ($tmdb_reviews as $tr):
                    $author    = $tr['author_details']['username'] ?? ($tr['author'] ?? 'TMDb user');
                    $avatar    = $tr['author_details']['avatar_path'] ?? null;
                    $tr_rating = $tr['author_details']['rating'] ?? null;
                    $created   = !empty($tr['created_at']) ? date('M j, Y', strtotime($tr['created_at'])) : '';
                    $content   = (string) ($tr['content'] ?? '');
                    $long      = strlen($content) > 600;
                    $short     = $long ? substr($content, 0, 600) . '…' : $content;

                    // Avatars come either as "/path.jpg" (TMDb-hosted) or
                    // "/https://gravatar.com/..." (prefixed with a leading slash; strip it).
                    $avatar_url = null;
                    if ($avatar) {
                        $avatar_url = (str_starts_with($avatar, '/https'))
                            ? substr($avatar, 1)
                            : TMDB_IMG_URL . '/w185' . $avatar;
                    }
                ?>
                    <div class="review-item tmdb">
                        <div class="review-header">
                            <div class="review-author">
                                <?php if ($avatar_url): ?>
                                    <img src="<?= e($avatar_url) ?>" alt="" class="review-avatar"
                                         onerror="this.style.display='none'">
                                <?php endif; ?>
                                <strong><?= e($author) ?></strong>
                                <span class="review-badge tmdb">TMDB</span>
                                <?php if ($tr_rating): ?>
                                    <span class="review-stars">★ <?= number_format((float) $tr_rating, 1) ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="review-date"><?= e($created) ?></span>
                        </div>
                        <p class="review-content" data-full="<?= e($content) ?>"
                           data-short="<?= e($short) ?>">
                            <?= nl2br(e($short)) ?>
                            <?php if ($long): ?>
                                <a class="review-more" href="#" data-state="short">Read more</a>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php if ($user): ?>
<script>
    const BASE    = '<?= e(BASE_URL) ?>';
    const TMDB_ID = <?= (int) $movie_id ?>;
    const CSRF    = '<?= e(csrf_token()) ?>';

    async function post(url, data) {
        const body = new URLSearchParams({csrf_token: CSRF, ...data});
        const res  = await fetch(BASE + url, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body
        });
        return res;
    }

    // Status buttons
    document.querySelectorAll('.status-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const status = btn.dataset.status;
            const res = await post('/api/set_status.php', {tmdb_id: TMDB_ID, status});
            if (res.ok) {
                document.querySelectorAll('.status-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            } else {
                alert('Failed to update status.');
            }
        });
    });

    // Rating
    document.querySelectorAll('.rating-star-btn').forEach((btn, idx) => {
        btn.addEventListener('mouseenter', () => {
            document.querySelectorAll('.rating-star-btn').forEach((b, i) => {
                b.classList.toggle('preview', i <= idx);
            });
        });
        btn.addEventListener('mouseleave', () => {
            document.querySelectorAll('.rating-star-btn').forEach(b => b.classList.remove('preview'));
        });
        btn.addEventListener('click', async () => {
            const rating = parseInt(btn.dataset.rating);
            const res = await post('/api/set_rating.php', {tmdb_id: TMDB_ID, rating});
            if (res.ok) {
                document.querySelectorAll('.rating-star-btn').forEach((b, i) => {
                    b.classList.toggle('filled', i < rating);
                });
                const cur = document.querySelector('.rating-current');
                if (cur) cur.textContent = rating + '/10';
            }
        });
    });

    // Add/remove from custom list (toggle)
    document.querySelectorAll('.addlist-chip[data-list-id]').forEach(chip => {
        chip.addEventListener('click', async (e) => {
            e.preventDefault();
            const id = chip.dataset.listId;
            const isIn = chip.classList.contains('in');
            const endpoint = isIn ? '/api/list_remove_movie.php' : '/api/list_add_movie.php';
            const res = await post(endpoint, { list_id: id, tmdb_id: TMDB_ID });
            if (res.ok) {
                chip.classList.toggle('in');
                chip.querySelector('.check').textContent = chip.classList.contains('in') ? '✓' : '+';
            } else {
                alert('Failed.');
            }
        });
    });

    // Review form
    const reviewForm = document.getElementById('reviewForm');
    if (reviewForm) {
        reviewForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(reviewForm);
            const res = await fetch(BASE + '/api/save_review.php', {method: 'POST', body: fd});
            if (res.ok) {
                location.reload();
            } else {
                const data = await res.json().catch(() => ({}));
                alert(data.error || 'Failed to post review.');
            }
        });
    }
</script>
<?php endif; ?>

<script>
// "Read more" toggle for long TMDb reviews — runs for everyone, not just logged-in users
document.addEventListener('click', (e) => {
    if (!e.target.classList.contains('review-more')) return;
    e.preventDefault();
    const link  = e.target;
    const p     = link.closest('.review-content');
    if (!p) return;
    const full  = p.dataset.full;
    const short = p.dataset.short;
    const isShort = link.dataset.state === 'short';
    const text  = (isShort ? full : short).replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c])).replace(/\n/g, '<br>');
    p.innerHTML = text + ' <a class="review-more" href="#" data-state="' +
        (isShort ? 'full' : 'short') + '">' +
        (isShort ? 'Show less' : 'Read more') + '</a>';
    p.dataset.full  = full;
    p.dataset.short = short;
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
