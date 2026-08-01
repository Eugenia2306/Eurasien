/* WP bridge: rewrite leftover #p-* links to real permalinks from egTheme.routes */
(function () {
  "use strict";
  function rewriteHashLinks() {
    if (!window.egTheme || !egTheme.routes) return;
    var links = document.querySelectorAll('a[href^="#p-"]');
    for (var i = 0; i < links.length; i++) {
      var a = links[i];
      var id = (a.getAttribute("href") || "").replace(/^#/, "");
      if (egTheme.routes[id]) {
        a.setAttribute("href", egTheme.routes[id]);
      }
    }
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", rewriteHashLinks);
  } else {
    rewriteHashLinks();
  }
})();
/* Eurasien Gesellschaft – theme.js
 * Ported from the HTML prototype. Hash routing (#p-*) still works on pages that
 * embed multiple .page sections; on normal WP templates, language toggle, burger,
 * analytics, filters, and membership UI remain active.
 */
/* Eurasien Gesellschaft theme JS (adapted from prototype) */
(function(){
'use strict';

/* ---- block 1 ---- */

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
    if(hd && window.innerWidth <= 1260){
      var nav = d.querySelector('.nav');
      if(nav) nav.style.top = Math.round(hd.getBoundingClientRect().bottom) + 'px';
    }
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
    return false;
  }

  /* ---------- Route change: close menu, run pending actions, scroll ---------- */
  function afterNav(){
    d.body.classList.remove('nav-open');
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
      focus: a.getAttribute('data-focus')
    };
    if(location.hash === '#' + page){
      setTimeout(function(){
        d.body.classList.remove('nav-open');
        var b = d.querySelector('.burger'); if(b) b.setAttribute('aria-expanded','false');
        if(!runPending()) window.scrollTo(0,0);
      }, 0);
    }
  });

  /* ---------- Language switch: delegated to eg-lang (lang-switch.js) ---------- */
  var langBtns = d.querySelectorAll('.lang button[data-lang]');
  function setLang(l){
    if (typeof window.egSetLang === 'function') {
      window.egSetLang(l);
      return;
    }
    var en = (l === 'en');
    d.querySelectorAll('.en').forEach(function(el){ el.hidden = !en; });
    d.querySelectorAll('.de').forEach(function(el){ el.hidden = en; });
    root.setAttribute('lang', l);
    root.setAttribute('data-eg-lang', l);
    d.body.classList.toggle('lang-en', en);
    d.body.classList.toggle('lang-de', !en);
    langBtns.forEach(function(b){ b.setAttribute('aria-pressed', String(b.getAttribute('data-lang') === l)); });
  }
  // Handlers live in lang-switch.js; keep setLang available for other modules.
  window.egThemeSetLang = setLang;

  /* ---------- Mobile menu ---------- */
  var burger = d.querySelector('.burger');
  if(burger){ burger.addEventListener('click', function(){
    var open = d.body.classList.toggle('nav-open');
    burger.setAttribute('aria-expanded', String(open));
    if(open) navOffset();
  }); }
  window.addEventListener('resize', function(){ if(d.body.classList.contains('nav-open')) navOffset(); });

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

/* ---- block 2 ---- */

