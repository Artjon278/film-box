<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tmdb.php';
require_login();
$user = current_user();

$api_key_missing = (TMDB_API_KEY === 'PUT_YOUR_TMDB_API_KEY_HERE' || TMDB_API_KEY === '');

// --- Get user's top-rated watched films (basis for recs) ---
$stmt = db()->prepare(
    "SELECT um.tmdb_id, um.rating, m.title
     FROM user_movies um
     LEFT JOIN movies_cache m ON m.tmdb_id = um.tmdb_id
     WHERE um.user_id = ?
       AND (um.rating >= 7 OR (um.status = 'watched' AND um.rating IS NULL))
     ORDER BY um.rating DESC, um.updated_at DESC
     LIMIT 5"
);
$stmt->execute([$user['id']]);
$source_films = $stmt->fetchAll();

// --- Get every tmdb_id already in user's library to exclude from recs ---
$stmt = db()->prepare('SELECT tmdb_id FROM user_movies WHERE user_id = ?');
$stmt->execute([$user['id']]);
$already_tracked = array_flip(array_column($stmt->fetchAll(), 'tmdb_id'));

// --- Aggregate recommendations from TMDb for each source film ---
$candidates = []; // tmdb_id => ['film' => ..., 'score' => N, 'because' => [titles]]

if (!$api_key_missing && !empty($source_films)) {
    foreach ($source_films as $src) {
        $recs = tmdb_movie_recommendations((int) $src['tmdb_id']);
        foreach (array_slice($recs, 0, 15) as $r) {
            $id = (int) $r['id'];
            if (isset($already_tracked[$id])) continue;
            if (!isset($candidates[$id])) {
                $candidates[$id] = [
                    'film'    => $r,
                    'score'   => 0,
                    'because' => [],
                ];
            }
            $candidates[$id]['score']++;
            $candidates[$id]['because'][] = $src['title'] ?? 'a film you watched';
        }
    }
}

// Sort by score desc, then by vote_average desc
uasort($candidates, function ($a, $b) {
    if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];
    return ($b['film']['vote_average'] ?? 0) <=> ($a['film']['vote_average'] ?? 0);
});

$candidates = array_slice($candidates, 0, 24, true);

$_title = 'Recommendations';
$_page  = 'recs';
include __DIR__ . '/includes/header.php';
?>

<style>
.recs-hero {
    padding: 3rem 3rem 2rem;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(180deg, var(--deep) 0%, var(--black) 100%);
}
.recs-hero h1 {
    font-family: var(--font-serif);
    font-size: clamp(2rem, 4vw, 3rem);
    color: var(--ivory);
}
.recs-hero p { color: var(--muted); margin-top: 0.4rem; max-width: 640px; line-height: 1.6; }

.recs-because {
    padding: 1.5rem 3rem;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}
.recs-because-label {
    font-family: var(--font-display);
    font-size: 0.75rem;
    letter-spacing: 0.25em;
    color: var(--amber);
    margin-right: 0.5rem;
}
.recs-because-chip {
    font-size: 0.85rem;
    padding: 0.4rem 0.9rem;
    background: var(--deep);
    border: 1px solid var(--border);
    color: var(--text);
}

.recs-grid-wrap { padding: 2rem 3rem 4rem; }

.rec-card-because {
    font-size: 0.7rem;
    color: var(--muted);
    letter-spacing: 0.1em;
    margin-top: 0.4rem;
    text-transform: uppercase;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.rec-card-because em { color: var(--amber); font-style: normal; }

.recs-empty {
    text-align: center;
    padding: 5rem 2rem;
    background: var(--surface);
    border: 1px dashed var(--border);
    margin: 0 3rem;
}
.recs-empty h2 {
    font-family: var(--font-serif);
    font-size: 1.6rem;
    color: var(--ivory);
    margin-bottom: 0.6rem;
}
.recs-empty p { color: var(--muted); margin-bottom: 1.5rem; }

@media (max-width: 768px) {
    .recs-hero, .recs-because, .recs-grid-wrap, .recs-empty { padding-left: 1.5rem; padding-right: 1.5rem; }
    .recs-empty { margin: 0 1.5rem; }
}
</style>

<section class="recs-hero">
    <h1>Recommended for you</h1>
    <p>Picks based on the films you've rated highest. The more you watch and rate, the better these get.</p>
</section>

<?php if (!empty($source_films)): ?>
<div class="recs-because">
    <span class="recs-because-label">BASED ON</span>
    <?php foreach ($source_films as $src): ?>
        <span class="recs-because-chip">
            <?= e($src['title'] ?? 'Film #' . $src['tmdb_id']) ?>
            <?php if ($src['rating']): ?>
                <span style="color:var(--amber); margin-left:0.4rem;">★ <?= (int) $src['rating'] ?></span>
            <?php endif; ?>
        </span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="recs-grid-wrap">
    <?php if ($api_key_missing): ?>
        <div class="alert alert-error">Set your TMDb API key in <code>includes/config.php</code> first.</div>
    <?php elseif (empty($source_films)): ?>
        <div class="recs-empty">
            <h2>Not enough data yet.</h2>
            <p>Watch and rate a few films first so we can find ones you'd like. Aim for a rating of 7+ on at least one.</p>
            <a href="<?= e(BASE_URL) ?>/search.php" class="btn-primary" style="width:auto; display:inline-block;">DISCOVER FILMS →</a>
        </div>
    <?php elseif (empty($candidates)): ?>
        <div class="recs-empty">
            <h2>No new recommendations right now.</h2>
            <p>You've already tracked everything TMDb would suggest based on your taste. Try rating a different film to expand your recommendations.</p>
        </div>
    <?php else: ?>
        <div class="movies-grid">
            <?php foreach ($candidates as $c):
                $f = $c['film'];
                $because = implode(', ', array_slice(array_unique($c['because']), 0, 2));
            ?>
                <a href="<?= e(BASE_URL) ?>/movie.php?id=<?= (int) $f['id'] ?>" class="movie-card">
                    <div class="movie-poster">
                        <img src="<?= e(tmdb_poster_url($f['poster_path'] ?? null)) ?>"
                             alt="<?= e($f['title']) ?>" loading="lazy">
                        <?php if (!empty($f['vote_average'])): ?>
                            <div class="movie-score"><?= number_format((float) $f['vote_average'], 1) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="movie-info">
                        <h3 class="movie-title"><?= e($f['title']) ?></h3>
                        <div class="movie-year">
                            <?= !empty($f['release_date']) ? substr($f['release_date'], 0, 4) : '—' ?>
                        </div>
                        <div class="rec-card-because">
                            Because: <em><?= e($because) ?></em>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
