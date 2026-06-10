<?php
/**
 * Primitive "Add to calendar" link → the .ics content representation of the
 * given page. Plain first-party link: no JavaScript, no widget, no cookies.
 *
 * Usage: snippet('add-to-calendar', ['page' => $showingOrEvent])
 *        optional 'class' overrides the default link styling.
 *
 * @var \Kirby\Cms\Page $page
 * @var ?string $class
 */
?>
<a class="<?= $class ?? 'add-to-calendar underline' ?>"
   href="<?= $page->url() ?>.ics"
   download="<?= $page->icsFilename() ?>"
   rel="nofollow"><?= html(t('kinemathek.calendar', 'In Kalender (.ics)')) ?></a>
