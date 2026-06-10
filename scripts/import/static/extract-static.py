#!/usr/bin/env python3
"""Extract the static subpages of the old Kinemathek Karlsruhe site (WordPress)
into static-pages.json for content migration.

Sources (cached REST responses fetched 2026-06-10 via curl, sequential, 0.8 s delay):
  _pages-full.json  = /wp-json/wp/v2/pages?per_page=100&_fields=id,slug,title,link,parent,content
  _posts-full.json  = /wp-json/wp/v2/posts?per_page=100&_fields=id,slug,title,link,content
  _home.html        = homepage (nav menus)

Static surface = WP `page` posts (about/contact/legal/forms) + a curated set of
evergreen `post` entries that serve as section landing pages (nav) and permanent
Filmbildung offerings. Reihe/series posts, films (wp_theatre_prod) and the
Spielplan are deliberately excluded — they are program content, not static pages.

Run from this directory: python3 extract-static.py
"""
import json
import re
import html
from datetime import datetime, timezone
from pathlib import Path

HERE = Path(__file__).parent
BASE = "https://kinemathek-karlsruhe.de"

# slug -> (source_type, parent_slug_or_None, title_override_or_None, nav_label_or_None)
SELECTION = {
    # --- WP pages ---
    "ueber-uns":            ("page", None, "Über uns", "Kommunales Kino"),
    "kontakt":              ("page", None, None, "Kontakt"),
    "mitglied-werden":      ("page", None, None, "Mitglied werden"),
    "anfrage":              ("page", None, None, "Vermietung"),
    "newsletter":           ("page", None, None, None),
    "datenschutzerklaerung":("page", None, None, None),
    # --- posts acting as section landing pages (in the main nav) ---
    "events":                       ("post", None, None, "Events"),
    "festivals-in-der-kinemathek":  ("post", None, None, "Festivals"),
    "filmbildung":                  ("post", None, None, "Filmbildung"),
    "projekte":                     ("post", None, None, "Projekte"),
    # --- evergreen Filmbildung offerings (linked as subpages from /filmbildung/) ---
    "kinoknirpse":                          ("post", "filmbildung", None, None),
    "bilderbuchkino":                       ("post", "filmbildung", None, None),
    "cinefete":                             ("post", "filmbildung", None, None),
    "i-like-films":                         ("post", "filmbildung", None, None),
    "le-cinema-cent-ans-de-jeunesse-ccaj":  ("post", "filmbildung", None, None),
    # --- evergreen youth programme (linked from the homepage) ---
    "junge-kinemathek":     ("post", None, None, None),
}

VOID = re.compile(r"<(br|hr)\s*/?>", re.I)
TAG = re.compile(r"<[^>]+>")


def absolutize(url):
    url = html.unescape(url.strip())
    if url.startswith("//"):
        return "https:" + url
    if url.startswith("/"):
        return BASE + url
    if not re.match(r"^[a-z]+:", url) and url:
        # bare domain like "dokka.de" or relative path
        if re.match(r"^[\w.-]+\.[a-z]{2,}(/|$)", url):
            return "https://" + url
        return BASE + "/" + url
    return url


def strip_block(src, tag):
    return re.sub(r"<%s\b[^>]*>.*?</%s>" % (tag, tag), " ", src, flags=re.S | re.I)


def to_text(content):
    """Markdown-ish plain text from WP block HTML."""
    c = strip_block(content, "style")
    c = strip_block(content, "script")
    c = strip_block(content, "noscript")
    c = strip_block(content, "form")
    c = strip_block(content, "svg")
    c = re.sub(r"<!--.*?-->", " ", c, flags=re.S)
    # headings
    def h(m):
        level = int(m.group(1))
        inner = TAG.sub("", m.group(2)).strip()
        return "\n\n" + "#" * level + " " + inner + "\n\n"
    c = re.sub(r"<h([1-6])[^>]*>(.*?)</h\1>", h, c, flags=re.S)
    # links -> [text](url)
    def a(m):
        href = absolutize(m.group(1))
        text = TAG.sub("", m.group(2)).strip()
        if not text:
            return " "
        if href.startswith("mailto:") or href.startswith("tel:"):
            return text
        return "[%s](%s)" % (text, href)
    c = re.sub(r'<a[^>]*href="([^"]*)"[^>]*>(.*?)</a>', a, c, flags=re.S)
    # list items
    c = re.sub(r"<li[^>]*>", "\n- ", c)
    # block boundaries -> blank line
    c = re.sub(r"</(p|div|ul|ol|figure|figcaption|blockquote|table|tr)>", "\n\n", c)
    c = VOID.sub("\n", c)
    c = TAG.sub(" ", c)
    c = html.unescape(c)
    c = re.sub(r"[ \t]+", " ", c)
    c = re.sub(r" ?\n ?", "\n", c)
    c = re.sub(r"\n{3,}", "\n\n", c)
    return c.strip()


def extract_images(content):
    imgs, seen = [], set()
    for m in re.finditer(r"<img\b[^>]*>", content):
        tag = m.group(0)
        src = re.search(r'src="([^"]*)"', tag)
        alt = re.search(r'alt="([^"]*)"', tag)
        if not src:
            continue
        url = absolutize(src.group(1))
        if url in seen:
            continue
        seen.add(url)
        imgs.append({"url": url, "alt": html.unescape(alt.group(1)) if alt else ""})
    return imgs


