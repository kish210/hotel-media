<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-video" style="color:#0ea5e9;margin-left:10px;"></i>دوربین مداربسته
  </h1>
  <button onclick="openM('camModal')" class="btn-primary text-sm">
    <i class="fas fa-plus text-xs ml-1"></i>افزودن دوربین
  </button>
</div>

<?php if (!$ffmpegOk): ?>
<div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);border-radius:10px;padding:14px;margin-bottom:18px;font-size:13px;color:#f87171;line-height:1.9;">
  <b>ffmpeg روی سرور نصب نیست.</b> دوربین RTSP می‌دهد و تلویزیون RTSP نمی‌خواند؛
  بدون ffmpeg تبدیل به HLS انجام نمی‌شود. نصب: <code style="direction:ltr;">apt install ffmpeg</code>
</div>
<?php endif; ?>

<div style="background:rgba(14,165,233,.07);border:1px solid rgba(14,165,233,.2);border-radius:10px;padding:13px;margin-bottom:18px;font-size:12px;color:#94a3b8;line-height:1.95;">
  <b style="color:#38bdf8;">چطور کار می‌کند:</b>
  آدرس RTSP دوربین را می‌دهید، ffmpeg آن را زنده به HLS تبدیل می‌کند و تلویزیون
  اتاق از <code style="direction:ltr;color:#60a5fa;">/hls/&lt;نام&gt;/index.m3u8</code> می‌خواند.<br>
  <b style="color:#fbbf24;">دو نکته‌ی مهم:</b>
  آدرس RTSP (که معمولا رمز دوربین را دارد) هرگز به تلویزیون مهمان فرستاده نمی‌شود — فقط آدرس HLS.
  و HLS روی Tizen و webOS و اندروید کار می‌کند ولی روی سامسونگ <b>Orsay ۲۰۱۳</b> نه.
</div>

<div id="camList" class="card" style="padding:16px;font-size:13px;color:#64748b;">در حال بارگذاری…</div>

<!-- ══ مودال افزودن/ویرایش ══ -->
<div id="camModal" class="hidden modal-bg">
  <div class="modal-box" style="max-width:560px;">
    <div class="modal-head">
      <h3 id="camModalTitle">افزودن دوربین</h3>
      <button onclick="closeM('camModal')" class="btn-ghost text-xs px-2"><i class="fas fa-times"></i></button>
    </div>
    <div style="display:grid;gap:10px;">
      <input type="hidden" id="c-id" value="">
      <div><label class="form-label">نام (برای مهمان دیده می‌شود)</label>
        <input id="c-name" class="form-input" placeholder="مثلا زمین بازی کودکان"></div>
      <div><label class="form-label">محل نصب (یادداشت داخلی)</label>
        <input id="c-loc" class="form-input" placeholder="مثلا حیاط شمالی"></div>
      <div><label class="form-label">آدرس RTSP</label>
        <input id="c-rtsp" class="form-input" dir="ltr" style="font-family:monospace;"
               placeholder="rtsp://user:pass@192.168.1.50:554/stream1"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div><label class="form-label">کیفیت</label>
          <select id="c-q" class="form-input">
            <option value="low">پایین — 480p</option>
            <option value="medium" selected>متوسط — 720p</option>
            <option value="high">بالا — 1080p</option>
            <option value="copy">بدون تبدیل (copy)</option>
          </select></div>
        <div><label class="form-label">صدا</label>
          <select id="c-a" class="form-input">
            <option value="mute" selected>بی‌صدا</option>
            <option value="include">با صدا</option>
          </select></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div><label class="form-label">حداقل سطح اتاق</label>
          <input id="c-lvl" type="number" min="0" value="0" class="form-input"></div>
        <div style="display:flex;align-items:end;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#94a3b8;">
            <input type="checkbox" id="c-vis" checked style="accent-color:#0ea5e9;width:16px;height:16px;">
            در پورتال مهمان دیده شود
          </label>
        </div>
      </div>
      <button onclick="saveCam()" class="btn-primary" style="padding:11px;">ذخیره</button>
    </div>
  </div>
