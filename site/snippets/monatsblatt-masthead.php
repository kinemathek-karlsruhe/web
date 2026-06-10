<?php
/**
 * Monatsblatt masthead — the reusable page header: eyebrow line (+ language
 * switcher), the WP7-pivot section nav, the "im Kino" sub line, the legend
 * and the brand logo (absolutely positioned, never pushes content down).
 * Includes the icon <symbol> defs, so templates using this need no separate
 * monatsblatt-icons include.
 *
 * Usage: snippet('monatsblatt-masthead', [
 *   'active'      => 'program',          // which pivot item is current
 *   'titleMonths' => $titleMonths,       // optional: 'Juni/Juli' (program only)
 *   'titleYear'   => $titleYear,         // optional: '26'
 * ])
 *
 * @var string  $active
 * @var ?string $titleMonths
 * @var ?string $titleYear
 */
$active      = $active ?? 'program';
$titleMonths = $titleMonths ?? null;
$titleYear   = $titleYear ?? null;

// Pivot sections: program first, containers only if they exist. New sections
// (Projekte, Newsletter, …) are one line each.
$pivotItems = array_values(array_filter([
    ['key' => 'program', 'url' => $site->url(),            'label' => t('kinemathek.mb.nav.program')],
    ['key' => 'films',   'url' => $site->find('films')?->url(),  'label' => t('kinemathek.mb.nav.films')],
    ['key' => 'events',  'url' => $site->find('events')?->url(), 'label' => t('kinemathek.mb.nav.events')],
], fn ($item) => $item['url'] !== null));
?>
<?php snippet('monatsblatt-icons') ?>

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
  <h1 class="sr-only"><?= $page->isHomePage() ? html(t('kinemathek.program', 'Spielplan')) : $page->title()->esc() ?> – <?= $site->title()->esc() ?></h1>
  <nav class="pivot" aria-label="<?= html(t('kinemathek.mb.nav')) ?>">
    <div class="pivot-strip">
      <?php foreach ($pivotItems as $item): ?>
        <a class="pivot-item<?= $item['key'] === $active ? ' is-active is-front' : '' ?>"
           href="<?= $item['url'] ?>" data-pivot="<?= $item['key'] ?>"
           <?= $item['key'] === $active ? 'aria-current="page"' : '' ?>>
          <?php if ($item['key'] === 'program' && $titleMonths !== null): ?>
            <span class="pivot-label">
              <span class="lbl lbl-months"><?= html($titleMonths) ?><sup><?= html($titleYear) ?></sup></span>
              <span class="lbl lbl-name" aria-hidden="true"><?= html($item['label']) ?></span>
            </span>
          <?php else: ?>
            <?= html($item['label']) ?>
          <?php endif ?>
        </a>
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
