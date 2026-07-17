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
 *   - GET movie/{id}           canonical detail (+credits,videos via append_to_response)
 *   - GET configuration        image base url + sizes
 * Images: https://image.tmdb.org/t/p/{size}{poster_path}
 *
 * NOTHING here runs on a public page request — it is driven exclusively by the
 * Panel-authenticated API routes in index.php, and all responses are cached.
 */
class Client
{
    /**
     * mapToFilm() keys whose values are language-dependent on TMDB. The apply
     * route writes ONLY these into non-default content languages (everything
     * else — people, codes, numbers, files — is translate:false and lives in
     * the default language). Keys must match the Film blueprint's translatable
     * fields.
     */
    public const TRANSLATABLE = ['title', 'synopsis', 'genre'];

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

    /**
     * TMDB locale for a Kirby content language code. With no code given, the
     * CURRENT language is used — in Panel API requests Kirby sets it from the
     * Panel's x-language header, so searches follow the editor's language tab.
     * Unmapped codes and single-language mode fall back to the flat
     * kinemathek.tmdb.language option ('de-DE').
     */
    protected function language(?string $kirbyCode = null): string
    {
        $kirbyCode ??= $this->kirby->language()?->code();

        if ($kirbyCode !== null) {
            $map = option('kinemathek.tmdb.languages');
            if (is_array($map) === true && isset($map[$kirbyCode]) === true) {
                return (string) $map[$kirbyCode];
            }
        }

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

    protected function stillSize(): string
    {
        return (string) (option('kinemathek.tmdb.stillSize') ?? 'w1280');
    }

    protected function maxStills(): int
    {
        return (int) (option('kinemathek.tmdb.maxStills') ?? 4);
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

    protected function imageUrl(?string $imagePath, string $size): ?string
    {
        if (empty($imagePath) === true) {
            return null;
        }
        return $this->imageBaseUrl() . $size . $imagePath;
    }

    // ---- search (multi-candidate) ----------------------------------------

    /**
     * Search movies by title. Returns a normalized candidate list so the editor
     * can confirm the correct match (SPEC §4 — no auto-accept). Side effect:
     * remembers each candidate's poster path so the first-party thumb proxy can
     * download it on demand — the search response never blocks on image fetches.
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
            if ($id > 0) {
                $this->cache()->set(
                    'posterpath/' . $id,
                    (string) ($r['poster_path'] ?? ''),
                    self::TTL_MOVIE
                );
            }

            $candidates[] = [
                'id'             => $id,
                'title'          => (string) ($r['title'] ?? ''),
                'original_title' => (string) ($r['original_title'] ?? ''),
                'year'           => $this->yearFromDate($r['release_date'] ?? null),
                'overview'       => Str::short((string) ($r['overview'] ?? ''), 240),
                // The Panel renders this via a plain <img>, which cannot send
                // the x-csrf header — without the csrf query param Kirby's API
                // auth rejects every thumb request for session-authed users.
                'thumb'          => $id > 0
                    ? $this->kirby->url('api') . '/kinemathek/tmdb/thumb/' . $id
                        . (($csrf = $this->csrfToken()) !== null ? '?csrf=' . urlencode($csrf) : '')
                    : null,
            ];
        }

        return $candidates;
    }

    // ---- movie detail bundle (movie + credits) ---------------------------

    /**
     * Movie detail bundle, localized for the given Kirby language code
     * (null = current language / configured fallback). Cached per TMDB locale,
     * so two Kirby languages mapping to the same TMDB locale share one entry.
     */
    public function movie(int $id, ?string $kirbyLang = null): array
    {
        $lang = $this->language($kirbyLang);
        return $this->get('movie/' . $id, [
            'language'           => $lang,
            'append_to_response' => 'credits,videos',
            // Videos are language-filtered like images: without this, a de-DE
            // request hides the (far more common) English-tagged trailers.
            'include_video_language' => implode(',', array_unique([
                substr($lang, 0, 2), 'de', 'en', 'null',
            ])),
            // 'v2': the pre-trailer cache entries lack the videos payload and
            // live for 30 days — a new key prevents serving them.
        ], 'movie/v2/' . $lang . '/' . $id, self::TTL_MOVIE);
    }

    // ---- trailer (YouTube watch URL from the videos payload) ---------------

    /**
     * Best trailer as a plain watch URL (YouTube or Vimeo — the two sites
     * TMDB hosts video keys for), or null. Preference: Trailer over Teaser,
     * official over fan uploads, German over English over rest — trailerUrl
     * is translate:false, so only the default-language (German) mapping is
     * ever stored. The public film template renders this as a plain external
     * link (a navigation like the ticket links — never an embed, SPEC §7).
     */
    public function trailerUrl(array $bundle): ?string
    {
        // key shape + watch-URL builder per supported TMDB video site
        $sites = [
            'YouTube' => [
                'pattern' => '/^[A-Za-z0-9_-]{5,20}$/',
                'url'     => fn (string $key) => 'https://www.youtube.com/watch?v=' . $key,
            ],
            'Vimeo' => [
                'pattern' => '/^[0-9]{5,15}$/',
                'url'     => fn (string $key) => 'https://vimeo.com/' . $key,
            ],
        ];

        $best  = null;
        $score = -1;

        foreach ($bundle['videos']['results'] ?? [] as $video) {
            $site = $sites[$video['site'] ?? ''] ?? null;
            if ($site === null) {
                continue;
            }
            $key = (string) ($video['key'] ?? '');
            if (preg_match($site['pattern'], $key) !== 1) {
                continue;
            }
            $type = (string) ($video['type'] ?? '');
            if ($type !== 'Trailer' && $type !== 'Teaser') {
                continue;
            }

            $s = ($type === 'Trailer' ? 8 : 0)
                + (($video['official'] ?? false) === true ? 4 : 0)
                + (match ($video['iso_639_1'] ?? '') {
                    'de'    => 2,
                    'en'    => 1,
                    default => 0,
                });

            if ($s > $score) {
                $score = $s;
                $best  = $site['url']($key);
            }
        }

        return $best;
    }

    // ---- movie images (backdrops -> Szenenbilder) -------------------------

    /**
     * Cached images payload for a movie. Requested WITHOUT the site language
     * as the primary filter: backdrops carrying baked-in text are tagged with
     * a language, textless artwork has iso_639_1 = null — and textless is what
     * we want for Szenenbilder, with de/en as fallback.
     */
    public function images(int $id): array
    {
        return $this->get('movie/' . $id . '/images', [
            'include_image_language' => 'null,de,en',
        ], 'images/' . $id, self::TTL_MOVIE);
    }

    /** Best backdrop file paths: textless first, TMDB's vote order preserved. */
    protected function backdropPaths(array $images, int $max): array
    {
        if ($max <= 0) {
            return [];
        }

        $textless = [];
        $titled   = [];
        foreach ($images['backdrops'] ?? [] as $b) {
            $path = $b['file_path'] ?? null;
            if (is_string($path) === false || $path === '') {
                continue;
            }
            if (($b['iso_639_1'] ?? null) === null) {
                $textless[] = $path;
            } else {
                $titled[] = $path;
            }
        }

        return array_slice(array_merge($textless, $titled), 0, $max);
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
            'trailerUrl'    => $this->trailerUrl($b),
            // Source URL only; the caller downloads this into a real file.
            'poster'        => $this->imageUrl($b['poster_path'] ?? null, $this->posterSize()),
        ];
    }

    // ---- server-side image download -> real Kirby files ------------------

    /**
     * Download one TMDB image server-side and attach it to the page under a
     * deterministic filename — the browser never contacts image.tmdb.org
     * (SPEC §7). Re-running is safe: a byte-identical existing file is kept
     * as-is (content + focus point survive); a same-named file with different
     * bytes (TMDB swapped the artwork, size option changed) goes through
     * File::replace(), which keeps uuid/content/refs and leaves the old file
     * untouched if it throws — createFile would DuplicateException here, and
     * delete-then-create would lose the old artwork on a failed create.
     * A failed download (non-200 or curl-level error) returns the existing
     * same-named file when there is one — a transient CDN error must never
     * cost a file that is already on the page — and null otherwise.
     */
    protected function attachImage(
        Page $page,
        string $url,
        string $filename,
        string $template,
        array $content
    ): ?string {
        $existing = $page->file($filename);

        try {
            $response = Remote::get($url, [
                'timeout' => 10,
                'agent'   => 'Kinemathek-Kirby/1.0 (+server-side cache)',
            ]);
            if ($response->code() !== 200) {
                return $existing?->filename();
            }
        } catch (\Throwable $e) {
            return $existing?->filename();
        }

        $tmpDir = $this->kirby->root('cache') . '/kinemathek-tmdb/tmp';
        Dir::make($tmpDir);
        $tmpFile = $tmpDir . '/' . uniqid('', true) . '-' . $filename;
        F::write($tmpFile, $response->content());

        try {
            if ($existing !== null) {
                if ($existing->sha1() === sha1_file($tmpFile)) {
                    return $existing->filename();
                }
                return $existing->replace($tmpFile)->filename();
            }

            $file = $page->createFile([
                'source'   => $tmpFile,
                'filename' => $filename,
                'template' => $template,
                'content'  => $content,
            ]);

            return $file->filename();
        } finally {
            if (is_file($tmpFile) === true) {
                F::remove($tmpFile);
            }
        }
    }

    /**
     * Download the chosen movie's poster and attach it as a first-party file.
     * $title is the freshly applied title (don't read $page->title(): it falls
     * back to the slug and the page object may predate the field update).
     */
    public function attachPoster(Page $page, array $bundle, ?string $title = null): ?string
    {
        $url = $this->imageUrl($bundle['poster_path'] ?? null, $this->posterSize());
        if ($url === null) {
            return null;
        }

        $tmdbId = (int) ($bundle['id'] ?? 0);
        $ext    = $this->extensionFromUrl($url);
        $title  = $title ?? (string) $page->content()->get('title')?->value();

        return $this->attachImage($page, $url, 'tmdb-poster-' . $tmdbId . '.' . $ext, 'poster', [
            'alt'    => trim($title . ' (Poster)'),
            'source' => 'TMDB',
        ]);
    }

    /**
     * Download the best backdrops as Szenenbilder (SPEC §2.1: poster/stills
     * from TMDB). Filenames are keyed on the TMDB image id, so re-runs replace
     * or reuse instead of stacking duplicates. One broken download must not
     * abort the batch, so each still is attached in its own try/catch.
     *
     * Returns ['attempted' => int, 'stored' => string[]] — attempted=0 means
     * TMDB has no backdrops at all, which callers must treat differently from
     * "downloads failed" (attempted > 0, stored empty).
     */
    public function attachStills(Page $page, array $bundle, ?string $title = null): array
    {
        $tmdbId = (int) ($bundle['id'] ?? 0);
        if ($tmdbId <= 0) {
            return ['attempted' => 0, 'stored' => []];
        }

        $paths = $this->backdropPaths($this->images($tmdbId), $this->maxStills());
        $title = $title ?? (string) $page->content()->get('title')?->value();
        $size  = $this->stillSize();

        $stored = [];
        foreach ($paths as $path) {
            $url = $this->imageUrl($path, $size);
            if ($url === null) {
                continue;
            }

            $key  = Str::slug(pathinfo($path, PATHINFO_FILENAME));
            $name = 'tmdb-still-' . $tmdbId . '-' . $key . '.' . $this->extensionFromUrl($url);

            try {
                $filename = $this->attachImage($page, $url, $name, 'still', [
                    'alt'    => trim($title . ' (Szenenbild)'),
                    'source' => 'TMDB',
                ]);
            } catch (\Throwable $e) {
                $filename = null; // this still failed; keep going with the rest
            }

            if ($filename !== null) {
                $stored[] = $filename;
            }
        }

        return ['attempted' => count($paths), 'stored' => $stored];
    }

    /**
     * Remove previously synced TMDB files of one kind ('poster'|'still'),
     * keeping $except (freshly attached filenames). Manual uploads are never
     * touched — only files matching the plugin's own tmdb-* naming go.
     */
    public function removeTmdbImages(Page $page, string $type, array $except = []): void
    {
        $prefix = 'tmdb-' . $type . '-';
        foreach ($page->files()->template($type) as $file) {
            if (
                Str::startsWith($file->filename(), $prefix) === true &&
                in_array($file->filename(), $except, true) === false
            ) {
                $file->delete();
            }
        }
    }

    protected function extensionFromUrl(string $url): string
    {
        return pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
    }

    /**
     * CSRF token to embed in thumb URLs. Prefers the incoming request's
     * (already validated) token — the Panel always sends x-csrf — then the
     * session token (covers panel.dev / api.csrf setups). Null when neither
     * exists (basic auth, CLI), where Kirby skips the csrf check anyway.
     */
    protected function csrfToken(): ?string
    {
        $token = $this->kirby->request()->csrf();
        if (is_string($token) === true && $token !== '') {
            return $token;
        }

        try {
            return $this->kirby->auth()->csrfFromSession();
        } catch (\Throwable $e) {
            return null;
        }
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

    /**
     * Disk path of the preview thumbnail, downloading it server-side on first
     * demand (called by the proxy route per request, so a failed download
     * self-heals on the next view instead of sticking as a placeholder).
     * Returns null when the movie has no poster or the download fails.
     */
    public function ensureThumb(int $tmdbId): ?string
    {
        if ($tmdbId <= 0) {
            return null;
        }
        if ($path = $this->thumbPath($tmdbId)) {
            return $path;
        }

        try {
            // Poster path remembered at search time; fall back to the (cached)
            // detail call for thumbs requested after the search cache expired.
            $posterPath = $this->cache()->get('posterpath/' . $tmdbId);
            if ($posterPath === null) {
                $posterPath = (string) ($this->movie($tmdbId)['poster_path'] ?? '');
            }

            $url = $this->imageUrl($posterPath ?: null, $this->thumbSize());
            if ($url === null) {
                return null;
            }

            $response = Remote::get($url, [
                'timeout' => 6,
                'agent'   => 'Kinemathek-Kirby/1.0 (+server-side cache)',
            ]);
            if ($response->code() !== 200) {
                return null;
            }

            $file = $this->thumbDir() . '/' . $tmdbId . '.' . $this->extensionFromUrl($url);
            Dir::make($this->thumbDir());
            F::write($file, $response->content());
            return $file;
        } catch (\Throwable $e) {
            return null; // preview falls back to the placeholder
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
            // Localized via the language files; German wording is the fallback.
            'text' => t(
                'kinemathek.tmdb.attribution',
                'Dieses Produkt nutzt die TMDB-API, ist aber nicht von TMDB '
                . 'unterstützt oder zertifiziert.'
            ),
            'url'  => 'https://www.themoviedb.org/',
            // Per TMDB branding a self-hosted logo should accompany this text
            // before launch: add it under /assets and render it via url() — never
            // hotlink from themoviedb.org (SPEC §7). Omitted until the asset exists.
        ];
    }
}
