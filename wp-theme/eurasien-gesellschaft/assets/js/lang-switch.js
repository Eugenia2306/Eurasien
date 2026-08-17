/**
 * Standalone language switcher (must not depend on theme.js succeeding).
 */
(function () {
  "use strict";

  function applyLang(lang) {
    if (lang !== "en" && lang !== "de") lang = "de";
    var en = lang === "en";
    var root = document.documentElement;
    var body = document.body;
    if (!body) return;

    root.setAttribute("lang", lang);
    root.setAttribute("data-eg-lang", lang);
    root.classList.toggle("eg-lang-en", en);
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
      var code = btn.getAttribute("data-lang");
      if (code === "en") btn.textContent = "EN";
      else if (code === "de") btn.textContent = "DE";
      btn.setAttribute("translate", "no");
      btn.setAttribute("aria-pressed", String(code === lang));
    });
    document.querySelectorAll(".lang").forEach(function (g) {
      g.setAttribute("translate", "no");
    });

    try {
      localStorage.setItem("eg-lang", lang);
    } catch (e) {}
    try {
      document.cookie = "eg_lang=" + lang + "; path=/; max-age=31536000; SameSite=Lax";
    } catch (e2) {}
    try {
      document.dispatchEvent(new CustomEvent("eg:lang", { detail: { lang: lang } }));
    } catch (e3) {}
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
    if (saved !== "en" && saved !== "de") {
      try {
        var m = document.cookie.match(/(?:^|;\s*)eg_lang=(en|de)(?:;|$)/);
        if (m) saved = m[1];
      } catch (e2) {}
    }
    applyLang(saved === "en" ? "en" : "de");
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  window.egSetLang = applyLang;
  window.EGLang = {
    set: function (l, opts) {
      applyLang(l);
    },
    get: function () {
      return document.documentElement.getAttribute("data-eg-lang") || "de";
    }
  };
})();
