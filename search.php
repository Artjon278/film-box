<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tmdb.php';

$query = trim($_GET['q'] ?? '');
$page  = max(1, (int) ($_GET['page'] ?? 1));

// --- Filters (only meaningful when there's no free-text query) ---
$f_genre  = (int) ($_GET['genre']  ?? 0);
$f_year   = (int) ($_GET['year']   ?? 0);
$f_rating = (float) ($_GET['rating'] ?? 0);
$f_sort   = $_GET['sort'] ?? 'popularity.desc';

$valid_sorts = [
    'popularity.desc'   => 'Most popular',
    'vote_average.desc' => 'Highest rated',
    'release_date.desc' => 'Newest',
    'release_date.asc'  => 'Oldest',
    'revenue.desc'      => 'Highest grossing',
];
if (!isset($valid_sorts[$f_sort])) $f_sort = 'popularity.desc';

$has_filters = $f_genre > 0 || $f_year > 0 || $f_rating > 0 || $f_sort !== 'popularity.desc';

$api_key_missing = (TMDB_API_KEY === 'PUT_YOUR_TMDB_API_KEY_HERE' || TMDB_API_KEY === '');

$results       = [];
$total_pages   = 0;
$total_results = 0;
$mode          = 'trending'; // trending | search | discover
$all_genres    = $api_key_missing ? [] : tmdb_genres();

if (!$api_key_missing) {
    if ($query !== '') {
        $mode          = 'search';
        $data          = tmdb_search_movies($query, $page);
        $results       = $data['results'];
        $total_pages   = min(500, (int) $data['total_pages']);
        $total_results = (int) $data['total_results'];
    } elseif ($has_filters) {
        $mode   = 'discover';
        $params = ['sort_by' => $f_sort, 'page' => $page];
        if ($f_genre > 0)  $params['with_genres']         = $f_genre;
        if ($f_year > 0)   $params['primary_release_year'] = $f_year;
        if ($f_rating > 0) {
            $params['vote_average.gte'] = $f_rating;
            $params['vote_count.gte']   = 50; // avoid tiny-vote outliers
        }
        $data          = tmdb_discover_movies($params);
        $results       = $data['results'];
        $total_pages   = min(500, (int) ($data['total_pages'] ?? 0));
        $total_results = (int) ($data['total_results'] ?? 0);
    } else {
        $results = tmdb_trending('week');
    }
}

// Build a query string for pagination links (preserve filters/query)
$qs_base = $_GET;
unset($qs_base['page']);
$qs_str = http_build_query($qs_base);

$_title = $query ? "Search: {$query}" : 'Discover';
$_page  = 'search';
include __DIR__ . '/includes/header.php';
?>

<style>
.search-toolbar {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.filters-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.7rem;
    align-items: center;
    padding: 1rem;
    background: var(--surface);
    border: 1px solid var(--border);
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.filter-group label {
    font-size: 0.65rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--muted);
}
.filter-input {
    padding: 0.5rem 0.7rem;
    font-family: var(--font-body);
    font-size: 0.85rem;
    background: var(--deep);
    border: 1px solid var(--border);
    color: var(--text-bright);
    outline: none;
    min-width: 110px;
}
.filter-input:focus { border-color: var(--amber-dim); }
select.filter-input { cursor: pointer; }

.filters-actions {
    margin-left: auto;
    display: flex;
    gap: 0.5rem;
    align-items: flex-end;
}

.filter-hint {
    color: var(--muted);
    font-size: 0.78rem;
    padding-left: 0.4rem;
    width: 100%;
    margin-top: -0.3rem;
}
.filter-hint strong { color: var(--amber); }
</style>

