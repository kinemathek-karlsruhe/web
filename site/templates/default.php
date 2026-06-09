<?php snippet('header') ?>

  <article class="mx-auto max-w-3xl p-8">
    <h1 class="text-2xl font-bold"><?= $page->title()->esc() ?></h1>
    <?= $page->text()->kt() ?>
  </article>

<?php snippet('footer') ?>
