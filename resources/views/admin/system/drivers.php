<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<?php
/** @var bool $supported @var bool $ready @var array $drivers @var string $appDir */
$drivers = $drivers ?? [];
?>
<style>
  .dv-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:16px;margin-bottom:12px;}
  .dv-badge{font-size:11px;padding:2px 10px;border-radius:999px;white-space:nowrap;}
  .dv-mono{font-family:ui-monospace,monospace;direction:ltr;text-align:left;}
</style>

<div style="margin-bottom:18px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-microchip" style="color:#f59e0b;margin-left:10px;"></i>درایورهای ترنسکدر و کارت کپچر
  </h1>
  <p style="font-size:12px;color:#64748b;margin-top:4px;line-height:1.9;">
    اگر سرور به اینترنت (یا مخزن داخلی) دسترسی دارد «نصب از اینترنت» را بزنید. اگر ندارد، فایل درایور را روی کامپیوتر دیگری
    بگیرید و اینجا بارگذاری کنید. بعد از نصب، صفحه‌ی ترنسکدر انکودر تازه را خودش می‌بیند.
  </p>
</div>

<?php if (!$supported): ?>
  <div class="dv-card" style="border-color:rgba(245,158,11,.4);color:#fbbf24;">نصب درایور از پنل فقط روی سرور لینوکس (اوبونتو) ممکن است.</div>
<?php elseif (!$ready): ?>
  <div class="dv-card" style="border-color:rgba(245,158,11,.4);">
    <div style="color:#fbbf24;font-weight:700;margin-bottom:6px;"><i class="fas fa-triangle-exclamation ml-1"></i>ابزار نصب روی سرور تنظیم نشده</div>
    <div style="font-size:12px;color:#94a3b8;line-height:2;">
      نصب بسته دسترسی root می‌خواهد و پنل با کاربر www-data اجرا می‌شود. یک بار روی سرور این را اجرا کنید
      (نصب‌کننده‌ی production از این نسخه به بعد خودش این کار را می‌کند):
    </div>
    <pre class="dv-mono" style="font-size:11px;color:#cbd5e1;background:rgba(0,0,0,.3);padding:10px;border-radius:8px;margin-top:8px;white-space:pre-wrap;">sudo install -o root -g root -m 0755 <?= e($appDir) ?>/deploy/hotel-media-driver.sh /usr/local/sbin/hotel-media-driver
sudo sed -i 's#__APP_DIR__#<?= e($appDir) ?>#' /usr/local/sbin/hotel-media-driver
echo 'www-data ALL=(root) NOPASSWD: /usr/local/sbin/hotel-media-driver' | sudo tee /etc/sudoers.d/hotel-media-driver
sudo chmod 0440 /etc/sudoers.d/hotel-media-driver</pre>
  </div>
<?php endif; ?>

<div id="list">
<?php foreach ($drivers as $d):
  $inst = $d['version'] !== ''; ?>
  <div class="dv-card" data-id="<?= e($d['id']) ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
      <div style="flex:1;min-width:260px;">
        <div style="font-size:15px;font-weight:800;color:#fff;"><?= e($d['name']) ?></div>
        <div style="font-size:12px;color:#94a3b8;margin-top:3px;"><?= e($d['why']) ?></div>
        <div style="margin-top:8px;">
          <span class="dv-badge dv-st" style="<?= $inst ? 'background:rgba(34,197,94,.12);color:#4ade80;' : 'background:rgba(100,116,139,.15);color:#94a3b8;' ?>">
            <?= $inst ? 'نصب است — ' . e($d['version']) : 'نصب نیست' ?>
          </span>
          <?php if ($d['hardware'] !== null): ?>
            <span class="dv-badge" style="<?= $d['hardware'] ? 'background:rgba(59,130,246,.12);color:#60a5fa;' : 'background:rgba(100,116,139,.12);color:#64748b;' ?>">
              <?= $d['hardware'] ? 'سخت‌افزارش روی این سرور هست' : 'سخت‌افزارش روی این سرور پیدا نشد' ?>
            </span>
          <?php endif; ?>
          <span class="dv-badge dv-job"></span>
        </div>
        <div style="font-size:11px;color:#64748b;margin-top:8px;">دانلود دستی: <?= e($d['manual']) ?></div>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
        <?php if ($d['online']): ?>
          <button class="btn-primary text-xs px-3" onclick="installOnline('<?= e($d['id']) ?>')" <?= $ready ? '' : 'disabled' ?>>
            <i class="fas fa-cloud-arrow-down"></i> نصب از اینترنت
          </button>
        <?php endif; ?>
        <label class="btn-ghost text-xs px-3" style="cursor:<?= $ready ? 'pointer' : 'not-allowed' ?>;<?= $ready ? '' : 'opacity:.5' ?>">
          <i class="fas fa-file-arrow-up"></i> بارگذاری فایل (<?= e(implode('، ', array_map(fn($t) => '.' . $t, $d['types']))) ?>)
          <input type="file" style="display:none" <?= $ready ? '' : 'disabled' ?>
                 accept="<?= e(implode(',', array_map(fn($t) => $t === 'tar.gz' ? '.tar.gz,.tgz' : '.' . $t, $d['types']))) ?>"
                 onchange="upload('<?= e($d['id']) ?>', this)">
        </label>
        <button class="btn-ghost text-xs px-3" onclick="showLog('<?= e($d['id']) ?>')"><i class="fas fa-file-lines"></i> لاگ</button>
      </div>
    </div>
    <div class="dv-progress" style="display:none;margin-top:10px;">
      <div style="height:6px;background:rgba(255,255,255,.08);border-radius:4px;overflow:hidden;"><div class="dv-bar" style="height:100%;width:0;background:#3b82f6;"></div></div>
      <div class="dv-ptext" style="font-size:11px;color:#94a3b8;margin-top:4px;"></div>
    </div>
    <pre class="dv-log dv-mono" style="display:none;font-size:11px;color:#cbd5e1;background:rgba(0,0,0,.35);padding:10px;border-radius:8px;margin-top:10px;max-height:320px;overflow:auto;white-space:pre-wrap;"></pre>
  </div>
