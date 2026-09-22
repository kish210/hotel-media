<?php
/**
 * Hotel Media — پخش‌کننده‌ی تابلوی دیجیتال (محیط عمومی هتل)
 *
 * لابی، رستوران، سالن همایش، راهرو، آسانسور. روی همان تلویزیون‌های
 * هتلی LG/Samsung اجرا می‌شود که پورتال اتاق، پس همان محدودیت:
 *   Samsung Tizen 2.3 → Chromium 34     LG webOS 3 → Chromium 38
 *
 * همه‌ی JS اینجا ES5 است و ظاهر از tv-base.css و tv-signage.css می‌آید.
 * بازرس: node tests/Support/tv-compat-lint.js
 *
 * چرخش پلی‌لیست + تابلوهای پویا (رویداد، منو، اخبار، شماره‌های داخلی،
 * معرفی سالن، نوار اطلاعات) + پخش فوری + لوگو + تیکر + ساعت + زیرنویس.
 */
$settings      = json_decode($screen['settings'] ?? '{}', true) ?: [];
$screenCode    = (string)($screen['code'] ?? '');
$isActive      = ($screen['status'] ?? '') === 'active';

$logoUrl       = (string)($settings['logo_url'] ?? '');
$logoPos       = (string)($settings['logo_position'] ?? 'bottom-right');
$logoOpacity   = (float)($settings['logo_opacity'] ?? 0.8);
$logoSize      = (int)($settings['logo_size'] ?? 120);

$tickerText    = (string)($settings['ticker_text'] ?? '');
$tickerEnabled = $tickerText !== '';
$tickerSpeed   = max(5, (int)($settings['ticker_speed'] ?? 40));
$tickerBg      = (string)($settings['ticker_bg'] ?? 'rgba(0,0,0,0.72)');
$tickerColor   = (string)($settings['ticker_color'] ?? '#ffffff');

$clockEnabled  = !empty($settings['show_clock']);
$clockPos      = (string)($settings['clock_position'] ?? 'top-right');
$clockFmt      = (string)($settings['clock_format'] ?? '24h');

/* مکان‌ها با کلاس داده می‌شوند نه CSS درون‌خطی، تا مقدار ورودی کاربر
   مستقیم داخل بلوک style نرود. */
$posClasses  = ['top-left','top-right','bottom-left','bottom-right','center'];
$logoPos     = in_array($logoPos, $posClasses, true) ? $logoPos : 'bottom-right';
$clockPos    = in_array($clockPos, $posClasses, true) ? $clockPos : 'top-right';

/* مدت یک دور کامل تیکر. متن بلندتر ⇒ زمان بیشتر، وگرنه تند رد می‌شود. */
$tickerSecs  = max(15, (int)(mb_strlen($tickerText) * (100 / $tickerSpeed)));

/* تاریخ شمسی از سرور — مرورگر تلویزیون داده‌ی Intl برای fa-IR ندارد و
   toLocaleDateString('fa-IR') آنجا تاریخ میلادی می‌دهد. */
$todayJalali = function_exists('jalaliDate') ? jalaliDate() : '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تابلو — <?= e($screen['name'] ?? 'Signage') ?></title>

<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css">
<link rel="stylesheet" href="/assets/css/tv-base.css">
<link rel="stylesheet" href="/assets/css/tv-signage.css">
<script src="/assets/vendor/hls/hls.min.js"></script>
<script src="/assets/js/tv-base.js"></script>
<style>
/* فقط چیزهای مخصوص این صفحه. بدون var() ، clamp() ، gap ، inset. */
html, body { background: #000; }

#stage { position: relative; width: 100%; height: 100%; background: #000; }
#media { position: relative; width: 100%; height: 100%; overflow: hidden; }

.item {
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  background: #000;
  opacity: 0;
  -webkit-transition: opacity .6s ease;
  transition: opacity .6s ease;
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
  -webkit-box-pack: center; -webkit-justify-content: center;
  -ms-flex-pack: center; justify-content: center;
}
.item.is-on { opacity: 1; z-index: 2; }
/* cover برای عکس و ویدیوی تبلیغاتی درست است: تابلو باید پر شود. */
.item img, .item video { width: 100%; height: 100%; -o-object-fit: cover; object-fit: cover; }
.item iframe { width: 100%; height: 100%; border: 0; }

.live-tag {
  position: absolute;
  top: .8rem; left: .8rem;
  z-index: 3;
  padding: .22rem .7rem;
  border-radius: .4rem;
  background: rgba(255, 95, 87, .92);
  font-size: .8rem; font-weight: 700; letter-spacing: .08em;
}
.live-tag.is-warn { background: rgba(245, 177, 61, .92); color: #201800; }

/* ── لوگو ── */
#logo-ov { position: absolute; z-index: 20; }
#logo-ov img { width: <?= $logoSize ?>px; max-width: <?= $logoSize ?>px; -o-object-fit: contain; object-fit: contain; display: block; }

/* ── ساعت ── */
#clock-ov {
  position: absolute; z-index: 20;
  padding: .5rem 1.05rem;
  border-radius: .65rem;
  background: rgba(0, 0, 0, .52);
  text-align: center;
  min-width: 7rem;
}
#clock-time { font-size: 1.9rem; font-weight: 800; font-family: monospace; letter-spacing: -.02em; }
#clock-date { font-size: .82rem; font-weight: 400; color: #a8b4c6; margin-top: .15rem; }

/* مکان‌های مشترک لوگو و ساعت */
.at-top-left     { top: 1rem;    left: 1rem; }
.at-top-right    { top: 1rem;    right: 1rem; }
.at-bottom-left  { bottom: 3.6rem; left: 1rem; }
.at-bottom-right { bottom: 3.6rem; right: 1rem; }
.at-center       { top: 42%;     left: 42%; }

/* ── تیکر ── */
#ticker {
  position: absolute;
  right: 0; bottom: 0; left: 0;
  z-index: 25;
  height: 2.7rem;
  overflow: hidden;
  background: <?= e($tickerBg) ?>;
  display: none;
}
#ticker.is-on {
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
}
#ticker-inner {
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  white-space: nowrap;
  -webkit-animation: tickerScroll <?= $tickerSecs ?>s linear infinite;
  animation: tickerScroll <?= $tickerSecs ?>s linear infinite;
}
.ticker-text { font-size: 1.15rem; font-weight: 600; color: <?= e($tickerColor) ?>; padding: 0 4rem; }
.ticker-sep  { color: rgba(255,255,255,.3); padding: 0 1.2rem; font-size: 1.25rem; }
@-webkit-keyframes tickerScroll { 0% { -webkit-transform: translateX(-50%); } 100% { -webkit-transform: translateX(0); } }
@keyframes tickerScroll { 0% { -webkit-transform: translateX(-50%); transform: translateX(-50%); } 100% { -webkit-transform: translateX(0); transform: translateX(0); } }

