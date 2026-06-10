#!/usr/bin/env python3
"""Scrape the current film program from the old (WordPress) Kinemathek Karlsruhe site.

Sources:
  - https://kinemathek-karlsruhe.de/spielplan/      (HTML; WP Theatre event listing, grouped by day)
  - https://kinemathek-karlsruhe.de/film/<slug>/    (HTML; per-production detail: original title,
                                                     technical specs, synopsis, Reihe tags)
  - /wp-json/wp/v2/wp_theatre_prod                  (REST; used as cross-check for synopsis/content)

The WP Theatre plugin's events (wp_theatre_event) are NOT exposed via REST, so the
schedule comes from the rendered /spielplan/ listing.

Polite scraping: sequential requests, 0.8 s delay between fetches.

Output: old-site-program.json next to this script.
"""

import html as htmllib
import json
import re
import subprocess
import sys
import time
from datetime import date
from pathlib import Path

BASE = "https://kinemathek-karlsruhe.de"
OUT = Path(__file__).parent / "old-site-program.json"
DELAY = 0.8

GERMAN_MONTHS = {
    "Januar": 1, "Februar": 2, "März": 3, "April": 4, "Mai": 5, "Juni": 6,
    "Juli": 7, "August": 8, "September": 9, "Oktober": 10, "November": 11,
    "Dezember": 12,
}

SUBTITLE_TOKENS = ["OmeU", "OmU", "OF", "DF", "dtF"]


def fetch(url: str) -> str:
    time.sleep(DELAY)
    res = subprocess.run(
        ["curl", "-sL", "--max-time", "45", url],
        capture_output=True, text=True, check=True,
    )
    return res.stdout


def clean(text: str) -> str:
    text = re.sub(r"<[^>]+>", "", text)
    text = htmllib.unescape(text)
    return re.sub(r"\s+", " ", text).strip()


# ---------------------------------------------------------------- spec parsing

def parse_specs(spec: str) -> dict:
    """Parse a WP Theatre 'technical specs' line like
    'Pedro Pinho, PT/FR/BR/RO 2025; 211' OmU'  or
    'Hayao Miyazaki, Japan 1997 | 128 Min. | OmU'."""
    out = {"directors": [], "country": [], "year": None, "runtime": None,
           "subtitles": [], "specRaw": spec}
    s = spec.replace("′", "'").replace("‘", "'")  # prime / curly quote -> '

    m_year = re.search(r"\b(19|20)\d{2}\b", s)
    if m_year:
        out["year"] = int(m_year.group(0))

    m_rt = re.search(r"(\d+)\s*(?:'|Min\b)", s)
    if m_rt:
        out["runtime"] = int(m_rt.group(1))

    for tok in SUBTITLE_TOKENS:
        if re.search(r"\b" + tok + r"\b", spec):
            out["subtitles"].append(tok)
            break  # tokens are mutually exclusive on this site

    # directors + country live before the year: "Name[, Name], CC/CC YYYY"
    if m_year:
        head = s[: m_year.start()].strip().rstrip(",|;").strip()
        # country chunk = last comma-separated piece
        if "," in head:
            directors_part, country_part = head.rsplit(",", 1)
        else:
            directors_part, country_part = "", head
        country_part = country_part.strip()
        if country_part:
            # keep only plausible country tokens (ISO-ish codes or capitalised names);
            # drops noise like "TV-Doku" from non-standard spec lines
            out["country"] = [
                c.strip() for c in country_part.split("/")
                if re.fullmatch(r"[A-Z]{2,3}|[A-ZÄÖÜ][a-zäöüß]+", c.strip())
            ]
        directors_part = directors_part.strip()
        if directors_part:
            parts = [p.strip() for p in re.split(r"\s*/\s*", directors_part) if p.strip()]
            directors = []
            for p in parts:
                # split "A und B" only when both halves look like full names
                m = re.match(r"^(\S+\s+\S.*?)\s+und\s+(\S+\s+\S.*)$", p)
                if m:
                    directors += [m.group(1), m.group(2)]
                else:
                    directors.append(p)
            out["directors"] = directors
    return out


