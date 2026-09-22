<?php
/**
 * تعریف اتاق‌ها — فقط مدیر ارشد.
 *
 * الگوی کارت به‌جای جدول: برای ۳۰۰ اتاق، جدول بی‌فایده است. اپراتور
 * باید در یک نگاه ببیند کدام اتاق پر است، کدام خالی، و کدام هنوز
 * تلویزیون ندارد. همان کاری که میدلورهای استاندارد هتلی می‌کنند.
 *
 * @var list<array<string,mixed>> $rooms
 * @var list<array<string,mixed>> $groups
 * @var list<array<string,mixed>> $locations
 * @var list<array<string,mixed>> $freeScreens
 * @var array<string,int>         $stats
 */
include VIEWS_PATH . '/partials/layout.php';

/** @var array<string,array{label:string,cls:string}> */
$statusMeta = [
    'available'   => ['label' => 'خالی',   'cls' => 'is-free'],
    'occupied'    => ['label' => 'پر',     'cls' => 'is-busy'],
    'maintenance' => ['label' => 'تعمیر',  'cls' => 'is-fix'],
];
?>

<style>
/* ── نوار آمار ── */
.rm-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: var(--s4);
  margin-bottom: var(--s5);
}
.rm-stat {
  background: var(--surface-2);
  border: 1px solid var(--line-1);
  border-radius: var(--r-md);
  padding: var(--s4);
}
.rm-stat-n { font-size: 26px; font-weight: 700; line-height: 1.1; }
.rm-stat-l { font-size: 12.5px; color: var(--text-3); margin-top: 3px; }

/* ── فیلتر ── */
.rm-bar {
  display: flex; flex-wrap: wrap; gap: var(--s3);
  align-items: center; margin-bottom: var(--s4);
}
.rm-bar .form-input { width: auto; min-width: 150px; }

/* ── شبکه‌ی کارت ── */
.rm-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: var(--s3);
}
.rm-card {
  position: relative;
  background: var(--surface-2);
  border: 1px solid var(--line-1);
  border-right: 4px solid var(--text-4);
  border-radius: var(--r-md);
  padding: var(--s4);
  cursor: pointer;
  transition: border-color var(--t-fast), background var(--t-fast),
              transform var(--t-fast);
}
.rm-card:hover { background: var(--surface-3); transform: translateY(-1px); }

/* رنگ نوار کناری = وضعیت. از فاصله هم خوانده می‌شود، بدون خواندن متن. */
.rm-card.is-free { border-right-color: var(--text-4); }
.rm-card.is-busy { border-right-color: var(--ok); }
.rm-card.is-fix  { border-right-color: var(--warn); }

.rm-no {
  font-size: 19px; font-weight: 700;
  font-family: 'Inter', monospace; letter-spacing: -.01em;
}
.rm-name  { font-size: 12.5px; color: var(--text-3); margin-top: 2px; min-height: 17px; }
.rm-meta  { font-size: 11.5px; color: var(--text-4); margin-top: var(--s3); line-height: 1.7; }
.rm-guest { font-size: 12.5px; color: var(--ok); margin-top: var(--s2); font-weight: 600; }

.rm-tag {
  position: absolute; top: var(--s3); left: var(--s3);
  font-size: 10.5px; padding: 1px 7px; border-radius: 999px;
  border: 1px solid var(--line-2); color: var(--text-3);
}
.rm-card.is-busy .rm-tag { border-color: rgba(50,209,122,.3); color: var(--ok); }
.rm-card.is-fix  .rm-tag { border-color: rgba(245,177,61,.3); color: var(--warn); }

/* تلویزیون متصل — نقطه‌ی کوچک با رنگ وضعیت اتصال */
.rm-tv { display: flex; align-items: center; gap: 5px; margin-top: var(--s2); font-size: 11.5px; }
.rm-dot { width: 7px; height: 7px; border-radius: 50%; flex: none; }
.rm-dot.on  { background: var(--ok); }
.rm-dot.off { background: var(--text-4); }
.rm-dot.non { background: var(--error); }