/* ── زیرنویس ── */
#subtitle {
  position: absolute;
  bottom: <?= $tickerEnabled ? '3.4rem' : '1.4rem' ?>;
  left: 10%; right: 10%;
  z-index: 22;
  text-align: center;
}
#subtitle-text {
  display: none;
  padding: .5rem 1.2rem;
  border-radius: .55rem;
  background: rgba(0, 0, 0, .78);
  font-size: 1.4rem; font-weight: 600; line-height: 1.5;
}
#subtitle-text.is-on { display: inline-block; }

/* ── پخش فوری ── */
#instant {
  position: fixed;
  top: 0; right: 0; bottom: 0; left: 0;
  z-index: 9999;
  background: #000;
  display: none;
}
#instant.is-on {
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
  -webkit-box-pack: center; -webkit-justify-content: center;
  -ms-flex-pack: center; justify-content: center;
}
#instant-body { width: 100%; height: 100%;
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
  -webkit-box-pack: center; -webkit-justify-content: center;
  -ms-flex-pack: center; justify-content: center;
}
#instant-body img, #instant-body video { max-width: 100%; max-height: 100%; -o-object-fit: contain; object-fit: contain; }
#instant-body iframe { width: 100%; height: 100%; border: 0; }
.instant-text { max-width: 82%; text-align: center; font-size: 4.2rem; font-weight: 800; line-height: 1.3; }

/* ── قطع ارتباط ── */
#offline {
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  z-index: 50;
  background: #000;
  display: none;
}
#offline.is-on {
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-orient: vertical; -webkit-box-direction: normal;
  -webkit-flex-direction: column; -ms-flex-direction: column; flex-direction: column;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
  -webkit-box-pack: center; -webkit-justify-content: center;
  -ms-flex-pack: center; justify-content: center;
}

/* ── فعال‌سازی ── */
#activation {
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  z-index: 100;
  background: #080b11;
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
  -webkit-box-pack: center; -webkit-justify-content: center;
  -ms-flex-pack: center; justify-content: center;
}
#act-card {
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 1.4rem;
  padding: 2.8rem 2.4rem;
  width: 23rem; max-width: 90%;
  text-align: center;
}
#act-logo { height: 3.2rem; width: auto; margin: 0 auto 1.3rem; display: block; }
#act-title { font-size: 1.35rem; font-weight: 800; margin-bottom: .35rem; }
#act-sub { font-size: .9rem; font-weight: 400; color: #a8b4c6; margin-bottom: 1.3rem; }
#act-screen { font-size: .85rem; font-weight: 400; color: #8c99ad; margin-bottom: .8rem; }
#act-screen b { color: #7bb8e8; font-family: monospace; letter-spacing: .08em; }
#act-code {
  width: 100%; padding: .95rem;
  border-radius: .75rem;
  background: rgba(0,0,0,.45);
  border: 2px solid rgba(26,122,196,.35);
  color: #fff; font-family: monospace;
  font-size: 1.6rem; letter-spacing: .45rem;
  text-align: center; text-transform: uppercase; outline: 0;
}
#act-code:focus { border-color: #4098db; }
#act-btn { width: 100%; margin-top: 1rem;
  -webkit-box-pack: center; -webkit-justify-content: center;
  -ms-flex-pack: center; justify-content: center; }
#act-err { font-size: .88rem; margin-top: .85rem; min-height: 1.3rem; color: #ff5f57; }
</style>
</head>
<body>

<div id="stage">

<?php if (!$isActive): ?>
  <div id="activation">
    <div id="act-card">
      <img id="act-logo" src="/assets/img/sama-logo.svg" alt="">
      <div id="act-title">Hotel Media</div>
      <div id="act-sub">این تابلو هنوز فعال نشده است</div>
      <div id="act-screen">کد صفحه: <b><?= e($screenCode ?: '—') ?></b></div>
      <input type="text" id="act-code" maxlength="6" placeholder="------"
             autocomplete="off" autocorrect="off" spellcheck="false">
      <button type="button" class="tv-btn" id="act-btn">فعال‌سازی</button>
      <div id="act-err"></div>
    </div>
  </div>

