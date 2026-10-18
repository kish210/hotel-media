<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<?php /** @var array $items @var array $screens @var array $groups @var array $media */ ?>
<style>
  .in-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:16px;margin-bottom:14px;}
  .in-row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
  .in-row > div{display:flex;flex-direction:column;gap:4px;}
  .in-off{opacity:.5;}
</style>

<div style="margin-bottom:18px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-clapperboard" style="color:#a855f7;margin-left:10px;"></i>تیزر شروع
  </h1>
  <p style="font-size:12px;color:#8593a8;margin-top:4px;line-height:1.9;">
    عکس یا ویدیوهایی که تلویزیون هنگام روشن‌شدن <b>یک‌بار</b> پخش می‌کند و بعد سراغ محتوای عادی می‌رود
    (پلی‌لیست تابلو یا منوی اتاق). ترتیب پخش با «ترتیب» تعیین می‌شود.
  </p>
</div>

<div class="in-card">
  <form method="post" action="/admin/intro" class="in-row">
    <?= csrf_field() ?>
    <div style="flex:1;min-width:180px;">
      <label class="form-label">نام</label>
      <input name="name" class="form-input" placeholder="مثلا: خوش‌آمدگویی هتل" required maxlength="200">
    </div>
    <div style="min-width:200px;">
      <label class="form-label">رسانه</label>
      <select name="media_id" class="form-input">
        <option value="">— یا آدرس مستقیم بدهید —</option>
        <?php foreach ($media as $m): ?>
          <option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?> (<?= e($m['type']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:180px;">
      <label class="form-label">یا آدرس</label>
      <input name="url" class="form-input ltr" placeholder="https://… یا /uploads/…">
    </div>
    <div style="width:110px;">
      <label class="form-label">مدت (ثانیه)</label>
      <input type="number" name="duration" class="form-input" value="8" min="1" max="600">
      <span style="font-size:10px;color:#8593a8;">ویدیو تا پایان خودش</span>
    </div>
    <div style="width:120px;">
      <label class="form-label">ترتیب</label>
      <input type="number" name="sort_order" class="form-input" value="0" min="0" max="999">
    </div>
    <div style="min-width:140px;">
      <label class="form-label">کجا پخش شود</label>
      <select name="scope" class="form-input">
        <option value="both">هر دو (تابلو و اتاق)</option>
        <option value="signage">فقط تابلو</option>
        <option value="iptv">فقط تلویزیون اتاق</option>
      </select>
    </div>
    <div style="min-width:150px;">
      <label class="form-label">هدف</label>
      <select name="target" id="in-target" class="form-input" onchange="inTarget()">
        <option value="all">همهٔ صفحه‌ها</option>
        <option value="group">گروه مشخص</option>
        <option value="screen">صفحهٔ مشخص</option>
      </select>
    </div>
    <div id="in-ids" style="display:none;min-width:200px;">
      <label class="form-label">انتخاب (چندتایی با Ctrl)</label>
      <select name="target_ids[]" id="in-ids-sel" class="form-input" multiple size="4"></select>
    </div>
    <div><button class="btn-primary text-sm"><i class="fas fa-plus ml-1"></i>افزودن</button></div>
  </form>
</div>

<?php if (!$items): ?>
  <div class="in-card" style="text-align:center;color:#8593a8;font-size:13px;">
    هنوز تیزری تعریف نشده. تلویزیون‌ها مستقیم سراغ محتوای عادی می‌روند.
  </div>
<?php endif; ?>

<?php foreach ($items as $it):
  $isOff = !(int)$it['is_active'];
  $src   = trim((string)($it['url'] ?? '')) ?: (string)($it['file_path'] ?? '');
  $kind  = $it['media_type'] ?? (preg_match('/\.(mp4|webm|mkv|mov)(\?|$)/i', $src) ? 'video' : 'image');
  $tgt   = ['all' => 'همهٔ صفحه‌ها', 'group' => 'گروه', 'screen' => 'صفحه'][$it['target']] ?? $it['target'];
  $scp   = ['both' => 'تابلو و اتاق', 'signage' => 'تابلو', 'iptv' => 'اتاق'][$it['scope']] ?? $it['scope'];
?>
<div class="in-card <?= $isOff ? 'in-off' : '' ?>" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
  <div style="width:86px;height:50px;border-radius:8px;overflow:hidden;background:#0b0f18;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
    <?php if ($kind === 'image' && $src): ?>
      <img src="<?= e($src) ?>" alt="" style="max-width:100%;max-height:100%;" loading="lazy">
    <?php else: ?>
      <i class="fas fa-<?= $kind === 'video' ? 'film' : 'image' ?>" style="color:#475569;"></i>
    <?php endif; ?>
  </div>
  <div style="flex:1;min-width:160px;">
    <div style="font-size:13px;font-weight:700;color:#fff;"><?= e($it['name']) ?></div>
    <div style="font-size:11px;color:#8593a8;margin-top:3px;">
      <?= e($scp) ?> · <?= e($tgt) ?> · <?= (int)$it['duration'] ?> ثانیه · ترتیب <?= (int)$it['sort_order'] ?>
      <?php if (($it['media_status'] ?? null) !== null && $it['media_status'] !== 'ready'): ?>
        · <span style="color:#fbbf24;">رسانه هنوز آماده نیست</span>
      <?php endif; ?>
    </div>
  </div>
  <form method="post" action="/admin/intro/<?= (int)$it['id'] ?>/toggle">
    <?= csrf_field() ?>
    <button class="btn-ghost text-sm"><?= $isOff ? 'روشن' : 'خاموش' ?></button>
  </form>
  <form method="post" action="/admin/intro/<?= (int)$it['id'] ?>/delete">
    <?= csrf_field() ?>
    <button class="btn-ghost text-sm" style="color:#ef4444;border-color:rgba(239,68,68,.3);"
            data-confirm="این تیزر حذف شود؟" data-confirm-name="<?= e($it['name']) ?>">
      <i class="fas fa-trash"></i>
    </button>
  </form>
</div>
<?php endforeach; ?>

<script>
var IN_GROUPS = <?= json_encode(array_map(fn($g) => ['id' => (int)$g['id'], 'name' => $g['name'] . ' (' . ($g['type'] === 'iptv' ? 'اتاق' : 'تابلو') . ')'], $groups), JSON_UNESCAPED_UNICODE) ?>;
var IN_SCREENS = <?= json_encode(array_map(fn($s) => ['id' => (int)$s['id'], 'name' => $s['name'] . ' — ' . $s['code']], $screens), JSON_UNESCAPED_UNICODE) ?>;
function inTarget() {
  var t = document.getElementById('in-target').value;
  var box = document.getElementById('in-ids'), sel = document.getElementById('in-ids-sel');
  if (t === 'all') { box.style.display = 'none'; sel.innerHTML = ''; return; }
  var list = t === 'group' ? IN_GROUPS : IN_SCREENS;
  sel.innerHTML = list.map(function (o) { return '<option value="' + o.id + '">' + o.name + '</option>'; }).join('');
  box.style.display = '';
}
</script>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
