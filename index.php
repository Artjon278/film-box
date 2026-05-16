<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tmdb.php';

start_session_once();
$user = current_user();

$api_key_missing = (TMDB_API_KEY === 'PUT_YOUR_TMDB_API_KEY_HERE' || TMDB_API_KEY === '');

// --- TMDb data ---
$trending    = $api_key_missing ? [] : tmdb_trending('week');
$top_rated   = $api_key_missing ? [] : tmdb_top_rated();
$now_playing = $api_key_missing ? [] : tmdb_now_playing();
$upcoming    = $api_key_missing ? [] : tmdb_upcoming();
$genres      = $api_key_missing ? [] : tmdb_genres();
$tmdb_err    = tmdb_last_error();

$spotlight     = !empty($trending) ? $trending[0] : null;
$trending_rest = !empty($trending) ? array_slice($trending, 1, 8) : [];
$top_rated_six = array_slice($top_rated, 0, 6);
$in_theaters   = array_slice($now_playing, 0, 6);
$coming_soon   = array_slice($upcoming, 0, 6);

// --- Spotlight trailer (one extra TMDb call) ---
$spotlight_trailer = null;
if ($spotlight && !$api_key_missing) {
    foreach (tmdb_movie_videos((int) $spotlight['id']) as $v) {
        if (($v['site'] ?? '') !== 'YouTube') continue;
        if (!in_array($v['type'] ?? '', ['Trailer', 'Teaser'], true)) continue;
        if ($spotlight_trailer === null) $spotlight_trailer = $v['key'];
        if (($v['type'] === 'Trailer') && !empty($v['official'])) {
            $spotlight_trailer = $v['key'];
            break;
        }
    }
}

// --- DB stats ---
$total_users   = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$total_reviews = (int) db()->query('SELECT COUNT(*) FROM reviews')->fetchColumn();
$total_tracked = (int) db()->query('SELECT COUNT(*) FROM user_movies')->fetchColumn();
$total_cached  = (int) db()->query('SELECT COUNT(*) FROM movies_cache')->fetchColumn();

// --- Recent reviews (with movie info from cache) ---
$stmt = db()->query(
    "SELECT r.id, r.content, r.rating, r.created_at, r.tmdb_id,
            u.username,
            m.title, m.poster_path
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     LEFT JOIN movies_cache m ON m.tmdb_id = r.tmdb_id
     ORDER BY r.created_at DESC
     LIMIT 6"
);
$recent_reviews = $stmt->fetchAll();

// Build ticker from trending titles
$ticker_titles = array_map(
    fn($m) => strtoupper($m['title']) . ' — ' . number_format((float) $m['vote_average'], 1),
    array_slice($trending, 0, 8)
);

// --- Personal panel (logged-in users) ---
$personal_stats  = null;
$personal_recent = [];
if ($user) {
    $stmt = db()->prepare(
        "SELECT COUNT(*)                                AS total,
                SUM(status = 'watched')                 AS watched,
                SUM(rating IS NOT NULL)                 AS rated,
                AVG(NULLIF(rating, 0))                  AS avg_rating
         FROM user_movies WHERE user_id = ?"
    );
    $stmt->execute([$user['id']]);
    $personal_stats = $stmt->fetch();

    $stmt = db()->prepare(
        "SELECT um.tmdb_id, um.status, m.title, m.poster_path
         FROM user_movies um
         LEFT JOIN movies_cache m ON m.tmdb_id = um.tmdb_id
         WHERE um.user_id = ?
         ORDER BY um.updated_at DESC LIMIT 5"
    );
    $stmt->execute([$user['id']]);
    $personal_recent = $stmt->fetchAll();
}

// --- Most active reviewers (last 30 days) ---
$active_reviewers = db()->query(
    "SELECT u.username, COUNT(r.id) AS n
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY u.id
     ORDER BY n DESC
     LIMIT 5"
)->fetchAll();

$_title = 'Discover films you love';
$_page  = 'home';
include __DIR__ . '/includes/header.php';
?>

<style>
/* ============ HOMEPAGE-SPECIFIC STYLES ============ */
.hero {
    position: relative;
    min-height: 600px;
    display: flex;
    align-items: flex-end;
    padding: 5rem 3rem 5rem;
    overflow: hidden;
    margin-top: -1px;
}
.hero-bg {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center 25%;
    filter: brightness(0.5);
}
.hero-bg::after {
    content: '';
    position: absolute; inset: 0;
    background:
        linear-gradient(180deg, transparent 0%, var(--black) 100%),
        linear-gradient(90deg, rgba(10,10,10,0.85) 0%, rgba(10,10,10,0.3) 50%, rgba(10,10,10,0.5) 100%);
}
.hero-content {
    position: relative;
    z-index: 2;
    max-width: 760px;
}
.hero-tag {
    font-family: var(--font-display);
    font-size: 0.85rem;
    letter-spacing: 0.35em;
    color: var(--amber);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}
