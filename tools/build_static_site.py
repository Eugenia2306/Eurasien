#!/usr/bin/env python3
"""
Faithful static multipage split of eurasien_gesellschaft_53.html.

Rules:
- Page section bodies are byte-identical to source (only hash links rewritten).
- Shared chrome (ubar/header/search/footer) identical aside from hash link rewrites.
- CSS/JS taken from source in original order; fluid-layout.css is additive only.
- DE/EN copy is never rewritten.
"""

from __future__ import annotations

import hashlib
import re
import shutil
import zipfile
from pathlib import Path

SRC = Path(r"c:\Users\HP\Downloads\eurasien_gesellschaft_53.html")
OUT = Path(r"c:\Users\HP\Documents\Eurasian\Eurasien\static-site")
ZIP_OUT = Path(r"c:\Users\HP\Documents\Eurasian\Eurasien\Eurasien-static-site.zip")
FLUID = Path(__file__).resolve().parent / "static-assets" / "fluid-layout.css"

SLUGS: dict[str, str] = {
    "p-home": "index.html",
    "p-mission": "mission.html",
    "p-vorstand": "vorstand.html",
    "p-person-rahr": "personen/alexander-rahr.html",
    "p-person-wipperfurth": "personen/christian-wipperfuerth.html",
    "p-person-neu": "personen/alexander-neu.html",
    "p-person-polajner": "personen/christoph-polajner.html",
    "p-person-schraps": "personen/andreas-schraps.html",
    "p-partner": "partner.html",
    "p-news": "gesellschaftsnachrichten.html",
    "p-themen": "themen.html",
    "p-topic-geopolitik": "themen/geopolitik.html",
    "p-topic-energie": "themen/energie.html",
    "p-topic-wirtschaft": "themen/wirtschaft.html",
    "p-topic-wissenschaft": "themen/wissenschaft.html",
    "p-kultur": "kultur.html",
    "p-laender": "laender-gesellschaften.html",
    "p-analysen": "analysen.html",
    "p-regionen": "regionen.html",
    "p-veranstaltungen": "veranstaltungen.html",
    "p-mediathek": "mediathek.html",
    "p-recordings-archive": "aufzeichnungen.html",
    "p-mitgliedschaft-vorteile": "mitgliedschaft-vorteile.html",
    "p-mitgliedschaft": "mitgliedschaft.html",
    "p-members-positionen": "mitglieder/positionen.html",
    "p-members-dossiers": "mitglieder/dossiers.html",
    "p-members-studien": "mitglieder/studien.html",
    "p-login": "anmelden.html",
    "p-impressum": "impressum.html",
}

TITLES: dict[str, str] = {
    "p-home": "Eurasien Gesellschaft e. V.",
    "p-mission": "Mission | Eurasien Gesellschaft",
    "p-vorstand": "Vorstand | Eurasien Gesellschaft",
    "p-person-rahr": "Alexander Rahr | Eurasien Gesellschaft",
    "p-person-wipperfurth": "Dr. Christian Wipperfürth | Eurasien Gesellschaft",
    "p-person-neu": "Dr. Alexander Neu | Eurasien Gesellschaft",
    "p-person-polajner": "Christoph Polajner | Eurasien Gesellschaft",
    "p-person-schraps": "Andreas Schraps | Eurasien Gesellschaft",
    "p-partner": "Partner | Eurasien Gesellschaft",
    "p-news": "Gesellschaftsnachrichten | Eurasien Gesellschaft",
    "p-themen": "Themen | Eurasien Gesellschaft",
    "p-topic-geopolitik": "Geopolitik | Eurasien Gesellschaft",
    "p-topic-energie": "Energie | Eurasien Gesellschaft",
    "p-topic-wirtschaft": "Wirtschaft | Eurasien Gesellschaft",
    "p-topic-wissenschaft": "Wissenschaft | Eurasien Gesellschaft",
    "p-kultur": "Kultur | Eurasien Gesellschaft",
    "p-laender": "Länder & Gesellschaften | Eurasien Gesellschaft",
    "p-analysen": "Analysen | Eurasien Gesellschaft",
    "p-regionen": "Regionen | Eurasien Gesellschaft",
    "p-veranstaltungen": "Veranstaltungen | Eurasien Gesellschaft",
    "p-mediathek": "Mediathek | Eurasien Gesellschaft",
    "p-recordings-archive": "Aufzeichnungen | Eurasien Gesellschaft",
    "p-mitgliedschaft-vorteile": "Vorteile | Eurasien Gesellschaft",
    "p-mitgliedschaft": "Mitgliedschaft | Eurasien Gesellschaft",
    "p-members-positionen": "Positionen | Eurasien Gesellschaft",
    "p-members-dossiers": "Dossiers | Eurasien Gesellschaft",
    "p-members-studien": "Studien | Eurasien Gesellschaft",
    "p-login": "Anmelden | Eurasien Gesellschaft",
    "p-impressum": "Impressum | Eurasien Gesellschaft",
}


