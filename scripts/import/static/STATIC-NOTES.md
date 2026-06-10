# Static-page scrape notes — old Kinemathek Karlsruhe site (WordPress)

Scraped 2026-06-10 by `extract-static.py` → `static-pages.json`.
Companion to the program scrape (`../SCRAPE-NOTES.md` / `../old-site-program.json`).

## Source / method

- **WP REST API works for this**: `wp/v2/pages` (10 pages total) and `wp/v2/posts`
  (77 posts total) both expose `content.rendered`, so no HTML scraping of the
  theme was needed. Cached raw responses: `_pages-full.json`, `_posts-full.json`,
  `_pages-index.json`, `_posts-index.json`, `_home.html` (for nav extraction).
- The "static" surface is a **mix of `page` and `post` types**: only 10 real WP
  pages exist; the nav section landing pages (Events, Festivals, Filmbildung,
  Projekte) and all evergreen offering pages (KinoKnirpse, Bilderbuchkino, …)
  are ordinary **posts**. The other ~60 posts are Reihe/series descriptions =
  program content, deliberately **not** included here.
- Re-run: `python3 extract-static.py` (reads the cached `_*.json`; refresh those
  with the two curl commands in the script docstring, sequential, 0.8 s delay).

## Inventory (16 pages)

| slug | title | parent | class |
|---|---|---|---|
| ueber-uns | Über uns | – | text |
| kontakt | Kontakt | – | contact |
| mitglied-werden | Mitglied werden | – | complex |
| anfrage | Vermietungsanfrage | – | complex |
| newsletter | Newsletter | – | complex |
| datenschutzerklaerung | Impressum/Datenschutzerklärung | – | text |
| events | Events | – | complex |
| festivals-in-der-kinemathek | Festivals in der Kinemathek | – | complex |
| filmbildung | Filmbildung | – | complex |
| projekte | Projekte | – | complex |
| kinoknirpse | KinoKnirpse | filmbildung | text |
| bilderbuchkino | Bilderbuchkino | filmbildung | text |
| cinefete | Cinéfête | filmbildung | complex |
| i-like-films | I Like Films | filmbildung | complex |
| le-cinema-cent-ans-de-jeunesse-ccaj | LE CINÉMA, CENT ANS DE JEUNESSE (CCAJ) | filmbildung | complex |
| junge-kinemathek | Junge Kinemathek | – | complex |

Counts: **4 text, 1 contact, 11 complex.** WP has no parent hierarchy at all
(every page/post is top-level); the `filmbildung` parents above are editorial,
derived from how `/filmbildung/` links its offerings.

Why the complex ones are complex:

- **mitglied-werden** — Contact Form 7 membership form (with acceptance
  checkbox) + two-column fee table layout. Prose (benefits, fees: 30 €/15 €
  reduced) extracts fine; the form does not.
- **anfrage** — entire page is a **Ninja Forms** booking form rendered
  client-side from embedded JSON ("Für diesen Inhalt ist JavaScript
  erforderlich"). Fields: Name, Email, Telefon, Name/Art des Events (DCP-Test,
  Testscreening, Lesung/Ausstellung/OpenMic, Workshop), Privat/Öffentlich,
  Anzahl der Gäste, Datum, Zeitraum (Morgens…Ganzer Tag), Saal-Auswahl etc.
  Only ~4 intro paragraphs are prose.
- **newsletter** — one paragraph + Contact Form 7 signup form.
- **events / projekte / festivals-in-der-kinemathek / filmbildung** — magazine
  layouts: multi-column teaser cards (image + excerpt + "Mehr"/"Weiterlesen")
  pointing at program posts and external partners; much of the content is
  **dynamic program state baked into a static page** and will go stale. On the
  new site these map to category listings (spielplan/koop/festival/filmbildung)
  plus a short editorial intro, not to migrated HTML.
- **cinefete** — prose + a poster grid of the current year's film selection
  (`/film/...` links) + registration PDF.
- **i-like-films** — prose + **5 video iframes** (4× YouTube, 1× Dailymotion)
  + columns.
