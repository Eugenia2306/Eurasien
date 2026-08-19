# -*- coding: utf-8 -*-
from pathlib import Path
import re
import urllib.request

local = Path(r"c:/Users/HP/Documents/Eurasian/Eurasien/static-site/mitgliedschaft.html").read_text(encoding="utf-8")
live = urllib.request.urlopen(
    "https://eurasia.uwzghana.com/mitgliedschaft.html?v=pmpro10&n=1", timeout=45
).read().decode("utf-8", "replace")

for label, html in (("LOCAL", local), ("LIVE", live)):
    print("====", label, "====")
    print("len", len(html))
    print("mtab-expert", 'id="mtab-expert"' in html)
    print("mpanel-expert", 'id="mpanel-expert"' in html)
    print("eg-form-reader", 'id="eg-form-reader"' in html)
    print("eg-form-expert", 'id="eg-form-expert"' in html)
    print("data-mpanel=expert", 'data-mpanel="expert"' in html)
    # expert panel hidden attr
    m = re.search(r'<div class="mpanel"[^>]*data-mpanel="expert"[^>]*>', html)
    print("expert_panel_tag", m.group(0) if m else None)
    # count forms
    print("form_count", len(re.findall(r"<form\b", html, re.I)))
    print("form_close", len(re.findall(r"</form>", html, re.I)))
    # Is expert form inside expert panel?
    exp = re.search(r'id="mpanel-expert"[\s\S]{0,500}', html)
    print("after_mpanel_expert", (exp.group(0)[:400] if exp else "MISSING"))
    # site-06 version
    print("site06", re.findall(r"site-06\.js\?v=[^\"]+", html))
    print("bridge", re.findall(r"app-bridge\.js\?v=[^\"]+", html))
    print()
