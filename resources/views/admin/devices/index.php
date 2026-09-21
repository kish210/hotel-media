<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<?php
$devices   = $devices   ?? [];
$tokens    = $tokens    ?? [];
$rooms     = $rooms     ?? [];
$groups    = $groups    ?? [];
$menus     = $menus     ?? [];
$stats     = $stats     ?? [];
$portalUrl = $portalUrl ?? '';
$capabilities = $capabilities ?? [];

$PLATFORMS = [
  'android' => ['Android TV',    'android',  '#22c55e'],
  'webos'   => ['LG webOS',      'tv',       '#ef4444'],
  'tizen'   => ['Samsung Tizen', 'tv',       '#3b82f6'],
  'windows' => ['Windows',       'desktop',  '#0ea5e9'],
  'browser' => ['مرورگر',        'globe',    '#a855f7'],
  'unknown' => ['نامشخص',        'question', '#64748b'],
];

$CMD_LABELS = [
  'refresh'     => 'تازه‌سازی صفحه',
  'reboot'      => 'راه‌اندازی مجدد',
  'volume'      => 'تنظیم صدا',
  'brightness'  => 'تنظیم روشنایی',
  'power'       => 'خاموش / روشن',
  'channel'     => 'تغییر کانال',
  'message'     => 'ارسال پیام',
  'update_app'  => 'به‌روزرسانی اپ',
  'open_url'    => 'باز کردن آدرس',
  'clear_cache' => 'پاک کردن کش',
  'screenshot'  => 'عکس صفحه',
];
?>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <div style="display:flex;align-items:center;gap:12px;">
    <h1 style="font-size:20px;font-weight:800;color:#fff;">
      <i class="fas fa-tv" style="color:#38bdf8;margin-left:10px;"></i>مدیریت تلویزیون‌ها
    </h1>
    <span id="liveCount" style="font-size:11px;color:#22c55e;background:rgba(34,197,94,.1);padding:3px 11px;border-radius:10px;">
      <?= (int)($stats['online'] ?? 0) ?> از <?= (int)($stats['total'] ?? 0) ?> آنلاین
    </span>
  </div>
  <div style="display:flex;gap:8px;">
    <button onclick="openModal('installModal')" class="btn-ghost text-sm px-3">
      <i class="fas fa-book text-xs ml-1"></i>راهنمای نصب
    </button>
    <button onclick="openModal('tokenModal')" class="btn-ghost text-sm px-3">
      <i class="fas fa-key text-xs ml-1"></i>توکن ثبت
    </button>
    <button onclick="openModal('bulkModal')" class="btn-primary text-sm">
      <i class="fas fa-bolt text-xs ml-1"></i>فرمان گروهی
    </button>
  </div>
</div>

<!-- آدرس پورتال — مهم‌ترین چیزی که تکنسین لازم دارد -->
<div style="background:linear-gradient(90deg,rgba(56,189,248,.10),rgba(56,189,248,.02));border:1px solid rgba(56,189,248,.25);border-radius:14px;padding:16px;margin-bottom:20px;">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
      <div style="font-size:12px;color:#38bdf8;font-weight:700;margin-bottom:5px;">
        <i class="fas fa-link text-xs ml-1"></i>آدرس پورتال — همین را در منوی مخفی <b>همه‌ی</b> تلویزیون‌ها وارد کنید
      </div>
      <div id="portalUrl" style="font-size:20px;font-family:monospace;color:#fff;direction:ltr;letter-spacing:1px;">
        <?= htmlspecialchars($portalUrl) ?>
      </div>
      <div style="font-size:11px;color:#64748b;margin-top:5px;">
        آدرس برای همه یکسان است — هر تلویزیون خودش را با MAC می‌شناساند و کد اختصاصی می‌گیرد
      </div>
    </div>
    <button onclick="copyUrl()" class="btn-ghost text-sm px-4">
      <i class="fas fa-copy text-xs ml-1"></i>کپی
    </button>
  </div>
</div>

