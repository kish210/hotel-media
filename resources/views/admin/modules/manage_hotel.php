<?php
use App\Core\{Auth, Database};
$db  = Database::getInstance();
$tid = Auth::tenantId();

$events    = $db->rows("SELECT * FROM hotel_events WHERE tenant_id=? ORDER BY start_at ASC LIMIT 50", [$tid]);
$amenities = $db->rows("SELECT * FROM hotel_amenities WHERE tenant_id=? ORDER BY sort_order ASC", [$tid]);
$hotelInfo = $db->row("SELECT * FROM hotel_info WHERE tenant_id=? LIMIT 1", [$tid]);

include VIEWS_PATH . '/partials/layout.php';
?>

<div class="flex items-center justify-between mb-5">
  <h1 class="text-xl font-bold text-white flex items-center gap-2">
    <i class="fas fa-hotel text-yellow-400"></i> اطلاع‌رسانی هتل
  </h1>
</div>

<!-- تب‌ها -->
<div style="display:flex;gap:4px;background:rgba(0,0,0,0.3);border-radius:10px;padding:4px;margin-bottom:20px;width:fit-content;">
  <?php foreach(['events'=>'رویدادها','amenities'=>'امکانات','info'=>'اطلاعات هتل'] as $t=>$l): ?>
  <button onclick="showTab('<?=$t?>')" id="tab-<?=$t?>"
    class="tab-btn px-4 py-2 text-sm rounded-8 font-semibold transition-all"
    style="border-radius:7px;<?=$t==='events'?'background:rgba(249,115,22,0.2);color:#f97316;':'color:#64748b;'?>">
    <?=$l?>
  </button>
  <?php endforeach; ?>
</div>

<!-- رویدادها -->
<div id="section-events">
  <div class="flex justify-between items-center mb-3">
    <span class="text-sm font-bold text-white">رویدادهای هتل (<?= count($events) ?>)</span>
    <button onclick="document.getElementById('addEventModal').classList.remove('hidden')"
      class="btn-primary text-xs flex items-center gap-1.5"><i class="fas fa-plus text-xs"></i> رویداد جدید</button>
  </div>
  <div class="card overflow-hidden">
    <table class="w-full text-sm">
      <thead><tr class="border-b border-white/5 text-xs text-slate-500 uppercase">
        <th class="text-right p-3">عنوان</th><th class="text-right p-3">سالن</th>
        <th class="text-right p-3">شروع</th><th class="text-right p-3">نوع</th><th class="p-3"></th>
      </tr></thead>
      <tbody>
        <?php if (empty($events)): ?>
        <tr><td colspan="5" class="text-center py-8 text-slate-600">رویدادی ثبت نشده</td></tr>
        <?php else: foreach ($events as $ev): ?>
        <tr class="table-row border-b border-white/3">
          <td class="p-3 text-white font-medium"><?= e($ev['title']) ?></td>
          <td class="p-3 text-slate-400"><?= e($ev['hall_name'] ?? $ev['location'] ?? '—') ?></td>
          <td class="p-3 text-slate-400 font-mono text-xs"><?= date('Y/m/d H:i', strtotime($ev['start_at'])) ?></td>
          <td class="p-3"><span class="badge-pending px-2 py-0.5 text-xs border rounded-full"><?= e($ev['type']) ?></span></td>
          <td class="p-3">
            <form method="POST" action="/admin/modules/hotel/events/<?=$ev['id']?>/delete" class="inline">
              <?= csrf_field() ?><button type="submit" class="btn-ghost text-xs px-2 py-1" onclick="return confirm('حذف؟')">
              <i class="fas fa-trash text-red-400 text-xs"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- امکانات -->
<div id="section-amenities" style="display:none;">
  <div class="flex justify-between items-center mb-3">
    <span class="text-sm font-bold text-white">امکانات هتل</span>
    <button onclick="document.getElementById('addAmenityModal').classList.remove('hidden')"
      class="btn-primary text-xs flex items-center gap-1.5"><i class="fas fa-plus text-xs"></i> امکان جدید</button>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($amenities as $a): ?>
    <div class="card flex items-center gap-3" style="border:1px solid rgba(255,255,255,0.07);">
      <div style="width:40px;height:40px;background:rgba(212,175,55,0.12);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="<?= e($a['icon']) ?>" style="color:#d4af37;font-size:16px;"></i>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-white"><?= e($a['name']) ?></p>
        <p class="text-xs text-slate-500"><?= e($a['hours'] ?? '') ?> · <?= e($a['floor'] ?? '') ?></p>
      </div>
      <form method="POST" action="/admin/modules/hotel/amenities/<?=$a['id']?>/delete" class="inline">
        <?= csrf_field() ?><button type="submit" class="btn-ghost text-xs px-2 py-1" onclick="return confirm('حذف؟')">
        <i class="fas fa-trash text-red-400 text-xs"></i></button>
      </form>
    </div>
    <?php endforeach; ?>
    <?php if (empty($amenities)): ?><p class="text-slate-600 text-sm">امکانی ثبت نشده</p><?php endif; ?>
  </div>