- **junge-kinemathek** — short prose + a huge baked-in grid of 37 film-teaser
  links (the kids' Sunday programme). Contains a **dead link** `/schulkino/`
  (301 → `?p=11594` → 404).

## Nav structure (as found on the homepage)

Header nav and burger/mobile nav are **identical** (same list twice):

1. Spielplan → `/spielplan/` (excluded — program scrape)
2. Events → `/events/`
3. Festivals → `/festivals-in-der-kinemathek/`
4. Filmbildung → `/filmbildung/`
5. Projekte → `/projekte/`
6. Kommunales Kino → `/ueber-uns/`
7. KinoBar → **instagram.com/kinobarkarlsruhe** (external!)
8. PhonoLuxMaschine → `/film/himmelssturz/` (a film page, excluded)
9. Mitglied werden → `/mitglied-werden/`
10. Kontakt → `/kontakt/`
11. Vermietung → `/anfrage`

There is no separate footer menu; `newsletter` and `datenschutzerklaerung` are
linked from the homepage body/footer area. The homepage additionally deep-links
project/series posts (bilderbuchkino, kinoknirpse, junge-kinemathek, masel-talk,
maschenkino, junge-alte, klima-krisen-utopien, fragile-teilhabe, film-noir,
cinefete, ccaj) — the recurring-series ones among these were left to the
program migration, not treated as static pages.

## Excluded (and why)

- `spielplan` (page) — the schedule, covered by `../old-site-program.json`.
- `frontpage` (page) — the homepage itself, not a subpage (teaser collage).
- `cookie-richtlinie-eu` (page) — auto-generated cookie-plugin boilerplate;
  the Datenschutz page itself says "Die Seite verwendet keine Cookies". Junk.
- `kinobar-craft-beer` (page) — **empty content**; its permalink redirects to a
  public **Basecamp** document (`public.3.basecamp.com/...`). The nav "KinoBar"
  entry points to Instagram instead. Nothing to migrate; the KinoBar needs new
  first-party content.
- All Reihe/series/festival-edition posts (film-noir, giallo, pride-pictures,
  dokka-xiii, …) — program content.
- Pages that do **not** exist on the old site at all (searched all content):
  Anfahrt, Barrierefreiheit, Presse, Eintrittspreise (prices only appear inside
  `mitglied-werden` and per-event "Eintritt frei" remarks), Team (people are
  listed on `kontakt`), Geschichte (covered inside `ueber-uns`).

## Migration hazards — third-party / embeds (new site forbids third-party requests)

1. **YouTube iframes ×4 + Dailymotion iframe ×1** on `i-like-films`
   (`youtube.com/embed/…`, `geo.dailymotion.com/player.html…`) — must become
   plain links or locally hosted video.
2. **Vimeo player iframe** on `events` (`player.vimeo.com/video/1170592296`,
   dnt=1 — still a third-party request).
3. **Ninja Forms** booking form on `anfrage` — JS-rendered, needs a first-party
   replacement (rebuild as form, or mailto + structured info page).
4. **Contact Form 7** forms on `newsletter` and `mitglied-werden` — POST to
   WordPress itself; the newsletter backend/provider is not visible from
   outside. New site needs its own signup mechanism (or a plain mailto).
5. **Basecamp** public doc as the KinoBar "page"; **Instagram** as the KinoBar
   nav target; **Spotify** podcast links on `projekte` — links only (no
   embeds), acceptable as plain `<a>` but worth an editorial decision.
6. **PDF registration forms** under `wp-content/uploads/` (Cinéfête/Filmbildung
   Anmeldung) — re-host locally.
7. Obfuscated emails on `kontakt` written as `name(ät)domain` — normalised in
   `contact.emails` in the JSON.
8. Dead link `/schulkino/` on `junge-kinemathek` (404).

## Data quality caveats

- `ueber-uns` has an **empty WP title**; title "Über uns" set editorially
  (nav label is "Kommunales Kino").
- `contact{}` is best-effort regex extraction (emails incl. de-obfuscated
  `(ät)`, phones `+49…`, the Kaiserpassage 6 / 76133 Karlsruhe address). No
  machine-readable opening hours exist anywhere — `kontakt` only says
  "telefonisch während den Kassen-Öffnungszeiten" without giving hours.
- `html` is the raw `content.rendered` (WP block markup, inline styles, the
  Ninja Forms JSON blob on `anfrage`); `text` is the cleaned markdown-ish
  version with absolute link URLs, forms/scripts/styles stripped.
- Image alts are often filename junk (e.g. `459149039 1040678551398781 … N`);
  expect to rewrite alts during migration.
- Teaser grids mean the `text` of the four section landing pages includes
  stale program snippets — migrate only their intro prose.