</div>

<!-- ══ مودال لاگ ══ -->
<div id="logModal" class="hidden modal-bg">
  <div class="modal-box" style="max-width:720px;">
    <div class="modal-head">
      <h3>لاگ ffmpeg</h3>
      <button onclick="closeM('logModal')" class="btn-ghost text-xs px-2"><i class="fas fa-times"></i></button>
    </div>
    <pre id="logBody" style="background:#0b0b11;padding:12px;border-radius:8px;max-height:400px;overflow:auto;
      font-size:11px;line-height:1.7;color:#94a3b8;direction:ltr;text-align:left;white-space:pre-wrap;">…</pre>
  </div>
</div>

<div id="toast" class="toast hidden"></div>

<script>
function openM(i){ document.getElementById(i).classList.remove('hidden'); }
function closeM(i){ document.getElementById(i).classList.add('hidden'); }

async function api(url, method='GET', body=null) {
  const o = { method, headers:{'Content-Type':'application/json','Accept':'application/json'}, credentials:'same-origin' };
  if (body) o.body = JSON.stringify(body);
  const r = await fetch(url, o);
  let d = {}; try { d = await r.json(); } catch(_) {}
  if (!r.ok) throw new Error(d.message || ('خطا (' + r.status + ')'));
  return d;
}
function toast(m, t='success'){
  const el = document.getElementById('toast');
  el.className = 'toast toast-' + t; el.textContent = m;
  el.classList.remove('hidden'); setTimeout(()=>el.classList.add('hidden'), 3500);
}
function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

async function loadCams() {
  const box = document.getElementById('camList');
  try {
    const d = await api('/api/v1/cameras');
    const rows = d.data || [];
    if (!rows.length) { box.innerHTML = 'هنوز دوربینی اضافه نشده.'; return; }
    box.innerHTML = rows.map(c => {
      const live = c.running && c.fresh;
      const dot  = live ? '#22c55e' : (c.running ? '#f59e0b' : '#64748b');
      const stat = live ? 'در حال پخش' : (c.running ? 'اجرا شده ولی خروجی کهنه' : 'متوقف');
      return '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;' +
             'border-bottom:1px solid rgba(255,255,255,.05);flex-wrap:wrap;">' +
        '<div style="min-width:220px;">' +
          '<div style="font-size:13px;color:#fff;font-weight:600;">' +
            '<i class="fas fa-circle" style="font-size:7px;color:' + dot + ';margin-left:6px;"></i>' +
            esc(c.name) + (c.location ? ' <span style="color:#64748b;font-weight:400;font-size:11px;">· ' + esc(c.location) + '</span>' : '') +
          '</div>' +
          '<div style="font-size:10px;color:#64748b;font-family:monospace;direction:ltr;">' + esc(c.rtsp_masked) + '</div>' +
          '<div style="font-size:10px;color:#64748b;">' + stat +
            ' · ' + esc(c.quality) + (c.guest_visible ? ' · برای مهمان' : ' · داخلی') + '</div>' +
        '</div>' +
        '<div style="display:flex;gap:6px;flex-wrap:wrap;">' +
          (live ? '<a href="' + esc(c.hls) + '" target="_blank" class="btn-ghost text-xs" style="padding:5px 10px;">آدرس HLS</a>' : '') +
          (c.running
            ? '<button onclick="stopCam(' + c.id + ')" class="btn-ghost text-xs" style="padding:5px 10px;color:#fbbf24;">توقف</button>'
            : '<button onclick="startCam(' + c.id + ')" class="btn-primary text-xs" style="padding:5px 10px;">شروع پخش</button>') +
          '<button onclick="showLog(' + c.id + ')" class="btn-ghost text-xs" style="padding:5px 10px;">لاگ</button>' +
          '<button onclick="editCam(' + c.id + ')" class="btn-ghost text-xs" style="padding:5px 10px;">ویرایش</button>' +
          '<button onclick="delCam(' + c.id + ')" class="btn-ghost text-xs" style="padding:5px 10px;color:#f87171;">حذف</button>' +
        '</div></div>';
    }).join('');
    window.__cams = rows;
  } catch(e) { box.innerHTML = '<span style="color:#f87171;">' + esc(e.message) + '</span>'; }
}

