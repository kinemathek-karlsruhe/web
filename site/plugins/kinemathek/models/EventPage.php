<?php

namespace Kinemathek;

use Kirby\Cms\Page;

/**
 * Event — standalone, event-first programming (SPEC §2.3).
 *
 * Reuses the occurrence mechanics (date, future/past) via OccurrenceTrait so it
 * can appear in the unified "what's on" program, but it has NO linked film
 * (the trait's default film() returns null) and is sourced from a separate
 * container, so it never pollutes the film archive.
 *
 * An event MAY reference a film it shows via the optional `relatedFilm` field
 * (emphasis stays on the event). That reference is deliberately kept OUT of
 * film() — film() feeds the facet logic and the program's detail-panel content,
 * and a related film must not make the event inherit the film's facets,
 * credits or synopsis. It only surfaces the event on the film's page
 * (FilmPage::relatedEvents()) and links the film from the event.
 */
class EventPage extends Page
{
    use OccurrenceTrait;

    /** The optional related Film (shadowing the field accessor, like ShowingPage::film()). */
    public function relatedFilm(): ?Page
    {
        return $this->content()->get('relatedfilm')->toPage();
    }

    public function displayTitle(): string
    {
        return $this->title()->isNotEmpty()
            ? $this->title()->value()
            : t('kinemathek.event', 'Veranstaltung');
    }
}
