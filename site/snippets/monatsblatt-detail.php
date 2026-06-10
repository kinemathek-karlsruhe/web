<?php
/**
 * The slide-down detail panel for one Monatsblatt entry. Rendered collapsed
 * (grid-template-rows: 0fr) as a full-width row of its day grid;
 * assets/js/program.js toggles .open. Same entry-data array as
 * monatsblatt-event.php.
 *
 * @var \Kirby\Cms\Page  $item
 * @var ?\Kirby\Cms\Page $film
 * @var string $detailId
 * @var string $detailDate
 * @var string $venueKey
 * @var string $timeH
 * @var string $timeM
 * @var string $series
 * @var string $title
 * @var string $credits
 * @var array  $marks
 * @var bool   $talk
 * @var string $note
 * @var ?\Kirby\Cms\File $still
 * @var string $synopsis
 */
?>
<div class="event-detail" id="<?= $detailId ?>">
  <div class="clip">
    <div class="detail">
      <header class="d-head">
        <span class="d-date"><?= html($detailDate) ?></span>
        <span class="d-time"><?= $timeH ?><sup><?= $timeM ?></sup></span>
        <span class="vtag <?= $venueKey ?>"><?= $venueKey === 'box' ? 'Box' : 'Saal' ?></span>
        <button type="button" class="d-close" aria-label="<?= html(t('kinemathek.mb.close')) ?>">&times;</button>
      </header>
      <?php if ($series !== ''): ?><p class="d-series"><?= html($series) ?></p><?php endif ?>
      <h3 class="d-title"><?= html($title) ?></h3>
      <?php if ($credits !== ''): ?><p class="d-credits"><?= html($credits) ?></p><?php endif ?>
      <?php if ($marks !== [] || $talk): ?>
        <ul class="d-flags">
          <?php foreach ($marks as $mark): ?>
            <li><?= html($mark['label']) ?></li>
          <?php endforeach ?>
          <?php if ($talk): ?>
            <li><?= html(t('kinemathek.mb.legend.talk')) ?></li>
          <?php endif ?>
        </ul>
      <?php endif ?>
      <?php if ($note !== ''): ?><p class="d-note"><?= html($note) ?></p><?php endif ?>
      <?php if ($still): ?>
        <figure class="d-still">
          <?php /* data-src: fetched on first open (program.js), not on page load —
                   ~45 collapsed panels would otherwise pull every still */ ?>
          <img data-src="<?= $still->resize(900)->url() ?>"
               alt="<?= $still->alt()->or($title)->esc() ?>">
        </figure>
      <?php endif ?>
      <?php if ($synopsis !== ''): ?><p class="d-syn"><?= nl2br(html($synopsis)) ?></p><?php endif ?>
      <p class="d-actions">
        <?php if ($item->ticketUrl()->isNotEmpty()): ?>
          <a class="btn" href="<?= $item->ticketUrl()->esc() ?>" rel="noopener noreferrer"><?= html(t('kinemathek.tickets', 'Tickets')) ?></a>
        <?php endif ?>
        <?php snippet('add-to-calendar', ['page' => $item, 'class' => 'btn']) ?>
        <?php if ($film): ?>
          <a class="btn" href="<?= $film->url() ?>"><?= html(t('kinemathek.mb.filmpage')) ?></a>
        <?php endif ?>
      </p>
    </div>
  </div>
</div>
