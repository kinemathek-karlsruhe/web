# Scrape notes — old Kinemathek Karlsruhe site (WordPress)

Scraped 2026-06-10 by `scrape-old-site.py` → `old-site-program.json`.
Re-run the script anytime to refresh (sequential fetches, 0.8 s delay).

## Source / method

- The old site runs WordPress with the **WP Theatre plugin** (`wp.theatre`):
  films are `wp_theatre_prod` productions, screenings are `wp_theatre_event` posts.
- **REST API**: `https://kinemathek-karlsruhe.de/wp-json/wp/v2/wp_theatre_prod` IS exposed
  (titles, slugs, content/synopsis, tag/category ids) — but `wp_theatre_event` (the actual
  dates) is **not** in the REST type list, and no ACF is installed (no `acf` keys; meta only
  has slim-seo). So REST alone can't produce the schedule.
- **Therefore HTML scrape**:
  - `https://kinemathek-karlsruhe.de/spielplan/` — the full upcoming schedule, grouped by
    `<h3 class="wpt_listing_group day">Mittwoch 10 Juni</h3>` with per-event divs
    (`wp_theatre_event_datetime|_title|_remark|_tickets|_cine_technical_specs` and a
    `wp_theatre_prod_tags` list = the Reihe label).
  - `https://kinemathek-karlsruhe.de/film/<slug>/` — per-production detail pages for
    original title (`wp_theatre_prod_cine_original_title`), technical specs, synopsis
    paragraphs and Reihe tags (`/reihe/<slug>/` links).
- Inline `<style>` blocks repeat all the class names — the parser strips them first.

## URL patterns

- Schedule: `/spielplan/` (all upcoming events; `/events/` and `/upcoming_events` are
  smaller sliders/subsets of the same data)
- Film detail: `/film/<slug>/`
- Reihe taxonomy: `/reihe/<slug>/`
- Tickets: `https://kinotickets.express/karlsruhe_kinemathek/booking/<id>` (per event)

## What was extracted

- 30 productions (films + recurring non-film events), 45 showings, **2026-06-10 → 2026-07-31**
  (everything the Spielplan listed at scrape time).
- Technical-spec lines parse cleanly in almost all cases:
  `"Pedro Pinho, PT/FR/BR/RO 2025; 211′ OmU"` / `"Hayao Miyazaki, Japan 1997 | 128 Min. | OmU"`
  → directors[], country[], year, runtime, subtitle marker. Raw line kept as `specRaw`.
- `isEvent` heuristic: spec line has neither a year nor a runtime → non-film event
  (Speakeasy Cinema bar nights, Trash or Treasure secret-cinema nights, Bilderbuchkino
  readings, Sadako origami action).

## Data quality caveats

- **Past June showings (before 2026-06-10) are not available** — the Spielplan only renders
  future events and there is no public month archive for `wp_theatre_event`.
- **No venue data** (Saal/Box is never shown) → `venue` is null except where a remark gives
  a location ("Ort: Open Air auf dem Kronenplatz" → Kronenplatz open air, 2026-07-24).
- **Country values are as printed**: mostly ISO-ish codes (FR, SD, GW…) but sometimes full
  names ("Japan", "Frankreich", "USA") and one apparent typo `NR` (likely NO, Norway).
  Normalisation left to the importer.
- **Day headings carry no year** — year inferred from scrape date, incremented if the month
  number rolls backwards. Safe for the current Jun–Jul window.
- **Subtitle markers** only OmU/OmeU appear plus one DF; films without a marker are German
  (or German-dubbed kids') versions. `(e)/(f)` markers don't occur, but one remark notes
  "Ciné Club: Originalsprache mit französischen UT" (Mommy, 2026-07-02).
- **Discussion flags**: no per-event "Filmgespräch" remarks exist right now; the film-level
  `mentionsDiscussion` is a text search for Filmgespräch/Einführung/Gespräch mit on the
  detail page (true for Die Eiche, Silent Flood, Suiten für eine verwundete Welt,
  Trash or Treasure) — verify before mapping to `hasDiscussion` per showing.
- **Synopses are German only** (site has no English content; `synopsisEn` always null).
  Synopsis = post paragraphs; for event-ish pages it may include practical info
  (registration, prices). Speakeasy Cinema has no paragraph content at all.
- **FSK** is never machine-readable on the site (`fsk` always null).
- One non-standard spec line: Margarete Schütte-Lihotzky =
  `"TV-Doku, 2018; 45′ + Kurzdoku 1927; 8′"` → no director/country; runtime captured as 45,
  the 8-min 1927 short only survives in `specRaw`.
- `directors` for "Jean-Pierre und Luc Dardenne" is kept as one string (shared surname);
  "M. Ahrens / M. Lüdemann" splits into two abbreviated names.
- Showing `title` may differ from the film page title (e.g. "Die leisen und die großen
  Töne (En Fanfare)" on the Spielplan); join via `filmSlug`, not title.
