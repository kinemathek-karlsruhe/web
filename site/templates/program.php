<?php

use Kinemathek\Kinemathek;

/**
 * Spielplan — Monatsblatt view. The designed program page, a faithful web
 * translation of the printed Showtimes sheet (see monatsblatt.html prototype):
 * grey day bars, chronological stream in up to three fixed columns, visible
 * Saal/Box venue tags, client-side quick filters, slide-down detail panels.
 *
 * @var \Kirby\Cms\Page  $page
 * @var array  $days       day key (Y-m-d) => Page[] (Showings + Events)
 * @var array  $dayMeta    day key => [dow, num, month|null, detailDate]
 * @var string $todayKey
 * @var ?string $stripKey
 * @var array  $stripItems
 * @var ?\Kirby\Cms\File $heroFile
 * @var ?string $heroTitle
 * @var string $titleMonths
 * @var string $titleYear
 */

// Saal/Box classification from the free-text venue field — single source for
// entries, strip and filters. (-> OccurrenceTrait::venueKey() once the plugin
// unfreezes.)
$venueKey = fn (\Kirby\Cms\Page $item): string =>
    stripos($item->venue()->value() ?? '', 'box') !== false ? 'box' : 'saal';

// Subtitle markers (multiselect keys): icon?, printed note, original version?
// Label = t('kinemathek.version.<key>').
$markMap = [
    'omu'  => ['icon' => true,  'note' => '',    'omu' => true],
    'omeu' => ['icon' => true,  'note' => '(e)', 'omu' => true],
    'of'   => ['icon' => false, 'note' => 'OF',  'omu' => true],
    'dtf'  => ['icon' => false, 'note' => 'DF',  'omu' => false],
];