def is_event_spec(spec: dict) -> bool:
    """No year and no runtime -> non-film event (bar night, reading, workshop...)."""
    return spec["year"] is None and spec["runtime"] is None


# ------------------------------------------------------------- spielplan parse

def parse_spielplan(html: str, scrape_day: date) -> list[dict]:
    # strip inline <style> blocks (they contain the same class names)
    body = re.sub(r"<style\b.*?</style>", "", html, flags=re.S)

    token_re = re.compile(
        r'<h3 class="wpt_listing_group day">(?P<day>[^<]+)</h3>'
        r'|<div class="wp_theatre_event">(?P<event>.*?)'
        r'<div class="wp_theatre_event_cine_technical_specs">(?P<spec>.*?)</div></div>',
        re.S,
    )

    showings = []
    cur_date = None
    year = scrape_day.year
    prev_month = None

    for m in token_re.finditer(body):
        if m.group("day"):
            parts = m.group("day").split()  # "Mittwoch 10 Juni"
            dom, month_name = int(parts[1]), parts[2]
            month = GERMAN_MONTHS[month_name]
            if prev_month is not None and month < prev_month:
                year += 1  # listing rolled over to the next year
            prev_month = month
            cur_date = date(year, month, dom)
            continue

        if cur_date is None:
            continue  # event outside the day-grouped listing (e.g. slider)

        ev = m.group("event")
        spec_raw = clean(m.group("spec"))

        time_m = re.search(r'wp_theatre_event_datetime">([^<]*)<', ev)
        title_m = re.search(r'wp_theatre_event_title"><a href="([^"]+)"[^>]*>(.*?)</a>', ev)
        remark_m = re.search(r'wp_theatre_event_remark">(.*?)</div>', ev, re.S)
        ticket_m = re.search(r'wp_theatre_event_tickets_url[^"]*" *href="([^"]+)"', ev) \
            or re.search(r'wp_theatre_event_tickets"><a href="([^"]+)"', ev)
        tags = [clean(t) for t in re.findall(
            r'<li class="wp_theatre_prod_tag[^"]*">(.*?)</li>', ev)]

        remark = clean(remark_m.group(1)) if remark_m else ""
        venue = None
        notes = []
        if remark:
            if remark.lower().startswith("ort:"):
                venue = remark[4:].strip()
            notes.append(remark)

        showings.append({
            "date": cur_date.isoformat(),
            "time": clean(time_m.group(1)) if time_m else None,
            "title": clean(title_m.group(2)) if title_m else None,
            "filmUrl": htmllib.unescape(title_m.group(1)) if title_m else None,
            "venue": venue,
            "series": tags[0] if tags else None,
            "seriesTags": tags,
            "subtitles": [],   # filled from spec below
            "discussion": bool(re.search(r"gespräch|einführung", remark, re.I)),
            "notes": notes,
            "ticketUrl": htmllib.unescape(ticket_m.group(1)) if ticket_m else None,
            "specRaw": spec_raw,
        })
    return showings


# ------------------------------------------------------------- film page parse

