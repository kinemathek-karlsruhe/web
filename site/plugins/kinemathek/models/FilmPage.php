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

    /** All showings that reference this film, regardless of date. */
    public function showings(): Pages
    {
        if ($this->showingsCache !== null) {
            return $this->showingsCache;
        }

        $id = $this->id();
        return $this->showingsCache = Kinemathek::showings()->filter(function (Page $showing) use ($id) {
            $film = $showing->film();
            return $film !== null && $film->id() === $id;
        });
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