(function(){
  var box=document.getElementById('ev-cal'); if(!box) return;
  var grid=document.getElementById('cal-grid'), moEl=document.getElementById('cal-mo'),
      yrEl=document.getElementById('cal-yr'), panel=document.getElementById('cal-panel');
  var MD=['Januar','Februar','M\u00e4rz','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
  var ME=['January','February','March','April','May','June','July','August','September','October','November','December'];
  var WD=['Mo','Di','Mi','Do','Fr','Sa','So'], WE=['Mo','Tu','We','Th','Fr','Sa','Su'];
  var evs={};
  Array.prototype.forEach.call(document.querySelectorAll('#p-veranstaltungen .ev[data-date]'), function(ev){
    var dt=ev.getAttribute('data-date'); if(!dt) return;
    var t=ev.querySelector('.ev__t'); var de='', en='';
    if(t){ var sd=t.querySelector('.de'), se=t.querySelector('.en');
      de=sd?sd.textContent.trim():t.textContent.trim(); en=se?se.textContent.trim():de; }
    (evs[dt]=evs[dt]||[]).push({de:de,en:en});
  });
  function enOn(){ var p=document.querySelector('.reg-btn .de'); return p?p.hidden:false; }
  function applyLang(el){ var en=enOn();
    Array.prototype.forEach.call(el.querySelectorAll('.de'),function(x){x.hidden=en;});
    Array.prototype.forEach.call(el.querySelectorAll('.en'),function(x){x.hidden=!en;}); }
  function pad(n){ return (n<10?'0':'')+n; }
  var cur=new Date(2026,6,1); var TODAY='2026-07-10';
  function render(){
    var y=cur.getFullYear(), m=cur.getMonth();
    moEl.innerHTML='<span class="de">'+MD[m]+'</span><span class="en" hidden>'+ME[m]+'</span>';
    yrEl.textContent=y;
    var startDow=(new Date(y,m,1).getDay()+6)%7, days=new Date(y,m+1,0).getDate(), h='', hasAny=false;
    for(var i=0;i<7;i++){ h+='<div class="cal__dow"><span class="de">'+WD[i]+'</span><span class="en" hidden>'+WE[i]+'</span></div>'; }
    for(var b=0;b<startDow;b++){ h+='<div class="cal__cell cal__cell--empty"></div>'; }
    for(var dnum=1;dnum<=days;dnum++){ var ds=y+'-'+pad(m+1)+'-'+pad(dnum); var has=!!evs[ds]; if(has){ hasAny=true; }
      var cls='cal__cell'; if(has){ cls += (ds < TODAY ? ' cal__cell--past' : ' cal__cell--future'); }
      h+='<div class="'+cls+'"'+(has?' role="button" tabindex="0" data-day="'+ds+'"':'')+'>'+dnum+'</div>'; }
    grid.innerHTML=h;
    if(hasAny){ panel.hidden=true; panel.innerHTML=''; }
    else { panel.innerHTML='<p class="cal__ph cal__ph--empty"><span class="de">Keine Veranstaltungen in diesem Monat.</span><span class="en" hidden>No events this month.</span></p>'; panel.hidden=false; }
    applyLang(box);
  }
  function show(ds){ var l=evs[ds]; if(!l) return;
    var p=ds.split('-'), dn=parseInt(p[2],10), mi=parseInt(p[1],10)-1;
    var head='<p class="cal__ph"><span class="de">Veranstaltungen am '+dn+'. '+MD[mi]+' '+p[0]+'</span>'
      +'<span class="en" hidden>Events on '+dn+' '+ME[mi]+' '+p[0]+'</span></p>';
    var items=l.map(function(e){ return '<li><span class="de">'+e.de+'</span><span class="en" hidden>'+e.en+'</span></li>'; }).join('');
    panel.innerHTML=head+'<ul class="cal__pl">'+items+'</ul>'; panel.hidden=false; applyLang(panel);
  }
  document.getElementById('cal-prev').addEventListener('click',function(){ cur.setMonth(cur.getMonth()-1); render(); });
  document.getElementById('cal-next').addEventListener('click',function(){ cur.setMonth(cur.getMonth()+1); render(); });
  grid.addEventListener('click',function(e){ var c=e.target.closest('[data-day]'); if(c) show(c.getAttribute('data-day')); });
  grid.addEventListener('keydown',function(e){ var c=e.target.closest('[data-day]'); if(c&&(e.key==='Enter'||e.key===' ')){ e.preventDefault(); show(c.getAttribute('data-day')); } });
  render();
})();

/* ---- block 3 ---- */

(function(){
  var root = document.getElementById('p-regionen');
  if(!root) return;
  var cfr = root.querySelector('.regions-explorer');
  if(!cfr) return;
  var zones = cfr.querySelectorAll('.reg-zone');
  var btns  = cfr.querySelectorAll('.reg-btn');
  var ctys  = cfr.querySelectorAll('.reg-cty');
  var search  = cfr.querySelector('.reg-search');
  var results = cfr.querySelector('.reg-results');
  var list    = cfr.querySelector('.reg-ctylist');
  var nores   = cfr.querySelector('.reg-noresult');
  var showall = cfr.querySelector('.reg-showall-link');

  function hot(region, on){
    if(!region) return;
    var sel = cfr.querySelectorAll('[data-region="'+region+'"]');
    for(var i=0;i<sel.length;i++){
      var el = sel[i];
      if(el.classList.contains('reg-zone')||el.classList.contains('reg-btn')||el.classList.contains('reg-cty')){
        el.classList.toggle('is-hot', !!on);
      }
    }
  }
  function bindHover(el){
    var r = el.getAttribute('data-region');
    el.addEventListener('mouseenter', function(){ hot(r, true); });
    el.addEventListener('mouseleave', function(){ hot(r, false); });
    el.addEventListener('focus', function(){ hot(r, true); }, true);
    el.addEventListener('blur',  function(){ hot(r, false); }, true);
  }
  Array.prototype.forEach.call(zones, bindHover);
  Array.prototype.forEach.call(btns,  bindHover);
  Array.prototype.forEach.call(ctys,  bindHover);

  Array.prototype.forEach.call(zones, function(z){
    z.addEventListener('keydown', function(e){
      if(e.key==='Enter'||e.key===' '||e.key==='Spacebar'){
        e.preventDefault();
        z.dispatchEvent(new MouseEvent('click',{bubbles:true}));
      }
    });
  });

  /* ----- search results: a quiet dropdown that opens only after typing ----- */
  function openResults(open){
    if(!results) return;
    results.hidden = !open;
    if(search) search.setAttribute('aria-expanded', String(!!open));
  }
  function norm(s){
    return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/\u00df/g,'ss');
  }
  function filterList(q){
    if(!list) return 0;
    var kids = list.children, shown = 0, curGroup = null, groupHas = false;
    for(var i=0;i<kids.length;i++){
      var li = kids[i];
      if(li.classList.contains('reg-ctygroup')){
        if(curGroup) curGroup.hidden = !groupHas;
        curGroup = li; groupHas = false; li.hidden = false;
        continue;
      }
      var bcty = li.querySelector('.reg-cty');
      if(!bcty){ continue; }
      var hay = bcty.getAttribute('data-search') || '';
      var match = !q || hay.indexOf(q) !== -1;
      li.hidden = !match;
      if(match){ shown++; groupHas = true; }
    }
    if(curGroup) curGroup.hidden = !groupHas;
    if(nores) nores.hidden = shown !== 0;
    return shown;
  }
  function selectCty(c){
    var r = c.getAttribute('data-region');
    var key = c.getAttribute('data-country') || c.getAttribute('data-space');
    var b = cfr.querySelector('.reg-btn[data-region="'+r+'"]');
    if(b){ b.dispatchEvent(new MouseEvent('click',{bubbles:true})); }
    if(key){ var chip = root.querySelector('.reg-countries[data-region="'+r+'"] .country-chip[data-country="'+key+'"]'); if(chip){ chip.dispatchEvent(new MouseEvent('click',{bubbles:true})); } }
    if(search){ search.value = ''; }
    openResults(false);
  }
  Array.prototype.forEach.call(ctys, function(c){
    c.addEventListener('click', function(){ selectCty(c); });
  });

  if(search){
    search.addEventListener('input', function(){
      var q = norm(search.value.trim());
      if(q.length >= 1){ filterList(q); openResults(true); }
      else { openResults(false); }
    });
    search.addEventListener('focus', function(){
      var q = norm(search.value.trim());
      if(q.length >= 1){ filterList(q); openResults(true); }
    });
    search.addEventListener('keydown', function(e){
      if(e.key==='Escape'){ openResults(false); search.blur(); }
    });
  }
  if(showall){
    showall.addEventListener('click', function(){
      filterList('');
      openResults(true);
      if(search){ try{ search.focus(); }catch(_){} }
    });
  }
  /* close the dropdown on outside click */
  document.addEventListener('click', function(e){
    if(!results || results.hidden) return;
    var wrap = cfr.querySelector('.reg-search-wrap');
    if(wrap && wrap.contains(e.target)) return;
    if(showall && showall.contains(e.target)) return;
    openResults(false);
  });

  /* keep the search placeholder in sync with the site language toggle */
  function setPh(lang){
    if(!search) return;
    var en = (lang === 'en');
    search.placeholder = en ? (search.getAttribute('data-ph-en')||'') : (search.getAttribute('data-ph-de')||'');
  }
  var langBtns = document.querySelectorAll('.lang button[data-lang]');
  if(langBtns.length){
    var cur = 'de';
    for(var i=0;i<langBtns.length;i++){
      if(langBtns[i].getAttribute('aria-pressed')==='true'){ cur = langBtns[i].getAttribute('data-lang'); }
      (function(bb){ bb.addEventListener('click', function(){ setPh(bb.getAttribute('data-lang')); }); })(langBtns[i]);
    }
    setPh(cur);
  } else { setPh('de'); }
})();

