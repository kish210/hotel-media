<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<div style="max-width:860px;margin:0 auto;">

<div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
  <div style="width:44px;height:44px;border-radius:14px;background:rgba(99,102,241,.15);display:flex;align-items:center;justify-content:center;">
    <i class="fas fa-book-open" style="color:#818cf8;font-size:20px;"></i>
  </div>
  <div>
    <h1 style="font-size:20px;font-weight:800;color:#fff;margin:0;">راهنمای Hotel Media</h1>
    <div style="font-size:12px;color:#64748b;margin-top:2px;">مستندات کامل سیستم</div>
  </div>
</div>

<!-- فهرست مطالب -->
<div class="card" style="padding:20px;margin-bottom:24px;">
  <div style="font-size:12px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;">فهرست</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
    <?php
    $toc = [
      ['#getting-started', 'fa-rocket',       '#6366f1', 'شروع سریع'],
      ['#screens',         'fa-tv',           '#1a7ac4', 'مدیریت صفحه‌نمایش'],
      ['#playlists',       'fa-list',         '#22c55e', 'پلی‌لیست‌ها'],
      ['#media',           'fa-photo-film',   '#a855f7', 'رسانه‌ها'],
      ['#iptv',            'fa-satellite-dish','#ef4444', 'IPTV کانال‌ها'],
      ['#tvheadend',       'fa-broadcast-tower','#ef4444','TVHeadend پخش زنده'],
      ['#vod',             'fa-film',         '#f59e0b', 'VOD ویدیو آنلاین'],
      ['#fids',            'fa-plane',        '#06b6d4', 'FIDS اطلاعات پروازی'],
      ['#websocket',       'fa-plug',         '#22c55e', 'WebSocket'],
      ['#api',             'fa-code',         '#94a3b8', 'REST API'],
      ['#server-req',      'fa-server',       '#64748b', 'نیازمندی‌های سرور'],
    ];
    foreach ($toc as [$href,$ico,$col,$label]):
    ?>
    <a href="<?= $href ?>" style="display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:8px;text-decoration:none;color:#94a3b8;font-size:12px;transition:background .15s;"
       onmouseenter="this.style.background='rgba(255,255,255,.05)'" onmouseleave="this.style.background=''">
      <i class="fas <?= $ico ?>" style="color:<?= $col ?>;width:14px;text-align:center;"></i><?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ─── شروع سریع ──────────────────────────────── -->
<section id="getting-started" style="margin-bottom:32px;">
  <h2 style="font-size:16px;font-weight:800;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
    <i class="fas fa-rocket" style="color:#6366f1;"></i> شروع سریع
  </h2>
  <div class="card" style="padding:20px;">
    <ol style="padding-right:20px;color:#94a3b8;font-size:13px;line-height:2.2;">
      <li>سرور را نصب کنید: <code style="background:#0f0f17;padding:2px 8px;border-radius:6px;color:#f87171;">Setup-HotelMedia.exe</code> را اجرا کنید</li>
      <li>از <strong style="color:#fff;">Admin → Screens → Add Screen</strong> صفحه جدید بسازید</li>
      <li>پلیر را به آدرس <code style="background:#0f0f17;padding:2px 8px;border-radius:6px;color:#f87171;">http://server/player/</code> ببرید</li>
      <li>کد فعال‌سازی را در Admin دریافت و در پلیر وارد کنید</li>
      <li>رسانه آپلود کنید ← پلی‌لیست بسازید ← به صفحه assign کنید</li>
    </ol>
  </div>
</section>

<!-- ─── صفحه‌نمایش ──────────────────────────────── -->
<section id="screens" style="margin-bottom:32px;">
  <h2 style="font-size:16px;font-weight:800;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
    <i class="fas fa-tv" style="color:#1a7ac4;"></i> مدیریت صفحه‌نمایش
  </h2>
  <div class="card" style="padding:20px;">
    <p style="font-size:13px;color:#94a3b8;line-height:1.9;margin-bottom:12px;">
      هر صفحه‌نمایش با یک <strong style="color:#fff;">کد یکتا (SCRXXXXXX)</strong> شناسایی می‌شود.
      پلیر با cookie به صفحه متصل می‌شود و بعد از یک‌بار اتصال، دیگر نیاز به وارد کردن کد نیست.
    </p>
    <div style="background:#0f0f17;border-radius:10px;padding:14px;font-size:12px;color:#64748b;">
      <div style="margin-bottom:6px;color:#94a3b8;font-weight:600;">آدرس‌های پلیر:</div>
      <div><code style="color:#f87171;">/player/</code> ← اگه cookie دارد: پلیر مستقیم / اگه نه: صفحه pairing</div>
      <div style="margin-top:4px;"><code style="color:#f87171;">/player/SCRXXXXXX</code> ← اتصال مستقیم با کد</div>
    </div>
  </div>
