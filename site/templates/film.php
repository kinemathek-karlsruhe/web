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

$showRow = function (\Kirby\Cms\Page $showing, bool $clickable) {
    $ts = $showing->timestamp();
    ?>
    <li class="show-row<?= $clickable ? '' : ' past' ?>">
      <span class="sr-date">
        <?php if ($clickable): ?><a href="<?= $showing->url() ?>"><?php endif ?>
        <?= html(rtrim(Kinemathek::localDate($ts, 'detail'), '.')) ?>
        <?php if ($clickable): ?></a><?php endif ?>
      </span>
      <span class="time"><?= date('G', $ts) ?><sup><?= date('i', $ts) ?></sup></span>
      <span class="vtag <?= $showing->venueKey() ?>"><?= html($showing->venueLabel()) ?></span>
      <?php if ($showing->subtitles()->isNotEmpty()): ?>
        <span class="sr-subs"><?= html($showing->subtitles()->commaList()) ?></span>
      <?php endif ?>
      <?php if ($clickable): ?>
        <span class="sr-actions">
          <?php if ($showing->ticketUrl()->isNotEmpty()): ?>
            <a class="btn" href="<?= $showing->ticketUrl()->esc() ?>" rel="noopener noreferrer"><?= html(t('kinemathek.tickets', 'Tickets')) ?></a>
          <?php endif ?>
          <?php snippet('add-to-calendar', ['page' => $showing, 'class' => 'btn']) ?>
        </span>
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
          <p class="d-series"><?= html($page->series()->commaList()) ?></p>
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
      <h3 class="daybar"><span class="dow"><?= html(t('kinemathek.film.upcoming', 'Kommende Vorstellungen')) ?></span></h3>
      <?php if ($upcoming->count() === 0): ?>
        <p class="program-empty" style="display:block"><?= html(t('kinemathek.program.none', 'Derzeit keine Termine.')) ?></p>
      <?php else: ?>
        <ul class="show-list">
          <?php foreach ($upcoming as $showing) $showRow($showing, true) ?>
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
