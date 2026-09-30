<?php
/**
 * Hotel Media Player — Samsung Orsay/Maple Profile (نسل ۲۰۱۳، پیش از Tizen)
 *
 * چرا جدا از samsung_tv.php:
 *   تلویزیون‌های هتلیِ خانواده‌ی HG..AE690 (ProductCap = Y2013) موتور Maple
 *   روی WebKit ~۵۳۴ دارند، نه Tizen WebEngine. سه فرقِ مرگبار با پروفایل Tizen:
 *     • object-fit از WebKit 537 آمده — اینجا نیست. برای همین تصویر با
 *       background-size:cover (که از WebKit 527 هست) نمایش داده می‌شود، نه <img object-fit>.
 *     • HLS/m3u8 و udp/rtp و AVPlay نیست — فقط تصویر و MP4/WebM مستقیم.
 *     • webapis.js/AVPlay وجود ندارد — حذف شد.
 *   ES5 خالص و بدون classList (برای اطمینان opacity مستقیم ست می‌شود).
 *   مرجع تفکیک دو خانواده: docs/TV-COMPAT-SIGNAGE.md
 */
$s = json_decode($screen['settings'] ?? '{}', true) ?: [];
$ticker  = $s['ticker_text'] ?? '';
$logoUrl = $s['logo_url']    ?? '';
$logoPos = $s['logo_position'] ?? 'bottom-right';
/* پیش‌فرض روشن: کلید نبودن یعنی «تنظیم نشده»، نه «خاموش». روی
   صفحه‌های واقعی این هتل ستون settings خالی بود و با !empty هیچ
   تلویزیونی ساعت نداشت بدون اینکه کسی آن را خاموش کرده باشد. */
$clk     = !array_key_exists('show_clock', $s) || !empty($s['show_clock']);
$posMap  = ['bottom-right'=>'bottom:14px;right:14px','bottom-left'=>'bottom:14px;left:14px',
            'top-right'=>'top:14px;right:14px','top-left'=>'top:14px;left:14px'];
?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<?php /* width=device-width نه یک عدد ثابت.
        این تلویزیون‌ها رزولوشن یکسانی ندارند: همین مدل در سایت واقعی
        screen.width را ۱۲۸۰ گزارش می‌کند، نه ۱۹۲۰. با چیدمان ثابتِ
        ۱۹۲۰ روی صفحه‌ی ۱۲۸۰ و overflow:hidden، فقط گوشه‌ی بالا-چپِ
        تصویر دیده می‌شود و ویدیو بریده به نظر می‌رسد. */ ?>
<meta name="viewport" content="width=device-width">
<title>Hotel Media — Samsung Orsay</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
/* درصد به‌جای پیکسل ثابت — چیدمان با هر رزولوشنی که تلویزیون
   گزارش کند جور درمی‌آید. درصد از WebKit خیلی قدیمی هم کار می‌کند. */
body,html{width:100%;height:100%;overflow:hidden;background:#000;}
#c{position:relative;width:100%;height:100%;}
/* opacity مستقیم ست می‌شود؛ transition خودش انیمیت می‌کند (بدون classList) */
.slide{position:absolute;top:0;left:0;width:100%;height:100%;opacity:0;background:#000;
       -webkit-transition:opacity .5s;transition:opacity .5s;}
/* تصویر با background-size:cover — چون object-fit روی Maple نیست */
.slide-img{width:100%;height:100%;background-repeat:no-repeat;background-position:center center;
           -webkit-background-size:cover;background-size:cover;}
<?php /* ویدیو پس‌زمینه‌ی خودش را ندارد: اگر سیاه باشد، نوارهای خالیِ
        بالا-پایین را خودش سیاه می‌کند و پس‌زمینه‌ی تابلو هیچ‌وقت دیده
        نمی‌شود. زیرش .slide-bg است که آن نوارها را پر می‌کند. */ ?>
.slide video{width:100%;height:100%;display:block;background:transparent;position:relative;z-index:2;}
<?php /* لایه‌ی پس‌زمینه — پشت ویدیو، به اندازه‌ی کل اسلاید */ ?>
.slide-bg{position:absolute;top:0;left:0;width:100%;height:100%;z-index:1;}
.slide iframe{width:100%;height:100%;border:0;}
<?php /* اگر تنظیماتِ صفحه متنی نداشته باشد پنهان شروع می‌شود، ولی متنِ
        پلی‌لیست که رسید startTicker خودش نمایانش می‌کند. */ ?>
#ticker{position:absolute;bottom:0;left:0;width:100%;height:40px;background:rgba(0,0,0,.8);overflow:hidden;<?= $ticker?'':'display:none;'?>}
<?php /* فاصله‌ی پیش از تکرار متن تیکر را JS از عرض واقعی صفحه ست
        می‌کند؛ عدد ثابت روی صفحه‌ی باریک‌تر شکاف بی‌جا می‌ساخت. */ ?>
#tick{position:absolute;top:0;height:40px;white-space:nowrap;font:600 19px/40px Arial,sans-serif;color:#fff;}
#logo{position:absolute;<?= $posMap[$logoPos]??$posMap['bottom-right'] ?>;opacity:.85;<?= $logoUrl?'':'display:none;'?>}
#logo img{width:120px;}
<?php /* لوگوی پلی‌لیست همیشه بالا-راست می‌نشیند و پس‌زمینه ندارد؛
        روی تصویر روشن هم باید خوانا بماند، پس یک سایه‌ی نرم دارد.
        z-index بالاتر از اسلایدهاست تا با عوض شدن تصویر نپرد. */ ?>
#brand{position:absolute;top:14px;right:14px;z-index:60;display:none;}
#brand img{width:110px;display:block;
           -webkit-filter:drop-shadow(0 2px 6px rgba(0,0,0,.55));}
