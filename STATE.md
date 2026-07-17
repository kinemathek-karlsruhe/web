# Kinemathek — Implementation State

Current state of the Kinemathek Karlsruhe website rebuild. Built on **Kirby 5.4.3**
(flat-file), PHP 8.4, package manager **Bun**. Replaces a WordPress + ACF site.
Spec: [`SPEC.md`](SPEC.md). The **Panel/admin UI is fully designed**; public design
work is done for the core surface: **all public templates carry the Monatsblatt
design** — a web translation of the printed program sheet (prototype in
`monatsblatt.html`, untracked). The Spielplan is the homepage (`'home' =>
'program'`; `content/home` unused). Shell pieces: `monatsblatt-masthead`
(eyebrow + WP7-pivot section nav + legend + floating logo; listed top-level
text/collection/custom pages join the pivot strip automatically),
`monatsblatt-listing` (filter bar + day grid, shared by program/events/
collection), `monatsblatt-colophon`; behaviour in `assets/js/monatsblatt.js`
(pivot, content swap via fetch+pushState) + `assets/js/program.js` (listing
filters/panels/columns, re-inits on `pivot:content`). Static page styles:
`text`, `collection` (intro + category-filtered program), `custom` (raw
HTML/CSS/JS box). Typeface: Lipa Agate High Cnd (`assets/font/`, WOFF2
self-hosted, weights 300/400/500/700 only). Logo SVG inlined via
`monatsblatt-logo` (fills bound to theme vars). Content imported from the old
WordPress site via `scripts/import-program.php` and `scripts/import-static.php`
(TMDB-enriched; see `scripts/import/SCRAPE-NOTES.md` + `static/STATIC-NOTES.md`). Deferred refactors (plugin
was frozen by concurrent work when the Monatsblatt landed): move the template's
venue classifier to `OccurrenceTrait::venueKey()`, the still-beats-poster pick to
`FilmPage::artwork()`, the credits line to `FilmPage::creditsLine()`, and add
`EventPage::imageFile()` for the native-`image()` trap. Last updated 2026-07-17.

---

## 1. Run / build

```bash
php -S localhost:8000 kirby/router.php   # dev server (composer start)
                                          # (Ruby runs the site via IndigoStack)
bun install                               # deps + vendors jQuery/Fancybox + builds nothing
bun run build                             # vendor assets + minified Tailwind -> assets/css/styles.css
bun run dev                               # vendor + Tailwind --watch
```

The built CSS (`assets/css/styles.css`) and `assets/vendor/` are committed, so the site
runs without `node_modules`. TMDB credentials live in a gitignored `.env` (see §7).

---

## 2. Content model (SPEC §2) — three types, three containers

```
content/
├── films/      (template films)    → Film pages        [permanent archive]
├── program/    (template program)  → Showing pages      [the Spielplan]
├── events/     (template events)   → Event pages        [non-film programming]
└── home/ + default pages           [editorial/static]
```

- **Film** — the canonical, permanent record. No screening data on it.
- **Showing** (Vorstellung) — a dated screening of **one** Film. The relation lives
  **only here**, in the `film` pages field. Sibling of all other showings under
  `program/` (not nested under its film), so the site-wide program is a single sort.
- **Event** (Veranstaltung) — standalone event-first programming. **No `film` field**;
  separate template + parent ⇒ can never enter the film archive. It MAY reference a film
  it shows via the optional `relatedFilm` pages field (max 1) — display-only: the film
  page lists such events in an own "Veranstaltungen mit diesem Film" section
  (`FilmPage::relatedEvents()`/`upcomingRelatedEvents()`), the event page/detail panel
  links back ("Zur Filmseite"). Deliberately NOT wired into `film()`, so it never counts
  as a screening and never feeds facets, `hasUpcoming()` or the archive.
- A Film discovers its screenings by **reverse lookup** (`FilmPage::showings()` →
  `upcomingShowings()`/`pastShowings()`), memoised per request.
- **Categories** (multiselect: spielplan/koop/festival/filmbildung) = *placement/routing*;
  **tags** (country/language/genre/series/keywords) = *descriptive facets*. Kept distinct
  per SPEC §2.4. Directors/cast stored **structurally** (with a `tmdbpersonid` column) for
  the deferred director search (SPEC §5).

