<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <div>
    <h1 style="font-size:20px;font-weight:800;color:#fff;">
      <i class="fab fa-android" style="color:#3ddc84;margin-left:10px;"></i>اپ Android + OTA Update
    </h1>
    <p style="font-size:12px;color:#475569;margin-top:4px;">مدیریت نصب و بروزرسانی خودکار اپ روی دستگاه‌های Android</p>
  </div>
  <button onclick="document.getElementById('uploadModal').classList.remove('hidden')"
    class="btn-primary text-sm flex items-center gap-2">
    <i class="fas fa-upload text-xs"></i> آپلود نسخه جدید
  </button>
</div>

<!-- راهنمای نصب -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px;">
  <?php foreach([
    ['1','دانلود APK','دانلود اپ از این صفحه','fa-download','#f97316'],
    ['2','نصب روی TV','نصب APK روی Android TV/Box','fa-android','#3ddc84'],
    ['3','تنظیم سرور','وارد کردن IP سرور و کد صفحه','fa-wifi','#60a5fa'],
  ] as [$n,$t,$d,$ic,$c]): ?>
  <div style="background:#16161f;border:1px solid rgba(255,255,255,.07);border-top:3px solid <?=$c?>;border-radius:14px;padding:16px;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
      <div style="width:28px;height:28px;background:<?=$c?>22;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:<?=$c?>;"><?=$n?></div>
      <i class="<?= strpos($ic,'android')!==false?'fab':'fas' ?> <?=$ic?>" style="color:<?=$c?>;font-size:16px;"></i>
      <strong style="color:#fff;font-size:13px;"><?=$t?></strong>
    </div>
    <p style="font-size:11px;color:#64748b;"><?=$d?></p>
  </div>
  <?php endforeach; ?>
</div>

<!-- دانلود APK -->
<?php $latest = array_filter($versions??[], fn($v)=>$v['is_active']); $latest = reset($latest); ?>
<?php if ($latest): ?>
<div style="background:rgba(61,220,132,.05);border:1px solid rgba(61,220,132,.2);border-radius:14px;padding:20px;margin-bottom:20px;display:flex;align-items:center;gap:16px;">
  <div style="width:56px;height:56px;background:rgba(61,220,132,.1);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
    <i class="fab fa-android" style="color:#3ddc84;font-size:28px;"></i>
  </div>
  <div style="flex:1;">
    <div style="font-size:16px;font-weight:700;color:#fff;">نسخه فعال: <?= e($latest['version_name']) ?></div>
    <div style="font-size:12px;color:#64748b;margin-top:3px;">
      کد: <?= $latest['version_code'] ?> · 
      حجم: <?= round($latest['file_size']/1024/1024, 1) ?> MB ·
      <?= date('Y/m/d', strtotime($latest['created_at'])) ?>
    </div>
    <?php if ($latest['changelog']): ?>
    <div style="font-size:11px;color:#94a3b8;margin-top:6px;background:rgba(255,255,255,.03);padding:8px;border-radius:8px;">
      <?= nl2br(e($latest['changelog'])) ?>
    </div>
    <?php endif; ?>
  </div>
  <div style="display:flex;flex-direction:column;gap:8px;">
    <a href="/apk/<?= e($latest['apk_filename']) ?>" download
      style="display:flex;align-items:center;gap:8px;padding:10px 18px;background:linear-gradient(135deg,#3ddc84,#2ba866);border-radius:10px;color:#000;font-weight:700;font-size:13px;text-decoration:none;">
      <i class="fas fa-download text-xs"></i> دانلود APK
    </a>
    <?php if ($latest['force_update']): ?>
    <span style="text-align:center;font-size:10px;color:#f59e0b;background:rgba(245,158,11,.1);padding:3px 10px;border-radius:10px;">⚡ بروزرسانی اجباری</span>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div style="background:#16161f;border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:32px;text-align:center;margin-bottom:20px;color:#475569;">
  <i class="fab fa-android" style="font-size:40px;display:block;margin-bottom:12px;opacity:.2;"></i>
  هنوز هیچ نسخه‌ای آپلود نشده
</div>
<?php endif; ?>

