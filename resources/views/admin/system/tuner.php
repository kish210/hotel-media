<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<?php
/** @var bool $hasSrc @var array $presets */
?>
<style>
  .tn-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:16px;margin-bottom:14px;}
  .tn-h{font-size:13px;font-weight:700;color:#fff;margin-bottom:10px;}
  .tn-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
  .tn-stat{background:rgba(255,255,255,.04);border-radius:10px;padding:10px 14px;min-width:110px;text-align:center;}
  .tn-stat b{display:block;font-size:20px;color:#fff;}
  .tn-stat span{font-size:11px;color:#64748b;}
  .tn-mono{font-family:ui-monospace,monospace;direction:ltr;}
  .tn-step{font-size:12px;color:#94a3b8;line-height:2;}
</style>

<div style="margin-bottom:18px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-satellite-dish" style="color:#ef4444;margin-left:10px;"></i>تیونر و اسکن کانال
  </h1>
  <p style="font-size:12px;color:#64748b;margin-top:4px;line-height:1.9;">
    تیونر USB را وصل کنید، «راه‌اندازی» و بعد «اسکن» را بزنید و در پایان «ورود کانال‌ها». هر بار که تیونر عوض شد همین مراحل
    کافی است؛ نصب مجدد لازم نیست. فقط API رسمی TVHeadend استفاده می‌شود و تنظیمات داخلی آن دست نمی‌خورد.
  </p>
</div>

<?php if (!$hasSrc): ?>
  <div class="tn-card" style="border-color:rgba(245,158,11,.4);color:#fbbf24;">
    TVHeadend در برنامه ثبت نیست. اول از «IPTV ← TVHeadend» سرور را اضافه کنید.
  </div>
<?php else: ?>

<div class="tn-card">
  <div class="tn-h"><i class="fas fa-plug ml-1" style="color:#22c55e;"></i>وضعیت</div>
  <div id="tn-err" style="display:none;color:#f87171;font-size:12px;margin-bottom:8px;"></div>
  <div id="tn-inputs" class="tn-step">در حال خواندن…</div>
  <div class="tn-row" style="margin-top:12px;" id="tn-stats"></div>
  <div id="tn-muxes" style="margin-top:14px;"></div>
  <div class="tn-step" style="margin-top:8px;">
    <i class="fas fa-circle-info ml-1" style="color:#60a5fa;"></i>
    هر تیونر در هر لحظه فقط <b>یک فرکانس (mux)</b> را می‌گیرد. کانال‌های هم‌فرکانس (مثلا همه‌ی کانال‌های ۶۸۲) با هم قابل‌پخش‌اند، ولی
    کانالی از فرکانس دیگر تا آزاد‌شدن تیونر پخش نمی‌شود (در پلیر «در دسترس نیست»). برای پخش هم‌زمان از چند فرکانس تیونر بیشتر لازم است.
  </div>
</div>

<div class="tn-card">
  <div class="tn-h"><i class="fas fa-sliders ml-1" style="color:#f59e0b;"></i>۱) راه‌اندازی تیونر</div>
  <div class="tn-row">
    <select id="tn-preset" class="form-input" style="max-width:420px;">
      <?php foreach ($presets as $k => $p): ?>
        <option value="<?= htmlspecialchars((string)$k) ?>"><?= htmlspecialchars((string)$p['label']) ?></option>
      <?php endforeach; ?>
      <option value="">فرکانس دلخواه…</option>
    </select>
    <input id="tn-freqs" class="form-input tn-mono" style="max-width:260px;display:none;" placeholder="578, 682, 730 (مگاهرتز)">
    <button class="btn-primary text-sm" onclick="act('setup')"><i class="fas fa-wand-magic-sparkles ml-1"></i>راه‌اندازی</button>
  </div>
  <div class="tn-step" style="margin-top:8px;">
    شبکه و فرکانس‌ها ساخته و تیونر به آن وصل می‌شود. چندبار زدن مشکلی ندارد (فرکانس تکراری ساخته نمی‌شود).
    پارامترها خودکار (AUTO) هستند.
  </div>
</div>

<div class="tn-card">
  <div class="tn-h"><i class="fas fa-magnifying-glass ml-1" style="color:#3b82f6;"></i>۲) اسکن</div>
  <div class="tn-row">
    <button class="btn-primary text-sm" onclick="act('scan')"><i class="fas fa-play ml-1"></i>شروع اسکن</button>
    <span id="tn-scanning" class="tn-step" style="display:none;color:#fbbf24;"><i class="fas fa-spinner fa-spin ml-1"></i>اسکن در جریان…</span>
  </div>
  <div class="tn-step" style="margin-top:8px;">
    اسکن چند دقیقه طول می‌کشد. در این مدت تعداد سرویس‌ها بالا می‌رود. اگر بعد از پایان صفر ماند، آنتن یا فرکانس‌ها را بررسی کنید.
  </div>
</div>

<div class="tn-card">
  <div class="tn-h"><i class="fas fa-download ml-1" style="color:#a855f7;"></i>۳) ورود کانال‌ها به برنامه</div>
  <button class="btn-primary text-sm" onclick="act('import')"><i class="fas fa-file-import ml-1"></i>ورود کانال‌ها</button>
  <div class="tn-step" style="margin-top:8px;">
    سرویس‌های پیداشده را کانال می‌کند (تلویزیون و رادیو جدا) و با لوگوی کانال‌های ایرانی وارد IPTV می‌کند.
  </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const $ = id => document.getElementById(id);
let busy = false;

$('tn-preset').addEventListener('change', e => { $('tn-freqs').style.display = e.target.value === '' ? '' : 'none'; });

function esc(s) { return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

async function refresh() {
  try {
    const r = await fetch('/admin/system/tuner/status', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    const d = (await r.json()).data || {};
    $('tn-err').style.display = d.reachable === false ? '' : 'none';
    $('tn-err').textContent = d.error || '';
    if (d.reachable === false) { $('tn-inputs').textContent = ''; return; }

    $('tn-inputs').innerHTML = (d.inputs || []).length
      ? d.inputs.map(i => `<div><i class="fas fa-microchip ml-1" style="color:#22c55e"></i>${esc(i.name)}
          — سیگنال ${i.signal} · SNR ${i.snr}</div>`).join('')
      : '<span style="color:#f87171"><i class="fas fa-triangle-exclamation ml-1"></i>تیونری دیده نشد — دستگاه را وصل کنید و درایور را از «درایورها» نصب کنید.</span>';

    const n = d.network, fe = d.frontends || [];
    const linked = fe.filter(f => f.enabled && f.linked).length;
    $('tn-stats').innerHTML = [
      ['فرکانس', n ? n.mux : 0], ['سرویس', n ? n.svc : 0], ['کانال', n ? n.chn : 0],
      ['تیونر وصل', linked + '/' + fe.length], ['صف اسکن', n ? n.queue : 0],
    ].map(([l, v]) => `<div class="tn-stat"><b>${v}</b><span>${l}</span></div>`).join('');
    $('tn-scanning').style.display = (n && n.queue > 0) ? '' : 'none';

    /* جدول فرکانس‌ها + روی کدام فرکانس هستیم */
    const tuned = (d.inputs || []).map(i => i.stream).filter(Boolean).join(' ');
    const STATE = ['', 'در صف اسکن', 'در حال اسکن'], RES = ['نامشخص', 'موفق', 'ناموفق'];
    $('tn-muxes').innerHTML = (d.muxes || []).length ? '<table style="width:100%;font-size:12px;border-collapse:collapse;">' +
      '<tr style="color:#64748b;text-align:right;"><th style="padding:4px 6px;">فرکانس</th><th>نوع</th><th>اسکن</th><th>سرویس</th><th>کانال</th><th></th></tr>' +
      d.muxes.map(m => {
        const here = tuned.indexOf(m.mhz + 'MHz') === 0 || tuned.indexOf(' ' + m.mhz + 'MHz') >= 0;
        const col = m.svc > 0 ? '#22c55e' : (m.result === 2 ? '#f87171' : '#94a3b8');
        return '<tr style="border-top:1px solid rgba(255,255,255,.05);">' +
          '<td style="padding:5px 6px;" class="tn-mono">' + m.mhz + ' MHz</td><td>' + esc(m.delsys) + '</td>' +
          '<td style="color:' + col + ';">' + (m.state ? STATE[m.state] : RES[m.result]) + '</td>' +
          '<td>' + m.svc + '</td><td>' + m.chn + '</td>' +
          '<td>' + (here ? '<span style="color:#fbbf24;"><i class="fas fa-satellite-dish ml-1"></i>تیونر الان اینجاست</span>' : '') + '</td></tr>';
      }).join('') + '</table>' : '';
  } catch (e) { $('tn-err').style.display = ''; $('tn-err').textContent = e.message; }
}

async function act(what) {
  if (busy) return; busy = true;
  const body = new URLSearchParams();
  if (what === 'setup') {
    const p = $('tn-preset').value;
    if (p) body.set('preset', p); else body.set('freqs', $('tn-freqs').value);
  }
  try {
    const r = await fetch('/admin/system/tuner/' + what, { method: 'POST', credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF, 'Content-Type': 'application/x-www-form-urlencoded' }, body });
    const d = await r.json().catch(() => ({}));
    if (!r.ok || d.success === false) throw new Error(d.message || 'خطا');
    showToast('success', d.message);
  } catch (e) { showToast('error', e.message); }
  busy = false; refresh();
}

refresh(); setInterval(refresh, 4000);
</script>
<?php endif; ?>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
