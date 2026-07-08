<?php

use Kinemathek\Kinemathek;

/**
 * Single Showing — Monatsblatt design (SPEC §2.2 / §3): the program's detail
 * panel as a standalone card. All info + Tickets/.ics/film actions, plus
 * other upcoming dates of the same film.
 *
 * @var \Kirby\Cms\Page $page
 * @var \Kirby\Cms\Page|null $film
 * @var bool $isPast
 * @var \Kirby\Cms\Pages $otherShowings
 */

$ts = $page->timestamp();

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
$still = $film ? ($film->stills()->toFiles()->first() ?? $film->posterFile()) : null;
?>
<?php snippet('header', ['languageNav' => false]) ?>
<div class="sheet">

  <?php snippet('monatsblatt-masthead', ['active' => 'program']) ?>

  <div id="pivot-content">

  <article class="single-card">
    <div class="detail">
      <header class="d-head">
        <span class="d-date"><?= $ts ? html(Kinemathek::localDate($ts, 'detail')) : '' ?></span>
        <?php if ($ts): ?><span class="d-time"><?= date('G', $ts) ?><sup><?= date('i', $ts) ?></sup></span><?php endif ?>
        <span class="vtag <?= $page->venueKey() ?>"><?= html($page->venueLabel()) ?></span>
        <?php if ($isPast): ?><span class="past-tag"><?= html(t('kinemathek.past', '(vergangen)')) ?></span><?php endif ?>
      </header>
      <?php
      $series    = $film ? (Kinemathek::splitField($film->series())[0] ?? '') : '';
      $seriesUrl = $series !== '' ? Kinemathek::seriesPage($series)?->url() : null;
      ?>
      <?php if ($series !== ''): ?>
        <p class="d-series"><?php if ($seriesUrl): ?><a href="<?= $seriesUrl ?>"><?= html($series) ?></a><?php else: ?><?= html($series) ?><?php endif ?></p>
      <?php endif ?>
      <h2 class="d-title"><?= html($page->displayTitle()) ?></h2>
      <?php if ($credits !== ''): ?><p class="d-credits"><?= html($credits) ?></p><?php endif ?>
      <?php
      $flags = [];
      foreach (Kinemathek::splitField($page->subtitles()) as $sub) {
          $flags[] = t('kinemathek.version.' . strtolower($sub), $sub);
      }
      if ($page->hasDiscussion()->toBool()) {
          $flags[] = t('kinemathek.mb.legend.talk');
      }
      ?>
      <?php if ($flags !== []): ?>
        <ul class="d-flags"><?php foreach ($flags as $flag): ?><li><?= html($flag) ?></li><?php endforeach ?></ul>
      <?php endif ?>
      <?php if ($page->sonderinfo()->isNotEmpty()): ?>
        <div class="d-note"><?= $page->sonderinfo()->kt() ?></div>
      <?php endif ?>
      <?php if ($still): ?>
        <figure class="d-still">
          <img src="<?= $still->resize(900)->url() ?>" alt="<?= $still->alt()->or($page->displayTitle())->esc() ?>">
        </figure>
      <?php endif ?>
      <?php if ($film && $film->synopsis()->isNotEmpty()): ?>
        <p class="d-syn"><?= nl2br(html(trim($film->synopsis()->value()))) ?></p>
      <?php endif ?>
      <p class="d-actions">
        <?php if (!$isPast): ?>
          <?php if ($page->freeAdmission()->toBool()): ?>
            <span class="free"><?= html(t('kinemathek.free', 'Freier Eintritt')) ?></span>
          <?php elseif ($page->ticketUrl()->isNotEmpty()): ?>
            <a class="btn" href="<?= $page->ticketUrl()->esc() ?>" rel="noopener noreferrer"><?= html(t('kinemathek.tickets.mars', 'Tickets (Mars EDV)')) ?></a>
          <?php endif ?>
          <?php snippet('add-to-calendar', ['page' => $page, 'class' => 'btn']) ?>
        <?php endif ?>
        <?php if ($film): ?>
          <a class="btn" href="<?= $film->url() ?>"><?= html(t('kinemathek.mb.filmpage')) ?></a>
        <?php endif ?>
        <?php if ($seriesUrl): ?>
          <a class="btn" href="<?= $seriesUrl ?>"><?= html(t('kinemathek.mb.seriespage', 'Zur Reihe')) ?></a>
        <?php endif ?>
        <a class="btn" href="<?= $site->url() ?>"><?= html(t('kinemathek.program', 'Spielplan')) ?></a>
      </p>
      <?php if ($otherShowings->count() > 0): ?>
        <p class="d-others">
          <strong><?= html(t('kinemathek.showing.others', 'Weitere Termine dieses Films')) ?>:</strong>
          <?php foreach ($otherShowings as $other): ?>
            <a href="<?= $other->url() ?>"><?= html($other->date()->localDate('short')) ?></a>
          <?php endforeach ?>
        </p>
      <?php endif ?>
    </div>
  </article>

  <?php snippet('monatsblatt-colophon') ?>

  </div><?php /* /#pivot-content */ ?>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/monatsblatt.js', 'assets/js/program.js']]) ?>
