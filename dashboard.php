<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tmdb.php';
require_login();
$user = current_user();

// --- Counts per status ---
$stmt = db()->prepare(
    "SELECT status, COUNT(*) AS n FROM user_movies WHERE user_id = ? GROUP BY status"
);
$stmt->execute([$user['id']]);
$counts = ['want' => 0, 'watching' => 0, 'watched' => 0, 'dropped' => 0];
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['n'];
}
$total_tracked = array_sum($counts);

// --- Current tab + sort ---
$current_status = $_GET['status'] ?? 'all';
$sort           = $_GET['sort']   ?? 'recent';
$valid_status   = ['all', 'want', 'watching', 'watched', 'dropped'];
$valid_sort     = ['recent', 'rating', 'title'];
if (!in_array($current_status, $valid_status, true)) $current_status = 'all';
if (!in_array($sort, $valid_sort, true))             $sort = 'recent';

$order_sql = match ($sort) {
    'rating' => 'um.rating DESC, um.updated_at DESC',
    'title'  => 'COALESCE(m.title, "") ASC',
    default  => 'um.updated_at DESC',
};

$where_sql = 'um.user_id = ?';
$params    = [$user['id']];
if ($current_status !== 'all') {
    $where_sql .= ' AND um.status = ?';
    $params[]   = $current_status;
}

$stmt = db()->prepare(
    "SELECT um.tmdb_id, um.status, um.rating, um.updated_at,
            m.title, m.poster_path, m.release_date, m.vote_average
     FROM user_movies um
     LEFT JOIN movies_cache m ON m.tmdb_id = um.tmdb_id
     WHERE {$where_sql}
     ORDER BY {$order_sql}
     LIMIT 60"
);
$stmt->execute($params);
$films = $stmt->fetchAll();

// --- Chart data ---
$stmt = db()->prepare(
    "SELECT m.genres
     FROM user_movies um
     JOIN movies_cache m ON m.tmdb_id = um.tmdb_id
     WHERE um.user_id = ? AND um.status = 'watched' AND m.genres IS NOT NULL"
);
$stmt->execute([$user['id']]);
$genre_counts = [];
foreach ($stmt->fetchAll() as $row) {
    foreach (array_filter(array_map('trim', explode(',', $row['genres']))) as $g) {
        $genre_counts[$g] = ($genre_counts[$g] ?? 0) + 1;
    }
}
arsort($genre_counts);
$genre_counts = array_slice($genre_counts, 0, 8, true);

$stmt = db()->prepare(
    "SELECT rating, COUNT(*) AS n
     FROM user_movies
     WHERE user_id = ? AND rating IS NOT NULL
     GROUP BY rating ORDER BY rating"
);
$stmt->execute([$user['id']]);
$rating_dist = array_fill(1, 10, 0);
foreach ($stmt->fetchAll() as $row) {
    $rating_dist[(int) $row['rating']] = (int) $row['n'];
}

$stmt = db()->prepare(
    "SELECT DATE_FORMAT(updated_at, '%Y-%m') AS ym, COUNT(*) AS n
     FROM user_movies
     WHERE user_id = ? AND status = 'watched'
       AND updated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY ym ORDER BY ym"
);
$stmt->execute([$user['id']]);
$by_month_raw = [];
foreach ($stmt->fetchAll() as $row) {
    $by_month_raw[$row['ym']] = (int) $row['n'];
}
$by_month = [];
for ($i = 11; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-{$i} months"));
    $by_month[$ym] = $by_month_raw[$ym] ?? 0;
}

$avg_rating = (float) db()->query(
    "SELECT AVG(rating) FROM user_movies WHERE user_id = " . (int) $user['id'] . " AND rating IS NOT NULL"
)->fetchColumn();
$my_reviews = (int) db()->query(
    "SELECT COUNT(*) FROM reviews WHERE user_id = " . (int) $user['id']
)->fetchColumn();

