<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<?php
$venues = $venues ?? [];
$today  = $today  ?? date('Y-m-d');

$KIND_LABELS = [
  'restaurant' => ['رستوران',  'utensils',  '#f59e0b'],
  'cafe'       => ['کافی‌شاپ', 'mug-hot',   '#a16207'],
  'pool'       => ['استخر',    'water-ladder','#06b6d4'],
  'spa'        => ['اسپا',     'spa',       '#ec4899'],
  'gym'        => ['باشگاه',   'dumbbell',  '#22c55e'],
  'hall'       => ['سالن',     'people-roof','#8b5cf6'],
  'other'      => ['سایر',     'location-dot','#64748b'],
];
$bookable = array_values(array_filter($venues, fn($v) => (int)$v['bookable'] === 1));
?>

<style>
  .rs-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:14px;}
  .rs-table{width:100%;border-collapse:collapse;font-size:12px;}
  .rs-table th{color:#64748b;font-weight:600;text-align:right;padding:8px;border-bottom:1px solid rgba(255,255,255,.06);}
  .rs-table td{color:#cbd5e1;padding:8px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;}
  .rs-badge{font-size:10px;padding:2px 8px;border-radius:8px;white-space:nowrap;}
  .rs-table input.rs-in{width:80px !important;min-width:0;padding:4px 6px;font-size:12px;}
  .rs-table input.rs-time{width:118px !important;}
</style>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-calendar-check" style="color:#14b8a6;margin-left:10px;"></i>رزرو رستوران و امکانات
  </h1>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <input type="date" id="day" class="form-input" value="<?= htmlspecialchars($today) ?>" style="width:auto;">
    <select id="venueFilter" class="form-input" style="width:auto;">
      <option value="">همه‌ی محل‌ها</option>
      <?php foreach ($bookable as $v): ?>
        <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn-primary text-sm px-3" onclick="openNew()" <?= $bookable ? '' : 'disabled title="اول یک محل را رزروپذیر کنید"' ?>>
      <i class="fas fa-plus text-xs ml-1"></i>رزرو تلفنی / حضوری
    </button>
  </div>
</div>

<?php if (!$bookable): ?>
<div class="rs-card" style="margin-bottom:16px;border-color:rgba(245,158,11,.4);color:#fbbf24;font-size:12px;">
  <i class="fas fa-circle-info ml-1"></i>
  هنوز هیچ محلی رزروپذیر نیست. در جدول پایین صفحه، کنار رستوران یا استخر «رزرو» را روشن کنید و ساعت کاری و ظرفیت را وارد کنید.
  بعد در «منوی مهمان» یک کاشی از نوع «رزرو رستوران و امکانات» بسازید تا مهمان از تلویزیون ببیند.
</div>
<?php endif; ?>

<!-- آمار روز -->
<div id="stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:16px;"></div>

<!-- رزروهای روز -->
<div class="rs-card" style="margin-bottom:24px;overflow-x:auto;">
  <table class="rs-table">
    <thead><tr>
      <th>ساعت</th><th>محل</th><th>مهمان</th><th>نفر</th><th>مبلغ</th><th>یادداشت</th><th>وضعیت</th><th></th>
    </tr></thead>
    <tbody id="rows"><tr><td colspan="8" style="text-align:center;color:#475569;padding:30px;">در حال بارگذاری…</td></tr></tbody>
  </table>
</div>

<!-- تنظیمات محل‌ها -->
<h2 style="font-size:13px;color:#94a3b8;margin-bottom:10px;font-weight:700;">
  <i class="fas fa-sliders text-xs ml-1"></i>محل‌های قابل رزرو
</h2>
<div class="rs-card" style="overflow-x:auto;margin-bottom:24px;">
  <?php if (!$venues): ?>
    <div style="color:#64748b;font-size:12px;padding:10px;">هیچ رستوران، کافی‌شاپ، استخر، اسپا یا باشگاهی تعریف نشده است.</div>
  <?php else: ?>
  <table class="rs-table">
    <thead><tr>
      <th>محل</th><th>رزرو</th><th>ساعت کاری</th><th>ظرفیت هم‌زمان</th><th>طول نوبت (دقیقه)</th>
      <th>حداکثر نفر</th><th>هزینه هر نفر (ریال)</th><th>چند روز جلوتر</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($venues as $v):
      [$kl, $ki, $kc] = $KIND_LABELS[$v['kind']] ?? $KIND_LABELS['other']; ?>
      <tr data-venue="<?= (int)$v['id'] ?>">
        <td><i class="fas fa-<?= $ki ?>" style="color:<?= $kc ?>;margin-left:6px;"></i><?= htmlspecialchars($v['name']) ?>
            <div style="font-size:10px;color:#64748b;"><?= $kl ?></div></td>
        <td><input type="checkbox" name="bookable" <?= (int)$v['bookable'] ? 'checked' : '' ?> style="accent-color:#14b8a6;"></td>
        <td style="white-space:nowrap;">
          <input type="time" name="open_from" class="form-input rs-in rs-time" value="<?= htmlspecialchars(substr((string)$v['open_from'], 0, 5)) ?>">
          تا
          <input type="time" name="open_to" class="form-input rs-in rs-time" value="<?= htmlspecialchars(substr((string)$v['open_to'], 0, 5)) ?>">
        </td>
        <td><input type="number" name="capacity" class="form-input rs-in" min="0" value="<?= (int)$v['capacity'] ?>" title="۰ = بدون سقف"></td>
        <td><input type="number" name="slot_minutes" class="form-input rs-in" min="15" max="480" step="15" value="<?= (int)$v['slot_minutes'] ?>"></td>
        <td><input type="number" name="max_party" class="form-input rs-in" min="1" max="50" value="<?= (int)$v['max_party'] ?>"></td>
        <td><input type="number" name="booking_price" class="form-input rs-in" style="width:110px !important;" min="0" step="1000" value="<?= (float)$v['booking_price'] ?>"></td>
        <td><input type="number" name="days_ahead" class="form-input rs-in" min="0" max="60" value="<?= (int)$v['days_ahead'] ?>"></td>
        <td><button class="btn-ghost text-xs px-2" onclick="saveVenue(<?= (int)$v['id'] ?>)"><i class="fas fa-floppy-disk"></i> ذخیره</button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p style="font-size:11px;color:#64748b;margin-top:10px;line-height:1.9;">
    ظرفیت یعنی چند نفر هم‌زمان جا می‌شوند: برای رستوران تعداد صندلی، برای استخر و باشگاه ظرفیت هر نوبت.
    هزینه موقع تایید رزرو روی صورتحساب اتاق می‌نشیند و با لغو باطل می‌شود. عدم حضور مهمان هزینه را نگه می‌دارد.
  </p>
  <?php endif; ?>
</div>

<!-- مودال رزرو جدید -->
<div id="newModal" class="hidden" style="position:fixed;top:0;right:0;bottom:0;left:0;background:rgba(0,0,0,.7);z-index:90;display:flex;align-items:center;justify-content:center;padding:20px;">
  <div class="rs-card" style="background:#0f172a;width:100%;max-width:460px;">
    <h3 style="color:#fff;font-weight:800;margin-bottom:14px;">رزرو تلفنی / حضوری</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
      <label class="text-xs" style="color:#94a3b8;grid-column:1/-1;">محل
        <select id="nVenue" class="form-input" onchange="loadSlots()">
          <?php foreach ($bookable as $v): ?>
            <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="text-xs" style="color:#94a3b8;">تاریخ
        <input type="date" id="nDate" class="form-input" onchange="loadSlots()">
      </label>
      <label class="text-xs" style="color:#94a3b8;">نوبت
        <select id="nSlot" class="form-input"></select>
      </label>
      <label class="text-xs" style="color:#94a3b8;">شماره اتاق
        <input type="text" id="nRoom" class="form-input" placeholder="خالی = مهمان بیرونی">
      </label>
      <label class="text-xs" style="color:#94a3b8;">تعداد نفر
        <input type="number" id="nParty" class="form-input" min="1" value="2">
      </label>
      <label class="text-xs" style="color:#94a3b8;grid-column:1/-1;">نام مهمان
        <input type="text" id="nName" class="form-input" maxlength="100" placeholder="برای مهمان اتاق اختیاری است">
      </label>
      <label class="text-xs" style="color:#94a3b8;grid-column:1/-1;">یادداشت
        <input type="text" id="nNote" class="form-input" maxlength="300" placeholder="صندلی کودک، کنار پنجره…">
      </label>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
      <button class="btn-ghost text-sm px-3" onclick="closeModal('newModal')">انصراف</button>
      <button class="btn-primary text-sm px-3" onclick="submitNew()">ثبت رزرو</button>
    </div>
  </div>
</div>

<div id="toast" class="hidden"></div>

<script>
const STATUS = {
  pending:   ['در انتظار تایید', '#ef4444'],
  confirmed: ['تایید شد',        '#3b82f6'],
  completed: ['حاضر شد',         '#22c55e'],
  no_show:   ['نیامد',           '#a855f7'],
  cancelled: ['لغو شد',          '#64748b'],
};
const NEXT = {
  pending:   [['confirmed', 'تایید'], ['cancelled', 'رد']],
  confirmed: [['completed', 'حاضر شد'], ['no_show', 'نیامد'], ['cancelled', 'لغو']],
};
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = n => Number(n || 0).toLocaleString('fa-IR');

async function load() {
  const day = document.getElementById('day').value;
  const vid = document.getElementById('venueFilter').value;
  const q = new URLSearchParams({ date: day });
  if (vid) q.set('venue_id', vid);
  try {
    const d = await apiFetch('/api/v1/reservations?' + q);
    render(d.data || []);
  } catch (e) { showToast(e.message, 'error'); }
}

function render(list) {
  const active = list.filter(r => r.status === 'pending' || r.status === 'confirmed');
  const cards = [
    ['در انتظار تایید', list.filter(r => r.status === 'pending').length, 'hourglass-half', '#ef4444'],
    ['رزرو فعال',       active.length, 'calendar-check', '#14b8a6'],
    ['نفرات فعال',      active.reduce((s, r) => s + Number(r.party_size), 0), 'users', '#3b82f6'],
    ['نیامد',           list.filter(r => r.status === 'no_show').length, 'user-slash', '#a855f7'],
  ];
  document.getElementById('stats').innerHTML = cards.map(([l, v, i, c]) =>
    `<div class="rs-card"><div style="font-size:11px;color:#64748b;margin-bottom:6px;"><i class="fas fa-${i}" style="color:${c};margin-left:6px;"></i>${l}</div>
     <div style="font-size:18px;font-weight:800;color:#fff;">${v}</div></div>`).join('');

  const body = document.getElementById('rows');
  if (!list.length) {
    body.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#475569;padding:30px;">برای این روز رزروی نیست</td></tr>';
    return;
  }
  body.innerHTML = list.map(r => {
    const [sl, sc] = STATUS[r.status] || [r.status, '#64748b'];
    const who = r.room_number ? `اتاق ${esc(r.room_number)}` : '<span style="color:#94a3b8">بیرونی</span>';
    const btns = (NEXT[r.status] || []).map(([to, label]) =>
      `<button class="btn-ghost text-xs px-2" onclick="setStatus(${r.id}, '${to}')">${label}</button>`).join(' ');
    return `<tr>
      <td style="white-space:nowrap;font-weight:700;color:#fff;">${esc(r.start_at.slice(11, 16))}–${esc(r.end_at.slice(11, 16))}
          ${r.start_at.slice(0, 10) !== document.getElementById('day').value ? '<div style="font-size:10px;color:#64748b">بامداد فردا</div>' : ''}</td>
      <td>${esc(r.venue_name)}</td>
      <td>${who}${r.guest_name ? '<div style="font-size:10px;color:#64748b">' + esc(r.guest_name) + '</div>' : ''}</td>
      <td>${Number(r.party_size)}</td>
      <td>${Number(r.price) > 0 ? money(r.price) : '—'}</td>
      <td style="max-width:220px;font-size:11px;">${esc(r.note || '')}${r.source === 'panel' ? ' <span style="color:#64748b">(پذیرش)</span>' : ''}</td>
      <td><span class="rs-badge" style="background:${sc}22;color:${sc};">${sl}</span></td>
      <td style="white-space:nowrap;">${btns}</td>
    </tr>`;
  }).join('');
}

async function setStatus(id, status) {
  if (status === 'cancelled' && !confirm('رزرو لغو شود؟ اگر هزینه‌ای روی صورتحساب نشسته، باطل می‌شود.')) return;
  try {
    await apiFetch(`/api/v1/reservations/${id}/status`, 'PUT', { status });
    showToast('وضعیت به‌روز شد');
    load();
  } catch (e) { showToast(e.message, 'error'); }
}

async function saveVenue(id) {
  const tr = document.querySelector(`tr[data-venue="${id}"]`);
  const val = n => tr.querySelector(`[name="${n}"]`);
  const body = {
    bookable:      val('bookable').checked ? 1 : 0,
    open_from:     val('open_from').value || null,
    open_to:       val('open_to').value || null,
    capacity:      Number(val('capacity').value || 0),
    slot_minutes:  Number(val('slot_minutes').value || 60),
    max_party:     Number(val('max_party').value || 1),
    booking_price: Number(val('booking_price').value || 0),
    days_ahead:    Number(val('days_ahead').value || 0),
  };
  try {
    await apiFetch(`/api/v1/venues/${id}`, 'PUT', body);
    showToast('تنظیمات محل ذخیره شد');
    // فهرست محل‌های فیلتر و مودال از سرور می‌آید
    setTimeout(() => location.reload(), 600);
  } catch (e) { showToast(e.message, 'error'); }
}

function openNew() {
  document.getElementById('nDate').value = document.getElementById('day').value;
  document.getElementById('newModal').classList.remove('hidden');
  loadSlots();
}

async function loadSlots() {
  const sel = document.getElementById('nSlot');
  sel.innerHTML = '<option>…</option>';
  const q = new URLSearchParams({ venue_id: document.getElementById('nVenue').value, date: document.getElementById('nDate').value });
  try {
    const d = await apiFetch('/api/v1/reservations/slots?' + q);
    const slots = d.data || [];
    sel.innerHTML = slots.length ? slots.map(s =>
      `<option value="${esc(s.start)}" ${s.available ? '' : 'disabled'}>${esc(s.label)}${s.remaining === null ? '' : ' — ' + s.remaining + ' جا'}${s.available ? '' : ' (پر یا گذشته)'}</option>`
    ).join('') : '<option value="">این روز نوبتی ندارد</option>';
  } catch (e) { sel.innerHTML = `<option value="">${esc(e.message)}</option>`; }
}

async function submitNew() {
  const body = {
    venue_id:    Number(document.getElementById('nVenue').value),
    start_at:    document.getElementById('nSlot').value,
    party_size:  Number(document.getElementById('nParty').value),
    room_number: document.getElementById('nRoom').value.trim(),
    guest_name:  document.getElementById('nName').value.trim(),
    note:        document.getElementById('nNote').value.trim(),
  };
  if (!body.start_at) { showToast('نوبت را انتخاب کنید', 'error'); return; }
  try {
    await apiFetch('/api/v1/reservations', 'POST', body);
    closeModal('newModal');
    showToast('رزرو ثبت شد');
    load();
  } catch (e) { showToast(e.message, 'error'); }
}

document.getElementById('day').addEventListener('change', load);
document.getElementById('venueFilter').addEventListener('change', load);
load();
// رزروهای تازه از تلویزیون اتاق‌ها بی‌آنکه پذیرش صفحه را تازه کند برسند
setInterval(() => { if (!document.hidden) load(); }, 30000);

// ══ Helpers ════════════════════════════════════════════════════════
async function apiFetch(url, method = 'GET', body = null) {
  const opts = { method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, credentials: 'same-origin' };
  if (body) opts.body = JSON.stringify(body);
  const r = await fetch(url, opts);
  let d = {};
  try { d = await r.json(); } catch (_) {}
  if (!r.ok) throw new Error(d.message || `خطا (${r.status})`);
  return d;
}
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.className = `toast toast-${type}`;
  t.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${esc(msg)}`;
  t.classList.remove('hidden');
  setTimeout(() => t.classList.add('hidden'), 3500);
}
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