.hero-tag::before {
    content: '';
    width: 40px;
    height: 1px;
    background: var(--amber);
}
.hero-title {
    font-family: var(--font-serif);
    font-size: clamp(2.6rem, 6vw, 5rem);
    font-weight: 700;
    line-height: 1.05;
    color: var(--ivory);
    margin-bottom: 1.5rem;
}
.hero-title em { font-style: italic; color: var(--amber); }
.hero-sub {
    font-size: 1.1rem;
    color: var(--text);
    line-height: 1.7;
    max-width: 540px;
    margin-bottom: 2rem;
}
.hero-actions { display: flex; gap: 1rem; flex-wrap: wrap; }

.hero-side {
    position: absolute;
    right: 3rem;
    bottom: 5rem;
    text-align: right;
    z-index: 2;
    display: grid;
    gap: 1.5rem;
}
.hero-stat-num {
    font-family: var(--font-display);
    font-size: 2.6rem;
    color: var(--ivory);
    line-height: 1;
}
.hero-stat-label {
    font-size: 0.7rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--muted);
    margin-top: 0.3rem;
}

.ticker-wrap {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 40px;
    background: var(--deep);
    border-top: 1px solid var(--border);
    overflow: hidden;
    display: flex;
    align-items: center;
    z-index: 3;
}
.ticker {
    display: flex;
    animation: ticker 60s linear infinite;
    white-space: nowrap;
}
.ticker span {
    font-family: var(--font-display);
    font-size: 0.75rem;
    letter-spacing: 0.2em;
    color: var(--muted);
    padding: 0 2rem;
}
.ticker span::before { content: '★'; margin-right: 1.4rem; color: var(--amber-dim); }
@keyframes ticker {
    from { transform: translateX(0); }
    to { transform: translateX(-50%); }
}

/* Section header */
.home-section { padding: 5rem 3rem; }
.home-section.alt { background: var(--deep); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 2.5rem;
    padding-bottom: 1.2rem;
    border-bottom: 1px solid var(--border);
}
.section-label {
    font-family: var(--font-display);
    font-size: 0.75rem;
    letter-spacing: 0.35em;
    color: var(--amber);
    margin-bottom: 0.5rem;
}
.section-title {
    font-family: var(--font-serif);
    font-size: clamp(1.8rem, 3.5vw, 2.8rem);
    font-weight: 700;
    color: var(--ivory);
}
.section-link {
    font-family: var(--font-display);
    font-size: 0.75rem;
    letter-spacing: 0.2em;
    color: var(--muted);
    transition: color 0.3s, gap 0.3s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.section-link:hover { color: var(--amber); gap: 0.9rem; }

/* Spotlight (replaces "Featured Review") */
.spotlight-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 0;
    background: var(--surface);
    border: 1px solid var(--border);
    min-height: 480px;
}
.spotlight-img { position: relative; overflow: hidden; background: var(--deep); }
.spotlight-img img { width: 100%; height: 100%; object-fit: cover; transition: transform 6s ease; }
.spotlight-grid:hover .spotlight-img img { transform: scale(1.05); }
.spotlight-img::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent 60%, var(--surface) 100%);
}
.spotlight-badge {
    position: absolute;
    top: 2rem; left: 2rem;
    font-family: var(--font-display);
    font-size: 0.7rem; letter-spacing: 0.3em;
    color: var(--black); background: var(--amber);
    padding: 0.4rem 1rem; z-index: 2;
}
.spotlight-body { padding: 3.5rem; display: flex; flex-direction: column; justify-content: center; }
.spotlight-meta {
    font-size: 0.75rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 1rem;
    display: flex; align-items: center; gap: 0.8rem;
}
.spotlight-meta .sep { width: 4px; height: 4px; background: var(--amber-dim); border-radius: 50%; }
.spotlight-title {
    font-family: var(--font-serif);
    font-size: 2.4rem; font-weight: 700;
    color: var(--ivory); line-height: 1.15;
    margin-bottom: 1rem;
}
.spotlight-score {
    display: inline-flex; align-items: baseline; gap: 0.4rem;
    margin-bottom: 1.5rem;
}
.spotlight-score .n {
    font-family: var(--font-display);
    font-size: 2rem; color: var(--amber); line-height: 1;
}
.spotlight-score .o { color: var(--muted); font-size: 0.85rem; }
.spotlight-excerpt {
    color: var(--text); line-height: 1.7;
    margin-bottom: 2rem; font-size: 0.95rem;
}