<!-- تاریخچه نسخه‌ها -->
<?php if (!empty($versions)): ?>
<div class="card overflow-hidden">
  <div style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,.06);">
    <h3 style="font-size:13px;font-weight:700;color:#fff;">تاریخچه نسخه‌ها</h3>
  </div>
  <table class="w-full text-sm">
    <thead><tr style="border-bottom:1px solid rgba(255,255,255,.06);font-size:11px;color:#475569;">
      <th style="text-align:right;padding:10px 16px;">نسخه</th>
      <th style="text-align:right;padding:10px;">حجم</th>
      <th style="text-align:right;padding:10px;">تاریخ</th>
      <th style="text-align:right;padding:10px;">وضعیت</th>
      <th style="padding:10px;"></th>
    </tr></thead>
    <tbody>
      <?php foreach ($versions as $v): ?>
      <tr style="border-bottom:1px solid rgba(255,255,255,.04);"
          onmouseenter="this.style.background='rgba(255,255,255,.02)'"
          onmouseleave="this.style.background=''">
        <td style="padding:10px 16px;">
          <div style="font-weight:700;color:#fff;"><?= e($v['version_name']) ?></div>
          <div style="font-size:10px;color:#475569;font-family:monospace;">build <?= $v['version_code'] ?></div>
        </td>
        <td style="padding:10px;color:#64748b;font-size:12px;"><?= round($v['file_size']/1024/1024,1) ?> MB</td>
        <td style="padding:10px;color:#64748b;font-size:12px;"><?= date('Y/m/d H:i',strtotime($v['created_at'])) ?></td>
        <td style="padding:10px;">
          <?php if ($v['is_active']): ?>
          <span style="background:rgba(34,197,94,.1);color:#4ade80;border:1px solid rgba(34,197,94,.3);padding:2px 10px;border-radius:12px;font-size:11px;font-weight:600;">فعال</span>
          <?php else: ?>
          <span style="color:#475569;font-size:11px;">قدیمی</span>
          <?php endif; ?>
        </td>
        <td style="padding:10px;">
          <div style="display:flex;gap:4px;">
            <a href="/apk/<?= e($v['apk_filename']) ?>" download class="btn-ghost text-xs px-2 py-1">
              <i class="fas fa-download text-green-400 text-xs"></i>
            </a>
            <form method="POST" action="/admin/app/<?=$v['id']?>/delete" class="inline">
              <?= csrf_field() ?>
              <button type="submit" class="btn-ghost text-xs px-2 py-1" onclick="return confirm('حذف؟')">
                <i class="fas fa-trash text-red-400 text-xs"></i>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- modal آپلود -->
<div id="uploadModal" class="modal-overlay hidden">
  <div class="modal max-w-lg">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="font-weight:700;color:#fff;"><i class="fas fa-upload text-orange-400 ml-2"></i>آپلود نسخه جدید</h3>
      <button onclick="document.getElementById('uploadModal').classList.add('hidden')" style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;">&times;</button>
    </div>
    <form method="POST" action="/admin/app/upload" enctype="multipart/form-data" class="space-y-4">
      <?= csrf_field() ?>
      <div>
        <label class="form-label">فایل APK *</label>
        <input type="file" name="apk_file" class="form-input" accept=".apk" required>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="form-label">نسخه (نام) *</label>
          <input type="text" name="version_name" class="form-input" placeholder="1.0.0" required>
        </div>
        <div>
          <label class="form-label">کد نسخه *</label>
          <input type="number" name="version_code" class="form-input" placeholder="1" min="1" required>
        </div>
      </div>
      <div>
        <label class="form-label">تغییرات این نسخه</label>
        <textarea name="changelog" class="form-input" rows="3" placeholder="- fix: ...&#10;- feature: ..."></textarea>
      </div>
      <div>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;color:#94a3b8;">
          <input type="checkbox" name="force_update" class="accent-orange-500 w-4 h-4">
          بروزرسانی اجباری (دستگاه‌ها بدون تأیید نصب می‌کنند)
        </label>
      </div>
      <div style="display:flex;gap:10px;padding-top:8px;">
        <button type="submit" class="btn-primary flex-1 py-3">آپلود و انتشار</button>
        <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button>
      </div>
    </form>
  </div>
</div>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
