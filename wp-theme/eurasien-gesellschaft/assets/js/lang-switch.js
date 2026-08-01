/**
 * Standalone language switcher (must not depend on theme.js succeeding).
 */
(function () {
  "use strict";

  function applyLang(lang) {
    var en = lang === "en";
    var root = document.documentElement;
    var body = document.body;
    if (!body) return;

    root.setAttribute("lang", lang);
    body.classList.toggle("lang-en", en);
    body.classList.toggle("lang-de", !en);

    document.querySelectorAll(".en").forEach(function (el) {
      if (en) {
        el.removeAttribute("hidden");
        el.setAttribute("aria-hidden", "false");
      } else {
        el.setAttribute("hidden", "hidden");
        el.setAttribute("aria-hidden", "true");
      }
    });
    document.querySelectorAll(".de").forEach(function (el) {
      if (en) {
        el.setAttribute("hidden", "hidden");
        el.setAttribute("aria-hidden", "true");
      } else {
        el.removeAttribute("hidden");
        el.setAttribute("aria-hidden", "false");
      }
    });

    document.querySelectorAll(".lang button[data-lang]").forEach(function (btn) {
      btn.setAttribute("aria-pressed", String(btn.getAttribute("data-lang") === lang));
    });

    try {
      localStorage.setItem("eg-lang", lang);
    } catch (e) {}
    root.setAttribute("data-eg-lang", lang);
  }

  function init() {
    document.querySelectorAll(".lang button[data-lang]").forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        applyLang(btn.getAttribute("data-lang") || "de");
      });
    });

    var saved = null;
    try {
      saved = localStorage.getItem("eg-lang");
    } catch (e) {}
    applyLang(saved === "en" ? "en" : "de");
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  window.egSetLang = applyLang;
})();
