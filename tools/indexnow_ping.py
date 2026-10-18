"""
Tell Bing (and the other IndexNow search engines) that pages changed, so they
re-crawl them now instead of waiting.

The key file static-site/<key>.txt must be live at https://eurasiengesellschaft.de/<key>.txt.

    python tools/indexnow_ping.py                                   # every URL in sitemap.xml
    python tools/indexnow_ping.py impressum.html veranstaltungen.html
    python tools/indexnow_ping.py --dry-run https://eurasiengesellschaft.de/

(Git Bash rewrites a bare "/" into a Windows path, so pass page names or full URLs.)

Run it after an upload, with the pages that changed.
"""
import json
import re
import sys
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent / "static-site"
HOST = "eurasiengesellschaft.de"
SITE = "https://" + HOST
ENDPOINT = "https://www.bing.com/indexnow"


def find_key():
    keys = [p for p in ROOT.glob("*.txt") if re.fullmatch(r"[0-9a-f]{32}\.txt", p.name)]
    if len(keys) != 1:
        sys.exit("expected exactly one IndexNow key file in static-site/, found %d" % len(keys))
    key = keys[0].read_text(encoding="utf-8").strip()
    if key != keys[0].stem:
        sys.exit("key file content does not match its name")
    return key


def sitemap_urls():
    return re.findall(r"<loc>([^<]+)</loc>", (ROOT / "sitemap.xml").read_text(encoding="utf-8"))


def main():
    args = [a for a in sys.argv[1:] if a != "--dry-run"]
    dry = "--dry-run" in sys.argv
    key = find_key()
    urls = [a if a.startswith("http") else SITE + "/" + a.lstrip("/") for a in args] or sitemap_urls()
    if any(not u.startswith(SITE + "/") for u in urls):
        sys.exit("all URLs must be on " + SITE)
    body = {"host": HOST, "key": key, "keyLocation": "%s/%s.txt" % (SITE, key), "urlList": urls}
    print("%d URL(s):" % len(urls), *urls, sep="\n  ")
    if dry:
        return
    req = urllib.request.Request(ENDPOINT, data=json.dumps(body).encode("utf-8"), method="POST",
                                 headers={"Content-Type": "application/json; charset=utf-8"})
    try:
        r = urllib.request.urlopen(req, timeout=30)
        print("IndexNow answered HTTP %d (200 = accepted, 202 = accepted, key check pending)" % r.status)
    except urllib.error.HTTPError as e:
        print("IndexNow answered HTTP %d: %s" % (e.code, e.read().decode("utf-8", "replace")[:300]))
        sys.exit(1)


if __name__ == "__main__":
    main()
