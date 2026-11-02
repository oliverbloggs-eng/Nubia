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
