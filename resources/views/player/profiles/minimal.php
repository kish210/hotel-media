<?php
/**
 * Hotel Media — پروفایل سبک
 *
 * برای سخت‌افزار ضعیف: Raspberry Pi، اندروید باکس ارزان، تلویزیون‌های
 * خیلی قدیمی. بدون انیمیشن، بدون محتوای پویا، فقط چرخش پلی‌لیست.
 *
 * ES5 خالص مثل بقیه‌ی صفحه‌های تلویزیون.
 * بازرس: node tests/Support/tv-compat-lint.js
 */
$screenCode = (string)($screen['code'] ?? '');
$isActive   = ($screen['status'] ?? '') === 'active';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پخش — <?= e($screen['name'] ?? '') ?></title>
<link rel="stylesheet" href="/assets/css/tv-base.css<?= v() ?>">
<script src="/assets/js/tv-base.js<?= v() ?>"></script>
<style>
/* سبک عمدی: هیچ ترنزیشنی نیست تا روی CPU ضعیف تپق نزند */
#c { width: 100%; height: 100%; background: #000; }
#c img, #c video { width: 100%; height: 100%; -o-object-fit: cover; object-fit: cover; display: block; }
#c iframe { width: 100%; height: 100%; border: 0; }
</style>
</head>
<body>
<div id="c">
  <div class="tv-center" id="tv-center">
    <?php if (!$isActive): ?>
      <div class="tv-center-title">این صفحه فعال نشده است</div>
      <div class="tv-center-text">کد صفحه: <?= e($screenCode ?: '—') ?></div>
    <?php else: ?>
      <div class="tv-spinner"></div>
      <div class="tv-center-text">در حال بارگذاری…</div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  'use strict';

  var CODE   = '<?= e($screenCode) ?>';
  /* مبدا از خود آدرس صفحه گرفته می‌شود نه از APP_URL در .env — اگر
     مدیر APP_URL را اشتباه بگذارد یا سرور با IP دیگری دیده شود،
     APP_URL کل پخش را می‌خواباند. */
  var ORIGIN = window.location.protocol + '//' + window.location.host;

  TV.boot();

<?php if ($isActive): ?>
  var list = [], idx = 0, timer = null;

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

    var host = TV.id('c');
    host.innerHTML = '';
    TV.hide(TV.id('tv-center'));

    /* المان‌ها با createElement ساخته می‌شوند نه با چسباندن رشته:
       آدرس از دیتابیس می‌آید و با innerHTML یک گیومه در نام فایل
       می‌توانست HTML صفحه را بشکند. */
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

  /* ضربان — فاصله را سرور تعیین می‌کند، مثل بقیه‌ی پروفایل‌ها */
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
      { version: '2.0', screen_type: 'signage' },
      function (err, d) {
        if (err || !d || !d.data) { scheduleHeartbeat(60); return; }

        TV.runCommands(d.data.commands, ORIGIN, CODE, function (name) {
          if (name === 'reload' || name === 'refresh') { load(); return true; }
          return false;
        });
        scheduleHeartbeat(d.data.sync_interval || 30);
      });
  }

  load();
  setTimeout(heartbeat, Math.floor(Math.random() * 10000));
<?php endif; ?>
})();
</script>
</body>
</html>