/* Review cards (community) */
.community-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}
.community-review {
    background: var(--surface);
    border: 1px solid var(--border);
    overflow: hidden;
    transition: border-color 0.3s, transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
}
.community-review:hover {
    border-color: var(--amber-dim);
    transform: translateY(-3px);
    box-shadow: 0 14px 40px rgba(0,0,0,0.35);
}
.community-review .poster-wrap {
    position: relative;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background: var(--deep);
}
.community-review .poster-wrap img {
    width: 100%; height: 100%; object-fit: cover;
    object-position: center 30%;
}
.community-review .body { padding: 1.4rem; }
.community-review .film-title {
    font-family: var(--font-serif);
    font-size: 1.3rem; color: var(--ivory);
    margin-bottom: 0.3rem;
}
.community-review .by-line {
    font-size: 0.75rem;
    color: var(--amber);
    letter-spacing: 0.1em;
    margin-bottom: 0.8rem;
    text-transform: uppercase;
}
.community-review .excerpt {
    color: var(--text);
    line-height: 1.7;
    font-size: 0.9rem;
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--surface);
    border: 1px dashed var(--border);
}
.empty-state h3 {
    font-family: var(--font-serif);
    font-size: 1.4rem;
    color: var(--ivory);
    margin-bottom: 0.5rem;
}
.empty-state p { color: var(--muted); margin-bottom: 1.5rem; }

/* Genres */
.genre-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
}
.genre-pill-link {
    font-family: var(--font-display);
    font-size: 0.8rem;
    letter-spacing: 0.15em;
    color: var(--text);
    border: 1px solid var(--border);
    background: var(--surface);
    padding: 0.6rem 1.2rem;
    transition: all 0.2s;
}
.genre-pill-link:hover {
    background: var(--amber);
    color: var(--black);
    border-color: var(--amber);
}

/* ============ PERSONAL PANEL (logged-in) ============ */
.personal-panel {
    padding: 3rem;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, var(--deep) 0%, var(--black) 100%);
    position: relative;
}
.personal-panel::before {
    content: '';
    position: absolute;
    top: 0; left: 0; height: 100%; width: 3px;
    background: linear-gradient(180deg, transparent, var(--amber), transparent);
}
.personal-grid {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 3rem;
    align-items: center;
    max-width: 1300px;
    margin: 0 auto;
}
.personal-greeting h2 {
    font-family: var(--font-serif);
    font-size: clamp(1.6rem, 3vw, 2.4rem);
    color: var(--ivory);
    margin-bottom: 0.6rem;
}
.personal-greeting p { color: var(--muted); margin-bottom: 1.2rem; line-height: 1.6; }
.personal-stats-row {
    display: flex;
    gap: 2rem;
    margin: 1.4rem 0;
    flex-wrap: wrap;
}
.pstat-num {
    font-family: var(--font-display);
    font-size: 2rem;
    color: var(--amber);
    line-height: 1;
}
.pstat-label {
    font-size: 0.7rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--muted);
    margin-top: 0.3rem;
}
.personal-recent {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.7rem;
}
.personal-recent a { display: block; aspect-ratio: 2/3; overflow: hidden; border: 1px solid var(--border); }
.personal-recent img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s; }
.personal-recent a:hover img { transform: scale(1.08); }
.personal-empty {
    color: var(--muted);
    font-size: 0.9rem;
    padding: 2rem;
    border: 1px dashed var(--border);
    text-align: center;
}

/* ============ HOW IT WORKS (guests) ============ */
.howto {
    padding: 5rem 3rem;
    background: var(--deep);
    border-bottom: 1px solid var(--border);
}
.howto-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    max-width: 1200px;
    margin: 2rem auto 0;
}
.howto-step {
    padding: 2rem;
    background: var(--surface);
    border: 1px solid var(--border);
    position: relative;
    transition: border-color 0.3s, transform 0.3s;
}
.howto-step:hover {
    border-color: var(--amber-dim);
    transform: translateY(-3px);
}
.howto-num {
    font-family: var(--font-display);
    font-size: 3.5rem;
    color: var(--amber);
    line-height: 1;
    opacity: 0.4;
    position: absolute;
    top: 1rem; right: 1.4rem;
}
.howto-step h3 {
    font-family: var(--font-serif);
    font-size: 1.5rem;
    color: var(--ivory);
    margin-bottom: 0.6rem;
}
.howto-step p {
    color: var(--text);
    font-size: 0.9rem;
    line-height: 1.7;
}

