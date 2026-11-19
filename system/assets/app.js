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

// Listing photos: send one at a time so a large set never hits the server's total upload limit.
// Redirects are not followed, so the messages stay in the session for the reload to show.
(function () {
  var form = document.getElementById('photoUpload');
  if (!form) return;
  var input = form.querySelector('input[type=file]'), btn = form.querySelector('button'), out = document.getElementById('photoProgress');
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var files = Array.prototype.slice.call(input.files), i = 0;
    btn.disabled = true;
    function next() {
      if (i >= files.length) { window.location.reload(); return; }
      out.textContent = 'Uploading photo ' + (i + 1) + ' of ' + files.length + '. Keep this page open.';
      var data = new FormData(form);
      data.delete('photos[]');
      data.append('photos[]', files[i]);
      fetch(window.location.href, {method: 'POST', body: data, redirect: 'manual', credentials: 'same-origin'}).then(function (r) {
        if (r.type !== 'opaqueredirect' && !r.ok) throw new Error('upload');
        i++;
        next();
      }).catch(function () {
        out.textContent = 'Photo ' + (i + 1) + ' could not be uploaded. It may be too large. Reload the page and try again.';
        btn.disabled = false;
      });
    }
    next();
  });
})();

// Meter readings: units used, amount and totals as you type
(function () {
  var form = document.getElementById('readingsForm');
  if (!form) return;
  var priceIn = document.getElementById('mrPrice');
  function kes(n) { return n.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
  function qty(n) { return n.toLocaleString('en-KE', {maximumFractionDigits: 2}); }
  function update() {
    var price = parseFloat(priceIn.value), units = 0, total = 0;
    form.querySelectorAll('[data-row]').forEach(function (tr) {
      var prevIn = tr.querySelector('[data-prev]'), curIn = tr.querySelector('[data-cur]');
      var prev = parseFloat(prevIn.value), cur = parseFloat(curIn.value);
      var used = tr.querySelector('[data-used]'), pr = tr.querySelector('[data-price]'), amt = tr.querySelector('[data-amount]');
      pr.textContent = isNaN(price) ? '—' : kes(price);
      tr.classList.remove('row-error');
      if (curIn.value === '' || isNaN(cur) || isNaN(prev)) { used.textContent = '—'; amt.textContent = '—'; return; }
      var u = Math.round((cur - prev) * 100) / 100;
      if (u < 0) { tr.classList.add('row-error'); used.textContent = 'Lower than previous'; amt.textContent = '—'; return; }
      var a = isNaN(price) ? 0 : Math.round(u * price * 100) / 100;
      used.textContent = qty(u); amt.textContent = isNaN(price) ? '—' : kes(a);
      units += u; total += a;
    });
    document.getElementById('mrUnits').textContent = qty(units);
    document.getElementById('mrTotal').textContent = 'KES ' + kes(total);
  }
  form.addEventListener('input', update);
  form.addEventListener('change', function (ev) {
    var box = ev.target;
    if (!box.hasAttribute('data-replaced')) return;
    var prevIn = box.closest('tr').querySelector('[data-prev]'), locked = prevIn.getAttribute('data-locked');
    if (locked === '') return;                       // no earlier reading: always typed by hand
    if (box.checked) { prevIn.readOnly = false; prevIn.value = ''; prevIn.focus(); }
    else { prevIn.readOnly = true; prevIn.value = locked; }
    update();
  });
  update();
})();
