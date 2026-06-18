#!/usr/bin/env python3
"""Scrape the FULL film back-catalogue from the old (WordPress) Kinemathek site.

Companion to `scrape-old-site.py`, which only captures the *current* /spielplan/
window (~30 productions with live screening dates). This script instead walks
the entire `wp_theatre_prod` archive via the WP REST API and produces a
films-only export for the permanent Kirby film archive.

Sources:
  - /wp-json/wp/v2/wp_theatre_prod?categories=7   (REST; the "Filme" category =
                                                   real films, not recurring
                                                   non-film events. id 7 verified
                                                   against /wp/v2/categories)
  - /film/<slug>/                                 (HTML; per-production detail:
                                                   original title, technical
                                                   specs, synopsis, Reihe tags —
                                                   the cine_* meta is NOT in REST)

The parsing functions (parse_film_page / parse_specs / is_event_spec / clean /
fetch) are imported verbatim from scrape-old-site.py so both scrapers share one
tested implementation; importing the module does not run its main() (guarded by
__name__ == "__main__").

Output shape mirrors old-site-program.json's `films` array exactly, so
`scripts/import-program.php --input=<this>` consumes it unchanged. There are NO
showings here (the archive has no live dates — wp_theatre_event is not in REST
and there is no public month archive), so the importer creates Film pages only;
existing films are skipped idempotently by slug / tmdbId.

Polite scraping: sequential REST pages + detail fetches with the shared 0.8 s
delay. Detail HTML is cached under CACHE_DIR so re-runs (and interrupted runs)
are cheap and resumable.

Usage:
  python3 scrape-archive.py                 # scrape all "Filme" -> archive-films.json
  python3 scrape-archive.py --limit=10      # first 10 (smoke test)
  python3 scrape-archive.py --refresh       # ignore the HTML cache, re-fetch
"""

import importlib.util
import json
import subprocess
import sys
import time
from datetime import date
from pathlib import Path

HERE = Path(__file__).parent

# ---- reuse the tested parsers from scrape-old-site.py (hyphen => importlib) ----
_spec = importlib.util.spec_from_file_location(
    "scrape_old_site", HERE / "scrape-old-site.py"
)
_mod = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(_mod)  # safe: main() is guarded by __name__ == "__main__"

BASE = _mod.BASE
fetch = _mod.fetch                  # sequential, 0.8 s delay, curl --max-time 45
parse_film_page = _mod.parse_film_page
parse_specs = _mod.parse_specs
is_event_spec = _mod.is_event_spec

FILME_CATEGORY_ID = 7               # /wp/v2/categories slug "filme" -> 1132 posts
OUT = HERE / "archive-films.json"
CACHE_DIR = HERE / ".archive-cache"
DELAY = 0.8


def parse_args(argv: list[str]) -> dict:
    opts = {"limit": None, "refresh": False}
    for arg in argv[1:]:
        if arg == "--refresh":
            opts["refresh"] = True
        elif arg.startswith("--limit="):
            opts["limit"] = int(arg.split("=", 1)[1])
        else:
            sys.exit(f"Unknown argument: {arg}")
    return opts


def rest_list_filme() -> list[dict]:
    """All wp_theatre_prod in the Filme category, slim fields, paginated."""
    prods: list[dict] = []
    page = 1
    while True:
        url = (
            f"{BASE}/wp-json/wp/v2/wp_theatre_prod"
            f"?categories={FILME_CATEGORY_ID}&per_page=100&page={page}"
            f"&_fields=id,slug,link,status,title"
        )
        time.sleep(DELAY)
        res = subprocess.run(
            ["curl", "-sL", "--max-time", "45", url],
            capture_output=True, text=True, check=True,
        )
        batch = json.loads(res.stdout)
        if not isinstance(batch, list) or not batch:
            break
        prods += batch
        print(f"  REST page {page}: +{len(batch)} (total {len(prods)})", file=sys.stderr)
        if len(batch) < 100:
            break
        page += 1
    return prods


