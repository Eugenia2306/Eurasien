import re
import urllib.request

pages = [
    "index.html",
    "vorstand.html",
    "veranstaltungen.html",
    "mitgliedschaft.html",
    "mission.html",
    "themen.html",
    "analysen.html",
    "partner.html",
    "regionen.html",
    "kultur.html",
]
for p in pages:
    try:
        html = urllib.request.urlopen("https://eurasia.uwzghana.com/" + p, timeout=25).read().decode(
            "utf-8", "replace"
        )
    except Exception as e:
        print(p, "FAIL", e)
        continue
    m = re.search(r'class="lang"[^>]*>(.*?)</div>', html, re.S)
    snippet = re.sub(r"\s+", " ", m.group(0) if m else "NO LANG")[:400]
    print("---", p)
    print(snippet)
    # count de/en spans in main content roughly
    de = len(re.findall(r'class="de"', html))
    en = len(re.findall(r'class="en"', html))
    print("spans de/en", de, en)
