<?php

/**
 * Kinemathek Karlsruhe — site configuration.
 *
 * Secrets (TMDB key/token) are loaded from a gitignored .env at the repo root,
 * never committed here. Copy .env.example to .env and fill it in. Everything
 * else below is non-sensitive. No analytics / no third-party requests on the
 * public path (SPEC §7).
 */

// Minimal .env loader (no dependencies): KEY=VALUE lines at the repo root.
// A real environment variable (e.g. set by the host) always wins over .env.
$envFile = __DIR__ . "/../../.env";
if (is_file($envFile) === true) {
	foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
		$line = trim($line);
		if ($line === "" || $line[0] === "#") {
			continue;
		}
		[$name, $value] = array_pad(explode("=", $line, 2), 2, "");
		$name  = trim($name);
		$value = trim(trim($value), "\"'");
		if ($name !== "" && getenv($name) === false) {
			putenv($name . "=" . $value);
			$_ENV[$name] = $value;
		}
	}
}

return [
	// Multi-language content (DE default at /, EN at /en) — definitions live in
	// site/languages/. Do NOT add 'languages.detect': it stores the detected
	// language in the session, i.e. sets a cookie (forbidden, SPEC §7).
	"languages" => true,

	"kinemathek" => [
		// TMDB integration (site/plugins/kinemathek-tmdb). Credentials come from
		// .env: TMDB_KEY for a v3 key, or TMDB_TOKEN for a v4 bearer token.
		"tmdb" => [
			"key" => getenv("TMDB_KEY") ?: null,
			"token" => getenv("TMDB_TOKEN") ?: null,
			// Fallback TMDB locale (single-language mode / unmapped codes).
			"language" => "de-DE",
			// Kirby language code -> TMDB locale, used by the per-language sync.
			"languages" => [
				"de" => "de-DE",
				"en" => "en-US",
			],
			"posterSize" => "w780",
			"thumbSize" => "w185",
			"stillSize" => "w1280", // TMDB backdrop sizes: w300/w780/w1280/original
			"maxStills" => 4, // backdrops pulled as Szenenbilder per sync
			"maxResults" => 8,
		],
		// Add-to-calendar (ICS) export defaults.
		"ics" => [
			"defaultDuration" => 120, // minutes, when no runtime known
			"timezone" => "Europe/Berlin",
		],
	],

	// Enable the server-side TMDB response cache (SPEC §4). Keyed by the plugin
	// name with a slash, matching kirby()->cache('kinemathek/tmdb').
	"cache" => [
		"kinemathek/tmdb" => true,
	],
];
