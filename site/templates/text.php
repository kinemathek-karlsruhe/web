<?php
/**
 * Textseite — simple prose page in the Monatsblatt shell. Listed top-level
 * text pages appear in the pivot strip automatically (their slug is the
 * pivot key); children/unlisted pages lead with their own title.
 *
 * @var \Kirby\Cms\Page $page
 */
?>
<?php snippet('header', ['languageNav' => false]) ?>
<div class="sheet">

  <?php snippet('monatsblatt-masthead', ['active' => $page->slug()]) ?>

  <div id="pivot-content">

  <?php
  /* Reihen-Übersicht: der Text ist ein regelmäßiger Katalog — je Reihe ein
     Block aus ##-Überschrift + Bild + Kurztext, durch --- getrennt. Statt
     einer einzigen langen Spalte teilen wir ihn an den ##-Grenzen in
     einzelne Karten und legen sie auf dem Desktop in ein zweispaltiges
     Raster mit ZEILENWEISER Leserichtung (1 oben-links, 2 oben-rechts …).
     Das Entfernen der ---Trenner heilt zugleich den Setext-Fehler, bei dem
     ein Kurztext direkt über --- fälschlich zur Überschrift würde. */
  $reihenCards = null;
  if ($page->slug() === 'reihen' && $page->text()->isNotEmpty()) {
      $reihenCards = array_values(array_filter(array_map(
          fn ($c) => preg_replace('/\n-{3,}\s*$/', '', rtrim($c)),
          preg_split('/\n(?=##)/', trim($page->text()->value()))
      ), fn ($c) => trim($c) !== ''));
  }
  ?>
  <article class="text-page<?= $reihenCards ? ' text-reihen' : '' ?>">
    <?php if ($page->intro()->isNotEmpty()): ?>
      <p class="intro"><?= $page->intro()->kti() ?></p>
    <?php endif ?>
    <?php if ($image = $page->content()->get('mainimage')->toFile()): ?>
      <figure class="tp-image">
        <img src="<?= $image->resize(1200)->url() ?>" alt="<?= $image->alt()->or($page->title())->esc() ?>">
      </figure>
    <?php endif ?>
    <?php if ($reihenCards !== null): ?>
      <div class="reihen-grid">
        <?php foreach ($reihenCards as $chunk): ?>
          <div class="reihe-card prose-mb">
            <?= (new \Kirby\Content\Field($page, 'text', $chunk))->kt() ?>
            <p class="reihe-cta" aria-hidden="true"><?= html(t('kinemathek.mb.seriespage', 'Zur Reihe')) ?></p>
          </div>
        <?php endforeach ?>
      </div>
    <?php else: ?>
      <div class="prose-mb"><?= $page->text()->kt() ?></div>
    <?php endif ?>
  </article>

  <?php snippet('monatsblatt-colophon') ?>

  </div><?php /* /#pivot-content */ ?>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/monatsblatt.js', 'assets/js/program.js']]) ?>