/* ============ FEATURED TRAILER ============ */
.trailer-section {
    padding: 5rem 3rem;
    background: var(--black);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}
.trailer-wrap-home {
    position: relative;
    max-width: 1000px;
    margin: 2rem auto 0;
    aspect-ratio: 16 / 9;
    background: var(--deep);
    border: 1px solid var(--border);
    overflow: hidden;
    cursor: pointer;
}
.trailer-thumb {
    position: absolute; inset: 0;
    background-size: cover;
    background-position: center;
}
.trailer-thumb::after {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.7) 100%);
}
.trailer-play {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 90px; height: 90px;
    background: rgba(212, 168, 83, 0.95);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    transition: transform 0.3s, box-shadow 0.3s;
    box-shadow: 0 0 0 0 rgba(212, 168, 83, 0.5);
    animation: pulsePlay 2s ease-in-out infinite;
}
@keyframes pulsePlay {
    0%, 100% { box-shadow: 0 0 0 0 rgba(212, 168, 83, 0.4); }
    50%      { box-shadow: 0 0 0 20px rgba(212, 168, 83, 0); }
}
.trailer-play::after {
    content: '';
    width: 0; height: 0;
    border-left: 22px solid var(--black);
    border-top: 14px solid transparent;
    border-bottom: 14px solid transparent;
    margin-left: 6px;
}
.trailer-wrap-home:hover .trailer-play {
    transform: translate(-50%, -50%) scale(1.1);
}
.trailer-wrap-home iframe {
    position: absolute; inset: 0;
    width: 100%; height: 100%;
    border: 0;
}
.trailer-caption {
    position: absolute;
    bottom: 1.5rem; left: 1.5rem;
    z-index: 2;
    color: var(--ivory);
}
.trailer-caption .label {
    font-family: var(--font-display);
    font-size: 0.7rem;
    letter-spacing: 0.3em;
    color: var(--amber);
    margin-bottom: 0.3rem;
}
.trailer-caption .name {
    font-family: var(--font-serif);
    font-size: 1.8rem;
    line-height: 1.1;
}

/* ============ BY THE NUMBERS ============ */
.by-numbers {
    padding: 5rem 3rem;
    background:
        radial-gradient(ellipse 60% 80% at 50% 50%, rgba(212, 168, 83, 0.08), transparent),
        var(--deep);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    text-align: center;
}
.by-numbers-title {
    font-family: var(--font-display);
    font-size: 0.85rem;
    letter-spacing: 0.4em;
    color: var(--amber);
    margin-bottom: 1rem;
}
.by-numbers-h {
    font-family: var(--font-serif);
    font-size: clamp(2rem, 4vw, 3.2rem);
    color: var(--ivory);
    margin-bottom: 3rem;
}
.by-numbers-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
    max-width: 1100px;
    margin: 0 auto;
}
.bn-cell {
    border-left: 1px solid var(--border);
    padding: 0 1.5rem;
    text-align: left;
}
.bn-cell:first-child { border-left: none; }
.bn-num {
    font-family: var(--font-display);
    font-size: clamp(2.5rem, 5vw, 4rem);
    color: var(--ivory);
    line-height: 1;
}
.bn-num.amber { color: var(--amber); }
.bn-label {
    font-size: 0.75rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--muted);
    margin-top: 0.5rem;
}

/* ============ ACTIVE REVIEWERS ============ */
.active-reviewers {
    margin-top: 2rem;
    padding: 1.5rem;
    background: var(--surface);
    border: 1px solid var(--border);
}
.active-reviewers h4 {
    font-family: var(--font-display);
    font-size: 0.75rem;
    letter-spacing: 0.3em;
    color: var(--amber);
    margin-bottom: 1rem;
}
.reviewer-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 0;
    border-bottom: 1px solid var(--border);
}
.reviewer-row:last-child { border-bottom: none; }
.reviewer-row .who {
    display: flex;
    align-items: center;
    gap: 0.7rem;
}
.reviewer-rank {
    font-family: var(--font-display);
    font-size: 1.1rem;
    color: var(--amber);
    width: 24px;
}
.reviewer-name { color: var(--text-bright); font-weight: 500; font-size: 0.9rem; }
.reviewer-count {
    font-family: var(--font-display);
    color: var(--muted);
    font-size: 0.75rem;
    letter-spacing: 0.15em;
}

/* Inline movies-grid override for home */
.home-section .movies-grid { gap: 1.2rem; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }

