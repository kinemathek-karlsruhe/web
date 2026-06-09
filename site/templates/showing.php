<?php snippet('header') ?>
<?php
/**
 * Single Showing (SPEC §2.2 / §3).
 *
 * @var \Kirby\Cms\Page|null $film
 * @var bool $isPast
 * @var \Kirby\Cms\Pages $otherShowings
 */
?>
<article class="mx-auto max-w-3xl p-6">
  <h1 class="text-2xl font-bold"><?= html($page->displayTitle()) ?></h1>

  <p class="text-lg"><?= html($page->date()->toDate('l, d.m.Y · H:i')) ?> Uhr<?= $isPast ? ' (vergangen)' : '' ?></p>

  <?php if ($page->venue()->isNotEmpty()): ?><p>Ort: <?= html($page->venue()) ?></p><?php endif ?>
  <?php if ($page->subtitles()->isNotEmpty()): ?><p>Fassung: <?= html($page->subtitles()->commaList()) ?></p><?php endif ?>
  <?php if ($page->hasDiscussion()->toBool()): ?><p>Mit Filmgespräch.</p><?php endif ?>
  <?php if ($page->sonderinfo()->isNotEmpty()): ?><div class="prose my-3"><?= $page->sonderinfo()->kt() ?></div><?php endif ?>

  <?php if ($film): ?>
    <p>Film: <a class="underline" href="<?= $film->url() ?>"><?= html($film->title()) ?></a></p>
  <?php endif ?>

  <p class="my-4">
    <?php if ($page->ticketUrl()->isNotEmpty()): ?>
      <a class="underline font-semibold" href="<?= $page->ticketUrl()->esc() ?>" rel="noopener noreferrer">Tickets (Mars EDV)</a> ·
    <?php endif ?>
    <?php snippet('add-to-calendar', ['page' => $page]) ?>
  </p>

  <?php if ($otherShowings->count() > 0): ?>
    <h2 class="font-semibold mt-6">Weitere Termine dieses Films</h2>
    <ul>
      <?php foreach ($otherShowings as $other): ?>
        <li><a class="underline" href="<?= $other->url() ?>"><?= html($other->date()->toDate('d.m.Y H:i')) ?> Uhr</a></li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</article>
<?php snippet('footer') ?>
