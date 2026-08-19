# -*- coding: utf-8 -*-
from pathlib import Path
import re

html = Path(r"c:/Users/HP/Documents/Eurasian/Eurasien/static-site/mitgliedschaft.html").read_text(encoding="utf-8")

# Extract expert panel fully
m = re.search(r'<div class="mpanel" data-mpanel="expert"[\s\S]*?(?=<div class="mfees">)', html)
if not m:
    # try until mtabwrap end
    m = re.search(r'<div class="mpanel" data-mpanel="expert"[\s\S]*?</div>\s*</div>\s*</div>\s*</div>\s*</div>', html)
print("expert_chunk_found", bool(m))
chunk = m.group(0) if m else ""
print("expert_chunk_len", len(chunk))
print("has_eg-form-expert", 'id="eg-form-expert"' in chunk)
print("has_vm-first", 'id="vm-first"' in chunk)
print("has_submit", 'type="submit"' in chunk)
print("has_vm-pdf", "vm-pdf" in chunk)
print("open_divs", chunk.count("<div"), "close_divs", chunk.count("</div>"))
print("open_form", chunk.count("<form"), "close_form", chunk.count("</form>"))
print("open_fieldset", chunk.count("<fieldset"), "close_fieldset", chunk.count("</fieldset>"))

# Show start and end of expert form
fm = re.search(r'<form[^>]*id="eg-form-expert"[\s\S]*?</form>', html)
if fm:
    f = fm.group(0)
    print("expert_form_len", len(f))
    print("START", f[:300])
    print("END", f[-400:])
else:
    print("NO EXPERT FORM MATCH")
    # find form open and nearby
    i = html.find('id="eg-form-expert"')
    print(html[i:i+800])
    print("---")
    print(html[i+800:i+1600])

# Check CSS for mpanel
css = Path(r"c:/Users/HP/Documents/Eurasian/Eurasien/static-site/assets/css/main.css").read_text(encoding="utf-8")
for line in css.splitlines():
    if "mpanel" in line or "mtab" in line and "is-active" in line:
        if "mpanel" in line or ".mtab" in line:
            print("CSS:", line[:200])
