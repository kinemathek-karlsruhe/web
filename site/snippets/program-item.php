<?php
/**
 * One row in the chronological program list (a Showing or an Event).
 * Primitive markup — structure only, no design (SPEC §10).
 * UI strings via t(); the date via the locale-aware localDate field method.
 *
 * @var \Kirby\Cms\Page $item
 */
?>
<li class="border-b border-gray-200 py-3">
  <time class="tabular-nums text-sm text-gray-600"><?= html($item->date()->localDate('datetime')) ?></time>
  <span class="text-xs uppercase tracking-wide text-gray-400">[<?= html($item->intendedTemplate()->name()) ?>]</span>
  <a class="font-semibold underline" href="<?= $item->url() ?>"><?= html($item->displayTitle()) ?></a>
  <?php if ($item->venue()->isNotEmpty()): ?> · <?= html($item->venue()) ?><?php endif ?>
  <?php if ($item->categories()->isNotEmpty()): ?> · <span class="text-gray-500"><?= html($item->categories()->commaList()) ?></span><?php endif ?>
  <?php if ($item->hasDiscussion()->toBool()): ?> · <span title="<?= html(t('kinemathek.showing.discussion', 'Mit Filmgespräch.')) ?>">🗣</span><?php endif ?>
  <?php if ($item->freeAdmission()->toBool()): ?>
    · <?= html(t('kinemathek.free', 'Freier Eintritt')) ?>
  <?php elseif ($item->ticketUrl()->isNotEmpty()): ?>
    · <a href="<?= $item->ticketUrl()->esc() ?>" rel="noopener noreferrer"><?= html(t('kinemathek.tickets', 'Tickets')) ?></a>
  <?php endif ?>
  · <?php snippet('add-to-calendar', ['page' => $item]) ?>
</li>
