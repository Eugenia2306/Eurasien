
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
