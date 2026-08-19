# -*- coding: utf-8 -*-
from pathlib import Path

p = Path(r"c:/Users/HP/Documents/Eurasian/Eurasien/static-site/mitgliedschaft.html")
t = p.read_text(encoding="utf-8")

# Broken: reader form closes, then mgrid__form closes, then expert panel opens
# INSIDE reader mpanel/mgrid. Need to close reader mgrid + mpanel before expert.
broken = '</form></div> <div class="mpanel" data-mpanel="expert"'
fixed = '</form></div></div></div> <div class="mpanel" data-mpanel="expert"'

# Also handle whitespace variants
import re
pattern = re.compile(
    r'</form>\s*</div>\s*<div class="mpanel" data-mpanel="expert"',
    re.I,
)
m = pattern.search(t)
if not m:
    raise SystemExit("pattern not found")
print("FOUND:", repr(m.group(0)[:80]))

# Count how many </div> we need: after form we have closed mgrid__form with one </div>
# Still open: mgrid, mpanel-reader. So add two </div>.
replacement = '</form></div></div></div> <div class="mpanel" data-mpanel="expert"'
t2, n = pattern.subn(replacement, t, count=1)
if n != 1:
    raise SystemExit(f"replace count {n}")

# Also close expert properly: end of expert should close mgrid__form, mgrid, mpanel, mtabwrap
# Trace said after expert: CLOSE mpanel-expert, CLOSE mgrid (reader's leftover), stack still has mpanel-reader
# After our fix, stack should be clean. May have EXTRA </div> at end that closed reader's mgrid
# Check trailing closes after expert panel

# Find mfees and look at what precedes it
idx = t2.find('class="mfees"')
print("BEFORE mfees:", repr(t2[idx-80:idx]))

# Bump cache
t2 = t2.replace("?v=pmpro10", "?v=pmpro11")
t2 = t2.replace("site-06.js?v=pmpro4", "site-06.js?v=pmpro11")
t2 = t2.replace("main.css?v=pmpro7", "main.css?v=pmpro11")
t2 = t2.replace("main.css?v=pmpro9", "main.css?v=pmpro11")
t2 = t2.replace("main.css?v=pmpro10", "main.css?v=pmpro11")

p.write_text(t2, encoding="utf-8")
print("written")

# Re-trace nesting briefly
from html.parser import HTMLParser
section = t2[t2.find('class="mtabwrap"'):t2.find('class="mfees"')]

class T(HTMLParser):
    def __init__(self):
        super().__init__(); self.stack=[]; self.bad=False
    def handle_starttag(self, tag, attrs):
        ad=dict(attrs)
        if tag in ("input","img","br","hr","meta","link"): return
        self.stack.append((tag, ad.get("id") or ad.get("class"), ad.get("data-mpanel")))
        if ad.get("id")=="mpanel-expert":
            parents=[x for x in self.stack if x[2]=="reader" or (x[1] and "mpanel-reader" in str(x[1]))]
            print("expert_open_depth", len(self.stack), "inside_reader", bool(parents), "stack_ids", [x[1] for x in self.stack[-6:]])
    def handle_endtag(self, tag):
        if tag in ("input","img","br","hr","meta","link"): return
        for i in range(len(self.stack)-1,-1,-1):
            if self.stack[i][0]==tag:
                del self.stack[i:]; break

tr=T(); tr.feed(section)
print("final_stack", [(a,b,c) for a,b,c in tr.stack if a in ("div","form")])
