
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
