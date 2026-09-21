<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<?php
$sources    = $sources    ?? [];
$tvhSources = $tvhSources ?? [];
$channels   = $channels   ?? [];
$unmatched  = $unmatched  ?? [];
$stats      = $stats      ?? [];

$TYPE_LABELS = [
  'tvheadend'  => ['TVHeadend', '#f87171'],
  'xmltv_url'  => ['XMLTV (آدرس)', '#60a5fa'],
  'xmltv_file' => ['XMLTV (فایل)', '#a78bfa'],
];
?>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <div style="display:flex;align-items:center;gap:12px;">
    <a href="/admin/iptv" class="btn-ghost text-sm px-3"><i class="fas fa-arrow-right text-xs"></i></a>
    <h1 style="font-size:20px;font-weight:800;color:#fff;">
      <i class="fas fa-calendar-days" style="color:#60a5fa;margin-left:10px;"></i>راهنمای برنامه‌ها (EPG)
    </h1>
  </div>
  <button onclick="document.getElementById('srcModal').classList.remove('hidden')" class="btn-primary text-sm">
    <i class="fas fa-plus text-xs ml-1"></i>منبع جدید
  </button>
</div>

<!-- آمار -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px;">
  <?php
  $until = !empty($stats['until']) ? date('Y/m/d H:i', strtotime((string)$stats['until'])) : '—';
  foreach ([
    ['برنامه‌های پیش‌رو', number_format((int)($stats['programs'] ?? 0)), 'list',        '#60a5fa'],
    ['کانال با برنامه',   ($stats['matched'] ?? 0) . ' از ' . ($stats['channels'] ?? 0), 'tv', '#22c55e'],
    ['کلید بدون کانال',   (int)($stats['unmatched'] ?? 0), 'link-slash', ((int)($stats['unmatched'] ?? 0) > 0 ? '#ef4444' : '#475569')],
    ['پوشش تا',           $until, 'clock', '#a78bfa'],
  ] as [$label, $value, $icon, $color]): ?>
    <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:14px;">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
        <i class="fas fa-<?= $icon ?>" style="color:<?= $color ?>;font-size:12px;"></i>
        <span style="font-size:11px;color:#64748b;"><?= $label ?></span>
      </div>
      <div style="font-size:16px;font-weight:800;color:#fff;"><?= htmlspecialchars((string)$value) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- منابع -->
<h2 style="font-size:13px;color:#94a3b8;margin-bottom:10px;font-weight:700;">
  <i class="fas fa-database text-xs ml-1"></i>منابع EPG
</h2>

<div style="background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.05);border-radius:12px;overflow:hidden;margin-bottom:24px;">
  <?php if (!$sources): ?>
    <div style="padding:26px;text-align:center;color:#475569;font-size:12px;">
      هنوز منبعی تعریف نشده — برای شروع یک سرور TVHeadend یا آدرس XMLTV اضافه کنید
    </div>
  <?php else: ?>
    <?php foreach ($sources as $s):
      [$typeLabel, $typeColor] = $TYPE_LABELS[$s['source_type']] ?? ['نامشخص', '#64748b'];
      $msg     = (string)($s['last_sync_msg'] ?? '');
      $lastOk  = str_starts_with($msg, '✓');
      $hasSync = $msg !== ''; ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.04);gap:10px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:10px;min-width:220px;">
          <span style="width:5px;height:30px;border-radius:3px;background:<?= $typeColor ?>;"></span>
          <div>
            <div style="font-size:13px;color:#fff;font-weight:700;">
              <?= htmlspecialchars((string)$s['name']) ?>
              <?php if (!(int)$s['is_active']): ?>
                <span style="font-size:10px;color:#64748b;">(غیرفعال)</span>
              <?php endif; ?>
            </div>
            <div style="font-size:10px;color:#64748b;">
              <?= $typeLabel ?>
              <?= $s['tvh_name'] ? ' — ' . htmlspecialchars((string)$s['tvh_name']) : '' ?>
              <?= $s['url'] ? ' — ' . htmlspecialchars(mb_substr((string)$s['url'], 0, 60)) : '' ?>
              · <?= (int)$s['days_ahead'] ?> روز
            </div>
          </div>
        </div>

        <div style="font-size:11px;color:<?= $hasSync ? ($lastOk ? '#22c55e' : '#ef4444') : '#475569' ?>;flex:1;min-width:180px;">
          <?php if ($hasSync): ?>
            <?= htmlspecialchars($msg) ?>
            <div style="font-size:10px;color:#475569;">
              <?= $s['last_sync_at'] ? date('Y/m/d H:i', strtotime((string)$s['last_sync_at'])) : '' ?>
              · <?= (int)$s['upcoming'] ?> برنامه پیش‌رو
            </div>
          <?php else: ?>
            هنوز همگام‌سازی نشده
          <?php endif; ?>
        </div>

        <div style="display:flex;gap:6px;">
          <button onclick="syncSource(<?= (int)$s['id'] ?>, this)" class="btn-primary text-xs" style="padding:6px 12px;font-size:11px;">
            <i class="fas fa-rotate text-xs ml-1"></i>همگام‌سازی
          </button>
          <button onclick="deleteSource(<?= (int)$s['id'] ?>)" class="btn-ghost text-xs px-2" style="color:#ef4444;">
            <i class="fas fa-trash" style="font-size:10px;"></i>
          </button>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- کلیدهای بدون کانال -->
