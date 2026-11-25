<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<?php
/** @var array $pages @var string $lang @var int $cols @var int $rows */
$langNames = ['fa' => 'فارسی', 'en' => 'English', 'ar' => 'العربية'];
?>
<style>
  .tt-card{background:var(--surface-2);border:1px solid var(--line-1);border-radius:var(--r-md);padding:16px;}
  .tt-table{width:100%;border-collapse:collapse;font-size:12.5px;}
  .tt-table th{color:var(--text-3);text-align:right;padding:9px 8px;border-bottom:1px solid var(--line-1);font-weight:500;}
  .tt-table td{color:var(--text-2);padding:8px;border-bottom:1px solid var(--line-1);}
  .tt-tab{padding:6px 14px;border-radius:var(--r-sm);font-size:12.5px;color:var(--text-3);
          background:var(--surface-3);border:1px solid var(--line-1);text-decoration:none;}
  .tt-tab.on{background:rgba(75,95,151,.22);color:var(--text-1);border-color:var(--sama-blue-600);}
  .tt-mono{font-family:var(--font-num);direction:ltr;text-align:left;}
  .tt-preview{background:#000;color:#e2e8f0;border:1px solid var(--line-2);border-radius:var(--r-sm);
              padding:10px 12px;font-family:var(--font-num);font-size:11.5px;line-height:1.55;
              white-space:pre;overflow:auto;min-height:300px;direction:rtl;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
  <div>
    <h1 style="font-size:20px;font-weight:800;color:var(--text-1);">
      <i class="fas fa-tv" style="color:var(--sama-blue-400);margin-left:10px;"></i>تله‌تکست
    </h1>
    <p style="font-size:12px;color:var(--text-3);margin-top:4px;">
      متن <?= (int)$cols ?>×<?= (int)$rows ?> — روی همهٔ تلویزیون‌ها یکسان نمایش داده می‌شود
    </p>
  </div>
  <div style="display:flex;gap:6px;">
    <?php foreach ($langNames as $code => $label): ?>
      <a href="/admin/teletext?lang=<?= $code ?>" class="tt-tab <?= $lang === $code ? 'on' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:14px;align-items:start;">
  <div class="tt-card" style="overflow-x:auto;">
    <table class="tt-table">
      <thead><tr><th style="width:70px;">صفحه</th><th>عنوان</th><th style="width:80px;">وضعیت</th><th style="width:110px;"></th></tr></thead>
      <tbody>
      <?php if (!$pages): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--text-3);padding:24px;">هنوز صفحه‌ای نیست</td></tr>
      <?php endif; ?>
      <?php foreach ($pages as $p): ?>
        <tr>
          <td class="tt-mono" style="color:var(--sama-gold-400);font-weight:700;"><?= (int)$p['page_no'] ?></td>
          <td><?= e($p['title'] ?? '') ?></td>
          <td><?= (int)$p['is_active'] ? '<span style="color:var(--ok)">فعال</span>' : '<span style="color:var(--text-3)">مخفی</span>' ?></td>
          <td style="white-space:nowrap;">
            <button class="btn-ghost text-xs px-2" onclick='editPage(<?= json_encode($p, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
              <i class="fas fa-pen"></i>
            </button>
            <form method="post" action="/admin/teletext/<?= (int)$p['id'] ?>/delete" style="display:inline;">
              <?= csrf_field() ?>
              <button class="btn-ghost text-xs px-2" data-confirm="این صفحه حذف شود؟" data-confirm-name="<?= e($p['title'] ?: ('صفحه ' . $p['page_no'])) ?>">
                <i class="fas fa-trash" style="color:var(--error)"></i>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <form method="post" action="/admin/teletext/import" style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <?= csrf_field() ?>
      <span style="font-size:11.5px;color:var(--text-3);">ورود از فایل‌های <code>teletext*.conf</code> سیستم قدیمی:</span>
      <input name="dir" class="form-input" style="flex:1;min-width:220px;" placeholder="/path/to/conf">
      <button class="btn-ghost text-xs px-3">ورود</button>
    </form>
  </div>

  <div class="tt-card">
    <h3 id="edTitle" style="font-size:14px;font-weight:700;color:var(--text-1);margin-bottom:10px;">صفحهٔ جدید</h3>
    <form method="post" action="/admin/teletext" id="ttForm">
      <?= csrf_field() ?>
      <input type="hidden" name="lang" value="<?= e($lang) ?>">
      <div style="display:grid;grid-template-columns:90px 1fr;gap:8px;">
        <label style="font-size:11px;color:var(--text-3);">شمارهٔ صفحه
          <input id="f_no" name="page_no" type="number" min="100" max="899" value="100" class="form-input" required>
        </label>
        <label style="font-size:11px;color:var(--text-3);">عنوان
          <input id="f_title" name="title" class="form-input" maxlength="120">
        </label>
      </div>
      <label style="font-size:11px;color:var(--text-3);display:block;margin-top:8px;">
        متن — هر خط حداکثر <?= (int)$cols ?> کاراکتر، حداکثر <?= (int)$rows ?> خط
        <textarea id="f_body" name="body" class="form-input" rows="14" style="font-family:var(--font-num);font-size:12px;"></textarea>
      </label>
      <div style="display:flex;gap:10px;align-items:center;margin-top:8px;">
        <label style="font-size:11.5px;color:var(--text-2);"><input type="checkbox" name="is_active" id="f_active" checked> نمایش</label>
        <label style="font-size:11.5px;color:var(--text-2);">ترتیب
          <input id="f_sort" name="sort_order" type="number" value="0" class="form-input" style="width:70px;display:inline-block;">
        </label>
        <span style="margin-right:auto;font-size:11px;color:var(--text-3);"><span id="cnt">0</span>/<?= (int)$rows ?></span>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
        <button type="button" class="btn-ghost text-sm px-3" onclick="resetForm()">جدید</button>
        <button type="submit" class="btn-primary text-sm px-4">ذخیره</button>
      </div>
    </form>

    <div style="margin-top:14px;">
      <div style="font-size:11px;color:var(--text-3);margin-bottom:6px;">پیش‌نمایش روی تلویزیون</div>
      <div class="tt-preview" id="prev"></div>
    </div>
  </div>
