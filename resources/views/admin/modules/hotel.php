<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-xl font-bold text-white flex items-center gap-2"><i class="fas fa-hotel text-yellow-400"></i> مدیریت هتل</h1>
  <a href="/admin/modules" class="btn-ghost text-sm px-4"><i class="fas fa-arrow-right text-xs ml-1"></i> ماژول‌ها</a>
</div>

<!-- Tabs -->
<div class="flex gap-1 bg-white/5 rounded-xl p-1 mb-6 w-fit">
  <?php foreach ([['info','اطلاعات هتل','hotel'],['events','رویدادها','calendar-star'],['amenities','امکانات','spa'],['rs','سرویس اتاق','concierge-bell'],['attractions','جاذبه‌ها','map-location-dot']] as [$tab,$label,$icon]): ?>
  <button onclick="showTab('<?=$tab?>')" id="tab-<?=$tab?>"
    class="hotel-tab px-4 py-2 rounded-lg text-sm transition-all <?=$tab==='info'?'bg-yellow-500 text-white font-semibold':'text-slate-400 hover:text-white'?>">
    <i class="fas fa-<?=$icon?> ml-1 text-xs"></i><?=$label?>
  </button>
  <?php endforeach; ?>
</div>

<!-- Info Tab -->
<div id="panel-info">
  <div class="card p-6 max-w-2xl">
    <h2 class="font-bold text-white mb-4 text-sm"><i class="fas fa-building text-yellow-400 ml-2"></i> اطلاعات پایه هتل</h2>
    <form method="POST" action="/admin/modules/hotel/info" class="grid grid-cols-2 gap-4">
      <?= csrf_field() ?>
      <div><label class="form-label">نام هتل *</label><input type="text" name="hotel_name" class="form-input" value="<?=e($info['hotel_name']??'')?>" required></div>
      <div><label class="form-label">نام انگلیسی</label><input type="text" name="hotel_name_en" class="form-input" value="<?=e($info['hotel_name_en']??'')?>"></div>
      <div class="col-span-2"><label class="form-label">شعار</label><input type="text" name="slogan" class="form-input" value="<?=e($info['slogan']??'')?>"></div>
      <div><label class="form-label">تلفن</label><input type="text" name="phone" class="form-input" value="<?=e($info['phone']??'')?>"></div>
      <div><label class="form-label">ایمیل</label><input type="email" name="email" class="form-input" value="<?=e($info['email']??'')?>"></div>
      <div><label class="form-label">ساعت ورود</label><input type="time" name="checkin_time" class="form-input" value="<?=e($info['checkin_time']??'14:00')?>"></div>
      <div><label class="form-label">ساعت خروج</label><input type="time" name="checkout_time" class="form-input" value="<?=e($info['checkout_time']??'12:00')?>"></div>
      <div><label class="form-label">نام Wi-Fi</label><input type="text" name="wifi_name" class="form-input" value="<?=e($info['wifi_name']??'')?>"></div>
      <div><label class="form-label">پسورد Wi-Fi</label><input type="text" name="wifi_pass" class="form-input" value="<?=e($info['wifi_pass']??'')?>"></div>
      <div><label class="form-label">رتبه (ستاره)</label>
        <select name="stars" class="form-input">
          <?php for ($i=5;$i>=1;$i--): ?><option value="<?=$i?>" <?=($info['stars']??5)==$i?'selected':''?>><?=str_repeat('★',$i)?></option><?php endfor; ?>
        </select>
      </div>
      <div class="col-span-2"><label class="form-label">آدرس</label><textarea name="address" class="form-input" rows="2"><?=e($info['address']??'')?></textarea></div>
      <div class="col-span-2 pt-2 border-t border-white/5"><button type="submit" class="btn-primary px-8">ذخیره اطلاعات</button></div>
    </form>
  </div>
</div>

