<?php
/**
 * صفحهٔ عیب‌یابی پخش — روی خودِ تلویزیون باز می‌شود.
 *
 * چرا لازم شد: روی تابلوی سامسونگ ۲۰۱۳ (Orsay/Maple) تصویر پخش می‌شد
 * ولی ویدیو سیاه می‌ماند. از بیرون نمی‌شود فهمید چرا — مرورگر آن
 * تلویزیون کنسول ندارد و با هیچ ابزاری از راه دور قابل بازرسی نیست.
 * پس خودِ دستگاه آزمون می‌کند و نتیجه را هم روی صفحه می‌نویسد (تا
 * اپراتور با چشم بخواند) و هم به سرور می‌فرستد (تا ما ببینیم).
 *
 * عمدا ES5 و بدون هیچ کتابخانه‌ای: این صفحه باید دقیقا روی همان
 * موتوری اجرا شود که مشکل دارد.
 *
 * @var string $code
 * @var array  $videos  چند ویدیوی واقعی همین هتل برای آزمون
 */
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>عیب‌یابی پخش</title>
<style>
  body{background:#07090e;color:#e2e8f0;font-family:Tahoma,Arial,sans-serif;margin:0;padding:14px;font-size:15px;}
  h1{font-size:18px;margin:0 0 10px;color:#fff;}
  .row{padding:6px 0;border-bottom:1px solid rgba(255,255,255,.08);}
  .k{color:#94a3b8;}
  .ok{color:#4ade80;} .no{color:#f87171;} .wa{color:#fbbf24;}
  video{width:320px;height:180px;background:#000;display:block;margin:8px 0;}
  #log{font-size:13px;line-height:1.9;}
</style>
</head>
<body>
<h1>عیب‌یابی پخش — <?= htmlspecialchars($code, ENT_QUOTES) ?></h1>
<div id="log">در حال آزمون…</div>
<video id="v" muted playsinline webkit-playsinline></video>

<script>
/* ── گزارش ────────────────────────────────────────────────────── */
var lines = [];
function say(k, v, cls) {
  lines.push('<div class="row"><span class="k">' + k + ':</span> <span class="' + (cls || '') + '">' + v + '</span></div>');
  document.getElementById('log').innerHTML = lines.join('');
}
function yn(b) { return b ? ['بله', 'ok'] : ['خیر', 'no']; }

var report = { code: <?= json_encode($code) ?>, ua: navigator.userAgent, steps: [] };
function note(k, v) { report.steps.push({ k: k, v: String(v) }); }

/* ── ۱) خودِ دستگاه ───────────────────────────────────────────── */
say('مرورگر', navigator.userAgent);
note('ua', navigator.userAgent);
say('اندازهٔ صفحه', screen.width + '×' + screen.height);
note('screen', screen.width + 'x' + screen.height);

/* ── ۲) آیا عنصر video اصلا هست و چه چیزی می‌فهمد ──────────────── */
var v = document.getElementById('v');
var hasVideo = !!(v && typeof v.play === 'function');
var r = yn(hasVideo); say('عنصر HTML5 video', r[0], r[1]);
note('html5_video', hasVideo);

var types = {
  'MP4 / H.264 Baseline': 'video/mp4; codecs="avc1.42E01E"',
  'MP4 / H.264 Main':     'video/mp4; codecs="avc1.4D401E"',
  'MP4 / H.264 High':     'video/mp4; codecs="avc1.64001E"',
  'MP4 (بدون codec)':     'video/mp4',
  'WebM':                 'video/webm',
  'MPEG-TS':              'video/mp2t'
};
for (var label in types) {
  var can = '';
  try { can = v.canPlayType ? v.canPlayType(types[label]) : '(تابع نیست)'; } catch (e) { can = '(خطا)'; }
  if (can === '') can = 'نه';
  say(label, can, can === 'نه' ? 'no' : 'ok');
  note('canplay_' + label, can);
}

/* ── ۳) افزونه‌های بومی سامسونگ ────────────────────────────────── */
/* روی Orsay، پخش ویدیو معمولا از راه افزونهٔ SEF انجام می‌شود، نه
   <video>. اگر این‌ها باشند، راهِ درست همان است. */
var sefNames = {
  'پلاگین SEF (AVPlay)': 'clsid:SAMSUNG-INFOLINK-SEF',
  'پلاگین Player':       'clsid:SAMSUNG-INFOLINK-PLAYER'
};
for (var nm in sefNames) {
  var found = false;
  try {
    var o = document.createElement('object');
    o.setAttribute('classid', sefNames[nm]);
    o.style.cssText = 'width:0;height:0;position:absolute;';
    document.body.appendChild(o);
    /* اگر پلاگین واقعا بار شود، متدهای بومی روی شیء ظاهر می‌شوند */
    found = !!(o.Open || o.Execute || o.Play);
    document.body.removeChild(o);
  } catch (e) { found = false; }
  var rr = yn(found); say(nm, rr[0], rr[1]);
  note('sef_' + nm, found);
}
say('webapis/AVPlay (مدل‌های Tizen)', (typeof webapis !== 'undefined' && webapis && webapis.avplay) ? 'هست' : 'نیست',
    (typeof webapis !== 'undefined' && webapis && webapis.avplay) ? 'ok' : 'no');

/* ── ۴) آزمون واقعی با ویدیوی همین هتل ─────────────────────────── */
var tests = <?= json_encode($videos, JSON_UNESCAPED_SLASHES) ?>;
var idx = 0;

function send() {
  try {
    var x = new XMLHttpRequest();
    x.open('POST', '/tv/diag/' + report.code, true);
    x.setRequestHeader('Content-Type', 'application/json');
    x.send(JSON.stringify(report));
    say('ارسال گزارش به سرور', 'انجام شد', 'ok');
  } catch (e) { say('ارسال گزارش', 'نشد: ' + e, 'no'); }
}

function ERRTXT(code) {
  return ({1: 'ABORTED', 2: 'NETWORK', 3: 'DECODE — فایل باز شد ولی دیکد نشد',
           4: 'SRC_NOT_SUPPORTED — این قالب پشتیبانی نمی‌شود'}[code]) || ('کد ' + code);
}

function next() {
  if (idx >= tests.length) { say('پایان آزمون', 'همهٔ موارد امتحان شد', 'ok'); send(); return; }
  var t = tests[idx++];
  say('— آزمون ' + idx, t.name);

  var done = false;
  function finish(txt, cls) {
    if (done) return; done = true;
    say('   نتیجه', txt, cls);
    note('play_' + t.name, txt);
    setTimeout(next, 400);
  }

  try { v.pause(); } catch (e) {}
  v.onerror = function () {
    var c = (v.error && v.error.code) || 0;
    finish('خطا — ' + ERRTXT(c), 'no');
  };
  v.onplaying = function () {
    finish('پخش شد ✓ (' + (v.videoWidth || '?') + '×' + (v.videoHeight || '?') + ')', 'ok');
  };
  v.onloadedmetadata = function () {
    note('meta_' + t.name, (v.videoWidth || '?') + 'x' + (v.videoHeight || '?'));
  };

  v.src = t.url;
  try { v.load(); } catch (e) {}
  try { v.play(); } catch (e) {}

  /* اگر نه خطا داد نه پخش شد، همان هم یک یافته است: دقیقا همان
     «صفحهٔ سیاه بی‌پیام» که گزارش شده. */
  setTimeout(function () { finish('نه خطا داد نه پخش شد (صفحهٔ سیاه)', 'wa'); }, 12000);
}

setTimeout(next, 600);
</script>
</body>
</html>
