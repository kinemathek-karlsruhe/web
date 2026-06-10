<?php snippet('header') ?>
<?php
/**
 * Single Film page (SPEC §3): metadata + upcoming (clickable) and past
 * (non-clickable history) showings. Poster/stills use Fancybox.
 * UI strings via t(); dates via the locale-aware localDate field method.
 *
 * @var \Kirby\Cms\Pages $upcoming
 * @var \Kirby\Cms\Pages $past
 * @var \Kirby\Content\Structure $directors
 */
?>
<article class="mx-auto max-w-3xl p-6">
  <h1 class="text-2xl font-bold"><?= html($page->title()) ?></h1>

  <?php if ($poster = $page->posterFile()): ?>
    <a href="<?= $poster->url() ?>" data-fancybox="film" data-caption="<?= html($page->title()) ?>">
      <img src="<?= $poster->url() ?>" alt="<?= html($page->title()) ?>" class="my-4 max-w-[220px] h-auto">
    </a>
  <?php endif ?>

  <dl class="grid grid-cols-[max-content_1fr] gap-x-4">
    <?php if ($page->originalTitle()->isNotEmpty()): ?><dt class="font-semibold"><?= html(t('kinemathek.film.originalTitle', 'Originaltitel')) ?></dt><dd><?= html($page->originalTitle()) ?></dd><?php endif ?>
    <?php if ($page->year()->isNotEmpty()): ?><dt class="font-semibold"><?= html(t('kinemathek.film.year', 'Jahr')) ?></dt><dd><?= html($page->year()) ?></dd><?php endif ?>
    <?php if ($page->country()->isNotEmpty()): ?><dt class="font-semibold"><?= html(t('kinemathek.film.country', 'Land')) ?></dt><dd><?= html($page->country()->commaList()) ?></dd><?php endif ?>
    <?php if ($page->language()->isNotEmpty()): ?><dt class="font-semibold"><?= html(t('kinemathek.film.language', 'Sprache')) ?></dt><dd><?= html($page->language()->commaList()) ?></dd><?php endif ?>
    <?php if ($page->runtime()->isNotEmpty()): ?><dt class="font-semibold"><?= html(t('kinemathek.film.runtime', 'Laufzeit')) ?></dt><dd><?= html($page->runtime()) ?> min</dd><?php endif ?>
    <?php if ($page->genre()->isNotEmpty()): ?><dt class="font-semibold"><?= html(t('kinemathek.film.genre', 'Genre')) ?></dt><dd><?= html($page->genre()->commaList()) ?></dd><?php endif ?>
  </dl>

  <?php if ($directors->count() > 0): ?>
    <h2 class="font-semibold mt-4"><?= html(t('kinemathek.film.directors', 'Regie')) ?></h2>
    <ul><?php foreach ($directors as $director): ?><li><?= html($director->name()) ?></li><?php endforeach ?></ul>
  <?php endif ?>

  <?php if ($page->synopsis()->isNotEmpty()): ?>
    <h2 class="font-semibold mt-4"><?= html(t('kinemathek.film.synopsis', 'Inhalt')) ?></h2>
    <div class="prose"><?= $page->synopsis()->kt() ?></div>
  <?php endif ?>

  <?php $stills = $page->stills()->toFiles() ?>
  <?php if ($stills->count() > 0): ?>
    <h2 class="font-semibold mt-4"><?= html(t('kinemathek.film.stills', 'Szenenbilder')) ?></h2>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($stills as $still): ?>
        <a href="<?= $still->url() ?>" data-fancybox="film-stills" data-caption="<?= html($still->caption()) ?>">
          <img src="<?= $still->url() ?>" alt="<?= html($still->alt()) ?>" class="h-24 w-auto">
        </a>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <h2 class="font-semibold mt-6"><?= html(t('kinemathek.film.upcoming', 'Kommende Vorstellungen')) ?></h2>
  <?php if ($upcoming->count() === 0): ?>
    <p class="text-gray-500"><?= html(t('kinemathek.program.none', 'Derzeit keine Termine.')) ?></p>
  <?php else: ?>
    <ul>
      <?php foreach ($upcoming as $showing): ?>
        <li class="py-1">
          <a class="underline" href="<?= $showing->url() ?>"><?= html($showing->date()->localDate('datetime')) ?></a>
          <?php if ($showing->subtitles()->isNotEmpty()): ?> · <?= html($showing->subtitles()->commaList()) ?><?php endif ?>
          <?php if ($showing->ticketUrl()->isNotEmpty()): ?> · <a href="<?= $showing->ticketUrl()->esc() ?>" rel="noopener noreferrer"><?= html(t('kinemathek.tickets', 'Tickets')) ?></a><?php endif ?>
          · <?php snippet('add-to-calendar', ['page' => $showing]) ?>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>

  <h2 class="font-semibold mt-6"><?= html(t('kinemathek.film.past', 'Frühere Vorstellungen')) ?></h2>
  <?php if ($past->count() === 0): ?>
    <p class="text-gray-500">—</p>
  <?php else: ?>
    <ul class="text-gray-500">
      <?php foreach ($past as $showing): ?>
        <!-- past showings are visible but NOT clickable (SPEC §3) -->
        <li class="py-1"><?= html($showing->date()->localDate('datetime')) ?><?php if ($showing->subtitles()->isNotEmpty()): ?> · <?= html($showing->subtitles()->commaList()) ?><?php endif ?></li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>

  <?php $attr = $site->tmdbAttribution() ?>
  <p class="text-xs text-gray-400 mt-10"><?= html($attr['text']) ?>
    <a href="<?= $attr['url'] ?>" rel="noopener noreferrer">themoviedb.org</a></p>
</article>
<?php snippet('footer') ?>