<!-- Events Tab -->
<div id="panel-events" class="hidden">
  <div class="flex justify-end mb-4"><button onclick="document.getElementById('addEventModal').classList.remove('hidden')" class="btn-primary text-sm flex items-center gap-2"><i class="fas fa-plus text-xs"></i> رویداد جدید</button></div>
  <div class="card overflow-hidden">
    <table class="w-full text-sm">
      <thead><tr class="border-b border-white/5 text-xs text-slate-500 uppercase">
        <th class="text-right p-3">عنوان</th><th class="text-right p-3">نوع</th><th class="text-right p-3">زمان شروع</th><th class="text-right p-3">سالن</th><th class="text-right p-3">ظرفیت</th><th class="p-3"></th>
      </tr></thead>
      <tbody>
        <?php if (empty($events)): ?>
        <tr><td colspan="6" class="text-center py-10 text-slate-600"><i class="fas fa-calendar-xmark text-3xl mb-2 block opacity-30"></i>رویدادی ثبت نشده</td></tr>
        <?php else: foreach ($events as $ev): ?>
        <tr class="border-b border-white/3 table-row">
          <td class="p-3"><div class="font-medium text-white"><?=e($ev['title'])?></div><?php if ($ev['organizer']): ?><div class="text-xs text-slate-500"><?=e($ev['organizer'])?></div><?php endif; ?></td>
          <td class="p-3"><span class="text-xs px-2 py-1 rounded-full bg-yellow-500/15 text-yellow-400 border border-yellow-500/30"><?=e($ev['type'])?></span></td>
          <td class="p-3 text-slate-300 font-mono text-sm"><?=date('Y/m/d H:i',strtotime($ev['start_at']))?></td>
          <td class="p-3 text-slate-400"><?=e($ev['hall_name']??$ev['location']??'—')?></td>
          <td class="p-3 text-slate-400"><?=$ev['capacity']?e($ev['capacity']):'—'?></td>
          <td class="p-3">
            <form method="POST" action="/admin/modules/hotel/events/<?=$ev['id']?>/delete" class="inline">
              <?= csrf_field() ?><button type="submit" class="btn-danger text-xs px-2 py-1" onclick="return confirm('حذف شود؟')"><i class="fas fa-trash text-xs"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Amenities Tab -->
<div id="panel-amenities" class="hidden">
  <div class="flex justify-end mb-4"><button onclick="document.getElementById('addAmenityModal').classList.remove('hidden')" class="btn-primary text-sm flex items-center gap-2"><i class="fas fa-plus text-xs"></i> امکانات جدید</button></div>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($amenities as $a): ?>
    <div class="card p-4 flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-yellow-500/10 border border-yellow-500/20 flex-shrink-0">
        <i class="<?=e($a['icon']??'fas fa-star')?> text-yellow-400"></i>
      </div>
      <div class="flex-1 min-w-0">
        <p class="font-medium text-white text-sm"><?=e($a['name'])?></p>
        <?php if ($a['floor']): ?><p class="text-xs text-slate-500">طبقه <?=e($a['floor'])?></p><?php endif; ?>
        <?php if ($a['hours']): ?><p class="text-xs text-slate-600"><?=e($a['hours'])?></p><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($amenities)): ?><div class="col-span-full text-center py-10 text-slate-600">امکاناتی ثبت نشده</div><?php endif; ?>
  </div>
</div>

<!-- Room Service Tab -->
<div id="panel-rs" class="hidden">
  <div class="card overflow-hidden">
    <table class="w-full text-sm">
      <thead><tr class="border-b border-white/5 text-xs text-slate-500 uppercase">
        <th class="text-right p-3">دسته</th><th class="text-right p-3">نام</th><th class="text-right p-3">قیمت</th><th class="text-right p-3">وضعیت</th>
      </tr></thead>
      <tbody>
        <?php if (empty($rs_items)): ?>
        <tr><td colspan="4" class="text-center py-10 text-slate-600">آیتمی ثبت نشده</td></tr>
        <?php else: foreach ($rs_items as $r): ?>
        <tr class="border-b border-white/3 table-row">
          <td class="p-3 text-slate-400"><?=e($r['category'])?></td>
          <td class="p-3 text-white font-medium"><?=e($r['name'])?></td>
          <td class="p-3 text-yellow-400 font-bold"><?=number_format($r['price'])?> تومان</td>
          <td class="p-3"><span class="<?=$r['is_available']?'badge-online':'badge-offline'?> px-2 py-0.5 rounded-full text-xs border"><?=$r['is_available']?'موجود':'ناموجود'?></span></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Attractions Tab -->
