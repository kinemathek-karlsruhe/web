<?php

use Kinemathek\Kinemathek;

/**
 * Spielplan / program controller (SPEC §5 / §8) — Monatsblatt view.
 *
 * Server-side: reads facets from the query string (?country=fr&subtitles=omu)
 * for deep links, builds the chronological future program (Showings + Events
 * interleaved), then prepares the Monatsblatt view data: items grouped by day
 * (Europe/Berlin, pinned by the plugin), localized day/month labels via
 * Kinemathek::localDate() (patterns: kinemathek.date.dow/month/detail in the
 * language files), the hero artwork and the Heute-Strip (today's — or the
 * next — screening day).
 *
 * The venue/OmU/Filmgespräch quick filters in the designed filter bar are
 * client-side (assets/js/program.js) over the rendered list — no requests,
 * no URL churn.
 */
return function ($site, $page, $kirby) {
    // "include today" can be toggled via ?today=0 (defaults to including today).
    $includeToday = get('today', '1') !== '0';

    // Archive toggle: ?past=1 shows the screening history (most-recent first)
    // instead of the upcoming program. Same listing + facet filters.
    $past = get('past', '0') === '1';

    // Optional placement restriction, e.g. ?category=koop.
    $categoryParam = get('category');
    $categories    = $categoryParam ? explode(',', $categoryParam) : null;

    // Base program (future, or past history) — category-restricted before facets.
    $program = $site->program([
        'includeToday' => $includeToday,
        'categories'   => $categories,
        'past'         => $past,
    ]);

    $results = Kinemathek::filterByFacets($program, [
        'country'      => get('country'),
        'language'     => get('language'),
        'genre'        => get('genre'),
        'series'       => get('series'),
        'subtitles'    => get('subtitles'),
        'keywords'     => get('keywords'),
        'discussion'   => get('discussion'),
        'hasSubtitles' => get('hasSubtitles'),
    ]);

    // ---- Monatsblatt view data ------------------------------------------

    // Group chronologically by day.
    $days = [];
    foreach ($results as $item) {
        if (!$ts = $item->timestamp()) {
            continue;
        }
        $days[date('Y-m-d', $ts)][] = $item;
    }
    // Future: oldest-first (soonest screening at the top). Past: newest-first.
    $past ? krsort($days) : ksort($days);

    $dayMeta   = [];
    $prevMonth = null;
    foreach (array_keys($days) as $key) {
        $ts    = strtotime($key . ' 12:00');
        $month = (int)date('n', $ts);
        $dayMeta[$key] = [
            'dow'        => rtrim(Kinemathek::localDate($ts, 'dow'), '.'),
            'num'        => (int)date('j', $ts),
            // month marker on the first day and on every month transition
            'month'      => $prevMonth === $month ? null : Kinemathek::localDate($ts, 'month'),
            'detailDate' => Kinemathek::localDate($ts, 'detail'),
        ];
        $prevMonth = $month;
    }

    // Heute-Strip: today's screening day, or the next one ("Demnächst").
    $todayKey = date('Y-m-d');
    $stripKey = null;
    foreach (array_keys($days) as $key) {
        if ($key >= $todayKey) {
            $stripKey = $key;
            break;
        }
    }
    $stripItems = $stripKey !== null ? $days[$stripKey] : [];

    // Hero: first strip item with artwork. A Showing uses its film's still
    // (preferred) or poster; an Event has no film, so fall back to the event's
    // own `image` (read via content()->get('image') — ->image() is a native
    // Page method, same trap the listing snippet works around).
    $heroFile  = null;
    $heroTitle = null;
    foreach ($stripItems as $item) {
        if ($film = $item->film()) {
            $file  = $film->stills()->toFiles()->first() ?? $film->posterFile();
            $title = $film->title()->value();
        } else {
            $file  = $item->content()->get('image')->toFile();
            $title = $item->displayTitle();
        }
        if ($file) {
            $heroFile  = $file;
            $heroTitle = $title;
            break;
        }
    }

    // Mobile start tiles: editor-curated Bereichsseiten (the `reihen` pages
    // field on the Spielplan page). Tile image = the Bereichsseite's first
    // `bilder` file, caption = its title; pages without an image still render
    // (striped placeholder, like the hero). Shown on phones only.
    $reihen = [];
    if (!$past) {
        foreach ($page->reihen()->toPages() as $reihePage) {
            $reihen[] = [
                'url'   => $reihePage->url(),
                'title' => $reihePage->title()->value(),
                'file'  => $reihePage->bilder()->toFiles()->first()
                    ?? $reihePage->images()->first(),
            ];
        }
    }

    // Masthead month label ("Juni/Juli" + two-digit year superscript),
    // anchored to the current calendar month — NOT the scheduled range, so
    // next month's already-published showings (visible in the listing below)
    // never bleed into the title. June + July are published as one combined
    // summer issue, so months 6+7 are the ONLY double label; every other
    // month is single. Both summer months share the current year.
    $year  = (int)date('Y');
    $month = (int)date('n');
    if ($month === 6 || $month === 7) {
        $titleMonths = Kinemathek::localDate(mktime(12, 0, 0, 6, 15, $year), 'month')
            . '/' . Kinemathek::localDate(mktime(12, 0, 0, 7, 15, $year), 'month');
    } else {
        $titleMonths = Kinemathek::localDate(time(), 'month');
    }
    $titleYear = date('y');

    return [
        'past'        => $past,
        'days'        => $days,
        'dayMeta'     => $dayMeta,
        'todayKey'    => $todayKey,
        'stripKey'    => $stripKey,
        'stripItems'  => $stripItems,
        'heroFile'    => $heroFile,
        'heroTitle'   => $heroTitle,
        'reihen'      => $reihen,
        'titleMonths' => $titleMonths,
        'titleYear'   => $titleYear,
    ];
};
