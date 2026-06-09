<?php snippet('header') ?>
<?php
/**
 * Spielplan — the primary public view (SPEC §5/§8). Primitive: structure only.
 *
 * @var \Kirby\Cms\Pages $program
 * @var array $availableFacets
 * @var array $activeFacets
 * @var int   $totalCount
 */
?>
<section class="mx-auto max-w-4xl p-6">
  <h1 class="text-2xl font-bold mb-4">Spielplan</h1>

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
  <?php foreach ($availableFacets as $facet => $values): ?>
    <?php if (empty($values)) continue; ?>
    <h3 class="mt-2 text-sm uppercase tracking-wide text-gray-500"><?= html($facet) ?></h3>
    <ul class="flex flex-wrap gap-2">
      <?php foreach ($values as $value => $count): ?>
        <li>
          <a class="underline" href="<?= $page->url() ?>?<?= html($facet) ?>=<?= urlencode($value) ?>">
            <?= html($value) ?> (<?= $count ?>)
          </a>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endforeach ?>

  <h2 class="font-semibold mt-6">Programm (<?= $totalCount ?>)</h2>
  <?php if ($program->count() === 0): ?>
    <p class="text-gray-500">Keine passenden Termine.</p>
  <?php else: ?>
    <ol>
      <?php foreach ($program as $item): ?>
        <?php snippet('program-item', ['item' => $item]) ?>
      <?php endforeach ?>
    </ol>
  <?php endif ?>
</section>
<?php snippet('footer') ?>