<div id="panel-attractions" class="hidden">
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($attractions as $a): ?>
    <div class="card p-4">
      <?php if ($a['image']): ?><img src="<?=e($a['image'])?>" class="w-full h-32 object-cover rounded-lg mb-3"><<?php endif; ?>
      <p class="font-bold text-white"><?=e($a['name'])?></p>
      <?php if ($a['distance']): ?><p class="text-xs text-yellow-400 mt-1"><i class="fas fa-location-dot ml-1"></i><?=e($a['distance'])?></p><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($attractions)): ?><div class="col-span-full text-center py-10 text-slate-600">جاذبه‌ای ثبت نشده</div><?php endif; ?>
  </div>
</div>

<!-- Modals -->
<div id="addEventModal" class="modal-overlay hidden">
  <div class="modal max-w-lg" style="max-height:90vh;overflow-y:auto;">
    <div class="flex items-center justify-between mb-4"><h3 class="font-bold text-white">رویداد جدید</h3><button onclick="document.getElementById('addEventModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fas fa-xmark"></i></button></div>
    <form method="POST" action="/admin/modules/hotel/events" class="space-y-3">
      <?= csrf_field() ?>
      <div><label class="form-label">عنوان *</label><input type="text" name="title" class="form-input" required></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">نوع</label>
          <select name="type" class="form-input">
            <?php foreach (['conference'=>'کنفرانس','wedding'=>'عروسی','seminar'=>'سمینار','party'=>'مهمانی','exhibition'=>'نمایشگاه','other'=>'سایر'] as $v=>$l): ?><option value="<?=$v?>"><?=$l?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="form-label">رنگ</label><input type="color" name="color" value="#d4af37" class="form-input h-10"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">شروع *</label><input type="datetime-local" name="start_at" class="form-input" required></div>
        <div><label class="form-label">پایان</label><input type="datetime-local" name="end_at" class="form-input"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">نام سالن</label><input type="text" name="hall_name" class="form-input"></div>
        <div><label class="form-label">طبقه</label><input type="text" name="floor" class="form-input"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">برگزارکننده</label><input type="text" name="organizer" class="form-input"></div>
        <div><label class="form-label">ظرفیت</label><input type="number" name="capacity" class="form-input"></div>
      </div>
      <div class="flex gap-3 pt-2"><button type="submit" class="btn-primary flex-1">ثبت رویداد</button><button type="button" onclick="document.getElementById('addEventModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button></div>
    </form>
  </div>
</div>

<div id="addAmenityModal" class="modal-overlay hidden">
  <div class="modal max-w-md">
    <div class="flex items-center justify-between mb-4"><h3 class="font-bold text-white">امکانات جدید</h3><button onclick="document.getElementById('addAmenityModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fas fa-xmark"></i></button></div>
    <form method="POST" action="/admin/modules/hotel/amenities" class="space-y-3">
      <?= csrf_field() ?>
      <div><label class="form-label">نام *</label><input type="text" name="name" class="form-input" required></div>
      <div><label class="form-label">نام انگلیسی</label><input type="text" name="name_en" class="form-input"></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">آیکون (FontAwesome)</label><input type="text" name="icon" class="form-input" value="fas fa-star" placeholder="fas fa-spa"></div>
        <div><label class="form-label">طبقه</label><input type="text" name="floor" class="form-input"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">ساعات کار</label><input type="text" name="hours" class="form-input" placeholder="09:00 - 22:00"></div>
        <div><label class="form-label">شماره</label><input type="text" name="phone" class="form-input"></div>
      </div>
      <div class="flex gap-3 pt-2"><button type="submit" class="btn-primary flex-1">ثبت</button><button type="button" onclick="document.getElementById('addAmenityModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button></div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
function showTab(tab) {
  ['info','events','amenities','rs','attractions'].forEach(t => {
    document.getElementById('panel-'+t).classList.toggle('hidden', t!==tab);
    const btn = document.getElementById('tab-'+t);
    if (btn) btn.className = `hotel-tab px-4 py-2 rounded-lg text-sm transition-all ${t===tab?'bg-yellow-500 text-white font-semibold':'text-slate-400 hover:text-white'}`;
  });
}
JS;
?>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
