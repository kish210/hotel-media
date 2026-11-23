<?php
/**
 * آزمون بصری «پلنِ ویدیو» — روی خودِ تلویزیون باز می‌شود.
 *
 * چرا: روی سامسونگ ۲۰۱۳ صدای ویدیو می‌آمد ولی تصویر سیاه بود. دو علت
 * ممکن بود و از بیرون قابل تفکیک نیستند:
 *   ۱) لایهٔ HTML روی پلنِ سخت‌افزاری ویدیو افتاده و تصویر را پوشانده
 *   ۲) پروفایل H.264 فایل برای دیکودرِ این نسل سنگین است
 * این صفحه یک ویدیوی واقعی را چهار حالتِ متفاوت پخش می‌کند و شمارهٔ
 * حالت را درشت می‌نویسد. اپراتور فقط نگاه می‌کند و می‌گوید در کدام
 * شماره‌ها تصویر دید — همین جواب را قطعی می‌کند، بدون کنسول.
 *
 * @var string $code
 * @var array  $videos
 */
$v = $videos[0]['url'] ?? '';
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>آزمون پلن ویدیو</title>
<style>
  html,body{width:100%;height:100%;margin:0;padding:0;overflow:hidden;background:#000;}
  /* حالت شفاف: اگر علت پوشاندنِ پلن باشد، تصویر فقط در این حالت می‌آید */
  html.clear,html.clear body{background:transparent;}
  #v{position:absolute;top:0;left:0;width:100%;height:100%;display:block;}
  /* حالت «لایه‌دار»: همان چیزی که کد قبلی داشت */
  html.layered #v{position:relative;z-index:2;}
  #lbl{position:absolute;top:0;right:0;z-index:99;background:rgba(0,0,0,.72);
       color:#fff;font:700 30px/1.5 Tahoma,Arial,sans-serif;padding:10px 22px;}
  #hint{position:absolute;bottom:0;right:0;z-index:99;background:rgba(0,0,0,.72);
        color:#9ae6b4;font:600 20px/1.6 Tahoma,Arial,sans-serif;padding:8px 18px;}
</style>
</head>
<body>
<video id="v" autoplay muted playsinline webkit-playsinline loop></video>
<div id="lbl">آماده…</div>
<div id="hint">در کدام شماره‌ها تصویر دیدید؟</div>
<script>
/* عمدا ES5 خالص: باید روی همان موتوری اجرا شود که مشکل دارد. */
var SRC = <?= json_encode($v, JSON_UNESCAPED_SLASHES) ?>;
var v = document.getElementById('v'), lbl = document.getElementById('lbl');

var MODES = [
  { n: '۱', cls: 'layered',       t: 'مثل قبل (پس‌زمینه مات + لایه روی ویدیو)' },
  { n: '۲', cls: 'clear layered', t: 'پس‌زمینه شفاف، ویدیو لایه‌دار' },
  { n: '۳', cls: '',              t: 'پس‌زمینه مات، ویدیو بدون لایه' },
  { n: '۴', cls: 'clear',         t: 'پس‌زمینه شفاف، ویدیو بدون لایه  ← اصلاحیه' }
];

var i = -1;
function step() {
  i++;
  if (i >= MODES.length) {
    document.documentElement.className = '';
    lbl.innerHTML = 'پایان آزمون — شماره‌هایی که تصویر داشتند را بگویید';
    try { v.pause(); } catch (e) {}
    return;
  }
  var m = MODES[i];
  document.documentElement.className = m.cls;
  lbl.innerHTML = 'حالت ' + m.n + ' — ' + m.t;
  /* سورس هر بار از نو ست می‌شود تا دیکودر با استایلِ تازه شروع کند؛
     عوض‌کردن استایلِ یک ویدیوی در حال پخش روی این نسل اثر نمی‌کند. */
  try { v.pause(); } catch (e) {}
  v.src = SRC;
  try { v.load(); } catch (e) {}
  try { v.play(); } catch (e) {}
  setTimeout(step, 9000);
}

if (!SRC) { lbl.innerHTML = 'هیچ ویدیویی روی سرور پیدا نشد'; }
else { setTimeout(step, 800); }
</script>
</body>
</html>
