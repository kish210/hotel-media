<?php
include VIEWS_PATH . '/partials/layout.php';
$items   = $items   ?? [];
$media   = $media   ?? [];
$screens = $screens ?? [];

// محاسبه مدت کل
$totalSec = array_sum(array_column($items, 'duration'));
$totalMin = floor($totalSec / 60);
$totalSec2 = $totalSec % 60;

// رنگ‌ها بر اساس نوع رسانه
$typeColors = [
    'image'   => ['#3b82f6','#1d4ed8'],   // آبی
    'video'   => ['#a855f7','#7e22ce'],   // بنفش
    'url'     => ['#22c55e','#15803d'],   // سبز
    'hls'     => ['#ef4444','#b91c1c'],   // قرمز — زنده
    'rtsp'    => ['#f97316','#c2570b'],   // نارنجی — زنده
    'xml'     => ['#f59e0b','#b45309'],   // زرد — XML
    'default' => ['#64748b','#475569'],
];
?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
  <a href="/admin/playlists" class="btn-ghost text-sm px-3">
    <i class="fas fa-arrow-right text-xs"></i>
  </a>
  <h1 style="font-size:20px;font-weight:700;color:#fff;">
    <i class="fas fa-film" style="color:#f97316;margin-left:8px;"></i><?= e($playlist['name']) ?>
  </h1>
  <span class="<?= ($playlist['is_active']??1) ? 'badge-online' : 'badge-offline' ?>" style="margin-right:auto;">
    <?= ($playlist['is_active']??1) ? 'فعال' : 'غیرفعال' ?>
  </span>
  <span style="font-size:12px;color:#64748b;">
    مدت کل: <strong style="color:#f97316;"><?= $totalMin ?>:<?= str_pad($totalSec2,2,'0',STR_PAD_LEFT) ?></strong>
  </span>
  <a href="/admin/playlists/<?=$playlist['id']?>/edit" class="btn-ghost text-sm flex items-center gap-1.5">
    <i class="fas fa-gear text-xs text-slate-400"></i> تنظیمات
  </a>
</div>

