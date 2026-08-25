#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Apply SEO + GEO metadata across static-site HTML and generate discovery files.

Usage:
  python tools/apply_seo.py
  python tools/apply_seo.py --base-url https://eurasia.uwzghana.com  # staging preview
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from datetime import date
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
STATIC = REPO / "static-site"
TODAY = date.today().isoformat()

sys.path.insert(0, str(Path(__file__).resolve().parent))
from seo_config import FAQ_HOME, ORG, PAGES, SITE_BASE, SITE_NAME, DEFAULT_OG_IMAGE, LOGO_URL  # noqa: E402


def page_url(rel: str, base: str) -> str:
    if rel == "index.html":
        return f"{base}/"
    return f"{base}/{rel.replace(chr(92), '/')}"


def asset_prefix(rel: str) -> str:
    depth = rel.count("/")
    return "../" * depth if depth else ""


def esc_attr(s: str) -> str:
    return (
        s.replace("&", "&amp;")
        .replace('"', "&quot;")
        .replace("<", "&lt;")
    )


def org_node(base: str) -> dict:
    addr = ORG["address"].copy()
    return {
        "@type": "Organization",
        "@id": f"{base}/#organization",
        "name": ORG["name"],
        "alternateName": ORG["alternateName"],
        "url": f"{base}/",
        "logo": {"@type": "ImageObject", "url": LOGO_URL},
        "email": ORG["email"],
        "foundingDate": ORG["foundingDate"],
        "description": ORG["description"],
        "address": {**addr, "@type": "PostalAddress"},
        "sameAs": ORG["sameAs"],
    }


def breadcrumb_node(crumbs: list[tuple[str, str]], base: str) -> dict:
    items = []
    for i, (name, href) in enumerate(crumbs, start=1):
        url = href if href.startswith("http") else f"{base}{href if href.startswith('/') else '/' + href}"
        if href == "/":
            url = f"{base}/"
        items.append(
            {
                "@type": "ListItem",
                "position": i,
                "name": name,
                "item": url,
            }
        )
    return {"@type": "BreadcrumbList", "itemListElement": items}


def faq_node(faqs: list[dict]) -> dict:
    return {
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": f["q"],
                "acceptedAnswer": {"@type": "Answer", "text": f["a"]},
            }
            for f in faqs
        ],
    }


def build_json_ld(rel: str, meta: dict, base: str) -> list[dict]:
    url = page_url(rel, base)
    graph: list[dict] = [org_node(base)]
    schema = meta.get("schema", "webpage")

    if schema == "home":
        graph.append(
            {
                "@type": "WebSite",
                "@id": f"{base}/#website",
                "url": f"{base}/",
                "name": SITE_NAME,
                "description": meta["description"],
                "publisher": {"@id": f"{base}/#organization"},
                "inLanguage": ["de", "en"],
                "potentialAction": {
                    "@type": "SearchAction",
                    "target": {
                        "@type": "EntryPoint",
                        "urlTemplate": f"{base}/?q={{search_term_string}}",
                    },
                    "query-input": "required name=search_term_string",
                },
            }
        )
        graph.append(faq_node(FAQ_HOME))

    web_page = {
        "@type": "WebPage",
        "@id": f"{url}#webpage",
        "url": url,
        "name": meta["title"],
        "description": meta["description"],
        "isPartOf": {"@id": f"{base}/#website"},
        "about": {"@id": f"{base}/#organization"},
        "inLanguage": "de",
    }
    graph.append(web_page)

    if schema == "person" and "person" in meta:
        p = meta["person"]
        graph.append(
            {
                "@type": "Person",
                "@id": f"{url}#person",
                "name": p["name"],
                "jobTitle": p["jobTitle"],
                "image": p["image"],
                "url": url,
                "worksFor": {"@id": f"{base}/#organization"},
            }
        )
        web_page["@type"] = "ProfilePage"
        web_page["mainEntity"] = {"@id": f"{url}#person"}

    if schema == "events":
        graph.append(
            {
                "@type": "CollectionPage",
                "@id": f"{url}#collection",
                "url": url,
                "name": meta["title"],
                "description": meta["description"],
                "about": {"@type": "Thing", "name": "Veranstaltungen im eurasischen Raum"},
            }
        )

    if schema == "membership":
        graph.append(faq_node(FAQ_HOME[2:3]))  # membership FAQ only

    if "breadcrumbs" in meta:
        graph.append(breadcrumb_node(meta["breadcrumbs"], base))

    return graph


