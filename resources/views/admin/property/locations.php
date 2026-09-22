<?php
/**
 * تعریف شعبه‌ها — فقط مدیر ارشد.
 *
 * برای هتل زنجیره‌ای. هتل تک‌شعبه‌ای اصلا لازم نیست اینجا چیزی تعریف
 * کند — همه‌چیز بدون شعبه هم کار می‌کند.
 *
 * فایده‌اش وقتی معلوم می‌شود که یک سرور چند هتل را می‌گرداند:
 * اپراتور شعبه فقط دستگاه‌های خودش را می‌بیند و اشتباهی روی شعبه‌ی
 * دیگر پخش نمی‌کند.
 *
 * @var list<array<string,mixed>> $locations
 */
include VIEWS_PATH . '/partials/layout.php';
?>

<style>
.lc-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: var(--s4);
}
.lc-card {
  background: var(--surface-2);
  border: 1px solid var(--line-1);
  border-radius: var(--r-md);
  padding: var(--s5);
  cursor: pointer;
  transition: background var(--t-fast), transform var(--t-fast);
}
.lc-card:hover { background: var(--surface-3); transform: translateY(-1px); }
.lc-card.is-off { opacity: .5; }
.lc-name { font-size: 16px; font-weight: 600; }
.lc-city { font-size: 12.5px; color: var(--brand-400); margin-top: 3px; }
.lc-addr { font-size: 12.5px; color: var(--text-3); margin-top: var(--s3); line-height: 1.8; min-height: 20px; }
.lc-nums { display: flex; gap: var(--s5); margin-top: var(--s4);
           padding-top: var(--s4); border-top: 1px solid var(--line-1); }
.lc-nums b { font-size: 17px; font-weight: 700; display: block; }
.lc-nums span { font-size: 12px; color: var(--text-3); }

.lc-modal { position: fixed; inset: 0; z-index: 90; background: rgba(0,0,0,.6);
            display: none; align-items: center; justify-content: center; padding: var(--s5); }
.lc-modal.is-on { display: flex; }
.lc-box { background: var(--surface-2); border: 1px solid var(--line-2);
          border-radius: var(--r-lg); box-shadow: var(--shadow-4);
          width: 480px; max-width: 100%; max-height: 88vh; overflow-y: auto;
          padding: var(--s6); animation: sheet var(--t-slow) var(--ease-out) both; }
.lc-field { margin-bottom: var(--s4); }
</style>

