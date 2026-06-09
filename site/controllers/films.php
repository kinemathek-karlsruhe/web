<?php

use Kinemathek\Kinemathek;

/**
 * Film archive controller (SPEC §5).
 *
 * Permanent back-catalogue of FILMS only — events never appear here. Supports
 * the same descriptive facets as the program, plus the two per-showing archive
 * facets SPEC §5 names explicitly ("films that have a discussion", "with
 * subtitles"), resolved across each film's showings. Films with an upcoming
 * screening surface first; the rest fall back to newest year. Returns ALL matches.
 */
return function ($site, $page) {
    $films = $site->films();

    $facets = [
        'country'  => get('country'),
        'language' => get('language'),
        'genre'    => get('genre'),
        'series'   => get('series'),
        'keywords' => get('keywords'),
    ];

    $results = Kinemathek::filterByFacets($films, $facets);

    // Per-showing archive facets (resolved across the film's screenings).
    $discussion   = get('discussion');
    $hasSubtitles = get('hasSubtitles');
    if ($discussion) {
        $results = $results->filter(fn ($film) => $film->hasDiscussionShowing());
    }
    if ($hasSubtitles) {
        $results = $results->filter(fn ($film) => $film->hasSubtitledShowing());
    }

    // Upcoming-first (SPEC §3 intent carried into the archive), then newest year.
    $results = $results->sortBy(
        fn ($film) => $film->hasUpcoming() ? 0 : 1, 'asc',
        fn ($film) => (int) $film->year()->value(), 'desc'
    );

    $activeFacets = array_filter($facets, fn ($v) => $v !== null && $v !== '');
    if ($discussion) {
        $activeFacets['discussion'] = $discussion;
    }
    if ($hasSubtitles) {
        $activeFacets['hasSubtitles'] = $hasSubtitles;
    }

    return [
        'films'           => $results,
        'availableFacets' => Kinemathek::availableFacets($results),
        'activeFacets'    => $activeFacets,
        'totalCount'      => $results->count(),
    ];
};
