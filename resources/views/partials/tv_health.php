<?php
/**
 * نوار «تلویزیون کار می‌کند؟» + چک‌لیست راه‌اندازی. فقط برای نقش فنی و وقتی ماژول IPTV روشن است.
 * داده از GET /admin/tv/health می‌آید (۸ ثانیه کش سرور).
 */
$__role = \App\Core\Auth::user()['role'] ?? '';
if (!in_array($__role, ['super_admin', 'admin'], true)) return;
?>
<div id="tvh" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:14px 18px;margin-bottom:20px;" aria-live="polite">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <span id="tvh-dot" style="width:12px;height:12px;border-radius:50%;background:#64748b;display:inline-block;"></span>
    <strong id="tvh-title" style="font-size:14px;color:#fff;">تلویزیون زنده</strong>
    <span id="tvh-sub" style="font-size:12px;color:#94a3b8;">در حال بررسی…</span>
    <a href="/admin/system/tuner" style="margin-inline-start:auto;font-size:12px;color:#60a5fa;text-decoration:none;">تیونر و اسکن ←</a>
  </div>
  <div id="tvh-problems" style="margin-top:10px;"></div>
  <div id="tvh-checks" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;"></div>
</div>
<script>
(function () {
  const $ = id => document.getElementById(id);
  const esc = s => String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const COL = { ok: '#22c55e', warn: '#f59e0b', down: '#ef4444', none: '#94a3b8' };
  const SUB = { ok: 'همه‌چیز سالم است', warn: 'نیاز به توجه', down: 'کار نمی‌کند', none: 'هنوز راه‌اندازی نشده' };
  async function load() {
    if (document.hidden) return;
    try {
      const r = await fetch('/admin/tv/health', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      if (!r.ok) return;
      const d = (await r.json()).data; if (!d) return;
      $('tvh-dot').style.background = COL[d.verdict] || COL.none;
      let sub = SUB[d.verdict] || '';
      if (d.tuner) sub += ' · ' + d.channels + ' کانال · ' + d.tuner.viewers + ' بیننده';
      $('tvh-sub').textContent = sub;
      $('tvh-problems').innerHTML = (d.problems || []).map(p =>
        '<div style="display:flex;gap:10px;align-items:flex-start;padding:6px 0;font-size:12.5px;line-height:1.9;color:' +
        (p.level === 'info' ? '#94a3b8' : '#e2e8f0') + ';"><span style="color:' + (p.level === 'down' ? '#ef4444' : p.level === 'warn' ? '#f59e0b' : '#60a5fa') +
        ';">●</span><span style="flex:1;">' + esc(p.text) + '</span>' +
        (p.url ? '<a href="' + esc(p.url) + '" style="white-space:nowrap;color:#60a5fa;text-decoration:none;">' + esc(p.label) + '</a>' : '') + '</div>').join('');
      const done = (d.checks || []).filter(c => c.ok).length, all = (d.checks || []).length;
      $('tvh-checks').style.display = done === all ? 'none' : 'flex';
      $('tvh-checks').innerHTML = '<span style="font-size:11px;color:#8593a8;align-self:center;">راه‌اندازی ' + done + '/' + all + '</span>' +
        (d.checks || []).map(c => '<a href="' + esc(c.url) + '" style="font-size:11.5px;text-decoration:none;padding:4px 10px;border-radius:999px;border:1px solid ' +
          (c.ok ? 'rgba(34,197,94,.4)' : 'rgba(245,158,11,.5)') + ';color:' + (c.ok ? '#86efac' : '#fcd34d') + ';">' + (c.ok ? '✓ ' : '○ ') + esc(c.label) + '</a>').join('');
    } catch (e) {}
  }
  load(); setInterval(load, 15000);
})();
</script>
