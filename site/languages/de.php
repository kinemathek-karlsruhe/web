<?php

/**
 * German — the DEFAULT content language (SPEC: the Kinemathek's house language).
 *
 * url '/' keeps German at the bare root (no /de prefix), so all existing URLs
 * stay stable; English lives under /en (see en.php). Content files carry the
 * language code: film.de.txt / film.en.txt (scripts/migrate-content-multilang.php
 * renames a pre-multilang content tree).
 *
 * The 'translations' block feeds the frontend t() helper. BOTH language files
 * must define the SAME keys — t() has no automatic cross-language fallback for
 * site translations, a missing key renders as an empty string.
 *
 * date.* keys are ICU datetime patterns for Kinemathek::localDate()
 * (IntlDateFormatter — quoted literals like 'Uhr' are ICU syntax, NOT PHP
 * date() format characters).
 */

return [
    'code'      => 'de',
    'name'      => 'Deutsch',
    'default'   => true,
    'direction' => 'ltr',
    'locale'    => [LC_ALL => 'de_DE.UTF-8'],
    'url'       => '/',

    'translations' => [
        // ICU date patterns (Kinemathek::localDate / the localDate field method)
        'kinemathek.date.datetime' => "EE dd.MM.yyyy · HH:mm 'Uhr'",
        'kinemathek.date.long'     => "EEEE, dd.MM.yyyy · HH:mm 'Uhr'",
        'kinemathek.date.short'    => "dd.MM.yyyy HH:mm 'Uhr'",
        'kinemathek.date.date'     => 'dd.MM.yyyy',
        'kinemathek.date.dow'      => 'EE',          // Monatsblatt day bar ("Mo")
        'kinemathek.date.month'    => 'LLLL',        // Monatsblatt month marker
        'kinemathek.date.detail'   => 'EE d. LLLL',  // Monatsblatt detail panel

        // Content-type fallback names (displayTitle when nothing is set)
        'kinemathek.showing' => 'Vorstellung',
        'kinemathek.event'   => 'Veranstaltung',

        // Shared UI
        'kinemathek.tickets'      => 'Tickets',
        'kinemathek.tickets.mars' => 'Tickets (Mars EDV)',
        'kinemathek.calendar'     => 'In Kalender (.ics)',
        'kinemathek.venue'        => 'Ort',
        'kinemathek.past'         => '(vergangen)',

        // Homepage
        'kinemathek.home.next'        => 'Als Nächstes',
        'kinemathek.home.featured'    => 'Festivals & Besonderes',
        'kinemathek.home.fullProgram' => 'Ganzen Spielplan ansehen →',

        // Program / Spielplan
        'kinemathek.program'           => 'Spielplan',
        'kinemathek.program.list'      => 'Programm',
        'kinemathek.program.none'      => 'Derzeit keine Termine.',
        'kinemathek.program.noMatches' => 'Keine passenden Termine.',

        // Faceted filtering
        'kinemathek.filters.active'     => 'Aktive Filter',
        'kinemathek.filters.none'       => 'keine',
        'kinemathek.filters.reset'      => 'Filter zurücksetzen',
        'kinemathek.filters.by'         => 'Filtern nach',
        'kinemathek.filters.discussion' => 'nur mit Filmgespräch',
        'kinemathek.filters.subtitled'  => 'nur mit Untertiteln',

        // Film archive + single film
        'kinemathek.archive'            => 'Filmarchiv',
        'kinemathek.films'              => 'Filme',
        'kinemathek.film.upcomingFlag'  => 'kommende Termine',
        'kinemathek.film.originalTitle' => 'Originaltitel',
        'kinemathek.film.year'          => 'Jahr',
        'kinemathek.film.country'       => 'Land',
        'kinemathek.film.language'      => 'Sprache',
        'kinemathek.film.runtime'       => 'Laufzeit',
        'kinemathek.film.genre'         => 'Genre',
        'kinemathek.film.directors'     => 'Regie',
        'kinemathek.film.synopsis'      => 'Inhalt',
        'kinemathek.film.stills'        => 'Szenenbilder',
        'kinemathek.film.upcoming'      => 'Kommende Vorstellungen',
        'kinemathek.film.past'          => 'Frühere Vorstellungen',

        // Single showing / event
        'kinemathek.showing.version'    => 'Fassung',
        'kinemathek.showing.discussion' => 'Mit Filmgespräch.',
        'kinemathek.showing.film'       => 'Film',
        'kinemathek.showing.others'     => 'Weitere Termine dieses Films',
        'kinemathek.event.discussion'   => 'Mit Gespräch.',

        // Monatsblatt (the designed Spielplan view)
        'kinemathek.mb.eyebrow'          => 'Kino in der Kaiserpassage',
        'kinemathek.mb.inCinema'         => 'im Kino',
        'kinemathek.mb.today'            => 'Heute',
        'kinemathek.mb.soon'             => 'Demnächst',
        'kinemathek.mb.filter'           => 'Filter',
        'kinemathek.mb.all'              => 'Alle',
        'kinemathek.mb.shows'            => 'Vorstellungen',
        'kinemathek.mb.talk'             => 'Filmgespräch',
        'kinemathek.mb.filmpage'         => 'Zur Filmseite',
        'kinemathek.mb.legend.omuVariants' => '(e/f) engl./franz. UT',
        'kinemathek.mb.legend.talk'      => 'Vorstellung mit Einführung/Filmgespräch',
        'kinemathek.mb.legend.saal'      => 'Veranstaltung im Saal',
        'kinemathek.mb.legend.box'       => 'Veranstaltung in der Box',
        'kinemathek.mb.support'          => 'Mit freundlicher Unterstützung von Filmförderung Baden-Württemberg und Stadt Karlsruhe',

        // Fassungen (spelled-out subtitle markers in the detail panel)
        'kinemathek.version.omu'  => 'Originalfassung mit deutschen Untertiteln',
        'kinemathek.version.omeu' => 'Originalfassung mit englischen Untertiteln',
        'kinemathek.version.of'   => 'Originalfassung',
        'kinemathek.version.dtf'  => 'Deutsche Fassung',

        // TMDB attribution (required wording, SPEC §4)
        'kinemathek.tmdb.attribution' =>
            'Dieses Produkt nutzt die TMDB-API, ist aber nicht von TMDB '
            . 'unterstützt oder zertifiziert.',
    ],
];
