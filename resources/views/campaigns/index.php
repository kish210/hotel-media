<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<div class="flex items-center justify-between mb-5">
  <h1 class="text-xl font-bold text-white flex items-center gap-2"><i class="fas fa-bullhorn text-orange-400"></i> کمپین‌ها و اعلان اضطراری</h1>
  <button onclick="document.getElementById('addCampModal').classList.remove('hidden')" class="btn-primary text-sm flex items-center gap-2"><i class="fas fa-plus text-xs"></i> کمپین جدید</button>
</div>
<div class="card overflow-hidden mb-4">
  <div style="font-size:13px;font-weight:700;color:#fff;padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.06);">🚨 پخش اضطراری فوری</div>
  <form method="POST" action="/admin/campaigns" class="p-4 flex gap-3">
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="emergency">
    <input type="text" name="message" class="form-input flex-1" placeholder="پیام اضطراری که روی همه صفحات نمایش داده می‌شود..." required>
    <button type="submit" class="btn-danger px-5">پخش فوری</button>
  </form>
</div>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead><tr class="border-b border-white/5 text-xs text-slate-500 uppercase"><th class="text-right p-3">نام</th><th class="text-right p-3">نوع</th><th class="text-right p-3">وضعیت</th><th class="text-right p-3">تاریخ</th><th class="p-3"></th></tr></thead>
    <tbody>
      <?php if (empty($campaigns)): ?>
      <tr><td colspan="5" class="text-center py-10 text-slate-600">کمپینی ثبت نشده</td></tr>
      <?php else: foreach ($campaigns as $c): ?>
      <tr class="table-row border-b border-white/3">
        <td class="p-3 text-white"><?=e($c['name'])?></td>
        <td class="p-3 text-slate-400"><?=e($c['type'])?></td>
        <td class="p-3"><span class="<?=$c['is_active']?'badge-online':'badge-offline'?> px-2 py-0.5 rounded-full text-xs border"><?=$c['is_active']?'فعال':'غیرفعال'?></span></td>
        <td class="p-3 text-slate-500 text-xs"><?=e($c['created_at'])?></td>
        <td class="p-3">
          <form method="POST" action="/admin/campaigns/<?=$c['id']?>/broadcast" class="inline">
            <?= csrf_field() ?><button type="submit" class="btn-ghost text-xs px-2 py-1"><i class="fas fa-broadcast-tower text-red-400 text-xs"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div id="addCampModal" class="modal-overlay hidden">
  <div class="modal max-w-md">
    <div class="flex items-center justify-between mb-4"><h3 class="font-bold text-white">کمپین جدید</h3><button onclick="document.getElementById('addCampModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fas fa-xmark"></i></button></div>
    <form method="POST" action="/admin/campaigns" class="space-y-3">
      <?= csrf_field() ?>
      <div><label class="form-label">نام کمپین *</label><input type="text" name="name" class="form-input" required></div>
      <div><label class="form-label">نوع</label><select name="type" class="form-input"><option value="banner">بنر</option><option value="emergency">اضطراری</option><option value="promo">تبلیغاتی</option></select></div>
      <div><label class="form-label">پیام</label><textarea name="message" class="form-input" rows="3"></textarea></div>
      <div class="flex gap-3"><button type="submit" class="btn-primary flex-1">ایجاد</button><button type="button" onclick="document.getElementById('addCampModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button></div>
    </form>
  </div>
</div>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