<?php endforeach; ?>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const card = id => document.querySelector(`.dv-card[data-id="${id}"]`);
let watching = {};

async function installOnline(id) {
  if (!confirm('نصب از اینترنت شروع شود؟ ممکن است چند دقیقه طول بکشد.')) return;
  try {
    const r = await fetch(`/admin/system/drivers/${id}/install`, { method: 'POST', credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF } });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(d.message || 'خطا');
    showToast('success', d.message); watch(id);
  } catch (e) { showToast('error', e.message); }
}

function upload(id, input) {
  const f = input.files[0]; if (!f) return;
  const c = card(id), bar = c.querySelector('.dv-bar'), txt = c.querySelector('.dv-ptext');
  c.querySelector('.dv-progress').style.display = '';
  const fd = new FormData(); fd.append('package', f); fd.append('_token', CSRF);
  /* XHR نه fetch: درصد بارگذاری فایل ۳۰۰ مگابایتی NVIDIA را نشان می‌دهد */
  const x = new XMLHttpRequest();
  x.open('POST', `/admin/system/drivers/${id}/upload`);
  x.setRequestHeader('Accept', 'application/json');
  x.setRequestHeader('X-CSRF-Token', CSRF);
  x.upload.onprogress = e => { if (e.lengthComputable) { const p = Math.round(e.loaded / e.total * 100); bar.style.width = p + '%'; txt.textContent = `بارگذاری ${p}٪ — ${f.name}`; } };
  x.onload = () => {
    let d = {}; try { d = JSON.parse(x.responseText); } catch (_) {}
    input.value = '';
    if (x.status >= 200 && x.status < 300) { txt.textContent = 'بارگذاری کامل شد — در حال نصب…'; showToast('success', d.message); watch(id); }
    else { txt.textContent = d.message || `خطا (${x.status})`; showToast('error', d.message || 'بارگذاری ناموفق بود'); }
  };
  x.onerror = () => { txt.textContent = 'ارتباط قطع شد'; };
  x.send(fd);
}

async function showLog(id) {
  const pre = card(id).querySelector('.dv-log');
  if (pre.style.display !== 'none' && !watching[id]) { pre.style.display = 'none'; return; }
  pre.style.display = ''; await refreshLog(id);
}

async function refreshLog(id) {
  const c = card(id), pre = c.querySelector('.dv-log'), badge = c.querySelector('.dv-job');
  try {
    const r = await fetch(`/admin/system/drivers/${id}/log`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    const d = (await r.json()).data;
    pre.textContent = d.log || 'هنوز نصبی انجام نشده';
    pre.scrollTop = pre.scrollHeight;
    const j = d.job || {};
    if (j.running) { badge.textContent = 'در حال نصب…'; badge.style.cssText = 'background:rgba(59,130,246,.15);color:#60a5fa;'; }
    else if (j.exit === 0) { badge.textContent = 'آخرین نصب موفق'; badge.style.cssText = 'background:rgba(34,197,94,.12);color:#4ade80;'; }
    else if (j.exit !== null && j.exit !== undefined) { badge.textContent = 'آخرین نصب ناموفق — لاگ را ببینید'; badge.style.cssText = 'background:rgba(239,68,68,.12);color:#f87171;'; }
    return j.running;
  } catch (_) { return false; }
}

function watch(id) {
  card(id).querySelector('.dv-log').style.display = '';
  if (watching[id]) return;
  watching[id] = setInterval(async () => {
    const running = await refreshLog(id);
    if (!running) { clearInterval(watching[id]); delete watching[id]; setTimeout(() => location.reload(), 1500); }
  }, 2000);
  refreshLog(id);
}

document.querySelectorAll('.dv-card').forEach(c => refreshLog(c.dataset.id));
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
