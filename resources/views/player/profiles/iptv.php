<?php
/**
 * Hotel Media — پورتال IPTV مهمان
 *
 * این صفحه در مرورگرِ خودِ تلویزیون هتل باز می‌شود، نه کروم دسکتاپ:
 *   Samsung Tizen 2.3 → Chromium 34     LG webOS 3 → Chromium 38
 *   Samsung Tizen 5   → Chromium 63     LG webOS 5 → Chromium 68
 *
 * پس همه‌ی JS اینجا ES5 است و CSS از tv-base.css می‌آید. اگر کد ES6
 * بنویسید خطای کنسول به جایی نمی‌رسد — تلویزیون فقط صفحه‌ی سیاه
 * نشان می‌دهد. بازرس: node tests/Support/tv-compat-lint.js
 *
 * ظاهر (رنگ، پس‌زمینه، لوگو، تیکر) از API منو می‌آید، نه از تنظیمات
 * صفحه — تا مدیر هتل بتواند یک‌بار تنظیم کند و روی همه‌ی اتاق‌ها بیفتد.
 */
$screenCode = (string)($screen['code'] ?? '');
$iptvMenuId = (int)($screen['iptv_menu_id'] ?? 0);
$screenName = (string)($screen['name'] ?? 'IPTV');
$isActive   = ($screen['status'] ?? '') === 'active';

/* تاریخ شمسی را سرور می‌سازد. مرورگر تلویزیون داده‌ی Intl برای fa-IR
   ندارد و toLocaleDateString('fa-IR') آنجا تاریخ میلادی برمی‌گرداند. */
$todayJalali = function_exists('jalaliDate') ? jalaliDate() : '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>IPTV — <?= e($screenName) ?></title>

<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css<?= v() ?>">
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-base.css<?= v() ?>">
<script src="/assets/vendor/hls/hls.min.js<?= v() ?>"></script>
<script src="/assets/js/tv-base.js<?= v() ?>"></script>
<style>
/* فقط چیزهایی که مخصوص همین صفحه‌اند. بقیه در tv-base.css است.
   یادآوری: بدون var() ، clamp() ، gap ، inset ، backdrop-filter. */

/* ── تیکر خبری ── */
#ticker {
  height: 2.6rem;
  background: #000;
  border-top: 1px solid rgba(26, 122, 196, .28);
  overflow: hidden;
  display: none;
}
#ticker.is-on {
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
}
#ticker-dot {
  width: .5rem; height: .5rem; border-radius: 50%;
  background: #4098db;
  margin: 0 1rem;
  -webkit-flex: 0 0 auto; -ms-flex: 0 0 auto; flex: 0 0 auto;
}
#ticker-track { -webkit-flex: 1; -ms-flex: 1; flex: 1; overflow: hidden; }
#ticker-inner {
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  white-space: nowrap;
  -webkit-animation: tickerMove 90s linear infinite;
  animation: tickerMove 90s linear infinite;
}
.ticker-text { font-size: 1rem; font-weight: 600; padding: 0 5rem; }
.ticker-sep  { color: rgba(255,255,255,.25); padding: 0 1.2rem; }
@-webkit-keyframes tickerMove { 0% { -webkit-transform: translateX(-50%); } 100% { -webkit-transform: translateX(0); } }
@keyframes tickerMove { 0% { -webkit-transform: translateX(-50%); transform: translateX(-50%); } 100% { -webkit-transform: translateX(0); transform: translateX(0); } }

/* ── نشان اتاق ── */
#room-badge {
  position: fixed;
  bottom: 3.4rem;
  left: 3.2rem;
  z-index: 25;
  padding: .45rem 1rem;
  border-radius: .65rem;
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.13);
  display: none;
}
#room-badge.is-on {
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
}
#rb-icon { color: #4098db; font-size: .95rem; margin-left: .55rem; }
#rb-number { font-size: 1rem; font-weight: 800; }
#rb-guest  { font-size: .78rem; color: #8c99ad; }

/* ── پیام نواری ── */
#msg-banner {
  position: fixed;
  right: 0; bottom: 2.6rem; left: 0;
  z-index: 30;
  -webkit-transform: translateY(150%);
  -ms-transform: translateY(150%);
  transform: translateY(150%);
  -webkit-transition: -webkit-transform .35s ease;
  transition: transform .35s ease;
}
#msg-banner.is-on {
  -webkit-transform: translateY(0); -ms-transform: translateY(0); transform: translateY(0);
}
#msg-banner-inner {
  margin: 0 3.2rem;
  padding: .9rem 1.3rem;
  border-radius: .9rem;
  border: 1px solid rgba(255,255,255,.14);
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
}
#mb-icon  { font-size: 1.4rem; margin-left: .85rem; -webkit-flex: 0 0 auto; -ms-flex: 0 0 auto; flex: 0 0 auto; }
#mb-title { font-size: 1rem; font-weight: 800; display: none; }
#mb-body  { font-size: .92rem; font-weight: 400; color: #c2cbd8; }

/* ── پیام وسط صفحه ── */
#msg-popup {
  position: fixed;
  top: 0; right: 0; bottom: 0; left: 0;
  z-index: 40;
  background: rgba(0,0,0,.72);
  display: none;
}
#msg-popup.is-on {
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
  -webkit-box-pack: center; -webkit-justify-content: center;
  -ms-flex-pack: center; justify-content: center;
}
#mp-card {
  background: #10151d;
  border: 1px solid rgba(255,255,255,.14);
  border-radius: 1.25rem;
  padding: 2.6rem 2.2rem;
  width: 34rem;
  max-width: 88%;
  text-align: center;
}
#mp-icon  { font-size: 3rem; display: block; margin-bottom: .9rem; }
#mp-title { font-size: 1.55rem; font-weight: 800; margin-bottom: .6rem; }
#mp-body  { font-size: 1.1rem; font-weight: 400; color: #c2cbd8; line-height: 1.7; }
#mp-close { margin-top: 1.6rem; }

/* ── پخش محتوا ── */
#stage-player {
  position: fixed;
  top: 0; right: 0; bottom: 0; left: 0;
  z-index: 50;
  background: #000;
  display: none;
}
#stage-player.is-on { display: block; }
#stage-player video,
#stage-player iframe { width: 100%; height: 100%; border: 0; }
/* contain نه cover — با cover لبه‌ی تصویر تلویزیون بریده می‌شد */
#stage-player video { -o-object-fit: contain; object-fit: contain; }

