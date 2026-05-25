<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<!-- Stats Grid -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
<?php
$cards = [
  ['label'=>'کل صفحات',    'val'=>$stats['total']??0,   'icon'=>'tv',           'color'=>'orange'],
  ['label'=>'آنلاین',       'val'=>$stats['online']??0,  'icon'=>'circle-check', 'color'=>'green'],
  ['label'=>'آفلاین',       'val'=>$stats['offline']??0, 'icon'=>'circle-xmark', 'color'=>'red'],
  ['label'=>'رسانه‌ها',     'val'=>$stats['media']??0,   'icon'=>'photo-film',   'color'=>'blue'],
];
foreach ($cards as $c):
$colorMap = ['orange'=>'#f97316','green'=>'#22c55e','red'=>'#ef4444','blue'=>'#3b82f6'];
$clr = $colorMap[$c['color']] ?? '#f97316';
?>
<div class="stat-card" style="border-top:3px solid <?=$clr?>;">
  <div style="display:flex;align-items:center;justify-content:space-between;">
    <div style="font-size:13px;color:#94a3b8;"><?=e($c['label'])?></div>
    <div style="width:36px;height:36px;background:<?=$clr?>18;border-radius:10px;display:flex;align-items:center;justify-content:center;">
      <i class="fas fa-<?=e($c['icon'])?>" style="color:<?=$clr?>;font-size:15px;"></i>
    </div>
  </div>
  <div style="font-size:32px;font-weight:900;color:#fff;margin-top:8px;"><?=e($c['val'])?></div>
</div>
<?php endforeach; ?>
</div>

<!-- Storage usage -->
<?php
$usedPct = $storage_limit > 0 ? round($storage_used / $storage_limit * 100, 1) : 0;
$storageColor = $usedPct > 90 ? '#ef4444' : ($usedPct > 70 ? '#f59e0b' : '#22c55e');
?>
<div class="card mb-6">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
    <span style="font-size:13px;font-weight:700;color:#fff;">فضای ذخیره‌سازی</span>
    <span style="font-size:12px;color:#64748b;"><?=formatBytes($storage_used)?> از <?=formatBytes($storage_limit)?></span>
  </div>
  <div style="background:rgba(255,255,255,0.06);border-radius:4px;height:8px;overflow:hidden;">
    <div style="height:100%;width:<?=$usedPct?>%;background:<?=$storageColor?>;border-radius:4px;transition:width 0.8s ease;"></div>
  </div>
  <div style="font-size:11px;color:#475569;margin-top:6px;"><?=$usedPct?>% استفاده شده</div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

<!-- Online Screens -->
<div class="card">
  <div style="font-size:14px;font-weight:700;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap-8px;">
    <i class="fas fa-satellite-dish text-green-400 ml-2"></i> صفحات آنلاین
    <a href="/admin/screens" style="margin-right:auto;font-size:11px;color:#f97316;text-decoration:none;">همه صفحات →</a>
  </div>
  <?php if (empty($online_screens)): ?>
  <p style="color:#475569;font-size:13px;text-align:center;padding:20px 0;">
    <i class="fas fa-tv" style="font-size:28px;display:block;margin-bottom:8px;opacity:0.2;"></i>
    هیچ صفحه‌ای آنلاین نیست
  </p>
  <?php else: ?>
  <?php foreach ($online_screens as $s): ?>
  <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
    <span class="online-dot"></span>
    <div style="flex:1;">
      <div style="font-size:13px;font-weight:600;color:#fff;"><?=e($s['name'])?></div>
      <div style="font-size:11px;color:#475569;font-family:monospace;"><?=e($s['code'])?></div>
    </div>
    <span style="font-size:11px;color:#64748b;"><?=timeAgo($s['last_seen_at'] ?? date('Y-m-d H:i:s'))?></span>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Recent Activity -->
<div class="card">
  <div style="font-size:14px;font-weight:700;color:#fff;margin-bottom:14px;">
    <i class="fas fa-clock-rotate-left text-blue-400 ml-2"></i> فعالیت اخیر
  </div>
  <?php if (empty($recent_logs)): ?>
  <p style="color:#475569;font-size:13px;text-align:center;padding:20px 0;">
    <i class="fas fa-list" style="font-size:28px;display:block;margin-bottom:8px;opacity:0.2;"></i>
    فعالیتی ثبت نشده
  </p>
  <?php else: ?>
  <?php foreach ($recent_logs as $log): ?>
  <div style="display:flex;gap:10px;padding:9px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
    <div style="width:28px;height:28px;background:rgba(249,115,22,0.1);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fas fa-bolt" style="color:#f97316;font-size:11px;"></i>
    </div>
    <div style="flex:1;min-width:0;">
      <div style="font-size:12px;color:#e2e8f0;"><?=e($log['action'])?></div>
      <div style="font-size:11px;color:#475569;"><?=e($log['user_name'] ?? 'سیستم')?> · <?=timeAgo($log['created_at'])?></div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

</div>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
