<?php snippet('header') ?>
<?php
/**
 * Single non-film Event (SPEC §2.3).
 * UI strings via t(); the date via the locale-aware localDate field method.
 *
 * @var bool $isPast
 */
?>
<article class="mx-auto max-w-3xl p-6">
  <h1 class="text-2xl font-bold"><?= html($page->displayTitle()) ?></h1>

  <p class="text-lg"><?= html($page->date()->localDate('long')) ?><?= $isPast ? ' ' . html(t('kinemathek.past', '(vergangen)')) : '' ?></p>

  <?php if ($page->venue()->isNotEmpty()): ?><p><?= html(t('kinemathek.venue', 'Ort')) ?>: <?= html($page->venue()) ?></p><?php endif ?>
  <?php if ($page->hasDiscussion()->toBool()): ?><p><?= html(t('kinemathek.event.discussion', 'Mit Gespräch.')) ?></p><?php endif ?>
  <?php if ($page->text()->isNotEmpty()): ?><div class="prose my-3"><?= $page->text()->kt() ?></div><?php endif ?>

  <p class="my-4">
    <?php if ($page->ticketUrl()->isNotEmpty()): ?>
      <a class="underline font-semibold" href="<?= $page->ticketUrl()->esc() ?>" rel="noopener noreferrer"><?= html(t('kinemathek.tickets', 'Tickets')) ?></a> ·
    <?php endif ?>
    <?php snippet('add-to-calendar', ['page' => $page]) ?>
  </p>
</article>
<?php snippet('footer') ?>
