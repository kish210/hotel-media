<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<!-- Stats bar -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
<?php
$statItems = [
  ['label'=>'پروازعزیمت',  'val'=>$stats['dep']??0,        'color'=>'sky',    'icon'=>'plane-departure'],
  ['label'=>'پرواز ورود',   'val'=>$stats['arr']??0,        'color'=>'blue',   'icon'=>'plane-arrival'],
  ['label'=>'در تأخیر',     'val'=>$stats['delayed_count']??0,    'color'=>'yellow', 'icon'=>'clock'],
  ['label'=>'سوارشوید',     'val'=>$stats['boarding_count']??0,   'color'=>'green',  'icon'=>'door-open'],
  ['label'=>'لغو شده',      'val'=>$stats['cancelled_count']??0,  'color'=>'red',    'icon'=>'ban'],
];
foreach ($statItems as $s): ?>
<div class="card p-4 text-center border border-<?=$s['color']?>-500/20 bg-<?=$s['color']?>-500/5">
  <i class="fas fa-<?=$s['icon']?> text-<?=$s['color']?>-400 text-xl mb-2 block"></i>
  <div class="text-2xl font-bold text-white"><?=$s['val']?></div>
  <div class="text-xs text-slate-500 mt-0.5"><?=$s['label']?></div>
</div>
<?php endforeach; ?>
</div>

<div class="flex items-center justify-between mb-5">
  <h1 class="text-xl font-bold text-white flex items-center gap-2">
    <i class="fas fa-plane text-sky-400"></i> مدیریت FIDS پروازها
  </h1>
  <div class="flex gap-2 items-center">
    <!-- Sync error message (inline) -->
    <span id="syncMsg" style="display:none;font-size:12px;max-width:320px;"></span>
    <!-- Refresh button -->
    <button id="syncBtn" onclick="syncNow()"
      style="display:flex;align-items:center;gap:7px;padding:8px 16px;background:rgba(56,189,248,.1);border:1px solid rgba(56,189,248,.25);color:#38bdf8;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;font-family:Vazirmatn,sans-serif;">
      <i class="fas fa-rotate" id="syncIcon"></i> بروزرسانی
    </button>
    <a href="/admin/modules/fids/manage" class="btn-ghost text-sm px-4">
      <i class="fas fa-sliders text-xs ml-1"></i> تنظیمات
    </a>
    <a href="/admin/modules" class="btn-ghost text-sm px-4">
      <i class="fas fa-puzzle-piece text-xs ml-1"></i> ماژول‌ها
    </a>
  </div>
</div>

<!-- Sync error banner (detailed, hidden by default) -->
<div id="syncErrorBanner" style="display:none;background:rgba(248,113,113,.07);border:1px solid rgba(248,113,113,.25);border-radius:14px;padding:12px 16px;margin-bottom:16px;">
  <div style="display:flex;align-items:flex-start;gap:10px;">
    <i class="fas fa-exclamation-circle" style="color:#f87171;font-size:16px;margin-top:2px;flex-shrink:0;"></i>
    <div style="flex:1;">
      <div id="syncErrorTitle" style="font-size:13px;font-weight:700;color:#f87171;margin-bottom:4px;"></div>
      <div id="syncErrorHint"  style="font-size:12px;color:#94a3b8;line-height:1.7;display:none;"></div>
    </div>
    <button onclick="document.getElementById('syncErrorBanner').style.display='none'"
      style="background:none;border:none;color:#475569;cursor:pointer;font-size:14px;flex-shrink:0;">
      <i class="fas fa-xmark"></i>
    </button>
  </div>
</div>

