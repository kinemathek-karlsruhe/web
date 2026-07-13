# CLAUDE.md — Kinemathek Karlsruhe

Backend + Panel for the **Kinemathek Karlsruhe** cinema website, rebuilding a WordPress+ACF
site on **Kirby 5.4.3** (flat-file CMS), PHP 8.4, package manager **Bun**. The full brief is
[`SPEC.md`](SPEC.md); current status is [`STATE.md`](STATE.md). The **Panel/admin UI is fully
designed**, and **all public templates carry the Monatsblatt design** (web translation of
the printed program sheet — Lipa Agate High Cnd in `assets/font/`, weights 300/400/500/700
only; `monatsblatt-*` snippets are the shell; behaviour split across `assets/js/monatsblatt.js`
(pivot masthead) and `assets/js/program.js` (listing). Static pages use the `text` /
`collection` / `custom` blueprints; listed top-level ones appear in the pivot nav).
Read this file before editing — it encodes the field contract and the gotchas that have
already bitten us.

---

## Commands

```bash
php -S localhost:8000 kirby/router.php   # dev server (Ruby normally runs the site via IndigoStack)
bun run build       # vendor jQuery/Fancybox + build minified Tailwind -> assets/css/styles.css
bun run dev         # vendor + tailwind --watch
bun run vendor      # copy jQuery/Fancybox from node_modules -> assets/vendor
php -l <file.php>   # syntax-lint a PHP file (there is no static analyzer / test suite)
node --check site/plugins/kinemathek-tmdb/index.js   # the Panel field is no-build; sanity-check JS
```

**There is no test suite.** Verify changes by **booting Kirby in a throwaway CLI script**
(fast, no server needed) — see "Verifying changes" below. `npm`/`npx` are shell-aliased to
`bun`/`bunx`; prefer `bun`.

---

## Architecture

- **Three content types** under three containers (slugs are authoritative):
  `films/` (Film), `program/` (Showing), `events/` (Event). Films are the permanent archive;
  Showings are dated screenings of **one** Film; Events are non-film programming.
- **The Film↔Showing relation lives only on the Showing**, in its `film` pages field. A Film
  has no screening field — it discovers screenings by **reverse lookup**
  (`FilmPage::showings()` → `upcomingShowings()`/`pastShowings()`), memoised per request.
  Events have **no** `film` field, so they can never enter the archive; their optional
  `relatedFilm` is display-only (film page section + backlink), never a screening.
- **Two plugins**: `kinemathek/core` (`site/plugins/kinemathek/`) = the data model, program,
  faceting, ICS; `kinemathek/tmdb` (`site/plugins/kinemathek-tmdb/`) = the server-side, cached
  TMDB sync + the Panel lookup field. Page behaviour lives on **page models**
  (`FilmPage`/`ShowingPage`/`EventPage` + `OccurrenceTrait`), logic on the static
  `Kinemathek` helper.
- **Categories vs tags** (SPEC §2.4, enforced structurally): `categories` (multiselect:
  spielplan/koop/festival/filmbildung) = *placement/routing*; `country/language/genre/series/
  keywords` (tags) = *descriptive facets*. Never conflate them.
- Controllers are matched by **template name** (`film.php` = single, `films.php` = archive).
  Public templates are primitive; the Panel UI (blueprints + the TMDB field) is the designed
  surface.
- **Multi-language: DE (default, bare root `/`) + EN (`/en`)** — definitions in
  `site/languages/`, enabled via `'languages' => true`. Content files are
  language-suffixed (`film.de.txt`); `scripts/migrate-content-multilang.php` migrates a
  pre-multilang content tree (run once per environment, `--apply`). Untranslated fields
  fall back to German. Frontend strings: `t('kinemathek.*')`, defined in BOTH language
  files; localized dates: `Kinemathek::localDate()` / the `localDate` field method.
  Panel labels/help stay German on purpose (German editorial team).

---

## Field contract — FROZEN. Do not rename/retype.

The TMDB sync (`mapToFilm`), faceting, ICS, and templates all key off these exact names.

**Film** (`film.yml` / `FilmPage`): `title`(text,req), `originalTitle`(text),
`directors`(structure: `name`+`tmdbpersonid`), `cast`(structure: `name`+`role`+`tmdbpersonid`),
`synopsis`(textarea), `year`(number), `runtime`(number, mins — drives ICS DTEND),
`country`/`language`/`genre`/`series`/`keywords`(tags), `tmdbId`(number,disabled),
`manualOverride`(toggle), `poster`(files,max1), `stills`(files).

