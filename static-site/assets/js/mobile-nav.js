/**
 * Hardened phone navigation drawer.
 * Accordion sections: subsections closed by default.
 */
(function () {
  "use strict";
  var MQ = "(max-width: 900px)";

  function phone() {
    return window.matchMedia && window.matchMedia(MQ).matches;
  }

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function chromeHeight() {
    var ubar = $(".ubar");
    var hd = $(".hd");
    var h = 0;
    if (ubar) {
      /* include safe-area padding baked into fixed ubar */
      h += ubar.getBoundingClientRect().height || ubar.offsetHeight || 0;
    }
    if (hd) h += hd.offsetHeight || 0;
    return Math.max(Math.round(h), 96);
  }

  function applyChromeVars() {
    var h = chromeHeight();
    document.documentElement.style.setProperty("--eg-chrome-h", h + "px");
    document.documentElement.style.setProperty("--eg-header-bottom", h + "px");
  }

  function collapseAll(drawer) {
    if (!drawer) return;
    drawer.querySelectorAll(".eg-mobile-drawer__item.is-open").forEach(function (item) {
      item.classList.remove("is-open");
      var btn = item.querySelector(".eg-mobile-drawer__toggle");
      var sub = item.querySelector(".eg-mobile-drawer__sub");
      if (btn) btn.setAttribute("aria-expanded", "false");
      if (sub) sub.hidden = true;
    });
  }

  function ensureDrawer() {
    var existing = $("#eg-mobile-drawer");
    if (existing) {
      if (existing.querySelector(".eg-mobile-drawer__toggle")) return existing;
      existing.remove();
    }

    var nav = $("nav.nav");
    if (!nav) return null;

    var drawer = document.createElement("div");
    drawer.id = "eg-mobile-drawer";
    drawer.className = "eg-mobile-drawer";
    drawer.setAttribute("hidden", "");
    drawer.innerHTML =
      '<div class="eg-mobile-drawer__panel" role="dialog" aria-modal="true" aria-label="Menü">' +
      '<div class="eg-mobile-drawer__head">' +
      '<p class="eg-mobile-drawer__title">Menü</p>' +
      '<button type="button" class="eg-mobile-drawer__close" aria-label="Menü schließen">×</button>' +
      "</div>" +
      '<div class="eg-mobile-drawer__body"></div>' +
      "</div>";

    var body = drawer.querySelector(".eg-mobile-drawer__body");
    var items = nav.querySelectorAll(":scope > .nav-item");
    items.forEach(function (item, idx) {
      var block = document.createElement("div");
      block.className = "eg-mobile-drawer__item";
      var top = item.querySelector(":scope > .nav-link");
      var mega = item.querySelector(":scope > .mega");
      var labelHtml = top ? top.innerHTML : "Menü";
      var mainHref = top ? top.getAttribute("href") || "#" : "#";
      var subId = "eg-drawer-sub-" + idx;

      if (mega) {
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "eg-mobile-drawer__toggle";
        btn.setAttribute("aria-expanded", "false");
        btn.setAttribute("aria-controls", subId);
        btn.innerHTML =
          '<span class="eg-mobile-drawer__toggle-label">' +
          labelHtml +
          '</span><span class="eg-mobile-drawer__chev" aria-hidden="true"></span>';
        block.appendChild(btn);

        var sub = document.createElement("div");
        sub.className = "eg-mobile-drawer__sub";
        sub.id = subId;
        sub.hidden = true;

        /* Overview link = top-level page */
        var overview = document.createElement("a");
        overview.className = "eg-mobile-drawer__sublink eg-mobile-drawer__sublink--main";
        overview.href = mainHref;
        overview.innerHTML =
          '<span class="de">Übersicht</span><span class="en" hidden>Overview</span>';
        sub.appendChild(overview);

        mega.querySelectorAll("a").forEach(function (sa) {
          var b = document.createElement("a");
          b.className = "eg-mobile-drawer__sublink";
          b.href = sa.getAttribute("href") || "#";
          b.innerHTML = sa.innerHTML;
          Array.prototype.slice.call(sa.attributes).forEach(function (attr) {
            if (attr.name.indexOf("data-") === 0) {
              b.setAttribute(attr.name, attr.value);
            }
          });
          sub.appendChild(b);
        });
        block.appendChild(sub);

        btn.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          var open = block.classList.contains("is-open");
          collapseAll(drawer);
          if (!open) {
            block.classList.add("is-open");
            btn.setAttribute("aria-expanded", "true");
            sub.hidden = false;
          }
        });
      } else if (top) {
        var a = document.createElement("a");
        a.className = "eg-mobile-drawer__link";
        a.href = mainHref;
        a.innerHTML = labelHtml;
        block.appendChild(a);
      }

      body.appendChild(block);
    });

    document.body.appendChild(drawer);

    drawer.querySelector(".eg-mobile-drawer__close").addEventListener("click", close);
    drawer.addEventListener("click", function (e) {
      if (e.target === drawer) close();
    });
    body.addEventListener("click", function (e) {
      var a = e.target.closest("a");
      if (a) close();
    });

    return drawer;
  }

  function open() {
    if (!phone()) return;
    applyChromeVars();
    var drawer = ensureDrawer();
    if (!drawer) return;
    collapseAll(drawer);
    drawer.removeAttribute("hidden");
    document.body.classList.add("nav-open", "eg-drawer-open");
    document.documentElement.classList.add("nav-open", "eg-drawer-open");
    var burger = $(".burger");
    if (burger) burger.setAttribute("aria-expanded", "true");
  }

  function close() {
    var drawer = $("#eg-mobile-drawer");
    if (drawer) {
      collapseAll(drawer);
      drawer.setAttribute("hidden", "");
    }
    document.body.classList.remove("nav-open", "eg-drawer-open");
    document.documentElement.classList.remove("nav-open", "eg-drawer-open");
    var burger = $(".burger");
    if (burger) burger.setAttribute("aria-expanded", "false");
  }

  function toggle(e) {
    if (!phone()) return;
    if (e) {
      e.preventDefault();
      e.stopPropagation();
      if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    }
    if (document.body.classList.contains("eg-drawer-open")) close();
    else open();
  }

  function bind() {
    var burger = $(".burger");
    if (!burger || burger.getAttribute("data-eg-drawer") === "1") return;
    burger.setAttribute("data-eg-drawer", "1");
    var last = 0;
    function guardedToggle(e) {
      var now = Date.now();
      if (now - last < 400) {
        if (e) {
          e.preventDefault();
          e.stopPropagation();
          if (e.stopImmediatePropagation) e.stopImmediatePropagation();
        }
        return;
      }
      last = now;
      toggle(e);
    }
    burger.addEventListener("click", guardedToggle, true);
    burger.addEventListener(
      "touchend",
      function (e) {
        if (!phone()) return;
        guardedToggle(e);
      },
      { passive: false, capture: true }
    );
  }

  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  onReady(function () {
    applyChromeVars();
    bind();
    window.addEventListener("resize", function () {
      applyChromeVars();
      if (!phone()) close();
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") close();
    });
  });
})();
