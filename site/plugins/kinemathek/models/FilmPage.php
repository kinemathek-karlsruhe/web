<?php

namespace Kinemathek;

use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;

/**
 * Film — the permanent, canonical record (SPEC §2.1).
 *
 * A Film owns no showings directly; the relationship is stored on the Showing
 * side (Showing.film -> this page). The Film discovers its screenings by
 * reverse lookup, so scheduling a new showing never requires editing the film,
 * and the film stays reachable independently of any showing (SPEC §3).
 */
class FilmPage extends Page
{
    /** Per-request memo: the films archive calls showings() many times per film. */
    protected ?Pages $showingsCache = null;

    /** Per-request memo for the related-events reverse lookup. */
    protected ?Pages $relatedEventsCache = null;

    /** All showings that reference this film, regardless of date. */
    public function showings(): Pages
    {
        // Looked up in the shared film-id => showings map (built once per
        // request) instead of scanning all showings per film — the archive
        // renders hundreds of films, and the per-film scan was its bottleneck.
        return $this->showingsCache ??=
            new Pages(Kinemathek::showingsByFilm()[$this->id()] ?? []);
    }

    /** Upcoming showings — clickable, soonest first (SPEC §3). Includes today. */
    public function upcomingShowings(bool $includeToday = true): Pages
    {
        $boundary = Kinemathek::now($includeToday);
        return $this->showings()
            ->filter(fn (Page $s) => ($ts = $s->timestamp()) !== null && $ts >= $boundary)
            ->sortBy(fn (Page $s) => $s->timestamp(), 'asc');
    }

    /** Past showings — preserved history, most recent first (SPEC §3). */
    public function pastShowings(bool $includeToday = true): Pages
    {
        $boundary = Kinemathek::now($includeToday);
        return $this->showings()
            ->filter(fn (Page $s) => ($ts = $s->timestamp()) !== null && $ts < $boundary)
            ->sortBy(fn (Page $s) => $s->timestamp(), 'desc');
    }

    /** Whether this film currently has any upcoming screening. */
    public function hasUpcoming(bool $includeToday = true): bool
    {
        return $this->upcomingShowings($includeToday)->count() > 0;
    }

    /**
     * Events that reference this film via their optional `relatedFilm` field
     * (event-first programming that happens to show the film, SPEC §2.3) —
     * most recent first. Reverse lookup like showings(), but deliberately
     * SEPARATE from it: related events are not screenings, so they never feed
     * hasUpcoming(), the archive facets or the screening history.
     */
    public function relatedEvents(): Pages
    {
        if ($this->relatedEventsCache !== null) {
            return $this->relatedEventsCache;
        }

        $id = $this->id();
        return $this->relatedEventsCache = Kinemathek::events()
            ->filter(function (Page $event) use ($id) {
                $film = $event->relatedFilm();
                return $film !== null && $film->id() === $id;
            })
            ->sortBy(fn (Page $e) => $e->timestamp() ?? 0, 'desc');
    }

    /** Upcoming related events — soonest first (shown on the film page). */
    public function upcomingRelatedEvents(bool $includeToday = true): Pages
    {
        $boundary = Kinemathek::now($includeToday);
        return $this->relatedEvents()
            ->filter(fn (Page $e) => ($ts = $e->timestamp()) !== null && $ts >= $boundary)
            ->sortBy(fn (Page $e) => $e->timestamp(), 'asc');
    }

    /**
     * Whether ANY screening of this film has/had a Filmgespräch — backs the
     * archive facet "films that have a discussion" (SPEC §5). Discussion is a
     * per-showing attribute, so it is resolved across the film's showings.
     */
    public function hasDiscussionShowing(): bool
    {
        return $this->showings()->filter(fn (Page $s) => $s->hasDiscussion()->toBool())->count() > 0;
    }

    /** Whether ANY screening of this film has subtitles (archive facet, SPEC §5). */
    public function hasSubtitledShowing(): bool
    {
        return $this->showings()->filter(fn (Page $s) => $s->subtitles()->isNotEmpty())->count() > 0;
    }

    /**
     * Directors stored structurally (SPEC §2.1) to enable director search later.
     * Each entry exposes a `name` field (+ optional tmdbpersonid).
     */
    public function directors()
    {
        return $this->content()->get('directors')->toStructure();
    }

    /** The locally stored poster file (first-party, never image.tmdb.org), or null. */
    public function posterFile(): ?File
    {
        return $this->content()->get('poster')->toFile();
    }
}
