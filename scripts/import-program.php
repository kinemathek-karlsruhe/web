<?php

/**
 * Content import: old WordPress site -> Kirby (Films, Showings, Events).
 *
 * Reads scraped program data (scripts/import/old-site-program.json, produced
 * by scrape-old-site.py) and creates:
 *   - Film pages under films/, enriched via the kinemathek/tmdb plugin
 *     (fields + poster + stills, DE full set / EN translatable set — mirrors
 *     the Panel apply route in site/plugins/kinemathek-tmdb/index.php),
 *   - Showing pages under program/ (one per dated screening),
 *   - Event pages under events/ (scraped entries with isEvent=true).
 *
 * Usage:
 *   php scripts/import-program.php                 # DRY RUN: print plan, write nothing
 *   php scripts/import-program.php --apply         # actually create content
 *   php scripts/import-program.php --limit=3       # only first 3 films (+ their showings;
 *                                                  # events are skipped under --limit)
 *   php scripts/import-program.php --no-tmdb       # films from scraped data only
 *   php scripts/import-program.php --input=path    # alternate JSON (default: scripts/import/old-site-program.json)
 *
 * Idempotent: films are skipped when a page with the same slug OR tmdbId
 * exists; showings when one exists with the same film AND date; events when
 * one exists with the same title AND date. Safe to re-run.
 *
 * NB: even the dry run performs (cached, server-side) TMDB *search* requests
 * to show match candidates — that only writes to the Kirby cache dir, never
 * to content/. Use --no-tmdb for a fully offline dry run.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ---------------------------------------------------------------------------
// CLI args
// ---------------------------------------------------------------------------

$apply  = false;
$noTmdb = false;
$limit  = null;
$input  = __DIR__ . '/import/old-site-program.json';

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } elseif ($arg === '--no-tmdb') {
        $noTmdb = true;
    } elseif (preg_match('/^--limit=(\d+)$/', $arg, $m) === 1) {
        $limit = (int) $m[1];
    } elseif (preg_match('/^--input=(.+)$/', $arg, $m) === 1) {
        $input = $m[1];
    } else {
        fwrite(STDERR, "Unknown argument: {$arg}\n");
        fwrite(STDERR, "Usage: php scripts/import-program.php [--apply] [--limit=N] [--no-tmdb] [--input=path]\n");
        exit(1);
    }
}

function fatal(string $msg): never
{
    fwrite(STDERR, "FATAL: {$msg}\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Load + validate input JSON
// ---------------------------------------------------------------------------

if (is_file($input) === false) {
    fatal("Input file not found: {$input}");
}
$json = json_decode((string) file_get_contents($input), true);
if (is_array($json) === false) {
    fatal("Input is not valid JSON: {$input}");
}

$scrapedFilms    = is_array($json['films'] ?? null) ? $json['films'] : [];
$scrapedShowings = is_array($json['showings'] ?? null) ? $json['showings'] : [];

// ---------------------------------------------------------------------------
// Boot Kirby (loads plugins; pins TZ to Europe/Berlin)
// ---------------------------------------------------------------------------

require dirname(__DIR__) . '/kirby/bootstrap.php';

use Kinemathek\Tmdb\Client;
use Kirby\Toolkit\Str;

$kirby = new Kirby();
$kirby->impersonate('kirby');

$filmsParent   = $kirby->site()->find('films')   ?? fatal('Container page films/ not found.');
$programParent = $kirby->site()->find('program') ?? fatal('Container page program/ not found.');
$eventsParent  = $kirby->site()->find('events')  ?? fatal('Container page events/ not found.');

$multilang   = $kirby->multilang();
$defaultLang = $multilang ? $kirby->defaultLanguage()->code() : null;   // 'de'
$otherLangs  = [];
if ($multilang === true) {
    foreach ($kirby->languages() as $language) {
        if ($language->code() !== $defaultLang) {
            $otherLangs[] = $language->code();                          // ['en']
        }
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Trimmed string or '' for null/missing. */
function s(mixed $v): string
{
    return is_scalar($v) ? trim((string) $v) : '';
}

/** List of non-empty strings from a scraped array-or-string field. */
function strList(mixed $v): array
{
    if (is_string($v) === true) {
        $v = [$v];
    }
    if (is_array($v) === false) {
        return [];
    }
    $out = [];
    foreach ($v as $item) {
        $item = s($item);
        if ($item !== '') {
            $out[] = $item;
        }
    }
    return $out;
}