#back-btn {
  position: fixed;
  top: 1.6rem; left: 1.6rem;
  z-index: 60;
  display: none;
}
#back-btn.is-on {
  display: -webkit-inline-box; display: -webkit-inline-flex;
  display: -ms-inline-flexbox; display: inline-flex;
}

/* ── صفحه فعال‌سازی ── */
#activation {
  position: fixed;
  top: 0; right: 0; bottom: 0; left: 0;
  z-index: 200;
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
  -webkit-box-pack: center; -webkit-justify-content: center;
  -ms-flex-pack: center; justify-content: center;
}
#act-card {
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 1.5rem;
  padding: 3rem 2.5rem;
  width: 24rem;
  max-width: 90%;
  text-align: center;
}
#act-logo { height: 3.4rem; width: auto; margin: 0 auto 1.4rem; display: block; }
#act-title { font-size: 1.4rem; font-weight: 800; margin-bottom: .4rem; }
#act-sub   { font-size: .92rem; font-weight: 400; color: #a8b4c6; margin-bottom: 1.4rem; }
#act-screen { font-size: .85rem; font-weight: 400; color: #8c99ad; margin-bottom: .8rem; }
#act-screen b { color: #7bb8e8; font-family: monospace; letter-spacing: .08em; }
#act-code {
  width: 100%;
  padding: 1rem;
  border-radius: .8rem;
  background: rgba(0,0,0,.45);
  border: 2px solid rgba(26,122,196,.35);
  color: #fff;
  font-family: monospace;
  font-size: 1.7rem;
  letter-spacing: .5rem;
  text-align: center;
  text-transform: uppercase;
  outline: 0;
}
#act-code:focus { border-color: #4098db; }
#act-btn { width: 100%; margin-top: 1rem; -webkit-box-pack: center; -webkit-justify-content: center; -ms-flex-pack: center; justify-content: center; }
#act-err { font-size: .9rem; margin-top: .9rem; min-height: 1.4rem; color: #ff5f57; }
</style>
</head>
<body>

<!-- لایه‌های پس‌زمینه. tv-layer یعنی top/right/bottom/left صفر — inset
     کوتاه‌نویس Chromium 87 می‌خواهد و هیچ تلویزیون هتلی ندارد. -->
<div class="tv-layer tv-bg"></div>
<div class="tv-layer tv-bg-image" id="bg-img"></div>
<div class="tv-layer tv-bg-glow" id="bg-glow"></div>
<div class="tv-layer tv-bg-dim" id="bg-dim"></div>

<?php if (!$isActive): ?>
<!-- ══ فعال‌سازی ══ -->
<div id="activation">
  <div id="act-card">
    <img id="act-logo" src="/assets/img/sama-logo.svg<?= v() ?>" alt="">
    <div id="act-title">Hotel Media</div>
    <div id="act-sub">این تلویزیون هنوز فعال نشده است</div>
    <div id="act-screen">کد صفحه: <b><?= e($screenCode) ?></b></div>
    <input type="text" id="act-code" maxlength="6" placeholder="------"
           autocomplete="off" autocorrect="off" spellcheck="false">
    <button type="button" class="tv-btn" id="act-btn">
      <i class="fas fa-satellite-dish" style="margin-left:.5rem"></i>فعال‌سازی
    </button>
    <div id="act-err"></div>
  </div>
</div>

<?php else: ?>
<!-- ══ پورتال ══ -->

<!-- بارگذاری/خطا. tv-base.js با TV.loading و TV.fail اینجا را پر می‌کند. -->
<div class="tv-center" id="tv-center">
  <div class="tv-spinner"></div>
  <div class="tv-center-text">در حال بارگذاری منو…</div>
</div>

<div class="tv-stage tv-col tv-hidden" id="stage">

  <div class="tv-header tv-row tv-row-top tv-spread tv-fixed tv-in">
    <div class="tv-grow">
      <img class="tv-logo tv-hidden" id="hotel-logo" src="" alt="">
      <div class="tv-welcome" id="welcome-title"><?= e($screenName) ?></div>
      <div class="tv-welcome-sub tv-hidden" id="welcome-sub"></div>
    </div>
    <div class="tv-clock tv-fixed">
      <div class="tv-clock-time" id="clock-time">--:--</div>
      <div class="tv-clock-date" id="clock-date"><?= e($todayJalali) ?></div>
    </div>
  </div>

  <div class="tv-divider tv-fixed"></div>

  <div class="tv-grid-wrap tv-grow tv-in">
    <div class="tv-grid" id="grid"></div>
  </div>

  <!-- راهنمای ریموت. قبلا رنگش #1e293b بود روی زمینه‌ی #09090f —
       یعنی عملا نامرئی. حالا از کلاس tv-hints می‌آید. -->
  <div class="tv-hints tv-row tv-spread tv-fixed">
    <div>
      <span class="tv-hint"><span class="tv-key">▲▼◀▶</span> جابه‌جایی</span>
      <span class="tv-hint"><span class="tv-key">OK</span> انتخاب</span>
      <span class="tv-hint"><span class="tv-key">↩</span> بازگشت</span>
    </div>
    <span style="font-family:monospace;letter-spacing:.06em"><?= e($screenCode) ?></span>
  </div>

  <div id="ticker" class="tv-fixed">
    <div id="ticker-dot"></div>
    <div id="ticker-track"><div id="ticker-inner"></div></div>
  </div>

</div><!-- /stage -->

<div id="room-badge">
  <i class="fas fa-door-open" id="rb-icon"></i>
  <div>
    <div id="rb-number"></div>
    <div id="rb-guest"></div>
  </div>
</div>

<div id="msg-banner">
  <div id="msg-banner-inner">
    <span id="mb-icon"></span>
    <div class="tv-grow">
      <div id="mb-title"></div>
      <div id="mb-body"></div>
    </div>
    <button type="button" class="tv-btn" id="mb-close" style="padding:.3rem .7rem;font-size:.85rem">
      <i class="fas fa-times"></i>
    </button>
  </div>
</div>

