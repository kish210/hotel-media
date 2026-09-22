<?php
/**
 * تعریف گروه‌ها — فقط مدیر ارشد.
 *
 * گروه یعنی «طبقه ۳»، «سوئیت‌ها»، «تابلوهای لابی». کارش این است که
 * بشود یک منو یا یک فرمان را یک‌جا روی چند دستگاه اعمال کرد، به‌جای
 * تک‌تک.
 *
 * دو نوع دارد و عمدا از هم جدا هستند: گروه تابلو و گروه تلویزیون اتاق
 * دو دنیای متفاوت‌اند و قاطی‌شدنشان همان چیزی است که اپراتور را گیج
 * می‌کرد.
 *
 * @var list<array<string,mixed>> $groups
 * @var list<array<string,mixed>> $locations
 */
include VIEWS_PATH . '/partials/layout.php';

$byType = ['iptv' => [], 'signage' => []];
foreach ($groups as $g) {
    $byType[$g['type'] === 'iptv' ? 'iptv' : 'signage'][] = $g;
}
?>

<style>
.gr-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: var(--s4);
}
.gr-card {
  position: relative;
  background: var(--surface-2);
  border: 1px solid var(--line-1);
  border-radius: var(--r-md);
  padding: var(--s5);
  cursor: pointer;
  transition: background var(--t-fast), transform var(--t-fast);
}
.gr-card:hover { background: var(--surface-3); transform: translateY(-1px); }
.gr-card.is-off { opacity: .5; }

/* نوار رنگی بالای کارت = رنگی که اپراتور برای گروه انتخاب کرده */
.gr-bar {
  position: absolute; top: 0; right: 0; left: 0;
  height: 3px;
  border-radius: var(--r-md) var(--r-md) 0 0;
}
.gr-name  { font-size: 15px; font-weight: 600; }
.gr-desc  { font-size: 12.5px; color: var(--text-3); margin-top: 4px; min-height: 18px; }
.gr-count { display: flex; gap: var(--s4); margin-top: var(--s4); font-size: 12.5px; }
.gr-count b { font-size: 16px; font-weight: 700; display: block; }
.gr-empty-hint { color: var(--warn); }

.gr-modal { position: fixed; inset: 0; z-index: 90; background: rgba(0,0,0,.6);
            display: none; align-items: center; justify-content: center; padding: var(--s5); }
.gr-modal.is-on { display: flex; }
.gr-box { background: var(--surface-2); border: 1px solid var(--line-2);
          border-radius: var(--r-lg); box-shadow: var(--shadow-4);
          width: 480px; max-width: 100%; max-height: 88vh; overflow-y: auto;
          padding: var(--s6); animation: sheet var(--t-slow) var(--ease-out) both; }
.gr-field { margin-bottom: var(--s4); }
</style>

