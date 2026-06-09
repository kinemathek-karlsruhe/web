<?php

/**
 * Kinemathek Karlsruhe — site configuration.
 *
 * Single source of truth for plugin options (read via option('kinemathek.*')).
 * No analytics, no third-party services beyond the SERVER-SIDE, cached TMDB
 * sync — the public site never makes an outbound request (SPEC §7).
 */
return [
    'kinemathek' => [
        // TMDB integration (site/plugins/kinemathek-tmdb). The key/token MUST
        // be injected per environment — never commit a real credential.
        'tmdb' => [
            'key'        => null,   // v3 API key, e.g. getenv('TMDB_KEY')
            'token'      => null,   // OR v4 bearer token
            'language'   => 'de-DE',
            'posterSize' => 'w780',
            'thumbSize'  => 'w185',
            'maxResults' => 8,
        ],
        // Add-to-calendar (ICS) export defaults.
        'ics' => [
            'defaultDuration' => 120,            // minutes, when no runtime known
            'timezone'        => 'Europe/Berlin',
        ],
    ],
];