<!-- ─── Timeline ─────────────────────────────────────────────────── -->
<div class="card mb-5" style="padding:0;overflow:hidden;">
  <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.06);">
    <span style="font-size:13px;font-weight:700;color:#fff;">
      <i class="fas fa-sliders" style="color:#f97316;margin-left:8px;"></i>خط زمانی پخش
    </span>
    <button onclick="document.getElementById('addItemModal').classList.remove('hidden')"
      class="btn-primary text-xs flex items-center gap-1.5" style="padding:6px 12px;">
      <i class="fas fa-plus text-xs"></i> افزودن محتوا
    </button>
  </div>

  <?php if (empty($items)): ?>
  <div style="text-align:center;padding:48px;color:#475569;">
    <i class="fas fa-sliders" style="font-size:40px;display:block;margin-bottom:12px;opacity:0.2;"></i>
    <p style="margin-bottom:16px;">پلی‌لیست خالی است</p>
    <button onclick="document.getElementById('addItemModal').classList.remove('hidden')" class="btn-primary text-sm">
      <i class="fas fa-plus text-xs ml-1"></i> افزودن اولین محتوا
    </button>
  </div>
  <?php else: ?>

  <!-- ─── Timeline ruler ─── -->
  <div id="timeline-container" style="padding:16px 18px;overflow-x:auto;">
    <!-- Ruler -->
    <div style="display:flex;align-items:center;margin-bottom:8px;min-width:<?= max(800, $totalSec * 3) ?>px;">
      <?php
      $elapsed = 0;
      foreach ($items as $idx => $item):
        $dur     = max(1, (int)$item['duration']);
        $type    = $item['media_type'] ?? $item['type'] ?? 'image';
        // تشخیص stream از URL
        $src = $item['file_path'] ?? $item['url'] ?? '';
        if (str_contains($src, '.m3u8')) $type = 'hls';
        if (str_starts_with($src, 'rtsp://')) $type = 'rtsp';
        if (str_contains($src, '.xml') || str_contains($src, 'xml')) $type = 'xml';

        [$c1,$c2] = $typeColors[$type] ?? $typeColors['default'];
        $widthPct = ($dur / max(1,$totalSec)) * 100;
        $isLive   = in_array($type, ['hls','rtsp']);
      ?>
      <!-- Timeline block -->
      <div class="timeline-block" data-id="<?= $item['id'] ?>" data-idx="<?= $idx ?>"
        style="flex:<?= $dur ?>;min-width:<?= max(60, $dur * 3) ?>px;
               background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);
               margin-left:2px;border-radius:8px;padding:8px 10px;
               cursor:pointer;position:relative;
               box-shadow:0 2px 8px <?= $c1 ?>44;
               transition:transform 0.15s,box-shadow 0.15s;
               border:1px solid <?= $c1 ?>88;"
        onclick="openItemEditor(<?= $idx ?>)"
        onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px <?= $c1 ?>66'"
        onmouseleave="this.style.transform='';this.style.boxShadow='0 2px 8px <?= $c1 ?>44'">

        <!-- نوع -->
        <div style="font-size:9px;font-weight:700;color:rgba(255,255,255,0.7);
                    text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">
          <?php if ($isLive): ?><span style="color:#fff;background:rgba(255,255,255,0.2);padding:1px 5px;border-radius:4px;font-size:8px;">● LIVE</span>
          <?php elseif ($type === 'xml'): ?><span style="background:rgba(0,0,0,0.2);padding:1px 5px;border-radius:4px;font-size:8px;">XML</span>
          <?php else: ?><?= strtoupper($type) ?><?php endif; ?>
        </div>

        <!-- نام -->
        <div style="font-size:11px;font-weight:600;color:#fff;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:<?= max(50,$dur*3-20) ?>px;">
          <?= e($item['media_name'] ?? ($isLive ? 'استریم زنده' : ($type==='xml'?'محتوای XML':'رسانه'))) ?>
        </div>

        <!-- مدت -->
        <div style="font-size:12px;font-weight:800;color:rgba(255,255,255,0.9);margin-top:3px;font-family:monospace;">
          <?php
          if ($isLive) {
              echo '∞';
          } else {
              $m = floor($dur/60); $s = $dur%60;
              echo $m>0 ? "{$m}:{$s}0"[0]."{$s}0"[1] : "{$s}s";
              // نمایش زیباتر
              echo $m>0 ? "{$m}:{$s}" : "{$s}s";
          }
          ?>
        </div>
      </div>
      <?php $elapsed += $dur; endforeach; ?>
    </div>

    <!-- Time ruler تیک -->
    <div style="display:flex;border-top:1px solid rgba(255,255,255,0.06);padding-top:6px;min-width:<?= max(800,$totalSec*3) ?>px;">
      <?php
      $marks = min(20, max(5, (int)($totalSec/30)));
      for ($i = 0; $i <= $marks; $i++):
        $sec = (int)(($i / $marks) * $totalSec);
        $m = floor($sec/60); $s = $sec%60;
      ?>
      <div style="flex:1;font-size:10px;color:#475569;font-family:monospace;">
        <?= $m ?>:<?= str_pad($s,2,'0',STR_PAD_LEFT) ?>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- ─── Items list ─── -->
  <div style="border-top:1px solid rgba(255,255,255,0.06);">
    <?php foreach ($items as $idx => $item):
      $dur    = (int)$item['duration'];
      $type   = $item['media_type'] ?? $item['type'] ?? 'image';
      $src    = $item['file_path'] ?? $item['url'] ?? '';
      if (str_contains($src,'.m3u8')) $type = 'hls';
      if (str_starts_with($src,'rtsp://')) $type = 'rtsp';
      if (str_contains($src,'.xml')) $type = 'xml';
      [$c1,$c2] = $typeColors[$type] ?? $typeColors['default'];
      $isLive = in_array($type,['hls','rtsp']);
      $m = floor($dur/60); $s = $dur%60;
    ?>
    <div class="timeline-item" data-id="<?=$item['id']?>" data-idx="<?=$idx?>"
      style="display:flex;align-items:center;gap:12px;padding:10px 18px;
             border-bottom:1px solid rgba(255,255,255,0.04);
             cursor:pointer;transition:background 0.15s;"
      onmouseenter="this.style.background='rgba(255,255,255,0.03)'"
      onmouseleave="this.style.background=''">

      <!-- رنگ نوع -->
      <div style="width:4px;height:48px;border-radius:4px;background:linear-gradient(180deg,<?=$c1?>,<?=$c2?>);flex-shrink:0;"></div>

      <!-- شماره -->
      <div style="width:24px;height:24px;background:rgba(255,255,255,0.05);border-radius:7px;
                  display:flex;align-items:center;justify-content:center;
                  font-size:11px;font-weight:700;color:#64748b;flex-shrink:0;">
        <?= $idx+1 ?>
      </div>

      <!-- تامبنیل -->
      <div style="width:72px;height:45px;border-radius:8px;overflow:hidden;flex-shrink:0;
                  background:#0a0a14;border:1px solid rgba(255,255,255,0.06);">
        <?php if ($type==='image' && ($item['thumbnail_path']??'')): ?>
        <img src="<?=e($item['thumbnail_path'])?>" style="width:100%;height:100%;object-fit:cover;">
        <?php elseif ($isLive): ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#0a0a1e;">
          <i class="fas fa-signal" style="color:#ef4444;font-size:18px;"></i>
        </div>
        <?php elseif ($type==='video'): ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#1a0a2e;">
          <i class="fas fa-play-circle" style="color:#a855f7;font-size:20px;"></i>
        </div>
        <?php elseif ($type==='xml'): ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#1a1400;">
          <i class="fas fa-code" style="color:#f59e0b;font-size:18px;"></i>
        </div>
        <?php else: ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#0a1a0a;">
          <i class="fas fa-globe" style="color:#22c55e;font-size:18px;"></i>
        </div>
        <?php endif; ?>
      </div>

      <!-- اطلاعات -->
      <div style="flex:1;min-width:0;">
        <div style="font-size:13px;font-weight:600;color:#fff;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
          <?= e($item['media_name'] ?? ($isLive ? 'استریم زنده' : 'رسانه')) ?>
        </div>
        <div style="display:flex;align-items:center;gap:10px;margin-top:3px;">
          <span style="font-size:10px;font-weight:700;color:<?=$c1?>;background:<?=$c1?>18;
                       padding:2px 7px;border-radius:5px;border:1px solid <?=$c1?>44;">
            <?= strtoupper($type) ?>
            <?php if ($isLive): ?> <i class="fas fa-circle" style="font-size:6px;color:#ef4444;"></i><?php endif; ?>
          </span>
          <?php if ($item['start_at'] || $item['end_at']): ?>
          <span style="font-size:10px;color:#64748b;">
            <i class="fas fa-clock text-xs ml-1"></i><?= $item['start_at']??'--' ?> → <?= $item['end_at']??'--' ?>
          </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- مدت -->
      <div style="text-align:center;padding:0 12px;">
        <div style="font-size:22px;font-weight:900;color:#f97316;font-family:monospace;line-height:1;">
          <?= $isLive ? '∞' : ($m>0 ? "{$m}:{$s}" : "{$dur}s") ?>
        </div>
        <div style="font-size:10px;color:#475569;"><?= $isLive ? 'زنده' : 'ثانیه' ?></div>
      </div>

      <!-- دکمه‌ها -->
      <div style="display:flex;gap:4px;">
        <button onclick="event.stopPropagation();openItemEditor(<?=$idx?>)"
          class="btn-ghost text-xs px-2 py-1" title="ویرایش">
          <i class="fas fa-pencil text-blue-400 text-xs"></i>
        </button>
        <button onclick="event.stopPropagation();moveItem(<?=$item['id']?>,<?=$idx?>,'up')"
          class="btn-ghost text-xs px-2 py-1" title="بالاتر" <?=$idx===0?'disabled':''?>>
          <i class="fas fa-arrow-up text-slate-400 text-xs"></i>
        </button>
        <button onclick="event.stopPropagation();moveItem(<?=$item['id']?>,<?=$idx?>,'down')"
          class="btn-ghost text-xs px-2 py-1" title="پایین‌تر" <?=$idx===count($items)-1?'disabled':''?>>
          <i class="fas fa-arrow-down text-slate-400 text-xs"></i>
        </button>
        <form method="POST" action="/admin/playlists/<?=$playlist['id']?>/items/<?=$item['id']?>/delete" class="inline" onclick="event.stopPropagation()">
          <?=csrf_field()?>
          <button type="submit" class="btn-ghost text-xs px-2 py-1" onclick="return confirm('حذف؟')">
            <i class="fas fa-trash text-red-400 text-xs"></i>
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ─── ستون کناری ─── -->
<div style="display:grid;grid-template-columns:1fr 280px;gap:16px;" id="bottom-row">
  <!-- screens -->
  <?php if (!empty($screens)): ?>
  <div class="card">
    <h3 style="font-size:13px;font-weight:700;color:#fff;margin-bottom:12px;">
      <i class="fas fa-tv" style="color:#f97316;margin-left:8px;"></i>صفحاتی که این پلی‌لیست را دارند
    </h3>
    <?php foreach ($screens as $s): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
      <span style="width:8px;height:8px;border-radius:50%;background:<?= $s['is_online'] ? '#4ade80' : '#64748b' ?>; flex-shrink:0;"></span>
      <span style="font-size:13px;color:#e2e8f0;"><?= e($s['name']) ?></span>
      <a href="/admin/schedules" style="margin-right:auto;font-size:11px;color:#f97316;text-decoration:none;">زمان‌بندی →</a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="card" style="background:rgba(249,115,22,0.04);border-color:rgba(249,115,22,0.15);">
    <p style="font-size:12px;color:#64748b;text-align:center;padding:8px 0;">
      <i class="fas fa-info-circle" style="color:#f97316;margin-left:6px;"></i>
      این پلی‌لیست به هیچ صفحه‌ای وصل نشده.<br>
      <a href="/admin/schedules" style="color:#f97316;text-decoration:none;">از زمان‌بندی وصل کنید ←</a>
    </p>
  </div>
  <?php endif; ?>

  <!-- stats -->
  <div class="card">
    <h3 style="font-size:13px;font-weight:700;color:#fff;margin-bottom:12px;">
      <i class="fas fa-chart-bar" style="color:#818cf8;margin-left:8px;"></i>آمار پلی‌لیست
    </h3>
    <?php
    $typeCounts = array_count_values(array_map(fn($i) => $i['media_type'] ?? $i['type'] ?? 'other', $items));
    $statItems = [
      ['آیتم‌ها', count($items), 'fas fa-list'],
      ['تصاویر', $typeCounts['image']??0, 'fas fa-image'],
      ['ویدیوها', $typeCounts['video']??0, 'fas fa-play-circle'],
      ['استریم', ($typeCounts['hls']??0)+($typeCounts['rtsp']??0), 'fas fa-signal'],
      ['URL', $typeCounts['url']??0, 'fas fa-globe'],
    ];
    foreach ($statItems as [$l,$v,$ic]):
    ?>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:12px;">
      <span style="color:#64748b;"><i class="<?=$ic?> text-xs ml-1"></i><?=$l?></span>
      <strong style="color:#f97316;"><?=$v?></strong>
    </div>
    <?php endforeach; ?>
    <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:12px;">
      <span style="color:#64748b;">مدت کل</span>
      <strong style="color:#fff;font-family:monospace;"><?= $totalMin ?>:<?= str_pad($totalSec2,2,'0',STR_PAD_LEFT) ?></strong>
    </div>
  </div>