</section>

<!-- ─── IPTV ──────────────────────────────────── -->
<section id="iptv" style="margin-bottom:32px;">
  <h2 style="font-size:16px;font-weight:800;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
    <i class="fas fa-satellite-dish" style="color:#ef4444;"></i> IPTV کانال‌ها
  </h2>
  <div class="card" style="padding:20px;">
    <p style="font-size:13px;color:#94a3b8;line-height:1.9;margin-bottom:16px;">
      کانال‌های IPTV را به سه روش می‌توانید اضافه کنید:
    </p>
    <div style="display:flex;flex-direction:column;gap:10px;">
      <?php
      $methods = [
        ['fa-plus','#22c55e','افزودن دستی','آدرس استریم را مستقیماً وارد کنید (HLS، RTSP، HTTP)'],
        ['fa-file-import','#f59e0b','ایمپورت فایل M3U','فایل .m3u یا .m3u8 آپلود کنید — تا ۵۰۰ کانال'],
        ['fa-broadcast-tower','#ef4444','TVHeadend (پیشنهادی)','کانال‌ها را از سرور TVHeadend به‌صورت خودکار وارد کنید'],
      ];
      foreach ($methods as [$ico,$col,$title,$desc]):
      ?>
      <div style="display:flex;align-items:flex-start;gap:12px;padding:12px;background:#0f0f17;border-radius:10px;">
        <i class="fas <?=$ico?>" style="color:<?=$col?>;font-size:16px;margin-top:2px;width:16px;text-align:center;flex-shrink:0;"></i>
        <div>
          <div style="font-size:13px;font-weight:600;color:#fff;"><?=$title?></div>
          <div style="font-size:12px;color:#64748b;margin-top:3px;"><?=$desc?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="margin-top:16px;padding:12px;background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.15);border-radius:10px;font-size:12px;color:#94a3b8;line-height:1.8;">
      <strong style="color:#f87171;">پروتکل‌های پشتیبانی‌شده:</strong><br>
      • <code style="color:#fff;">HLS (.m3u8)</code> — بهترین سازگاری با مرورگر<br>
      • <code style="color:#fff;">HTTP (ts, mp4)</code> — استریم مستقیم<br>
      • <code style="color:#fff;">RTSP / RTMP</code> — نیاز به Transcoder دارد (FFmpeg)
    </div>
  </div>
</section>

<!-- ─── TVHeadend ─────────────────────────────── -->
<section id="tvheadend" style="margin-bottom:32px;">
  <h2 style="font-size:16px;font-weight:800;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
    <i class="fas fa-broadcast-tower" style="color:#ef4444;"></i> TVHeadend — راهنمای کامل
  </h2>

  <!-- معرفی -->
  <div class="card" style="padding:20px;margin-bottom:14px;">
    <h3 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:10px;">TVHeadend چیست؟</h3>
    <p style="font-size:13px;color:#94a3b8;line-height:1.9;">
      <a href="https://github.com/tvheadend/tvheadend" target="_blank" style="color:#f87171;">TVHeadend</a>
      یک سرور متن‌باز پخش زنده تلویزیون است که از طریق
      <strong style="color:#fff;">DVB-T</strong> (آنتن زمینی)،
      <strong style="color:#fff;">DVB-S</strong> (ماهواره)،
      <strong style="color:#fff;">DVB-C</strong> (کابل)،
      <strong style="color:#fff;">IPTV</strong> و
      <strong style="color:#fff;">HDHomeRun</strong>
      کانال‌ها را دریافت و از طریق شبکه پخش می‌کند.
    </p>
  </div>

  <!-- نصب TVHeadend -->
  <div class="card" style="padding:20px;margin-bottom:14px;">
    <h3 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:12px;">
      <i class="fas fa-broadcast-tower" style="color:#06b6d4;margin-left:6px;"></i>نصب TVHeadend
    </h3>
    <div style="font-size:12px;color:#94a3b8;margin-bottom:10px;">
      TVHeadend یک سرویس جداگانه است که کانال‌های Live TV را تأمین می‌کند.
      آن را روی همان سرور یا یک سرور دیگر نصب کنید:
    </div>
    <pre style="background:#0a0a12;border-radius:10px;padding:16px;font-size:12px;color:#a3e635;overflow-x:auto;direction:ltr;text-align:left;line-height:1.8;"><code># Windows — نصب‌کننده رسمی
