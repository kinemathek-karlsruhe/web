<?php

/**
 * One-time, idempotent content migration: single-language -> multi-language.
 *
 * Kirby reads multi-language content from language-suffixed files
 * (film.de.txt, site.de.txt, tmdb-poster-19.jpg.de.txt …). A content tree
 * created BEFORE 'languages' => true was enabled uses bare *.txt files, which
 * Kirby then no longer finds — every page would render empty. This script
 * renames every bare content file to the default-language form (*.de.txt).
 *
 * content/ is gitignored and provisioned per environment, so run this ONCE in
 * EACH environment when deploying the multi-language change:
 *
 *     php scripts/migrate-content-multilang.php          # dry-run (prints plan)
 *     php scripts/migrate-content-multilang.php --apply  # actually rename
 *
 * Idempotent: files already carrying a language code (*.de.txt / *.en.txt for
 * every language defined in site/languages/) are skipped, as is everything
 * that is not a .txt content file.
 */

declare(strict_types=1);

$root       = dirname(__DIR__);
$contentDir = $root . '/content';
$apply      = in_array('--apply', $argv, true);

if (is_dir($contentDir) === false) {
    fwrite(STDERR, "No content directory at {$contentDir} — nothing to do.\n");
    exit(1);
}

// Language codes come from the language definitions, default language first.
$codes   = [];
$default = null;
foreach (glob($root . '/site/languages/*.php') as $file) {
    $props = include $file;
    if (is_array($props) === false) {
        continue;
    }
    $code    = $props['code'] ?? pathinfo($file, PATHINFO_FILENAME);
    $codes[] = $code;
    if (($props['default'] ?? false) === true) {
        $default = $code;
    }
}

if ($default === null) {
    fwrite(STDERR, "No default language found in site/languages/ — aborting.\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($contentDir, FilesystemIterator::SKIP_DOTS)
);

$renamed = 0;
$skipped = 0;

foreach ($iterator as $file) {
    if ($file->isFile() === false || $file->getExtension() !== 'txt') {
        continue;
    }

    $name = $file->getBasename('.txt'); // e.g. "film", "poster.jpg", "film.de"

    // Already language-suffixed? (film.de / tmdb-poster-19.jpg.en)
    $suffix = pathinfo($name, PATHINFO_EXTENSION);
    if (in_array($suffix, $codes, true) === true) {
        $skipped++;
        continue;
    }

    $target = $file->getPath() . '/' . $name . '.' . $default . '.txt';

    if (file_exists($target) === true) {
        echo "SKIP (target exists): {$file->getPathname()}\n";
        $skipped++;
        continue;
    }

    echo ($apply ? 'RENAME' : 'WOULD RENAME') . ": {$file->getPathname()} -> {$target}\n";

    if ($apply === true) {
        if (rename($file->getPathname(), $target) === false) {
            fwrite(STDERR, "FAILED: {$file->getPathname()}\n");
            exit(1);
        }
    }

    $renamed++;
}

echo "\n" . ($apply ? 'Renamed' : 'Would rename') . " {$renamed} file(s), skipped {$skipped}.\n";
if ($apply === false) {
    echo "Dry-run only — re-run with --apply to perform the renames.\n";
}