<?php if ($unmatched): ?>
<h2 style="font-size:13px;color:#f59e0b;margin-bottom:6px;font-weight:700;">
  <i class="fas fa-triangle-exclamation text-xs ml-1"></i>کلیدهایی که به کانالی وصل نشدند
</h2>
<p style="font-size:11px;color:#64748b;margin-bottom:10px;">
  این برنامه‌ها دریافت شده‌اند ولی معلوم نیست به کدام کانال تعلق دارند. هر کلید را به کانال درست وصل کنید.
</p>

<div style="background:rgba(245,158,11,.05);border:1px solid rgba(245,158,11,.2);border-radius:12px;overflow:hidden;margin-bottom:24px;">
  <?php foreach ($unmatched as $u): ?>
    <form method="POST" action="/admin/epg/map"
          style="display:flex;align-items:center;justify-content:space-between;padding:9px 14px;border-bottom:1px solid rgba(255,255,255,.04);gap:10px;flex-wrap:wrap;">
      <?= csrf_field() ?>
      <input type="hidden" name="channel_key" value="<?= htmlspecialchars((string)$u['channel_key']) ?>">
      <div style="font-size:12px;color:#fbbf24;font-family:monospace;direction:ltr;min-width:180px;">
        <?= htmlspecialchars((string)$u['channel_key']) ?>
      </div>
      <div style="font-size:10px;color:#64748b;"><?= (int)$u['n'] ?> برنامه</div>
      <div style="display:flex;gap:6px;">
        <select name="channel_id" required
                style="font-size:11px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:7px;color:#cbd5e1;padding:5px 8px;">
          <option value="">— انتخاب کانال —</option>
          <?php foreach ($channels as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars((string)$c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-ghost text-xs px-3" style="font-size:11px;">وصل کن</button>
      </div>
    </form>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- وضعیت کانال‌ها -->
<h2 style="font-size:13px;color:#94a3b8;margin-bottom:10px;font-weight:700;">
  <i class="fas fa-tv text-xs ml-1"></i>پوشش کانال‌ها
</h2>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;">
  <?php foreach ($channels as $c):
    $n  = (int)$c['program_count'];
    $ok = $n > 0; ?>
    <div style="background:rgba(255,255,255,.03);border:1px solid <?= $ok ? 'rgba(34,197,94,.2)' : 'rgba(255,255,255,.06)' ?>;border-radius:10px;padding:10px;display:flex;align-items:center;gap:9px;">
      <?php if (!empty($c['logo_url'])): ?>
        <img src="<?= htmlspecialchars((string)$c['logo_url']) ?>" alt=""
             style="width:28px;height:28px;object-fit:contain;border-radius:6px;background:rgba(0,0,0,.3);">
      <?php else: ?>
        <span style="width:28px;height:28px;border-radius:6px;background:rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-tv" style="color:#475569;font-size:11px;"></i>
        </span>
      <?php endif; ?>
      <div style="flex:1;min-width:0;">
        <div style="font-size:12px;color:#fff;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
          <?= htmlspecialchars((string)$c['name']) ?>
        </div>
        <div style="font-size:10px;color:<?= $ok ? '#22c55e' : '#64748b' ?>;">
          <?= $ok ? "$n برنامه" : 'بدون برنامه' ?>
          <?= !empty($c['epg_id']) ? ' · ' . htmlspecialchars((string)$c['epg_id']) : '' ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- مودال منبع جدید -->
<div id="srcModal" class="hidden" style="position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:90;display:flex;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#0f172a;border:1px solid rgba(255,255,255,.1);border-radius:16px;max-width:480px;width:100%;padding:20px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:15px;font-weight:800;color:#fff;">منبع EPG جدید</h3>
      <button onclick="document.getElementById('srcModal').classList.add('hidden')" class="btn-ghost text-xs px-2">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <form onsubmit="return addSource(event)" style="display:flex;flex-direction:column;gap:10px;">
      <input id="sName" placeholder="نام منبع" required
             style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:9px;color:#fff;font-size:12px;">

      <select id="sType" onchange="toggleType()"
              style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:9px;color:#fff;font-size:12px;">
        <option value="tvheadend">TVHeadend (از سرور موجود)</option>
        <option value="xmltv_url">XMLTV — آدرس اینترنتی</option>
        <option value="xmltv_file">XMLTV — فایل داخل storage</option>
      </select>

      <div id="tvhBlock">
        <?php if ($tvhSources): ?>
          <select id="sTvh"
                  style="width:100%;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:9px;color:#fff;font-size:12px;">
            <?php foreach ($tvhSources as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars((string)$t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <div style="font-size:11px;color:#f59e0b;padding:8px;background:rgba(245,158,11,.08);border-radius:8px;">
            هیچ سرور TVHeadend فعالی تعریف نشده —
            <a href="/admin/iptv/tvheadend" style="color:#fbbf24;text-decoration:underline;">اول یکی اضافه کنید</a>
          </div>
        <?php endif; ?>
      </div>

      <input id="sUrl" placeholder="https://example.com/epg.xml  یا  epg/guide.xml" class="hidden"
             style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:9px;color:#fff;font-size:12px;direction:ltr;">

      <label style="font-size:11px;color:#64748b;display:flex;align-items:center;gap:8px;">
        چند روز جلوتر دریافت شود:
        <input id="sDays" type="number" min="1" max="14" value="7"
               style="width:70px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:7px;padding:5px;color:#fff;font-size:12px;">
      </label>

      <button type="submit" class="btn-primary text-sm" style="margin-top:4px;">افزودن منبع</button>
    </form>
  </div>
</div>

<div id="toast" class="hidden"></div>

<script>
function toggleType() {
  const t = document.getElementById('sType').value;
  document.getElementById('tvhBlock').classList.toggle('hidden', t !== 'tvheadend');
  document.getElementById('sUrl').classList.toggle('hidden', t === 'tvheadend');
}

async function addSource(ev) {
  ev.preventDefault();
  const type = document.getElementById('sType').value;
  const body = {
    name:       document.getElementById('sName').value.trim(),
    source_type: type,
    days_ahead: Number(document.getElementById('sDays').value || 7),
  };
  if (type === 'tvheadend') {
    const el = document.getElementById('sTvh');
    if (!el) { showToast('اول یک سرور TVHeadend اضافه کنید', 'error'); return false; }
    body.tvh_source_id = Number(el.value);
  } else {
    body.url = document.getElementById('sUrl').value.trim();
  }

  try {
    await apiFetch('/api/v1/epg/sources', 'POST', body);
    showToast('منبع اضافه شد');
    setTimeout(() => location.reload(), 500);
  } catch (e) { showToast(e.message, 'error'); }
  return false;
}

async function syncSource(id, btn) {
  const original = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs ml-1"></i>در حال دریافت…';
  try {
    const d = await apiFetch(`/api/v1/epg/sources/${id}/sync`, 'POST');
    showToast(d.message || 'همگام‌سازی انجام شد');
    setTimeout(() => location.reload(), 700);
  } catch (e) {
    showToast(e.message, 'error');
    btn.disabled = false;
    btn.innerHTML = original;
  }
}

async function deleteSource(id) {
  if (!confirm('این منبع و همه برنامه‌های آن حذف شود؟')) return;
  try {
    await apiFetch(`/api/v1/epg/sources/${id}`, 'DELETE');
    showToast('حذف شد');
    setTimeout(() => location.reload(), 400);
  } catch (e) { showToast(e.message, 'error'); }
}

async function apiFetch(url, method = 'GET', body = null) {
  const opts = { method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, credentials: 'same-origin' };
  if (body) opts.body = JSON.stringify(body);
  const r = await fetch(url, opts);
  let d = {};
  try { d = await r.json(); } catch (_) {}
  if (!r.ok) throw new Error(d.message || `خطا (${r.status})`);
  return d;
}

function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.className = `toast toast-${type}`;
  t.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${msg}`;
  t.classList.remove('hidden');
  setTimeout(() => t.classList.add('hidden'), 4000);
}

toggleType();
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
