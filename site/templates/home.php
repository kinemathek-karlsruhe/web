<?php snippet('header') ?>
<?php
/**
 * Homepage (SPEC §8) — primitive. Surfaces the next screening, featured
 * (festival) programming and a compact Spielplan overview.
 * UI strings via t(); internal links via page()->url() so they carry the
 * current language prefix (url('program') would always point at the default).
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
      <h2 class="text-sm uppercase tracking-wide text-gray-500"><?= html(t('kinemathek.home.next', 'Als Nächstes')) ?></h2>
      <p class="text-lg">
        <a class="font-semibold underline" href="<?= $next->url() ?>"><?= html($next->displayTitle()) ?></a>
        — <?= html($next->date()->localDate('datetime')) ?>
      </p>
    </div>
  <?php endif ?>

  <?php if ($featured->count() > 0): ?>
    <h2 class="text-xl font-bold mt-8"><?= html(t('kinemathek.home.featured', 'Festivals & Besonderes')) ?></h2>
    <ol><?php foreach ($featured as $item): ?><?php snippet('program-item', ['item' => $item]) ?><?php endforeach ?></ol>
  <?php endif ?>

  <h2 class="text-xl font-bold mt-8"><?= html(t('kinemathek.program', 'Spielplan')) ?></h2>
  <?php if ($overview->count() === 0): ?>
    <p class="text-gray-500"><?= html(t('kinemathek.program.none', 'Derzeit keine Termine.')) ?></p>
  <?php else: ?>
    <ol><?php foreach ($overview as $item): ?><?php snippet('program-item', ['item' => $item]) ?><?php endforeach ?></ol>
    <?php if ($programPage = page('program')): ?>
      <p class="mt-3"><a class="underline" href="<?= $programPage->url() ?>"><?= html(t('kinemathek.home.fullProgram', 'Ganzen Spielplan ansehen →')) ?></a></p>
    <?php endif ?>
  <?php endif ?>

  <?php if ($filmsPage = page('films')): ?>
    <p class="mt-8"><a class="underline" href="<?= $filmsPage->url() ?>"><?= html(t('kinemathek.archive', 'Filmarchiv')) ?></a></p>
  <?php endif ?>
</section>
<?php snippet('footer') ?>
