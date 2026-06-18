<?php

/**
 * Content import: old WordPress Reihe/series/festival POSTS -> Kirby DRAFTS.
 *
 * The curated static-page import (import-static.php) deliberately migrated only
 * the 16 evergreen pages and left the ~65 Reihe/series/festival posts ("program
 * content") alone. Ruby asked for those to come over too — as DRAFTS for the
 * editorial team to refine, place, and de-duplicate.
 *
 * Reads the conversion output produced by the `kinemathek-post-drafts` workflow
 * (scripts/import/static/post-drafts.json: { drafts: [ {slug,title,category,
 * intro,text,hazards[],isStale} ] }) and creates:
 *
 *   - one UNLISTED staging parent page `reihen-archiv` (template collection, no
 *     program categories) — a Panel-only bucket, NOT in the public pivot nav,
 *   - one `text` DRAFT child per post (status stays draft => never public),
 *     with the suggested category + migration hazards recorded in a clearly
 *     delimited "Migrations-Notiz" block at the top of the text field so the
 *     team sees them in the editor.
 *
 * Usage:
 *   php scripts/import-post-drafts.php                 # DRY RUN
 *   php scripts/import-post-drafts.php --apply         # create staging page + drafts
 *   php scripts/import-post-drafts.php --input=path    # alternate JSON
 *
 * Idempotent: the staging parent is reused if present; a draft is skipped when
 * a child with the same slug already exists under it. Safe to re-run.
 *
 * Multilang: German content written with ->update($values, 'de'); EN falls back.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$apply = false;
$input = __DIR__ . '/import/static/post-drafts.json';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } elseif (preg_match('/^--input=(.+)$/', $arg, $m) === 1) {
        $input = $m[1];
    } else {
        fwrite(STDERR, "Unknown argument: {$arg}\n");
        fwrite(STDERR, "Usage: php scripts/import-post-drafts.php [--apply] [--input=path]\n");
        exit(1);
    }
}

function fatal(string $msg): never
{
    fwrite(STDERR, "FATAL: {$msg}\n");
    exit(1);
}

if (is_file($input) === false) {
    fatal("Input file not found: {$input}");
}
$json   = json_decode((string) file_get_contents($input), true);
$drafts = is_array($json['drafts'] ?? null) ? $json['drafts'] : null;
if ($drafts === null) {
    fatal("Input is not valid JSON with a 'drafts' array: {$input}");
}

require dirname(__DIR__) . '/kirby/bootstrap.php';

$kirby = new Kirby();
$kirby->impersonate('kirby');
$site     = $kirby->site();
$langCode = $kirby->multilang() ? $kirby->defaultLanguage()->code() : null; // 'de'

const STAGING_SLUG  = 'reihen-archiv';
const STAGING_TITLE = 'Reihen & Festivals — Archiv (Migration)';
const STAGING_INTRO = <<<'MD'
Automatisch migrierte Reihen-, Serien- und Festivalbeiträge der alten Website
— **Entwürfe** zur redaktionellen Überarbeitung. Diese Seite ist versteckt
(nicht im Menü). Jede Unterseite nennt oben eine Migrations-Notiz mit
Kategorie-Vorschlag und Hinweisen. Bitte sichten, ggf. verschieben/zusammen­führen
und veröffentlichen oder löschen.
MD;

/** Build the German `text` field: a migration note block + the converted body. */
function buildText(array $d): string
{
    $cat     = (string) ($d['category'] ?? 'sonstiges');
    $hazards = is_array($d['hazards'] ?? null) ? $d['hazards'] : [];
    $stale   = ($d['isStale'] ?? false) === true;
    $slug    = (string) ($d['slug'] ?? '');

    $note  = "(( **Migrations-Notiz** — Kategorie-Vorschlag: **{$cat}**"
        . ($stale ? ' · ⚠ überwiegend veraltete Programm-Inhalte' : '')
        . " · Quelle: https://kinemathek-karlsruhe.de/{$slug}/ ))\n";
    if ($hazards !== []) {
        $note .= "\nHinweise für die Redaktion:\n";
        foreach ($hazards as $h) {
            $note .= '- ' . trim((string) $h) . "\n";
        }
    }

    $body = trim((string) ($d['text'] ?? ''));
    return $body === '' ? rtrim($note) : $note . "\n----\n\n" . $body;
}

