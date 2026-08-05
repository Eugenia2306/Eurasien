
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