// Per-item display data, shared by the entry and its detail panel.
$entryData = function (\Kirby\Cms\Page $item, string $detailDate) use ($venueKey, $markMap): array {
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
        'detailId'   => 'detail-' . str_replace('/', '-', $item->id()),
        'detailDate' => $detailDate,
        'venueKey'   => $venueKey($item),
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
?>
<?php snippet('header') ?>
<?php snippet('monatsblatt-icons') ?>
<div class="sheet">

  <p class="eyebrow"><?= html(t('kinemathek.mb.eyebrow')) ?></p>

  <header class="masthead">
    <h1><?= html($titleMonths) ?><sup><?= html($titleYear) ?></sup><br>
      <span class="thin"><?= html(t('kinemathek.mb.inCinema')) ?></span></h1>

    <div class="legend">
      <p>
        <svg class="icon" aria-hidden="true"><use href="#i-ut"/></svg>
        <?= html(t('kinemathek.version.omu')) ?>
        <span class="sep">|</span>
        <svg class="icon" aria-hidden="true"><use href="#i-ut"/></svg><?= html(t('kinemathek.mb.legend.omuVariants')) ?>
      </p>
      <p>
        <svg class="icon" aria-hidden="true"><use href="#i-talk"/></svg>
        <?= html(t('kinemathek.mb.legend.talk')) ?>
      </p>
      <p>
        <span class="vtag saal">Saal</span> <?= html(t('kinemathek.mb.legend.saal')) ?>
        <span class="sep">|</span>
        <span class="vtag box">Box</span> <?= html(t('kinemathek.mb.legend.box')) ?>
      </p>
    </div>

    <?php snippet('monatsblatt-logo') ?>
  </header>

  <figure class="hero<?= $heroFile ? '' : ' no-image' ?>">
    <?php if ($heroFile): ?>
      <img src="<?= $heroFile->resize(1600)->url() ?>"
           alt="<?= $heroFile->alt()->or($heroTitle)->esc() ?>">
      <figcaption>+ <?= html($heroTitle) ?></figcaption>
    <?php endif ?>
    <?php if ($stripItems !== []): ?>
      <div class="heute-strip">
        <span class="hs-label">
          <?= html($stripKey === $todayKey ? t('kinemathek.mb.today') : t('kinemathek.mb.soon')) ?>
          <?= html($dayMeta[$stripKey]['dow']) ?> <?= $dayMeta[$stripKey]['num'] ?>.
        </span>
        <?php foreach ($stripItems as $item): ?>
          <?php $ts = $item->timestamp(); ?>
          <a class="hs-event" href="#tag-<?= $stripKey ?>">
            <span class="hs-time"><?= date('G', $ts) ?><sup><?= date('i', $ts) ?></sup></span>
            <span class="hs-title"><?= html($item->displayTitle()) ?></span>
            <?php $vk = $venueKey($item); ?>
            <span class="vtag <?= $vk ?>"><?= $vk === 'box' ? 'Box' : 'Saal' ?></span>
          </a>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </figure>

  <nav class="filters" aria-label="<?= html(t('kinemathek.filters.by', 'Filtern nach')) ?>">
    <span class="label"><?= html(t('kinemathek.mb.filter')) ?></span>
    <div class="seg" role="group" aria-label="Saal / Box">
      <button type="button" data-venue="alle" aria-pressed="true"><?= html(t('kinemathek.mb.all')) ?></button>
      <button type="button" data-venue="saal" aria-pressed="false">Saal</button>
      <button type="button" data-venue="box" aria-pressed="false">Box</button>
    </div>
    <button type="button" class="chip" data-flag="omu" aria-pressed="false">
      <svg class="icon" aria-hidden="true"><use href="#i-ut"/></svg> OmU
    </button>
    <button type="button" class="chip" data-flag="talk" aria-pressed="false">
      <svg class="icon" aria-hidden="true"><use href="#i-talk"/></svg> <?= html(t('kinemathek.mb.talk')) ?>
    </button>
    <span class="count" aria-live="polite" data-label="<?= html(t('kinemathek.mb.shows')) ?>"></span>
  </nav>

  <?php /* header.php already opens the document's <main> */ ?>
  <section class="program" id="program">
    <?php if ($days === []): ?>
      <p><?= html(t('kinemathek.program.none', 'Derzeit keine Termine.')) ?></p>
    <?php endif ?>
    <?php foreach ($days as $key => $items): ?>
      <?php
      $meta = $dayMeta[$key];
      $entries = array_map(fn ($item) => $entryData($item, $meta['detailDate']), $items);
      $saal = array_filter($entries, fn ($e) => $e['venueKey'] === 'saal');
      $box  = array_filter($entries, fn ($e) => $e['venueKey'] === 'box');
      ?>
      <section class="day<?= $key === $todayKey ? ' today' : '' ?>" data-date="<?= $key ?>" id="tag-<?= $key ?>">
        <h2 class="daybar">
          <?php if ($meta['month']): ?><span class="month"><?= html($meta['month']) ?>/</span><?php endif ?>
          <span class="dow"><?= html($meta['dow']) ?></span>
          <span class="num"><?= $meta['num'] ?>.</span>
          <?php if ($key === $todayKey): ?><span class="today-tag"><?= html(t('kinemathek.mb.today')) ?></span><?php endif ?>
        </h2>
        <div class="day-events<?= $saal !== [] && $box !== [] ? ' duo' : '' ?>">
          <?php foreach (['saal' => $saal, 'box' => $box] as $venue => $list): ?>
            <?php if ($list === []) continue ?>
            <div class="venue-col" data-venue="<?= $venue ?>">
              <?php foreach ($list as $entry) snippet('monatsblatt-event', $entry) ?>
            </div>
          <?php endforeach ?>
          <?php foreach ($entries as $entry) snippet('monatsblatt-detail', $entry) ?>
        </div>
      </section>
    <?php endforeach ?>
  </section>

  <footer class="colophon">
    <p>
      <strong>Kinemathek Karlsruhe</strong>, Kaiserpassage 6, 76133 Karlsruhe<span class="sep">|</span>
      Kasse: <a href="tel:+4972183189585">Tel. 0721&#8201;-&#8201;83189585</a>,
      Büro: <a href="tel:+4972183189580">0721&#8201;-&#8201;83189580</a>
    </p>
    <p class="support"><?= html(t('kinemathek.mb.support')) ?></p>
  </footer>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/program.js']]) ?>