</div>

<!-- ─── Modal افزودن محتوا ─── -->
<div id="addItemModal" class="modal-overlay hidden">
  <div class="modal" style="max-width:680px;width:95vw;max-height:85vh;display:flex;flex-direction:column;padding:0;overflow:hidden;">
    <!-- Modal Header - ثابت -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.06);flex-shrink:0;">
      <h3 style="font-weight:700;color:#fff;font-size:15px;">
        <i class="fas fa-plus text-orange-400 ml-2"></i> افزودن محتوا به پلی‌لیست
      </h3>
      <button onclick="document.getElementById('addItemModal').classList.add('hidden')"
        style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;">&times;</button>
    </div>

    <!-- تب نوع محتوا -->
    <div style="padding:10px 20px 0;flex-shrink:0;">
      <div style="display:flex;gap:2px;background:rgba(0,0,0,0.4);border-radius:10px;padding:4px;margin-bottom:12px;overflow-x:auto;">
      <?php foreach([
        ['media','🖼 رسانه','image/video'],
        ['stream','📡 استریم زنده','HLS/RTSP'],
        ['xml','📄 محتوای XML','feed/data'],
        ['url','🌐 صفحه وب','iframe'],
        ['module','🧩 ماژول سیستم','FIDS · Hotel · Menu'],
      ] as [$t,$l,$sub]): ?>
      <button onclick="aiSetTab('<?=$t?>')" id="ai-tab-<?=$t?>"
        style="flex:1;padding:9px 6px;border-radius:7px;border:none;cursor:pointer;
               font-size:11px;font-weight:600;font-family:'Vazirmatn',sans-serif;transition:all 0.2s;
               background:<?=$t==='media'?'rgba(249,115,22,0.2)':'transparent'?>;
               color:<?=$t==='media'?'#f97316':'#64748b'?>;">
        <?=$l?><br><span style="font-weight:400;font-size:9px;opacity:0.7;"><?=$sub?></span>
      </button>
      <?php endforeach; ?>
    </div>

    <form method="POST" action="/admin/playlists/<?=$playlist['id']?>/items"
      style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
      <?=csrf_field()?>
      <input type="hidden" name="media_id" id="ai-media-id" value="">
      <input type="hidden" name="stream_url" id="ai-stream-url" value="">
      <input type="hidden" name="xml_url" id="ai-xml-url" value="">
      <input type="hidden" name="webpage_url" id="ai-webpage-url" value="">
      <input type="hidden" name="content_type" id="ai-content-type" value="media">

