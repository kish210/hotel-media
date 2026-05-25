<?php
/**
 * Screens Index — Signage / IPTV tabs with groups
 * @var array $screens
 * @var array $groups
 */

// ─── داده‌ها ───────────────────────────────────────────────────
$tab     = $_GET['tab'] ?? 'signage';
$tab     = in_array($tab, ['signage','iptv']) ? $tab : 'signage';
$screens = array_values(array_filter(is_array($screens ?? []) ? $screens : [], 'is_array'));
$groups  = is_array($groups  ?? []) ? $groups  : [];

// فیلتر صفحات این تب
$filtered = array_values(array_filter(
    $screens,
    fn($s) => ($s['screen_type'] ?? 'signage') === $tab
));

// گروه‌های این تب
$tabGroups = array_values(array_filter(
    $groups,
    fn($g) => ($g['type'] ?? 'signage') === $tab
));

// آمار
$cntOnline  = count(array_filter($filtered, fn($s) => (int)($s['is_online'] ?? 0) === 1));
$cntOffline = count(array_filter($filtered, fn($s) => (int)($s['is_online'] ?? 0) === 0 && ($s['status'] ?? '') === 'active'));
$cntPending = count(array_filter($filtered, fn($s) => ($s['status'] ?? '') === 'pending'));

include VIEWS_PATH . '/partials/layout.php';
?>

<!-- ─── Header ────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-tv" style="color:#f97316;margin-left:10px;"></i>صفحات نمایش
  </h1>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a href="/admin/screens/monitor" class="btn-ghost text-sm flex items-center gap-1.5">
      <i class="fas fa-display text-green-400 text-xs"></i> مانیتورینگ
    </a>
    <button onclick="showGroupModal()"
      style="padding:8px 14px;background:rgba(234,179,8,.08);border:1px solid rgba(234,179,8,.25);
             border-radius:10px;color:#fbbf24;font-size:13px;font-weight:600;cursor:pointer;
             display:flex;align-items:center;gap:6px;font-family:'Vazirmatn',sans-serif;">
      <i class="fas fa-folder text-xs"></i> مدیریت گروه‌ها
    </button>
    <a href="/admin/screens/create?type=<?= e($tab) ?>" class="btn-primary text-sm flex items-center gap-2">
      <i class="fas fa-plus text-xs"></i> صفحه جدید
    </a>
  </div>
</div>

<!-- ─── Tabs ────────────────────────────────────────────────── -->
<?php
$allSig = count(array_filter($screens, fn($s) => ($s['screen_type']??'signage')==='signage'));
$allIpt = count(array_filter($screens, fn($s) => ($s['screen_type']??'signage')==='iptv'));
?>
<div style="display:flex;gap:3px;background:rgba(0,0,0,.4);border-radius:12px;padding:4px;
            margin-bottom:20px;width:fit-content;">
  <a href="?tab=signage"
     style="padding:9px 22px;border-radius:9px;font-size:13px;font-weight:700;
            text-decoration:none;display:flex;align-items:center;gap:7px;transition:all .2s;
            <?= $tab==='signage'
              ? 'background:rgba(249,115,22,.18);color:#f97316;box-shadow:0 2px 8px rgba(249,115,22,.15);'
              : 'color:#64748b;' ?>">
    <i class="fas fa-tv text-xs"></i> Signage
    <span style="background:rgba(255,255,255,.1);border-radius:20px;padding:1px 8px;font-size:10px;"><?= $allSig ?></span>
  </a>
  <a href="?tab=iptv"
     style="padding:9px 22px;border-radius:9px;font-size:13px;font-weight:700;
            text-decoration:none;display:flex;align-items:center;gap:7px;transition:all .2s;
            <?= $tab==='iptv'
              ? 'background:rgba(239,68,68,.18);color:#f87171;box-shadow:0 2px 8px rgba(239,68,68,.15);'
              : 'color:#64748b;' ?>">
    <i class="fas fa-satellite-dish text-xs"></i> IPTV
    <span style="background:rgba(255,255,255,.1);border-radius:20px;padding:1px 8px;font-size:10px;"><?= $allIpt ?></span>
  </a>
