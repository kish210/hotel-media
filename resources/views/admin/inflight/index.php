<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<style>
/* ── Inflight Admin Styles ─────────────────────────────────────── */
.flight-card {
  background:#16161f; border:1px solid rgba(255,255,255,0.07); border-radius:16px;
  padding:20px; cursor:pointer; transition:all 0.2s; position:relative; overflow:hidden;
}
.flight-card:hover { border-color:rgba(0,180,216,0.4); transform:translateY(-1px); }
.flight-card.selected { border-color:#00b4d8; box-shadow:0 0 0 2px rgba(0,180,216,0.2); }
.flight-card .accent-bar {
  position:absolute; top:0; left:0; right:0; height:3px;
  background:var(--acc, #00b4d8);
}
.phase-badge {
  display:inline-flex; align-items:center; gap:5px;
  padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700;
  text-transform:uppercase; letter-spacing:0.5px;
}
.phase-preflight  { background:rgba(148,163,184,.12); color:#94a3b8; }
.phase-taxi       { background:rgba(251,191,36,.12);  color:#fbbf24; }
.phase-takeoff    { background:rgba(249,115,22,.14);  color:#fb923c; }
.phase-climb      { background:rgba(34,211,238,.12);  color:#22d3ee; }
.phase-cruise     { background:rgba(0,180,216,.14);   color:#00b4d8; }
.phase-descent    { background:rgba(168,85,247,.12);  color:#a78bfa; }
.phase-approach   { background:rgba(244,114,182,.12); color:#f472b6; }
.phase-landing    { background:rgba(249,115,22,.14);  color:#fb923c; }
.phase-landed     { background:rgba(34,197,94,.12);   color:#4ade80; }
.progress-track {
  height:6px; background:rgba(255,255,255,0.07); border-radius:3px; overflow:hidden;
}
.progress-fill { height:100%; border-radius:3px; transition:width 0.5s; }
.telemetry-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-top:10px; }
.tele-item { background:rgba(255,255,255,0.04); border-radius:10px; padding:10px; text-align:center; }
.tele-val { font-size:20px; font-weight:800; color:#fff; font-variant-numeric:tabular-nums; }
.tele-lbl { font-size:10px; color:#64748b; margin-top:2px; letter-spacing:0.3px; }
.airport-badge {
  display:inline-flex; align-items:center; gap:6px;
  background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08);
  border-radius:10px; padding:6px 12px; font-size:13px; white-space:nowrap;
}
.airport-badge .iata { font-size:18px; font-weight:800; color:#fff; }
.route-arrow { color:#475569; font-size:20px; margin:0 4px; }
.split-layout { display:grid; grid-template-columns:380px 1fr; gap:20px; }
@media(max-width:900px) { .split-layout { grid-template-columns:1fr; } }
.panel-head {
  font-size:11px; font-weight:700; color:#475569; letter-spacing:0.8px;
  text-transform:uppercase; margin-bottom:12px; padding-bottom:8px;
  border-bottom:1px solid rgba(255,255,255,0.06);
}
.btn-live {
  background:linear-gradient(135deg,#00b4d8,#0077b6);
  color:#fff; padding:8px 16px; border-radius:10px; font-size:13px;
  font-weight:700; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px;
}
.btn-live:hover { opacity:0.9; }
.range-row { display:flex; align-items:center; gap:10px; }
.range-row input[type=range] {
  flex:1; accent-color:#00b4d8; height:4px; border-radius:2px;
}
.range-val { min-width:60px; text-align:right; font-weight:700; color:#fff; font-size:14px; }
.bg-preview {
  width:100%; height:100px; border-radius:12px; margin-bottom:6px;
  background:#000; display:flex; align-items:center; justify-content:center;
  font-size:12px; color:#64748b; border:1px solid rgba(255,255,255,0.07);
  overflow:hidden;
}
.bg-space  { background:radial-gradient(ellipse at center,#0d1b2a 0%,#000 70%); }
.bg-clouds { background:linear-gradient(180deg,#1a3a5c 0%,#4a90d9 50%,#87c0ee 100%); }
.bg-ocean  { background:linear-gradient(180deg,#03045e 0%,#0077b6 50%,#00b4d8 100%); }
.bg-dusk   { background:linear-gradient(180deg,#2d1b6e 0%,#8b1a6b 40%,#e76f51 80%,#ffd166 100%); }
</style>

<div style="padding:24px;">
<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
  <div>
    <h1 style="font-size:22px;font-weight:800;color:#fff;margin:0;">
      <i class="fas fa-plane" style="color:#00b4d8;margin-left:8px;"></i>
      نمایش اطلاعات پرواز
    </h1>
    <p style="color:#64748b;font-size:13px;margin:4px 0 0;">
      مدیریت پروازها و کنترل زنده نمایشگرهای داخل هواپیما
    </p>
  </div>
  <button onclick="openCreate()" class="btn-primary">
    <i class="fas fa-plus ml-1"></i> پرواز جدید
  </button>
</div>

<!-- Stats row -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;">
  <div class="stat-card" style="border-left:3px solid #00b4d8;">
    <div style="font-size:26px;font-weight:800;color:#fff;" id="stat-total"><?= count($flights) ?></div>
    <div style="font-size:12px;color:#64748b;margin-top:2px;">کل پروازها</div>
  </div>
  <div class="stat-card" style="border-left:3px solid #4ade80;">
    <div style="font-size:26px;font-weight:800;color:#4ade80;" id="stat-active">
      <?= count(array_filter($flights, fn($f) => $f['is_active'] && in_array($f['phase'],['takeoff','climb','cruise','descent','approach','landing']))) ?>
    </div>
    <div style="font-size:12px;color:#64748b;margin-top:2px;">در پرواز</div>
  </div>
  <div class="stat-card" style="border-left:3px solid #fbbf24;">
    <div style="font-size:26px;font-weight:800;color:#fbbf24;" id="stat-screens"><?= count($screens) ?></div>
    <div style="font-size:12px;color:#64748b;margin-top:2px;">نمایشگر هواپیما</div>
  </div>
  <div class="stat-card" style="border-left:3px solid #a78bfa;">
    <div style="font-size:26px;font-weight:800;color:#a78bfa;" id="stat-landed">
      <?= count(array_filter($flights, fn($f) => $f['phase'] === 'landed')) ?>
    </div>
    <div style="font-size:12px;color:#64748b;margin-top:2px;">فرود کرده</div>
  </div>
</div>

<!-- Main layout -->
<div class="split-layout">
<!-- Left: flight list -->
<div>
  <div class="panel-head">لیست پروازها</div>
  <div id="flight-list" style="display:flex;flex-direction:column;gap:10px;">
    <?php if (empty($flights)): ?>
    <div style="text-align:center;padding:60px 20px;color:#475569;">
      <i class="fas fa-plane-slash" style="font-size:36px;margin-bottom:12px;display:block;"></i>
      هیچ پروازی ثبت نشده است
    </div>
    <?php else: ?>
    <?php foreach ($flights as $f): ?>
    <div class="flight-card" id="fc-<?= $f['id'] ?>"
         style="--acc:<?= htmlspecialchars($f['accent_color']) ?>;"
         onclick="selectFlight(<?= $f['id'] ?>)">
      <div class="accent-bar"></div>
      <div style="display:flex;justify-content:space-between;align-items:flex-start;">
        <div>
          <div style="font-size:16px;font-weight:800;color:#fff;letter-spacing:1px;">
            <?= htmlspecialchars($f['flight_number']) ?>
          </div>
          <?php if ($f['airline_name']): ?>
          <div style="font-size:11px;color:#64748b;margin-top:1px;"><?= htmlspecialchars($f['airline_name']) ?></div>
          <?php endif; ?>
        </div>
        <span class="phase-badge phase-<?= $f['phase'] ?>" id="fc-phase-<?= $f['id'] ?>">
          <i class="fas fa-<?= phaseIcon($f['phase']) ?>"></i>
          <?= phaseLabel($f['phase']) ?>
        </span>
      </div>
      <div style="display:flex;align-items:center;gap:8px;margin:10px 0 8px;">
        <div class="airport-badge">
          <span class="iata"><?= htmlspecialchars($f['origin_iata'] ?: '---') ?></span>
          <span style="font-size:11px;color:#94a3b8;"><?= htmlspecialchars($f['origin_city'] ?: '') ?></span>
        </div>
        <i class="fas fa-arrow-left route-arrow"></i>
        <div class="airport-badge">
          <span class="iata"><?= htmlspecialchars($f['dest_iata'] ?: '---') ?></span>
          <span style="font-size:11px;color:#94a3b8;"><?= htmlspecialchars($f['dest_city'] ?: '') ?></span>
        </div>
      </div>
      <div class="progress-track">
        <div class="progress-fill" id="fc-prog-<?= $f['id'] ?>"
             style="width:<?= $f['progress_pct'] ?>%;background:<?= htmlspecialchars($f['accent_color']) ?>;"></div>
      </div>
      <div style="display:flex;justify-content:space-between;margin-top:4px;font-size:11px;color:#475569;">
        <span><?= $f['progress_pct'] ?>% طی شده</span>
        <span><?= $f['screen_count'] ?> نمایشگر</span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Right: detail / live control panel -->
<div id="detail-panel">
  <div style="text-align:center;padding:80px 20px;color:#475569;">
    <i class="fas fa-plane-up" style="font-size:48px;margin-bottom:16px;display:block;opacity:0.3;"></i>
    <p style="font-size:14px;">یک پرواز را از لیست انتخاب کنید</p>
  </div>
</div>
</div><!-- end split -->
</div>

<!-- ── Create / Edit Modal ──────────────────────────────────────────────── -->
<div class="modal-overlay hidden" id="flight-modal">
  <div class="modal" style="max-width:700px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="font-size:16px;font-weight:700;color:#fff;margin:0;" id="modal-title">پرواز جدید</h3>
      <button onclick="closeModal()" class="btn-ghost" style="padding:6px 10px;"><i class="fas fa-times"></i></button>
    </div>
    <form id="flight-form" onsubmit="saveFlight(event)">
      <input type="hidden" id="f-id" value="">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div>
          <label class="form-label">شماره پرواز *</label>
          <input type="text" id="f-number" class="form-input" placeholder="IRA711" maxlength="20" required>
        </div>
        <div>
          <label class="form-label">نام ایرلاین</label>
          <input type="text" id="f-airline" class="form-input" placeholder="Iran Air" maxlength="100">
        </div>
      </div>

      <!-- Origin -->
      <div style="margin-top:14px;padding:14px;background:rgba(0,180,216,0.05);border:1px solid rgba(0,180,216,0.15);border-radius:12px;">
        <div style="font-size:11px;font-weight:700;color:#00b4d8;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.5px;">
          <i class="fas fa-plane-departure ml-1"></i> مبدأ
        </div>
        <div style="display:grid;grid-template-columns:80px 1fr 1fr;gap:10px;">
          <div>
            <label class="form-label">کد IATA</label>
            <input type="text" id="f-orig-iata" class="form-input" placeholder="IKA" maxlength="10" style="text-transform:uppercase;">
          </div>
          <div>
            <label class="form-label">شهر</label>
            <input type="text" id="f-orig-city" class="form-input" placeholder="Tehran">
          </div>
          <div>
            <label class="form-label">کشور</label>
            <input type="text" id="f-orig-country" class="form-input" placeholder="Iran">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:10px;">
          <div>
            <label class="form-label">عرض جغرافیایی</label>
            <input type="number" id="f-orig-lat" class="form-input" placeholder="35.4161" step="0.0001">
          </div>
          <div>
            <label class="form-label">طول جغرافیایی</label>
            <input type="number" id="f-orig-lng" class="form-input" placeholder="51.1522" step="0.0001">
          </div>
          <div>
            <label class="form-label">منطقه زمانی</label>
            <input type="text" id="f-orig-tz" class="form-input" placeholder="Asia/Tehran">
          </div>
        </div>
      </div>

      <!-- Destination -->
      <div style="margin-top:14px;padding:14px;background:rgba(168,85,247,0.05);border:1px solid rgba(168,85,247,0.15);border-radius:12px;">
        <div style="font-size:11px;font-weight:700;color:#a78bfa;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.5px;">
          <i class="fas fa-plane-arrival ml-1"></i> مقصد
        </div>
        <div style="display:grid;grid-template-columns:80px 1fr 1fr;gap:10px;">
          <div>
            <label class="form-label">کد IATA</label>
            <input type="text" id="f-dest-iata" class="form-input" placeholder="DXB" maxlength="10" style="text-transform:uppercase;">
          </div>
          <div>
            <label class="form-label">شهر</label>
            <input type="text" id="f-dest-city" class="form-input" placeholder="Dubai">
          </div>
          <div>
            <label class="form-label">کشور</label>
            <input type="text" id="f-dest-country" class="form-input" placeholder="UAE">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:10px;">
          <div>
            <label class="form-label">عرض جغرافیایی</label>
            <input type="number" id="f-dest-lat" class="form-input" placeholder="25.2528" step="0.0001">
          </div>
          <div>
            <label class="form-label">طول جغرافیایی</label>
            <input type="number" id="f-dest-lng" class="form-input" placeholder="55.3644" step="0.0001">
          </div>
          <div>
            <label class="form-label">منطقه زمانی</label>
            <input type="text" id="f-dest-tz" class="form-input" placeholder="Asia/Dubai">
          </div>
        </div>
      </div>

      <!-- Times -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;">
        <div>
          <label class="form-label">زمان پرواز (UTC)</label>
          <input type="datetime-local" id="f-dep" class="form-input">
        </div>
        <div>
          <label class="form-label">زمان فرود (UTC)</label>
          <input type="datetime-local" id="f-arr" class="form-input">
        </div>
      </div>

      <!-- Appearance -->
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:14px;">
        <div>
          <label class="form-label">رنگ اکسنت</label>
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="color" id="f-accent" value="#00b4d8"
                   style="width:40px;height:36px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);background:transparent;cursor:pointer;padding:2px;">
            <input type="text" id="f-accent-txt" class="form-input" value="#00b4d8" maxlength="7" style="flex:1;"
                   oninput="syncColor(this.value)">
          </div>
        </div>
        <div>
          <label class="form-label">پس‌زمینه</label>
          <select id="f-bg" class="form-input">
            <option value="space">🌌 فضا (Space)</option>
            <option value="clouds">☁️ ابر (Clouds)</option>
            <option value="ocean">🌊 اقیانوس (Ocean)</option>
            <option value="dusk">🌅 غروب (Dusk)</option>
          </select>
        </div>
        <div>
          <label class="form-label">وضعیت</label>
          <select id="f-active" class="form-input">
            <option value="1">فعال</option>
            <option value="0">غیرفعال</option>
          </select>
        </div>
      </div>

      <div style="margin-top:14px;">
        <label class="form-label">پیام خوش‌آمد (اختیاری)</label>
        <input type="text" id="f-welcome" class="form-input" placeholder="خوش‌آمدید — لطفاً کمربند ایمنی ببندید" maxlength="255">
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
        <button type="button" onclick="closeModal()" class="btn-ghost">انصراف</button>
        <button type="submit" class="btn-primary">
          <i class="fas fa-save ml-1"></i> ذخیره پرواز
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const API = '/api/v1/inflight';
let selectedId = null;
let flightsData = <?= json_encode($flights, JSON_UNESCAPED_UNICODE) ?>;
const screensData = <?= json_encode($screens, JSON_UNESCAPED_UNICODE) ?>;

// ── JWT token from meta ─────────────────────────────────────────
function getToken() {
  return localStorage.getItem('auth_token') || '';
}
async function apiFetch(url, opts = {}) {
  const token = getToken();
  const res = await fetch(url, {
    headers: { 'Content-Type':'application/json', 'Authorization':'Bearer '+token, ...(opts.headers||{}) },
    ...opts,
  });
  return res.json();
}

// ── Select flight ────────────────────────────────────────────────
function selectFlight(id) {
  selectedId = id;
  document.querySelectorAll('.flight-card').forEach(c => c.classList.remove('selected'));
  const card = document.getElementById('fc-'+id);
  if (card) card.classList.add('selected');
  const f = flightsData.find(x => x.id == id);
  if (f) renderDetail(f);
}

function renderDetail(f) {
  const distKm = haversine(f.origin_lat, f.origin_lng, f.dest_lat, f.dest_lng);
  const remaining = distKm ? Math.round(distKm * (1 - f.progress_pct/100)) : null;
  const etaStr = f.speed_kmh > 0 && remaining ? fmtEta(Math.round(remaining / f.speed_kmh * 60)) : '---';
  const screenOpts = screensData
    .map(s => `<option value="${s.id}" ${s.inflight_flight_id==f.id?'selected':''}>${esc(s.name)}</option>`)
    .join('');

  document.getElementById('detail-panel').innerHTML = `
<div>
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
    <div>
      <div style="font-size:22px;font-weight:800;color:#fff;letter-spacing:1.5px;">${esc(f.flight_number)}</div>
      <div style="font-size:13px;color:#64748b;">${esc(f.airline_name||'')}</div>
    </div>
    <div style="display:flex;gap:8px;">
      <button class="btn-ghost" onclick="openEdit(${f.id})" title="ویرایش">
        <i class="fas fa-edit"></i>
      </button>
      <button class="btn-danger" onclick="deleteFlight(${f.id})" title="حذف">
        <i class="fas fa-trash"></i>
      </button>
      <a href="/player/PREVIEW_${f.id}" target="_blank" class="btn-ghost" title="پیش‌نمایش">
        <i class="fas fa-external-link-alt"></i>
      </a>
    </div>
  </div>

  <!-- Route -->
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;padding:14px;background:rgba(255,255,255,0.03);border-radius:14px;border:1px solid rgba(255,255,255,0.07);">
    <div style="text-align:center;">
      <div style="font-size:28px;font-weight:900;color:#fff;">${esc(f.origin_iata||'---')}</div>
      <div style="font-size:12px;color:#94a3b8;">${esc(f.origin_city||'')}</div>
      <div style="font-size:10px;color:#475569;">${esc(f.origin_country||'')}</div>
    </div>
    <div style="flex:1;text-align:center;">
      <div style="font-size:10px;color:#475569;margin-bottom:4px;">مسافت کل</div>
      <div style="font-size:13px;font-weight:700;color:#00b4d8;">${distKm ? Math.round(distKm).toLocaleString() + ' km' : '---'}</div>
      <div style="position:relative;height:2px;background:rgba(255,255,255,0.07);margin:8px 0;border-radius:1px;">
        <div style="position:absolute;top:0;right:0;height:100%;width:${f.progress_pct}%;background:${esc(f.accent_color)};border-radius:1px;transition:width 0.5s;"></div>
        <div style="position:absolute;top:-4px;right:${f.progress_pct}%;transform:translateX(50%);">
          <i class="fas fa-plane" style="color:${esc(f.accent_color)};font-size:12px;"></i>
        </div>
      </div>
    </div>
    <div style="text-align:center;">
      <div style="font-size:28px;font-weight:900;color:#fff;">${esc(f.dest_iata||'---')}</div>
      <div style="font-size:12px;color:#94a3b8;">${esc(f.dest_city||'')}</div>
      <div style="font-size:10px;color:#475569;">${esc(f.dest_country||'')}</div>
    </div>
  </div>

  <!-- Telemetry -->
  <div class="telemetry-grid">
    <div class="tele-item">
      <div class="tele-val" id="d-alt">${f.altitude_ft.toLocaleString()}</div>
      <div class="tele-lbl">ارتفاع (ft)</div>
    </div>
    <div class="tele-item">
      <div class="tele-val" id="d-spd">${f.speed_kmh}</div>
      <div class="tele-lbl">سرعت (km/h)</div>
    </div>
    <div class="tele-item">
      <div class="tele-val" id="d-eta">${etaStr}</div>
      <div class="tele-lbl">ETA</div>
    </div>
    <div class="tele-item">
      <div class="tele-val" id="d-pct">${f.progress_pct}%</div>
      <div class="tele-lbl">پیشرفت</div>
    </div>
  </div>

  <!-- Live Control Panel -->
  <div class="card" style="margin-top:16px;">
    <div class="panel-head">
      <i class="fas fa-sliders ml-1" style="color:#00b4d8;"></i> کنترل زنده پرواز
    </div>

    <!-- Phase selector -->
    <div style="margin-bottom:14px;">
      <label class="form-label">فاز پرواز</label>
      <div style="display:flex;flex-wrap:wrap;gap:6px;" id="phase-btns">
        ${['preflight','taxi','takeoff','climb','cruise','descent','approach','landing','landed'].map(ph =>
          `<button type="button" class="phase-badge phase-${ph} ${f.phase===ph?'ring-2':''}"
                  style="${f.phase===ph?'outline:2px solid white;':''}"
                  onclick="setPhase('${ph}')">
            <i class="fas fa-${phaseIconJs(ph)}"></i> ${phaseLabelJs(ph)}
          </button>`
        ).join('')}
      </div>
    </div>

    <!-- Progress -->
    <div style="margin-bottom:14px;">
      <label class="form-label">پیشرفت مسیر — <span id="pct-label">${f.progress_pct}</span>%</label>
      <div class="range-row">
        <input type="range" id="ctrl-pct" min="0" max="100" value="${f.progress_pct}"
               oninput="document.getElementById('pct-label').textContent=this.value">
        <button class="btn-live" onclick="pushTelemetry()">
          <i class="fas fa-broadcast-tower"></i> ارسال
        </button>
      </div>
    </div>

    <!-- Altitude & Speed -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
      <div>
        <label class="form-label">ارتفاع (ft) — <span id="alt-label">${f.altitude_ft}</span></label>
        <div class="range-row">
          <input type="range" id="ctrl-alt" min="0" max="45000" step="500" value="${f.altitude_ft}"
                 oninput="document.getElementById('alt-label').textContent=parseInt(this.value).toLocaleString()">
        </div>
      </div>
      <div>
        <label class="form-label">سرعت (km/h) — <span id="spd-label">${f.speed_kmh}</span></label>
        <div class="range-row">
          <input type="range" id="ctrl-spd" min="0" max="1000" step="10" value="${f.speed_kmh}"
                 oninput="document.getElementById('spd-label').textContent=this.value">
        </div>
      </div>
    </div>

    <!-- Heading -->
    <div style="margin-bottom:14px;">
      <label class="form-label">مسیر (heading) — <span id="hdg-label">${f.heading_deg}°</span></label>
      <div class="range-row">
        <input type="range" id="ctrl-hdg" min="0" max="359" value="${f.heading_deg}"
               oninput="document.getElementById('hdg-label').textContent=this.value+'°'">
      </div>
    </div>

    <button class="btn-live" style="width:100%;justify-content:center;" onclick="pushTelemetry()">
      <i class="fas fa-satellite-dish"></i> ارسال همه تله‌متری
    </button>
  </div>

  <!-- Assigned screens -->
  <div class="card" style="margin-top:16px;">
    <div class="panel-head"><i class="fas fa-tv ml-1" style="color:#fbbf24;"></i> نمایشگرهای این پرواز</div>
    ${screensData.length === 0
      ? '<p style="color:#475569;font-size:13px;text-align:center;padding:20px 0;">نمایشگری با نوع inflight ثبت نشده</p>'
      : `<p style="font-size:12px;color:#64748b;margin-bottom:8px;">این پرواز را به نمایشگرها اختصاص دهید:</p>
         <select multiple id="screen-assign" class="form-input" style="height:120px;">${screenOpts}</select>
         <button class="btn-live" style="margin-top:8px;" onclick="assignScreens(${f.id})">
           <i class="fas fa-link"></i> اعمال
         </button>`
    }
  </div>

  <!-- ── Raspberry Pi Bridge ─────────────────────────────────────── -->
  <div class="card" style="margin-top:16px;border-color:rgba(0,180,216,0.25);">
    <div class="panel-head" style="color:#00b4d8;border-color:rgba(0,180,216,0.15);">
      <span style="font-size:14px;">🍓</span> Raspberry Pi — GPS + ADS-B
    </div>

    <!-- Connection form -->
    <div style="display:grid;grid-template-columns:1fr 90px auto;gap:8px;align-items:end;margin-bottom:14px;">
      <div>
        <label class="form-label">آدرس IP Raspberry Pi</label>
        <input type="text" id="rpi-ip-field" class="form-input"
               placeholder="192.168.1.100"
               value="${esc(f.rpi_ip||'')}">
      </div>
      <div>
        <label class="form-label">پورت</label>
        <input type="number" id="rpi-port-field" class="form-input"
               value="${f.rpi_port||5055}" min="1" max="65535">
      </div>
      <button class="btn-live" onclick="saveRpiIp(${f.id})" style="height:38px;">
        <i class="fas fa-save"></i>
      </button>
    </div>

    <!-- Status panel -->
    <div id="rpi-status-box"
         style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);
                border-radius:12px;padding:14px;min-height:80px;margin-bottom:12px;">
      ${f.rpi_ip
        ? `<div style="color:#64748b;font-size:12px;text-align:center;padding:12px 0;">
             <i class="fas fa-satellite-dish" style="font-size:20px;display:block;margin-bottom:6px;opacity:0.4;"></i>
             برای بررسی وضعیت کلیک کنید
           </div>`
        : `<div style="color:#475569;font-size:12px;text-align:center;padding:12px 0;">
             <i class="fas fa-plug" style="font-size:20px;display:block;margin-bottom:6px;opacity:0.3;"></i>
             آدرس IP Raspberry Pi را وارد و ذخیره کنید
           </div>`
      }
    </div>

    <!-- Action buttons -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
      <button class="btn-live" onclick="checkRpiStatus(${f.id})" id="rpi-check-btn">
        <i class="fas fa-satellite-dish"></i> بررسی وضعیت
      </button>
      <button class="btn-live"
              style="background:linear-gradient(135deg,#22c55e,#16a34a);"
              onclick="syncFromRpi(${f.id})" id="rpi-sync-btn">
        <i class="fas fa-sync"></i> همگام GPS
      </button>
      <button class="btn-live"
              style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);"
              onclick="openPushModal(${f.id})">
        <i class="fas fa-paper-plane"></i> تنظیم Push خودکار
      </button>
    </div>

    <!-- Auto-sync toggle -->
    <div style="padding:10px 12px;background:rgba(0,180,216,0.05);border:1px solid rgba(0,180,216,0.15);
                border-radius:10px;display:flex;align-items:center;justify-content:space-between;">
      <div>
        <div style="font-size:12px;color:#94a3b8;font-weight:600;">همگام‌سازی خودکار</div>
        <div style="font-size:10px;color:#475569;margin-top:1px;" id="autosync-sub">
          هر 10 ثانیه GPS را می‌خواند و تله‌متری را به‌روز می‌کند
        </div>
      </div>
      <button id="autosync-btn" onclick="toggleAutoSync(${f.id})"
              style="padding:5px 14px;border-radius:8px;font-size:12px;font-weight:700;
                     border:1px solid rgba(0,180,216,0.3);background:rgba(0,180,216,0.08);
                     color:#00b4d8;cursor:pointer;">
        شروع
      </button>
    </div>

    <!-- Setup guide -->
    <details style="margin-top:12px;">
      <summary style="font-size:11px;color:#475569;cursor:pointer;padding:6px 0;
                      border-top:1px solid rgba(255,255,255,0.06);">
        <i class="fas fa-book ml-1"></i> راهنمای نصب Raspberry Pi
      </summary>
      <div style="margin-top:10px;padding:12px;background:rgba(0,0,0,0.3);border-radius:10px;">
        <div style="font-size:11px;color:#64748b;line-height:1.9;">
          <b style="color:#00b4d8;display:block;margin-bottom:6px;">نیازمندی‌ها:</b>
          • Raspberry Pi 3/4/5 با Raspberry Pi OS<br>
          • ماژول GPS (مثل NEO-6M/8M) روی UART یا USB<br>
          • گیرنده RTL-SDR برای ADS-B (اختیاری)<br><br>
          <b style="color:#00b4d8;display:block;margin-bottom:6px;">نصب سریع:</b>
          <code style="display:block;background:rgba(0,0,0,0.4);padding:10px;border-radius:8px;
                       font-size:10px;color:#4ade80;white-space:pre-wrap;">git clone https://github.com/your-repo/signage-cms.git /tmp/signage
cd /tmp/signage/rpi
sudo bash setup.sh</code>
          <br>
          <b style="color:#00b4d8;display:block;margin-bottom:6px;">بررسی وضعیت:</b>
          <code style="display:block;background:rgba(0,0,0,0.4);padding:8px;border-radius:8px;
                       font-size:10px;color:#4ade80;">curl http://&lt;IP_PI&gt;:5055/api/status</code>
        </div>
      </div>
    </details>
  </div>
</div>
  `;
}

// Phase helpers (JS version)
function phaseIconJs(ph) {
  const m = {preflight:'clock',taxi:'car',takeoff:'plane-departure',climb:'arrow-trend-up',
             cruise:'plane',descent:'arrow-trend-down',approach:'plane-arrival',landing:'plane-arrival',landed:'flag-checkered'};
  return m[ph] || 'circle';
}
function phaseLabelJs(ph) {
  const m = {preflight:'قبل از پرواز',taxi:'Taxi',takeoff:'برخاستن',climb:'صعود',
             cruise:'کروز',descent:'نزول',approach:'فرود',landing:'نشست',landed:'فرود کرده'};
  return m[ph] || ph;
}

// ── Live telemetry ────────────────────────────────────────────────
async function setPhase(ph) {
  if (!selectedId) return;
  const r = await apiFetch(`${API}/${selectedId}/live`, {
    method:'PUT', body: JSON.stringify({phase: ph})
  });
  if (r.success) {
    updateFlightLocal(selectedId, {phase: ph});
    toast('فاز پرواز: '+phaseLabelJs(ph), 'success');
    document.querySelectorAll('#phase-btns button').forEach(b => b.style.outline='');
    document.querySelectorAll('#phase-btns button').forEach(b => {
      if (b.textContent.includes(phaseLabelJs(ph))) b.style.outline='2px solid white';
    });
  }
}

async function pushTelemetry() {
  if (!selectedId) return;
  const pct = parseInt(document.getElementById('ctrl-pct')?.value || 0);
  const alt = parseInt(document.getElementById('ctrl-alt')?.value || 0);
  const spd = parseInt(document.getElementById('ctrl-spd')?.value || 0);
  const hdg = parseInt(document.getElementById('ctrl-hdg')?.value || 0);
  const r = await apiFetch(`${API}/${selectedId}/live`, {
    method:'PUT',
    body: JSON.stringify({progress_pct:pct, altitude_ft:alt, speed_kmh:spd, heading_deg:hdg})
  });
  if (r.success) {
    updateFlightLocal(selectedId, {progress_pct:pct, altitude_ft:alt, speed_kmh:spd, heading_deg:hdg});
    document.getElementById('d-alt').textContent = alt.toLocaleString();
    document.getElementById('d-spd').textContent = spd;
    document.getElementById('d-pct').textContent = pct+'%';
    const f = flightsData.find(x=>x.id==selectedId);
    const distKm = f ? haversine(f.origin_lat,f.origin_lng,f.dest_lat,f.dest_lng) : 0;
    if (distKm && spd > 0) {
      const rem = distKm*(1-pct/100);
      document.getElementById('d-eta').textContent = fmtEta(Math.round(rem/spd*60));
    }
    toast('تله‌متری ارسال شد', 'success');
  }
}

function updateFlightLocal(id, patch) {
  const idx = flightsData.findIndex(x=>x.id==id);
  if (idx >= 0) { Object.assign(flightsData[idx], patch); }
  // Update card progress bar
  if (patch.progress_pct !== undefined) {
    const bar = document.getElementById('fc-prog-'+id);
    if (bar) bar.style.width = patch.progress_pct+'%';
  }
  if (patch.phase) {
    const badge = document.getElementById('fc-phase-'+id);
    if (badge) {
      badge.className = 'phase-badge phase-'+patch.phase;
      badge.innerHTML = `<i class="fas fa-${phaseIconJs(patch.phase)}"></i> ${phaseLabelJs(patch.phase)}`;
    }
  }
}

// ── Assign screens ────────────────────────────────────────────────
async function assignScreens(flightId) {
  const sel = document.getElementById('screen-assign');
  if (!sel) return;
  const ids = Array.from(sel.selectedOptions).map(o=>parseInt(o.value));
  // for each screen in screensData, set or clear inflight_flight_id
  await Promise.all(screensData.map(async s => {
    const assign = ids.includes(s.id);
    const current = s.inflight_flight_id == flightId;
    if (assign !== current) {
      await apiFetch(`/api/v1/screens/${s.id}`, {
        method:'PUT',
        body: JSON.stringify({inflight_flight_id: assign ? flightId : null})
      });
      s.inflight_flight_id = assign ? flightId : null;
    }
  }));
  toast('نمایشگرها به‌روز شدند', 'success');
}

// ── CRUD modal ────────────────────────────────────────────────────
function openCreate() {
  document.getElementById('modal-title').textContent = 'پرواز جدید';
  document.getElementById('f-id').value = '';
  document.getElementById('flight-form').reset();
  document.getElementById('f-accent').value = '#00b4d8';
  document.getElementById('f-accent-txt').value = '#00b4d8';
  document.getElementById('flight-modal').classList.remove('hidden');
}
function openEdit(id) {
  const f = flightsData.find(x=>x.id==id);
  if (!f) return;
  document.getElementById('modal-title').textContent = 'ویرایش پرواز';
  document.getElementById('f-id').value = id;
  document.getElementById('f-number').value = f.flight_number||'';
  document.getElementById('f-airline').value = f.airline_name||'';
  document.getElementById('f-orig-iata').value = f.origin_iata||'';
  document.getElementById('f-orig-city').value = f.origin_city||'';
  document.getElementById('f-orig-country').value = f.origin_country||'';
  document.getElementById('f-orig-lat').value = f.origin_lat||'';
  document.getElementById('f-orig-lng').value = f.origin_lng||'';
  document.getElementById('f-orig-tz').value = f.origin_timezone||'';
  document.getElementById('f-dest-iata').value = f.dest_iata||'';
  document.getElementById('f-dest-city').value = f.dest_city||'';
  document.getElementById('f-dest-country').value = f.dest_country||'';
  document.getElementById('f-dest-lat').value = f.dest_lat||'';
  document.getElementById('f-dest-lng').value = f.dest_lng||'';
  document.getElementById('f-dest-tz').value = f.dest_timezone||'';
  document.getElementById('f-dep').value = (f.departure_at||'').replace(' ','T').substring(0,16);
  document.getElementById('f-arr').value = (f.arrival_at||'').replace(' ','T').substring(0,16);
  document.getElementById('f-accent').value = f.accent_color||'#00b4d8';
  document.getElementById('f-accent-txt').value = f.accent_color||'#00b4d8';
  document.getElementById('f-bg').value = f.bg_style||'space';
  document.getElementById('f-active').value = f.is_active||'1';
  document.getElementById('f-welcome').value = f.welcome_msg||'';
  document.getElementById('flight-modal').classList.remove('hidden');
}
function closeModal() { document.getElementById('flight-modal').classList.add('hidden'); }

function syncColor(v) {
  if (/^#[0-9a-f]{6}$/i.test(v)) document.getElementById('f-accent').value = v;
}

async function saveFlight(e) {
  e.preventDefault();
  const id = document.getElementById('f-id').value;
  const body = {
    flight_number:   document.getElementById('f-number').value.toUpperCase().trim(),
    airline_name:    document.getElementById('f-airline').value.trim(),
    origin_iata:     document.getElementById('f-orig-iata').value.toUpperCase().trim(),
    origin_city:     document.getElementById('f-orig-city').value.trim(),
    origin_country:  document.getElementById('f-orig-country').value.trim(),
    origin_lat:      parseFloat(document.getElementById('f-orig-lat').value)||null,
    origin_lng:      parseFloat(document.getElementById('f-orig-lng').value)||null,
    origin_timezone: document.getElementById('f-orig-tz').value.trim()||'UTC',
    dest_iata:       document.getElementById('f-dest-iata').value.toUpperCase().trim(),
    dest_city:       document.getElementById('f-dest-city').value.trim(),
    dest_country:    document.getElementById('f-dest-country').value.trim(),
    dest_lat:        parseFloat(document.getElementById('f-dest-lat').value)||null,
    dest_lng:        parseFloat(document.getElementById('f-dest-lng').value)||null,
    dest_timezone:   document.getElementById('f-dest-tz').value.trim()||'UTC',
    departure_at:    document.getElementById('f-dep').value||null,
    arrival_at:      document.getElementById('f-arr').value||null,
    accent_color:    document.getElementById('f-accent-txt').value||'#00b4d8',
    bg_style:        document.getElementById('f-bg').value||'space',
    is_active:       parseInt(document.getElementById('f-active').value),
    welcome_msg:     document.getElementById('f-welcome').value.trim()||null,
  };

  const isEdit = !!id;
  const r = await apiFetch(isEdit ? `${API}/${id}` : API, {
    method: isEdit ? 'PUT' : 'POST',
    body: JSON.stringify(body)
  });
  if (r.success) {
    toast(isEdit ? 'پرواز ویرایش شد' : 'پرواز اضافه شد', 'success');
    closeModal();
    setTimeout(() => location.reload(), 700);
  } else {
    toast(r.message || 'خطا در ذخیره', 'error');
  }
}

async function deleteFlight(id) {
  if (!confirm('پرواز حذف شود؟')) return;
  const r = await apiFetch(`${API}/${id}`, {method:'DELETE'});
  if (r.success) { toast('پرواز حذف شد','success'); setTimeout(()=>location.reload(),700); }
}

// ── Utilities ────────────────────────────────────────────────────
function haversine(lat1,lng1,lat2,lng2) {
  if (!lat1||!lng1||!lat2||!lng2) return 0;
  const R=6371, dLat=(lat2-lat1)*Math.PI/180, dLng=(lng2-lng1)*Math.PI/180;
  const a=Math.sin(dLat/2)**2+Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)**2;
  return R*2*Math.asin(Math.sqrt(a));
}
function fmtEta(mins) {
  if (!mins || mins<0) return '---';
  const h=Math.floor(mins/60), m=mins%60;
  return h>0 ? `${h}h ${m}m` : `${m}m`;
}
function esc(s) { const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
function toast(msg, type='success') {
  const t=document.createElement('div');
  t.className='toast toast-'+type;
  t.innerHTML=`<i class="fas fa-${type==='success'?'check':'exclamation'}-circle"></i> ${msg}`;
  document.body.appendChild(t);
  setTimeout(()=>t.remove(),3000);
}
// Color sync
document.getElementById('f-accent')?.addEventListener('input', function() {
  document.getElementById('f-accent-txt').value = this.value;
});

// auto-select first flight
if (flightsData.length > 0) selectFlight(flightsData[0].id);

// ══════════════════════════════════════════════════════════════════
// ── Raspberry Pi Bridge functions ─────────────────────────────────
// ══════════════════════════════════════════════════════════════════

let autoSyncTimer  = null;
let autoSyncActive = false;

// Save RPi IP/port to SignageCMS server
async function saveRpiIp(flightId) {
  const ip   = document.getElementById('rpi-ip-field')?.value?.trim() || '';
  const port = parseInt(document.getElementById('rpi-port-field')?.value || 5055);
  const r = await apiFetch(`${API}/${flightId}/rpi-save`, {
    method: 'POST', body: JSON.stringify({rpi_ip: ip, rpi_port: port})
  });
  if (r.success) {
    updateFlightLocal(flightId, {rpi_ip: ip, rpi_port: port});
    toast('IP ذخیره شد', 'success');
  } else {
    toast(r.message || 'خطا', 'error');
  }
}

// Check RPi status (proxied via SignageCMS backend)
async function checkRpiStatus(flightId) {
  const btn = document.getElementById('rpi-check-btn');
  const box = document.getElementById('rpi-status-box');
  if (!box) return;
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال بررسی...'; }

  box.innerHTML = `<div style="text-align:center;padding:16px;color:#64748b;font-size:12px;">
    <i class="fas fa-spinner fa-spin" style="font-size:20px;display:block;margin-bottom:6px;"></i>
    در حال اتصال...
  </div>`;

  try {
    const r = await apiFetch(`${API}/${flightId}/rpi-status`);
    if (r.success) {
      renderRpiStatus(r.data);
    } else {
      box.innerHTML = rpiErrorBox(r.message || 'اتصال ممکن نشد');
    }
  } catch(e) {
    box.innerHTML = rpiErrorBox('خطای شبکه: ' + e.message);
  }
  if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-satellite-dish"></i> بررسی وضعیت'; }
}

function renderRpiStatus(d) {
  const box = document.getElementById('rpi-status-box');
  if (!box) return;
  const gps  = d.gps  || {};
  const adsb = d.adsb || {};
  const push = d.push || {};
  const upMin = d.uptime_s ? Math.floor(d.uptime_s/60) : 0;

  const gpsColor   = gps.fix  ? '#4ade80' : '#f87171';
  const adsbColor  = adsb.total > 0 ? '#4ade80' : '#fbbf24';

  box.innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px;margin-bottom:12px;">
      <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:10px;text-align:center;">
        <div style="font-size:18px;font-weight:800;color:${gpsColor};">${gps.fix ? '✅ Fix' : '❌ No Fix'}</div>
        <div style="font-size:10px;color:#64748b;margin-top:2px;">GPS</div>
      </div>
      <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:10px;text-align:center;">
        <div style="font-size:18px;font-weight:800;color:#fff;">${gps.satellites_used||0}</div>
        <div style="font-size:10px;color:#64748b;margin-top:2px;">ماهواره</div>
      </div>
      <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:10px;text-align:center;">
        <div style="font-size:18px;font-weight:800;color:${adsbColor};">${adsb.total||0}</div>
        <div style="font-size:10px;color:#64748b;margin-top:2px;">ADS-B هواپیما</div>
      </div>
      <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:10px;text-align:center;">
        <div style="font-size:18px;font-weight:800;color:#00b4d8;">${upMin}m</div>
        <div style="font-size:10px;color:#64748b;margin-top:2px;">Uptime</div>
      </div>
    </div>
    ${gps.fix ? `
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;font-size:11px;">
      <div style="background:rgba(0,180,216,0.06);border-radius:8px;padding:8px;text-align:center;">
        <div style="font-weight:700;color:#fff;">${(gps.lat||0).toFixed(4)}°</div>
        <div style="color:#475569;">Lat</div>
      </div>
      <div style="background:rgba(0,180,216,0.06);border-radius:8px;padding:8px;text-align:center;">
        <div style="font-weight:700;color:#fff;">${(gps.lng||0).toFixed(4)}°</div>
        <div style="color:#475569;">Lng</div>
      </div>
      <div style="background:rgba(0,180,216,0.06);border-radius:8px;padding:8px;text-align:center;">
        <div style="font-weight:700;color:#fff;">${(gps.alt_ft||0).toLocaleString()} ft</div>
        <div style="color:#475569;">ارتفاع</div>
      </div>
      <div style="background:rgba(0,180,216,0.06);border-radius:8px;padding:8px;text-align:center;">
        <div style="font-weight:700;color:#fff;">${Math.round(gps.speed_kmh||0)} km/h</div>
        <div style="color:#475569;">سرعت</div>
      </div>
    </div>` : ''}
    ${push.push_enabled ? `
    <div style="margin-top:8px;padding:6px 10px;background:rgba(34,197,94,.08);
                border:1px solid rgba(34,197,94,.2);border-radius:8px;font-size:11px;
                display:flex;align-items:center;gap:6px;">
      <span style="color:#4ade80;font-size:13px;">●</span>
      <span style="color:#4ade80;">Push فعال</span>
      <span style="color:#475569;margin-right:4px;">آخرین: ${push.last_push_at ? new Date(push.last_push_at).toLocaleTimeString('fa-IR') : '---'}</span>
      <span style="color:#475569;">|</span>
      <span style="color:#94a3b8;">${push.push_count||0} بار ارسال</span>
    </div>` : ''}
  `;
}

function rpiErrorBox(msg) {
  return `<div style="display:flex;align-items:center;gap:8px;padding:12px;
               background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);
               border-radius:10px;color:#f87171;font-size:12px;">
    <i class="fas fa-exclamation-triangle"></i> ${esc(msg)}
  </div>`;
}

// Sync GPS from RPi → update flight telemetry
async function syncFromRpi(flightId) {
  const btn = document.getElementById('rpi-sync-btn');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> همگام...'; }

  const r = await apiFetch(`${API}/${flightId}/rpi-sync`, {method: 'POST'});
  if (r.success) {
    const d = r.data;
    updateFlightLocal(flightId, {
      altitude_ft:  d.altitude_ft,
      speed_kmh:    d.speed_kmh,
      heading_deg:  d.heading_deg,
      phase:        d.phase,
      progress_pct: d.progress_pct ?? flightsData.find(x=>x.id==flightId)?.progress_pct,
    });
    // Update display values
    const f = flightsData.find(x=>x.id==flightId);
    if (f) {
      document.getElementById('d-alt')?.textContent && (document.getElementById('d-alt').textContent = (d.altitude_ft||0).toLocaleString());
      document.getElementById('d-spd')?.textContent && (document.getElementById('d-spd').textContent = d.speed_kmh||0);
      if (d.progress_pct != null) document.getElementById('d-pct')?.textContent && (document.getElementById('d-pct').textContent = d.progress_pct+'%');
    }
    toast(`GPS همگام شد — ${(d.altitude_ft||0).toLocaleString()} ft / ${d.speed_kmh||0} km/h`, 'success');
  } else {
    toast(r.message || 'خطا در همگام‌سازی', 'error');
  }
  if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync"></i> همگام GPS'; }
}

// Auto-sync toggle
function toggleAutoSync(flightId) {
  const btn = document.getElementById('autosync-btn');
  const sub = document.getElementById('autosync-sub');
  if (autoSyncActive) {
    clearInterval(autoSyncTimer);
    autoSyncTimer  = null;
    autoSyncActive = false;
    if (btn) { btn.textContent = 'شروع'; btn.style.color = '#00b4d8'; }
    if (sub) sub.textContent = 'هر 10 ثانیه GPS را می‌خواند و تله‌متری را به‌روز می‌کند';
    toast('همگام‌سازی خودکار متوقف شد', 'success');
  } else {
    autoSyncActive = true;
    syncFromRpi(flightId);
    autoSyncTimer = setInterval(() => syncFromRpi(flightId), 10000);
    if (btn) { btn.textContent = 'توقف'; btn.style.color = '#f87171'; }
    if (sub) sub.textContent = '🟢 فعال — هر 10 ثانیه به‌روز می‌شود';
    toast('همگام‌سازی خودکار شروع شد', 'success');
  }
}

// Open push-config modal
function openPushModal(flightId) {
  const f = flightsData.find(x=>x.id==flightId);
  document.getElementById('push-flight-id').value   = flightId;
  document.getElementById('push-cms-url').value     = window.location.origin;
  document.getElementById('push-interval').value    = 10;
  document.getElementById('push-enabled').checked   = false;
  document.getElementById('push-modal').classList.remove('hidden');
}

async function savePushConfig(e) {
  e.preventDefault();
  const flightId = document.getElementById('push-flight-id').value;
  const body = {
    cms_url:       document.getElementById('push-cms-url').value.trim(),
    api_token:     document.getElementById('push-api-token').value.trim(),
    push_enabled:  document.getElementById('push-enabled').checked,
    push_interval: parseInt(document.getElementById('push-interval').value||10),
  };
  const r = await apiFetch(`${API}/${flightId}/rpi-push-config`, {
    method:'POST', body: JSON.stringify(body)
  });
  document.getElementById('push-modal').classList.add('hidden');
  if (r.success) {
    toast('تنظیمات Push به RPi ارسال شد', 'success');
  } else {
    toast(r.message || 'خطا در ارسال config', 'error');
  }
}
</script>

<!-- ── Push Config Modal ──────────────────────────────────────────────── -->
<div class="modal-overlay hidden" id="push-modal">
  <div class="modal" style="max-width:480px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="font-size:15px;font-weight:700;color:#fff;margin:0;">
        <i class="fas fa-paper-plane ml-2" style="color:#8b5cf6;"></i>
        تنظیم Push خودکار از RPi
      </h3>
      <button onclick="document.getElementById('push-modal').classList.add('hidden')" class="btn-ghost" style="padding:6px 10px;">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <p style="font-size:12px;color:#64748b;margin-bottom:16px;line-height:1.7;">
      با این تنظیم، Raspberry Pi هر چند ثانیه یکبار داده GPS را مستقیماً
      به SignageCMS ارسال می‌کند — حتی بدون نیاز به باز بودن این پنل.
    </p>
    <form onsubmit="savePushConfig(event)">
      <input type="hidden" id="push-flight-id">
      <div style="margin-bottom:14px;">
        <label class="form-label">آدرس SignageCMS (URL کامل)</label>
        <input type="url" id="push-cms-url" class="form-input"
               placeholder="https://your-signage-server.com" required>
      </div>
      <div style="margin-bottom:14px;">
        <label class="form-label">
          API Token
          <span style="color:#64748b;font-weight:400;">(از localStorage مرورگر کپی کنید)</span>
        </label>
        <div style="position:relative;">
          <input type="password" id="push-api-token" class="form-input"
                 placeholder="eyJhbGciOi..." style="padding-left:36px;" required>
          <button type="button"
                  onclick="const el=document.getElementById('push-api-token');el.type=el.type==='password'?'text':'password';"
                  style="position:absolute;left:10px;top:50%;transform:translateY(-50%);
                         background:none;border:none;color:#64748b;cursor:pointer;">
            <i class="fas fa-eye"></i>
          </button>
        </div>
        <div style="font-size:10px;color:#475569;margin-top:4px;">
          برای دریافت token: کنسول مرورگر → <code>localStorage.getItem('auth_token')</code>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div>
          <label class="form-label">فاصله ارسال (ثانیه)</label>
          <input type="number" id="push-interval" class="form-input"
                 value="10" min="5" max="60">
        </div>
        <div style="display:flex;align-items:center;padding-top:24px;gap:8px;">
          <input type="checkbox" id="push-enabled"
                 style="width:16px;height:16px;accent-color:#8b5cf6;">
          <label for="push-enabled" style="font-size:13px;color:#94a3b8;cursor:pointer;">
            Push فعال باشد
          </label>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button"
                onclick="document.getElementById('push-modal').classList.add('hidden')"
                class="btn-ghost">
          انصراف
        </button>
        <button type="submit"
                style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;
                       padding:8px 18px;border-radius:10px;font-size:13px;font-weight:700;
                       border:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
          <i class="fas fa-paper-plane"></i> ارسال به RPi
        </button>
      </div>
    </form>
  </div>
</div>

<?php
function phaseIcon(string $ph): string {
  $m = ['preflight'=>'clock','taxi'=>'car','takeoff'=>'plane-departure','climb'=>'arrow-trend-up',
        'cruise'=>'plane','descent'=>'arrow-trend-down','approach'=>'plane-arrival','landing'=>'plane-arrival','landed'=>'flag-checkered'];
  return $m[$ph] ?? 'circle';
}
function phaseLabel(string $ph): string {
  $m = ['preflight'=>'قبل از پرواز','taxi'=>'Taxi','takeoff'=>'برخاستن','climb'=>'صعود',
        'cruise'=>'کروز','descent'=>'نزول','approach'=>'فرود','landing'=>'نشست','landed'=>'فرود کرده'];
  return $m[$ph] ?? $ph;
}
?>

<?php include VIEWS_PATH . '/partials/footer.php'; ?>
