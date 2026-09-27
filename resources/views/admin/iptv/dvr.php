<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-record-vinyl" style="color:#ef4444;margin-left:10px;"></i>ضبط و Catch-up
  </h1>
  <div style="display:flex;gap:8px;">
    <button onclick="syncDvr()" class="btn-ghost text-sm px-3">
      <i class="fas fa-rotate text-xs ml-1"></i>هماهنگی با هدِند
    </button>
    <button onclick="openM('recModal')" class="btn-primary text-sm">
      <i class="fas fa-plus text-xs ml-1"></i>ضبط جدید
    </button>
  </div>
</div>

<?php if (!$tvhReady): ?>
<div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-radius:10px;padding:14px;margin-bottom:18px;font-size:13px;color:#fbbf24;line-height:1.9;">
  <b>هنوز منبع TVHeadend فعالی تنظیم نشده.</b><br>
  ضبط و Catch-up روی زیرساخت TVHeadend کار می‌کنند. ابتدا از
  <a href="/admin/iptv/tvheadend" style="color:#60a5fa;">هدِند (TVHeadend)</a> یک سرور فعال اضافه کنید.
</div>
<?php endif; ?>

<!-- ══ Catch-up روی کانال‌ها ══ -->
<div class="card" style="margin-bottom:22px;">
  <div style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.06);">
    <h3 style="font-size:14px;font-weight:700;color:#fff;">
      <i class="fas fa-clock-rotate-left ml-2" style="color:#a855f7;"></i>بازگشت به عقب (Catch-up) روی کانال‌ها
    </h3>
    <p style="font-size:11px;color:#64748b;margin-top:5px;line-height:1.8;">
      روشن‌کردن یعنی همه‌ی برنامه‌های کانال ضبط می‌شوند تا مهمان بتواند برنامه‌ی گذشته را ببیند.
      فضای دیسک مصرف می‌کند — پنجره را کوتاه بگذارید.
      کانال باید به TVHeadend نگاشت شده باشد (<code>tvh_uuid</code>).
    </p>
  </div>
  <div style="padding:8px 16px 14px;">
    <?php if (empty($channels)): ?>
      <p style="font-size:12px;color:#475569;padding:14px 0;">کانالی تعریف نشده.
        <a href="/admin/iptv" style="color:#60a5fa;">افزودن کانال ←</a></p>
    <?php else: foreach ($channels as $c): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.04);flex-wrap:wrap;">
        <div style="min-width:180px;">
          <div style="font-size:13px;color:#fff;font-weight:600;"><?= e((string)$c['name']) ?></div>
          <div style="font-size:10px;color:#64748b;font-family:monospace;">
            <?= $c['tvh_uuid'] ? e((string)$c['tvh_uuid']) : '<span style="color:#f59e0b;">به هدِند نگاشت نشده</span>' ?>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
          <input type="number" min="1" max="720" value="<?= (int)($c['catchup_window_hours'] ?: 24) ?>"
            id="cu-h-<?= (int)$c['id'] ?>" title="ساعت"
            style="width:74px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:6px;color:#fff;font-size:12px;">
          <span style="font-size:11px;color:#64748b;">ساعت</span>
          <button onclick="toggleCatchup(<?= (int)$c['id'] ?>, <?= !empty($c['catchup_enabled']) ? 'false' : 'true' ?>)"
            class="<?= !empty($c['catchup_enabled']) ? 'btn-ghost' : 'btn-primary' ?> text-xs" style="padding:6px 12px;">
            <?= !empty($c['catchup_enabled']) ? 'خاموش کن' : 'روشن کن' ?>
          </button>
          <?php if (!empty($c['catchup_enabled'])): ?>
            <span style="font-size:10px;color:#22c55e;"><i class="fas fa-circle" style="font-size:7px;"></i> فعال</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- ══ سهمیه‌ی ضبط شخصی مهمان (NPVR) ══ -->
