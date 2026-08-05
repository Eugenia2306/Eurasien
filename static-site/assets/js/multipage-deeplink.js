/* Multipage deep-links from prototype 54 behaviours:
   - Home cooperation cards -> partner.html#partner-{key} (open accordion + scroll)
   - Mediathek "YouTube Channel" -> mediathek.html#mediathek-videoarchiv
*/
(function () {
  function activateFromHash() {
    var h = (location.hash || "").replace(/^#/, "");
    if (!h) return;
    var el = document.getElementById(h);
    if (!el) {
      // Fallback: partner key without prefix
      el = document.querySelector('[data-partner-key="' + h + '"]');
    }
    if (!el) return;

    if (el.tagName && el.tagName.toLowerCase() === "details") {
      el.open = true;
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
      setTimeout(activateFromHash, 50);
    });
  } else {
    setTimeout(activateFromHash, 50);
  }
  window.addEventListener("hashchange", activateFromHash);
})();
