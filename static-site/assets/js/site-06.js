
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
    /* Benefits-page plan CTAs: activate the matching membership tab, then land on the registration section (runs after the router's own post-navigation scroll). The anchor href still drives navigation and the no-JS fallback. */
    var mplan=e.target.closest('[data-membership-plan]');
    if(mplan){
      selectTab(mplan.getAttribute('data-membership-plan'));
      var landOnReg=function(){ var rt=d.getElementById('membership-registration'); if(!rt) return; if(rt.getAttribute('tabindex')===null){ rt.setAttribute('tabindex','-1'); } try{ rt.focus({preventScroll:true}); }catch(x){} try{ rt.scrollIntoView({behavior:'smooth',block:'start'}); }catch(x){ try{ rt.scrollIntoView(); }catch(y){} } };
      if(location.hash==='#p-mitgliedschaft'){ landOnReg(); }
      else { window.addEventListener('hashchange', function _mreg(){ window.removeEventListener('hashchange', _mreg); landOnReg(); }); }
      return;
    }
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
