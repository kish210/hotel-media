<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<h1 class="text-xl font-bold text-white mb-6 flex items-center gap-2"><i class="fas fa-chart-bar text-orange-400"></i> گزارش‌ها</h1>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <?php foreach ([
    ['صفحات',    'screens',   'tv',         '#f97316'],
    ['پلی‌لیست', 'playlists', 'list',        '#3b82f6'],
    ['رسانه‌ها', 'media',     'photo-film',  '#a855f7'],
    ['کاربران',  'users',     'users',       '#22c55e'],
  ] as [$label,$key,$icon,$clr]):
    $val = $stats[$key] ?? 0;
  ?>
  <div class="stat-card text-center" style="border-top:3px solid <?=$clr?>;">
    <i class="fas fa-<?=$icon?>" style="color:<?=$clr?>;font-size:22px;margin-bottom:8px;display:block;"></i>
    <div style="font-size:28px;font-weight:900;color:#fff;"><?=number_format($val)?></div>
    <div style="font-size:12px;color:#94a3b8;margin-top:4px;"><?=$label?></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="card text-center py-12">
  <i class="fas fa-chart-line" style="font-size:48px;color:#475569;display:block;margin-bottom:12px;opacity:0.3;"></i>
  <p style="color:#475569;font-size:15px;">نمودارهای تفصیلی در نسخه‌های آینده</p>
</div>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
