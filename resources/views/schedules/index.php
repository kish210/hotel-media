<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<div class="flex items-center justify-between mb-5">
  <h1 class="text-xl font-bold text-white flex items-center gap-2"><i class="fas fa-calendar text-orange-400"></i> زمان‌بندی محتوا</h1>
  <button onclick="document.getElementById('addScheduleModal').classList.remove('hidden')" class="btn-primary text-sm flex items-center gap-2"><i class="fas fa-plus text-xs"></i> زمان‌بندی جدید</button>
</div>

<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead><tr class="border-b border-white/5 text-xs text-slate-500 uppercase">
      <th class="text-right p-3">نام</th>
      <th class="text-right p-3">پلی‌لیست</th>
      <th class="text-right p-3">صفحه</th>
      <th class="text-right p-3">نوع</th>
      <th class="text-right p-3">اولویت</th>
      <th class="p-3"></th>
    </tr></thead>
    <tbody>
      <?php if (empty($schedules)): ?>
      <tr><td colspan="6" class="text-center py-12 text-slate-600"><i class="fas fa-calendar text-4xl mb-3 block opacity-20"></i>زمان‌بندی‌ای تنظیم نشده</td></tr>
      <?php else: foreach ($schedules as $sc): ?>
      <tr class="table-row border-b border-white/3">
        <td class="p-3 text-white font-medium"><?=e($sc['name'])?></td>
        <td class="p-3 text-slate-400"><?=e($sc['playlist_name'])?></td>
        <td class="p-3 text-slate-400"><?=e($sc['screen_name'] ?? 'همه صفحات')?></td>
        <td class="p-3"><span class="badge-pending px-2 py-0.5 rounded-full text-xs border"><?=e($sc['type'])?></span></td>
        <td class="p-3 text-slate-400 font-mono"><?=e($sc['priority'])?></td>
        <td class="p-3">
          <form method="POST" action="/admin/schedules/<?=$sc['id']?>/delete" class="inline">
            <?= csrf_field() ?><button type="submit" class="btn-danger text-xs px-2 py-1" onclick="return confirm('حذف شود؟')"><i class="fas fa-trash text-xs"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- Add Schedule Modal -->
<div id="addScheduleModal" class="modal-overlay hidden">
  <div class="modal max-w-lg" style="max-height:90vh;overflow-y:auto;">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-bold text-white">زمان‌بندی جدید</h3>
      <button onclick="document.getElementById('addScheduleModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fas fa-xmark"></i></button>
    </div>
    <form method="POST" action="/admin/schedules" class="space-y-3">
      <?= csrf_field() ?>
      <div><label class="form-label">نام *</label><input type="text" name="name" class="form-input" required></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">پلی‌لیست *</label>
          <select name="playlist_id" class="form-input" required>
            <option value="">انتخاب کنید</option>
            <?php foreach ($playlists as $p): ?><option value="<?=$p['id']?>"><?=e($p['name'])?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="form-label">صفحه</label>
          <select name="screen_id" class="form-input">
            <option value="">همه صفحات</option>
            <?php foreach ($screens as $s): ?><option value="<?=$s['id']?>"><?=e($s['name'])?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">نوع</label>
          <select name="type" class="form-input">
            <?php foreach (['always'=>'همیشه','daily'=>'روزانه','weekly'=>'هفتگی','once'=>'یک‌بار'] as $v=>$l): ?>
            <option value="<?=$v?>"><?=$l?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="form-label">اولویت (۱-۱۰)</label><input type="number" name="priority" class="form-input" value="5" min="1" max="10"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">تاریخ شروع</label><input type="date" name="start_date" class="form-input"></div>
        <div><label class="form-label">تاریخ پایان</label><input type="date" name="end_date" class="form-input"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">ساعت شروع</label><input type="time" name="start_time" class="form-input"></div>
        <div><label class="form-label">ساعت پایان</label><input type="time" name="end_time" class="form-input"></div>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn-primary flex-1 py-2.5">ایجاد زمان‌بندی</button>
        <button type="button" onclick="document.getElementById('addScheduleModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button>
      </div>
    </form>
  </div>
</div>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
