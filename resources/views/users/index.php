<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<div class="flex items-center justify-between mb-5">
  <h1 class="text-xl font-bold text-white flex items-center gap-2"><i class="fas fa-users text-orange-400"></i> مدیریت کاربران</h1>
  <button onclick="document.getElementById('addUserModal').classList.remove('hidden')" class="btn-primary text-sm flex items-center gap-2"><i class="fas fa-plus text-xs"></i> کاربر جدید</button>
</div>
<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead><tr class="border-b border-white/5 text-xs text-slate-500 uppercase">
      <th class="text-right p-3">نام</th><th class="text-right p-3">ایمیل</th><th class="text-right p-3">نقش</th><th class="text-right p-3">وضعیت</th><th class="text-right p-3">آخرین ورود</th><th class="p-3"></th>
    </tr></thead>
    <tbody>
      <?php if (empty($users)): ?>
      <tr><td colspan="6" class="text-center py-12 text-slate-600"><i class="fas fa-users text-4xl mb-3 block opacity-20"></i>کاربری وجود ندارد</td></tr>
      <?php else: foreach ($users as $u): ?>
      <tr class="table-row border-b border-white/3">
        <td class="p-3"><div class="flex items-center gap-2"><div style="width:32px;height:32px;background:linear-gradient(135deg,#f97316,#c2570b);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;"><?=mb_substr($u['name'],0,1)?></div><?=e($u['name'])?></div></td>
        <td class="p-3 text-slate-400 font-mono text-xs"><?=e($u['email'])?></td>
        <td class="p-3"><span class="badge-pending px-2 py-0.5 rounded-full text-xs border"><?=e($u['role'])?></span></td>
        <td class="p-3"><span class="<?=$u['is_active']?'badge-online':'badge-offline'?> px-2 py-0.5 rounded-full text-xs border"><?=$u['is_active']?'فعال':'غیرفعال'?></span></td>
        <td class="p-3 text-slate-500 text-xs"><?=$u['last_login_at'] ? timeAgo($u['last_login_at']) : '—'?></td>
        <td class="p-3">
          <button onclick="editUser(<?=htmlspecialchars(json_encode($u))?>,this)" class="btn-ghost text-xs px-2 py-1"><i class="fas fa-pencil text-blue-400 text-xs"></i></button>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal-overlay hidden">
  <div class="modal max-w-md">
    <div class="flex items-center justify-between mb-4"><h3 class="font-bold text-white" id="userModalTitle">کاربر جدید</h3><button onclick="document.getElementById('addUserModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fas fa-xmark"></i></button></div>
    <form id="userForm" method="POST" action="/admin/users" class="space-y-3">
      <?= csrf_field() ?>
      <div><label class="form-label">نام *</label><input type="text" name="name" id="uName" class="form-input" required></div>
      <div><label class="form-label">ایمیل *</label><input type="email" name="email" id="uEmail" class="form-input" required></div>
      <div><label class="form-label">رمز عبور *</label><input type="password" name="password" id="uPass" class="form-input" required></div>
      <div><label class="form-label">نقش</label>
        <select name="role" id="uRole" class="form-input">
          <?php foreach (['admin'=>'ادمین','manager'=>'مدیر','editor'=>'ویرایشگر','viewer'=>'بازدیدکننده'] as $v=>$l): ?><option value="<?=$v?>"><?=$l?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="flex gap-3 pt-2"><button type="submit" class="btn-primary flex-1" id="userSubmitBtn">ایجاد کاربر</button><button type="button" onclick="document.getElementById('addUserModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button></div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
function editUser(u) {
  document.getElementById('userModalTitle').textContent = 'ویرایش کاربر';
  document.getElementById('userForm').action = '/admin/users/' + u.id;
  document.getElementById('uName').value  = u.name;
  document.getElementById('uEmail').value = u.email;
  document.getElementById('uPass').value  = '';
  document.getElementById('uPass').required = false;
  document.getElementById('uRole').value  = u.role;
  document.getElementById('userSubmitBtn').textContent = 'ذخیره';
  document.getElementById('addUserModal').classList.remove('hidden');
}
JS;
?>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