<div style="flex:1;overflow-y:auto;padding:0 20px;min-height:0;">
      <!-- بخش رسانه -->
      <div id="ai-sec-media" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;padding:0 20px;">
        <div style="margin-bottom:10px;flex-shrink:0;">
          <input type="text" id="ai-search" class="form-input" placeholder="🔍 جستجو..." oninput="aiFilter(this.value)">
        </div>
        <div id="ai-media-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:6px;overflow-y:auto;flex:1;">
          <?php foreach ($media as $m):
            $thumb = $m['thumbnail_path'] ?? '';
            $type  = $m['type'];
          ?>
          <div onclick="aiSelectMedia(<?=$m['id']?>,'<?=addslashes($m['name'])?>',this)"
            class="ai-media-item" data-name="<?=strtolower(e($m['name']))?>"
            style="border-radius:10px;overflow:hidden;border:2px solid rgba(255,255,255,0.08);cursor:pointer;transition:all 0.15s;">
            <div style="height:72px;background:#0d0d14;overflow:hidden;">
              <?php if ($type==='image' && $thumb): ?>
              <img src="<?=e($thumb)?>" style="width:100%;height:100%;object-fit:cover;">
              <?php elseif ($type==='video'): ?>
              <div style="height:100%;display:flex;align-items:center;justify-content:center;background:#1a0a2e;">
                <i class="fas fa-play-circle" style="font-size:24px;color:#a855f7;"></i>
              </div>
              <?php else: ?>
              <div style="height:100%;display:flex;align-items:center;justify-content:center;background:#0a1428;">
                <i class="fas fa-globe" style="font-size:24px;color:#3b82f6;"></i>
              </div>
              <?php endif; ?>
            </div>
            <div style="padding:5px 6px;font-size:10px;color:#94a3b8;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
              <?=e($m['name'])?>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($media)): ?>
          <div style="grid-column:1/-1;text-align:center;padding:32px;color:#475569;">
            <p>رسانه‌ای آپلود نشده</p>
            <a href="/admin/media" style="color:#f97316;font-size:12px;">آپلود رسانه ←</a>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- بخش استریم -->
      <div id="ai-sec-stream" style="display:none;flex:1;overflow-y:auto;">
        <div class="space-y-3">
          <div style="background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.2);border-radius:10px;padding:12px;font-size:12px;color:#94a3b8;line-height:1.8;">
            <strong style="color:#f87171;">📡 استریم زنده:</strong><br>
            • HLS: <code style="color:#60a5fa;">https://server/stream.m3u8</code><br>
            • RTSP: <code style="color:#60a5fa;">rtsp://camera-ip:554/stream</code>
          </div>
          <div><label class="form-label">آدرس استریم</label>
            <input type="url" id="ai-stream-input" class="form-input" placeholder="https://example.com/live.m3u8"
              oninput="document.getElementById('ai-stream-url').value=this.value"></div>
          <div><label class="form-label">نام نمایشی</label>
            <input type="text" id="ai-stream-name" class="form-input" placeholder="دوربین لابی"></div>
        </div>
      </div>

      <!-- بخش XML -->
      <div id="ai-sec-xml" style="display:none;flex:1;overflow-y:auto;">
        <div class="space-y-3">
          <div style="background:rgba(245,158,11,0.07);border:1px solid rgba(245,158,11,0.2);border-radius:10px;padding:12px;font-size:12px;color:#94a3b8;line-height:1.8;">
            <strong style="color:#fbbf24;">📄 محتوای XML/RSS:</strong><br>
            • RSS/XML Feed: <code style="color:#60a5fa;">https://rss.example.com/feed.xml</code><br>
            • سیستم FIDS XML، خبرگزاری‌ها، داده‌های زنده
          </div>
          <div><label class="form-label">آدرس XML/RSS</label>
            <input type="url" id="ai-xml-input" class="form-input" placeholder="https://example.com/feed.xml"
              oninput="document.getElementById('ai-xml-url').value=this.value"></div>
          <div><label class="form-label">نوع محتوا</label>
            <select name="xml_type" class="form-input">
              <option value="rss">RSS خبر</option>
              <option value="fids_xml">FIDS XML (پروازی)</option>
              <option value="weather_xml">آب‌وهوا XML</option>
              <option value="custom_xml">XML سفارشی</option>
            </select></div>
        </div>
      </div>

      <!-- بخش URL -->
      <div id="ai-sec-url" style="display:none;flex:1;overflow-y:auto;">
        <div class="space-y-3">
          <div><label class="form-label">آدرس صفحه وب</label>
            <input type="url" id="ai-url-input" class="form-input" placeholder="https://example.com"
              oninput="document.getElementById('ai-webpage-url').value=this.value"></div>
          <div style="font-size:11px;color:#475569;">صفحه وب در iframe نمایش داده می‌شود</div>
        </div>
      </div>

      <!-- ─── بخش ماژول ─── -->
      <div id="ai-sec-module" style="display:none;flex:1;overflow-y:auto;">
        <input type="hidden" name="module_type" id="ai-module-type" value="">
        <input type="hidden" name="module_settings" id="ai-module-settings" value="{}">

        <!-- انتخاب ماژول -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px;" id="ai-module-list">
          <?php
          $availableModules = [
            'fids'      => ['✈','FIDS','تابلوی پروازی','#0ea5e9'],
            'hotel'     => ['🏨','Hotel','اطلاع‌رسانی هتل','#d4af37'],
            'menu'      => ['🍽','Menu','منوی رستوران','#22c55e'],
            'corporate' => ['📊','Corporate','سازمانی','#6366f1'],
            'retail'    => ['🛍','Retail','فروشگاه','#f59e0b'],
            'transport' => ['🚌','Transport','حمل‌ونقل','#06b6d4'],
          ];
          foreach ($availableModules as $mod=>[$ico,$name,$desc,$clr]): ?>
          <div onclick="selectModule('<?=$mod?>')" id="mod-card-<?=$mod?>"
            style="padding:10px 6px;border-radius:10px;text-align:center;cursor:pointer;
                   border:2px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03);
                   transition:all .2s;">
            <div style="font-size:20px;margin-bottom:4px;"><?=$ico?></div>
            <div style="font-size:11px;font-weight:700;color:#94a3b8;"><?=$name?></div>
            <div style="font-size:9px;color:#475569;"><?=$desc?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- تنظیمات ماژول (دینامیک) -->
        <div id="ai-module-settings-form" style="display:none;background:#0d0d14;border-radius:12px;padding:14px;border:1px solid rgba(255,255,255,.08);">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <h4 id="ai-mod-title" style="font-size:13px;font-weight:700;color:#fff;"></h4>
            <button type="button" onclick="clearModule()" style="background:none;border:none;color:#64748b;cursor:pointer;font-size:16px;">&times;</button>
          </div>
          <div id="ai-mod-fields"></div>
        </div>
      </div>

