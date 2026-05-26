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
    <span style="color:#1e293b;">SignageCMS v1.6.0</span>
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
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'circle-xmark'}"></i> ${msg}`;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 4000);
}

window.showToast = showToast;

// Auto-dismiss flash
setTimeout(() => { document.getElementById('flash-msg')?.remove(); }, 4000);

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(btn => {
  btn.addEventListener('click', e => { if (!confirm(btn.dataset.confirm)) e.preventDefault(); });
});
</script>

<?php if (isset($extraScript)): ?>
<script><?= $extraScript ?></script>
<?php endif; ?>

</body>
</html>
