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
// Label = t('kinemathek.version.<key>'). The OmU quick filter means
// "subtitled original" — OF (no subtitles) deliberately doesn't match.
$markMap = [
    'omu'  => ['icon' => true,  'note' => '',    'omu' => true],
    'omeu' => ['icon' => true,  'note' => '(e)', 'omu' => true],
    'of'   => ['icon' => false, 'note' => 'OF',  'omu' => false],
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
<?php snippet('header', ['languageNav' => false]) ?>
<?php snippet('monatsblatt-icons') ?>
<div class="sheet">

  <div class="eyebrow">
    <p><?= html(t('kinemathek.mb.eyebrow')) ?></p>
    <nav class="eyebrow-lang" aria-label="Sprache / Language">
      <?php $first = true; foreach ($kirby->languages() as $lang): ?>
        <?php if (!$first): ?><span class="sep" aria-hidden="true">|</span><?php endif; $first = false; ?>
        <?php if ($kirby->language()?->code() === $lang->code()): ?>
          <span aria-current="true"><?= html($lang->name()) ?></span>
        <?php else: ?>
          <a href="<?= $page->url($lang->code()) ?>"
             hreflang="<?= $lang->code() ?>" lang="<?= $lang->code() ?>"><?= html($lang->name()) ?></a>
        <?php endif ?>
      <?php endforeach ?>
    </nav>
  </div>

  <header class="masthead">
    <h1 class="sr-only"><?= html(t('kinemathek.program', 'Spielplan')) ?> – <?= $site->title()->esc() ?></h1>
    <?php
    // WP7-pivot section nav: the headline line IS the menu. The active
    // section sits at full opacity, the others ghost off to the right;
    // program.js slides the strip and swaps #pivot-content in place.
    $navTargets = array_values(array_filter([
        ['page' => $site->find('films'),  'key' => 'films',  'label' => t('kinemathek.mb.nav.films')],
        ['page' => $site->find('events'), 'key' => 'events', 'label' => t('kinemathek.mb.nav.events')],
    ], fn ($target) => $target['page'] !== null));
    ?>
    <nav class="pivot" aria-label="<?= html(t('kinemathek.mb.nav')) ?>">
      <div class="pivot-strip">
        <a class="pivot-item is-active" href="<?= $site->url() ?>" data-pivot="program" aria-current="page">
          <span class="pivot-label">
            <span class="lbl lbl-months"><?= html($titleMonths) ?><sup><?= html($titleYear) ?></sup></span>
            <span class="lbl lbl-name" aria-hidden="true"><?= html(t('kinemathek.mb.nav.program')) ?></span>
          </span>
        </a>
        <?php foreach ($navTargets as $target): ?>
          <a class="pivot-item" href="<?= $target['page']->url() ?>" data-pivot="<?= $target['key'] ?>"><?= html($target['label']) ?></a>
        <?php endforeach ?>
      </div>
    </nav>
    <p class="mast-sub"><?= html(t('kinemathek.mb.inCinema')) ?></p>

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

  <?php /* everything below the masthead is the pivot's swappable region */ ?>
  <div id="pivot-content">

  <figure class="hero<?= $heroFile ? '' : ' no-image' ?>">
    <?php if ($heroFile): ?>
      <img src="<?= $heroFile->resize(1280)->url() ?>"
           srcset="<?= $heroFile->resize(768)->url() ?> 768w,
                   <?= $heroFile->resize(1280)->url() ?> 1280w,
                   <?= $heroFile->resize(1920)->url() ?> 1920w"
           sizes="(min-width: 1320px) 1232px, 100vw"
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
  </nav>

  <?php /* header.php already opens the document's <main> */ ?>
  <section class="program" id="program">
    <?php if ($days === []): ?>
      <p><?= html(t('kinemathek.program.none', 'Derzeit keine Termine.')) ?></p>
    <?php endif ?>
    <p class="program-empty" hidden><?= html(t('kinemathek.program.noMatches', 'Keine passenden Termine.')) ?></p>
    <?php foreach ($days as $key => $items): ?>
      <?php
      $meta = $dayMeta[$key];
      $entries = $dayEntries[$key];
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

  </div><?php /* /#pivot-content */ ?>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/program.js']]) ?>
