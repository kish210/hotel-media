<?php
use App\Core\Auth;
use App\Services\AirportIrFetcher;
$settings  = json_decode($screen['settings'] ?? '{}', true) ?: [];
$isOnline  = (bool)($screen['is_online'] ?? false);
$isActive  = ($screen['status'] ?? '') === 'active';
$actCode   = $screen['activation_code'] ?? null;
$actExpiry = $screen['activation_expires_at'] ?? null;
$actValid  = $actCode && $actExpiry && strtotime($actExpiry) > time();
$locations = $locations ?? [];
$heartbeats= $heartbeats ?? [];

$profiles  = [
  'modern'     => ['🎬','مدرن','Chrome / کامپیوتر','#f97316'],
  'android_tv' => ['📱','Android TV','TV Box / Android','#22c55e'],
  'lg_tv'      => ['🔵','LG WebOS','تلویزیون LG','#006eb6'],
  'samsung_tv' => ['⚫','Samsung','تلویزیون Samsung','#1428a0'],
  'legacy'     => ['🖥','سازگار','مرورگر قدیمی','#60a5fa'],
  'minimal'    => ['⚡','حداقل','Raspberry Pi','#64748b'],
  'kiosk'      => ['👆','کیوسک','صفحه لمسی','#a855f7'],
];
$currentProfile = $settings['player_profile'] ?? 'modern';

include VIEWS_PATH . '/partials/layout.php';
?>

<!-- ─── Header ─── -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
  <a href="/admin/screens" class="btn-ghost text-sm px-3"><i class="fas fa-arrow-right text-xs"></i></a>
  <div style="flex:1">
    <h1 style="font-size:20px;font-weight:800;color:#fff;"><?= e($screen['name']) ?></h1>
    <div style="display:flex;align-items:center;gap:8px;margin-top:3px;">
      <code style="font-size:12px;color:#475569;"><?= e($screen['code']) ?></code>
      <span style="padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;
                   background:<?= $isActive?'rgba(34,197,94,.12)':($actValid?'rgba(245,158,11,.12)':'rgba(100,116,139,.12)') ?>;
                   color:<?= $isActive?'#4ade80':($actValid?'#fbbf24':'#64748b') ?>;
                   border:1px solid <?= $isActive?'rgba(34,197,94,.3)':($actValid?'rgba(245,158,11,.3)':'rgba(100,116,139,.3)') ?>;">
        <?= $isActive ? ($isOnline?'● آنلاین':'○ فعال') : ($actValid?'⏳ منتظر کد':'⚪ غیرفعال') ?>
      </span>
    </div>
  </div>
  <a href="/player/" target="_blank" class="btn-ghost text-sm flex items-center gap-1.5">
    <i class="fas fa-external-link text-green-400 text-xs"></i> پلیر
  </a>
  <form method="POST" action="/admin/screens/<?= $screen['id'] ?>/delete"
    onsubmit="return confirm('صفحه «<?= e(addslashes($screen['name'])) ?>» حذف شود؟')">
    <?= csrf_field() ?>
    <button type="submit" class="btn-danger text-sm flex items-center gap-1.5">
      <i class="fas fa-trash text-xs"></i> حذف
    </button>
  </form>
</div>

