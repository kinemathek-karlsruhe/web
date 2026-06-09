<?php

use Kinemathek\Tmdb\Client;
use Kirby\Cms\App;
use Kirby\Filesystem\F;
use Kirby\Http\Response;

/**
 * Kinemathek TMDB integration (SPEC §4, §7).
 *
 * Privacy boundary: the only code that ever performs an HTTP request to TMDB
 * lives in Kinemathek\Tmdb\Client, and the only callers are the Panel API
 * routes below, which Kirby protects behind the authentication layer. The
 * PUBLIC site never triggers a live TMDB call — the film template reads stored
 * fields + the locally stored poster file only. Responses are cached server-side.
 */

load([
    'Kinemathek\\Tmdb\\Client' => __DIR__ . '/src/Client.php',
]);

App::plugin('kinemathek/tmdb', [

    // NB: do NOT register 'options' => ['cache' => true] here. That stores a flat
    // `kinemathek.tmdb` option key which SHADOWS the nested credentials in
    // config.php (kinemathek.tmdb.key/token would resolve to null). The TMDB
    // cache is enabled in site/config/config.php via 'cache' => ['kinemathek/tmdb' => true].

    // Required TMDB attribution (SPEC §4) — plain text + navigation link, no
    // network access. Rendered on the public film template.
    'siteMethods' => [
        'tmdbAttribution' => fn (): array => Client::attribution(),
    ],

    // --------------------------------------------------------------------
    // Panel API routes (auto-protected by Kirby auth). Editor-only.
    // Reachable under /api/kinemathek/tmdb/*
    // --------------------------------------------------------------------
    'api' => [
        'routes' => [
            [
                // Title search — returns MULTIPLE candidates so the editor can
                // confirm the correct match (SPEC §4: no auto-accept).
                'pattern' => 'kinemathek/tmdb/search',
                'method'  => 'GET',
                'action'  => function () {
                    $kirby = App::instance();
                    $query = trim((string) $kirby->request()->get('query', ''));
                    $page  = (int) $kirby->request()->get('page', 1);

                    if ($query === '') {
                        return ['status' => 'ok', 'query' => '', 'candidates' => []];
                    }

                    try {
                        $candidates = (new Client())->search($query, max(1, $page));
                    } catch (\Throwable $e) {
                        return ['status' => 'error', 'message' => $e->getMessage()];
                    }

                    return ['status' => 'ok', 'query' => $query, 'candidates' => $candidates];
                },
            ],
            [
                // Apply a chosen TMDB movie to a Film page. Respects the page's
                // manualOverride flag (SPEC §2.1/§4): when locked, only empty
                // fields are filled and overwrite is refused.
                'pattern' => 'kinemathek/tmdb/apply',
                'method'  => 'POST',
                'action'  => function () {
                    $kirby   = App::instance();
                    $request = $kirby->request();

                    $tmdbId = (int) $request->get('id');
                    $pageId = (string) $request->get('page');
                    $mode   = (string) $request->get('mode', 'fill-empty');
                    $only   = $request->get('fields'); // null => all mappable

                    if ($tmdbId <= 0 || $pageId === '') {
                        return ['status' => 'error', 'message' => 'Missing TMDB id or page id.'];
                    }

                    $page = $kirby->page($pageId);
                    if ($page === null) {
                        return ['status' => 'error', 'message' => 'Film page not found: ' . $pageId];
                    }

                    // Authorize the REAL caller against THIS page before any
                    // privileged work. The default API auth only requires panel
                    // access; without this, a restricted editor could overwrite
                    // any Film via the impersonate('kirby') block below.
                    $user = $kirby->user();
                    if ($user === null || $page->permissions()->can('update') === false) {
                        return ['status' => 'error', 'code' => 403, 'message' => 'Keine Berechtigung für diese Seite.'];
                    }
                    $canCreateFile = $page->permissions()->can('createFile');

                    $override = $page->manualOverride()->toBool();
                    if ($override === true && $mode === 'overwrite') {
                        return [
                            'status'  => 'locked',
                            'message' => 'Dieser Film ist als manuell kuratiert markiert. '
                                . 'Überschreiben ist gesperrt; nur leere Felder werden gefüllt.',
                        ];
                    }
                    if ($override === true) {
                        $mode = 'fill-empty'; // force fill-empty regardless of request
                    }

                    try {
                        $client = new Client();
                        $bundle = $client->movie($tmdbId);
                        $values = $client->mapToFilm($bundle);
                    } catch (\Throwable $e) {
                        return ['status' => 'error', 'message' => $e->getMessage()];
                    }

                    if (is_array($only) === true && $only !== []) {
                        $values = array_intersect_key($values, array_flip($only));
                    }

                    $wantsPoster = $only === null
                        || (is_array($only) && in_array('poster', $only, true));

                    // Never clobber existing editorial content in fill-empty mode.
                    $update = [];
                    foreach ($values as $key => $value) {
                        if ($key === 'poster') {
                            continue; // poster is a file, handled below
                        }
                        $existing = $page->content()->get($key);
                        $isEmpty  = $existing === null || $existing->isEmpty();
                        if ($mode === 'fill-empty' && $isEmpty === false) {
                            continue;
                        }
                        $update[$key] = $value;
                    }

                    $kirby->impersonate('kirby', function () use (
                        $page, $update, $client, $bundle, $wantsPoster, $mode, $canCreateFile
                    ) {
                        if ($update !== []) {
                            $page->update($update);
                        }

                        if ($wantsPoster === true && $canCreateFile === true) {
                            $posterEmpty = $page->poster()->isEmpty();
                            if ($mode !== 'fill-empty' || $posterEmpty === true) {
                                $filename = $client->attachPoster($page, $bundle);
                                if ($filename !== null) {
                                    $page->update(['poster' => $filename]);
                                }
                            }
                        }
                    });

                    return [
                        'status'  => 'ok',
                        'applied' => array_keys($update),
                        'mode'    => $mode,
                        'locked'  => $override,
                    ];
                },
            ],
            [
                // First-party, auth-protected proxy for a search-preview
                // thumbnail. The image is downloaded + cached server-side at
                // search time, then streamed through here — the browser never
                // contacts image.tmdb.org, even during preview (SPEC §7).
                'pattern' => 'kinemathek/tmdb/thumb/(:num)',
                'method'  => 'GET',
                'action'  => function (int $tmdbId) {
                    $path = (new Client())->thumbPath($tmdbId);

                    if ($path === null || F::exists($path) === false) {
                        // 1x1 transparent gif fallback — no upstream call.
                        return new Response(
                            base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),
                            'image/gif'
                        );
                    }

                    return new Response(F::read($path), F::mime($path) ?? 'image/jpeg');
                },
            ],
        ],
    ],

    // Custom Panel field. The Vue component (index.js) is a thin UI calling the
    // auth-protected API routes above; the privacy + caching logic is all PHP.
    'fields' => [
        'tmdblookup' => [
            'props' => [
                'titleField' => fn (string $titleField = 'title') => $titleField,
            ],
        ],
    ],
]);