<?php /* دمای هوا: بالا-چپ، شفاف. عمدا بدون کادر توپر — روی تیزر
        تبلیغاتی یک مستطیل مشکی مثل خرابی دیده می‌شود. */ ?>
#wx{position:absolute;top:14px;left:14px;z-index:60;display:none;
    color:#fff;font-family:Tahoma,Arial,sans-serif;
    text-shadow:0 2px 6px rgba(0,0,0,.8),0 0 2px rgba(0,0,0,.9);
    -webkit-transition:opacity .6s;transition:opacity .6s;opacity:0;}
#wx-t{font-size:34px;font-weight:700;line-height:1;}
#wx-s{font-size:15px;margin-top:4px;opacity:.9;}
<?php /* ساعت از بالا-راست به پایین-راست رفت: جای بالا-راست حالا لوگوی
        همیشگی است و دو عنصر روی هم می‌افتادند. بالای نوار تیکر
        می‌نشیند تا وقتی تیکر روشن است پشتش پنهان نشود. */ ?>
<?php /* ساعت پیش‌فرض روشن است. قبلا فقط با کلیدِ show_clock در تنظیماتِ
        صفحه روشن می‌شد و آن تنظیمات روی صفحه‌های واقعی هتل خالی بود،
        پس عملا هیچ تلویزیونی ساعت نداشت. برای خاموش کردنش باید
        show_clock صراحتا false باشد. */ ?>
#clock{position:absolute;bottom:54px;right:14px;padding:7px 16px;background:rgba(0,0,0,.55);border-radius:8px;font:700 28px/1 monospace;color:#fff;<?= $clk?'':'display:none;'?>}
<?php /* تابلوی پرواز — با table نه flex: روی WebKit ماپل flex نسخه‌ی
        قدیمی است و ستون‌ها جابه‌جا می‌شوند. واحدها درصدی‌اند تا روی
        ۱۲۸۰ و ۱۹۲۰ هر دو درست بنشیند. */ ?>
<?php /* اندازه‌ها em هستند و font-size پایه را JS از عرض واقعی صحنه ست
        می‌کند. عمدا vw استفاده نشده: واحد viewport روی این نسل ماپل
        تضمین‌شده نیست و اگر پشتیبانی نشود، متن به اندازه‌ی پیش‌فرض
        مرورگر می‌افتد و جدول روی تلویزیون ریز و ناخوانا می‌شود. */ ?>
.fb{width:100%;height:100%;background:#071018;padding:3% 4%;color:#e2e8f0;
    font-family:Tahoma,Arial,sans-serif;font-size:20px;}
.fb-h{font-size:1.7em;font-weight:700;color:#fff;margin-bottom:2%;
      border-bottom:2px solid rgba(255,255,255,.18);padding-bottom:1.2%;}
.fb-tb{width:100%;border-collapse:collapse;font-size:1.05em;}
.fb-tb td{padding:1.1% 0.6%;border-bottom:1px solid rgba(255,255,255,.08);
          white-space:nowrap;overflow:hidden;}
