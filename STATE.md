# Kinemathek — Implementation State

Status of the first backend pass against `SPEC.md`. Built on **Kirby 5.4.3** (flat-file),
PHP 8.4, package manager **Bun**. Design is deliberately deferred (SPEC §10) — templates
are primitive (structure only). Date of this pass: 2026-06-09.

---

## 1. How to run

```bash
bun install              # installs deps + vendors jQuery/Fancybox into assets/vendor
bun run build            # vendor + build minified Tailwind CSS -> assets/css/styles.css
bun run dev              # vendor + Tailwind --watch
php -S localhost:8000 kirby/router.php   # or: composer start
```

The built CSS (`assets/css/styles.css`) and `assets/vendor/` are committed so the site
runs without `node_modules`. TMDB needs a key in `site/config/config.php`
(`kinemathek.tmdb.key` or `.token`) — without it the public site is unaffected; only the
Panel lookup is disabled.

---

## 2. Content model (SPEC §2)

Three first-class page types under three container pages, plus a clean
placement-vs-facets taxonomy.

### Page tree
```
films/      (template: films)    → children are Film pages        [permanent archive]
program/    (template: program)  → children are Showing pages      [the Spielplan]
events/     (template: events)   → children are Event pages        [non-film programming]
home        (template: home)
```
Showings live under `program/` as siblings (NOT under their film), so the site-wide
chronological program is a single sort and showings/events interleave by date.

### Film — `site/blueprints/pages/film.yml`
Canonical, permanent record. Fields: `title`, `originalTitle`, **structured**
`directors` (name + tmdbpersonid) and `cast` (name + role + tmdbpersonid),
`synopsis`, `year`, `runtime`, `country`/`language`/`genre`/`series`/`keywords`
(tags = facets), `poster` (files, max 1) + `stills` (files), `tmdbId`,
`manualOverride` (locks TMDB sync), and a `tmdblookup` Panel field. A Film exists
independently of any showing (SPEC §3).

### Showing — `site/blueprints/pages/showing.yml`
A dated screening of **one** Film. Fields: `film` (single-select pages relation,
required), optional `title` (falls back to film title), `date` (date+time, required),
`venue`, `subtitles` (multiselect OmU/OmeU/…), `hasDiscussion` (toggle),
`categories` (multiselect placement), `keywords`, `sonderinfo`, `ticketUrl` (Mars EDV).
`num` encodes date+time for chronological Panel ordering.

### Event — `site/blueprints/pages/event.yml`
Standalone non-film programming. Reuses the occurrence mechanics (`date`, `endDate`,
`venue`, `categories`, `subtitles`, `hasDiscussion`, `keywords`, `ticketUrl`, `text`,
`image`) but has **no film field** and lives under `events/`, so it can never enter the
film archive (the archive query is template/parent-scoped).

### Taxonomy distinction (SPEC §2.4)
- **Categories** = placement/routing — `multiselect` (spielplan / koop / festival /
  filmbildung), multiple allowed.
- **Tags/keywords** = descriptive facets for filtering — individually typed tag fields,
  retiring the old single "Schlagwort".

### File blueprints
`files/poster.yml` (alt, source=TMDB) and `files/still.yml` (alt, caption, source) —
required so the TMDB poster download (`createFile(template: 'poster')`) and the Film
file fields work.

---

## 3. Backend logic — `site/plugins/kinemathek/`

One plugin (`kinemathek/core`) registers everything; sets `date_default_timezone_set`
to the configured venue zone so "today", program ordering and ICS local time agree.

- **`classes/Kinemathek.php`** — static, side-effect-free logic:
  - `program(opts)` — merges all showings + events, future-only (optionally incl.
    today), optional category restriction, sorted soonest-first. The unified "what's on".
  - `films()` — the archive source (`page('films')->children()`); events excluded.
  - `filterByFacets(pages, facets)` — AND across facets, OR within a multi-value facet;
    absent facets don't constrain; returns ALL matches (no pagination). Plus boolean
    facets `discussion` (→ `hasDiscussion`) and `hasSubtitles`.
  - `availableFacets(pages)` — derives facet values + counts from the data.
  - `FACETS` map with a `self` / `film` / `both` source indirection:
    `country`/`language`/`genre`/`series` live on the film, `subtitles` on the
    occurrence, `keywords` on **both** (a showing matches its own and its film's
    keywords). For a Showing, film facets hop to the linked Film; an Event yields nothing
    for film facets; a Film page in the archive reads itself.
  - `listValues(field)` — tolerant reader for tags/multiselect stored as either
    comma-separated or YAML list.
