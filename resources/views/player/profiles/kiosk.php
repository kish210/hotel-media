<?php
/**
 * Hotel Media — پروفایل کیوسک لمسی
 *
 * برای نمایشگر لمسی لابی و پذیرش. تفاوتش با تابلوی معمولی: وقتی کسی
 * دست نمی‌زند پرده‌ی «برای شروع لمس کنید» می‌آید و با لمس کنار می‌رود.
 *
 * ES5 خالص. بازرس: node tests/Support/tv-compat-lint.js
 */
$screenCode = (string)($screen['code'] ?? '');
$isActive   = ($screen['status'] ?? '') === 'active';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>کیوسک — <?= e($screen['name'] ?? '') ?></title>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css">
<link rel="stylesheet" href="/assets/css/tv-base.css">
<script src="/assets/vendor/hls/hls.min.js"></script>
<script src="/assets/js/tv-base.js"></script>
<style>
* { -webkit-tap-highlight-color: transparent; }

#slide {
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  background: #000;
}
/* iframe اینجا قانون اندازه نداشت، پس آیتم‌های صفحه‌وب با ارتفاع صفر
   رندر می‌شدند و کیوسک سیاه می‌ماند. */
#slide img, #slide video { width: 100%; height: 100%; -o-object-fit: cover; object-fit: cover; display: block; }
#slide iframe { width: 100%; height: 100%; border: 0; display: block; }

#idle {
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  z-index: 10;
  background: rgba(0, 0, 0, .72);
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-orient: vertical; -webkit-box-direction: normal;
  -webkit-flex-direction: column; -ms-flex-direction: column; flex-direction: column;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
  -webkit-box-pack: center; -webkit-justify-content: center;
  -ms-flex-pack: center; justify-content: center;
  text-align: center;
  -webkit-transition: opacity .45s ease;
  transition: opacity .45s ease;
}
#idle.is-off { opacity: 0; }
#idle-logo  { height: 4rem; width: auto; margin-bottom: 1.8rem; }
#idle-title { font-size: 3.4rem; font-weight: 800; line-height: 1.15; }
#idle-sub   { font-size: 1.5rem; font-weight: 400; color: #a8b4c6; margin-top: .8rem; }
#idle-hint {
  position: absolute;
  bottom: 3rem; left: 0; right: 0;
  font-size: 1.15rem;
  color: #79849a;
  -webkit-animation: kioskPulse 2.2s ease-in-out infinite;
  animation: kioskPulse 2.2s ease-in-out infinite;
}
@-webkit-keyframes kioskPulse { 0%,100% { opacity: .35; } 50% { opacity: .9; } }
@keyframes kioskPulse { 0%,100% { opacity: .35; } 50% { opacity: .9; } }

#clock {
  position: absolute;
  top: 1.2rem; right: 1.2rem;
  z-index: 5;
  font-size: 2rem; font-weight: 800;
  font-family: monospace;
  color: rgba(255, 255, 255, .82);
}
</style>
</head>
<body class="tv-no-cursor">

<div id="slide"></div>

<div class="tv-center" id="tv-center">
  <?php if (!$isActive): ?>
    <div class="tv-center-title">این کیوسک فعال نشده است</div>
    <div class="tv-center-text">کد صفحه: <?= e($screenCode ?: '—') ?></div>
  <?php else: ?>
    <div class="tv-spinner"></div>
    <div class="tv-center-text">در حال بارگذاری…</div>
  <?php endif; ?>
</div>

<div id="idle">
  <img id="idle-logo" src="/assets/img/sama-logo.svg" alt="">
  <div id="idle-title">خوش آمدید</div>
  <div id="idle-sub">برای شروع صفحه را لمس کنید</div>
  <div id="idle-hint">👆 لمس کنید</div>
</div>

<div id="clock">--:--</div>

