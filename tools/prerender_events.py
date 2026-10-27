"""
Pre-render the events into the static pages so search engines and AI crawlers
that do not run JavaScript can read them.

Writes, between markers, into files under static-site/:
  - veranstaltungen.html : full event list (same markup as events-feed.js) + Event JSON-LD
  - index.html           : the three-card "Termine" preview
  - llms.txt             : the events section

events-feed.js still loads the live WordPress feed in the browser and replaces the
pre-rendered markup, so visitors always see current data. Re-run this after adding
or changing events in WordPress:

    python tools/prerender_events.py                  # uses the live feed
    python tools/prerender_events.py --feed feed.json # uses a saved feed

Only touches the marked regions; it is NOT build_static_site.py.
"""
import argparse
import datetime as dt
import html
import json
import re
import sys
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent / "static-site"
SITE = "https://eurasiengesellschaft.de"
FEED_URL = SITE + "/app/eg-events.json.php"
ORG_ID = SITE + "/#organization"
BOARD = {  # speaker-name pattern -> person @id on the profile pages
    r"\bRahr\b": "alexander-rahr",
    r"\bNeu\b": "alexander-neu",
    r"\bPolajner\b": "christoph-polajner",
    r"\bWipperf(?:ü|ue)rth\b": "christian-wipperfuerth",
    r"\bSchraps\b": "andreas-schraps",
}
IN_GERMANY = re.compile(r"Berlin|Köln|Hamburg|München|Deutschland|Logenhaus", re.I)

MD = ["Jan", "Feb", "Mär", "Apr", "Mai", "Jun", "Jul", "Aug", "Sep", "Okt", "Nov", "Dez"]
MD_FULL = ["Januar", "Februar", "März", "April", "Mai", "Juni", "Juli", "August", "September", "Oktober", "November", "Dezember"]
ME = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"]
WD_DE = ["Sonntag", "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag"]
WD_EN = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"]


# ---------- port of events-feed.js rendering ----------

def esc(s):
    s = "" if s is None else str(s)
    return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace('"', "&quot;")


def bi(de, en):
    de = de or ""
    en = en or de
    return '<span class="de">' + esc(de) + '</span><span class="en" hidden>' + esc(en) + "</span>"


def bi_html(de, en):
    de = de or ""
    en = en or de
    return '<div class="de">' + de + '</div><div class="en" hidden>' + en + "</div>"


def parse_ymd(date):
    p = str(date or "").split("-")
    if len(p) < 3:
        return None
    try:
        return int(p[0]), int(p[1]), int(p[2][:2])
    except ValueError:
        return None


def js_weekday(y, m, d):
    return (dt.date(y, m, d).weekday() + 1) % 7  # JS getUTCDay(): Sunday = 0


def format_when(ev):
    p = parse_ymd(ev.get("date"))
    if not p:
        return "", ""
    y, m, d = p
    wd = js_weekday(y, m, d)
    if ev.get("time_tba") and ev.get("status") != "upcoming":
        # past event without a known time: date only
        return ("%s, %d. %s %d" % (WD_DE[wd], d, MD_FULL[m - 1], y),
                "%s, %d %s %d" % (WD_EN[wd], d, ME[m - 1], y))
    if ev.get("time_tba"):
        return ("%s, %d. %s %d · Uhrzeit folgt" % (WD_DE[wd], d, MD_FULL[m - 1], y),
                "%s, %d %s %d · Time to be announced" % (WD_EN[wd], d, ME[m - 1], y))
    t = (ev.get("time_start") or "19:00")[:5]
    th = int(t.split(":")[0])
    tm = t.split(":")[1] if ":" in t else "00"
    if th == 0:
        ampm = "12:%s am" % tm
    elif th < 12:
        ampm = "%d:%s am" % (th, tm)
    elif th == 12:
        ampm = "12:%s pm" % tm
    else:
        ampm = "%d:%s pm" % (th - 12, tm)
    return ("%s, %d. %s %d · %s Uhr" % (WD_DE[wd], d, MD_FULL[m - 1], y, t),
            "%s, %d %s %d · %s" % (WD_EN[wd], d, ME[m - 1], y, ampm))


def safe_url(u):
    return u if isinstance(u, str) and re.match(r"^https?://", u, re.I) else ""


def num(v):
    if v is None:
        return "0"
    if isinstance(v, float) and v.is_integer():
        return str(int(v))
    return str(v)


