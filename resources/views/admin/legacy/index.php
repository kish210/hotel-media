<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<?php
/** @var array $runs @var string $default */
$preview = $_SESSION['legacy_preview'] ?? null;
unset($_SESSION['legacy_preview']);
?>
<style>
  .lg-card{background:var(--surface-2);border:1px solid var(--line-1);border-radius:var(--r-md);padding:16px;}
  .lg-table{width:100%;border-collapse:collapse;font-size:12.5px;}
  .lg-table th{color:var(--text-3);text-align:right;padding:9px 8px;border-bottom:1px solid var(--line-1);font-weight:500;}
  .lg-table td{color:var(--text-2);padding:8px;border-bottom:1px solid var(--line-1);}
  .lg-num{font-family:var(--font-num);font-variant-numeric:tabular-nums;}
</style>

<div style="margin-bottom:16px;">
  <h1 style="font-size:20px;font-weight:800;color:var(--text-1);">
    <i class="fas fa-arrow-right-to-bracket" style="color:var(--sama-blue-400);margin-left:10px;"></i>مهاجرت از سیستم قدیمی
  </h1>
  <p style="font-size:12px;color:var(--text-3);margin-top:4px;">
    کانال‌ها، اتاق‌ها، لوگوها و تنظیمات سیستم VINAK/MMS را وارد می‌کند — بدون پاک‌کردن دادهٔ موجود
  </p>
</div>

<div class="lg-card" style="margin-bottom:14px;">
  <form method="post" action="/admin/legacy/preview" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
    <?= csrf_field() ?>
    <label style="flex:1;min-width:260px;font-size:11px;color:var(--text-3);">
      مسیر پوشهٔ پشتیبان (پوشه‌ای که <code>channels.json</code> دارد، یا ریشهٔ backups)
      <input name="path" class="form-input" style="direction:ltr;text-align:left;" value="<?= e($default) ?>" required>
    </label>
    <button class="btn-ghost text-sm px-3">پیش‌نمایش (بدون تغییر)</button>
  </form>

  <form method="post" action="/admin/legacy/import" style="margin-top:12px;display:flex;gap:14px;flex-wrap:wrap;align-items:center;">
    <?= csrf_field() ?>
    <input type="hidden" name="path" id="importPath" value="<?= e($default) ?>">
    <label style="font-size:11.5px;color:var(--text-2);"><input type="checkbox" name="with_teletext" value="1"> صفحه‌های تله‌تکست</label>
    <button class="btn-primary text-sm px-4" data-confirm="دادهٔ سیستم قدیمی روی دیتابیس این هتل وارد شود؟" data-confirm-name="مهاجرت">
      اجرای مهاجرت
    </button>
  </form>
</div>

<?php if (is_array($preview)): ?>
<div class="lg-card" style="margin-bottom:14px;border-color:rgba(75,95,151,.45);">
  <h3 style="font-size:14px;font-weight:700;color:var(--text-1);margin-bottom:8px;">نتیجهٔ پیش‌نمایش</h3>
  <div style="display:flex;gap:18px;flex-wrap:wrap;font-size:12px;color:var(--text-2);">
    <span>کانال: <b class="lg-num" style="color:var(--text-1)"><?= (int)($preview['channels'] ?? 0) ?></b></span>
    <span>اتاق: <b class="lg-num" style="color:var(--text-1)"><?= (int)($preview['rooms'] ?? 0) ?></b></span>
    <span>لوگو: <b class="lg-num" style="color:var(--text-1)"><?= (int)($preview['logos'] ?? 0) ?></b></span>
    <span>تنظیم: <b class="lg-num" style="color:var(--text-1)"><?= (int)($preview['settings'] ?? 0) ?></b></span>
    <span>کاتالوگ فیلم: <b class="lg-num" style="color:var(--text-1)"><?= (int)($preview['vod'] ?? 0) ?></b></span>
  </div>
  <div style="font-size:11px;color:var(--text-3);margin-top:8px;direction:ltr;text-align:left;">
    <?= e($preview['source'] ?? '') ?>
  </div>
  <?php foreach (($preview['warnings'] ?? []) as $w): ?>
    <div style="font-size:11.5px;color:var(--warn);margin-top:6px;"><?= e($w) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="lg-card" style="overflow-x:auto;">
  <h3 style="font-size:14px;font-weight:700;color:var(--text-1);margin-bottom:10px;">سابقهٔ مهاجرت</h3>
  <table class="lg-table">
    <thead><tr><th>تاریخ</th><th>حالت</th><th>کانال</th><th>اتاق</th><th>لوگو</th><th>مسیر</th></tr></thead>
    <tbody>
    <?php if (!$runs): ?>
      <tr><td colspan="6" style="text-align:center;color:var(--text-3);padding:22px;">هنوز مهاجرتی اجرا نشده</td></tr>
    <?php endif; ?>
    <?php foreach ($runs as $r): ?>
      <tr>
        <td class="lg-num"><?= e(jalaliDate(strtotime((string)$r['created_at']), true)) ?></td>
        <td><?= e($r['mode']) ?></td>
        <td class="lg-num"><?= (int)$r['channels'] ?></td>
        <td class="lg-num"><?= (int)$r['rooms'] ?></td>
        <td class="lg-num"><?= (int)$r['logos'] ?></td>
        <td style="direction:ltr;text-align:left;font-size:11px;color:var(--text-3);"><?= e($r['source']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
/* مسیر یک‌بار تایپ می‌شود و هر دو فرم از همان استفاده می‌کنند */
(function () {
  var inp = document.querySelector('input[name="path"]');
  var hidden = document.getElementById('importPath');
  if (!inp || !hidden) return;
  inp.addEventListener('input', function () { hidden.value = inp.value; });
})();
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
