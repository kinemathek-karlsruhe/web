<?php

namespace Kinemathek;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Content\Field;

/**
 * Central backend logic for the Kinemathek program & archive.
 *
 * Everything is static and side-effect free: it only reads local content
 * (SPEC §7 — no outbound calls anywhere on the public path).
 */
class Kinemathek
{
    /**
     * Descriptive facet fields used for FILTERING (SPEC §2.4 / §5).
     *
     * 'on' tells the logic where the value lives:
     *   - 'self'  -> read the showing/event page directly (per-occurrence)
     *   - 'film'  -> read the linked Film page (intrinsic to the work)
     * 'multi' marks multi-value fields (tags / multiselect).
     */
    public const FACETS = [
        'country'   => ['on' => 'film', 'multi' => true],
        'language'  => ['on' => 'film', 'multi' => true],
        'genre'     => ['on' => 'film', 'multi' => true],
        'series'    => ['on' => 'film', 'multi' => true],   // Reihe (lives on the Film)
        'subtitles' => ['on' => 'self', 'multi' => true],   // OmU / OmeU (per showing)
        // keywords are curated on the Film but also allowed per occurrence, so a
        // screening matches on its own AND its film's keywords ('both').
        'keywords'  => ['on' => 'both', 'multi' => true],
    ];

    /**
     * Boundary timestamp for "is this in the future".
     * includeToday = true  -> start of today (today's screenings still count)
     * includeToday = false -> right now
     */
    public static function now(bool $includeToday = true): int
    {
        return $includeToday ? strtotime('today midnight') : time();
    }

    /** The canonical film archive source. Events are NOT here (SPEC §2.3). */
    public static function films(): Pages
    {
        $archive = page('films');
        return $archive ? $archive->children() : new Pages();
    }

    /** Every showing page (the "many" side of Film -> Showings). */
    public static function showings(): Pages
    {
        return site()->index()->filterBy('intendedTemplate', 'showing');
    }

    /** Per-request memo for showingsByFilm() (static, like seriesPage()). */
    protected static ?array $showingsByFilm = null;

    /**
     * All showings grouped by their film's page id — ONE pass over the
     * showings, each resolving its `film` ref once. The former per-film
     * reverse scan made the films archive O(films × showings) (365 films
     * × 77 showings ≈ 28k toPage() calls ≈ 1s); this map costs O(showings)
     * once and every FilmPage::showings() after it is a hash lookup.
     *
     * @return array<string,Page[]> film id => its showing pages (index order)
     */
    public static function showingsByFilm(): array
    {
        if (static::$showingsByFilm !== null) {
            return static::$showingsByFilm;
        }

        $map = [];
        foreach (static::showings() as $showing) {
            if (($film = $showing->film()) !== null) {
                $map[$film->id()][] = $showing;
            }
        }
        return static::$showingsByFilm = $map;
    }

    /** Every standalone (non-film) event page. */
    public static function events(): Pages
    {
        return site()->index()->filterBy('intendedTemplate', 'event');
    }

    /**
     * The unified chronological "what's on" program (SPEC §3 / §8):
     * Showings + Events merged, future-only, soonest first.
     *
     * In `past` mode it instead returns the screening history (items strictly
     * before today, most-recent first) — the data behind the Spielplan's
     * "Frühere" archive toggle. NB: most back-catalogue films carry no historic
     * screening dates (the old WP event dates were not migratable), so this
     * accrues forward as upcoming showings age out.
     *
     * @param array $options includeToday(bool=true), categories(array|null),
     *                        limit(int|null), past(bool=false)
     */
    public static function program(array $options = []): Pages
    {
        $includeToday = $options['includeToday'] ?? true;
        $categories   = $options['categories']   ?? null;
        $limit        = $options['limit']         ?? null;
        $past         = $options['past']         ?? false;
        $boundary     = static::now($includeToday);

        // Merge both content types into one collection.
        $items = static::showings()->merge(static::events());

        // Future program (future-incl-today) or, in `past` mode, the screening
        // history (strictly before today). Skip items without a usable date.
        $items = $items->filter(function (Page $item) use ($boundary, $past) {
            $ts = $item->timestamp();
            if ($ts === null) {
                return false;
            }
            return $past === true ? $ts < $boundary : $ts >= $boundary;
        });

        // Optional placement restriction (e.g. only Spielplan, or only Festival).
        if (is_array($categories) === true && count($categories) > 0) {
            $wanted = array_map(fn ($c) => mb_strtolower(trim((string) $c)), $categories);
            $items  = $items->filter(function (Page $item) use ($wanted) {
                $itemCats = static::listValues($item->categories());
                return count(array_intersect($itemCats, $wanted)) > 0;
            });
        }

        // Soonest first (future) / most-recent first (past). Sort on the
        // timestamp via callback so heterogeneous page types interleave.
        $items = $items->sortBy(
            fn (Page $item) => $item->timestamp() ?? PHP_INT_MAX,
            $past === true ? 'desc' : 'asc'
        );

        if (is_int($limit) === true) {
            $items = $items->limit($limit);
        }

        return $items;
    }