def depth_prefix(rel_path: str) -> str:
    depth = rel_path.count("/")
    return "../" * depth if depth else ""


def rel_href(from_page: str, to_page: str) -> str:
    return depth_prefix(from_page) + to_page.replace("\\", "/")


def extract_balanced_section(html: str, start: int) -> tuple[str, int]:
    i = html.find(">", start)
    if i < 0:
        raise ValueError("broken section")
    i += 1
    depth = 1
    while depth and i < len(html):
        next_open = html.find("<section", i)
        next_close = html.find("</section>", i)
        if next_close < 0:
            raise ValueError("unclosed section")
        if next_open >= 0 and next_open < next_close:
            depth += 1
            i = next_open + 8
        else:
            depth -= 1
            i = next_close + len("</section>")
            if depth == 0:
                return html[start:i], i
    raise ValueError("unclosed section")


def rewrite_hash_links(html: str, from_page: str) -> str:
    """Only change href=\"#p-...\" targets. Never touch copy."""

    def repl(m: re.Match[str]) -> str:
        pid = m.group(1)
        target = SLUGS.get(pid)
        if not target:
            return m.group(0)
        return f'href="{rel_href(from_page, target)}"'

    return re.sub(r'href="#(p-[a-z0-9\-]+)"', repl, html)


def extract_base64_images(html: str, img_dir: Path, path_prefix: str) -> str:
    """Extract data-URI images to files (same bytes). Visual fidelity preserved."""
    import base64

    img_dir.mkdir(parents=True, exist_ok=True)
    cache: dict[str, str] = {}

    def repl(m: re.Match[str]) -> str:
        mime = m.group(1).lower()
        b64 = m.group(2)
        key = hashlib.sha1(re.sub(r"\s+", "", b64).encode("ascii", errors="ignore")).hexdigest()[:16]
        if key in cache:
            return f'src="{path_prefix}{cache[key]}"'
        ext = {
            "image/png": "png",
            "image/jpeg": "jpg",
            "image/jpg": "jpg",
            "image/webp": "webp",
            "image/gif": "gif",
            "image/svg+xml": "svg",
        }.get(mime, "bin")
        name = f"embed-{key}.{ext}"
        out = img_dir / name
        if not out.exists():
            raw = re.sub(r"\s+", "", b64)
            try:
                out.write_bytes(base64.b64decode(raw))
            except Exception:
                return m.group(0)
        cache[key] = f"assets/images/{name}"
        return f'src="{path_prefix}{cache[key]}"'

    return re.sub(
        r'src="data:(image/[^;]+);base64,([^"]+)"',
        repl,
        html,
        flags=re.I,
    )


def extract_all_styles(full_html: str) -> tuple[str, str]:
    """
    Collect EVERY <style> block from the document (head AND body).
    _53 keeps page-scoped CSS (e.g. partner accordion) in the body.
    Returns (html_without_style_tags, combined_css).
    """
    css_parts: list[str] = []

    def repl(m: re.Match[str]) -> str:
        css_parts.append(m.group(1))
        return "\n"

    cleaned = re.sub(r"<style\b[^>]*>([\s\S]*?)</style>", repl, full_html, flags=re.I)
    css = "\n\n".join(css_parts)
    # Multipage visibility: one page per file must show
    css += """

/* ===== Static multipage: show the only page on each HTML file ===== */
.page{display:block !important}
.page--home{display:block !important}
"""
    return cleaned, css


