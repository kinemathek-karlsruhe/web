<?php
/**
 * Bereichsseite — section landing in the Monatsblatt shell: intro, the
 * upcoming program filtered to the page's categories (day-grouped, same
 * listing as the Spielplan), subpage links, optional closing text.
 *
 * Zwei-Spalten-Layout: Inhalt links (2/3), Bilder rechts (1/3) — aber nur,
 * wenn Bilder hochgeladen sind; sonst volle Breite wie bisher.
 *
 * @var \Kirby\Cms\Page $page
 * @var array  $days
 * @var array  $dayMeta
 * @var string $todayKey
 * @var bool   $configured  page is scoped by category and/or a pre-filter
 */
$bilder = $page->bilder()->toFiles();
?>
<?php snippet('header', ['languageNav' => false]) ?>
<style>
  .two-cols {
    display: grid;
    grid-template-columns: 2fr 1fr;   /* 2/3 + 1/3 */
    align-items: start;
    gap: 1.4rem 2.4rem;
  }
  .tc-main { min-width: 0; }
  .tc-side {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
    gap: 0.6rem;
    align-items: start;
    margin: 0;
  }
  .tc-img {
    margin: 0;
    aspect-ratio: 1 / 1;        /* quadratischer Kasten */
    background: transparent;
    border: 1px solid var(--line);
    padding: 0.4rem;
  }
  .tc-img a {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
  }
  .tc-img img {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    display: block;
    object-fit: contain;        /* Originalformat, nicht beschnitten */
  }
  @media (max-width: 720px) {
    .two-cols { grid-template-columns: 1fr; }
  }
</style>
<div class="sheet">

  <?php snippet('monatsblatt-masthead', ['active' => $page->slug()]) ?>

  <div id="pivot-content">

  <?php /* ── Oberer Teil: ganzspaltig ──────────────────────────────── */ ?>

  <article class="text-page collection-intro">
    <?php if ($page->intro()->isNotEmpty()): ?>
      <p class="intro"><?= $page->intro()->kti() ?></p>
    <?php endif ?>
  </article>

  <?php if ($days !== []): ?>
    <?php snippet('monatsblatt-listing', [
        'days'     => $days,
        'dayMeta'  => $dayMeta,
        'todayKey' => $todayKey,
    ]) ?>
  <?php elseif ($configured): ?>
    <p class="collection-none"><?= html(t('kinemathek.mb.collection.none')) ?></p>
  <?php endif ?>

  <?php if ($page->children()->listed()->count() > 0): ?>
    <nav class="subpage-list" aria-label="<?= $page->title()->esc() ?>">
      <?php foreach ($page->children()->listed() as $child): ?>
        <a class="subpage-link" href="<?= $child->url() ?>">
          <span class="sp-title"><?= html($child->title()) ?></span>
          <?php if ($child->intro()->isNotEmpty()): ?>
            <span class="sp-intro"><?= $child->intro()->excerpt(120) ?></span>
          <?php endif ?>
        </a>
      <?php endforeach ?>
    </nav>
  <?php endif ?>

  <?php /* ── Ab hier: Text links, Bilder rechts (nur wenn Bilder da) ── */ ?>

  <?php if ($bilder->count() > 0): ?>
  <div class="two-cols">
    <div class="tc-main">
      <?php if ($page->text()->isNotEmpty()): ?>
        <article class="text-page">
          <div class="prose-mb"><?= $page->text()->kt() ?></div>
        </article>
      <?php endif ?>
    </div><?php /* /.tc-main */ ?>
    <aside class="tc-side">
      <?php foreach ($bilder as $bild): ?>
        <?php $caption = $bild->alt()->or($bild->filename())->esc() ?>
        <figure class="tc-img">
          <?php if ($bild->link()->isNotEmpty()): /* eigener Link → öffnet die Adresse */ ?>
            <a href="<?= $bild->link()->esc() ?>" rel="noopener noreferrer" target="_blank">
          <?php else: /* kein Link → Lightbox */ ?>
            <a href="<?= $bild->url() ?>" data-fancybox="collection-bilder" data-caption="<?= $caption ?>">
          <?php endif ?>
            <img
              src="<?= $bild->resize(300)->url() ?>"
              alt="<?= $caption ?>"
              loading="lazy"
            >
          </a>
        </figure>
      <?php endforeach ?>
    </aside>
  </div><?php /* /.two-cols */ ?>
  <?php elseif ($page->text()->isNotEmpty()): /* keine Bilder → Text ganzspaltig */ ?>
    <article class="text-page">
      <div class="prose-mb"><?= $page->text()->kt() ?></div>
    </article>
  <?php endif ?>

  <?php snippet('monatsblatt-colophon') ?>

  </div><?php /* /#pivot-content */ ?>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/monatsblatt.js', 'assets/js/program.js']]) ?>
