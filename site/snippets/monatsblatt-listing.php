<?php

use Kinemathek\Kinemathek;

/**
 * Monatsblatt listing — the filter bar + day-grouped chronological listing
 * (grey day bars, Saal/Box venue columns, slide-down detail panels). Shared
 * by the Spielplan and the Events page; behaviour in assets/js/program.js.
 *
 * Usage: snippet('monatsblatt-listing', [
 *   'days'     => $days,      // day key (Y-m-d) => Page[] (Showings/Events)
 *   'dayMeta'  => $dayMeta,   // day key => [dow, num, month|null, detailDate]
 *   'todayKey' => $todayKey,
 * ])
 *
 * @var array  $days
 * @var array  $dayMeta
 * @var string $todayKey
 * @var bool   $past          archive mode: suppress ticket/calendar CTAs on rows
 * @var bool   $archiveToggle render the Archiv button in the filter row (Spielplan only)
 */
$past          = $past ?? false;
$archiveToggle = $archiveToggle ?? false;

// Subtitle markers (multiselect keys): icon?, printed note, original version?
// Label = t('kinemathek.version.<key>'). The OmU quick filter means
// "subtitled original" — OF (no subtitles) deliberately doesn't match.
$markMap = [
    'omu'  => ['icon' => true,  'note' => '',    'omu' => true],
    'omeu' => ['icon' => true,  'note' => '(e)', 'omu' => true],
    'of'   => ['icon' => false, 'note' => 'OF',  'omu' => false],
    'dtf'  => ['icon' => false, 'note' => 'DF',  'omu' => false],
];

// Per-item display data, shared by the entry and its detail panel.
$entryData = function (\Kirby\Cms\Page $item, string $detailDate) use ($markMap, $past): array {
    $film  = $item->film();
    $ts    = $item->timestamp();
    $isEvent = $item->intendedTemplate()->name() === 'event';

    $marks = [];
    $omu   = false;
    foreach (Kinemathek::splitField($item->subtitles()) as $sub) {
        $key = strtolower($sub);
        if (!isset($markMap[$key])) {
            continue;
        }
        $marks[] = $markMap[$key] + ['label' => t('kinemathek.version.' . $key)];
        $omu = $omu || $markMap[$key]['omu'];
    }

    // Series / Reihe label (from the film; events fall back to their keywords)
    $series = '';
    if ($film && $film->series()->isNotEmpty()) {
        $series = Kinemathek::splitField($film->series())[0] ?? '';
    } elseif ($isEvent) {
        $series = Kinemathek::splitField($item->keywords())[0] ?? '';
    }

    // Credits line, print style: "P. Cortellesi, IT 2024; 118′"
    $credits = '';
    if ($film) {
        $names = [];
        foreach ($film->directors() as $director) {
            $names[] = (string)$director->name();
        }
        $credits = implode(', ', array_filter($names));
        $tail = trim(implode('/', array_map('strtoupper', Kinemathek::splitField($film->country())))
            . ' ' . $film->year()->value());
        if ($tail !== '') {
            $credits .= ($credits !== '' ? ', ' : '') . $tail;
        }
        if ($film->runtime()->isNotEmpty()) {
            $credits .= ($credits !== '' ? '; ' : '') . $film->runtime()->value() . '′';
        }
    }

    // Detail panel artwork: film still > poster > event image
    $still = $film ? ($film->stills()->toFiles()->first() ?? $film->posterFile()) : null;
    if (!$still && $isEvent) {
        // NB: ->image() is a native Page method (returns ?File), so the magic
        // field accessor never fires for the Event's `image` files field.
        $still = $item->content()->get('image')->toFile();
    }

    return [
        'item'       => $item,
        'film'       => $film,
        'isEvent'    => $isEvent,
        'past'       => $past,
        'detailId'   => 'detail-' . str_replace('/', '-', $item->id()),
        'detailDate' => $detailDate,
        'venueKey'   => $item->venueKey(),
        'venueLabel' => $item->venueLabel(),
        'timeH'      => date('G', $ts),
        'timeM'      => date('i', $ts),
        'series'     => $series,
        'title'      => $item->displayTitle(),
        'credits'    => $credits,
        'marks'      => $marks,
        'omu'        => $omu,
        'talk'       => $item->hasDiscussion()->toBool(),
        'note'       => trim($item->sonderinfo()->value() ?? ''),
        'still'      => $still,
        'synopsis'   => trim(($film ? $film->synopsis()->value() : $item->text()->value()) ?? ''),
    ];
};