$_title = 'Dashboard';
$_page  = 'dashboard';
include __DIR__ . '/includes/header.php';
?>

<style>
.dash-hero {
    padding: 3rem 3rem 2rem;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(180deg, var(--deep) 0%, var(--black) 100%);
}
.dash-hero h1 {
    font-family: var(--font-serif);
    font-size: clamp(2rem, 4vw, 3rem);
    color: var(--ivory);
    margin-bottom: 0.4rem;
}
.dash-hero p { color: var(--muted); }

.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    padding: 2rem 3rem;
}
.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    padding: 1.4rem 1.6rem;
    transition: border-color 0.3s, transform 0.3s;
    cursor: pointer;
    text-decoration: none;
    display: block;
}
.stat-card:hover {
    border-color: var(--amber-dim);
    transform: translateY(-2px);
}
.stat-num {
    font-family: var(--font-display);
    font-size: 2.6rem;
    color: var(--amber);
    line-height: 1;
}
.stat-label {
    font-size: 0.75rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--muted);
    margin-top: 0.5rem;
}

.dash-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 3rem 1rem;
    border-top: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 1rem;
}
.dash-tabs {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.dash-tab {
    font-family: var(--font-display);
    font-size: 0.75rem;
    letter-spacing: 0.15em;
    padding: 0.5rem 1.1rem;
    color: var(--text);
    background: var(--deep);
    border: 1px solid var(--border);
    text-decoration: none;
    transition: all 0.2s;
}
.dash-tab:hover { border-color: var(--amber-dim); color: var(--amber); }
.dash-tab.active {
    background: var(--amber);
    color: var(--black);
    border-color: var(--amber);
}

.dash-sort {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.85rem;
    color: var(--muted);
}
.dash-sort select {
    background: var(--deep);
    color: var(--text-bright);
    border: 1px solid var(--border);
    padding: 0.4rem 0.8rem;
    font-family: var(--font-body);
    font-size: 0.85rem;
}

.dash-films { padding: 1rem 3rem 3rem; }

.film-status {
    position: absolute;
    top: 0.6rem; left: 0.6rem;
    font-family: var(--font-display);
    font-size: 0.65rem;
    letter-spacing: 0.15em;
    padding: 0.25rem 0.6rem;
    background: rgba(10,10,10,0.85);
    backdrop-filter: blur(6px);
    border: 1px solid var(--border);
}
.film-status.want     { color: #9ec5e8; }
.film-status.watching { color: var(--amber); }
.film-status.watched  { color: #9bd0a8; }
.film-status.dropped  { color: #e88a8a; }

.film-rating {
    position: absolute;
    bottom: 0.6rem; left: 0.6rem;
    font-family: var(--font-display);
    font-size: 0.85rem;
    background: var(--amber);
    color: var(--black);
    padding: 0.15rem 0.55rem;
}

.charts-wrap {
    padding: 3rem;
    border-top: 1px solid var(--border);
    background: var(--deep);
}
.charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-top: 1.5rem;
}
.chart-card {
    background: var(--surface);
    border: 1px solid var(--border);
    padding: 1.5rem;
}
.chart-card h3 {
    font-family: var(--font-display);
    font-size: 0.85rem;
    letter-spacing: 0.2em;
    color: var(--amber);
    margin-bottom: 1rem;
}
.chart-card.wide { grid-column: 1 / -1; }
.chart-canvas-wrap {
    position: relative;
    height: 260px;
}

.empty-dash {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--surface);
    border: 1px dashed var(--border);
    margin: 1rem 0;
}
.empty-dash h3 {
    font-family: var(--font-serif);
    color: var(--ivory);
    margin-bottom: 0.5rem;
}
.empty-dash p { color: var(--muted); margin-bottom: 1.2rem; }

.dash-quick-links {
    display: flex;
    gap: 0.6rem;
    padding: 0 3rem 2rem;
    flex-wrap: wrap;
}

@media (max-width: 900px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); padding: 1.5rem; }
    .dash-hero, .dash-toolbar, .dash-films, .charts-wrap, .dash-quick-links { padding-left: 1.5rem; padding-right: 1.5rem; }
    .charts-grid { grid-template-columns: 1fr; }
}
</style>