/** De-scream ALL-CAPS series labels (KOOP-KINO -> Koop-Kino); keep mixed case as-is. */
function deScream(string $label): string
{
    if (mb_strtoupper($label, 'UTF-8') === $label && preg_match('/\p{L}/u', $label) === 1) {
        return mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
    }
    return $label;
}

/** Map scraped subtitle markers onto the blueprint's option keys. */
function mapSubtitles(array $raw, array &$warnings, string $context): array
{
    $map = [
        'omu'         => 'OmU',
        'omeu'        => 'OmeU',
        '(e)'         => 'OmeU',
        'e'           => 'OmeU',
        'of'          => 'OF',
        'ov'          => 'OF',
        'df'          => 'dtF',
        'dtf'         => 'dtF',
        'dt. fassung' => 'dtF',
        'dt fassung'  => 'dtF',
    ];
    $out = [];
    foreach ($raw as $marker) {
        $key = $map[mb_strtolower(trim($marker))] ?? null;
        if ($key === null) {
            $warnings[] = "{$context}: unknown subtitle marker \"{$marker}\" dropped";
        } elseif (in_array($key, $out, true) === false) {
            $out[] = $key;
        }
    }
    return $out;
}

/** spielplan always; koop when the series label says so. */
function categoriesFor(?string $series): array
{
    $categories = ['spielplan'];
    if ($series !== null && str_contains(mb_strtolower($series), 'koop') === true) {
        $categories[] = 'koop';
    }
    return $categories;
}

/** Normalized form for fuzzy title comparison. */
function normTitle(string $title): string
{
    $t = mb_strtolower(trim($title), 'UTF-8');
    $t = strtr($t, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
    if (is_string($ascii) === true && $ascii !== '') {
        $t = $ascii;
    }
    $t = (string) preg_replace('/[^a-z0-9]+/', ' ', $t);
    return trim((string) preg_replace('/\s+/', ' ', $t));
}

/** 0..100 similarity of two titles (normalized). */
function titleSimilarity(string $a, string $b): float
{
    $a = normTitle($a);
    $b = normTitle($b);
    if ($a === '' || $b === '') {
        return 0.0;
    }
    if ($a === $b) {
        return 100.0;
    }
    similar_text($a, $b, $percent);
    return $percent;
}

/**
 * True when one normalized title is a prefix of the other (TMDB often appends
 * a subtitle: "Kein Land für Niemand - Abschottung eines …"). Requires a
 * reasonably long shorter title so generic one-worders can't false-positive.
 */
function titleIsPrefix(string $a, string $b): bool
{
    $a = normTitle($a);
    $b = normTitle($b);
    if (min(strlen($a), strlen($b)) < 8) {
        return false;
    }
    return str_starts_with($a, $b) === true || str_starts_with($b, $a) === true;
}

/** 'yyyy-mm-dd' + 'HH:MM' -> 'Y-m-d H:i' or null. */
function combineDate(mixed $date, mixed $time): ?string
{
    $date = s($date);
    $time = s($time);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
        return null;
    }
    if (preg_match('/^\d{1,2}:\d{2}$/', $time) !== 1) {
        $time = '00:00';
    }
    $ts = strtotime($date . ' ' . $time);
    return $ts === false ? null : date('Y-m-d H:i', $ts);
}

/**
 * Pick a confident TMDB candidate for a scraped film, or null.
 * Confident = exact normalized title match (title or original_title) with a
 * non-contradicting year, OR similarity >= 85 with the year within +/-1.
 */
