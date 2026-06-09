<?php snippet('header') ?>
<?php
/**
 * Film archive — permanent back-catalogue (SPEC §5). Films only.
 *
 * @var \Kirby\Cms\Pages $films
 * @var array $availableFacets
 * @var array $activeFacets
 * @var int   $totalCount
 */
?>
<section class="mx-auto max-w-4xl p-6">
  <h1 class="text-2xl font-bold mb-4">Filmarchiv</h1>

  <h2 class="font-semibold mt-4">Aktive Filter</h2>
  <?php if (empty($activeFacets)): ?>
    <p class="text-gray-500">keine</p>
  <?php else: ?>
    <ul>
      <?php foreach ($activeFacets as $name => $value): ?>
        <li><?= html($name) ?>: <?= html($value) ?></li>
      <?php endforeach ?>
    </ul>
    <p><a class="underline" href="<?= $page->url() ?>">Filter zurücksetzen</a></p>
  <?php endif ?>

  <h2 class="font-semibold mt-4">Filtern nach</h2>
  <ul class="flex flex-wrap gap-3">
    <li><a class="underline" href="<?= $page->url() ?>?discussion=1">nur mit Filmgespräch</a></li>
    <li><a class="underline" href="<?= $page->url() ?>?hasSubtitles=1">nur mit Untertiteln</a></li>
  </ul>
  <?php foreach ($availableFacets as $facet => $values): ?>
    <?php if (empty($values)) continue; ?>
    <h3 class="mt-2 text-sm uppercase tracking-wide text-gray-500"><?= html($facet) ?></h3>
    <ul class="flex flex-wrap gap-2">
      <?php foreach ($values as $value => $count): ?>
        <li><a class="underline" href="<?= $page->url() ?>?<?= html($facet) ?>=<?= urlencode($value) ?>"><?= html($value) ?> (<?= $count ?>)</a></li>
      <?php endforeach ?>
    </ul>
  <?php endforeach ?>

  <h2 class="font-semibold mt-6">Filme (<?= $totalCount ?>)</h2>
  <ul>
    <?php foreach ($films as $film): ?>
      <li class="border-b border-gray-200 py-2">
        <a class="font-semibold underline" href="<?= $film->url() ?>"><?= html($film->title()) ?></a>
        <?php if ($film->year()->isNotEmpty()): ?>(<?= html($film->year()) ?>)<?php endif ?>
        <?php if ($film->country()->isNotEmpty()): ?> · <?= html($film->country()->commaList()) ?><?php endif ?>
        <?php if ($film->hasUpcoming()): ?> · <strong class="text-green-700">kommende Termine</strong><?php endif ?>
      </li>
    <?php endforeach ?>
  </ul>
</section>
<?php snippet('footer') ?>