</div>
      <!-- تنظیمات مشترک - sticky footer -->
      <div style="flex-shrink:0;padding:12px 20px 0;border-top:1px solid rgba(255,255,255,0.06);background:#16161f;">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
          <div>
            <label class="form-label">مدت نمایش (ثانیه)</label>
            <input type="number" name="duration" class="form-input" value="10" min="1" id="ai-duration">
          </div>
          <div>
            <label class="form-label">شروع از ساعت</label>
            <input type="time" name="start_at" class="form-input">
          </div>
          <div>
            <label class="form-label">پایان در ساعت</label>
            <input type="time" name="end_at" class="form-input">
          </div>
        </div>
        <div style="margin-top:10px;display:flex;align-items:center;gap:16px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#94a3b8;">
            <input type="checkbox" name="skip_on_weekends" class="accent-orange-500 w-4 h-4">
            آخر هفته‌ها پخش نشود
          </label>
        </div>
      </div>

      <!-- submit -->
      <div style="display:flex;gap:10px;margin-top:12px;padding-bottom:4px;flex-shrink:0;">
        <button type="submit" id="ai-submit" class="btn-primary flex-1 py-3" style="font-size:14px;">
          <i class="fas fa-plus text-xs ml-1"></i> افزودن به پلی‌لیست
        </button>
        <button type="button" onclick="document.getElementById('addItemModal').classList.add('hidden')"
          class="btn-ghost px-6">لغو</button>
      </div>
    </form>
  </div>
</div>