<!-- آمار -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px;">
  <?php
  $platCounts = [];
  foreach ($devices as $d) {
      $p = $d['platform'] ?: 'unknown';
      $platCounts[$p] = ($platCounts[$p] ?? 0) + 1;
  }
  $cards = [
    ['کل دستگاه‌ها', (int)($stats['total'] ?? 0),   'tv',            '#38bdf8'],
    ['آنلاین',       (int)($stats['online'] ?? 0),  'circle-check',  '#22c55e'],
    ['آفلاین',       (int)($stats['offline'] ?? 0), 'circle-xmark',  '#ef4444'],
    ['منتظر تایید',  (int)($stats['pending'] ?? 0), 'hourglass-half','#f59e0b'],
  ];
  foreach ($cards as [$label, $value, $icon, $color]): ?>
    <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:14px;">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
        <i class="fas fa-<?= $icon ?>" style="color:<?= $color ?>;font-size:12px;"></i>
        <span style="font-size:11px;color:#64748b;"><?= $label ?></span>
      </div>
      <div style="font-size:20px;font-weight:800;color:#fff;"><?= $value ?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- فیلتر -->
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;align-items:center;">
  <button class="pf active" data-p="all" onclick="filterPlat('all',this)">همه</button>
  <?php foreach ($PLATFORMS as $key => [$label, $icon, $color]):
    if (empty($platCounts[$key])) continue; ?>
    <button class="pf" data-p="<?= $key ?>" onclick="filterPlat('<?= $key ?>',this)">
      <i class="fas fa-<?= $icon ?>" style="color:<?= $color ?>;font-size:10px;"></i>
      <?= $label ?> (<?= $platCounts[$key] ?>)
    </button>
  <?php endforeach; ?>
  <input id="search" oninput="doSearch()" placeholder="جستجوی اتاق، کد، مدل…"
         style="margin-right:auto;font-size:12px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:9px;color:#cbd5e1;padding:6px 12px;min-width:220px;">
</div>

