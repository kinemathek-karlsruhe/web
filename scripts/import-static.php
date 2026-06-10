<?php

/**
 * Content import: old WordPress site -> Kirby (static/editorial pages).
 *
 * Reads the scraped static pages (scripts/import/static/static-pages.json,
 * produced by extract-static.py — see STATIC-NOTES.md) and creates the
 * editorial page tree:
 *
 *   - 8 listed top-level pages (the pivot-strip menu, in this order):
 *     ueber-uns, festivals, filmbildung, projekte, newsletter,
 *     mitglied-werden, vermietung, kontakt
 *   - 1 unlisted top-level page: datenschutz (Impressum/Datenschutz)
 *   - 6 listed children of filmbildung/: junge-kinemathek, cinefete,
 *     i-like-films, ccaj, bilderbuchkino, kinoknirpse
 *
 * The page content (DE) is *curated* in this script: clean kirbytext/markdown
 * derived from the scraped html/text, German wording kept verbatim. Curation
 * decisions (forms replaced with e-mail pointers, iframes replaced with plain
 * video links, teaser grids stripped, …) are inlined as comments per page.
 * The JSON is still required: the script verifies every source page exists
 * and warns when its text has drifted from what was curated here.
 *
 * Usage:
 *   php scripts/import-static.php                # DRY RUN: print plan, write nothing
 *   php scripts/import-static.php --apply        # actually create content
 *   php scripts/import-static.php --input=path   # alternate JSON (default: scripts/import/static/static-pages.json)
 *
 * Idempotent: pages are skipped when one with the same slug already exists
 * under the same parent (existing pages are left untouched, including their
 * status/menu position). Safe to re-run.
 *
 * Multilang: the site is DE (default) + EN. German content is written with
 * ->update($values, 'de'); no EN content is written — EN falls back to DE.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ---------------------------------------------------------------------------
// CLI args
// ---------------------------------------------------------------------------

$apply = false;
$input = __DIR__ . '/import/static/static-pages.json';

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } elseif (preg_match('/^--input=(.+)$/', $arg, $m) === 1) {
        $input = $m[1];
    } else {
        fwrite(STDERR, "Unknown argument: {$arg}\n");
        fwrite(STDERR, "Usage: php scripts/import-static.php [--apply] [--input=path]\n");
        exit(1);
    }
}

function fatal(string $msg): never
{
    fwrite(STDERR, "FATAL: {$msg}\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Load + validate input JSON
// ---------------------------------------------------------------------------

if (is_file($input) === false) {
    fatal("Input file not found: {$input}");
}
$json = json_decode((string) file_get_contents($input), true);
if (is_array($json) === false || is_array($json['pages'] ?? null) === false) {
    fatal("Input is not valid JSON with a 'pages' array: {$input}");
}

$sources = []; // old slug => scraped record
foreach ($json['pages'] as $p) {
    if (is_array($p) === true && is_string($p['slug'] ?? null)) {
        $sources[$p['slug']] = $p;
    }
}

// ---------------------------------------------------------------------------
// Boot Kirby
// ---------------------------------------------------------------------------

require dirname(__DIR__) . '/kirby/bootstrap.php';

$kirby = new Kirby();
$kirby->impersonate('kirby');

$site     = $kirby->site();
$langCode = $kirby->multilang() ? $kirby->defaultLanguage()->code() : null; // 'de'

// ---------------------------------------------------------------------------
// Curated page specs
// ---------------------------------------------------------------------------
//
// Each spec:
//   slug      target slug
//   parent    null = site root, or the target slug of the parent page
//   template  'text' | 'collection'
//   title     page title (DE)
//   status    'listed' | 'unlisted'
//   position  menu position for listed pages (changeStatus('listed', position))
//   source    slug in static-pages.json (provenance / drift check)
//   anchors   distinctive substrings expected in the source's scraped text;
//             a missing anchor = the old page changed after curation -> warning
//   fields    blueprint fields (intro/text/categories) — clean kirbytext.
//
// Shared contact e-mail (from kontakt's contact{} block in the JSON).

const CONTACT_EMAIL = 'info@kinemathek-karlsruhe.de';

$specs = [];

// 1 ── Über uns ──────────────────────────────────────────────────────────────
// Clean prose page; wording verbatim. The em-stripped "die  PhonoLuxMaschine ."
// artefact is restored as emphasis (*PhonoLuxMaschine*, as in the source html).
$specs[] = [
    'slug' => 'ueber-uns', 'parent' => null, 'template' => 'text',
    'title' => 'Über uns', 'status' => 'listed', 'position' => 1,
    'source' => 'ueber-uns',
    'anchors' => ['Vor bald 50 Jahren', 'Soziokratie'],
    'fields' => [
        'text' => <<<'MD'
Vor bald 50 Jahren begann die Geschichte der Kinemathek Karlsruhe. Damals bildeten Mitglieder des Akademischen Filmstudios (AFK) an der Uni und der linksalternativen Werkstatt 68 eine “Initiativgruppe Kommunales Kino”, ermutigt durch das Beispiel des ersten kommunalen Kinos in Frankfurt. Nach zweimonatiger Probezeit im Jugendheim Anne Frank wurde im September 1974 der Verein “Arbeitsgemeinschaft Kommunales Kino Karlsruhe” gegründet.

1976 setzte eine spärliche Bezuschussung ein. In der Stadtverwaltung stieß man sich am Namen “Kommunales” Kino, denn eine städtische Trägerschaft gab es nicht und würde es auch nie geben. Seit der Jahreswende 1978 firmierte man als “Das Kino. Arbeitsgemeinschaft Film e.V.”.

Von Anfang an zeigte man Filme, die im kommerziellen Spielbetrieb nur geringe Chancen haben: Autorenfilme, Filmklassiker, Stummfilme, Experimental- und Avantgarde-Filme, Filme aus aller Welt, Filme in Originalfassung, Werkreihen, Präsentation mit Kommentar oder Diskussion, Stummfilme mit Klavierbegleitung.

1981 zog “Das Kino” in das neu eingerichtete Souterrain des Prinz-Max-Palais. Die sichere Basis führte zu vermehrten Aktivitäten und zu einer verstärkten Förderung durch die Stadt.

Es war die Idee des “Kinos” zu Ausstellungen oder Veranstaltungsreihen passende Filmreihen zusammenzustellen. Die Kunsthalle, der Badische Kunstverein, die Musikhochschule, das Jubez u.a. machten von diesem Angebot Gebrauch. “Das Kino” ist von Anfang an mit einem eigenen Beitrag bei den Europäischen Kulturtagen (EKT) und bei den “Frauenperspektiven” dabei. Der Verein ist Mitbetreiber der PRIDE PICTURES.

1993 benannte man den Verein und das Kino in Kinemathek um und 2010 verortete man sich am aktuellen Spielort in die Kaiserpassage 6.

In unserem Foyer, wo früher Kinomaler großflächige Gemälde anbrachten, mit denen die damaligen Kinohits beworben wurden, befindet sich seit 2020 die *PhonoLuxMaschine*. Zwei Projektoren werden anhand einer Mapping-Programmierung aufeinander abgestimmt. Dadurch können verschiedene Lichtspiele generiert werden, die auch durch die Glasfassade der Kinemathek auf den Vorplatz im Passagehof strahlen.

Seit 2025 wird die Kinemathek nach dem Prinzip der Soziokratie geführt. Soziokratie ist eine Organisationsform, die auf der Gleichwertigkeit aller Beteiligten und der Entscheidungsfindung durch Konsent basiert.
MD,
    ],
];

// 2 ── Festivals (collection) ────────────────────────────────────────────────
// The old page is a teaser-image grid only. Intro = the festival names from
// the image captions (verbatim), linked where the card linked an external
// festival site. Per-edition dates dropped (stale program state); the
// Cinema! Italia! card linked an old-site Reihe post -> kept as plain text.
$specs[] = [
    'slug' => 'festivals', 'parent' => null, 'template' => 'collection',
    'title' => 'Festivals', 'status' => 'listed', 'position' => 2,
    'source' => 'festivals-in-der-kinemathek',
    'anchors' => ['PRIDE PICTURES', 'DokKa'],
    'fields' => [
        'categories' => ['festival'],
        'intro' => <<<'MD'
- [T-Short – Animationsfilmfestival](https://t-short.art/intro/index.php/de/startseite/startseite-2/)
- [PRIDE PICTURES – Das Queer Film Festival Karlsruhe](https://pridepictures.de)
- Festivaltournee Cinema! Italia! – Kino aus Italien
- Filme aus dem Farsi-Sprachraum
- [DokKa – Dokumentarfilme und Hördokumentationen](https://dokka.de)
- Stummfilmfestival Karlsruhe
MD,
    ],
];

// 3 ── Filmbildung (collection) ──────────────────────────────────────────────
// The old page is a magazine layout with no intro prose of its own -> intro
// stays empty (program listing + subpages carry the page). The evergreen
// offerings that have NO page of their own (Schulkino, Von der Leinwand aufs
// Papier, KIT Klimamonster, Cinemini Europe) are kept verbatim in `text` so
// they aren't lost. Dropped as stale program state: Schulkinowoche (dated,
// registration deadline passed) and the Projektarchiv teasers.
$specs[] = [
    'slug' => 'filmbildung', 'parent' => null, 'template' => 'collection',
    'title' => 'Filmbildung', 'status' => 'listed', 'position' => 3,
    'source' => 'filmbildung',
    'anchors' => ['Kinosaal statt Klassenzimmer', 'Cinemini Europe'],
    'fields' => [
        'categories' => ['filmbildung'],
        'text' => <<<'MD'
## Schulkino

Kinosaal statt Klassenzimmer

Wir möchten motivierte Pädagoginnen bei der Filmbildung und Filmvermittlung unterstützen. Alle im regulären Kinoprogramm der Kinemathek angebotenen Filme, die geeignet für Schulklassen sind, können als Sondervorstellungen gebucht werden. Pädagogisches Begleitmaterial oder Filmreferentinnen liefern wir gerne dazu. Der Eintrittspreis beträgt 5 Euro pro Person bei einer Mindestannahme von 25 Schülern. Zur Anmeldung schreiben Sie uns eine E-Mail an schule@kinemathek-karlsruhe.de.

Schulen können auch spezielle Filme außerhalb unseres Programms buchen, hier fallen lediglich noch die Filmmieten und die Vorführhonorare an.

## Von der Leinwand aufs Papier

Kreatives Schreiben im Kino

Dieses Angebot verbindet Kurzfilme mit kreativem Schreiben: anhand ausgewählter Filme erhalten die Teilnehmenden passende Schreibaufgaben, um eigene Texte zu entwickeln. In einer inspirierenden Kinoatmosphäre setzen sie sich mit filmischen Erzählweisen auseinander und nutzen diese als Anregung für ihre Texte. Zum Abschluss besteht die Möglichkeit, die eigenen Texte in der Gruppe vorzulesen und sich darüber auszutauschen.

## KIT Klimamonster

Regelmäßige Trickfilm-Workshops in der Kinemathek

Kit Klimamonster Workshops in der Kinemathek rund um’s Thema Film, Animation, Natur, Klima und Selbstwirksamkeit!
Wir filmen, animieren, gucken Filme, basteln, zeichnen Storyboards, spielen und produzieren unsere eigenen Klimamonstertrickfilme!
Anmeldung unter klimamonster@kinemathek-karlsruhe.de
Mehr übers Klimamonster auf [klima-kit.de/AKTIONEN/](https://klima-kit.de/AKTIONEN/)

## [Cinemini Europe](https://cinemini-europe.eu/)

Filmpädagogisches Projekt für Kinder von 3-6 Jahren

Cinemini Europe ist ein filmpädagogisches Projekt, das sich damit beschäftigt, wie wir das Filmeschauen für Kinder im Alter von 3 bis 6 Jahren bereichernd gestalten können. Dabei spielt es keine Rolle, ob die Filme im Kino oder in der Kita gezeigt werden. Es handelt sich um kindgerechte kurze Filme, die ohne Sprache auskommen, wodurch sie besonders für junge Kinder geeignet sind. Dieses Angebot steht Kitas kostenfrei zur Verfügung und kann sowohl in der Kita selbst als auch im Kino stattfinden.
MD,
    ],
];

// 4 ── Projekte ──────────────────────────────────────────────────────────────
// The scraped text is already editorial prose (no film-teaser grid survived
// the scrape). Project names become ### headings; [Mehr]-links kept (the
// EXPECT_art link points at the old site — flagged in the import summary).
// Em-stripped CULT project title restored as emphasis.
$specs[] = [
    'slug' => 'projekte', 'parent' => null, 'template' => 'text',
    'title' => 'Projekte', 'status' => 'listed', 'position' => 4,
    'source' => 'projekte',
    'anchors' => ['EXPECT_art', 'QUARTIERSKINO'],
    'fields' => [
        'intro' => <<<'MD'
Im Rahmen unserer Filmvermittlungsarbeit nehmen wir regelmäßig an Projekten und Forschungsarbeiten teil oder leiten diese und arbeiten mit Lehrern, Partnern aus der Gemeinschaft und Programmteilnehmern zusammen, um neue Projekte zu realisieren.
Auf dieser Seite sammeln und teilen wir Informationen zu unseren aktuellen und vergangenen Projekten.
MD,
        'text' => <<<'MD'
## Aktuelle Projekte

### Open Call CULT: Modena x Karlsruhe

Im Rahmen der Aktivitäten als UNESCO Creative City of Media Arts hat Karlsruhe ein Projekt mit der Medienkunststadt Modena (Italien) initiiert. Das EU-geförderte Projekt *CULT. Dialogue and exchange of citizens on cultural heritage and media arts as tool for the creation of a stronger European identity* fördert den Austausch künstlerischer und urbaner Positionen. Das Projekt zielt darauf ab, den interkulturellen Austausch und den Dialog zwischen Bürger\*innen von den beiden Medienkunststädten zu fördern, um unser gemeinsames kulturelles Erbe zu reflektieren und Medienkunst als kreatives Werkzeug zur Stärkung einer europäischen Identität zu nutzen. Die Kinemathek freut sich Teil des Projektes zu sein.
[Mehr](https://www.karlsruhe.de/stadt-rathaus/aktuelles/meldungen/cult-modena-x-karlsruhe-discovering-media-arts)

### EXPECT_art: Erforschung und Vermittlung kultureller Kompetenz durch Kunst

EXPECT_art ist ein europäisches Forschungsprojekt zur Förderung von kultureller Kompetenz durch Kunsterziehung und wird durch ein gemeinschaftsbasiertes Forschungsdesign geleitet, das kunstbasierte Methoden einschließt und Kinder, Lehrer und Bürger als Forschungspartner direkt einbezieht.
[Mehr](https://kinemathek-karlsruhe.de/expect_art/)

### LICHTSPIEL – Netzwerk kulturelle Filmbildung

LICHTSPIEL- das neugegründete Netzwerk kulturelle Filmbildung, besteht aus Filminstitutionen, Kinematheken, Festivals, Kinos, Filmemacher\*innen und Filmvermittler\*innen. Als führende Expert:innen der kulturellen Filmbildung stehen wir seit langem für eine umfassende, innovative und multiperspektivische Vermittlungspraxis jenseits kommerzieller Interessen. Unsere Arbeitsweise ist kollaborativ und dezentral, partizipativ und international ausstrahlend – sowohl in der Praxis als auch im theoretischen Diskurs. Vor allem aber stiften wir mit unseren vielfältigen Angeboten, Projekten und Vorhaben langfristige Beziehungen zwischen einem jungen Publikum, der Filmkunst und dem Kulturort Kino.
[Mehr](https://lichtspiel-netzwerk.de/)

### DOPPELBELICHTUNG – Lust auf Kino

Wir sind eine Gruppe von Menschen mit sehr unterschiedlichen Schwerpunkten und biografischen Hintergründen. Was uns alle – auf unterschiedliche Art und Weise – verbindet, ist das Medium Film, das Kino und der Wunsch, darüber ins Gespräch zu kommen.

Unsere Idee dafür ist die DOPPELBELICHTUNG. Diese einzigartige Präsentationsweise schafft neue Zusammenhänge, regt zu Diskussionen an und eröffnet faszinierende Kommunikationsanlässe. Mit unseren Filmveranstaltungen möchten wir einen Raum der Begegnungen schaffen.
Und Lust aufs Kino machen!
[Mehr](https://doppelbelichtung.org/)

### Mehr als Kino – Vermittlungsangebote

In unserem Verständnis ist Kino mehr als nur bewegte Bilder auf einer Leinwand. Es ist eine lebendige Sprache, eine Kunstform, die Geschichten erzählt, Emotionen weckt, Denkanstöße gibt und Gemeinschaft erzeugt.
Unsere Vermittlungsarbeit basiert auf dem Konzept der nachhaltigen kulturellen Bildung. Die Broschüre liegt bei uns im Kino aus.

### Gespräche über Filmbildung

Wir haben persönliche Gespräche über Filmbildung mit Menschen geführt, auf deren Perspektive und Erfahrungen wir neugierig sind. Sie arbeiten mit Filmbildung und Filmvermittlung in unterschiedlichen Zusammenhängen, stellen sich vor und beleuchten dabei Aspekte des Themas über eine breite Zeitspanne.
[Zu den Podcasts](https://open.spotify.com/show/1rmMK9ltZiXVoGWNlR1Iqc)

## Vergangene Projekte

### QUARTIERSKINO

7 Stadtteile, 7 Filme, 7 großartige Liveshows

Das Projekt Quartierskino fand 2021 und 2023 statt. Zusammen mit Vertreterinnen und Vertretern aus einem Quartier Karlsruhes wurden Filme ausgewählt, die den Stadtteil beleuchten und seine Charakteristik hervorheben sollten. Die Filme wurden dann bei uns im Kino gezeigt. Als besondere Aktion gab es dazu für jeden ausgewählten Stadtteil eine Liveshow mit Filmgespräch, die über YouTube gestreamt werden und live bei uns besucht werden konnten.

Quartierskino wurde gefördert im Impulsprogramm Kultur nach Corona des Ministeriums für Wissenschaft, Forschung und Kunst Baden-Württemberg.
Zudem wurden wir freundlicherweise unterstützt durch die Volkswohnung GmbH Karlsruhe.

### NACHKLANG – Filmgespräche aus dem Kinosaal

Mit dieser handgemachten Reihe sind wir selbst unter die Produzenten gegangen. Es handelte sich um Gespräche mit Mitarbeiter\*innen, Vereinsmitgliedern der Kinemathek sowie Abonnent:innen von Kinemathek+, unserem ehemaligen Streamingportals über die aktuell dort laufenden Filme.

### KÜNSTLER OHNE GRENZEN: Publikum gesucht – Ein Fachvernetzungstreffen

Freitag 2. Februar 10-18 Uhr & Samstag 3. Februar 10-15 Uhr
Kulturküche, Kaiserstrasse 47

Wieviel Vielfalt braucht Karlsruhe, um kulturelle Teilhabe für alle zu ermöglichen? Wie erhalten wir die Vielfalt unserer Kulturlandschaft?
1,5 Tage lang werden wir uns mit VertreterInnen der kulturellen Einrichtungen Karlsruhes, Kulturschaffenden, in der Kulturszene aktiven BürgerInnen darüber austauschen und daran arbeiten, was es braucht, um weiterhin ein vielfältiges Kulturprogramm anzubieten, um JEDEM Menschen kulturelle Teilhabe zu ermöglichen.

### KLANGKUNST IM KINO – Kooperation mit Medienkunst Sound der HfG Karlsruhe

Seit mehreren Jahren realisiert die Kinemathek Karlsruhe gemeinsam mit Lorenz Schwarz und Dr. Paul Modler aus dem Bereich Medienkunst Sound der HfG Karlsruhe Konzerte, Ausstellungen und Workshops im Feld experimenteller Klang- und Medienkunst. Dazu zählen die Roundabout-Abschlusskonzerte, Ausstellungen sowie Konzert- und Workshopreihen. Präsentiert werden audiovisuelle Performances, Live Electronics, Mehrkanal-Sound und robotische Klangobjekte von Studierenden und internationalen Gästen. Weitere Informationen zu unserem Projektpartner: [medienkunst-sound.de](http://medienkunst-sound.de).
MD,
    ],
];

// 5 ── Newsletter ────────────────────────────────────────────────────────────
// The Contact Form 7 signup form cannot migrate (and no third-party embeds
// are allowed) -> prose kept, form replaced with an e-mail pointer.
$specs[] = [
    'slug' => 'newsletter', 'parent' => null, 'template' => 'text',
    'title' => 'Newsletter', 'status' => 'listed', 'position' => 5,
    'source' => 'newsletter',
    'anchors' => ['Programm als PDF-Datei'],
    'fields' => [
        'intro' => <<<'MD'
Melden Sie sich zu unserem Newsletter an, um das Programm als PDF-Datei zugesendet zu bekommen und über unsere aktuellen und zukünftigen Veranstaltungen und Projekte bequem und automatisch über Ihren Posteingang informiert zu werden.
MD,
        'text' => <<<'MD'
Die Anmeldung zum Newsletter ist zurzeit per E-Mail möglich: Schreiben Sie uns dafür einfach an info@kinemathek-karlsruhe.de.
MD,
    ],
];

// 6 ── Mitglied werden ───────────────────────────────────────────────────────
// Prose kept verbatim (em-stripped gender stars restored from the html:
// Besucher*innen, Freund*innen, Schüler*innen, Rentner*innen — escaped so
// markdown doesn't read them as emphasis). The Contact Form 7 membership
// form (incl. SEPA mandate fields) cannot migrate -> replaced with a short
// paragraph pointing to the contact e-mail.
$specs[] = [
    'slug' => 'mitglied-werden', 'parent' => null, 'template' => 'text',
    'title' => 'Mitglied werden', 'status' => 'listed', 'position' => 6,
    'source' => 'mitglied-werden',
    'anchors' => ['gemeinnütziger Verein', 'Jahresbeitrag'],
    'fields' => [
        'intro' => <<<'MD'
Wir laden unsere Besucher\*innen und Freund\*innen ein, Mitglied der Kinemathek Karlsruhe e.V. (wir sind ein gemeinnütziger Verein) zu werden. Mit Ihrer Mitgliedschaft fördern Sie den Erhalt und die besondere Qualität des Programms. Sie erhalten damit ermäßigten Eintritt zu allen Vorführungen der Kinemathek und die ermäßigte Abogebühr bei Kinemathek⁺. Über Filmwochen, Diskussionen, Vorträge oder einzelne Filmreihen – die von der Kinemathek Karlsruhe begleitend zum Filmprogramm veranstaltet werden – werden Sie rechtzeitig informiert. Schüler\*innen, Studierenden, Arbeitslosen und Rentner\*innen bieten wir eine ermäßigte Mitgliedschaft an.
MD,
        'text' => <<<'MD'
Wenn Sie die Kinemathek in besonderem Maße fördern möchten, besteht die Möglichkeit einer Fördermitgliedschaft.

Jahresbeitrag: 30 € / ermäßigt 20 € / Fördermitglied 90 €.

\*Ermäßigt: Schüler\*innen, Studierende, Arbeitslose, Rentner\*innen, Schwerbehinderte

Mitglieder erhalten einen ermäßigten Eintritt für unsere Vorstellungen.
Die Bezahlung läuft über eine jederzeit widerrufbare Einzugsermächtigung – diese erspart uns viel Verwaltungs- und Schreibaufwand.

Der Mitgliedsbeitrag wird jährlich zum 1. März abgebucht. Im ersten Jahr der Mitgliedschaft wird der Betrag anteilig bis zum nächsten 1. März berechnet. Eine Beendigung der Mitgliedschaft muss bis zum 31.12. erfolgen. Eine Erstattung bereits gezahlter Mitgliedsbeiträge erfolgt nicht.

Das Beitrittsformular der alten Website steht zurzeit nicht online zur Verfügung. Wenn Sie Mitglied werden möchten, schreiben Sie uns bitte eine E-Mail an info@kinemathek-karlsruhe.de.
MD,
    ],
];

// 7 ── Vermietung (old: anfrage) ─────────────────────────────────────────────
// The page was almost entirely a JS-rendered Ninja Forms booking form ->
// intro prose kept verbatim; the form's request fields (from the embedded
// form JSON, cf. STATIC-NOTES.md) documented as a "Bitte nennen Sie uns"
// list + contact e-mail.
$specs[] = [
    'slug' => 'vermietung', 'parent' => null, 'template' => 'text',
    'title' => 'Vermietung', 'status' => 'listed', 'position' => 7,
    'source' => 'anfrage',
    'anchors' => ['Wir vermieten unsere Kinosäle', 'DCP-Format'],
    'fields' => [
        'intro' => <<<'MD'
Wir vermieten unsere Kinosäle sowie die KinoBar.
MD,
        'text' => <<<'MD'
Die Säle stehen für Screenings, Pressevorführungen, Testscreenings, (Team-)Premieren und DCP-Tests zur Verfügung. Unabhängig vom Wochentag können Veranstaltungen bis 16 Uhr regulär gebucht werden; Abendtermine sind gegen einen Premiumzuschlag möglich.

Neben der Raummiete können technische Betreuung, Eventmanagement sowie Service-Personal hinzugebucht werden.

Vorführungen erfolgen grundsätzlich im DCP-Format. Andere Formate sind nach Absprache möglich und mit einer zusätzlichen Gebühr verbunden.

Ihre Vermietungsanfrage richten Sie zurzeit bitte per E-Mail an info@kinemathek-karlsruhe.de. Bitte nennen Sie uns:

- Name, E-Mail-Adresse und Telefonnummer
- Name des Events
- Art des Events (z. B. DCP-Test, Testscreening, Lesung/Ausstellung/OpenMic, Workshop)
- Privat oder öffentlich
- Anzahl der Gäste
- Datum des Events
- Zeitraum des Events (Morgens, Vormittags, Mittags, Nachmittags, Abends, Ganzer Tag oder anderer Zeitraum)
- Länge des Events
- KinoBar oder eigenes Catering
- Format des Films
- Einmaliges oder regelmäßiges Event
- Weiteres technisches Equipment (z. B. Spotlights, Mikrofon, PA)
- Beschreibung
MD,
    ],
];

// 8 ── Kontakt ───────────────────────────────────────────────────────────────
// Structured from the scraped text + the de-obfuscated contact{} block
// ((ät) -> @). No machine-readable opening hours exist on the old site —
// only "telefonisch während den Kassen-Öffnungszeiten" (kept verbatim).
$specs[] = [
    'slug' => 'kontakt', 'parent' => null, 'template' => 'text',
    'title' => 'Kontakt', 'status' => 'listed', 'position' => 8,
    'source' => 'kontakt',
    'anchors' => ['Kaiserpassage 6', 'Ticketreservierung', 'IBAN'],
    'fields' => [
        'intro' => <<<'MD'
Kinemathek Karlsruhe
Kaiserpassage 6
76133 Karlsruhe
Deutschland
MD,
        'text' => <<<'MD'
## Ticketreservierung

Online beim entsprechenden Film
oder telefonisch während den Kassen-Öffnungszeiten
Tel. +49 721 83189585

## Ansprechpersonen

**EXPECT_Art / Sonderprojekte**
Kimlotte Stöber
kimlotte.stoeber@kinemathek-karlsruhe.de

**Finanzen / Personal**
Lutz Welz
buchhaltung@kinemathek-karlsruhe.de

**Geschäftsführung**
Die Kinemathek wird soziokratisch geführt.
info@kinemathek-karlsruhe.de

**Kinotechnik / Projektion**
Michael Schier
info@kinemathek-karlsruhe.de

**Kinobar**
Craig Judkins
kinobar@kinemathek-karlsruhe.de

**Marketing und Abrechnung / Quartierskino**
Ursula Niessen-Ursprung
ursula.niessen@kinemathek-karlsruhe.de

**Programm**
Samuel Israel
israel@kinemathek-karlsruhe.de

**Schule und Kommunikation**
Carmen Beckenbach
vermittlung@kinemathek-karlsruhe.de

**Vermietung Kino und technische Infrastruktur**
Samuel Israel und Carmen Beckenbach
info@kinemathek-karlsruhe.de

**Website**
Samuel Israel
israel@kinemathek-karlsruhe.de

## Vorstand

Dr. phil. Nina Rind, Kuratorin und Mitarbeiterin an der Professur für Bau- und Architekturgeschichte, KIT
Christian Haardt, Filmemacher und Mitarbeiter audiovisuelle Archive, ZKM
Prof. Dr. Christine Reeh-Peters, Filmemacherin und Lehrende an der EVH Bochum

## Bankverbindung

Kinemathek Karlsruhe
Kaiserpassage 6, 76133 Karlsruhe
IBAN: DE88 5206 0410 0005 0107 99
MD,
    ],
];

// 9 ── Datenschutz & Impressum (old: datenschutzerklaerung) — UNLISTED ───────
// Verbatim; the source H1 is dropped (the page title covers it).
$specs[] = [
    'slug' => 'datenschutz', 'parent' => null, 'template' => 'text',
    'title' => 'Datenschutz & Impressum', 'status' => 'unlisted', 'position' => null,
    'source' => 'datenschutzerklaerung',
    'anchors' => ['§ 5 TMG', 'verwendet keine Cookies'],
    'fields' => [
        'text' => <<<'MD'
## Wer wir sind

Angaben gemäß § 5 TMG:

Kinemathek Karlsruhe e.V.
Kaiserpassage 6
76133 Karlsruhe

Kontakt:
Telefon: +49 721 83189580
E-Mail: info@kinemathek-karlsruhe.de

Registereintrag:
Eintragung im Vereinsregister.
Registergericht: Amtsgericht Mannheim
Registernummer: VR 101030

Umsatzsteuer-ID gemäß § 27 a Umsatzsteuergesetz:
DE143610294

Webseite:
Samuel Israel

Jugendschutzbeauftragter:
Fabian Schauren
E-Mail: jugendschutz@kommunale-kinos.de

Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV:
Samuel Israel i.A.

## Cookies

Die Seite verwendet keine Cookies

## Eingebettete Inhalte von anderen Websites

Beiträge auf dieser Website können eingebettete Inhalte beinhalten (z. B. Videos, Bilder, Beiträge etc.). Eingebettete Inhalte von anderen Websites verhalten sich exakt so, als ob der Besucher die andere Website besucht hätte.

Diese Websites können Daten über dich sammeln, Cookies benutzen, zusätzliche Tracking-Dienste von Dritten einbetten und deine Interaktion mit diesem eingebetteten Inhalt aufzeichnen, inklusive deiner Interaktion mit dem eingebetteten Inhalt, falls du ein Konto hast und auf dieser Website angemeldet bist.
MD,
    ],
];

// ── Children of filmbildung/ (template text, listed, in this order) ─────────

// 10 ── Junge Kinemathek ─────────────────────────────────────────────────────
// Short prose kept; the baked-in 37-film teaser grid and the dangling
// sponsor line ("Mit freundlicher Unterstützung von") dropped. The old
// /schulkino/ link is DEAD on the old site (404) — retargeted to the new
// /filmbildung page, which carries the Schulkino text.
$specs[] = [
    'slug' => 'junge-kinemathek', 'parent' => 'filmbildung', 'template' => 'text',
    'title' => 'Junge Kinemathek', 'status' => 'listed', 'position' => 1,
    'source' => 'junge-kinemathek',
    'anchors' => ['Familiensonderpreis'],
    'fields' => [
        'intro' => <<<'MD'
Bezaubernde Märchen, aufregende Teenabenteuer und wunderschöne Zeichentrickfilme: sorgfältig ausgewählte Kinderfilme zeigen wir jede Woche Sonntags um 15 Uhr zum Familiensonderpreis.
MD,
        'text' => <<<'MD'
Wir bieten alle unsere Filme auch für Schulveranstaltungen an. Mehr Informationen dazu erhalten Sie [hier](/filmbildung).
MD,
    ],
];

// 11 ── Cinéfête ─────────────────────────────────────────────────────────────
// Prose kept; the registration PDF stays a link to the old wp-content URL
// for now, marked "(PDF auf der alten Website)". The stale current-year
// film list ("## Filme" with old-site /film/ links) dropped.
$specs[] = [
    'slug' => 'cinefete', 'parent' => 'filmbildung', 'template' => 'text',
    'title' => 'Cinéfête', 'status' => 'listed', 'position' => 2,
    'source' => 'cinefete',
    'anchors' => ['CINÉFÊTE', 'Anmeldung_Cinefete_26-1.pdf'],
    'fields' => [
        'text' => <<<'MD'
CINÉFÊTE ist ein traditionsreiches und beliebtes Schulfilmfestival, das seit dem Jahr 2000 durch deutsche Programmkinos tourt und inzwischen rund 100.000 Schülern jährlich den Umgang mit frankophoner Filmkunst und der französischen Sprache ermöglicht.

Um Ihre Klasse anzumelden, finden Sie [hier](https://kinemathek-karlsruhe.de/wp-content/uploads/2026/01/Anmeldung_Cinefete_26-1.pdf) das Programm mit Anmeldeformular *(PDF auf der alten Website)* und senden es uns per E-Mail an cinefete@kinemathek-karlsruhe.de. Wir melden uns, sobald wir Ihre Anmeldung bestätigen können.

Schulmaterialien und Infos zu den Filmen finden sich auf [https://cinefete.de/](https://cinefete.de/).
MD,
    ],
];

// 12 ── I Like Films ─────────────────────────────────────────────────────────
// Full pedagogical content kept verbatim. The 5 video iframes (4x YouTube,
// 1x Dailymotion — third-party embeds, forbidden) are replaced with plain
// "Video ansehen:" links right where each trailer sat (after its film's
// heading, order verified against the source html). Registration PDF ->
// old wp-content URL with "(PDF auf der alten Website)".
$specs[] = [
    'slug' => 'i-like-films', 'parent' => 'filmbildung', 'template' => 'text',
    'title' => 'I Like Films', 'status' => 'listed', 'position' => 3,
    'source' => 'i-like-films',
    'anchors' => ['Britfilms', 'Anmeldung_I_Like_Films_26_n.pdf', 'Canterbury Panther'],
    'fields' => [
        'intro' => <<<'MD'
Mit I Like Films bringt die Kinemathek aktuelle englischsprachige Filme aus der ganzen Welt auf die große Leinwand – speziell für Schüler\*innen. Das Festival schließt an die Tradition von Britfilms an, das deutschlandweit eingestellt wurde.
Mit spannenden Geschichten und authentischer Sprache bietet es eine lebendige Ergänzung zum Englischunterricht und macht Kino zum Lernort.
MD,
        'text' => <<<'MD'
Gezeigt werden:

- Paddington in Peru (UK) – Abenteuer & Familie, 106 Min. – empfohlen ab Klasse 3
- Lilly und die Kängurus (Australien) – Natur & Freundschaft, 107 Min. – empfohlen ab Klasse 5
- Bookworm (Neuseeland) – Fantasie & Mut, 103 Min. – empfohlen ab Klasse 6
- I Like Movies (Kanada) – Coming of Age & Jugendkultur, 99 Min. – empfohlen ab Klasse 9
- The Whale and the Raven (Kanada) – Umwelt & Klima, 101 Min. – empfohlen ab Klasse 10

Eintritt: 5 € pro Schüler\*in, Begleitpersonen frei.
Das Anmeldeformular findet sich [hier](https://kinemathek-karlsruhe.de/wp-content/uploads/2026/01/Anmeldung_I_Like_Films_26_n.pdf) *(PDF auf der alten Website)*.
Anmeldung über vermittlung@kinemathek-karlsruhe.de

## Paddington in Peru

(UK, 106 Min.) – ab Klasse 3

Video ansehen: [https://www.youtube.com/watch?v=lKgitu25ZAg](https://www.youtube.com/watch?v=lKgitu25ZAg)

Paddington, der liebenswerte Bär aus Peru, erlebt humorvolle und spannende Abenteuer in London. Der Film erzählt von Freundschaft, Zusammenhalt und Mut und besticht durch eine Mischung aus Live-Action und Animation.

Besonderheiten: leicht verständliches Englisch, positive Werte, fantasievolle Abenteuer.

Filmbildungsangebote:

- Charakteranalyse: Eigenschaften und Handlungen von Paddington erkunden
- Abenteuerreise: Orte und Herausforderungen auf einer Karte oder im Tagebuch festhalten
- Szenen nachspielen oder eigene Dialoge erfinden
- Diskussionen zu Freundschaft, Mut und Hilfsbereitschaft
- Kurze Film- oder Stop-Motion-Projekte inspiriert von Paddingtons Erlebnissen
- Kulturelle Brücke: Peru und London vergleichen

Englische Vorschläge:

- Character Study: Explore Paddington’s personality, values, and decision-making. Compare his traits with other characters or with students’ own experiences.
- Adventure and Story Mapping: Create maps or diaries of Paddington’s journey, noting challenges and key events.
- Values and Ethics: Discuss themes of friendship, kindness, courage, and helping others. Ask students: “What would you do in Paddington’s situation?”
- Creative Filmmaking: Encourage students to produce short stop-motion or animated films inspired by Paddington’s adventures.
- Cultural Exploration: Compare London and Peru, exploring cultural differences and similarities.

## Lilly und die Kängurus

(Australien, 107 Min.) – ab Klasse 5

Video ansehen: [https://www.youtube.com/watch?v=-WOlXJ-fpQQ](https://www.youtube.com/watch?v=-WOlXJ-fpQQ)

Der Film erzählt die Geschichte von Lilly, die verwaiste Kängurus im australischen Outback aufzieht. Humorvoll und einfühlsam vermittelt er Werte wie Freundschaft, Verantwortung und den respektvollen Umgang mit Tieren. Beeindruckende Landschaften und authentische Figuren machen den Film zu einem visuellen Erlebnis.

Besonderheiten: wahre Geschichte, Natur- und Tierschutz, indigene Perspektiven, emotionale und humorvolle Erzählweise.

Filmbildungsangebote:

- Tier- und Naturschutz: Lebensweise von Kängurus erforschen und Schutzmaßnahmen diskutieren
- Verantwortung und Empathie: Handlungen der Figuren reflektieren
- Szenenanalyse: Filmtechnik, Kamera, Musik und Bildgestaltung untersuchen
- Kreatives Arbeiten: eigene kleine Film- oder Stop-Motion-Projekte zu Natur- oder Tiergeschichten
- Kulturelle Perspektiven: indigene Kultur Australiens kennenlernen und mit Handlung verbinden

Englische Vorschläge:

- Animal and Nature Education: Explore kangaroo behavior and discuss wildlife conservation. Encourage students to research Australian wildlife.
- Responsibility and Empathy: Analyze the characters’ actions and discuss the importance of taking responsibility for others and the environment.
- Cultural Awareness: Introduce aspects of Indigenous Australian culture and their connection to nature, comparing them with the film’s representation.
- Storytelling and Film Techniques: Examine how cinematography, sound, and editing create emotional impact and highlight landscapes.
- Creative Projects: Have students create short films, stop-motion sequences, or illustrated stories inspired by the characters’ adventures.

## Bookworm

(Neuseeland, 96 Min.) – ab Klasse 6

Video ansehen: [https://www.youtube.com/watch?v=idGQkAoHHyY](https://www.youtube.com/watch?v=idGQkAoHHyY)

Bookworm erzählt die Geschichte der 11-jährigen Mildred, die zusammen mit ihrem entfremdeten Vater Strawn Wise eine abenteuerliche Reise durch die neuseeländische Wildnis unternimmt, um den sagenumwobenen Canterbury Panther zu finden. Dabei entwickeln Vater und Tochter eine besondere Bindung, während sie spannende Herausforderungen meistern.

Besonderheiten:
Der Film besticht durch seine humorvolle und zugleich tiefgründige Erzählweise, die starken schauspielerischen Leistungen, insbesondere von Elijah Wood und Nell Fisher, sowie die beeindruckende Darstellung der neuseeländischen Landschaft. Mythologie und Abenteuer werden geschickt miteinander verbunden und machen Bookworm zu einem einfühlsamen, humorvollem Erlebnis für junge Zuschauer\*innen.

Filmbildungsangebote:

- Analyse der Vater-Tochter-Beziehung und der Figurenentwicklung
- Erforschung der Legende des Canterbury Panthers und ihrer filmischen Umsetzung
- Diskussion über Natur, Umwelt und die Rolle der Landschaft im Film
- Untersuchung von Filmtechnik, Kamera und visuellen Stilmitteln
- Kreatives Schreiben eigener Kurzgeschichten über Abenteuer oder Mythen

Englische Vorschläge:

- Character Development: Analyze the evolving relationship between Mildred and her father. Discuss motivations, conflicts, and growth throughout the story.
- Adventure and Myth: Explore the legend of the Canterbury Panther. Students can research local myths or legends and compare them to the film’s adaptation.
- Nature and Environment: Discuss the role of New Zealand’s landscape in shaping the narrative. Consider themes of conservation and human interaction with nature.
- Storytelling Techniques: Examine how humor, suspense, and visual storytelling are used to engage the audience. Analyze camera work, editing, and music.
- Creative Writing: Encourage students to write their own short adventure stories or myths inspired by the film.

## I Like Movies

(Kanada, 99 Min.) – ab Klasse 9

Video ansehen: [https://www.youtube.com/watch?v=godMZzeWTu0](https://www.youtube.com/watch?v=godMZzeWTu0)

Der Film erzählt die Geschichte eines Jugendlichen, der seine Leidenschaft für Filme entdeckt und dabei persönliche Herausforderungen und Freundschaften meistert. Humorvolle und authentische Szenen zeigen Coming-of-Age-Erlebnisse und typische Teenagerprobleme in Kanada.

Besonderheiten: leicht verständliches Englisch, authentische jugendliche Perspektive, Humor und emotionale Tiefe.

Filmbildungsangebote:

- Charakteranalyse: Entwicklung des Protagonisten und seiner Beziehungen erkunden
- Coming-of-Age-Reise: Herausforderungen, Erfolge und Wendepunkte auf einer Zeitleiste festhalten
- Szenen nachspielen oder eigene Dialoge schreiben
- Diskussionen zu Freundschaft, Selbstfindung und Entscheidungen
- Kreative Projekte: kurze Filmsequenzen oder Storyboards entwickeln
- Kulturelle Brücke: kanadische Jugendkultur mit eigener Realität vergleichen

Englische Vorschläge:

- Character Study: Explore the protagonist’s personality, relationships, and growth throughout the film.
- Coming-of-Age Journey: Create timelines or diaries noting key events and turning points.
- Values and Reflection: Discuss themes of friendship, identity, and personal choices.
- Creative Filmmaking: Develop short film sequences or storyboards inspired by the movie.
- Cultural Exploration: Compare Canadian teenage life with students’ own experiences.

## The Whale and the Raven

(Kanada, 101 Min.) – ab Klasse 10

Video ansehen: [https://www.dailymotion.com/video/x7xj62x](https://www.dailymotion.com/video/x7xj62x)

Der Dokumentarfilm begleitet die Heiltsuk-Gemeinschaft an der kanadischen Pazifikküste in ihrem Widerstand gegen industrielle Eingriffe in ihren Lebensraum. Persönliche Perspektiven, indigene Identität und der Schutz der Natur stehen im Mittelpunkt. Der Film verbindet Umwelt- und Klimathemen mit Fragen von kultureller Selbstbestimmung, Verantwortung und globalen wirtschaftlichen Interessen. Die ruhige, eindringliche Erzählweise lädt zur vertieften Auseinandersetzung und Diskussion ein.

Besonderheiten: starke Naturaufnahmen, authentische indigene Perspektiven, Verbindung von Umwelt-, Kultur- und Gesellschaftsthemen, gut verständliches Englisch mit dokumentarischem Stil.

Filmbildungsangebote:

- Charakter- und Themenanalyse: Indigene Identität, Gemeinschaft und Umweltverantwortung untersuchen
- Dokumentarfilm-Analyse: Erzählweise, Perspektiven und Rolle der Filmemacher\*innen reflektieren
- Diskussionsrunden: Umweltschutz, indigene Rechte und wirtschaftliche Interessen diskutieren
- Kreative Projekte: Eigene kurze Dokumentationen, Podcasts oder Präsentationen zu lokalen Umweltkonflikten erstellen
- Globale Perspektiven: Parallelen zwischen den im Film gezeigten Konflikten und regionalen/globalen Umweltfragen ziehen

Englische Vorschläge:

- Character and Theme Analysis: Explore Indigenous identity, community, and environmental responsibility.
- Documentary Analysis: Examine narrative perspective, authenticity, and filmmaking choices.
- Visual Storytelling: Analyze nature imagery, symbolism, and cinematography.
- Discussion and Debate: Discuss environmental protection, Indigenous rights, and economic interests.
- Creative Projects: Create short documentaries, podcasts, or presentations on local environmental issues.
- Global Perspectives: Compare the film’s environmental conflicts with local and global experiences.
MD,
    ],
];

// 13 ── CCAJ ─────────────────────────────────────────────────────────────────
// Slug shortened to 'ccaj'; the ALL-CAPS source title de-screamed. Dangling
// sponsor-logo lines ("Im Rahmen von / In Zusammenarbeit mit / Mit
// Unterstützung von") dropped; the bare results-blog URL made a link.
$specs[] = [
    'slug' => 'ccaj', 'parent' => 'filmbildung', 'template' => 'text',
    'title' => 'Le Cinéma, cent ans de jeunesse (CCAJ)', 'status' => 'listed', 'position' => 4,
    'source' => 'le-cinema-cent-ans-de-jeunesse-ccaj',
    'anchors' => ['Helmholtz Gymnasiums', 'Jeunes lumières'],
    'fields' => [
        'text' => <<<'MD'
Die Kinemathek nimmt im Schuljahr 2023/2024 das erste Mal an dem Programm „Le Cinéma, Cent Ans de Jeunesse” teil. Gemeinsam mit der Filmemacherin Tschekideh Schahabian und der Betreuerin Annika Herynek beschäftigten sich Schüler:innen des Helmholtz Gymnasiums Karlsruhe mit der filmischen Fragestellung „Das Andere filmen – die dokumentarische Geste”. Der Kurzfilm gibt einen Einblick in das Werk der Tänzerin Gabriela Lang.

Einen Überblick über die Ergebnisse (auch anderer Städte/Schulen) gibt es hier:
[http://blogcinemacentansdejeunesse.org/filmer-l-autre/films-essais/](http://blogcinemacentansdejeunesse.org/filmer-l-autre/films-essais/)

Kontakt:
Projektkoordination
Marc Teuscher
E-Mail: marc.teuscher@kinemathek-karlsruhe.de

Le Cinéma, cent ans de jeunesse (Kino, 100 Jahre jung) wurde 1995 zum 100-jährigen Jubiläum des Kinos in Frankreich gegründet. Ziel war die Entwicklung eines Bildungssystems für Film, das praktische Erfahrung im Filmemachen mit filmischer Analyse verbindet. Dieses Projekt entstand aus der Zusammenarbeit von Archivzentren wie La Cinémathèque Française, L‘Institut Lumière und La Cinémathèque de Toulouse sowie dem Kino L‘Edenle Volcan in Le Havre. Die Gründer:innen waren Alain Bergala und Nathalie Bourgeois u. a. Erste Workshops fanden 1994/95 in Frankreich statt, aus denen der Film ‹Jeunes lumières› entstand, der auf Festivals gezeigt wurde. Der Film kombiniert 60 Minuten Material aus 300 Super-8-Filmen, die von Kindern und Jugendlichen erstellt wurden.
MD,
    ],
];

// 14 ── Bilderbuchkino ───────────────────────────────────────────────────────
// Prose + booking info kept; the dated special-screening list and the
// Stadtbibliothek film teasers (stale program state) dropped.
$specs[] = [
    'slug' => 'bilderbuchkino', 'parent' => 'filmbildung', 'template' => 'text',
    'title' => 'Bilderbuchkino', 'status' => 'listed', 'position' => 5,
    'source' => 'bilderbuchkino',
    'anchors' => ['Bildergeschichten auf der großen Kinoleinwand'],
    'fields' => [
        'intro' => <<<'MD'
Wir lesen animierte Bildergeschichten auf der großen Kinoleinwand! Macht es euch in den Sesseln der Kinemathek gemütlich und begleitet uns beim eintauchen in liebevoll gestaltete Bilderwelten.
MD,
        'text' => <<<'MD'
## Sondervorführungen für Schulklassen und Kindergarten

Mit Anmeldung über die Kinemathek an vermittlung@kinemathek-karlsruhe.de
MD,
    ],
];

// 15 ── KinoKnirpse ──────────────────────────────────────────────────────────
// Fully clean page; "•" bullets normalized to "-", the Dauer/Teilnehmende/
// Alter/Anmeldung block set as a list.
$specs[] = [
    'slug' => 'kinoknirpse', 'parent' => 'filmbildung', 'template' => 'text',
    'title' => 'KinoKnirpse', 'status' => 'listed', 'position' => 6,
    'source' => 'kinoknirpse',
    'anchors' => ['Kita-Alter', 'FFA Filmförderungsanstalt'],
    'fields' => [
        'intro' => <<<'MD'
Kino entdecken mit allen Sinnen. Unser kostenloses Angebot für Kindergärten.
MD,
        'text' => <<<'MD'
Wie kommt der Film eigentlich auf die Leinwand?

Wie fühlt sich die Leinwand an?

Und was passiert im Kino, bevor das Licht ausgeht?

Bei unserem KinoKnirpse-Angebot entdecken Kinder im Kita-Alter mit allen Sinnen spielerisch die Welt des Kinos. Gemeinsam schauen wir hinter die Kulissen der Kinemathek, dürfen die Leinwand anfassen, den Kinosaal erforschen und erleben kurze Filmbeispiele.

Inhalte:

- kindgerechte Einführung: Was ist ein Film?
- Entdeckungstour durchs Kino
- Leinwand & Projektion zum Anfassen und Staunen
- altersgerechte Kurzfilme oder Filmsequenzen

Auf einen Blick:

- Dauer: ca. 60–75 Minuten, kostenlos
- Teilnehmende: max. 10 Kinder + 2 Begleitpersonen
- Alter: Kita-Kinder (3–6 Jahre)
- Anmeldung: vermittlung@kinemathek-karlsruhe.de

Wir danken der FFA Filmförderungsanstalt für finanzielle Unterstützung.
MD,
    ],
];

// Source pages deliberately NOT migrated (cf. STATIC-NOTES.md):
//   events — the new site has its own /events container (program import).
const SKIPPED_SOURCES = ['events'];

// ---------------------------------------------------------------------------
// Plan
// ---------------------------------------------------------------------------

$warnings = [];

// Existing-content index (idempotency by slug, per parent).
$existsAt = function (?string $parentSlug, string $slug) use ($site): ?\Kirby\Cms\Page {
    $parent = $parentSlug === null ? $site : $site->find($parentSlug);
    if ($parent === null) {
        return null; // parent doesn't exist yet -> child can't either
    }
    return $parent->childrenAndDrafts()->find($slug);
};

echo "== Static-page import plan ({$input})" . ($apply ? ' [APPLY]' : ' [DRY RUN]') . " ==\n";
echo 'Scraped pages in input: ' . count($sources)
    . ' (migrating ' . count($specs) . ', skipping: ' . implode(', ', SKIPPED_SOURCES) . ")\n\n";

foreach ($specs as $i => $spec) {
    $src  = $sources[$spec['source']] ?? null;
    $note = '';

    if ($src === null) {
        $warnings[] = "{$spec['slug']}: source page \"{$spec['source']}\" missing from JSON";
        $note = '!! source missing from JSON';
    } else {
        // Drift check: curated content was derived from this exact scrape.
        $haystack = ($src['text'] ?? '') . "\n" . ($src['html'] ?? '');
        foreach ($spec['anchors'] as $anchor) {
            if (str_contains($haystack, $anchor) === false) {
                $warnings[] = "{$spec['slug']}: anchor \"{$anchor}\" no longer in scraped source "
                    . "\"{$spec['source']}\" — old page changed since curation, review the curated text";
            }
        }
    }

    $existing = $existsAt($spec['parent'], $spec['slug']);
    if ($existing !== null) {
        $specs[$i]['action'] = 'skip';
        $note = 'already exists (' . $existing->id() . ')';
    } else {
        $specs[$i]['action'] = 'create';
    }

    $path   = ($spec['parent'] !== null ? $spec['parent'] . '/' : '') . $spec['slug'];
    $status = $spec['status'] . ($spec['position'] !== null ? ' #' . $spec['position'] : '');
    $sizes  = [];
    foreach (['intro', 'text'] as $f) {
        if (isset($spec['fields'][$f]) === true) {
            $sizes[] = $f . ' ' . mb_strlen($spec['fields'][$f]) . ' chars';
        }
    }
    if (isset($spec['fields']['categories']) === true) {
        $sizes[] = 'categories: ' . implode(',', $spec['fields']['categories']);
    }
    printf(
        "  [%-6s] %-30s %-10s %-12s %s\n",
        $specs[$i]['action'],
        $path,
        $spec['template'],
        $status,
        $note !== '' ? $note : '(' . implode('; ', $sizes) . ')'
    );
}

$creates = count(array_filter($specs, fn ($s) => $s['action'] === 'create'));
$skips   = count($specs) - $creates;
echo "\nSUMMARY\n  pages: {$creates} to create, {$skips} skipped\n";
echo "  menu order: ueber-uns, festivals, filmbildung, projekte, newsletter, mitglied-werden, vermietung, kontakt\n";

if ($warnings !== []) {
    echo "\nWARNINGS\n";
    foreach (array_unique($warnings) as $w) {
        echo "  ! {$w}\n";
    }
}

if ($apply === false) {
    echo "\nDry run only — nothing was written. Re-run with --apply to create content.\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// APPLY
// ---------------------------------------------------------------------------

echo "\n== Applying ==\n";
$errors = 0;

foreach ($specs as $spec) {
    if ($spec['action'] !== 'create') {
        continue;
    }
    $path = ($spec['parent'] !== null ? $spec['parent'] . '/' : '') . $spec['slug'];
    echo "page {$path}: ";
    try {
        $parent = $spec['parent'] === null ? $site : $site->find($spec['parent']);
        if ($parent === null) {
            throw new \RuntimeException("parent page \"{$spec['parent']}\" missing (creation failed earlier?)");
        }

        // Create as draft with the title, then write the German content.
        $page = $parent->createChild([
            'slug'     => $spec['slug'],
            'template' => $spec['template'],
            'content'  => ['title' => $spec['title']],
        ]);

        // update() returns a NEW page object — always rechain. EN stays
        // untranslated and falls back to German.
        $page = $page->update($spec['fields'], $langCode);

        // Publish: listed pages get their pivot-strip menu position.
        $page = $spec['status'] === 'listed'
            ? $page->changeStatus('listed', $spec['position'])
            : $page->changeStatus('unlisted');

        echo "created ({$spec['template']}, {$spec['status']}"
            . ($spec['position'] !== null ? " #{$spec['position']}" : '') . ")\n";
    } catch (\Throwable $e) {
        $errors++;
        echo "ERROR: {$e->getMessage()}\n";
    }
}

echo "\nDone" . ($errors > 0 ? " with {$errors} error(s)" : '') . ".\n";
exit($errors > 0 ? 1 : 0);
