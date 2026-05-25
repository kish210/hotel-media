<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<h1 class="text-xl font-bold text-white mb-6 flex items-center gap-2"><i class="fas fa-gear text-orange-400"></i> تنظیمات</h1>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
  <!-- Organization settings -->
  <div class="card">
    <h2 class="font-bold text-white mb-4 text-sm"><i class="fas fa-building text-blue-400 ml-2"></i> اطلاعات سازمان</h2>
    <form method="POST" action="/admin/settings" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="tenant">
      <div><label class="form-label">نام سازمان</label>
        <input type="text" name="name" class="form-input" value="<?=e($tenant['name']??'')?>"></div>
      <button type="submit" class="btn-primary text-sm px-6">ذخیره</button>
    </form>
  </div>

  <!-- Add Location -->
  <div class="card">
    <h2 class="font-bold text-white mb-4 text-sm"><i class="fas fa-location-dot text-orange-400 ml-2"></i> شعبه جدید</h2>
    <form method="POST" action="/admin/settings" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="location">
      <div><label class="form-label">نام شعبه *</label><input type="text" name="name" class="form-input" required></div>
      <div><label class="form-label">آدرس</label><input type="text" name="address" class="form-input"></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">شهر</label><input type="text" name="city" class="form-input" value="تهران"></div>
        <div><label class="form-label">کشور</label><input type="text" name="country" class="form-input" value="ایران"></div>
      </div>
      <button type="submit" class="btn-primary text-sm px-6">اضافه کردن شعبه</button>
    </form>
  </div>

  <!-- Locations list -->
  <?php if (!empty($locations)): ?>
  <div class="card lg:col-span-2">
    <h2 class="font-bold text-white mb-4 text-sm"><i class="fas fa-map-marker-alt text-green-400 ml-2"></i> شعبه‌ها</h2>
    <div class="overflow-hidden">
      <table class="w-full text-sm">
        <thead><tr class="border-b border-white/5 text-xs text-slate-500 uppercase">
          <th class="text-right p-3">نام</th><th class="text-right p-3">شهر</th><th class="text-right p-3">آدرس</th>
        </tr></thead>
        <tbody>
          <?php foreach ($locations as $loc): ?>
          <tr class="border-b border-white/3">
            <td class="p-3 text-white"><?=e($loc['name'])?></td>
            <td class="p-3 text-slate-400"><?=e($loc['city']??'—')?></td>
            <td class="p-3 text-slate-400"><?=e($loc['address']??'—')?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- System info -->
  <div class="card">
    <h2 class="font-bold text-white mb-4 text-sm"><i class="fas fa-circle-info text-indigo-400 ml-2"></i> اطلاعات سیستم</h2>
    <?php foreach ([
      'نسخه SignageCMS' => 'v1.1.0',
      'PHP' => PHP_VERSION,
      'محیط' => env('APP_ENV','production'),
      'دیباگ' => env('APP_DEBUG',false) ? 'فعال ⚠' : 'غیرفعال ✅',
      'Timezone' => env('APP_TIMEZONE','Asia/Tehran'),
    ] as $k => $v): ?>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:12px;">
      <span style="color:#64748b;"><?=e($k)?></span>
      <span style="color:#94a3b8;font-family:monospace;"><?=e($v)?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
