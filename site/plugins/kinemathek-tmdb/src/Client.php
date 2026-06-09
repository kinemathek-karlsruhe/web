<?php

namespace Kinemathek\Tmdb;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Http\Remote;
use Kirby\Toolkit\Str;

/**
 * Server-side TMDB v3 client for the Kinemathek backend (SPEC §4, §7).
 *
 * Endpoints used (TMDB API v3, https://api.themoviedb.org/3):
 *   - GET search/movie         multi-candidate title search
 *   - GET movie/{id}           canonical detail (+credits via append_to_response)
 *   - GET configuration        image base url + sizes
 * Images: https://image.tmdb.org/t/p/{size}{poster_path}
 *
 * NOTHING here runs on a public page request — it is driven exclusively by the
 * Panel-authenticated API routes in index.php, and all responses are cached.
 */
class Client
{
    protected const API_BASE = 'https://api.themoviedb.org/3/';
    protected const IMG_BASE = 'https://image.tmdb.org/t/p/';

    // Cache TTLs in MINUTES.
    protected const TTL_SEARCH = 1440;   // 1 day  — volatile / ambiguous
    protected const TTL_MOVIE  = 43200;  // 30 days — canonical, rarely changes
    protected const TTL_CONFIG = 10080;  // 7 days  — TMDB image config

    protected App $kirby;

    public function __construct(?App $kirby = null)
    {
        $this->kirby = $kirby ?? App::instance();
    }

    // ---- config / auth ---------------------------------------------------

    protected function language(): string
    {
        return (string) (option('kinemathek.tmdb.language') ?? 'de-DE');
    }

    protected function posterSize(): string
    {
        return (string) (option('kinemathek.tmdb.posterSize') ?? 'w780');
    }

    protected function thumbSize(): string
    {
        return (string) (option('kinemathek.tmdb.thumbSize') ?? 'w185');
    }

    protected function maxResults(): int
    {
        return (int) (option('kinemathek.tmdb.maxResults') ?? 8);
    }

    /** Kirby namespaced cache for this plugin (enabled via plugin option). */
    protected function cache()
    {
        return $this->kirby->cache('kinemathek/tmdb');
    }

    /**
     * Auth params/headers. Supports v3 api_key (query) OR v4 bearer token
     * (header). At least one must be configured in site/config/config.php.
     */
    protected function auth(): array
    {
        $key   = option('kinemathek.tmdb.key');
        $token = option('kinemathek.tmdb.token');

        if (empty($key) === true && empty($token) === true) {
            throw new \RuntimeException(
                'TMDB-Zugangsdaten fehlen. kinemathek.tmdb.key (v3 API key) oder '
                . 'kinemathek.tmdb.token (v4 bearer) in site/config/config.php setzen.'
            );
        }

        $params  = [];
        $headers = ['Accept' => 'application/json'];

        if (empty($token) === false) {
            $headers['Authorization'] = 'Bearer ' . $token;
        } else {
            $params['api_key'] = $key;
        }

        return [$params, $headers];
    }

    // ---- low-level cached request ----------------------------------------

    /** Cached GET against the TMDB API. Returns decoded array; throws on error. */
    protected function get(string $path, array $query, string $cacheKey, int $ttl): array
    {
        $cache  = $this->cache();
        $cached = $cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        [$authParams, $headers] = $this->auth();

        $response = Remote::get(self::API_BASE . ltrim($path, '/'), [
            'data'    => array_merge($authParams, $query),
            'headers' => $this->normalizeHeaders($headers),
            'timeout' => 8,
            'agent'   => 'Kinemathek-Kirby/1.0 (+server-side cache)',
        ]);

        $code = $response->code();
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException('TMDB request failed (' . $code . ') for ' . $path);
        }

        $data = $response->json();
        if (is_array($data) === false) {
            throw new \RuntimeException('TMDB returned a non-JSON response.');
        }