<div class="container">
    <form method="get" class="search-toolbar">
        <div style="display:flex; gap:0;">
            <input type="text" name="q" class="form-input search-input"
                   placeholder="Search films..." value="<?= e($query) ?>" <?= $query === '' ? 'autofocus' : '' ?>>
            <button class="btn-primary search-btn" type="submit">SEARCH</button>
        </div>

        <div class="filters-row">
            <div class="filter-group">
                <label for="f_genre">Genre</label>
                <select name="genre" id="f_genre" class="filter-input">
                    <option value="0">Any genre</option>
                    <?php foreach ($all_genres as $g): ?>
                        <option value="<?= (int) $g['id'] ?>" <?= $f_genre === (int) $g['id'] ? 'selected' : '' ?>>
                            <?= e($g['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="f_year">Year</label>
                <input type="number" name="year" id="f_year" class="filter-input"
                       placeholder="e.g. 2024" min="1900" max="<?= (int) date('Y') + 2 ?>"
                       value="<?= $f_year > 0 ? (int) $f_year : '' ?>" style="width:120px;">
            </div>

            <div class="filter-group">
                <label for="f_rating">Min rating</label>
                <input type="number" name="rating" id="f_rating" class="filter-input"
                       placeholder="0–10" min="0" max="10" step="0.5"
                       value="<?= $f_rating > 0 ? (float) $f_rating : '' ?>" style="width:100px;">
            </div>

            <div class="filter-group">
                <label for="f_sort">Sort</label>
                <select name="sort" id="f_sort" class="filter-input">
                    <?php foreach ($valid_sorts as $val => $lbl): ?>
                        <option value="<?= e($val) ?>" <?= $f_sort === $val ? 'selected' : '' ?>>
                            <?= e($lbl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filters-actions">
                <button type="submit" class="btn-outline" style="padding:0.55rem 1.2rem; font-size:0.75rem;">APPLY</button>
                <?php if ($has_filters || $query !== ''): ?>
                    <a href="<?= e(BASE_URL) ?>/search.php" class="btn-outline" style="padding:0.55rem 1.2rem; font-size:0.75rem;">CLEAR</a>
                <?php endif; ?>
            </div>

            <?php if ($query !== '' && $has_filters): ?>
                <div class="filter-hint">
                    <strong>Note:</strong> filters are ignored when a search query is set —
                    clear the search box to use them.
                </div>
            <?php elseif ($mode === 'discover'): ?>
                <div class="filter-hint">Browsing by filters · <strong><?= number_format($total_results) ?></strong> matching films.</div>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($api_key_missing): ?>
        <div class="alert alert-error">
            <strong>TMDb API key not set.</strong>
            Edit <code>includes/config.php</code> and add your key from
            <a href="https://www.themoviedb.org/settings/api" target="_blank" rel="noopener">themoviedb.org</a>.
        </div>
    <?php elseif (empty($results) && tmdb_last_error()): ?>
        <div class="alert alert-error">
            <strong>TMDb request failed.</strong>
            <code style="display:block; margin-top:0.5rem; font-size:0.8rem; word-break:break-all;">
                <?= e(tmdb_last_error()) ?>
            </code>
        </div>
    <?php else: ?>
        <h2 class="page-title" style="margin-top: 1.5rem; font-size: 1.6rem;">
            <?php if ($mode === 'search'): ?>
                Results for "<?= e($query) ?>"
            <?php elseif ($mode === 'discover'): ?>
                Browsing films
            <?php else: ?>
                Trending this week
            <?php endif; ?>
        </h2>
        <?php if ($mode === 'search'): ?>
            <p class="page-subtitle"><?= number_format($total_results) ?> films found</p>
        <?php elseif ($mode === 'trending'): ?>
            <p class="page-subtitle">Search above, or use the filters to narrow down.</p>
        <?php endif; ?>

        <?php if (empty($results)): ?>
            <p style="color: var(--muted);">No results.</p>
        <?php else: ?>
            <div class="movies-grid">
                <?php foreach ($results as $m): ?>
                    <a href="<?= e(BASE_URL) ?>/movie.php?id=<?= (int) $m['id'] ?>" class="movie-card">
                        <div class="movie-poster">
                            <img src="<?= e(tmdb_poster_url($m['poster_path'] ?? null)) ?>"
                                 alt="<?= e($m['title'] ?? '') ?>" loading="lazy">
                            <?php if (!empty($m['vote_average'])): ?>
                                <div class="movie-score"><?= number_format((float) $m['vote_average'], 1) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="movie-info">
                            <h3 class="movie-title"><?= e($m['title'] ?? '') ?></h3>
                            <div class="movie-year">
                                <?= !empty($m['release_date']) ? substr($m['release_date'], 0, 4) : '—' ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($mode !== 'trending' && $total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= e($qs_str) ?>&page=<?= $page - 1 ?>" class="btn-outline">← Previous</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <span style="color: var(--muted); font-size: 0.85rem;">
                        Page <?= $page ?> of <?= $total_pages ?>
                    </span>
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= e($qs_str) ?>&page=<?= $page + 1 ?>" class="btn-outline">Next →</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