https://tvheadend.org/projects/tvheadend/wiki/Windows

# Debian / Ubuntu
sudo apt install tvheadend

# Fedora / RHEL
sudo dnf install tvheadend</code></pre>
    <div style="margin-top:10px;font-size:12px;color:#64748b;">
      پس از نصب، Web UI تی‌وی‌هدند روی پورت
      <code style="background:#0f0f17;padding:2px 6px;border-radius:4px;color:#f87171;">9981</code>
      در دسترس است.
    </div>
  </div>
  <!-- تنظیم TVHeadend -->
  <div class="card" style="padding:20px;margin-bottom:14px;">
    <h3 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:12px;">
      <i class="fas fa-cog" style="color:#f59e0b;margin-left:6px;"></i>تنظیمات TVHeadend برای Hotel Media
    </h3>
    <ol style="padding-right:20px;color:#94a3b8;font-size:13px;line-height:2.3;">
      <li>وارد Web UI شوید: <code style="background:#0f0f17;padding:2px 6px;border-radius:4px;color:#f87171;">http://tvh-server:9981</code></li>
      <li>از منوی <strong style="color:#fff;">Configuration → Users → Access Entries</strong> یک کاربر برای Hotel Media بسازید</li>
      <li>دسترسی <strong style="color:#fff;">Web Interface</strong> و <strong style="color:#fff;">Stream</strong> را فعال کنید</li>
      <li>
        پروفایل استریم را تنظیم کنید:<br>
        <span style="font-size:12px;color:#64748b;">
          <code style="color:#fff;">pass</code> — بدون تبدیل (کمترین CPU، پیشنهادی)<br>
          <code style="color:#fff;">hls</code> — تبدیل به HLS (سازگاری بیشتر با مرورگر)
        </span>
      </li>
    </ol>
  </div>

  <!-- اتصال به Hotel Media -->
  <div class="card" style="padding:20px;margin-bottom:14px;">
    <h3 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:12px;">
      <i class="fas fa-link" style="color:#22c55e;margin-left:6px;"></i>اتصال به Hotel Media
    </h3>
    <ol style="padding-right:20px;color:#94a3b8;font-size:13px;line-height:2.3;">
      <li>به <a href="/admin/iptv/tvheadend" style="color:#f87171;">Admin → IPTV → TVHeadend</a> بروید</li>
      <li>روی <strong style="color:#fff;">«افزودن سرور»</strong> کلیک کنید</li>
      <li>آدرس سرور را وارد کنید: <code style="background:#0f0f17;padding:2px 6px;border-radius:4px;color:#f87171;">http://192.168.x.x:9981</code></li>
      <li>نام کاربری و رمز عبور را وارد کنید (اگه تنظیم کردید)</li>
      <li>روی <strong style="color:#22c55e;">«تست اتصال»</strong> کلیک کنید تا اتصال تأیید شود</li>
      <li>روی <strong style="color:#22c55e;">«سینک کانال‌ها»</strong> کلیک کنید — کانال‌ها وارد IPTV می‌شوند</li>
    </ol>
  </div>

  <!-- API های TVHeadend -->
  <div class="card" style="padding:20px;">
    <h3 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:12px;">
      <i class="fas fa-code" style="color:#94a3b8;margin-left:6px;"></i>API های مورد استفاده
    </h3>
    <div style="display:flex;flex-direction:column;gap:8px;">
      <?php
      $apis = [
        ['GET', '/api/serverinfo',   'اطلاعات نسخه و نام سرور (تست اتصال)'],
        ['GET', '/api/channel/list', 'لیست کانال‌ها با UUID، نام، آیکون، شماره'],
        ['GET', '/playlist',         'پلی‌لیست M3U همه کانال‌ها'],
        ['GET', '/stream/channel/{uuid}?profile=pass', 'استریم مستقیم کانال'],
      ];
      foreach ($apis as [$m,$path,$desc]):
      ?>
      <div style="display:flex;align-items:flex-start;gap:10px;background:#0f0f17;border-radius:8px;padding:10px 12px;">
        <span style="font-size:10px;font-weight:700;color:#22c55e;background:rgba(34,197,94,.1);padding:2px 7px;border-radius:5px;flex-shrink:0;margin-top:1px;"><?=$m?></span>
        <div>
          <code style="font-size:12px;color:#f87171;"><?=$path?></code>
          <div style="font-size:11px;color:#64748b;margin-top:2px;"><?=$desc?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ─── VOD ───────────────────────────────────── -->