<!-- ─── Tabs ─── -->
<div style="display:flex;gap:2px;background:rgba(0,0,0,0.4);border-radius:12px;padding:4px;margin-bottom:20px;width:fit-content;">
<button type="button" id="stab-info"       onclick="showTab('info')"       style="padding:9px 16px;border-radius:9px;border:none;cursor:pointer;font-size:12px;font-weight:600;font-family:inherit;display:flex;align-items:center;gap:6px;background:rgba(249,115,22,.2);color:#f97316;"><i class="fas fa-sliders"></i>اطلاعات</button>
<button type="button" id="stab-activation" onclick="showTab('activation')" style="padding:9px 16px;border-radius:9px;border:none;cursor:pointer;font-size:12px;font-weight:600;font-family:inherit;display:flex;align-items:center;gap:6px;background:transparent;color:#64748b;"><i class="fas fa-qrcode"></i>فعال‌سازی</button>
<button type="button" id="stab-player"     onclick="showTab('player')"     style="padding:9px 16px;border-radius:9px;border:none;cursor:pointer;font-size:12px;font-weight:600;font-family:inherit;display:flex;align-items:center;gap:6px;background:transparent;color:#64748b;"><i class="fas fa-tv"></i>پلیر</button>
<button type="button" id="stab-broadcast"  onclick="showTab('broadcast')"  style="padding:9px 16px;border-radius:9px;border:none;cursor:pointer;font-size:12px;font-weight:600;font-family:inherit;display:flex;align-items:center;gap:6px;background:transparent;color:#64748b;"><i class="fas fa-bolt"></i>پخش فوری</button>
<button type="button" id="stab-status"     onclick="showTab('status')"     style="padding:9px 16px;border-radius:9px;border:none;cursor:pointer;font-size:12px;font-weight:600;font-family:inherit;display:flex;align-items:center;gap:6px;background:transparent;color:#64748b;"><i class="fas fa-signal"></i>وضعیت</button>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:16px;align-items:start;">
<div>
<div id="sec-info">
  <div class="card">
    <h2 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:16px;">
      <i class="fas fa-sliders text-blue-400 ml-2"></i>تنظیمات صفحه
    </h2>
    <form method="POST" action="/admin/screens/<?= $screen['id'] ?>">
      <?= csrf_field() ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
        <div>
          <label class="form-label">نام صفحه *</label>
          <input type="text" name="name" class="form-input" required value="<?= e($screen['name']) ?>">
        </div>
        <div>
          <label class="form-label">توضیحات</label>
          <input type="text" name="description" class="form-input" value="<?= e($screen['description'] ?? '') ?>">
        </div>
        <div>
          <label class="form-label">جهت نمایش</label>
          <select name="orientation" class="form-input">
            <option value="landscape" <?= ($screen['orientation']??'')!=='portrait'?'selected':'' ?>>افقی (Landscape)</option>
            <option value="portrait"  <?= ($screen['orientation']??'')==='portrait'?'selected':'' ?>>عمودی (Portrait)</option>
          </select>
        </div>
        <div>
          <label class="form-label">رزولوشن</label>
          <select name="resolution" class="form-input">
            <?php foreach(['1920x1080'=>'1920×1080 (Full HD)','3840x2160'=>'3840×2160 (4K)','1280x720'=>'1280×720 (HD)','1080x1920'=>'1080×1920 (Portrait)'] as $v=>$l): ?>
            <option value="<?=$v?>" <?=($screen['resolution']??'1920x1080')===$v?'selected':''?>><?=$l?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">موقعیت / شعبه</label>
          <select name="location_id" class="form-input">
            <option value="">— بدون موقعیت —</option>
            <?php foreach ($locations as $loc): ?>
            <option value="<?=$loc['id']?>" <?=($screen['location_id']==$loc['id'])?'selected':''?>><?= e($loc['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">نوع صفحه</label>
          <select name="screen_type" id="screen_type_sel" class="form-input"
                  onchange="toggleIptvFields(this.value)">
            <option value="signage"  <?=($screen['screen_type']??'signage')==='signage'?'selected':''?>>📺 Signage (محتوای دیجیتال)</option>
            <option value="iptv"     <?=($screen['screen_type']??'')==='iptv'?'selected':''?>>📡 IPTV (کانال زنده)</option>
            <option value="inflight" <?=($screen['screen_type']??'')==='inflight'?'selected':''?>>✈ In-Flight (داخل هواپیما)</option>
          </select>
        </div>
        <div>
          <label class="form-label">گروه</label>
          <select name="group_id" id="group_id_sel" class="form-input"
                  onchange="onGroupChange(this.value)">
            <option value="">— بدون گروه —</option>
            <?php foreach ($allGroups ?? [] as $g): ?>
            <option value="<?=$g['id']?>" data-type="<?=$g['type']?>"
                    <?=($screen['group_id']==$g['id'])?'selected':''?>>
              <?=e($g['name'])?> (<?=$g['type']?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">برچسب‌ها</label>
          <input type="text" name="tags" class="form-input" value="<?= e($screen['tags'] ?? '') ?>" placeholder="tv-lobby, floor-1, ...">
        </div>
      </div>

      <!-- ── بخش IPTV: منو (فقط وقتی نوع صفحه = IPTV) ─────────── -->
      <?php $isIptv = ($screen['screen_type'] ?? 'signage') === 'iptv'; ?>
      <div id="iptv-menu-section" style="<?= $isIptv ? '' : 'display:none;' ?>
           margin-bottom:14px;background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.15);
           border-radius:12px;padding:14px;">
        <h3 style="font-size:12px;font-weight:700;color:#f87171;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-bars"></i>منوی IPTV — محتوای قابل انتخاب توسط کاربر
        </h3>
        <div style="display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end;">
          <div>
            <label class="form-label">انتخاب منو</label>
            <select name="iptv_menu_id" id="iptv_menu_sel" class="form-input">
              <option value="">— بدون منو —</option>
              <?php foreach ($iptvMenus ?? [] as $menu): ?>
              <option value="<?=$menu['id']?>"
                      data-group="<?=$menu['group_id']?>"
                      <?=($screen['iptv_menu_id']==$menu['id'])?'selected':''?>>
                <?=e($menu['name'])?><?= $menu['group_name'] ? ' ('.$menu['group_name'].')' : '' ?>
                (<?=$menu['item_count']?> آیتم)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <a href="/admin/iptv/menus" target="_blank"
             style="padding:9px 14px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);
                    border-radius:10px;color:#f87171;font-size:12px;text-decoration:none;
                    display:inline-flex;align-items:center;gap:5px;white-space:nowrap;">
            <i class="fas fa-external-link text-xs"></i>مدیریت منوها
          </a>
        </div>

        <!-- پیش‌نمایش آیتم‌های منوی انتخابی -->
        <div id="menu-items-preview" style="margin-top:10px;display:none;">
          <div style="font-size:10px;color:#64748b;margin-bottom:6px;">آیتم‌های این منو:</div>
          <div id="menu-items-chips" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
        </div>

        <!-- اتاق IPTV -->
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(239,68,68,.1);">
          <label class="form-label"><i class="fas fa-door-open text-xs ml-1" style="color:#f87171;"></i>اتاق / واحد <span style="color:#475569;font-weight:400;">(اختیاری)</span></label>
          <div style="display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end;">
            <select name="iptv_room_id" id="iptv_room_sel" class="form-input">
              <option value="">— بدون اتاق —</option>
              <?php foreach ($iptvRooms ?? [] as $room): ?>
              <option value="<?= $room['id'] ?>"
                      <?= ($screen['iptv_room_id'] ?? '') == $room['id'] ? 'selected' : '' ?>>
                <?= e($room['room_number']) ?><?= $room['room_name'] ? ' — ' . e($room['room_name']) : '' ?>
                <?= $room['floor'] !== null ? ' (طبقه '.$room['floor'].')' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
            <a href="/admin/iptv/rooms" target="_blank"
               style="padding:9px 12px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);
                      border-radius:10px;color:#f87171;font-size:12px;text-decoration:none;
                      display:inline-flex;align-items:center;gap:5px;white-space:nowrap;">
              <i class="fas fa-external-link text-xs"></i>اتاق‌ها
            </a>
          </div>
          <div style="font-size:10px;color:#475569;margin-top:5px;">
            با انتخاب اتاق، شماره اتاق روی TV نمایش می‌یابد و می‌توانید از طریق PMS پیام بفرستید.
          </div>
        </div>
      </div>

      <!-- ── بخش In-Flight: پرواز (فقط وقتی نوع صفحه = inflight) ─── -->
      <?php $isInflight = ($screen['screen_type'] ?? 'signage') === 'inflight'; ?>
      <div id="inflight-section" style="<?= $isInflight ? '' : 'display:none;' ?>
           margin-bottom:14px;background:rgba(0,180,216,.04);border:1px solid rgba(0,180,216,.2);
           border-radius:12px;padding:14px;">
        <h3 style="font-size:12px;font-weight:700;color:#00b4d8;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-plane"></i>پرواز — اطلاعات نمایش داده می‌شود
        </h3>
        <div style="display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end;">
          <div>
            <label class="form-label">انتخاب پرواز</label>
            <select name="inflight_flight_id" id="inflight_flight_sel" class="form-input">
              <option value="">— بدون پرواز —</option>
              <?php foreach ($inflightFlights ?? [] as $fl): ?>
              <option value="<?= $fl['id'] ?>"
                      <?= ($screen['inflight_flight_id'] ?? '') == $fl['id'] ? 'selected' : '' ?>>
                <?= e($fl['flight_number']) ?>
                <?= $fl['origin_iata'] ? ' ('.$fl['origin_iata'].' → '.($fl['dest_iata']??'?').')' : '' ?>
                <?= $fl['airline_name'] ? ' — '.e($fl['airline_name']) : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <a href="/admin/inflight" target="_blank"
             style="padding:9px 14px;background:rgba(0,180,216,.1);border:1px solid rgba(0,180,216,.3);
                    border-radius:10px;color:#00b4d8;font-size:12px;text-decoration:none;
                    display:inline-flex;align-items:center;gap:5px;white-space:nowrap;">
            <i class="fas fa-external-link text-xs"></i>مدیریت پروازها
          </a>
        </div>
        <div style="font-size:10px;color:#475569;margin-top:6px;">
          نمایشگر اطلاعات پرواز (نقشه، ارتفاع، سرعت، ETA) را برای مسافران نشان می‌دهد.
        </div>
      </div>

      <button type="submit" class="btn-primary text-sm px-6 py-2.5">
        <i class="fas fa-save text-xs ml-1"></i> ذخیره اطلاعات
      </button>
    </form>
    <script>
    // داده منوها برای پیش‌نمایش
    const IPTV_MENUS_DATA = <?= json_encode(
      array_map(fn($m) => [
        'id'         => $m['id'],
        'group_id'   => $m['group_id'],
        'name'       => $m['name'],
        'item_count' => $m['item_count'],
        'items'      => $m['items'] ?? [],
      ], $iptvMenus ?? []),
      JSON_UNESCAPED_UNICODE
    ) ?>;

    function toggleIptvFields(type) {
      const sec = document.getElementById('iptv-menu-section');
      sec.style.display = type === 'iptv' ? '' : 'none';
      const appr = document.getElementById('iptv-appearance-card');
      if (appr) appr.style.display = type === 'iptv' ? '' : 'none';
      const ifSec = document.getElementById('inflight-section');
      if (ifSec) ifSec.style.display = type === 'inflight' ? '' : 'none';
      // فیلتر گروه‌ها بر اساس نوع
      const grpSel = document.getElementById('group_id_sel');
      Array.from(grpSel.options).forEach(opt => {
        if (!opt.value) return; // گزینه خالی
        opt.style.display = (opt.dataset.type === type || opt.dataset.type === '') ? '' : 'none';
      });
    }

    function onGroupChange(groupId) {
      // فیلتر منوها بر اساس گروه انتخابی
      const menuSel = document.getElementById('iptv_menu_sel');
      if (!menuSel) return;
      const gid = groupId ? parseInt(groupId) : null;
      Array.from(menuSel.options).forEach(opt => {
        if (!opt.value) return;
        const optGroup = opt.dataset.group ? parseInt(opt.dataset.group) : null;
        opt.style.display = (!gid || optGroup === gid) ? '' : 'none';
      });
      // اگر منوی فعلی دیده نمیشه، reset کن
      const cur = menuSel.options[menuSel.selectedIndex];
      if (cur && cur.style.display === 'none') menuSel.value = '';
      showMenuPreview(menuSel.value);
    }

    document.getElementById('iptv_menu_sel')?.addEventListener('change', function() {
      showMenuPreview(this.value);
    });

    async function showMenuPreview(menuId) {
      const preview = document.getElementById('menu-items-preview');
      const chips   = document.getElementById('menu-items-chips');
      if (!menuId || !preview) { preview && (preview.style.display='none'); return; }
      try {
        const r = await fetch(`/api/v1/iptv/menus/${menuId}`);
        const d = await r.json();
        const items = d.data?.items || [];
        if (!items.length) { preview.style.display='none'; return; }
        chips.innerHTML = items.map(item => `
          <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;
                       border-radius:20px;background:${item.color}15;border:1px solid ${item.color}33;
                       font-size:11px;color:${item.color};">
            <i class="${item.icon}"></i>${item.label}
          </span>`).join('');
        preview.style.display = '';
      } catch(e) { preview.style.display='none'; }
    }

    // اجرا هنگام بارگذاری
    toggleIptvFields(document.getElementById('screen_type_sel').value);
    showMenuPreview(document.getElementById('iptv_menu_sel')?.value || '');
    </script>
  </div>
</div>

<div id="sec-activation" style="display:none;">
  <div class="card" style="border:1px solid rgba(<?=$isActive?'34,197,94':'245,158,11'?>,0.25);">
    <h2 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:16px;">
      <i class="fas fa-qrcode text-yellow-400 ml-2"></i>فعال‌سازی صفحه نمایش
    </h2>

    <!-- راهنما step-by-step -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px;">
      <?php $steps = [
        [1,'کد جدید',$actCode||$isActive,'بازتولید کد ۶ رقمی'],
        [2,'باز کردن پلیر',$isActive||$actValid,'آدرس را در TV باز کنید'],
        [3,'وارد کردن کد',$isActive,'کد را در صفحه TV وارد کنید'],
        [4,'پلی‌لیست',$isActive,'پلی‌لیست را از زمان‌بندی وصل کنید'],
      ];
      foreach ($steps as [$n,$t,$done,$desc]): ?>
      <div style="text-align:center;padding:12px 8px;border-radius:10px;
                  background:<?=$done?'rgba(34,197,94,.08)':'rgba(255,255,255,.03)'?>;
                  border:1px solid <?=$done?'rgba(34,197,94,.25)':'rgba(255,255,255,.06)'?>;">
        <div style="width:28px;height:28px;border-radius:50%;margin:0 auto 8px;
                    display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;
                    background:<?=$done?'#22c55e':'rgba(255,255,255,.08)'?>;
                    color:<?=$done?'#000':'#64748b'?>;">
          <?= $done ? '✓' : $n ?>
        </div>
        <div style="font-size:11px;font-weight:700;color:<?=$done?'#4ade80':'#94a3b8'?>;margin-bottom:4px;"><?= $t ?></div>
        <div style="font-size:9px;color:#475569;"><?= $desc ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- کد -->
    <div style="display:flex;gap:10px;margin-bottom:14px;">
      <div style="flex:1;background:#0a0a14;border:2px solid rgba(245,158,11,<?=$actValid?.5:.2?>);
                  border-radius:12px;padding:18px;text-align:center;
                  font-family:monospace;font-size:36px;font-weight:900;letter-spacing:12px;
                  color:<?=$actValid?'#f97316':'#475569'?>;">
        <?= $actValid ? e($actCode) : '──────' ?>
      </div>
      <form method="POST" action="/admin/screens/<?= $screen['id'] ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="regenerate_code">
        <button type="submit"
          style="padding:0 20px;height:100%;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.35);
                 border-radius:12px;color:#fbbf24;cursor:pointer;font-size:13px;font-weight:600;
                 font-family:'Vazirmatn',sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;">
          <i class="fas fa-rotate fa-lg"></i>
          <span>کد جدید</span>
        </button>
      </form>
    </div>
    <?php if ($actValid): ?>
    <p style="font-size:11px;color:#64748b;text-align:center;margin-bottom:14px;">
      ⏱ اعتبار تا: <strong style="color:#fbbf24;"><?= date('H:i:s', strtotime($actExpiry)) ?></strong>
      (<?= max(0, (int)((strtotime($actExpiry)-time())/60)) ?> دقیقه مانده)
    </p>
    <?php endif; ?>

    <!-- URL پلیر -->
    <div style="background:#0d0d14;border-radius:10px;padding:14px;">
      <p style="font-size:11px;color:#64748b;margin-bottom:8px;">
        <i class="fas fa-tv text-xs ml-1"></i>آدرس پلیر (یکسان برای همه صفحات):
      </p>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
        <code id="player-url" style="flex:1;font-size:13px;color:#60a5fa;word-break:break-all;
              background:rgba(59,130,246,.05);padding:8px;border-radius:8px;border:1px solid rgba(59,130,246,.15);">
          <?= env('APP_URL','http://localhost') ?>/player/
        </code>
        <button onclick="copyPlayerUrl()"
          style="flex-shrink:0;padding:8px 12px;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);
                 border-radius:8px;color:#60a5fa;cursor:pointer;font-size:11px;font-family:'Vazirmatn',sans-serif;">
          <i class="fas fa-copy ml-1 text-xs"></i>کپی
        </button>
        <a href="/player/" target="_blank"
           style="flex-shrink:0;padding:8px 12px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);
                  border-radius:8px;color:#4ade80;font-size:11px;text-decoration:none;">
          <i class="fas fa-external-link ml-1 text-xs"></i>باز کن
        </a>
      </div>
      <p style="font-size:11px;color:#475569;line-height:1.7;">
        <i class="fas fa-circle-info text-xs ml-1" style="color:#38bdf8;"></i>
        مرورگر/TV را به این آدرس ببرید، سپس کد فعال‌سازی زیر را وارد کنید تا این صفحه متصل شود.
      </p>
    </div>
  </div>
</div>

<div id="sec-player" style="display:none;">
  <form method="POST" action="/admin/screens/<?= $screen['id'] ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="overlay">

    <!-- انتخاب پروفایل -->
    <div class="card mb-4">
      <h2 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:16px;">
        <i class="fas fa-tv text-purple-400 ml-2"></i>پروفایل پلیر
      </h2>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:6px;">
        <?php foreach ($profiles as $pk=>[$ico,$name,$desc,$clr]): $on=$currentProfile===$pk; ?>
        <button type="button" onclick="setProfile('<?=$pk?>')" id="pp-<?=$pk?>"
          style="padding:14px 8px;border-radius:12px;text-align:center;cursor:pointer;
                 background:<?=$on?'rgba(249,115,22,.15)':'rgba(255,255,255,.03)'?>;
                 border:1px solid <?=$on?$clr:'rgba(255,255,255,.08)'?>;
                 transition:all 0.2s;font-family:'Vazirmatn',sans-serif;">
          <div style="font-size:24px;margin-bottom:8px;"><?=$ico?></div>
          <div style="font-size:12px;font-weight:700;color:<?=$on?$clr:'#94a3b8'?>;margin-bottom:4px;"><?=$name?></div>
          <div style="font-size:9px;color:#475569;line-height:1.4;"><?=$desc?></div>
        </button>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="settings[player_profile]" id="pp-input" value="<?= e($currentProfile) ?>">
    </div>

    <!-- Overlay Settings -->
    <div class="card mb-4">
      <h2 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:16px;">
        <i class="fas fa-layer-group text-indigo-400 ml-2"></i>تنظیمات نمایش
      </h2>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">

        <!-- Logo -->
        <div style="background:#0d0d14;border-radius:10px;padding:14px;border:1px solid rgba(255,255,255,.06);">
          <h3 style="font-size:12px;font-weight:700;color:#818cf8;margin-bottom:10px;">
            <i class="fas fa-image ml-1"></i>لوگو
          </h3>
          <label class="form-label">آدرس لوگو (URL)</label>
          <input type="url" name="settings[logo_url]" class="form-input mb-2" style="font-size:12px;"
            placeholder="https://example.com/logo.png" value="<?= e($settings['logo_url']??'') ?>">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <div>
              <label class="form-label">موقعیت</label>
              <select name="settings[logo_position]" class="form-input" style="font-size:11px;">
                <?php foreach(['bottom-right'=>'پایین راست','bottom-left'=>'پایین چپ','top-right'=>'بالا راست','top-left'=>'بالا چپ'] as $v=>$l): ?>
                <option value="<?=$v?>" <?=($settings['logo_position']??'bottom-right')===$v?'selected':''?>><?=$l?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="form-label">اندازه (px)</label>
              <input type="number" name="settings[logo_size]" class="form-input" style="font-size:12px;" value="<?=$settings['logo_size']??120?>" min="40" max="400">
            </div>
          </div>
          <label class="form-label mt-2">شفافیت</label>
          <input type="range" name="settings[logo_opacity]" class="form-input" min=".1" max="1" step=".05"
            value="<?=$settings['logo_opacity']??.8?>" oninput="document.getElementById('logo-op-val').textContent=this.value">
          <span id="logo-op-val" style="font-size:10px;color:#64748b;"><?=$settings['logo_opacity']??.8?></span>
        </div>

        <!-- Ticker -->
        <div style="background:#0d0d14;border-radius:10px;padding:14px;border:1px solid rgba(255,255,255,.06);">
          <h3 style="font-size:12px;font-weight:700;color:#f59e0b;margin-bottom:10px;">
            <i class="fas fa-scroll ml-1"></i>Ticker
          </h3>
          <label class="form-label">متن ticker</label>
          <textarea name="settings[ticker_text]" class="form-input mb-2" rows="2" style="font-size:12px;"
            placeholder="آخرین اخبار · اطلاعیه ..."><?= e($settings['ticker_text']??'') ?></textarea>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <div>
              <label class="form-label">رنگ متن</label>
              <input type="color" name="settings[ticker_color]" class="form-input" style="height:36px;padding:3px;" value="<?=$settings['ticker_color']??'#ffffff'?>">
            </div>
            <div>
              <label class="form-label">سرعت</label>
              <input type="number" name="settings[ticker_speed]" class="form-input" style="font-size:12px;" value="<?=$settings['ticker_speed']??40?>" min="5" max="200">
            </div>
          </div>
        </div>

        <!-- Clock -->
        <div style="background:#0d0d14;border-radius:10px;padding:14px;border:1px solid rgba(255,255,255,.06);">
          <h3 style="font-size:12px;font-weight:700;color:#34d399;margin-bottom:10px;">
            <i class="fas fa-clock ml-1"></i>ساعت
          </h3>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <div>
              <label class="form-label">نمایش ساعت</label>
              <select name="settings[show_clock]" class="form-input" style="font-size:11px;">
                <option value="0" <?=empty($settings['show_clock'])?'selected':''?>>خاموش</option>
                <option value="1" <?=!empty($settings['show_clock'])?'selected':''?>>روشن</option>
              </select>
            </div>
            <div>
              <label class="form-label">موقعیت</label>
              <select name="settings[clock_position]" class="form-input" style="font-size:11px;">
                <?php foreach(['top-right'=>'بالا راست','top-left'=>'بالا چپ','bottom-right'=>'پایین راست','bottom-left'=>'پایین چپ'] as $v=>$l): ?>
                <option value="<?=$v?>" <?=($settings['clock_position']??'top-right')===$v?'selected':''?>><?=$l?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- روشنایی/صدا -->
        <div style="background:#0d0d14;border-radius:10px;padding:14px;border:1px solid rgba(255,255,255,.06);">
          <h3 style="font-size:12px;font-weight:700;color:#fb923c;margin-bottom:10px;">
            <i class="fas fa-sun ml-1"></i>روشنایی و صدا
          </h3>
          <label class="form-label">روشنایی (%)</label>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <input type="range" name="settings[brightness]" style="flex:1;" min="10" max="100" step="5"
              value="<?=$settings['brightness']??100?>" oninput="document.getElementById('br-val').textContent=this.value+'%'">
            <span id="br-val" style="font-size:11px;color:#f97316;width:36px;"><?=$settings['brightness']??100?>%</span>
          </div>
          <label class="form-label">صدای پیش‌فرض (%)</label>
          <div style="display:flex;align-items:center;gap:8px;">
            <input type="range" name="settings[volume]" style="flex:1;" min="0" max="100" step="5"
              value="<?=$settings['volume']??100?>" oninput="document.getElementById('vol-val').textContent=this.value+'%'">
            <span id="vol-val" style="font-size:11px;color:#f97316;width:36px;"><?=$settings['volume']??100?>%</span>
          </div>
        </div>

      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         تنظیمات ظاهری IPTV (فقط برای صفحات IPTV)
    ══════════════════════════════════════════════════════ -->
    <div id="iptv-appearance-card" class="card mb-4"
         style="<?= ($screen['screen_type']??'signage')==='iptv' ? '' : 'display:none;' ?>
                border:1px solid rgba(239,68,68,.2);">
      <h2 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:16px;">
        <i class="fas fa-satellite-dish text-red-400 ml-2"></i>ظاهر پلیر IPTV
        <span style="font-size:10px;font-weight:400;color:#475569;margin-right:8px;">ویژه صفحات IPTV</span>
      </h2>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">

        <!-- پس‌زمینه -->
        <div style="background:#0d0d14;border-radius:10px;padding:14px;border:1px solid rgba(255,255,255,.06);">
          <h3 style="font-size:12px;font-weight:700;color:#f87171;margin-bottom:10px;">
            <i class="fas fa-image ml-1"></i>پس‌زمینه
          </h3>
          <label class="form-label">آدرس تصویر (URL)</label>
          <input type="url" name="settings[iptv_bg_image]" class="form-input mb-2" style="font-size:12px;"
            placeholder="https://example.com/bg.jpg"
            value="<?= e($settings['iptv_bg_image']??'') ?>">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;">
            <div>
              <label class="form-label">تاریکی پس‌زمینه</label>
              <div style="display:flex;align-items:center;gap:6px;">
                <input type="range" name="settings[iptv_bg_dim]" min="0" max="1" step=".05"
                  value="<?= $settings['iptv_bg_dim'] ?? .55 ?>"
                  oninput="document.getElementById('bg-dim-v').textContent=Math.round(this.value*100)+'%'"
                  style="flex:1;">
                <span id="bg-dim-v" style="font-size:10px;color:#ef4444;width:32px;"><?= round(($settings['iptv_bg_dim']??.55)*100) ?>%</span>
              </div>
            </div>
            <div>
              <label class="form-label">بلور (blur)</label>
              <div style="display:flex;align-items:center;gap:6px;">
                <input type="range" name="settings[iptv_bg_blur]" min="0" max="20" step="1"
                  value="<?= $settings['iptv_bg_blur'] ?? 0 ?>"
                  oninput="document.getElementById('bg-blur-v').textContent=this.value+'px'"
                  style="flex:1;">
                <span id="bg-blur-v" style="font-size:10px;color:#ef4444;width:32px;"><?= $settings['iptv_bg_blur'] ?? 0 ?>px</span>
              </div>
            </div>
          </div>
          <label class="form-label">رنگ accent (کارت‌ها، فوکوس)</label>
          <div style="display:flex;align-items:center;gap:8px;">
            <input type="color" name="settings[iptv_accent]" class="form-input"
              style="height:36px;padding:3px;width:60px;flex-shrink:0;"
              value="<?= e($settings['iptv_accent'] ?? '#ef4444') ?>">
            <input type="text" id="accent-hex-input"
              value="<?= e($settings['iptv_accent'] ?? '#ef4444') ?>"
              class="form-input" style="font-size:12px;font-family:monospace;"
              oninput="document.querySelector('[name=\'settings[iptv_accent]\']').value=this.value"
              placeholder="#ef4444">
          </div>
        </div>

        <!-- خوش‌آمدگویی -->
        <div style="background:#0d0d14;border-radius:10px;padding:14px;border:1px solid rgba(255,255,255,.06);">
          <h3 style="font-size:12px;font-weight:700;color:#a78bfa;margin-bottom:10px;">
            <i class="fas fa-hand-wave ml-1"></i>پیام خوش‌آمدگویی
          </h3>
          <label class="form-label">آدرس لوگو (URL)</label>
          <input type="url" name="settings[iptv_logo_url]" class="form-input mb-2" style="font-size:12px;"
            placeholder="https://example.com/logo.png"
            value="<?= e($settings['iptv_logo_url'] ?? $settings['logo_url'] ?? '') ?>">
          <label class="form-label">عنوان اصلی</label>
          <input type="text" name="settings[iptv_welcome_title]" class="form-input mb-2" style="font-size:12px;"
            placeholder="مثلاً: خوش آمدید · Welcome"
            value="<?= e($settings['iptv_welcome_title']??'') ?>">
          <label class="form-label">زیرعنوان</label>
          <input type="text" name="settings[iptv_welcome_sub]" class="form-input" style="font-size:12px;"
            placeholder="مثلاً: لطفاً گزینه مورد نظر را انتخاب کنید"
            value="<?= e($settings['iptv_welcome_sub']??'') ?>">
        </div>

        <!-- Ticker (IPTV) -->
        <div style="background:#0d0d14;border-radius:10px;padding:14px;border:1px solid rgba(255,255,255,.06);">
          <h3 style="font-size:12px;font-weight:700;color:#fbbf24;margin-bottom:10px;">
            <i class="fas fa-scroll ml-1"></i>تیزر / Ticker
          </h3>
          <label class="form-label">متن پیام‌نوار</label>
          <textarea name="settings[ticker_text]" class="form-input mb-2" rows="2" style="font-size:12px;"
            placeholder="آخرین اخبار · اطلاعیه · Welcome..."><?= e($settings['ticker_text']??'') ?></textarea>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
            <div>
              <label class="form-label">رنگ متن</label>
              <input type="color" name="settings[ticker_color]" class="form-input"
                style="height:36px;padding:3px;"
                value="<?= $settings['ticker_color'] ?? '#ffffff' ?>">
            </div>
            <div>
              <label class="form-label">رنگ پس‌زمینه</label>
              <input type="color" name="settings[ticker_bg_color]" class="form-input"
                style="height:36px;padding:3px;"
                value="<?= $settings['ticker_bg_color'] ?? '#000000' ?>">
            </div>
            <div>
              <label class="form-label">سرعت</label>
              <input type="number" name="settings[ticker_speed]" class="form-input" style="font-size:12px;"
                value="<?= $settings['ticker_speed'] ?? 40 ?>" min="5" max="200">
            </div>
          </div>
        </div>

        <!-- پیش‌نمایش -->
        <div style="background:#0d0d14;border-radius:10px;padding:14px;border:1px solid rgba(255,255,255,.06);">
          <h3 style="font-size:12px;font-weight:700;color:#34d399;margin-bottom:10px;">
            <i class="fas fa-eye ml-1"></i>پیش‌نمایش پلیر
          </h3>
          <div id="iptv-preview-box"
               style="border-radius:8px;overflow:hidden;border:1px solid rgba(255,255,255,.08);
                      aspect-ratio:16/9;position:relative;background:#09090f;
                      display:flex;align-items:center;justify-content:center;">
            <div style="text-align:center;color:#334155;font-size:11px;">
              <i class="fas fa-satellite-dish" style="font-size:28px;margin-bottom:8px;display:block;opacity:.3;"></i>
              بعد از ذخیره، روی «پلیر» کلیک کنید
            </div>
          </div>
          <a href="/player/" target="_blank"
             style="display:flex;align-items:center;justify-content:center;gap:6px;
                    margin-top:10px;padding:8px;border-radius:8px;
                    background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);
                    color:#f87171;font-size:12px;text-decoration:none;">
            <i class="fas fa-external-link text-xs"></i>باز کردن پلیر IPTV
          </a>
        </div>

      </div>
    </div>

    <button type="submit" class="btn-primary text-sm px-6 py-2.5">
      <i class="fas fa-save text-xs ml-1"></i> ذخیره تنظیمات پلیر
    </button>
  </form>
</div>

<div id="sec-broadcast" style="display:none;">
  <div class="card" style="border:1px solid rgba(249,115,22,.2);">
    <h2 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:16px;">
      <i class="fas fa-bolt text-orange-400 ml-2"></i>پخش فوری روی صفحه
    </h2>
    <!-- نوع محتوا -->
    <div style="display:flex;flex-wrap:wrap;gap:4px;background:rgba(0,0,0,.3);border-radius:8px;padding:3px;margin-bottom:14px;">
      <?php foreach([['image','🖼 تصویر'],['video','🎬 ویدیو'],['url','🌐 وب'],['text','📝 متن'],['fids_live','✈ FIDS زنده']] as [$t,$l]): ?>
      <button onclick="bcType('<?=$t?>')" id="bc-<?=$t?>"
        style="flex:1;min-width:70px;padding:8px;border-radius:6px;border:none;cursor:pointer;font-size:11px;font-weight:600;
               font-family:'Vazirmatn',sans-serif;transition:all .2s;
               background:<?=$t==='image'?'rgba(249,115,22,.2)':'transparent'?>;
               color:<?=$t==='image'?'#f97316':'#64748b'?>;"><?=$l?></button>
      <?php endforeach; ?>
    </div>

    <div id="bc-media-wrap">
      <div id="bc-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:6px;max-height:160px;overflow-y:auto;background:rgba(0,0,0,.2);border-radius:8px;padding:6px;margin-bottom:10px;">
        <div style="grid-column:1/-1;text-align:center;padding:16px;color:#475569;font-size:11px;">
          <i class="fas fa-spinner fa-spin" style="display:block;margin-bottom:6px;"></i>بارگذاری...
        </div>
      </div>
      <input id="bc-direct" type="url" class="form-input" style="font-size:12px;" placeholder="یا URL مستقیم...">
    </div>
    <div id="bc-url-wrap" style="display:none;">
      <input id="bc-webpage" type="url" class="form-input" placeholder="https://example.com">
    </div>
    <div id="bc-text-wrap" style="display:none;">
      <textarea id="bc-txt" class="form-input" rows="3" placeholder="پیام اضطراری..."></textarea>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
        <div><label class="form-label">رنگ متن</label><input id="bc-tc" type="color" class="form-input" style="height:36px;padding:3px;" value="#ffffff"></div>
        <div><label class="form-label">رنگ زمینه</label><input id="bc-bg" type="color" class="form-input" style="height:36px;padding:3px;" value="#000000"></div>
      </div>
    </div>

    <!-- ─── FIDS زنده ─── -->
    <div id="bc-fids_live-wrap" style="display:none;">
      <div style="background:rgba(14,165,233,.07);border:1px solid rgba(14,165,233,.2);border-radius:10px;padding:12px;display:flex;flex-direction:column;gap:10px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px;">
          <i class="fas fa-satellite-dish" style="color:#0ea5e9;font-size:14px;"></i>
          <span style="font-size:12px;font-weight:700;color:#0ea5e9;">تابلو زنده از fids.airport.ir</span>
        </div>
        <div>
          <label class="form-label" style="font-size:11px;">فرودگاه / شهر</label>
          <select id="bc-fids-airport" class="form-input" style="font-size:12px;">
            <?php foreach(\App\Services\AirportIrFetcher::AIRPORTS as $aid => $ainfo): ?>
            <option value="<?= $aid ?>" <?= $aid === 2 ? 'selected' : '' ?>><?= e($ainfo['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div>
            <label class="form-label" style="font-size:11px;">نوع پرواز</label>
            <select id="bc-fids-direction" class="form-input" style="font-size:12px;">
              <option value="departure">خروجی (Departures)</option>
              <option value="arrival">ورودی (Arrivals)</option>
              <option value="all">هر دو</option>
            </select>
          </div>
          <div>
            <label class="form-label" style="font-size:11px;">مسیر پرواز</label>
            <select id="bc-fids-route" class="form-input" style="font-size:12px;">
              <option value="domestic">داخلی</option>
              <option value="international">خارجی</option>
              <option value="all">هر دو</option>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <div>
            <label class="form-label" style="font-size:11px;">تعداد ردیف</label>
            <input id="bc-fids-rows" type="number" class="form-input" style="font-size:12px;" value="14" min="5" max="30">
          </div>
          <div>
            <label class="form-label" style="font-size:11px;">تم رنگی</label>
            <select id="bc-fids-theme" class="form-input" style="font-size:12px;">
              <option value="dark">تاریک</option>
              <option value="airport">فرودگاهی</option>
              <option value="navy">Navy</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px;">
      <div><label class="form-label">مدت نمایش</label>
        <select id="bc-dur" class="form-input" style="font-size:12px;">
          <option value="15">۱۵ ثانیه</option>
          <option value="30" selected>۳۰ ثانیه</option>
          <option value="60">۱ دقیقه</option>
          <option value="300">۵ دقیقه</option>
          <option value="0">تا توقف</option>
        </select>
      </div>
      <div><label class="form-label">هدف</label>
        <select id="bc-tgt" class="form-input" style="font-size:12px;">
          <option value="this">این صفحه</option>
          <option value="all">همه صفحات</option>
        </select>
      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:12px;">
      <button onclick="bcSend(<?= $screen['id'] ?>)" class="btn-primary flex-1 py-2.5 text-sm">
        <i class="fas fa-bolt text-xs ml-1"></i> پخش فوری
      </button>
      <button onclick="bcStop(<?= $screen['id'] ?>)" id="bc-stop-btn"
        style="display:none;padding:0 14px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);
               border-radius:10px;color:#f87171;cursor:pointer;font-size:12px;font-family:'Vazirmatn',sans-serif;">
        <i class="fas fa-stop text-xs ml-1"></i> توقف
      </button>
    </div>
    <div id="bc-preview" style="display:none;margin-top:10px;border-radius:8px;overflow:hidden;border:1px solid rgba(249,115,22,.2);">
      <div style="padding:5px 10px;background:rgba(249,115,22,.1);font-size:10px;color:#f97316;">
        <i class="fas fa-eye ml-1 text-xs"></i> در حال پخش
      </div>
      <div id="bc-preview-inner" style="padding:10px;font-size:12px;color:#94a3b8;"></div>
    </div>
  </div>
</div>

<div id="sec-status" style="display:none;">
  <div class="card mb-4">
    <h2 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:14px;">
      <i class="fas fa-chart-line text-green-400 ml-2"></i>اطلاعات وضعیت
    </h2>
    <?php foreach ([
      'کد صفحه'       => $screen['code'],
      'وضعیت'          => $screen['status'] ?? '—',
      'آنلاین'          => ($screen['is_online'] ?? false) ? '✅ بله' : '❌ خیر',
      'رزولوشن'        => $screen['resolution'] ?? '—',
      'جهت'            => $screen['orientation'] ?? '—',
      'آخرین ارتباط'   => $screen['last_seen_at'] ? timeAgo($screen['last_seen_at']) : '—',
      'آخرین IP'       => $screen['last_ip'] ?? '—',
      'پروفایل پلیر'   => $currentProfile,
    ] as $k=>$v): ?>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:12px;">
      <span style="color:#64748b;"><?= e($k) ?></span>
      <span style="color:#94a3b8;font-family:monospace;"><?= e($v) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- دستورات -->
  <div class="card mb-4">
    <h2 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:14px;">
      <i class="fas fa-terminal text-purple-400 ml-2"></i>دستورات از راه دور
    </h2>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
      <?php foreach(['reload'=>['🔄','بارگذاری'],'reboot'=>['🔁','ریستارت'],'screenshot'=>['📸','اسکرین‌شات']] as $cmd=>[$ico,$lbl]): ?>
      <form method="POST" action="/admin/screens/<?= $screen['id'] ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="command">
        <input type="hidden" name="command" value="<?= $cmd ?>">
        <button type="submit" class="btn-ghost text-xs w-full py-3"><?= $ico ?><br><?= $lbl ?></button>
      </form>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Heartbeats -->
  <div class="card">
    <h2 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:14px;">
      <i class="fas fa-heartbeat text-red-400 ml-2"></i>Heartbeat‌های اخیر
    </h2>
    <?php if (empty($heartbeats)): ?>
    <div style="text-align:center;padding:20px;color:#475569;font-size:12px;">
      <i class="fas fa-heart-crack" style="font-size:24px;display:block;margin-bottom:8px;opacity:.2;"></i>
      هنوز heartbeatی دریافت نشده
    </div>
    <?php else: ?>
    <?php foreach (array_slice($heartbeats, 0, 10) as $hb): ?>
    <div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:11px;">
      <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;flex-shrink:0;"></span>
      <span style="color:#64748b;flex:1;"><?= e($hb['app_version'] ?? '—') ?></span>
      <span style="color:#475569;font-family:monospace;"><?= timeAgo($hb['created_at']) ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

</div><!-- /col1 -->

<!-- ─── Side column ─── -->
<div style="display:flex;flex-direction:column;gap:12px;">
  <!-- Quick info -->
  <div class="card">
    <div style="display:flex;flex-direction:column;gap:8px;">
      <div style="display:flex;justify-content:space-between;font-size:12px;">
        <span style="color:#64748b;">وضعیت</span>
        <span style="color:<?=$isActive?'#4ade80':'#fbbf24'?>;font-weight:700;"><?= $isActive?'فعال':'غیرفعال' ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:12px;">
        <span style="color:#64748b;">آنلاین</span>
        <span style="color:<?=$isOnline?'#4ade80':'#64748b'?>"><?=$isOnline?'● آری':'○ خیر'?></span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:12px;">
        <span style="color:#64748b;">پروفایل</span>
        <span style="color:#94a3b8;"><?= e($profiles[$currentProfile][1] ?? $currentProfile) ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:12px;">
        <span style="color:#64748b;">آخرین ارتباط</span>
        <span style="color:#94a3b8;"><?= $screen['last_seen_at'] ? timeAgo($screen['last_seen_at']) : '—' ?></span>
      </div>
    </div>
  </div>

  <!-- زمان‌بندی -->
  <div class="card">
    <h3 style="font-size:13px;font-weight:700;color:#fff;margin-bottom:10px;">
      <i class="fas fa-calendar text-orange-400 ml-2"></i>زمان‌بندی
    </h3>
    <a href="/admin/schedules" class="btn-ghost text-xs w-full flex items-center justify-center gap-1.5 py-2">
      <i class="fas fa-plus text-xs text-orange-400"></i> افزودن زمان‌بندی
    </a>
  </div>
</div>
</div><!-- /grid -->


</div><!-- /grid -->

<script>
function showTab(t) {
  ['info','activation','player','broadcast','status'].forEach(function(id){
    var s=document.getElementById('sec-'+id);
    var b=document.getElementById('stab-'+id);
    if(s) s.style.display=(id===t)?'block':'none';
    if(b){
      b.style.background=(id===t)?'rgba(249,115,22,.2)':'transparent';
      b.style.color=(id===t)?'#f97316':'#64748b';
    }
  });
  if(t==='broadcast'){try{bcType('image');loadBcMedia('image');}catch(e){}}
}

var ppColors={modern:'#f97316',android_tv:'#22c55e',lg_tv:'#006eb6',samsung_tv:'#1428a0',legacy:'#60a5fa',minimal:'#64748b',kiosk:'#a855f7'};
function setProfile(p){
  var inp=document.getElementById('pp-input');
  if(inp)inp.value=p;
  document.querySelectorAll('[id^="pp-"]').forEach(function(b){
    var k=b.id.replace('pp-',''),c=ppColors[k]||'#f97316',on=(k===p);
    b.style.background=on?'rgba('+hx(c)+',0.15)':'rgba(255,255,255,0.03)';
    b.style.borderColor=on?c:'rgba(255,255,255,0.08)';
    var lbl=b.querySelectorAll('div')[1];
    if(lbl)lbl.style.color=on?c:'#94a3b8';
  });
}
function hx(h){return parseInt(h.slice(1,3),16)+','+parseInt(h.slice(3,5),16)+','+parseInt(h.slice(5,7),16);}



// ─── Profile ──────────────────────────────────────────

// ─── Broadcast ────────────────────────────────────────
let _bcType='image', _bcMediaId=null;
function bcType(t) {
  _bcType=t; _bcMediaId=null;
  ['image','video','url','text','fids_live'].forEach(x=>{
    const b=document.getElementById('bc-'+x);
    if(!b)return;
    b.style.background=x===t?'rgba(249,115,22,.2)':'transparent';
    b.style.color=x===t?'#f97316':'#64748b';
  });
  document.getElementById('bc-media-wrap').style.display=['image','video'].includes(t)?'':'none';
  document.getElementById('bc-url-wrap').style.display=t==='url'?'':'none';
  document.getElementById('bc-text-wrap').style.display=t==='text'?'':'none';
  const fidsWrap=document.getElementById('bc-fids_live-wrap');
  if(fidsWrap) fidsWrap.style.display=t==='fids_live'?'':'none';
  if(['image','video'].includes(t)) loadBcMedia(t==='video'?'video':'image');
}

async function loadBcMedia(type) {
  try {
    const r = await fetch(`/admin/screens/<?= $screen['id'] ?>/media-list?type=${type}`);
    const d = await r.json();
    const g = document.getElementById('bc-grid');
    const items = d.data || [];
    if (!items.length) { g.innerHTML='<div style="grid-column:1/-1;text-align:center;padding:16px;color:#475569;font-size:11px;">رسانه‌ای نیست</div>'; return; }
    const base = window.location.origin;
    g.innerHTML = items.map(m => `
      <div onclick="bcSelect(${m.id},'${(m.file_path||'').replace(/'/g,"\\'")}',this)"
        style="border-radius:7px;overflow:hidden;border:2px solid rgba(255,255,255,.08);cursor:pointer;transition:all .15s;">
        ${m.thumbnail_path?`<img src="${base+m.thumbnail_path}" style="width:100%;height:52px;object-fit:cover;">`
          :`<div style="height:52px;display:flex;align-items:center;justify-content:center;background:#1a0a2e;"><i class="fas fa-play-circle" style="color:#a855f7;font-size:16px;"></i></div>`}
        <div style="padding:3px 4px;font-size:9px;color:#94a3b8;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">${m.name}</div>
      </div>`).join('');
  } catch(e) {}
}

function bcSelect(id, url, el) {
  _bcMediaId=id;
  document.querySelectorAll('#bc-grid > div').forEach(e=>{e.style.borderColor='rgba(255,255,255,.08)';e.style.boxShadow='';});
  el.style.borderColor='#f97316'; el.style.boxShadow='0 0 0 2px rgba(249,115,22,.25)';
}

async function bcSend(sid) {
  const fd=new FormData();
  fd.append('duration',document.getElementById('bc-dur').value);
  fd.append('target',document.getElementById('bc-tgt').value);

  if(_bcType==='fids_live'){
    // ── FIDS زنده: ساخت URL به module renderer ──────────────────────
    const airportId  = document.getElementById('bc-fids-airport').value;
    const direction  = document.getElementById('bc-fids-direction').value;
    const routeType  = document.getElementById('bc-fids-route').value;
    const rows       = document.getElementById('bc-fids-rows').value;
    const theme      = document.getElementById('bc-fids-theme').value;
    const settings   = JSON.stringify({
      zone_type:'fids_live_board',
      airport_id: airportId,
      direction:  direction,
      route_type: routeType,
      rows:       rows,
      color_scheme: theme,
      refresh_sec: '60',
    });
    const fidsUrl = window.location.origin + '/player/module/fids?settings=' + encodeURIComponent(settings);
    fd.append('type','url');
    fd.append('content', fidsUrl);
  } else if(_bcType==='text'){
    const txt=document.getElementById('bc-txt').value.trim();
    if(!txt){alert('متن الزامی است');return;}
    fd.append('type','text');
    fd.append('content',JSON.stringify({text:txt,color:document.getElementById('bc-tc').value,bg:document.getElementById('bc-bg').value}));
  } else if(_bcType==='url'){
    const u=document.getElementById('bc-webpage').value.trim();
    if(!u){alert('آدرس الزامی است');return;}
    fd.append('type','url');
    fd.append('content',u);
  } else {
    fd.append('type',_bcType);
    const direct=document.getElementById('bc-direct').value.trim();
    if(_bcMediaId) fd.append('media_id',_bcMediaId);
    else if(direct) fd.append('content',direct);
    else{alert('رسانه انتخاب کنید یا URL وارد کنید');return;}
  }

  const r=await fetch(`/admin/screens/${sid}/broadcast`,{method:'POST',body:fd});
  const d=await r.json();
  showToast(d.success?'success':'error',d.message);
  if(d.success){
    document.getElementById('bc-stop-btn').style.display='';
    document.getElementById('bc-preview').style.display='';
    const previewLabel=_bcType==='fids_live'
      ? '✈ تابلو FIDS زنده در حال پخش...'
      : '✅ '+(d.message||'ارسال شد');
    document.getElementById('bc-preview-inner').textContent=previewLabel;
    const dur=parseInt(document.getElementById('bc-dur').value);
    if(dur>0) setTimeout(()=>bcStop(sid,true),dur*1000);
  }
}

async function bcStop(sid,auto=false){
  await fetch(`/admin/screens/${sid}/broadcast/clear`,{method:'POST',body:new FormData()});
  document.getElementById('bc-stop-btn').style.display='none';
  document.getElementById('bc-preview').style.display='none';
  if(!auto) showToast('success','پخش متوقف شد');
}

function copyPlayerUrl(){
  navigator.clipboard.writeText(document.getElementById('player-url').textContent.trim())
    .then(()=>showToast('success','آدرس کپی شد'));
}
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
