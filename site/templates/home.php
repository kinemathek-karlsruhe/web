<?php snippet('header') ?>
<?php
/**
 * Homepage (SPEC §8) — primitive. Surfaces the next screening, featured
 * (festival) programming and a compact Spielplan overview.
 *
 * @var \Kirby\Cms\Page|null $next
 * @var \Kirby\Cms\Pages $featured
 * @var \Kirby\Cms\Pages $overview
 */
?>
<section class="mx-auto max-w-4xl p-6">
  <h1 class="text-3xl font-bold"><?= html($site->title()) ?></h1>

  <?php if ($next): ?>
    <div class="my-6 border border-gray-300 p-4">
      <h2 class="text-sm uppercase tracking-wide text-gray-500">Als Nächstes</h2>
      <p class="text-lg">
        <a class="font-semibold underline" href="<?= $next->url() ?>"><?= html($next->displayTitle()) ?></a>
        — <?= html($next->date()->toDate('D, d.m.Y · H:i')) ?> Uhr
      </p>
    </div>
  <?php endif ?>

  <?php if ($featured->count() > 0): ?>
    <h2 class="text-xl font-bold mt-8">Festivals &amp; Besonderes</h2>
    <ol><?php foreach ($featured as $item): ?><?php snippet('program-item', ['item' => $item]) ?><?php endforeach ?></ol>
  <?php endif ?>

  <h2 class="text-xl font-bold mt-8">Spielplan</h2>
  <?php if ($overview->count() === 0): ?>
    <p class="text-gray-500">Derzeit keine Termine.</p>
  <?php else: ?>
    <ol><?php foreach ($overview as $item): ?><?php snippet('program-item', ['item' => $item]) ?><?php endforeach ?></ol>
    <p class="mt-3"><a class="underline" href="<?= url('program') ?>">Ganzen Spielplan ansehen →</a></p>
  <?php endif ?>

  <p class="mt-8"><a class="underline" href="<?= url('films') ?>">Filmarchiv</a></p>
</section>
<?php snippet('footer') ?>
