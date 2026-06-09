<?php

use Kinemathek\Kinemathek;

/**
 * Homepage controller (SPEC §8).
 *
 * Surfaces the three P0 homepage pieces from a SINGLE program build:
 *  - the next screening/event (today's film if there is one today)
 *  - featured "special" programming: festival placement OR a Filmgespräch
 *    ("anything bigger than a routine screening", SPEC §8)
 *  - a compact program overview (Spielplan)
 */
return function ($site) {
    // Built once (future, soonest first); all three views derive from it.
    $program = $site->program();

    $featured = $program->filter(function ($item) {
        $isFestival = in_array('festival', Kinemathek::listValues($item->categories()), true);
        return $isFestival || $item->hasDiscussion()->toBool();
    })->limit(6);

    $overview = $program->filter(
        fn ($item) => in_array('spielplan', Kinemathek::listValues($item->categories()), true)
    )->limit(8);

    return [
        'next'     => $program->first(),
        'featured' => $featured,
        'overview' => $overview,
    ];
};