function pickCandidate(array $candidates, string $title, string $originalTitle, ?int $year): ?array
{
    $best = null;
    foreach ($candidates as $c) {
        $pairs = [[$title, (string) $c['title']], [$title, (string) $c['original_title']]];
        if ($originalTitle !== '') {
            $pairs[] = [$originalTitle, (string) $c['original_title']];
            $pairs[] = [$originalTitle, (string) $c['title']];
        }
        $sim    = 0.0;
        $prefix = false;
        foreach ($pairs as [$a, $b]) {
            $sim    = max($sim, titleSimilarity($a, $b));
            $prefix = $prefix || titleIsPrefix($a, $b);
        }

        $candYear = $c['year'] ?? null;
        $yearOk   = null; // unknown
        if ($year !== null && $candYear !== null) {
            $yearOk = abs((int) $candYear - $year) <= 1;
        }

        $confident = ($sim >= 99.5 && $yearOk !== false)   // exact title, year absent or matching
                  || ($sim >= 85 && $yearOk === true)       // close title + matching year
                  || ($prefix === true && $yearOk === true); // TMDB-appended subtitle + matching year
        if ($confident === false) {
            continue;
        }

        $score = $sim + ($yearOk === true ? 20 : 0);
        if ($best === null || $score > $best['score']) {
            $best = ['candidate' => $c, 'score' => $score, 'sim' => $sim, 'yearOk' => $yearOk];
        }
    }
    return $best;
}

// ---------------------------------------------------------------------------
// Existing-content indexes (idempotency)
// ---------------------------------------------------------------------------

$existingFilmsBySlug   = [];
$existingFilmsByTmdbId = [];
foreach ($filmsParent->childrenAndDrafts() as $p) {
    $existingFilmsBySlug[$p->slug()] = $p;
    $tmdbId = (int) $p->content($defaultLang)->get('tmdbid')->value();
    if ($tmdbId > 0) {
        $existingFilmsByTmdbId[$tmdbId] = $p;
    }
}

$existingShowingKeys = []; // "films/<slug>|Y-m-d H:i" => page id
foreach ($programParent->childrenAndDrafts() as $p) {
    $film = $p->content($defaultLang)->get('film')->yaml();
    $film = is_array($film) ? s($film[0] ?? '') : s($film);
    $ts   = strtotime(s($p->content($defaultLang)->get('date')->value()));
    if ($film !== '' && $ts !== false) {
        $existingShowingKeys[$film . '|' . date('Y-m-d H:i', $ts)] = $p->id();
    }
}

$existingEventKeys = []; // "norm-title|Y-m-d H:i" => page id
foreach ($eventsParent->childrenAndDrafts() as $p) {
    $title = s($p->content($defaultLang)->get('title')->value());
    $ts    = strtotime(s($p->content($defaultLang)->get('date')->value()));
    if ($title !== '' && $ts !== false) {
        $existingEventKeys[normTitle($title) . '|' . date('Y-m-d H:i', $ts)] = $p->id();
    }
}

// ---------------------------------------------------------------------------
// Split scraped films: real films vs event descriptors (isEvent=true entries
// carry the description for recurring events like "Speakeasy Cinema")
// ---------------------------------------------------------------------------

$realFilms        = [];
$eventDescriptors = []; // slug => descriptor
foreach ($scrapedFilms as $f) {
    if (is_array($f) === false) {
        continue;
    }
    $slug = s($f['slug'] ?? '') !== '' ? s($f['slug']) : Str::slug(s($f['title'] ?? ''));
    if ($slug === '') {
        continue;
    }
    $f['slug'] = $slug;
    if (($f['isEvent'] ?? false) === true) {
        $eventDescriptors[$slug] = $f;
    } else {
        $realFilms[] = $f;
    }
}

$limitedFilms  = $limit !== null ? array_slice($realFilms, 0, $limit) : $realFilms;
$selectedSlugs = array_column($limitedFilms, 'slug');

// Series labels seen on showings, merged into the film's series tags.
$showingSeriesBySlug = [];
foreach ($scrapedShowings as $sShowing) {
    $slug   = s($sShowing['filmSlug'] ?? '');
    $series = s($sShowing['series'] ?? '');
    if ($slug !== '' && $series !== '') {
        $showingSeriesBySlug[$slug][] = $series;
    }
}

// ---------------------------------------------------------------------------
// Plan: films
// ---------------------------------------------------------------------------

$client   = $noTmdb ? null : new Client();
$warnings = [];
$filmPlan = []; // slug => plan row

echo "== Import plan ({$input})" . ($apply ? ' [APPLY]' : ' [DRY RUN]') . " ==\n";
echo 'Scraped: ' . count($realFilms) . ' films, ' . count($scrapedShowings) . ' showings ('
    . count($eventDescriptors) . " event descriptors)\n";
if ($limit !== null) {
    echo "--limit={$limit}: importing first " . count($limitedFilms) . " film(s); events are skipped under --limit\n";
}
echo "\nFILMS\n";