<div class="dash-hero">
    <h1>Welcome back, <?= e($user['username']) ?>.</h1>
    <p>You're tracking <strong><?= number_format($total_tracked) ?></strong> films
        <?php if ($avg_rating > 0): ?>
            · average rating <strong><?= number_format($avg_rating, 1) ?>/10</strong>
        <?php endif; ?>
        <?php if ($my_reviews > 0): ?>
            · <strong><?= number_format($my_reviews) ?></strong> reviews written
        <?php endif; ?>
    </p>
</div>

<div class="dash-quick-links">
    <a href="<?= e(BASE_URL) ?>/lists.php" class="btn-outline">MY LISTS</a>
    <a href="<?= e(BASE_URL) ?>/search.php" class="btn-outline">DISCOVER</a>
</div>

<div class="stats-row">
    <a class="stat-card" href="?status=want">
        <div class="stat-num"><?= (int) $counts['want'] ?></div>
        <div class="stat-label">Want to watch</div>
    </a>
    <a class="stat-card" href="?status=watching">
        <div class="stat-num"><?= (int) $counts['watching'] ?></div>
        <div class="stat-label">Watching</div>
    </a>
    <a class="stat-card" href="?status=watched">
        <div class="stat-num"><?= (int) $counts['watched'] ?></div>
        <div class="stat-label">Watched</div>
    </a>
    <a class="stat-card" href="?status=dropped">
        <div class="stat-num"><?= (int) $counts['dropped'] ?></div>
        <div class="stat-label">Dropped</div>
    </a>
</div>

<div class="dash-toolbar">
    <div class="dash-tabs">
        <?php
        $tabs = [
            'all'      => 'All',
            'want'     => 'Want',
            'watching' => 'Watching',
            'watched'  => 'Watched',
            'dropped'  => 'Dropped',
        ];
        foreach ($tabs as $key => $label):
            $active = $current_status === $key ? 'active' : '';
        ?>
            <a class="dash-tab <?= $active ?>" href="?status=<?= $key ?>&sort=<?= e($sort) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <form method="get" class="dash-sort">
        <input type="hidden" name="status" value="<?= e($current_status) ?>">
        <label for="sort">Sort:</label>
        <select name="sort" id="sort" onchange="this.form.submit()">
            <option value="recent"<?= $sort === 'recent' ? ' selected' : '' ?>>Recently updated</option>
            <option value="rating"<?= $sort === 'rating' ? ' selected' : '' ?>>My rating</option>
            <option value="title"<?= $sort === 'title'  ? ' selected' : '' ?>>Title (A–Z)</option>
        </select>
    </form>
</div>

<div class="dash-films">
    <?php if (empty($films)): ?>
        <div class="empty-dash">
            <h3>Nothing here yet.</h3>
            <p>
                <?= $current_status === 'all'
                    ? 'Start by searching for a film and adding it to your list.'
                    : "You don't have any films marked as '" . e($tabs[$current_status]) . "'." ?>
            </p>
            <a href="<?= e(BASE_URL) ?>/search.php" class="btn-outline">FIND A FILM →</a>
        </div>
    <?php else: ?>
        <div class="movies-grid">
            <?php foreach ($films as $f): ?>
                <a href="<?= e(BASE_URL) ?>/movie.php?id=<?= (int) $f['tmdb_id'] ?>" class="movie-card">
                    <div class="movie-poster">
                        <span class="film-status <?= e($f['status']) ?>"><?= e(strtoupper($f['status'])) ?></span>
                        <img src="<?= e(tmdb_poster_url($f['poster_path'] ?? null)) ?>"
                             alt="<?= e($f['title'] ?? '') ?>" loading="lazy">
                        <?php if (!empty($f['rating'])): ?>
                            <div class="film-rating">★ <?= (int) $f['rating'] ?></div>
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