// One pass over everything: per-day entry data + the distinct Reihen for the
// series filter <select>.
$dayEntries = [];
$allSeries  = [];
foreach ($days as $key => $items) {
    foreach ($items as $item) {
        $entry = $entryData($item, $dayMeta[$key]['detailDate']);
        $dayEntries[$key][] = $entry;
        if ($entry['series'] !== '') {
            $allSeries[$entry['series']] = true;
        }
    }
}
$allSeries = array_keys($allSeries);
usort($allSeries, 'strcasecmp');
?>
<nav class="filters" aria-label="<?= html(t('kinemathek.filters.by', 'Filtern nach')) ?>">
  <span class="label"><?= html(t('kinemathek.mb.filter')) ?></span>
  <div class="seg" role="group" aria-label="Saal / Box / <?= html(t('kinemathek.venue.unterwegs', 'Unterwegs')) ?>">
    <button type="button" data-venue="alle" aria-pressed="true"><?= html(t('kinemathek.mb.all')) ?></button>
    <button type="button" data-venue="saal" aria-pressed="false">Saal</button>
    <button type="button" data-venue="box" aria-pressed="false">Box</button>
    <button type="button" data-venue="unterwegs" aria-pressed="false"><?= html(t('kinemathek.venue.unterwegs', 'Unterwegs')) ?></button>
  </div>
  <button type="button" class="chip" data-flag="omu" aria-pressed="false">
    <svg class="icon" aria-hidden="true"><use href="#i-ut"/></svg> OmU
  </button>
  <button type="button" class="chip" data-flag="talk" aria-pressed="false">
    <svg class="icon" aria-hidden="true"><use href="#i-talk"/></svg> <?= html(t('kinemathek.mb.talk')) ?>
  </button>
  <?php if ($allSeries !== []): ?>
    <label class="reihe">
      <span class="sr-only"><?= html(t('kinemathek.mb.series')) ?></span>
      <select data-filter="series">
        <option value=""><?= html(t('kinemathek.mb.allSeries')) ?></option>
        <?php foreach ($allSeries as $series): ?>
          <option value="<?= html($series) ?>"><?= html($series) ?></option>
        <?php endforeach ?>
      </select>
    </label>
  <?php endif ?>
  <button type="button" class="reset" hidden><?= html(t('kinemathek.filters.reset', 'Filter zurücksetzen')) ?></button>
  <span class="count" aria-live="polite"
        data-label-one="<?= html(t('kinemathek.mb.show')) ?>"
        data-label-many="<?= html(t('kinemathek.mb.shows')) ?>"></span>
  <?php if ($archiveToggle): ?>
    <?php /* mode switch, not a live filter: a plain link to the past view and
             back. Active (aria-current) while viewing the archive. */ ?>
    <a class="chip archive-toggle" href="<?= $past ? $page->url() : $page->url() . '?past=1' ?>"<?= $past ? ' aria-current="page"' : '' ?>><?= html(t('kinemathek.mb.archive', 'Archiv')) ?></a>
  <?php endif ?>
</nav>

<section class="program" id="program">
  <?php if ($days === []): ?>
    <p><?= html($past
        ? t('kinemathek.program.nonePast', 'Noch keine vergangenen Termine.')
        : t('kinemathek.program.none', 'Derzeit keine Termine.')) ?></p>
  <?php endif ?>
  <p class="program-empty" hidden><?= html(t('kinemathek.program.noMatches', 'Keine passenden Termine.')) ?></p>
  <?php foreach ($days as $key => $items): ?>
    <?php
    $meta = $dayMeta[$key];
    $entries = $dayEntries[$key];
    // left column = Saal + Unterwegs (print put open-air entries left),
    // right column = Box
    $left = array_filter($entries, fn ($e) => $e['venueKey'] !== 'box');
    $box  = array_filter($entries, fn ($e) => $e['venueKey'] === 'box');
    ?>
    <section class="day<?= $key === $todayKey ? ' today' : '' ?>" data-date="<?= $key ?>" id="tag-<?= $key ?>">
      <h2 class="daybar">
        <?php if ($meta['month']): ?><span class="month"><?= html($meta['month']) ?>/</span><?php endif ?>
        <span class="dow"><?= html($meta['dow']) ?></span>
        <span class="num"><?= $meta['num'] ?>.</span>
        <?php if ($key === $todayKey): ?><span class="today-tag"><?= html(t('kinemathek.mb.today')) ?></span><?php endif ?>
      </h2>
      <div class="day-events<?= $left !== [] && $box !== [] ? ' duo' : '' ?>">
        <?php foreach ([$left, $box] as $list): ?>
          <?php if ($list === []) continue ?>
          <div class="venue-col">
            <?php foreach ($list as $entry) snippet('monatsblatt-event', $entry) ?>
          </div>
        <?php endforeach ?>
        <?php foreach ($entries as $entry) snippet('monatsblatt-detail', $entry) ?>
      </div>
    </section>
  <?php endforeach ?>
</section>