</div>

<!-- ─── آمار ─────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px;">
  <?php foreach([
    ['کل', count($filtered), 'fa-tv', '#f97316'],
    ['آنلاین', $cntOnline, 'fa-circle-check', '#22c55e'],
    ['آفلاین', $cntOffline, 'fa-circle-xmark', '#ef4444'],
    ['انتظار', $cntPending, 'fa-hourglass', '#f59e0b'],
  ] as [$lbl,$val,$ico,$clr]): ?>
  <div style="background:#16161f;border:1px solid rgba(255,255,255,.07);border-top:3px solid <?=$clr?>;
              border-radius:12px;padding:12px 14px;display:flex;align-items:center;gap:10px;">
    <i class="fas <?=$ico?>" style="color:<?=$clr?>;font-size:18px;"></i>
    <div>
      <div style="font-size:22px;font-weight:900;color:#fff;"><?=$val?></div>
      <div style="font-size:11px;color:#64748b;"><?=$lbl?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ─── گروه‌های این تب ─────────────────────────────────────── -->
<?php foreach ($tabGroups as $group):
  $gid = (int)$group['id'];
  $groupScreens = array_values(array_filter($filtered, fn($s) => (int)($s['group_id']??0) === $gid));
  // گروه رو نشون بده حتی اگه خالیه
?>
<div style="margin-bottom:20px;">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,.05);">
    <div style="width:12px;height:12px;border-radius:3px;flex-shrink:0;background:<?=e($group['color']??'#f97316')?>"></div>
    <span style="font-size:14px;font-weight:700;color:#e2e8f0;"><?=e($group['name']??'')?></span>
    <span style="font-size:11px;color:#475569;background:rgba(255,255,255,.05);padding:2px 8px;border-radius:10px;"><?=count($groupScreens)?> صفحه</span>
    <form method="POST" action="/admin/screens/groups/<?=$gid?>/delete" class="inline" style="margin-right:auto;">
      <?=csrf_field()?>
      <button type="submit" onclick="return confirm('گروه «<?=e(addslashes($group['name']??''))?>» حذف شود؟')"
        style="background:none;border:none;color:#475569;cursor:pointer;font-size:12px;padding:2px 6px;">
        <i class="fas fa-trash-alt"></i>
      </button>
    </form>
  </div>
  <?php if (!empty($groupScreens)): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px;">
    <?php foreach ($groupScreens as $s): renderScreenCard($s, $tab); endforeach; ?>
  </div>
  <?php else: ?>
  <div style="text-align:center;padding:20px;color:#334155;font-size:12px;
              background:rgba(255,255,255,.02);border-radius:10px;border:1px dashed rgba(255,255,255,.06);">
    هنوز صفحه‌ای در این گروه نیست — از صفحه مدیریت هر TV، گروه آن را تنظیم کنید
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- ─── بدون گروه ──────────────────────────────────────────── -->
<?php
$ungrouped = array_values(array_filter($filtered, fn($s) => empty($s['group_id'])));
if (!empty($ungrouped)):
?>
<div style="margin-bottom:20px;">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,.05);">
    <div style="width:12px;height:12px;border-radius:3px;background:#334155;"></div>
    <span style="font-size:14px;font-weight:700;color:#64748b;">بدون گروه</span>
    <span style="font-size:11px;color:#334155;background:rgba(255,255,255,.03);padding:2px 8px;border-radius:10px;"><?=count($ungrouped)?> صفحه</span>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px;">
    <?php foreach ($ungrouped as $s): renderScreenCard($s, $tab); endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (empty($filtered)): ?>
