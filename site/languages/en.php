<?php

/**
 * English — secondary content language, served under /en.
 *
 * Untranslated content falls back to German automatically (Kirby merges the
 * default language's fields under every translation), so an EN page never
 * renders empty. Keep the translation KEYS in sync with de.php — a key missing
 * here renders as an empty string on the /en site.
 */

return [
    'code'      => 'en',
    'name'      => 'English',
    'default'   => false,
    'direction' => 'ltr',
    'locale'    => [LC_ALL => 'en_GB.UTF-8'],
    'url'       => '/en',

    'translations' => [
        // ICU date patterns (Kinemathek::localDate / the localDate field method)
        'kinemathek.date.datetime' => 'EE dd MMM yyyy · HH:mm',
        'kinemathek.date.long'     => 'EEEE, dd MMMM yyyy · HH:mm',
        'kinemathek.date.short'    => 'dd MMM yyyy, HH:mm',
        'kinemathek.date.date'     => 'dd MMM yyyy',
        'kinemathek.date.dow'      => 'EE',          // Monatsblatt day bar ("Mon")
        'kinemathek.date.month'    => 'LLLL',        // Monatsblatt month marker
        'kinemathek.date.detail'   => 'EE d MMMM',   // Monatsblatt detail panel

        // Content-type fallback names (displayTitle when nothing is set)
        'kinemathek.showing' => 'Screening',
        'kinemathek.event'   => 'Event',

        // Shared UI
        'kinemathek.tickets'      => 'Tickets',
        'kinemathek.tickets.mars' => 'Tickets (Mars EDV)',
        'kinemathek.calendar'     => 'Add to calendar (.ics)',
        'kinemathek.venue'        => 'Venue',
        'kinemathek.past'         => '(past)',

        // Homepage
        'kinemathek.home.next'        => 'Up next',
        'kinemathek.home.featured'    => 'Festivals & specials',
        'kinemathek.home.fullProgram' => 'View the full programme →',

        // Program / Spielplan
        'kinemathek.program'           => 'Programme',
        'kinemathek.program.list'      => 'Programme',
        'kinemathek.program.none'      => 'No screenings scheduled at the moment.',
        'kinemathek.program.noMatches' => 'No matching dates.',

        // Faceted filtering
        'kinemathek.filters.active'     => 'Active filters',
        'kinemathek.filters.none'       => 'none',
        'kinemathek.filters.reset'      => 'Reset filters',
        'kinemathek.filters.by'         => 'Filter by',
        'kinemathek.filters.discussion' => 'only with film talk',
        'kinemathek.filters.subtitled'  => 'only with subtitles',

        // Film archive + single film
        'kinemathek.archive'            => 'Film archive',
        'kinemathek.films'              => 'Films',
        'kinemathek.film.upcomingFlag'  => 'upcoming screenings',
        'kinemathek.film.originalTitle' => 'Original title',
        'kinemathek.film.year'          => 'Year',
        'kinemathek.film.country'       => 'Country',
        'kinemathek.film.language'      => 'Language',
        'kinemathek.film.runtime'       => 'Runtime',
        'kinemathek.film.genre'         => 'Genre',
        'kinemathek.film.directors'     => 'Directed by',
        'kinemathek.film.synopsis'      => 'Synopsis',
        'kinemathek.film.stills'        => 'Stills',
        'kinemathek.film.upcoming'      => 'Upcoming screenings',
        'kinemathek.film.past'          => 'Past screenings',

        // Single showing / event
        'kinemathek.showing.version'    => 'Version',
        'kinemathek.showing.discussion' => 'With film talk.',
        'kinemathek.showing.film'       => 'Film',
        'kinemathek.showing.others'     => 'More dates for this film',
        'kinemathek.event.discussion'   => 'With talk.',

        // Monatsblatt (the designed Spielplan view)
        'kinemathek.mb.eyebrow'          => 'Cinema in the Kaiserpassage',
        'kinemathek.mb.inCinema'         => 'at the cinema',
        'kinemathek.mb.today'            => 'Today',
        'kinemathek.mb.soon'             => 'Coming up',
        'kinemathek.mb.filter'           => 'Filter',
        'kinemathek.mb.all'              => 'All',
        'kinemathek.mb.show'             => 'screening',
        'kinemathek.mb.shows'            => 'screenings',
        'kinemathek.mb.series'           => 'Series',
        'kinemathek.mb.allSeries'        => 'All series',
        'kinemathek.mb.close'            => 'Close',
        'kinemathek.mb.nav'              => 'Sections',
        'kinemathek.mb.nav.films'        => 'Films',
        'kinemathek.mb.nav.events'       => 'Events',
        'kinemathek.mb.talk'             => 'Film talk',
        'kinemathek.mb.filmpage'         => 'Film page',
        'kinemathek.mb.legend.omuVariants' => '(e) English subtitles',
        'kinemathek.mb.legend.talk'      => 'Screening with introduction/film talk',
        'kinemathek.mb.legend.saal'      => 'Held in the Saal',
        'kinemathek.mb.legend.box'       => 'Held in the Box',
        'kinemathek.mb.support'          => 'Kindly supported by Filmförderung Baden-Württemberg and the City of Karlsruhe',

        // Versions (spelled-out subtitle markers in the detail panel)
        'kinemathek.version.omu'  => 'Original version with German subtitles',
        'kinemathek.version.omeu' => 'Original version with English subtitles',
        'kinemathek.version.of'   => 'Original version',
        'kinemathek.version.dtf'  => 'German-dubbed version',

        // TMDB attribution (required wording, SPEC §4)
        'kinemathek.tmdb.attribution' =>
            'This product uses the TMDB API but is not endorsed or certified '
            . 'by TMDB.',
    ],
];