<!-- ─── Modal ویرایش آیتم ─── -->
<div id="editItemModal" class="modal-overlay hidden">
  <div class="modal max-w-md">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-weight:700;color:#fff;" id="edit-item-title">ویرایش آیتم</h3>
      <button onclick="document.getElementById('editItemModal').classList.add('hidden')"
        style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;">&times;</button>
    </div>
    <form id="editItemForm" method="POST" class="space-y-3">
      <?=csrf_field()?>
      <input type="hidden" name="edit_mode" value="1">
      <div><label class="form-label">مدت نمایش (ثانیه)</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
          <?php foreach([5,10,15,30,60,120,300,600] as $preset): ?>
          <button type="button" onclick="document.getElementById('edit-duration').value=<?=$preset?>"
            class="btn-ghost text-xs px-3 py-1">
            <?= $preset < 60 ? "{$preset}s" : floor($preset/60)."m" ?>
          </button>
          <?php endforeach; ?>
        </div>
        <input type="number" name="duration" id="edit-duration" class="form-input" min="1"></div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div><label class="form-label">شروع (ساعت روز)</label>
          <input type="time" name="start_at" id="edit-start" class="form-input"></div>
        <div><label class="form-label">پایان (ساعت روز)</label>
          <input type="time" name="end_at" id="edit-end" class="form-input"></div>
      </div>
      <div><label class="form-label">صدا (%)</label>
        <input type="range" name="volume" id="edit-volume" class="form-input" min="0" max="100" value="100"></div>

      <div style="display:flex;gap:10px;padding-top:8px;">
        <button type="submit" class="btn-primary flex-1 py-2.5">ذخیره</button>
        <button type="button" onclick="document.getElementById('editItemModal').classList.add('hidden')"
          class="btn-ghost px-5">لغو</button>
      </div>
    </form>
  </div>
</div>

<?php
$itemsJson  = json_encode($items, JSON_UNESCAPED_UNICODE);
$playlistId = (int)($playlist['id'] ?? 0);
$extraScript = <<<JSEOF
// ─── Items data ───────────────────────────────────────────────
const ITEMS = {$itemsJson};
const PLAYLIST_ID = {$playlistId};

// ─── Tab switching ─────────────────────────────────────────────
JSEOF;
?>

<script>
function aiSetTab(t) {
  ['media','stream','xml','url','module'].forEach(tab => {
    const sec = document.getElementById('ai-sec-' + tab);
    const btn = document.getElementById('ai-tab-' + tab);
    if (sec) sec.style.display = tab === t ? '' : 'none';
    if (btn) {
      btn.style.background = tab === t ? 'rgba(249,115,22,0.2)' : '';
      btn.style.color = tab === t ? '#f97316' : '#64748b';
    }
  });
  document.getElementById('ai-content-type').value = t;
  const dur = document.getElementById('ai-duration');
  if (t === 'stream') dur.value = 0;
  else if (t === 'xml') dur.value = 30;
  else if (t === 'module') dur.value = 30;
  else dur.value = 10;
}

// ─── Media selection ───────────────────────────────────────────
let selectedMediaId = null;
function aiSelectMedia(id, name, el) {
  selectedMediaId = id;
  document.getElementById('ai-media-id').value = id;
  document.querySelectorAll('.ai-media-item').forEach(e => {
    e.style.borderColor = 'rgba(255,255,255,0.08)';
    e.style.boxShadow = 'none';
  });
  el.style.borderColor = '#f97316';
  el.style.boxShadow = '0 0 0 2px rgba(249,115,22,0.3)';
}

function aiFilter(q) {
  document.querySelectorAll('.ai-media-item').forEach(el => {
    el.style.display = (!q || el.dataset.name.includes(q.toLowerCase())) ? '' : 'none';
  });
}

// ─── Item editor ───────────────────────────────────────────────
function openItemEditor(idx) {
  const item = ITEMS[idx];
  if (!item) return;
  document.getElementById('edit-item-title').textContent = item.media_name || 'ویرایش آیتم';
  document.getElementById('editItemForm').action = '/admin/playlists/' + PLAYLIST_ID + '/items/' + item.id + '/edit';
  document.getElementById('edit-duration').value = item.duration || 10;
  document.getElementById('edit-start').value    = item.start_at || '';
  document.getElementById('edit-end').value      = item.end_at || '';
  document.getElementById('edit-volume').value   = item.volume || 100;
  document.getElementById('editItemModal').classList.remove('hidden');
}


// ─── Move items ────────────────────────────────────────────────
async function moveItem(id, idx, dir) {
  const swapIdx = dir === 'up' ? idx - 1 : idx + 1;
  if (swapIdx < 0 || swapIdx >= ITEMS.length) return;
  const ids = ITEMS.map(i => i.id);
  [ids[idx], ids[swapIdx]] = [ids[swapIdx], ids[idx]];
  await fetch('/admin/playlists/' + PLAYLIST_ID + '/items/reorder', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'order=' + JSON.stringify(ids) + '&_token=' + document.querySelector('meta[name=csrf-token]').content
  });
  location.reload();
}

