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
 * @var string  $active       pivot key ('program', 'films', 'events' or a
 *                            top-level static page slug)
 * @var ?string $titleMonths
 * @var ?string $titleYear
 */
$active      = $active ?? 'program';
$titleMonths = $titleMonths ?? null;
$titleYear   = $titleYear ?? null;

// Pivot sections: program + containers first, then every LISTED top-level
// static page (text/collection/custom blueprints) — publishing a page adds
// it to the strip.
$pivotItems = array_values(array_filter([
    ['key' => 'program', 'url' => $site->url(),            'label' => t('kinemathek.mb.nav.program')],
    ['key' => 'films',   'url' => $site->find('films')?->url(),  'label' => t('kinemathek.mb.nav.films')],
    ['key' => 'events',  'url' => $site->find('events')?->url(), 'label' => t('kinemathek.mb.nav.events')],
], fn ($item) => $item['url'] !== null));

foreach ($site->children()->listed() as $sectionPage) {
    if (in_array($sectionPage->intendedTemplate()->name(), ['text', 'collection', 'custom'], true)) {
        $pivotItems[] = [
            'key'   => $sectionPage->slug(),
            'url'   => $sectionPage->url(),
            'label' => $sectionPage->title()->value(),
        ];
    }
}

// Pages outside the strip (children, unlisted) lead with their own title.
$selfTitle = null;
if (!in_array($active, array_column($pivotItems, 'key'), true)) {
    $selfTitle = $page->title()->value();
}
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

<?php /* data-section drives per-section masthead extras (the legend shows
         only for 'program') and is updated by the pivot JS on swaps, so
         everything is always in the markup, never baked out server-side */ ?>
<header class="masthead" data-section="<?= $selfTitle !== null ? 'self' : html($active) ?>">
  <h1 class="sr-only"><?= $page->isHomePage() ? html(t('kinemathek.program', 'Spielplan')) : $page->title()->esc() ?> – <?= $site->title()->esc() ?></h1>
  <nav class="pivot" aria-label="<?= html(t('kinemathek.mb.nav')) ?>">
    <div class="pivot-strip">
      <?php if ($selfTitle !== null): ?>
        <span class="pivot-item is-active is-front" data-pivot="self" aria-current="page"><?= html($selfTitle) ?></span>
      <?php endif ?>
      <?php foreach ($pivotItems as $item): ?>
        <a class="pivot-item<?= $selfTitle === null && $item['key'] === $active ? ' is-active is-front' : '' ?>"
           href="<?= $item['url'] ?>" data-pivot="<?= $item['key'] ?>"
           <?= $selfTitle === null && $item['key'] === $active ? 'aria-current="page"' : '' ?>>
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
      <span class="sep">|</span>
      <span class="vtag unterwegs"><?= html(t('kinemathek.venue.unterwegs', 'Unterwegs')) ?></span> <?= html(t('kinemathek.mb.legend.unterwegs')) ?>
    </p>
  </div>

  <?php snippet('monatsblatt-logo') ?>
</header>
