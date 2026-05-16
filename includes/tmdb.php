<?php
require_once __DIR__ . '/config.php';

function tmdb_last_error(): ?string {
    return $GLOBALS['_tmdb_last_error'] ?? null;
}

/**
 * Resolve a hostname via Cloudflare DNS-over-HTTPS.
 * Needed because some ISPs (notably in Albania) block themoviedb.org at the DNS level.
 * Cached in the system temp dir for 5 minutes.
 */
function tmdb_resolve_host(string $host): array {
    $cache_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'filmbox_dns_' . md5($host) . '.json';
    if (is_file($cache_file) && (time() - filemtime($cache_file)) < 300) {
        $cached = json_decode((string) file_get_contents($cache_file), true);
        if (!empty($cached) && is_array($cached)) return $cached;
    }

    $ch = curl_init("https://1.1.1.1/dns-query?name=" . urlencode($host) . "&type=A");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => ['Accept: application/dns-json'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if (!$body) return [];

    $data = json_decode($body, true);
    $ips  = array_values(array_filter(
        array_column($data['Answer'] ?? [], 'data'),
        fn($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
    ));

    if (!empty($ips)) {
        @file_put_contents($cache_file, json_encode($ips));
    }
    return $ips;
}

function tmdb_request(string $endpoint, array $params = []): ?array {
    $GLOBALS['_tmdb_last_error'] = null;

    $url     = TMDB_BASE_URL . $endpoint;
    $headers = ['Accept: application/json'];

    // v4 Read Access Tokens are JWTs (start with "eyJ"). v3 keys are 32-char hex.
    $is_v4 = str_starts_with(TMDB_API_KEY, 'eyJ');
    if ($is_v4) {
        $headers[] = 'Authorization: Bearer ' . TMDB_API_KEY;
    } else {
        $params['api_key'] = TMDB_API_KEY;
    }

    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    // Pre-resolve via DoH to bypass blocked local DNS.
    $parsed = parse_url($url);
    $host   = $parsed['host'] ?? '';
    $port   = ($parsed['scheme'] ?? 'https') === 'https' ? 443 : 80;
    $ips    = $host ? tmdb_resolve_host($host) : [];

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    if (!empty($ips)) {
        $opts[CURLOPT_RESOLVE] = array_map(fn($ip) => "{$host}:{$port}:{$ip}", $ips);
    }
    curl_setopt_array($ch, $opts);

    $response  = curl_exec($ch);
    $curl_err  = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        $GLOBALS['_tmdb_last_error'] = "cURL: {$curl_err}";
        return null;
    }
    if ($http_code !== 200) {
        $GLOBALS['_tmdb_last_error'] =
            "HTTP {$http_code} from TMDb. Body: " . substr((string) $response, 0, 300);
        return null;
    }

    return json_decode($response, true);
}

function tmdb_search_movies(string $query, int $page = 1): array {
    $result = tmdb_request('/search/movie', [
        'query'         => $query,
        'page'          => $page,
        'include_adult' => 'false',
    ]);
    return $result ?? ['results' => [], 'total_pages' => 0, 'total_results' => 0];
}

function tmdb_movie_details(int $movie_id): ?array {
    return tmdb_request("/movie/{$movie_id}", [
        'append_to_response' => 'videos,credits,similar,watch/providers,reviews',
    ]);
}

function tmdb_trending(string $time_window = 'week'): array {
    $result = tmdb_request("/trending/movie/{$time_window}");
    return $result['results'] ?? [];
}

function tmdb_popular(int $page = 1): array {
    $result = tmdb_request('/movie/popular', ['page' => $page]);
    return $result['results'] ?? [];
}

function tmdb_top_rated(int $page = 1): array {
    $result = tmdb_request('/movie/top_rated', ['page' => $page]);
    return $result['results'] ?? [];
}

function tmdb_now_playing(int $page = 1): array {
    $result = tmdb_request('/movie/now_playing', ['page' => $page]);
    return $result['results'] ?? [];
}

function tmdb_upcoming(int $page = 1): array {
    $result = tmdb_request('/movie/upcoming', ['page' => $page]);
    return $result['results'] ?? [];
}

function tmdb_movie_videos(int $id): array {
    $result = tmdb_request("/movie/{$id}/videos");
    return $result['results'] ?? [];
}

function tmdb_movie_recommendations(int $id, int $page = 1): array {
    $result = tmdb_request("/movie/{$id}/recommendations", ['page' => $page]);
    return $result['results'] ?? [];
}

/**
 * Filter-based discovery. Pass any subset of:
 *   with_genres (comma-separated IDs), primary_release_year,
 *   vote_average.gte, sort_by, page, with_runtime.gte, etc.
 */
function tmdb_discover_movies(array $params = []): array {
    $defaults = ['sort_by' => 'popularity.desc', 'include_adult' => 'false'];
    $result   = tmdb_request('/discover/movie', array_merge($defaults, $params));
    return $result ?? ['results' => [], 'total_pages' => 0, 'total_results' => 0];
}

function tmdb_genres(): array {
    $result = tmdb_request('/genre/movie/list');
    return $result['genres'] ?? [];
}

function tmdb_poster_url(?string $path, string $size = 'w500'): string {
    if (!$path) {
        return 'https://via.placeholder.com/500x750/1a1a1a/666666?text=No+Poster';
    }
    return TMDB_IMG_URL . '/' . $size . $path;
}

function tmdb_backdrop_url(?string $path, string $size = 'original'): string {
    return $path ? TMDB_IMG_URL . '/' . $size . $path : '';
}