function clearForm() {
  document.getElementById('c-id').value   = '';
  document.getElementById('c-name').value = '';
  document.getElementById('c-loc').value  = '';
  document.getElementById('c-rtsp').value = '';
  document.getElementById('c-q').value    = 'medium';
  document.getElementById('c-a').value    = 'mute';
  document.getElementById('c-lvl').value  = 0;
  document.getElementById('c-vis').checked = true;
  document.getElementById('camModalTitle').textContent = 'افزودن دوربین';
}

function editCam(id) {
  const c = (window.__cams || []).find(x => x.id === id);
  if (!c) return;
  clearForm();
  document.getElementById('c-id').value   = c.id;
  document.getElementById('c-name').value = c.name || '';
  document.getElementById('c-loc').value  = c.location || '';
  document.getElementById('c-q').value    = c.quality || 'medium';
  document.getElementById('c-a').value    = c.audio || 'mute';
  document.getElementById('c-lvl').value  = c.access_level || 0;
  document.getElementById('c-vis').checked = !!c.guest_visible;
  document.getElementById('c-rtsp').placeholder = 'خالی بگذارید تا تغییر نکند';
  document.getElementById('camModalTitle').textContent = 'ویرایش دوربین';
  openM('camModal');
}

async function saveCam() {
  const id = document.getElementById('c-id').value;
  const body = {
    name:          document.getElementById('c-name').value.trim(),
    location:      document.getElementById('c-loc').value.trim(),
    quality:       document.getElementById('c-q').value,
    audio:         document.getElementById('c-a').value,
    access_level:  Number(document.getElementById('c-lvl').value) || 0,
    guest_visible: document.getElementById('c-vis').checked ? 1 : 0,
  };
  const rtsp = document.getElementById('c-rtsp').value.trim();
  if (rtsp) body.rtsp_url = rtsp;

  try {
    if (id) { await api('/api/v1/cameras/' + id, 'PUT', body); }
    else    { await api('/api/v1/cameras', 'POST', body); }
    toast('ذخیره شد'); closeM('camModal'); clearForm(); loadCams();
  } catch(e) { toast(e.message, 'error'); }
}

async function startCam(id) {
  try { const d = await api('/api/v1/cameras/' + id + '/start', 'POST'); toast(d.message || 'شروع شد');
    setTimeout(loadCams, 2500); } catch(e) { toast(e.message, 'error'); }
}
async function stopCam(id) {
  try { await api('/api/v1/cameras/' + id + '/stop', 'POST'); toast('متوقف شد'); loadCams(); }
  catch(e) { toast(e.message, 'error'); }
}
async function delCam(id) {
  if (!confirm('این دوربین حذف شود؟')) return;
  try { await api('/api/v1/cameras/' + id, 'DELETE'); toast('حذف شد'); loadCams(); }
  catch(e) { toast(e.message, 'error'); }
}
async function showLog(id) {
  openM('logModal');
  document.getElementById('logBody').textContent = 'در حال خواندن…';
  try { const d = await api('/api/v1/cameras/' + id + '/log');
    document.getElementById('logBody').textContent = (d.data && d.data.log) ? d.data.log : '(لاگی ثبت نشده)'; }
  catch(e) { document.getElementById('logBody').textContent = e.message; }
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelector('[onclick="openM(\'camModal\')"]').addEventListener('click', clearForm);
  loadCams();
  setInterval(() => { if (!document.querySelector('.modal-bg:not(.hidden)')) loadCams(); }, 20000);
});
</script>