<div class="content">

  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--s4);margin-bottom:var(--s5)">
    <div>
      <h1 style="margin:0;font-size:19px;font-weight:600">تعریف شعبه‌ها</h1>
      <div style="font-size:12.5px;color:var(--text-3);margin-top:4px">
        فقط برای هتل زنجیره‌ای. هتل تک‌شعبه‌ای به این بخش نیازی ندارد.
      </div>
    </div>
    <button class="btn btn-primary" id="btn-new"><i class="fas fa-plus"></i> شعبه جدید</button>
  </div>

  <?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><i class="fas fa-circle-check"></i><span><?= e($flash['success']) ?></span></div>
  <?php endif; ?>
  <?php if (!empty($flash['error'])): ?>
    <div class="alert alert-error"><i class="fas fa-circle-xmark"></i><span><?= e($flash['error']) ?></span></div>
  <?php endif; ?>

  <?php if (!$locations): ?>
    <div class="card">
      <div class="empty-state" style="padding:var(--s10) var(--s5)">
        <div style="font-size:15px;color:var(--text-2);margin-bottom:var(--s3)">هیچ شعبه‌ای تعریف نشده</div>
        <div style="font-size:13px;color:var(--text-3);line-height:1.9">
          اگر فقط یک هتل دارید، لازم نیست چیزی اینجا بسازید —
          همه‌ی بخش‌ها بدون شعبه هم کار می‌کنند.
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="lc-grid">
      <?php foreach ($locations as $l): ?>
        <div class="lc-card <?= empty($l['is_active']) ? 'is-off' : '' ?>"
             data-loc='<?= e(json_encode($l, JSON_UNESCAPED_UNICODE)) ?>'>
          <div class="lc-name"><?= e($l['name']) ?></div>
          <?php if (!empty($l['city'])): ?>
            <div class="lc-city"><i class="fas fa-location-dot" style="font-size:10px"></i> <?= e($l['city']) ?></div>
          <?php endif; ?>
          <div class="lc-addr"><?= e((string)($l['address'] ?? '')) ?></div>
          <div class="lc-nums">
            <div><b class="num"><?= (int)$l['room_count'] ?></b><span>اتاق</span></div>
            <div><b class="num"><?= (int)$l['screen_count'] ?></b><span>صفحه‌نمایش</span></div>
            <?php if (!empty($l['phone'])): ?>
              <div><b style="font-size:13px;font-weight:600;font-family:monospace"><?= e($l['phone']) ?></b><span>تلفن</span></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="lc-modal" id="modal">
  <form class="lc-box" method="POST" id="form">
    <?= csrf_field() ?>
    <h2 style="margin:0 0 var(--s5);font-size:17px;font-weight:600" id="m-title">شعبه جدید</h2>

    <div class="lc-field">
      <label class="form-label" for="name">نام شعبه *</label>
      <input class="form-input" id="name" name="name" required placeholder="هتل کیش">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--s4)">
      <div class="lc-field">
        <label class="form-label" for="city">شهر</label>
        <input class="form-input" id="city" name="city" placeholder="کیش">
      </div>
      <div class="lc-field">
        <label class="form-label" for="phone">تلفن</label>
        <input class="form-input" id="phone" name="phone" placeholder="۰۷۶۴۴۴۴۴۴۴۴">
      </div>
    </div>

    <div class="lc-field">
      <label class="form-label" for="address">آدرس</label>
      <input class="form-input" id="address" name="address" placeholder="میدان امیرکبیر، بلوار…">
    </div>

    <div class="lc-field">
      <label class="form-label" for="timezone">منطقه زمانی</label>
      <input class="form-input" id="timezone" name="timezone" value="Asia/Tehran">
      <div style="font-size:11.5px;color:var(--text-4);margin-top:5px">
        اگر شعبه‌ای در کشور دیگری است، ساعت تابلوهایش از همین خوانده می‌شود.
      </div>
    </div>

    <div class="lc-field hidden" id="active-field">
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
    form.action = '/admin/property/locations';
    document.getElementById('m-title').textContent = 'شعبه جدید';
    document.getElementById('timezone').value = 'Asia/Tehran';
    document.getElementById('active-field').classList.add('hidden');
    document.getElementById('btn-del').classList.add('hidden');
    modal.classList.add('is-on');
    document.getElementById('name').focus();
  });

  Array.prototype.forEach.call(document.querySelectorAll('.lc-card'), function (card) {
    card.addEventListener('click', function () {
      var l;
      try { l = JSON.parse(card.getAttribute('data-loc')); } catch (e) { return; }

      form.reset();
      form.action = '/admin/property/locations/' + l.id;
      document.getElementById('m-title').textContent = l.name;
      document.getElementById('active-field').classList.remove('hidden');

      document.getElementById('name').value     = l.name || '';
      document.getElementById('city').value     = l.city || '';
      document.getElementById('phone').value    = l.phone || '';
      document.getElementById('address').value  = l.address || '';
      document.getElementById('timezone').value = l.timezone || 'Asia/Tehran';
      document.getElementById('is_active').checked = !!Number(l.is_active);

      var del = document.getElementById('btn-del');
      del.classList.remove('hidden');
      del.onclick = function () {
        if (!confirm('شعبه «' + l.name + '» حذف شود؟')) return;
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = '/admin/property/locations/' + l.id + '/delete';
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
