<?php
/**
 * Partner-Seite — Text links, Logos/Bilder rechts.
 * Ähnlich dem Film-Layout (fp-head), aber mit einer Logo-Galerie
 * statt Filmplakat.
 *
 * @var \Kirby\Cms\Page $page
 */
$cols = $page->logoColumns()->or('2')->value();
?>
<?php snippet('header', ['languageNav' => false]) ?>
<div class="sheet">

  <?php snippet('monatsblatt-masthead', ['active' => $page->slug()]) ?>

  <div id="pivot-content">

  <article class="partner-page">
    <div class="pp-layout">

      <div class="pp-text">
        <?php if ($page->intro()->isNotEmpty()): ?>
          <p class="intro"><?= $page->intro()->kti() ?></p>
        <?php endif ?>
        <div class="prose-mb"><?= $page->text()->kt() ?></div>
      </div>

      <?php $logos = $page->logos()->toFiles() ?>
      <?php if ($logos->count() > 0): ?>
        <aside class="pp-logos" data-cols="<?= html($cols) ?>">
          <?php foreach ($logos as $logo): ?>
            <?php $href = $logo->link()->isNotEmpty() ? $logo->link()->esc() : null ?>
            <figure class="pp-logo">
              <?php if ($href): ?>
                <a href="<?= $href ?>" rel="noopener noreferrer" target="_blank">
              <?php endif ?>
              <img
                src="<?= $logo->url() ?>"
                alt="<?= $logo->alt()->or($logo->filename())->esc() ?>"
                loading="lazy"
              >
              <?php if ($href): ?></a><?php endif ?>
            </figure>
          <?php endforeach ?>
        </aside>
      <?php endif ?>

    </div>
  </article>

  <?php snippet('monatsblatt-colophon') ?>

  </div><?php /* /#pivot-content */ ?>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/monatsblatt.js', 'assets/js/program.js']]) ?>