def detail_html(slug: str, link: str, refresh: bool) -> str:
    """Fetch /film/<slug>/ with an on-disk cache (resumable, polite re-runs)."""
    CACHE_DIR.mkdir(exist_ok=True)
    cached = CACHE_DIR / f"{slug}.html"
    if cached.is_file() and not refresh and cached.stat().st_size > 0:
        return cached.read_text(encoding="utf-8")
    url = link or f"{BASE}/film/{slug}/"
    html = fetch(url)
    cached.write_text(html, encoding="utf-8")
    return html


def main() -> None:
    opts = parse_args(sys.argv)

    print("fetching wp_theatre_prod (Filme) via REST ...", file=sys.stderr)
    prods = rest_list_filme()
    prods = [p for p in prods if p.get("slug")]
    prods.sort(key=lambda p: p["slug"])
    if opts["limit"] is not None:
        prods = prods[: opts["limit"]]
    print(f"  {len(prods)} Filme productions to scrape", file=sys.stderr)

    films_out = []
    event_like = []   # category-Filme prods whose spec line parses as a non-film
    failed = []
    for i, p in enumerate(prods, 1):
        slug = p["slug"]
        rest_title = (p.get("title") or {}).get("rendered") or ""
        print(f"[{i}/{len(prods)}] {slug}", file=sys.stderr)
        try:
            html = detail_html(slug, p.get("link") or "", opts["refresh"])
            info = parse_film_page(html)
        except subprocess.CalledProcessError as e:
            print(f"  FAILED: {e}", file=sys.stderr)
            failed.append(slug)
            continue

        spec = parse_specs(info["specRaw"] or "")
        spec_is_event = is_event_spec(spec)
        if spec_is_event:
            event_like.append(slug)

        title = info["title"] or rest_title
        original = info["originalTitle"] if info["originalTitle"] != title else None

        films_out.append({
            "title": title,
            "originalTitle": original,
            "directors": spec["directors"],
            "year": spec["year"],
            "country": spec["country"],
            "runtime": spec["runtime"],
            "subtitleVersion": spec["subtitles"][0] if spec["subtitles"] else None,
            "synopsis": info["synopsis"],
            "synopsisEn": None,                  # old site is German-only
            "series": info["tags"],
            "fsk": info["fsk"],
            "mentionsDiscussion": info["mentionsDiscussion"],
            # archive = films only (Ruby's choice); keep all category-Filme prods
            # as films. specIsEvent flags the few whose spec line lacks year AND
            # runtime so the post-import audit can review them.
            "isEvent": False,
            "specIsEvent": spec_is_event,
            "specRaw": info["specRaw"],
            "slug": slug,
            "wpId": p.get("id"),
            "url": p.get("link") or f"{BASE}/film/{slug}/",
        })

    doc = {
        "scrapedAt": date.today().isoformat(),
        "source": {
            "site": BASE,
            "method": "REST wp_theatre_prod?categories=7 (Filme) + /film/<slug>/ detail pages",
            "scope": "full film back-catalogue (archive); no live showings",
        },
        "stats": {
            "filmsScraped": len(films_out),
            "specParsedAsEvent": len(event_like),
            "fetchFailed": len(failed),
        },
        "films": sorted(films_out, key=lambda f: f["slug"]),
        # no "showings" key on purpose: the importer treats it as []
    }
    OUT.write_text(json.dumps(doc, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(
        f"\nwrote {OUT} ({len(films_out)} films; "
        f"{len(event_like)} spec-as-event; {len(failed)} fetch-failed)",
        file=sys.stderr,
    )
    if event_like:
        print("spec-as-event (review): " + ", ".join(event_like), file=sys.stderr)
    if failed:
        print("fetch-failed: " + ", ".join(failed), file=sys.stderr)


if __name__ == "__main__":
    main()
