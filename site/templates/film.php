<?php

use Kinemathek\Kinemathek;

/**
 * Single Film page — Monatsblatt design (SPEC §3): big typographic head with
 * the poster alongside, synopsis, Fancybox stills, then upcoming (clickable)
 * and past (history-only) showings as listing-style rows.
 *
 * @var \Kirby\Cms\Page $page
 * @var \Kirby\Cms\Pages $upcoming
 * @var \Kirby\Cms\Pages $past
 * @var \Kirby\Cms\Pages $events
 * @var \Kirby\Content\Structure $directors
 */

// Credits line, print style (same dialect as the listing snippet —
// -> FilmPage::creditsLine() once the plugin unfreezes)
$names = [];
foreach ($directors as $director) {
    $names[] = (string)$director->name();
}
$credits = implode(', ', array_filter($names));
$tail = trim(implode('/', array_map('strtoupper', Kinemathek::splitField($page->country())))
    . ' ' . $page->year()->value());
if ($tail !== '') {
    $credits .= ($credits !== '' ? ', ' : '') . $tail;
}
if ($page->runtime()->isNotEmpty()) {
    $credits .= ($credits !== '' ? '; ' : '') . $page->runtime()->value() . '′';
}

// Subtitle markers, same dialect as the Monatsblatt listing ($markMap there —
// wants to be a shared helper once the plugin unfreezes): icon?, printed note
$markMap = [
    'omu'  => ['icon' => true,  'note' => ''],
    'omeu' => ['icon' => true,  'note' => '(e)'],
    'of'   => ['icon' => false, 'note' => 'OF'],
    'dtf'  => ['icon' => false, 'note' => 'DF'],
];