<div style="text-align:center;padding:70px 20px;color:#475569;">
  <i class="fas <?=$tab==='iptv'?'fa-satellite-dish':'fa-tv'?>"
     style="font-size:52px;display:block;margin-bottom:16px;opacity:.12;color:<?=$tab==='iptv'?'#ef4444':'#f97316'?>;"></i>
  <p style="margin-bottom:20px;font-size:15px;">هیچ صفحه <?=$tab==='iptv'?'IPTV':'Signage'?> ای ندارید</p>
  <a href="/admin/screens/create?type=<?=$tab?>"
     class="btn-primary text-sm inline-flex items-center gap-2">
    <i class="fas fa-plus text-xs"></i> افزودن صفحه جدید
  </a>
</div>
<?php endif; ?>

<!-- ─── Modal مدیریت گروه‌ها ───────────────────────────────── -->
<div id="groupModal" class="modal-overlay" style="display:none;">
  <div class="modal" style="max-width:500px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="font-weight:700;color:#fff;font-size:16px;">
        <i class="fas fa-folder text-yellow-400 ml-2"></i>مدیریت گروه‌ها
      </h3>
      <button onclick="hideGroupModal()"
        style="background:none;border:none;color:#64748b;font-size:22px;cursor:pointer;line-height:1;">&times;</button>
    </div>

    <!-- لیست گروه‌های موجود -->
    <?php if (!empty($groups)): ?>
    <div style="margin-bottom:16px;border:1px solid rgba(255,255,255,.07);border-radius:10px;overflow:hidden;">
      <div style="padding:10px 14px;font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;
                  background:rgba(0,0,0,.3);border-bottom:1px solid rgba(255,255,255,.06);">گروه‌های موجود</div>
      <?php foreach ($groups as $g): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.04);">
        <div style="width:14px;height:14px;border-radius:3px;flex-shrink:0;background:<?=e($g['color']??'#f97316')?>"></div>
        <span style="font-size:13px;color:#fff;flex:1;"><?=e($g['name']??'')?></span>
        <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;
          <?=($g['type']??'')==='iptv'
            ?'background:rgba(239,68,68,.1);color:#f87171;border:1px solid rgba(239,68,68,.3);'
            :'background:rgba(249,115,22,.1);color:#f97316;border:1px solid rgba(249,115,22,.3);'?>">
          <?=($g['type']??'')==='iptv'?'📡 IPTV':'📺 Signage'?>
        </span>
        <form method="POST" action="/admin/screens/groups/<?=(int)$g['id']?>/delete" class="inline">
          <?=csrf_field()?>
          <button type="submit" onclick="return confirm('حذف گروه «<?=e(addslashes($g['name']??''))?>»؟')"
            style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#f87171;
                   border-radius:6px;padding:3px 8px;cursor:pointer;font-size:11px;">
            <i class="fas fa-trash-alt text-xs"></i>
          </button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:20px;color:#475569;font-size:13px;margin-bottom:16px;">
      هنوز گروهی تعریف نشده
    </div>
    <?php endif; ?>

    <!-- فرم افزودن گروه جدید -->
    <form method="POST" action="/admin/screens/groups" class="space-y-3"
          style="background:rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.06);border-radius:10px;padding:16px;">
      <?=csrf_field()?>
      <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:10px;">+ گروه جدید</div>
      <div>
        <label class="form-label">نام گروه *</label>
        <input type="text" name="name" class="form-input" required placeholder="مثال: لابی طبقه اول">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label class="form-label">نوع</label>
          <select name="type" class="form-input" id="grp-type-sel">
            <option value="signage" <?=$tab==='signage'?'selected':''?>>📺 Signage</option>
            <option value="iptv"    <?=$tab==='iptv'?'selected':''?>>📡 IPTV</option>
          </select>
        </div>
        <div>
          <label class="form-label">رنگ</label>
          <input type="color" name="color" class="form-input" value="#f97316"
                 style="height:42px;padding:4px;cursor:pointer;">
        </div>
      </div>
      <button type="submit"
        style="width:100%;padding:11px;background:linear-gradient(135deg,#f97316,#c2570b);
               color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;
               cursor:pointer;font-family:'Vazirmatn',sans-serif;margin-top:4px;">
        <i class="fas fa-plus text-xs ml-1"></i> افزودن گروه
      </button>
    </form>
  </div>