<!-- جدول دستگاه‌ها -->
<div style="background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.05);border-radius:12px;overflow:hidden;">
  <?php if (!$devices): ?>
    <div style="padding:40px;text-align:center;color:#475569;font-size:13px;">
      <i class="fas fa-tv" style="font-size:30px;display:block;margin-bottom:12px;color:#1e293b;"></i>
      هنوز تلویزیونی ثبت نشده — اول یک توکن ثبت بسازید و آدرس بالا را در منوی تلویزیون‌ها وارد کنید
    </div>
  <?php else: ?>
    <?php foreach ($devices as $d):
      [$pLabel, $pIcon, $pColor] = $PLATFORMS[$d['platform'] ?: 'unknown'] ?? $PLATFORMS['unknown'];
      $online  = !empty($d['online']);
      $pending = $d['status'] === 'pending';
      $caps    = $capabilities[$d['platform'] ?: 'unknown'] ?? [];
      $search  = mb_strtolower(implode(' ', array_filter([
          $d['name'], $d['code'], $d['model'], $d['mac_address'], $d['room_number'], $d['room_name'],
      ])));
    ?>
    <div class="dev" data-p="<?= $d['platform'] ?: 'unknown' ?>" data-s="<?= htmlspecialchars($search) ?>"
         style="display:flex;align-items:center;gap:12px;padding:11px 14px;border-bottom:1px solid rgba(255,255,255,.04);flex-wrap:wrap;">

      <span title="<?= $online ? 'آنلاین' : 'آفلاین' ?>"
            style="width:9px;height:9px;border-radius:50%;flex-shrink:0;background:<?= $online ? '#22c55e' : '#475569' ?>;<?= $online ? 'box-shadow:0 0 7px #22c55e88;' : '' ?>"></span>

      <span style="width:30px;height:30px;border-radius:8px;background:<?= $pColor ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fas fa-<?= $pIcon ?>" style="color:<?= $pColor ?>;font-size:12px;"></i>
      </span>

      <div style="min-width:170px;flex:1;">
        <div style="font-size:13px;color:#fff;font-weight:600;">
          <?= $d['room_number'] ? 'اتاق ' . htmlspecialchars((string)$d['room_number']) : htmlspecialchars((string)$d['name']) ?>
          <?php if ($pending): ?>
            <span style="font-size:10px;color:#f59e0b;background:rgba(245,158,11,.12);padding:1px 7px;border-radius:7px;">منتظر تایید</span>
          <?php endif; ?>
        </div>
        <div style="font-size:10px;color:#64748b;font-family:monospace;direction:ltr;text-align:right;">
          <?= htmlspecialchars((string)$d['code']) ?>
          <?= $d['model'] ? ' · ' . htmlspecialchars((string)$d['model']) : '' ?>
          <?= $d['mac_address'] ? ' · ' . htmlspecialchars((string)$d['mac_address']) : '' ?>
        </div>
      </div>

      <div style="font-size:10px;color:#475569;min-width:110px;">
        <?= $pLabel ?>
        <?php if (!empty($d['app_version'])): ?><br>v<?= htmlspecialchars((string)$d['app_version']) ?><?php endif; ?>
      </div>

      <div style="font-size:10px;color:#475569;min-width:90px;">
        <?php if ($d['seconds_ago'] === null): ?>
          هرگز متصل نشده
        <?php elseif ($online): ?>
          <span style="color:#22c55e;">همین الان</span>
        <?php else: ?>
          <?= floor((int)$d['seconds_ago'] / 60) ?> دقیقه پیش
        <?php endif; ?>
        <?php if ((int)$d['pending_commands'] > 0): ?>
          <br><span style="color:#f59e0b;"><?= (int)$d['pending_commands'] ?> فرمان در صف</span>
        <?php endif; ?>
      </div>

      <div style="display:flex;gap:5px;flex-wrap:wrap;">
        <?php if ($pending): ?>
          <button onclick="approve(<?= (int)$d['id'] ?>)" class="btn-primary text-xs" style="padding:5px 11px;font-size:11px;">تایید</button>
        <?php endif; ?>

        <select onchange="assignRoom(<?= (int)$d['id'] ?>, this.value)"
                style="font-size:10px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:7px;color:#cbd5e1;padding:5px;max-width:130px;">
          <option value="">— بدون اتاق —</option>
          <?php foreach ($rooms as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= (int)($d['iptv_room_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)$r['room_number']) ?><?= $r['floor'] !== null ? ' (ط' . (int)$r['floor'] . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>

        <select onchange="sendCmd(<?= (int)$d['id'] ?>, this)"
                style="font-size:10px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:7px;color:#cbd5e1;padding:5px;">
          <option value="">— فرمان —</option>
          <?php foreach ($caps as $c): ?>
            <option value="<?= $c ?>"><?= $CMD_LABELS[$c] ?? $c ?></option>
          <?php endforeach; ?>
        </select>

        <button onclick="history(<?= (int)$d['id'] ?>)" class="btn-ghost text-xs px-2" title="تاریخچه">
          <i class="fas fa-clock-rotate-left" style="font-size:10px;"></i>
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ═══ مودال راهنمای نصب ═══ -->
<div id="installModal" class="hidden modal-bg">
  <div class="modal-box" style="max-width:820px;">
    <div class="modal-head">
      <h3>راهنمای نصب روی تلویزیون هتل</h3>
      <button onclick="closeModal('installModal')" class="btn-ghost text-xs px-2"><i class="fas fa-times"></i></button>
    </div>

    <div style="background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.2);border-radius:10px;padding:12px;margin-bottom:16px;">
      <div style="font-size:11px;color:#64748b;margin-bottom:4px;">آدرسی که باید وارد شود (برای همه یکسان):</div>
      <div style="font-size:17px;font-family:monospace;color:#38bdf8;direction:ltr;"><?= htmlspecialchars($portalUrl) ?></div>
    </div>

    <!-- LG -->
    <div class="guide">
      <h4 style="color:#ef4444;"><i class="fas fa-tv ml-2"></i>LG — تلویزیون هتلی webOS</h4>
      <ol>
        <li>دکمه‌ی <b>MENU</b> (یا SETTINGS) روی ریموت را نگه دارید تا منوی تلویزیون محو شود و اطلاعات کانال ظاهر شود، سپس رها کنید.</li>
        <li>بلافاصله <b>1105</b> را بزنید و <b>OK</b> را فشار دهید — منوی نصب باز می‌شود.</li>
        <li>وارد <b>Manual Pro:Centric</b> شوید و تنظیم کنید:
          <ul>
            <li><b>Pro:Centric Mode</b> → <code>HTML</code></li>
            <li><b>Media Type</b> → <code>IP</code></li>
            <li><b>IP Address</b> → <code><?= htmlspecialchars(parse_url($portalUrl, PHP_URL_HOST) ?: '') ?></code></li>
            <li><b>Port</b> → <code><?= (int)(parse_url($portalUrl, PHP_URL_PORT) ?: 80) ?></code></li>
          </ul>
        </li>
        <li>ذخیره کنید و تلویزیون را ریبوت کنید. دستگاه خودش ثبت می‌شود و در همین صفحه ظاهر می‌گردد.</li>
      </ol>
      <p class="note">با منوی <b>994</b> می‌توانید آدرس‌های ثبت‌شده را بررسی کنید.</p>
    </div>

    <!-- Samsung -->
    <div class="guide">
      <h4 style="color:#3b82f6;"><i class="fas fa-tv ml-2"></i>Samsung — تلویزیون هتلی Tizen</h4>
      <ol>
        <li>روی ریموت به‌ترتیب و سریع بزنید: <b>MUTE</b> → <b>1</b> → <b>1</b> → <b>9</b> → <b>ENTER</b> — منوی Hotel Mode باز می‌شود.</li>
        <li><b>Hospitality Mode</b> را روی <code>ON</code> بگذارید.</li>
        <li>به بخش <b>URL Launcher Setting</b> بروید (در بعضی مدل‌ها زیر <b>SI Vendor</b> یا <b>Server URL Setting</b>).</li>
        <li>آدرس بالا را وارد کنید و ذخیره کنید.</li>
        <li><b>SI Vendor</b> را روی <code>URL Launcher</code> تنظیم و تلویزیون را ریبوت کنید.</li>
      </ol>
      <p class="note">توالی دکمه‌ها باید ظرف ۲ تا ۳ ثانیه کامل شود، وگرنه منو باز نمی‌شود.</p>
    </div>

    <!-- Android -->
    <div class="guide">
      <h4 style="color:#22c55e;"><i class="fas fa-android ml-2"></i>Android TV</h4>
      <ol>
        <li>فایل APK را از بخش <a href="/admin/app" style="color:#38bdf8;">به‌روزرسانی اپ</a> روی دستگاه نصب کنید.</li>
        <li>در اولین اجرا، آدرس سرور را وارد کنید: <code><?= htmlspecialchars($portalUrl) ?></code></li>
        <li>اپ خودش را ثبت می‌کند. روی Android TV همه‌ی فرمان‌ها (ریبوت، صدا، روشنایی) در دسترس است.</li>
      </ol>
    </div>

    <div style="background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:12px;font-size:12px;color:#fbbf24;line-height:1.9;">
      <b>محدودیت واقعی که باید بدانید:</b><br>
      روی LG و Samsung، صفحه‌ی ما یک اپ HTML5 داخل تلویزیون است. فرمان‌هایی مثل
      <b>ریبوت و خاموش/روشن از راه دور</b> کار سرور Pro:Centric (LG) و LYNK REACH (سامسونگ) است،
      نه اپ. به همین دلیل این فرمان‌ها برای این دو برند در لیست نمایش داده نمی‌شوند.
      روی Android TV که اپ بومی داریم، همه‌ی فرمان‌ها کار می‌کنند.
    </div>
  </div>
</div>

<!-- ═══ مودال توکن ═══ -->
<div id="tokenModal" class="hidden modal-bg">
  <div class="modal-box" style="max-width:700px;">
    <div class="modal-head">
      <h3>توکن ثبت دستگاه</h3>
      <button onclick="closeModal('tokenModal')" class="btn-ghost text-xs px-2"><i class="fas fa-times"></i></button>
    </div>

    <p style="font-size:12px;color:#64748b;margin-bottom:14px;line-height:1.9;">
      توکن مشخص می‌کند دستگاه‌های تازه به کدام گروه و منو بروند و آیا خودکار فعال شوند یا منتظر تایید بمانند.
      آدرس <code>/tv</code> از آخرین توکن فعال استفاده می‌کند.
    </p>

    <form onsubmit="return addToken(event)" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:7px;margin-bottom:16px;">
      <input id="tLabel" placeholder="عنوان، مثلا «طبقه ۳»" required
             style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:8px;color:#fff;font-size:12px;">
      <select id="tGroup" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:8px;color:#fff;font-size:12px;">
        <option value="">بدون گروه</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars((string)$g['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="tMenu" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:8px;color:#fff;font-size:12px;">
        <option value="">بدون منو</option>
        <?php foreach ($menus as $m): ?>
          <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars((string)$m['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-primary text-xs" style="padding:8px 14px;">ساخت</button>
      <label style="grid-column:1/-1;font-size:11px;color:#94a3b8;display:flex;align-items:center;gap:7px;cursor:pointer;">
        <input type="checkbox" id="tAuto" checked style="accent-color:#38bdf8;">
        دستگاه‌های تازه بدون نیاز به تایید، فعال شوند
      </label>
    </form>

    <?php foreach ($tokens as $t): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 11px;border-bottom:1px solid rgba(255,255,255,.05);gap:10px;flex-wrap:wrap;">
        <div style="min-width:180px;">
          <div style="font-size:12px;color:#fff;font-weight:600;"><?= htmlspecialchars((string)$t['label']) ?></div>
          <div style="font-size:10px;color:#64748b;">
            <?= (int)$t['used_count'] ?> دستگاه<?= $t['max_devices'] ? ' از ' . (int)$t['max_devices'] : '' ?>
            <?= (int)$t['auto_approve'] ? ' · تایید خودکار' : ' · نیاز به تایید' ?>
            <?= $t['group_name'] ? ' · ' . htmlspecialchars((string)$t['group_name']) : '' ?>
          </div>
        </div>
        <code style="font-size:10px;color:#38bdf8;direction:ltr;background:rgba(0,0,0,.25);padding:4px 9px;border-radius:6px;">
          /tv?t=<?= htmlspecialchars(substr((string)$t['token'], 0, 12)) ?>…
        </code>
        <button onclick="delToken(<?= (int)$t['id'] ?>)" class="btn-ghost text-xs px-2" style="color:#ef4444;">
          <i class="fas fa-trash" style="font-size:10px;"></i>
        </button>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ═══ مودال فرمان گروهی ═══ -->
<div id="bulkModal" class="hidden modal-bg">
  <div class="modal-box" style="max-width:520px;">
    <div class="modal-head">
      <h3>فرمان گروهی</h3>
      <button onclick="closeModal('bulkModal')" class="btn-ghost text-xs px-2"><i class="fas fa-times"></i></button>
    </div>

    <form onsubmit="return bulkSend(event)" style="display:flex;flex-direction:column;gap:10px;">
      <select id="bPlat" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:9px;color:#fff;font-size:12px;">
        <option value="">همه‌ی پلتفرم‌ها</option>
        <?php foreach ($PLATFORMS as $k => [$l]): ?>
          <option value="<?= $k ?>"><?= $l ?></option>
        <?php endforeach; ?>
      </select>

      <select id="bCmd" onchange="toggleBulkValue()"
              style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:9px;color:#fff;font-size:12px;">
        <?php foreach ($CMD_LABELS as $k => $l): ?>
          <option value="<?= $k ?>"><?= $l ?></option>
        <?php endforeach; ?>
      </select>

      <input id="bValue" class="hidden" placeholder="مقدار"
             style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:9px;color:#fff;font-size:12px;">

      <label style="font-size:11px;color:#f59e0b;display:flex;align-items:center;gap:7px;cursor:pointer;">
        <input type="checkbox" id="bAll" style="accent-color:#f59e0b;">
        تایید می‌کنم که این فرمان روی <b>همه‌ی</b> دستگاه‌های منطبق اجرا شود
      </label>

      <button type="submit" class="btn-primary text-sm">ارسال</button>
    </form>
  </div>
</div>

<!-- ═══ مودال تاریخچه ═══ -->
<div id="histModal" class="hidden modal-bg">
  <div class="modal-box" style="max-width:640px;">
    <div class="modal-head">
      <h3>تاریخچه دستگاه</h3>
      <button onclick="closeModal('histModal')" class="btn-ghost text-xs px-2"><i class="fas fa-times"></i></button>
    </div>
    <div id="histBody" style="max-height:60vh;overflow:auto;"></div>
  </div>
</div>

<div id="toast" class="hidden"></div>

<style>
.pf{font-size:11px;padding:5px 12px;border-radius:9px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);color:#94a3b8;cursor:pointer;}
.pf.active{background:rgba(56,189,248,.15);border-color:rgba(56,189,248,.4);color:#7dd3fc;}
.dev.hide{display:none;}
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:90;display:flex;align-items:center;justify-content:center;padding:20px;}
.modal-box{background:#0f172a;border:1px solid rgba(255,255,255,.1);border-radius:16px;width:100%;max-height:88vh;overflow:auto;padding:20px;}
.modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
.modal-head h3{font-size:15px;font-weight:800;color:#fff;}
.guide{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:11px;padding:14px;margin-bottom:12px;}
.guide h4{font-size:13px;font-weight:700;margin-bottom:9px;}
.guide ol{padding-right:19px;font-size:12px;color:#cbd5e1;line-height:2.1;}
.guide ul{padding-right:17px;margin:4px 0;color:#94a3b8;}
.guide code{background:rgba(0,0,0,.35);padding:1px 7px;border-radius:5px;color:#7dd3fc;font-size:11px;direction:ltr;display:inline-block;}
.guide .note{font-size:11px;color:#64748b;margin-top:8px;}
</style>

<script>
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function copyUrl() {
  const t = document.getElementById('portalUrl').textContent.trim();
  // clipboard API روی http غیرلوکال در دسترس نیست — روش قدیمی هم لازم است
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(t).then(() => showToast('آدرس کپی شد'));
    return;
  }
  const ta = document.createElement('textarea');
  ta.value = t; ta.style.position = 'fixed'; ta.style.opacity = '0';
  document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); showToast('آدرس کپی شد'); }
  catch (e) { showToast('کپی نشد — دستی انتخاب کنید', 'error'); }
  document.body.removeChild(ta);
}

function filterPlat(p, btn) {
  document.querySelectorAll('.pf').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  window._plat = p;
  applyFilters();
}

function doSearch() { applyFilters(); }

function applyFilters() {
  const p = window._plat || 'all';
  const q = (document.getElementById('search').value || '').trim().toLowerCase();
  document.querySelectorAll('.dev').forEach(el => {
    const okP = p === 'all' || el.dataset.p === p;
    const okQ = !q || (el.dataset.s || '').indexOf(q) >= 0;
    el.classList.toggle('hide', !(okP && okQ));
  });
}

async function approve(id) {
  try { await api(`/api/v1/devices/${id}/approve`, 'POST'); showToast('تایید شد'); reload(); }
  catch (e) { showToast(e.message, 'error'); }
}

async function assignRoom(id, roomId) {
  try {
    await api(`/api/v1/devices/${id}/assign-room`, 'POST', { room_id: Number(roomId) || 0 });
    showToast('اتاق به‌روزرسانی شد'); reload();
  } catch (e) { showToast(e.message, 'error'); }
}

async function sendCmd(id, sel) {
  const cmd = sel.value;
  sel.value = '';
  if (!cmd) return;

  const payload = {};
  if (cmd === 'volume' || cmd === 'brightness') {
    const v = prompt('مقدار (۰ تا ۱۰۰):', '50');
    if (v === null) return;
    payload.value = Number(v);
  } else if (cmd === 'message') {
    const t = prompt('متن پیام:');
    if (!t) return;
    payload.text = t;
  } else if (cmd === 'open_url') {
    const u = prompt('آدرس:', 'https://');
    if (!u) return;
    payload.url = u;
  } else if (cmd === 'power') {
    payload.state = confirm('روشن شود؟ (لغو = خاموش)') ? 'on' : 'off';
  }

  try {
    const d = await api(`/api/v1/devices/${id}/command`, 'POST', { command: cmd, payload });
    showToast(d.message || 'فرمان ارسال شد');
  } catch (e) { showToast(e.message, 'error'); }
}

function toggleBulkValue() {
  const c = document.getElementById('bCmd').value;
  const needs = ['volume', 'brightness', 'message', 'open_url'].indexOf(c) >= 0;
  document.getElementById('bValue').classList.toggle('hidden', !needs);
}

async function bulkSend(ev) {
  ev.preventDefault();
  if (!document.getElementById('bAll').checked) {
    showToast('برای اجرای گروهی باید تایید کنید', 'error');
    return false;
  }

  const cmd  = document.getElementById('bCmd').value;
  const plat = document.getElementById('bPlat').value;
  const val  = document.getElementById('bValue').value.trim();

  const payload = {};
  if (cmd === 'volume' || cmd === 'brightness') payload.value = Number(val);
  else if (cmd === 'message')                   payload.text  = val;
  else if (cmd === 'open_url')                  payload.url   = val;

  try {
    const d = await api('/api/v1/devices/bulk-command', 'POST', {
      command: cmd, payload,
      filter: plat ? { platform: plat, all: true } : { all: true },
    });
    showToast(d.message || 'ارسال شد');
    closeModal('bulkModal');
  } catch (e) { showToast(e.message, 'error'); }
  return false;
}

async function history(id) {
  openModal('histModal');
  document.getElementById('histBody').innerHTML = '<div style="padding:20px;text-align:center;color:#475569;">…</div>';
  try {
    const d = await api(`/api/v1/devices/${id}/history`);
    const cmds = (d.data.commands || []).map(c =>
      `<div style="display:flex;justify-content:space-between;padding:7px 4px;border-bottom:1px solid rgba(255,255,255,.04);font-size:11px;">
         <span style="color:#cbd5e1;">${esc(c.command)}</span>
         <span style="color:#64748b;">${esc(c.status)} · ${esc(c.created_at)}</span>
       </div>`).join('');
    const evs = (d.data.events || []).map(e =>
      `<div style="display:flex;justify-content:space-between;padding:7px 4px;border-bottom:1px solid rgba(255,255,255,.04);font-size:11px;">
         <span style="color:#94a3b8;">${esc(e.event)} ${esc(e.detail || '')}</span>
         <span style="color:#475569;">${esc(e.created_at)}</span>
       </div>`).join('');
    document.getElementById('histBody').innerHTML =
      `<h4 style="font-size:12px;color:#38bdf8;margin:8px 0;">فرمان‌ها</h4>${cmds || '<div style="color:#475569;font-size:11px;padding:6px;">موردی نیست</div>'}
       <h4 style="font-size:12px;color:#38bdf8;margin:14px 0 8px;">رویدادها</h4>${evs || '<div style="color:#475569;font-size:11px;padding:6px;">موردی نیست</div>'}`;
  } catch (e) {
    document.getElementById('histBody').innerHTML = `<div style="color:#f87171;padding:14px;">${esc(e.message)}</div>`;
  }
}

async function addToken(ev) {
  ev.preventDefault();
  try {
    await api('/api/v1/devices/tokens', 'POST', {
      label:        document.getElementById('tLabel').value.trim(),
      group_id:     Number(document.getElementById('tGroup').value) || 0,
      menu_id:      Number(document.getElementById('tMenu').value) || 0,
      auto_approve: document.getElementById('tAuto').checked,
    });
    showToast('توکن ساخته شد'); reload();
  } catch (e) { showToast(e.message, 'error'); }
  return false;
}

async function delToken(id) {
  if (!confirm('این توکن غیرفعال شود؟')) return;
  try { await api(`/api/v1/devices/tokens/${id}`, 'DELETE'); showToast('غیرفعال شد'); reload(); }
  catch (e) { showToast(e.message, 'error'); }
}

// ── وضعیت زنده ──
setInterval(async () => {
  if (document.querySelector('.modal-bg:not(.hidden)')) return;
  try {
    const r = await fetch('/admin/devices/feed', { credentials: 'same-origin' });
    const d = await r.json();
    if (!d.success) return;
    document.getElementById('liveCount').textContent = `${d.online} از ${d.total} آنلاین`;
  } catch (_) {}
}, 20000);

async function api(url, method = 'GET', body = null) {
  const opts = { method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, credentials: 'same-origin' };
  if (body) opts.body = JSON.stringify(body);
  const r = await fetch(url, opts);
  let d = {};
  try { d = await r.json(); } catch (_) {}
  if (!r.ok) throw new Error(d.message || `خطا (${r.status})`);
  return d;
}

function reload() { setTimeout(() => location.reload(), 500); }
function esc(s) { return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.className = `toast toast-${type}`;
  t.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${msg}`;
  t.classList.remove('hidden');
  setTimeout(() => t.classList.add('hidden'), 3500);
}
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
