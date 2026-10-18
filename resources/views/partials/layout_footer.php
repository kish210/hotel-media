</main>

<!-- ── Company Footer ──────────────────────────────────────────────────── -->
<footer style="background:#0d1117;border-top:1px solid rgba(255,255,255,0.05);
               padding:14px 24px;display:flex;align-items:center;justify-content:space-between;
               flex-wrap:wrap;gap:10px;font-size:11px;color:#475569;">
  <div style="display:flex;align-items:center;gap:10px;">
    <a href="https://kishwifi.com" target="_blank" rel="noopener"
       style="display:flex;align-items:center;gap:8px;text-decoration:none;">
      <img src="/assets/img/sama-logo.svg" alt="سماع رایانه کیش"
           style="height:28px;width:auto;opacity:.85;"
           onerror="this.style.display='none'">
      <span style="color:#7ba4e0;font-weight:600;font-size:12px;">سماع رایانه کیش</span>
    </a>
    <span style="color:#1e293b;">|</span>
    <a href="https://kishwifi.com" target="_blank" rel="noopener"
       style="color:#c8943a;text-decoration:none;letter-spacing:0.3px;">kishwifi.com</a>
  </div>
  <div style="display:flex;align-items:center;gap:16px;">
    <span style="color:#1e293b;">Hotel Media v<?= e(config('app.version')) ?></span>
    <a href="https://github.com/kish210/hotel-media" target="_blank" rel="noopener"
       style="color:#475569;text-decoration:none;display:flex;align-items:center;gap:4px;">
      <i class="fab fa-github" style="font-size:13px;"></i> GitHub
    </a>
    <a href="/privacy/" target="_blank"
       style="color:#475569;text-decoration:none;">Privacy</a>
  </div>
</footer>

</div>

<!-- WebSocket -->
<script>
let ws = null, wsTimer = null;

function connectWS() {
  const port = <?= env('WS_PORT', 8080) ?>;
  try {
    ws = new WebSocket(`ws://${location.hostname}:${port}`);
    ws.onopen = () => {
      document.getElementById('ws-indicator').style.background = '#4ade80';
      document.getElementById('ws-status').textContent = 'Live';
      ws.send(JSON.stringify({ type: 'subscribe', channel: 'admin_<?= \App\Core\Auth::tenantId() ?>' }));
      clearTimeout(wsTimer);
    };
    ws.onmessage = (e) => {
      try {
        const msg = JSON.parse(e.data);
        if (msg.type === 'screen_online')  showToast('success', (msg.data?.name || 'صفحه') + ' آنلاین شد');
        if (msg.type === 'screen_offline') showToast('error',   (msg.data?.name || 'صفحه') + ' آفلاین شد');
        if (msg.type === 'notification')   showToast('error',    msg.data?.title || 'اعلان جدید');
      } catch(e) {}
    };
    ws.onclose = () => {
      document.getElementById('ws-indicator').style.background = '#f87171';
      document.getElementById('ws-status').textContent = '—';
      wsTimer = setTimeout(connectWS, 5000);
    };
    ws.onerror = () => ws.close();
  } catch(e) {}
}

connectWS();

function showToast(type, msg) {
  /* شش صفحه‌ی پنل (خدمات مهمان، اتاق‌ها، منوها، EPG، تی‌وی‌هدند،
     دستگاه‌ها) تابع خودشان را با ترتیب (msg, type) تعریف کرده بودند.
     این تعریف بعد از آن‌ها بار می‌شود و جایشان را می‌گیرد، پس هر
     پیامشان «undefined» نشان داده می‌شد. هر دو ترتیب پذیرفته می‌شود. */
  const TYPES = ['success', 'error', 'info', 'warning'];
  if (!TYPES.includes(type)) {
    const text = type;
    type = TYPES.includes(msg) ? msg : 'success';
    msg  = text;
  }
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'circle-xmark'}"></i> ${msg}`;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 4000);
}

window.showToast = showToast;

// Auto-dismiss flash
setTimeout(() => { document.getElementById('flash-msg')?.remove(); }, 4000);

// دیالوگ تأیید یکپارچه: عنوان با نام آیتم، یک خط پیامد، دکمهٔ قرمز «حذف» و
// «انصراف» که به‌طور پیش‌فرض فوکوس دارد (Enter تصادفی چیزی را پاک نمی‌کند).
// data-confirm="متن" [data-confirm-name="نام"] [data-confirm-ok="حذف"] روی دکمه یا فرم.
window.confirmDialog = function (o) {
  return new Promise(function (resolve) {
    const ov = document.createElement('div');
    ov.className = 'confirm-ov'; ov.setAttribute('role', 'alertdialog'); ov.setAttribute('aria-modal', 'true');
    const box = document.createElement('div'); box.className = 'confirm-box';
    const h = document.createElement('h3'); h.textContent = o.title || 'مطمئن هستید؟';
    const p = document.createElement('p'); p.textContent = o.text || '';
    const row = document.createElement('div'); row.className = 'row';
    const ok = document.createElement('button'); ok.type = 'button'; ok.className = o.danger === false ? '' : 'danger'; ok.textContent = o.ok || 'حذف';
    const no = document.createElement('button'); no.type = 'button'; no.textContent = 'انصراف';
    row.append(ok, no); box.append(h, p, row); ov.append(box); document.body.appendChild(ov);
    function done(v) { document.removeEventListener('keydown', key); ov.remove(); resolve(v); }
    function key(e) { if (e.key === 'Escape') done(false); }
    ok.onclick = function () { done(true); }; no.onclick = function () { done(false); };
    ov.addEventListener('click', function (e) { if (e.target === ov) done(false); });
    document.addEventListener('keydown', key); no.focus();
  });
};
document.addEventListener('click', function (e) {
  const el = e.target.closest && e.target.closest('[data-confirm]');
  if (!el || el.dataset.confirmed === '1') return;
  e.preventDefault(); e.stopPropagation();
  const name = el.dataset.confirmName;
  window.confirmDialog({
    title: name ? ('«' + name + '» ' + (el.dataset.confirmOk || 'حذف') + ' شود؟') : el.dataset.confirm,
    text: name ? el.dataset.confirm : '',
    ok: el.dataset.confirmOk || 'حذف'
  }).then(function (yes) {
    if (!yes) return;
    el.dataset.confirmed = '1';
    if (el.tagName === 'A' || el.type === 'submit' || el.tagName === 'BUTTON') el.click(); else if (el.form) el.form.submit();
  });
}, true);

// جلوگیری از ثبت دوبارهٔ فرم (دابل‌کلیک): دکمه‌های ثبت بعد از ارسال غیرفعال می‌شوند.
// فرم‌هایی که با fetch ارسال می‌شوند (preventDefault) دست‌نخورده‌اند.
document.addEventListener('submit', function (e) {
  const f = e.target;
  setTimeout(function () {
    if (e.defaultPrevented || !f || f.dataset.noLock) return;
    f.querySelectorAll('button[type=submit],button:not([type]),input[type=submit]').forEach(function (b) { b.disabled = true; });
    setTimeout(function () { f.querySelectorAll('button,input[type=submit]').forEach(function (b) { b.disabled = false; }); }, 8000);
  }, 0);
});
</script>

<?php if (isset($extraScript)): ?>
<script><?= $extraScript ?></script>
<?php endif; ?>

</body>
</html>