</div>

<!-- اطلاعات هتل -->
<div id="section-info" style="display:none;">
  <div class="card max-w-xl">
    <h3 class="font-bold text-white text-sm mb-4">اطلاعات هتل</h3>
    <form method="POST" action="/admin/modules/hotel/info" class="space-y-3">
      <?= csrf_field() ?>
      <div><label class="form-label">نام هتل (فارسی)</label>
        <input type="text" name="hotel_name" class="form-input" value="<?= e($hotelInfo['hotel_name']??'') ?>"></div>
      <div><label class="form-label">نام هتل (انگلیسی)</label>
        <input type="text" name="hotel_name_en" class="form-input" value="<?= e($hotelInfo['hotel_name_en']??'') ?>"></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">ساعت check-in</label>
          <input type="time" name="checkin_time" class="form-input" value="<?= e($hotelInfo['checkin_time']??'14:00') ?>"></div>
        <div><label class="form-label">ساعت check-out</label>
          <input type="time" name="checkout_time" class="form-input" value="<?= e($hotelInfo['checkout_time']??'12:00') ?>"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">نام WiFi</label>
          <input type="text" name="wifi_name" class="form-input" value="<?= e($hotelInfo['wifi_name']??'') ?>"></div>
        <div><label class="form-label">رمز WiFi</label>
          <input type="text" name="wifi_pass" class="form-input" value="<?= e($hotelInfo['wifi_pass']??'') ?>"></div>
      </div>
      <button type="submit" class="btn-primary text-sm px-6">ذخیره</button>
    </form>
  </div>
</div>

<!-- Modal رویداد -->
<div id="addEventModal" class="modal-overlay hidden">
  <div class="modal max-w-lg">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-bold text-white">رویداد جدید</h3>
      <button onclick="document.getElementById('addEventModal').classList.add('hidden')" class="text-slate-500 hover:text-white">&times;</button>
    </div>
    <form method="POST" action="/admin/modules/hotel/events" class="space-y-3">
      <?= csrf_field() ?>
      <div><label class="form-label">عنوان رویداد *</label><input type="text" name="title" class="form-input" required></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">سالن</label><input type="text" name="hall_name" class="form-input"></div>
        <div><label class="form-label">نوع</label>
          <select name="type" class="form-input">
            <?php foreach(['conference'=>'کنفرانس','wedding'=>'عروسی','seminar'=>'سمینار','party'=>'مهمانی','exhibition'=>'نمایشگاه','other'=>'سایر'] as $v=>$l): ?>
            <option value="<?=$v?>"><?=$l?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">شروع *</label><input type="datetime-local" name="start_at" class="form-input" required></div>
        <div><label class="form-label">پایان</label><input type="datetime-local" name="end_at" class="form-input"></div>
      </div>
      <div><label class="form-label">توضیحات</label><textarea name="description" class="form-input" rows="2"></textarea></div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn-primary flex-1">ثبت رویداد</button>
        <button type="button" onclick="document.getElementById('addEventModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal امکان -->
<div id="addAmenityModal" class="modal-overlay hidden">
  <div class="modal max-w-md">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-bold text-white">امکان جدید</h3>
      <button onclick="document.getElementById('addAmenityModal').classList.add('hidden')" class="text-slate-500 hover:text-white">&times;</button>
    </div>
    <form method="POST" action="/admin/modules/hotel/amenities" class="space-y-3">
      <?= csrf_field() ?>
      <div><label class="form-label">نام *</label><input type="text" name="name" class="form-input" required></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">آیکون (Font Awesome)</label>
          <input type="text" name="icon" class="form-input" value="fas fa-star" placeholder="fas fa-pool"></div>
        <div><label class="form-label">طبقه</label><input type="text" name="floor" class="form-input"></div>
      </div>
      <div><label class="form-label">ساعات کاری</label><input type="text" name="hours" class="form-input" placeholder="08:00 - 22:00"></div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn-primary flex-1">افزودن</button>
        <button type="button" onclick="document.getElementById('addAmenityModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
function showTab(name) {
  ['events','amenities','info'].forEach(t => {
    document.getElementById('section-' + t).style.display = t === name ? '' : 'none';
    const btn = document.getElementById('tab-' + t);
    btn.style.background = t === name ? 'rgba(249,115,22,0.2)' : '';
    btn.style.color = t === name ? '#f97316' : '#64748b';
  });
}
JS;
?>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
