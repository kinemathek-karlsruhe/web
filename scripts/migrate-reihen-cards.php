<?php

/**
 * One-time, idempotent content migration: Reihen-Übersicht -> Karteikarten.
 *
 * Die Seite /reihen baute ihr Kartenraster früher von Hand im Kirbytext: je
 * Reihe eine ##-Überschrift (verlinkt auf die Reihenseite), ein (image:)-Tag
 * und ein Kurztext, alles im Textfeld der ÜBERSICHT. Seit dem Umbau rendert
 * `subpage-cards` die Karten automatisch aus den Unterseiten (Titel + intro +
 * `cardImage()`), genau wie auf /festivals & Co.
 *
 * Damit dabei nichts verloren geht, verschiebt dieses Skript den Bestand von
 * der Übersicht auf die Reihenseiten:
 *
 *   1. Kurztext -> `intro` der Reihenseite, FALLS die noch keins hat.
 *   2. Bild     -> als Datei in die Reihenseite kopiert und als `cover`
 *                  ("Kartenbild") gesetzt, FALLS `cardImage()` sonst leer
 *                  bliebe. Die Bilddateien lagen bisher auf der ÜBERSICHT,
 *                  nicht auf den Reihenseiten — deshalb die Kopie.
 *   3. Textfeld der Übersicht leeren, sonst stünde der alte Katalog unter
 *      den neuen Karten noch einmal.
 *
 * content/ ist gitignored und wird pro Umgebung bereitgestellt — also EINMAL
 * PRO UMGEBUNG laufen lassen:
 *
 *     php scripts/migrate-reihen-cards.php          # Probelauf (zeigt nur den Plan)
 *     php scripts/migrate-reihen-cards.php --apply  # wirklich schreiben
 *
 * Idempotent: schon gefüllte `intro`/`cover`-Felder und ein bereits geleertes
 * Textfeld werden übersprungen, ein zweiter Lauf ändert nichts. Vor der ersten
 * Änderung landet eine Kopie aller Inhaltsdateien der Übersicht in
 * scripts/.backup/ — von dort lässt sich der alte Katalog zurückholen.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/kirby/bootstrap.php';

$apply = in_array('--apply', $argv, true);

$kirby = new Kirby(['roots' => ['index' => $root]]);
$kirby->impersonate('kirby');

$overview = $kirby->page('reihen');
if ($overview === null) {
    fwrite(STDERR, "Keine Seite 'reihen' gefunden — nichts zu tun.\n");
    exit(1);
}

/**
 * Zerlegt den Katalog in seine ##-Blöcke und zieht je Block die verlinkte
 * Reihenseite, das Bild und den Kurztext heraus. Die Notation ist über die
 * Jahre unsauber gewachsen (mal "##(link:", mal "## (link:", ein doppeltes
 * "link: link:"), deshalb bewusst tolerante Muster.
 */
function parseCatalogue(string $text): array
{
    $blocks = preg_split('/\n(?=##)/', trim($text));
    $out    = [];

    foreach ($blocks as $block) {
        $block = trim(preg_replace('/\n-{3,}\s*$/', '', rtrim($block)));
        if ($block === '') {
            continue;
        }

        // Erste Zeile: die verlinkte Überschrift -> UUID der Reihenseite.
        if (preg_match('/page:\/\/([a-z0-9]+)/i', $block, $m) !== 1) {
            continue;
        }
        $pageUuid = $m[1];

        // (image: file://…) — das Kartenbild.
        $fileUuid = null;
        if (preg_match('/\(image:\s*file:\/\/([a-z0-9]+)/i', $block, $m) === 1) {
            $fileUuid = $m[1];
        }

        // Kurztext: alles, was weder Überschrift noch Bild-Tag ist.
        $lines = [];
        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '##') || str_starts_with($line, '(image:')) {
                continue;
            }
            $lines[] = $line;
        }

        $out[$pageUuid] = [
            'file' => $fileUuid,
            'text' => trim(implode("\n", $lines)),
        ];
    }

    return $out;
}

$catalogue = parseCatalogue((string) $overview->text()->value());
if ($catalogue === []) {
    echo "Das Textfeld der Übersicht enthält keinen Katalog mehr — vermutlich schon migriert.\n";
}