def parse_film_page(html: str) -> dict:
    body = re.sub(r"<style\b.*?</style>", "", html, flags=re.S)

    def grab(cls):
        m = re.search(r'class="[^"]*\b' + cls + r'\b[^"]*"[^>]*>(.*?)</', body, re.S)
        return clean(m.group(1)) if m else None

    title = grab("wp_theatre_prod_title")
    original = grab("wp_theatre_prod_cine_original_title")
    specs = grab("wp_theatre_prod_cine_technical_specs")

    tags = [clean(t) for t in re.findall(
        r'<a href="https://kinemathek-karlsruhe\.de/reihe/[^"]+" rel="tag">(.*?)</a>', body)]

    # synopsis: post-content paragraphs; stop before footer/contact boilerplate
    paras = []
    for p in re.findall(r'<p class="wp-block-paragraph">(.*?)</p>', body, re.S):
        t = clean(p)
        if not t or re.match(r"^(Tel:|Kinemathek Karlsruhe|Kaiserpassage)", t):
            continue
        paras.append(t)
    synopsis = "\n\n".join(paras) if paras else None

    full_text = clean(body)
    mentions_discussion = bool(re.search(
        r"Filmgespräch|Publikumsgespräch|Einführung|Gespräch mit", full_text))

    fsk_m = re.search(r"FSK\s*:?\s*(?:ab\s*)?(\d+)", full_text)

    return {
        "title": title,
        "originalTitle": original,
        "specRaw": specs,
        "tags": list(dict.fromkeys(tags)),
        "synopsis": synopsis,
        "mentionsDiscussion": mentions_discussion,
        "fsk": int(fsk_m.group(1)) if fsk_m else None,
    }


# ----------------------------------------------------------------------- main

def main():
    today = date.today()
    print("fetching /spielplan/ ...", file=sys.stderr)
    spielplan_html = fetch(f"{BASE}/spielplan/")
    showings = parse_spielplan(spielplan_html, today)
    print(f"  {len(showings)} showings", file=sys.stderr)

    film_urls = sorted({s["filmUrl"] for s in showings if s["filmUrl"]})
    films = {}
    for url in film_urls:
        print("fetching", url, file=sys.stderr)
        try:
            info = parse_film_page(fetch(url))
        except subprocess.CalledProcessError as e:
            print("  FAILED:", e, file=sys.stderr)
            continue
        info["url"] = url
        info["slug"] = url.rstrip("/").rsplit("/", 1)[-1]
        films[url] = info

    # merge spec parsing into films + showings
    films_out = []
    for url, f in films.items():
        spec = parse_specs(f["specRaw"] or "")
        is_event = is_event_spec(spec)
        films_out.append({
            "title": f["title"],
            "originalTitle": f["originalTitle"] if f["originalTitle"] != f["title"] else None,
            "directors": spec["directors"],
            "year": spec["year"],
            "country": spec["country"],
            "runtime": spec["runtime"],
            "subtitleVersion": spec["subtitles"][0] if spec["subtitles"] else None,
            "synopsis": f["synopsis"],
            "synopsisEn": None,  # site is German-only
            "series": f["tags"],
            "fsk": f["fsk"],
            "mentionsDiscussion": f["mentionsDiscussion"],
            "isEvent": is_event,
            "specRaw": f["specRaw"],
            "slug": f["slug"],
            "url": url,
        })

    for s in showings:
        spec = parse_specs(s.pop("specRaw") or "")
        s["subtitles"] = spec["subtitles"]
        film = films.get(s.pop("filmUrl"))
        s["filmSlug"] = film["slug"] if film else None
        s["isEvent"] = is_event_spec(spec)
        s["notes"] = "; ".join(s["notes"]) if s["notes"] else None
        s.pop("seriesTags", None)

    doc = {
        "scrapedAt": today.isoformat(),
        "source": {
            "site": BASE,
            "method": "HTML scrape of /spielplan/ (WP Theatre listing) + /film/<slug>/ detail pages",
            "restApiNote": "wp_theatre_prod exposed via /wp-json/wp/v2/wp_theatre_prod, "
                           "but wp_theatre_event (dates) is not in REST; schedule taken from HTML.",
        },
        "films": sorted(films_out, key=lambda f: f["slug"]),
        "showings": sorted(showings, key=lambda s: (s["date"], s["time"] or "")),
    }
    OUT.write_text(json.dumps(doc, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"wrote {OUT} ({len(films_out)} films, {len(showings)} showings)", file=sys.stderr)


if __name__ == "__main__":
    main()