<?php if ($total_tracked > 0): ?>
<section class="charts-wrap">
    <div style="display:flex; align-items:flex-end; justify-content:space-between; border-bottom:1px solid var(--border); padding-bottom:1rem;">
        <div>
            <div style="font-family:var(--font-display); color:var(--amber); letter-spacing:0.3em; font-size:0.75rem;">YOUR ACTIVITY</div>
            <h2 style="font-family:var(--font-serif); color:var(--ivory); font-size:1.8rem;">Statistics</h2>
        </div>
    </div>
    <div class="charts-grid">
        <?php if (!empty($genre_counts)): ?>
        <div class="chart-card">
            <h3>TOP GENRES (WATCHED)</h3>
            <div class="chart-canvas-wrap"><canvas id="genreChart"></canvas></div>
        </div>
        <?php endif; ?>

        <?php if (array_sum($rating_dist) > 0): ?>
        <div class="chart-card">
            <h3>RATING DISTRIBUTION</h3>
            <div class="chart-canvas-wrap"><canvas id="ratingChart"></canvas></div>
        </div>
        <?php endif; ?>

        <?php if (array_sum($by_month) > 0): ?>
        <div class="chart-card wide">
            <h3>WATCHED — LAST 12 MONTHS</h3>
            <div class="chart-canvas-wrap" style="height: 240px;"><canvas id="monthChart"></canvas></div>
        </div>
        <?php endif; ?>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const AMBER = '#d4a853', AMBER_DIM = '#b8923e', MUTED = '#666', TEXT = '#c8c2b8', BORDER = '#2a2a2a';
Chart.defaults.color = TEXT;
Chart.defaults.borderColor = BORDER;
Chart.defaults.font.family = "'DM Sans', sans-serif";

<?php if (!empty($genre_counts)): ?>
new Chart(document.getElementById('genreChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($genre_counts), JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            data: <?= json_encode(array_values($genre_counts)) ?>,
            backgroundColor: AMBER,
            borderColor: AMBER_DIM,
            borderWidth: 1,
        }]
    },
    options: {
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        responsive: true, maintainAspectRatio: false,
        scales: {
            x: { ticks: { precision: 0 }, grid: { color: BORDER } },
            y: { grid: { display: false } }
        }
    }
});
<?php endif; ?>

<?php if (array_sum($rating_dist) > 0): ?>
new Chart(document.getElementById('ratingChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($i) => "{$i}", array_keys($rating_dist))) ?>,
        datasets: [{
            data: <?= json_encode(array_values($rating_dist)) ?>,
            backgroundColor: AMBER,
            borderColor: AMBER_DIM,
            borderWidth: 1,
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        responsive: true, maintainAspectRatio: false,
        scales: {
            x: { grid: { display: false }, title: { display: true, text: 'Rating (1–10)', color: MUTED } },
            y: { ticks: { precision: 0 }, grid: { color: BORDER } }
        }
    }
});
<?php endif; ?>

<?php if (array_sum($by_month) > 0): ?>
new Chart(document.getElementById('monthChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($ym) => date('M Y', strtotime($ym . '-01')), array_keys($by_month))) ?>,
        datasets: [{
            data: <?= json_encode(array_values($by_month)) ?>,
            borderColor: AMBER,
            backgroundColor: 'rgba(212, 168, 83, 0.15)',
            fill: true,
            tension: 0.35,
            pointRadius: 4,
            pointBackgroundColor: AMBER,
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        responsive: true, maintainAspectRatio: false,
        scales: {
            x: { grid: { display: false } },
            y: { ticks: { precision: 0 }, beginAtZero: true, grid: { color: BORDER } }
        }
    }
});
<?php endif; ?>
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