<?php else: ?>
  <div id="media">
    <div class="item is-on" id="boot">
      <div class="sg-empty">
        <div class="tv-spinner"></div>
        <div class="sg-empty-text">در حال بارگذاری محتوا…</div>
      </div>
    </div>
  </div>

  <div id="logo-ov" class="at-<?= e($logoPos) ?>"
       style="opacity:<?= $logoOpacity ?>;display:<?= $logoUrl ? 'block' : 'none' ?>">
    <?php if ($logoUrl): ?>
      <img src="<?= e($logoUrl) ?>" alt="" onerror="this.parentNode.style.display='none'">
    <?php endif; ?>
  </div>

  <div id="clock-ov" class="at-<?= e($clockPos) ?>"
       style="display:<?= $clockEnabled ? 'block' : 'none' ?>">
    <div id="clock-time">--:--</div>
    <div id="clock-date"><?= e($todayJalali) ?></div>
  </div>

  <div id="ticker" class="<?= $tickerEnabled ? 'is-on' : '' ?>">
    <div id="ticker-inner">
      <?php if ($tickerEnabled): for ($i = 0; $i < 2; $i++): ?>
        <span class="ticker-text"><?= e($tickerText) ?></span><span class="ticker-sep">◆</span>
      <?php endfor; endif; ?>
    </div>
  </div>

  <div id="subtitle"><span id="subtitle-text"></span></div>

  <div id="offline">
    <div class="sg-empty-title" style="color:#ff5f57">ارتباط با سرور قطع شد</div>
    <div class="sg-empty-text">در حال تلاش دوباره…</div>
  </div>
<?php endif; ?>

  <div id="instant"><div id="instant-body"></div></div>

  <!-- پیام‌های زمان‌بندی‌شده (خوش‌آمد، تبریک، اطلاعیه، هشدار).
       این قابلیت فقط در player/index.php بود و در پروفایل تابلو
       وجود نداشت، یعنی روی تابلوهای واقعی هیچ‌وقت نشان داده نمی‌شد. -->
  <div id="msg-ov"></div>

</div><!-- /stage -->

