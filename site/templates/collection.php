<?php
/**
 * Bereichsseite — section landing in the Monatsblatt shell: intro, the
 * upcoming program filtered to the page's categories (day-grouped, same
 * listing as the Spielplan), subpage links, optional closing text.
 *
 * @var \Kirby\Cms\Page $page
 * @var array  $days
 * @var array  $dayMeta
 * @var string $todayKey
 * @var bool   $configured  page is scoped by category and/or a pre-filter
 */
?>
<?php snippet('header', ['languageNav' => false]) ?>
<div class="sheet">

  <?php snippet('monatsblatt-masthead', ['active' => $page->slug()]) ?>

  <div id="pivot-content">

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

  <?php if ($page->text()->isNotEmpty()): ?>
    <article class="text-page">
      <div class="prose-mb"><?= $page->text()->kt() ?></div>
    </article>
  <?php endif ?>

  <?php snippet('monatsblatt-colophon') ?>

  </div><?php /* /#pivot-content */ ?>

</div>
<?php snippet('footer', ['scripts' => ['assets/js/monatsblatt.js', 'assets/js/program.js']]) ?>
