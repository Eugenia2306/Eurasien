/**
 * Mobile scroll affordances: edge fades + draggable slider when content overflows.
 * Horizontal: filter chips / region tabs (wrapped)
 * Vertical: tall panels — in-place (no wrap, preserves modal layout)
 */
(function () {
  "use strict";

  var H_SELECTORS = [
    ".chips",
    ".kx-chips",
    ".sx-chips",
    ".cx-chips",
    "#p-regionen .reg-tabs",
  ];
  var V_SELECTORS = [
    ".reg-results",
    ".regmodal__dialog",
    ".search-dialog__panel",
    ".eg-mobile-drawer__body",
  ];

  function isEn() {
    return document.documentElement.getAttribute("data-eg-lang") === "en";
  }

  function hintText(axis) {
    if (axis === "x") {
      return isEn() ? "Swipe for more →" : "Wischen für mehr →";
    }
    return isEn() ? "Scroll for more ↓" : "Scrollen für mehr ↓";
  }

  function makeUi() {
    var ui = document.createElement("div");
    ui.className = "eg-scroll__ui";
    ui.setAttribute("aria-hidden", "true");
    ui.innerHTML =
      '<div class="eg-scroll__meter" role="presentation">' +
      '<div class="eg-scroll__thumb"></div>' +
      "</div>" +
      '<p class="eg-scroll__hint"></p>';
    return ui;
  }

  function wrapHorizontal(el) {
    if (el.closest(".eg-scroll")) return el.closest(".eg-scroll");
    var shell = document.createElement("div");
    shell.className = "eg-scroll eg-scroll--x";
    el.parentNode.insertBefore(shell, el);
    shell.appendChild(el);
    el.classList.add("eg-scroll__pane");
    shell.appendChild(makeUi());
    return shell;
  }

  function enhanceVerticalInPlace(el) {
    if (el.classList.contains("eg-scroll")) return el;
    el.classList.add("eg-scroll", "eg-scroll--y", "eg-scroll--inplace");
    el.classList.add("eg-scroll__pane");
    if (!el.querySelector(":scope > .eg-scroll__ui")) {
      el.appendChild(makeUi());
    }
    return el;
  }

  function update(shell) {
    var axis = shell.classList.contains("eg-scroll--y") ? "y" : "x";
    var pane = axis === "y" && shell.classList.contains("eg-scroll--inplace")
      ? shell
      : shell.querySelector(".eg-scroll__pane");
    var thumb = shell.querySelector(".eg-scroll__thumb");
    var hint = shell.querySelector(".eg-scroll__hint");
    if (!pane) return;

    var scrollPos = axis === "x" ? pane.scrollLeft : pane.scrollTop;
    var client = axis === "x" ? pane.clientWidth : pane.clientHeight;
    var scrollSize = axis === "x" ? pane.scrollWidth : pane.scrollHeight;
    var max = Math.max(scrollSize - client, 0);
    var overflow = max > 8;

    shell.classList.toggle("is-scrollable", overflow);
    if (!overflow) {
      shell.classList.remove("has-more-start", "has-more-end");
      if (thumb) {
        thumb.style.left = "0";
        thumb.style.top = "0";
        thumb.style.width = "100%";
        thumb.style.height = "100%";
      }
      if (hint) hint.textContent = "";
      return;
    }

    shell.classList.toggle("has-more-start", scrollPos > 4);
    shell.classList.toggle("has-more-end", scrollPos < max - 4);

    var ratio = client / scrollSize;
    var progress = scrollPos / max;
    var thumbPct = Math.max(ratio * 100, 22);
    var offsetPct = progress * (100 - thumbPct);

    if (thumb) {
      if (axis === "x") {
        thumb.style.top = "0";
        thumb.style.height = "100%";
        thumb.style.width = thumbPct + "%";
        thumb.style.left = offsetPct + "%";
      } else {
        thumb.style.left = "0";
        thumb.style.width = "100%";
        thumb.style.height = thumbPct + "%";
        thumb.style.top = offsetPct + "%";
      }
    }

    if (hint) {
      var showEnd = scrollPos < max - 4;
      hint.textContent = showEnd ? hintText(axis) : (isEn() ? "← back" : "← zurück");
    }
  }

  function bindMeterDrag(shell, pane) {
    var meter = shell.querySelector(".eg-scroll__meter");
    if (!meter || meter.dataset.egBound) return;
    meter.dataset.egBound = "1";

    function seek(clientX, clientY) {
      var axis = shell.classList.contains("eg-scroll--y") ? "y" : "x";
      var rect = meter.getBoundingClientRect();
      var t =
        axis === "x"
          ? (clientX - rect.left) / Math.max(rect.width, 1)
          : (clientY - rect.top) / Math.max(rect.height, 1);
      t = Math.min(1, Math.max(0, t));
      if (axis === "x") {
        pane.scrollLeft = t * Math.max(pane.scrollWidth - pane.clientWidth, 0);
      } else {
        pane.scrollTop = t * Math.max(pane.scrollHeight - pane.clientHeight, 0);
      }
    }

    meter.addEventListener("pointerdown", function (e) {
      if (!shell.classList.contains("is-scrollable")) return;
      e.preventDefault();
      meter.setPointerCapture(e.pointerId);
      seek(e.clientX, e.clientY);
    });
    meter.addEventListener("pointermove", function (e) {
      if (!meter.hasPointerCapture(e.pointerId)) return;
      seek(e.clientX, e.clientY);
    });
  }

  function bind(shell, pane) {
    if (shell.dataset.egScrollReady) return;
    shell.dataset.egScrollReady = "1";
    bindMeterDrag(shell, pane);

    var onScroll = function () {
      update(shell);
    };
    pane.addEventListener("scroll", onScroll, { passive: true });
    update(shell);

    if (typeof ResizeObserver !== "undefined") {
      try {
        new ResizeObserver(onScroll).observe(pane);
      } catch (e) {}
    }
    window.addEventListener("resize", onScroll, { passive: true });
    document.addEventListener("eg-lang-change", onScroll);
  }

  function enhanceOne(el, axis) {
    if (!el || el.dataset.egScrollCue === "1") return;
    el.dataset.egScrollCue = "1";

    var shell;
    var pane;
    if (axis === "x") {
      shell = wrapHorizontal(el);
      pane = el;
    } else {
      shell = enhanceVerticalInPlace(el);
      pane = el;
    }
    bind(shell, pane);
  }

  function scan() {
    H_SELECTORS.forEach(function (sel) {
      document.querySelectorAll(sel).forEach(function (el) {
        enhanceOne(el, "x");
      });
    });
    V_SELECTORS.forEach(function (sel) {
      document.querySelectorAll(sel).forEach(function (el) {
        enhanceOne(el, "y");
      });
    });
  }

  var scanTimer = null;
  function scheduleScan() {
    if (scanTimer) return;
    scanTimer = setTimeout(function () {
      scanTimer = null;
      scan();
    }, 80);
  }

  function boot() {
    scan();
    if (typeof MutationObserver !== "undefined") {
      new MutationObserver(scheduleScan).observe(document.documentElement, {
        childList: true,
        subtree: true,
      });
    }
    setTimeout(scan, 500);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
