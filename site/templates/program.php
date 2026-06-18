<?php
/**
 * Spielplan — Monatsblatt view (the homepage). A faithful web translation of
 * the printed Showtimes sheet: masthead with pivot nav, hero with Heute-Strip,
 * then the shared listing snippet (filter bar + day-grouped program).
 *
 * @var \Kirby\Cms\Page  $page
 * @var bool   $past       archive mode (screening history) vs upcoming program
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
?>
<?php snippet('header', ['languageNav' => false]) ?>
<div class="sheet">

  <?php snippet('monatsblatt-masthead', [
      'active'      => 'program',
      'titleMonths' => $titleMonths,
      'titleYear'   => $titleYear,
  ]) ?>

  <?php /* everything below the masthead is the pivot's swappable region */ ?>
  <div id="pivot-content">

  <?php if (!$past): ?>
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
            <span class="vtag <?= $item->venueKey() ?>"><?= html($item->venueLabel()) ?></span>
          </a>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </figure>
  <?php endif /* !$past */ ?>

  <?php snippet('monatsblatt-listing', [
      'days'          => $days,
      'dayMeta'       => $dayMeta,
      'todayKey'      => $todayKey,
      'past'          => $past,
      'archiveToggle' => true,  // the Archiv button lives in the filter row (Spielplan only)
  ]) ?>

  <?php snippet('monatsblatt-colophon') ?>

  </div><?php /* /#pivot-content */ ?>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/monatsblatt.js', 'assets/js/program.js']]) ?>
