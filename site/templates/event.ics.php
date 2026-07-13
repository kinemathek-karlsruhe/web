<?php

/**
 * ICS representation of a single non-film Event — URL: /<event>.ics
 *
 * @var \Kirby\Cms\App  $kirby
 * @var \Kirby\Cms\Page $page  the event
 */

use Kinemathek\Ics;

$start = $page->icsStart();
if ($start === null) {
    throw new \Kirby\Exception\NotFoundException('Diese Veranstaltung hat kein Datum.');
}

$tz = new \DateTimeZone((string) (option('kinemathek.ics.timezone') ?? 'Europe/Berlin'));

// DTEND from endDate when present (multi-day events), else default duration.
$end = null;
if ($page->endDate()->isNotEmpty()) {
    try {
        $end = new \DateTime($page->endDate()->value(), $tz);
    } catch (\Throwable $e) {
        $end = null;
    }
}
if ($end === null || $end <= $start) {
    $runtime = max(1, (int) (option('kinemathek.ics.defaultDuration') ?? 120));
    $end = (clone $start)->modify('+' . $runtime . ' minutes');
}

$parts = [];
if ($page->sonderinfo()->isNotEmpty()) {
    $parts[] = $page->sonderinfo()->value();
}
if ($page->text()->isNotEmpty()) {
    $parts[] = $page->text()->value();
}
if ($page->freeAdmission()->toBool()) {
    $parts[] = t('kinemathek.free', 'Freier Eintritt');
} elseif ($page->ticketUrl()->isNotEmpty()) {
    $parts[] = t('kinemathek.tickets', 'Tickets') . ': ' . $page->ticketUrl()->value();
}

$ics = Ics::build([
    'uid'         => $page->icsUid(),
    'start'       => $start,
    'end'         => $end,
    'summary'     => $page->title()->value(),
    'description' => implode("\n\n", $parts),
    'location'    => $page->venue()->isNotEmpty() ? $page->venue()->value() : 'Kinemathek Karlsruhe',
    'url'         => (string) $page->url(),
    'tzid'        => option('kinemathek.ics.timezone') ?? 'Europe/Berlin',
]);

echo Ics::respond($kirby, $ics, $page->icsFilename());