foreach ($limitedFilms as $f) {
    $slug  = $f['slug'];
    $title = s($f['title'] ?? '');
    $year  = is_numeric($f['year'] ?? null) ? (int) $f['year'] : null;

    // Merge series: film-level labels + labels from its showings, de-screamed.
    $seriesLabels = strList($f['series'] ?? []);
    foreach ($showingSeriesBySlug[$slug] ?? [] as $label) {
        $seriesLabels[] = $label;
    }
    $series = [];
    foreach ($seriesLabels as $label) {
        $label = deScream($label);
        if (in_array(mb_strtolower($label), array_map('mb_strtolower', $series), true) === false) {
            $series[] = $label;
        }
    }

    $plan = [
        'scraped'  => $f,
        'slug'     => $slug,
        'title'    => $title,
        'year'     => $year,
        'series'   => $series,
        'action'   => 'create',
        'tmdb'     => null,   // confident match ['id','title','year']
        'note'     => '',
        'filmId'   => 'films/' . $slug,
    ];

    if ($title === '') {
        $plan['action'] = 'skip';
        $plan['note']   = 'no title in scraped data';
    } elseif (isset($existingFilmsBySlug[$slug]) === true) {
        $plan['action'] = 'skip';
        $plan['note']   = 'already exists (slug)';
        $plan['filmId'] = $existingFilmsBySlug[$slug]->id();
    } elseif ($noTmdb === false) {
        try {
            $candidates = $client->search($title);
            if ($candidates === [] && s($f['originalTitle'] ?? '') !== '') {
                $candidates = $client->search(s($f['originalTitle']));
            }
            $match = pickCandidate($candidates, $title, s($f['originalTitle'] ?? ''), $year);

            if ($match !== null) {
                $c = $match['candidate'];
                if (isset($existingFilmsByTmdbId[$c['id']]) === true) {
                    $plan['action'] = 'skip';
                    $plan['note']   = "already exists (tmdbId {$c['id']} = " . $existingFilmsByTmdbId[$c['id']]->id() . ')';
                    $plan['filmId'] = $existingFilmsByTmdbId[$c['id']]->id();
                } else {
                    $plan['tmdb'] = $c;
                    $plan['note'] = "TMDB #{$c['id']} \"{$c['title']}\" ({$c['year']})"
                        . ($match['yearOk'] === true ? '' : ' [matched on title only]');
                }
            } else {
                $top = array_slice($candidates, 0, 3);
                $candStr = implode(', ', array_map(
                    fn ($c) => "#{$c['id']} \"{$c['title']}\" ({$c['year']})",
                    $top
                ));
                $plan['note'] = '!! NO CONFIDENT TMDB MATCH -> scraped data only'
                    . ($candStr !== '' ? " (candidates: {$candStr})" : ' (no candidates)');
            }
            usleep(250000); // be polite to TMDB even though responses are cached
        } catch (\Throwable $e) {
            $plan['note'] = '!! TMDB error (' . $e->getMessage() . ') -> scraped data only';
        }
    } else {
        $plan['note'] = 'scraped data only (--no-tmdb)';
    }

    $filmPlan[$slug] = $plan;

    printf(
        "  [%-6s] %-45s %s\n",
        $plan['action'],
        $slug . ($year !== null ? " ({$year})" : ''),
        $plan['note']
    );
}

// ---------------------------------------------------------------------------
// Plan: showings + events
// ---------------------------------------------------------------------------

$showingPlan = [];
$eventPlan   = [];
$plannedShowingKeys = [];
$plannedEventKeys   = [];

echo "\nSHOWINGS\n";