/* ---- block 4 ---- */

/* ===== build32: membership tabs + two-step payment (prototype), event registration ===== */
(function(){
  "use strict";
  var d=document;
  var __ferr=0;
  var REF='2026-07-10';
  function emailOk(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v||'').trim()); }
  function paySelected(scope){ var g=scope.querySelector('.pay__opts'); return !!(g && g.querySelector('.pay__opt[aria-checked="true"]')); }
  function stepScope(el){ return el.closest('.formcard')||el.closest('.regmodal__dialog'); }
  function stepEl(scope,name){ return scope.querySelector('[data-step="'+name+'"]'); }
  function stepErr(step,on){ if(!step) return; var e=step.querySelector('[data-formerr]'); if(e){ e.classList.toggle('on',!!on); if(on){ try{ e.scrollIntoView({behavior:'smooth',block:'center'}); }catch(x){} } } }
  function advance(btn){
    var scope=stepScope(btn); if(!scope) return;
    var s1=stepEl(scope,'details'), s2=stepEl(scope,'payment'); if(!s1||!s2) return;
    var ok=true, firstBad=null;
    var errBox=s1.querySelector('[data-formerr]'); var errId=errBox?(errBox.id||(errBox.id='vm-ferr-'+(++__ferr))):null;
    function bad(f){ if(!f) return; ok=false; if(!firstBad) firstBad=f; f.setAttribute('aria-invalid','true'); if(errId) f.setAttribute('aria-describedby',errId); }
    [].slice.call(s1.querySelectorAll('[required]')).forEach(function(f){ var v=(f.type==='checkbox')?f.checked:String(f.value||'').trim()!==''; if(!v){ bad(f); } else { f.removeAttribute('aria-invalid'); } });
    [].slice.call(s1.querySelectorAll('[data-confirm]')).forEach(function(f){ var o=d.getElementById(f.getAttribute('data-confirm')); if(o && f.value!==o.value){ bad(f); } });
    var mail=s1.querySelector('input[type="email"]'); if(mail && !emailOk(mail.value)){ bad(mail); }
    if(!ok){ stepErr(s1,true); if(firstBad){ try{ firstBad.focus(); }catch(x){} } return; }
    stepErr(s1,false); s1.hidden=true; s2.hidden=false; try{ s2.scrollIntoView({behavior:'smooth',block:'nearest'}); }catch(x){}
  }
  function goBack(btn){ var scope=stepScope(btn); if(!scope) return; var s1=stepEl(scope,'details'), s2=stepEl(scope,'payment'); if(s2){ s2.hidden=true; stepErr(s2,false); } if(s1) s1.hidden=false; }
  function finalize(btn){
    var scope=stepScope(btn); if(!scope) return;
    var s2=stepEl(scope,'payment'); if(!s2) return;
    if(!paySelected(s2)){ stepErr(s2,true); return; }
    stepErr(s2,false);
    var s1=stepEl(scope,'details'); if(s1) s1.hidden=true; s2.hidden=true;
    var msg=scope.querySelector('[data-formmsg]'); if(msg){ msg.classList.add('on'); try{ msg.scrollIntoView({behavior:'smooth',block:'center'}); }catch(x){} }
  }
  function resetSteps(scope){
    if(!scope) return;
    var s1=stepEl(scope,'details'), s2=stepEl(scope,'payment'), msg=scope.querySelector('[data-formmsg]');
    if(s1){ s1.hidden=false; stepErr(s1,false); } if(s2){ s2.hidden=true; stepErr(s2,false); }
    if(msg) msg.classList.remove('on');
    [].slice.call(scope.querySelectorAll('.pay__opt')).forEach(function(o){ o.setAttribute('aria-checked','false'); o.classList.remove('is-selected'); });
  }
  /* ---- event modal ---- */
  var modal=d.getElementById('ev-regmodal'), lastFocus=null;
  var MD=['Januar','Februar','M\u00e4rz','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
  var ME=['January','February','March','April','May','June','July','August','September','October','November','December'];
  function evTitle(ev){ var t=ev.querySelector('.ev__t'); if(!t) return ''; var c=t.cloneNode(true); [].slice.call(c.querySelectorAll('[hidden]')).forEach(function(n){ n.parentNode.removeChild(n); }); return c.textContent.replace(/\s+/g,' ').trim(); }
  function langEn(){ var pr=d.querySelector('.reg-btn .de'); return pr?pr.hidden:false; }
  function setDate(ev){
    var dd=d.getElementById('ev-reg-date'); if(!dd) return;
    var dt=ev.getAttribute('data-date'); if(!dt){ dd.innerHTML=''; return; }
    var p=dt.split('-'), dn=parseInt(p[2],10), mi=parseInt(p[1],10)-1;
    dd.innerHTML='<span class="de">'+dn+'. '+MD[mi]+' '+p[0]+'</span><span class="en" hidden>'+dn+' '+ME[mi]+' '+p[0]+'</span>';
    var en=langEn(); [].slice.call(dd.querySelectorAll('.de')).forEach(function(x){x.hidden=en;}); [].slice.call(dd.querySelectorAll('.en')).forEach(function(x){x.hidden=!en;});
  }
  function openModal(ev, trigger){
    if(!modal) return; lastFocus=trigger||null;
    var tt=d.getElementById('ev-reg-title'); if(tt) tt.textContent=evTitle(ev);
    setDate(ev);
    [].slice.call(modal.querySelectorAll('input,textarea')).forEach(function(i){ if(i.type==='checkbox') i.checked=false; else i.value=''; });
    resetSteps(modal.querySelector('.regmodal__dialog'));
    modal.hidden=false; d.body.classList.add('modal-open');
    var f=d.getElementById('er-first'); if(f){ try{ f.focus(); }catch(e){} }
  }
  function closeModal(){ if(!modal||modal.hidden) return; modal.hidden=true; d.body.classList.remove('modal-open'); if(lastFocus){ try{ lastFocus.focus(); }catch(e){} } }
  d.addEventListener('keydown', function(e){ if((e.key==='Escape'||e.key==='Esc') && modal && !modal.hidden) closeModal(); });
  /* ---- membership tabs ---- */
  function selectTab(key){
    [].slice.call(d.querySelectorAll('.mtab')).forEach(function(t){ t.setAttribute('aria-selected', String(t.getAttribute('data-mtab')===key)); });
    [].slice.call(d.querySelectorAll('.mpanel')).forEach(function(p){ var on=p.getAttribute('data-mpanel')===key; p.hidden=!on; if(on) resetSteps(p.querySelector('.formcard')); });
  }
  /* ---- delegated clicks ---- */
  d.addEventListener('click', function(e){
    var opt=e.target.closest('.pay__opt');
    if(opt){ var grp=opt.closest('.pay__opts'); if(grp){ [].slice.call(grp.querySelectorAll('.pay__opt')).forEach(function(o){ o.setAttribute('aria-checked', String(o===opt)); o.classList.toggle('is-selected', o===opt); }); } return; }
    var tab=e.target.closest('.mtab'); if(tab){ selectTab(tab.getAttribute('data-mtab')); return; }
    var nx=e.target.closest('[data-step-next]'); if(nx){ advance(nx); return; }
    var bk=e.target.closest('[data-step-back]'); if(bk){ goBack(bk); return; }
    if(e.target.closest('[data-modal-close]')){ closeModal(); return; }
    var sub=e.target.closest('[data-form-submit]'); if(sub){ finalize(sub); return; }
    var g=e.target.closest('[data-goto]'); if(g){ var t=d.getElementById(g.getAttribute('data-goto')); if(t){ try{ t.scrollIntoView({behavior:'smooth',block:'start'}); }catch(x){} } return; }
  });
  /* ---- a11y: clear aria-invalid on input ---- */
  d.addEventListener('input', function(e){
    var f=e.target; if(!f||!f.getAttribute) return;
    if(f.getAttribute('aria-invalid')==='true'){ var okv=(f.type==='checkbox')?f.checked:String(f.value||'').trim()!==''; if(okv) f.removeAttribute('aria-invalid'); }
  });
  /* ---- per-event: date-aware. upcoming AND future -> fee + register; otherwise -> completed ---- */
  function isUpcoming(ev){ var dt=ev.getAttribute('data-date'); return ev.getAttribute('data-status')==='upcoming' && (!dt || dt >= REF); }
  [].slice.call(d.querySelectorAll('#p-veranstaltungen .ev')).forEach(function(ev){
    var side=ev.querySelector('.ev__side'); if(!side) return;
    if(isUpcoming(ev)){
      var fee=d.createElement('div'); fee.className='ev__fee';
      fee.innerHTML='<span class="de">Teilnahme: 10 \u20ac</span><span class="en" hidden>Participation: \u20ac10</span>';
      var btn=d.createElement('button'); btn.type='button'; btn.className='ev__reg'; btn.setAttribute('data-event-open','');
      btn.innerHTML='<span class="de">F\u00fcr diese Veranstaltung anmelden</span><span class="en" hidden>Register for this event</span>';
      btn.addEventListener('click', function(){ openModal(ev, btn); });
      side.appendChild(fee); side.appendChild(btn);
    } else {
      var done=d.createElement('div'); done.className='ev__done';
      done.innerHTML='<span class="de">Veranstaltung abgeschlossen</span><span class="en" hidden>Event completed</span>';
      side.appendChild(done);
    }
  });
})();