// ── Sicherungskopie der Übersichts-Inhaltsdateien ────────────────────────
$backupDir = $root . '/scripts/.backup';
if ($apply === true && $catalogue !== []) {
    if (is_dir($backupDir) === false) {
        mkdir($backupDir, 0o775, true);
    }
    foreach (glob($overview->root() . '/*.txt') as $file) {
        $target = $backupDir . '/reihen-' . basename($file) . '.' . date('Ymd-His');
        copy($file, $target);
        echo "BACKUP: {$target}\n";
    }
}

$changed = 0;

foreach ($overview->children()->listed() as $child) {
    $uuid  = $child->uuid() ? $child->uuid()->id() : null;
    $entry = $uuid !== null ? ($catalogue[$uuid] ?? null) : null;
    $todo  = [];

    // 1. Kurztext -> intro (nur wenn die Reihenseite noch keins hat).
    if ($entry !== null && $entry['text'] !== '' && $child->intro()->isEmpty() === true) {
        $todo['intro'] = $entry['text'];
    }

    // 2. Kartenbild — nur nötig, wenn die Automatik sonst nichts findet.
    $copyFrom = null;
    if ($entry !== null && $entry['file'] !== null && $child->cardImage() === null) {
        $copyFrom = $kirby->file('file://' . $entry['file']);
        if ($copyFrom === null) {
            fwrite(STDERR, "  ! Bild file://{$entry['file']} für {$child->slug()} nicht gefunden\n");
        }
    }

    if ($todo === [] && $copyFrom === null) {
        printf("%-36s ok — nichts zu tun\n", $child->slug());
        continue;
    }

    printf("%-36s %s\n", $child->slug(), $apply ? 'MIGRIERE' : 'WÜRDE MIGRIEREN');
    if (isset($todo['intro'])) {
        echo "        intro  <- \"" . mb_substr($todo['intro'], 0, 70) . "\"\n";
    }
    if ($copyFrom !== null) {
        echo "        cover  <- " . $copyFrom->filename() . " (von der Übersicht kopiert)\n";
    }

    if ($apply === false) {
        $changed++;
        continue;
    }

    // Bild in die Reihenseite kopieren. Ein gleichnamiges File wird
    // wiederverwendet — createFile() würde bei abweichenden Bytes eine
    // DuplicateException werfen (siehe CLAUDE.md).
    if ($copyFrom !== null) {
        $existing = $child->file($copyFrom->filename());
        $file     = $existing ?? $child->createFile([
            'source'   => $copyFrom->root(),
            'filename' => $copyFrom->filename(),
            'template' => 'bild',
            'content'  => [
                'alt'     => $copyFrom->alt()->value(),
                'groesse' => 'gross',
            ],
        ]);
        // Datei-Referenzen sind translate: false und MÜSSEN mit explizitem
        // Default-Sprachcode geschrieben werden, sonst filtert das Form sie
        // in einer anderen Sprache weg (CLAUDE.md, Multi-language).
        $todo['cover'] = $file->uuid()->toString();
    }

    // update() liefert ein NEUES Seitenobjekt und friert das alte ein.
    $child = $child->update($todo, $kirby->defaultLanguage()->code());
    $changed++;
}

// 3. Den nun doppelten Katalog aus der Übersicht nehmen.
if ($catalogue !== []) {
    echo "\nreihen (Übersicht): " . ($apply ? 'LEERE' : 'WÜRDE LEEREN') . " das Textfeld"
       . " (der Katalog steht jetzt in den Karten)\n";
    if ($apply === true) {
        foreach ($kirby->languages() as $language) {
            $raw = $overview->version()->read($language->code());
            if ($raw === null || trim((string) ($raw['text'] ?? '')) === '') {
                continue;   // keine eigene Übersetzung / schon leer
            }
            $overview = $overview->update(['text' => ''], $language->code());
        }
        $changed++;
    }
}

echo "\n" . ($apply ? 'Geändert' : 'Zu ändern') . ": {$changed} Seite(n).\n";
if ($apply === false) {
    echo "Probelauf — mit --apply erneut aufrufen, um wirklich zu schreiben.\n";
} else {
    echo "Fertig. Danach den Seiten-Cache leeren: rm -rf site/cache/<domain>/pages\n";
}
