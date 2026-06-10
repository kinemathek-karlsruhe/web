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

  <?php snippet('monatsblatt-masthead', ['active' => $page->slug(), 'sub' => false]) ?>

  <div id="pivot-content">

  <article class="text-page">
    <?php if ($page->intro()->isNotEmpty()): ?>
      <p class="intro"><?= $page->intro()->esc() ?></p>
    <?php endif ?>
    <?php if ($image = $page->content()->get('mainimage')->toFile()): ?>
      <figure class="tp-image">
        <img src="<?= $image->resize(1200)->url() ?>" alt="<?= $image->alt()->or($page->title())->esc() ?>">
      </figure>
    <?php endif ?>
    <div class="prose-mb"><?= $page->text()->kt() ?></div>
  </article>

  <?php snippet('monatsblatt-colophon') ?>

  </div><?php /* /#pivot-content */ ?>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/monatsblatt.js', 'assets/js/program.js']]) ?>
