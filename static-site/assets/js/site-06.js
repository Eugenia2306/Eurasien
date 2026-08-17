
/* ===== build32: membership tabs + two-step payment (prototype), event registration ===== */
(function(){
  "use strict";
  var d=document;
  var __ferr=0;
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
  var modal=d.getElementById('ev-regmodal'), lastFocus=null, currentEv=null;
  var MD=['Januar','Februar','M\u00e4rz','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
  var ME=['January','February','March','April','May','June','July','August','September','October','November','December'];
  function evTitle(ev){ var t=ev.querySelector('.ev__t'); if(!t) return ''; var c=t.cloneNode(true); [].slice.call(c.querySelectorAll('[hidden]')).forEach(function(n){ n.parentNode.removeChild(n); }); return c.textContent.replace(/\s+/g,' ').trim(); }
  function langEn(){ var pr=d.querySelector('.reg-btn .de'); return pr?pr.hidden:false; }
  function unitPrice(){
    var u=parseFloat(modal && modal.getAttribute('data-unit-price') || '10', 10);
    return isFinite(u) && u > 0 ? u : 10;
  }
  function qtyValue(){
    var q=d.getElementById('er-qty');
    var n=q ? parseInt(q.value, 10) : 1;
    if(!isFinite(n) || n < 1) n=1;
    if(n > 20) n=20;
    if(q) q.value=String(n);
    return n;
  }
  function updateEventTotal(){
    if(!modal) return;
    var total=unitPrice() * qtyValue();
    var label=total.toFixed(total % 1 ? 2 : 0).replace('.', ',') + ' \u20ac';
    var unitEl=d.getElementById('er-unit-label');
    var totEl=d.getElementById('er-total-label');
    if(unitEl) unitEl.textContent=unitPrice().toFixed(unitPrice() % 1 ? 2 : 0).replace('.', ',') + ' \u20ac';
    if(totEl) totEl.textContent=label;
  }
  function setDate(ev){
    var dd=d.getElementById('ev-reg-date'); if(!dd) return;
    var dt=ev.getAttribute('data-date'); if(!dt){ dd.innerHTML=''; return; }
    var p=dt.split('-'), dn=parseInt(p[2],10), mi=parseInt(p[1],10)-1;
    dd.innerHTML='<span class="de">'+dn+'. '+MD[mi]+' '+p[0]+'</span><span class="en" hidden>'+dn+' '+ME[mi]+' '+p[0]+'</span>';
    var en=langEn(); [].slice.call(dd.querySelectorAll('.de')).forEach(function(x){x.hidden=en;}); [].slice.call(dd.querySelectorAll('.en')).forEach(function(x){x.hidden=!en;});
  }
  function openModal(ev, trigger){
    if(!modal) return; lastFocus=trigger||null; currentEv=ev;
    var tt=d.getElementById('ev-reg-title'); if(tt) tt.textContent=evTitle(ev);
    setDate(ev);
    var price=ev.getAttribute('data-price') || '10';
    var loc=ev.getAttribute('data-location') || '';
    if(!loc){
      var meta=ev.querySelector('.ev__meta span');
      if(meta){ var c=meta.cloneNode(true); [].slice.call(c.querySelectorAll('[hidden]')).forEach(function(x){x.parentNode.removeChild(x);}); loc=c.textContent.replace(/\s+/g,' ').trim(); }
    }
    modal.setAttribute('data-event-id', ev.id || '');
    modal.setAttribute('data-event-date', ev.getAttribute('data-date') || '');
    modal.setAttribute('data-event-time-start', ev.getAttribute('data-time-start') || '19:00');
    modal.setAttribute('data-event-time-end', ev.getAttribute('data-time-end') || '21:00');
    modal.setAttribute('data-event-location', loc);
    modal.setAttribute('data-unit-price', price);
    [].slice.call(modal.querySelectorAll('input,textarea')).forEach(function(i){
      if(i.type==='checkbox') i.checked=false;
      else if(i.id==='er-qty') i.value='1';
      else i.value='';
    });
    resetSteps(modal.querySelector('.regmodal__dialog'));
    updateEventTotal();
    modal.hidden=false; d.body.classList.add('modal-open');
    var f=d.getElementById('er-first'); if(f){ try{ f.focus(); }catch(e){} }
  }
  function closeModal(){ if(!modal||modal.hidden) return; modal.hidden=true; d.body.classList.remove('modal-open'); if(lastFocus){ try{ lastFocus.focus(); }catch(e){} } }
  d.addEventListener('keydown', function(e){ if((e.key==='Escape'||e.key==='Esc') && modal && !modal.hidden) closeModal(); });
  window.EG_EVENT_REG = {
    getPayload: function(){
      if(!modal) return null;
      return {
        event_id: modal.getAttribute('data-event-id') || '',
        event_title: (d.getElementById('ev-reg-title') || {}).textContent || '',
        event_date: modal.getAttribute('data-event-date') || '',
        event_time_start: modal.getAttribute('data-event-time-start') || '19:00',
        event_time_end: modal.getAttribute('data-event-time-end') || '21:00',
        event_location: modal.getAttribute('data-event-location') || '',
        unit_price: String(unitPrice()),
        qty: String(qtyValue()),
        first_name: (d.getElementById('er-first') || {}).value || '',
        last_name: (d.getElementById('er-last') || {}).value || '',
        email: (d.getElementById('er-mail') || {}).value || '',
        consent: !!(d.getElementById('er-consent') && d.getElementById('er-consent').checked)
      };
    },
    validate: function(){
      if(!modal) return false;
      return window.EG_MEMBERSHIP && typeof window.EG_MEMBERSHIP.validateDetails === 'function'
        ? window.EG_MEMBERSHIP.validateDetails(modal.querySelector('.regmodal__dialog') || modal)
        : false;
    },
    updateTotal: updateEventTotal
  };
  /* ---- membership tabs ---- */
  function selectTab(key){
    if(key === 'verein' || key === 'association') key = 'expert';
    if(key !== 'reader' && key !== 'expert') return;
    [].slice.call(d.querySelectorAll('.mtab')).forEach(function(t){
      var on = t.getAttribute('data-mtab') === key;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.classList.toggle('is-active', on);
      t.tabIndex = on ? 0 : -1;
    });
    [].slice.call(d.querySelectorAll('.mpanel')).forEach(function(p){
      var on = p.getAttribute('data-mpanel') === key;
      if(on){
        p.removeAttribute('hidden');
        p.setAttribute('aria-hidden', 'false');
        p.classList.add('is-active');
        resetSteps(p.querySelector('.formcard'));
      } else {
        p.setAttribute('hidden', '');
        p.setAttribute('aria-hidden', 'true');
        p.classList.remove('is-active');
      }
    });
    try{
      if(d.querySelector('.mtabwrap')){
        var url = new URL(location.href);
        url.searchParams.set('plan', key);
        var hash = url.hash || '#p-mitgliedschaft';
        history.replaceState(null, '', url.pathname + '?' + url.searchParams.toString() + hash);
      }
    }catch(err){}
  }
  function planFromUrl(){
    try{
      var q = new URLSearchParams(location.search || '');
      var p = (q.get('plan') || '').toLowerCase();
      if(p === 'expert' || p === 'verein' || p === 'association') return 'expert';
      if(p === 'reader' || p === 'leser') return 'reader';
    }catch(e){}
    return '';
  }
  function bindMembershipTabs(){
    [].slice.call(d.querySelectorAll('.mtab')).forEach(function(tab){
      if(tab.getAttribute('data-eg-mtab') === '1') return;
      tab.setAttribute('data-eg-mtab', '1');
      if(!tab.getAttribute('type')) tab.setAttribute('type', 'button');
      tab.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        selectTab(tab.getAttribute('data-mtab'));
      });
      tab.addEventListener('keydown', function(e){
        var tabs = [].slice.call(d.querySelectorAll('.mtab'));
        var i = tabs.indexOf(tab);
        if(i < 0) return;
        var next = null;
        if(e.key === 'ArrowRight' || e.key === 'ArrowDown') next = tabs[(i + 1) % tabs.length];
        if(e.key === 'ArrowLeft' || e.key === 'ArrowUp') next = tabs[(i - 1 + tabs.length) % tabs.length];
        if(e.key === 'Home') next = tabs[0];
        if(e.key === 'End') next = tabs[tabs.length - 1];
        if(!next) return;
        e.preventDefault();
        selectTab(next.getAttribute('data-mtab'));
        try{ next.focus(); }catch(x){}
      });
    });
  }
  /* ---- delegated clicks ---- */
  d.addEventListener('click', function(e){
    /* Benefits-page plan CTAs: activate the matching membership tab, then land on the registration section (runs after the router's own post-navigation scroll). The anchor href still drives navigation and the no-JS fallback. */
    var mplan=e.target.closest('[data-membership-plan]');
    if(mplan){
      selectTab(mplan.getAttribute('data-membership-plan'));
      var landOnReg=function(){ var rt=d.getElementById('membership-registration'); if(!rt) return; if(rt.getAttribute('tabindex')===null){ rt.setAttribute('tabindex','-1'); } try{ rt.focus({preventScroll:true}); }catch(x){} try{ rt.scrollIntoView({behavior:'smooth',block:'start'}); }catch(x){ try{ rt.scrollIntoView(); }catch(y){} } };
      if(location.pathname.indexOf('mitgliedschaft') >= 0 && location.pathname.indexOf('vorteile') < 0){
        landOnReg();
      }
      return;
    }
    var qtyBtn=e.target.closest('[data-qty-delta]');
    if(qtyBtn && modal && !modal.hidden){
      e.preventDefault();
      var q=d.getElementById('er-qty');
      if(q){
        var next=parseInt(q.value||'1',10)+parseInt(qtyBtn.getAttribute('data-qty-delta')||'0',10);
        if(!isFinite(next)||next<1) next=1;
        if(next>20) next=20;
        q.value=String(next);
        updateEventTotal();
      }
      return;
    }
    var opt=e.target.closest('.pay__opt');
    if(opt){ var grp=opt.closest('.pay__opts'); if(grp){ [].slice.call(grp.querySelectorAll('.pay__opt')).forEach(function(o){ o.setAttribute('aria-checked', String(o===opt)); o.classList.toggle('is-selected', o===opt); }); } return; }
    var tab=e.target.closest('.mtab'); if(tab){ e.preventDefault(); selectTab(tab.getAttribute('data-mtab')); return; }
    var nx=e.target.closest('[data-step-next]'); if(nx){ advance(nx); return; }
    var bk=e.target.closest('[data-step-back]'); if(bk){ goBack(bk); return; }
    if(e.target.closest('[data-modal-close]')){ closeModal(); return; }
    var sub=e.target.closest('[data-form-submit]'); if(sub){
      if(sub.getAttribute('data-form-submit')==='event'){
        /* Live bridge posts to Stripe; prototype-only path shows thank-you. */
        if(window.EG_APP && window.EG_APP.enabled) return;
      }
      finalize(sub);
      return;
    }
    var g=e.target.closest('[data-goto]'); if(g){ var t=d.getElementById(g.getAttribute('data-goto')); if(t){ try{ t.scrollIntoView({behavior:'smooth',block:'start'}); }catch(x){} } return; }
  });
  bindMembershipTabs();
  /* ---- a11y: clear aria-invalid on input ---- */
  d.addEventListener('input', function(e){
    var f=e.target; if(!f||!f.getAttribute) return;
    if(f.id==='er-qty'){ updateEventTotal(); }
    if(f.getAttribute('aria-invalid')==='true'){ var okv=(f.type==='checkbox')?f.checked:String(f.value||'').trim()!==''; if(okv) f.removeAttribute('aria-invalid'); }
  });
  /* ---- per-event: bookable upcoming -> fee + register; otherwise completed when past ---- */
  var REF = (function(){
    try{
      var n=new Date();
      var p=function(x){ return (x<10?'0':'')+x; };
      return n.getFullYear()+'-'+p(n.getMonth()+1)+'-'+p(n.getDate());
    }catch(e){ return '2026-07-10'; }
  })();
  function isUpcoming(ev){
    var dt=ev.getAttribute('data-date');
    var status=ev.getAttribute('data-status')||'';
    return status==='upcoming' && (!dt || dt >= REF);
  }
  function isBookable(ev){
    if(ev.getAttribute('data-bookable')==='1') return isUpcoming(ev);
    return false;
  }
  function bindRegisterButtons(){
    [].slice.call(d.querySelectorAll('#p-veranstaltungen .ev')).forEach(function(ev){
      var side=ev.querySelector('.ev__side'); if(!side) return;
      if(side.querySelector('.ev__reg, .ev__done, .ev__fee')) return;
      if(isBookable(ev)){
        var feeAmt=ev.getAttribute('data-price') || '10';
        var fee=d.createElement('div'); fee.className='ev__fee';
        fee.innerHTML='<span class="de">Teilnahme: '+feeAmt+' \u20ac</span><span class="en" hidden>Participation: \u20ac'+feeAmt+'</span>';
        var btn=d.createElement('button'); btn.type='button'; btn.className='ev__reg'; btn.setAttribute('data-event-open','');
        btn.innerHTML='<span class="de">F\u00fcr diese Veranstaltung anmelden</span><span class="en" hidden>Register for this event</span>';
        btn.addEventListener('click', function(){ openModal(ev, btn); });
        side.appendChild(fee); side.appendChild(btn);
      } else if(!isUpcoming(ev)){
        var done=d.createElement('div'); done.className='ev__done';
        done.innerHTML='<span class="de">Veranstaltung abgeschlossen</span><span class="en" hidden>Event completed</span>';
        side.appendChild(done);
      }
    });
  }
  if(window.EG_EVENT_REG){
    window.EG_EVENT_REG.bindRegisterButtons = bindRegisterButtons;
  } else {
    window.EG_EVENT_REG = { bindRegisterButtons: bindRegisterButtons };
  }
  bindRegisterButtons();
  /* Open the correct plan form when arriving from Vorteile CTAs (?plan=reader|expert). */
  var fromUrl = planFromUrl();
  selectTab(fromUrl || 'reader');
  if(fromUrl){
    setTimeout(function(){
      var rt=d.getElementById('membership-registration');
      if(!rt) return;
      try{ rt.scrollIntoView({behavior:'smooth',block:'start'}); }catch(x){}
    }, 120);
  }
  window.EG_MEMBERSHIP = window.EG_MEMBERSHIP || {};
  window.EG_MEMBERSHIP.selectTab = selectTab;
  window.EG_MEMBERSHIP.validateDetails = function(scope){
    if(!scope) return false;
    var s1 = stepEl(scope, 'details');
    if(!s1) return false;
    var ok = true, firstBad = null;
    var errBox = s1.querySelector('[data-formerr]');
    var errId = errBox ? (errBox.id || (errBox.id = 'vm-ferr-' + (++__ferr))) : null;
    function bad(f){
      if(!f) return;
      ok = false;
      if(!firstBad) firstBad = f;
      f.setAttribute('aria-invalid', 'true');
      if(errId) f.setAttribute('aria-describedby', errId);
    }
    [].slice.call(s1.querySelectorAll('[required]')).forEach(function(f){
      var v = (f.type === 'checkbox') ? f.checked : String(f.value || '').trim() !== '';
      if(!v) bad(f);
      else f.removeAttribute('aria-invalid');
    });
    [].slice.call(s1.querySelectorAll('[data-confirm]')).forEach(function(f){
      var o = d.getElementById(f.getAttribute('data-confirm'));
      if(o && f.value !== o.value) bad(f);
    });
    var mail = s1.querySelector('input[type="email"]');
    if(mail && !emailOk(mail.value)) bad(mail);
    if(!ok){
      stepErr(s1, true);
      if(firstBad){ try{ firstBad.focus(); }catch(x){} }
      return false;
    }
    stepErr(s1, false);
    return true;
  };
})();