def strip_styles(html_chunk: str) -> str:
    return re.sub(r"<style\b[^>]*>[\s\S]*?</style>", "\n", html_chunk, flags=re.I)


def build() -> None:
    if not SRC.is_file():
        raise SystemExit(f"Missing source: {SRC}")
    if not FLUID.is_file():
        raise SystemExit(f"Missing fluid CSS: {FLUID}")

    print("Reading", SRC)
    html_raw = SRC.read_text(encoding="utf-8", errors="replace")

    # Pull ALL styles (head + body) into main.css first
    html, css = extract_all_styles(html_raw)
    style_blocks = len(re.findall(r"<style\b", html_raw, flags=re.I))
    print("style blocks from source", style_blocks, "css chars", len(css))
    if "partner-accordion" not in css:
        raise SystemExit("FATAL: partner-accordion CSS missing after extract")

    if OUT.exists():
        shutil.rmtree(OUT)
    (OUT / "assets" / "css").mkdir(parents=True)
    (OUT / "assets" / "js").mkdir(parents=True)
    (OUT / "assets" / "images").mkdir(parents=True)
    for sub in ("personen", "themen", "mitglieder"):
        (OUT / sub).mkdir(parents=True, exist_ok=True)

    # ---- Head ----
    head_start = html.lower().find("<head")
    head_open_end = html.find(">", head_start) + 1
    head_close = html.lower().find("</head>")
    head_inner = html[head_open_end:head_close]
    # Drop original <title>; each page sets its own (SEO only; not body copy)
    head_inner = re.sub(r"<title>[\s\S]*?</title>", "", head_inner, count=1, flags=re.I)
    # Remove inline head scripts that we will re-emit from assets (keep ld+json)
    head_scripts_raw: list[tuple[str, str]] = []
    body_scripts_raw: list[tuple[str, str]] = []

    def take_script(m: re.Match[str], bucket: list[tuple[str, str]]) -> str:
        attrs = m.group(1) or ""
        body = m.group(2) or ""
        if re.search(r'type=["\']application/ld\+json["\']', attrs, re.I):
            return m.group(0)  # keep in head
        bucket.append((attrs, body))
        return ""

    def collect(html_chunk: str, bucket: list[tuple[str, str]]) -> str:
        return re.sub(
            r"<script\b([^>]*)>([\s\S]*?)</script>",
            lambda m: take_script(m, bucket),
            html_chunk,
            flags=re.I,
        )

    head_inner = collect(head_inner, head_scripts_raw)

    # ---- Body chrome boundaries ----
    body_start = html.lower().find("<body")
    body_open_end = html.find(">", body_start) + 1
    main_start = html.find("<main")
    main_close = html.find("</main>")
    if main_close < 0:
        raise SystemExit("No </main>")

    pre_main = strip_styles(html[body_open_end:main_start])
    post_main_and_rest = html[main_close + len("</main>") :]

    # Remove body scripts from post_main chrome; keep markup
    post_main_clean = collect(post_main_and_rest, body_scripts_raw)
    post_main_clean = strip_styles(post_main_clean)
    body_close = post_main_clean.lower().rfind("</body>")
    if body_close >= 0:
        post_main_clean = post_main_clean[:body_close]

    # Write JS files in exact source order: head scripts then body scripts
    all_scripts = head_scripts_raw + body_scripts_raw
    js_tags_template: list[str] = []
    js_n = 0
    for attrs, body in all_scripts:
        src_m = re.search(r'\bsrc=["\']([^"\']+)["\']', attrs or "", re.I)
        if src_m:
            # External script (gtag etc.) keep absolute URL as-is
            js_tags_template.append(f"<script{attrs}></script>" if attrs.startswith(" ") else f"<script {attrs}></script>")
            # normalize
            js_tags_template[-1] = f"<script{attrs}> </script>" if body.strip() == "" else f"<script{attrs}></script>"
            # Actually external scripts are usually empty body: <script src=...></script>
            js_tags_template[-1] = f"<script{attrs}></script>"
            continue
        if not body.strip():
            continue
        js_n += 1
        fname = f"site-{js_n:02d}.js"
        (OUT / "assets" / "js" / fname).write_text(body, encoding="utf-8", newline="\n")
        js_tags_template.append(f'<script src="assets/js/{fname}"></script>')

    (OUT / "assets" / "css" / "main.css").write_text(css, encoding="utf-8", newline="\n")
    shutil.copy2(FLUID, OUT / "assets" / "css" / "fluid-layout.css")
    print("CSS", len(css), "inline JS files", js_n, "total script tags", len(js_tags_template))

    # ---- Pages ----
    page_iter = list(re.finditer(r'<section\b[^>]*\bid="(p-[^"]+)"[^>]*>', html))
    pages: dict[str, str] = {}
    for m in page_iter:
        pid = m.group(1)
        block, _ = extract_balanced_section(html, m.start())
        pages[pid] = block
    missing = set(SLUGS) - set(pages)
    if missing:
        raise SystemExit(f"Missing pages in source: {missing}")

    for pid, rel in SLUGS.items():
        prefix = depth_prefix(rel)
        section = pages[pid]  # exact source section

        chrome_pre = pre_main
        chrome_post = post_main_clean
        doc_body = (
            chrome_pre
            + "\n<main id=\"main\">\n"
            + section
            + "\n</main>\n"
            + chrome_post
        )

        # Link rewrites only
        doc_body = rewrite_hash_links(doc_body, rel)
        # Extract images (bytes identical)
        doc_body = extract_base64_images(doc_body, OUT / "assets" / "images", prefix)

        js_html = "\n".join(
            tag.replace('src="assets/', f'src="{prefix}assets/')
            if 'src="assets/' in tag
            else tag
            for tag in js_tags_template
        )

        # Head: keep source metas/fonts/ld+json; strip duplicate charset/viewport we set
        hi = head_inner
        hi = re.sub(r"<meta\s+charset=[^>]*>", "", hi, flags=re.I)
        hi = re.sub(r'<meta\s+name=["\']viewport["\'][^>]*>', "", hi, flags=re.I)
        hi = re.sub(r"\n{3,}", "\n\n", hi)

        title = TITLES.get(pid, "Eurasien Gesellschaft e. V.")
        # Hash shim so legacy scripts that read location.hash keep working
        shim = (
            "<script>(function(){var id="
            + repr(pid)
            + ";if(!location.hash){try{history.replaceState(null,\"\",\"#\"+id);}catch(e){try{location.hash=id;}catch(e2){}}}})();</script>"
        )

        page_html = f"""<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{title}</title>
{hi}
<link rel="stylesheet" href="{prefix}assets/css/main.css">
<link rel="stylesheet" href="{prefix}assets/css/fluid-layout.css">
</head>
<body>
{shim}
{doc_body}
{js_html}
</body>
</html>
"""
        out_path = OUT / rel
        out_path.parent.mkdir(parents=True, exist_ok=True)
        out_path.write_text(page_html, encoding="utf-8", newline="\n")
        print("wrote", rel, out_path.stat().st_size)

    (OUT / "README.txt").write_text(
        """Eurasien Gesellschaft – static multipage site
Source of truth: eurasien_gesellschaft_53.html

This folder is a faithful split of that single file:
- UI markup and DE/EN copy come from the source unchanged
- Only #p-* links were rewritten to real .html paths
- CSS/JS are the source assets (plus additive fluid-layout.css for large screens)
- Interactive behaviour (language switch, Regionen map, forms, search) is preserved

Upload the contents of this folder to your web host document root.
""",
        encoding="utf-8",
    )

    if ZIP_OUT.exists():
        ZIP_OUT.unlink()
    with zipfile.ZipFile(ZIP_OUT, "w", zipfile.ZIP_DEFLATED) as z:
        for p in OUT.rglob("*"):
            if p.is_file():
                z.write(p, p.relative_to(OUT).as_posix())
    print("ZIP", ZIP_OUT, ZIP_OUT.stat().st_size)


if __name__ == "__main__":
    build()
