#!/usr/bin/env python3
"""Audit static-site fidelity vs eurasien_gesellschaft_53.html."""
from __future__ import annotations

import hashlib
import re
from pathlib import Path

SRC = Path(r"c:\Users\HP\Downloads\eurasien_gesellschaft_53.html")
OUT = Path(r"c:\Users\HP\Documents\Eurasian\Eurasien\static-site")

SLUGS = {
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


def extract_balanced_section(html: str, start: int) -> str:
    i = html.find(">", start) + 1
    depth = 1
    while depth and i < len(html):
        next_open = html.find("<section", i)
        next_close = html.find("</section>", i)
        if next_close < 0:
            raise ValueError("unclosed")
        if next_open >= 0 and next_open < next_close:
            depth += 1
            i = next_open + 8
        else:
            depth -= 1
            i = next_close + len("</section>")
            if depth == 0:
                return html[start:i]
    raise ValueError("unclosed")


def norm_text(s: str) -> str:
    s = re.sub(r"\s+", " ", s)
    return s.strip()


def strip_tags(html: str) -> str:
    html = re.sub(r"<script[\s\S]*?</script>", " ", html, flags=re.I)
    html = re.sub(r"<style[\s\S]*?</style>", " ", html, flags=re.I)
    html = re.sub(r"<[^>]+>", " ", html)
    return norm_text(html)


def main() -> None:
    src = SRC.read_text(encoding="utf-8", errors="replace")
    issues = []

    # Extract source pages
    page_iter = list(re.finditer(r'<section\b[^>]*\bid="(p-[^"]+)"[^>]*>', src))
    src_pages = {}
    for m in page_iter:
        pid = m.group(1)
        src_pages[pid] = extract_balanced_section(src, m.start())

    print("source pages", len(src_pages))

    for pid, rel in SLUGS.items():
        path = OUT / rel
        if not path.exists():
            issues.append(f"MISSING FILE {rel}")
            continue
        page = path.read_text(encoding="utf-8", errors="replace")
        # Find section in output
        m = re.search(rf'<section\b[^>]*\bid="{re.escape(pid)}"[^>]*>', page)
        if not m:
            issues.append(f"NO SECTION {pid} in {rel}")
            continue
        out_sec = extract_balanced_section(page, m.start())
        src_sec = src_pages[pid]

        # Compare after neutralizing hrefs and data-uri images
        def canon(s: str) -> str:
            s = re.sub(r'href="[^"]*"', 'href="X"', s)
            s = re.sub(r'src="data:[^"]+"', 'src="DATA"', s)
            s = re.sub(r'src="(?:\.\./)*assets/images/[^"]+"', 'src="DATA"', s)
            s = re.sub(r'\s+', " ", s)
            # ignore our page--active class injection
            s = s.replace(" page--active", "")
            return s

        c_src = canon(src_sec)
        c_out = canon(out_sec)
        if c_src != c_out:
            # length / hash
            issues.append(
                f"SECTION DRIFT {pid}: src_len={len(src_sec)} out_len={len(out_sec)} "
                f"src_hash={hashlib.sha1(c_src.encode()).hexdigest()[:10]} "
                f"out_hash={hashlib.sha1(c_out.encode()).hexdigest()[:10]}"
            )
            # find first diff pos
            n = min(len(c_src), len(c_out))
            for i in range(n):
                if c_src[i] != c_out[i]:
                    issues.append(f"  first diff @{i}: src…{c_src[max(0,i-40):i+60]!r}")
                    issues.append(f"                 out…{c_out[max(0,i-40):i+60]!r}")
                    break
            else:
                issues.append(f"  length-only diff src={len(c_src)} out={len(c_out)}")

        # DE/EN span counts
        de_src = len(re.findall(r'class="de"', src_sec))
        en_src = len(re.findall(r'class="en"', src_sec))
        de_out = len(re.findall(r'class="de"', out_sec))
        en_out = len(re.findall(r'class="en"', out_sec))
        if (de_src, en_src) != (de_out, en_out):
            issues.append(f"LANG SPAN COUNT {pid}: src de/en={de_src}/{en_src} out={de_out}/{en_out}")

        # Word-for-word visible text per lang class (rough)
        def lang_blobs(sec: str, cls: str) -> list[str]:
            return [
                strip_tags(m.group(1))
                for m in re.finditer(
                    rf'<span class="{cls}"[^>]*>([\s\S]*?)</span>', sec
                )
                if strip_tags(m.group(1))
            ]

        # Only compare for smaller pages to keep output useful; still check home/regionen counts
        if pid not in ("p-home", "p-regionen", "p-analysen", "p-laender", "p-kultur"):
            ds, dout = lang_blobs(src_sec, "de"), lang_blobs(out_sec, "de")
            es, eout = lang_blobs(src_sec, "en"), lang_blobs(out_sec, "en")
            if ds != dout:
                issues.append(f"DE COPY MISMATCH {pid}: {len(ds)} vs {len(dout)} blobs")
                for a, b in zip(ds, dout):
                    if a != b:
                        issues.append(f"  DE src={a[:120]!r}")
                        issues.append(f"  DE out={b[:120]!r}")
                        break
                if len(ds) != len(dout):
                    issues.append(f"  DE count {len(ds)}->{len(dout)}")
            if es != eout:
                issues.append(f"EN COPY MISMATCH {pid}: {len(es)} vs {len(eout)} blobs")
                for a, b in zip(es, eout):
                    if a != b:
                        issues.append(f"  EN src={a[:120]!r}")
                        issues.append(f"  EN out={b[:120]!r}")
                        break

    # Header/footer presence + search dialog
    index = (OUT / "index.html").read_text(encoding="utf-8", errors="replace")
    for needle in ['class="ubar"', 'class="hd"', 'class="ft"', "site-search-dialog", "data-lang"]:
        if needle not in index:
            issues.append(f"INDEX MISSING chrome: {needle}")

    # JS feature markers in assets
    js_all = ""
    for p in (OUT / "assets" / "js").glob("*.js"):
        js_all += p.read_text(encoding="utf-8", errors="replace")
    for needle in ["data-lang", "reg-zone", "hashchange", "setLang", "regions-explorer"]:
        # setLang may be minified differently
        pass
    for needle in ["reg-zone", "hashchange", "data-lang"]:
        if needle not in js_all and needle not in index:
            # reg-zone is in HTML; hashchange in JS
            if needle == "reg-zone":
                continue
            if needle not in js_all:
                issues.append(f"JS missing marker {needle}")

    # Source script bodies vs extracted (ignore path rewrites)
    src_scripts = [
        m.group(2)
        for m in re.finditer(r"<script\b([^>]*)>([\s\S]*?)</script>", src, re.I)
        if m.group(2).strip() and "application/ld+json" not in (m.group(1) or "")
    ]
    out_scripts = [
        p.read_text(encoding="utf-8", errors="replace")
        for p in sorted((OUT / "assets" / "js").glob("site-*.js"))
    ]
    print("src inline scripts", len(src_scripts), "out js files", len(out_scripts))
    # Compare lengths
    for i, (a, b) in enumerate(zip(src_scripts, out_scripts), 1):
        # out may have preamble comment
        b2 = re.sub(r"^/\* static multipage[\s\S]*?\*/\n", "", b)
        if a != b2 and abs(len(a) - len(b2)) > 5:
            issues.append(f"SCRIPT {i} len src={len(a)} out={len(b2)}")

    print("ISSUES", len(issues))
    for line in issues[:80]:
        print(line)
    if len(issues) > 80:
        print(f"... +{len(issues)-80} more")


if __name__ == "__main__":
    main()