</div>

<script>
/* پنل، مدرن است — ولی پیش‌نمایش عینا همان چیزی است که تلویزیون می‌گیرد */
(function () {
  var COLS = <?= (int)$cols ?>, ROWS = <?= (int)$rows ?>;
  var f = { no: document.getElementById('f_no'), title: document.getElementById('f_title'),
            body: document.getElementById('f_body'), active: document.getElementById('f_active'),
            sort: document.getElementById('f_sort') };
  var prev = document.getElementById('prev'), cnt = document.getElementById('cnt');

  function clip(text) {
    var lines = String(text || '').replace(/\r\n/g, '\n').split('\n').slice(0, ROWS);
    return lines.map(function (l) { return l.substring(0, COLS); }).join('\n');
  }
  function paint() {
    var text = clip(f.body.value);
    f.body.value = text;
    cnt.textContent = text.split('\n').length;
    prev.textContent = text + '\n';
  }
  f.body.addEventListener('input', paint);

  window.editPage = function (p) {
    document.getElementById('edTitle').textContent = 'ویرایش صفحه ' + p.page_no;
    f.no.value = p.page_no; f.title.value = p.title || '';
    f.body.value = p.body || ''; f.active.checked = +p.is_active === 1;
    f.sort.value = p.sort_order || 0;
    paint(); f.body.focus();
  };
  window.resetForm = function () {
    document.getElementById('ttForm').reset();
    document.getElementById('edTitle').textContent = 'صفحهٔ جدید';
    f.active.checked = true; paint();
  };
  paint();
})();
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