def extract_links(content):
    links, seen = [], set()
    for m in re.finditer(r'<a[^>]*href="([^"]*)"[^>]*>(.*?)</a>', content, re.S):
        url = absolutize(m.group(1))
        text = html.unescape(TAG.sub("", m.group(2))).strip()
        if not url or url.startswith("#") or url in seen:
            continue
        seen.add(url)
        links.append({"url": url, "text": text})
    return links


def extract_embeds(content):
    embeds = []
    for m in re.finditer(r'<iframe[^>]*src="([^"]*)"', content):
        embeds.append({"type": "iframe", "src": absolutize(m.group(1))})
    for m in re.finditer(r'<form[^>]*>', content):
        cls = re.search(r'class="([^"]*)"', m.group(0))
        embeds.append({"type": "form", "class": cls.group(1) if cls else ""})
    if "nfForms" in content:  # Ninja Forms renders client-side from embedded JSON
        embeds.append({"type": "js-form", "provider": "Ninja Forms"})
    if "wpcf7" in content and not any(e["type"] == "form" for e in embeds):
        embeds.append({"type": "form", "provider": "Contact Form 7"})
    return embeds


def extract_contact(text):
    """Best-effort structured contact data from the plain text."""
    contact = {}
    emails = sorted(set(
        re.findall(r"[\w.+-]+@[\w.-]+\.[a-z]{2,}", text)
        + [e.replace("(ät)", "@").replace("(at)", "@")
           for e in re.findall(r"[\w.+-]+\((?:ät|at)\)[\w.-]+\.[a-z]{2,}", text)]
    ))
    if emails:
        contact["emails"] = emails
    phones = sorted(set(re.findall(r"(?:Tel\.?\s*)?(\+49[\d /()-]{7,})", text)))
    if phones:
        contact["phones"] = [re.sub(r"\s+", " ", p).strip() for p in phones]
    addr = re.search(r"((?:Kinemathek Karlsruhe\n)?[A-ZÄÖÜ][\wäöüß.-]+(?:passage|straße|strasse|weg|platz)\s*\d+[a-z]?\n\d{5}\s+[A-ZÄÖÜ][\wäöüß]+)", text)
    if addr:
        contact["address"] = addr.group(1)
    hours = [ln.strip() for ln in text.split("\n")
             if re.search(r"\bUhr\b", ln) and re.search(r"Öffnungszeit|Kasse|geöffnet|Mo|Di|Mi|Do|Fr|Sa|So|täglich", ln)]
    if hours:
        contact["hours"] = hours[:8]
    return contact


# pages whose auto-classification is wrong; slug -> (classification, reason)
OVERRIDES = {
    # carries contact data, but it is the legal page, not the contact page
    "datenschutzerklaerung": ("text", "legal page (Impressum + Datenschutzerklärung); plain prose"),
}


def classify(slug, content, text, embeds, links, contact):
    if slug in OVERRIDES:
        return OVERRIDES[slug]
    reasons = []
    if any(e["type"] in ("form", "js-form") for e in embeds):
        provider = next((e.get("provider") or e.get("class", "") for e in embeds
                         if e["type"] in ("form", "js-form")), "")
        reasons.append("embedded form (%s)" % (provider or "WordPress"))
    if any(e["type"] == "iframe" for e in embeds):
        reasons.append("%d iframe embed(s)" % sum(1 for e in embeds if e["type"] == "iframe"))
    teaser_links = sum(1 for l in links if "/film/" in l["url"] or
                       (l["text"] in ("Mehr", "Weiterlesen", "Zum Film")))
    if teaser_links >= 3:
        reasons.append("teaser/program grid layout (%d teaser links — dynamic program content baked into the page)" % teaser_links)
    cols = len(re.findall(r"wp-block-columns", content))
    if cols >= 3:
        reasons.append("multi-column block layout (%d column blocks)" % cols)
    if reasons:
        return "complex", "; ".join(reasons)
    if (contact.get("emails") or contact.get("phones")) and contact.get("address") and len(text) < 4000:
        return "contact", "address/phone/email/opening-hours dominated page"
    return "text", "plain prose"


def main():
    pages = {p["slug"]: p for p in json.load(open(HERE / "_pages-full.json"))}
    posts = {p["slug"]: p for p in json.load(open(HERE / "_posts-full.json"))}

    out_pages = []
    for slug, (src_type, parent, title_override, nav_label) in SELECTION.items():
        rec = (pages if src_type == "page" else posts)[slug]
        content = rec["content"]["rendered"]
        title = title_override or html.unescape(rec["title"]["rendered"]).strip() or slug
        text = to_text(content)
        embeds = extract_embeds(content)
        links = extract_links(content)
        images = extract_images(content)
        contact = extract_contact(text)
        classification, reason = classify(slug, content, text, embeds, links, contact)
        page = {
            "slug": slug,
            "title": title,
            "parent": parent,
            "wpType": src_type,
            "wpId": rec["id"],
            "url": "%s/%s/" % (BASE, slug),
            "navLabel": nav_label,
            "classification": classification,
            "classificationReason": reason,
            "html": content,
            "text": text,
            "images": images,
            "links": links,
            "embeds": embeds,
            "contact": contact,
        }
        out_pages.append(page)

    out = {
        "scrapedAt": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "source": BASE + "/wp-json/wp/v2/ (pages + posts, rendered content)",
        "pages": out_pages,
    }
    dest = HERE / "static-pages.json"
    dest.write_text(json.dumps(out, ensure_ascii=False, indent=2))
    print("wrote %s (%d pages)" % (dest.name, len(out_pages)))
    for p in out_pages:
        print("  %-38s %-8s %s" % (p["slug"], p["classification"], p["classificationReason"][:80]))


if __name__ == "__main__":
    main()
