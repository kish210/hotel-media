<?php include VIEWS_PATH . '/partials/layout.php';
$isEdit   = isset($playlist);
$title    = $isEdit ? 'ویرایش پلی‌لیست' : 'پلی‌لیست جدید';
$action   = $isEdit ? '/admin/playlists/' . $playlist['id'] : '/admin/playlists';
$playlist = $playlist ?? [];
?>

<div class="flex items-center gap-3 mb-6">
  <a href="/admin/playlists" class="btn-ghost text-sm px-3"><i class="fas fa-arrow-right text-xs"></i></a>
  <h1 class="text-xl font-bold text-white"><?= e($title) ?></h1>
</div>

<div class="max-w-2xl">
  <div class="card p-6">
    <form method="POST" action="<?= e($action) ?>" class="space-y-4">
      <?= csrf_field() ?>

      <div>
        <label class="form-label">نام پلی‌لیست *</label>
        <input type="text" name="name" class="form-input" required
          value="<?= e($playlist['name'] ?? '') ?>" placeholder="مثلاً: منوی صبحانه">
      </div>

      <div>
        <label class="form-label">توضیحات</label>
        <input type="text" name="description" class="form-input"
          value="<?= e($playlist['description'] ?? '') ?>" placeholder="توضیح اختیاری">
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">چیدمان (Layout)</label>
          <select name="layout_id" class="form-input">
            <option value="">— بدون چیدمان —</option>
            <?php foreach ($layouts ?? [] as $l): ?>
            <option value="<?= $l['id'] ?>" <?= ($playlist['layout_id'] ?? '') == $l['id'] ? 'selected' : '' ?>>
              <?= e($l['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">زمان پیش‌فرض هر آیتم (ثانیه)</label>
          <input type="number" name="default_duration" class="form-input"
            value="<?= e($playlist['default_duration'] ?? 10) ?>" min="1" max="3600">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">انیمیشن انتقال</label>
          <select name="transition" class="form-input">
            <?php foreach (['fade'=>'محو','slide'=>'لغزش','zoom'=>'زوم','none'=>'بدون'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($playlist['transition'] ?? 'fade') === $v ? 'selected' : '' ?>>
              <?= $l ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="flex flex-col gap-2 pt-5">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="shuffle" value="1" class="accent-orange-500 w-4 h-4"
              <?= !empty($playlist['shuffle']) ? 'checked' : '' ?>>
            <span class="text-sm text-slate-400">پخش تصادفی</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" class="accent-orange-500 w-4 h-4"
              <?= ($playlist['is_active'] ?? 1) ? 'checked' : '' ?>>
            <span class="text-sm text-slate-400">فعال</span>
          </label>
        </div>
      </div>

      <div class="flex gap-3 pt-4 border-t border-white/5">
        <button type="submit" class="btn-primary flex-1 py-3">
          <?= $isEdit ? 'ذخیره تغییرات' : 'ایجاد پلی‌لیست' ?>
        </button>
        <a href="/admin/playlists" class="btn-ghost px-6">لغو</a>
      </div>
    </form>
  </div>
</div>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