foreach ($scrapedShowings as $i => $sShowing) {
    if (is_array($sShowing) === false) {
        continue;
    }
    $isEvent  = ($sShowing['isEvent'] ?? false) === true;
    $title    = s($sShowing['title'] ?? '');
    $filmSlug = s($sShowing['filmSlug'] ?? '');
    if ($filmSlug === '' && $isEvent === false) {
        // Fall back to matching the showing title against scraped film slugs.
        $filmSlug = Str::slug($title);
    }
    $datetime = combineDate($sShowing['date'] ?? null, $sShowing['time'] ?? null);
    $label    = $title !== '' ? $title : ($filmSlug !== '' ? $filmSlug : "showing #{$i}");

    if ($datetime === null) {
        $warnings[] = "showing \"{$label}\": missing/invalid date -> skipped";
        continue;
    }

    if ($isEvent === true) {
        if ($limit !== null) {
            continue; // cautious first run: films only
        }
        if ($title === '') {
            $warnings[] = "event on {$datetime}: no title -> skipped";
            continue;
        }
        $key = normTitle($title) . '|' . $datetime;
        $descriptor = $eventDescriptors[$filmSlug] ?? null;
        $text  = s($descriptor['synopsis'] ?? '');
        $notes = s($sShowing['notes'] ?? '');
        if ($notes !== '') {
            $text = $text === '' ? $notes : $text . "\n\n" . $notes;
        }
        $plan = [
            'slug'     => Str::slug($title) . '-' . date('Ymd-Hi', (int) strtotime($datetime)),
            'title'    => $title,
            'datetime' => $datetime,
            'venue'    => s($sShowing['venue'] ?? ''),
            'text'     => $text,
            'ticketUrl' => s($sShowing['ticketUrl'] ?? ''),
            'hasDiscussion' => (($sShowing['discussion'] ?? false) === true)
                || (($descriptor['mentionsDiscussion'] ?? false) === true),
            'categories' => categoriesFor(s($sShowing['series'] ?? '') ?: null),
            'subtitles'  => mapSubtitles(strList($sShowing['subtitles'] ?? []), $warnings, "event \"{$title}\""),
            'action'   => 'create',
            'note'     => '',
        ];
        if (isset($existingEventKeys[$key]) === true) {
            $plan['action'] = 'skip';
            $plan['note']   = 'already exists (' . $existingEventKeys[$key] . ')';
        } elseif (isset($plannedEventKeys[$key]) === true) {
            $plan['action'] = 'skip';
            $plan['note']   = 'duplicate in input';
        }
        $plannedEventKeys[$key] = true;
        $eventPlan[] = $plan;
        continue;
    }

    // Regular showing -> needs a film page to point at.
    if ($limit !== null && in_array($filmSlug, $selectedSlugs, true) === false) {
        continue; // its film is outside --limit
    }

    $filmId = null;
    if (isset($filmPlan[$filmSlug]) === true) {
        $filmId = $filmPlan[$filmSlug]['filmId'];
    } elseif (isset($existingFilmsBySlug[$filmSlug]) === true) {
        $filmId = $existingFilmsBySlug[$filmSlug]->id();
    }
    if ($filmId === null) {
        $warnings[] = "showing \"{$label}\" ({$datetime}): film \"{$filmSlug}\" not in import set and not in Kirby -> skipped";
        continue;
    }

    $key  = $filmId . '|' . $datetime;
    $plan = [
        'slug'      => $filmSlug . '-' . date('Ymd-Hi', (int) strtotime($datetime)),
        'filmId'    => $filmId,
        'filmSlug'  => $filmSlug,
        'title'     => $title,
        'datetime'  => $datetime,
        'venue'     => s($sShowing['venue'] ?? ''),
        'sonderinfo' => s($sShowing['notes'] ?? ''),
        'ticketUrl' => s($sShowing['ticketUrl'] ?? ''),
        'subtitles' => mapSubtitles(strList($sShowing['subtitles'] ?? []), $warnings, "showing \"{$label}\""),
        'hasDiscussion' => ($sShowing['discussion'] ?? false) === true,
        'categories' => categoriesFor(s($sShowing['series'] ?? '') ?: null),
        'action'    => 'create',
        'note'      => '',
    ];
    if (isset($existingShowingKeys[$key]) === true) {
        $plan['action'] = 'skip';
        $plan['note']   = 'already exists (' . $existingShowingKeys[$key] . ')';
    } elseif (isset($plannedShowingKeys[$key]) === true) {
        $plan['action'] = 'skip';
        $plan['note']   = 'duplicate in input';
    }
    $plannedShowingKeys[$key] = true;
    $showingPlan[] = $plan;
}

foreach ($showingPlan as $plan) {
    printf("  [%-6s] %s  %-35s %s\n", $plan['action'], $plan['datetime'], $plan['filmSlug'], $plan['note']);
}
if ($showingPlan === []) {
    echo "  (none)\n";
}

