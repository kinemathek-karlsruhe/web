<?php

/**
 * Kinemathek Karlsruhe — core backend plugin.
 *
 * One plugin, one registration. Covers:
 *  - the Film / Showing / Event content model (page models)
 *  - the relational logic (Film <-> Showings) and the unified, chronological
 *    "what's on" program (Showings + Events interleaved) — SPEC §3 / §8
 *  - faceted filtering of films/events (SPEC §5)
 *  - per-Showing / per-Event ICS export building blocks (SPEC §6)
 *
 * Privacy (SPEC §7): nothing here makes an outbound request. TMDB enrichment is
 * a separate, server-side, cached plugin (kinemathek-tmdb); this one only reads
 * local content. The public site never talks to a third party.
 */

use Kirby\Cms\App;
use Kirby\Cms\Pages;
use Kirby\Toolkit\Str;

// All dates are interpreted in the venue's wall-clock time zone so that
// "today", program ordering and the ICS local-time output agree (SPEC §6).
date_default_timezone_set((string) (option('kinemathek.ics.timezone') ?? 'Europe/Berlin'));

// Autoload plugin classes + page models.
load([
    'Kinemathek\\Kinemathek'      => __DIR__ . '/classes/Kinemathek.php',
    'Kinemathek\\Ics'             => __DIR__ . '/src/Ics.php',
    'Kinemathek\\OccurrenceTrait' => __DIR__ . '/models/OccurrenceTrait.php',
    'Kinemathek\\FilmPage'        => __DIR__ . '/models/FilmPage.php',
    'Kinemathek\\ShowingPage'     => __DIR__ . '/models/ShowingPage.php',
    'Kinemathek\\EventPage'       => __DIR__ . '/models/EventPage.php',
]);

App::plugin('kinemathek/core', [

    // Behaviour intrinsic to each content type lives on its model.
    'pageModels' => [
        'film'    => Kinemathek\FilmPage::class,
        'showing' => Kinemathek\ShowingPage::class,
        'event'   => Kinemathek\EventPage::class,
    ],

    // Makes Kirby resolve the .ics extension to the right MIME type.
    'fileTypes' => [
        'ics' => [
            'mime' => 'text/calendar',
            'type' => 'document',
        ],
    ],

    'siteMethods' => [
        /**
         * The unified, chronological "what's on" program (SPEC §3 / §8):
         * Showings + Events merged, future-only, soonest first.
         *
         * @param array $options includeToday(bool), categories(array|null),
         *                        limit(int|null)
         */
        'program' => fn (array $options = []): Pages => Kinemathek\Kinemathek::program($options),

        /** Canonical film archive source (events excluded, SPEC §2.3). */
        'films' => fn (): Pages => Kinemathek\Kinemathek::films(),

        /** The single next item on the program (homepage "today/next", SPEC §8). */
        'nextOnProgram' => fn (bool $includeToday = true) =>
            Kinemathek\Kinemathek::program(['includeToday' => $includeToday, 'limit' => 1])->first(),

        /**
         * Distinct values a facet carries across the content — for Panel
         * pre-filter option lists, e.g. `query: site.facetOptions("series")`.
         */
        'facetOptions' => fn (string $facet = ''): array => Kinemathek\Kinemathek::facetOptions($facet),
    ],

    'fieldMethods' => [
        /**
         * Render a tags/multiselect value as a clean delimited list, regardless
         * of whether it is stored comma-separated or as a YAML sequence.
         * Preserves the original casing (unlike the lower-casing facet logic).
         */
        'commaList' => fn ($field, string $glue = ', ') =>
            implode($glue, Kinemathek\Kinemathek::splitField($field)),

        /**
         * Locale-aware date output for the current content language (templates
         * only — Panel info:/num: keep the locale-neutral toDate()). Formats:
         * datetime | long | short | date — see Kinemathek::localDate().
         */
        'localDate' => fn ($field, string $format = 'datetime'): string =>
            $field->isEmpty()
                ? ''
                : Kinemathek\Kinemathek::localDate($field->toDate(), $format),
    ],

    // Cross-type page methods for the ICS export. Showings and Events both
    // carry a `date` field, so these work uniformly on either.
    'pageMethods' => [
        /**
         * Stable, globally-unique UID for an ICS VEVENT — anchored on the page
         * UUID (falls back to id) so re-downloading an edited screening updates
         * the existing calendar entry instead of duplicating it.
         */
        'icsUid' => function (): string {
            $anchor = $this->uuid() ? $this->uuid()->id() : $this->id();
            $host   = parse_url(kirby()->url(), PHP_URL_HOST) ?: 'kinemathek-karlsruhe.de';
            return $anchor . '@' . $host;
        },

        /** Descriptive, date-stamped .ics download filename. */
        'icsFilename' => function (): string {
            // displayTitle() resolves a Showing's film title; plain $this->title()
            // would fall back to the (date-bearing) slug and double the stamp.
            $title = method_exists($this, 'displayTitle') ? $this->displayTitle() : $this->title()->value();
            $slug  = Str::slug($title);
            $date  = $this->icsStart();
            $stamp = $date ? $date->format('Y-m-d') : 'termin';
            return trim($slug . '-' . $stamp, '-') . '.ics';
        },

        /**
         * Start as a DateTime in the venue time zone, or null if no parseable
         * date. Reads the `date` field (blueprint uses time: true).
         */
        'icsStart' => function (): ?\DateTime {
            $raw = $this->date()->value();
            if (empty($raw) === true) {
                return null;
            }
            try {
                return new \DateTime($raw, new \DateTimeZone(
                    (string) (option('kinemathek.ics.timezone') ?? 'Europe/Berlin')
                ));
            } catch (\Throwable $e) {
                return null;
            }
        },
    ],
]);
