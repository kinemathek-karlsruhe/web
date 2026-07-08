<?php

use Kinemathek\Kinemathek;

/**
 * Single non-film Event — Monatsblatt design (SPEC §2.3): the program's
 * detail panel as a standalone card.
 *
 * @var \Kirby\Cms\Page $page
 * @var bool $isPast
 */

$ts = $page->timestamp();
// NB: ->image() is a native Page method — read the files field explicitly
$image = $page->content()->get('image')->toFile();
?>
<?php snippet('header', ['languageNav' => false]) ?>
<div class="sheet">

  <?php snippet('monatsblatt-masthead', ['active' => 'events']) ?>

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
      $series    = Kinemathek::splitField($page->keywords())[0] ?? '';
      $seriesUrl = $series !== '' ? Kinemathek::seriesPage($series)?->url() : null;
      ?>
      <?php if ($series !== ''): ?>
        <p class="d-series"><?php if ($seriesUrl): ?><a href="<?= $seriesUrl ?>"><?= html($series) ?></a><?php else: ?><?= html($series) ?><?php endif ?></p>
      <?php endif ?>
      <h2 class="d-title"><?= html($page->displayTitle()) ?></h2>
      <?php
      $flags = [];
      foreach (Kinemathek::splitField($page->subtitles()) as $sub) {
          $flags[] = t('kinemathek.version.' . strtolower($sub), $sub);
      }
      if ($page->hasDiscussion()->toBool()) {
          $flags[] = t('kinemathek.event.discussion', 'Mit Gespräch.');
      }
      ?>
      <?php if ($flags !== []): ?>
        <ul class="d-flags"><?php foreach ($flags as $flag): ?><li><?= html($flag) ?></li><?php endforeach ?></ul>
      <?php endif ?>
      <?php if ($image): ?>
        <figure class="d-still">
          <img src="<?= $image->resize(900)->url() ?>" alt="<?= $image->alt()->or($page->displayTitle())->esc() ?>">
        </figure>
      <?php endif ?>
      <?php if ($page->text()->isNotEmpty()): ?>
        <div class="d-syn"><?= $page->text()->kt() ?></div>
      <?php endif ?>
      <p class="d-actions">
        <?php if (!$isPast): ?>
          <?php if ($page->freeAdmission()->toBool()): ?>
            <span class="free"><?= html(t('kinemathek.free', 'Freier Eintritt')) ?></span>
          <?php elseif ($page->ticketUrl()->isNotEmpty()): ?>
            <a class="btn" href="<?= $page->ticketUrl()->esc() ?>" rel="noopener noreferrer"><?= html(t('kinemathek.tickets', 'Tickets')) ?></a>
          <?php endif ?>
          <?php snippet('add-to-calendar', ['page' => $page, 'class' => 'btn']) ?>
        <?php endif ?>
        <?php if ($seriesUrl): ?>
          <a class="btn" href="<?= $seriesUrl ?>"><?= html(t('kinemathek.mb.seriespage', 'Zur Reihe')) ?></a>
        <?php endif ?>
        <a class="btn" href="<?= $site->find('events')?->url() ?? $site->url() ?>"><?= html(t('kinemathek.mb.nav.events')) ?></a>
      </p>
    </div>
  </article>

  <?php snippet('monatsblatt-colophon') ?>

  </div><?php /* /#pivot-content */ ?>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/monatsblatt.js', 'assets/js/program.js']]) ?>
