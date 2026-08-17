
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
    function syncLangUi(){
      var en = enOn() || (root.getAttribute('data-eg-lang') === 'en');
      relabel(en);
      setTitle(en);
    }
    [].slice.call(langBtns).forEach(function(b){
      b.addEventListener('click', function(){ setTimeout(syncLangUi, 0); });
    });
    d.addEventListener('eg:lang', syncLangUi);
    syncLangUi();
    apply();
  }
  if(document.readyState === 'loading'){ document.addEventListener('DOMContentLoaded', initCx); } else { initCx(); }
})();

