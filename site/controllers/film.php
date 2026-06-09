<?php

/**
 * Single Film controller (SPEC §3).
 *
 * A film page is reachable independently of any showing; its screenings appear
 * below it automatically — upcoming (clickable) and past (visible history).
 */
return function ($page) {
    return [
        'upcoming'  => $page->upcomingShowings(), // soonest first
        'past'      => $page->pastShowings(),     // most recent first
        'directors' => $page->directors(),
    ];
};