- **`models/FilmPage.php`** — `showings()` (reverse lookup), `upcomingShowings()` /
  `pastShowings()` (sorted; upcoming asc, past desc), `hasUpcoming()`,
  `hasDiscussionShowing()` / `hasSubtitledShowing()` (archive facets across a film's
  screenings), `directors()`, `posterFile()`.
- **`models/ShowingPage.php`** — `film()` (resolves the linked Film), `timestamp()`
  (reads `date`), `isPast()`, `displayTitle()` (reads the raw content title to avoid
  Kirby's slug fallback, else the film title).
- **`models/EventPage.php`** — `film()` returns null (uniform interface), `timestamp()`,
  `isPast()`, `displayTitle()`.
- **`fieldMethods.commaList`** — renders tags/multiselect cleanly (case-preserving).
- **`pageMethods`** — `icsUid` (stable, UUID-anchored), `icsFilename`
  (uses `displayTitle`), `icsStart` (DateTime in venue zone).
- **`siteMethods`** — `program`, `films`, `nextOnProgram`.

### Controllers (`site/controllers/`)
`program` (Spielplan: facets from query string → filtered program + available facets),
`films` (archive: descriptive facets + the two per-showing archive facets, upcoming-first
then newest year), `film` (upcoming/past/directors), `showing` (linked film, other
showings), `event`, `home` (next / featured = festival OR Filmgespräch / overview).

### Templates (`site/templates/`)
Primitive HTML wrapped in header/footer: `program`, `films`, `film` (poster + stills via
Fancybox; upcoming clickable, past non-clickable per SPEC §3), `showing`, `event`,
`home`, `default`, plus the two `.ics` representations. Shared snippets:
`header`, `footer`, `program-item`, `add-to-calendar`.

---

## 4. TMDB integration — `site/plugins/kinemathek-tmdb/` (SPEC §4, §7)

- **`src/Client.php`** — server-side v3 client. `search()` returns **multiple
  candidates** (editor confirms, never auto-accept); `movie()` fetches detail + credits;
  `mapToFilm()` maps to Film fields (genre/language keys reconciled, structured
  directors/cast); `attachPoster()` downloads the poster server-side into a real
  first-party file; thumbnails cached server-side. All responses cached via
  `kirby()->cache('kinemathek/tmdb')` (TTLs: search 1d, movie 30d, config 7d).
- **`index.php`** — Panel API routes (`/api/kinemathek/tmdb/{search,apply,thumb}`),
  auth-protected by Kirby's API layer. `apply` honours `manualOverride` and **authorizes
  the real caller** (`update` / `createFile` permission) before any privileged work.
  `thumb` is a first-party proxy so the browser never contacts image.tmdb.org.
  Required attribution via the `tmdbAttribution` site method.
- **`index.js` / `index.css`** — the `tmdblookup` Panel field (inline Vue template, no
  build step needed; German labels).

---

## 5. ICS / add-to-calendar — (SPEC §6, §7)

- **`src/Ics.php`** — RFC 5545 builder: octet-aware 75-char line folding, TEXT escaping,
  UTC `DTSTAMP`, local `DTSTART`/`DTEND` with `TZID=Europe/Berlin` + a matching
  `VTIMEZONE`.
- **`templates/showing.ics.php`** — `DTEND` from the film's runtime (fallback to
  `kinemathek.ics.defaultDuration`); SUMMARY from the linked film; description from
  Sonderinfo + Fassung + ticket link.
- **`templates/event.ics.php`** — `DTEND` from `endDate` if present, else default;
  description from `text` + ticket link.
- Both set `text/calendar` + a `Content-Disposition` attachment filename. First-party,
  no cookies. Reachable as `/<page>.ics` (the `add-to-calendar` snippet links to it).

---

## 6. Front-end asset pipeline

- **`package.json`** (Bun) — `build`, `dev`, `vendor`, `build:css`, `watch:css`;
  `postinstall` vendors runtime libs.
- **Tailwind CSS v4.3** — `assets/css/index.css` uses `@import "tailwindcss" source(none)`
  + `@source "../../site"` (scans only project templates/snippets/plugins, not the
  committed `kirby/` core) → minified `assets/css/styles.css`.