/* ---- block 5 ---- */

/* Kultur article explorer (kx): self-contained region x topic filter. Independent of the main script. */
(function(){
  function initKx(){
    var root = document.getElementById('kx-explorer');
    if(!root) return;
    var regionFilter = document.getElementById('kx-region-filter');
    var topicFilter  = document.getElementById('kx-topic-filter');
    var cards  = [].slice.call(root.querySelectorAll('.kx-card'));
    var countN = document.getElementById('kx-count-n');
    var empty  = document.getElementById('kx-empty');
    var reset  = document.getElementById('kx-reset');
    var regionSel = 'all', topicSel = 'all';
    function press(container, val){
      if(!container) return;
      [].slice.call(container.querySelectorAll('.kx-chip')).forEach(function(c){
        c.setAttribute('aria-pressed', String(c.getAttribute('data-f') === val));
      });
    }
    function apply(){
      var shown = 0;
      for(var i=0;i<cards.length;i++){
        var card = cards[i];
        var r = card.getAttribute('data-region') || '';
        var t = ' ' + (card.getAttribute('data-topic') || '') + ' ';
        var okR = (regionSel === 'all') || (r === regionSel);
        var okT = (topicSel === 'all') || (t.indexOf(' ' + topicSel + ' ') !== -1);
        var ok = okR && okT;
        card.hidden = !ok;
        if(ok) shown++;
      }
      if(countN) countN.textContent = String(shown);
      if(empty) empty.hidden = (shown > 0);
      if(reset) reset.hidden = (regionSel === 'all' && topicSel === 'all');
      press(regionFilter, regionSel);
      press(topicFilter, topicSel);
    }
    if(regionFilter) regionFilter.addEventListener('click', function(e){
      var b = e.target.closest('.kx-chip'); if(!b) return;
      regionSel = b.getAttribute('data-f'); apply();
    });
    if(topicFilter) topicFilter.addEventListener('click', function(e){
      var b = e.target.closest('.kx-chip'); if(!b) return;
      topicSel = b.getAttribute('data-f'); apply();
    });
    if(reset) reset.addEventListener('click', function(){
      regionSel = 'all'; topicSel = 'all'; apply();
    });
    apply();
  }
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initKx);
  } else { initKx(); }
})();