def seo_block(rel: str, meta: dict, base: str) -> str:
    url = page_url(rel, base)
    robots = meta.get("robots", "index,follow")
    og_image = meta.get("og_image", DEFAULT_OG_IMAGE)
    og_type = "profile" if meta.get("schema") == "person" else "website"
    prefix = asset_prefix(rel)
    graph = build_json_ld(rel, meta, base)
    ld_json = json.dumps(
        {"@context": "https://schema.org", "@graph": graph},
        ensure_ascii=False,
        indent=2,
    )

    lines = [
        f'<meta name="description" content="{esc_attr(meta["description"])}">',
        f'<meta name="robots" content="{robots}">',
        f'<link rel="canonical" href="{url}">',
        f'<link rel="alternate" hreflang="de" href="{url}">',
        f'<link rel="alternate" hreflang="en" href="{url}">',
        f'<link rel="alternate" hreflang="x-default" href="{url}">',
        "<!-- Open Graph -->",
        f'<meta property="og:type" content="{og_type}">',
        f'<meta property="og:site_name" content="{esc_attr(SITE_NAME)}">',
        f'<meta property="og:title" content="{esc_attr(meta["title"])}">',
        f'<meta property="og:description" content="{esc_attr(meta["description"])}">',
        f'<meta property="og:url" content="{url}">',
        f'<meta property="og:locale" content="de_DE">',
        f'<meta property="og:locale:alternate" content="en_US">',
        f'<meta property="og:image" content="{og_image}">',
        f'<meta property="og:image:alt" content="{esc_attr(SITE_NAME)}">',
        "<!-- Twitter Card -->",
        '<meta name="twitter:card" content="summary_large_image">',
        f'<meta name="twitter:title" content="{esc_attr(meta["title"])}">',
        f'<meta name="twitter:description" content="{esc_attr(meta["description"])}">',
        f'<meta name="twitter:image" content="{og_image}">',
        "<!-- Icons / PWA -->",
        f'<link rel="icon" href="{prefix}assets/images/embed-62c28610d17731c2.png" type="image/png">',
        f'<link rel="apple-touch-icon" href="{prefix}assets/images/embed-62c28610d17731c2.png">',
        f'<link rel="manifest" href="{prefix}site.webmanifest">',
        "<!-- Structured data (schema.org) -->",
        f'<script type="application/ld+json">\n{ld_json}\n</script>',
    ]
    return "\n".join(lines) + "\n"


SEO_REPLACE_RE = re.compile(
    r'<meta name="description" content="[^"]*">.*?'
    r'<script type="application/ld\+json">\s*\{.*?\}\s*</script>\s*',
    re.DOTALL,
)


def patch_html(path: Path, rel: str, meta: dict, base: str) -> bool:
    text = path.read_text(encoding="utf-8")
    block = seo_block(rel, meta, base)

    # Update title if config differs
    new_title = meta["title"]
    text2, n = re.subn(
        r"(<title>)(.*?)(</title>)",
        lambda m: f"{m.group(1)}{new_title}{m.group(3)}",
        text,
        count=1,
    )
    text = text2 if n else text

    if SEO_REPLACE_RE.search(text):
        new_text = SEO_REPLACE_RE.sub(block, text, count=1)
    else:
        # Fallback: inject after </title>
        new_text = re.sub(
            r"(</title>\s*)",
            r"\1\n" + block + "\n",
            text,
            count=1,
        )

    if new_text != text:
        path.write_text(new_text, encoding="utf-8")
        return True
    return False


def write_sitemap(base: str) -> None:
    lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ]
    for rel, meta in sorted(PAGES.items(), key=lambda x: x[0]):
        if meta.get("sitemap", True) is False:
            continue
        loc = page_url(rel, base)
        cf = meta.get("changefreq", "monthly")
        pr = meta.get("priority", "0.5")
        lines.append("  <url>")
        lines.append(f"    <loc>{loc}</loc>")
        lines.append(f"    <lastmod>{TODAY}</lastmod>")
        lines.append(f"    <changefreq>{cf}</changefreq>")
        lines.append(f"    <priority>{pr}</priority>")
        lines.append("  </url>")
    lines.append("</urlset>")
    (STATIC / "sitemap.xml").write_text("\n".join(lines) + "\n", encoding="utf-8")


def write_robots(base: str) -> None:
    content = f"""User-agent: *
Allow: /

# WordPress admin (under /app/)
Disallow: /app/wp-admin/
Disallow: /app/wp-login.php
Disallow: /app/wp-includes/
Disallow: /app/membership-account/
Disallow: /app/my-account/

# Gated member brochure stubs (redirect to /app/)
Disallow: /mitglieder/

# Login stub
Disallow: /anmelden.html

# AI / LLM crawlers — same public content as search engines
User-agent: GPTBot
Allow: /

User-agent: ChatGPT-User
Allow: /

User-agent: Claude-Web
Allow: /

User-agent: anthropic-ai
Allow: /

User-agent: PerplexityBot
Allow: /

User-agent: Google-Extended
Allow: /

Sitemap: {base}/sitemap.xml
"""
    (STATIC / "robots.txt").write_text(content, encoding="utf-8")