<script>
/* ES5 خالص — دلیلش بالای فایل. بازرس: tests/Support/tv-compat-lint.js */
(function () {
  'use strict';

  var SCREEN_CODE = '<?= e($screenCode) ?>';
  var WS_PORT     = <?= (int)env('WS_PORT', 8080) ?>;
  var ORIGIN      = window.location.protocol + '//' + window.location.host;

  TV.boot();

<?php if (!$isActive): ?>
  /* ── فعال‌سازی ─────────────────────────────────────────────────── */
  var codeEl = TV.id('act-code');
  var errEl  = TV.id('act-err');
  var busy   = false;

  function activate() {
    if (busy) return;
    var code = (codeEl.value || '').replace(/^\s+|\s+$/g, '').toUpperCase();
    if (code.length !== 6) {
      errEl.style.color = '#ff5f57';
      TV.text(errEl, 'کد باید ۶ نویسه باشد');
      return;
    }
    busy = true;
    errEl.style.color = '#a8b4c6';
    TV.text(errEl, 'در حال فعال‌سازی…');

    TV.post(ORIGIN + '/player/activate',
      { activation_code: code, screen_code: SCREEN_CODE },
      function (err, d) {
        busy = false;
        if (!err && d && d.success) {
          errEl.style.color = '#32d17a';
          TV.text(errEl, 'فعال شد — در حال بازآوری…');
          setTimeout(function () { location.reload(); }, 1200);
          return;
        }
        errEl.style.color = '#ff5f57';
        TV.text(errEl, (d && d.message) ? d.message : 'کد نامعتبر است یا سرور در دسترس نیست');
      });
  }

  TV.on(TV.id('act-btn'), 'click', activate);
  TV.on(codeEl, 'input', function () { codeEl.value = codeEl.value.toUpperCase(); });
  TV.on(document, 'keydown', function (e) { if (TV.keyName(e) === 'OK') activate(); });
  if (codeEl.focus) codeEl.focus();

<?php else: ?>
  /* ── وضعیت ─────────────────────────────────────────────────────── */
  var CLOCK_ON  = <?= $clockEnabled ? 'true' : 'false' ?>;
  var CLOCK_24  = <?= $clockFmt === '12h' ? 'false' : 'true' ?>;

  var playlist  = [];
  var curIdx    = 0;
  var playTimer = null;
  var hlsList   = [];       /* نمونه‌های فعال HLS، برای پاک‌کردن */

  /* ── ساعت ───────────────────────────────────────────────────────
     زمان از سرور تنظیم می‌شود چون ساعت خود تلویزیون بعد از قطع برق
     معمولا عقب است و تابلوی لابی ساعت غلط نشان می‌داد. */
  var timeSkew = 0;

  function syncTime() {
    TV.get(ORIGIN + '/api/v1/time', function (err, d) {
      if (err || !d || !d.timestamp) return;
      timeSkew = (d.timestamp * 1000) - (new Date()).getTime();
      /* تاریخ شمسی هم اگر سرور فرستاد به‌روز شود (گذر از نیمه‌شب) */
      if (d.jalali) TV.text(TV.id('clock-date'), d.jalali);
    });
  }

  function drawClock() {
    var now = new Date((new Date()).getTime() + timeSkew);
    var h = now.getHours(), m = now.getMinutes(), suffix = '';
    if (!CLOCK_24) {
      suffix = h < 12 ? ' AM' : ' PM';
      h = h % 12; if (h === 0) h = 12;
    }
    TV.text(TV.id('clock-time'),
            (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m) + suffix);
  }

  if (CLOCK_ON) {
    syncTime();
    drawClock();
    /* هر ۱۵ ثانیه کافی است — ساعت دقیقه‌ای است و بیدارشدن هر ثانیه
       روی SoC تلویزیون پخش ویدیو را می‌لرزاند. */
    setInterval(drawClock, 15000);
    setInterval(syncTime, 300000);
  }

  /* ── ابزار ─────────────────────────────────────────────────────── */
  function hm(dt) {
    /* «2026-09-22 14:30:00» — بدون Date() چون این قالب روی موتورهای
       قدیمی تلویزیون گاهی NaN می‌دهد. */
    if (!dt) return '';
    var m = String(dt).match(/(\d{2}):(\d{2})/);
    return m ? m[1] + ':' + m[2] : '';
  }

  var esc = TV.esc;

  /* ── تابلوهای پویا ─────────────────────────────────────────────── */
  function renderDynamic(c) {
    if (!c || !c.kind) return '';
    if (c.kind === 'event_board') return evEventBoard(c);
    if (c.kind === 'menu_board')  return evMenuBoard(c);
    if (c.kind === 'news')        return evNews(c);
    if (c.kind === 'directory')   return evDirectory(c);
    if (c.kind === 'venue_info')  return evVenue(c);
    if (c.kind === 'info_bar')    return evInfoBar(c);
    if (c.kind === 'live_tv')     return evLive(c);
    return '';
  }

  function evEventBoard(c) {
    var list = c.events || [], rows = '', i, e, state, cls;

    for (i = 0; i < list.length; i++) {
      e = list[i];

      if (e.is_cancelled)                { state = 'لغو شد';            cls = 'is-cancelled'; }
      else if (e.is_now)                 { state = 'در حال برگزاری';    cls = 'is-now'; }
      else if (e.minutes_away < 60)      { state = e.minutes_away + ' دقیقه دیگر'; cls = 'is-soon'; }
      else                               { state = '';                   cls = ''; }

      rows +=
        '<div class="sg-row' + (e.is_cancelled ? ' is-off' : '') + '">' +
          '<div class="sg-ev-time">' + hm(e.start_at) +
            (e.end_at ? '–' + hm(e.end_at) : '') + '</div>' +
          '<div class="sg-grow">' +
            '<div class="sg-ev-title sg-clip' + (e.is_cancelled ? ' is-cancelled' : '') + '">' +
              esc(e.title) + '</div>' +
            (e.organizer ? '<div class="sg-ev-org sg-clip">' + esc(e.organizer) + '</div>' : '') +
          '</div>' +
          (e.venue_name && !c.venue
            ? '<div class="sg-ev-venue sg-clip">' + esc(e.venue_name) +
              (e.venue_floor ? '<span class="sg-ev-floor">طبقه ' + esc(e.venue_floor) + '</span>' : '') +
              '</div>' : '') +
          (state ? '<div class="sg-ev-state ' + cls + '">' + esc(state) + '</div>' : '') +
        '</div>';
    }

    if (!rows) rows = '<div class="sg-empty-text">برنامه‌ای برای امروز ثبت نشده است</div>';

    return '<div class="sg">' +
             '<div class="sg-head">' +
               '<div class="sg-title">' + esc(c.venue ? c.venue.name : 'برنامه امروز') + '</div>' +
               '<div class="sg-sub">برنامه امروز</div>' +
               '<div class="sg-rule"></div>' +
             '</div>' +
             '<div class="sg-body">' + rows + '</div>' +
           '</div>';
  }

  function evMenuBoard(c) {
    var pages = c.pages || [];
    if (!pages.length) return '';
    /* چند صفحه‌ای: هر ۱۲ ثانیه صفحه عوض می‌شود تا کل منو دیده شود.
       از ساعت مشتق می‌شود نه شمارنده، تا چند تابلوی هم‌زمان هماهنگ
       بمانند بدون اینکه لازم باشد با هم حرف بزنند. */
    var idx = Math.floor((new Date()).getTime() / 12000) % pages.length;
    var p = pages[idx];

    return '<div class="sg-menu">' +
             '<img src="' + esc(p.image_url) + '" alt="' + esc(c.title || '') + '">' +
             (pages.length > 1
               ? '<div class="sg-menu-page">' + (idx + 1) + ' / ' + pages.length + '</div>'
               : '') +
           '</div>';
  }

  function evNews(c) {
    var list = c.items || [], rows = '', i, n;
    for (i = 0; i < list.length; i++) {
      n = list[i];
      rows +=
        '<div class="sg-row">' +
          (n.image_url ? '<img class="sg-news-img" src="' + esc(n.image_url) + '" alt="">' : '') +
          '<div class="sg-grow">' +
            '<div class="sg-news-title">' + esc(n.title) + '</div>' +
            (n.extra ? '<div class="sg-news-meta sg-clip">' + esc(n.extra) + '</div>' : '') +
          '</div>' +
        '</div>';
    }
    if (!rows) rows = '<div class="sg-empty-text">خبری برای نمایش نیست</div>';

    return '<div class="sg">' +
             '<div class="sg-head"><div class="sg-title">اخبار</div><div class="sg-rule"></div></div>' +
             '<div class="sg-body">' + rows + '</div>' +
           '</div>';
  }

  function evDirectory(c) {
    var list = c.items || [], rows = '', i, d;
    for (i = 0; i < list.length; i++) {
      d = list[i];
      rows +=
        '<div class="sg-row">' +
          '<span class="sg-dir-name sg-grow sg-clip">' + esc(d.title) + '</span>' +
          '<span class="sg-dir-ext">' + esc(d.extra) + '</span>' +
        '</div>';
    }
    if (!rows) rows = '<div class="sg-empty-text">شماره‌ای ثبت نشده است</div>';

    return '<div class="sg">' +
             '<div class="sg-head"><div class="sg-title">شماره‌های داخلی</div><div class="sg-rule"></div></div>' +
             '<div class="sg-body">' + rows + '</div>' +
           '</div>';
  }

  function evVenue(c) {
    var openTxt = '', openCls = '';
    if (c.open_now === true)       { openTxt = 'باز است';  openCls = 'is-open'; }
    else if (c.open_now === false) { openTxt = 'بسته است'; openCls = 'is-closed'; }

    return '<div class="sg sg-venue">' +
             '<div class="sg-venue-name">' + esc(c.name) + '</div>' +
             (c.floor ? '<div class="sg-venue-floor">طبقه ' + esc(c.floor) + '</div>' : '') +
             (c.description ? '<div class="sg-venue-desc">' + esc(c.description) + '</div>' : '') +
             ((c.open_from && c.open_to)
               ? '<div class="sg-venue-hours">' +
                   '<span class="sg-venue-time">' + hm(c.open_from) + ' تا ' + hm(c.open_to) + '</span>' +
                   (openTxt ? '<span class="sg-badge ' + openCls + '">' + openTxt + '</span>' : '') +
                 '</div>' : '') +
             (c.now_playing
               ? '<div class="sg-now">' +
                   '<div class="sg-now-label">در حال برگزاری</div>' +
                   '<div class="sg-now-title">' + esc(c.now_playing.title) + '</div>' +
                 '</div>' : '') +
           '</div>';
  }

  function evInfoBar(c) {
    var d = c.data || {}, w = c.widgets || [], cells = '', i, keys, parts, v;

    function has(name) {
      for (var j = 0; j < w.length; j++) if (w[j] === name) return true;
      return false;
    }

    if (has('clock')) {
      var t = new Date((new Date()).getTime() + timeSkew);
      var hh = t.getHours(), mm = t.getMinutes();
      cells += '<div class="sg-info-cell"><div class="sg-info-clock">' +
               (hh < 10 ? '0' + hh : hh) + ':' + (mm < 10 ? '0' + mm : mm) +
               '</div></div>';
    }

    if (d.weather) {
      cells += '<div class="sg-info-cell">' +
                 '<div class="sg-info-temp">' + esc(d.weather.temp) + '°</div>' +
                 '<div class="sg-info-label">' + esc(d.weather.description) + '</div>' +
               '</div>';
    }

    if (d.prayer) {
      cells += '<div class="sg-info-cell"><div class="sg-info-line">' +
                 'اذان صبح ' + esc(d.prayer.fajr) +
                 ' · ظهر ' + esc(d.prayer.dhuhr) +
                 ' · مغرب ' + esc(d.prayer.maghrib) +
               '</div></div>';
    }

    if (d.currency) {
      keys = [];
      for (var k in d.currency) {
        if (d.currency.hasOwnProperty(k)) keys.push(k);
        if (keys.length === 3) break;
      }
      parts = [];
      for (i = 0; i < keys.length; i++) {
        /* toLocaleString('fa-IR') روی تلویزیون داده‌ی Intl ندارد و
           عدد خام یا NaN می‌دهد، پس جداکننده را خودمان می‌گذاریم. */
        v = String(d.currency[keys[i]]).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        parts.push(keys[i].toUpperCase() + ' ' + v);
      }
      cells += '<div class="sg-info-cell"><div class="sg-info-line">' +
               esc(parts.join('  ·  ')) + '</div></div>';
    }

    return '<div class="sg sg-info">' + cells + '</div>';
  }

  function evLive(c) {
    return '<div class="sg sg-live">' +
             (c.logo_url ? '<img src="' + esc(c.logo_url) + '" alt="">' : '') +
             '<div class="sg-live-name">' + esc(c.name) + '</div>' +
             (c.now ? '<div class="sg-live-now">' + esc(c.now.title) + '</div>' : '') +
           '</div>';
  }

  /* ── تشخیص نوع رسانه ───────────────────────────────────────────── */
  function mediaType(item) {
    var src  = item.file_url || item.src || item.file_path || item.url || '';
    var type = item.type || item.media_type || '';
    var mime = item.mime_type || '';

    if (type === 'dynamic') return 'dynamic';
    if (type === 'module')  return 'module';

    if (/\.m3u8(\?|$)/i.test(src) || mime.indexOf('mpegurl') !== -1) return 'hls';
    if (src.indexOf('rtsp://') === 0 || src.indexOf('rtp://') === 0)  return 'rtsp';
    if (src.indexOf('rtmp://') === 0)                                 return 'rtmp';
    if (type === 'video' || /\.(mp4|webm|ogv|m4v|mov)(\?|$)/i.test(src)) return 'video';
    if (type === 'image' || /\.(jpe?g|png|gif|webp|svg|bmp)(\?|$)/i.test(src)) return 'image';
    if (type === 'url' || src.indexOf('http') === 0) return 'webpage';
    return 'image';
  }

  /* ── ساخت آیتم ─────────────────────────────────────────────────── */
  function buildItem(item) {
    var src = item.file_url || item.src || item.url || item.file_path || '';
    var div = document.createElement('div');
    div.className = 'item';

    /* محتوای پویا فایل نیست — سرور داده‌اش را در item.content می‌فرستد
       و اینجا هر بار تازه رندر می‌شود. */
    if (item.type === 'dynamic' && item.content) {
      div.innerHTML = renderDynamic(item.content);
      return div;
    }

    var kind = mediaType(item);

    if (kind === 'image') {
      var img = document.createElement('img');
      img.onerror = next;
      img.src = src;
      div.appendChild(img);

    } else if (kind === 'video') {
      var v = mkVideo(false);
      v.src = src;
      v.onended = next;
      v.onerror = next;
      div.appendChild(v);

    } else if (kind === 'hls') {
      attachHls(div, src, '● زنده');

    } else if (kind === 'rtsp' || kind === 'rtmp') {
      /* مرورگر RTSP/RTMP را مستقیم باز نمی‌کند؛ سرور آن را به HLS
         تبدیل می‌کند. اگر relay بالا نبود، پیام روشن بده. */
      attachHls(div,
        ORIGIN + '/api/v1/stream/proxy?format=hls&url=' + encodeURIComponent(src),
        '● ' + (kind === 'rtsp' ? 'RTSP' : 'RTMP'));

    } else if (kind === 'module' || kind === 'webpage') {
      var f = document.createElement('iframe');
      f.setAttribute('allow', 'autoplay; fullscreen');
      if (kind === 'webpage') {
        f.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms allow-popups');
      }
      f.src = src;
      div.appendChild(f);

    } else {
      var im2 = document.createElement('img');
      im2.onerror = next;
      im2.src = src;
      div.appendChild(im2);
    }

    return div;
  }

  function mkVideo(loop) {
    var v = document.createElement('video');
    v.autoplay = true;
    v.muted    = true;    /* بدون این، پخش خودکار روی webOS رد می‌شود */
    v.loop     = !!loop;
    v.preload  = 'auto';
    v.setAttribute('playsinline', 'playsinline');
    v.setAttribute('webkit-playsinline', 'webkit-playsinline');
    return v;
  }

  function attachHls(div, src, label) {
    var v = mkVideo(true);
    div.appendChild(v);

    var tag = document.createElement('div');
    tag.className = 'live-tag';
    tag.appendChild(document.createTextNode(label));
    div.appendChild(tag);

    /* HLS بومی مقدم است: روی SoC تلویزیون خیلی سبک‌تر از hls.js است */
    if (v.canPlayType && v.canPlayType('application/vnd.apple.mpegurl')) {
      v.src = src;
      v.onerror = function () { markWarn(tag); };
      return;
    }
    if (typeof Hls !== 'undefined' && Hls.isSupported()) {
      var h = new Hls({ enableWorker: false });   /* worker روی تلویزیون ناپایدار است */
      h.loadSource(src);
      h.attachMedia(v);
      h.on(Hls.Events.ERROR, function (evt, data) {
        if (data && data.fatal) { markWarn(tag); next(); }
      });
      hlsList.push(h);
      return;
    }
    markWarn(tag);
    next();
  }

  function markWarn(tag) {
    TV.addClass(tag, 'is-warn');
    TV.text(tag, '⚠ جریان در دسترس نیست');
  }

  function clearHls() {
    var i;
    for (i = 0; i < hlsList.length; i++) {
      try { hlsList[i].destroy(); } catch (e) {}
    }
    hlsList = [];
  }

  /* ── چرخش پلی‌لیست ─────────────────────────────────────────────── */
  function loadPlaylist() {
    TV.get(ORIGIN + '/api/v1/screens/' + SCREEN_CODE + '/playlist', function (err, d) {
      if (err) { showOffline(); return; }
      if (!d || !d.success || !d.data || !d.data.items || !d.data.items.length) {
        showEmpty();
        return;
      }
      TV.removeClass(TV.id('offline'), 'is-on');
      playlist = d.data.items;
      curIdx = 0;
      playAt(0);
    });
  }

  function playAt(idx) {
    if (!playlist.length) return;
    clearTimeout(playTimer);
    clearHls();

    var item = playlist[idx];
    var host = TV.id('media');
    var kind = mediaType(item);
    var secs = Number(item.duration);
    if (!secs || isNaN(secs) || secs < 1) secs = 10;

    /* آیتم‌های قبلی پاک می‌شوند. بدون این، هر دور یک المان ویدیو
       اضافه می‌ماند و بعد از چند ساعت حافظه‌ی تلویزیون پر می‌شود. */
    var old = TV.all('.item', host), i;
    for (i = 0; i < old.length; i++) {
      if (old[i].parentNode) old[i].parentNode.removeChild(old[i]);
    }

    var el = buildItem(item);
    host.appendChild(el);
    /* یک تیک صبر تا ترنزیشن opacity واقعا اجرا شود */
    setTimeout(function () { TV.addClass(el, 'is-on'); }, 30);

    showCaption(item.subtitle_text || item.caption || '', 6000);

    if (kind === 'video') {
      /* ویدیو با onended جلو می‌رود؛ تایمر فقط سقف است تا ویدیوی
         خراب تابلو را برای همیشه قفل نکند. */
      playTimer = setTimeout(next, Math.max(secs, 5) * 1000);
    } else {
      playTimer = setTimeout(next, secs * 1000);
    }
  }

  function next() {
    if (!playlist.length) return;
    curIdx = (curIdx + 1) % playlist.length;
    playAt(curIdx);
  }

  function showEmpty() {
    TV.id('media').innerHTML =
      '<div class="item is-on"><div class="sg-empty">' +
        '<div class="sg-empty-title">محتوایی تنظیم نشده</div>' +
        '<div class="sg-empty-text">از پنل مدیریت، پلی‌لیست و زمان‌بندی این تابلو را تنظیم کنید</div>' +
        '<div class="sg-empty-code">' + esc(SCREEN_CODE) + '</div>' +
      '</div></div>';
    setTimeout(loadPlaylist, 30000);
  }

  function showOffline() {
    TV.addClass(TV.id('offline'), 'is-on');
    setTimeout(loadPlaylist, 15000);
  }

  /* ── زیرنویس ───────────────────────────────────────────────────── */
  var capTimer = null;

  function showCaption(text, ms) {
    var el = TV.id('subtitle-text');
    if (!el) return;
    clearTimeout(capTimer);
    if (!text) { TV.removeClass(el, 'is-on'); return; }
    TV.text(el, text);
    TV.addClass(el, 'is-on');
    capTimer = setTimeout(function () { TV.removeClass(el, 'is-on'); }, ms || 5000);
  }

  /* ── تیکر و لوگو از راه دور ────────────────────────────────────── */
  function setTicker(text) {
    var bar = TV.id('ticker');
    if (!bar) return;
    if (!text) { TV.removeClass(bar, 'is-on'); return; }
    var one = '<span class="ticker-text">' + esc(text) + '</span><span class="ticker-sep">◆</span>';
    /* دو بار، چون انیمیشن از -۵۰٪ به ۰ می‌رود */
    TV.id('ticker-inner').innerHTML = one + one;
    TV.addClass(bar, 'is-on');
  }

  function setLogo(url, opacity) {
    var el = TV.id('logo-ov');
    if (!el) return;
    if (!url) { el.style.display = 'none'; return; }
    var o = Number(opacity);
    el.style.opacity = (!o || isNaN(o)) ? 0.8 : o;
    el.innerHTML = '';
    var img = document.createElement('img');
    img.onerror = function () { el.style.display = 'none'; };
    img.src = url;
    el.appendChild(img);
    el.style.display = 'block';
  }

  /* ── پخش فوری ──────────────────────────────────────────────────── */
  var instantTimer = null;

  function instantShow(data) {
    if (!data) return;
    clearTimeout(instantTimer);

    var box  = TV.id('instant');
    var body = TV.id('instant-body');
    var type = data.type || 'image';
    var src  = data.content || '';
    var secs = parseInt(data.duration, 10);
    if (!secs || isNaN(secs)) secs = 30;

    body.innerHTML = '';
    body.style.background = '';
    TV.addClass(box, 'is-on');

    if (type === 'image') {
      var img = document.createElement('img');
      img.onerror = instantClear;
      img.src = src;
      body.appendChild(img);

    } else if (type === 'video') {
      var v = mkVideo(false);
      v.muted = false;          /* اعلام فوری باید صدا داشته باشد */
      v.src = src;
      v.onended = instantClear;
      v.onerror = instantClear;
      body.appendChild(v);

    } else if (type === 'hls') {
      var v2 = mkVideo(true);
      body.appendChild(v2);
      if (v2.canPlayType && v2.canPlayType('application/vnd.apple.mpegurl')) {
        v2.src = src;
      } else if (typeof Hls !== 'undefined' && Hls.isSupported()) {
        var h = new Hls({ enableWorker: false });
        h.loadSource(src);
        h.attachMedia(v2);
        hlsList.push(h);
      }

    } else if (type === 'url') {
      var f = document.createElement('iframe');
      f.src = src;
      body.appendChild(f);

    } else if (type === 'text') {
      var t = { text: src, color: '#ffffff', bg: '#000000' };
      try {
        var parsed = JSON.parse(src);
        if (parsed && typeof parsed === 'object') t = parsed;
      } catch (e) {}
      body.style.background = t.bg || '#000';
      var p = document.createElement('div');
      p.className = 'instant-text';
      p.style.color = t.color || '#fff';
      /* textContent نه innerHTML — متن از پنل می‌آید و نباید HTML شود */
      p.textContent = t.text || '';
      body.appendChild(p);
    }

    if (secs > 0) instantTimer = setTimeout(instantClear, secs * 1000);
  }

  function instantClear() {
    clearTimeout(instantTimer);
    var box  = TV.id('instant');
    var body = TV.id('instant-body');
    TV.removeClass(box, 'is-on');
    body.innerHTML = '';
    body.style.background = '';
  }

  /* ── پیام‌های زمان‌بندی‌شده ────────────────────────────────────
     سرور آن‌ها را در پاسخ ضربان می‌فرستد. صف دارند چون ممکن است چند
     پیام هم‌زمان سررسید شوند و روی هم افتادنشان روی تابلوی لابی
     بدترین حالت است. */
  var MSG = {
    queue: [],
    seen:  {},
    now:   null,
    timer: null,

    /* متن به زبان مرورگر. تلویزیون‌های هتل معمولا روی زبان میهمان
       تنظیم می‌شوند، پس اگر ترجمه هست همان را نشان بده. */
    pick: function (m, field) {
      var lang = (navigator.language || 'fa').slice(0, 2);
      if (lang === 'en' && m[field + '_en']) return m[field + '_en'];
      if (lang === 'ar' && m[field + '_ar']) return m[field + '_ar'];
      return m[field] || '';
    },

    icon: function (type) {
      var map = { welcome: '🤝', congratulation: '🎉', announcement: '📢',
                  warning: '⚠️', info: 'ℹ️' };
      return map[type] || '📢';
    },

    enqueue: function (list) {
      if (!list || !list.length) return;
      var i, m;
      for (i = 0; i < list.length; i++) {
        m = list[i];
        if (this.seen[m.id]) continue;
        this.seen[m.id] = 1;
        this.queue.push(m);
      }
      if (!this.now) this.showNext();
    },

    showNext: function () {
      if (!this.queue.length) { this.now = null; return; }
      this.now = this.queue.shift();
      this.render(this.now);
    },

    render: function (m) {
      var ov = TV.id('msg-ov');
      if (!ov) return;

      var style  = m.style || 'overlay';
      var title  = this.pick(m, 'title');
      var body   = this.pick(m, 'body');
      var accent = /^#[0-9a-f]{6}$/i.test(m.accent_color || '') ? m.accent_color : '#4098db';
      var bg     = /^#[0-9a-f]{6}$/i.test(m.bg_color || '')     ? m.bg_color     : '#131a2b';
      var color  = /^#[0-9a-f]{6}$/i.test(m.text_color || '')   ? m.text_color   : '#ffffff';
      var secs   = parseInt(m.duration, 10);
      if (!secs || isNaN(secs)) secs = 15;

      /* جهت متن از خود متن حدس زده می‌شود: پیام انگلیسی داخل قاب
         راست‌چین بد می‌نشیند. */
      var rtl = /[؀-ۿ]/.test(title + body);

      ov.innerHTML = '';

      var card = document.createElement('div');
      card.className = 'msg-card as-' + style;
      card.style.background  = bg;
      card.style.color       = color;
      card.style.borderColor = accent;
      card.dir = rtl ? 'rtl' : 'ltr';

      var html = '';
      if (style !== 'banner') {
        html += '<span class="msg-icon">' + esc(m.icon || this.icon(m.type)) + '</span>';
      }
      if (title) html += '<div class="msg-title">' + esc(title) + '</div>';
      if (style !== 'banner') {
        html += '<div class="msg-rule" style="background:' + esc(accent) + '"></div>';
      }
      if (body) html += '<div class="msg-body">' + esc(body) + '</div>';
      html += '<button type="button" class="msg-close" style="background:' + esc(accent) + '">بستن</button>';
      card.innerHTML = html;

      ov.appendChild(card);
      TV.addClass(ov, 'is-on');

      var self = this;
      TV.on(card.getElementsByTagName('button')[0], 'click', function () { self.dismiss(); });

      clearTimeout(this.timer);
      this.timer = setTimeout(function () { self.dismiss(); }, secs * 1000);
    },

    dismiss: function () {
      clearTimeout(this.timer);
      var ov = TV.id('msg-ov');
      if (!ov) return;
      var card = ov.getElementsByTagName('div')[0];
      var self = this;

      function finish() {
        ov.innerHTML = '';
        TV.removeClass(ov, 'is-on');
        self.now = null;
        /* مکث کوتاه بین دو پیام، وگرنه پشت‌سرهم می‌پرند و خوانده نمی‌شوند */
        setTimeout(function () { self.showNext(); }, 2000);
      }

      if (card) { TV.addClass(card, 'is-out'); setTimeout(finish, 380); }
      else      { finish(); }
    }
  };

  /* ── ضربان ─────────────────────────────────────────────────────
     فاصله را سرور تعیین می‌کند. قبلا ثابت ۱۵ ثانیه بود: در هتلی با
     ۳۰۰ تابلو و تلویزیون یعنی ۲۰ درخواست در ثانیه، بدون هیچ راهی
     برای کم‌کردنش جز تغییر کد روی همه‌ی دستگاه‌ها. */
  var hbTimer = null;

  function scheduleHeartbeat(sec) {
    var s = Number(sec);
    if (!s || isNaN(s)) s = 30;
    if (s < 10) s = 10;
    if (s > 300) s = 300;
    clearTimeout(hbTimer);
    hbTimer = setTimeout(heartbeat, s * 1000);
  }

  function heartbeat() {
    TV.post(ORIGIN + '/api/v1/screens/' + SCREEN_CODE + '/heartbeat',
      { version: '2.0', screen_type: 'signage', current_item: curIdx },
      function (err, d) {
        if (err || !d || !d.data) { scheduleHeartbeat(60); return; }
        MSG.enqueue(d.data.messages);
        runCommands(d.data.commands);
        scheduleHeartbeat(d.data.sync_interval || 30);
      });
  }

  /* فرمان‌های مخصوص تابلو. بقیه (open_url، clear_cache، volume،
     reboot) را TV.runCommands می‌گیرد تا بین صفحه‌ها یکسان بماند و
     دوباره از هم دور نیفتند. ack هم آنجا فرستاده می‌شود — قبلا
     تابلوها هیچ‌وقت ack نمی‌فرستادند و در پنل همه‌ی فرمان‌ها
     «در انتظار» می‌ماند. */
  function signageCommand(name, p) {
    if (name === 'instant_media') { instantShow(p); return true; }
    if (name === 'clear_instant') { instantClear(); return true; }
    if (name === 'subtitle')      { showCaption(p.text || '', (p.duration || 5) * 1000); return true; }
    if (name === 'ticker')        { setTicker(p.text || ''); return true; }
    if (name === 'logo')          { setLogo(p.url, p.opacity); return true; }

    /* بارگذاری دوباره‌ی پلی‌لیست کافی است؛ لازم نیست کل صفحه رفرش شود
       و تابلو چند ثانیه سیاه بماند. */
    if (name === 'reload' || name === 'refresh') { loadPlaylist(); return true; }

    /* پیام را با نمایش اختصاصی خودمان نشان بده، نه نوار پیش‌فرض */
    if (name === 'message') {
      MSG.enqueue([{ id: 'cmd-' + (new Date()).getTime(),
                     title: p.title || '', body: p.body || p.text || '',
                     type: p.type || 'announcement',
                     style: p.style || 'overlay',
                     duration: p.duration || 15 }]);
      return true;
    }
    return false;
  }

  function runCommands(list) {
    TV.runCommands(list, ORIGIN, SCREEN_CODE, signageCommand);
  }

  /* ── WebSocket ─────────────────────────────────────────────────── */
  function connectWS() {
    if (typeof WebSocket === 'undefined') return;
    var ws;
    try { ws = new WebSocket('ws://' + location.hostname + ':' + WS_PORT); }
    catch (e) { return; }

    ws.onopen = function () {
      try {
        ws.send(JSON.stringify({ type: 'subscribe', channel: 'screen_' + SCREEN_CODE }));
      } catch (e2) {}
    };
    ws.onmessage = function (ev) {
      var m;
      try { m = JSON.parse(ev.data); } catch (e3) { return; }
      var p = m.data || {};
      if (m.type === 'broadcast' || m.type === 'instant_media') instantShow(p);
      else if (m.type === 'clear')    instantClear();
      else if (m.type === 'reload')   loadPlaylist();
      else if (m.type === 'reboot')   location.reload();
      else if (m.type === 'subtitle') showCaption(p.text || '', (p.duration || 5) * 1000);
      else if (m.type === 'ticker')   setTicker(p.text || '');
      else if (m.type === 'logo')     setLogo(p.url, p.opacity);
    };
    ws.onclose = function () { setTimeout(connectWS, 5000); };
    ws.onerror = function () { try { ws.close(); } catch (e4) {} };
  }

  /* ── شروع ──────────────────────────────────────────────────────── */
  loadPlaylist();
  connectWS();

  /* پخش تصادفی: بعد از قطعی برقِ هتل همه‌ی تابلوها همزمان بوت می‌شوند
     و بدون این، در یک لحظه به سرور می‌زنند. */
  setTimeout(heartbeat, Math.floor(Math.random() * 10000));

<?php endif; ?>
})();
</script>
</body>
</html>