/* ---- block 6 ---- */

/* Wissenschaft article explorer (sx): self-contained region x field x keyword filter. Independent of kx and the main script. */
(function(){
  function initSx(){
    var root = document.getElementById('sx-explorer');
    if(!root) return;
    var regionFilter = document.getElementById('sx-region-filter');
    var topicFilter  = document.getElementById('sx-topic-filter');
    var search = document.getElementById('sx-search-input');
    var cards  = [].slice.call(root.querySelectorAll('.sx-card'));
    var countN = document.getElementById('sx-count-n');
    var unit   = document.getElementById('sx-count-unit');
    var unitDe = unit ? unit.querySelector('.de') : null;
    var unitEn = unit ? unit.querySelector('.en') : null;
    var empty  = document.getElementById('sx-empty');
    var reset  = document.getElementById('sx-reset');
    var regionSel = 'all', topicSel = 'all', q = '';
    function press(container, val){
      if(!container) return;
      [].slice.call(container.querySelectorAll('.sx-chip')).forEach(function(c){
        c.setAttribute('aria-pressed', String(c.getAttribute('data-f') === val));
      });
    }
    function apply(){
      var shown = 0;
      for(var i=0;i<cards.length;i++){
        var card = cards[i];
        var r = ' ' + (card.getAttribute('data-region') || '') + ' ';
        var t = ' ' + (card.getAttribute('data-topic') || '') + ' ';
        var s = (card.getAttribute('data-search') || '').toLowerCase();
        var okR = (regionSel === 'all') || (r.indexOf(' ' + regionSel + ' ') !== -1);
        var okT = (topicSel === 'all') || (t.indexOf(' ' + topicSel + ' ') !== -1);
        var okQ = (q === '') || (s.indexOf(q) !== -1);
        var ok = okR && okT && okQ;
        card.hidden = !ok;
        if(ok) shown++;
      }
      if(countN) countN.textContent = String(shown);
      if(unitDe) unitDe.textContent = (shown === 1) ? 'Beitrag' : 'Beiträge';
      if(unitEn) unitEn.textContent = (shown === 1) ? 'contribution' : 'contributions';
      if(empty) empty.hidden = (shown > 0);
      if(reset) reset.hidden = (regionSel === 'all' && topicSel === 'all' && q === '');
      press(regionFilter, regionSel);
      press(topicFilter, topicSel);
    }
    if(regionFilter) regionFilter.addEventListener('click', function(e){
      var b = e.target.closest('.sx-chip'); if(!b) return;
      regionSel = b.getAttribute('data-f'); apply();
    });
    if(topicFilter) topicFilter.addEventListener('click', function(e){
      var b = e.target.closest('.sx-chip'); if(!b) return;
      topicSel = b.getAttribute('data-f'); apply();
    });
    if(search) search.addEventListener('input', function(){
      q = (search.value || '').trim().toLowerCase(); apply();
    });
    if(reset) reset.addEventListener('click', function(){
      regionSel = 'all'; topicSel = 'all'; q = ''; if(search) search.value = ''; apply();
    });
    apply();
  }
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initSx);
  } else { initSx(); }
})();