def render_card(ev):
    p = parse_ymd(ev.get("date"))
    when_de, when_en = format_when(ev)
    status = "upcoming" if ev.get("status") == "upcoming" else "past"
    badge_class = "badge badge--up" if status == "upcoming" else "badge badge--past"
    attrs = " ".join([
        'class="ev"',
        'id="%s"' % esc(ev.get("id")),
        'data-date="%s"' % esc(ev.get("date")),
        'data-status="%s"' % status,
        'data-type="%s"' % esc(ev.get("type") or "lecture"),
        'data-price="%s"' % esc(num(ev.get("price"))),
        'data-time-start="%s"' % esc(ev.get("time_start") or "19:00"),
        'data-time-end="%s"' % esc(ev.get("time_end") or "21:00"),
        'data-location="%s"' % esc(ev.get("location") or ""),
        'data-bookable="%s"' % ("1" if ev.get("bookable") else "0"),
    ])
    meta = ""
    if ev.get("location") or ev.get("location_en"):
        meta += "<span>" + bi(ev.get("location"), ev.get("location_en")) + "</span>"
    if when_de:
        meta += "<span>" + bi(when_de, when_en) + "</span>"
    if ev.get("speaker") or ev.get("speaker_en"):
        meta += "<span>" + bi(ev.get("speaker"), ev.get("speaker_en")) + "</span>"
    more = ""
    if ev.get("body_html") or ev.get("body_html_en"):
        lab = bi("Rückblick", "Review") if status == "past" else bi("Beschreibung und Veranstaltungsdetails", "Description and event details")
        more = ('<details class="ev-more"><summary class="ev-more__summary"><span class="ev-more__summary-lab">' + lab +
                '</span><span class="ev-more__chev" aria-hidden="true"></span></summary>'
                '<div class="ev-more__body"><div class="ev-more__content">' +
                bi_html(ev.get("body_html") or "", ev.get("body_html_en") or "") + "</div></div></details>")
    links = ""
    if status == "past":
        rep, rec = safe_url(ev.get("report_url")), safe_url(ev.get("recording_url"))
        if rep:
            links += '<a class="link-more ext small" href="' + esc(rep) + '" target="_blank" rel="noopener">' + bi("Zum Bericht", "Read the report") + "</a>"
        if rec:
            links += '<a class="link-more ext small" href="' + esc(rec) + '" target="_blank" rel="noopener">' + bi("Aufzeichnung ansehen", "Watch the recording") + "</a>"
        if links:
            links = '<div class="ev__links" style="display:flex;flex-wrap:wrap;gap:6px 18px;margin-top:8px">' + links + "</div>"
    return ("<div " + attrs + ">" +
            '<div class="ev__date"><div class="ev__d">' + ("%02d" % p[2] if p else "") +
            '</div><div class="ev__m">' + esc(MD[p[1] - 1] if p else "") +
            '</div><div class="ev__y">' + esc(p[0] if p else "") + "</div></div>" +
            '<div><h3 class="ev__t">' + bi(ev.get("title"), ev.get("title_en")) + '</h3><div class="ev__meta">' + meta + "</div>" + links + "</div>" +
            '<div class="ev__side"><span class="' + badge_class + '">' + bi(ev.get("badge"), ev.get("badge_en")) + "</span></div>" +
            more + "</div>")


def render_list(events):
    # Same order as events-feed.js: upcoming first (soonest first), then past (newest first);
    # year groups in the order the events come in.
    up = sorted([e for e in events if e.get("status") == "upcoming"], key=lambda e: str(e.get("date") or ""))
    past = sorted([e for e in events if e.get("status") != "upcoming"], key=lambda e: str(e.get("date") or ""), reverse=True)
    groups = {}
    for ev in up + past:
        groups.setdefault(str(ev.get("date") or "")[:4] or "0000", []).append(ev)
    return "".join('<div class="ev-group"><h2 class="ev-group__y">' + esc(y) + "</h2>" + "".join(render_card(e) for e in groups[y]) + "</div>"
                   for y in groups)