    /**
     * Format a timestamp for the CURRENT content language (multilang, SPEC).
     *
     * PHP's date() always produces English day/month names, so localized
     * output goes through IntlDateFormatter with the active language's locale.
     * The ICU pattern comes from the language's translations
     * (kinemathek.date.<format> in site/languages/*.php) with German defaults,
     * so " Uhr" lives in the pattern (quoted ICU literal), not in templates.
     *
     * Formats: 'datetime' (abbrev. weekday), 'long' (full weekday),
     * 'short' (date+time, no weekday), 'date' (date only).
     */
    public static function localDate(?int $timestamp, string $format = 'datetime'): string
    {
        if ($timestamp === null) {
            return '';
        }

        $defaults = [
            'datetime' => "EE dd.MM.yyyy · HH:mm 'Uhr'",
            'long'     => "EEEE, dd.MM.yyyy · HH:mm 'Uhr'",
            'short'    => "dd.MM.yyyy HH:mm 'Uhr'",
            'date'     => 'dd.MM.yyyy',
        ];
        $pattern = t(
            'kinemathek.date.' . $format,
            $defaults[$format] ?? $defaults['datetime']
        );

        if (class_exists(\IntlDateFormatter::class) === false) {
            // intl missing on the host: neutral, unlocalized fallback.
            return date('d.m.Y H:i', $timestamp);
        }

        $language  = kirby()->language();
        $formatter = new \IntlDateFormatter(
            $language?->locale(LC_ALL) ?? 'de_DE',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::FULL,
            (string) (option('kinemathek.ics.timezone') ?? 'Europe/Berlin'),
            \IntlDateFormatter::GREGORIAN,
            $pattern
        );

        return (string) $formatter->format($timestamp);
    }

    /**
     * Compose faceted filters over a collection (SPEC §5).
     *
     * AND across facets, OR within a multi-value facet. Absent/empty facets are
     * skipped, so callers can pass the whole query-param set and only present
     * facets narrow the result. Returns ALL matches (no pagination here).
     *
     * @param array<string,string|array|null> $facets facet => requested value(s)
     */
    public static function filterByFacets(Pages $pages, array $facets): Pages
    {
        foreach (static::FACETS as $name => $config) {
            $requested = static::normaliseValues($facets[$name] ?? null);
            if ($requested === []) {
                continue; // facet not active — does not constrain the result
            }

            $pages = $pages->filter(function (Page $item) use ($name, $config, $requested) {
                $actual = static::facetValues($item, $name, $config);
                // OR within a facet: match if the item carries ANY requested value.
                return count(array_intersect($actual, $requested)) > 0;
            });
        }

        // Boolean facet: "has discussion / Filmgespräch" (SPEC §2.4 / §5).
        // Query param is ?discussion=1; the field is hasDiscussion.
        if (static::truthy($facets['discussion'] ?? null)) {
            $pages = $pages->filter(fn (Page $item) => $item->hasDiscussion()->toBool());
        }

        // Boolean convenience facet: "has any subtitles".
        if (static::truthy($facets['hasSubtitles'] ?? null)) {
            $pages = $pages->filter(fn (Page $item) => $item->subtitles()->isNotEmpty());
        }

        return $pages;
    }

    /**
     * Derive the facet values that actually exist in a collection, with counts,
     * so the UI only offers filters that have results (SPEC §5: facets come
     * from the data, not hard-coded).
     *
     * @return array<string,array<string,int>> facet => [value => count]
     */
    public static function availableFacets(Pages $pages): array
    {
        $result = [];
        foreach (static::FACETS as $name => $config) {
            $counts = [];
            foreach ($pages as $item) {
                foreach (static::facetValues($item, $name, $config) as $value) {
                    $counts[$value] = ($counts[$value] ?? 0) + 1;
                }
            }
            ksort($counts);
            $result[$name] = $counts;
        }
        return $result;
    }