// ---------------------------------------------------------------------------
// Plan
// ---------------------------------------------------------------------------

echo "== Post-drafts import ({$input})" . ($apply ? ' [APPLY]' : ' [DRY RUN]') . " ==\n";
echo 'Drafts in input: ' . count($drafts) . "\n";

$parent       = $site->find(STAGING_SLUG);
$parentExists = $parent !== null;
echo 'Staging parent /' . STAGING_SLUG . ': ' . ($parentExists ? 'exists' : 'will be created') . "\n\n";

$byCat = [];
$plan  = [];
foreach ($drafts as $d) {
    $slug = is_string($d['slug'] ?? null) ? $d['slug'] : null;
    if ($slug === null || $slug === '') {
        continue;
    }
    $exists = $parentExists && $parent->childrenAndDrafts()->find($slug) !== null;
    $plan[] = ['draft' => $d, 'slug' => $slug, 'action' => $exists ? 'skip' : 'create'];
    $byCat[(string) ($d['category'] ?? 'sonstiges')][] = $slug;
    printf(
        "  [%-6s] %-44s %-14s %s\n",
        $exists ? 'skip' : 'create',
        $slug,
        (string) ($d['category'] ?? 'sonstiges'),
        ($d['isStale'] ?? false) === true ? '(stale)' : ''
    );
}

echo "\nBY CATEGORY\n";
foreach ($byCat as $cat => $slugs) {
    echo '  ' . str_pad($cat, 14) . ' ' . count($slugs) . "\n";
}

$creates = count(array_filter($plan, fn ($p) => $p['action'] === 'create'));
echo "\nSUMMARY\n  drafts: {$creates} to create, " . (count($plan) - $creates) . " skipped\n";

if ($apply === false) {
    echo "\nDry run only — nothing was written. Re-run with --apply.\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Apply
// ---------------------------------------------------------------------------

echo "\n== Applying ==\n";
$errors = 0;

// 1) staging parent (unlisted collection, no program categories)
if ($parentExists === false) {
    echo 'parent /' . STAGING_SLUG . ': ';
    try {
        $parent = $site->createChild([
            'slug'     => STAGING_SLUG,
            'template' => 'collection',
            'content'  => ['title' => STAGING_TITLE],
        ]);
        $parent = $parent->update(['intro' => STAGING_INTRO], $langCode);
        $parent = $parent->changeStatus('unlisted');
        echo "created (collection, unlisted)\n";
    } catch (\Throwable $e) {
        fatal('could not create staging parent: ' . $e->getMessage());
    }
}

// 2) one text draft per post (status stays draft)
foreach ($plan as $row) {
    if ($row['action'] !== 'create') {
        continue;
    }
    $d = $row['draft'];
    echo 'draft ' . STAGING_SLUG . '/' . $row['slug'] . ': ';
    try {
        $page = $parent->createChild([
            'slug'     => $row['slug'],
            'template' => 'text',
            'content'  => ['title' => (string) ($d['title'] ?? $row['slug'])],
        ]);
        // createChild leaves the page as a DRAFT; we deliberately never publish.
        $fields = ['text' => buildText($d)];
        $intro  = trim((string) ($d['intro'] ?? ''));
        if ($intro !== '') {
            $fields['intro'] = $intro;
        }
        $page = $page->update($fields, $langCode);
        echo "created (draft)\n";
    } catch (\Throwable $e) {
        $errors++;
        echo "ERROR: {$e->getMessage()}\n";
    }
}

echo "\nDone" . ($errors > 0 ? " with {$errors} error(s)" : '') . ".\n";
exit($errors > 0 ? 1 : 0);
