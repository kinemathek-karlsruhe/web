# Migration report — old WordPress → Kirby (2026-06-14)

Second migration pass, extending the initial Spielplan-window import to the full
film back-catalogue plus the remaining editorial posts. Run by the dev; the
**manual follow-ups** below are for the editorial team. Companion notes:
[`SCRAPE-NOTES.md`](import/SCRAPE-NOTES.md), [`static/STATIC-NOTES.md`](import/static/STATIC-NOTES.md).

## What was migrated

| Area | Result |
|---|---|
| **Film archive** | **342 films** total (316 newly imported + 26 from the first pass) |
| — TMDB enrichment | ~74 % got a confident match → local poster + cast/director/year/genre; the rest keep scraped data (German synopsis, director, country, year from the old spec line). **Poster only, no stills** (`--no-stills`) — pull stills per-film in the Panel when featuring a film. |
| **Events excluded** | 40 non-film productions (festival sub-events, lectures, Kurzfilm-programme, Bilderbuchkino readings, Speakeasy/Trash-or-Treasure) kept OUT of the archive |
| **Reihe/festival drafts** | 65 old posts → `reihen-archiv/` (unlisted staging page, all **drafts**), one `text` page each, annotated with a category suggestion + migration hazards |
| **Curated static pages** | unchanged (the 16 hand-curated pages from the first pass) |

New tooling: `scripts/import/scrape-archive.py` (full back-catalogue crawler),
`scripts/import-post-drafts.php` (post → draft importer), and a `--no-stills`
flag on `scripts/import-program.php`.

---

## Manual follow-ups (editorial team)

### 1. Re-match 8 films in the Panel
A TMDB **false positive** was detected (same/similar title, different film) and
**reset to scraped-only data** (no wrong poster/year). Open each in the Panel,
run the TMDB search, and pick the correct match:

- `1001-frames`
- `blame-2`
- `do-you-love-me`
- `salaam-cinema`
- `sterne`
- `stromboli`
- `the-vanishing-point`
- `winners`

### 2. Spot-check 11 unverifiable matches
The old site gave no year/synopsis for these, so the title-only TMDB match could
not be confirmed (titles are distinctive, so they are **probably** right). A
quick look at poster/year in the Panel is enough:

- `der-tag-vor-dem-abend`
- `eine-krankheit-wie-ein-gedicht`
- `kukata-miti`
- `ljosvikingar`
- `nessuno-vi-fara-del-male`
- `queer-as-punk`
- `sabbath-queen`
- `sedimente`
- `this-is-my-body`
- `when-pigs-fly`
- `wohin-mit-mir`

### 3. 40 excluded events
These were tagged "Filme" on the old site but are **not single films**. They are
NOT in the archive. If any should appear in the programme, add them as **Events**
(or, for recurring ones, schedule Showings):

- `95-mm-spezial-das-cabinet-des-dr-caligari-song-schmutziges-geld`
- `ag-dok-werkstattpraesentation-branchentreff`
- `arizonas-todeszone-fuer-migranten`
- `bibliothek-der-zukunft`
- `buchvorstellung-deserteure`
- `chimaltenango-kleines-paradies-in-der-hoelle`
- `die-kleine-hexe-ausflug-mit-abraxas`
- `die-letzte-vorstellung-2`
- `die-staerksten-olchis-der-welt`
- `dokka-fruehstueck-2026`
- `dokka-kids`
- `dokka-party-2026`
- `einblicke-in-die-mosuo`
- `eroeffnung-farsi-film-festival`
- `es-knistert-kurzfilmprogramm`
- `es-knistert-wdh-prism-award`
- `filmschool-picks-filme-der-hfg`
- `fraulein-hicks-und-die-kleine-pupswolke`
- `freeride-filmfestival`
- `generation-u-ukrainische-jugendliche-in-deutschland`
- `groundwork`
- `himmelssturz`
- `im-ohr-der-landschaft`
- `lesung-judenhass-im-kunstbetrieb`
- `multivisionsschau-naturwunder-erde`
- `nowruz`
- `oh-wie-schoen-ist-panama`
- `pettersson-findus-co`
- `sadakos-kraniche-fuer-eine-welt-ohne-atomwaffen`
- `sieben-grummelige-groemmels-und-ein-kleines-schwein`
- `speakeasy-cinema`
- `stephan-grigat-konstellationen-nach-dem-7-oktober`
- `t-short-animationsfilme`
- `trash-or-treasure`
- `und-der-himmel-der-ist-blau`
- `unsere-asche-wird-weiter-brennen`
- `von-der-leinwand-aufs-papier-kreatives-schreiben-im-kino`
- `vortrag-der-iran-im-umbruch`
- `welt-in-aufruhr-kurzfilmprogramm`
- `wer-bis-zum-ende-bleibt`

### 4. Review 65 drafts under `reihen-archiv/`
Each draft starts with a **Migrations-Notiz** giving a category suggestion and
hazards (forms/embeds removed, stale dated program dropped, external/PDF links
kept, likely duplicates). Decide per page: refine + publish, move, merge, or
delete. The staging page itself is unlisted (not in the nav).

---

## Data caveats

- **No historic screening dates.** The old WP `wp_theatre_event` dates are not in
  the REST API and there is no public month archive, so archive films carry **no
  past showings**. The new Spielplan **Archiv** view therefore starts with only
  the few already-past showings and fills forward over time.
- **Synopses** are the cinema's own German text where it existed; a few carry a
  leading series/cooperation blurb — clean as you go. Empty ones were filled from
  TMDB where matched.
- The migration is reproducible: re-run `scrape-archive.py` then
  `import-program.php --apply --no-stills --input=import/archive-films-final.json`
  (idempotent — existing films are skipped).

---

## Also shipped this pass (public site)

- **Spielplan "Archiv" toggle** — a button in the filter row switches the listing
  to past showings (most-recent first, same filters; ticket/calendar CTAs
  dropped, film-page links kept).
- **Hero fix** — when the next screening is an Event (not a film), the hero box
  now uses the **event's own image** instead of staying empty.