// ─── Module selector ──────────────────────────────────────────
const MODULE_FIELDS = {
  iptv: {
    title: '📡 تنظیمات IPTV',
    color: '#ef4444',
    fields: [
      {key:'stream_url', label:'آدرس استریم', type:'text', defVal:''},
      {key:'channel_name', label:'نام کانال', type:'text', defVal:'کانال زنده'},
      {key:'show_info', label:'نمایش اطلاعات', type:'select', options:{'1':'بله','0':'خیر'}, defVal:'1'},
    ]
  },
  fids: {
    title: '✈ تنظیمات FIDS',
    color: '#0ea5e9',
    fields: [
      {key:'zone_type', label:'نوع نمایش', type:'select', triggerVisibility:true, options:{
        'fids_live_board':'🛰 تابلو زنده (fids.airport.ir)',
        'departure':'خروجی — پروازهای رفت (دستی)',
        'arrival':'ورودی — پروازهای آمد (دستی)',
        'split_flap':'Split-Flap کلاسیک',
        'gate':'نمایش Gate',
      }, defVal:'fids_live_board'},
      // ── فقط برای تابلو زنده ─────────────────────────────────────────
      {key:'airport_id', label:'فرودگاه / شهر', type:'select',
       showIf:{key:'zone_type', val:'fids_live_board'},
       options:{
        '2':'مهرآباد (تهران)','102':'مشهد','1':'شیراز','103':'تبریز',
        '114':'اصفهان','401':'اهواز','104':'بوشهر','201':'کرمان',
        '117':'بندرعباس','110':'ارومیه','203':'رشت','109':'زاهدان',
        '301':'آبادان','202':'گرگان','112':'همدان','113':'اردبیل',
        '105':'ایلام','204':'بیرجند','402':'سنندج','108':'شهرکرد',
        '107':'یزد','106':'ساری','111':'کرمانشاه','901':'بجنورد',
        '601':'لارستان','701':'خرم‌آباد','801':'سمنان','1201':'نوشهر',
        '802':'شاهرود','1001':'یاسوج','501':'زنجان','1401':'اراک','1501':'زابل',
       }, defVal:'2'},
      {key:'direction', label:'نوع پرواز', type:'select',
       showIf:{key:'zone_type', val:'fids_live_board'},
       options:{'arrival':'ورودی (Arrivals)','departure':'خروجی (Departures)','all':'هر دو'},
       defVal:'departure'},
      {key:'route_type', label:'مسیر پرواز', type:'select',
       showIf:{key:'zone_type', val:'fids_live_board'},
       options:{'domestic':'داخلی','international':'خارجی','all':'داخلی + خارجی'},
       defVal:'domestic'},
      // ── مشترک ─────────────────────────────────────────────────────
      {key:'rows', label:'تعداد ردیف', type:'number', defVal:14, min:4, max:30},
      {key:'color_scheme', label:'تم رنگی', type:'select',
       options:{dark:'تاریک',airport:'فرودگاهی',navy:'Navy'}, defVal:'dark'},
      {key:'lang', label:'زبان', type:'select',
       options:{fa:'فارسی',en:'انگلیسی',both:'دوزبانه'}, defVal:'fa'},
      {key:'refresh_sec', label:'بروزرسانی (ثانیه)', type:'number', defVal:60, min:30, max:300},
      {key:'show_logo', label:'نمایش لوگو ایرلاین', type:'select',
       showIf:{key:'zone_type', val:'fids_live_board', negate:true},
       options:{'1':'بله','0':'خیر'}, defVal:'1'},
    ]
  },
  hotel: {
    title: '🏨 تنظیمات Hotel',
    color: '#d4af37',
    fields: [
      {key:'zone_type', label:'نوع نمایش', type:'select', options:{events:'رویدادها','amenities':'امکانات','room_service':'سرویس اتاق','welcome':'صفحه خوش‌آمد','attractions':'جاذبه‌ها'}, defVal:'events'},
      {key:'theme', label:'تم', type:'select', options:{luxury:'لوکس (پیش‌فرض)','modern':'مدرن','classic':'کلاسیک'}, defVal:'luxury'},
      {key:'language', label:'زبان', type:'select', options:{fa:'فارسی','en':'انگلیسی','both':'دوزبانه'}, defVal:'fa'},
      {key:'auto_scroll', label:'پیمایش خودکار', type:'select', options:{'1':'بله','0':'خیر'}, defVal:'1'},
    ]
  },
  menu: {
    title: '🍽 تنظیمات Menu',
    color: '#22c55e',
    fields: [
      {key:'zone_type', label:'نوع نمایش', type:'select', options:{full_menu:'منوی کامل','daily_special':'غذای روز','category':'یک دسته'}, defVal:'full_menu'},
      {key:'category_id', label:'دسته (اختیاری)', type:'number', defVal:0},
      {key:'theme', label:'تم', type:'select', options:{dark:'تاریک',light:'روشن',rustic:'رستیک'}, defVal:'dark'},
      {key:'show_prices', label:'نمایش قیمت', type:'select', options:{'1':'بله','0':'خیر'}, defVal:'1'},
      {key:'currency', label:'واحد پول', type:'text', defVal:'تومان'},
    ]
  },
  corporate: {
    title: '📊 تنظیمات Corporate',
    color: '#6366f1',
    fields: [
      {key:'zone_type', label:'نوع', type:'select', options:{kpi:'KPI‌ها','news':'اخبار','departments':'راهنمای طبقات','mixed':'ترکیبی'}, defVal:'kpi'},
      {key:'theme', label:'تم', type:'select', options:{dark:'تاریک','corporate':'سازمانی'}, defVal:'dark'},
      {key:'refresh_min', label:'تازه‌سازی (دقیقه)', type:'number', defVal:5, min:1, max:60},
    ]
  },
  retail: {
    title: '🛍 تنظیمات Retail',
    color: '#f59e0b',
    fields: [
      {key:'zone_type', label:'نوع نمایش', type:'select', options:{products:'محصولات',offers:'پیشنهادات ویژه',queue:'صف انتظار',featured:'محصولات ویژه'}, defVal:'products'},
      {key:'theme', label:'تم', type:'select', options:{dark:'تاریک',bright:'روشن'}, defVal:'dark'},
      {key:'show_prices', label:'نمایش قیمت', type:'select', options:{'1':'بله','0':'خیر'}, defVal:'1'},
      {key:'currency', label:'واحد پول', type:'text', defVal:'تومان'},
    ]
  },
  transport: {
    title: '🚌 تنظیمات Transport',
    color: '#06b6d4',
    fields: [
      {key:'zone_type', label:'نوع', type:'select', options:{schedule:'جدول حرکت',realtime:'Real-time'}, defVal:'schedule'},
      {key:'transport_type', label:'نوع حمل‌ونقل', type:'select', options:{bus:'اتوبوس',metro:'مترو',train:'قطار'}, defVal:'bus'},
      {key:'theme', label:'تم', type:'select', options:{dark:'تاریک',transit:'ترانزیت'}, defVal:'dark'},
    ]
  },
};

