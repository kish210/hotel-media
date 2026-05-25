</main>
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
      document.getElementById('ws-status').textContent = 'زنده';
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
      document.getElementById('ws-status').textContent = 'قطع';
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
