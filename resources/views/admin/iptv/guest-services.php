<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<?php
$CAT_LABELS = [
  'room_service' => ['روم‌سرویس',   'utensils',      '#f59e0b'],
  'breakfast'    => ['صبحانه',      'mug-hot',       '#fbbf24'],
  'housekeeping' => ['خانه‌داری',    'broom',         '#22c55e'],
  'laundry'      => ['خشک‌شویی',    'shirt',         '#3b82f6'],
  'taxi'         => ['تاکسی',       'taxi',          '#eab308'],
  'maintenance'  => ['تعمیرات',     'screwdriver-wrench', '#1a7ac4'],
  'wakeup'       => ['بیدارباش',    'bell',          '#8b5cf6'],
  'feedback'     => ['نظرسنجی',     'star',          '#ec4899'],
  'other'        => ['سایر',        'ellipsis',      '#64748b'],
];
$STATUS_LABELS = [
  'pending'     => ['در انتظار',   '#ef4444'],
  'accepted'    => ['پذیرفته شد',  '#f59e0b'],
  'in_progress' => ['در حال انجام','#3b82f6'],
  'done'        => ['انجام شد',    '#22c55e'],
  'cancelled'   => ['لغو شد',      '#64748b'],
];
$NEXT_STATUS = [
  'pending'     => ['accepted' => 'پذیرش', 'cancelled' => 'رد'],
  'accepted'    => ['in_progress' => 'شروع کار', 'done' => 'انجام شد', 'cancelled' => 'لغو'],
  'in_progress' => ['done' => 'انجام شد', 'cancelled' => 'لغو'],
];

$requests       = $requests       ?? [];
$itemsByRequest = $itemsByRequest ?? [];
$services       = $services       ?? [];
$staff          = $staff          ?? [];
$stats          = $stats          ?? [];

$open = array_values(array_filter($requests, fn($r) => in_array($r['status'], ['pending','accepted','in_progress'], true)));
$closed = array_values(array_filter($requests, fn($r) => in_array($r['status'], ['done','cancelled'], true)));

$money = fn($n) => number_format((float)$n) . ' ریال';
?>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <div style="display:flex;align-items:center;gap:12px;">
    <h1 style="font-size:20px;font-weight:800;color:#fff;">
      <i class="fas fa-concierge-bell" style="color:#f59e0b;margin-left:10px;"></i>خدمات مهمان
    </h1>
    <span id="openCount" style="font-size:11px;color:#475569;background:rgba(255,255,255,.05);padding:2px 10px;border-radius:10px;">
      <?= count($open) ?> درخواست باز
    </span>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <label style="font-size:11px;color:#64748b;display:flex;align-items:center;gap:6px;cursor:pointer;">
      <input type="checkbox" id="autoRefresh" checked style="accent-color:#f59e0b;"> به‌روزرسانی خودکار
    </label>
    <button onclick="openServicesModal()" class="btn-ghost text-sm px-3">
      <i class="fas fa-list text-xs ml-1"></i>کاتالوگ خدمات
    </button>
  </div>
</div>

<!-- آمار -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px;">
  <?php
  $cards = [
    ['در انتظار',        $stats['pending']     ?? 0, 'hourglass-half', '#ef4444'],
    ['در حال رسیدگی',    $stats['in_progress'] ?? 0, 'spinner',        '#3b82f6'],
    ['انجام‌شده امروز',   $stats['done_today']  ?? 0, 'check-circle',   '#22c55e'],
    ['درآمد امروز',      $money($stats['revenue'] ?? 0), 'coins',       '#f59e0b'],
    ['میانگین پاسخ',     ($stats['avg_minutes'] ?? 0) . ' دقیقه', 'stopwatch', '#8b5cf6'],
  ];
  foreach ($cards as [$label, $value, $icon, $color]): ?>
    <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:14px;">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
        <i class="fas fa-<?= $icon ?>" style="color:<?= $color ?>;font-size:12px;"></i>
        <span style="font-size:11px;color:#64748b;"><?= $label ?></span>
      </div>
      <div style="font-size:18px;font-weight:800;color:#fff;"><?= is_int($value) ? $value : htmlspecialchars((string)$value) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- فیلتر دسته -->
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;">
  <button class="cat-filter active" data-cat="all" onclick="filterCat('all',this)">همه</button>
  <?php foreach ($CAT_LABELS as $key => [$label, $icon, $color]): ?>
    <button class="cat-filter" data-cat="<?= $key ?>" onclick="filterCat('<?= $key ?>',this)">
      <i class="fas fa-<?= $icon ?>" style="color:<?= $color ?>;font-size:10px;"></i> <?= $label ?>
    </button>
  <?php endforeach; ?>