    /**
     * Every distinct value a facet actually carries across the content, for
     * Panel pre-filter option lists ("presets" the editor can pick instead of
     * free-typing). Read live in the Panel, so the list always reflects the
     * real archive — including this environment's content, which is not in the
     * repo. Source follows the facet's 'on': film-facets (series/Reihe,
     * genre, country, language) come from the film archive; per-occurrence and
     * 'both' facets also read showings/events.
     *
     * Original casing is preserved (first seen wins) and values are sorted
     * naturally + case-insensitively, like a human would expect a picker.
     *
     * @return string[] distinct, original-cased values
     */
    public static function facetOptions(string $facet): array
    {
        $config = static::FACETS[$facet] ?? null;
        if ($config === null) {
            return [];
        }

        $pages = match ($config['on']) {
            'self' => static::showings()->merge(static::events()),
            'both' => static::films()->merge(static::showings())->merge(static::events()),
            default => static::films(), // 'film'
        };

        // Read the facet field directly on each page in the chosen source (the
        // source already targets where the value lives, so no film-hop here).
        $values = [];
        foreach ($pages as $page) {
            foreach (static::splitField($page->{$facet}()) as $value) {
                $values[mb_strtolower($value)] ??= $value; // de-dupe, keep first casing
            }
        }

        $values = array_values($values);
        natcasesort($values);
        return array_values($values);
    }

    /**
     * Resolve a Reihe name to its Bereichsseite: the first non-draft
     * collection page whose "Reihe" pre-filter (filterSeries) carries the
     * value — or, as a fallback, whose "Schlagworte" pre-filter does (some
     * Reihen, e.g. Maschenkino, scope their page by keyword so that non-film
     * events can join the listing). Lets templates link a series label to the
     * curated series page without storing a relation anywhere.
     * Case-insensitive; memoised per content language (both filter fields are
     * translatable).
     */
    public static function seriesPage(string $series): ?Page
    {
        static $maps = [];

        $lang = kirby()->language()?->code() ?? 'default';
        if (isset($maps[$lang]) === false) {
            $map   = [];
            $pages = site()->index()->filterBy('intendedTemplate', 'collection');
            // two passes: an explicit Reihe filter always beats a keyword one
            foreach (['filterSeries', 'filterKeywords'] as $field) {
                foreach ($pages as $page) {
                    foreach (static::listValues($page->{$field}()) as $value) {
                        $map[$value] ??= $page; // first page wins on duplicates
                    }
                }
            }
            $maps[$lang] = $map;
        }

        return $maps[$lang][mb_strtolower(trim($series))] ?? null;
    }

    /**
     * Resolve the value(s) of a facet for a single item, following the
     * 'self' / 'film' / 'both' indirection.
     *
     *   self  -> the showing/event/film page itself
     *   film  -> the linked Film (Showing); an Event (no film) yields nothing;
     *            a Film page (no film() method) reads itself
     *   both  -> union of self and film
     *
     * @return string[] lower-cased, trimmed, de-duplicated values
     */
    protected static function facetValues(Page $item, string $name, array $config): array
    {
        $on      = $config['on'];
        $sources = [];

        if ($on === 'self' || $on === 'both') {
            $sources[] = $item;
        }

        if ($on === 'film' || $on === 'both') {
            if (method_exists($item, 'film') === true) {
                // Showing/Event: hop to the linked film if there is one.
                if (($film = $item->film()) !== null) {
                    $sources[] = $film;
                }
            } else {
                // A Film page in the archive: the facet lives on the item itself.
                $sources[] = $item;
            }
        }

        $values = [];
        foreach ($sources as $source) {
            $values = array_merge($values, static::listValues($source->{$name}()));
        }

        return array_values(array_unique($values));
    }

    /**
     * Split a tags/multiselect field into trimmed, non-empty values, tolerant of
     * both comma-separated and YAML-list storage. Case is preserved (for display
     * via the commaList field method); listValues() lower-cases for matching.
     *
     * @return string[]
     */
    public static function splitField(Field $field): array
    {
        if ($field->isEmpty() === true) {
            return [];
        }

        $raw    = (string) $field->value();
        // YAML sequence? (Kirby may store multiselect/tags as a yaml list.)
        $values = str_starts_with(ltrim($raw), '- ') ? $field->yaml() : explode(',', $raw);

        return array_values(array_filter(
            array_map(fn ($v) => trim((string) $v), $values),
            fn ($v) => $v !== ''
        ));
    }

    /**
     * Lower-cased facet values from a field (for matching).
     *
     * @return string[]
     */
    public static function listValues(Field $field): array
    {
        return array_map('mb_strtolower', static::splitField($field));
    }

    /**
     * Normalise an incoming facet request (string, comma string, or array)
     * into a clean lower-cased array.
     *
     * @return string[]
     */
    protected static function normaliseValues($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value) === true) {
            $value = explode(',', $value);
        }
        return array_values(array_filter(
            array_map(fn ($v) => mb_strtolower(trim((string) $v)), (array) $value),
            fn ($v) => $v !== ''
        ));
    }

    protected static function truthy($value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes', 'ja'], true);
    }
}
