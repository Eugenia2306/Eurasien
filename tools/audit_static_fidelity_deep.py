#!/usr/bin/env python3
"""Deep fidelity check: chrome + all DE/EN blobs vs source."""
from __future__ import annotations

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


def lang_texts(html: str, cls: str) -> list[str]:
    out = []
    for m in re.finditer(rf'<span class="{cls}"([^>]*)>([\s\S]*?)</span>', html):
        inner = m.group(2)
        # unwrap nested only if needed; compare raw inner HTML for word-for-word
        out.append(inner)
    return out


def canon_links(s: str) -> str:
    s = re.sub(r'href="[^"]*"', 'href="X"', s)
    s = re.sub(r'src="data:[^"]+"', 'src="IMG"', s)
    s = re.sub(r'src="(?:\.\./)*assets/images/[^"]+"', 'src="IMG"', s)
    return s


def main() -> None:
    src = SRC.read_text(encoding="utf-8", errors="replace")
    issues = []

    # Chrome: ubar through </header>, and footer block
    body_open = src.find(">", src.lower().find("<body")) + 1
    main_start = src.find("<main")
    main_close = src.find("</main>")
    src_pre = src[body_open:main_start]
    # footer: from <footer or class="ft" container through end before scripts
    foot_m = re.search(r"<footer\b[\s\S]*?</footer>", src[main_close:], re.I)
    if not foot_m:
        # div.ft
        ft = src.find('class="ft"', main_close)
        # take a chunk - find search dialog end already in post
        src_post = src[main_close + len("</main>") : src.lower().rfind("</body>")]
        src_post = re.sub(r"<script\b[^>]*>[\s\S]*?</script>", "", src_post, flags=re.I)
    else:
        src_post = src[main_close + len("</main>") : main_close + len("</main>") + foot_m.end()]
        # include search dialog before footer
        src_post = src[main_close + len("</main>") : main_close + len("</main>") + foot_m.end()]

    index = (OUT / "index.html").read_text(encoding="utf-8", errors="replace")
    # extract pre-main from index (after shim script)
    imain = index.find("<main")
    # find body content after shim
    ibody = index.find("<body")
    ibody_end = index.find(">", ibody) + 1
    # skip shim script
    rest = index[ibody_end:imain]
    rest = re.sub(r"^[\s\S]*?</script>", "", rest, count=1).lstrip("\n") if rest.lstrip().startswith("<script>") else rest

    if canon_links(src_pre) != canon_links(rest):
        a, b = canon_links(src_pre), canon_links(rest)
        issues.append(f"CHROME PRE drift src={len(a)} out={len(b)}")
        for i in range(min(len(a), len(b))):
            if a[i] != b[i]:
                issues.append(f"  @{i} src={a[i:i+80]!r}")
                issues.append(f"  @{i} out={b[i:i+80]!r}")
                break

    # Compare all page DE/EN inners
    for m in re.finditer(r'<section\b[^>]*\bid="(p-[^"]+)"[^>]*>', src):
        pid = m.group(1)
        src_sec = extract_balanced_section(src, m.start())
        rel = SLUGS[pid]
        page = (OUT / rel).read_text(encoding="utf-8", errors="replace")
        om = re.search(rf'<section\b[^>]*\bid="{re.escape(pid)}"[^>]*>', page)
        out_sec = extract_balanced_section(page, om.start())
        for cls in ("de", "en"):
            s_list = lang_texts(src_sec, cls)
            o_list = lang_texts(out_sec, cls)
            if s_list != o_list:
                issues.append(f"{cls.upper()} mismatch {pid}: {len(s_list)} vs {len(o_list)}")
                for j, (x, y) in enumerate(zip(s_list, o_list)):
                    if x != y:
                        issues.append(f"  #{j} src={x[:100]!r}")
                        issues.append(f"  #{j} out={y[:100]!r}")
                        break
                if len(s_list) != len(o_list):
                    issues.append(f"  count {len(s_list)} -> {len(o_list)}")

        # Feature markers on regionen / membership
        if pid == "p-regionen":
            for needle, label in [
                ("reg-zone", "reg-zone"),
                ("regions-explorer", "explorer"),
                ('class="wm"', "map svg class"),
            ]:
                if src_sec.count(needle) != out_sec.count(needle):
                    issues.append(
                        f"marker {label} {pid}: {src_sec.count(needle)} vs {out_sec.count(needle)}"
                    )
        if pid == "p-mitgliedschaft":
            for needle in ["mtab", "data-mtab", "joincard", "data-plan"]:
                if src_sec.count(needle) != out_sec.count(needle):
                    issues.append(f"marker {needle} {pid}: {src_sec.count(needle)} vs {out_sec.count(needle)}")

    # Chrome DE/EN
    for cls in ("de", "en"):
        if lang_texts(src_pre, cls) != lang_texts(rest, cls):
            issues.append(f"CHROME {cls.upper()} copy mismatch")

    print("DEEP ISSUES", len(issues))
    for line in issues[:60]:
        print(line)


if __name__ == "__main__":
    main()