<section id="vod" style="margin-bottom:32px;">
  <h2 style="font-size:16px;font-weight:800;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
    <i class="fas fa-film" style="color:#f59e0b;"></i> VOD — ویدیو آنلاین
  </h2>
  <div class="card" style="padding:20px;">
    <p style="font-size:13px;color:#94a3b8;line-height:1.9;">
      بخش VOD برای آپلود و نمایش ویدیوهای از پیش ضبط‌شده است.
      ویدیوها هنگام آپلود به‌صورت خودکار به فرمت <strong style="color:#fff;">MPEG-TS</strong> تبدیل می‌شوند.
    </p>
    <div style="margin-top:14px;display:grid;grid-template-columns:1fr 1fr;gap:10px;">
      <div style="background:#0f0f17;border-radius:10px;padding:12px;">
        <div style="font-size:11px;color:#475569;margin-bottom:4px;">حداکثر حجم فایل</div>
        <div style="font-size:20px;font-weight:900;color:#f59e0b;">۵ گیگابایت</div>
      </div>
      <div style="background:#0f0f17;border-radius:10px;padding:12px;">
        <div style="font-size:11px;color:#475569;margin-bottom:4px;">فرمت خروجی</div>
        <div style="font-size:20px;font-weight:900;color:#f59e0b;">MPEG-TS</div>
      </div>
    </div>
  </div>
</section>

<!-- ─── نیازمندی‌های سرور ─────────────────────── -->
<section id="server-req" style="margin-bottom:32px;">
  <h2 style="font-size:16px;font-weight:800;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
    <i class="fas fa-server" style="color:#64748b;"></i> نیازمندی‌های سرور
  </h2>
  <div class="card" style="padding:20px;">
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead>
          <tr style="border-bottom:1px solid rgba(255,255,255,.08);">
            <th style="text-align:right;padding:8px 12px;color:#64748b;font-weight:600;">تعداد صفحه</th>
            <th style="text-align:right;padding:8px 12px;color:#64748b;font-weight:600;">CPU</th>
            <th style="text-align:right;padding:8px 12px;color:#64748b;font-weight:600;">RAM</th>
            <th style="text-align:right;padding:8px 12px;color:#64748b;font-weight:600;">SSD</th>
            <th style="text-align:right;padding:8px 12px;color:#64748b;font-weight:600;">اینترنت (Upload)</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $rows = [
            ['۵ صفحه (حداقل)',    '2 هسته', '2 GB', '40 GB',  '30 Mbps',  false],
            ['۵ صفحه (پیشنهادی)', '2 هسته', '4 GB', '100 GB', '50 Mbps',  true],
            ['۲۰ صفحه',           '4 هسته', '6 GB', '200 GB', '100 Mbps', false],
            ['۵۰ صفحه',           '8 هسته', '12 GB','500 GB', '250 Mbps', false],
          ];
          foreach ($rows as [$n,$c,$r,$s,$b,$hi]):
          ?>
          <tr style="border-bottom:1px solid rgba(255,255,255,.04);<?= $hi?'background:rgba(34,197,94,.04);':'' ?>">
            <td style="padding:9px 12px;color:#fff;font-weight:<?=$hi?'700':'400'?>"><?=$n?></td>
            <td style="padding:9px 12px;color:#94a3b8;"><?=$c?></td>
            <td style="padding:9px 12px;color:#94a3b8;"><?=$r?></td>
            <td style="padding:9px 12px;color:#94a3b8;"><?=$s?></td>
            <td style="padding:9px 12px;color:#94a3b8;"><?=$b?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="margin-top:14px;padding:12px;background:rgba(245,158,11,.05);border:1px solid rgba(245,158,11,.15);border-radius:10px;font-size:12px;color:#94a3b8;">
      <i class="fas fa-exclamation-triangle" style="color:#f59e0b;margin-left:6px;"></i>
      <strong style="color:#f59e0b;">مهم:</strong> پهنای باند مهم‌ترین فاکتور است.
      هر کلاینت ویدیو ۱۰۸۰p ≈ ۵ Mbps مصرف می‌کند.
      اگر کلاینت‌ها ویدیو را کش کنند، مصرف به‌شدت کاهش می‌یابد.
    </div>
  </div>
