<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<style>
.card-3d {
  background:#111118;
  border:1px solid rgba(0,229,255,.15);
  border-radius:16px;
  overflow:hidden;
  transition:border-color .2s, box-shadow .2s;
}
.card-3d:hover {
  border-color:rgba(0,229,255,.35);
  box-shadow:0 0 24px rgba(0,229,255,.1);
}
.format-badge {
  display:inline-flex; align-items:center; gap:5px;
  padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700;
  letter-spacing:.5px; text-transform:uppercase;
}
</style>

<!-- ─── Header ──────────────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
  <div>
    <h1 style="font-size:22px;font-weight:800;color:#fff;display:flex;align-items:center;gap:10px;">
      <span style="font-size:28px;">⬡</span> مانیتورهای ۳D
    </h1>
    <p style="font-size:13px;color:#475569;margin-top:4px;">
      نمایشگرهای تبلیغاتی سه‌بعدی (LED Hologram / Glasses-free 3D) — تنظیمات عمق و فرمت
    </p>
  </div>
  <a href="/admin/screens/create?type=monitor_3d"
     style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;
            background:linear-gradient(135deg,#00e5ff,#0097a7);color:#000;
            border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;">
    <i class="fas fa-plus text-xs"></i> افزودن مانیتور ۳D
  </a>
</div>

<!-- ─── Stats bar ────────────────────────────────────────────────────── -->
<?php
$total  = count($screens ?? []);
$online = count(array_filter($screens ?? [], fn($s) => $s['is_online']));
?>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px;">
  <?php foreach ([
    ['⬡ کل صفحات',    $total,          '#00e5ff', 'fas fa-layer-group'],
    ['● آنلاین',       $online,         '#22c55e', 'fas fa-circle'],
    ['○ آفلاین',       $total-$online,  '#ef4444', 'fas fa-circle-dot'],
    ['فرمت‌ها',        count(array_unique(array_column($screens??[], 'format_3d'))), '#a855f7', 'fas fa-cube'],
  ] as [$label, $val, $col, $icon]): ?>
  <div style="background:#111118;border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:16px 20px;">
    <div style="font-size:11px;color:#475569;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
      <i class="<?=$icon?>" style="color:<?=$col?>;font-size:10px;"></i><?=$label?>
    </div>
    <div style="font-size:26px;font-weight:800;color:<?=$col?>;"><?=$val?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ─── راهنما ──────────────────────────────────────────────────────── -->
<div style="background:rgba(0,229,255,.04);border:1px solid rgba(0,229,255,.15);border-radius:14px;
            padding:16px 20px;margin-bottom:24px;display:flex;gap:16px;align-items:flex-start;">
  <span style="font-size:24px;flex-shrink:0;">💡</span>
  <div style="font-size:12px;color:#94a3b8;line-height:1.8;">
    <strong style="color:#00e5ff;">راهنما:</strong>
    برای اضافه کردن مانیتور ۳D ابتدا از بخش «صفحات نمایش» یک صفحه جدید با نوع
    <strong style="color:#fff;">Monitor 3D</strong> بسازید، سپس اینجا تنظیمات عمق را اعمال کنید.
    پلیر اختصاصی ۳D به طور خودکار انیمیشن عمق، شعاع هولوگرام و جلوه‌های مخصوص نمایشگرهای LED را اعمال می‌کند.
  </div>
</div>

<!-- ─── Screen cards ─────────────────────────────────────────────────── -->
<?php if (empty($screens)): ?>
<div style="text-align:center;padding:80px 20px;">
  <div style="font-size:80px;opacity:0.12;margin-bottom:20px;">⬡</div>
  <div style="font-size:18px;color:#475569;margin-bottom:8px;">هنوز مانیتور ۳D اضافه نشده</div>
  <div style="font-size:13px;color:#334155;margin-bottom:24px;">از صفحات نمایش، نوع «Monitor 3D» را انتخاب کنید</div>
  <a href="/admin/screens/create?type=monitor_3d"
     style="display:inline-flex;align-items:center;gap:8px;padding:12px 24px;
            background:linear-gradient(135deg,#00e5ff,#0097a7);color:#000;
            border-radius:12px;font-size:14px;font-weight:700;text-decoration:none;">
    <i class="fas fa-plus text-xs"></i> افزودن اولین مانیتور ۳D
  </a>
</div>

<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(400px,1fr));gap:16px;">
<?php foreach ($screens as $s):
  $isOnline   = (bool)$s['is_online'];
  $secsAgo    = (int)($s['seconds_ago'] ?? 99999);
  $format     = $s['format_3d'] ?? 'normal';
  $dColor     = $s['depth_color'] ?? '#00e5ff';
  $formats    = [
    'normal'    => ['عادی + عمق', 'fas fa-layer-group'],
    'sbs'       => ['SBS استریو', 'fas fa-columns'],
    'top_bottom'=> ['Top-Bottom', 'fas fa-arrows-alt-v'],
    'hologram'  => ['هولوگرام', 'fas fa-atom'],
    'anaglyphic'=> ['Anaglyph', 'fas fa-glasses'],
  ];
  [$fLabel, $fIcon] = $formats[$format] ?? ['نامشخص', 'fas fa-cube'];
?>
<div class="card-3d">
  <!-- header -->
  <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.05);
              display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="width:36px;height:36px;border-radius:10px;
                  background:linear-gradient(135deg,<?=$dColor?>,<?=$dColor?>44);
                  display:flex;align-items:center;justify-content:center;
                  box-shadow:0 0 12px <?=$dColor?>40;font-size:18px;">⬡</div>
      <div>
        <div style="font-size:14px;font-weight:700;color:#fff;"><?= e($s['name']) ?></div>
        <div style="font-size:11px;color:#475569;"><?= e($s['location_name'] ?? 'بدون موقعیت') ?></div>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
      <!-- online status -->
      <span style="padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;
                   background:<?=$isOnline?'rgba(34,197,94,.15)':'rgba(100,116,139,.1)'?>;
                   color:<?=$isOnline?'#22c55e':'#64748b'?>;
                   border:1px solid <?=$isOnline?'rgba(34,197,94,.3)':'rgba(100,116,139,.2)'?>;">
        <?= $isOnline ? '● آنلاین' : '○ آفلاین' ?>
      </span>
      <!-- format badge -->
      <span class="format-badge"
            style="background:<?=$dColor?>18;color:<?=$dColor?>;border:1px solid <?=$dColor?>44;">
        <i class="<?=$fIcon?> text-xs"></i><?=$fLabel?>
      </span>
    </div>
  </div>

  <!-- config form -->
  <form method="POST" action="/admin/monitor3d/<?= $s['id'] ?>/config" style="padding:18px;">
    <?= csrf_field() ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
      <!-- فرمت 3D -->
      <div>
        <label class="form-label"><i class="fas fa-cube text-xs ml-1" style="color:#00e5ff;"></i>فرمت ۳D</label>
        <select name="format_3d" class="form-input">
          <option value="normal"     <?=$format==='normal'?'selected':''?>>🌀 عادی + عمق (پیش‌فرض)</option>
          <option value="hologram"   <?=$format==='hologram'?'selected':''?>>👾 هولوگرام LED</option>
          <option value="sbs"        <?=$format==='sbs'?'selected':''?>>🎭 SBS استریوسکوپیک</option>
          <option value="top_bottom" <?=$format==='top_bottom'?'selected':''?>>↕ Top-Bottom</option>
          <option value="anaglyphic" <?=$format==='anaglyphic'?'selected':''?>>👓 Anaglyphic قرمز-آبی</option>
        </select>
      </div>

      <!-- رنگ عمق -->
      <div>
        <label class="form-label"><i class="fas fa-palette text-xs ml-1" style="color:#a855f7;"></i>رنگ عمق / گلوی هولوگرام</label>
        <div style="display:flex;gap:8px;align-items:center;">
          <input type="color" name="depth_color" value="<?= e($dColor) ?>"
                 style="width:44px;height:36px;border:none;background:transparent;cursor:pointer;border-radius:8px;">
          <input type="text" value="<?= e($dColor) ?>"
                 style="flex:1;background:#0d0d14;border:1px solid rgba(255,255,255,.08);border-radius:8px;
                        padding:8px 10px;color:#fff;font-size:12px;font-family:monospace;"
                 oninput="this.previousElementSibling.value=this.value" readonly>
        </div>
      </div>

      <!-- رنگ پس‌زمینه -->
      <div>
        <label class="form-label"><i class="fas fa-fill-drip text-xs ml-1" style="color:#64748b;"></i>رنگ پس‌زمینه</label>
        <div style="display:flex;gap:8px;align-items:center;">
          <input type="color" name="bg_color" value="<?= e($s['bg_color'] ?? '#000000') ?>"
                 style="width:44px;height:36px;border:none;background:transparent;cursor:pointer;border-radius:8px;">
          <input type="text" value="<?= e($s['bg_color'] ?? '#000000') ?>"
                 style="flex:1;background:#0d0d14;border:1px solid rgba(255,255,255,.08);border-radius:8px;
                        padding:8px 10px;color:#fff;font-size:12px;font-family:monospace;"
                 oninput="this.previousElementSibling.value=this.value" readonly>
        </div>
      </div>

      <!-- سطح عمق -->
      <div>
        <label class="form-label" style="display:flex;justify-content:space-between;">
          <span><i class="fas fa-arrows-alt-z text-xs ml-1" style="color:#00e5ff;"></i>سطح عمق</span>
          <span id="depthVal_<?=$s['id']?>" style="color:#00e5ff;font-weight:700;"><?= $s['depth_level'] ?? 5 ?></span>
        </label>
        <input type="range" name="depth_level" min="1" max="10"
               value="<?= (int)($s['depth_level'] ?? 5) ?>"
               style="width:100%;accent-color:#00e5ff;"
               oninput="document.getElementById('depthVal_<?=$s['id']?>').textContent=this.value">
      </div>

      <!-- شدت parallax -->
      <div>
        <label class="form-label" style="display:flex;justify-content:space-between;">
          <span><i class="fas fa-wave-square text-xs ml-1" style="color:#a855f7;"></i>شدت Parallax</span>
          <span id="paraVal_<?=$s['id']?>" style="color:#a855f7;font-weight:700;"><?= $s['parallax_intensity'] ?? 6 ?></span>
        </label>
        <input type="range" name="parallax_intensity" min="1" max="10"
               value="<?= (int)($s['parallax_intensity'] ?? 6) ?>"
               style="width:100%;accent-color:#a855f7;"
               oninput="document.getElementById('paraVal_<?=$s['id']?>').textContent=this.value">
      </div>

      <!-- سرعت چرخش -->
      <div>
        <label class="form-label" style="display:flex;justify-content:space-between;">
          <span><i class="fas fa-sync text-xs ml-1" style="color:#f97316;"></i>سرعت چرخش</span>
          <span id="rotVal_<?=$s['id']?>" style="color:#f97316;font-weight:700;"><?= $s['rotate_speed'] ?? 5 ?></span>
        </label>
        <input type="range" name="rotate_speed" min="1" max="20"
               value="<?= (int)($s['rotate_speed'] ?? 5) ?>"
               style="width:100%;accent-color:#f97316;"
               oninput="document.getElementById('rotVal_<?=$s['id']?>').textContent=this.value">
      </div>
    </div>

    <!-- toggles -->
    <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:16px;">
      <?php foreach ([
        ['is_outdoor',       $s['is_outdoor']??0,       '#22c55e', 'fa-sun',          'نمایشگر فضای باز'],
        ['auto_rotate',      $s['auto_rotate']??0,      '#f97316', 'fa-sync',          'چرخش خودکار محور Y'],
        ['show_depth_badge', $s['show_depth_badge']??1, '#00e5ff', 'fa-certificate',   'نمایش نشان ۳D'],
      ] as [$name, $val, $col, $ico, $lbl]): ?>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
        <input type="checkbox" name="<?=$name?>" value="1" <?= $val ? 'checked' : '' ?>
               style="accent-color:<?=$col?>;width:16px;height:16px;">
        <span style="font-size:12px;color:#94a3b8;display:flex;align-items:center;gap:5px;">
          <i class="fas <?=$ico?> text-xs" style="color:<?=$col?>;"></i><?=$lbl?>
        </span>
      </label>
      <?php endforeach; ?>
    </div>

    <!-- buttons -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <button type="submit"
              style="padding:9px 20px;background:linear-gradient(135deg,#00e5ff,#0097a7);color:#000;
                     border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;
                     font-family:inherit;display:flex;align-items:center;gap:6px;">
        <i class="fas fa-save text-xs"></i> ذخیره تنظیمات
      </button>

      <a href="/admin/monitor3d/<?=$s['id']?>/preview" target="_blank"
         style="padding:9px 16px;background:rgba(0,229,255,.1);border:1px solid rgba(0,229,255,.3);
                color:#00e5ff;border-radius:10px;font-size:12px;text-decoration:none;
                display:inline-flex;align-items:center;gap:5px;">
        <i class="fas fa-eye text-xs"></i> پیش‌نمایش پلیر
      </a>

      <a href="/admin/screens/<?=$s['id']?>"
         style="padding:9px 16px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);
                color:#94a3b8;border-radius:10px;font-size:12px;text-decoration:none;
                display:inline-flex;align-items:center;gap:5px;">
        <i class="fas fa-sliders text-xs"></i> تنظیمات صفحه
      </a>

      <a href="/player/<?= e($s['code']) ?>" target="_blank"
         style="padding:9px 16px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);
                color:#22c55e;border-radius:10px;font-size:12px;text-decoration:none;
                display:inline-flex;align-items:center;gap:5px;">
        <i class="fas fa-tv text-xs"></i> پلیر زنده
      </a>
    </div>
  </form>

  <!-- آخرین وضعیت -->
  <div style="padding:10px 18px;border-top:1px solid rgba(255,255,255,.04);
              display:flex;align-items:center;justify-content:space-between;
              background:rgba(0,0,0,.2);">
    <span style="font-size:10px;color:#334155;">
      <i class="fas fa-clock text-xs ml-1"></i>
      <?= $isOnline
          ? 'آخرین تماس ' . (int)($s['seconds_ago'] ?? 0) . ' ثانیه پیش'
          : ($s['last_seen_at'] ? 'آخرین: ' . $s['last_seen_at'] : 'هرگز متصل نشده') ?>
    </span>
    <code style="font-size:10px;color:#1e293b;font-family:monospace;"><?= e($s['code']) ?></code>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ─── فرمت‌های 3D راهنما ──────────────────────────────────────────── -->
