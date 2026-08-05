
(function(){
  "use strict";
  var d = document, root = d.documentElement;
  root.classList.remove('no-js'); root.classList.add('js');

  /* ---------- Analytics (GA4-ready placeholder) ----------
     Prototype: logs to console. Once GA4 is enabled in <head>,
     window.gtag exists and events are forwarded automatically.
     No data is sent in the prototype. */
  function trackEvent(action, detail){
    try{
      if(typeof window.gtag === 'function'){ window.gtag('event', action, detail ? {detail: detail} : {}); }
      if(window.console && console.debug){ console.debug('[trackEvent]', action, detail || ''); }
    }catch(e){}
  }
  window.trackEvent = trackEvent;

  /* Delegated tracking: any [data-analytics] element + external YouTube/LinkedIn links */
  d.addEventListener('click', function(e){
    var el = e.target.closest('[data-analytics]');
    if(el){ trackEvent(el.getAttribute('data-analytics'), el.getAttribute('href') || el.textContent.trim().slice(0,40)); }
    var a = e.target.closest('a[href]');
    if(a && !a.hasAttribute('data-analytics')){
      var h = a.getAttribute('href') || '';
      if(/youtube\.com|youtu\.be/.test(h)) trackEvent('youtube_click', h);
      else if(/linkedin\.com/.test(h)) trackEvent('linkedin_source_click', h);
    }
  });

  /* ---------- Header height -> mobile nav overlay offset ---------- */
  var hd = d.querySelector('.hd');
  function navOffset(){
    if(!hd || window.innerWidth > 1260) return;
    var nav = d.querySelector('.nav');
    if(!nav) return;
    /* .nav is position:fixed, but .hd (backdrop-filter) is its containing block,
       so express the offset relative to that block rather than the viewport. */
    nav.style.top = '0px';
    var cbTop = nav.getBoundingClientRect().top;
    nav.style.top = Math.round(hd.getBoundingClientRect().bottom - cbTop) + 'px';
  }

  /* ---------- Pending post-navigation actions (filters, region select, focus) ---------- */
  var pending = null;
  function runPending(){
    if(!pending) return false;
    var p = pending; pending = null;
    if(p.ev) applyEvFilter(p.ev);
    if(p.an) applyAnFilter(p.an);
    if(p.region) selectRegion(p.region);
    if(p.focus){
      var t = d.getElementById(p.focus);
      if(t){
        var prev = d.querySelectorAll('.is-focused'); for(var q=0;q<prev.length;q++){ prev[q].classList.remove('is-focused'); }
        t.classList.add('is-focused');
        if(t.getAttribute('tabindex') === null){ t.setAttribute('tabindex','-1'); }
        try{ t.focus({preventScroll:true}); }catch(e){}
        t.scrollIntoView({behavior:'smooth', block:'center'});
        setTimeout(function(){ t.classList.remove('is-focused'); }, 3500);
        return true;
      }
    }
    if(p.partner){
      var acc = d.querySelector('[data-partner-key="' + p.partner + '"]');
      if(acc){
        if(!acc.open){ acc.open = true; }
        var rmP = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        try{ acc.scrollIntoView({behavior: rmP ? 'auto' : 'smooth', block: 'start'}); }catch(e){ acc.scrollIntoView(); }
        var sm = acc.querySelector('summary');
        if(sm){ try{ sm.focus({preventScroll:true}); }catch(e){} }
        return true;
      }
    }
    if(p.scrollTo){
      var st = d.getElementById(p.scrollTo);
      if(st){
        var rmS = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        try{ st.scrollIntoView({behavior: rmS ? 'auto' : 'smooth', block: 'start'}); }catch(e){ st.scrollIntoView(); }
        return true;
      }
    }
    return false;
  }

  /* ---------- Route change: close menu, run pending actions, scroll ---------- */
  function afterNav(){
    d.body.classList.remove('nav-open');
    d.documentElement.classList.remove('nav-open');
    var b = d.querySelector('.burger'); if(b) b.setAttribute('aria-expanded','false');
    if(!runPending()) window.scrollTo(0,0);
  }
  window.addEventListener('hashchange', afterNav);

  /* Internal page links may carry data-evfilter / data-anfilter / data-region / data-focus.
     Record them so the following hashchange applies them; handle the "already on this page" case. */
  d.addEventListener('click', function(e){
    var a = e.target.closest('a[href^="#p-"]'); if(!a) return;
    var page = a.getAttribute('href').slice(1);
    pending = {
      page: page,
      ev: a.getAttribute('data-evfilter'),
      an: a.getAttribute('data-anfilter'),
      region: a.getAttribute('data-region'),
      focus: a.getAttribute('data-focus'),
      partner: a.getAttribute('data-partner-target'),
      scrollTo: a.getAttribute('data-scrollto')
    };
    if(location.hash === '#' + page){
      setTimeout(function(){
        d.body.classList.remove('nav-open');
        d.documentElement.classList.remove('nav-open');
        var b = d.querySelector('.burger'); if(b) b.setAttribute('aria-expanded','false');
        if(!runPending()) window.scrollTo(0,0);
      }, 0);
    }
  });

  /* ---------- Language switch (DE default; EN spans hidden -> works without JS) ---------- */
  var langBtns = d.querySelectorAll('.lang button[data-lang]');
  function setLang(l){
    var en = (l === 'en');
    d.querySelectorAll('.en').forEach(function(el){ el.hidden = !en; });
    d.querySelectorAll('.de').forEach(function(el){ el.hidden = en; });
    root.setAttribute('lang', l);
    langBtns.forEach(function(b){ b.setAttribute('aria-pressed', String(b.getAttribute('data-lang') === l)); });
  }
  langBtns.forEach(function(b){ b.addEventListener('click', function(){ setLang(b.getAttribute('data-lang')); }); });

  /* ---------- Mobile menu ---------- */
  var burger = d.querySelector('.burger');
  if(burger){ burger.addEventListener('click', function(){
    var open = d.body.classList.toggle('nav-open');
    d.documentElement.classList.toggle('nav-open', open);
    burger.setAttribute('aria-expanded', String(open));
    if(open) navOffset();
  }); }
  window.addEventListener('resize', function(){ if(d.body.classList.contains('nav-open')) navOffset(); });
  window.addEventListener('orientationchange', function(){ if(d.body.classList.contains('nav-open')) navOffset(); });

  /* ---------- Generic chip filter ---------- */
  function matchItem(it, f){
    return (f === 'all')
      || (it.getAttribute('data-status') === f)
      || (it.getAttribute('data-type') === f)
      || (it.getAttribute('data-cat') === f);
  }

  /* Events filter (grouped by year), shared by chips and nav links */
  var evFilterEl = d.getElementById('ev-filter');
  var evItems = [].slice.call(d.querySelectorAll('#p-veranstaltungen .ev'));
  var evGroups = [].slice.call(d.querySelectorAll('#p-veranstaltungen .ev-group'));
  var evEmpty = d.getElementById('ev-empty');
  function applyEvFilter(f){
    var shown = 0;
    if(evFilterEl){ evFilterEl.querySelectorAll('.chip').forEach(function(c){ c.setAttribute('aria-pressed', String(c.getAttribute('data-f') === f)); }); }
    evItems.forEach(function(it){ var ok = matchItem(it, f); it.hidden = !ok; if(ok) shown++; });
    evGroups.forEach(function(g){ g.hidden = !g.querySelector('.ev:not([hidden])'); });
    if(evEmpty) evEmpty.hidden = (shown > 0);
    trackEvent('event_filter', f);
  }
  if(evFilterEl){
    evFilterEl.addEventListener('click', function(e){
      var btn = e.target.closest('.chip'); if(!btn) return;
      applyEvFilter(btn.getAttribute('data-f'));
    });
  }

  /* Contributions / source-inventory filter (no groups; empty state for categories w/o rows) */
  var anFilterEl = d.getElementById('an-filter');
  var anItems = [].slice.call(d.querySelectorAll('#an-list .srcrow'));
  var anEmpty = d.getElementById('an-empty');
  function applyAnFilter(f){
    var shown = 0;
    if(anFilterEl){ anFilterEl.querySelectorAll('.chip').forEach(function(c){ c.setAttribute('aria-pressed', String(c.getAttribute('data-f') === f)); }); }
    anItems.forEach(function(it){ var ok = matchItem(it, f); it.hidden = !ok; if(ok) shown++; });
    if(anEmpty) anEmpty.hidden = (shown > 0);
    trackEvent('analysis_filter', f);
  }
  if(anFilterEl){
    anFilterEl.addEventListener('click', function(e){
      var btn = e.target.closest('.chip'); if(!btn) return;
      applyAnFilter(btn.getAttribute('data-f'));
    });
  }

  /* ---------- Regions: list + schematic map zones drive the detail panel ---------- */
  var regRoot = d.getElementById('p-regionen');
  function regName(r){
    var b = regRoot.querySelector('.reg-btn[data-region="'+r+'"]'); if(!b) return {de:r,en:r};
    var full = b.querySelector('.reg-btn__full') || b;
    var d1=full.querySelector('.de'), e1=full.querySelector('.en');
    return {de:d1?d1.textContent.trim():r, en:e1?e1.textContent.trim():r};
  }
  function regMembers(r){
    var de=[], en=[];
    regRoot.querySelectorAll('.reg-countries[data-region="'+r+'"] .country-chip').forEach(function(ch){
      if(ch.getAttribute('data-country')==='all') return;
      var cd=ch.querySelector('.de'), ce=ch.querySelector('.en');
      if(cd) de.push(cd.textContent.trim());
      if(ce) en.push(ce.textContent.trim());
    });
    return {de:de.join(', '), en:en.join(', ')};
  }
  function ctyName(r, country){
    var chip = regRoot.querySelector('.reg-countries[data-region="'+r+'"] .country-chip[data-country="'+country+'"]');
    if(!chip) return {de:country, en:country};
    var cd=chip.querySelector('.de'), ce=chip.querySelector('.en');
    return {de:cd?cd.textContent.trim():country, en:ce?ce.textContent.trim():country};
  }
  function setBi(el, de, en){ if(!el) return; var a=el.querySelector('.de'), b=el.querySelector('.en'); if(a) a.textContent=de; if(b) b.textContent=en; }
  function updateActiveBar(r){
    var nm=regName(r), mem=regMembers(r);
    setBi(regRoot.querySelector('.regions-explorer__active-name'), nm.de, nm.en);
    setBi(regRoot.querySelector('.regions-explorer__active-members'), mem.de, mem.en);
  }
  /* ---------- Coordinated region state: one region + country + tab drives one render ---------- */
  var stateRegion = 'europa', stateCountry = 'all', stateTab = 'all';
  function setChips(region, country){
    var cc = regRoot.querySelector('.reg-countries[data-region="' + region + '"]'); if(!cc) return;
    cc.querySelectorAll('.country-chip').forEach(function(ch){
      var on = ch.getAttribute('data-country') === country;
      ch.classList.toggle('is-active', on); ch.setAttribute('aria-pressed', String(on));
    });
  }
  function visCards(ap){ return [].slice.call(ap.querySelectorAll('.reg-cards-grid > .card')).filter(function(c){ return !c.classList.contains('cc-hide'); }); }
  function langIsEn(){ var b = d.querySelector('[data-lang="en"]'); return !!(b && b.getAttribute('aria-pressed') === 'true'); }
  function setMoreLabel(more, expanded){
    var de = more.querySelector('.de'), en = more.querySelector('.en');
    if(de) de.textContent = expanded ? 'Weniger anzeigen' : 'Mehr anzeigen';
    if(en) en.textContent = expanded ? 'Show less' : 'Show more';
  }
  function ensureMore(ap){
    var more = ap.querySelector('.reg-more');
    if(more) return more;
    if(visCards(ap).length <= 12) return null;
    more = d.createElement('button');
    more.type = 'button'; more.className = 'btn btn--ghost btn--sm reg-more'; more.hidden = true;
    var en = langIsEn();
    var sde = d.createElement('span'); sde.className = 'de'; sde.textContent = 'Mehr anzeigen'; sde.hidden = en;
    var sen = d.createElement('span'); sen.className = 'en'; sen.textContent = 'Show more'; sen.hidden = !en;
    more.appendChild(sde); more.appendChild(sen);
    var grid = ap.querySelector('.reg-cards-grid');
    if(grid && grid.parentNode) grid.parentNode.insertBefore(more, grid.nextSibling); else ap.appendChild(more);
    return more;
  }
  function applyRegMore(ap){
    if(!ap) return;
    var grid = ap.querySelector('.reg-cards-grid'); if(!grid) return;
    var more = ensureMore(ap);
    var vis = visCards(ap); var CAP = 12;
    var expanded = grid.classList.contains('is-expanded');
    if(vis.length <= CAP){ expanded = false; grid.classList.remove('is-expanded'); }
    vis.forEach(function(c, idx){ c.classList.toggle('cap-hide', !expanded && idx >= CAP); });
    if(!more) return;
    if(vis.length > CAP){ more.hidden = false; setMoreLabel(more, expanded); }
    else { more.hidden = true; setMoreLabel(more, false); }
  }
  function chipEl(region, country){
    return regRoot.querySelector('.reg-countries[data-region="' + region + '"] .country-chip[data-country="' + country + '"]');
  }
  function setHidden(el, hide){ if(!el) return; if(hide){ el.style.setProperty('display','none','important'); el.setAttribute('hidden',''); } else { el.style.removeProperty('display'); el.removeAttribute('hidden'); } }
  function directEyebrow(panel){
    if(!panel) return null;
    var kids = panel.children;
    for(var i = 0; i < kids.length; i++){ if(kids[i].tagName === 'P' && kids[i].classList.contains('eyebrow')) return kids[i]; }
    return null;
  }
  function itemMatches(el, country){
    if(country === 'all') return true;
    var raw = el.getAttribute('data-countries') || el.getAttribute('data-country') || 'regional';
    return raw.split(',').indexOf(country) !== -1;
  }
  function headFor(region, country, tab){
    var nm = regName(region);
    var isAll = (!country || country === 'all');
    var name = isAll ? nm : ctyName(region, country);
    var kind = 'country';
    if(!isAll){ var ch = chipEl(region, country); if(ch && ch.getAttribute('data-kind')) kind = ch.getAttribute('data-kind'); }
    var de, en;
    if(tab === 'analysen'){ de = 'Analysen zu ' + name.de; en = 'Analysis on ' + name.en; }
    else if(tab === 'veranstaltungen'){ de = 'Veranstaltungen zu ' + name.de; en = 'Events on ' + name.en; }
    else if(tab === 'profiles'){
      if(isAll){ de = 'Länder- und Raumprofile: ' + nm.de; en = 'Country and area profiles: ' + nm.en; }
      else if(kind === 'area'){ de = 'Raumprofil: ' + name.de; en = 'Area profile: ' + name.en; }
      else if(kind === 'union'){ de = 'Profil: ' + name.de; en = 'Profile: ' + name.en; }
      else { de = 'Länderprofil: ' + name.de; en = 'Country profile: ' + name.en; }
    } else { de = 'Übersicht zu ' + name.de; en = 'Overview of ' + name.en; }
    return { de: de, en: en };
  }
  function eyebrowFor(tab){
    if(tab === 'analysen') return { de: 'Analysen', en: 'Analysis' };
    if(tab === 'veranstaltungen') return { de: 'Veranstaltungen', en: 'Events' };
    if(tab === 'profiles') return { de: 'Länder & Räume', en: 'Countries & areas' };
    return { de: 'Überblick', en: 'Overview' };
  }
  function updateHead(region, country, tab){
    var h = headFor(region, country, tab), e = eyebrowFor(tab);
    setBi(regRoot.querySelector('.reg-content__title'), h.de, h.en);
    setBi(regRoot.querySelector('.reg-content__eyebrow'), e.de, e.en);
    var reset = regRoot.querySelector('[data-reg-reset]');
    if(reset) reset.classList.toggle('on', !!(country && country !== 'all'));
  }
  function renderProfiles(region, country, tab){
    var pp = regRoot.querySelector('.reg-ppanel[data-region="' + region + '"]'); if(!pp) return 0;
    var count = 0;
    pp.querySelectorAll('.faq').forEach(function(fq){
      var ok = itemMatches(fq, country);
      setHidden(fq, !ok);
      if(!ok) fq.open = false;
      if(ok) count++;
    });
    var isFocus = (tab === 'profiles'), showEmpty = isFocus && count === 0;
    var eb = directEyebrow(pp); setHidden(eb, !(count > 0 || showEmpty));
    var empty = pp.querySelector('.reg-profile-empty'); setHidden(empty, !showEmpty);
    return count;
  }
  function renderAnalyses(region, country, tab){
    var ap = regRoot.querySelector('.reg-apanel[data-region="' + region + '"]'); if(!ap) return 0;
    var count = 0;
    ap.querySelectorAll('.reg-cards-grid > .card').forEach(function(c){
      var ok = itemMatches(c, country);
      c.classList.toggle('cc-hide', !ok);
      if(ok) count++;
    });
    var isFocus = (tab === 'analysen'), showEmpty = isFocus && count === 0;
    var eb = directEyebrow(ap); setHidden(eb, !(count > 0 || showEmpty));
    var empty = ap.querySelector('.reg-country-empty'); setHidden(empty, !showEmpty);
    applyRegMore(ap);
    return count;
  }
  function renderEvents(region, country, tab){
    var ev = regRoot.querySelector('.reg-events[data-region="' + region + '"]'); if(!ev) return 0;
    var count = 0;
    ev.querySelectorAll('.rel-list--events > li').forEach(function(li){
      var ok = itemMatches(li, country);
      setHidden(li, !ok);
      if(ok) count++;
    });
    var isFocus = (tab === 'veranstaltungen'), showEmpty = isFocus && count === 0;
    var eb = directEyebrow(ev); setHidden(eb, !(count > 0 || showEmpty));
    var note = ev.querySelector('.rel-note'); setHidden(note, !(count > 0));
    var ul = ev.querySelector('.rel-list--events'); setHidden(ul, !(count > 0));
    var all = ev.querySelector('.rel-ev-all'); setHidden(all, !(count > 0));
    var empty = ev.querySelector('.reg-event-empty'); setHidden(empty, !showEmpty);
    return count;
  }
  function renderRegions(){
    if(!regRoot) return;
    regRoot.querySelectorAll('.reg-panel, .reg-apanel, .reg-events, .reg-ppanel').forEach(function(p){
      p.classList.toggle('on', p.getAttribute('data-region') === stateRegion);
    });
    regRoot.querySelectorAll('.reg-zone').forEach(function(n){ n.classList.toggle('on', n.getAttribute('data-region') === stateRegion); });
    regRoot.querySelectorAll('.reg-btn').forEach(function(b){
      var on = b.getAttribute('data-region') === stateRegion;
      b.setAttribute('aria-pressed', String(on)); b.setAttribute('aria-selected', String(on));
    });
    var content = regRoot.querySelector('.reg-content'); if(content) content.setAttribute('data-tab', stateTab);
    regRoot.querySelectorAll('.reg-tab').forEach(function(b){ var on = b.getAttribute('data-tab') === stateTab; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', String(on)); });
    setChips(stateRegion, stateCountry);
    renderProfiles(stateRegion, stateCountry, stateTab);
    renderAnalyses(stateRegion, stateCountry, stateTab);
    renderEvents(stateRegion, stateCountry, stateTab);
    updateActiveBar(stateRegion);
    updateHead(stateRegion, stateCountry, stateTab);
  }
  /* Public wrappers kept for the search dropdown and hash-router (do not rename) */
  function selectRegion(r){ stateRegion = r; stateCountry = 'all'; renderRegions(); trackEvent('region_select', r); }
  function filterCountry(region, country){ if(region !== stateRegion) stateRegion = region; stateCountry = country || 'all'; renderRegions(); }
  function resetCountryFilter(region){ if(region && region !== stateRegion) stateRegion = region; stateCountry = 'all'; renderRegions(); }
  function setRegTab(tb){ stateTab = tb; renderRegions(); }
  if(regRoot){
    var _initB = regRoot.querySelector('.reg-btn[aria-pressed="true"]');
    stateRegion = _initB ? _initB.getAttribute('data-region') : 'europa';
    stateCountry = 'all'; stateTab = 'all';
    regRoot.addEventListener('click', function(e){
      var rReset = e.target.closest('[data-reg-reset]');
      if(rReset){ e.preventDefault(); stateCountry = 'all'; renderRegions(); return; }
      var rTab = e.target.closest('.reg-tab');
      if(rTab){ stateTab = rTab.getAttribute('data-tab'); renderRegions(); return; }
      var chip = e.target.closest('.country-chip');
      if(chip){ var cc = chip.closest('.reg-countries'); if(cc){ stateRegion = cc.getAttribute('data-region'); stateCountry = chip.getAttribute('data-country') || 'all'; renderRegions(); } return; }
      var more = e.target.closest('.reg-more');
      if(more){
        var ap = more.closest('.reg-apanel'); if(!ap) return;
        var g = ap.querySelector('.reg-cards-grid');
        var wasExpanded = g && g.classList.contains('is-expanded');
        if(g) g.classList.toggle('is-expanded');
        applyRegMore(ap);
        if(wasExpanded){ setTimeout(function(){ ap.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 0); }
        return;
      }
      var b = e.target.closest('.reg-btn') || e.target.closest('.reg-zone');
      if(b && b.getAttribute('data-region')){ selectRegion(b.getAttribute('data-region')); }
    });
    regRoot.querySelectorAll('.reg-apanel').forEach(function(ap){ applyRegMore(ap); });
    renderRegions();
  }


  /* ---------- Membership application (prototype: confirmation only) ---------- */
  var mSubmit = d.getElementById('m-submit');
  if(mSubmit){ mSubmit.addEventListener('click', function(){
    var msg = d.getElementById('m-msg'); if(msg){ msg.classList.add('on'); msg.scrollIntoView({behavior:'smooth', block:'center'}); }
  }); }

  /* ---------- Password-reset help toggle ---------- */
  var lForgot = d.getElementById('l-forgot');
  if(lForgot){ lForgot.addEventListener('click', function(e){
    e.preventDefault();
    var box = d.getElementById('l-reset'); if(box) box.hidden = !box.hidden;
  }); }
})();
