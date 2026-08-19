# -*- coding: utf-8 -*-
"""Trace div/form nesting around membership panels."""
from pathlib import Path
from html.parser import HTMLParser

html = Path(r"c:/Users/HP/Documents/Eurasian/Eurasien/static-site/mitgliedschaft.html").read_text(encoding="utf-8")
# only the mtabwrap section
start = html.find('class="mtabwrap"')
end = html.find('class="mfees"')
section = html[start:end]
print("section_len", len(section))

class Trace(HTMLParser):
    def __init__(self):
        super().__init__()
        self.stack = []
        self.events = []
    def handle_starttag(self, tag, attrs):
        ad = dict(attrs)
        interesting = tag in ("div", "form", "button", "section") and (
            "mpanel" in (ad.get("class") or "")
            or "mtab" in (ad.get("class") or "")
            or "mgrid" in (ad.get("class") or "")
            or "formcard" in (ad.get("class") or "")
            or "joincard" in (ad.get("class") or "")
            or ad.get("id", "").startswith("eg-form")
            or ad.get("id", "").startswith("mpanel")
            or ad.get("id", "").startswith("mtab")
            or "vm-pdf" in (ad.get("class") or "")
            or "regstep" in (ad.get("class") or "")
        )
        if tag not in ("input", "img", "br", "hr", "meta", "link", "source", "col", "area", "embed", "wbr"):
            self.stack.append((tag, ad.get("id") or ad.get("class") or "", ad.get("data-mpanel"), "hidden" in ad))
        if interesting:
            depth = len(self.stack)
            self.events.append(("OPEN", depth, tag, ad.get("id"), ad.get("class"), ad.get("data-mpanel"), "hidden" in ad))
    def handle_endtag(self, tag):
        if tag in ("input", "img", "br", "hr", "meta", "link", "source", "col", "area", "embed", "wbr"):
            return
        # pop matching
        for i in range(len(self.stack) - 1, -1, -1):
            if self.stack[i][0] == tag:
                closed = self.stack[i]
                del self.stack[i:]
                if closed[1] or closed[2]:
                    self.events.append(("CLOSE", len(self.stack), tag, closed[1], closed[2]))
                break

t = Trace()
t.feed(section)
for e in t.events:
    print(e)
print("final_stack", t.stack[-8:])