**Authoritative field contract** — see [`CLAUDE.md`](CLAUDE.md). The backend logic, TMDB
sync and ICS export all depend on these exact names; do not rename/retype them.

### Multi-language (DE default / EN)

Native Kirby multilang is ON (`'languages' => true` in config; definitions in
`site/languages/de.php` + `en.php`). **German is the default language at the bare root
(`url: '/'`), English lives under `/en`.** Content files carry the language code
(`film.de.txt` / `film.en.txt`); `scripts/migrate-content-multilang.php` renames a
pre-multilang content tree (idempotent, dry-run by default, `--apply` to execute) — run it
once per environment when deploying this change, since `content/` is provisioned per
environment. **No `languages.detect`** — it would store the detected language in the
session (= a cookie), violating SPEC §7; switching is two plain links in the header.

**Translation contract** (enforced via `translate: false` in the blueprints): translatable
are Film `title/synopsis/genre/series/keywords`, Showing `title/sonderinfo/keywords`,
Event `title/sonderinfo/text/keywords`, file `alt/caption`. Everything else (dates, numbers, person
structures, ISO codes, routing categories, subtitle codes, URLs, file refs, `tmdbId`,
`manualOverride`, `source`) is language-invariant and lives in the default language;
untranslated fields fall back to German automatically. Frontend UI strings resolve via
`t('kinemathek.*')` from the language files; dates render through
`Kinemathek::localDate()` / the `localDate` field method (IntlDateFormatter, ICU patterns
per language — PHP `date()` would always print English weekday names).

---

## 3. Backend logic — `site/plugins/kinemathek/` (plugin `kinemathek/core`)

- **`classes/Kinemathek.php`** — `program()` (merge showings+events, future-only,
  soonest-first, optional category restriction); `films()` (archive, films-only);
  `filterByFacets()`/`availableFacets()` (AND across facets, OR within; computed on the
  filtered set); the `FACETS` map (`country/language/genre/series` on the film,
  `subtitles` per-occurrence, `keywords` on both) with a `self`/`film`/`both` indirection;
  `splitField()`/`listValues()` (tolerant of comma- **or** YAML-stored tags/multiselect).
- **`models/`** — `FilmPage` (reverse-lookup + memo, `hasUpcoming`,
  `hasDiscussionShowing`/`hasSubtitledShowing` for archive facets, `posterFile`),
  `ShowingPage` (`film()`, `displayTitle()`), `EventPage`, and `OccurrenceTrait`
  (`timestamp`/`isPast`/default `film()=null`, shared by Showing+Event).
- **`src/Ics.php`** — RFC 5545 builder (octet-aware 75-char folding, TEXT escaping,
  Europe/Berlin `VTIMEZONE`, `Ics::respond()` shared header tail).
- **`index.php`** — registers page models, the `.ics` fileType, site methods
  (`program`/`films`/`nextOnProgram`), the `commaList` field method, and the
  `icsUid`/`icsFilename`/`icsStart` page methods; pins `Europe/Berlin` at load.
- **Controllers/templates** (`site/controllers`, `site/templates`) — program (Spielplan),
  films archive, film, showing, event, home + the `.ics` representations
  (`/<page>.ics`). Primitive HTML (Tailwind utility classes only), wrapped in
  `header`/`footer` snippets.

---

## 4. TMDB integration — `site/plugins/kinemathek-tmdb/` (SPEC §4, §7)