<!-- Flights table -->
<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-white/5 text-xs text-slate-500 uppercase">
          <th class="text-right p-3 pl-0">شماره پرواز</th>
          <th class="text-right p-3">ایرلاین</th>
          <th class="text-right p-3">مسیر</th>
          <th class="text-right p-3">نوع</th>
          <th class="text-right p-3">زمان</th>
          <th class="text-right p-3">دروازه</th>
          <th class="text-right p-3">وضعیت</th>
          <th class="p-3"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($flights)): ?>
        <tr><td colspan="8" class="text-center py-12 text-slate-600">
          <i class="fas fa-plane text-4xl mb-3 block opacity-20"></i>
          پروازی برای امروز ثبت نشده
        </td></tr>
        <?php else: foreach ($flights as $f):
          $statusColors = [
            'scheduled' => 'badge-online',
            'boarding'  => 'badge-online',
            'delayed'   => 'badge-pending',
            'cancelled' => 'badge-offline',
            'departed'  => 'text-slate-500 bg-slate-500/10 border-slate-500/20',
            'arrived'   => 'text-slate-500 bg-slate-500/10 border-slate-500/20',
          ];
          $cls = $statusColors[$f['status']] ?? 'badge-online';
        ?>
        <tr class="table-row border-b border-white/3 hover:bg-white/2">
          <td class="p-3 font-mono font-bold text-white"><?=e($f['flight_number'])?></td>
          <td class="p-3">
            <div class="font-medium text-white"><?=e($f['airline_name'])?></div>
            <div class="text-xs text-slate-600 font-mono"><?=e($f['airline_code'])?></div>
          </td>
          <td class="p-3">
            <?php if ($f['type'] === 'departure'): ?>
            <span class="text-slate-400">→</span> <span class="text-white"><?=e($f['destination']??'—')?></span>
            <span class="text-slate-600 text-xs font-mono ml-1"><?=$f['destination_code']??''?></span>
            <?php else: ?>
            <span class="text-slate-400">←</span> <span class="text-white"><?=e($f['origin']??'—')?></span>
            <span class="text-slate-600 text-xs font-mono ml-1"><?=$f['origin_code']??''?></span>
            <?php endif; ?>
          </td>
          <td class="p-3">
            <span class="text-xs px-2 py-1 rounded-full <?= $f['type']==='departure' ? 'bg-sky-500/15 text-sky-400 border border-sky-500/30' : 'bg-blue-500/15 text-blue-400 border border-blue-500/30' ?>">
              <?= $f['type']==='departure' ? 'عزیمت' : 'ورود' ?>
            </span>
          </td>
          <td class="p-3">
            <div class="font-mono font-bold text-white"><?=date('H:i',strtotime($f['scheduled_time']))?></div>
            <?php if ($f['estimated_time'] && $f['estimated_time'] !== $f['scheduled_time']): ?>
            <div class="text-xs text-yellow-400 font-mono">→ <?=date('H:i',strtotime($f['estimated_time']))?></div>
            <?php endif; ?>
            <?php if ($f['delay_minutes'] > 0): ?>
            <div class="text-xs text-red-400">+<?=$f['delay_minutes']?>دق</div>
            <?php endif; ?>
          </td>
          <td class="p-3 font-mono font-bold text-sky-400 text-lg"><?=e($f['gate']??'—')?></td>
          <td class="p-3">
            <span class="<?=$cls?> px-2 py-1 rounded-full text-xs border"><?=e($f['status_fa']??$f['status'])?></span>
          </td>
          <td class="p-3">
            <button onclick="openStatusModal(<?=htmlspecialchars(json_encode(['id'=>$f['id'],'status'=>$f['status'],'gate'=>$f['gate']??'','delay'=>$f['delay_minutes']??0]))?>,this)"
              class="btn-ghost text-xs px-2 py-1.5"><i class="fas fa-edit text-yellow-400"></i></button>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="modal-overlay hidden">
  <div class="modal max-w-sm">
    <h3 class="font-bold text-white mb-4"><i class="fas fa-edit text-yellow-400 ml-2"></i> به‌روزرسانی وضعیت</h3>
    <form id="statusForm" method="POST" class="space-y-3">
      <?= csrf_field() ?>
      <div><label class="form-label">وضعیت</label>
        <select name="status" id="statusSelect" class="form-input">
          <?php foreach (['scheduled'=>'زمان‌بندی','boarding'=>'سوارشوید','departed'=>'پرواز کرد','arrived'=>'فرود آمد','delayed'=>'تأخیر','cancelled'=>'لغو','gate_change'=>'تغییر دروازه'] as $v=>$l): ?>
          <option value="<?=$v?>"><?=$l?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label class="form-label">دروازه جدید</label><input type="text" name="gate" id="gateInput" class="form-input" style="text-transform:uppercase"></div>
      <div><label class="form-label">تأخیر (دقیقه)</label><input type="number" name="delay_minutes" id="delayInput" class="form-input" min="0" value="0"></div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn-primary flex-1 py-2.5">ذخیره</button>
        <button type="button" onclick="document.getElementById('statusModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