echo "\nEVENTS\n";
foreach ($eventPlan as $plan) {
    printf("  [%-6s] %s  %-35s %s\n", $plan['action'], $plan['datetime'], $plan['title'], $plan['note']);
}
if ($eventPlan === []) {
    echo $limit !== null ? "  (skipped under --limit)\n" : "  (none)\n";
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

$count = fn (array $plans, string $action) => count(array_filter($plans, fn ($p) => $p['action'] === $action));
$filmsCreate    = $count($filmPlan, 'create');
$filmsTmdb      = count(array_filter($filmPlan, fn ($p) => $p['action'] === 'create' && $p['tmdb'] !== null));
$showingsCreate = $count($showingPlan, 'create');
$eventsCreate   = $count($eventPlan, 'create');

echo "\nSUMMARY\n";
echo "  films:    {$filmsCreate} to create ({$filmsTmdb} with TMDB match, " . ($filmsCreate - $filmsTmdb)
    . ' scraped-only), ' . $count($filmPlan, 'skip') . " skipped\n";
echo "  showings: {$showingsCreate} to create, " . $count($showingPlan, 'skip') . " skipped\n";
echo "  events:   {$eventsCreate} to create, " . $count($eventPlan, 'skip') . " skipped\n";

if ($warnings !== []) {
    echo "\nWARNINGS\n";
    foreach (array_unique($warnings) as $w) {
        echo "  ! {$w}\n";
    }
}

if ($apply === false) {
    echo "\nDry run only — nothing was written. Re-run with --apply to create content.\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// APPLY
// ---------------------------------------------------------------------------

echo "\n== Applying ==\n";
$errors = 0;

/**
 * Default-language field set for a new Film: TMDB mapped values as the base
 * (when matched), overlaid with the scraped editorial values. 'poster' (a URL
 * in mapToFilm's output, not a blueprint field) is stripped — the file is
 * attached separately, exactly like the Panel apply route does.
 */
function filmContent(array $plan, ?array $mapped): array
{
    $f = $plan['scraped'];

    $directors = [];
    foreach (strList($f['directors'] ?? []) as $name) {
        $directors[] = ['name' => $name, 'tmdbpersonid' => ''];
    }

    $scraped = [
        'title'         => $plan['title'],
        'originalTitle' => s($f['originalTitle'] ?? ''),
        'directors'     => $directors,
        'synopsis'      => s($f['synopsis'] ?? ''),
        'year'          => $plan['year'],
        'runtime'       => is_numeric($f['runtime'] ?? null) ? (int) $f['runtime'] : null,
        'country'       => strList($f['country'] ?? []),
    ];

    if ($mapped === null) {
        $content = $scraped;
    } else {
        $content = $mapped;
        unset($content['poster']); // URL only — becomes a real file via attachPoster()
        // Editorial values win where the old site had them; TMDB fills the rest.
        foreach (['title', 'synopsis', 'runtime', 'year', 'country', 'originalTitle'] as $key) {
            if (empty($scraped[$key]) === false) {
                $content[$key] = $scraped[$key];
            }
        }
        if ($content['directors'] === [] && $scraped['directors'] !== []) {
            $content['directors'] = $scraped['directors'];
        }
    }

    $content['series'] = $plan['series'];

    // Drop empties so we don't write "0"/"" garbage into fresh pages.
    return array_filter($content, fn ($v) => $v !== null && $v !== '' && $v !== [] && $v !== 0);
}

foreach ($filmPlan as $slug => $plan) {
    if ($plan['action'] !== 'create') {
        continue;
    }
    echo "film {$slug}: ";
    try {
        $mapped   = null;
        $bundle   = null;
        $enValues = null;

        if ($plan['tmdb'] !== null) {
            $tmdbId = (int) $plan['tmdb']['id'];
            $bundle = $client->movie($tmdbId, $defaultLang);          // de bundle
            $mapped = $client->mapToFilm($bundle);
            foreach ($otherLangs as $code) {                          // 'en'
                $values = array_intersect_key(
                    $client->mapToFilm($client->movie($tmdbId, $code)),
                    array_flip(Client::TRANSLATABLE)                  // title, synopsis, genre
                );
                $enValues[$code] = array_filter($values, fn ($v) => $v !== '' && $v !== []);
            }
        }

        $page = $filmsParent->createChild([
            'slug'     => $slug,
            'template' => 'film',
            'content'  => filmContent($plan, $mapped),
        ]);

        // Non-default languages get only the TRANSLATABLE set (apply-route flow).
        foreach ($enValues ?? [] as $code => $values) {
            if ($values !== []) {
                $page = $page->update($values, $code); // update() returns a NEW page — rechain
            }
        }

        $log = ['fields'];
        if ($bundle !== null) {
            $titleForAlt = (string) $page->content($defaultLang)->get('title')->value();
            if (empty($bundle['poster_path']) === false) {
                $filename = $client->attachPoster($page, $bundle, $titleForAlt);
                if ($filename !== null) {
                    $page  = $page->update(['poster' => $filename], $defaultLang);
                    $log[] = 'poster';
                } else {
                    $log[] = 'poster FAILED';
                }
            }
            $stills = $client->attachStills($page, $bundle, $titleForAlt);
            if ($stills['stored'] !== []) {
                $page  = $page->update(['stills' => $stills['stored']], $defaultLang);
                $log[] = 'stills ' . count($stills['stored']) . '/' . $stills['attempted'];
            } elseif ($stills['attempted'] > 0) {
                $log[] = 'stills FAILED';
            }
            usleep(250000);
        }

        $page = $page->changeStatus('listed');
        $existingFilmsBySlug[$slug] = $page; // keep indexes fresh for re-checks
        echo 'created (' . implode(', ', $log) . ")\n";
    } catch (\Throwable $e) {
        $errors++;
        echo "ERROR: {$e->getMessage()}\n";
    }
}

foreach ($showingPlan as $plan) {
    if ($plan['action'] !== 'create') {
        continue;
    }
    echo "showing {$plan['slug']}: ";
    try {
        $filmPage  = $kirby->page($plan['filmId']);
        if ($filmPage === null) {
            throw new \RuntimeException("film page {$plan['filmId']} missing (creation failed earlier?)");
        }
        // Optional title override only when it actually differs from the film.
        $filmTitle = s($filmPage->content($defaultLang)->get('title')->value());
        $override  = normTitle($plan['title']) !== '' && normTitle($plan['title']) !== normTitle($filmTitle)
            ? $plan['title'] : '';

        $content = array_filter([
            'film'          => $plan['filmId'],
            'title'         => $override,
            'date'          => $plan['datetime'] . ':00',
            'venue'         => $plan['venue'],
            'sonderinfo'    => $plan['sonderinfo'],
            'ticketUrl'     => $plan['ticketUrl'],
            'subtitles'     => $plan['subtitles'],
            'categories'    => $plan['categories'],
            'hasDiscussion' => $plan['hasDiscussion'] ? 'true' : 'false',
        ], fn ($v) => $v !== '' && $v !== []);

        $page = $programParent->createChild([
            'slug'     => $plan['slug'],
            'template' => 'showing',
            'content'  => $content,
        ]);
        $page->changeStatus('listed'); // num auto-derives from date via blueprint
        echo "created\n";
    } catch (\Throwable $e) {
        $errors++;
        echo "ERROR: {$e->getMessage()}\n";
    }
}

foreach ($eventPlan as $plan) {
    if ($plan['action'] !== 'create') {
        continue;
    }
    echo "event {$plan['slug']}: ";
    try {
        $content = array_filter([
            'title'         => $plan['title'],
            'date'          => $plan['datetime'] . ':00',
            'venue'         => $plan['venue'],
            'text'          => $plan['text'],          // the description field is named `text`
            'ticketUrl'     => $plan['ticketUrl'],
            'subtitles'     => $plan['subtitles'],
            'categories'    => $plan['categories'],
            'hasDiscussion' => $plan['hasDiscussion'] ? 'true' : 'false',
        ], fn ($v) => $v !== '' && $v !== []);

        $page = $eventsParent->createChild([
            'slug'     => $plan['slug'],
            'template' => 'event',
            'content'  => $content,
        ]);
        $page->changeStatus('listed');
        echo "created\n";
    } catch (\Throwable $e) {
        $errors++;
        echo "ERROR: {$e->getMessage()}\n";
    }
}

echo "\nDone" . ($errors > 0 ? " with {$errors} error(s)" : '') . ".\n";
exit($errors > 0 ? 1 : 0);