<div id="msg-popup">
  <div id="mp-card">
    <span id="mp-icon"></span>
    <div id="mp-title"></div>
    <div id="mp-body"></div>
    <button type="button" class="tv-btn" id="mp-close">متوجه شدم</button>
  </div>
</div>

<div id="stage-player"></div>

<!-- انتخاب زیرنویس و صدا. مهمان وسط فیلم دکمه‌ی زیرنویس ریموت را
     می‌زند؛ پخش نباید قطع شود، پس لایه‌ی شناور است نه صفحه‌ی جدا. -->
<div class="tv-tracks" id="tracks-screen">
  <div class="tv-tracks-box">
    <div class="tv-tracks-title">زیرنویس و صدا</div>
    <div class="tv-tracks-group" id="tracks-subs-group">
      <div class="tv-tracks-label">زیرنویس</div>
      <div id="tracks-subs"></div>
    </div>
    <div class="tv-tracks-group" id="tracks-audio-group">
      <div class="tv-tracks-label">صدا</div>
      <div id="tracks-audio"></div>
    </div>
    <div class="tv-tracks-hint">
      <span class="tv-key">OK</span> انتخاب &nbsp;·&nbsp;
      <span class="tv-key">↩</span> بستن
    </div>
  </div>
</div>

<!-- اتصال دستگاه مهمان. منوی خود تلویزیون در اتاق قفل است، پس وصل‌کردن
     موبایل یا کنسول بازی باید از همین‌جا شدنی باشد. -->
<div class="tv-inputs" id="inputs-screen">
  <div class="tv-inputs-title">اتصال دستگاه</div>
  <div class="tv-inputs-sub" id="inputs-sub">در حال بررسی…</div>
  <div class="tv-grid tv-inputs-grid" id="inputs-grid"></div>
  <button type="button" class="tv-btn tv-inputs-back" id="inputs-back">
    <i class="fas fa-chevron-right" style="margin-left:.5rem"></i>بازگشت
  </button>
</div>

<button type="button" class="tv-btn" id="back-btn">
  <i class="fas fa-chevron-right" style="margin-left:.5rem"></i>
  <span id="back-label">بازگشت</span>
</button>

<?php endif; ?>

<script>
/* ES5 خالص — بدون const/let، تابع arrow، رشته‌ی template، fetch یا Promise.
   دلیلش بالای فایل و در tv-base.js توضیح داده شده. */