const TOKEN = localStorage.getItem('signage_token') || localStorage.getItem('auth_token') || '';
const AUTH  = { 'Authorization': 'Bearer ' + TOKEN, 'Content-Type': 'application/json' };

// ── Sync / Refresh button ─────────────────────────────────────────────────
async function syncNow() {
  const btn    = document.getElementById('syncBtn');
  const icon   = document.getElementById('syncIcon');
  const banner = document.getElementById('syncErrorBanner');
  const title  = document.getElementById('syncErrorTitle');
  const hint   = document.getElementById('syncErrorHint');
  const msg    = document.getElementById('syncMsg');

  btn.disabled = true;
  icon.className = 'fas fa-circle-notch fa-spin';
  if (banner) banner.style.display = 'none';
  if (msg)    { msg.style.display = 'none'; }

  try {
    const r = await fetch('/api/v1/fids/sync-live', {
      method: 'POST', headers: AUTH, body: JSON.stringify({})
    });
    const d = await r.json();

    if (d.success) {
      const info = d.data || {};
      if (msg) {
        msg.style.display = 'inline';
        msg.style.color   = '#4ade80';
        msg.textContent   = '✓ ' + (info.saved ?? 0) + ' پرواز دریافت شد';
      }
      showToast('success', d.message || (info.saved + ' پرواز ذخیره شد'));
      // reload table after brief delay
      setTimeout(() => location.reload(), 1000);
    } else {
      // Show error banner
      if (banner) {
        banner.style.display = 'flex';
        title.textContent    = d.message || 'خطا در دریافت پروازها';
        if (d.hint) {
          hint.style.display  = 'block';
          hint.textContent    = d.hint;
        } else {
          hint.style.display  = 'none';
        }
      }
      if (msg) {
        msg.style.display = 'inline';
        msg.style.color   = '#f87171';
        msg.textContent   = '✗ ' + (d.message || 'خطا');
      }
      showToast('error', d.message || 'خطا در دریافت');
    }
  } catch(e) {
    if (banner) {
      banner.style.display = 'flex';
      title.textContent    = 'خطا در اتصال به سرور';
      hint.style.display   = 'none';
    }
    if (msg) {
      msg.style.display = 'inline';
      msg.style.color   = '#f87171';
      msg.textContent   = '✗ خطا در اتصال';
    }
    showToast('error', 'خطا در اتصال به سرور');
  }

  btn.disabled   = false;
  icon.className = 'fas fa-rotate';
  setTimeout(() => { if (msg) msg.style.display = 'none'; }, 6000);
}

// ── Status modal ──────────────────────────────────────────────────────────
function openStatusModal(data) {
  document.getElementById('statusForm').action  = '/admin/modules/fids/flights/' + data.id + '/status';
  document.getElementById('statusSelect').value = data.status;
  document.getElementById('gateInput').value    = data.gate  || '';
  document.getElementById('delayInput').value   = data.delay || 0;
  document.getElementById('statusModal').classList.remove('hidden');
}

// ── Live refresh every 30s ────────────────────────────────────────────────
setInterval(() => { if (document.hidden) return; location.reload(); }, 30000);
JS;
?>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
