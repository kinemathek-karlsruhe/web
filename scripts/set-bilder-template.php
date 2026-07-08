<?php

/**
 * One-time, idempotent content fix: stamp `template: bild` on images that a
 * Bereichsseite's Bilder field references but that were uploaded BEFORE the
 * field's `uploads: template: bild` existed. Without the template the Panel
 * shows no fields for them (Alt-Text, Link, Darstellung).
 *
 * content/ is gitignored and provisioned per environment, so run this ONCE in
 * EACH environment (i.e. on the live server after deploying):
 *
 *     php scripts/set-bilder-template.php          # dry-run (prints plan)
 *     php scripts/set-bilder-template.php --apply  # actually write
 *
 * Idempotent: files that already carry any template are skipped.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/kirby/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$kirby = new Kirby(['roots' => ['index' => dirname(__DIR__)]]);
$kirby->impersonate('kirby');

// The template is invariant metadata: write it to the default language
// explicitly, or a Panel-context update() would drop it (see CLAUDE.md).
$defaultLang = $kirby->defaultLanguage()->code();

$fixed = 0;
$ok    = 0;
foreach ($kirby->site()->index(true)->filterBy('intendedTemplate', 'collection') as $page) {
    foreach ($page->bilder()->toFiles() as $file) {
        if ((string)$file->template() !== '') {
            $ok++;
            continue;
        }
        echo ($apply ? 'FIX ' : 'WOULD FIX ') . $file->id() . "\n";
        if ($apply) {
            $file->update(['template' => 'bild'], $defaultLang);
        }
        $fixed++;
    }
}

echo "\n" . ($apply ? "Done: " : "Dry-run: ") . $fixed . ' file(s) ' .
    ($apply ? 'fixed' : 'to fix') . ", {$ok} already fine." .
    ($apply || $fixed === 0 ? '' : ' Run again with --apply to write.') . "\n";