- **jQuery 4.0** + **Fancybox 6.1** — `scripts/vendor.mjs` copies them (+ German Fancybox
  l10n) into `assets/vendor/`; `assets/js/app.js` binds Fancybox to `[data-fancybox]`.
- **`site/snippets/header.php` / `footer.php`** — load the built CSS + Fancybox CSS in
  `<head>` and jQuery → Fancybox → l10n → app.js before `</body>`, all first-party.

---

## 7. Privacy posture (SPEC §7) — hard requirement

No cookies, no third-party tracking, no consent banner. All assets (Tailwind/jQuery/
Fancybox) are vendored locally. TMDB is fetched server-side and cached; posters are
downloaded into local files; preview thumbnails go through a first-party auth-protected
proxy. Ticket links (Mars EDV) and the TMDB attribution link are plain navigations, not
embeds. ICS is first-party static-style content. No iframe anywhere.

---

## 8. Verified working (live, `php -S`, 12/12 checks)

Routing for all page types; chronological program with showings/events interleaved
(soonest first); film upcoming/past split; faceted filtering incl. the Showing→Film hop
and event exclusion; film-keyword facet hopping to the film; archive "has discussion"
facet; available-facets narrowing to the filtered set; valid `.ics` (Berlin VTIMEZONE,
runtime→DTEND, CRLF, headers, single clean date in filename); homepage next/featured/
overview; Panel boots; TMDB API returns **401** to unauthenticated requests; Tailwind
builds and scans `.php`; all six front-end assets return 200.

## 9. Implemented but NOT end-to-end tested (honest caveats)

- **TMDB live round-trip** — no API key configured this session, so `search`/`apply`/
  poster download were not exercised against the real API; the code path and caching are
  in place and the routes correctly 401 when anonymous.
- **Panel custom field UI** — the authenticated film edit view (the `tmdblookup` Vue
  component) wasn't tested headlessly; it uses Kirby's supported inline-template approach.
- **The authorization fix** (`apply` permission check) — verified by reading Kirby's
  permission source; not tested with an actual restricted (non-admin) user, since the
  project currently defines only the default admin role.
- **ICS no-date 404 path** — `date` is required, so the `NotFoundException` branch isn't
  reached in normal use.
- **Poster file resolution** — `posterFile()` / `->toFile()` round-trip wasn't exercised
  without a downloaded poster.

## 10. Deferred / open (by design)

- **Static subpages** — Filmbildung ("more than a cinema"), Projekte (Nachklang), Koop,
  Kontakt. Content-migration work, deferred to the teams (SPEC §11). The `filmbildung`
  placement category and a generic `default` page type exist; the pages themselves don't.
- **Design** (SPEC §10), **director search** (SPEC §5, data stored structurally for it),
  **month-grid calendar** (SPEC §5, list view suffices), **contact form vs. details**
  (SPEC §13) — all deferred.
- **Self-hosted TMDB logo** for attribution — text + link are in place; the logo asset
  should be added under `/assets` before launch (do not hotlink).
- **DB backing store** — flat-file is used throughout; revisit only if the catalogue
  outgrows `site()->index()` scans (SPEC §1/§13).

## 11. Sample content (for smoke testing — remove before production)

`content/films/metropolis`, two showings under `content/program/` (a past 2026-05-20 and
an upcoming 2026-06-20, both linked to Metropolis), and one event
`content/events/vortrag-stummfilm` (2026-06-15).

---

## 12. Files (relative to repo root)

**New:** `package.json`, `bun.lock`, `scripts/vendor.mjs`, `assets/css/index.css`,
`assets/js/app.js`, `assets/vendor/**`, `site/config/config.php`,
`site/blueprints/{files/poster,files/still,pages/film,pages/showing,pages/event,pages/films,pages/program}.yml`,
`site/plugins/kinemathek/**`, `site/plugins/kinemathek-tmdb/**`,
`site/controllers/{program,films,film,showing,event,home}.php`,
`site/templates/{program,films,film,showing,event,home,showing.ics,event.ics}.php`,
`site/snippets/{header,footer,program-item,add-to-calendar}.php`, `content/{films,program,events}/**`.

**Modified:** `.gitignore` (ignore `/node_modules`), `content/site.txt` (title),
`site/blueprints/site.yml` (container sections), `site/templates/default.php`
(wrap header/footer).

**Untracked (pre-existing):** `SPEC.md`.

Nothing has been committed.