<div class="card" style="margin-bottom:22px;">
  <div style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.06);">
    <h3 style="font-size:14px;font-weight:700;color:#fff;">
      <i class="fas fa-user-clock ml-2" style="color:#0ea5e9;"></i>سهمیه‌ی ضبط شخصی مهمان (NPVR)
    </h3>
    <p style="font-size:11px;color:#64748b;margin-top:5px;">
      سقف تعداد و مجموع دقیقه برای هر اتاق. پیش‌فرض:
      <?= (int)\App\Services\DvrService::DEFAULT_MAX_RECORDINGS ?> ضبط و
      <?= (int)\App\Services\DvrService::DEFAULT_MAX_MINUTES ?> دقیقه.
    </p>
  </div>
  <div style="padding:14px 16px;">
    <?php if (empty($rooms)): ?>
      <p style="font-size:12px;color:#475569;">اتاقی تعریف نشده.</p>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;align-items:end;max-width:640px;">
      <div>
        <label class="form-label">اتاق</label>
        <select id="q-room" class="form-input">
          <?php foreach ($rooms as $r): ?>
            <option value="<?= (int)$r['id'] ?>"><?= e((string)($r['room_number'] ?: $r['room_name'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label class="form-label">سقف تعداد</label>
        <input id="q-rec" type="number" min="0" class="form-input" value="<?= (int)\App\Services\DvrService::DEFAULT_MAX_RECORDINGS ?>"></div>
      <div><label class="form-label">سقف دقیقه</label>
        <input id="q-min" type="number" min="0" class="form-input" value="<?= (int)\App\Services\DvrService::DEFAULT_MAX_MINUTES ?>"></div>
      <button onclick="saveQuota()" class="btn-primary text-sm" style="padding:9px 16px;">ذخیره</button>
    </div>
    <div id="q-info" style="font-size:11px;color:#64748b;margin-top:10px;"></div>
    <?php endif; ?>
  </div>
</div>

<!-- ══ فهرست ضبط‌ها ══ -->
<div class="card">
  <div style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
    <h3 style="font-size:14px;font-weight:700;color:#fff;">
      <i class="fas fa-film ml-2" style="color:#22c55e;"></i>ضبط‌ها
      <span style="font-size:11px;color:#64748b;font-weight:400;">(<?= count($recordings) ?>)</span>
    </h3>
  </div>
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
      <thead>
        <tr style="color:#64748b;text-align:right;">
          <th style="padding:9px 12px;font-weight:600;">عنوان</th>
          <th style="padding:9px 12px;font-weight:600;">کانال</th>
          <th style="padding:9px 12px;font-weight:600;">نوع</th>
          <th style="padding:9px 12px;font-weight:600;">اتاق</th>
          <th style="padding:9px 12px;font-weight:600;">زمان</th>
          <th style="padding:9px 12px;font-weight:600;">وضعیت</th>
          <th style="padding:9px 12px;font-weight:600;"></th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($recordings)): ?>
        <tr><td colspan="7" style="padding:26px;text-align:center;color:#475569;">هنوز ضبطی ثبت نشده</td></tr>
      <?php else: foreach ($recordings as $d):
        $kindLabel = ['pvr'=>'زمان‌بندی‌شده','npvr'=>'مهمان','catchup'=>'Catch-up'][$d['kind']] ?? $d['kind'];
        $stColor   = ['finished'=>'#22c55e','recording'=>'#f59e0b','scheduled'=>'#60a5fa','failed'=>'#ef4444','removed'=>'#64748b'][$d['status']] ?? '#94a3b8';
        $stLabel   = ['finished'=>'تمام شد','recording'=>'در حال ضبط','scheduled'=>'زمان‌بندی‌شده','failed'=>'ناموفق','removed'=>'حذف‌شده'][$d['status']] ?? $d['status'];
      ?>
        <tr style="border-top:1px solid rgba(255,255,255,.04);">
          <td style="padding:9px 12px;color:#fff;"><?= e((string)$d['title']) ?></td>
          <td style="padding:9px 12px;color:#94a3b8;"><?= e((string)($d['channel_name'] ?? '—')) ?></td>
          <td style="padding:9px 12px;color:#94a3b8;"><?= e($kindLabel) ?></td>
          <td style="padding:9px 12px;color:#94a3b8;"><?= e((string)($d['room_number'] ?? '—')) ?></td>
          <td style="padding:9px 12px;color:#94a3b8;direction:ltr;font-family:monospace;font-size:11px;">
            <?= e(substr((string)$d['starts_at'], 0, 16)) ?>
          </td>
          <td style="padding:9px 12px;"><span style="color:<?= $stColor ?>;"><?= e($stLabel) ?></span></td>
          <td style="padding:9px 12px;white-space:nowrap;">
            <?php if (!empty($d['file_url'])): ?>
              <a href="<?= e((string)$d['file_url']) ?>" target="_blank" class="btn-ghost text-xs" style="padding:4px 9px;">پخش</a>
            <?php endif; ?>
            <?php if ($d['status'] !== 'removed'): ?>
              <button onclick="delRec(<?= (int)$d['id'] ?>)" class="btn-ghost text-xs" style="padding:4px 9px;color:#f87171;">حذف</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ══ مودال ضبط جدید ══ -->
<div id="recModal" class="modal-overlay hidden">
  <div class="modal" style="max-width:520px;">
    <div class="modal-head">
      <h3>ضبط جدید</h3>
      <button onclick="closeM('recModal')" class="btn-ghost text-xs px-2"><i class="fas fa-times"></i></button>
    </div>
    <div style="display:grid;gap:10px;">
      <div><label class="form-label">کانال</label>
        <select id="r-ch" class="form-input">
          <?php foreach ($channels as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e((string)$c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label class="form-label">عنوان</label>
        <input id="r-title" class="form-input" placeholder="مثلا مسابقه فوتبال"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div><label class="form-label">شروع</label>
          <input id="r-start" type="datetime-local" class="form-input"></div>
        <div><label class="form-label">پایان</label>
          <input id="r-stop" type="datetime-local" class="form-input"></div>
      </div>
      <button onclick="addRec()" class="btn-primary" style="padding:11px;">ثبت ضبط</button>
    </div>
  </div>
</div>

<div id="toast" class="toast hidden"></div>

<script>
function openM(i){ document.getElementById(i).classList.remove('hidden'); }
function closeM(i){ document.getElementById(i).classList.add('hidden'); }

async function api(url, method = 'GET', body = null) {
  const opts = { method, headers: {'Content-Type':'application/json','Accept':'application/json'}, credentials:'same-origin' };
  if (body) opts.body = JSON.stringify(body);
  const r = await fetch(url, opts);
  let d = {}; try { d = await r.json(); } catch(_) {}
  if (!r.ok) throw new Error(d.message || ('خطا (' + r.status + ')'));
  return d;
}
function toast(m, t='success'){
  const el = document.getElementById('toast');
  el.className = 'toast toast-' + t;
  el.textContent = m;
  el.classList.remove('hidden');
  setTimeout(()=>el.classList.add('hidden'), 3500);
}
function reload(){ setTimeout(()=>location.reload(), 600); }

async function toggleCatchup(id, enable) {
  const h = Number(document.getElementById('cu-h-' + id).value) || 24;
  try {
    const d = await api('/api/v1/dvr/catchup', 'POST', { channel_id: id, enabled: enable, hours: h });
    toast(d.message || 'ذخیره شد'); reload();
  } catch(e){ toast(e.message, 'error'); }
}

async function saveQuota() {
  const room = Number(document.getElementById('q-room').value);
  try {
    await api('/api/v1/dvr/quota/' + room, 'POST', {
      max_recordings: Number(document.getElementById('q-rec').value) || 0,
      max_minutes:    Number(document.getElementById('q-min').value) || 0
    });
    toast('سهمیه ذخیره شد');
    showQuota();
  } catch(e){ toast(e.message, 'error'); }
}

async function showQuota() {
  const sel = document.getElementById('q-room');
  if (!sel) return;
  try {
    const d = await api('/api/v1/dvr/quota/' + Number(sel.value));
    const q = d.data || {};
    document.getElementById('q-info').textContent =
      'مصرف فعلی: ' + (q.used_recordings||0) + ' ضبط از ' + (q.max_recordings||0) +
      ' · ' + (q.used_minutes||0) + ' دقیقه از ' + (q.max_minutes||0);
    document.getElementById('q-rec').value = q.max_recordings || 0;
    document.getElementById('q-min').value = q.max_minutes || 0;
  } catch(e){}
}

async function addRec() {
  const s = document.getElementById('r-start').value;
  const e2 = document.getElementById('r-stop').value;
  if (!s || !e2) { toast('شروع و پایان لازم است', 'error'); return; }
  try {
    await api('/api/v1/dvr', 'POST', {
      channel_id: Number(document.getElementById('r-ch').value),
      title:      document.getElementById('r-title').value,
      start:      Math.floor(new Date(s).getTime()/1000),
      stop:       Math.floor(new Date(e2).getTime()/1000)
    });
    toast('ضبط ثبت شد'); reload();
  } catch(err){ toast(err.message, 'error'); }
}

async function delRec(id) {
  if (!confirm('این ضبط حذف شود؟')) return;
  try { await api('/api/v1/dvr/' + id, 'DELETE'); toast('حذف شد'); reload(); }
  catch(e){ toast(e.message, 'error'); }
}

async function syncDvr() {
  try { const d = await api('/api/v1/dvr/sync', 'POST'); toast(d.message || 'هماهنگ شد'); reload(); }
  catch(e){ toast(e.message, 'error'); }
}

document.addEventListener('DOMContentLoaded', () => {
  const sel = document.getElementById('q-room');
  if (sel) { sel.addEventListener('change', showQuota); showQuota(); }
});
</script>