Server-side, cached, **Panel-only**. **Multi-language sync:** `apply` fetches the movie
bundle once per site language (Kirby code → TMDB locale via the
`kinemathek.tmdb.languages` map, cached per TMDB locale) and writes the full mapped set
into the default language plus `Client::TRANSLATABLE` (`title/synopsis/genre`) into every
other language; fill-empty is evaluated **per language** against the raw stored
translation (`$page->version()->read($lang)` — the Content object's default-language
fallback would make untranslated fields look non-empty). `search()` follows the Panel's
current content language (`x-language` header). `src/Client.php`: multi-candidate `search()`,
`movie()` (+credits,videos), `images()` (backdrops), `mapToFilm()` (incl. `trailerUrl` —
best official trailer (YouTube or Vimeo) via `trailerUrl()`, rendered public as a **plain
external link** in `film.php` whose label names the destination platform, same navigation
dialect as the ticket links; a Zwei-Klick embed was built and deliberately reverted to
keep the Datenschutzerklärung untouched), `attachPoster()` +
`attachStills()` (download poster + best textless backdrops into local first-party files
under deterministic `tmdb-poster-{id}` / `tmdb-still-{id}-{img}` names — byte-identical
re-syncs reuse the file, changed artwork replaces it, `removeTmdbImages()` cleans up
superseded TMDB files while manual uploads survive; changed artwork swaps bytes via
`File::replace`, keeping uuid/refs; a transient CDN failure keeps the last good copy), an
**on-demand** first-party thumbnail proxy (`ensureThumb()` — search no longer blocks on
image downloads; thumb URLs carry `?csrf=` because a Panel `<img>` can't send the `x-csrf`
header), and TMDB `attribution()`. Auth-protected API routes
`/api/kinemathek/tmdb/{search,apply,thumb}`; `apply` authorises the real caller
(`permissions()->can('update')` + role `files`-permissions `create`/`delete` — page-level
`can('createFile')` is an unknown action and silently returns false for real users)
**before** `impersonate('kirby')`, respects
`manualOverride`, chains every page write off the latest page object (Kirby 5 freezes the
old one), distinguishes "no artwork on TMDB" (overwrite then also cleans up a corrected
wrong match's old artwork) from "download failed" (changes nothing, suggests retry), and
reports per-asset outcomes (`poster`/`stills`/`stillsCount`/`stillsTotal`) so a failed
image download never masks applied fields.
`index.js`/`index.css`: the `tmdblookup` Panel field — no-build inline Vue. Flow: search →
candidate cards with **two apply buttons** („Übernehmen" = fill-empty / „Überschreiben" =
overwrite; no mode toggle) → on success the **search bar holds the linked film** as
„Titel (Jahr) – Regie – TMDB id" until „Lösen & neu suchen" releases it (page data is only
changed by applying a new match). Loading/empty/error states, poster/stills outcome
notices (warning theme on download failures), German microcopy, theme-aware CSS.

---

## 5. Panel / admin UI

- **Film** — three tabs: **Stammdaten** (TMDB-Suche → titles → structured Regie/Besetzung →
  synopsis; sidebar of Eckdaten, grouped *Facetten*, TMDB controls), **Medien** (poster +
  stills card galleries), **Vorführungen** (read-only `page.upcomingShowings`/`pastShowings`
  lists, `create: false`).
- **Showing / Event** — 2/3 + 1/3 column forms; header surfaces the film poster + date.
- **Containers** — table layouts (Filmarchiv: poster, year, country, "Kommt noch?",
  screening count; Programm/Veranstaltungen: date, venue, categories, Gespräch), with
  search/sort/empty states.
- **Dashboard** (`site.yml`) — a stats row (program total, **upcoming count**, archive
  size, events) linking into each container.
- **File blueprints** — focusable poster (2/3) / still (16/9) previews + alt/source/caption.

---

## 6. Front-end asset pipeline

- **Tailwind CSS v4.3** — `assets/css/index.css` uses `@import "tailwindcss" source(none)`
  + `@source "../../site"` (scans only project files, **never** the committed `kirby/` core
  → output stays ~11 KB) → minified `assets/css/styles.css` (committed).
- **jQuery 4.0** + **Fancybox 6.1** — vendored into `assets/vendor/` (+ German Fancybox
  l10n) by `scripts/vendor.mjs`; bound in `assets/js/app.js`. Loaded first-party in
  `header.php`/`footer.php`.
- **Bun** scripts in `package.json` (`vendor`/`build:css`/`watch:css`/`build`/`dev`/
  `postinstall`). `node_modules`, `content/`, `media/`, `.env` are gitignored.

---

## 7. Configuration & secrets

`site/config/config.php` loads a **gitignored `.env`** at the repo root (a tiny `getenv`
loader) and reads `kinemathek.tmdb.{key,token,language,languages,posterSize,thumbSize,stillSize,maxStills,maxResults}`,
`kinemathek.ics.{defaultDuration,timezone}`, and enables the TMDB cache via
`'cache' => ['kinemathek/tmdb' => true]`. `.env.example` (committed) is the template.
Put a TMDB **v3 API key in `TMDB_KEY`** (sent as `api_key`) — *not* `TMDB_TOKEN` (v4 JWT).

---

## 8. Privacy posture (SPEC §7 — hard requirement)

No cookies, no third-party tracking, no consent banner. All assets are vendored locally.
TMDB is fetched server-side and cached; posters are downloaded into local files;
preview thumbnails go through a first-party auth-protected proxy — the browser never
contacts TMDB. Ticket links (Mars EDV) and the TMDB attribution link are plain
navigations (`rel="noopener noreferrer"`); ICS is first-party. No iframes.

---

## 9. Verified working

Validated repeatedly this session via live HTTP smoke tests and Kirby-boot harnesses:

- Routing for all page types; chronological program (showings + events interleaved);
  film upcoming/past split.
- Faceted filtering incl. the Showing→Film hop, event exclusion, the keyword `both`-hop,
  and the films-archive `?discussion=1`/`?hasSubtitles=1` derived facets.
- Valid `.ics` per showing/event (Berlin VTIMEZONE, runtime→DTEND, CRLF, headers, escaping).
- **TMDB end-to-end with a real key**: search → candidates, `movie()` + `mapToFilm()`
  (genre/language/structured directors), and **poster download** into a local file.
- TMDB API routes return **401** to anonymous requests.
- All Panel blueprints parse (Film: 3 tabs); `tmdblookup` registers; the dashboard
  upcoming-count query resolves correctly; **clicking a row in a container table opens the
  edit view** (the title-column link fix).
- Tailwind builds and scans only `.php` under `site/`; all six front-end assets load 200.
- **Multi-language (CLI boot harness, 38 checks):** languages de/en registered; DE at
  root, EN under `/en`; blueprints parse with the translate flags; `t()` + `localDate()`
  resolve per language (Mittwoch/„Uhr" vs Wednesday); home/program/films/film render in
  both languages with localized headings and dates; hreflang alternates + `lang` attr;
  `.ics` renders in both languages; `update($vals,'en')` writes translatable fields into
  `film.en.txt`, silently drops `translate:false` fields, leaves German untouched, and
  untranslated fields fall back to German.
- **Mobile start (phones ≤760px only):** the Spielplan opens with hero + curated
  Reihen tiles (`reihen` pages field on the program page, Panel-picked Bereichsseiten;
  tile image = first `bilder` file, else first page image, else striped placeholder) and
  a „Spielplan anzeigen" button; the listing + colophon sit in a `.mb-fold` wrapper that
  program.js opens (button, Heute-Strip tap, or any `location.hash` deep link — archive
  `?past=1` renders unfolded, desktop/print unaffected). Verified via CLI render harness
  + browser preview both viewports. NB: tile class is `.reihe-tile` — the filter bar's
  series `<label>` already owns `.reihe`.

---

## 10. Deferred / open (by design)

- **Static subpages** — Filmbildung ("more than a cinema"), Projekte (Nachklang), Koop,
  Kontakt. Content-migration work, deferred to the teams (SPEC §11). A generic `default`
  page type + the `filmbildung` placement category exist; the pages themselves don't.
- **Public-facing design** (SPEC §10), **director search** (SPEC §5 — data stored
  structurally for it), **month-grid calendar** (SPEC §5), **contact form vs. details**
  (SPEC §13) — all deferred.
- **Self-hosted TMDB logo** for attribution — text + link are in place; add the logo asset
  under `/assets` before launch (do not hotlink).
- **DB backing store** — flat-file throughout; revisit only if the catalogue outgrows
  `site()->index()` scans (SPEC §1/§13).

---

## 11. Git / repo policy

Single `main` branch. **`content/`, `media/`, `node_modules/`, `.env` are gitignored**
(content is editorial data, provisioned per environment / staging "dry dock"). The Kirby
core (`kirby/`), the built `assets/css/styles.css`, `assets/vendor/`, and `bun.lock` ARE
committed. `SPEC.md`, `STATE.md`, `CLAUDE.md`, `.env.example` are committed.

> As of this writing the repo has only the initial commit; the env/credential/cache/poster
> fixes, the Panel UI pass, and the listing-link fix are **uncommitted** in the working tree.