<script>
(function () {
  'use strict';

  var CODE   = '<?= e($screenCode) ?>';
  var ORIGIN = window.location.protocol + '//' + window.location.host;

  TV.boot();
  TV.startClock(TV.id('clock'), true);

<?php if ($isActive): ?>
  var list = [], idx = 0, timer = null;
  var idle = true, idleTimer = null;

  /* ── پرده‌ی بی‌کاری ───────────────────────────────────────────── */
  function wake() {
    if (idle) {
      idle = false;
      TV.addClass(TV.id('idle'), 'is-off');
    }
    resetIdle();
  }

  function resetIdle() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(function () {
      idle = true;
      TV.removeClass(TV.id('idle'), 'is-off');
    }, 120000);   /* دو دقیقه بی‌حرکتی */
  }

  /* هم لمس و هم کلیک: بعضی نمایشگرها touch نمی‌فرستند و بعضی
     مرورگرها click را با تاخیر می‌دهند. */
  TV.on(document, 'click', wake);
  TV.on(document, 'touchstart', wake);

  /* ── پلی‌لیست ─────────────────────────────────────────────────── */
  function load() {
    TV.get(ORIGIN + '/api/v1/screens/' + CODE + '/playlist', function (err, d) {
      if (err) { setTimeout(load, 15000); return; }
      if (!d || !d.success || !d.data || !d.data.items || !d.data.items.length) {
        TV.fail('محتوایی تنظیم نشده', 'کد صفحه: ' + CODE);
        setTimeout(load, 30000);
        return;
      }
      list = d.data.items;
      idx = 0;
      show(0);
    });
  }

  function show(n) {
    clearTimeout(timer);
    var it = list[n];
    if (!it) return;

    var src  = it.file_url || it.file_path || it.url || it.src || '';
    var type = it.type || 'image';
    var secs = Number(it.duration);
    if (!secs || isNaN(secs) || secs < 1) secs = 10;

    var host = TV.id('slide');
    host.innerHTML = '';
    TV.hide(TV.id('tv-center'));

    /* createElement نه innerHTML — آدرس از دیتابیس می‌آید و یک گیومه
       در نام فایل می‌توانست HTML صفحه را بشکند. */
    var el;
    if (type === 'video') {
      el = document.createElement('video');
      el.autoplay = true;
      el.muted    = true;
      el.setAttribute('playsinline', 'playsinline');
      el.onended  = next;
      el.onerror  = next;
      el.src      = src;
    } else if (type === 'url' || type === 'webpage' || type === 'module') {
      el = document.createElement('iframe');
      el.setAttribute('allow', 'autoplay; fullscreen');
      el.src = src;
    } else {
      el = document.createElement('img');
      el.onerror = next;
      el.src = src;
    }
    host.appendChild(el);

    timer = setTimeout(next, secs * 1000);
  }

  function next() {
    if (!list.length) return;
    idx = (idx + 1) % list.length;
    show(idx);
  }

  /* ── ضربان ────────────────────────────────────────────────────── */
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
    TV.post(ORIGIN + '/api/v1/screens/' + CODE + '/heartbeat',
      { version: '2.0', screen_type: 'kiosk' },
      function (err, d) {
        if (err || !d || !d.data) { scheduleHeartbeat(60); return; }

        /* فرمان‌ها قبلا اینجا اصلا خوانده نمی‌شدند — کیوسک ضربان
           می‌فرستاد ولی هیچ دستوری از پنل نمی‌گرفت. حالا همان اجرای
           مشترک همه‌ی صفحه‌هاست. */
        TV.runCommands(d.data.commands, ORIGIN, CODE, function (name) {
          if (name === 'reload' || name === 'refresh') { load(); return true; }
          return false;
        });
        scheduleHeartbeat(d.data.sync_interval || 30);
      });
  }

  load();
  resetIdle();
  setTimeout(heartbeat, Math.floor(Math.random() * 10000));
<?php endif; ?>
})();
</script>
</body>
</html>
