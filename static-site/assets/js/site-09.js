
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
    if(/^#p-(mitgliedschaft|login)/.test(hash)) return '#p-mitgliedschaft';
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
