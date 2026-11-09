<?php
/**
 * Bereichsseite — section landing in the Monatsblatt shell: intro, the
 * upcoming program filtered to the page's categories (day-grouped, same
 * listing as the Spielplan), subpage links, optional closing text.
 *
 * Bilder-Layout, zwei Fälle — beide automatisch, ohne Panel-Schalter:
 *  - mind. ein Bild „Groß" (Plakat/Foto): die Bildspalte (1/3) läuft ab dem
 *    Intro NEBEN der ganzen Seite mit (Intro, Termine, Karten, Text links).
 *    Ist es genau ein großes Bild ohne Logos, bleibt es beim Scrollen
 *    stehen (sticky) — sonst könnte eine lange Bildspalte unerreichbar werden.
 *  - nur kleine Bilder (Logo-Kacheln): Text + Logos erst unter den Terminen,
 *    wie bisher. Ohne Bilder: alles volle Breite.
 *
 * @var \Kirby\Cms\Page $page
 * @var array  $days
 * @var array  $dayMeta
 * @var string $todayKey
 * @var bool   $configured  page is scoped by category and/or a pre-filter
 */
$bilder = $page->bilder()->toFiles();
$gross  = $bilder->filter(fn ($b) => $b->groesse()->value() === 'gross');
$poster = $gross->count() > 0;
$sticky = $gross->count() === 1 && $bilder->count() === 1;

$bilderAside = function () use ($bilder, $sticky) { ?>
    <aside class="tc-side<?= $sticky ? ' is-sticky' : '' ?>">
      <?php foreach ($bilder as $bild): ?>
        <?php $caption = $bild->alt()->or($bild->filename())->esc() ?>
        <?php $isGross = $bild->groesse()->value() === 'gross' ?>
        <figure class="tc-img<?= $isGross ? ' is-large' : '' ?>">
          <?php if ($bild->link()->isNotEmpty()): /* eigener Link → öffnet die Adresse */ ?>
            <a href="<?= $bild->link()->esc() ?>" rel="noopener noreferrer" target="_blank">
          <?php else: /* kein Link → Lightbox */ ?>
            <a href="<?= $bild->url() ?>" data-fancybox="collection-bilder" data-caption="<?= $caption ?>">
          <?php endif ?>
            <img
              src="<?= $bild->resize($isGross ? 900 : 300)->url() ?>"
              alt="<?= $caption ?>"
              loading="lazy"
            >
          </a>
        </figure>
      <?php endforeach ?>
    </aside>
<?php };
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
  /* Darstellung "Groß" (bild.yml): volle Spaltenbreite, natürliches
     Seitenverhältnis statt Logo-Quadrat */
  .tc-img.is-large {
    grid-column: 1 / -1;
    aspect-ratio: auto;
    padding: 0;
  }
  .tc-img.is-large a { display: block; height: auto; }
  .tc-img.is-large img { width: 100%; max-height: none; }
  /* Variante „Plakat": Bildspalte neben der ganzen Seite */
  .two-cols.has-poster { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); }
  .has-poster .tc-side { margin-top: 1.4rem; }   /* auf Höhe des Intro-Texts */
  .has-poster .tc-side.is-sticky { position: sticky; top: 1rem; }
  .has-poster .tc-side.is-sticky .tc-img.is-large img {
    width: auto;
    max-width: 100%;
    max-height: calc(100vh - 2rem);   /* hohe Plakate passen ganz ins Fenster */
  }
  /* die Termine teilen sich nur 2/3 der Breite → eine Spalte weniger */
  @media (min-width: 720px)  { .has-poster .program { grid-template-columns: 1fr; } }
  @media (min-width: 1080px) { .has-poster .program { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 720px) {
    .two-cols, .two-cols.has-poster { grid-template-columns: 1fr; }
    .has-poster .tc-side.is-sticky { position: static; }
  }
</style>
<div class="sheet">

  <?php snippet('monatsblatt-masthead', ['active' => $page->slug()]) ?>

  <div id="pivot-content">

  <?php if ($poster): /* ── Plakat: Bildspalte läuft ab hier neben allem mit ── */ ?>
  <div class="two-cols has-poster">
  <div class="tc-main">
  <?php endif ?>

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

  <?php snippet('subpage-cards', [
      'pages' => $page->children()->listed(),
      'label' => $page->title()->value(),
  ]) ?>

  <?php if ($poster): ?>
    <?php if ($page->text()->isNotEmpty()): ?>
      <article class="text-page">
        <div class="prose-mb"><?= $page->text()->kt() ?></div>
      </article>
    <?php endif ?>
  </div><?php /* /.tc-main */ ?>
  <?php $bilderAside() ?>
  </div><?php /* /.two-cols.has-poster */ ?>

  <?php elseif ($bilder->count() > 0): /* nur Logos → Text links, Logos rechts, unter den Terminen */ ?>
  <div class="two-cols">
    <div class="tc-main">
      <?php if ($page->text()->isNotEmpty()): ?>
        <article class="text-page">
          <div class="prose-mb"><?= $page->text()->kt() ?></div>
        </article>
      <?php endif ?>
    </div><?php /* /.tc-main */ ?>
    <?php $bilderAside() ?>
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
