<?php

use Kinemathek\Kinemathek;

/**
 * Bereichsseite controller — the upcoming program restricted to the page's
 * categories AND any editor-curated pre-filters, grouped by day for the shared
 * Monatsblatt listing. The pre-filters mirror the Spielplan's query-string
 * facets (Kinemathek::FACETS) but are read off the page's filter<Facet> fields,
 * so a section (or subpage) can be scoped to e.g. a single Reihe — series lives
 * on the Film, so filterByFacets() resolves it through the linked film.
 * (Grouping block mirrors the program/events controllers — wants to be a
 * Kinemathek::groupByDay() helper once the plugin unfreezes.)
 */
return function ($site, $page, $kirby) {
    $categories = Kinemathek::splitField($page->categories());

    // Pre-set facets: read filterSeries/filterGenre/… by facet name. Absent or
    // empty fields yield [] and are skipped, so they never constrain the result.
    $presetFacets = [];
    foreach (array_keys(Kinemathek::FACETS) as $facet) {
        $values = Kinemathek::splitField($page->{'filter' . ucfirst($facet)}());
        if ($values !== []) {
            $presetFacets[$facet] = $values;
        }
    }
    // Boolean facet: only screenings with a Filmgespräch.
    if ($page->filterDiscussion()->toBool() === true) {
        $presetFacets['discussion'] = '1';
    }

    // The auto-listing appears once the page is scoped — by category and/or a
    // pre-filter. An unconfigured Bereichsseite shows nothing (rather than
    // dumping the whole calendar). With only pre-filters set, the program is
    // unrestricted by category, then narrowed to the chosen facets.
    $configured = $categories !== [] || $presetFacets !== [];
    $results    = $configured === false
        ? new \Kirby\Cms\Pages([])
        : Kinemathek::filterByFacets(
            $site->program(['categories' => $categories ?: null]),
            $presetFacets
        );

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
            // month name is always carried (client filtering re-picks the first
            // visible day per month); monthStart marks the full-list transition
            // so the no-JS view still shows one marker per month.
            'month'      => Kinemathek::localDate($ts, 'month'),
            'monthStart' => $prevMonth !== $month,
            'detailDate' => Kinemathek::localDate($ts, 'detail'),
        ];
        $prevMonth = $month;
    }

    return [
        'days'       => $days,
        'dayMeta'    => $dayMeta,
        'todayKey'   => date('Y-m-d'),
        'configured' => $configured,
    ];
};