/* Laender & Gesellschaften explorer (cx): region x country x society-topic x keyword. Independent of kx, sx and the main script. */
(function(){
  function initCx(){
    var root = document.getElementById('cx-explorer');
    if(!root) return;
    var d = document;
    var regionFilter = d.getElementById('cx-region-filter');
    var topicFilter  = d.getElementById('cx-topic-filter');
    var countrySel   = d.getElementById('cx-country-filter');
    var search = d.getElementById('cx-search-input');
    var cards  = [].slice.call(root.querySelectorAll('.cx-card'));
    var countN = d.getElementById('cx-count-n');
    var unit   = d.getElementById('cx-count-unit');
    var unitDe = unit ? unit.querySelector('.de') : null;
    var unitEn = unit ? unit.querySelector('.en') : null;
    var empty  = d.getElementById('cx-empty');
    var reset  = d.getElementById('cx-reset');
    var regionS='all', topicS='all', countryS='all', q='';
    function press(container, val){
      if(!container) return;
      [].slice.call(container.querySelectorAll('.cx-chip')).forEach(function(c){
        c.setAttribute('aria-pressed', String(c.getAttribute('data-f') === val));
      });
    }
    function apply(){
      var shown=0;
      for(var i=0;i<cards.length;i++){
        var card=cards[i];
        var r=' '+(card.getAttribute('data-region')||'')+' ';
        var co=' '+(card.getAttribute('data-country')||'')+' ';
        var t=' '+(card.getAttribute('data-society-topic')||'')+' ';
        var s=(card.getAttribute('data-search')||'').toLowerCase();
        var okR=(regionS==='all')||(r.indexOf(' '+regionS+' ')!==-1);
        var okC=(countryS==='all')||(co.indexOf(' '+countryS+' ')!==-1);
        var okT=(topicS==='all')||(t.indexOf(' '+topicS+' ')!==-1);
        var okQ=(q==='')||(s.indexOf(q)!==-1);
        var ok=okR&&okC&&okT&&okQ;
        card.hidden=!ok;
        if(ok) shown++;
      }
      if(countN) countN.textContent=String(shown);
      if(unitDe) unitDe.textContent=(shown===1)?'Beitrag':'Beiträge';
      if(unitEn) unitEn.textContent=(shown===1)?'contribution':'contributions';
      if(empty) empty.hidden=(shown>0);
      if(reset) reset.hidden=(regionS==='all'&&countryS==='all'&&topicS==='all'&&q==='');
      press(regionFilter, regionS);
      press(topicFilter, topicS);
    }
    if(regionFilter) regionFilter.addEventListener('click', function(e){ var b=e.target.closest('.cx-chip'); if(!b) return; regionS=b.getAttribute('data-f'); apply(); });
    if(topicFilter)  topicFilter.addEventListener('click', function(e){ var b=e.target.closest('.cx-chip'); if(!b) return; topicS=b.getAttribute('data-f'); apply(); });
    if(countrySel)   countrySel.addEventListener('change', function(){ countryS=countrySel.value||'all'; apply(); });
    if(search)       search.addEventListener('input', function(){ q=(search.value||'').trim().toLowerCase(); apply(); });
    if(reset)        reset.addEventListener('click', function(){ regionS='all'; topicS='all'; countryS='all'; q=''; if(search) search.value=''; if(countrySel) countrySel.value='all'; apply(); });
    function relabel(en){
      if(!countrySel) return;
      [].slice.call(countrySel.options).forEach(function(o){
        var de=o.getAttribute('data-de'), enl=o.getAttribute('data-en');
        if(de!==null && enl!==null) o.textContent = en ? enl : de;
      });
    }
    function enOn(){ var p=root.querySelector('.cx-fblock__label .de'); return p ? p.hidden : false; }
    /* Safe route-aware document title (title only; router, canonical URLs and OG untouched). */
    var ORIG_TITLE = d.title;
    var ROUTE_TITLES = {
      '#p-topic-wissenschaft': { de: 'Wissenschaft | Eurasien Gesellschaft e. V.',            en: 'Science | Eurasien Gesellschaft e. V.' },
      '#p-kultur':             { de: 'Kultur | Eurasien Gesellschaft e. V.',                  en: 'Culture | Eurasien Gesellschaft e. V.' },
      '#p-laender':            { de: 'Länder & Gesellschaften | Eurasien Gesellschaft e. V.', en: 'Countries & Societies | Eurasien Gesellschaft e. V.' }
    };
    function setTitle(en){ var t = ROUTE_TITLES[location.hash]; d.title = t ? (en ? t.en : t.de) : ORIG_TITLE; }
    window.addEventListener('hashchange', function(){ setTitle(enOn()); });
    var langBtns = d.querySelectorAll('.lang button[data-lang]');
    [].slice.call(langBtns).forEach(function(b){
      b.addEventListener('click', function(){ var en=b.getAttribute('data-lang')==='en'; relabel(en); setTitle(en); });
    });
    relabel(enOn());
    setTitle(enOn());
    apply();
  }
  if(document.readyState === 'loading'){ document.addEventListener('DOMContentLoaded', initCx); } else { initCx(); }
})();

