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

    // Optional placement restriction, e.g. ?category=koop.
    $categoryParam = get('category');
    $categories    = $categoryParam ? explode(',', $categoryParam) : null;

    // Base future program (optionally category-restricted) before facet filtering.
    $program = $site->program([
        'includeToday' => $includeToday,
        'categories'   => $categories,
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
    ksort($days);

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

    // Hero: first strip item whose film has artwork (still preferred).
    // (-> FilmPage::artwork() once the plugin unfreezes; same policy in the
    //     template's $entryData.)
    $heroFile  = null;
    $heroTitle = null;
    foreach ($stripItems as $item) {
        if (!$film = $item->film()) {
            continue;
        }
        $file = $film->stills()->toFiles()->first() ?? $film->posterFile();
        if ($file) {
            $heroFile  = $file;
            $heroTitle = $film->title()->value();
            break;
        }
    }

    // Masthead month range ("Juni/Juli" + two-digit year superscript).
    $dayKeys = array_keys($days);
    if ($dayKeys !== []) {
        $firstTs = strtotime(reset($dayKeys) . ' 12:00');
        $lastTs  = strtotime(end($dayKeys) . ' 12:00');
        $m1 = Kinemathek::localDate($firstTs, 'month');
        $m2 = Kinemathek::localDate($lastTs, 'month');
        $titleMonths = $m1 === $m2 ? $m1 : $m1 . '/' . $m2;
        $titleYear   = date('y', $firstTs);
    } else {
        $titleMonths = Kinemathek::localDate(time(), 'month');
        $titleYear   = date('y');
    }

    return [
        'days'        => $days,
        'dayMeta'     => $dayMeta,
        'todayKey'    => $todayKey,
        'stripKey'    => $stripKey,
        'stripItems'  => $stripItems,
        'heroFile'    => $heroFile,
        'heroTitle'   => $heroTitle,
        'titleMonths' => $titleMonths,
        'titleYear'   => $titleYear,
    ];
};
