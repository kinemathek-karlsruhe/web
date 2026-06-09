<?php
/**
 * Primitive "Add to calendar" link → the .ics content representation of the
 * given page. Plain first-party link: no JavaScript, no widget, no cookies.
 *
 * Usage: snippet('add-to-calendar', ['page' => $showingOrEvent])
 *
 * @var \Kirby\Cms\Page $page
 */
?>
<a class="add-to-calendar underline"
   href="<?= $page->url() ?>.ics"
   download="<?= $page->icsFilename() ?>"
   rel="nofollow">In Kalender (.ics)</a>