def render_home(events):
    up = sorted([e for e in events if e.get("status") == "upcoming"], key=lambda e: str(e.get("date") or ""))
    past = sorted([e for e in events if e.get("status") != "upcoming"], key=lambda e: str(e.get("date") or ""), reverse=True)
    out = []
    for ev in (up + past)[:3]:
        p = parse_ymd(ev.get("date"))
        status = "upcoming" if ev.get("status") == "upcoming" else "past"
        badge_class = "badge badge--up" if status == "upcoming" else "badge badge--past"
        out.append('<a class="ev" href="veranstaltungen.html#' + esc(ev.get("id")) + '">' +
                   '<div class="ev__date"><div class="ev__d">' + ("%02d" % p[2] if p else "") +
                   '</div><div class="ev__m">' + esc(MD[p[1] - 1] if p else "") +
                   '</div><div class="ev__y">' + esc(p[0] if p else "") + "</div></div>" +
                   '<div><h3 class="ev__t">' + bi(ev.get("title"), ev.get("title_en")) + '</h3><div class="ev__meta">' +
                   ("<span>" + bi(ev.get("location"), ev.get("location_en")) + "</span>" if ev.get("location") or ev.get("location_en") else "") +
                   ("<span>" + bi(ev.get("speaker"), ev.get("speaker_en")) + "</span>" if ev.get("speaker") or ev.get("speaker_en") else "") +
                   "</div></div>" +
                   '<div class="ev__side"><span class="' + badge_class + '">' + bi(ev.get("badge"), ev.get("badge_en")) + "</span></div></a>")
    return "".join(out)


def city_of(loc):
    return re.sub(r"^\d{4,5}\s+", "", str(loc or "").split(",")[-1].strip())


def join_dot(parts):
    return " · ".join(x for x in parts if x)


def render_news(events):
    """Homepage "Aktuelles" lead card: the next upcoming event, otherwise the latest past one."""
    up = sorted([e for e in events if e.get("status") == "upcoming"], key=lambda e: str(e.get("date") or ""))
    past = sorted([e for e in events if e.get("status") != "upcoming"], key=lambda e: str(e.get("date") or ""), reverse=True)
    ev = (up + past)[0] if (up + past) else None
    p = ev and parse_ymd(ev.get("date"))
    if not ev or not p:
        return ""
    is_up = ev.get("status") == "upcoming"
    date_de = "%d. %s %d" % (p[2], MD_FULL[p[1] - 1], p[0])
    date_en = "%d %s %d" % (p[2], ME[p[1] - 1], p[0])
    city_de, city_en = city_of(ev.get("location")), city_of(ev.get("location_en") or ev.get("location"))
    k_de = join_dot([ev.get("badge"), city_de]) if is_up else join_dot(["Rückblick", city_de])
    k_en = join_dot([ev.get("badge_en") or ev.get("badge"), city_en]) if is_up else join_dot(["Review", city_en])
    t_de = plain(ev.get("body_html"), 150) or ev.get("speaker") or ""
    t_en = plain(ev.get("body_html_en"), 150) or ev.get("speaker_en") or t_de
    link = bi("Zur Veranstaltung", "View event") if is_up else bi("Zum Rückblick", "Read the review")
    return ('<a class="card card--feat card--link" id="home-news-event" href="veranstaltungen.html#' + esc(ev.get("id")) + '">' +
            '<span class="card__k">' + bi(k_de, k_en) + "</span>" +
            '<h3 class="card__t">' + bi(ev.get("title"), ev.get("title_en")) + "</h3>" +
            # skip the date prefix when the description already starts with it
            "<p>" + bi(t_de if date_de in t_de[:60] else join_dot([date_de, t_de]),
                       t_en if date_en in t_en[:60] else join_dot([date_en, t_en])) + "</p>" +
            '<span class="link-more">' + link + "</span></a>")


def merge_archive(events, arch):
    events = list(events or [])
    if not isinstance(arch, dict):
        return events
    hidden = set(arch.get("hidden_ids") or [])  # WordPress events the website must not show
    events = [e for e in events if e.get("id") not in hidden]
    desc = arch.get("descriptions") or {}
    for ev in events:
        a = desc.get(ev.get("id"))
        if not a or (a.get("date") and a.get("date") != ev.get("date")):
            continue
        if ev.get("body_html") or ev.get("body_html_en"):
            continue  # WordPress text wins
        ev["body_html"] = a.get("de") or ""
        ev["body_html_en"] = a.get("en") or ""
    have = {ev.get("id") for ev in events}
    added = False
    for x in arch.get("extra_events") or []:
        if x and x.get("id") and x["id"] not in have:
            x = dict(x)
            x["status"] = "upcoming" if str(x.get("date") or "") >= dt.date.today().isoformat() else "past"
            events.append(x)
            added = True
    if added:
        events.sort(key=lambda e: str(e.get("date") or ""), reverse=True)
    return events