**Showing** (`showing.yml` / `ShowingPage`): `film`(pages,req,max1), `title`(text,optional
override), `date`(date,time:true,req), `venue`(text), `sonderinfo`(textarea), `ticketUrl`(url),
`freeAdmission`(toggle — hides the ticket button, shows „Freier Eintritt" instead),
`subtitles`(multiselect OmU/OmeU/OF/dtF), `hasDiscussion`(toggle),
`categories`(multiselect spielplan/koop/festival/filmbildung), `keywords`(tags).
`num: "{{ page.date.toDate('YmdHi') }}"`.

**Event** (`event.yml` / `EventPage`): `title`(text,req), `relatedFilm`(pages,optional,max1
— event shows a film but stays event-first; display-only: film page lists it via
`FilmPage::relatedEvents()`/`upcomingRelatedEvents()`, NEVER a screening or facet source —
`EventPage::film()` stays `null` on purpose), `date`(date,time:true,req),
`endDate`(date,time:true,optional multi-day), `venue`(text), `hasDiscussion`(toggle),
`text`(textarea — the description, **named `text`, not `synopsis`**), `ticketUrl`(url),
`freeAdmission`(toggle — hides the ticket button, shows „Freier Eintritt" instead),
`categories`(multiselect), `subtitles`(multiselect), `keywords`(tags), `image`(files,max1).
**No required `film` field** (that stays Showing-only, so events can't enter the archive).

**Poster/Still files** (`poster.yml`/`still.yml`): `alt`(text), `source`(text, default TMDB),
`caption`(text / textarea). Written by `attachPoster()`.

**Translation contract** (multilang; enforced via `translate: false` in the blueprints —
keep blueprint, TMDB sync and this list in agreement): TRANSLATABLE are Film
`title/synopsis/genre/series/keywords`, Showing `title/sonderinfo/keywords`, Event
`title/text/keywords`, file `alt/caption`. Everything else is `translate: false`
(invariant, default language only): dates, numbers, `directors`/`cast`, `originalTitle`,
`country`/`language` (codes), `subtitles`/`categories` (option keys), `venue`,
`ticketUrl`, `tmdbId`, `manualOverride`, `poster`/`stills`/`image` (file refs),
`relatedFilm` (page ref), `source`.
The TMDB sync writes `Client::TRANSLATABLE = title/synopsis/genre` into non-default
languages — keep that const in sync with this contract.

Facet routing (`Kinemathek::FACETS`): `country/language/genre/series` → on the **film**;
`subtitles` → per **occurrence**; `keywords` → **both** (occurrence ∪ film). Boolean facets:
`?discussion=1` → field `hasDiscussion`; `?hasSubtitles=1` → `subtitles` non-empty.

---

## Gotchas / hard-won lessons (read before touching the relevant area)

**Kirby 5 API**
- The content Field class is **`Kirby\Content\Field`**, NOT `Kirby\Cms\Field` (the old import
  caused a fatal 500). Type-hint field values with the Content namespace.
- **`$page->update()` returns a NEW page object and freezes the old one**
  (`ImmutableMemoryStorage`) — a second write through the stale object throws
  `LogicException: Storage … is immutable`. Always chain: `$page = $page->update(...)`.
- **`$page->createFile()` throws a `DuplicateException`** when a same-named file exists
  with *different* bytes (identical bytes are reused silently). Use **`$file->replace($src)`**
  to swap bytes in place (keeps uuid/content/refs; old file untouched if it throws) — the
  TMDB `Client::attachImage()` wraps all of this; reuse it for any synced file.
- **Panel `<img>` tags can't call API routes bare**: Kirby's API auth requires a CSRF token
  (header `x-csrf` or `?csrf=` query param) for session-authed requests, and an `<img>`
  sends neither — append `?csrf=` (see `Client::csrfToken()`), or every image 403s.
- **`$page->title()` falls back to the slug** when the title field is empty (→ date-stamped
  garbage in listings/filenames). For optional/derived titles read
  `$page->content()->get('title')` or call `displayTitle()`.
- **Don't define a model method whose name collides with a blueprint field** — e.g. a
  `ticketUrl()` method shadows the magic field accessor, so `$page->ticketUrl()` returns a
  string instead of a Field and breaks `->isNotEmpty()`/`->value()`/`->esc()`. (`film()` and
  `displayTitle()` are deliberate overrides; `ticketUrl()` is deliberately absent.)
- **Kirby query language can call model methods** that return Pages/bool/string. Used for the
  Film's read-only screening sections (`query: page.upcomingShowings`) and table column flags
  (`{{ page.hasUpcoming }}`, `{{ page.showings.count }}`, `{{ page.displayTitle }}`). No
  controller needed for those.

**Multi-language**
- **`$page->content($lang)` / field accessors MERGE the default language under every
  translation** — an untranslated field never looks empty. For "is this field actually
  translated" (e.g. fill-empty checks) read the RAW translation:
  `$page->version()->read($lang)` (lowercased keys, `null` when no translation file).
- **`$page->update($vals, $nonDefaultLang)` silently DROPS `translate: false` fields**
  (the Form filters them). In Panel API context the implicit current language is whatever
  tab the editor is on (`x-language` header) — so invariant fields (file refs!) must be
  written with an explicit default-language code or they vanish into thin air.
- **`update()` on a non-default language MATERIALIZES every translatable field** in that
  translation file, copying the current fallback values — after the first EN write,
  `film.en.txt` contains a German synopsis copy that raw reads see as non-empty.
  "Überschreiben" (overwrite) is the editor's way out, not fill-empty.
- **Never enable `languages.detect`** — it stores the detected language in the session,
  i.e. sets a cookie (SPEC §7 forbids cookies). Language switching is plain links.
- **`t()` keys must exist in BOTH `site/languages/*.php` files** — there is no
  cross-language fallback for site translations; a missing key renders the fallback arg
  or nothing. Always pass the German string as the `t()` fallback parameter.
- In CLI scripts, `setCurrentLanguage()` alone leaves `t()` on the old language — also
  call `setCurrentTranslation()`, or use `$kirby->site()->visit($page, $lang)` (which is
  what real requests do) before rendering.
- **PHP `date()`/`toDate()` always produce English weekday/month names** — public output
  goes through `localDate()` (IntlDateFormatter + per-language ICU pattern). Keep
  `toDate('YmdHi')` for `num:` and other locale-neutral machine formats.
- **`url('program')` is not language-aware** (always the default-language path) — build
  internal links with `page('program')->url()`; the other-language URL of the same page
  is `$page->url('en')`.

**Panel blueprints**
- **Never mix top-level `columns:` with a sibling top-level `sections:`** (invalid in Kirby 5).
  To combine a form with listings, nest `tabs → columns → sections` (see `film.yml`).
- **Never override a table section's `title` column `value:`** — it replaces the `{text,href}`
  link object with a bare string and breaks navigation ("Could not find Panel view…"). To set
  the clickable title text, use the **section-level `text:`** option (see `program.yml`, which
  sets `text: "{{ page.displayTitle }}"`).
- `num:` must encode **date+time** (`YmdHi`), or two same-day showings collide.
- `info:`/header templates have **no inline conditionals** — single plain placeholders only.

**Config, secrets, cache (TMDB)**
- **Do NOT register `'options' => ['cache' => true]` on the tmdb plugin.** It stores a flat
  `kinemathek.tmdb` option key that **shadows the nested credentials** in config.php, so
  `option('kinemathek.tmdb.key')` resolves to `null` and TMDB silently 401s. Enable the cache
  in `config.php` via `'cache' => ['kinemathek/tmdb' => true]` and access it with the **slash**
  form `kirby()->cache('kinemathek/tmdb')`.
- **A v3 API key (32-hex) goes in `TMDB_KEY`** (sent as `api_key` query param). A **v4 bearer
  JWT goes in `TMDB_TOKEN`** (sent as a `Bearer` header; takes precedence if both set). Putting
  a v3 key in `token` → TMDB 401.
- Secrets live in a **gitignored `.env`** loaded by `config.php` via a small `getenv` loader.
  Never put a real credential in a tracked file.
- In `apply`, authorise the **real caller** *before* `impersonate('kirby')` and capture the
  flags into the closure — permission checks inside `impersonate` evaluate against the
  almighty system user.
- **`$model->permissions()->can()` returns `false` for unknown actions** instead of
  throwing — and file create/delete are **`files`-category role permissions**
  (`$user->role()->permissions()->for('files', 'create')`), NOT page actions. The bogus
  `$page->permissions()->can('createFile')` silently returned false for every real Panel
  user (only the almighty `kirby` user short-circuits to true, so CLI tests that
  impersonate `kirby` can't catch it — verify permission gates as a **real** user).

**Data handling**
- **Multiselect/tags are stored as comma-separated OR as a YAML sequence** (`- value`). Never
  `explode(',')` them — use `Kinemathek::splitField()`/`listValues()` or the `commaList` field
  method (they sniff a leading `- ` and use `->yaml()`).
- **Timezone is pinned to Europe/Berlin** at plugin load (`date_default_timezone_set`); the
  program boundary, `isPast`, and ICS DTSTART all depend on it.
- For the `.ics` response set **only** `->type('text/calendar')` — Kirby appends
  `charset=UTF-8`; setting it yourself doubles the charset. ICS line folding counts **octets**
  (75/74), UTF-8-aware, or German umlauts break.
- `FilmPage::showings()` is O(all showings) and the archive calls it per row — keep the
  per-request `$showingsCache` memo.

**Asset pipeline**
- Tailwind v4 uses `@import "tailwindcss" source(none)` + `@source "../../site"` **on purpose**
  — automatic detection would scan the committed `kirby/` core. If classes start living outside
  `site/`, add another `@source`.
- Bun blocks untrusted postinstalls (e.g. `@parcel/watcher`, used by `tailwind --watch`); the
  project's own `postinstall` (vendor) still runs. If watch misbehaves, that's why.
- The TMDB Panel field is **no-build** (Kirby ships the Vue compiler for plugins) — keep the
  inline `template`; don't add a bundler. Use `k-icon type="loader"` (there is no `k-loader`).

---

## Common tasks

- **Add a Film/Showing/Event field**: add it to the blueprint AND wherever it's read
  (templates, `Kinemathek`/models, `mapToFilm` if TMDB-sourced). Keep names lowercase-safe and
  update the contract above + `CLAUDE.md`/`STATE.md`.
- **Add a descriptive facet**: add to `Kinemathek::FACETS` with `on: self|film|both`; it's
  picked up by `filterByFacets`/`availableFacets` and the controllers automatically.
- **Work with TMDB**: credentials in `.env`; lookups are Panel-only (the `tmdblookup` field on
  a Film). Server-side + cached; the public site never calls TMDB. Apply syncs **every
  content language** (Kirby code → TMDB locale via `kinemathek.tmdb.languages`): the full
  field set to DE, `Client::TRANSLATABLE` (title/synopsis/genre, localized) to EN;
  fill-empty is checked per language. Apply pulls fields,
  the poster AND stills (best textless backdrops, `maxStills`); synced files are named
  `tmdb-poster-{id}.*` / `tmdb-still-{id}-{img}.*` — that prefix is the contract that lets
  overwrite-cleanup distinguish them from manual uploads. Search thumbnails download
  on demand in the `/thumb` route, never during search.
- **Verifying changes** (preferred over the dev server): a throwaway script —
  `require __DIR__.'/kirby/bootstrap.php'; $kirby = new Kirby(); $kirby->impersonate('kirby');`
  — then assert blueprints parse (`$page->blueprint()->toArray()`), render pages
  (`$page->render()`), render ICS (`$page->render([], 'ics')`), or call model/Client methods.
  Delete the script after.

---

## Privacy (hard, non-negotiable — SPEC §7)

No cookies, no third-party tracking, no consent banner. All assets vendored locally; TMDB
fetched server-side + cached; posters downloaded to local files; thumbnails via a first-party
proxy. Ticket (Mars EDV) and TMDB attribution links are plain `<a>` navigations
(`rel="noopener noreferrer"`). No iframes. Don't introduce any client-side third-party request.

---

## Repo policy

- Branch `main`. **`content/`, `media/`, `node_modules/`, `.env` are gitignored** (content is
  editorial data, provisioned per environment). `kirby/`, built `assets/css/styles.css`,
  `assets/vendor/`, `bun.lock` ARE committed.
- **Commit only when asked.** When you do, prefer small logical commits.
