#!/usr/bin/env python3
"""Confirm word-for-word DE/EN text equality (ignoring href rewrites only)."""
from __future__ import annotations

import re
from pathlib import Path

SRC = Path(r"c:\Users\HP\Documents\Eurasian\Eurasien\eurasien_gesellschaft_54.html")
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


def visible(s: str) -> str:
    s = re.sub(r"<[^>]+>", "", s)
    s = re.sub(r"\s+", " ", s)
    return s.strip()


def lang_visible(html: str, cls: str) -> list[str]:
    return [
        visible(m.group(1))
        for m in re.finditer(rf'<span class="{cls}"[^>]*>([\s\S]*?)</span>', html)
    ]


def main() -> None:
    src = SRC.read_text(encoding="utf-8", errors="replace")
    bad = 0
    checked = 0
    for m in re.finditer(r'<section\b[^>]*\bid="(p-[^"]+)"[^>]*>', src):
        pid = m.group(1)
        src_sec = extract_balanced_section(src, m.start())
        page = (OUT / SLUGS[pid]).read_text(encoding="utf-8", errors="replace")
        om = re.search(rf'<section\b[^>]*\bid="{re.escape(pid)}"[^>]*>', page)
        out_sec = extract_balanced_section(page, om.start())
        for cls in ("de", "en"):
            a = lang_visible(src_sec, cls)
            b = lang_visible(out_sec, cls)
            checked += 1
            if a != b:
                bad += 1
                print("FAIL", cls, pid)
                for i, (x, y) in enumerate(zip(a, b)):
                    if x != y:
                        print(" ", i, x[:160])
                        print(" ", i, y[:160])
                        break
                if len(a) != len(b):
                    print("  counts", len(a), len(b))
    print("checked_lang_lists", checked, "failures", bad)

    # markers
    r = (OUT / "regionen.html").read_text(encoding="utf-8", errors="replace")
    print("reg-zone", r.count('class="reg-zone"'))
    print("data-lang buttons", (OUT / "index.html").read_text(encoding="utf-8").count("data-lang"))


if __name__ == "__main__":
    main()