.fb-t{font-weight:700;color:#fff;width:16%;}
.fb-new{color:#f59e0b;margin-right:8px;font-size:.8em;}
.fb-n{width:14%;color:#93c5fd;}
.fb-a{width:28%;}
.fb-lg{width:1em;height:1em;vertical-align:middle;margin-left:8px;}
.fb-c{width:18%;color:#fff;}
.fb-s{width:24%;text-align:left;}
#act{position:absolute;top:0;left:0;width:100%;height:100%;background:#09090f;text-align:center;}
#act-box{position:absolute;top:50%;left:50%;margin:-160px 0 0 -190px;width:380px;background:#111;border-radius:16px;padding:40px;}
#act-inp{font:700 28px/1 monospace;letter-spacing:12px;padding:14px;width:100%;background:#0d0d14;border:2px solid rgba(26,122,196,.4);border-radius:12px;color:#fff;text-align:center;text-transform:uppercase;}
#act-btn{width:100%;margin-top:14px;padding:15px;font-size:17px;background:#1a7ac4;color:#fff;border:0;border-radius:12px;cursor:pointer;}
#act-msg{font-size:13px;margin-top:12px;min-height:20px;color:#ef4444;}
</style>
</head>
<body>
<div id="c">
  <?php if (($screen['status']??'') !== 'active'): ?>
  <div id="act">
    <div id="act-box">
      <div style="font-size:40px;margin-bottom:12px;">📺</div>
      <div style="font-size:20px;font-weight:700;color:#fff;margin-bottom:6px;">Hotel Media</div>
      <div style="font-size:12px;color:#64748b;margin-bottom:20px;">کد فعال‌سازی را وارد کنید</div>
      <div style="font-size:13px;color:#94a3b8;margin-bottom:14px;font-family:monospace;"><?= e($screen['code']??'') ?></div>
      <input id="act-inp" type="text" maxlength="6" placeholder="______">
      <button id="act-btn" onclick="doActivate()">فعال‌سازی</button>
      <div id="act-msg"></div>
    </div>
  </div>
  <?php else: ?>
  <div id="logo"><?= $logoUrl ? '<img src="'.e($logoUrl).'" alt="" onerror="this.parentNode.style.display=\'none\'">' : '' ?></div>
  <?php /* لوگوی پلی‌لیست — منبعش از /playlist می‌آید، پس اینجا خالی
           ساخته می‌شود و JS پرش می‌کند. onerror مخفی‌اش می‌کند تا یک
           آدرس خراب، آیکن شکسته‌ی گوشه‌ی تابلو نشود. */ ?>
  <div id="brand"><img id="brand-img" src="" alt=""></div>
  <div id="wx"><div id="wx-t">--°</div><div id="wx-s"></div></div>
  <div id="clock">--:--</div>
  <div id="ticker"><div id="tick"><?= e($ticker).'&nbsp;&nbsp;&nbsp;&nbsp;'.e($ticker) ?></div></div>
  <?php endif; ?>
</div>

<script>
/* ES5 خالص — Maple/WebKit 534. نه const/let، نه arrow، نه template literal،
   نه fetch/Promise، نه classList. */
/* reload() روی مرورگر این تلویزیون‌ها می‌تواند همان صفحه را از کش
   داخلی خودش بردارد — سرور هم no-store می‌فرستد ولی مرورگرِ ماپل
   همیشه رعایتش نمی‌کند. با یک پارامتر یکتا، آدرس دیگری می‌شود و
   ناچار از سرور می‌گیرد. */
function hardReload() {
  var u = window.location.pathname + '?_bound=1&r=' +
          (new Date().getTime()) + '' + Math.floor(Math.random() * 1000);
  window.location.href = u;
}

function tvPlay(el) {
  if (!el || !el.play) return;
  try { el.play(); } catch (e) {}
}

/* WebKit 537.42 روی Maple ویدیو را با عرض/ارتفاع درصدی مقیاس نمی‌دهد؛
   آن را در اندازه‌ی ذاتی فایل می‌کشد. یک کلیپ 848×480 روی صحنه‌ی
   1280×720 حدود یک‌سومِ صفحه را خالی می‌گذاشت. پس اندازه را همیشه
   به پیکسل و از خودِ صحنه می‌دهیم، هم روی style هم روی attribute
   (این نسخه attribute را ترجیح می‌دهد). */
function sizeVideo(v) {
  if (!v) return;
  var c = document.getElementById('c');
  var w = (c && c.offsetWidth)  || screen.width  || 1280;
  var h = (c && c.offsetHeight) || screen.height || 720;
  v.setAttribute('width',  w);
  v.setAttribute('height', h);
  v.style.width  = w + 'px';
  v.style.height = h + 'px';
}

/* ── پس‌زمینه‌ی پشت ویدیو ────────────────────────────────────────
   ویدیویی که نسبت تصویرش با صفحه یکی نیست، نوار خالی می‌گذارد. این
   لایه آن نوار را پر می‌کند.

   عمدا هیچ‌جا به videoWidth تکیه نمی‌شود: روی موتور ماپل آن عدد
   غلط است (روی تلویزیون واقعی همین هتل برای یک فایل ۱۶:۹ مقدار
   1280x1280 گزارش شد)، پس هر محاسبه‌ای بر پایه‌ی آن روی همان
   تلویزیون‌هایی می‌شکند که این پروفایل برایشان نوشته شده. پس‌زمینه
   به اندازه‌ی ویدیو کاری ندارد و همیشه درست است. */
var BACKDROP = null;   // از /playlist می‌آید
var VIDEO_FIT = 'fit';

function applyBackdrop(div) {
  if (!BACKDROP || BACKDROP.mode === 'black') return;

  var bg = document.createElement('div');
  bg.className = 'slide-bg';

  var c = document.getElementById('c');
  var w = (c && c.offsetWidth) || screen.width || 1280;

  if (BACKDROP.mode === 'color') {
    bg.style.backgroundColor = BACKDROP.color || '#000000';

  } else if (BACKDROP.mode === 'image') {
    bg.style.backgroundImage    = 'url("' + BACKDROP.image + '")';
    bg.style.backgroundRepeat   = 'no-repeat';
    bg.style.backgroundPosition = 'center center';
    /* cover تا تصویر پس‌زمینه خودش نوار خالی نسازد */
    bg.style.webkitBackgroundSize = 'cover';
    bg.style.backgroundSize       = 'cover';

  } else if (BACKDROP.mode === 'logo') {
    /* لوگوی تکرارشونده. اندازه‌ی کاشی از عرض صفحه حساب می‌شود، نه
       عدد ثابت — روی ۱۲۸۰ و ۱۹۲۰ هر دو باید یک‌جور دیده شود.

       رنگ روی خودِ اسلاید می‌نشیند نه روی این لایه: opacity کل لایه را
       محو می‌کند و اگر رنگ هم همین‌جا بود، رنگ پس‌زمینه هم کم‌رنگ
       می‌شد و نوار خالی دوباره خاکستریِ بی‌رنگ می‌شد. */
    var tile = Math.max(60, Math.round(w / 9));
    div.style.backgroundColor = BACKDROP.color || '#0b1220';
    bg.style.backgroundImage  = 'url("' + BACKDROP.image + '")';
    bg.style.backgroundRepeat = 'repeat';
    bg.style.webkitBackgroundSize = tile + 'px auto';
    bg.style.backgroundSize       = tile + 'px auto';
    /* کم‌رنگ، وگرنه پس‌زمینه با خودِ آگهی رقابت می‌کند */
    bg.style.opacity = 0.18;
  }

  div.appendChild(bg);
}

/* هر بار که این صفحه عوض می‌شود این عدد هم باید عوض شود — در ضربان
   گزارش می‌شود و تنها راه فهمیدن اینکه تلویزیون کد تازه را گرفته یا
   نسخه‌ی کش‌شده‌ی خودش را اجرا می‌کند. */
var PAGE_BUILD = 'orsay-2026-09-30-e';

var SERVER = window.location.origin;
var CODE   = '<?= e($screen['code']??'') ?>';
var __plSig = null;   /* امضای پلی‌لیستِ در حال پخش */
var pl = [], ci = 0, tm = null, curSlide = null;
var _serverOffset = 0;

// ─── Server time ─────────────────────────────────────────────
function syncTime() {
  var x = new XMLHttpRequest();
  x.open('GET', SERVER + '/api/v1/time', true);
  x.onload = function() {
    try { var d=JSON.parse(x.responseText); if(d.success) _serverOffset=d.timestamp*1000-Date.now(); } catch(e) {}
  };
  x.send();
}
<?php /* این بلوک دیگر پشت شرط PHP نیست. قبلا اگر تنظیماتِ صفحه در
        لحظه‌ی رندر خالی بود، خودِ تابعِ ساعت داخل صفحه چاپ نمی‌شد و
        هیچ کدی در زمان اجرا نمی‌توانست ساعت را روشن کند — روشن‌کردنش
        نیاز به ویرایش دیتابیس و بارگذاری دوباره‌ی صفحه داشت. */ ?>
function updateClock() {
  var d = new Date(Date.now() + _serverOffset);
  var h=d.getHours(), m=d.getMinutes(), s=d.getSeconds();
  var el=document.getElementById('clock');
  if(el) el.textContent=(h<10?'0':'')+h+':'+(m<10?'0':'')+m+':'+(s<10?'0':'')+s;
}
setInterval(syncTime, 300000);
setInterval(updateClock, 1000);
updateClock();

// ─── Ticker ───────────────────────────────────────────────────
<?php /* مثل ساعت، این هم دیگر پشت شرط PHP نیست. متن زیرنویس حالا از
        پلی‌لیست می‌آید و ممکن است بعد از رندر صفحه برسد؛ با شرط PHP،
        حلقه‌ی حرکت اصلا داخل صفحه چاپ نمی‌شد و متن بی‌حرکت می‌ماند —
        بدون هیچ خطایی، فقط یک نوار ساکن. */ ?>
var _tickTimer = null;

function startTicker() {
  var t = document.getElementById('tick');
  var w = document.getElementById('ticker');
  if (!t || !w) return;

  /* متن خالی یعنی نواری برای نشان دادن نیست */
  if (!t.innerHTML) { w.style.display = 'none'; return; }

  /* نمایان کردن باید قبل از اندازه‌گیری باشد: نوار با display:none
     رندر می‌شود و فرزندِ عنصر پنهان همیشه offsetWidth صفر دارد. با
     ترتیب برعکس، شرطِ محافظ خودش نوار را برای همیشه پنهان نگه
     می‌داشت — بدون هیچ خطایی. */
  w.style.display = 'block';

  /* حالا که در جریان چیدمان است، اندازه معنا دارد */
  if (t.offsetWidth === 0) { w.style.display = 'none'; return; }

  /* فاصله‌ی پیش از تکرار = یک عرض صفحه، هرچقدر که هست. با عدد ثابت
     ۱۹۲۰ روی تلویزیون ۱۲۸۰ یک شکاف خالیِ طولانی وسط تیکر می‌افتاد. */
  var vw = document.getElementById('c').offsetWidth || 1280;
  t.style.paddingRight = vw + 'px';

  /* اگر متن عوض شود این دوباره صدا زده می‌شود؛ بدون پاک‌کردن تایمر
     قبلی، دو حلقه هم‌زمان left را می‌نویسند و نوار تند و پرشی می‌شود. */
  if (_tickTimer) clearInterval(_tickTimer);

  var pos = 0;
  t.style.left = '0px';
  _tickTimer = setInterval(function(){
    pos -= 1.5;
    if (pos < -t.offsetWidth / 2) pos = 0;
    t.style.left = pos + 'px';
  }, 16);
}

startTicker();

// ─── URL fix ─────────────────────────────────────────────────
function fixUrl(u) {
  if (!u) return '';
  return u.replace(/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/g, SERVER);
}

// ─── Playlist ─────────────────────────────────────────────────
function loadPlaylist() {
  var x = new XMLHttpRequest();
  x.open('GET', SERVER + '/api/v1/screens/' + CODE + '/playlist', true);
  x.timeout = 10000;
  x.onload = function() {
    try {
      var d = JSON.parse(x.responseText);
      if (d.success && d.data && d.data.items && d.data.items.length) {
        pl = d.data.items; ci = 0;
        applyBrand(d.data.brand);
        play(0);
      } else { setTimeout(loadPlaylist, 30000); }
    } catch(e) { setTimeout(loadPlaylist, 15000); }
  };
  x.ontimeout = x.onerror = function() { setTimeout(loadPlaylist, 15000); };
  x.send();
}

/* ── برند تابلو: لوگو، زیرنویس، دما ─────────────────────────────
   هر سه از پلی‌لیست می‌آیند نه از تنظیمات صفحه، چون هتل بیش از یک
   تابلو دارد و متن یا لوگوی هرکدام می‌تواند فرق کند. اگر پلی‌لیست
   چیزی نداده باشد، همان مقدارِ تنظیماتِ صفحه که PHP رندر کرده سر
   جایش می‌ماند. */
function applyBrand(b) {
  if (!b) return;

  if (b.backdrop) BACKDROP = b.backdrop;
  if (b.video_fit) VIDEO_FIT = b.video_fit;

  if (b.logo) {
    var bi = document.getElementById('brand-img');
    var bx = document.getElementById('brand');
    if (bi && bx) {
      bi.onerror = function() { bx.style.display = 'none'; };
      bi.onload  = function() { bx.style.display = 'block'; };
      bi.src = b.logo;
    }
  }

  if (b.ticker_text) {
    var t = document.getElementById('tick');
    var w = document.getElementById('ticker');
    if (t && w) {
      /* دو نسخه پشت سر هم تا حلقه‌ی متن شکاف نداشته باشد — همان
         کاری که رندر PHP هم می‌کند. */
      t.innerHTML = TV_esc(b.ticker_text) + '&nbsp;&nbsp;&nbsp;&nbsp;'
                  + TV_esc(b.ticker_text);
      /* حرکت را از نو راه می‌اندازد — نمایش دادنِ تنها کافی نیست،
         نوارِ ساکن همان چیزی است که تا حالا دیده می‌شد. */
      startTicker();
    }
  }

  if (b.weather && b.weather.enabled && b.weather.data) startWeather(b.weather);
}

/* ES5 و بدون وابستگی: سه کاراکتر خطرناک HTML کافی است چون متن فقط
   داخل یک div متنی می‌نشیند. */
function TV_esc(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/* دما هر «every» ثانیه ظاهر و «show» ثانیه دیده می‌شود.
   محوشدن با opacity است نه display، تا روی تیزر ناگهانی نپرد. */
function startWeather(w) {
  var box = document.getElementById('wx');
  if (!box) return;

  var d = w.data;
  document.getElementById('wx-t').innerHTML = Math.round(d.temp) + '°';
  document.getElementById('wx-s').innerHTML =
    TV_esc((d.label ? d.label + ' · ' : '') + (d.city || ''));

  box.style.display = 'block';

  var showMs  = Math.max(10, w.show  || 60)  * 1000;
  var everyMs = Math.max(30, w.every || 240) * 1000;
  /* «هر ۴ دقیقه، ۱ دقیقه» یعنی فاصله‌ی بین دو ظهور ۴ دقیقه است،
     پس خوابِ بین آن‌ها everyMs منهای مدت نمایش است. */
  var gapMs   = Math.max(5000, everyMs - showMs);

  function hide() { box.style.opacity = 0; setTimeout(show, gapMs); }
  function show() { box.style.opacity = 1; setTimeout(hide, showMs); }

  show();
}

function makeSlide() {
  var div = document.createElement('div');
  div.className = 'slide';
  return div;
}

/* بدون classList: opacity را مستقیم ست می‌کنیم و transition انیمیت می‌کند */
function swapSlide(newDiv) {
  var c = document.getElementById('c');
  c.appendChild(newDiv);
  setTimeout(function(){ newDiv.style.opacity = '1'; }, 50);
  if (curSlide) {
    var old = curSlide;
    setTimeout(function(){
      old.style.opacity = '0';
      setTimeout(function(){ if(old.parentNode) old.parentNode.removeChild(old); }, 600);
    }, 50);
  }
  curSlide = newDiv;
}

function play(i) {
  clearTimeout(tm);
  if (!pl.length) return;
  var item = pl[i];
  var src  = fixUrl(item.file_url || item.src || item.file_path || item.url || '');
  var type = item.type || 'image';
  var dur  = (item.duration || 10) * 1000;
  var div  = makeSlide();

  // ─ IMAGE ─ (background-size:cover به‌جای object-fit)
  if (type === 'image' || src.match(/\.(jpg|jpeg|png|gif|webp)(\?|$)/i)) {
    // پیش‌بارگذاری تا موقع سوییچ، فریمِ خالی دیده نشود
    var pre = new Image();
    pre.onerror = function() { setTimeout(nextItem, 500); };
    pre.onload  = function() {
      var box = document.createElement('div');
      box.className = 'slide-img';
      box.style.backgroundImage = 'url("' + src + '")';
      div.appendChild(box);
      swapSlide(div);
    };
    pre.src = src;
    tm = setTimeout(nextItem, dur);

  // ─ VIDEO ─ فقط MP4/WebM مستقیم. HLS/udp/rtp روی Maple پخش نمی‌شود.
  } else if (type === 'video' || src.match(/\.(mp4|webm|ogv|mov)(\?|$)/i)) {
    // پروتکل‌ها/فرمت‌های ناسازگار را رد کن تا صفحه سیاه نماند
    if (src.match(/\.m3u8(\?|$)/i) || src.indexOf('udp://') === 0 || src.indexOf('rtp://') === 0) {
      setTimeout(nextItem, 500);
      return;
    }
    var vid = document.createElement('video');
    vid.setAttribute('autoplay', '');
    vid.setAttribute('muted', '');
    vid.setAttribute('playsinline', '');
    vid.setAttribute('webkit-playsinline', '');
    vid.setAttribute('preload', 'auto');
    vid.muted = true;
    vid.loop  = false;
    vid.onended = nextItem;
    vid.onerror = function() { setTimeout(nextItem, 1000); };
    /* پس‌زمینه قبل از ویدیو به اسلاید اضافه می‌شود تا در همان فریم
       اول زیرش باشد — اگر بعد اضافه شود، لحظه‌ی اول نوار سیاه دیده
       می‌شود و روی تابلو همان لحظه به چشم می‌آید. */
    applyBackdrop(div);

    vid.oncanplay = function() { sizeVideo(vid); tvPlay(vid); };
    /* ابعاد ذاتی تا loadedmetadata معلوم نیست، ولی چون اندازه را از
       صحنه می‌گیریم نه از فایل، می‌شود از همان اول هم ست کرد. */
    vid.onloadedmetadata = function() { sizeVideo(vid); };
    sizeVideo(vid);
    vid.src = src;

    div.appendChild(vid);
    sizeVideo(vid);
    swapSlide(div);
    try { vid.load(); } catch(e) {}
    tvPlay(vid);
    setTimeout(function(){ tvPlay(vid); }, 500);

    if (dur > 0 && dur < 7200000) tm = setTimeout(nextItem, dur);

  // ─ محتوای پویا (تابلوی پرواز و مانند آن) ─
  } else if (type === 'dynamic' && item.content) {
    var html = renderDynamic(item.content);
    if (!html) { setTimeout(nextItem, 300); return; }
    div.innerHTML = html;
    sizeBoard(div);
    swapSlide(div);
    tm = setTimeout(nextItem, dur);

  // ─ IFRAME (صفحه وب) ─ best-effort روی Maple
  } else {
    var ifr = document.createElement('iframe');
    ifr.src = src;
    div.appendChild(ifr);
    swapSlide(div);
    tm = setTimeout(nextItem, dur);
  }
}

function nextItem() { ci = (ci+1) % pl.length; play(ci); }

/* ── محتوای پویا ────────────────────────────────────────────────
   فعلا فقط تابلوی پرواز. بقیه‌ی انواع (تابلوی رویداد، منو، اخبار)
   روی این نسل تلویزیون هنوز رندر ندارند و رد می‌شوند تا پخش روی یک
   صفحه‌ی خالی گیر نکند. */
/* font-size پایه‌ی تابلو از عرض واقعی صحنه — همان کاری که برای ویدیو
   هم می‌کنیم، و به همان دلیل: این تلویزیون‌ها ۱۲۸۰ گزارش می‌کنند نه
   ۱۹۲۰، و عدد ثابت روی یکی‌شان غلط است. */
function sizeBoard(div) {
  var c = document.getElementById('c');
  var w = (c && c.offsetWidth) || screen.width || 1280;
  var box = div.getElementsByTagName('div')[0];
  if (box) box.style.fontSize = Math.max(14, Math.round(w / 45)) + 'px';
}

function renderDynamic(c) {
  if (c.kind === 'flight_board') return renderFlights(c);
  return '';
}

/* جدول با table چیده می‌شود نه flex: روی WebKit ماپل flex-box نسخه‌ی
   قدیمی و ناپایدار است و ستون‌ها جابه‌جا می‌شوند. */
function renderFlights(c) {
  var list = c.flights || [], i, f, rows = '', color;
  if (!list.length) return '';

  for (i = 0; i < list.length; i++) {
    f = list[i];

    if (f.status === 'cancelled')                       color = '#ef4444';
    else if (f.status === 'delayed' || f.delay_minutes) color = '#f59e0b';
    else if (f.status === 'boarding')                   color = '#22c55e';
    else if (f.status === 'departed' || f.status === 'arrived') color = '#94a3b8';
    else                                                color = '#e2e8f0';

    rows +=
      '<tr>' +
        '<td class="fb-t">' + TV_esc(hhmm(f.scheduled_at)) +
          (f.delay_minutes
            ? '<span class="fb-new">' + TV_esc(hhmm(f.estimated_at)) + '</span>' : '') +
        '</td>' +
        '<td class="fb-n">' + TV_esc(f.flight_number) + '</td>' +
        '<td class="fb-a">' +
          (f.airline_logo
            ? '<img src="' + TV_esc(f.airline_logo) + '" class="fb-lg" onerror="this.style.display=\'none\'">'
            : '') +
          TV_esc(f.airline) +
        '</td>' +
        '<td class="fb-c">' + TV_esc(f.city) + '</td>' +
        '<td class="fb-s" style="color:' + color + '">' +
          TV_esc(f.status_text || f.status_label) +
        '</td>' +
      '</tr>';
  }

  return '<div class="fb">' +
           '<div class="fb-h">' + TV_esc(c.title) + '</div>' +
           '<table class="fb-tb">' + rows + '</table>' +
         '</div>';
}

/* «2026-09-28 18:15:00» → «18:15». عمدا با برش رشته نه Date: سازنده‌ی
   Date روی این موتور برای رشته‌ی با فاصله نتیجه‌ی NaN می‌دهد. */
function hhmm(s) {
  if (!s) return '';
  var m = String(s).match(/(\d{2}):(\d{2})/);
  return m ? m[1] + ':' + m[2] : '';
}

// ─── Heartbeat ────────────────────────────────────────────────
function heartbeat() {
  var x = new XMLHttpRequest();
  x.open('POST', SERVER+'/api/v1/screens/'+CODE+'/heartbeat', true);
  x.setRequestHeader('Content-Type', 'application/json');
  x.timeout = 8000;
  x.onload = function() {
    try {
      var d = JSON.parse(x.responseText);
      var cmds = (d.data && d.data.commands) ? d.data.commands : [];

      /* تغییر پلی‌لیست را خودِ تلویزیون تشخیص می‌دهد.
         سرور از قبل playlist_id را می‌فرستاد و هیچ پلیری نگاهش
         نمی‌کرد: اپراتور پلی‌لیست صفحه را عوض می‌کرد و تا وقتی دستی
         فرمان refresh نمی‌داد یا تلویزیون ریبوت نمی‌شد هیچ اتفاقی
         نمی‌افتاد. rev هم لازم است تا ویرایشِ آیتم‌های همان پلی‌لیست
         دیده شود، نه فقط عوض‌شدن خودش. */
      if (d.data && d.data.playlist_id != null) {
        var sig = String(d.data.playlist_id) + ':' + String(d.data.playlist_rev || '');
        if (__plSig === null)   { __plSig = sig; }
        else if (sig !== __plSig) { __plSig = sig; loadPlaylist(); }
      }
      for (var i=0; i<cmds.length; i++) {
        /* سرور کلید cmd می‌فرستد (ScreenController)، ولی صف قدیمی
           command داشت. هر دو را می‌پذیریم — تا امروز فقط command
           خوانده می‌شد و هیچ فرمانی از پنل روی این تلویزیون اجرا
           نمی‌شد، بدون اینکه جایی خطایی ثبت شود. */
        var n = cmds[i].cmd || cmds[i].command;
        var p = cmds[i].payload || cmds[i].data;

        if (n==='reload' || n==='refresh') loadPlaylist();
        if (n==='reboot') hardReload();
        if (n==='instant_media' || n==='emergency') showInstant(p);
        if (n==='clear_instant') clearInstant();
      }
    } catch(e) {}
  };
  /* اندازه‌ی واقعی را گزارش می‌کنیم، نه فقط screen.width.
     این دو روی تلویزیون یکی نیستند: screen اندازه‌ی پنل است و
     clientWidth اندازه‌ی بومِ چیدمان مرورگر. وقتی فرق کنند، چیدمانِ
     تمام‌عرض روی بومِ کوچک‌تر رسم می‌شود و بقیه‌ی پنل سیاه می‌ماند. */
  var c = document.getElementById('c');
  x.send(JSON.stringify({
    version: 'samsung-orsay',
    item: ci,
    metrics: {
      /* شناسه‌ی نسخه‌ی صفحه. بدون این نمی‌شود فهمید تلویزیون کد جدید را
         اجرا می‌کند یا نسخه‌ی قدیمی را از کش خودش — و همه‌ی تشخیص‌های
         بعدی روی همین حدس بنا می‌شود. */
      build:   PAGE_BUILD,
      /* وضعیت واقعیِ سه عنصری که دیده نمی‌شوند */
      wx:      (function(){ var e = document.getElementById('wx');
                  return e ? ((e.style.display || 'css') + '/' + (e.style.opacity === '' ? '-' : e.style.opacity)) : 'نیست'; })(),
      logo:    (function(){ var e = document.getElementById('brand-img');
                  return e ? (e.src ? 'دارد' : 'خالی') : 'نیست'; })(),
      clock:   (function(){ var e = document.getElementById('clock');
                  return e ? ((e.style.display || 'css') + '/' + (e.textContent || '-')) : 'نیست'; })(),
      /* نوار ساکن و نوار متحرک از بیرون یک‌شکل‌اند؛ left تنها چیزی است
         که فرقشان را نشان می‌دهد. */
      tick:    (function(){ var e = document.getElementById('tick');
                  var w = document.getElementById('ticker');
                  if (!e || !w) return 'نیست';
                  return (w.style.display || 'css') + '/left=' + (e.style.left || '-') +
                         '/' + (_tickTimer ? 'متحرک' : 'ساکن'); })(),
      items:   pl.length,
      types:   (function(){ var a = [], i;
                  for (i = 0; i < pl.length; i++) a.push(pl[i].type || '?');
                  return a.join(','); })(),
      screen:  screen.width + 'x' + screen.height,
      avail:   (screen.availWidth||0) + 'x' + (screen.availHeight||0),
      client:  document.documentElement.clientWidth + 'x' + document.documentElement.clientHeight,
      inner:   (window.innerWidth||0) + 'x' + (window.innerHeight||0),
      body:    document.body.offsetWidth + 'x' + document.body.offsetHeight,
      stage:   c ? (c.offsetWidth + 'x' + c.offsetHeight) : '-',
      dpr:     window.devicePixelRatio || 1,
      /* اندازه‌ی واقعیِ عنصر ویدیو در برابر اندازه‌ی ذاتی فایل — تنها
         راهِ دیدنِ اینکه sizeVideo روی خود دستگاه اثر کرده یا نه. */
      vid:     (function(){
                 var vs = document.getElementsByTagName('video');
                 var v = vs.length ? vs[vs.length-1] : null;
                 if (!v) return '-';
                 return v.offsetWidth + 'x' + v.offsetHeight +
                        ' (ذاتی ' + (v.videoWidth||0) + 'x' + (v.videoHeight||0) + ')';
               })()
    }
  }));
}
setInterval(heartbeat, 15000);

// ─── Instant broadcast ────────────────────────────────────────
var instTm = null;
function showInstant(data) {
  clearInstant();
  var ov = document.createElement('div');
  ov.id = 'inst-ov';
  ov.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;z-index:9999;background:#000;';
  var c = document.getElementById('c');

  if (data.type==='image') {
    var box = document.createElement('div');
    box.style.cssText = 'width:100%;height:100%;background-repeat:no-repeat;background-position:center center;-webkit-background-size:contain;background-size:contain;';
    box.style.backgroundImage = 'url("' + fixUrl(data.content) + '")';
    ov.appendChild(box);
  } else if (data.type==='video') {
    ov.innerHTML = '<video src="'+fixUrl(data.content)+'" autoplay muted playsinline style="background:#000;" onended="clearInstant()"></video>';
    sizeVideo(ov.getElementsByTagName('video')[0]);
  } else if (data.type==='text') {
    var t={text:'',color:'#fff',bg:'#000'};
    try { t=JSON.parse(data.content); } catch(e) { t.text=data.content; }
    ov.style.background=t.bg||'#000';
    ov.innerHTML='<div style="position:absolute;top:50%;left:0;width:100%;margin-top:-60px;font-size:80px;font-weight:900;color:'+(t.color||'#fff')+';text-align:center;padding:0 60px;">'+(t.text||'')+'</div>';
  }
  c.appendChild(ov);
  var dur=parseInt(data.duration,10)||30;
  if(dur>0) instTm=setTimeout(clearInstant, dur*1000);
}
function clearInstant() {
  clearTimeout(instTm);
  var el=document.getElementById('inst-ov');
  if(el && el.parentNode) el.parentNode.removeChild(el);
}

// ─── Activation ──────────────────────────────────────────────
function doActivate() {
  var code=(document.getElementById('act-inp').value||'').toUpperCase();
  code = code.replace(/^\s+|\s+$/g,'');
  var msg=document.getElementById('act-msg');
  if(!code||code.length!==6){msg.textContent='کد ۶ کاراکتر وارد کنید';return;}
  msg.style.color='#94a3b8'; msg.textContent='در حال اتصال...';
  var x=new XMLHttpRequest();
  x.open('POST',SERVER+'/player/activate',true);
  x.setRequestHeader('Content-Type','application/json');
  x.timeout=10000;
  x.onload=function(){
    var d={}; try{d=JSON.parse(x.responseText);}catch(e){}
    if(d.success){
      msg.style.color='#22c55e'; msg.textContent='✅ موفق!';
      // بعد از فعال‌سازی هم از سرور بگیرد، نه از کش
      setTimeout(hardReload,1200);
    } else {
      msg.style.color='#ef4444'; msg.textContent=d.message||'کد نامعتبر';
    }
  };
  x.ontimeout=x.onerror=function(){
    msg.style.color='#f59e0b'; msg.textContent='خطا: '+SERVER;
  };
  x.send(JSON.stringify({activation_code:code, screen_code:CODE}));
}

// Samsung ریموت: Enter (13) + Return (10009 روی مدل‌های جدیدتر، 88/461 روی Orsay)
document.addEventListener('keydown', function(e) {
  if (window.__tvaOwnsKeys) return;
  if (e.keyCode===13 || e.keyCode===10009) {
    var a=document.getElementById('act');
    if(a) doActivate();
  }
});

// ─── Start ────────────────────────────────────────────────────
<?php if (($screen['status']??'') === 'active'): ?>
syncTime();
loadPlaylist();
heartbeat();
<?php else: ?>
syncTime();
<?php endif; ?>
</script>
<!-- صفحه‌کلید عددی فعال‌سازی با ریموت (همه‌ی مدل‌ها) -->
<script src="/assets/js/tv-activate.js"></script>
</body>
</html>