/* ── گفتگو ── */
.rm-modal {
  position: fixed; inset: 0; z-index: 90;
  background: rgba(0,0,0,.6);
  display: none; align-items: center; justify-content: center;
  padding: var(--s5);
}
.rm-modal.is-on { display: flex; }
.rm-box {
  background: var(--surface-2);
  border: 1px solid var(--line-2);
  border-radius: var(--r-lg);
  box-shadow: var(--shadow-4);
  width: 560px; max-width: 100%;
  max-height: 88vh; overflow-y: auto;
  padding: var(--s6);
  animation: sheet var(--t-slow) var(--ease-out) both;
}
.rm-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: var(--s4); }
.rm-field { margin-bottom: var(--s4); }
@media (max-width: 560px) { .rm-grid2 { grid-template-columns: 1fr; } }
</style>

<div class="content">

  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--s4);margin-bottom:var(--s5)">
    <div>
      <h1 style="margin:0;font-size:19px;font-weight:600">تعریف اتاق‌ها</h1>
      <div style="font-size:12.5px;color:var(--text-3);margin-top:4px">
        ساختار پایه‌ی هتل. ورود و خروج مهمان از بخش «تلویزیون اتاق» انجام می‌شود.
      </div>
    </div>
    <div style="display:flex;gap:var(--s3)">
      <button class="btn btn-ghost" id="btn-bulk">
        <i class="fas fa-layer-group"></i> افزودن گروهی
      </button>
      <button class="btn btn-primary" id="btn-new">
        <i class="fas fa-plus"></i> اتاق جدید
      </button>
    </div>
  </div>

  <?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><i class="fas fa-circle-check"></i><span><?= e($flash['success']) ?></span></div>
  <?php endif; ?>
  <?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><i class="fas fa-circle-xmark"></i><span><?= e($flash['error']) ?></span></div>
  <?php endif; ?>

  <div class="rm-stats">
    <div class="rm-stat">
      <div class="rm-stat-n num"><?= (int)$stats['total'] ?></div>
      <div class="rm-stat-l">کل اتاق‌ها</div>
    </div>
    <div class="rm-stat">
      <div class="rm-stat-n num" style="color:var(--ok)"><?= (int)$stats['occupied'] ?></div>
      <div class="rm-stat-l">پر</div>
    </div>
    <div class="rm-stat">
      <div class="rm-stat-n num"><?= (int)$stats['available'] ?></div>
      <div class="rm-stat-l">خالی</div>
    </div>
    <div class="rm-stat">
      <div class="rm-stat-n num" style="color:<?= $stats['no_screen'] > 0 ? 'var(--error)' : 'var(--text-1)' ?>">
        <?= (int)$stats['no_screen'] ?>
      </div>
      <div class="rm-stat-l">بدون تلویزیون</div>
    </div>
  </div>

  <?php if (!$rooms): ?>
    <div class="card">
      <div class="empty-state" style="padding:var(--s10) var(--s5)">
        <div style="font-size:15px;color:var(--text-2);margin-bottom:var(--s3)">هنوز اتاقی تعریف نشده</div>
        <div style="font-size:13px;color:var(--text-3);line-height:1.9;margin-bottom:var(--s5)">
          برای هتلی با ۳۰۰ اتاق، «افزودن گروهی» را بزنید و بازه‌ی شماره‌ها را
          وارد کنید — مثلا ۱۰۱ تا ۱۲۰ برای طبقه‌ی اول.
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('btn-bulk').click()">
          <i class="fas fa-layer-group"></i> افزودن گروهی
        </button>
      </div>
    </div>
  <?php else: ?>

    <div class="rm-bar">
      <input type="search" class="form-input" id="f-search" placeholder="جستجوی شماره یا نام اتاق…">
      <select class="form-input" id="f-status">
        <option value="">همه‌ی وضعیت‌ها</option>
        <option value="available">خالی</option>
        <option value="occupied">پر</option>
        <option value="maintenance">در تعمیر</option>
      </select>
      <select class="form-input" id="f-floor">
        <option value="">همه‌ی طبقات</option>
        <?php
        $floors = array_values(array_unique(array_filter(
            array_column($rooms, 'floor'),
            static fn($f) => $f !== null
        )));
        sort($floors);
        foreach ($floors as $f): ?>
          <option value="<?= (int)$f ?>">طبقه <?= (int)$f ?></option>
        <?php endforeach; ?>
      </select>
      <span style="font-size:12.5px;color:var(--text-3)" id="f-count"></span>
    </div>

    <div class="rm-grid" id="rm-grid">
      <?php foreach ($rooms as $r):
        $meta = $statusMeta[$r['status']] ?? $statusMeta['available'];
      ?>
      <div class="rm-card <?= $meta['cls'] ?>"
           data-room='<?= e(json_encode($r, JSON_UNESCAPED_UNICODE)) ?>'
           data-number="<?= e($r['room_number']) ?>"
           data-name="<?= e((string)($r['room_name'] ?? '')) ?>"
           data-status="<?= e($r['status']) ?>"
           data-floor="<?= $r['floor'] !== null ? (int)$r['floor'] : '' ?>">
        <span class="rm-tag"><?= e($meta['label']) ?></span>
        <div class="rm-no"><?= e($r['room_number']) ?></div>
        <div class="rm-name"><?= e((string)($r['room_name'] ?? '')) ?></div>

        <?php if (!empty($r['guest_name'])): ?>
          <div class="rm-guest"><i class="fas fa-user" style="font-size:10px"></i> <?= e($r['guest_name']) ?></div>
        <?php endif; ?>

        <div class="rm-meta">
          <?php
          $bits = [];
          if (!empty($r['building']))   $bits[] = e($r['building']);
          if (!empty($r['wing']))       $bits[] = e($r['wing']);
          if ($r['floor'] !== null)     $bits[] = 'طبقه ' . (int)$r['floor'];
          if (!empty($r['room_type']))  $bits[] = e($r['room_type']);
          echo $bits ? implode(' · ', $bits) : '—';
          ?>
          <?php if (!empty($r['group_name'])): ?>
            <br><i class="fas fa-object-group" style="font-size:9px"></i> <?= e($r['group_name']) ?>
          <?php endif; ?>
        </div>

        <div class="rm-tv">
          <?php if (empty($r['screen_code'])): ?>
            <span class="rm-dot non"></span>
            <span style="color:var(--error)">تلویزیون وصل نشده</span>
          <?php else: ?>
            <span class="rm-dot <?= !empty($r['is_online']) ? 'on' : 'off' ?>"></span>
            <span style="color:var(--text-3);font-family:monospace"><?= e($r['screen_code']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ══ گفتگوی تکی ══ -->
<div class="rm-modal" id="modal-room">
  <form class="rm-box" method="POST" id="form-room">
    <?= csrf_field() ?>
    <h2 style="margin:0 0 var(--s5);font-size:17px;font-weight:600" id="modal-title">اتاق جدید</h2>

    <div class="rm-grid2">
      <div class="rm-field">
        <label class="form-label" for="room_number">شماره اتاق *</label>
        <input class="form-input" id="room_number" name="room_number" required placeholder="۱۲۰۴">
      </div>
      <div class="rm-field">
        <label class="form-label" for="room_name">نام اتاق</label>
        <input class="form-input" id="room_name" name="room_name" placeholder="سوئیت رویال">
      </div>
    </div>

    <div class="rm-grid2">
      <div class="rm-field">
        <label class="form-label" for="building">ساختمان</label>
        <input class="form-input" id="building" name="building" placeholder="برج شرقی">
      </div>
      <div class="rm-field">
        <label class="form-label" for="wing">بال</label>
        <input class="form-input" id="wing" name="wing" placeholder="بلوک A">
      </div>
    </div>

    <div class="rm-grid2">
      <div class="rm-field">
        <label class="form-label" for="floor">طبقه</label>
        <input class="form-input" id="floor" name="floor" type="number" placeholder="۳">
      </div>
      <div class="rm-field">
        <label class="form-label" for="room_type">نوع اتاق</label>
        <input class="form-input" id="room_type" name="room_type" placeholder="دو تخته">
      </div>
    </div>

    <?php if ($locations): ?>
    <div class="rm-field">
      <label class="form-label" for="location_id">شعبه</label>
      <select class="form-input" id="location_id" name="location_id">
        <option value="">—</option>
        <?php foreach ($locations as $l): ?>
          <option value="<?= (int)$l['id'] ?>"><?= e($l['name']) ?><?= $l['city'] ? ' — ' . e($l['city']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <div class="rm-grid2">
      <div class="rm-field">
        <label class="form-label" for="group_id">گروه</label>
        <select class="form-input" id="group_id" name="group_id">
          <option value="">—</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="rm-field">
        <label class="form-label" for="access_level">سطح دسترسی کانال</label>
        <select class="form-input" id="access_level" name="access_level">
          <option value="0">۰ — پایه</option>
          <option value="1">۱ — استاندارد</option>
          <option value="2">۲ — ویژه</option>
          <option value="3">۳ — VIP</option>
        </select>
      </div>
    </div>

    <div class="rm-field">
      <label class="form-label" for="screen_id">تلویزیون متصل</label>
      <select class="form-input" id="screen_id" name="screen_id">
        <option value="">— بعدا وصل می‌شود —</option>
        <?php foreach ($freeScreens as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= e($s['code']) ?> — <?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div style="font-size:11.5px;color:var(--text-4);margin-top:5px">
        فقط تلویزیون‌هایی که هنوز به اتاقی وصل نیستند نشان داده می‌شوند.
      </div>
    </div>

    <div style="display:flex;gap:var(--s3);justify-content:space-between;margin-top:var(--s6)">
      <button type="button" class="btn btn-danger hidden" id="btn-del">
        <i class="fas fa-trash"></i> حذف اتاق
      </button>
      <div style="display:flex;gap:var(--s3);margin-right:auto">
        <button type="button" class="btn btn-ghost" data-close>انصراف</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> ذخیره</button>
      </div>
    </div>
  </form>
</div>

<!-- ══ گفتگوی گروهی ══ -->
<div class="rm-modal" id="modal-bulk">
  <form class="rm-box" method="POST" action="/admin/property/rooms/bulk">
    <?= csrf_field() ?>
    <h2 style="margin:0 0 var(--s3);font-size:17px;font-weight:600">افزودن گروهی اتاق</h2>
    <div style="font-size:12.5px;color:var(--text-3);line-height:1.9;margin-bottom:var(--s5)">
      یک بازه‌ی شماره وارد کنید. اتاق‌هایی که از قبل هستند رد می‌شوند، پس
      می‌توانید بازه را دوباره بزنید تا جاافتاده‌ها اضافه شوند.
    </div>

    <div class="rm-grid2">
      <div class="rm-field">
        <label class="form-label" for="b-from">از شماره *</label>
        <input class="form-input" id="b-from" name="from" type="number" required placeholder="۳۰۱">
      </div>
      <div class="rm-field">
        <label class="form-label" for="b-to">تا شماره *</label>
        <input class="form-input" id="b-to" name="to" type="number" required placeholder="۳۲۰">
      </div>
    </div>

    <div class="rm-grid2">
      <div class="rm-field">
        <label class="form-label" for="b-prefix">پیشوند</label>
        <input class="form-input" id="b-prefix" name="prefix" placeholder="A-">
      </div>
      <div class="rm-field">
        <label class="form-label" for="b-floor">طبقه</label>
        <input class="form-input" id="b-floor" name="floor" type="number" placeholder="۳">
      </div>
    </div>

    <div class="rm-grid2">
      <div class="rm-field">
        <label class="form-label" for="b-building">ساختمان</label>
        <input class="form-input" id="b-building" name="building" placeholder="برج شرقی">
      </div>
      <div class="rm-field">
        <label class="form-label" for="b-type">نوع اتاق</label>
        <input class="form-input" id="b-type" name="room_type" placeholder="دو تخته">
      </div>
    </div>

    <div class="rm-grid2">
      <?php if ($locations): ?>
      <div class="rm-field">
        <label class="form-label" for="b-location">شعبه</label>
        <select class="form-input" id="b-location" name="location_id">
          <option value="">—</option>
          <?php foreach ($locations as $l): ?>
            <option value="<?= (int)$l['id'] ?>"><?= e($l['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="rm-field">
        <label class="form-label" for="b-group">گروه</label>
        <select class="form-input" id="b-group" name="group_id">
          <option value="">—</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div style="display:flex;gap:var(--s3);justify-content:flex-end;margin-top:var(--s6)">
      <button type="button" class="btn btn-ghost" data-close>انصراف</button>
      <button type="submit" class="btn btn-primary"><i class="fas fa-layer-group"></i> ساخت اتاق‌ها</button>
    </div>
  </form>
</div>

<script>
(function () {
  'use strict';

  var modalRoom = document.getElementById('modal-room');
  var modalBulk = document.getElementById('modal-bulk');
  var form      = document.getElementById('form-room');

  function open(m)  { m.classList.add('is-on'); }
  function close(m) { m.classList.remove('is-on'); }

  /* بستن با کلیک روی زمینه و با Escape — هر دو انتظار کاربر است */
  [modalRoom, modalBulk].forEach(function (m) {
    m.addEventListener('click', function (e) { if (e.target === m) close(m); });
    Array.prototype.forEach.call(m.querySelectorAll('[data-close]'), function (b) {
      b.addEventListener('click', function () { close(m); });
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { close(modalRoom); close(modalBulk); }
  });

  // ── اتاق جدید ──
  document.getElementById('btn-new').addEventListener('click', function () {
    form.reset();
    form.action = '/admin/property/rooms';
    document.getElementById('modal-title').textContent = 'اتاق جدید';
    document.getElementById('room_number').readOnly = false;
    document.getElementById('btn-del').classList.add('hidden');
    open(modalRoom);
    document.getElementById('room_number').focus();
  });

  document.getElementById('btn-bulk').addEventListener('click', function () { open(modalBulk); });

  // ── ویرایش با کلیک روی کارت ──
  Array.prototype.forEach.call(document.querySelectorAll('.rm-card'), function (card) {
    card.addEventListener('click', function () {
      var r;
      try { r = JSON.parse(card.getAttribute('data-room')); } catch (e) { return; }

      form.reset();
      form.action = '/admin/property/rooms/' + r.id;
      document.getElementById('modal-title').textContent = 'اتاق ' + r.room_number;

      /* شماره اتاق بعد از ساخت قفل است: عوض‌کردنش یعنی صورتحساب و
         تاریخچه‌ی این اتاق به اتاق دیگری نسبت داده می‌شود. */
      var num = document.getElementById('room_number');
      num.value = r.room_number;
      num.readOnly = true;

      set('room_name',    r.room_name);
      set('building',     r.building);
      set('wing',         r.wing);
      set('floor',        r.floor);
      set('room_type',    r.room_type);
      set('location_id',  r.location_id);
      set('group_id',     r.group_id);
      set('access_level', r.access_level || 0);

      var del = document.getElementById('btn-del');
      del.classList.remove('hidden');
      del.onclick = function () {
        if (!confirm('اتاق ' + r.room_number + ' حذف شود؟\n\nتلویزیون متصل آزاد می‌شود.')) return;
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = '/admin/property/rooms/' + r.id + '/delete';
        f.innerHTML = form.querySelector('input[name="_token"]').outerHTML;
        document.body.appendChild(f);
        f.submit();
      };

      open(modalRoom);
    });
  });

  function set(id, val) {
    var el = document.getElementById(id);
    if (el) el.value = (val === null || val === undefined) ? '' : val;
  }

  // ── فیلتر ──
  var cards  = Array.prototype.slice.call(document.querySelectorAll('.rm-card'));
  var search = document.getElementById('f-search');
  var fStat  = document.getElementById('f-status');
  var fFloor = document.getElementById('f-floor');
  var count  = document.getElementById('f-count');

  function filter() {
    if (!cards.length) return;
    var q  = (search.value || '').trim().toLowerCase();
    var st = fStat.value;
    var fl = fFloor.value;
    var shown = 0;

    cards.forEach(function (c) {
      var ok = true;
      if (q) {
        var hay = (c.getAttribute('data-number') + ' ' + c.getAttribute('data-name')).toLowerCase();
        if (hay.indexOf(q) === -1) ok = false;
      }
      if (ok && st && c.getAttribute('data-status') !== st) ok = false;
      if (ok && fl && c.getAttribute('data-floor') !== fl) ok = false;

      c.style.display = ok ? '' : 'none';
      if (ok) shown++;
    });

    count.textContent = shown === cards.length
      ? cards.length + ' اتاق'
      : shown + ' از ' + cards.length + ' اتاق';
  }

  if (search) {
    [search, fStat, fFloor].forEach(function (el) {
      el.addEventListener('input',  filter);
      el.addEventListener('change', filter);
    });
    filter();
  }
})();
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
