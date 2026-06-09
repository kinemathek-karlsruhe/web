<?php

use Kirby\Cms\Pages;

/**
 * Single Showing controller (SPEC §2.2 / §3).
 */
return function ($page) {
    $film = $page->film();

    return [
        'film'          => $film,
        'isPast'        => $page->isPast(),
        // Other upcoming screenings of the same film, for cross-linking.
        'otherShowings' => $film
            ? $film->upcomingShowings()->not($page)
            : new Pages(),
    ];
};
