<?php

use Kinemathek\Tmdb\Client;
use Kirby\Cms\App;
use Kirby\Filesystem\F;
use Kirby\Http\Response;
use Kirby\Toolkit\Str;

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
                // Multi-language: the default content language receives the
                // full mapped value set; every other language receives the
                // translatable fields (Client::TRANSLATABLE), fetched from
                // TMDB in that language. fill-empty is evaluated per language.
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

                    // File create/delete are FILE permissions ('files' category),
                    // NOT page actions: $page->permissions()->can('createFile')
                    // is an unknown action and silently returns false for every
                    // real user — only the almighty 'kirby' user short-circuits
                    // to true, which is why CLI verification passed while the
                    // Panel never attached a single image.
                    $rolePermissions = $user->role()->permissions();
                    $canCreateFile   = $rolePermissions->for('files', 'create');
                    $canDeleteFile   = $rolePermissions->for('files', 'delete');

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

                    // Content languages to sync. The default language comes
                    // first and receives the FULL mapped value set; every other
                    // language only gets Client::TRANSLATABLE (title/synopsis/
                    // genre, fetched from TMDB in that language) — all other
                    // Film fields are translate:false and live in the default
                    // language only. Single-language mode syncs the pseudo-code
                    // 'default' (Language::ensure understands it in both modes).
                    $multilang   = $kirby->multilang();
                    $defaultCode = $multilang ? $kirby->defaultLanguage()->code() : 'default';
                    $codes       = [$defaultCode];
                    if ($multilang === true) {
                        foreach ($kirby->languages() as $language) {
                            if ($language->code() !== $defaultCode) {
                                $codes[] = $language->code();
                            }
                        }
                    }

                    try {
                        $client       = new Client();
                        $bundle       = null;
                        $valuesByLang = [];
                        foreach ($codes as $code) {
                            $b      = $client->movie($tmdbId, $code === 'default' ? null : $code);
                            $values = $client->mapToFilm($b);
                            if ($code === $defaultCode) {
                                // Poster/stills artwork is shared across
                                // languages and comes from the default bundle.
                                $bundle = $b;
                            } else {
                                $values = array_intersect_key(
                                    $values,
                                    array_flip(Client::TRANSLATABLE)
                                );
                            }
                            if (is_array($only) === true && $only !== []) {
                                $values = array_intersect_key($values, array_flip($only));
                            }
                            $valuesByLang[$code] = $values;
                        }
                    } catch (\Throwable $e) {
                        return ['status' => 'error', 'message' => $e->getMessage()];
                    }

                    $wantsPoster = $only === null
                        || (is_array($only) && in_array('poster', $only, true));
                    $wantsStills = $only === null
                        || (is_array($only) && in_array('stills', $only, true));

                    // Never clobber existing editorial content in fill-empty
                    // mode — checked PER LANGUAGE against the raw stored values
                    // (version()->read(), lowercase keys). The Content object
                    // would merge the default language under every translation,
                    // so an untranslated EN field would look non-empty and
                    // fill-empty could never fill a translation.
                    $updates = [];
                    foreach ($valuesByLang as $code => $values) {
                        $stored = $page->version()->read($code) ?? [];
                        $update = [];
                        foreach ($values as $key => $value) {
                            if ($key === 'poster') {
                                continue; // poster is a file, handled below
                            }
                            $existing = $stored[strtolower($key)] ?? null;
                            if (
                                $mode === 'fill-empty' &&
                                $existing !== null &&
                                trim((string) $existing) !== ''
                            ) {
                                continue;
                            }
                            $update[$key] = $value;
                        }
                        $updates[$code] = $update;
                    }
                    $update = $updates[$defaultCode];

                    // Alt texts use the title the page will actually carry: the
                    // applied one when this sync sets it, else the existing
                    // editorial title (NOT $page->title(): slug fallback), else
                    // TMDB's — always in the DEFAULT language (file content is
                    // stored once, not per language). fill-empty keeping an
                    // editorial title must not leak TMDB's title into alt text.
                    $existingTitle = (string) $page
                        ->content($multilang ? $defaultCode : null)
                        ->get('title')?->value();
                    $titleForAlt   = (string) ($update['title']
                        ?? ($existingTitle !== ''
                            ? $existingTitle
                            : ($valuesByLang[$defaultCode]['title'] ?? '')));

                    // Per-asset outcome for an honest Panel notice: a failed image
                    // download must not roll back / mask the applied fields.
                    $assets = [
                        'poster'      => 'skipped',
                        'stills'      => 'skipped',
                        'stillsCount' => 0,
                        'stillsTotal' => 0,
                    ];

                    // File-ref fields (poster/stills) are translate:false and
                    // must be written to the DEFAULT language explicitly: an
                    // apply from the Panel's EN tab runs with current language
                    // EN, where update() without a language code silently
                    // drops non-translatable fields.
                    $contentLang = $multilang ? $defaultCode : null;

                    $kirby->impersonate('kirby', function () use (
                        $page, $updates, $client, $bundle, $wantsPoster, $wantsStills,
                        $mode, $canCreateFile, $canDeleteFile, $titleForAlt, $contentLang, &$assets
                    ) {
                        foreach ($updates as $code => $values) {
                            if ($values === []) {
                                continue;
                            }
                            // update() returns a NEW page object and freezes the old
                            // one (ImmutableMemoryStorage) — every later write must
                            // chain off the latest object or it throws/goes stale.
                            $page = $page->update(
                                $values,
                                $code === 'default' ? null : $code
                            );
                        }

                        if ($wantsPoster === true && $canCreateFile === true) {
                            if ($mode !== 'fill-empty' || $page->poster()->isEmpty() === true) {
                                // "TMDB has no poster" and "download failed" must
                                // not be conflated: only the former may clean up,
                                // and only the latter should suggest a retry.
                                $hasArtwork = empty($bundle['poster_path']) === false;
                                try {
                                    $filename = $hasArtwork === true
                                        ? $client->attachPoster($page, $bundle, $titleForAlt)
                                        : null;

                                    if ($filename !== null) {
                                        if ($mode === 'overwrite' && $canDeleteFile === true) {
                                            // Replacing: drop posters from earlier syncs
                                            // (e.g. a corrected wrong match). Manual
                                            // uploads are never touched.
                                            $client->removeTmdbImages($page, 'poster', [$filename]);
                                        }
                                        $page = $page->update(['poster' => $filename], $contentLang);
                                        $assets['poster'] = 'attached';
                                    } elseif ($hasArtwork === true) {
                                        $assets['poster'] = 'failed';
                                    } else {
                                        if ($mode === 'overwrite' && $canDeleteFile === true) {
                                            // No artwork on the new match: still drop
                                            // earlier synced posters, or a corrected
                                            // wrong match keeps showing the previous
                                            // film's artwork on the public page.
                                            $client->removeTmdbImages($page, 'poster', []);
                                            if (
                                                $page->poster()->isNotEmpty() === true &&
                                                $page->poster()->toFile() === null
                                            ) {
                                                $page = $page->update(['poster' => ''], $contentLang);
                                            }
                                        }
                                        $assets['poster'] = 'none';
                                    }
                                } catch (\Throwable $e) {
                                    error_log('kinemathek/tmdb: poster sync failed for ' . $page->id() . ': ' . $e->getMessage());
                                    $assets['poster'] = 'failed';
                                }
                            } else {
                                $assets['poster'] = 'kept';
                            }
                        }

                        if ($wantsStills === true && $canCreateFile === true) {
                            if ($mode !== 'fill-empty' || $page->stills()->isEmpty() === true) {
                                try {
                                    $result = $client->attachStills($page, $bundle, $titleForAlt);
                                    $stored = $result['stored'];

                                    if ($stored !== []) {
                                        $refs = $stored;
                                        if ($mode === 'overwrite' && $canDeleteFile === true) {
                                            // Keep manually uploaded stills in front;
                                            // replace only previously synced ones.
                                            $manual = [];
                                            foreach ($page->stills()->toFiles() as $file) {
                                                if (Str::startsWith($file->filename(), 'tmdb-still-') === false) {
                                                    $manual[] = $file->filename();
                                                }
                                            }
                                            $client->removeTmdbImages($page, 'still', $stored);
                                            $refs = array_merge($manual, $stored);
                                        }
                                        $page = $page->update(['stills' => $refs], $contentLang);
                                        $assets['stills']      = 'attached';
                                        $assets['stillsCount'] = count($stored);
                                        $assets['stillsTotal'] = $result['attempted'];
                                    } elseif ($result['attempted'] > 0) {
                                        // Backdrops exist but no download succeeded:
                                        // change nothing, report an honest failure.
                                        $assets['stills'] = 'failed';
                                    } else {
                                        if ($mode === 'overwrite' && $canDeleteFile === true) {
                                            // No backdrops on the new match — see the
                                            // poster branch: clean up earlier syncs and
                                            // strip their dangling field refs.
                                            $client->removeTmdbImages($page, 'still', []);
                                            $manual = [];
                                            foreach ($page->stills()->toFiles() as $file) {
                                                $manual[] = $file->filename();
                                            }
                                            $page = $page->update(['stills' => $manual], $contentLang);
                                        }
                                        $assets['stills'] = 'none';
                                    }
                                } catch (\Throwable $e) {
                                    error_log('kinemathek/tmdb: stills sync failed for ' . $page->id() . ': ' . $e->getMessage());
                                    $assets['stills'] = 'failed';
                                }
                            } else {
                                $assets['stills'] = 'kept';
                            }
                        }
                    });

                    // Translated fields applied per non-default language, for
                    // the Panel notice ({"en": ["title", "synopsis"], …}).
                    $appliedTranslations = [];
                    foreach ($updates as $code => $values) {
                        if ($code !== $defaultCode && $values !== []) {
                            $appliedTranslations[$code] = array_keys($values);
                        }
                    }

                    return [
                        'status'      => 'ok',
                        'applied'     => array_keys($update),
                        'appliedTranslations' => $appliedTranslations,
                        'poster'      => $assets['poster'],
                        'stills'      => $assets['stills'],
                        'stillsCount' => $assets['stillsCount'],
                        'stillsTotal' => $assets['stillsTotal'],
                        'mode'        => $mode,
                        'locked'      => $override,
                    ];
                },
            ],
            [
                // First-party, auth-protected proxy for a search-preview
                // thumbnail. Downloaded + cached server-side on first demand,
                // then streamed through here — the browser never contacts
                // image.tmdb.org, even during preview (SPEC §7).
                'pattern' => 'kinemathek/tmdb/thumb/(:num)',
                'method'  => 'GET',
                'action'  => function (int $tmdbId) {
                    $path = (new Client())->ensureThumb($tmdbId);

                    if ($path === null || F::exists($path) === false) {
                        // 1x1 transparent gif fallback. no-store so a thumb
                        // that arrives later isn't hidden by a cached miss.
                        return new Response(
                            base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),
                            'image/gif',
                            200,
                            ['Cache-Control' => 'no-store']
                        );
                    }

                    return new Response(F::read($path), F::mime($path) ?? 'image/jpeg', 200, [
                        'Cache-Control' => 'private, max-age=86400',
                    ]);
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
