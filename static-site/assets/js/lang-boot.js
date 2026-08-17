/* Apply saved language before first paint (load sync in <head>). */
(function () {
  try {
    var l = null;
    try {
      l = localStorage.getItem("eg-lang");
    } catch (e) {}
    if (l !== "en" && l !== "de") {
      var m = document.cookie.match(/(?:^|;\s*)eg_lang=(en|de)(?:;|$)/);
      if (m) l = m[1];
    }
    if (l !== "en" && l !== "de") return;
    var root = document.documentElement;
    root.setAttribute("lang", l);
    root.setAttribute("data-eg-lang", l);
    if (l === "en") root.classList.add("eg-lang-en");
    else root.classList.remove("eg-lang-en");
  } catch (e2) {}
})();