</div>

<!-- صف درخواست‌های باز -->
<h2 style="font-size:13px;color:#94a3b8;margin-bottom:10px;font-weight:700;">
  <i class="fas fa-inbox text-xs ml-1"></i>صف کاری
</h2>

<div id="queue" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:12px;margin-bottom:28px;">
  <?php if (!$open): ?>
    <div style="grid-column:1/-1;text-align:center;padding:40px;color:#475569;font-size:13px;">
      <i class="fas fa-check-circle" style="font-size:28px;display:block;margin-bottom:10px;color:#22c55e;"></i>
      هیچ درخواست بازی وجود ندارد
    </div>
  <?php endif; ?>

  <?php foreach ($open as $r):
    [$catLabel, $catIcon, $catColor] = $CAT_LABELS[$r['category']] ?? $CAT_LABELS['other'];
    [$stLabel, $stColor]             = $STATUS_LABELS[$r['status']] ?? ['نامشخص', '#64748b'];
    $items   = $itemsByRequest[(int)$r['id']] ?? [];
    $age     = (int)($r['age_minutes'] ?? 0);
    $urgent  = $r['status'] === 'pending' && $age > 15;
  ?>
  <div class="req-card" data-cat="<?= $r['category'] ?>" data-id="<?= (int)$r['id'] ?>"
       style="background:rgba(255,255,255,.03);border:1px solid <?= $urgent ? 'rgba(239,68,68,.5)' : 'rgba(255,255,255,.06)' ?>;border-radius:14px;padding:14px;">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
      <div style="display:flex;align-items:center;gap:8px;">
        <span style="width:30px;height:30px;border-radius:9px;background:<?= $catColor ?>22;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-<?= $catIcon ?>" style="color:<?= $catColor ?>;font-size:12px;"></i>
        </span>
        <div>
          <div style="font-size:13px;font-weight:700;color:#fff;">اتاق <?= htmlspecialchars((string)$r['room_number']) ?></div>
          <div style="font-size:10px;color:#64748b;"><?= $catLabel ?><?= $r['guest_name'] ? ' — ' . htmlspecialchars((string)$r['guest_name']) : '' ?></div>
        </div>
      </div>
      <span style="font-size:10px;padding:2px 8px;border-radius:8px;background:<?= $stColor ?>22;color:<?= $stColor ?>;"><?= $stLabel ?></span>
    </div>

    <?php if ($items): ?>
      <div style="background:rgba(0,0,0,.2);border-radius:9px;padding:8px;margin-bottom:8px;">
        <?php foreach ($items as $it): ?>
          <div style="display:flex;justify-content:space-between;font-size:11px;color:#cbd5e1;padding:2px 0;">
            <span><?= htmlspecialchars((string)$it['name_snapshot']) ?> × <?= (int)$it['qty'] ?></span>
            <?php if ((float)$it['line_total'] > 0): ?>
              <span style="color:#94a3b8;"><?= number_format((float)$it['line_total']) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($r['note'])): ?>
      <div style="font-size:11px;color:#94a3b8;background:rgba(255,255,255,.03);border-radius:8px;padding:7px;margin-bottom:8px;">
        <i class="fas fa-comment-dots text-xs ml-1" style="color:#64748b;"></i><?= htmlspecialchars((string)$r['note']) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($r['scheduled_at'])): ?>
      <div style="font-size:11px;color:#8b5cf6;margin-bottom:8px;">
        <i class="fas fa-clock text-xs ml-1"></i>زمان: <?= htmlspecialchars((string)$r['scheduled_at']) ?>
      </div>
    <?php endif; ?>

    <div style="display:flex;align-items:center;justify-content:space-between;font-size:10px;color:#475569;margin-bottom:10px;">
      <span><i class="fas fa-hourglass-half text-xs ml-1"></i><?= $age ?> دقیقه پیش</span>
      <?php if ((float)$r['total_price'] > 0): ?>
        <span style="color:#f59e0b;font-weight:700;"><?= $money($r['total_price']) ?></span>
      <?php endif; ?>
    </div>

    <div style="display:flex;gap:6px;flex-wrap:wrap;">
      <?php foreach ($NEXT_STATUS[$r['status']] ?? [] as $to => $btnLabel):
        $isDanger = in_array($to, ['cancelled'], true); ?>
        <button onclick="setStatus(<?= (int)$r['id'] ?>,'<?= $to ?>')"
                class="<?= $isDanger ? 'btn-ghost' : 'btn-primary' ?> text-xs"
                style="flex:1;min-width:70px;padding:6px 8px;font-size:11px;"><?= $btnLabel ?></button>
      <?php endforeach; ?>
      <select onchange="assign(<?= (int)$r['id'] ?>, this.value)"
              style="flex:1;min-width:100px;font-size:10px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:7px;color:#cbd5e1;padding:5px;">
        <option value="">— تخصیص —</option>
        <?php foreach ($staff as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= (int)($r['assigned_to'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars((string)$s['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- تاریخچه ۲۴ ساعت -->
<h2 style="font-size:13px;color:#94a3b8;margin-bottom:10px;font-weight:700;">
  <i class="fas fa-clock-rotate-left text-xs ml-1"></i>۲۴ ساعت گذشته
</h2>
<div style="background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.05);border-radius:12px;overflow:hidden;">
  <?php if (!$closed): ?>
    <div style="padding:22px;text-align:center;color:#475569;font-size:12px;">موردی ثبت نشده است</div>
  <?php else: ?>
    <?php foreach ($closed as $r):
      [$catLabel,,$catColor] = $CAT_LABELS[$r['category']] ?? $CAT_LABELS['other'];
      [$stLabel, $stColor]   = $STATUS_LABELS[$r['status']] ?? ['نامشخص', '#64748b']; ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 14px;border-bottom:1px solid rgba(255,255,255,.04);font-size:11px;">
        <div style="display:flex;align-items:center;gap:10px;">
          <span style="width:6px;height:6px;border-radius:50%;background:<?= $catColor ?>;"></span>
          <span style="color:#cbd5e1;font-weight:600;">اتاق <?= htmlspecialchars((string)$r['room_number']) ?></span>
          <span style="color:#64748b;"><?= $catLabel ?></span>
          <?php if (!empty($r['rating'])): ?>
            <span style="color:#fbbf24;"><?= str_repeat('★', (int)$r['rating']) ?></span>
          <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
          <?php if ((float)$r['total_price'] > 0): ?>
            <span style="color:#94a3b8;"><?= $money($r['total_price']) ?></span>
          <?php endif; ?>
          <span style="color:<?= $stColor ?>;"><?= $stLabel ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- مودال کاتالوگ خدمات -->
<div id="servicesModal" class="hidden" style="position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:90;display:flex;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#0f172a;border:1px solid rgba(255,255,255,.1);border-radius:16px;max-width:760px;width:100%;max-height:85vh;overflow:auto;padding:20px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:15px;font-weight:800;color:#fff;">کاتالوگ خدمات قابل سفارش</h3>
      <button onclick="closeModal('servicesModal')" class="btn-ghost text-xs px-2"><i class="fas fa-times"></i></button>
    </div>

    <form onsubmit="return addService(event)" style="display:grid;grid-template-columns:2fr 1.2fr 1fr 1fr auto;gap:7px;margin-bottom:16px;">
      <input id="svcName" placeholder="نام سرویس" required
             style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:8px;color:#fff;font-size:12px;">
      <select id="svcCat" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:8px;color:#fff;font-size:12px;">
        <option value="room_service">روم‌سرویس</option>
        <option value="breakfast">صبحانه</option>
        <option value="housekeeping">خانه‌داری</option>
        <option value="laundry">خشک‌شویی</option>
        <option value="taxi">تاکسی</option>
        <option value="maintenance">تعمیرات</option>
        <option value="other">سایر</option>
      </select>
      <input id="svcPrice" type="number" min="0" step="1000" placeholder="قیمت" value="0"
             style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:8px;color:#fff;font-size:12px;">
      <input id="svcUnit" placeholder="واحد"
             style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:8px;color:#fff;font-size:12px;">
      <button type="submit" class="btn-primary text-xs" style="padding:8px 14px;">افزودن</button>
    </form>

    <div id="svcList">
      <?php foreach ($services as $s):
        [$catLabel,,$catColor] = $CAT_LABELS[$s['category']] ?? $CAT_LABELS['other']; ?>
        <div class="svc-row" data-id="<?= (int)$s['id'] ?>"
             style="display:flex;align-items:center;justify-content:space-between;padding:9px 11px;border-bottom:1px solid rgba(255,255,255,.05);">
          <div style="display:flex;align-items:center;gap:9px;">
            <span style="width:5px;height:20px;border-radius:3px;background:<?= $catColor ?>;"></span>
            <div>
              <div style="font-size:12px;color:#fff;font-weight:600;"><?= htmlspecialchars((string)$s['name_fa']) ?></div>
              <div style="font-size:10px;color:#64748b;">
                <?= $catLabel ?>
                <?= (float)$s['price'] > 0 ? ' — ' . number_format((float)$s['price']) . ' ریال' : ' — رایگان' ?>
                <?= $s['unit'] ? ' / ' . htmlspecialchars((string)$s['unit']) : '' ?>
              </div>
            </div>
          </div>
          <div style="display:flex;gap:6px;align-items:center;">
            <button onclick="toggleService(<?= (int)$s['id'] ?>, <?= (int)$s['is_active'] ? 0 : 1 ?>)"
                    class="btn-ghost text-xs px-2" style="font-size:10px;color:<?= (int)$s['is_active'] ? '#22c55e' : '#64748b' ?>;">
              <?= (int)$s['is_active'] ? 'فعال' : 'غیرفعال' ?>
            </button>
            <button onclick="deleteService(<?= (int)$s['id'] ?>)" class="btn-ghost text-xs px-2" style="color:#ef4444;">
              <i class="fas fa-trash" style="font-size:10px;"></i>
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div id="toast" class="hidden"></div>

<style>
.cat-filter{font-size:11px;padding:5px 12px;border-radius:9px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);color:#94a3b8;cursor:pointer;transition:.15s;}
.cat-filter:hover{background:rgba(255,255,255,.08);}
.cat-filter.active{background:rgba(245,158,11,.15);border-color:rgba(245,158,11,.4);color:#fbbf24;}
.req-card{transition:.15s;}
.req-card.hide{display:none;}
</style>

<script>
// ══ فیلتر دسته ══════════════════════════════════════════════════════
function filterCat(cat, btn) {
  document.querySelectorAll('.cat-filter').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.req-card').forEach(c => {
    c.classList.toggle('hide', cat !== 'all' && c.dataset.cat !== cat);
  });
}

// ══ تغییر وضعیت ════════════════════════════════════════════════════
async function setStatus(id, status) {
  try {
    await apiFetch(`/api/v1/guest/requests/${id}/status`, 'PUT', { status });
    showToast('وضعیت به‌روزرسانی شد');
    setTimeout(() => location.reload(), 500);
  } catch (e) { showToast(e.message, 'error'); }
}

async function assign(id, userId) {
  if (!userId) return;
  try {
    const card = document.querySelector(`.req-card[data-id="${id}"]`);
    await apiFetch(`/api/v1/guest/requests/${id}/status`, 'PUT', {
      status: 'accepted', assigned_to: Number(userId)
    });
    showToast('درخواست تخصیص داده شد');
    setTimeout(() => location.reload(), 500);
  } catch (e) { showToast(e.message, 'error'); }
}

// ══ کاتالوگ خدمات ═══════════════════════════════════════════════════
function openServicesModal() { document.getElementById('servicesModal').classList.remove('hidden'); }

async function addService(ev) {
  ev.preventDefault();
  try {
    await apiFetch('/api/v1/guest/services', 'POST', {
      name_fa:  document.getElementById('svcName').value.trim(),
      category: document.getElementById('svcCat').value,
      price:    Number(document.getElementById('svcPrice').value || 0),
      unit:     document.getElementById('svcUnit').value.trim(),
    });
    showToast('سرویس اضافه شد');
    setTimeout(() => location.reload(), 500);
  } catch (e) { showToast(e.message, 'error'); }
  return false;
}

async function toggleService(id, isActive) {
  try {
    await apiFetch(`/api/v1/guest/services/${id}`, 'PUT', { is_active: isActive });
    setTimeout(() => location.reload(), 300);
  } catch (e) { showToast(e.message, 'error'); }
}

async function deleteService(id) {
  if (!confirm('این سرویس حذف شود؟')) return;
  try {
    await apiFetch(`/api/v1/guest/services/${id}`, 'DELETE');
    showToast('حذف شد');
    setTimeout(() => location.reload(), 400);
  } catch (e) { showToast(e.message, 'error'); }
}

// ══ به‌روزرسانی خودکار صف ═══════════════════════════════════════════
let lastPending = <?= (int)($stats['pending'] ?? 0) ?>;
setInterval(async () => {
  if (!document.getElementById('autoRefresh').checked) return;
  if (!document.getElementById('servicesModal').classList.contains('hidden')) return;
  try {
    const r = await fetch('/admin/guest-services/feed', { credentials: 'same-origin' });
    const d = await r.json();
    if (!d.success) return;
    document.getElementById('openCount').textContent = `${d.data.length} درخواست باز`;
    if (d.pending > lastPending) {
      showToast('درخواست جدید رسید');
      location.reload();
    }
    lastPending = d.pending;
  } catch (_) {}
}, 20000);

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
  t.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${msg}`;
  t.classList.remove('hidden');
  setTimeout(() => t.classList.add('hidden'), 3500);
}
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
