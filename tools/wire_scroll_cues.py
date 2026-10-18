"""Wire scroll-cues.js + bump main.css cache on all static HTML pages."""
from __future__ import annotations

import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
STATIC = ROOT / "static-site"
THEME = ROOT / "wp-theme" / "eurasien-gesellschaft"

shutil.copy2(STATIC / "assets/css/main.css", THEME / "assets/css/main.css")
shutil.copy2(STATIC / "assets/js/scroll-cues.js", THEME / "assets/js/scroll-cues.js")

wired = 0
bumped = 0
for path in STATIC.rglob("*.html"):
    text = path.read_text(encoding="utf-8")
    original = text
    rel = path.relative_to(STATIC).as_posix()
    depth = rel.count("/")
    if depth == 0:
        prefix = "assets/js/"
    elif depth == 1:
        prefix = "../assets/js/"
    else:
        prefix = "../../assets/js/"

    text2 = (
        text.replace("main.css?v=pmob4", "main.css?v=pscroll1")
        .replace("main.css?v=pmob3", "main.css?v=pscroll1")
        .replace("main.css?v=pevfix1", "main.css?v=pscroll1")
    )
    if text2 != text:
        bumped += 1
        text = text2

    if "scroll-cues.js" not in text:
        tag = f'<script src="{prefix}scroll-cues.js?v=pscroll1"></script>'
        m = re.search(
            rf'<script[^>]*src="{re.escape(prefix)}mobile-nav\.js[^"]*"[^>]*>\s*</script>',
            text,
        )
        if m:
            text = text[: m.end()] + "\n" + tag + text[m.end() :]
        else:
            text = text.replace("</body>", tag + "\n</body>")
        wired += 1

    if text != original:
        path.write_text(text, encoding="utf-8")

print(f"css_bumped={bumped} js_wired={wired}")
print("index_ok", "scroll-cues.js" in (STATIC / "index.html").read_text(encoding="utf-8"))
print("index_css", "pscroll1" in (STATIC / "index.html").read_text(encoding="utf-8"))
