<?php
/**
 * Textseite — simple prose page in the Monatsblatt shell. Listed top-level
 * text pages appear in the pivot strip automatically (their slug is the
 * pivot key); children/unlisted pages lead with their own title.
 *
 * Unterseiten erscheinen — wie auf der Bereichsseite — als Karteikarten
 * (snippet `subpage-cards`). Die Reihen-Übersicht baute dieses Raster früher
 * von Hand im Kirbytext (##-Überschrift + (image:) + Kurztext je Reihe, hier
 * an den ##-Grenzen zerlegt); das ist zugunsten des automatischen Rasters
 * entfallen — siehe scripts/migrate-reihen-cards.php.
 *
 * @var \Kirby\Cms\Page $page
 */
?>
<?php snippet('header', ['languageNav' => false]) ?>
<div class="sheet">

  <?php snippet('monatsblatt-masthead', ['active' => $page->slug()]) ?>

  <div id="pivot-content">

  <article class="text-page">
    <?php if ($page->intro()->isNotEmpty()): ?>
      <p class="intro"><?= $page->intro()->kti() ?></p>
    <?php endif ?>
    <?php if ($image = $page->content()->get('mainimage')->toFile()): ?>
      <figure class="tp-image">
        <img src="<?= $image->resize(1200)->url() ?>" alt="<?= $image->alt()->or($page->title())->esc() ?>">
      </figure>
    <?php endif ?>
    <?php if ($page->text()->isNotEmpty()): ?>
      <div class="prose-mb"><?= $page->text()->kt() ?></div>
    <?php endif ?>
  </article>

  <?php snippet('subpage-cards', [
      'pages' => $page->children()->listed(),
      'label' => $page->title()->value(),
  ]) ?>

  <?php snippet('monatsblatt-colophon') ?>

  </div><?php /* /#pivot-content */ ?>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/monatsblatt.js', 'assets/js/program.js']]) ?>
