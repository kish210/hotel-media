<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<?php /** @var array $groups */ ?>
<style>
  .sg-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:16px;margin-bottom:14px;}
  .sg-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
  .sg-dot{width:14px;height:14px;border-radius:50%;flex-shrink:0;}
</style>

<div style="margin-bottom:18px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;"><i class="fas fa-layer-group" style="color:#ef4444;margin-left:10px;"></i>گروه‌های IPTV</h1>
  <p style="font-size:12px;color:#64748b;margin-top:4px;line-height:1.9;">
    گروه، صفحه‌ها، اتاق‌ها و منوهای IPTV را دسته‌بندی می‌کند (مثلا «طبقه ۱»، «سوئیت‌ها»). این غیر از «گروه کانال» است که کانال‌ها را دسته می‌کند.
  </p>
</div>

<div class="sg-card">
  <form method="post" action="/admin/iptv/screen-groups" class="sg-row">
    <?= csrf_field() ?>
    <input name="name" class="form-input" style="max-width:240px;" placeholder="نام گروه" required maxlength="100">
    <input name="description" class="form-input" style="max-width:300px;" placeholder="توضیح (اختیاری)" maxlength="255">
    <input type="color" name="color" value="#ef4444" style="width:42px;height:36px;border:0;background:none;">
    <button class="btn-primary text-sm"><i class="fas fa-plus ml-1"></i>افزودن گروه</button>
  </form>
</div>

<?php if (!$groups): ?>
  <div class="sg-card" style="text-align:center;color:#64748b;font-size:13px;">هنوز گروهی نساخته‌اید.</div>
<?php endif; ?>

<?php foreach ($groups as $g): ?>
<div class="sg-card">
  <form method="post" action="/admin/iptv/screen-groups/<?= (int)$g['id'] ?>" class="sg-row">
    <?= csrf_field() ?>
    <span class="sg-dot" style="background:<?= e($g['color'] ?: '#ef4444') ?>;"></span>
    <input name="name" class="form-input" style="max-width:220px;" value="<?= e($g['name']) ?>" required maxlength="100">
    <input name="description" class="form-input" style="max-width:280px;" value="<?= e((string)($g['description'] ?? '')) ?>" placeholder="توضیح" maxlength="255">
    <input type="color" name="color" value="<?= e($g['color'] ?: '#ef4444') ?>" style="width:42px;height:36px;border:0;background:none;">
    <span style="font-size:11px;color:#64748b;"><?= (int)$g['screens_count'] ?> صفحه · <?= (int)$g['menus_count'] ?> منو</span>
    <button class="btn-ghost text-sm">ذخیره</button>
  </form>
  <form method="post" action="/admin/screens/groups/<?= (int)$g['id'] ?>/theme" class="sg-row" style="margin-top:8px;">
    <?= csrf_field() ?>
    <input type="hidden" name="back" value="iptv">
    <label style="font-size:12px;color:#94a3b8;">تم این گروه:</label>
    <select name="theme" class="form-input" style="max-width:240px;" onchange="this.form.submit()">
      <?php foreach (['' => 'پیش‌فرض (ارث‌بری)', 'classic' => 'کلاسیک — شبکهٔ کاشی', 'hero' => 'هیرو — سینمایی', 'sidebar' => 'ستونی — منوی کنار صفحه', 'luxe' => 'لوکس — مینیمال'] as $k => $v): ?>
        <option value="<?= e($k) ?>"<?= (string)($g['theme'] ?? '') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <form method="post" action="/admin/iptv/screen-groups/<?= (int)$g['id'] ?>/delete" style="margin-top:8px;"
        onsubmit="return confirm('گروه حذف شود؟ صفحه‌ها و منوهای آن فقط بی‌گروه می‌شوند و پاک نمی‌شوند.')">
    <?= csrf_field() ?>
    <button class="btn-ghost text-sm" style="color:#ef4444;border-color:rgba(239,68,68,.3);"><i class="fas fa-trash ml-1"></i>حذف گروه</button>
  </form>
</div>
<?php endforeach; ?>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