def write_llms_txt(base: str) -> None:
    content = f"""# Eurasien Gesellschaft e. V.

> Unabhängige, gemeinnützige Berliner Think-Tank-Plattform für Dialog, Analyse und Verständigung im eurasischen Raum (Europa, Russland, Zentralasien, Ostasien, Südasien, Naher Osten).

## About

- Name: Eurasien Gesellschaft e. V. (registered association, Berlin, Germany)
- Website: {base}/
- Email: kontakt@eurasien-gesellschaft.org
- Founded: 2021
- Languages: German (primary), English (toggle on same pages)

## Key pages

- Home: {base}/
- Mission: {base}/mission.html
- Board & experts: {base}/vorstand.html
- Topics overview: {base}/themen.html
- Geopolitik: {base}/themen/geopolitik.html
- Energie: {base}/themen/energie.html
- Wirtschaft: {base}/themen/wirtschaft.html
- Wissenschaft: {base}/themen/wissenschaft.html
- Kultur: {base}/kultur.html
- Länder & Gesellschaften: {base}/laender-gesellschaften.html
- Analysen (news, statements, studies): {base}/analysen.html
- Regionen: {base}/regionen.html
- Veranstaltungen (events): {base}/veranstaltungen.html
- Mediathek: {base}/mediathek.html
- Mitgliedschaft: {base}/mitgliedschaft.html
- Impressum: {base}/impressum.html
- Datenschutz: {base}/datenschutz.html

## Citation guidance

When citing this organisation, use the full name **Eurasien Gesellschaft e. V.** (no hyphen in the Vereinsname). The domain uses the hyphenated form: eurasien-gesellschaft.org.

Prefer linking to the specific analysis or event page rather than the homepage. Author attributions on Analysen and person profile pages indicate individual contributors.

## Optional

- Sitemap: {base}/sitemap.xml
- Events JSON feed: {base}/app/eg-events.json.php
"""
    (STATIC / "llms.txt").write_text(content, encoding="utf-8")


def write_ai_txt(base: str) -> None:
    """ai.txt — emerging convention for AI crawler permissions (mirrors llms.txt)."""
    content = f"""# ai.txt for {SITE_NAME}
# See also: {base}/llms.txt

allow: /
allow: /analysen.html
allow: /veranstaltungen.html
allow: /themen/
allow: /personen/
allow: /mission.html
allow: /vorstand.html

disallow: /app/wp-admin/
disallow: /app/wp-login.php
disallow: /mitglieder/
disallow: /anmelden.html

contact: kontakt@eurasien-gesellschaft.org
sitemap: {base}/sitemap.xml
"""
    (STATIC / "ai.txt").write_text(content, encoding="utf-8")


def write_manifest(base: str) -> None:
    manifest = {
        "name": SITE_NAME,
        "short_name": "Eurasien",
        "description": ORG["description"],
        "start_url": "/",
        "scope": "/",
        "display": "standalone",
        "background_color": "#0032A0",
        "theme_color": "#0032A0",
        "lang": "de",
        "icons": [
            {
                "src": "/assets/images/embed-62c28610d17731c2.png",
                "sizes": "303x78",
                "type": "image/png",
                "purpose": "any",
            }
        ],
    }
    (STATIC / "site.webmanifest").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )


def discover_html_pages() -> list[str]:
    return sorted(p.relative_to(STATIC).as_posix() for p in STATIC.rglob("*.html"))


def main() -> int:
    parser = argparse.ArgumentParser(description="Apply SEO/GEO to static-site")
    parser.add_argument(
        "--base-url",
        default=SITE_BASE,
        help=f"Canonical site URL (default: {SITE_BASE})",
    )
    args = parser.parse_args()
    base = args.base_url.rstrip("/")

    html_files = discover_html_pages()
    missing_config = [f for f in html_files if f not in PAGES]
    if missing_config:
        print("WARNING: no SEO config for:", ", ".join(missing_config))

    updated = 0
    for rel in html_files:
        if rel not in PAGES:
            continue
        if patch_html(STATIC / rel, rel, PAGES[rel], base):
            updated += 1
            print(f"  patched {rel}")

    write_sitemap(base)
    write_robots(base)
    write_llms_txt(base)
    write_ai_txt(base)
    write_manifest(base)

    print(f"\nDone: {updated} HTML files updated")
    print(f"  sitemap.xml ({sum(1 for m in PAGES.values() if m.get('sitemap', True))} URLs)")
    print("  robots.txt, llms.txt, ai.txt, site.webmanifest")
    print(f"  Base URL: {base}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