let _selectedModule = null;

function selectModule(name) {
  _selectedModule = name;
  const def = MODULE_FIELDS[name];
  if (!def) return;

  // highlight
  document.querySelectorAll('[id^="mod-card-"]').forEach(el => {
    el.style.background = 'rgba(255,255,255,0.03)';
    el.style.borderColor = 'rgba(255,255,255,0.08)';
  });
  const card = document.getElementById('mod-card-' + name);
  if (card) {
    card.style.background = def.color + '18';
    card.style.borderColor = def.color + '88';
  }

  document.getElementById('ai-module-type').value = name;
  document.getElementById('ai-mod-title').textContent = def.title;
  document.getElementById('ai-mod-title').style.color = def.color;

  // ساخت فرم فیلدها
  const container = document.getElementById('ai-mod-fields');
  container.innerHTML = def.fields.map(f => {
    // data-showif برای visibility
    const siAttr = f.showIf
      ? ` data-showif-key="${f.showIf.key}" data-showif-val="${f.showIf.val}" data-showif-negate="${f.showIf.negate?'1':'0'}"`
      : '';
    const trigAttr = f.triggerVisibility ? ` data-trigger-visibility="1"` : '';

    if (f.type === 'select') {
      const opts = Object.entries(f.options).map(([v,l]) =>
        `<option value="${v}" ${v==f.defVal?'selected':''}>${l}</option>`).join('');
      return `<div class="msf-field" style="margin-bottom:10px;"${siAttr}>
        <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:4px;">${f.label}</label>
        <select name="msf_${f.key}" class="form-input" style="font-size:12px;"${trigAttr}
          onchange="updateModuleSettings();checkModuleFieldVisibility()">
          ${opts}
        </select>
      </div>`;
    } else if (f.type === 'number') {
      return `<div class="msf-field" style="margin-bottom:10px;"${siAttr}>
        <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:4px;">${f.label}</label>
        <input type="number" name="msf_${f.key}" class="form-input" style="font-size:12px;"
          value="${f.defVal}" min="${f.min||0}" max="${f.max||999}" onchange="updateModuleSettings()">
      </div>`;
    } else {
      return `<div class="msf-field" style="margin-bottom:10px;"${siAttr}>
        <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:4px;">${f.label}</label>
        <input type="text" name="msf_${f.key}" class="form-input" style="font-size:12px;"
          value="${f.defVal||''}" oninput="updateModuleSettings()">
      </div>`;
    }
  }).join('');

  document.getElementById('ai-module-settings-form').style.display = '';
  checkModuleFieldVisibility();
  updateModuleSettings();
}

/** نمایش/پنهان‌کردن فیلدها بر اساس showIf */
function checkModuleFieldVisibility() {
  const fields = document.querySelectorAll('#ai-mod-fields .msf-field[data-showif-key]');
  fields.forEach(div => {
    const depKey    = div.dataset.showifKey;
    const depVal    = div.dataset.showifVal;
    const negate    = div.dataset.showifNegate === '1';
    const trigger   = document.querySelector(`[name="msf_${depKey}"]`);
    if (!trigger) return;
    const match = trigger.value === depVal;
    div.style.display = (negate ? !match : match) ? '' : 'none';
  });
}

function updateModuleSettings() {
  if (!_selectedModule) return;
  const def = MODULE_FIELDS[_selectedModule];
  const settings = {};
  def.fields.forEach(f => {
    const el = document.querySelector(`[name="msf_${f.key}"]`);
    if (!el) return;
    // فقط فیلدهای visible رو بگیر
    const wrapper = el.closest('.msf-field');
    if (wrapper && wrapper.style.display === 'none') return;
    settings[f.key] = el.value;
  });
  document.getElementById('ai-module-settings').value = JSON.stringify(settings);
}

function clearModule() {
  _selectedModule = null;
  document.getElementById('ai-module-type').value = '';
  document.getElementById('ai-module-settings').value = '{}';
  document.getElementById('ai-module-settings-form').style.display = 'none';
  document.querySelectorAll('[id^="mod-card-"]').forEach(el => {
    el.style.background = 'rgba(255,255,255,0.03)';
    el.style.borderColor = 'rgba(255,255,255,0.08)';
  });
}

// aiSetTab handles module tab natively



</script>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
