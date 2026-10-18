/*
 * Eurasien Gesellschaft: floating contact button (no third-party service, no cookies).
 * "E-Mail schreiben" opens a short contact form in the panel; it is sent by the website's
 * WordPress (action eg_contact in mu-plugins/eg-interest-form.php) to the Society's inbox.
 * Channels appear only when configured below. Text follows the site's DE/EN toggle.
 */
(function () {
  "use strict";

  var CFG = {
    email: "kontakt@eurasien-gesellschaft.org",
    phone: "",     /* e.g. "+49 30 1234567". Shows a "call" button when set. */
    whatsapp: "4915223399100",   /* digits only, e.g. "4930123456". Shows a WhatsApp button when set. */
    endpoint: "/app/wp-admin/admin-post.php"
  };

  var d = document;
  if (d.getElementById("eg-contact")) return;

  function isEn() {
    return (d.documentElement.getAttribute("lang") || "de").slice(0, 2) === "en";
  }
  function bi(de, en) {
    var en_ = isEn();
    return '<span class="de"' + (en_ ? " hidden" : "") + ">" + de + "</span>" +
           '<span class="en"' + (en_ ? "" : " hidden") + ">" + en + "</span>";
  }

  var css =
    ".egc{position:fixed;left:24px;bottom:24px;z-index:119;font-family:inherit}" +
    ".egc__btn{display:flex;align-items:center;gap:8px;height:48px;padding:0 18px 0 8px;border:0;border-radius:999px;" +
      "background:#fff;color:var(--brand-blue,#0032A0);font:inherit;font-weight:700;font-size:.9rem;cursor:pointer;" +
      "box-shadow:0 0 0 1px rgba(0,50,160,.18),0 12px 28px rgba(11,35,56,.28);transition:transform .15s ease}" +
    ".egc__btn:hover{transform:translateY(-2px);background:#f4f7fc}" +
    ".egc__btn:focus-visible,.egc__opt:focus-visible,.egc__x:focus-visible,.egc__send:focus-visible,.egc__back:focus-visible{outline:3px solid var(--brand-gold,#E1A734);outline-offset:2px}" +
    ".egc__ico{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;" +
      "background:var(--brand-red,#DA291C);color:#fff;flex:0 0 auto}" +
    ".egc__ico svg{width:18px;height:18px}" +
    ".egc__panel{position:absolute;left:0;bottom:60px;width:330px;max-width:calc(100vw - 28px);max-height:calc(100vh - 120px);overflow:auto;" +
      "box-sizing:border-box;background:#fff;border:1px solid rgba(0,50,160,.12);border-radius:14px;padding:20px 18px 16px;" +
      "box-shadow:0 18px 50px rgba(11,35,56,.22)}" +
    ".egc__panel[hidden],.egc [hidden]{display:none}" +
    ".egc__t{margin:0 26px 6px 0;font-family:var(--serif,Georgia,serif);font-size:1.15rem;line-height:1.25;color:var(--brand-blue,#0032A0)}" +
    ".egc__p{margin:0 0 12px;font-size:.88rem;line-height:1.45;color:#4a5a6a}" +
    ".egc__opt{display:flex;align-items:center;gap:12px;width:100%;box-sizing:border-box;margin-top:8px;padding:10px 12px;" +
      "border:1px solid rgba(0,50,160,.16);border-radius:12px;background:#fff;color:var(--brand-blue,#0032A0);text-decoration:none;" +
      "text-align:left;font:inherit;font-weight:600;font-size:.92rem;line-height:1.25;cursor:pointer}" +
    ".egc__opt:hover{background:rgba(0,50,160,.05);border-color:rgba(0,50,160,.3)}" +
    ".egc__tile{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:50%;flex:0 0 auto}" +
    ".egc__tile svg{width:20px;height:20px}" +
    ".egc__tile--mail{background:var(--brand-red,#DA291C);color:#fff}" +
    ".egc__tile--wa{background:#25D366;color:#fff}" +
    ".egc__tile--wa svg{width:22px;height:22px}" +
    ".egc__tile--phone{background:var(--brand-blue,#0032A0);color:#fff}" +
    ".egc__sub{display:block;font-weight:400;font-size:.8rem;color:#5b6b7b;margin-top:2px;word-break:break-all}" +
    ".egc__form{position:relative}" +
    ".egc__f{display:block;margin:10px 0 0;font-size:.82rem;font-weight:600;color:var(--brand-blue,#0032A0)}" +
    ".egc__f input,.egc__f textarea{display:block;width:100%;box-sizing:border-box;margin-top:4px;padding:9px 10px;" +
      "border:1px solid rgba(0,50,160,.28);border-radius:8px;font:inherit;font-size:.9rem;font-weight:400;color:#1d2b3a;background:#fff}" +
    ".egc__f textarea{resize:vertical;min-height:88px}" +
    ".egc__f input:focus,.egc__f textarea:focus{outline:2px solid var(--brand-gold,#E1A734);outline-offset:1px;border-color:transparent}" +
    ".egc__c{display:flex;gap:8px;align-items:flex-start;margin:12px 0 0;font-size:.8rem;line-height:1.4;color:#4a5a6a}" +
    ".egc__c input{margin-top:2px;flex:0 0 auto}" +
    ".egc__c a,.egc__alt a{color:var(--brand-blue,#0032A0)}" +
    ".egc__send{display:block;width:100%;height:44px;margin-top:12px;border:0;border-radius:10px;background:var(--brand-red,#DA291C);" +
      "color:#fff;font:inherit;font-weight:700;font-size:.92rem;cursor:pointer}" +
    ".egc__send:hover{filter:brightness(.95)}.egc__send[disabled]{opacity:.6;cursor:wait}" +
    ".egc__back{margin-top:8px;padding:6px 0;border:0;background:none;color:var(--brand-blue,#0032A0);font:inherit;font-size:.85rem;cursor:pointer}" +
    ".egc__alt{margin:6px 0 0;font-size:.78rem;line-height:1.4;color:#5b6b7b;word-break:break-word}" +
    ".egc__msg{margin:12px 0 0;padding:10px 12px;border-radius:10px;font-size:.86rem;line-height:1.45}" +
    ".egc__msg--ok{background:#E2F2E6;color:#14532d}.egc__msg--err{background:#FDE4E1;color:#7f1d1d}" +
    ".egc__x{position:absolute;top:10px;right:10px;width:30px;height:30px;border:0;border-radius:50%;background:transparent;" +
      "color:#647585;font-size:20px;line-height:30px;cursor:pointer}" +
    ".egc__x:hover{background:rgba(0,50,160,.06)}" +
    ".egc__hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}" +
    "@media (max-width:760px){.egc{left:14px;bottom:84px}.egc__btn{width:52px;height:52px;padding:0;justify-content:center;background:var(--brand-red,#DA291C)}" +
      ".egc__btn:hover{background:var(--brand-red,#DA291C)}.egc__ico{width:52px;height:52px}.egc__ico svg{width:22px;height:22px}.egc__lbl{display:none}" +
      ".egc__panel{max-height:calc(100vh - 170px)}}" +
    "@media print{.egc{display:none}}";

  /* Chat bubble with dots (button), filled envelope (e-mail), handset (phone): Material Icons, Apache-2.0. */
  var icoChat = '<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 11H7V9h2v2zm4 0h-2V9h2v2zm4 0h-2V9h2v2z"/></svg>';
  var icoMail = '<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/></svg>';
  var icoPhone = '<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1l-2.3 2.2z"/></svg>';
  /* Official WhatsApp glyph (Simple Icons, CC0), shown white on WhatsApp green. */
  var icoWa = '<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>';

  function opt(href, icon, tile, label, sub, ext, key) {
    return '<a class="egc__opt" href="' + href + '"' + (ext ? ' target="_blank" rel="noopener"' : "") +
           ' data-analytics="contact_' + key + '"><span class="egc__tile egc__tile--' + tile + '">' + icon + "</span>" +
           "<span>" + label + '<span class="egc__sub">' + sub + "</span></span></a>";
  }

  var opts = "";
  if (CFG.email) {
    opts += '<button type="button" class="egc__opt" data-egc-form data-analytics="contact_email">' +
            '<span class="egc__tile egc__tile--mail">' + icoMail + "</span>" +
            "<span>" + bi("E-Mail schreiben", "Send an email") + '<span class="egc__sub">' +
            bi("Nachricht direkt hier schreiben", "Write your message right here") + "</span></span></button>";
  }
  if (CFG.phone) {
    opts += opt("tel:" + CFG.phone.replace(/[^\d+]/g, ""), icoPhone, "phone", bi("Anrufen", "Call us"), CFG.phone, false, "phone");
  }
  if (CFG.whatsapp) {
    var waShow = "+" + CFG.whatsapp.replace(/\D/g, "").replace(/^(\d{2})(\d{4})(\d+)$/, "$1 $2 $3");
    opts += opt("https://wa.me/" + CFG.whatsapp.replace(/\D/g, ""), icoWa, "wa", "WhatsApp", waShow, true, "whatsapp");
  }
  if (!opts) return;

  var form = !CFG.email ? "" :
    '<form class="egc__form" hidden>' +
      '<input type="hidden" name="action" value="eg_contact"><input type="hidden" name="ajax" value="1">' +
      '<input type="hidden" name="elapsed" value=""><input type="hidden" name="page" value="">' +
      '<div class="egc__hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>' +
      '<label class="egc__f">' + bi("Name", "Name") + ' <input type="text" name="name" maxlength="120" autocomplete="name" required></label>' +
      '<label class="egc__f">' + bi("E-Mail-Adresse", "Email address") + ' <input type="email" name="email" maxlength="190" autocomplete="email" inputmode="email" required></label>' +
      '<label class="egc__f">' + bi("Ihre Nachricht", "Your message") + ' <textarea name="message" rows="4" maxlength="3000" required></textarea></label>' +
      '<label class="egc__c"><input type="checkbox" name="consent" value="1" required><span>' +
        bi('Ich habe die <a href="/datenschutz.html">Datenschutzhinweise</a> zur Kenntnis genommen.', 'I have read the <a href="/datenschutz.html">privacy information</a>.') +
      "</span></label>" +
      '<div class="egc__msg egc__msg--err" role="alert" hidden></div>' +
      '<button type="submit" class="egc__send">' + bi("Nachricht senden", "Send message") + "</button>" +
      '<button type="button" class="egc__back">' + bi("← Zurück", "← Back") + "</button>" +
      '<p class="egc__alt">' + bi("Oder direkt per E-Mail an ", "Or email us directly at ") +
        '<a href="mailto:' + CFG.email + '">' + CFG.email + "</a></p>" +
    "</form>" +
    '<div class="egc__msg egc__msg--ok" role="status" hidden></div>';

  var style = d.createElement("style");
  style.textContent = css;
  d.head.appendChild(style);

  var wrap = d.createElement("div");
  wrap.className = "egc";
  wrap.id = "eg-contact";
  wrap.innerHTML =
    '<div class="egc__panel" id="eg-contact-panel" role="dialog" aria-labelledby="eg-contact-title" hidden>' +
      '<button type="button" class="egc__x" aria-label="Schließen / Close">&times;</button>' +
      '<p class="egc__t" id="eg-contact-title">' + bi("Kontakt aufnehmen", "Get in touch") + "</p>" +
      '<div class="egc__home"><p class="egc__p">' + bi(
        "Sie haben eine Frage zur Gesellschaft, zu Veranstaltungen oder zur Mitgliedschaft? Schreiben Sie uns, wir melden uns zeitnah.",
        "Questions about the Society, our events or membership? Write to us and we will get back to you soon.") + "</p>" +
      opts + "</div>" +
      form +
    "</div>" +
    '<button type="button" class="egc__btn" aria-expanded="false" aria-controls="eg-contact-panel" data-analytics="contact_open">' +
      '<span class="egc__ico">' + icoChat + '</span><span class="egc__lbl">' + bi("Kontakt", "Contact") + "</span>" +
    "</button>";

  var MSG = {
    sent: ["<strong>Vielen Dank für Ihre Nachricht.</strong> Wir melden uns zeitnah bei Ihnen.", "<strong>Thank you for your message.</strong> We will get back to you soon."],
    too_fast: ["Bitte warten Sie einen Moment und senden Sie die Nachricht dann erneut.", "Please wait a moment, then send your message again."],
    limit: ["Es wurden gerade sehr viele Nachrichten gesendet. Bitte versuchen Sie es später erneut oder schreiben Sie uns direkt per E-Mail.", "Many messages were sent just now. Please try again later or email us directly."],
    error: ["Ihre Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es erneut oder schreiben Sie uns direkt per E-Mail.", "Your message could not be sent. Please try again or email us directly."]
  };

  function mount() {
    d.body.appendChild(wrap);
    var btn = wrap.querySelector(".egc__btn");
    var panel = wrap.querySelector(".egc__panel");
    var close = wrap.querySelector(".egc__x");
    var home = wrap.querySelector(".egc__home");
    var f = wrap.querySelector(".egc__form");
    var ok = wrap.querySelector(".egc__msg--ok");
    var err = f ? f.querySelector(".egc__msg--err") : null;
    var t0 = 0;

    function view(which) {
      home.hidden = which !== "home";
      if (f) f.hidden = which !== "form";
      if (ok) ok.hidden = which !== "done";
      if (which === "form") {
        t0 = Date.now();
        if (err) err.hidden = true;
        var first = f.querySelector('[name="name"]');
        if (first) first.focus();
      }
    }
    function setOpen(open) {
      panel.hidden = !open;
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      if (open) {
        view("home");
        var first = panel.querySelector(".egc__opt");
        if (first) first.focus();
      }
    }
    btn.addEventListener("click", function () { setOpen(panel.hidden); });
    close.addEventListener("click", function () { setOpen(false); btn.focus(); });
    d.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !panel.hidden) { setOpen(false); btn.focus(); }
    });
    d.addEventListener("click", function (e) {
      if (!panel.hidden && !wrap.contains(e.target)) setOpen(false);
    });

    if (!f) return;
    wrap.querySelector("[data-egc-form]").addEventListener("click", function () { view("form"); });
    f.querySelector(".egc__back").addEventListener("click", function () { view("home"); });
    f.addEventListener("submit", function (e) {
      e.preventDefault();
      var send = f.querySelector(".egc__send");
      f.querySelector('[name="elapsed"]').value = String(Math.round((Date.now() - t0) / 1000));
      f.querySelector('[name="page"]').value = location.pathname;
      send.disabled = true;
      err.hidden = true;
      function show(res) {
        send.disabled = false;
        var code = res && res.code;
        if (res && res.ok) {
          ok.innerHTML = bi(MSG.sent[0], MSG.sent[1]);
          f.reset();
          view("done");
          return;
        }
        var m = MSG[code] || MSG.error;
        err.innerHTML = bi(m[0], m[1]);
        err.hidden = false;
      }
      fetch(CFG.endpoint, { method: "POST", body: new FormData(f), credentials: "same-origin" })
        .then(function (r) { return r.json().catch(function () { return { ok: false, code: "error" }; }); })
        .then(show)
        .catch(function () { show({ ok: false, code: "error" }); });
    });
  }

  if (d.readyState === "loading") d.addEventListener("DOMContentLoaded", mount);
  else mount();
})();