# ---------- structured data ----------

def plain(s, limit=None):
    s = html.unescape(re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", s or ""))).strip()
    if limit and len(s) > limit:
        s = s[:limit].rsplit(" ", 1)[0].rstrip(" ,;:–-") + " …"
    return s


def berlin_offset(y, m, d, hh):
    def last_sunday(year, month):
        nxt = dt.date(year + (month == 12), month % 12 + 1, 1)
        last = nxt - dt.timedelta(days=1)
        return last - dt.timedelta(days=(last.weekday() - 6) % 7)
    local = dt.datetime(y, m, d, hh)
    summer = dt.datetime.combine(last_sunday(y, 3), dt.time(2)) <= local < dt.datetime.combine(last_sunday(y, 10), dt.time(3))
    return "+02:00" if summer else "+01:00"


def iso(ev, key):
    p = parse_ymd(ev.get("date"))
    t = (ev.get(key) or "")[:5]
    if not p or not re.match(r"^\d\d:\d\d$", t):
        return "%04d-%02d-%02d" % p if p else ""
    return "%04d-%02d-%02dT%s:00%s" % (p[0], p[1], p[2], t, berlin_offset(p[0], p[1], p[2], int(t[:2])))


def event_schema(ev):
    url = SITE + "/veranstaltungen.html#" + ev["id"]
    upcoming = ev.get("status") == "upcoming"
    loc = ev.get("location") or "Berlin"
    desc = plain(ev.get("body_html"), 500) or " · ".join(x for x in [ev.get("badge"), ev.get("speaker"), loc] if x)
    item = {
        "@type": "Event",
        "@id": url,
        "name": ev.get("title") or "",
        "url": url,
        "description": desc,
        "inLanguage": "de",
        # Past events were often recorded without a reliable start time, so they get the date only.
        "startDate": iso(ev, "time_start") if upcoming and not ev.get("time_tba") else iso({"date": ev.get("date")}, "x"),
        "eventStatus": "https://schema.org/EventScheduled",
        "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode",
        "location": {"@type": "Place", "name": loc, "address": loc},
        "image": SITE + "/assets/images/social-share.jpg?v=3",
    }
    if ev.get("title_en") and ev.get("title_en") != ev.get("title"):
        item["alternateName"] = ev["title_en"]
    if upcoming and ev.get("time_end") and not ev.get("time_tba"):
        item["endDate"] = iso(ev, "time_end")
    if IN_GERMANY.search(loc):
        item["organizer"] = {"@id": ORG_ID}
    else:
        item["contributor"] = {"@id": ORG_ID}  # participation in a partner's event abroad
    people = [{"@id": "%s/personen/%s.html#person" % (SITE, slug)} for rx, slug in BOARD.items() if re.search(rx, ev.get("speaker") or "")]
    if people:
        item["performer"] = people if len(people) > 1 else people[0]
    if upcoming:
        price = ev.get("price") or 0
        if ev.get("price") is not None:  # unknown for a save-the-date
            item["isAccessibleForFree"] = not price
        if ev.get("bookable") or price:
            item["offers"] = {"@type": "Offer", "price": num(price), "priceCurrency": "EUR", "url": url,
                              "availability": "https://schema.org/InStock"}
    rec = safe_url(ev.get("recording_url"))
    if rec:
        item["recordedIn"] = {"@type": "CreativeWork", "url": rec}
    return item


def schema_block(events):
    data = {"@context": "https://schema.org", "@graph": [event_schema(e) for e in events if e.get("id") and parse_ymd(e.get("date"))]}
    js = json.dumps(data, ensure_ascii=False, indent=1).replace("</", "<\\/")
    return '<script type="application/ld+json" id="ev-schema">\n' + js + "\n</script>"


# ---------- llms.txt ----------

def llms_section(events):
    up = sorted([e for e in events if e.get("status") == "upcoming"], key=lambda e: e.get("date") or "")
    past = [e for e in events if e.get("status") != "upcoming" and parse_ymd(e.get("date"))]
    up = [e for e in up if parse_ymd(e.get("date"))]

    def line(e, with_time):
        p = parse_ymd(e.get("date"))
        when = "%04d-%02d-%02d" % p
        if with_time and e.get("time_tba"):
            when += ", time to be announced"
        elif with_time and e.get("time_start"):
            when += ", %s" % e["time_start"][:5]
        title = e.get("title") or ""
        if e.get("title_en") and e.get("title_en") != title:
            title += " (EN: %s)" % e["title_en"]
        bits = [when, title, e.get("speaker"), e.get("location"), e.get("badge")]
        return "- " + " – ".join(b for b in bits if b) + " – " + SITE + "/veranstaltungen.html#" + e["id"]

    out = ["## Events (Veranstaltungen)", "",
           "Full calendar and archive with descriptions: " + SITE + "/veranstaltungen.html", "",
           "Format of each line: date – title – speakers – place – format – link.", ""]
    out += ["### Upcoming", ""] + ([line(e, True) for e in up] or ["- No upcoming events announced at the moment."]) + [""]
    out += ["### Past events", ""] + [line(e, False) for e in past]
    return "\n".join(out)


# ---------- file patching ----------

def patch(path, pattern, replacement, first_time=None):
    raw = path.read_bytes().decode("utf-8")
    nl = "\r\n" if "\r\n" in raw else "\n"
    replacement = replacement.replace("\n", nl)
    new, n = re.subn(pattern, lambda m: replacement, raw, count=1, flags=re.S)
    if n == 0 and first_time:
        new, n = re.subn(first_time[0], lambda m: first_time[1].replace("\n", nl).replace("@@", replacement), raw, count=1, flags=re.S)
    if n == 0:
        sys.exit("marker not found in %s" % path.name)
    if new != raw:
        path.write_bytes(new.encode("utf-8"))
    return new != raw


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--feed", help="saved feed JSON instead of the live WordPress feed")
    a = ap.parse_args()
    if a.feed:
        feed = json.loads(Path(a.feed).read_text(encoding="utf-8"))
    else:
        req = urllib.request.Request(FEED_URL, headers={"User-Agent": "Mozilla/5.0 (compatible; EG-prerender)", "Accept": "application/json"})
        feed = json.loads(urllib.request.urlopen(req, timeout=30).read().decode("utf-8"))
    arch = json.loads((ROOT / "assets/data/event-archive.json").read_text(encoding="utf-8"))
    events = merge_archive(feed.get("events") or [], arch)
    if not events:
        sys.exit("feed returned no events; nothing written")

    list_html = "<!--ev-prerender-->" + render_list(events) + "<!--/ev-prerender-->"
    home_html = "<!--ev-prerender-->" + render_home(events) + "<!--/ev-prerender-->"
    changed = []
    v = ROOT / "veranstaltungen.html"
    if patch(v, r"<!--ev-prerender-->.*?<!--/ev-prerender-->", list_html,
             (r'(<div id="ev-list-root" aria-live="polite">)(</div>)', r'<div id="ev-list-root" aria-live="polite">@@</div>')):
        changed.append("veranstaltungen.html list")
    if patch(v, r'<script type="application/ld\+json" id="ev-schema">.*?</script>', schema_block(events),
             (r"</head>", "@@\n</head>")):
        changed.append("veranstaltungen.html Event schema")
    if patch(ROOT / "index.html", r"<!--ev-prerender-->.*?<!--/ev-prerender-->", home_html,
             (r'(<div id="home-ev-preview" aria-live="polite">)\s*<p class="ev-loading">.*?</p>\s*(</div>)',
              '<div id="home-ev-preview" aria-live="polite">@@</div>')):
        changed.append("index.html preview")
    news = render_news(events)
    if news and patch(ROOT / "index.html", r"<!--ev-news-->.*?<!--/ev-news-->", "<!--ev-news-->" + news + "<!--/ev-news-->",
                      (r'<a class="card card--feat card--link" href="veranstaltungen\.html">.*?</a>', "<!--ev-news-->@@<!--/ev-news-->")):
        changed.append("index.html news card")
    if patch(ROOT / "llms.txt", r"<!-- events:start -->.*?<!-- events:end -->",
             "<!-- events:start -->\n" + llms_section(events) + "\n<!-- events:end -->"):
        changed.append("llms.txt events")
    up = sum(1 for e in events if e.get("status") == "upcoming")
    print("%d events (%d upcoming) | updated: %s" % (len(events), up, ", ".join(changed) or "nothing (already current)"))


if __name__ == "__main__":
    main()
