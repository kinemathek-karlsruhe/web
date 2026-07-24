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
    // Categories and the pre-filters are editorial routing config (all
    // translate: false), so they must be read from the DEFAULT language — never
    // the current request language. On /en the translation file can physically
    // carry an emptied copy of a (formerly translatable) filter field, and the
    // content merge overlays that empty string over the German value on read,
    // silently emptying the /en listing. Reading the default language is immune
    // to such stale copies without touching content on the server.
    $cfg        = $page->content($kirby->defaultLanguage()?->code());
    $categories = Kinemathek::splitField($cfg->get('categories'));

    // Pre-set facets: read filterSeries/filterGenre/… by facet name. Absent or
    // empty fields yield [] and are skipped, so they never constrain the result.
    $presetFacets = [];
    foreach (array_keys(Kinemathek::FACETS) as $facet) {
        $values = Kinemathek::splitField($cfg->get('filter' . ucfirst($facet)));
        if ($values !== []) {
            $presetFacets[$facet] = $values;
        }
    }
    // Boolean facet: only screenings with a Filmgespräch.
    if ($cfg->get('filterDiscussion')->toBool() === true) {
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
            $presetFacets,
            // match the German pre-filter values against the films'/showings'
            // DEFAULT-language facet values — see filterByFacets(): translatable
            // facet fields may be empty in the /en translation and would
            // otherwise drop every result on the English Bereichsseite.
            $kirby->defaultLanguage()?->code()
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
