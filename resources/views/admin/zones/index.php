<?php $title = $title ?? 'محل‌ها و Zone'; ?>
<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<style>
.zgrid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:14px; }
.zcard { background:#16161f; border:1px solid rgba(255,255,255,.07); border-radius:14px; padding:15px; }
.zcard:hover { border-color:rgba(56,189,248,.35); }
.zhead { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
.zname { font-size:14px; font-weight:700; color:#e2e8f0; }
.zkind { font-size:11px; color:#64748b; margin-top:2px; }
.zstat { display:flex; gap:14px; margin-top:12px; padding-top:12px; border-top:1px solid rgba(255,255,255,.06); }
.zstat > div { font-size:11px; color:#64748b; }
.zstat b { display:block; font-size:17px; color:#e2e8f0; font-weight:700; }
.zbtns { display:flex; gap:6px; margin-top:12px; flex-wrap:wrap; }
.zbtn  { padding:5px 10px; border-radius:7px; font-size:11px; cursor:pointer; font-family:inherit;
         background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1); color:#cbd5e1; }
.zbtn:hover { background:rgba(255,255,255,.09); }
.zbtn-p { background:rgba(56,189,248,.12); border-color:rgba(56,189,248,.3); color:#7dd3fc; }
.zbtn-d { background:rgba(248,113,113,.1); border-color:rgba(248,113,113,.25); color:#fca5a5; }
.zpill { font-size:10px; padding:2px 7px; border-radius:20px; background:rgba(255,255,255,.06); color:#94a3b8; }
.zempty { text-align:center; padding:50px 20px; color:#475569; }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
  <div>
    <h2 style="font-size:17px;font-weight:700;color:#fff;margin:0;">محل‌ها و Zone</h2>
    <p style="font-size:12px;color:#64748b;margin:4px 0 0;">
      هر محل در هتل یک Zone است. پلی‌لیست را می‌شود یک‌جا روی کل محل منتشر کرد،
      نه تک‌تک صفحه‌ها.
    </p>
  </div>
  <button class="zbtn zbtn-p" style="padding:8px 14px;font-size:12px;" onclick="openZone()">
    <i class="fas fa-plus"></i> محل جدید
  </button>
</div>

<?php if (!empty($unassigned)): ?>
<?php /* صفحه‌ی بی‌محل در عمل از انتشار روی Zone جا می‌ماند و کسی
         نمی‌فهمد چرا — پس همان‌جا که Zone مدیریت می‌شود هشدار می‌دهیم. */ ?>
<div style="background:rgba(251,191,36,.07);border:1px solid rgba(251,191,36,.22);border-radius:12px;padding:12px 14px;margin-bottom:16px;">
  <div style="font-size:12px;color:#fbbf24;font-weight:600;margin-bottom:7px;">
    <i class="fas fa-circle-exclamation"></i>
    <?= count($unassigned) ?> صفحه هنوز به هیچ محلی وصل نیست
  </div>
  <div style="display:flex;gap:6px;flex-wrap:wrap;">
    <?php foreach ($unassigned as $u): ?>
    <a href="/admin/screens/<?= (int)$u['id'] ?>" class="zpill" style="text-decoration:none;">
      <?= e($u['name']) ?>
    </a>
    <?php endforeach; ?>
  </div>
  <div style="font-size:11px;color:#64748b;margin-top:8px;">
    انتشار روی Zone به این صفحه‌ها نمی‌رسد تا محلشان تعیین شود.
  </div>
</div>
<?php endif; ?>

<?php if (empty($zones)): ?>
<div class="zempty">
  <i class="fas fa-location-dot" style="font-size:34px;opacity:.3;"></i>
  <div style="margin-top:12px;font-size:13px;">هنوز محلی ثبت نشده است</div>
  <div style="font-size:12px;margin-top:6px;">لابی، رستوران، استخر، آسانسور… هر جایی که صفحه دارد.</div>
</div>
<?php else: ?>
<div class="zgrid">
  <?php foreach ($zones as $z): ?>
  <div class="zcard">
    <div class="zhead">
      <div>
        <div class="zname"><?= e($z['name']) ?></div>
        <div class="zkind">
          <?= e($kinds[$z["kind"]] ?? $z["kind"]) ?>
          <?= $z['floor'] ? ' · طبقهٔ ' . e($z['floor']) : '' ?>
        </div>
      </div>
      <?php if (!$z['is_active']): ?><span class="zpill">غیرفعال</span><?php endif; ?>
    </div>

    <div class="zstat">
      <div><b><?= (int)$z['screen_count'] ?></b> صفحه</div>
      <div><b style="color:<?= (int)$z['online_count'] ? '#4ade80' : '#64748b' ?>;"><?= (int)$z['online_count'] ?></b> روشن</div>
      <div><b><?= (int)$z['schedule_count'] ?></b> برنامه</div>
    </div>

    <div class="zbtns">
      <button class="zbtn zbtn-p" onclick="publishTo(<?= (int)$z['id'] ?>, '<?= e(addslashes($z['name'])) ?>')">
        <i class="fas fa-paper-plane"></i> انتشار پلی‌لیست
      </button>
      <button class="zbtn" onclick='openZone(<?= json_encode($z, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
        <i class="fas fa-pen"></i>
      </button>
      <button class="zbtn zbtn-d" onclick="delZone(<?= (int)$z['id'] ?>, <?= (int)$z['screen_count'] ?>)">
        <i class="fas fa-trash"></i>
      </button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── ساخت/ویرایش محل ──────────────────────────────────────────── -->
<div class="modal-overlay hidden" id="zoneModal">
  <div class="modal" style="max-width:520px;">
    <div class="modal-head">
      <h3 id="zoneModalTitle">محل جدید</h3>
      <button class="btn-ghost text-xs px-2" onclick="closeZone()"><i class="fas fa-times"></i></button>
    </div>
    <div style="display:grid;gap:10px;">
      <input type="hidden" id="z_id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div>
          <label class="form-label">نام محل *</label>
          <input class="form-input" id="z_name" placeholder="لابی اصلی">
        </div>
        <div>
          <label class="form-label">نوع</label>
          <select class="form-input" id="z_kind">
            <?php foreach ($kinds as $k => $fa): ?>
            <option value="<?= $k ?>"><?= $fa ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div>
          <label class="form-label">طبقه</label>
          <input class="form-input" id="z_floor" placeholder="۱">
        </div>
        <div>
          <label class="form-label">ترتیب نمایش</label>
          <input class="form-input" id="z_sort" type="number" value="0">
        </div>
      </div>
      <div>
        <label class="form-label">توضیح</label>
        <input class="form-input" id="z_desc" placeholder="اختیاری">
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;">
      <button class="btn-ghost text-sm" onclick="closeZone()">انصراف</button>
      <button class="btn-primary text-sm" id="z_save" onclick="saveZone()">ذخیره</button>
    </div>
  </div>
</div>

<!-- ── انتشار پلی‌لیست روی محل ───────────────────────────────────── -->
<div class="modal-overlay hidden" id="pubModal">
  <div class="modal" style="max-width:480px;">
    <div class="modal-head">
      <h3>انتشار روی <span id="pubZoneName"></span></h3>
      <button class="btn-ghost text-xs px-2" onclick="closePub()"><i class="fas fa-times"></i></button>
    </div>
    <div style="display:grid;gap:10px;">
      <input type="hidden" id="p_zone">
      <div>
        <label class="form-label">پلی‌لیست *</label>
        <select class="form-input" id="p_playlist">
          <option value="">— انتخاب کنید —</option>
          <?php foreach ($playlists as $pl): ?>
          <option value="<?= (int)$pl['id'] ?>"><?= e($pl['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px;">
        <div>
          <label class="form-label">از ساعت</label>
          <input class="form-input" id="p_from" type="time">
        </div>
        <div>
          <label class="form-label">تا ساعت</label>
          <input class="form-input" id="p_to" type="time">
        </div>
      </div>
      <p style="font-size:11px;color:#64748b;margin-top:12px;line-height:1.7;">
        ساعت را خالی بگذارید تا تمام شبانه‌روز پخش شود.
        این برنامه روی همهٔ صفحه‌های این محل اعمال می‌شود، ولی پلی‌لیستی که
        روی یک صفحهٔ خاص دستی انتخاب شده را کنار نمی‌زند.
      </p>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;">
      <button class="btn-ghost text-sm" onclick="closePub()">انصراف</button>
      <button class="btn-primary text-sm" id="p_save" onclick="savePub()">انتشار</button>
    </div>
  </div>
</div>

<script>
function hdr() {
  return { 'Content-Type':'application/json',
           'X-CSRF-Token': document.querySelector('meta[name=csrf-token]')?.content || '' };
}
function jfetch(url, method, body) {
  return fetch(url, { method, headers: hdr(), body: body ? JSON.stringify(body) : null })
    .then(r => r.json()).catch(() => ({ success:false, message:'ارتباط با سرور برقرار نشد' }));
}

// ── محل ────────────────────────────────────────────────────────────
function openZone(z) {
  document.getElementById('zoneModalTitle').textContent = z ? 'ویرایش محل' : 'محل جدید';
  document.getElementById('z_id').value    = z ? z.id : '';
  document.getElementById('z_name').value  = z ? (z.name || '') : '';
  document.getElementById('z_kind').value  = z ? (z.kind || 'other') : 'lobby';
  document.getElementById('z_floor').value = z ? (z.floor || '') : '';
  document.getElementById('z_sort').value  = z ? (z.sort_order || 0) : 0;
  document.getElementById('z_desc').value  = z ? (z.description || '') : '';
  document.getElementById('zoneModal').classList.remove("hidden");
}
function closeZone() { document.getElementById('zoneModal').classList.add("hidden"); }

function saveZone() {
  const id   = document.getElementById('z_id').value;
  const name = document.getElementById('z_name').value.trim();
  if (!name) { alert('نام محل الزامی است'); return; }

  const body = {
    name:        name,
    kind:        document.getElementById('z_kind').value,
    floor:       document.getElementById('z_floor').value.trim(),
    sort_order:  parseInt(document.getElementById('z_sort').value || '0', 10),
    description: document.getElementById('z_desc').value.trim(),
  };

  const btn = document.getElementById('z_save');
  btn.disabled = true;
  jfetch(id ? '/api/v1/venues/' + id : '/api/v1/venues', id ? 'PUT' : 'POST', body).then(r => {
    btn.disabled = false;
    if (r.success) location.reload();
    else alert(r.message || 'ذخیره نشد');
  });
}

function delZone(id, screens) {
  const warn = screens > 0
    ? 'این محل ' + screens + ' صفحه دارد. با حذف محل، آن صفحه‌ها بی‌محل می‌شوند و انتشار روی Zone به آن‌ها نمی‌رسد. ادامه؟'
    : 'این محل حذف شود؟';
  if (!confirm(warn)) return;
  jfetch('/api/v1/venues/' + id, 'DELETE').then(r => {
    if (r.success) location.reload();
    else alert(r.message || 'حذف نشد');
  });
}

// ── انتشار ─────────────────────────────────────────────────────────
function publishTo(id, name) {
  document.getElementById('p_zone').value = id;
  document.getElementById('pubZoneName').textContent = name;
  document.getElementById('pubModal').classList.remove("hidden");
}
function closePub() { document.getElementById('pubModal').classList.add("hidden"); }

function savePub() {
  const pl = document.getElementById('p_playlist').value;
  if (!pl) { alert('پلی‌لیست را انتخاب کنید'); return; }

  const btn   = document.getElementById('p_save');
  const zName = document.getElementById('pubZoneName').textContent;
  const plName = document.getElementById('p_playlist').selectedOptions[0].textContent.trim();

  btn.disabled = true;
  jfetch('/api/v1/schedules', 'POST', {
    /* نام اجباری است (اعتبارسنجی خودِ API). نام خودکار از محل و
       پلی‌لیست ساخته می‌شود تا در فهرست برنامه‌ها قابل‌تشخیص باشد؛
       «برنامهٔ ۴» به کسی نمی‌گوید چه چیزی کجا پخش می‌شود. */
    name:        plName + ' — ' + zName,
    venue_id:    parseInt(document.getElementById('p_zone').value, 10),
    playlist_id: parseInt(pl, 10),
    /* ENUM ستون type مقدار recurring ندارد؛ always با معنای این فرم
       (بدون تاریخ شروع/پایان) می‌خواند */
    type:        'always',
    start_time:  document.getElementById('p_from').value || null,
    end_time:    document.getElementById('p_to').value || null,
  }).then(r => {
    btn.disabled = false;
    if (r.success) location.reload();
    else alert(r.message || 'انتشار نشد');
  });
}
</script>
</main></div></body></html>