        $cache->set($cacheKey, $data, $ttl);
        return $data;
    }

    /** Remote expects headers as a numerically-indexed list of "Key: Value". */
    protected function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $out[] = $k . ': ' . $v;
        }
        return $out;
    }

    // ---- image configuration ---------------------------------------------

    protected function imageBaseUrl(): string
    {
        try {
            $config = $this->get('configuration', [], 'config', self::TTL_CONFIG);
            $base   = $config['images']['secure_base_url'] ?? null;
            if (is_string($base) === true && $base !== '') {
                return rtrim($base, '/') . '/';
            }
        } catch (\Throwable $e) {
            // fall through to the static base url
        }
        return self::IMG_BASE;
    }

    protected function posterUrl(?string $posterPath, string $size): ?string
    {
        if (empty($posterPath) === true) {
            return null;
        }
        return $this->imageBaseUrl() . $size . $posterPath;
    }

    // ---- search (multi-candidate) ----------------------------------------

    /**
     * Search movies by title. Returns a normalized candidate list so the editor
     * can confirm the correct match (SPEC §4 — no auto-accept). Side effect:
     * caches a small preview thumbnail per candidate for the first-party proxy.
     */
    public function search(string $query, int $page = 1): array
    {
        $lang     = $this->language();
        $cacheKey = 'search/' . $lang . '/' . sha1($query . '|' . $page);

        $data = $this->get('search/movie', [
            'query'         => $query,
            'language'      => $lang,
            'include_adult' => 'false',
            'page'          => $page,
        ], $cacheKey, self::TTL_SEARCH);

        $candidates = [];
        foreach (array_slice($data['results'] ?? [], 0, $this->maxResults()) as $r) {
            $id = (int) ($r['id'] ?? 0);
            $this->cacheThumb($id, $r['poster_path'] ?? null);

            $candidates[] = [
                'id'             => $id,
                'title'          => (string) ($r['title'] ?? ''),
                'original_title' => (string) ($r['original_title'] ?? ''),
                'year'           => $this->yearFromDate($r['release_date'] ?? null),
                'overview'       => Str::short((string) ($r['overview'] ?? ''), 240),
                'thumb'          => $id > 0
                    ? $this->kirby->url('api') . '/kinemathek/tmdb/thumb/' . $id
                    : null,
            ];
        }

        return $candidates;
    }

    // ---- movie detail bundle (movie + credits) ---------------------------

    public function movie(int $id): array
    {
        $lang = $this->language();
        return $this->get('movie/' . $id, [
            'language'           => $lang,
            'append_to_response' => 'credits',
        ], 'movie/' . $lang . '/' . $id, self::TTL_MOVIE);
    }

    // ---- map a TMDB bundle to Film fields --------------------------------

    /**
     * Map a movie+credits bundle to the Film blueprint fields.
     *
     * Field keys MUST match the Film blueprint (SPEC §2.1): genre (singular,
     * tags), language (the descriptive facet), structured directors/cast with a
     * tmdbpersonid column (SPEC §5 — deferred director search). 'poster' is the
     * source URL only; the caller turns it into a real, local file.
     */
    public function mapToFilm(array $b): array
    {
        $credits = $b['credits'] ?? [];

        // Directors — structured rows (name + tmdbpersonid).
        $directors = [];
        foreach ($credits['crew'] ?? [] as $person) {
            if (($person['job'] ?? null) === 'Director') {
                $directors[] = [
                    'name'         => (string) ($person['name'] ?? ''),
                    'tmdbpersonid' => (int) ($person['id'] ?? 0),
                ];
            }
        }

        // Cast — structured rows (name + role + tmdbpersonid), top 10.
        $cast = [];
        foreach (array_slice($credits['cast'] ?? [], 0, 10) as $person) {
            $name = (string) ($person['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $cast[] = [
                'name'         => $name,
                'role'         => (string) ($person['character'] ?? ''),
                'tmdbpersonid' => (int) ($person['id'] ?? 0),
            ];
        }

        // Genres -> descriptive tags facet.
        $genre = [];
        foreach ($b['genres'] ?? [] as $g) {
            $name = (string) ($g['name'] ?? '');
            if ($name !== '') {
                $genre[] = $name;
            }
        }

        // Countries -> tags facet (co-productions can have several).
        $country = [];
        foreach ($b['production_countries'] ?? [] as $c) {
            $code = (string) ($c['iso_3166_1'] ?? '');
            if ($code !== '') {
                $country[] = $code;
            }
        }

        return [
            'title'         => (string) ($b['title'] ?? ''),
            'originalTitle' => (string) ($b['original_title'] ?? ''),
            'year'          => $this->yearFromDate($b['release_date'] ?? null),
            'runtime'       => (int) ($b['runtime'] ?? 0),
            'language'      => (string) ($b['original_language'] ?? ''),
            'country'       => $country,
            'synopsis'      => (string) ($b['overview'] ?? ''),
            'genre'         => $genre,
            'cast'          => $cast,
            'directors'     => $directors,
            'tmdbId'        => (int) ($b['id'] ?? 0),
            // Source URL only; the caller downloads this into a real file.
            'poster'        => $this->posterUrl($b['poster_path'] ?? null, $this->posterSize()),
        ];
    }

    // ---- server-side poster download -> real Kirby file ------------------

    /**
     * Download the chosen movie's poster server-side and attach it to the Film
     * page as a genuine first-party file. Returns the stored filename or null —
     * the browser never contacts image.tmdb.org (SPEC §7).
     */
    public function attachPoster(Page $page, array $bundle): ?string
    {
        $url = $this->posterUrl($bundle['poster_path'] ?? null, $this->posterSize());
        if ($url === null) {
            return null;
        }

        $response = Remote::get($url, [
            'timeout' => 10,
            'agent'   => 'Kinemathek-Kirby/1.0 (+server-side cache)',
        ]);
        if ($response->code() !== 200) {
            return null;
        }

        $tmdbId   = (int) ($bundle['id'] ?? 0);
        $ext      = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'tmdb-poster-' . $tmdbId . '.' . $ext;
        $tmpDir   = $this->kirby->root('cache') . '/kinemathek-tmdb/tmp';
        Dir::make($tmpDir);
        $tmpFile  = $tmpDir . '/' . $filename;
        F::write($tmpFile, $response->content());

        try {
            $file = $page->createFile([
                'source'   => $tmpFile,
                'filename' => $filename,
                'template' => 'poster',
                'content'  => [
                    'alt'    => $page->title()->value() . ' (Poster)',
                    'source' => 'TMDB',
                ],
            ]);
        } finally {
            F::remove($tmpFile);
        }

        return $file?->filename();
    }

    // ---- search-preview thumbnails (server-side cache, first-party proxy)--

    protected function thumbDir(): string
    {
        return $this->kirby->root('cache') . '/kinemathek-tmdb/thumbs';
    }

    /** Absolute path to a cached preview thumbnail for a TMDB id, or null. */
    public function thumbPath(int $tmdbId): ?string
    {
        if ($tmdbId <= 0) {
            return null;
        }
        foreach (['jpg', 'png', 'webp'] as $ext) {
            $candidate = $this->thumbDir() . '/' . $tmdbId . '.' . $ext;
            if (F::exists($candidate) === true) {
                return $candidate;
            }
        }
        return null;
    }

    /** Best-effort: download + store a small preview thumbnail server-side. */
    protected function cacheThumb(int $tmdbId, ?string $posterPath): void
    {
        if ($tmdbId <= 0 || empty($posterPath) === true || $this->thumbPath($tmdbId) !== null) {
            return;
        }

        $url = $this->posterUrl($posterPath, $this->thumbSize());
        if ($url === null) {
            return;
        }

        try {
            $response = Remote::get($url, [
                'timeout' => 6,
                'agent'   => 'Kinemathek-Kirby/1.0 (+server-side cache)',
            ]);
            if ($response->code() !== 200) {
                return;
            }
            $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            Dir::make($this->thumbDir());
            F::write($this->thumbDir() . '/' . $tmdbId . '.' . $ext, $response->content());
        } catch (\Throwable $e) {
            // Non-fatal: preview falls back to the placeholder.
        }
    }

    // ---- helpers ----------------------------------------------------------

    protected function yearFromDate(?string $date): ?int
    {
        if (empty($date) === true) {
            return null;
        }
        $year = (int) substr($date, 0, 4);
        return $year > 0 ? $year : null;
    }

    /**
     * Required TMDB attribution (SPEC §4 + §7). Rendered as plain text + a
     * navigation link. Performs no network access.
     */
    public static function attribution(): array
    {
        return [
            'text' => 'Dieses Produkt nutzt die TMDB-API, ist aber nicht von TMDB '
                . 'unterstützt oder zertifiziert.',
            'url'  => 'https://www.themoviedb.org/',
            // Per TMDB branding a self-hosted logo should accompany this text
            // before launch: add it under /assets and render it via url() — never
            // hotlink from themoviedb.org (SPEC §7). Omitted until the asset exists.
        ];
    }
}
