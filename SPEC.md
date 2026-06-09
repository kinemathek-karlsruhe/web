# Kinemathek Karlsruhe — Website Redesign Specification

## 1. Summary

The current site is WordPress, using Custom Post Types and ACF (Advanced Custom Fields). The film/showing data model evolved organically and has two recurring problems: films and screenings are awkwardly entangled, and non-film events (lectures, etc.) get stored as fake "films" that clog the archive.

The rebuild has **two real backend problems to solve** — a **film archive** and **event management**. Everything else on the site is text and images and is comparatively trivial.

**Intended platform:** Kirby. If Kirby's flat-file model can't cleanly express the Film ↔ Showing relationship and the faceted filtering, a small database (Postgres or SQLite) is acceptable as a backing store. This is considered solvable and is not a project risk.

**Hard non-functional constraint:** no cookies, no tracking, no analytics that would require consent — and therefore no cookie banner. This is non-negotiable.

---

## 2. Content model

The core of the rebuild. Three first-class content types, with a clean separation between the **canonical work** (Film), a **dated occurrence** (Showing), and **non-film programming** (Event).

### 2.1 Film

The permanent, canonical record for a film. Films persist in the archive indefinitely. A film exists independently of whether it currently has any scheduled screenings.

Metadata should be pulled automatically from **TMDB** by title lookup, with a **manual override** for everything (see §4). Fields are stored individually (not as one free-text blob) so they can be used as filter facets later.

| Field | Source | Notes |
|---|---|---|
| Title | TMDB / manual | |
| Original title | TMDB / manual | |
| Director(s) | TMDB / manual | Stored structured to support director search later (future) |
| Country | TMDB / manual | Filterable facet |
| Year | TMDB / manual | |
| Runtime | TMDB / manual | |
| Original language | TMDB / manual | Filterable facet |
| Synopsis / description | TMDB, overridable | Kinemathek can also write its own text |
| Poster / stills | TMDB / manual upload | |
| Cast | TMDB | Optional |
| Genre | TMDB / manual | See tags vs. categories, §2.4 |
| Tags / keywords | Manual | Free, multiple per film |
| TMDB ID | TMDB | Used for linkage and refresh |
| Manual-override flag | — | Marks a film as hand-curated; suppresses TMDB overwrites |

### 2.2 Showing (Veranstaltung)

A specific screening of a Film at a date and time. **One Film → many Showings.** Linked to its Film by relationship. Carries information specific to that occurrence.

| Field | Notes |
|---|---|
| Linked Film | Required relationship |
| Date & time | |
| Venue / room | If more than one space is ever used |
| Subtitles | e.g. OmU / OmeU — can differ per showing |
| Special info (Sonderinfo) | Intro, guest, "with Filmgespräch" + with whom, etc. |
| Ticket link | External Mars EDV URL (see §6) |
| Calendar export | Derived ICS/iCal data (see §6) |
| Placement / category | Which public listings this surfaces in (see §2.4) |

### 2.3 Event (non-film)

Standalone programming that is **not** a film — a lecture, a talk, a festival item without a single attached film. Must be creatable **without** going through the Film type, and must **not** appear in the film archive.

Can otherwise reuse the Showing mechanics (date/time, ticket link, calendar export, tags) and appear in the general "what's on" listing alongside screenings.

### 2.4 Categories vs. Tags

The current site mixes these two concepts; the rebuild should keep them distinct.

- **Categories = placement / routing.** Controls *where* an item surfaces (e.g. Spielplan, Koop list, Festival, Filmbildung). This is a structural/editorial taxonomy, not a descriptive one.
- **Tags / keywords = descriptive facets.** Country, language, subtitles, genre, series/Reihe, "has discussion," etc. Used for **filtering**. Multiple tags per item are expected and encouraged. The current use of a single "Schlagwort" as genre-or-series should be retired in favour of multiple, properly typed facets.

---

## 3. Relational display logic

- **Film page** shows the film's metadata plus its screenings:
  - **Upcoming showings** — clickable, with ticket links.
  - **Past showings** — visible but styled differently (not clickable), so the screening history of a film is preserved and visible.
- **A film page is reachable from the film itself**, not only from a showing. When a visitor lands on a film page (with no specific showing in mind), the showings appear below it automatically.
- **Ordering by date** across the program: the soonest upcoming showing surfaces first. A film screening tomorrow appears at the top; a later screening (of the same or another film) appears later in the list. The same ordering applies whether the program is rendered as a stacked list or as a slider.

---

## 4. TMDB integration

- On creating/editing a Film, look it up on **TMDB** by title and auto-populate metadata (synopsis, poster, director, country, year, runtime, language, cast, genre).
- **Manual override is required**, for two reasons:
  1. Editorial — Kinemathek wants to write its own text in some cases.
  2. Coverage — esoteric / student films are often not on TMDB at all. When no match is found, the editor must be able to enter all fields by hand.
- **Implementation notes / honest caveats:**
  - Title lookups are ambiguous (multiple films share a title; remakes); the editor needs to confirm/select the correct match rather than auto-accepting the first hit.
  - TMDB requires API-key usage and attribution per its terms; budget for that.
  - Cache TMDB responses locally so the public site never depends on a live TMDB call at request time (also keeps it privacy-clean — see §7).
- **Reference:** colleagues at the *Blauer Salon* (HFG) already use an automated TMDB pull — worth looking at their setup, though we'll likely build our own.

---

## 5. Public listings, archive & filtering

