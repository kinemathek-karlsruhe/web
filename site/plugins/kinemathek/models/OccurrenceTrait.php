<?php

namespace Kinemathek;

use Kirby\Cms\Page;

/**
 * Shared "dated occurrence" behaviour for Showing and Event pages (SPEC §2.2/§2.3):
 * a `date` field that drives program ordering and the future/past split, plus a
 * default (no) film link so the facet logic can call film() uniformly.
 * ShowingPage overrides film() to resolve its linked Film.
 */
trait OccurrenceTrait
{
    /** Linked Film, or null. Overridden by ShowingPage. */
    public function film(): ?Page
    {
        return null;
    }

    /** UNIX timestamp of this occurrence, or null. Reads the `date` field. */
    public function timestamp(): ?int
    {
        $date = $this->date();
        return $date->isEmpty() ? null : $date->toDate();
    }

    /** Has this occurrence already happened? */
    public function isPast(bool $includeToday = true): bool
    {
        $ts = $this->timestamp();
        return $ts !== null && $ts < Kinemathek::now($includeToday);
    }

    /**
     * Venue classification from the free-text `venue` field:
     * 'box' when it names the Box, 'saal' when it names the Saal or is empty
     * (the house default), 'unterwegs' for everything else — Open Air on the
     * Kronenplatz, Stadtbibliothek, any pop-up location.
     */
    public function venueKey(): string
    {
        $venue = trim($this->venue()->value() ?? '');
        if (stripos($venue, 'box') !== false) {
            return 'box';
        }
        if ($venue === '' || stripos($venue, 'saal') !== false) {
            return 'saal';
        }
        return 'unterwegs';
    }

    /** Display label for the venue tag (Saal/Box are proper names). */
    public function venueLabel(): string
    {
        return match ($this->venueKey()) {
            'box'       => 'Box',
            'unterwegs' => t('kinemathek.venue.unterwegs', 'Unterwegs'),
            default     => 'Saal',
        };
    }
}
