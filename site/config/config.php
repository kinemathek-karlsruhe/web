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
	"kinemathek" => [
		// TMDB integration (site/plugins/kinemathek-tmdb). Credentials come from
		// .env: TMDB_KEY for a v3 key, or TMDB_TOKEN for a v4 bearer token.
		"tmdb" => [
			"key" => getenv("TMDB_KEY") ?: null,
			"token" => getenv("TMDB_TOKEN") ?: null,
			"language" => "de-DE",
			"posterSize" => "w780",
			"thumbSize" => "w185",
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
