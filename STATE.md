# Kinemathek — Implementation State

Current state of the Kinemathek Karlsruhe website rebuild. Built on **Kirby 5.4.3**
(flat-file), PHP 8.4, package manager **Bun**. Replaces a WordPress + ACF site.
Spec: [`SPEC.md`](SPEC.md). Design is deliberately deferred (SPEC §10) — public
templates are primitive; the **Panel/admin UI is fully designed**. Last updated 2026-06-10.

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
- **Event** (Veranstaltung) — standalone non-film programming. **No `film` field**;
  separate template + parent ⇒ can never enter the film archive.
- A Film discovers its screenings by **reverse lookup** (`FilmPage::showings()` →
  `upcomingShowings()`/`pastShowings()`), memoised per request.
- **Categories** (multiselect: spielplan/koop/festival/filmbildung) = *placement/routing*;
  **tags** (country/language/genre/series/keywords) = *descriptive facets*. Kept distinct
  per SPEC §2.4. Directors/cast stored **structurally** (with a `tmdbpersonid` column) for
  the deferred director search (SPEC §5).

**Authoritative field contract** — see [`CLAUDE.md`](CLAUDE.md). The backend logic, TMDB
sync and ICS export all depend on these exact names; do not rename/retype them.

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

Server-side, cached, **Panel-only**. `src/Client.php`: multi-candidate `search()`,
`movie()` (+credits), `mapToFilm()`, `attachPoster()` (downloads the poster into a local
first-party file), a first-party thumbnail proxy, and TMDB `attribution()`. Auth-protected
API routes `/api/kinemathek/tmdb/{search,apply,thumb}`; `apply` authorises the real caller
(`permissions()->can('update')`/`createFile`) **before** `impersonate('kirby')`, and
respects `manualOverride`. `index.js`/`index.css`: the `tmdblookup` Panel field — no-build
inline Vue, loading/empty/error states, candidate cards, German microcopy, theme-aware CSS.

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
loader) and reads `kinemathek.tmdb.{key,token,language,posterSize,thumbSize,maxResults}`,
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
