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
<?php /* ── چرا یک کلاس جدا برای «ویدیو روی صفحه است» ──────────────────
        روی Orsay/Maple ویدیو را خودِ سخت‌افزار روی یک «پلنِ ویدیو»
        رمزگشایی می‌کند که *زیرِ* تمام لایهٔ HTML می‌نشیند. مرورگر
        صفحه را روی آن سوار می‌کند. پس هر پیکسلِ ماتی که روی ناحیهٔ
        ویدیو کشیده شود — از جمله پس‌زمینهٔ خودِ body — تصویر را
        می‌پوشاند و فقط صدا می‌ماند. دقیقا همان «صدا می‌آمد ولی تصویر
        سیاه بود».
        پس تا وقتی ویدیو پخش می‌شود body شفاف می‌شود تا پلن دیده شود،
        و به محض رفتن به تصویر یا متن دوباره سیاه می‌شود (وگرنه سر
        تعویض اسلاید سفیدی دیده می‌شود). */ ?>
body,html{width:100%;height:100%;overflow:hidden;background:#000;}
html.on-video,html.on-video body,html.on-video #c{background:transparent;}
#c{position:relative;width:100%;height:100%;}
/* opacity مستقیم ست می‌شود؛ transition خودش انیمیت می‌کند (بدون classList) */
<?php /* overflow پنهان لازم است: در حالت «پر کردن صفحه» ویدیو عمدا
        بزرگ‌تر از صحنه می‌شود و باید همین‌جا بریده شود، نه اینکه روی
        تیکر و لوگو بیفتد. */ ?>
/* پس‌زمینه عمدا شفاف است، نه مشکی. اسلاید تازه روی قبلی محو می‌شود؛
   اگر خودش هم مشکی باشد، در میانهٔ انیمیشن نیمی از تصویر قبلی را
   می‌پوشاند و سر هر تعویض یک تیرگیِ کوتاه دیده می‌شود. سیاهی از body
   می‌آید که همیشه زیر همه است. */
.slide{position:absolute;top:0;left:0;width:100%;height:100%;opacity:0;background:transparent;overflow:hidden;
       -webkit-transition:opacity .5s;transition:opacity .5s;}
/* تصویر با background-size:cover — چون object-fit روی Maple نیست */
.slide-img{width:100%;height:100%;background-repeat:no-repeat;background-position:center center;
           -webkit-background-size:cover;background-size:cover;}
<?php /* `position` و `z-index` عمدا اینجا نیست. دادنِ z-index به ویدیو
        روی این نسل یک «لایهٔ ترکیب‌شدهٔ HTML» می‌سازد و مرورگر تلاش
        می‌کند قاب‌ها را خودش بکشد؛ نتیجه تصویرِ سیاه با صدای سالم
        است. ویدیو باید در جریان عادی بماند تا پلنِ سخت‌افزاری کار
        خودش را بکند. به همین دلیل هم `.slide-bg` دیگر زیر ویدیو
        گذاشته نمی‌شود — روی پلنِ سخت‌افزاری «زیر» وجود ندارد. */ ?>
.slide video{width:100%;height:100%;display:block;background:transparent;}
.slide iframe{width:100%;height:100%;border:0;}
<?php /* اگر تنظیماتِ صفحه متنی نداشته باشد پنهان شروع می‌شود، ولی متنِ
        پلی‌لیست که رسید startTicker خودش نمایانش می‌کند.

        z-index صریح لازم است: زیرنویس و ساعت و لوگو داخل #c و *قبل از*
        اسلایدها در DOM هستند، پس اسلایدِ ویدیو که position دارد رویشان
        می‌افتاد و زیرنویس زیر ویدیو گم می‌شد. */ ?>
#ticker{position:absolute;z-index:60;bottom:0;left:0;width:100%;height:40px;background:rgba(0,0,0,.8);overflow:hidden;<?= $ticker?'':'display:none;'?>}
<?php /* فاصله‌ی پیش از تکرار متن تیکر را JS از عرض واقعی صفحه ست
        می‌کند؛ عدد ثابت روی صفحه‌ی باریک‌تر شکاف بی‌جا می‌ساخت. */ ?>
#tick{position:absolute;top:0;height:40px;white-space:nowrap;font:600 19px/40px Arial,sans-serif;color:#fff;}
#logo{position:absolute;z-index:60;<?= $posMap[$logoPos]??$posMap['bottom-right'] ?>;opacity:.85;<?= $logoUrl?'':'display:none;'?>}
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
#clock{position:absolute;z-index:60;bottom:54px;right:14px;padding:7px 16px;background:rgba(0,0,0,.55);border-radius:8px;font:700 28px/1 monospace;color:#fff;<?= $clk?'':'display:none;'?>}
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

/* overlayها (لوگو، آب‌وهوا، ساعت، تیکر) را دوباره نمایان می‌کند.
   وقتی پلن سخت‌افزاری ویدیو تمام‌صفحه می‌شود و برمی‌گردد، بعضی
   مدل‌های Orsay استایل عناصر را دست‌نخورده نگه می‌دارند ولی بعضی
   ری‌پینت لازم دارند؛ این یک no-op بی‌ضرر روی مدل‌های سالم است. */
function reassertOverlays() {
  var ids = ['brand', 'wx', 'clock', 'ticker'];
  for (var i = 0; i < ids.length; i++) {
    var e = document.getElementById(ids[i]);
    if (!e) continue;
    /* فقط آن‌هایی که محتوا دارند دوباره نشان داده می‌شوند — ساعت و
       تیکر خودشان با display:none شروع می‌شوند اگر خاموش باشند. */
    if (e.getAttribute('data-off') === '1') continue;
    if (e.style.display === 'none' && e.getAttribute('data-shown') !== '1') continue;
    var z = e.style.zIndex;
    e.style.zIndex = '';
    e.style.zIndex = z || '60';
  }
}

/* جلوگیری از تمام‌صفحهٔ بومیِ پلیر سخت‌افزاری. علت باگِ گزارش‌شده:
   دوبار EXIT روی ریموت، ویدیو را روی پلنِ سخت‌افزاری تمام‌صفحه
   می‌کرد و overlayهای HTML زیرش گم می‌شدند. */
function guardFullscreen(v) {
  if (!v) return;
  /* ── چرا سقف تلاش ────────────────────────────────────────────────
     پلیر سخت‌افزاری سامسونگ گاهی بعد از هر بار بافر شدن دوباره وارد
     تمام‌صفحه می‌شود. نسخهٔ قبل بی‌قیدوشرط هر بار بیرونش می‌آورد، و
     روی تابلوی واقعی این تبدیل به یک کشمکش می‌شد: ورود، خروج، ورود…
     و هر خروج یک پرش سیاه روی تصویر می‌گذاشت («هر چند ثانیه صفحه
     سیاه می‌شود»). حالا حداکثر سه بار و با فاصلهٔ دست‌کم دو ثانیه
     تلاش می‌شود؛ اگر تلویزیون اصرار داشت، تصویر تمام‌صفحه بماند
     بهتر از چشمک‌زدن دائمی است (فقط overlayها زیر آن پنهان می‌شوند). */
  var tries = 0, lastTry = 0;
  function exitFs() {
    var now = (new Date()).getTime();
    if (tries >= 3 || (now - lastTry) < 2000) { reassertOverlays(); return; }
    tries++; lastTry = now;
    try { if (v.webkitExitFullscreen)      v.webkitExitFullscreen(); } catch (e) {}
    try { if (v.webkitExitFullScreen)      v.webkitExitFullScreen(); } catch (e) {}
    try { if (document.webkitExitFullscreen) document.webkitExitFullscreen(); } catch (e) {}
    reassertOverlays();
  }
  /* نام رویداد روی وبکیت تلویزیون‌ها فرق می‌کند؛ به همه گوش می‌دهیم */
  if (v.addEventListener) {
    v.addEventListener('webkitbeginfullscreen', exitFs, false);
    v.addEventListener('webkitfullscreenchange', reassertOverlays, false);
    v.addEventListener('fullscreenchange', reassertOverlays, false);
  }
  /* صفت‌هایی که ورود خودکار به تمام‌صفحه را مهار می‌کنند */
  try { v.setAttribute('webkit-playsinline', ''); } catch (e) {}
  try { v.setAttribute('playsinline', ''); } catch (e) {}
  try { v.controls = false; } catch (e) {}
  try { v.removeAttribute('controls'); } catch (e) {}
}

/* WebKit 537.42 روی Maple ویدیو را با عرض/ارتفاع درصدی مقیاس نمی‌دهد؛
   آن را در اندازه‌ی ذاتی فایل می‌کشد. یک کلیپ 848×480 روی صحنه‌ی
   1280×720 حدود یک‌سومِ صفحه را خالی می‌گذاشت. پس اندازه را همیشه
   به پیکسل و از خودِ صحنه می‌دهیم، هم روی style هم روی attribute
   (این نسخه attribute را ترجیح می‌دهد). */
function sizeVideo(v, item) {
  if (!v) return;
  var c = document.getElementById('c');
  var w = (c && c.offsetWidth)  || screen.width  || 1280;
  var h = (c && c.offsetHeight) || screen.height || 720;

  /* ── پر کردن صفحه ───────────────────────────────────────────
     ویدیو را بزرگ‌تر از صحنه می‌کنیم تا کوچک‌ترین بُعدش صحنه را
     بپوشاند، و با حاشیه‌ی منفی وسط‌چین می‌کنیم؛ اسلاید overflow
     پنهان دارد پس اضافه بریده می‌شود.

     ابعاد از سرور می‌آید نه از خود ویدیو: `videoWidth` روی موتور
     ماپل غلط است (برای فایل ۸۴۸×۴۸۰ مقدار ۸۴۸×۸۴۸ داد). اگر سرور
     هم ابعاد نداشته باشد — رسانه‌ی قدیمی که probe نشده — به حالت
     «جا شدن کامل» برمی‌گردیم. بدون این بازگشت، با نسبت حدسی تصویر
     بد بریده می‌شد و کسی نمی‌فهمید چرا. */
  var mw = item ? parseInt(item.media_width  || 0, 10) : 0;
  var mh = item ? parseInt(item.media_height || 0, 10) : 0;
  /* ── چرا پیش‌فرض دیگر contain نیست ───────────────────────────────
     تابلوی تبلیغاتی باید پر شود؛ نوار سیاه دور ویدیو روی تابلوی هتل
     مثل خرابی دیده می‌شود. روی سرور واقعی هیچ آیتمی `fit_mode` را
     صریح ست نکرده بود و ابعاد رسانه هم به پلیر نمی‌رسید، پس هر
     ویدیو letterbox می‌شد — همان «تمام‌صفحه نیست» که گزارش شد.
     حالا پیش‌فرض پر کردن است و اپراتور می‌تواند برای یک آیتم خاص
     (مثلا پوستری که نباید بریده شود) `contain` را انتخاب کند. */
  var fit = item && item.fit_mode ? item.fit_mode : 'cover';

  /* ── چیدنِ ویدیو، همیشه وسط‌چین و با مقیاسِ پلن ────────────────────
     هر دو حالت (پر کردن و جا شدن) از یک جا می‌گذرند تا PLANE_SCALE
     جایی از قلم نیفتد. قبلا حالت بازگشتی اصلا وسط‌چین نمی‌شد و فقط
     عرض و ارتفاع می‌گرفت؛ با مقیاس، همان یعنی ویدیوی چسبیده به
     گوشهٔ بالا-چپ. */
  function place(tw, th) {
    tw = Math.ceil(tw * PLANE_SCALE);
    th = Math.ceil(th * PLANE_SCALE);
    v.setAttribute('width',  tw);
    v.setAttribute('height', th);
    v.style.width      = tw + 'px';
    v.style.height     = th + 'px';
    v.style.position   = 'absolute';
    v.style.left       = '50%';
    v.style.top        = '50%';
    v.style.marginLeft = (-Math.round(tw / 2)) + 'px';
    v.style.marginTop  = (-Math.round(th / 2)) + 'px';
  }

  if (fit === 'cover' && mw > 0 && mh > 0) {
    var scale = Math.max(w / mw, h / mh);
    place(mw * scale, mh * scale);
    return;
  }

  place(w, h);
}

/* ── پس‌زمینهٔ پشت ویدیو: روی این نسل ممکن نیست ───────────────────
   اینجا تا امروز لایه‌ای ساخته می‌شد تا نوارهای خالیِ کنارِ ویدیویی
   که نسبتش با صفحه یکی نیست پر شود. روی Orsay/Maple اصلا شدنی نیست:
   ویدیو روی پلنِ سخت‌افزاری *زیرِ* کل لایهٔ HTML رمزگشایی می‌شود، پس
   چنین لایه‌ای زیر ویدیو نمی‌رود بلکه رویش می‌افتد و تصویر را
   می‌خورد — همان تابلوی سیاه با صدای سالم. نوار خالی حالا سیاهِ خودِ
   تلویزیون است. مقدارها نگه داشته می‌شوند چون /playlist می‌فرستدشان
   و پروفایل‌های دیگر (مرورگرهای تازه) از آن‌ها استفاده می‌کنند. */
var BACKDROP = null;   // از /playlist می‌آید
var VIDEO_FIT = 'fit';

/* هر بار که این صفحه عوض می‌شود این عدد هم باید عوض شود — در ضربان
   گزارش می‌شود و تنها راه فهمیدن اینکه تلویزیون کد تازه را گرفته یا
   نسخه‌ی کش‌شده‌ی خودش را اجرا می‌کند. */
<?php
/* ── شناسهٔ نسخهٔ کدی که روی دستگاه اجرا می‌شود ────────────────────
   این مقدار دست‌نویس بود و با هر اصلاح به‌روز نمی‌شد. نتیجه: دستگاه
   در ضربان نسخهٔ ماه‌ها پیش را گزارش می‌کرد و من یک بار نزدیک بود
   اشتباه نتیجه بگیرم که تلویزیون کد تازه را نگرفته. حالا به فایل
   VERSION گره خورده، پس هر استقرار خودش عوضش می‌کند. */
$__build = @file_get_contents(ROOT_PATH . '/VERSION');
$__build = $__build !== false && trim($__build) !== '' ? trim($__build) : '0';

/* ── مقیاس پلنِ ویدیو ─────────────────────────────────────────────
   روی بعضی Orsayها بومِ مرورگر ۱۲۸۰×۷۲۰ است ولی پنل ۱۹۲۰×۱۰۸۰؛
   لایهٔ HTML را خودِ تلویزیون بزرگ می‌کند (پس تصویرها درست پر
   می‌شوند) ولی پلنِ سخت‌افزاریِ ویدیو مختصات را پیکسلِ فیزیکی
   می‌گیرد. حاصل: ویدیو وسطِ صفحه با نوار سیاه دور تا دور — همان
   «از هر طرف ۲۰ سانت مشکی».
   جاوااسکریپت اندازهٔ واقعی پنل را نمی‌بیند (screen.width هم ۱۲۸۰
   می‌گوید)، پس عدد حدس‌زدنی نیست و باید تنظیم‌شدنی باشد. ۱ یعنی
   دست‌نزن؛ برای پنل Full HD با بومِ ۷۲۰p مقدار ۱٫۵ درست است. */
$__pscale = isset($settings['plane_scale']) ? (float)$settings['plane_scale'] : 1.0;
if ($__pscale < 1 || $__pscale > 3) $__pscale = 1.0;
?>
var PAGE_BUILD  = <?= json_encode($__build) ?>;
var PLANE_SCALE = <?= json_encode($__pscale) ?>;

var SERVER = window.location.origin;
var CODE   = '<?= e($screen['code']??'') ?>';
var __plSig = null;   /* امضای پلی‌لیستِ در حال پخش */
var pl = [], ci = 0, tm = null, curSlide = null;
/* نگهبانِ پیشرفتِ ویدیو. بیرون از play() است تا با عوض‌شدن آیتم حتما
   خاموش شود؛ نگهبانِ آیتمِ رفته اگر زنده بماند آیتمِ تازه را رد می‌کند. */
var progTimer = null;
function stopProg() { if (progTimer) { clearInterval(progTimer); progTimer = null; } }
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
     قبلی، دو حلقه هم‌زمان حرکت را می‌نویسند و نوار تند و پرشی می‌شود. */
  if (_tickTimer) { clearInterval(_tickTimer); _tickTimer = null; }

  /* ── چرا دیگر setInterval(16ms) نیست ───────────────────────────────
     نسخهٔ قبل ۶۰ بار در ثانیه `left` را از جاوااسکریپت می‌نوشت. تغییر
     `left` چیدمان را دوباره حساب می‌کند (reflow) و روی موتور ماپلِ
     تلویزیون — همان سخت‌افزاری که این پروفایل برایش نوشته شده — این
     کار با دیکود ویدیو بر سر یک هسته رقابت می‌کرد. نتیجه روی تابلوی
     واقعی: پرش و کندی پخش.

     حالا حرکت با انیمیشن CSS روی `transform` است: مرورگر آن را روی
     لایهٔ ترکیب‌بندی اجرا می‌کند، بدون reflow و بدون کار جاوااسکریپت.
     اگر این موتور انیمیشن CSS نداشته باشد، به همان تایمر برمی‌گردیم
     ولی با فاصلهٔ ۳۳ میلی‌ثانیه (۳۰ فریم) که نصف بار قبلی است. */
  var dist  = t.offsetWidth;
  var speed = 90;                                  // پیکسل در ثانیه
  var secs  = Math.max(8, Math.round(dist / speed));
  var st    = t.style;
  var canCss = ('webkitAnimationName' in st) || ('animationName' in st);

  if (canCss) {
    t.style.left = '0px';
    /* کلید انیمیشن یک‌بار ساخته می‌شود؛ ساختن دوباره‌اش در هر
       به‌روزرسانی متن، استایل‌شیت را بی‌دلیل بزرگ می‌کند. */
    if (!document.getElementById('tick-kf')) {
      var sEl = document.createElement('style');
      sEl.id = 'tick-kf';
      sEl.appendChild(document.createTextNode(
        '@-webkit-keyframes tickmove{from{-webkit-transform:translateX(0)}' +
        'to{-webkit-transform:translateX(-' + Math.round(dist / 2) + 'px)}}' +
        '@keyframes tickmove{from{transform:translateX(0)}' +
        'to{transform:translateX(-' + Math.round(dist / 2) + 'px)}}'
      ));
      (document.head || document.getElementsByTagName('head')[0]).appendChild(sEl);
    }
    st.webkitAnimation = 'tickmove ' + secs + 's linear infinite';
    st.animation       = 'tickmove ' + secs + 's linear infinite';
    return;
  }

  var pos = 0;
  t.style.left = '0px';
  _tickTimer = setInterval(function(){
    pos -= 3;
    if (pos < -t.offsetWidth / 2) pos = 0;
    t.style.left = pos + 'px';
  }, 33);
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
        /* overlayهای پنجره‌دار استودیو — هر بار با پلی‌لیست تازه از نو
           بار می‌شوند تا پنجره‌ی حذف‌شده روی تابلو نماند. */
        if (window.TVOV) TVOV.load(d.data.overlays);
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

/* بدون classList: opacity را مستقیم ست می‌کنیم و transition انیمیت می‌کند.
 *
 * ترتیب مهم است. نسخهٔ پیشین هر دو را با فاصلهٔ ۵۰ms شروع می‌کرد، یعنی
 * اسلاید قبلی پیش از آن‌که تازه کاملا ظاهر شود محو می‌شد. حاصلش این بود
 * که جمعِ دو opacity مدتی حدود ۵۰٪ می‌ماند و زیرشان سیاهیِ صفحه دیده
 * می‌شد: دقیقا همان «چند ثانیه یک بار صفحه مشکی می‌شود» که گزارش شد.
 * حالا اول تازه ظاهر می‌شود، و تنها پس از پایان محوشدنش قبلی کنار
 * می‌رود. انیمیشن ۰٫۵ ثانیه است، پس ۵۰۰ms صبر می‌کنیم. */
function swapSlide(newDiv) {
  var c = document.getElementById('c');
  c.appendChild(newDiv);
  setTimeout(function(){ newDiv.style.opacity = '1'; }, 20);
  if (curSlide) {
    var old = curSlide;
    setTimeout(function(){
      old.style.opacity = '0';
      setTimeout(function(){
        /* ویدیوی اسلایدِ رفته باید صریحا متوقف شود. اگر فقط از DOM
           برداشته شود، روی این نسل دیکودر گاهی ادامه می‌دهد و روی پلن
           سخت‌افزاری تصویرِ قبلی را نگه می‌دارد — همان چیزی که به‌شکل
           «صفحه سیاه / تصویر گیرکرده» دیده می‌شد. */
        var vs = old.getElementsByTagName ? old.getElementsByTagName('video') : [];
        for (var k = 0; k < vs.length; k++) {
          try { vs[k].pause(); } catch (e1) {}
          try { vs[k].removeAttribute('src'); vs[k].load(); } catch (e2) {}
        }
        if (old.parentNode) old.parentNode.removeChild(old);
      }, 600);
    }, 520);
  }
  curSlide = newDiv;
}

function play(i) {
  clearTimeout(tm);
  stopProg();
  if (!pl.length) return;

  /* ساعت overlay را با شروع هر آیتم هم‌تراز کن. offset از جمع مدت
     آیتم‌های پیشین می‌آید — همان مبنایی که استودیو به‌کار برد. بی این،
     بافر شدن ویدیو کم‌کم ساعت را عقب می‌اندازد. */
  if (window.TVOV) {
    var _off = 0, _k, _d;
    for (_k = 0; _k < i && _k < pl.length; _k++) {
      _d = Number(pl[_k].duration);
      _off += (!_d || isNaN(_d) || _d < 1) ? 10 : _d;
    }
    TVOV.sync(_off);
  }
  var item = pl[i];
  var src  = fixUrl(item.file_url || item.src || item.file_path || item.url || '');
  var type = item.type || 'image';
  var dur  = (item.duration || 10) * 1000;
  var div  = makeSlide();

  /* آیتمی که نه نوع دارد نه آدرس، پیش‌تر به شاخهٔ iframe می‌افتاد و یک
     مستطیل سیاهِ خالی روی تابلو می‌گذاشت — تا نوبت آیتم بعد برسد. روی
     پلی‌لیست واقعی همین هتل، آیتم شمارهٔ ۳۳ دقیقا این‌طور بود (type و
     file_path هر دو خالی). مستقیم رد می‌شود. */
  if (!src && type !== 'dynamic') { setTimeout(nextItem, 50); return; }

  /* پیش‌فرض هر آیتم: صفحهٔ مات و سیاه. فقط شاخهٔ ویدیو پایین‌تر آن را
     شفاف می‌کند تا پلنِ سخت‌افزاری دیده شود. اگر اینجا پاک نشود، روی
     تصویر و متن هم شفاف می‌ماند و حاشیه‌ها سفید دیده می‌شود. */
  document.documentElement.className = '';

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
    /* subtitle track for this media item. Deliberately not <track>:
       the Orsay profile runs on WebKit 534 which has no TextTrack, so
       cues are rendered from VTT by tv-subtitles.js instead and every
       profile behaves the same way. */
    if (window.TVSUB) TVSUB.attach(vid, item.subtitles);
    vid.setAttribute('autoplay', '');
    vid.setAttribute('playsinline', '');
    vid.setAttribute('webkit-playsinline', '');
    vid.setAttribute('preload', 'auto');

    /* ── صدا ─────────────────────────────────────────────────────
       تا امروز `muted = true` ثابت در کد بود، پس هیچ تابلویی صدا
       نداشت و راهی هم برای روشن کردنش نبود. حالا از آیتم می‌آید و
       پیش‌فرضش همان بی‌صدا است تا تابلوهای در حال کار یک‌باره صدادار
       نشوند.

       اگر مرورگر تلویزیون پخش خودکارِ صدادار را رد کند، ویدیو اصلا
       شروع نمی‌شود و تابلو سیاه می‌ماند — که از بی‌صدا بودن خیلی
       بدتر است. پس چند لحظه بعد بررسی می‌شود و در صورت نخواندن،
       بی‌صدا دوباره تلاش می‌کند. */
    var wantSound = item && (item.muted === 0 || item.muted === '0' || item.muted === false);
    vid.muted = !wantSound;
    if (!wantSound) vid.setAttribute('muted', '');

    var vol = item ? parseInt(item.volume, 10) : 100;
    if (!isNaN(vol) && vol >= 0 && vol <= 100) {
      try { vid.volume = vol / 100; } catch (e) {}
    }

    if (wantSound) {
      setTimeout(function () {
        /* هنوز تکان نخورده یعنی پخش خودکارِ صدادار رد شده */
        if (vid.paused || vid.currentTime === 0) {
          vid.muted = true;
          tvPlay(vid);
        }
      }, 1500);
    }

    vid.loop  = false;
    vid.onended = function() { stopProg(); nextItem(); };
    vid.onerror = function() { stopProg(); setTimeout(nextItem, 1000); };
    /* ── تمام‌صفحه: جلوگیری، نه بیرون‌کشیدن ─────────────────────
       تا امروز فقط «اگر وارد شد بیرونش بیاور» بود. روی Orsay آن‌قدر
       دیر جواب می‌داد که تلویزیون یک قاب کامل سیاه نشان می‌داد و صدای
       پخش هم قطع می‌شد؛ چون کاربر «ویدیو تمام‌صفحه نمی‌شود» می‌دید در
       حالی که پلیر سخت‌افزاری واقعا وارد شده بود و بعد بیرون آمده بود.
       راه درست این است که اصلا وارد نشود: ویدیو با `playsinline` و
       بی‌کنترل می‌ماند و پلیر خودِ اپ (پلن HTML) همان است که دیده
       می‌شود. guardFullscreen فقط تور ایمنی برای وقتی است که پلیر
       سخت‌افزاری به‌هرحال بالا بیاید.

       مهم: `controls` و پخش خودکار تمام‌صفحه را هم خاموش می‌کنیم؛ روی
       این نسل، ویدیوی دارای صدا اغلب همین دو را بهانه می‌کند. */
    vid.setAttribute('controls', 'false');
    try { vid.controls = false; } catch (e) {}
    vid.setAttribute('disablePictureInPicture', '');
    vid.setAttribute('x-webkit-airplay', 'deny');
    guardFullscreen(vid);
    /* پس‌زمینه قبل از ویدیو به اسلاید اضافه می‌شود تا در همان فریم
       اول زیرش باشد — اگر بعد اضافه شود، لحظه‌ی اول نوار سیاه دیده
       می‌شود و روی تابلو همان لحظه به چشم می‌آید. */
    /* ── هیچ لایه‌ای زیر ویدیو کشیده نمی‌شود ────────────────────
       پیش‌تر اینجا یک پس‌زمینه (رنگ/تصویر/لوگو) ساخته می‌شد تا نوارهای
       خالیِ بالا و پایین را پر کند. روی Orsay این کار خودِ تصویر را
       می‌کشت: پس‌زمینه در لایهٔ HTML است و لایهٔ HTML *روی* پلنِ
       سخت‌افزاری می‌نشیند، پس نه «زیرِ» ویدیو می‌رفت، بلکه رویش. نتیجه
       همان تابلوی سیاه با صدای سالم بود. نوارهای خالی حالا سیاهِ خودِ
       تلویزیون‌اند — که درست و بی‌هزینه است. */
    document.documentElement.className = 'on-video';

    /* ── تعویض با اولین فریم واقعی ──────────────────────────────
       تا امروز اسلاید ویدیو بلافاصله swap می‌شد، پیش از آن‌که حتی یک
       فریم رمزگشایی شده باشد. روی Maple این یعنی مکث سیاهِ کوتاه سرِ
       هر ویدیو: اسلاید قبلی محو شده و پلن سخت‌افزاری هنوز چیزی برای
       نشان دادن ندارد. حالا اسلاید تازه تا `playing` اول به‌صورت
       نامرئی بالای قبلی می‌نشیند و فقط با اولین فریم ظاهر می‌شود؛ اگر
       ویدیو پخش نشد، همان قبلی روی صفحه می‌ماند.

       بررسی ۲٫۵ ثانیه‌ای برای فایل خراب است: اگر `playing` نیامد،
       بی‌صدا یک بار دیگر تلاش می‌شود و در نهایت رد می‌شود. */
    var swapped = false;
    function revealIt() {
      if (swapped) return;
      swapped = true;
      swapSlide(div);
    }
    vid.onplaying = function() { sizeVideo(vid, item); revealIt(); };
    vid.oncanplay = function() { sizeVideo(vid, item); tvPlay(vid); };
    /* ابعاد ذاتی تا loadedmetadata معلوم نیست، ولی چون اندازه را از
       صحنه می‌گیریم نه از فایل، می‌شود از همان اول هم ست کرد. */
    vid.onloadedmetadata = function() { sizeVideo(vid, item); };
    sizeVideo(vid, item);
    vid.src = src;

    div.appendChild(vid);
    sizeVideo(vid, item);
    /* ── چرا load() صدا زده نمی‌شود ───────────────────────────────
       `src` همین چند خط بالاتر ست شده و مرورگر خودش بارگذاری را شروع
       کرده است. صدا زدن `load()` بعد از آن، عنصر را **ریست** می‌کند و
       هرچه بافر شده دور ریخته می‌شود — شروعِ کندتر و یک بافر دورریخته
       در هر اسلاید. نمایش اسلاید را `revealIt()` بالاتر، وقتی پخش
       واقعا شروع شد، انجام می‌دهد؛ پس اینجا فقط نباید بارگذاری را از
       صفر دوباره شروع کنیم. */
    tvPlay(vid);

    /* ── نگهبان: بر پایهٔ پیشرفتِ واقعی، نه رویداد ──────────────────
       نسخهٔ پیشین بعد از ۲٫۵ ثانیه، اگر رویداد `playing` نیامده بود،
       ویدیو را «خراب» می‌شمرد: بی‌صدا می‌کرد و ۱٫۵ ثانیه بعد می‌پرید
       به کلیپ بعدی. ولی این نسل سامسونگ آن رویداد را عملا نمی‌فرستد،
       پس نگهبان روی ویدیوهای کاملا سالم هم شلیک می‌کرد. روی تابلو
       همان «۲ ثانیه تصویر، بعد سیاه، بعد کلیپ بعدی» دیده می‌شد.

       تنها سیگنالِ قابل‌اعتماد روی ماپل خودِ `currentTime` است: اگر
       جلو می‌رود، ویدیو واقعا پخش می‌شود — هر رویدادی هم که نیاید.
       پس نگهبان فقط وقتی دست به کار می‌شود که زمان **هیچ** تکانی
       نخورده باشد. */
    var lastT = -1, stuck = 0;
    progTimer = setInterval(function () {
      var t = 0;
      try { t = vid.currentTime || 0; } catch (e) {}

      /* اولین کسری از ثانیه یعنی فریم واقعی روی پلن نشسته است */
      if (t > 0.05) revealIt();

      if (t > lastT + 0.02) { lastT = t; stuck = 0; return; }

      stuck++;
      /* ۵ ثانیه بی‌حرکت: شاید پخشِ خودکارِ صدادار رد شده — بی‌صدا یک
         تلاش دیگر. */
      if (stuck === 10) {
        try { vid.muted = true; } catch (e) {}
        if (vid.paused) tvPlay(vid);
      }
      /* ۱۲ ثانیه بی‌حرکت: فایل واقعا باز نمی‌شود، رد کن. این آستانه
         عمدا بلند است؛ کوتاهش همان باگی بود که اصلاح شد. */
      if (stuck >= 24) { stopProg(); revealIt(); nextItem(); }
    }, 500);

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

/* ── آزمون پلنِ ویدیو، از راه دور ─────────────────────────────────
   «صدا می‌آید ولی تصویر سیاه است» دو علتِ کاملا متفاوت دارد و از
   بیرون قابل تفکیک نیستند:
     ۱) لایهٔ HTML روی پلنِ سخت‌افزاری ویدیو افتاده و تصویر را پوشانده
     ۲) پروفایل H.264 فایل برای دیکودرِ این نسل سنگین است
   این تابع یک ویدیوی واقعیِ همین پلی‌لیست را چهار حالت پخش می‌کند و
   شمارهٔ حالت را درشت می‌نویسد. اپراتور فقط نگاه می‌کند و می‌گوید در
   کدام شماره‌ها تصویر داشت — همین جواب را قطعی می‌کند.

   از صف فرمان می‌آید، نه از آدرس: تلویزیون روی صفحهٔ پیش‌فرض خودش
   می‌ماند و کسی لازم نیست سرِ دستگاه برود و URL عوض کند. */
function runPlaneTest() {
  var src = '';
  for (var i = 0; i < pl.length; i++) {
    var s = pl[i].file_url || pl[i].src || pl[i].file_path || pl[i].url || '';
    if ((pl[i].type === 'video') || String(s).match(/\.(mp4|webm|ogv|mov)(\?|$)/i)) { src = fixUrl(s); break; }
  }

  /* پخش عادی باید کامل بخوابد، وگرنه اسلایدِ بعدی وسط آزمون می‌آید و
     پس‌زمینه‌اش دوباره پلن را می‌پوشاند و نتیجه بی‌معنا می‌شود. */
  clearTimeout(tm);
  stopProg();
  var c = document.getElementById('c');
  if (c) {
    var vs = c.getElementsByTagName('video');
    for (var k = 0; k < vs.length; k++) { try { vs[k].pause(); } catch (e) {} }
    c.style.display = 'none';
  }

  var ov = document.createElement('div');
  /* خودِ این لایه باید شفاف بماند؛ ماتی هر حالت از پس‌زمینهٔ html
     می‌آید. اگر اینجا رنگ بگذاریم، هر چهار حالت سیاه می‌شود. */
  ov.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;z-index:9998;background:transparent;';
  var lbl = document.createElement('div');
  lbl.style.cssText = 'position:absolute;top:0;right:0;z-index:9999;background:rgba(0,0,0,.72);'
    + 'color:#fff;font:700 30px/1.5 Tahoma,Arial,sans-serif;padding:10px 22px;';
  var vid = document.createElement('video');
  vid.setAttribute('muted', ''); vid.muted = true;
  vid.setAttribute('playsinline', ''); vid.setAttribute('webkit-playsinline', '');
  vid.setAttribute('autoplay', ''); vid.loop = true;
  vid.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;display:block;';
  ov.appendChild(vid); ov.appendChild(lbl);
  document.body.appendChild(ov);

  if (!src) { lbl.innerHTML = 'در پلی‌لیست این صفحه هیچ ویدیویی نیست'; setTimeout(hardReload, 20000); return; }

  var MODES = [
    { n: '۱', cls: '',                   layer: true,  t: 'مثل قبل: پس‌زمینه مات + لایه روی ویدیو' },
    { n: '۲', cls: 'on-video',           layer: true,  t: 'پس‌زمینه شفاف، ویدیو لایه‌دار' },
    { n: '۳', cls: '',                   layer: false, t: 'پس‌زمینه مات، ویدیو بدون لایه' },
    { n: '۴', cls: 'on-video',           layer: false, t: 'پس‌زمینه شفاف، بدون لایه ← اصلاحیه' }
  ];
  var m = -1;
  function step() {
    m++;
    if (m >= MODES.length) {
      lbl.innerHTML = 'پایان آزمون — تا چند لحظه به پخش عادی برمی‌گردد';
      setTimeout(hardReload, 4000);
      return;
    }
    var M = MODES[m];
    document.documentElement.className = M.cls;
    /* همان z-index که علت احتمالی است: در حالت‌های «لایه‌دار» گذاشته
       می‌شود تا با نبودش مقایسه شود. */
    vid.style.position = M.layer ? 'relative' : 'absolute';
    vid.style.zIndex   = M.layer ? '2' : '';
    lbl.innerHTML = 'حالت ' + M.n + ' — ' + M.t;
    /* سورس هر بار از نو ست می‌شود: عوض‌کردن استایلِ ویدیویی که در حال
       پخش است روی این نسل هیچ اثری ندارد. */
    try { vid.pause(); } catch (e) {}
    vid.src = src;
    try { vid.load(); } catch (e) {}
    tvPlay(vid);
    setTimeout(step, 9000);
  }
  setTimeout(step, 600);
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

      /* پیام و تبلیغ: سرور همیشه می‌فرستاد و این پروفایل دورش می‌ریخت */
      if (window.TVMSG) TVMSG.enqueue(d.data && d.data.messages);

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
        /* عیب‌یابی از راه دور: تلویزیون روی آدرس پیش‌فرضش می‌ماند و
           آزمون از همین‌جا اجرا می‌شود. */
        if (n==='diag_plane') runPlaneTest();
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
  /* EXIT / RETURN / BACK — روی Orsay همین کلیدها بودند که با دوبار
     زدن، پلیرِ سخت‌افزاری را تمام‌صفحه می‌کردند و لوگو و آب‌وهوا را
     می‌پوشاندند. جلوی رفتار پیش‌فرض گرفته می‌شود و به‌جایش overlayها
     دوباره نمایان و از تمام‌صفحه خارج می‌شویم. فعال‌سازی (صفحهٔ act)
     دست‌نخورده می‌ماند. */
  if (e.keyCode===10009 || e.keyCode===88 || e.keyCode===461 || e.keyCode===27 || e.keyCode===8) {
    if (!document.getElementById('act')) {
      if (e.preventDefault)  e.preventDefault();
      if (e.stopPropagation) e.stopPropagation();
      var vids = document.getElementsByTagName('video');
      for (var i = 0; i < vids.length; i++) {
        try { if (vids[i].webkitExitFullscreen) vids[i].webkitExitFullscreen(); } catch (ex) {}
      }
      reassertOverlays();
      return false;
    }
  }
});

/* اگر با هر مسیری — کلید دیگر، منوی تلویزیون — باز هم تمام‌صفحه شد،
   به‌محض برگشت overlayها را بازمی‌گردانیم. */
if (document.addEventListener) {
  document.addEventListener('webkitfullscreenchange', reassertOverlays, false);
  document.addEventListener('fullscreenchange', reassertOverlays, false);
}

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
<script src="/assets/js/tv-messages.js"></script>
<script src="/assets/js/tv-subtitles.js"></script>
<script src="/assets/js/tv-overlays.js"></script>
</body>
</html>