- **Spielplan (program):** the primary view — a fast, clear listing of upcoming screenings. Required.
- **Film archive:** the full back-catalogue of films, persisting permanently.
- **Filtering** is a core requirement, for both the public and internal use. Visitors (and editors) should be able to narrow a list of films/events by facets and see *all* matching results — e.g. "only films that have a discussion," "all films from France," "with subtitles." Facets come from the tags/keywords in §2.4.
- **Director search** (list every film by a given director) is desirable but **deferred** — Ruby flagged it as a lot of work and not the main goal. The data model should store directors structurally so this can be added later without a rebuild.
- **Calendar / overview view:** a complete overview of all upcoming events. A month-grid calendar view would be nice but is not required; a clean chronological list (as now) is acceptable. Whatever the layout, it needs good filtering on top.

---

## 6. Ticketing & calendar export

- **Ticketing stays external (Mars EDV).** The site shows the ticket link/button; clicking it sends the visitor to Mars EDV. **No payment processing on the website.** Keeping this abstracted away is a deliberate choice and good for maintenance — there is no intention to move off Mars EDV.
- **No iframe embed.** The previous iframe approach is explicitly dropped: it was not robust (looked broken on some devices, CORS headaches) and the benefit didn't justify it. Plain links are fine.
- **Add-to-calendar (new):** generate an ICS/iCal file per showing/event so visitors can add it to their own calendar. This does not exist today and is wanted. (ICS download is a static file — no cookies, no tracking.)

---

## 7. Privacy (hard requirement)

- **No cookies. No third-party tracking. No consent-gated analytics. No cookie banner.**
- External destinations (Mars EDV ticket links, any TMDB attribution links) are **navigations, not embeds** — they don't set cookies on the Kinemathek domain. TMDB data must be **fetched server-side and cached**, never loaded client-side from TMDB, so the visitor's browser never talks to a third party.
- ICS exports and the rest of the site are served as first-party static content.

---

## 8. Homepage

The homepage was already most of the way there in the current design. Requirements:

- **Featured Events / Festivals** prominently visible — the "special" programming: festival items (e.g. DOK), films with a Filmgespräch, anything bigger than a routine screening. Events and Festivals can be mixed here.
- **Today's / next screening** surfaced immediately — if there's a showing today, today's film shows directly on the homepage.
- **Quick program overview** — a compact view of the Spielplan on the homepage. (This existed before but was pulled due to technical problems; it should work this time.)
- **Hero image** — nice to have, not essential, and could be smaller than the current one.
- A representation of **Filmbildung / "more than a cinema"** (see §9).

---

## 9. Static pages & content

These are mostly text + image and are lower-complexity than the backend, but several are wanted by the wider team.

- **Filmbildung ("more than a cinema"):** Kinemathek is not only a cinema — film education is a major part of what it does. This content currently exists **twice and inconsistently** (once as a homepage info slider, once stacked further down). Consolidate into a proper **Filmbildung subpage**, plus a single teaser/representation on the homepage.
- **Projects page (Projektseite):** not front-and-centre but important. Static, linked content. Example: colleague Michael Endepols' *Nachklang* film discussions from the Covid era, run via *Kinemathek Plus* / *Film-Friends* (the small streaming-style offering at the time). This material needs a home.
- **Koop listing:** content categorised as cooperations surfaces here (placement category, §2.4).
- **Generic subpages:** for everything else, freely editable.
- **Contact:** classic contact forms are a possible nice-to-have, but both Ruby and the dev are lukewarm — forms are dated and a maintenance/spam liability. Likely resolved as plain contact details rather than a form. **Open question.**

---

## 10. Design

Deliberately deferred. **Backend and data model first**, then as much design iteration as wanted — no constraint there.

- Sliders are optional. Ruby used them previously for visual appeal but is fully open to dropping them.
- Outside the program/archive logic, the site is text and image.

---

## 11. Migration

- Built entirely in a **staging environment ("dry dock")** with no live-money or production hookups needed during development. Cut over to production in a **single switch-over** once ready.
- **Content/text migration** onto subpages, and any **subpage consolidation**, is left to Ruby and the relevant teams and decided during migration — not blocking the build.

---

## 12. Priority summary

| Priority | Item |
|---|---|
| **P0 — non-negotiable** | Spielplan / program; Events; Festivals; immediate display of today's/next screening |
| **P0** | Film ↔ Showing relational model; film pages showing upcoming + past screenings |
| **P0** | Standalone non-film Events that don't pollute the film archive |
| **P0** | No cookies / no tracking / no cookie banner |
| **P0** | External Mars EDV ticket links (no payment, no iframe) |
| **P1 — important** | Film archive (permanent); TMDB auto-fill with manual override |
| **P1** | Faceted filtering of films/events (country, language, subtitles, has-discussion, etc.) |
| **P1** | Add-to-calendar (ICS) export |
| **P1** | Filmbildung subpage + homepage teaser (de-duplicated); Projects page |
| **P2 — nice to have** | Hero image; month-grid calendar view; sliders |
| **Future** | Search by director |
| **Open** | Contact form vs. plain contact details |

---

## 13. Open questions

1. **CMS commitment** — Kirby flat-file vs. Kirby + small DB. Decide once the relational/filtering needs are prototyped against Kirby's native capabilities.
2. **TMDB match confirmation** — UI for picking the right match when a title is ambiguous.
3. **Contact** — form or just published contact details.
4. **Calendar UI** — chronological list (sufficient) vs. month grid (nice-to-have); confirm before building the grid.
5. **Subpage consolidation** — which existing pages merge, to be decided with the teams during migration.