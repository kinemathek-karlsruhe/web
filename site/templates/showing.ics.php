<?php

/**
 * ICS representation of a single Showing — URL: /<showing>.ics
 * Auto-discovered content representation; first-party, no cookies (SPEC §6/§7).
 *
 * @var \Kirby\Cms\App  $kirby
 * @var \Kirby\Cms\Page $page  the showing
 */

use Kinemathek\Ics;

$start = $page->icsStart();
if ($start === null) {
    throw new \Kirby\Exception\NotFoundException('Diese Vorstellung hat kein Datum.');
}

// DTEND from the linked film's runtime (minutes) if known, else default.
$film    = $page->film(); // model method → Page|null
$runtime = ($film && $film->runtime()->isNotEmpty())
    ? (int) $film->runtime()->value()
    : (int) (option('kinemathek.ics.defaultDuration') ?? 120);
$end = (clone $start)->modify('+' . max(1, $runtime) . ' minutes');

$summary = ($film && $film->title()->isNotEmpty())
    ? $film->title()->value()
    : $page->displayTitle();

$parts = [];
if ($page->sonderinfo()->isNotEmpty()) {
    $parts[] = $page->sonderinfo()->value();
}
if ($page->subtitles()->isNotEmpty()) {
    $parts[] = 'Fassung: ' . $page->subtitles()->commaList();
}
if ($page->ticketUrl()->isNotEmpty()) {
    $parts[] = 'Tickets: ' . $page->ticketUrl()->value();
}

$ics = Ics::build([
    'uid'         => $page->icsUid(),
    'start'       => $start,
    'end'         => $end,
    'summary'     => $summary,
    'description' => implode("\n\n", $parts),
    'location'    => $page->venue()->isNotEmpty() ? $page->venue()->value() : 'Kinemathek Karlsruhe',
    'url'         => (string) $page->url(),
    'tzid'        => option('kinemathek.ics.timezone') ?? 'Europe/Berlin',
]);

echo Ics::respond($kirby, $ics, $page->icsFilename());