(function () {
  'use strict';

  var SCREEN_CODE  = '<?= e($screenCode) ?>';
  var IPTV_MENU_ID = <?= $iptvMenuId ?>;
  var SCREEN_NAME  = '<?= e($screenName) ?>';
  var WS_PORT      = <?= (int)env('WS_PORT', 8080) ?>;
  var ORIGIN       = window.location.protocol + '//' + window.location.host;

<?php if (!$isActive): ?>
  /* ── فعال‌سازی ─────────────────────────────────────────────────── */
  TV.boot();

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
  TV.on(document, 'keydown', function (e) {
    if (TV.keyName(e) === 'OK') activate();
  });
  if (codeEl.focus) codeEl.focus();

<?php else: ?>
  /* ── پورتال ────────────────────────────────────────────────────── */
  TV.boot();

  var items      = [];      /* آیتم‌های منو */
  var tiles      = [];      /* المان کاشی‌ها، هم‌ترتیب با items */
  var focusIdx   = 0;
  var autoTimer  = null;
  var hlsInst    = null;
  var playing    = false;
  var curVideo   = null;    /* المان ویدیوی در حال پخش */

  /* زیرنویس و باند صوتی فیلم در حال پخش */
  var trackData  = { subtitles: [], audio: [] };
  var trackRows  = [];
  var trackFocus = 0;
  var tracksOpen = false;
  var accent     = '#1a7ac4';
  var accentRgb  = '26,122,196';

  /* Set در Chromium 34 ناقص است، پس نقشه‌ی ساده */
  var seenBanner = {};
  var seenPopup  = {};

  /* ── رنگ ────────────────────────────────────────────────────────
     hex به «r,g,b» تا بتوان rgba() ساخت. */
  function hexRgb(h, fallback) {
    var m = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(h || '');
    if (!m) return fallback;
    return parseInt(m[1], 16) + ',' + parseInt(m[2], 16) + ',' + parseInt(m[3], 16);
  }

  /* ── ظاهر از تنظیمات منو ───────────────────────────────────────── */
  function applyAppearance(d) {
    if (/^#[0-9a-f]{6}$/i.test(d.accent_color || '')) {
      accent    = d.accent_color;
      accentRgb = hexRgb(accent, accentRgb);
    }

    var dim = parseFloat(d.bg_dim);
    if (isNaN(dim)) dim = 0.58;
    if (dim < 0) dim = 0; if (dim > 1) dim = 1;
    TV.id('bg-dim').style.background = 'rgba(4,7,12,' + dim + ')';

    var bg = TV.id('bg-img');
    if (d.bg_image) {
      bg.style.backgroundImage = "url('" + String(d.bg_image).replace(/'/g, '%27') + "')";
      /* بلور پس‌زمینه با filter روی SoC تلویزیون هر فریم دوباره حساب
         می‌شود و پخش ویدیو را می‌لرزاند. تصویر ثابت است، پس یک‌بار
         تیره‌کردن کافی است و بلور را عمدا اعمال نمی‌کنیم. */
      TV.hide(TV.id('bg-glow'));
    } else {
      bg.style.backgroundImage = '';
      TV.show(TV.id('bg-glow'));
    }

    TV.text(TV.id('welcome-title'), d.welcome_title || SCREEN_NAME);

    var sub = TV.id('welcome-sub');
    if (d.welcome_sub) { TV.text(sub, d.welcome_sub); TV.show(sub); }
    else               { TV.hide(sub); }

    var logo = TV.id('hotel-logo');
    if (d.logo_url) {
      logo.onerror = function () { TV.hide(logo); };
      logo.src = d.logo_url;
      TV.show(logo);
    } else {
      TV.hide(logo);
    }

    if (d.ticker_text) setTicker([d.ticker_text], d.ticker_color, d.ticker_bg);
  }

  function setTicker(texts, color, bg) {
    if (!texts || !texts.length) return;
    var box = TV.id('ticker');
    var inner = TV.id('ticker-inner');
    var html = '', i;
    /* دو بار تکرار می‌شود چون انیمیشن از -۵۰٪ به ۰ می‌رود و باید
       نیمه‌ی دوم دقیقا نیمه‌ی اول باشد وگرنه در حلقه پرش دارد. */
    var pass;
    for (pass = 0; pass < 2; pass++) {
      for (i = 0; i < texts.length; i++) {
        html += '<span class="ticker-text"' +
                (color ? ' style="color:' + TV.esc(color) + '"' : '') + '>' +
                TV.esc(texts[i]) + '</span><span class="ticker-sep">◆</span>';
      }
    }
    inner.innerHTML = html;
    if (bg) box.style.background = bg;
    TV.id('ticker-dot').style.background = accent;
    TV.addClass(box, 'is-on');
  }

  /* ── منو ───────────────────────────────────────────────────────── */
  function loadMenu() {
    if (!IPTV_MENU_ID) { noMenu(); return; }

    TV.get(ORIGIN + '/api/v1/player/iptv-menu/' + IPTV_MENU_ID, function (err, d) {
      if (err || !d || !d.success || !d.data || !d.data.items || !d.data.items.length) {
        if (err) {
          /* شبکه قطع است — دوباره تلاش کن، ولی صفحه‌ی سیاه نگذار */
          TV.fail('ارتباط با سرور برقرار نشد', 'تلاش دوباره تا چند لحظه دیگر');
          setTimeout(loadMenu, 10000);
        } else {
          noMenu();
        }
        return;
      }
      items = d.data.items;
      applyAppearance(d.data);
      buildGrid();
      showStage();
    });
  }

  function noMenu() {
    TV.fail('منویی تنظیم نشده',
            'از پنل مدیریت، برای این صفحه یک منوی IPTV انتخاب کنید' +
            ' · کد صفحه ' + SCREEN_CODE);
    setTimeout(loadMenu, 30000);
  }

  function buildGrid() {
    var grid = TV.id('grid'), html = '', i, it, rgb;
    for (i = 0; i < items.length; i++) {
      it  = items[i];
      rgb = hexRgb(it.color, accentRgb);
      html +=
        '<div class="tv-tile" data-idx="' + i + '">' +
          '<div class="tv-tile-icon" style="background:rgba(' + rgb + ',.18);' +
               'border:2px solid rgba(' + rgb + ',.34)">' +
            '<i class="' + TV.esc(it.icon || 'fas fa-tv') + '"' +
               ' style="color:' + TV.esc(it.color || accent) + '"></i>' +
          '</div>' +
          '<div class="tv-tile-label">' + TV.esc(it.label) + '</div>' +
        '</div>';
    }
    grid.innerHTML = html;
    tiles = TV.all('.tv-tile', grid);

    /* کلیک برای نمایشگر لمسی لابی. روی تلویزیون معمولی اتفاق نمی‌افتد. */
    for (i = 0; i < tiles.length; i++) {
      (function (idx) {
        TV.on(tiles[idx], 'click', function () { select(idx); });
      })(i);
    }
  }

  function showStage() {
    TV.ready();
    TV.show(TV.id('stage'));
    focus(0);
    TV.startClock(TV.id('clock-time'), true);
    scheduleAutoPlay();
  }

  /* اولین آیتمی که آدرس دارد بعد از ۸ ثانیه خودش پخش می‌شود، تا
     اگر مهمان ریموت دست نگرفت، تلویزیون روی منوی ساکن نماند. */
  function scheduleAutoPlay() {
    clearTimeout(autoTimer);
    var i;
    for (i = 0; i < items.length; i++) {
      if (items[i].target_url) {
        autoTimer = setTimeout((function (idx) {
          return function () { select(idx); };
        })(i), 8000);
        return;
      }
    }
  }

  /* ── فوکوس ─────────────────────────────────────────────────────── */
  function focus(idx) {
    if (!tiles.length) return;
    if (idx < 0) idx = 0;
    if (idx >= tiles.length) idx = tiles.length - 1;
    var i;
    for (i = 0; i < tiles.length; i++) {
      TV.toggleClass(tiles[i], 'is-focused', i === idx);
    }
    focusIdx = idx;
  }

  /* ── پخش ───────────────────────────────────────────────────────── */
  function select(idx) {
    clearTimeout(autoTimer);
    var it = items[idx];
    if (!it) return;

    /* «اتصال دستگاه» محتوا نیست — صفحه‌ی خودش را باز می‌کند.
       بدون این، کاشی به شاخه‌ی ماژول می‌افتاد و iframe خالی می‌ساخت. */
    if (it.type === 'input' || it.type === 'hdmi') { openInputs(); return; }

    play(it);
  }

  function play(it) {
    playing  = true;
    curVideo = null;
    TV.hide(TV.id('stage'));

    TV.text(TV.id('back-label'), it.label || 'بازگشت');
    TV.addClass(TV.id('back-btn'), 'is-on');

    /* زیرنویس و صدا همراه خود آیتم می‌آیند، پس درخواست اضافه‌ای به
       سرور نمی‌خورد. آیتم‌هایی که ندارند فقط فهرست خالی دارند. */
    trackData = {
      subtitles: it.subtitles || [],
      audio:     it.audio || []
    };
    /* باند پیش‌فرض را علامت بزن تا تیک درست نشان داده شود */
    var t;
    for (t = 0; t < trackData.audio.length; t++) {
      trackData.audio[t]._active = (t === 0) || !!trackData.audio[t].is_default;
    }

    destroyHls();
    var host = TV.id('stage-player');
    host.innerHTML = '';
    TV.addClass(host, 'is-on');

    var src = it.target_url || '';

    if (!src) {
      iframe(ORIGIN + '/player/module/' + encodeURIComponent(it.type || ''), host);
    } else if (/\.m3u8(\?|$)/i.test(src)) {
      hls(src, host);
    } else if (src.indexOf('udp://') === 0 || src.indexOf('rtp://') === 0 ||
               src.indexOf('rtsp://') === 0) {
      /* multicast مستقیم.
         تلویزیون‌های هتلی LG (webOS) و Samsung (Tizen) در تست میدانی
         udp:// را با پخش‌کننده‌ی بومی خودشان باز کردند، پس آدرس را به
         تگ video می‌دهیم. روی مرورگر معمولی این کار نمی‌کند و onerror
         پیام روشن نشان می‌دهد — نه پلیر مرده و نه صفحه‌ی سیاه. */
      multicast(src, host);
    } else if (/\.(mp4|webm|m4v|mov)(\?|$)/i.test(src)) {
      video(src, host);
    } else {
      iframe(src, host);
    }

    /* بازگشت خودکار بعد از ۵ دقیقه تا تلویزیون روی یک صفحه گیر نکند */
    autoTimer = setTimeout(back, 300000);
  }

  function mkVideo(loop) {
    var v = document.createElement('video');
    v.autoplay = true;
    v.muted    = true;      /* بدون این، پخش خودکار روی webOS رد می‌شود */
    v.loop     = !!loop;
    v.setAttribute('playsinline', 'playsinline');
    v.setAttribute('webkit-playsinline', 'webkit-playsinline');
    return v;
  }

  function hls(src, host) {
    var v = mkVideo(false);
    curVideo = v;
    TV.tracks.attachSubtitles(v, trackData.subtitles);
    host.appendChild(v);

    /* ترتیب مهم است: تلویزیون‌هایی که HLS بومی دارند باید از آن استفاده
       کنند، چون hls.js روی SoC ضعیف کند است و MediaSource بعضی
       نسخه‌های webOS ناقص است. */
    if (v.canPlayType && v.canPlayType('application/vnd.apple.mpegurl')) {
      v.src = src;
      v.onerror = back;
      return;
    }
    if (typeof Hls !== 'undefined' && Hls.isSupported()) {
      hlsInst = new Hls({ enableWorker: false });   /* worker روی تلویزیون ناپایدار است */
      hlsInst.loadSource(src);
      hlsInst.attachMedia(v);
      hlsInst.on(Hls.Events.ERROR, function (evt, data) {
        if (data && data.fatal) back();
      });
      return;
    }
    host.innerHTML = centerMsg('این کانال پخش نشد',
      'مرورگر این تلویزیون از این قالب پشتیبانی نمی‌کند.');
    setTimeout(back, 8000);
  }

  /* ── multicast مستقیم (udp / rtp / rtsp) ──────────────────────────
     تلویزیون خودش سوکت را باز می‌کند و سرور هیچ باری نمی‌گیرد — کل
     دلیل وجود multicast همین است. اگر رله (udpxy) وسط بود، سرور در
     هتل ۳۰۰ اتاقی باید ۳۰۰ جریان unicast می‌ساخت.

     شرط شبکه: روی VLAN مربوط به IPTV باید IGMP snooping روشن و دقیقا
     یک querier فعال باشد. بدون querier، جدول عضویت خالی می‌ماند و
     سوییچ یا هیچ‌چیز پخش نمی‌کند یا به همه‌ی پورت‌ها flood می‌کند. */
  function multicast(src, host) {
    var v = mkVideo(true);
    curVideo = v;
    TV.tracks.attachSubtitles(v, trackData.subtitles);
    var settled = false;

    function giveUp() {
      if (settled) return;
      settled = true;
      host.innerHTML = centerMsg('این کانال از این دستگاه باز نشد',
        'کانال روی شبکه multicast پخش می‌شود و پخش‌کننده‌ی این دستگاه ' +
        'آن را نمی‌خواند. از تیونر خود تلویزیون ببینید.');
      setTimeout(back, 8000);
    }

    v.onerror = giveUp;
    /* اگر پخش شروع شد دیگر نگهبان را اجرا نکن */
    v.onplaying = function () { settled = true; };

    /* بعضی پخش‌کننده‌ها روی آدرس پشتیبانی‌نشده نه خطا می‌دهند نه پخش
       می‌کنند و صفحه برای همیشه سیاه می‌ماند؛ این نگهبان لازم است. */
    setTimeout(function () {
      if (!settled && (!v.readyState || v.readyState < 2)) giveUp();
    }, 12000);

    v.src = src;
    host.appendChild(v);
  }

  function video(src, host) {
    var v = mkVideo(false);
    curVideo = v;
    TV.tracks.attachSubtitles(v, trackData.subtitles);
    v.src = src;
    v.onended = back;
    v.onerror = back;
    host.appendChild(v);
  }

  function iframe(src, host) {
    var f = document.createElement('iframe');
    f.setAttribute('allow', 'autoplay; fullscreen');
    f.src = src;
    host.appendChild(f);
  }

  function centerMsg(title, text) {
    return '<div class="tv-center" style="position:absolute">' +
             '<div class="tv-center-title">' + TV.esc(title) + '</div>' +
             '<div class="tv-center-text">' + TV.esc(text) + '</div>' +
           '</div>';
  }

  function destroyHls() {
    if (!hlsInst) return;
    try { hlsInst.destroy(); } catch (e) {}
    hlsInst = null;
  }

  function back() {
    clearTimeout(autoTimer);
    destroyHls();
    closeTracks();
    playing  = false;
    curVideo = null;
    trackData = { subtitles: [], audio: [] };
    var host = TV.id('stage-player');
    TV.removeClass(host, 'is-on');
    host.innerHTML = '';
    TV.removeClass(TV.id('back-btn'), 'is-on');
    TV.show(TV.id('stage'));
    focus(focusIdx);
    scheduleAutoPlay();
  }

  TV.on(TV.id('back-btn'), 'click', back);

  /* ── ریموت ─────────────────────────────────────────────────────── */
  TV.on(document, 'keydown', function (e) {
    var k = TV.keyName(e);
    if (!k) return;

    /* صفحه‌ی اتصال دستگاه روی بقیه است، پس اول او. */
    if (inputsOpen) {
      if (k === 'BACK' || k === 'EXIT') { closeInputs(); if (e.preventDefault) e.preventDefault(); return; }
      if (k === 'OK') { pickInput(inputFocus); if (e.preventDefault) e.preventDefault(); return; }
      if (k === 'LEFT' || k === 'RIGHT' || k === 'UP' || k === 'DOWN') {
        var ni = TV.findNeighbor(inputTiles, inputFocus, k);
        if (ni >= 0) focusInput(ni);
        if (e.preventDefault) e.preventDefault();
      }
      return;
    }

    /* لایه‌ی زیرنویس و صدا روی پخش است */
    if (tracksOpen) {
      if (k === 'BACK' || k === 'EXIT') { closeTracks(); if (e.preventDefault) e.preventDefault(); return; }
      if (k === 'OK') { pickTrack(trackFocus); if (e.preventDefault) e.preventDefault(); return; }
      if (k === 'UP')   { focusTrack(trackFocus - 1); if (e.preventDefault) e.preventDefault(); return; }
      if (k === 'DOWN') { focusTrack(trackFocus + 1); if (e.preventDefault) e.preventDefault(); return; }
      return;
    }

    if (playing) {
      if (k === 'BACK' || k === 'EXIT') { back(); if (e.preventDefault) e.preventDefault(); return; }

      /* دکمه‌ی زرد ریموت و SUBTITLE هر دو زیرنویس را باز می‌کنند.
         روی ریموت هتلی معمولا دکمه‌ی SUBTITLE جدا نیست، ولی چهار
         دکمه‌ی رنگی همیشه هست — و مهمان با راهنمای روی صفحه یاد
         می‌گیرد کدام است. */
      if (k === 'YELLOW' || k === 'SUBTITLE') {
        openTracks();
        if (e.preventDefault) e.preventDefault();
      }
      return;
    }
    if (!tiles.length) return;

    if (k === 'OK') { select(focusIdx); if (e.preventDefault) e.preventDefault(); return; }

    if (k === 'LEFT' || k === 'RIGHT' || k === 'UP' || k === 'DOWN') {
      /* همسایه بر اساس مکان واقعی روی صفحه انتخاب می‌شود، نه با
         حدسِ «تعداد ستون». با wrap شدن کاشی‌ها، حدس ستون غلط است
         و «پایین» مهمان را به کاشی نامربوط می‌برد. */
      var n = TV.findNeighbor(tiles, focusIdx, k);
      if (n >= 0) focus(n);
      if (e.preventDefault) e.preventDefault();
      return;
    }

    if (k === 'BACK') { dismissPopup(); dismissBanner(); }
  });

  /* ── زیرنویس و صدا ────────────────────────────────────────────────
     مهمان خارجی فیلم فارسی می‌بیند و مهمان ایرانی فیلم خارجی. هر دو
     زیرنویس می‌خواهند، و بعضی فیلم‌ها دوبله هم دارند.

     فهرست از همان پاسخی می‌آید که آدرس ویدیو را داد، پس درخواست
     اضافه‌ای به سرور نمی‌خورد. */
  function hasTracks() {
    return (trackData.subtitles && trackData.subtitles.length) ||
           (trackData.audio && trackData.audio.length);
  }

  function openTracks() {
    if (!playing || !hasTracks()) return;
    tracksOpen = true;
    buildTracks();
    TV.addClass(TV.id('tracks-screen'), 'is-on');
  }

  function closeTracks() {
    tracksOpen = false;
    TV.removeClass(TV.id('tracks-screen'), 'is-on');
  }

  function buildTracks() {
    var subs  = trackData.subtitles || [];
    var audio = trackData.audio || [];
    trackRows = [];

    /* گزینه‌ی خاموش همیشه اول — مهمانی که زیرنویس نمی‌خواهد نباید
       دنبال راه خاموش‌کردنش بگردد. */
    var subHtml = '';
    if (subs.length) {
      subHtml += trackRow('sub', -1, 'خاموش',
                          TV.tracks.currentSubtitle(curVideo, hlsInst) === -1);
      var cur = TV.tracks.currentSubtitle(curVideo, hlsInst), i;
      for (i = 0; i < subs.length; i++) {
        subHtml += trackRow('sub', i,
          subs[i].label + (subs[i].is_sdh ? ' (ناشنوایان)' : ''), cur === i);
      }
      TV.show(TV.id('tracks-subs-group'));
    } else {
      TV.hide(TV.id('tracks-subs-group'));
    }
    TV.id('tracks-subs').innerHTML = subHtml;

    var audHtml = '';
    if (audio.length > 1) {
      var j;
      for (j = 0; j < audio.length; j++) {
        audHtml += trackRow('aud', j, audio[j].label, !!audio[j]._active);
      }
      TV.show(TV.id('tracks-audio-group'));
    } else {
      /* یک باند یعنی انتخابی در کار نیست؛ نشان‌دادن فهرست تک‌گزینه‌ای
         فقط مهمان را گیج می‌کند. */
      TV.hide(TV.id('tracks-audio-group'));
    }
    TV.id('tracks-audio').innerHTML = audHtml;

    trackRows = TV.all('.tv-track', TV.id('tracks-screen'));
    var k;
    for (k = 0; k < trackRows.length; k++) {
      (function (idx) {
        TV.on(trackRows[idx], 'click', function () { pickTrack(idx); });
      })(k);
    }
    focusTrack(0);
  }

  function trackRow(kind, idx, label, active) {
    return '<div class="tv-track' + (active ? '' : ' is-off') + '"' +
             ' data-kind="' + kind + '" data-idx="' + idx + '">' +
             '<span class="tv-track-tick">' + (active ? '✓' : '') + '</span>' +
             '<span>' + TV.esc(label) + '</span>' +
           '</div>';
  }

  function focusTrack(i) {
    if (!trackRows.length) return;
    if (i < 0) i = 0;
    if (i >= trackRows.length) i = trackRows.length - 1;
    var n;
    for (n = 0; n < trackRows.length; n++) {
      TV.toggleClass(trackRows[n], 'is-focused', n === i);
    }
    trackFocus = i;
  }

  function pickTrack(i) {
    var row = trackRows[i];
    if (!row) return;

    var kind = row.getAttribute('data-kind');
    var idx  = parseInt(row.getAttribute('data-idx'), 10);

    if (kind === 'sub') {
      TV.tracks.showSubtitle(curVideo, idx, hlsInst);
      buildTracks();          /* تیک‌ها به‌روز شوند */
      focusTrack(i);
      return;
    }

    var item = (trackData.audio || [])[idx];
    if (!item) return;

    var a;
    for (a = 0; a < trackData.audio.length; a++) {
      trackData.audio[a]._active = (a === idx);
    }

    TV.tracks.selectAudio(curVideo, item, hlsInst, function (err) {
      if (err) {
        /* مهمان باید بفهمد چرا چیزی عوض نشد */
        TV.id('tracks-audio').innerHTML =
          '<div class="tv-track is-off">تغییر صدا انجام نشد</div>';
        return;
      }
      buildTracks();
      focusTrack(i);
    });
  }

  /* ── اتصال دستگاه مهمان ───────────────────────────────────────────
     مهمان می‌خواهد گوشی یا کنسول بازی‌اش را وصل کند. چون منوی خود
     تلویزیون در اتاق قفل است، تنها راهش همین صفحه است.

     هر پلتفرم مسیر خودش را دارد و TV.inputs آن را پنهان می‌کند. اگر
     دستگاه اصلا نتواند (مثل Mi TV Stick که پورت HDMI ورودی ندارد،
     یا وقتی صفحه‌ی وب مجوز لازم را ندارد) صادقانه گفته می‌شود — نه
     دکمه‌ی بی‌اثر. */
  var inputList  = [];
  var inputTiles = [];
  var inputFocus = 0;
  var inputsOpen = false;

  function openInputs() {
    inputsOpen = true;
    clearTimeout(autoTimer);
    TV.addClass(TV.id('inputs-screen'), 'is-on');
    TV.text(TV.id('inputs-sub'), 'در حال بررسی…');
    TV.id('inputs-grid').innerHTML = '';

    if (!TV.inputs.supported()) {
      TV.text(TV.id('inputs-sub'),
        'این تلویزیون از تعویض ورودی به‌صورت خودکار پشتیبانی نمی‌کند. ' +
        'برای اتصال دستگاه، از دکمه‌ی ورودی روی ریموت استفاده کنید ' +
        'یا با پذیرش تماس بگیرید.');
      return;
    }

    TV.inputs.list(function (err, list) {
      if (err || !list || !list.length) {
        TV.text(TV.id('inputs-sub'),
          'ورودی قابل استفاده‌ای پیدا نشد. اگر دستگاه را تازه وصل ' +
          'کرده‌اید چند لحظه صبر کنید و دوباره امتحان کنید.');
        return;
      }
      inputList = list;
      TV.text(TV.id('inputs-sub'),
        'دستگاه خود را به یکی از پورت‌های پشت تلویزیون وصل کنید، ' +
        'سپس همان پورت را از اینجا انتخاب کنید.');
      buildInputs();
    });
  }

  function buildInputs() {
    var html = '', i, it, state;
    for (i = 0; i < inputList.length; i++) {
      it = inputList[i];

      /* connected === null یعنی نمی‌دانیم (روی webOS از مسیر اپ‌ها
         قابل تشخیص نیست). آنجا هیچ ادعایی نمی‌کنیم. */
      if (it.connected === true)       state = '<div class="tv-input-state is-on">دستگاه وصل است</div>';
      else if (it.connected === false) state = '<div class="tv-input-state is-off">چیزی وصل نیست</div>';
      else                             state = '';

      html +=
        '<div class="tv-input" data-idx="' + i + '">' +
          '<div class="tv-input-icon">' +
            (it.type === 'HDMI' ? '🔌' : '📺') + '</div>' +
          '<div class="tv-input-label">' + TV.esc(it.label) + '</div>' +
          state +
        '</div>';
    }
    TV.id('inputs-grid').innerHTML = html;
    inputTiles = TV.all('.tv-input', TV.id('inputs-grid'));

    for (i = 0; i < inputTiles.length; i++) {
      (function (idx) {
        TV.on(inputTiles[idx], 'click', function () { pickInput(idx); });
      })(i);
    }
    focusInput(0);
  }

  function focusInput(idx) {
    if (!inputTiles.length) return;
    if (idx < 0) idx = 0;
    if (idx >= inputTiles.length) idx = inputTiles.length - 1;
    var i;
    for (i = 0; i < inputTiles.length; i++) {
      TV.toggleClass(inputTiles[i], 'is-focused', i === idx);
    }
    inputFocus = idx;
  }

  function pickInput(idx) {
    var it = inputList[idx];
    if (!it) return;
    TV.text(TV.id('inputs-sub'), 'در حال تغییر به ' + it.label + '…');

    TV.inputs.switchTo(it, function (err) {
      if (err) {
        TV.text(TV.id('inputs-sub'),
          'تغییر به ' + it.label + ' انجام نشد. از دکمه‌ی ورودی روی ' +
          'ریموت استفاده کنید یا با پذیرش تماس بگیرید.');
        return;
      }
      /* اگر موفق شد، تلویزیون از این صفحه بیرون می‌رود و چیزی برای
         نشان‌دادن نمی‌ماند؛ ولی اگر برگشت، صفحه نباید روی «در حال
         تغییر…» گیر کند. */
      closeInputs();
    });
  }

  function closeInputs() {
    inputsOpen = false;
    TV.removeClass(TV.id('inputs-screen'), 'is-on');
    focus(focusIdx);
    scheduleAutoPlay();
  }

  TV.on(TV.id('inputs-back'), 'click', closeInputs);

  /* ── اتاق و پیام‌ها ────────────────────────────────────────────── */
  function loadRoom() {
    if (!SCREEN_CODE) return;
    TV.get(ORIGIN + '/api/v1/player/room-info/' + SCREEN_CODE, function (err, d) {
      if (err || !d || !d.success || !d.data) return;
      var info = d.data;

      var badge = TV.id('room-badge');
      if (info.room_number) {
        TV.text(TV.id('rb-number'), 'اتاق ' + info.room_number);
        TV.text(TV.id('rb-guest'), info.guest_name || info.room_name || '');
        TV.addClass(badge, 'is-on');
      } else {
        TV.removeClass(badge, 'is-on');
      }

      if (info.guest_name) {
        TV.text(TV.id('welcome-title'), 'خوش آمدید، ' + info.guest_name);
      }

      if (info.messages && info.messages.length) showMessages(info.messages);
    });
  }

  var MSG_ICON = { welcome: '🌟', info: '💡', urgent: '⚠️', promo: '🎁', custom: '📢' };
  var MSG_COLOR = { welcome: '#32d17a', info: '#4098db', urgent: '#ff5f57',
                    promo: '#f5b13d', custom: '#a78bfa' };

  function showMessages(msgs) {
    var banners = [], popups = [], tickers = [], i, m;
    for (i = 0; i < msgs.length; i++) {
      m = msgs[i];
      if (m.display_mode === 'ticker') tickers.push(m.title ? m.title + '؛ ' + m.body : m.body);
      else if (m.display_mode === 'popup' && !seenPopup[m.id])  popups.push(m);
      else if (m.display_mode === 'banner' && !seenBanner[m.id]) banners.push(m);
    }
    if (tickers.length) setTicker(tickers);
    if (popups.length)       showPopup(popups[0]);
    else if (banners.length) showBanner(banners[0]);
  }

  var bannerTimer = null;

  function showBanner(m) {
    var col = MSG_COLOR[m.msg_type] || '#4098db';
    var box = TV.id('msg-banner-inner');
    box.style.background  = col + '22';
    box.style.borderColor = col + '55';
    TV.text(TV.id('mb-icon'), MSG_ICON[m.msg_type] || '💡');

    var t = TV.id('mb-title');
    if (m.title) { TV.text(t, m.title); t.style.display = 'block'; }
    else         { t.style.display = 'none'; }

    TV.text(TV.id('mb-body'), m.body);
    TV.addClass(TV.id('msg-banner'), 'is-on');
    seenBanner[m.id] = 1;

    clearTimeout(bannerTimer);
    bannerTimer = setTimeout(dismissBanner, 12000);
  }

  function dismissBanner() {
    clearTimeout(bannerTimer);
    TV.removeClass(TV.id('msg-banner'), 'is-on');
  }

  var popupTimer = null;

  function showPopup(m) {
    var col = MSG_COLOR[m.msg_type] || '#4098db';
    TV.text(TV.id('mp-icon'), MSG_ICON[m.msg_type] || '💡');
    TV.text(TV.id('mp-title'), m.title || '');
    TV.text(TV.id('mp-body'), m.body);
    TV.id('mp-card').style.borderColor = col + '55';
    TV.addClass(TV.id('msg-popup'), 'is-on');
    seenPopup[m.id] = 1;

    clearTimeout(popupTimer);
    popupTimer = setTimeout(dismissPopup, 30000);
  }

  function dismissPopup() {
    clearTimeout(popupTimer);
    TV.removeClass(TV.id('msg-popup'), 'is-on');
  }

  TV.on(TV.id('mb-close'), 'click', dismissBanner);
  TV.on(TV.id('mp-close'), 'click', dismissPopup);

  /* ── پلتفرم ────────────────────────────────────────────────────── */
  var PLATFORM = (function () {
    var ua = (navigator.userAgent || '').toLowerCase();
    if (ua.indexOf('webos') !== -1 || ua.indexOf('web0s') !== -1) return 'webos';
    if (ua.indexOf('tizen') !== -1 || ua.indexOf('smart-tv') !== -1) return 'tizen';
    if (ua.indexOf('android') !== -1) return 'android';
    if (ua.indexOf('electron') !== -1) return 'windows';
    return 'browser';
  })();

  /* ── ضربان ─────────────────────────────────────────────────────
     فاصله را سرور تعیین می‌کند: ۳۰۰ تلویزیون با فاصله‌ی ثابت ۱۵ ثانیه
     ۲۰ درخواست در ثانیه می‌سازند و مدیر باید بتواند بدون تغییر اپِ
     تلویزیون‌ها بار را کم کند. */
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
      { version: '2.0', screen_type: 'iptv', platform: PLATFORM },
      function (err, d) {
        if (err || !d || !d.data) {
          /* سرور در دسترس نیست — کندتر تلاش کن تا وقتی برگشت،
             ۳۰۰ تلویزیون همزمان به آن هجوم نیاورند. */
          scheduleHeartbeat(60);
          return;
        }
        runCommands(d.data.commands);
        scheduleHeartbeat(d.data.sync_interval || 30);
      });
  }

  /* فرمان‌های مخصوص پورتال. بقیه (open_url، clear_cache، volume،
     message، reboot) را TV.runCommands می‌گیرد — یک‌جا برای همه‌ی
     صفحه‌ها، تا دوباره از هم دور نیفتند. ack هم آنجاست. */
  function portalCommand(name, p) {
    if (name === 'reload' || name === 'refresh') {
      clearTimeout(autoTimer);
      loadMenu();
      return true;
    }

    /* تعویض کانال از پنل: اگر آیتمی با همان شماره در منو باشد، بازش
       کن. DeviceService این فرمان را برای webOS و Tizen مجاز می‌داند
       ولی تا امروز هیچ‌جا اجرا نمی‌شد. */
    if (name === 'channel') {
      var no = Number(p.number || p.channel);
      if (!no || isNaN(no)) return true;
      var i;
      for (i = 0; i < items.length; i++) {
        if (Number(items[i].channel_no) === no) { select(i); return true; }
      }
      return true;
    }
    return false;
  }

  function runCommands(list) {
    TV.runCommands(list, ORIGIN, SCREEN_CODE, portalCommand);
  }

  /* ── WebSocket ─────────────────────────────────────────────────── */
  function connectWS() {
    if (typeof WebSocket === 'undefined') return;   /* Tizen 2.3 گاهی ندارد */
    var ws;
    try {
      ws = new WebSocket('ws://' + location.hostname + ':' + WS_PORT);
    } catch (e) { return; }

    ws.onopen = function () {
      try {
        ws.send(JSON.stringify({ type: 'subscribe', channel: 'screen_' + SCREEN_CODE }));
      } catch (e2) {}
    };
    ws.onmessage = function (ev) {
      var m;
      try { m = JSON.parse(ev.data); } catch (e3) { return; }
      if (m.type === 'reload') loadMenu();
      if (m.type === 'reboot') location.reload();
    };
    ws.onclose = function () { setTimeout(connectWS, 5000); };
    ws.onerror = function () { try { ws.close(); } catch (e4) {} };
  }

  /* ── شروع ──────────────────────────────────────────────────────── */
  loadMenu();
  loadRoom();
  connectWS();

  /* پخش تصادفی اولین درخواست‌ها: بدون این، بعد از قطعی برقِ هتل کل
     ۳۰۰ تلویزیون همزمان بوت می‌شوند و در یک لحظه به سرور می‌زنند. */
  var jitter = Math.floor(Math.random() * 10000);
  setTimeout(heartbeat, jitter);
  setInterval(loadRoom, 30000 + jitter);

<?php endif; ?>
})();
</script>
</body>
</html>
