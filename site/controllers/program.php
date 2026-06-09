<?php

use Kinemathek\Kinemathek;

/**
 * Spielplan / program controller (SPEC §5 / §8).
 *
 * Reads facets from the query string (?country=fr&subtitles=omu&discussion=1),
 * builds the chronological future program (Showings + Events interleaved),
 * applies the facets and hands the template the full result set plus the facets
 * that exist in the (unfiltered) data.
 */
return function ($site, $page) {
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

    // Every known facet from the query string. Absent ones stay null and so do
    // not constrain the result.
    $facets = [
        'country'      => get('country'),
        'language'     => get('language'),
        'genre'        => get('genre'),
        'series'       => get('series'),
        'subtitles'    => get('subtitles'),
        'keywords'     => get('keywords'),
        'discussion'   => get('discussion'),
        'hasSubtitles' => get('hasSubtitles'),
    ];

    $results = Kinemathek::filterByFacets($program, $facets);

    return [
        'includeToday'    => $includeToday,
        'program'         => $results,
        // Facets reflect the filtered result set, so only filters that still
        // have results are offered (SPEC §5), with accurate counts.
        'availableFacets' => Kinemathek::availableFacets($results),
        'activeFacets'    => array_filter($facets, fn ($v) => $v !== null && $v !== ''),
        'totalCount'      => $results->count(),
    ];
};