<div class="content">

  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--s4);margin-bottom:var(--s5)">
    <div>
      <h1 style="margin:0;font-size:19px;font-weight:600">تعریف گروه‌ها</h1>
      <div style="font-size:12.5px;color:var(--text-3);margin-top:4px">
        برای اعمال یک منو یا فرمان روی چند دستگاه با هم.
      </div>
    </div>
    <button class="btn btn-primary" id="btn-new"><i class="fas fa-plus"></i> گروه جدید</button>
  </div>

  <?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><i class="fas fa-circle-check"></i><span><?= e($flash['success']) ?></span></div>
  <?php endif; ?>
  <?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><i class="fas fa-circle-xmark"></i><span><?= e($flash['error']) ?></span></div>
  <?php endif; ?>

  <?php if (!$groups): ?>
    <div class="card">
      <div class="empty-state" style="padding:var(--s10) var(--s5)">
        <div style="font-size:15px;color:var(--text-2);margin-bottom:var(--s3)">هنوز گروهی تعریف نشده</div>
        <div style="font-size:13px;color:var(--text-3);line-height:1.9">
          گروه یعنی «طبقه ۳» یا «تابلوهای لابی» — تا بتوانید یک منو را
          یک‌بار روی همه‌شان اعمال کنید.
        </div>
      </div>
    </div>
  <?php else: ?>

    <?php foreach ([
      'iptv'    => ['تلویزیون اتاق', 'fa-satellite-dish'],
      'signage' => ['تابلوی دیجیتال', 'fa-display'],
    ] as $type => [$label, $icon]):
      if (!$byType[$type]) continue; ?>

      <div style="display:flex;align-items:center;gap:var(--s2);margin:var(--s6) 0 var(--s4)">
        <i class="fas <?= $icon ?>" style="color:var(--text-4);font-size:13px"></i>
        <h2 style="margin:0;font-size:14px;font-weight:600;color:var(--text-2)"><?= e($label) ?></h2>
        <span style="font-size:12px;color:var(--text-4)">(<?= count($byType[$type]) ?>)</span>
      </div>

      <div class="gr-grid">
        <?php foreach ($byType[$type] as $g): ?>
          <div class="gr-card <?= empty($g['is_active']) ? 'is-off' : '' ?>"
               data-group='<?= e(json_encode($g, JSON_UNESCAPED_UNICODE)) ?>'>
            <div class="gr-bar" style="background:<?= e($g['color'] ?: '#1a7ac4') ?>"></div>
            <div class="gr-name"><?= e($g['name']) ?></div>
            <div class="gr-desc"><?= e((string)($g['description'] ?? '')) ?></div>
            <div class="gr-count">
              <?php if ($type === 'iptv'): ?>
                <div>
                  <b class="num <?= (int)$g['room_count'] === 0 ? 'gr-empty-hint' : '' ?>"><?= (int)$g['room_count'] ?></b>
                  <span style="color:var(--text-3)">اتاق</span>
                </div>
              <?php else: ?>
                <div>
                  <b class="num <?= (int)$g['screen_count'] === 0 ? 'gr-empty-hint' : '' ?>"><?= (int)$g['screen_count'] ?></b>
                  <span style="color:var(--text-3)">صفحه‌نمایش</span>
                </div>
              <?php endif; ?>
              <?php if (!empty($g['location_name'])): ?>
                <div>
                  <b style="font-size:13px;font-weight:600"><?= e($g['location_name']) ?></b>
                  <span style="color:var(--text-3)">شعبه</span>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="gr-modal" id="modal">
  <form class="gr-box" method="POST" id="form">
    <?= csrf_field() ?>
    <h2 style="margin:0 0 var(--s5);font-size:17px;font-weight:600" id="m-title">گروه جدید</h2>

    <div class="gr-field">
      <label class="form-label" for="name">نام گروه *</label>
      <input class="form-input" id="name" name="name" required placeholder="طبقه ۳">
    </div>

    <div class="gr-field" id="type-field">
      <label class="form-label" for="type">این گروه مال کجاست؟ *</label>
      <select class="form-input" id="type" name="type">
        <option value="iptv">تلویزیون اتاق</option>
        <option value="signage">تابلوی دیجیتال</option>
      </select>
      <div style="font-size:11.5px;color:var(--text-4);margin-top:5px">
        بعد از ساخت قابل تغییر نیست — گروه اتاق و گروه تابلو با هم قاطی نمی‌شوند.
      </div>
    </div>

    <div class="gr-field">
      <label class="form-label" for="description">توضیح</label>
      <input class="form-input" id="description" name="description" placeholder="اتاق‌های ۳۰۱ تا ۳۲۰">
    </div>

    <?php if ($locations): ?>
    <div class="gr-field">
      <label class="form-label" for="location_id">شعبه</label>
      <select class="form-input" id="location_id" name="location_id">
        <option value="">—</option>
        <?php foreach ($locations as $l): ?>
          <option value="<?= (int)$l['id'] ?>"><?= e($l['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--s4)">
      <div class="gr-field">
        <label class="form-label" for="color">رنگ</label>
        <input class="form-input" id="color" name="color" type="color" value="#1a7ac4" style="height:38px;padding:3px">
      </div>
      <div class="gr-field">
        <label class="form-label" for="sort_order">ترتیب</label>
        <input class="form-input" id="sort_order" name="sort_order" type="number" value="0">
      </div>
    </div>

    <div class="gr-field hidden" id="active-field">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" id="is_active" name="is_active" value="1" checked>
        <span style="font-size:13px">فعال</span>
      </label>
    </div>

    <div style="display:flex;gap:var(--s3);justify-content:space-between;margin-top:var(--s6)">
      <button type="button" class="btn btn-danger hidden" id="btn-del"><i class="fas fa-trash"></i> حذف</button>
      <div style="display:flex;gap:var(--s3);margin-right:auto">
        <button type="button" class="btn btn-ghost" data-close>انصراف</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> ذخیره</button>
      </div>
    </div>
  </form>
</div>

<script>
(function () {
  'use strict';
  var modal = document.getElementById('modal');
  var form  = document.getElementById('form');

  function close() { modal.classList.remove('is-on'); }
  modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
  Array.prototype.forEach.call(modal.querySelectorAll('[data-close]'), function (b) {
    b.addEventListener('click', close);
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

  document.getElementById('btn-new').addEventListener('click', function () {
    form.reset();
    form.action = '/admin/property/groups';
    document.getElementById('m-title').textContent = 'گروه جدید';
    document.getElementById('type-field').classList.remove('hidden');
    document.getElementById('active-field').classList.add('hidden');
    document.getElementById('btn-del').classList.add('hidden');
    modal.classList.add('is-on');
    document.getElementById('name').focus();
  });

  Array.prototype.forEach.call(document.querySelectorAll('.gr-card'), function (card) {
    card.addEventListener('click', function () {
      var g;
      try { g = JSON.parse(card.getAttribute('data-group')); } catch (e) { return; }

      form.reset();
      form.action = '/admin/property/groups/' + g.id;
      document.getElementById('m-title').textContent = g.name;

      /* نوع بعد از ساخت قفل است: تبدیل گروه اتاق به گروه تابلو یعنی
         عضوهایش بی‌معنی می‌شوند. */
      document.getElementById('type-field').classList.add('hidden');
      document.getElementById('active-field').classList.remove('hidden');

      document.getElementById('name').value        = g.name || '';
      document.getElementById('description').value = g.description || '';
      document.getElementById('color').value       = g.color || '#1a7ac4';
      document.getElementById('sort_order').value  = g.sort_order || 0;
      document.getElementById('is_active').checked = !!Number(g.is_active);

      var loc = document.getElementById('location_id');
      if (loc) loc.value = g.location_id || '';

      var del = document.getElementById('btn-del');
      del.classList.remove('hidden');
      del.onclick = function () {
        if (!confirm('گروه «' + g.name + '» حذف شود؟\n\nاتاق‌ها و دستگاه‌ها حذف نمی‌شوند، فقط از گروه بیرون می‌آیند.')) return;
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = '/admin/property/groups/' + g.id + '/delete';
        f.innerHTML = form.querySelector('input[name="_token"]').outerHTML;
        document.body.appendChild(f);
        f.submit();
      };

      modal.classList.add('is-on');
    });
  });
})();
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
