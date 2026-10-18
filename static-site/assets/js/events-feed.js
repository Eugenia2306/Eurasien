/**
 * Load Veranstaltungen from WordPress (/app/eg-events.json.php)
 * and render brochure cards. Also fills the homepage preview.
 */
(function () {
  "use strict";
  var d = document;
  var root = d.getElementById("ev-list-root");
  var homeRoot = d.getElementById("home-ev-preview");
  var newsCard = d.getElementById("home-news-event");
  if (!root && !homeRoot && !newsCard) return;
  /* tools/prerender_events.py writes a static copy of the list for crawlers; keep it until the live feed arrives. */
  var hasPrerender = !!(root && root.querySelector(".ev-group"));

  var landingHash = (location.hash || "").replace(/^#/, "");
  if (root && (landingHash === "ev-list-root" || landingHash.indexOf("ev-") === 0)) {
    try {
      if (history.scrollRestoration) history.scrollRestoration = "manual";
    } catch (eSR) {}
  }

  var MD = [
    "Jan", "Feb", "Mär", "Apr", "Mai", "Jun",
    "Jul", "Aug", "Sep", "Okt", "Nov", "Dez"
  ];
  var WD_DE = ["Sonntag", "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag"];
  var WD_EN = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
  var MD_FULL_DE = [
    "Januar", "Februar", "März", "April", "Mai", "Juni",
    "Juli", "August", "September", "Oktober", "November", "Dezember"
  ];
  var ME = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
  ];

  function esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function bi(de, en) {
    var dText = de || "";
    var eText = en || de || "";
    return (
      '<span class="de">' +
      esc(dText) +
      '</span><span class="en" hidden>' +
      esc(eText) +
      "</span>"
    );
  }

  function biHtml(deHtml, enHtml) {
    var de = deHtml || "";
    var en = enHtml || deHtml || "";
    return (
      '<div class="de">' +
      de +
      '</div><div class="en" hidden>' +
      en +
      "</div>"
    );
  }

  function parseYmd(date) {
    var p = String(date || "").split("-");
    if (p.length < 3) return null;
    return {
      y: p[0],
      m: parseInt(p[1], 10),
      d: parseInt(p[2], 10),
      iso: date
    };
  }

  function formatWhen(ev) {
    var p = parseYmd(ev.date);
    if (!p) return { de: "", en: "" };
    var MD_FULL = [
      "Januar", "Februar", "März", "April", "Mai", "Juni",
      "Juli", "August", "September", "Oktober", "November", "Dezember"
    ];
    var dt = new Date(Date.UTC(p.y, p.m - 1, p.d, 12, 0, 0));
    var wd = dt.getUTCDay();
    if (ev.time_tba) {
      return {
        de: WD_DE[wd] + ", " + p.d + ". " + MD_FULL[p.m - 1] + " " + p.y + " · Uhrzeit folgt",
        en: WD_EN[wd] + ", " + p.d + " " + ME[p.m - 1] + " " + p.y + " · Time to be announced"
      };
    }
    var t = (ev.time_start || "19:00").slice(0, 5);
    var th = parseInt(t.split(":")[0], 10);
    var tm = t.split(":")[1] || "00";
    var ampm =
      th === 0
        ? "12:" + tm + " am"
        : th < 12
          ? th + ":" + tm + " am"
          : th === 12
            ? "12:" + tm + " pm"
            : th - 12 + ":" + tm + " pm";
    return {
      de: WD_DE[wd] + ", " + p.d + ". " + MD_FULL[p.m - 1] + " " + p.y + " · " + t + " Uhr",
      en: WD_EN[wd] + ", " + p.d + " " + ME[p.m - 1] + " " + p.y + " · " + ampm
    };
  }

  function monthLabel(m) {
    return MD[m - 1] || "";
  }

  function dayPad(n) {
    return n < 10 ? "0" + n : String(n);
  }

  function renderCard(ev) {
    var p = parseYmd(ev.date);
    var when = formatWhen(ev);
    var status = ev.status === "upcoming" ? "upcoming" : "past";
    var badgeClass = status === "upcoming" ? "badge badge--up" : "badge badge--past";
    var price = ev.price != null ? ev.price : 0;
    var attrs = [
      'class="ev"',
      'id="' + esc(ev.id) + '"',
      'data-date="' + esc(ev.date) + '"',
      'data-status="' + esc(status) + '"',
      'data-type="' + esc(ev.type || "lecture") + '"',
      'data-price="' + esc(price) + '"',
      'data-time-start="' + esc(ev.time_start || "19:00") + '"',
      'data-time-end="' + esc(ev.time_end || "21:00") + '"',
      'data-location="' + esc(ev.location || "") + '"',
      'data-bookable="' + (ev.bookable ? "1" : "0") + '"'
    ].join(" ");

    var meta = "";
    if (ev.location || ev.location_en) {
      meta += "<span>" + bi(ev.location, ev.location_en) + "</span>";
    }
    if (when.de) {
      meta += "<span>" + bi(when.de, when.en) + "</span>";
    }
    if (ev.speaker || ev.speaker_en) {
      meta += "<span>" + bi(ev.speaker, ev.speaker_en) + "</span>";
    }

    var more = "";
    if (ev.body_html || ev.body_html_en) {
      more =
        '<details class="ev-more"><summary class="ev-more__summary">' +
        '<span class="ev-more__summary-lab">' +
        (status === "past"
          ? bi("Rückblick", "Review")
          : bi("Beschreibung und Veranstaltungsdetails", "Description and event details")) +
        '</span><span class="ev-more__chev" aria-hidden="true"></span></summary>' +
        '<div class="ev-more__body"><div class="ev-more__content">' +
        biHtml(ev.body_html || "", ev.body_html_en || "") +
        "</div></div></details>";
    }

    /* Past events: optional report / recording links (set in the WordPress event editor). */
    var links = "";
    if (status === "past") {
      var safeUrl = function (u) { return typeof u === "string" && /^https?:\/\//i.test(u) ? u : ""; };
      var rep = safeUrl(ev.report_url), rec = safeUrl(ev.recording_url);
      if (rep) {
        links += '<a class="link-more ext small" href="' + esc(rep) + '" target="_blank" rel="noopener">' + bi("Zum Bericht", "Read the report") + "</a>";
      }
      if (rec) {
        links += '<a class="link-more ext small" href="' + esc(rec) + '" target="_blank" rel="noopener">' + bi("Aufzeichnung ansehen", "Watch the recording") + "</a>";
      }
      if (links) {
        links = '<div class="ev__links" style="display:flex;flex-wrap:wrap;gap:6px 18px;margin-top:8px">' + links + "</div>";
      }
    }

    return (
      "<div " +
      attrs +
      ">" +
      '<div class="ev__date"><div class="ev__d">' +
      esc(p ? dayPad(p.d) : "") +
      '</div><div class="ev__m">' +
      esc(p ? monthLabel(p.m) : "") +
      '</div><div class="ev__y">' +
      esc(p ? p.y : "") +
      "</div></div>" +
      "<div><h3 class=\"ev__t\">" +
      bi(ev.title, ev.title_en) +
      '</h3><div class="ev__meta">' +
      meta +
      "</div>" +
      links +
      "</div>" +
      '<div class="ev__side"><span class="' +
      badgeClass +
      '">' +
      bi(ev.badge, ev.badge_en) +
      "</span></div>" +
      more +
      "</div>"
    );
  }

  /* List order: upcoming events first (soonest first), then past events (newest first). */
  function orderForList(events) {
    var up = [], past = [];
    events.forEach(function (ev) { (ev.status === "upcoming" ? up : past).push(ev); });
    up.sort(function (a, b) { return String(a.date || "").localeCompare(String(b.date || "")); });
    past.sort(function (a, b) { return String(b.date || "").localeCompare(String(a.date || "")); });
    return up.concat(past);
  }

  /* Year groups in the order the events come in (see orderForList). */
  function groupByYear(events) {
    var map = {}, order = [];
    events.forEach(function (ev) {
      var y = String(ev.date || "").slice(0, 4) || "0000";
      if (!map[y]) { map[y] = []; order.push(y); }
      map[y].push(ev);
    });
    return order.map(function (y) {
      return { year: y, events: map[y] };
    });
  }

  function feedUrl() {
    var app = window.EG_APP || {};
    var base = app.base || "/app";
    var u = (app.eventsFeed || base + "/eg-events.json.php") + "";
    if (u.indexOf("http") === 0) return u;
    return window.location.origin + u;
  }

  function fallbackFeedUrl() {
    var scripts = d.querySelectorAll("script[src]");
    for (var i = 0; i < scripts.length; i++) {
      var src = scripts[i].getAttribute("src") || "";
      var m = src.match(/^(.*\/assets\/)/);
      if (m) return m[1] + "data/events-feed.json";
    }
    return "assets/data/events-feed.json";
  }

  function loadFeed() {
    var url = feedUrl();
    url += (url.indexOf("?") >= 0 ? "&" : "?") + "nocache=" + Date.now();
    return fetch(url, { credentials: "same-origin", cache: "no-store", headers: { Accept: "application/json" } })
      .then(function (r) {
        if (!r.ok) throw new Error("feed-" + r.status);
        return r.json();
      })
      .catch(function () {
        return fetch(fallbackFeedUrl(), { credentials: "same-origin", headers: { Accept: "application/json" } })
          .then(function (r) {
            if (!r.ok) throw new Error("fallback-" + r.status);
            return r.json();
          });
      });
  }

  /* Static event archive (descriptions + events missing in WordPress). Never blocks the feed. */
  function archiveUrl() {
    var scripts = d.querySelectorAll("script[src]");
    for (var i = 0; i < scripts.length; i++) {
      var src = scripts[i].getAttribute("src") || "";
      var m = src.match(/^(.*\/assets\/)/);
      if (m) return m[1] + "data/event-archive.json?v=5";
    }
    return "assets/data/event-archive.json?v=5";
  }

  function loadArchive() {
    return fetch(archiveUrl(), { credentials: "same-origin", headers: { Accept: "application/json" } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .catch(function () { return null; });
  }

  function mergeArchive(events, arch) {
    events = (events || []).slice();
    if (!arch || typeof arch !== "object") return events;
    /* hidden_ids: WordPress events the website must not show (e.g. entered by mistake). */
    var hidden = {};
    (arch.hidden_ids || []).forEach(function (id) { hidden[id] = true; });
    events = events.filter(function (ev) { return ev && !hidden[ev.id]; });
    var desc = arch.descriptions || {};
    events.forEach(function (ev) {
      var a = ev && desc[ev.id];
      if (!a || (a.date && a.date !== ev.date)) return;
      if (ev.body_html || ev.body_html_en) return; /* WordPress text wins */
      ev.body_html = a.de || "";
      ev.body_html_en = a.en || "";
    });
    var have = {};
    events.forEach(function (ev) { if (ev && ev.id) have[ev.id] = true; });
    var added = false;
    var now = new Date();
    var todayIso = now.getFullYear() + "-" + dayPad(now.getMonth() + 1) + "-" + dayPad(now.getDate());
    (arch.extra_events || []).forEach(function (x) {
      if (x && x.id && !have[x.id]) {
        x.status = String(x.date || "") >= todayIso ? "upcoming" : "past"; /* archive-only events: status from the date */
        events.push(x);
        added = true;
      }
    });
    if (added) {
      events.sort(function (a, b) { return String(b.date || "").localeCompare(String(a.date || "")); });
    }
    return events;
  }

  function loadEvents() {
    return Promise.all([loadFeed(), loadArchive()]).then(function (res) {
      var data = res[0];
      return mergeArchive((data && data.events) || [], res[1]);
    });
  }

  /* Region → event-ID mapping (mirrors regionen.html reg-events panels) */
  var EV_REGION_MAP = {
    europa:      ['ev-krieg-oder-frieden-2026','ev-sieben-gruende-warum-kein-2025','ev-vom-niedergang-des-westens-2024','ev-je-taime-moi-non-2024','ev-erbe-vermaechtnis-bewahren-wofuer-2025','ev-endspiel-europa-2022','ev-tanz-dem-vulkan-2023','ev-choices-ukraine-russia-eu-2022','ev-search-peaceful-coexistence-eurasia-2023'],
    osteuropa:   ['ev-ruestungskontrolle-vizeaussenminister-ryabkov-2026','ev-passiven-aktiven-abschreckung-russlands-2025','ev-chancen-frieden-ukraine-trump-2025','ev-friedensplaene-ukraine-2024','ev-wladimir-putin-politische-biographie-2022'],
    zentralasien:['ev-asien-brics-soz-apec-2026-10','ev-deutsche-aussenpolitik-eurasien-2025','ev-european-silk-road-summit-2024','ev-globale-bedeutung-cica-konferenz-2022','ev-eurasische-handels-transportkorridore-2024'],
    kaukasus:    ['ev-selbsternannten-republiken-postsowjetischen-raum-2023','ev-european-silk-road-summit-2024'],
    ostasien:    ['ev-asien-brics-soz-apec-2026-10','ev-10th-china-global-think-2025','ev-veraenderungen-internationalen-ordnung-2026','ev-china-logistik-eurasiens-2023','ev-teilnahme-verona-eurasian-economic-2024'],
    suedasien:   ['ev-asien-brics-soz-apec-2026-10','ev-zeitenwende-eurasien-interessen-deutschlands-2024'],
    naherosten:  ['ev-teilnahme-petersburger-dialog-2026','ev-fachgespraech-amerikanischen-experten-2025','ev-gespraech-prof-theodore-postol-2025']
  };

  function applyEvRegionFilter(region) {
    var ids = EV_REGION_MAP[region];
    if (!ids) return false;
    var idSet = {};
    for (var i = 0; i < ids.length; i++) idSet[ids[i]] = true;
    var shown = 0;
    var evItems = [].slice.call(d.querySelectorAll('#ev-list-root .ev'));
    var evGroups = [].slice.call(d.querySelectorAll('#ev-list-root .ev-group'));
    evItems.forEach(function(el) {
      var ok = !!idSet[el.id];
      el.hidden = !ok;
      if (ok) shown++;
    });
    evGroups.forEach(function(g) { g.hidden = !g.querySelector('.ev:not([hidden])'); });
    var evEmpty = d.getElementById('ev-empty');
    if (evEmpty) evEmpty.hidden = shown > 0;
    /* Mark all-filter chip as unselected, no chip "selected" for region filter */
    var filterEl = d.getElementById('ev-filter');
    if (filterEl) filterEl.querySelectorAll('.chip').forEach(function(c) { c.setAttribute('aria-pressed','false'); });
    /* Scroll to list */
    var listTop = d.getElementById('ev-list-root') || d.getElementById('ev-filter');
    if (listTop) {
      try {
        var hd = d.querySelector('.hd');
        var off = (hd ? hd.getBoundingClientRect().height : 96) + 18;
        var y = listTop.getBoundingClientRect().top + (window.pageYOffset || 0) - off;
        window.scrollTo(0, Math.max(0, Math.round(y)));
      } catch(e) { try { listTop.scrollIntoView(true); } catch(e2) {} }
    }
    return true;
  }

  function afterRender() {
    if (window.EGLang && typeof window.EGLang.set === "function" && typeof window.EGLang.get === "function") {
      try {
        window.EGLang.set(window.EGLang.get(), { persist: false });
      } catch (e) {}
    }
    if (window.EG_EVENTS_UI && typeof window.EG_EVENTS_UI.refresh === "function") {
      window.EG_EVENTS_UI.refresh();
    }
    if (window.EG_EVENT_CAL && typeof window.EG_EVENT_CAL.refresh === "function") {
      window.EG_EVENT_CAL.refresh();
    }
    if (window.EG_EVENT_REG && typeof window.EG_EVENT_REG.bindRegisterButtons === "function") {
      window.EG_EVENT_REG.bindRegisterButtons();
    }
    if (window.EG_EVENT_REG && typeof window.EG_EVENT_REG.openTicketsFromUrl === "function") {
      window.EG_EVENT_REG.openTicketsFromUrl();
    }
    requestAnimationFrame(function () {
      requestAnimationFrame(applyEventHash);
    });
  }

  function applyEventHash() {
    var h = (location.hash || "").replace(/^#/, "");
    if (!h) return;
    if (h === "ev-filter-upcoming" || h === "ev-filter-past") {
      if (window.EGNav && typeof window.EGNav.applyLocationHash === "function") {
        window.EGNav.applyLocationHash();
      }
      return;
    }
    if (h.indexOf("ev-region-") === 0) {
      var region = h.slice("ev-region-".length);
      applyEvRegionFilter(region);
      return;
    }
    if (h !== "ev-list-root" && h.indexOf("ev-") !== 0) return;
    var linked = h !== "ev-list-root" ? d.getElementById(h) : null;
    var bar = linked && linked.classList.contains("ev") ? openFromLink(linked) : null;
    if (window.EGNav && typeof window.EGNav.focusElementById === "function") {
      window.EGNav.focusElementById(h, { instant: true });
      if (bar) window.scrollBy(0, -(bar.offsetHeight + 10)); /* keep the back link in view */
      return;
    }
    var t = h === "ev-list-root"
      ? (root && (root.querySelector(".ev-group") || root.querySelector(".ev") || root))
      : d.getElementById(h);
    if (!t) return;
    if (h !== "ev-list-root") t.classList.add("is-focused");
    try {
      var hd = d.querySelector(".hd");
      var offset = (hd ? hd.getBoundingClientRect().height : 96) + 18;
      var y = (bar || t).getBoundingClientRect().top + (window.pageYOffset || 0) - offset;
      window.scrollTo(0, Math.max(0, Math.round(y)));
    } catch (e) {
      try { t.scrollIntoView(true); } catch (e2) {}
    }
    if (h !== "ev-list-root") {
      setTimeout(function () { t.classList.remove("is-focused"); }, 6000);
    }
  }

  function removeBackBar() {
    var old = d.getElementById("ev-backbar");
    if (old && old.parentNode) old.parentNode.removeChild(old);
  }

  /* Arrived through a link to one event: open its description and offer a way back to the calendar. */
  function openFromLink(evEl) {
    var det = evEl.querySelector("details.ev-more");
    if (det) det.open = true;
    removeBackBar();
    var bar = d.createElement("p");
    bar.id = "ev-backbar";
    bar.style.cssText = "margin:0 0 10px;font-size:.92rem;font-weight:600";
    bar.innerHTML =
      '<a href="veranstaltungen.html" style="color:var(--brand-blue,#0032A0);text-decoration:none">← ' +
      bi("Kalender und alle Veranstaltungen", "Calendar and all events") + "</a>";
    bar.firstChild.addEventListener("click", function (e) {
      e.preventDefault();
      removeBackBar();
      try { history.replaceState(null, "", location.pathname + location.search); } catch (e2) {}
      var top = d.getElementById("ev-filter") || root;
      var hd = d.querySelector(".hd");
      var off = (hd ? hd.getBoundingClientRect().height : 96) + 18;
      window.scrollTo(0, Math.max(0, Math.round(top.getBoundingClientRect().top + (window.pageYOffset || 0) - off)));
    });
    evEl.parentNode.insertBefore(bar, evEl);
    var chips = d.getElementById("ev-filter");
    if (chips) chips.addEventListener("click", removeBackBar, { once: true });
    if (window.EGLang && typeof window.EGLang.set === "function" && typeof window.EGLang.get === "function") {
      try { window.EGLang.set(window.EGLang.get(), { persist: false }); } catch (e3) {}
    }
    return bar;
  }

  function renderHome(events) {
    if (!homeRoot) return;
    var list = (events || []).slice();
    list.sort(function (a, b) {
      var au = a.status === "upcoming" ? 0 : 1;
      var bu = b.status === "upcoming" ? 0 : 1;
      if (au !== bu) return au - bu;
      if (au === 0) return String(a.date || "").localeCompare(String(b.date || ""));
      return String(b.date || "").localeCompare(String(a.date || ""));
    });
    var pick = list.slice(0, 3);
    if (!pick.length) {
      homeRoot.innerHTML = "";
      return;
    }
    homeRoot.innerHTML = pick
      .map(function (ev) {
        var p = parseYmd(ev.date);
        var status = ev.status === "upcoming" ? "upcoming" : "past";
        var badgeClass = status === "upcoming" ? "badge badge--up" : "badge badge--past";
        var href = "veranstaltungen.html#" + esc(ev.id);
        return (
          '<a class="ev" href="' + href + '">' +
          '<div class="ev__date"><div class="ev__d">' +
          esc(p ? dayPad(p.d) : "") +
          '</div><div class="ev__m">' +
          esc(p ? monthLabel(p.m) : "") +
          '</div><div class="ev__y">' +
          esc(p ? p.y : "") +
          "</div></div>" +
          "<div><h3 class=\"ev__t\">" +
          bi(ev.title, ev.title_en) +
          '</h3><div class="ev__meta">' +
          (ev.location || ev.location_en ? "<span>" + bi(ev.location, ev.location_en) + "</span>" : "") +
          (ev.speaker || ev.speaker_en ? "<span>" + bi(ev.speaker, ev.speaker_en) + "</span>" : "") +
          "</div></div>" +
          '<div class="ev__side"><span class="' + badgeClass + '">' +
          bi(ev.badge, ev.badge_en) +
          "</span></div></a>"
        );
      })
      .join("");
  }

  /* Plain text from description HTML, cut at a word boundary (same rules as tools/prerender_events.py). */
  function plainText(h, limit) {
    var s = String(h || "").replace(/<[^>]+>/g, " ");
    try {
      s = new DOMParser().parseFromString("<!doctype html><body>" + s, "text/html").body.textContent || "";
    } catch (e) {}
    s = s.replace(/\s+/g, " ").trim();
    if (limit && s.length > limit) {
      var cut = s.slice(0, limit);
      var sp = cut.lastIndexOf(" ");
      if (sp > 0) cut = cut.slice(0, sp);
      s = cut.replace(/[ ,;:–-]+$/, "") + " …";
    }
    return s;
  }

  function cityOf(loc) {
    return String(loc || "").split(",").pop().trim().replace(/^\d{4,5}\s+/, "");
  }

  function joinDot(parts) {
    return parts.filter(function (x) { return !!x; }).join(" · ");
  }

  /* Homepage "Aktuelles" lead card: the next upcoming event, otherwise the latest past one. */
  function renderHomeNews(events) {
    if (!newsCard) return;
    var list = (events || []).slice();
    var up = list.filter(function (e) { return e.status === "upcoming"; })
      .sort(function (a, b) { return String(a.date || "").localeCompare(String(b.date || "")); });
    var past = list.filter(function (e) { return e.status !== "upcoming"; })
      .sort(function (a, b) { return String(b.date || "").localeCompare(String(a.date || "")); });
    var ev = up[0] || past[0];
    var p = ev && parseYmd(ev.date);
    if (!ev || !p) return;
    var isUp = ev.status === "upcoming";
    var dateDe = p.d + ". " + MD_FULL_DE[p.m - 1] + " " + p.y;
    var dateEn = p.d + " " + ME[p.m - 1] + " " + p.y;
    var cityDe = cityOf(ev.location), cityEn = cityOf(ev.location_en || ev.location);
    var kDe = isUp ? joinDot([ev.badge, cityDe]) : joinDot(["Rückblick", cityDe]);
    var kEn = isUp ? joinDot([ev.badge_en || ev.badge, cityEn]) : joinDot(["Review", cityEn]);
    var tDe = plainText(ev.body_html, 150) || ev.speaker || "";
    var tEn = plainText(ev.body_html_en, 150) || ev.speaker_en || tDe;
    var html =
      '<a class="card card--feat card--link" id="home-news-event" href="veranstaltungen.html#' + esc(ev.id) + '">' +
      '<span class="card__k">' + bi(kDe, kEn) + "</span>" +
      '<h3 class="card__t">' + bi(ev.title, ev.title_en) + "</h3>" +
      /* skip the date prefix when the description already starts with it */
      "<p>" + bi(tDe.slice(0, 60).indexOf(dateDe) >= 0 ? tDe : joinDot([dateDe, tDe]),
        tEn.slice(0, 60).indexOf(dateEn) >= 0 ? tEn : joinDot([dateEn, tEn])) + "</p>" +
      '<span class="link-more">' + (isUp ? bi("Zur Veranstaltung", "View event") : bi("Zum Rückblick", "Read the review")) + "</span></a>";
    var tmp = d.createElement("div");
    tmp.innerHTML = html;
    var fresh = tmp.firstChild;
    newsCard.parentNode.replaceChild(fresh, newsCard);
    newsCard = fresh;
  }

  function showError() {
    if (hasPrerender) {
      afterRender(); /* feed unavailable: keep the pre-rendered list and wire up filters/calendar on it */
      return;
    }
    if (root) {
      root.innerHTML =
        '<p class="ev-feed-error"><span class="de">Veranstaltungen konnten nicht geladen werden. Bitte später erneut versuchen.</span>' +
        '<span class="en" hidden>Events could not be loaded. Please try again later.</span></p>';
    }
  }

  function render(events) {
    try {
      renderHome(events);
      renderHomeNews(events);
      if (!root) {
        afterRender();
        return;
      }
      if (!events || !events.length) {
        root.innerHTML = "";
        var empty = d.getElementById("ev-empty");
        if (empty) empty.hidden = false;
        afterRender();
        return;
      }
      var groups = groupByYear(orderForList(events));
      var html = groups
        .map(function (g) {
          return (
            '<div class="ev-group"><h2 class="ev-group__y">' +
            esc(g.year) +
            "</h2>" +
            g.events.map(renderCard).join("") +
            "</div>"
          );
        })
        .join("");
      root.innerHTML = html;
      var emptyEl = d.getElementById("ev-empty");
      if (emptyEl) emptyEl.hidden = true;
      afterRender();
    } catch (err) {
      showError();
    }
  }

  function startFeed() {
    if (root && !hasPrerender) {
      root.innerHTML =
        '<p class="ev-loading"><span class="de">Veranstaltungen werden geladen…</span>' +
        '<span class="en" hidden>Loading events…</span></p>';
    }
    loadEvents()
      .then(function (events) {
        render(events);
      })
      .catch(function () {
        showError();
      });
  }

  window.EG_EVENTS_FEED = {
    reload: function () {
      return loadEvents()
        .then(function (events) {
          render(events);
        })
        .catch(function () {
          showError();
        });
    }
  };

  if (d.readyState === "loading") {
    d.addEventListener("DOMContentLoaded", startFeed);
  } else {
    startFeed();
  }
})();
