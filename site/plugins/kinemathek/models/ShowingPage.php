<?php

namespace Kinemathek;

use Kirby\Cms\Page;

/**
 * Showing / Veranstaltung — a dated screening of exactly one Film (SPEC §2.2).
 *
 * The Film relationship is stored here as a single-page `film` field. Shared
 * occurrence behaviour (timestamp/isPast) lives in OccurrenceTrait.
 *
 * NOTE: we deliberately do NOT define a ticketUrl() method — that would shadow
 * the `ticketUrl` field accessor that templates and the ICS export rely on
 * ($page->ticketUrl() must return a Field, not a string).
 */
class ShowingPage extends Page
{
    use OccurrenceTrait;

    /** The linked Film page, or null if unset/missing. */
    public function film(): ?Page
    {
        return $this->content()->get('film')->toPage();
    }

    /**
     * Title shown in listings: fall back to the linked film's title.
     * Reads the raw content field — $page->title() would fall back to the slug.
     */
    public function displayTitle(): string
    {
        $own = $this->content()->get('title');
        if ($own->isNotEmpty()) {
            return $own->value();
        }
        $film = $this->film();
        return $film ? $film->title()->value() : 'Vorstellung';
    }
}