<div style="margin-top:32px;padding:20px;background:#111118;border:1px solid rgba(255,255,255,.06);border-radius:16px;">
  <h3 style="font-size:13px;font-weight:700;color:#94a3b8;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
    <i class="fas fa-book text-xs" style="color:#00e5ff;"></i>راهنمای فرمت‌های ۳D
  </h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;">
    <?php foreach ([
      ['🌀', 'Normal + Depth',   'normal',     '#00e5ff', 'تصویر عادی با انیمیشن شناوری و عمق CSS — برای اکثر نمایشگرهای ۳D'],
      ['👾', 'Hologram LED',     'hologram',   '#00e5ff', 'جلوه هولوگرام با scan-line، فلیکر و glow — برای LED Fan و پنل‌های هولوگرافیک'],
      ['🎭', 'SBS Stereoscopic', 'sbs',        '#818cf8', 'تصویر نیمه‌چپ برای چشم چپ — برای هدست‌های VR و نمایشگرهای دو-لنز'],
      ['↕',  'Top-Bottom',       'top_bottom', '#a855f7', 'نیمه بالا / پایین برای فرمت‌های استریو عمودی'],
      ['👓', 'Anaglyphic',       'anaglyphic', '#f87171', 'جلوه قرمز-سبز برای استفاده با عینک‌های آناگلیف'],
    ] as [$icon, $name, $key, $col, $desc]): ?>
    <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);
                border-radius:12px;padding:14px;">
      <div style="font-size:20px;margin-bottom:8px;"><?=$icon?></div>
      <div style="font-size:12px;font-weight:700;color:<?=$col?>;margin-bottom:6px;"><?=$name?></div>
      <div style="font-size:11px;color:#475569;line-height:1.6;"><?=$desc?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
