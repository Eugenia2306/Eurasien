/**
 * Routes prototype login / membership / event payment UI to WordPress (/app/).
 * Does not charge on the static site. Loads after site-*.js.
 */
(function () {
  "use strict";
  var d = document;
  var app = window.EG_APP;
  if (!app || !app.enabled) return;

  function go(url) {
    if (!url) return;
    wlocation(url);
  }
  function wlocation(url) {
    try {
      window.location.assign(url);
    } catch (e) {
      window.location.href = url;
    }
  }

  function planCheckout(plan) {
    if (plan === "expert-yearly" || plan === "expert" || plan === "verein") {
      return app.checkoutVerein;
    }
    return app.checkoutReader;
  }

  function textOf(el) {
    return (el.textContent || "").replace(/\s+/g, " ").trim().toLowerCase();
  }

  function loginWithRedirect(target) {
    var dest = target || (app.account || "/app/membership-account/");
    return "/app/login/?redirect_to=" + encodeURIComponent(dest);
  }

  function gatedUrlForKey(key) {
    if (!app.gated) return null;
    if (key === "positionen" || key === "positions") return app.gated.positionen;
    if (key === "dossiers") return app.gated.dossiers;
    if (key === "studien" || key === "studies") return app.gated.studien;
    return null;
  }

  function resolveGatedFromText(t) {
    if (!t) return null;
    if (t.indexOf("position") >= 0) return gatedUrlForKey("positionen");
    if (t.indexOf("dossier") >= 0) return gatedUrlForKey("dossiers");
    if (t.indexOf("studien") >= 0 || t.indexOf("stud") >= 0) return gatedUrlForKey("studien");
    return null;
  }

  /* Rewrite locked-gate and members-area chrome links to gated WP URLs */
  function wireGatedLinks() {
    var map = [
      { match: /mitglieder\/positionen\.html/i, to: app.gated.positionen },
      { match: /mitglieder\/dossiers\.html/i, to: app.gated.dossiers },
      { match: /mitglieder\/studien\.html/i, to: app.gated.studien }
    ];
    [].slice.call(d.querySelectorAll("a[href]")).forEach(function (a) {
      var href = a.getAttribute("href") || "";
      for (var i = 0; i < map.length; i++) {
        if (map[i].match.test(href)) {
          a.setAttribute("href", map[i].to);
          a.setAttribute("data-eg-app", "gated");
          break;
        }
      }
    });
    [].slice.call(d.querySelectorAll('a[data-analytics="membership_click"]')).forEach(function (a) {
      var t = textOf(a);
      if (t.indexOf("mitgliederbereich") >= 0 || t.indexOf("members’ area") >= 0 || t.indexOf("members' area") >= 0) {
        a.setAttribute("href", app.gated.positionen);
        a.setAttribute("data-eg-app", "members-hub");
      }
    });
    /* Analysen .fmt-lock badges always open the WP libraries */
    [].slice.call(d.querySelectorAll("a.fmt-lock")).forEach(function (a) {
      var card = a.closest("[id]");
      var id = card ? card.id : "";
      var url = null;
      if (/positionen/i.test(id) || /positionen/i.test(a.getAttribute("href") || "")) {
        url = app.gated.positionen;
      } else if (/dossiers/i.test(id) || /dossiers/i.test(a.getAttribute("href") || "")) {
        url = app.gated.dossiers;
      } else if (/studien/i.test(id) || /studien/i.test(a.getAttribute("href") || "")) {
        url = app.gated.studien;
      }
      if (!url) url = resolveGatedFromText(textOf(card || a));
      if (!url) return;
      a.setAttribute("href", url);
      a.setAttribute("data-eg-app", "gated");
    });
  }

  /* Guest gate: Choose access / Mitglied werden -> brochure form; Login -> deep-link */
  function wireLockedGateCtas() {
    [].slice.call(d.querySelectorAll(".locked-gate")).forEach(function (gate) {
      var page = gate.closest(".page");
      var id = page && page.id ? page.id : "";
      var target = app.gated.positionen;
      if (/dossiers/i.test(id)) target = app.gated.dossiers;
      if (/studien/i.test(id)) target = app.gated.studien;
      [].slice.call(gate.querySelectorAll("a[href]")).forEach(function (a) {
        var t = textOf(a);
        if (t.indexOf("zugang w") >= 0 || t.indexOf("choose access") >= 0 || t.indexOf("mitglied werden") >= 0) {
          a.setAttribute("href", app.membership);
          a.setAttribute("data-eg-app", "signup");
          a.setAttribute("data-analytics", "membership_click");
          return;
        }
        if (t.indexOf("anmelden") >= 0 || t === "login" || t.indexOf("login") === 0) {
          a.setAttribute("href", loginWithRedirect(target));
          a.setAttribute("data-eg-app", "login");
          a.setAttribute("data-analytics", "login_click");
        }
      });
    });
  }

  /* Membership preview cards become openable links */
  function wireLockedGrid(hasMembership) {
    if (!app.gated) return;
    [].slice.call(d.querySelectorAll(".locked-grid .lock")).forEach(function (lock) {
      var h = lock.querySelector("h4");
      var url = resolveGatedFromText(textOf(h));
      if (!url) return;
      lock.classList.add("lock--linked");
      lock.setAttribute("role", "link");
      lock.setAttribute("tabindex", "0");
      lock.style.cursor = "pointer";
      if (hasMembership) {
        lock.classList.add("lock--open");
        var badge = lock.querySelector(".lock__b");
        if (badge) {
          badge.innerHTML =
            '<span class="de">Jetzt öffnen</span><span class="en" hidden>Open now</span>';
          try {
            if (document.documentElement.getAttribute("data-eg-lang") === "en") {
              var deEl = badge.querySelector(".de");
              var enEl = badge.querySelector(".en");
              if (deEl) deEl.hidden = true;
              if (enEl) enEl.hidden = false;
            }
          } catch (err) {}
        }
      }
      if (lock.getAttribute("data-eg-wired") === "1") return;
      lock.setAttribute("data-eg-wired", "1");
      function openLock(e) {
        if (e) e.preventDefault();
        go(url);
      }
      lock.addEventListener("click", openLock);
      lock.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          openLock();
        }
      });
    });
  }

  /* Analysen format cards (Positionen / Dossiers / Studien): whole card opens library */
  function wireFormatCards() {
    if (!app.gated) return;
    var map = {
      "an-fmt-positionen": app.gated.positionen,
      "an-fmt-dossiers": app.gated.dossiers,
      "an-fmt-studien": app.gated.studien
    };
    Object.keys(map).forEach(function (id) {
      var card = d.getElementById(id);
      if (!card) return;
      var url = map[id];
      var badge = card.querySelector("a.fmt-lock");
      if (badge) {
        badge.setAttribute("href", url);
        badge.setAttribute("data-eg-app", "gated");
      }
      card.classList.add("card--link", "card--gated");
      card.style.cursor = "pointer";
      card.setAttribute("role", "link");
      card.setAttribute("tabindex", "0");
      if (card.getAttribute("data-eg-card-wired") === "1") return;
      card.setAttribute("data-eg-card-wired", "1");
      function openCard(e) {
        if (e.target && e.target.closest("a") && !e.target.closest("a.fmt-lock")) return;
        e.preventDefault();
        go(url);
      }
      card.addEventListener("click", openCard);
      card.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          go(url);
        }
      });
    });
  }

  function unlockFmtLocks() {
    var checkSvg =
      '<svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><path d="M9.2 16.6 4.8 12.2l1.4-1.4 3 3 8-8 1.4 1.4z"/></svg>';
    [].slice.call(d.querySelectorAll("a.fmt-lock")).forEach(function (a) {
      var href = a.getAttribute("href") || "";
      a.classList.add("fmt-lock--open");
      a.classList.remove("fmt-lock--locked");
      a.setAttribute("data-analytics", "members_click");
      a.setAttribute("data-eg-access", "member");
      if (href) a.setAttribute("href", href);
      a.innerHTML =
        checkSvg +
        '<span class="de">Zugang freigeschaltet</span><span class="en" hidden>Access unlocked</span>';
      try {
        if (document.documentElement.getAttribute("data-eg-lang") === "en") {
          var deEl = a.querySelector(".de");
          var enEl = a.querySelector(".en");
          if (deEl) deEl.hidden = true;
          if (enEl) enEl.hidden = false;
        }
      } catch (err) {}
    });
  }

  function memberHasAccess(status) {
    if (!status || !status.loggedIn) return false;
    if (status.hasMembership) return true;
    if (status.levelIds && status.levelIds.length) return true;
    return false;
  }

  /* Login page form -> real /app/login/ */
  function wireLoginPage() {
    var page = d.getElementById("p-login");
    if (!page) return;
    var submit = page.querySelector(".btn.btn--accent");
    if (submit) {
      submit.addEventListener("click", function (e) {
        e.preventDefault();
        go(app.login);
      });
    }
    var forgot = d.getElementById("l-forgot");
    if (forgot) {
      forgot.setAttribute("href", app.lostPassword);
      forgot.addEventListener("click", function (e) {
        e.preventDefault();
        go(app.lostPassword);
      });
    }
  }

  /* Menu Login = /app/login/ (also works if HTML was already hard-wired) */
  function wireChromeLogin() {
    var loginUrl = app.login || loginWithRedirect(app.account || "/app/membership-account/");
    [].slice.call(d.querySelectorAll("a[href]")).forEach(function (a) {
      var href = a.getAttribute("href") || "";
      var t = textOf(a);
      var isLoginClick = a.getAttribute("data-analytics") === "login_click";
      var pointsAtAnmelden =
        /anmelden\.html(#|$|\?)/i.test(href) ||
        /\/anmelden\/?(\?|#|$)/i.test(href) ||
        /#p-login/i.test(href);
      var alreadyAppLogin = /\/app\/login\/?/i.test(href);
      var labelIsLogin =
        t === "anmelden" ||
        t === "login" ||
        t === "anmelden login" ||
        (t.indexOf("anmelden") === 0 && t.indexOf("veranstaltung") < 0 && t.indexOf("mitglied") < 0);
      if (!isLoginClick && !pointsAtAnmelden && !alreadyAppLogin && !labelIsLogin) return;
      if (d.getElementById("p-login") && a.closest("#p-login")) return;
      if (a.closest(".locked-gate")) return;
      /* Never send Login to WP home or the dead /anmelden/ stub. */
      if (
        href === "/" ||
        href === "/app/" ||
        href === "/app" ||
        /\/anmelden\/?$/i.test(href) ||
        pointsAtAnmelden ||
        isLoginClick ||
        labelIsLogin ||
        alreadyAppLogin
      ) {
        a.setAttribute("href", loginUrl);
        a.setAttribute("data-eg-app", "login");
        a.setAttribute("data-analytics", "login_click");
      }
    });
  }

  /* Join CTAs always open the brochure registration form (skip PMPro levels). */
  function membershipFormUrl(plan) {
    var base = (app.membership && String(app.membership).indexOf("mitgliedschaft") >= 0)
      ? String(app.membership).split("#")[0]
      : "/mitgliedschaft.html";
    var hash = "#membership-registration";
    if (plan === "expert" || plan === "verein" || plan === "2") {
      return base + "?plan=expert" + hash;
    }
    if (plan === "reader" || plan === "1") {
      return base + "?plan=reader" + hash;
    }
    return base + hash;
  }

  function wireSignupCtas() {
    var formUrl = membershipFormUrl();
    [].slice.call(d.querySelectorAll("a[data-membership-plan]")).forEach(function (a) {
      var plan = a.getAttribute("data-membership-plan") || "reader";
      var normalized = plan === "expert" || plan === "verein" ? "expert" : "reader";
      a.setAttribute("href", membershipFormUrl(normalized));
      a.setAttribute("data-eg-app", "membership-form");
      a.setAttribute("data-analytics", "membership_click");
      a.setAttribute("data-membership-plan", normalized);
    });
    [].slice.call(d.querySelectorAll("a[href]")).forEach(function (a) {
      var href = a.getAttribute("href") || "";
      var t = textOf(a);
      var isLogin =
        a.getAttribute("data-analytics") === "login_click" ||
        a.getAttribute("data-eg-app") === "login" ||
        /\/app\/login\/?/i.test(href) ||
        /anmelden\.html/i.test(href) ||
        t === "anmelden" ||
        t === "login" ||
        t === "anmelden login";
      if (isLogin) return;
      var isLevels = /membership-levels/i.test(href);
      var isBecome =
        t.indexOf("mitglied werden") >= 0 ||
        t.indexOf("become a member") >= 0 ||
        t.indexOf("zugang w") >= 0 ||
        t.indexOf("choose access") >= 0;
      if (a.hasAttribute("data-membership-plan")) return;
      if (!isLevels && !isBecome) return;
      if (a.getAttribute("data-eg-app") === "members-hub") return;
      a.setAttribute("href", formUrl);
      a.setAttribute("data-eg-app", "membership-form");
      a.setAttribute("data-analytics", "membership_click");
    });
  }

  function payMethodSelected(scope) {
    if (!scope) return false;
    var step = scope.querySelector('[data-step="payment"]');
    if (!step || step.hidden) return true;
    return !!step.querySelector('.pay__opt[aria-checked="true"]');
  }

  function collectMemberFields(scope) {
    var out = {};
    if (!scope) return out;
    var details = scope.querySelector('[data-step="details"]') || scope;
    [].slice.call(details.querySelectorAll("input, select, textarea")).forEach(function (f) {
      var key = f.getAttribute("name") || f.id || "";
      if (!key) return;
      if (f.type === "checkbox" || f.type === "radio") {
        if (f.checked) out[key] = f.value || "1";
      } else {
        out[key] = String(f.value || "").trim();
      }
    });
    /* Normalize common ids from the static application forms */
    if (!out.email) out.email = out["mr-mail"] || out["vm-mail"] || "";
    if (!out.first_name) out.first_name = out["mr-first"] || out["vm-first"] || "";
    if (!out.last_name) out.last_name = out["mr-last"] || out["vm-last"] || "";
    if (!out.password) out.password = out["mr-pw"] || "";
    if (!out.password2) out.password2 = out["mr-pw2"] || "";
    return out;
  }

  function memberHandoffUrl(plan, scope) {
    var base = planCheckout(plan);
    var fields = collectMemberFields(scope);
    var params = [];
    if (fields.email || fields.bemail || fields.user_email) {
      params.push("email=" + encodeURIComponent(fields.email || fields.bemail || fields.user_email));
    }
    var first = fields.first_name || fields.bfirstname || fields.firstname || fields.first;
    var last = fields.last_name || fields.blastname || fields.lastname || fields.last || fields.surname;
    if (first) params.push("first_name=" + encodeURIComponent(first));
    if (last) params.push("last_name=" + encodeURIComponent(last));
    try {
      sessionStorage.setItem(
        "eg_member_application",
        JSON.stringify({
          plan: plan,
          fields: fields,
          at: Date.now()
        })
      );
    } catch (err) {}
    if (!params.length) return base;
    return base + (base.indexOf("?") >= 0 ? "&" : "?") + params.join("&");
  }

  function loginChromeLinks() {
    return [].slice.call(
      d.querySelectorAll(
        'a[data-analytics="login_click"], a[data-analytics="logout_click"], a[data-eg-app="login"], a[data-eg-app="logout"]'
      )
    ).filter(function (a) {
      return !a.closest(".locked-gate");
    });
  }

  function setLinkLabel(a, de, en) {
    a.innerHTML =
      '<span class="de">' +
      de +
      '</span><span class="en" hidden>' +
      en +
      "</span>";
    try {
      if (document.documentElement.getAttribute("data-eg-lang") === "en") {
        var deEl = a.querySelector(".de");
        var enEl = a.querySelector(".en");
        if (deEl) deEl.hidden = true;
        if (enEl) enEl.hidden = false;
      }
    } catch (err) {}
  }

  /* If the member is already logged in, show Logout + account CTA (not Become a member) */
  function wireAuthChrome() {
    var url = app.authStatus;
    if (!url || !window.fetch) {
      wireLockedGrid(false);
      return;
    }
    fetch(url, {
      credentials: "include",
      headers: { Accept: "application/json" },
      cache: "no-store"
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (status) {
        if (!status || !status.loggedIn) {
          wireLockedGrid(false);
          return;
        }
        loginChromeLinks().forEach(function (a) {
          a.setAttribute("href", status.logoutUrl || app.logout);
          a.setAttribute("data-analytics", "logout_click");
          a.setAttribute("data-eg-app", "logout");
          setLinkLabel(a, "Abmelden", "Logout");
        });
        [].slice.call(d.querySelectorAll("a[href]")).forEach(function (a) {
          var t = textOf(a);
          var isSignup =
            t.indexOf("mitglied werden") >= 0 ||
            t.indexOf("become a member") >= 0 ||
            a.getAttribute("data-eg-app") === "signup";
          if (!isSignup) return;
          if (a.closest(".locked-gate")) return;
          if (memberHasAccess(status)) {
            a.setAttribute("href", status.membersUrl || (app.gated && app.gated.positionen) || app.account);
            a.setAttribute("data-eg-app", "members-hub");
            a.setAttribute("data-analytics", "members_click");
            setLinkLabel(a, "Mitgliederbereich", "Members' Area");
          } else {
            a.setAttribute("href", status.accountUrl || app.account);
            a.setAttribute("data-eg-app", "account");
            a.setAttribute("data-analytics", "account_click");
            setLinkLabel(a, "Mein Konto", "My Account");
          }
        });
        if (memberHasAccess(status)) {
          unlockFmtLocks();
          wireLockedGrid(true);
        } else {
          wireLockedGrid(false);
        }
      })
      .catch(function () {
        wireLockedGrid(false);
      });
  }

  function showMemberHandoffError(scope, message) {
    var details = scope && (scope.querySelector('[data-step="details"]') || scope);
    var err = details && details.querySelector("[data-formerr]");
    if (err) {
      var de = err.querySelector(".de");
      var en = err.querySelector(".en");
      if (de) de.textContent = message;
      if (en) en.textContent = message;
      err.classList.add("on");
      try {
        err.scrollIntoView({ behavior: "smooth", block: "center" });
      } catch (x) {}
    } else {
      window.alert(message);
    }
  }

  function postMemberHandoff(plan, scope, triggerEl) {
    var fields = collectMemberFields(scope);
    var level =
      plan === "expert-yearly" || plan === "expert" || plan === "verein" ? 2 : 1;
    var endpoint = app.memberHandoff || "/app/eg-member-handoff.php";
    if (endpoint.indexOf("http") !== 0) {
      endpoint = window.location.origin + endpoint;
    }

    try {
      sessionStorage.setItem(
        "eg_member_application",
        JSON.stringify({ plan: plan, fields: fields, at: Date.now() })
      );
    } catch (err) {}

      if (triggerEl) {
      triggerEl.setAttribute("aria-busy", "true");
      /* Do not disable before submit; it can cancel navigation in some browsers. */
    }

    /* Full-page POST so WordPress auth cookies are set on a real navigation */
    var form = d.createElement("form");
    form.method = "POST";
    form.action = endpoint;
    form.acceptCharset = "UTF-8";
    form.style.display = "none";
    form.setAttribute("data-eg-handoff", "1");

    function add(name, value) {
      var input = d.createElement("input");
      input.type = "hidden";
      input.name = name;
      input.value = value == null ? "" : String(value);
      form.appendChild(input);
    }

    add("redirect", "1");
    add("plan", plan);
    add("level", String(level));
    add("email", fields.email || "");
    add("password", fields.password || fields["mr-pw"] || "");
    add("password2", fields.password2 || fields["mr-pw2"] || "");
    add("first_name", fields.first_name || "");
    add("last_name", fields.last_name || "");
    try {
      add("fields_json", JSON.stringify(fields));
    } catch (e2) {}

    d.body.appendChild(form);
    form.submit();
  }

  function absoluteHandoffAction() {
    var endpoint = (app && app.memberHandoff) || "/app/eg-member-handoff.php";
    if (endpoint.indexOf("http") === 0) return endpoint;
    return window.location.origin + endpoint;
  }

  function forceHandoffSubmit(form) {
    if (!form) return;
    try {
      form.setAttribute("method", "post");
      form.method = "post";
      form.setAttribute("action", absoluteHandoffAction());
      form.action = absoluteHandoffAction();
    } catch (err) {}
    /* Bypass other submit listeners that might cancel navigation. */
    try {
      HTMLFormElement.prototype.submit.call(form);
    } catch (err2) {
      form.submit();
    }
  }

  function wireNativeHandoffForms() {
    [].slice.call(d.querySelectorAll("form[action*='eg-member-handoff'], #eg-form-reader, #eg-form-expert")).forEach(function (form) {
      if (form.getAttribute("data-eg-wired") === "1") return;
      form.setAttribute("data-eg-wired", "1");
      try {
        form.setAttribute("method", "post");
        form.setAttribute("action", absoluteHandoffAction());
        form.action = absoluteHandoffAction();
      } catch (absErr) {}

      form.addEventListener("submit", function (e) {
        var pw = form.querySelector('input[name="password"]');
        var pw2 = form.querySelector('input[name="password2"]');
        if (pw && pw2 && pw.value !== pw2.value) {
          e.preventDefault();
          showMemberHandoffError(form, "Passwords do not match.");
          return;
        }
        try {
          var payload = {
            plan: (form.querySelector('[name="plan"]') || {}).value || "reader-monthly",
            fields: {},
            at: Date.now()
          };
          [].slice.call(form.querySelectorAll("input,textarea,select")).forEach(function (el) {
            if (!el.name) return;
            if ((el.type === "checkbox" || el.type === "radio") && !el.checked) return;
            payload.fields[el.name] = el.value;
          });
          sessionStorage.setItem("eg_member_application", JSON.stringify(payload));
        } catch (err) {}
        try {
          form.setAttribute("action", absoluteHandoffAction());
          form.action = absoluteHandoffAction();
        } catch (abs2) {}
        /* Do NOT disable the submit button here. Disabling it during submit
           cancels the POST in Chrome/Edge and leaves users stuck. */
      });

      /* Click path: validate, then force native POST (avoids swallowed submits). */
      [].slice.call(form.querySelectorAll('button[type="submit"], input[type="submit"]')).forEach(function (btn) {
        if (btn.getAttribute("data-eg-pay-wired") === "1") return;
        btn.setAttribute("data-eg-pay-wired", "1");
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          if (typeof form.reportValidity === "function" && !form.reportValidity()) {
            return;
          }
          var pw = form.querySelector('input[name="password"]');
          var pw2 = form.querySelector('input[name="password2"]');
          if (pw && pw2 && pw.value !== pw2.value) {
            showMemberHandoffError(form, "Passwords do not match.");
            return;
          }
          btn.setAttribute("aria-busy", "true");
          forceHandoffSubmit(form);
        });
      });
    });
  }

  function validateMemberDetails(scope) {
    if (window.EG_MEMBERSHIP && typeof window.EG_MEMBERSHIP.validateDetails === "function") {
      return window.EG_MEMBERSHIP.validateDetails(scope);
    }
    if (!scope) return false;
    var details = scope.querySelector('[data-step="details"]') || scope;
    var ok = true;
    [].slice.call(details.querySelectorAll("[required]")).forEach(function (f) {
      var v = f.type === "checkbox" ? f.checked : String(f.value || "").trim() !== "";
      if (!v) ok = false;
    });
    var mail = details.querySelector('input[type="email"]');
    if (mail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(mail.value || "").trim())) ok = false;
    [].slice.call(details.querySelectorAll("[data-confirm]")).forEach(function (f) {
      var o = d.getElementById(f.getAttribute("data-confirm"));
      if (o && f.value !== o.value) ok = false;
    });
    if (!ok) {
      var err = details.querySelector("[data-formerr]");
      if (err) err.classList.add("on");
    }
    return ok;
  }

  function planFromMemberControl(el, scope) {
    var plan =
      (el && (el.getAttribute("data-plan") || el.getAttribute("data-step-next"))) ||
      (scope && scope.getAttribute("data-plan")) ||
      "reader-monthly";
    if (plan === "expert" || plan === "verein") return "expert-yearly";
    if (plan === "reader") return "reader-monthly";
    return plan;
  }

  function postEventHandoff(triggerEl) {
    var payload =
      window.EG_EVENT_REG && typeof window.EG_EVENT_REG.getPayload === "function"
        ? window.EG_EVENT_REG.getPayload()
        : null;
    if (!payload) return;

    var endpoint = app.eventHandoff || "/app/eg-event-handoff.php";
    if (endpoint.indexOf("http") !== 0) {
      endpoint = window.location.origin + endpoint;
    }

    if (triggerEl) {
      triggerEl.setAttribute("aria-busy", "true");
    }

    var form = d.createElement("form");
    form.method = "POST";
    form.action = endpoint;
    form.acceptCharset = "UTF-8";
    form.style.display = "none";
    form.setAttribute("data-eg-event-handoff", "1");

    function add(name, value) {
      var input = d.createElement("input");
      input.type = "hidden";
      input.name = name;
      input.value = value == null ? "" : String(value);
      form.appendChild(input);
    }

    add("eg_event_handoff", "1");
    add("event_id", payload.event_id || "");
    add("event_title", payload.event_title || "");
    add("event_date", payload.event_date || "");
    add("event_time_start", payload.event_time_start || "19:00");
    add("event_time_end", payload.event_time_end || "21:00");
    add("event_location", payload.event_location || "");
    add("unit_price", payload.unit_price || "10");
    add("qty", payload.qty || "1");
    add("first_name", payload.first_name || "");
    add("last_name", payload.last_name || "");
    add("email", payload.email || "");
    add("consent", payload.consent ? "1" : "");

    d.body.appendChild(form);
    form.submit();
  }

  function validateEventDetails() {
    if (window.EG_EVENT_REG && typeof window.EG_EVENT_REG.validate === "function") {
      return window.EG_EVENT_REG.validate();
    }
    var modal = d.getElementById("ev-regmodal");
    if (!modal) return false;
    return validateMemberDetails(modal.querySelector(".regmodal__dialog") || modal);
  }

  function handoffMember(el) {
    var scope = el.closest(".formcard") || el.closest(".joincard");
    if (!scope) return false;
    if (!validateMemberDetails(scope)) return true;
    var plan = planFromMemberControl(el, scope);
    postMemberHandoff(plan, scope, el);
    return true;
  }

  d.addEventListener(
    "click",
    function (e) {
      /* Native membership forms POST themselves; do not intercept their submit buttons. */
      if (e.target.closest("form[action*='eg-member-handoff'], form[action*='eg-event-handoff']")) {
        return;
      }
      var next = e.target.closest("[data-step-next]");
      if (next) {
        var nextScope = next.closest(".formcard[data-plan], .joincard[data-plan]");
        if (nextScope) {
          e.preventDefault();
          e.stopPropagation();
          if (e.stopImmediatePropagation) e.stopImmediatePropagation();
          handoffMember(next);
          return;
        }
      }
      var sub = e.target.closest("[data-form-submit]");
      if (sub) {
        var kind = sub.getAttribute("data-form-submit");
        if (kind === "member") {
          e.preventDefault();
          e.stopPropagation();
          if (e.stopImmediatePropagation) e.stopImmediatePropagation();
          var scope = sub.closest(".formcard") || sub.closest(".joincard");
          if (!scope) return;
          if (!validateMemberDetails(scope)) return;
          var plan = planFromMemberControl(sub, scope);
          postMemberHandoff(plan, scope, sub);
          return;
        }
        if (kind === "event") {
          e.preventDefault();
          e.stopPropagation();
          if (e.stopImmediatePropagation) e.stopImmediatePropagation();
          if (!validateEventDetails()) return;
          postEventHandoff(sub);
          return;
        }
      }
    },
    true
  );

  wireGatedLinks();
  wireFormatCards();
  wireLockedGateCtas();
  wireLoginPage();
  wireChromeLogin();
  wireSignupCtas();
  wireAuthChrome();
  wireNativeHandoffForms();

  try {
    var hq = new URLSearchParams(location.search || "");
    var he = hq.get("eg_handoff");
    if (he) {
      var msg =
        he === "exists" || he === "auth"
          ? "An account with this email already exists. Use the same password you set before, or log in first."
          : he === "password_mismatch"
            ? "Passwords do not match."
            : he === "need_form"
              ? "Please fill in all required fields, then click Continue to payment again."
              : he === "session"
                ? "Your payment session expired. Please submit the form again."
                : he === "stripe"
                  ? "Could not open Stripe checkout. Please try again."
                : "Could not start checkout. Please check your details and try again.";
      var card = d.querySelector("#eg-form-reader, #eg-form-expert, .formcard");
      showMemberHandoffError(card, msg);
      try {
        hq.delete("eg_handoff");
        var clean =
          location.pathname +
          (hq.toString() ? "?" + hq.toString() : "") +
          (location.hash || "#membership-registration");
        history.replaceState(null, "", clean);
      } catch (cleanErr) {}
    }
    var ee = hq.get("eg_event");
    if (ee) {
      var emsg =
        ee === "fields"
          ? "Please complete all event registration fields and try again."
          : ee === "stripe"
            ? "Could not open Stripe checkout for this event. Please try again."
            : "Could not start event registration. Please try again.";
      window.alert(emsg);
      try {
        hq.delete("eg_event");
        history.replaceState(
          null,
          "",
          location.pathname + (hq.toString() ? "?" + hq.toString() : "") + (location.hash || "")
        );
      } catch (eClean) {}
    }
  } catch (handoffErr) {}
})();