@media (max-width: 900px) {
    .hero { padding: 4rem 1.5rem 4rem; }
    .hero-side { display: none; }
    .home-section, .trailer-section, .by-numbers, .howto, .personal-panel { padding: 3.5rem 1.5rem; }
    .spotlight-grid { grid-template-columns: 1fr; }
    .spotlight-img { aspect-ratio: 16/9; }
    .spotlight-img::after { background: linear-gradient(180deg, transparent 50%, var(--surface) 100%); }
    .spotlight-body { padding: 2rem; }
    .personal-grid { grid-template-columns: 1fr; gap: 1.5rem; }
    .personal-recent { grid-template-columns: repeat(5, 1fr); gap: 0.4rem; }
    .howto-grid { grid-template-columns: 1fr; gap: 1rem; }
    .by-numbers-grid { grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
    .bn-cell { border-left: none; padding: 1rem 0; text-align: center; }
    .trailer-play { width: 64px; height: 64px; }
    .trailer-play::after { border-left-width: 16px; border-top-width: 10px; border-bottom-width: 10px; }
}
</style>

<?php if ($api_key_missing): ?>
    <div class="alert alert-error" style="margin: 1rem 3rem;">
        <strong>TMDb API key not set.</strong>
        Edit <code>includes/config.php</code> to enable film data.
    </div>
<?php elseif (!empty($tmdb_err) && empty($trending)): ?>
    <div class="alert alert-error" style="margin: 1rem 3rem;">
        <strong>TMDb request failed.</strong>
        <code style="display:block; margin-top:0.5rem; font-size:0.8rem;"><?= e($tmdb_err) ?></code>
    </div>
<?php endif; ?>

<!-- ============ HERO ============ -->
<section class="hero">
    <div class="hero-bg" <?php if ($spotlight && !empty($spotlight['backdrop_path'])): ?>
        style="background-image: url('<?= e(tmdb_backdrop_url($spotlight['backdrop_path'])) ?>');"
    <?php endif; ?>></div>

    <div class="hero-content">
        <div class="hero-tag">FILMBOX</div>
        <h1 class="hero-title">
            Track films you <em>love</em>.<br>
            Rate. Review. Repeat.
        </h1>
        <p class="hero-sub">
            Your personal film library, powered by TMDb. Search any film, save it to your
            watchlist, track what you've seen, and share your reviews with the community.
        </p>
        <div class="hero-actions">
            <a href="<?= e(BASE_URL) ?>/search.php" class="btn-primary" style="width:auto;">DISCOVER FILMS →</a>
            <?php if ($user): ?>
                <a href="<?= e(BASE_URL) ?>/dashboard.php" class="btn-outline">YOUR DASHBOARD</a>
            <?php else: ?>
                <a href="<?= e(BASE_URL) ?>/register.php" class="btn-outline">SIGN UP FREE</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="hero-side">
        <div>
            <div class="hero-stat-num"><?= number_format($total_cached) ?></div>
            <div class="hero-stat-label">Films cached</div>
        </div>
        <div>
            <div class="hero-stat-num"><?= number_format($total_reviews) ?></div>
            <div class="hero-stat-label">Reviews</div>
        </div>
        <div>
            <div class="hero-stat-num"><?= number_format($total_users) ?></div>
            <div class="hero-stat-label">Members</div>
        </div>
    </div>

    <?php if (!empty($ticker_titles)): ?>
        <div class="ticker-wrap">
            <div class="ticker">
                <?php
                $tick_html = implode('', array_map(fn($t) => '<span>' . e($t) . '</span>', $ticker_titles));
                echo $tick_html . $tick_html; // duplicate for seamless loop
                ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- ============ PERSONAL PANEL (logged in) ============ -->
<?php if ($user && $personal_stats): ?>
<section class="personal-panel">
    <div class="personal-grid">
        <div class="personal-greeting">
            <div style="font-family:var(--font-display); color:var(--amber); letter-spacing:0.3em; font-size:0.7rem; margin-bottom:0.5rem;">YOUR WEEK</div>
            <h2>Welcome back, <?= e($user['username']) ?>.</h2>
            <p>Pick up where you left off, or keep building your collection.</p>
            <div class="personal-stats-row">
                <div>
                    <div class="pstat-num"><?= (int) $personal_stats['total'] ?></div>
                    <div class="pstat-label">Tracking</div>
                </div>
                <div>
                    <div class="pstat-num"><?= (int) $personal_stats['watched'] ?></div>
                    <div class="pstat-label">Watched</div>
                </div>
                <div>
                    <div class="pstat-num"><?= $personal_stats['avg_rating'] ? number_format((float) $personal_stats['avg_rating'], 1) : '—' ?></div>
                    <div class="pstat-label">Avg rating</div>
                </div>
            </div>
            <div style="display:flex; gap:0.6rem;">
                <a href="<?= e(BASE_URL) ?>/dashboard.php" class="btn-primary" style="width:auto;">OPEN DASHBOARD →</a>
                <a href="<?= e(BASE_URL) ?>/lists.php" class="btn-outline">MY LISTS</a>
            </div>
        </div>
        <div>
            <div style="font-family:var(--font-display); color:var(--muted); letter-spacing:0.25em; font-size:0.7rem; margin-bottom:0.8rem;">RECENTLY TRACKED</div>
            <?php if (empty($personal_recent)): ?>
                <div class="personal-empty">
                    You haven't tracked any films yet. <a href="<?= e(BASE_URL) ?>/search.php">Discover one →</a>
                </div>
            <?php else: ?>
                <div class="personal-recent">
                    <?php foreach ($personal_recent as $f): ?>
                        <a href="<?= e(BASE_URL) ?>/movie.php?id=<?= (int) $f['tmdb_id'] ?>" title="<?= e($f['title'] ?? '') ?>">
                            <img src="<?= e(tmdb_poster_url($f['poster_path'] ?? null, 'w342')) ?>" alt="<?= e($f['title'] ?? '') ?>" loading="lazy">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ HOW IT WORKS (guests) ============ -->
<?php if (!$user): ?>
<section class="howto">
    <div class="section-header" style="max-width:1200px; margin: 0 auto 1.5rem;">
        <div>
            <div class="section-label">GETTING STARTED</div>
            <h2 class="section-title">How FilmBox works</h2>
        </div>
        <a href="<?= e(BASE_URL) ?>/register.php" class="section-link">SIGN UP FREE →</a>
    </div>
    <div class="howto-grid">
        <div class="howto-step">
            <span class="howto-num">01</span>
            <h3>Discover</h3>
            <p>Search across 800,000+ films from TMDb. Browse trending, top-rated, and what's playing in theaters right now.</p>
        </div>
        <div class="howto-step">
            <span class="howto-num">02</span>
            <h3>Track</h3>
            <p>Mark films as Want to watch, Watching, Watched, or Dropped. Rate them 1–10 and see your stats grow over time.</p>
        </div>
        <div class="howto-step">
            <span class="howto-num">03</span>
            <h3>Share</h3>
            <p>Write reviews, build custom lists like "Top 10 Horror 2024", and share your taste with the community.</p>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ SPOTLIGHT ============ -->
<?php if ($spotlight): ?>
<section class="home-section">
    <div class="section-header">
        <div>
            <div class="section-label">SPOTLIGHT THIS WEEK</div>
            <h2 class="section-title">What everyone's watching</h2>
        </div>
        <a href="<?= e(BASE_URL) ?>/movie.php?id=<?= (int) $spotlight['id'] ?>" class="section-link">FULL DETAILS →</a>
    </div>
    <a href="<?= e(BASE_URL) ?>/movie.php?id=<?= (int) $spotlight['id'] ?>"
       style="text-decoration:none; color:inherit; display:block;">
        <div class="spotlight-grid">
            <div class="spotlight-img">
                <span class="spotlight-badge">#1 TRENDING</span>
                <img src="<?= e(tmdb_backdrop_url($spotlight['backdrop_path'] ?? null, 'w1280')) ?>"
                     alt="<?= e($spotlight['title']) ?>">
            </div>
            <div class="spotlight-body">
                <div class="spotlight-meta">
                    <span><?= !empty($spotlight['release_date']) ? substr($spotlight['release_date'], 0, 4) : '—' ?></span>
                    <span class="sep"></span>
                    <span><?= number_format((int) ($spotlight['vote_count'] ?? 0)) ?> votes</span>
                </div>
                <h3 class="spotlight-title"><?= e($spotlight['title']) ?></h3>
                <div class="spotlight-score">
                    <span class="n"><?= number_format((float) $spotlight['vote_average'], 1) ?></span>
                    <span class="o">/ 10</span>
                </div>
                <p class="spotlight-excerpt"><?= e($spotlight['overview'] ?? '') ?></p>
                <span class="btn-outline" style="align-self:flex-start;">VIEW FILM →</span>
            </div>
        </div>
    </a>
</section>
<?php endif; ?>

<!-- ============ FEATURED TRAILER ============ -->
<?php if ($spotlight_trailer && $spotlight): ?>
<section class="trailer-section">
    <div class="section-header" style="max-width:1000px; margin: 0 auto 0.5rem;">
        <div>
            <div class="section-label">WATCH THE TRAILER</div>
            <h2 class="section-title"><?= e($spotlight['title']) ?></h2>
        </div>
    </div>
    <div class="trailer-wrap-home" id="trailerWrap">
        <div class="trailer-thumb" id="trailerThumb"
             style="background-image: url('<?= e(tmdb_backdrop_url($spotlight['backdrop_path'] ?? null, 'w1280')) ?>');"></div>
        <div class="trailer-caption">
            <div class="label">OFFICIAL TRAILER</div>
            <div class="name"><?= e($spotlight['title']) ?></div>
        </div>
        <div class="trailer-play" id="trailerPlay"></div>
    </div>
</section>
<script>
(function() {
    const wrap = document.getElementById('trailerWrap');
    if (!wrap) return;
    wrap.addEventListener('click', () => {
        const iframe = document.createElement('iframe');
        iframe.src = 'https://www.youtube.com/embed/<?= e($spotlight_trailer) ?>?autoplay=1';
        iframe.allow = 'autoplay; encrypted-media; fullscreen';
        iframe.allowFullscreen = true;
        wrap.innerHTML = '';
        wrap.appendChild(iframe);
        wrap.style.cursor = 'default';
    });
})();
</script>
<?php endif; ?>

<!-- ============ TRENDING GRID ============ -->
<?php if (!empty($trending_rest)): ?>
<section class="home-section alt">
    <div class="section-header">
        <div>
            <div class="section-label">FRESH PICKS</div>
            <h2 class="section-title">Trending now</h2>
        </div>
        <a href="<?= e(BASE_URL) ?>/search.php" class="section-link">EXPLORE ALL →</a>
    </div>
    <div class="movies-grid">
        <?php foreach ($trending_rest as $m): ?>
            <a href="<?= e(BASE_URL) ?>/movie.php?id=<?= (int) $m['id'] ?>" class="movie-card">
                <div class="movie-poster">
                    <img src="<?= e(tmdb_poster_url($m['poster_path'] ?? null)) ?>"
                         alt="<?= e($m['title']) ?>" loading="lazy">
                    <?php if (!empty($m['vote_average'])): ?>
                        <div class="movie-score"><?= number_format((float) $m['vote_average'], 1) ?></div>
                    <?php endif; ?>
                </div>
                <div class="movie-info">
                    <h3 class="movie-title"><?= e($m['title']) ?></h3>
                    <div class="movie-year">
                        <?= !empty($m['release_date']) ? substr($m['release_date'], 0, 4) : '—' ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============ IN THEATERS NOW ============ -->
<?php if (!empty($in_theaters)): ?>
<section class="home-section">
    <div class="section-header">
        <div>
            <div class="section-label">ON THE BIG SCREEN</div>
            <h2 class="section-title">In theaters now</h2>
        </div>
        <a href="<?= e(BASE_URL) ?>/search.php" class="section-link">SEE MORE →</a>
    </div>
    <div class="movies-grid">
        <?php foreach ($in_theaters as $m): ?>
            <a href="<?= e(BASE_URL) ?>/movie.php?id=<?= (int) $m['id'] ?>" class="movie-card">
                <div class="movie-poster">
                    <img src="<?= e(tmdb_poster_url($m['poster_path'] ?? null)) ?>"
                         alt="<?= e($m['title']) ?>" loading="lazy">
                    <?php if (!empty($m['vote_average'])): ?>
                        <div class="movie-score"><?= number_format((float) $m['vote_average'], 1) ?></div>
                    <?php endif; ?>
                </div>
                <div class="movie-info">
                    <h3 class="movie-title"><?= e($m['title']) ?></h3>
                    <div class="movie-year">
                        <?= !empty($m['release_date']) ? substr($m['release_date'], 0, 4) : '—' ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============ BY THE NUMBERS ============ -->
<section class="by-numbers">
    <div class="by-numbers-title">FILMBOX IN NUMBERS</div>
    <h2 class="by-numbers-h">A library that grows with every search.</h2>
    <div class="by-numbers-grid">
        <div class="bn-cell">
            <div class="bn-num amber"><?= number_format($total_cached) ?></div>
            <div class="bn-label">Films catalogued</div>
        </div>
        <div class="bn-cell">
            <div class="bn-num"><?= number_format($total_tracked) ?></div>
            <div class="bn-label">Items in watchlists</div>
        </div>
        <div class="bn-cell">
            <div class="bn-num"><?= number_format($total_reviews) ?></div>
            <div class="bn-label">Reviews written</div>
        </div>
        <div class="bn-cell">
            <div class="bn-num"><?= number_format($total_users) ?></div>
            <div class="bn-label">Members</div>
        </div>
    </div>
</section>

<!-- ============ COMMUNITY REVIEWS ============ -->
<section class="home-section">
    <div class="section-header">
        <div>
            <div class="section-label">FROM THE COMMUNITY</div>
            <h2 class="section-title">Latest reviews</h2>
        </div>
        <?php if ($user): ?>
            <a href="<?= e(BASE_URL) ?>/search.php" class="section-link">WRITE ONE →</a>
        <?php else: ?>
            <a href="<?= e(BASE_URL) ?>/register.php" class="section-link">JOIN TO REVIEW →</a>
        <?php endif; ?>
    </div>

    <?php if (empty($recent_reviews)): ?>
        <div class="empty-state">
            <h3>No reviews yet.</h3>
            <p>Be the first to share your thoughts on a film.</p>
            <a href="<?= e(BASE_URL) ?>/search.php" class="btn-outline">PICK A FILM</a>
        </div>
    <?php else: ?>
        <div class="community-grid">
            <?php foreach ($recent_reviews as $r): ?>
                <a href="<?= e(BASE_URL) ?>/movie.php?id=<?= (int) $r['tmdb_id'] ?>"
                   class="community-review" style="text-decoration:none;">
                    <div class="poster-wrap">
                        <img src="<?= e(tmdb_poster_url($r['poster_path'] ?? null, 'w780')) ?>"
                             alt="<?= e($r['title'] ?? '') ?>" loading="lazy">
                    </div>
                    <div class="body">
                        <h3 class="film-title"><?= e($r['title'] ?? 'Film #' . $r['tmdb_id']) ?></h3>
                        <div class="by-line">
                            BY <?= e(strtoupper($r['username'])) ?> ·
                            <?= e(date('M j', strtotime($r['created_at']))) ?>
                        </div>
                        <p class="excerpt"><?= e($r['content']) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- ============ TOP RATED ============ -->
<?php if (!empty($top_rated_six)): ?>
<section class="home-section alt">
    <div class="section-header">
        <div>
            <div class="section-label">ALL-TIME CLASSICS</div>
            <h2 class="section-title">Top rated</h2>
        </div>
        <a href="<?= e(BASE_URL) ?>/search.php" class="section-link">SEARCH MORE →</a>
    </div>
    <div class="movies-grid">
        <?php foreach ($top_rated_six as $m): ?>
            <a href="<?= e(BASE_URL) ?>/movie.php?id=<?= (int) $m['id'] ?>" class="movie-card">
                <div class="movie-poster">
                    <img src="<?= e(tmdb_poster_url($m['poster_path'] ?? null)) ?>"
                         alt="<?= e($m['title']) ?>" loading="lazy">
                    <?php if (!empty($m['vote_average'])): ?>
                        <div class="movie-score"><?= number_format((float) $m['vote_average'], 1) ?></div>
                    <?php endif; ?>
                </div>
                <div class="movie-info">
                    <h3 class="movie-title"><?= e($m['title']) ?></h3>
                    <div class="movie-year">
                        <?= !empty($m['release_date']) ? substr($m['release_date'], 0, 4) : '—' ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============ COMING SOON ============ -->
<?php if (!empty($coming_soon)): ?>
<section class="home-section">
    <div class="section-header">
        <div>
            <div class="section-label">RELEASE CALENDAR</div>
            <h2 class="section-title">Coming soon</h2>
        </div>
        <a href="<?= e(BASE_URL) ?>/search.php" class="section-link">EXPLORE →</a>
    </div>
    <div class="movies-grid">
        <?php foreach ($coming_soon as $m): ?>
            <a href="<?= e(BASE_URL) ?>/movie.php?id=<?= (int) $m['id'] ?>" class="movie-card">
                <div class="movie-poster">
                    <img src="<?= e(tmdb_poster_url($m['poster_path'] ?? null)) ?>"
                         alt="<?= e($m['title']) ?>" loading="lazy">
                </div>
                <div class="movie-info">
                    <h3 class="movie-title"><?= e($m['title']) ?></h3>
                    <div class="movie-year">
                        <?= !empty($m['release_date']) ? date('M j, Y', strtotime($m['release_date'])) : 'TBA' ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============ GENRES ============ -->
<?php if (!empty($genres)): ?>
<section class="home-section">
    <div class="section-header">
        <div>
            <div class="section-label">BROWSE BY</div>
            <h2 class="section-title">Genre</h2>
        </div>
    </div>
    <div class="genre-pills">
        <?php foreach ($genres as $g): ?>
            <a href="<?= e(BASE_URL) ?>/search.php?q=<?= urlencode($g['name']) ?>" class="genre-pill-link">
                <?= e($g['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
