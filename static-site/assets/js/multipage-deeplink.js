/* Multipage deep-links:
   - partner.html#partner-{key}
   - mediathek.html#mediathek-videoarchiv
   - regionen.html#ostasien (etc.) via EGNav
   - analysen.html#an-fmt-* via EGNav
*/
(function () {
  var REGION_IDS = {
    europa: 1,
    kaukasus: 1,
    naherosten: 1,
    ostasien: 1,
    osteuropa: 1,
    suedasien: 1,
    zentralasien: 1
  };

  function isPageRootHash(h, el) {
    if (!h) return false;
    if (/^(p-topic-|p-person-|p-members-)/.test(h)) return true;
    if (
      /^(p-themen|p-kultur|p-laender|p-regionen|p-analysen|p-veranstaltungen|p-mediathek|p-mitgliedschaft|p-mitgliedschaft-vorteile|p-mission|p-vorstand|p-partner|p-home|p-news|p-login)$/.test(
        h
      )
    ) {
      return true;
    }
    if (el && el.classList && el.classList.contains("page")) return true;
    return false;
  }

  function activateFromHash() {
    var h = (location.hash || "").replace(/^#/, "");
    if (!h) return;

    if (window.EGNav && typeof window.EGNav.applyLocationHash === "function") {
      if (window.EGNav.applyLocationHash()) return;
    }

    if (REGION_IDS[h]) {
      var btn = document.querySelector('.reg-btn[data-region="' + h + '"]');
      if (btn) {
        try {
          btn.click();
        } catch (e) {}
        return;
      }
    }

    var el = document.getElementById(h);
    if (!el) {
      el = document.querySelector('[data-partner-key="' + h + '"]');
    }
    if (!el) return;

    /* Page-root ids: keep the hero in view on click, not on Back. */
    if (isPageRootHash(h, el)) {
      return;
    }

    if (el.tagName && el.tagName.toLowerCase() === "details") {
      el.open = true;
      el.classList.add("is-focused");
      setTimeout(function () {
        el.classList.remove("is-focused");
      }, 3500);
    } else {
      el.classList.add("is-focused");
      setTimeout(function () {
        el.classList.remove("is-focused");
      }, 3500);
    }

    var reduce =
      window.matchMedia &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    try {
      el.scrollIntoView({
        behavior: reduce ? "auto" : "smooth",
        block: "start",
      });
    } catch (e) {
      try {
        el.scrollIntoView(true);
      } catch (e2) {}
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      setTimeout(activateFromHash, 100);
    });
  } else {
    setTimeout(activateFromHash, 100);
  }
  window.addEventListener("hashchange", activateFromHash);
})();

/* Phone chrome: move nav to <body>, full-screen drawer, prevent title underlap. */
(function () {
  var MQ = "(max-width: 767px)";

  function isPhone() {
    return window.matchMedia && window.matchMedia(MQ).matches;
  }

  function setHeaderBottom() {
    var ubar = document.querySelector(".ubar");
    var hd = document.querySelector(".hd");
    var bottom = 0;
    if (ubar) bottom = Math.max(bottom, ubar.getBoundingClientRect().bottom);
    if (hd) bottom = Math.max(bottom, hd.getBoundingClientRect().bottom);
    /* When chrome is fixed on phones, measure laid-out heights instead */
    if (isPhone() && hd) {
      var h = 0;
      if (ubar) h += ubar.offsetHeight;
      h += hd.offsetHeight;
      bottom = h;
    }
    document.documentElement.style.setProperty(
      "--eg-header-bottom",
      Math.round(bottom) + "px"
    );
    document.documentElement.style.setProperty(
      "--eg-chrome-h",
      Math.round(bottom) + "px"
    );
  }

  function placeNav() {
    var nav = document.querySelector("nav.nav");
    var hdIn = document.querySelector(".hd__in");
    if (!nav || !hdIn) return;
    if (isPhone()) {
      if (nav.parentElement !== document.body) {
        nav.setAttribute("data-eg-home", "header");
        document.body.appendChild(nav);
      }
    } else if (nav.getAttribute("data-eg-home") === "header") {
      /* Restore into header between brand and actions */
      var actions = hdIn.querySelector(".actions");
      if (actions) hdIn.insertBefore(nav, actions);
      else hdIn.appendChild(nav);
      nav.removeAttribute("data-eg-home");
      nav.style.top = "";
    }
  }

  function closeMenu() {
    document.body.classList.remove("nav-open");
    document.documentElement.classList.remove("nav-open");
    var b = document.querySelector(".burger");
    if (b) b.setAttribute("aria-expanded", "false");
  }

  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  onReady(function () {
    placeNav();
    setHeaderBottom();

    window.addEventListener("resize", function () {
      placeNav();
      setHeaderBottom();
      if (!isPhone()) closeMenu();
    });
    window.addEventListener("orientationchange", function () {
      placeNav();
      setHeaderBottom();
    });
    window.addEventListener("scroll", setHeaderBottom, { passive: true });

    var burger = document.querySelector(".burger");
    if (burger) {
      burger.addEventListener(
        "click",
        function () {
          placeNav();
          requestAnimationFrame(setHeaderBottom);
        },
        true
      );
    }

    /* Close drawer after choosing a link */
    document.addEventListener("click", function (e) {
      if (!document.body.classList.contains("nav-open")) return;
      var a = e.target.closest("nav.nav a");
      if (a) closeMenu();
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") closeMenu();
    });
  });
})();