</section>

<!-- ─── WebSocket ──────────────────────────────── -->
<section id="websocket" style="margin-bottom:32px;">
  <h2 style="font-size:16px;font-weight:800;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
    <i class="fas fa-plug" style="color:#22c55e;"></i> WebSocket — real-time
  </h2>
  <div class="card" style="padding:20px;">
    <p style="font-size:13px;color:#94a3b8;line-height:1.9;margin-bottom:14px;">
      سرور WebSocket روی پورت <code style="background:#0f0f17;padding:2px 6px;border-radius:4px;color:#f87171;">8080</code> اجرا می‌شود.
      پلیرها از طریق WebSocket دستورات آنی دریافت می‌کنند.
    </p>
    <div style="background:#0f0f17;border-radius:10px;padding:14px;font-size:12px;">
      <div style="color:#94a3b8;font-weight:600;margin-bottom:8px;">قابلیت‌ها:</div>
      <div style="display:flex;flex-direction:column;gap:6px;color:#64748b;">
        <div><i class="fas fa-check" style="color:#22c55e;margin-left:8px;"></i>تغییر فوری محتوا بدون refresh</div>
        <div><i class="fas fa-check" style="color:#22c55e;margin-left:8px;"></i>پیام‌های اضطراری (Emergency Broadcast)</div>
        <div><i class="fas fa-check" style="color:#22c55e;margin-left:8px;"></i>وضعیت real-time صفحه‌ها در Dashboard</div>
        <div><i class="fas fa-check" style="color:#22c55e;margin-left:8px;"></i>آخرین heartbeat و IP پلیر</div>
      </div>
    </div>
  </div>
</section>

<!-- ─── API ──────────────────────────────────────── -->
<section id="api" style="margin-bottom:32px;">
  <h2 style="font-size:16px;font-weight:800;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
    <i class="fas fa-code" style="color:#94a3b8;"></i> REST API
  </h2>
  <div class="card" style="padding:20px;">
    <p style="font-size:13px;color:#94a3b8;line-height:1.9;margin-bottom:14px;">
      Base URL: <code style="background:#0f0f17;padding:2px 8px;border-radius:6px;color:#f87171;">http://server/api/v1</code><br>
      احراز هویت: <code style="background:#0f0f17;padding:2px 8px;border-radius:6px;color:#f87171;">Authorization: Bearer &lt;JWT&gt;</code>
    </p>
    <pre style="background:#0a0a12;border-radius:10px;padding:16px;font-size:11px;color:#a3e635;overflow-x:auto;direction:ltr;text-align:left;line-height:1.9;"><code>POST /api/v1/auth/login          # ورود
GET  /api/v1/screens             # لیست صفحه‌ها
GET  /api/v1/screens/{code}/playlist  # پلی‌لیست فعال
POST /api/v1/screens/{code}/heartbeat # heartbeat پلیر
GET  /api/v1/iptv/channels       # کانال‌های IPTV
GET  /api/v1/fids/live           # اطلاعات پروازی</code></pre>
    <div style="margin-top:10px;">
      <a href="/docs/API.md" style="font-size:12px;color:#f87171;text-decoration:none;">
        <i class="fas fa-file-alt" style="margin-left:4px;"></i>مستندات کامل API ←
      </a>
    </div>
  </div>
</section>

</div><!-- /max-width -->
