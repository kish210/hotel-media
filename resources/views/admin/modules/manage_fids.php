<?php
use App\Core\{Auth, Database};
use App\Services\AirportIrFetcher;
use App\Modules\Core\ModuleRegistry;

$db  = Database::getInstance();
$tid = Auth::tenantId();

// بارگذاری تنظیمات ماژول
ModuleRegistry::ensureTable();
ModuleRegistry::boot($tid);
$mod      = ModuleRegistry::get('fids');
$settings = $mod ? $mod->getSettings() : [];

$airportId  = (int)($settings['airport_id']  ?? 2);
$direction  = $settings['direction']          ?? 'all';
$route      = $settings['route']              ?? 'all';
$limit      = (int)($settings['limit']        ?? 50);
$clearOld   = (bool)($settings['clear_old']   ?? true);
$cronToken  = $settings['cron_token']         ?? '';
$lastSync   = $settings['last_sync_at']       ?? null;
$lastCount  = (int)($settings['last_sync_count'] ?? 0);
$lastAirId  = (int)($settings['last_sync_airport'] ?? 0);

// آمار امروز
$today  = date('Y-m-d');
$stats  = $db->row(
    "SELECT COUNT(*) AS total,
     SUM(type='departure') AS dep,
     SUM(type='arrival')   AS arr,
     SUM(status='delayed') AS delayed_c,
     SUM(status='cancelled') AS cancelled_c,
     SUM(source='auto')    AS auto_c
     FROM fids_flights WHERE tenant_id=? AND DATE(scheduled_time)=? AND is_active=1",
    [$tid, $today]
) ?: [];

$airports = AirportIrFetcher::AIRPORTS;

// ساخت URL سایت
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl  = $scheme . '://' . $host;
$cronUrl  = $baseUrl . '/api/v1/fids/cron-sync?token=' . urlencode($cronToken);

include VIEWS_PATH . '/partials/layout.php';
?>

<!-- Header -->
<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-bold text-white flex items-center gap-2">
      <i class="fas fa-plane-departure" style="color:#38bdf8;"></i> تنظیمات FIDS — دریافت خودکار پروازها
    </h1>
    <p class="text-xs text-slate-500 mt-1">دریافت اطلاعات زنده از <span style="color:#38bdf8;">fids.airport.ir</span> و ذخیره در دیتابیس</p>
  </div>
  <div style="display:flex;align-items:center;gap:10px;">
    <!-- Connectivity badge -->
    <div id="connBadge" style="display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:5px 12px;font-size:12px;cursor:pointer;" onclick="checkConn()" title="کلیک برای بررسی مجدد">
      <span id="connDot" style="width:8px;height:8px;border-radius:50%;background:#475569;display:inline-block;"></span>
      <span id="connText" style="color:#64748b;">بررسی اتصال...</span>
    </div>
    <a href="/admin/modules/fids/flights" class="btn-ghost text-sm gap-2">
      <i class="fas fa-list text-sky-400"></i> لیست پروازها
    </a>
  </div>
</div>

<!-- Connectivity warning (hidden by default, shown if unreachable) -->
<div id="connWarning" style="display:none;background:rgba(251,191,36,.07);border:1px solid rgba(251,191,36,.25);border-radius:14px;padding:14px 18px;margin-bottom:20px;">
  <div style="display:flex;align-items:flex-start;gap:12px;">
    <i class="fas fa-triangle-exclamation" style="color:#fbbf24;font-size:18px;margin-top:2px;flex-shrink:0;"></i>
    <div style="flex:1;">
      <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:6px;">سایت fids.airport.ir از این سرور قابل دسترس نیست</div>
      <div id="connErrDetail" style="font-size:12px;color:#94a3b8;margin-bottom:10px;"></div>
      <div style="font-size:12px;color:#64748b;line-height:1.8;">
        <strong style="color:#fbbf24;">راه‌حل:</strong> اگر سرور خارج از ایران است، یک proxy داخل ایران را در فایل <code style="color:#38bdf8;">.env</code> تنظیم کنید:<br>
        <code style="background:rgba(0,0,0,.3);padding:4px 10px;border-radius:6px;display:inline-block;margin-top:6px;color:#a5f3fc;font-size:11px;font-family:monospace;">FIDS_HTTP_PROXY=http://YOUR_IRAN_PROXY_IP:PORT</code>
      </div>
    </div>
    <button onclick="checkConn()" style="background:rgba(251,191,36,.15);border:1px solid rgba(251,191,36,.3);color:#fbbf24;border-radius:8px;padding:5px 10px;font-size:11px;cursor:pointer;flex-shrink:0;font-family:Vazirmatn,sans-serif;">
      <i class="fas fa-rotate ml-1"></i> تست مجدد
    </button>
  </div>
