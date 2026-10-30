// Mobile menu
(function () {
  var b = document.body, btn = document.getElementById('menuBtn'), scrim = document.getElementById('scrim');
  if (btn) btn.addEventListener('click', function () { b.classList.toggle('nav-open'); });
  if (scrim) scrim.addEventListener('click', function () { b.classList.remove('nav-open'); });
})();

// Ask before destructive or money-moving actions: <form data-confirm="...">
document.addEventListener('submit', function (ev) {
  var msg = ev.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) ev.preventDefault();
});

// Payment form: show the selected account's balance and suggest it as the amount
(function () {
  var sel = document.getElementById('accountSelect');
  if (!sel) return;
  var out = document.getElementById('balanceHint'), amt = document.getElementById('amountInput');
  function update() {
    var o = sel.options[sel.selectedIndex];
    var bal = o ? parseFloat(o.getAttribute('data-balance') || '0') : 0;
    if (!o || !o.value) { out.textContent = ''; return; }
    out.textContent = bal > 0 ? 'Outstanding balance: KES ' + bal.toLocaleString('en-KE', {minimumFractionDigits: 2})
      : (bal < 0 ? 'In credit: KES ' + Math.abs(bal).toLocaleString('en-KE', {minimumFractionDigits: 2}) : 'Account is fully paid up.');
    if (amt && !amt.value && bal > 0) amt.value = bal.toFixed(2);
  }
  sel.addEventListener('change', update); update();
})();

// Unit form: show sale price only for sale units
(function () {
  var cat = document.getElementById('categorySelect');
  if (!cat) return;
  function update() {
    document.querySelectorAll('[data-sale-only]').forEach(function (el) { el.style.display = cat.value === 'Sale' ? '' : 'none'; });
    var lbl = document.getElementById('monthlyLabel');
    if (lbl) lbl.firstChild.textContent = cat.value === 'Sale' ? 'Monthly instalment (KES) ' : 'Monthly rent (KES) ';
  }
  cat.addEventListener('change', update); update();
})();
