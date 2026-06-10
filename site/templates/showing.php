<?php snippet('header') ?>
<?php
/**
 * Single Showing (SPEC §2.2 / §3).
 * UI strings via t(); dates via the locale-aware localDate field method.
 *
 * @var \Kirby\Cms\Page|null $film
 * @var bool $isPast
 * @var \Kirby\Cms\Pages $otherShowings
 */
?>
<article class="mx-auto max-w-3xl p-6">
  <h1 class="text-2xl font-bold"><?= html($page->displayTitle()) ?></h1>

  <p class="text-lg"><?= html($page->date()->localDate('long')) ?><?= $isPast ? ' ' . html(t('kinemathek.past', '(vergangen)')) : '' ?></p>

  <?php if ($page->venue()->isNotEmpty()): ?><p><?= html(t('kinemathek.venue', 'Ort')) ?>: <?= html($page->venue()) ?></p><?php endif ?>
  <?php if ($page->subtitles()->isNotEmpty()): ?><p><?= html(t('kinemathek.showing.version', 'Fassung')) ?>: <?= html($page->subtitles()->commaList()) ?></p><?php endif ?>
  <?php if ($page->hasDiscussion()->toBool()): ?><p><?= html(t('kinemathek.showing.discussion', 'Mit Filmgespräch.')) ?></p><?php endif ?>
  <?php if ($page->sonderinfo()->isNotEmpty()): ?><div class="prose my-3"><?= $page->sonderinfo()->kt() ?></div><?php endif ?>

  <?php if ($film): ?>
    <p><?= html(t('kinemathek.showing.film', 'Film')) ?>: <a class="underline" href="<?= $film->url() ?>"><?= html($film->title()) ?></a></p>
  <?php endif ?>

  <p class="my-4">
    <?php if ($page->ticketUrl()->isNotEmpty()): ?>
      <a class="underline font-semibold" href="<?= $page->ticketUrl()->esc() ?>" rel="noopener noreferrer"><?= html(t('kinemathek.tickets.mars', 'Tickets (Mars EDV)')) ?></a> ·
    <?php endif ?>
    <?php snippet('add-to-calendar', ['page' => $page]) ?>
  </p>

  <?php if ($otherShowings->count() > 0): ?>
    <h2 class="font-semibold mt-6"><?= html(t('kinemathek.showing.others', 'Weitere Termine dieses Films')) ?></h2>
    <ul>
      <?php foreach ($otherShowings as $other): ?>
        <li><a class="underline" href="<?= $other->url() ?>"><?= html($other->date()->localDate('short')) ?></a></li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</article>
<?php snippet('footer') ?>