</div>

<!-- آمار امروز -->
<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:24px;">
  <?php
  $cards = [
    ['کل پروازها',    $stats['total']      ?? 0, 'fas fa-plane',         '#38bdf8'],
    ['پرواز',         $stats['dep']        ?? 0, 'fas fa-plane-departure','#4ade80'],
    ['ورود',          $stats['arr']        ?? 0, 'fas fa-plane-arrival',  '#a78bfa'],
    ['تأخیر',         $stats['delayed_c']  ?? 0, 'fas fa-clock',          '#fbbf24'],
    ['لغو',           $stats['cancelled_c']?? 0, 'fas fa-ban',            '#f87171'],
    ['دریافت خودکار', $stats['auto_c']     ?? 0, 'fas fa-robot',          '#f97316'],
  ];
  foreach ($cards as [$label, $val, $icon, $color]):
  ?>
  <div class="stat-card" style="text-align:center;">
    <i class="<?= $icon ?>" style="color:<?= $color ?>;font-size:20px;display:block;margin-bottom:8px;"></i>
    <div style="font-size:22px;font-weight:800;color:#fff;"><?= $val ?></div>
    <div style="font-size:11px;color:#475569;margin-top:2px;"><?= $label ?></div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start;">

  <!-- ═══ ستون چپ: تنظیمات + Fetch ═══════════════════════════════════════ -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- تنظیمات دریافت -->
    <div class="card">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
        <div style="width:38px;height:38px;background:rgba(56,189,248,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-sliders" style="color:#38bdf8;font-size:16px;"></i>
        </div>
        <div>
          <h2 style="font-size:14px;font-weight:700;color:#fff;">تنظیمات دریافت از fids.airport.ir</h2>
          <p style="font-size:11px;color:#475569;">این تنظیمات هم برای دریافت دستی و هم برای Cron Job استفاده می‌شود</p>
        </div>
      </div>

      <form id="fidsSettingsForm" style="display:grid;gap:16px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
          <div>
            <label class="form-label">فرودگاه *</label>
            <select name="airport_id" id="sAirport" class="form-input">
              <?php foreach ($airports as $id => $info): ?>
              <option value="<?= $id ?>" <?= $id === $airportId ? 'selected' : '' ?>>
                <?= e($info['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">نوع پرواز</label>
            <select name="direction" id="sDir" class="form-input">
              <option value="all"       <?= $direction==='all'       ? 'selected' : '' ?>>همه (ورود + پرواز)</option>
              <option value="departure" <?= $direction==='departure' ? 'selected' : '' ?>>فقط پرواز (Departure)</option>
              <option value="arrival"   <?= $direction==='arrival'   ? 'selected' : '' ?>>فقط ورود (Arrival)</option>
            </select>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
          <div>
            <label class="form-label">مسیر پرواز</label>
            <select name="route" id="sRoute" class="form-input">
              <option value="all"           <?= $route==='all'           ? 'selected' : '' ?>>همه</option>
              <option value="domestic"      <?= $route==='domestic'      ? 'selected' : '' ?>>داخلی</option>
              <option value="international" <?= $route==='international' ? 'selected' : '' ?>>خارجی</option>
            </select>
          </div>
          <div>
            <label class="form-label">حداکثر تعداد</label>
            <input name="limit" type="number" class="form-input" value="<?= $limit ?>" min="5" max="200" placeholder="50">
          </div>
          <div style="display:flex;flex-direction:column;justify-content:flex-end;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:10px;">
              <input type="checkbox" name="clear_old" id="sClearOld" <?= $clearOld ? 'checked' : '' ?> style="accent-color:#f97316;width:15px;height:15px;">
              <span style="font-size:12px;color:#94a3b8;">حذف پروازهای قبلی</span>
            </label>
          </div>
        </div>

        <div style="display:flex;gap:10px;padding-top:4px;border-top:1px solid rgba(255,255,255,.05);">
          <button type="button" onclick="saveSettings()" class="btn-primary text-sm gap-2" id="saveBtn">
            <i class="fas fa-save"></i> ذخیره تنظیمات
          </button>
          <span style="font-size:11px;color:#475569;align-self:center;" id="saveStatus"></span>
        </div>
      </form>
    </div>

    <!-- دریافت فوری -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:10px;">
          <div style="width:38px;height:38px;background:rgba(74,222,128,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-cloud-arrow-down" style="color:#4ade80;font-size:16px;"></i>
          </div>
          <div>
            <h2 style="font-size:14px;font-weight:700;color:#fff;">دریافت فوری اطلاعات پرواز</h2>
            <?php if ($lastSync): ?>
            <p style="font-size:11px;color:#475569;">
              آخرین sync:
              <span style="color:#4ade80;"><?= $lastSync ?></span>
              — <?= $lastCount ?> پرواز از <?= $airports[$lastAirId]['name'] ?? "فرودگاه #{$lastAirId}" ?>
            </p>
            <?php else: ?>
            <p style="font-size:11px;color:#475569;">هنوز دریافت نشده</p>
            <?php endif; ?>
          </div>
        </div>
        <button onclick="fetchNow()" id="fetchBtn"
          style="display:flex;align-items:center;gap:8px;padding:10px 20px;background:linear-gradient(135deg,#4ade80,#16a34a);color:#fff;border:none;border-radius:12px;font-size:13px;font-weight:700;cursor:pointer;font-family:Vazirmatn,sans-serif;">
          <i class="fas fa-rotate" id="fetchIcon"></i> دریافت الآن
        </button>
      </div>

      <!-- Result box -->
      <div id="fetchResult" style="display:none;background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:14px;">
        <div id="fetchResultContent" style="font-size:13px;color:#94a3b8;"></div>
      </div>

      <!-- Preview table -->
      <div id="previewWrap" style="display:none;margin-top:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
          <span style="font-size:12px;font-weight:600;color:#64748b;">پیش‌نمایش پروازهای دریافتی</span>
          <span id="previewCount" style="font-size:11px;background:rgba(56,189,248,.1);color:#38bdf8;padding:2px 10px;border-radius:20px;"></span>
        </div>
        <div style="max-height:300px;overflow-y:auto;border-radius:10px;border:1px solid rgba(255,255,255,.06);">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr style="background:rgba(255,255,255,.03);color:#475569;">
                <th style="padding:8px 12px;text-align:right;font-weight:600;">پرواز</th>
                <th style="padding:8px 12px;text-align:right;font-weight:600;">ایرلاین</th>
                <th style="padding:8px 12px;text-align:right;font-weight:600;">نوع</th>
                <th style="padding:8px 12px;text-align:right;font-weight:600;">مقصد/مبدأ</th>
                <th style="padding:8px 12px;text-align:right;font-weight:600;">زمان</th>
                <th style="padding:8px 12px;text-align:right;font-weight:600;">وضعیت</th>
              </tr>
            </thead>
            <tbody id="previewTbody"></tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

  <!-- ═══ ستون راست: Cron Job ═══════════════════════════════════════════ -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Cron Token -->
    <div class="card">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
        <div style="width:38px;height:38px;background:rgba(249,115,22,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-key" style="color:#f97316;font-size:16px;"></i>
        </div>
        <div>
          <h2 style="font-size:14px;font-weight:700;color:#fff;">توکن Cron</h2>
          <p style="font-size:11px;color:#475569;">برای احراز هویت cron job استفاده می‌شود</p>
        </div>
      </div>

      <div style="background:#0d0d14;border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:10px 12px;display:flex;align-items:center;gap:8px;margin-bottom:12px;">
        <code style="flex:1;font-size:11px;color:#38bdf8;font-family:'JetBrains Mono',monospace;word-break:break-all;">
          <?= $cronToken ? e($cronToken) : '<span style="color:#475569;">— هنوز تولید نشده —</span>' ?>
        </code>
        <?php if ($cronToken): ?>
        <button onclick="copyText('<?= e($cronToken) ?>')" title="کپی"
          style="background:none;border:none;color:#475569;cursor:pointer;font-size:13px;padding:4px;">
          <i class="fas fa-copy"></i>
        </button>
        <?php endif; ?>
      </div>

      <button onclick="generateToken()" id="tokenBtn"
        style="width:100%;padding:9px;background:rgba(249,115,22,.08);border:1px dashed rgba(249,115,22,.3);border-radius:10px;color:#f97316;font-size:12px;font-weight:600;cursor:pointer;font-family:Vazirmatn,sans-serif;display:flex;align-items:center;justify-content:center;gap:6px;">
        <i class="fas fa-rotate-right text-xs"></i>
        <?= $cronToken ? 'تولید توکن جدید' : 'تولید توکن' ?>
      </button>
    </div>

    <!-- Cron URL -->
    <?php if ($cronToken): ?>
    <div class="card">
      <h3 style="font-size:13px;font-weight:700;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap-8px;">
        <i class="fas fa-link text-sky-400 ml-2"></i> آدرس Cron Job
      </h3>

      <div style="background:#0d0d14;border:1px solid rgba(56,189,248,.2);border-radius:10px;padding:10px 12px;margin-bottom:10px;position:relative;">
        <code id="cronUrl" style="font-size:10.5px;color:#7dd3fc;font-family:'JetBrains Mono',monospace;word-break:break-all;display:block;line-height:1.6;">
          <?= e($cronUrl) ?>
        </code>
        <button onclick="copyText(document.getElementById('cronUrl').textContent.trim())"
          style="position:absolute;top:8px;left:8px;background:rgba(56,189,248,.1);border:1px solid rgba(56,189,248,.2);border-radius:6px;color:#38bdf8;cursor:pointer;font-size:11px;padding:3px 8px;">
          <i class="fas fa-copy"></i>
        </button>
      </div>

      <!-- Crontab commands -->
      <h3 style="font-size:12px;font-weight:700;color:#64748b;margin:14px 0 10px;text-transform:uppercase;letter-spacing:.5px;">
        دستور Crontab (Linux)
      </h3>

      <?php
      $cmds = [
        ['هر ۵ دقیقه',  '*/5 * * * *'],
        ['هر ۱۰ دقیقه', '*/10 * * * *'],
        ['هر ۱۵ دقیقه', '*/15 * * * *'],
        ['هر ۳۰ دقیقه', '*/30 * * * *'],
      ];
      foreach ($cmds as [$label, $schedule]): ?>
      <?php $cmd = "{$schedule} curl -s \"{$cronUrl}\" > /dev/null 2>&1"; ?>
      <div style="margin-bottom:8px;">
        <div style="font-size:10px;color:#475569;margin-bottom:4px;"><?= $label ?>:</div>
        <div style="background:#0d0d14;border:1px solid rgba(255,255,255,.06);border-radius:8px;padding:7px 10px;display:flex;align-items:center;gap:6px;">
          <code style="flex:1;font-size:10px;color:#a5f3fc;font-family:'JetBrains Mono',monospace;word-break:break-all;"><?= e($cmd) ?></code>
          <button onclick="copyText('<?= addslashes(e($cmd)) ?>')"
            style="background:none;border:none;color:#475569;cursor:pointer;font-size:11px;flex-shrink:0;padding:2px;">
            <i class="fas fa-copy"></i>
          </button>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Windows Task Scheduler -->
      <h3 style="font-size:12px;font-weight:700;color:#64748b;margin:14px 0 10px;text-transform:uppercase;letter-spacing:.5px;">
        PowerShell (Windows)
      </h3>
      <?php $psCmd = "Invoke-WebRequest -Uri '{$cronUrl}' -UseBasicParsing | Out-Null"; ?>
      <div style="background:#0d0d14;border:1px solid rgba(255,255,255,.06);border-radius:8px;padding:7px 10px;display:flex;align-items:center;gap:6px;">
        <code style="flex:1;font-size:10px;color:#c4b5fd;font-family:'JetBrains Mono',monospace;word-break:break-all;"><?= e($psCmd) ?></code>
        <button onclick="copyText('<?= addslashes(e($psCmd)) ?>')"
          style="background:none;border:none;color:#475569;cursor:pointer;font-size:11px;flex-shrink:0;">
          <i class="fas fa-copy"></i>
        </button>
      </div>
    </div>
    <?php endif; ?>

    <!-- راهنما -->
    <div class="card" style="background:rgba(56,189,248,.04);border-color:rgba(56,189,248,.12);">
      <h3 style="font-size:12px;font-weight:700;color:#38bdf8;margin-bottom:10px;">
        <i class="fas fa-circle-info ml-2"></i> راهنما
      </h3>
      <ul style="font-size:12px;color:#64748b;line-height:2;list-style:none;padding:0;margin:0;">
        <li>📡 داده از <strong style="color:#38bdf8;">fids.airport.ir</strong> دریافت می‌شود</li>
        <li>🔄 پروازهای auto با هر sync بروز می‌شوند</li>
        <li>✋ پروازهای دستی حفظ می‌شوند</li>
        <li>⏱ Cron هر N دقیقه اجرا کنید</li>
        <li>🔒 توکن را محرمانه نگه دارید</li>
      </ul>
    </div>

  </div>
</div>

<?php
$settingsJson = json_encode([
    'airport_id' => $airportId,
    'direction'  => $direction,
    'route'      => $route,
    'limit'      => $limit,
    'clear_old'  => $clearOld,
    'cron_token' => $cronToken,
], JSON_UNESCAPED_UNICODE);

$airports_json = json_encode(array_map(fn($v) => $v['name'], $airports), JSON_UNESCAPED_UNICODE);

$extraScript = <<<JS
const TOKEN = localStorage.getItem('signage_token') || localStorage.getItem('auth_token') || '';
const AUTH  = { 'Authorization': 'Bearer ' + TOKEN, 'Content-Type': 'application/json' };

// ── Connectivity check ────────────────────────────────────────────────────
async function checkConn() {
  const dot  = document.getElementById('connDot');
  const txt  = document.getElementById('connText');
  const warn = document.getElementById('connWarning');
  const detail = document.getElementById('connErrDetail');
  if (dot) { dot.style.background = '#475569'; dot.style.animation = 'spin 1s linear infinite'; }
  if (txt) txt.textContent = 'در حال بررسی...';
  try {
    const r = await fetch('/api/v1/fids/ping?airport_id=' + (document.getElementById('sAirport')?.value || 2));
    const d = await r.json();
    if (d.reachable) {
      if (dot) { dot.style.background = '#4ade80'; dot.style.animation = ''; }
      if (txt) { txt.textContent = 'fids.airport.ir متصل (' + d.latency_ms + 'ms)'; txt.style.color = '#4ade80'; }
      if (warn) warn.style.display = 'none';
    } else {
      if (dot) { dot.style.background = '#f87171'; dot.style.animation = ''; }
      if (txt) { txt.textContent = 'fids.airport.ir قطع'; txt.style.color = '#f87171'; }
      if (warn) warn.style.display = 'flex';
      if (detail) {
        detail.textContent = (d.error || 'connection timeout') +
          (d.proxy && d.proxy !== '(none)' ? ' | Proxy: ' + d.proxy : ' | بدون proxy');
      }
    }
  } catch(e) {
    if (txt) { txt.textContent = 'خطا در بررسی'; txt.style.color = '#f87171'; }
  }
}
// Auto-check on page load
setTimeout(checkConn, 800);

// ── Save settings ────────────────────────────────────────────────────────
async function saveSettings() {
  const f = document.getElementById('fidsSettingsForm');
  const data = {
    airport_id: parseInt(document.getElementById('sAirport').value),
    direction:  document.getElementById('sDir').value,
    route:      document.getElementById('sRoute').value,
    limit:      parseInt(f.querySelector('[name=limit]').value) || 50,
    clear_old:  f.querySelector('[name=clear_old]').checked,
  };
  const btn = document.getElementById('saveBtn');
  const st  = document.getElementById('saveStatus');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> در حال ذخیره...';

  try {
    const r = await fetch('/api/v1/modules/fids/settings', {
      method: 'PUT', headers: AUTH, body: JSON.stringify(data)
    });
    const d = await r.json();
    if (d.success) {
      st.textContent = '✓ ذخیره شد';
      st.style.color = '#4ade80';
      showToast('success', 'تنظیمات ذخیره شد');
    } else {
      st.textContent = '✗ ' + (d.message || 'خطا');
      st.style.color = '#f87171';
    }
  } catch(e) {
    st.textContent = '✗ خطا در ارتباط';
    st.style.color = '#f87171';
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-save"></i> ذخیره تنظیمات';
  setTimeout(() => { st.textContent = ''; }, 4000);
}

// ── Fetch Now ────────────────────────────────────────────────────────────
async function fetchNow() {
  const btn  = document.getElementById('fetchBtn');
  const icon = document.getElementById('fetchIcon');
  const res  = document.getElementById('fetchResult');
  const cont = document.getElementById('fetchResultContent');
  const wrap = document.getElementById('previewWrap');

  btn.disabled = true;
  icon.className = 'fas fa-circle-notch fa-spin';
  btn.style.opacity = '0.7';
  res.style.display = 'block';
  cont.innerHTML = '<i class="fas fa-circle-notch fa-spin ml-2 text-sky-400"></i> در حال دریافت از fids.airport.ir ...';
  wrap.style.display = 'none';

  const params = {
    airport_id: parseInt(document.getElementById('sAirport').value),
    direction:  document.getElementById('sDir').value,
    route:      document.getElementById('sRoute').value,
    limit:      parseInt(document.getElementById('fidsSettingsForm').querySelector('[name=limit]').value) || 50,
    clear_old:  document.getElementById('sClearOld').checked,
  };

  try {
    const r = await fetch('/api/v1/fids/sync-live', {
      method: 'POST', headers: AUTH, body: JSON.stringify(params)
    });
    const d = await r.json();

    if (d.success) {
      const info = d.data || {};
      cont.innerHTML = \`
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <span style="color:#4ade80;font-weight:700;font-size:15px;">
            <i class="fas fa-check-circle ml-1"></i> \${info.saved ?? 0} پرواز ذخیره شد
          </span>
          <span style="color:#475569;font-size:12px;">از «\${info.airport ?? ''}»</span>
          <span style="color:#475569;font-size:12px;">ساعت \${(info.synced_at||'').substring(11,16)}</span>
          <span style="background:rgba(56,189,248,.1);color:#38bdf8;font-size:11px;padding:2px 10px;border-radius:20px;">
            \${info.fetched ?? 0} دریافتی
          </span>
        </div>\`;
      showToast('success', d.message || info.saved + ' پرواز ذخیره شد');
      loadPreview(params.airport_id, params.direction, params.route, params.limit);
    } else {
      const isConn = (d.message||'').includes('قابل دسترس') || (d.message||'').includes('timeout') || (d.message||'').includes('تایم');
      cont.innerHTML = \`
        <div>
          <div style="color:#f87171;font-weight:600;margin-bottom:6px;">
            <i class="fas fa-\${isConn ? 'wifi' : 'exclamation-circle'} ml-1"></i>
            \${d.message || 'خطا در دریافت'}
          </div>
          \${d.hint ? \`<div style="font-size:11px;color:#94a3b8;background:rgba(255,255,255,.04);padding:8px 10px;border-radius:8px;line-height:1.8;">\${d.hint}</div>\` : ''}
          \${isConn ? \`<div style="margin-top:8px;"><button onclick="checkConn()" style="font-size:11px;background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);color:#fbbf24;border-radius:6px;padding:4px 10px;cursor:pointer;font-family:Vazirmatn,sans-serif;"><i class="fas fa-satellite-dish ml-1"></i> بررسی وضعیت اتصال</button></div>\` : ''}
        </div>\`;
      showToast('error', d.message || 'خطا');
      if (isConn) checkConn();
    }
  } catch(e) {
    cont.innerHTML = '<span style="color:#f87171;"><i class="fas fa-wifi ml-1"></i> خطا در اتصال به سرور</span>';
    showToast('error', 'خطا در اتصال');
  }

  btn.disabled = false;
  icon.className = 'fas fa-rotate';
  btn.style.opacity = '1';
}

// ── Load preview table ────────────────────────────────────────────────────
async function loadPreview(airportId, direction, route, limit) {
  try {
    const url = \`/api/v1/fids/live?airport_id=\${airportId}&type=\${direction}&route=\${route}&limit=\${limit}\`;
    const r = await fetch(url, { headers: { 'Authorization': 'Bearer ' + TOKEN } });
    const d = await r.json();
    if (!d.success || !d.data?.length) return;

    const tbody = document.getElementById('previewTbody');
    const statusMap = {
      scheduled:'زمان‌بندی', boarding:'سوارشوید', departed:'پرواز کرد',
      arrived:'فرود آمد', delayed:'تأخیر', cancelled:'لغو', diverted:'انحراف'
    };
    const statusColor = {
      scheduled:'#94a3b8', boarding:'#4ade80', departed:'#60a5fa',
      arrived:'#4ade80', delayed:'#fbbf24', cancelled:'#f87171', diverted:'#f97316'
    };

    tbody.innerHTML = d.data.map(f => \`
      <tr style="border-bottom:1px solid rgba(255,255,255,.03);">
        <td style="padding:7px 12px;font-family:monospace;font-weight:700;color:#fff;">\${f.flight_number}</td>
        <td style="padding:7px 12px;color:#94a3b8;font-size:11px;">\${f.airline_name||'—'}</td>
        <td style="padding:7px 12px;">
          \${f.type==='departure'
            ? '<span style="color:#4ade80;font-size:10px;">🛫 پرواز</span>'
            : '<span style="color:#a78bfa;font-size:10px;">🛬 ورود</span>'}
        </td>
        <td style="padding:7px 12px;color:#94a3b8;font-size:11px;">\${f.destination||f.origin||'—'}</td>
        <td style="padding:7px 12px;font-family:monospace;color:#f97316;font-size:12px;">\${(f.scheduled_time||'').substring(11,16)}</td>
        <td style="padding:7px 12px;font-size:11px;font-weight:600;color:\${statusColor[f.status]||'#94a3b8'};">\${statusMap[f.status]||f.status}</td>
      </tr>\`).join('');

    document.getElementById('previewCount').textContent = d.count + ' پرواز';
    document.getElementById('previewWrap').style.display = 'block';
  } catch(e) {}
}

// ── Generate token ────────────────────────────────────────────────────────
async function generateToken() {
  const btn = document.getElementById('tokenBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-circle-notch fa-spin text-xs"></i> در حال تولید...';

  const newToken = Array.from(crypto.getRandomValues(new Uint8Array(24)))
    .map(b => b.toString(16).padStart(2,'0')).join('');

  try {
    const r = await fetch('/api/v1/modules/fids/settings', {
      method: 'PUT', headers: AUTH,
      body: JSON.stringify({ cron_token: newToken })
    });
    const d = await r.json();
    if (d.success) {
      showToast('success', 'توکن جدید تولید شد — صفحه reload می‌شود');
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast('error', d.message || 'خطا در ذخیره توکن');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-rotate-right text-xs"></i> تولید توکن جدید';
    }
  } catch(e) {
    showToast('error', 'خطا در اتصال');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-rotate-right text-xs"></i> تولید توکن جدید';
  }
}

// ── Copy helper ───────────────────────────────────────────────────────────
function copyText(txt) {
  navigator.clipboard?.writeText(txt).then(() => showToast('success','کپی شد ✓')).catch(()=>{
    const ta = document.createElement('textarea');
    ta.value = txt; document.body.appendChild(ta); ta.select();
    document.execCommand('copy'); document.body.removeChild(ta);
    showToast('success','کپی شد ✓');
  });
}
JS;
?>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