$showRow = function (\Kirby\Cms\Page $showing, bool $clickable) use ($markMap) {
    $ts = $showing->timestamp();
    $marks = [];
    foreach (Kinemathek::splitField($showing->subtitles()) as $sub) {
        $key     = strtolower($sub);
        $marks[] = ($markMap[$key] ?? ['icon' => false, 'note' => $sub])
            + ['label' => t('kinemathek.version.' . $key, $sub)];
    }
    $talk = $showing->hasDiscussion()->toBool();
    ?>
    <li class="show-row<?= $clickable ? '' : ' past' ?>">
      <span class="sr-date">
        <?php if ($clickable): ?><a href="<?= $showing->url() ?>"><?php endif ?>
        <?= html(rtrim(Kinemathek::localDate($ts, 'detail'), '.')) ?>
        <?php if ($clickable): ?></a><?php endif ?>
      </span>
      <span class="time"><?= date('G', $ts) ?><sup><?= date('i', $ts) ?></sup></span>
      <span class="vtag <?= $showing->venueKey() ?>"><?= html($showing->venueLabel()) ?></span>
      <?php if ($marks !== [] || $talk): ?>
        <span class="sr-subs">
          <?php foreach ($marks as $mark): ?>
            <?php if ($mark['icon']): ?>
              <svg class="icon" role="img" aria-label="<?= html($mark['label']) ?>"><use href="#i-ut"/></svg><?php if ($mark['note'] !== ''): ?><span class="ut-note"><?= html($mark['note']) ?></span><?php endif ?>
            <?php else: ?>
              <span class="ut-note" title="<?= html($mark['label']) ?>"><?= html($mark['note']) ?></span>
            <?php endif ?>
          <?php endforeach ?>
          <?php if ($talk): ?>
            <svg class="icon" role="img" aria-label="<?= html(t('kinemathek.mb.legend.talk')) ?>"><use href="#i-talk"/></svg>
          <?php endif ?>
        </span>
      <?php endif ?>
      <?php if ($clickable): ?>
        <span class="sr-actions">
          <?php if ($showing->freeAdmission()->toBool()): ?>
            <span class="free"><?= html(t('kinemathek.free', 'Freier Eintritt')) ?></span>
          <?php elseif ($showing->ticketUrl()->isNotEmpty()): ?>
            <a class="btn" href="<?= $showing->ticketUrl()->esc() ?>" rel="noopener noreferrer"><?= html(t('kinemathek.tickets', 'Tickets')) ?></a>
          <?php endif ?>
          <?php snippet('add-to-calendar', ['page' => $showing, 'class' => 'btn']) ?>
        </span>
      <?php endif ?>
      <?php /* Sonderinfo as KirbyText, upcoming rows only — history stays lean;
               last child so the full-width note wraps under the row line */ ?>
      <?php if ($clickable && $showing->sonderinfo()->isNotEmpty()): ?>
        <div class="sr-note"><?= $showing->sonderinfo()->kt() ?></div>
      <?php endif ?>
    </li>
    <?php
};
?>
<?php snippet('header', ['languageNav' => false]) ?>
<div class="sheet">

  <?php snippet('monatsblatt-masthead', ['active' => 'films']) ?>

  <div id="pivot-content">

  <a class="backlink" href="<?= $site->find('films')?->url() ?? $site->url() ?>">&larr; <?= html(t('kinemathek.mb.allFilms')) ?></a>

  <article class="film-page">
    <div class="fp-head">
      <div class="fp-text">
        <?php if ($page->series()->isNotEmpty()): ?>
          <p class="d-series"><?php
            // each Reihe links to its Bereichsseite, when one is curated for it
            $reihen = [];
            foreach (Kinemathek::splitField($page->series()) as $reihe) {
                $target   = Kinemathek::seriesPage($reihe);
                $reihen[] = $target
                    ? '<a href="' . $target->url() . '">' . html($reihe) . '</a>'
                    : html($reihe);
            }
            echo implode(', ', $reihen);
          ?></p>
        <?php endif ?>
        <h2 class="fp-title"><?= html($page->title()) ?></h2>
        <?php if ($credits !== ''): ?><p class="fp-credits"><?= html($credits) ?></p><?php endif ?>
        <p class="fp-facts">
          <?php if ($page->originalTitle()->isNotEmpty() && $page->originalTitle()->value() !== $page->title()->value()): ?>
            <span><?= html(t('kinemathek.film.originalTitle', 'Originaltitel')) ?>: <?= html($page->originalTitle()) ?></span>
          <?php endif ?>
          <?php if ($page->genre()->isNotEmpty()): ?>
            <span><?= html($page->genre()->commaList()) ?></span>
          <?php endif ?>
          <?php if ($page->language()->isNotEmpty()): ?>
            <span><?= html(t('kinemathek.film.language', 'Sprache')) ?>: <?= html(strtoupper($page->language()->commaList())) ?></span>
          <?php endif ?>
        </p>
        <?php if ($page->synopsis()->isNotEmpty()): ?>
          <div class="fp-syn"><?= $page->synopsis()->kt() ?></div>
        <?php endif ?>
      </div>
      <?php if ($poster = $page->posterFile()): ?>
        <figure class="fp-poster">
          <a href="<?= $poster->url() ?>" data-fancybox="film" data-caption="<?= html($page->title()) ?>">
            <img src="<?= $poster->resize(560)->url() ?>" alt="<?= $poster->alt()->or($page->title())->esc() ?>">
          </a>
        </figure>
      <?php endif ?>
    </div>

    <?php $stills = $page->stills()->toFiles() ?>
    <?php if ($stills->count() > 0): ?>
      <ul class="fp-stills">
        <?php foreach ($stills as $still): ?>
          <li>
            <a href="<?= $still->url() ?>" data-fancybox="film-stills" data-caption="<?= html($still->caption()) ?>">
              <img src="<?= $still->resize(520)->url() ?>" alt="<?= $still->alt()->esc() ?>" loading="lazy">
            </a>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>

    <section class="fp-shows">
      <?php /* "Kommende Vorstellungen" with its "keine Termine" empty state
               only when it carries information: with no upcoming showing but
               an upcoming related event, the message would contradict the
               event date right below — skip the whole block instead. */ ?>
      <?php if ($upcoming->count() > 0 || $events->count() === 0): ?>
        <h3 class="daybar"><span class="dow"><?= html(t('kinemathek.film.upcoming', 'Kommende Vorstellungen')) ?></span></h3>
        <?php if ($upcoming->count() === 0): ?>
          <p class="program-empty" style="display:block"><?= html(t('kinemathek.program.none', 'Derzeit keine Termine.')) ?></p>
        <?php else: ?>
          <ul class="show-list">
            <?php foreach ($upcoming as $showing) $showRow($showing, true) ?>
          </ul>
        <?php endif ?>
      <?php endif ?>

      <?php /* Event-first programming that shows this film (Event.relatedFilm
               reverse lookup) — its own section, NOT part of the screenings:
               the row carries the EVENT's title and links to the event page.
               Upcoming, so it sits before the screening history. */ ?>
      <?php if ($events->count() > 0): ?>
        <h3 class="daybar"><span class="dow"><?= html(t('kinemathek.film.events', 'Veranstaltungen mit diesem Film')) ?></span></h3>
        <ul class="show-list">
          <?php foreach ($events as $event): $ts = $event->timestamp(); ?>
            <li class="show-row">
              <span class="sr-date">
                <a href="<?= $event->url() ?>"><?= html(rtrim(Kinemathek::localDate($ts, 'detail'), '.')) ?></a>
              </span>
              <span class="time"><?= date('G', $ts) ?><sup><?= date('i', $ts) ?></sup></span>
              <span class="vtag <?= $event->venueKey() ?>"><?= html($event->venueLabel()) ?></span>
              <span class="sr-title"><a href="<?= $event->url() ?>"><?= html($event->displayTitle()) ?></a></span>
              <span class="sr-actions">
                <?php if ($event->freeAdmission()->toBool()): ?>
                  <span class="free"><?= html(t('kinemathek.free', 'Freier Eintritt')) ?></span>
                <?php elseif ($event->ticketUrl()->isNotEmpty()): ?>
                  <a class="btn" href="<?= $event->ticketUrl()->esc() ?>" rel="noopener noreferrer"><?= html(t('kinemathek.tickets', 'Tickets')) ?></a>
                <?php endif ?>
                <?php snippet('add-to-calendar', ['page' => $event, 'class' => 'btn']) ?>
              </span>
            </li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>

      <?php if ($past->count() > 0): ?>
        <h3 class="daybar"><span class="dow"><?= html(t('kinemathek.film.past', 'Frühere Vorstellungen')) ?></span></h3>
        <ul class="show-list">
          <?php /* past showings are visible but NOT clickable (SPEC §3) */ ?>
          <?php foreach ($past as $showing) $showRow($showing, false) ?>
        </ul>
      <?php endif ?>
    </section>
  </article>

  <?php $attr = $site->tmdbAttribution(); ?>
  <?php snippet('monatsblatt-colophon', [
      'extra' => html($attr['text']) . ' <a href="' . $attr['url'] . '" rel="noopener noreferrer">themoviedb.org</a>',
  ]) ?>

  </div><?php /* /#pivot-content */ ?>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/monatsblatt.js', 'assets/js/program.js']]) ?>
