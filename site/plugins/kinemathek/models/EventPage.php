<?php

namespace Kinemathek;

use Kirby\Cms\Page;

/**
 * Event — standalone, non-film programming (SPEC §2.3).
 *
 * Reuses the occurrence mechanics (date, future/past) via OccurrenceTrait so it
 * can appear in the unified "what's on" program, but it has NO linked film
 * (the trait's default film() returns null) and is sourced from a separate
 * container, so it never pollutes the film archive.
 */
class EventPage extends Page
{
    use OccurrenceTrait;

    public function displayTitle(): string
    {
        return $this->title()->isNotEmpty()
            ? $this->title()->value()
            : t('kinemathek.event', 'Veranstaltung');
    }
}
