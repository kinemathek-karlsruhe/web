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
 * @var array  $reihen     mobile start tiles: ['url','title','file'] each
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

  <?php /* Mobile start (phones only, CSS-gated): curated Reihen tiles in the
           hero's visual language, then the reveal button. The listing below
           stays in the markup for desktop, no-JS and search engines — the
           .mb-fold wrapper is collapsed on phones until the button (or a
           Heute-Strip/deep link) opens it via program.js. */ ?>
  <?php if ($reihen !== []): ?>
    <nav class="reihen" aria-label="<?= html(t('kinemathek.mb.reihen', 'Aktuelle Reihen')) ?>">
      <p class="reihen-label"><?= html(t('kinemathek.mb.reihen', 'Aktuelle Reihen')) ?></p>
      <?php foreach ($reihen as $reihe): ?>
        <a class="reihe-tile" href="<?= $reihe['url'] ?>">
          <figure class="hero<?= $reihe['file'] ? '' : ' no-image' ?>">
            <?php if ($reihe['file']): ?>
              <img src="<?= $reihe['file']->resize(768)->url() ?>"
                   srcset="<?= $reihe['file']->resize(768)->url() ?> 768w,
                           <?= $reihe['file']->resize(1280)->url() ?> 1280w"
                   sizes="100vw" loading="lazy"
                   alt="<?= $reihe['file']->alt()->or($reihe['title'])->esc() ?>">
            <?php endif ?>
            <figcaption>+ <?= html($reihe['title']) ?></figcaption>
          </figure>
        </a>
      <?php endforeach ?>
    </nav>
  <?php endif ?>

  <button class="mb-reveal" type="button" aria-expanded="false">
    <?= html(t('kinemathek.mb.showProgram', 'Spielplan anzeigen')) ?>
    <svg class="icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M2 5l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2"/></svg>
  </button>
  <?php endif /* !$past */ ?>

  <div class="mb-fold<?= $past ? ' open' : '' ?>"><?php /* archive: never folded */ ?>

  <?php snippet('monatsblatt-listing', [
      'days'          => $days,
      'dayMeta'       => $dayMeta,
      'todayKey'      => $todayKey,
      'past'          => $past,
      'archiveToggle' => true,  // the Archiv button lives in the filter row (Spielplan only)
  ]) ?>

  <?php snippet('monatsblatt-colophon') ?>

  </div><?php /* /.mb-fold */ ?>

  </div><?php /* /#pivot-content */ ?>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/monatsblatt.js', 'assets/js/program.js']]) ?>
