/* ═══════════════════════════════════════════════════════════════════
   جست‌وجوی سریع پنل — Ctrl+K (یا ⌘K)، و دکمهٔ ذره‌بین در نوار بالا.

   چرا: سایدبار حدود ۵۰ لینک دارد و اپراتور برای رسیدن به «تیونر» یا
   «زیرنویس» باید بین بخش‌ها بگردد. اینجا نام را می‌نویسد و Enter می‌زند.

   فهرست از خودِ سایدبار خوانده می‌شود، نه از یک فهرست جدا: هر لینکی که
   نقش کاربر اجازه‌اش را ندارد اصلا در سایدبار نیست، پس اینجا هم نیست.
   یعنی هیچ‌وقت نمی‌شود صفحه‌ای را دید که نباید.
   ═══════════════════════════════════════════════════════════════════ */
(function (w, d) {
  'use strict';

  var ID = 'cmdp';

  /* ی/ك عربی و فارسی یکی می‌شوند و ZWNJ برداشته می‌شود، وگرنه «زیرنویس»
     با نیم‌فاصله پیدا نمی‌شد. */
  function norm(s) {
    return String(s || '').toLowerCase()
      .replace(/ي/g, 'ی').replace(/ك/g, 'ک')
      .replace(/‌/g, ' ').replace(/\s+/g, ' ').trim();
  }

  function collect() {
    var out = [], seen = {}, i, a, txt, sec = '';
    var nodes = d.querySelectorAll('.sidebar .sidebar-section, .sidebar a.sidebar-link');
    for (i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      if (el.classList.contains('sidebar-section')) { sec = (el.textContent || '').trim(); continue; }
      a = el;
      txt = (a.textContent || '').replace(/\s+/g, ' ').trim();
      if (!txt || !a.getAttribute('href') || seen[a.getAttribute('href') + txt]) continue;
      seen[a.getAttribute('href') + txt] = 1;
      out.push({ label: txt, href: a.getAttribute('href'), section: sec, key: norm(txt + ' ' + sec) });
    }
    return out;
  }

  var items = [], filtered = [], idx = 0;

  function box() {
    var el = d.getElementById(ID);
    if (el) return el;
    el = d.createElement('div');
    el.id = ID;
    el.innerHTML =
      '<div class="cmdp-bd"></div>' +
      '<div class="cmdp-win" role="dialog" aria-modal="true" aria-label="جست‌وجوی سریع">' +
      '<input id="cmdp-q" type="search" autocomplete="off" placeholder="نام صفحه را بنویسید…" aria-label="جست‌وجو">' +
      '<div id="cmdp-list" role="listbox"></div>' +
      '<div class="cmdp-foot">↑↓ انتخاب · Enter رفتن · Esc بستن</div></div>';
    d.body.appendChild(el);
    el.querySelector('.cmdp-bd').addEventListener('click', close);
    el.querySelector('#cmdp-q').addEventListener('input', function () { search(this.value); });
    return el;
  }

  function render() {
    var list = d.getElementById('cmdp-list');
    if (!filtered.length) {
      list.innerHTML = '<div class="cmdp-empty">چیزی پیدا نشد</div>';
      return;
    }
    list.innerHTML = filtered.slice(0, 12).map(function (it, i) {
      return '<a class="cmdp-it' + (i === idx ? ' on' : '') + '" role="option" href="' + it.href + '">' +
        '<span>' + it.label + '</span>' +
        (it.section ? '<small>' + it.section + '</small>' : '') + '</a>';
    }).join('');
  }

  function search(q) {
    var n = norm(q);
    filtered = !n ? items.slice(0, 12) : items.filter(function (it) { return it.key.indexOf(n) >= 0; });
    idx = 0;
    render();
  }

  function open() {
    items = collect();
    box().classList.add('on');
    var q = d.getElementById('cmdp-q');
    q.value = ''; search('');
    setTimeout(function () { q.focus(); }, 30);
  }
  function close() { var el = d.getElementById(ID); if (el) el.classList.remove('on'); }
  function isOpen() { var el = d.getElementById(ID); return !!el && el.classList.contains('on'); }

  d.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
      e.preventDefault(); isOpen() ? close() : open(); return;
    }
    if (!isOpen()) return;
    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      var max = Math.min(filtered.length, 12) - 1;
      idx = e.key === 'ArrowDown' ? Math.min(idx + 1, max) : Math.max(idx - 1, 0);
      render(); return;
    }
    if (e.key === 'Enter' && filtered[idx]) { e.preventDefault(); w.location.href = filtered[idx].href; }
  });

  w.openCommandPalette = open;
})(window, document);