/* ---- block 7 ---- */

(function(){
  'use strict';
  var d=document, root=d.documentElement;
  root.classList.add('v48-js');

  function currentLanguage(){
    var b=d.querySelector('.lang button[aria-pressed="true"]');
    return b && b.getAttribute('data-lang')==='en' ? 'en' : 'de';
  }
  function visibleText(el, lang){
    if(!el) return '';
    var clone=el.cloneNode(true);
    clone.querySelectorAll(lang==='en'?'.de':'.en').forEach(function(n){n.remove();});
    return (clone.textContent||'').replace(/\s+/g,' ').trim();
  }
  function esc(s){return String(s).replace(/[&<>'"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c];});}

  function addSearchTrigger(){
    var actions=d.querySelector('.actions');
    if(!actions || actions.querySelector('.search-launch')) return;
    var btn=d.createElement('button');
    btn.type='button'; btn.className='search-launch'; btn.setAttribute('aria-label','Suche öffnen / Open search');
    btn.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m16.5 16.5 4.5 4.5"></path></svg>';
    actions.insertBefore(btn,actions.firstChild);
  }
  addSearchTrigger();

  var header=d.querySelector('.hd');
  var progressTrack=d.querySelector('.site-progress');
  var progress=progressTrack?progressTrack.querySelector('span'):null;
  var progressRaf=0;

  /* Die Leiste ist genau so breit wie der Viewport. Der Farbverlauf wird auf
     exakt diese Breite dimensioniert (--sp-track), damit Blau, Gold und Rot
     immer Drittel der vollen Breite bleiben und nicht in den aktuellen
     Fortschritt gestaucht werden. Gemessen wird die reale Breite der Leiste,
     nicht 100vw: eine sichtbare Scrollbar wuerde sonst einen Rest am rechten
     Rand hinterlassen. */
  function measureProgress(){
    if(!progressTrack) return;
    var w=progressTrack.clientWidth||root.clientWidth||0;
    if(w>0) progressTrack.style.setProperty('--sp-track',w+'px');
  }
  function updateProgress(){
    if(!progress) return;
    var max=root.scrollHeight-window.innerHeight;
    var y=window.scrollY||window.pageYOffset||0;
    var pct=(max>0)?(y/max)*100:0;
    if(!isFinite(pct)) pct=0;
    if(pct<0) pct=0; if(pct>100) pct=100;
    progress.style.width=pct+'%';
  }
  function refreshProgress(){
    if(progressRaf) return;
    progressRaf=window.requestAnimationFrame(function(){
      progressRaf=0; measureProgress(); updateProgress();
    });
  }
  /* Hash-Navigation, Sprachwechsel und Filter aendern die Dokumenthoehe erst
     nach dem Klick, deshalb zusaetzlich einmal verzoegert nachrechnen. */
  function refreshProgressSoon(){ refreshProgress(); window.setTimeout(refreshProgress,180); }

  var back=d.getElementById('back-to-top');
  function onScroll(){
    var y=window.scrollY||0;
    if(header) header.classList.toggle('is-scrolled',y>18);
    if(back) back.classList.toggle('on',y>520);
    updateProgress();
  }
  window.addEventListener('scroll',onScroll,{passive:true});
  window.addEventListener('resize',refreshProgress,{passive:true});
  window.addEventListener('orientationchange',refreshProgressSoon);
  window.addEventListener('hashchange',refreshProgressSoon);
  window.addEventListener('load',refreshProgressSoon);
  d.addEventListener('click',function(e){
    var t=(e.target&&e.target.closest)?e.target.closest('a[href^="#"], .lang button'):null;
    if(t) refreshProgressSoon();
  },true);
  measureProgress(); onScroll();
  if(back) back.addEventListener('click',function(){window.scrollTo({top:0,behavior:'smooth'});});

  function routeGroup(hash){
    if(!hash||hash==='#p-home') return '#p-home';
    if(/^#p-(mission|vorstand|person-|partner|news)/.test(hash)) return '#p-mission';
    if(/^#p-(themen|topic-|kultur|laender)/.test(hash)) return '#p-themen';
    if(/^#p-(analysen|members-positionen|members-dossiers|members-studien)/.test(hash)) return '#p-analysen';
    if(hash.indexOf('#p-regionen')===0) return '#p-regionen';
    if(hash.indexOf('#p-veranstaltungen')===0) return '#p-veranstaltungen';
    if(hash.indexOf('#p-mediathek')===0) return '#p-mediathek';
    if(/^#p-(mitgliedschaft-vorteile|mitgliedschaft|login)/.test(hash)) {
      return hash.indexOf('vorteile')>=0 ? '#p-mitgliedschaft-vorteile' : '#p-mitgliedschaft';
    }
    return hash;
  }
  function syncCurrent(){
    var hash=location.hash||'#p-home', group=routeGroup(hash);
    d.querySelectorAll('.nav-link,.mobile-dock a').forEach(function(a){
      var active=a.getAttribute('href')===group;
      a.classList.toggle('is-current',active);
      if(active) a.setAttribute('aria-current','page'); else a.removeAttribute('aria-current');
    });
  }
  window.addEventListener('hashchange',syncCurrent); syncCurrent();

  /* Lightweight reveal animation. */
  var revealEls=[].slice.call(d.querySelectorAll('.sec .card,.sec .topic,.sec .person,.sec .mcard,.sec .ev,.sec .plan,.sec .pillar,.sec .subcat'));
  revealEls.forEach(function(el,i){el.classList.add('reveal-ready'); el.style.transitionDelay=((i%4)*45)+'ms';});
  if('IntersectionObserver' in window){
    var io=new IntersectionObserver(function(entries){entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add('revealed');io.unobserve(e.target);}});},{rootMargin:'0px 0px -8% 0px',threshold:.07});
    revealEls.forEach(function(el){io.observe(el);});
  } else {revealEls.forEach(function(el){el.classList.add('revealed');});}

  /* Accessible site-wide page search. */
  var dialog=d.getElementById('site-search-dialog');
  var input=d.getElementById('site-search-input');
  var results=d.getElementById('site-search-results');
  var lastFocus=null;
  var pages=[].slice.call(d.querySelectorAll('.page')).map(function(page){
    var h=page.querySelector('h1')||page.querySelector('h2');
    var p=page.querySelector('.phead p:last-child')||page.querySelector('.lead')||page.querySelector('p');
    return {id:page.id, page:page, h:h, p:p, all:(page.textContent||'').toLowerCase()};
  });
  function quickResults(lang){
    var ids=['p-analysen','p-veranstaltungen','p-regionen','p-mitgliedschaft'];
    return ids.map(function(id){return pages.find(function(x){return x.id===id;});}).filter(Boolean);
  }
  function renderSearch(q){
    if(!results) return;
    var lang=currentLanguage(); q=(q||'').trim().toLowerCase();
    var found=q ? pages.filter(function(x){return x.all.indexOf(q)!==-1;}).slice(0,9) : quickResults(lang);
    if(!found.length){
      results.innerHTML='<div class="search-empty">'+(lang==='en'?'No matching page found.':'Keine passende Seite gefunden.')+'</div>';
      return;
    }
    results.innerHTML=found.map(function(x,i){
      var title=visibleText(x.h,lang)||x.id.replace(/^p-/,'');
      var desc=visibleText(x.p,lang).slice(0,120);
      return '<a class="search-result" href="#'+esc(x.id)+'"><span class="search-result__icon">'+String(i+1).padStart(2,'0')+'</span><span><b>'+esc(title)+'</b><small>'+esc(desc)+'</small></span><i aria-hidden="true">→</i></a>';
    }).join('');
    results.querySelectorAll('a').forEach(function(a){a.addEventListener('click',closeSearch);});
  }
  function openSearch(){
    if(!dialog) return; lastFocus=d.activeElement; dialog.hidden=false; d.body.style.overflow='hidden'; renderSearch(input?input.value:''); setTimeout(function(){if(input) input.focus();},30);
  }
  function closeSearch(){
    if(!dialog) return; dialog.hidden=true; d.body.style.overflow=''; if(lastFocus&&lastFocus.focus) lastFocus.focus();
  }
  d.addEventListener('click',function(e){
    if(e.target.closest('.search-launch')) openSearch();
    if(e.target.closest('[data-search-close]')) closeSearch();
  });
  if(input) input.addEventListener('input',function(){renderSearch(input.value);});
  d.addEventListener('keydown',function(e){
    if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();openSearch();}
    if(e.key==='Escape'&&dialog&&!dialog.hidden) closeSearch();
  });

  /* Make the original desktop search field open the richer search dialog if it is restored. */
  d.querySelectorAll('.search input').forEach(function(old){old.addEventListener('focus',openSearch);});

  /* Re-render search labels after language changes. */
  d.querySelectorAll('.lang button[data-lang]').forEach(function(btn){btn.addEventListener('click',function(){setTimeout(function(){if(dialog&&!dialog.hidden) renderSearch(input?input.value:'');},0);});});
})();