</div>

<style>@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}</style>

<?php
// ─── تابع رندر کارت صفحه ──────────────────────────────────
function renderScreenCard(array $s, string $tab): void {
    $online  = (int)($s['is_online'] ?? 0) === 1;
    $active  = ($s['status'] ?? '') === 'active';
    $stClr   = $online ? '#22c55e' : ($active ? '#ef4444' : '#f59e0b');
    $stLbl   = $online ? 'آنلاین'  : ($active ? 'آفلاین'  : 'انتظار');
    $id      = (int)($s['id'] ?? 0);
    $name    = htmlspecialchars($s['name'] ?? '', ENT_QUOTES);
    $code    = htmlspecialchars($s['code'] ?? '', ENT_QUOTES);
    $loc     = htmlspecialchars($s['location_name'] ?? '', ENT_QUOTES);
    echo <<<HTML
<div style="background:#16161f;border:1px solid rgba(255,255,255,.07);border-radius:12px;
            overflow:hidden;transition:all .2s;"
     onmouseenter="this.style.borderColor='rgba(249,115,22,.3)'"
     onmouseleave="this.style.borderColor='rgba(255,255,255,.07)'">
  <div style="height:88px;background:#0d0d1e;position:relative;cursor:pointer;"
       onclick="window.open('/player/{$code}','_blank')">
    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">
HTML;
    if ($online) {
        $ico = $tab === 'iptv' ? 'fa-satellite-dish' : 'fa-play-circle';
        $lbl = $tab === 'iptv' ? 'LIVE' : 'پخش';
        echo "<div style='text-align:center;'>
          <i class='fas {$ico}' style='font-size:22px;color:#f97316;display:block;margin-bottom:3px;'></i>
          <span style='font-size:9px;color:#64748b;'>{$lbl}</span></div>";
    } else {
        echo "<i class='fas fa-moon' style='font-size:22px;color:#1e293b;'></i>";
    }
    echo <<<HTML
    </div>
    <div style="position:absolute;top:7px;right:7px;display:flex;align-items:center;gap:4px;
                padding:2px 8px;border-radius:12px;font-size:9px;font-weight:700;
                background:{$stClr}18;border:1px solid {$stClr}44;color:{$stClr};">
      <span style="width:5px;height:5px;border-radius:50%;background:{$stClr};"></span> {$stLbl}
    </div>
  </div>
  <div style="padding:11px 12px;">
    <div style="font-weight:700;color:#fff;font-size:13px;margin-bottom:3px;
                overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{$name}</div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
      <code style="font-size:10px;color:#475569;">{$code}</code>
      <span style="font-size:10px;color:#64748b;">{$loc}</span>
    </div>
    <div style="display:flex;gap:4px;">
      <a href="/admin/screens/{$id}" style="flex:1;text-align:center;padding:6px;
         background:rgba(249,115,22,.08);border:1px solid rgba(249,115,22,.2);
         border-radius:8px;color:#f97316;font-size:11px;font-weight:600;text-decoration:none;">
        <i class="fas fa-gear" style="font-size:10px;margin-left:3px;"></i>مدیریت
      </a>
      <a href="/player/{$code}" target="_blank"
         style="padding:6px 10px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);
                border-radius:8px;color:#4ade80;text-decoration:none;">
        <i class="fas fa-play" style="font-size:10px;"></i>
      </a>
    </div>
  </div>
</div>
HTML;
}
?>

<script>
function showGroupModal() {
  var m = document.getElementById('groupModal');
  if (m) m.style.display = 'flex';
}
function hideGroupModal() {
  var m = document.getElementById('groupModal');
  if (m) m.style.display = 'none';
}
// بستن با کلیک خارج
var gm = document.getElementById('groupModal');
if (gm) gm.addEventListener('click', function(e) {
  if (e.target === this) hideGroupModal();
});
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
