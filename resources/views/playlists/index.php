<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<div class="flex items-center justify-between mb-5">
  <h1 class="text-xl font-bold text-white flex items-center gap-2">
    <i class="fas fa-list text-orange-400"></i> پلی‌لیست‌ها
  </h1>
  <a href="/admin/playlists/create" class="btn-primary text-sm flex items-center gap-2">
    <i class="fas fa-plus text-xs"></i> پلی‌لیست جدید
  </a>
</div>

<?php
$list = $playlists['data'] ?? $playlists ?? [];
?>

<?php if (empty($list)): ?>
<div class="card text-center py-16">
  <i class="fas fa-list text-5xl text-slate-700 mb-4 block"></i>
  <p class="text-slate-500 mb-4">پلی‌لیستی ساخته نشده</p>
  <a href="/admin/playlists/create" class="btn-primary text-sm">اولین پلی‌لیست را بساز</a>
</div>
<?php else: ?>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
  <?php foreach ($list as $p): ?>
  <div class="card hover:border-white/20 transition-all" style="border:1px solid rgba(255,255,255,0.08);">

    <div class="flex items-start justify-between mb-3">
      <div class="flex items-center gap-3">
        <div style="width:42px;height:42px;background:rgba(249,115,22,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fas fa-film" style="color:#f97316;font-size:17px;"></i>
        </div>
        <div>
          <h3 style="font-size:14px;font-weight:700;color:#fff;"><?= e($p['name']) ?></h3>
          <span style="font-size:11px;color:#475569;">
            <?= (int)($p['item_count'] ?? 0) ?> رسانه
            <?php if ($p['default_duration'] ?? 0): ?>
            · <?= e($p['default_duration']) ?>ث پیش‌فرض
            <?php endif; ?>
          </span>
        </div>
      </div>
      <span class="<?= ($p['is_active'] ?? 1) ? 'badge-online' : 'badge-offline' ?>">
        <?= ($p['is_active'] ?? 1) ? 'فعال' : 'غیرفعال' ?>
      </span>
    </div>

    <?php if (!empty($p['description'])): ?>
    <p style="font-size:12px;color:#64748b;margin-bottom:12px;line-height:1.5;"><?= e($p['description']) ?></p>
    <?php endif; ?>

    <!-- تنظیمات -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
      <?php if (!empty($p['transition'])): ?>
      <span style="font-size:11px;background:rgba(255,255,255,0.05);color:#64748b;padding:2px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.07);">
        <?= e($p['transition']) ?>
      </span>
      <?php endif; ?>
      <?php if (!empty($p['shuffle'])): ?>
      <span style="font-size:11px;background:rgba(59,130,246,0.1);color:#60a5fa;padding:2px 8px;border-radius:6px;border:1px solid rgba(59,130,246,0.2);">
        🔀 تصادفی
      </span>
      <?php endif; ?>
    </div>

    <!-- Buttons -->
    <div style="display:flex;gap:6px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.05);">
      <!-- مدیریت آیتم‌ها - مهم‌ترین دکمه -->
      <a href="/admin/playlists/<?= $p['id'] ?>"
        class="btn-primary text-xs flex-1 flex items-center justify-center gap-1.5 py-2">
        <i class="fas fa-photo-film text-xs"></i> مدیریت رسانه‌ها
      </a>
      <a href="/admin/playlists/<?= $p['id'] ?>/edit"
        class="btn-ghost text-xs px-3 py-2" title="ویرایش تنظیمات">
        <i class="fas fa-gear text-slate-400 text-xs"></i>
      </a>
      <form method="POST" action="/admin/playlists/<?= $p['id'] ?>/delete" class="inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn-danger text-xs px-3 py-2"
          onclick="return confirm('پلی‌لیست «<?= e($p['name']) ?>» حذف شود؟')">
          <i class="fas fa-trash text-xs"></i>
        </button>
      </form>
    </div>

  </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
