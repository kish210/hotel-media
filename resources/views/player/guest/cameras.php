<?php
/**
 * دوربین‌های هتل روی تلویزیون اتاق (TODO ۵.۷)
 *
 * API از فاز ۱۰ بود (/api/v1/guest/{code}/cameras، با سطح دسترسی
 * اتاق و بدون آدرس RTSP) ولی هیچ صفحه‌ای صدایش نمی‌زد. رله‌ی دوربین را
 * اپراتور از پنل روشن می‌کند؛ دوربین خاموش اینجا با برچسب دیده می‌شود.
 *
 * کلیدها: OK تمام‌صفحه · چپ/راست در تمام‌صفحه: دوربین بعد/قبل · BACK
 *
 * @var array $screen
 */
$code = (string)($screen['code'] ?? '');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>دوربین‌ها</title>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css<?= v() ?>">
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-base.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-guest.css<?= v() ?>">
<script src="/assets/vendor/hls/hls.min.js<?= v() ?>"></script>
<script src="/assets/js/tv-base.js<?= v() ?>"></script>
<script src="/assets/js/tv-guest.js<?= v() ?>"></script>
<style>
#cm-full { position: absolute; top: 0; right: 0; bottom: 0; left: 0; background: #000; display: none; }
#cm-full video { width: 100%; height: 100%; background: #000; }
#cm-label { position: absolute; top: 1.4rem; right: 1.6rem; font-size: 1.3rem; font-weight: 800; color: #fff;
            background: rgba(0,0,0,.6); padding: .4rem 1rem; border-radius: .6rem; }
#cm-msg { position: absolute; top: 45%; left: 0; right: 0; text-align: center; font-size: 1.3rem; color: #fff; }
.g-tile.is-off { opacity: .45; }
</style>
</head>
<body>
<div id="g-root"></div>
<div id="cm-full"><div id="cm-video"></div><div id="cm-label"></div><div id="cm-msg"></div></div>
<div id="tv-center" class="tv-center tv-hidden"></div>

<script>
/* ES5 خالص — تلویزیون‌های هتلی. */
(function () {
  'use strict';

  var API = '/api/v1/guest/' + encodeURIComponent(<?= json_encode($code) ?>) + '/cameras';
  var ui = TVG.ui(TV.id('g-root'));
  var cams = [], full = -1, hls = null, listFocus = 0;

  function load() {
    TV.loading('در حال بارگذاری…');
    TV.get(API, function (err, d) {
      TV.ready();
      if (err || !d || !d.success) { TV.fail('دوربین‌ها در دسترس نیستند', 'لطفاً کمی بعد دوباره امتحان کنید.'); return; }
      cams = d.data || [];
      showList(listFocus);
    });
  }

  function showList(focusIdx) {
    ui.paint(function () {
      var h = '<div class="g-title">دوربین‌های هتل</div><div class="g-sub">پارکینگ، ورودی، استخر و محوطه</div>';
      if (!cams.length) return h + '<div class="g-msg">دوربینی برای این اتاق در دسترس نیست.</div>' + ui.hint('BACK: بازگشت');
      h += '<div class="g-wrap">';
      var i;
      for (i = 0; i < cams.length; i++) {
        (function (c, idx) {
          h += ui.btn('g-tile' + (c.live ? '' : ' is-off'),
            '<i class="fas fa-video"></i><div class="g-tile-name">' + TV.esc(c.name) + '</div>' +
            '<div class="g-tile-meta">' + TV.esc(c.location || '') + (c.live ? '' : '<br>در حال حاضر خاموش') + '</div>',
            function () { listFocus = idx; openFull(idx); });
        })(cams[i], i);
      }
      return h + '</div>' + ui.hint('OK: تمام‌صفحه  ·  BACK: بازگشت');
    }, focusIdx);
  }

  function stop() {
    if (hls) { try { hls.destroy(); } catch (e) {} hls = null; }
    TV.id('cm-video').innerHTML = '';
  }

  function openFull(idx) {
    stop();
    full = idx;
    var c = cams[idx];
    TV.id('g-root').style.display = 'none';
    TV.id('cm-full').style.display = 'block';
    TV.text(TV.id('cm-label'), c.name + (cams.length > 1 ? '   ◀ ▶' : ''));
    TV.text(TV.id('cm-msg'), c.live ? '' : 'این دوربین در حال حاضر خاموش است');
    if (!c.live) return;

    var v = document.createElement('video');
    v.autoplay = true; v.muted = true;
    v.setAttribute('playsinline', 'playsinline');
    TV.id('cm-video').appendChild(v);
    v.onerror = function () { TV.text(TV.id('cm-msg'), 'تصویر این دوربین باز نشد'); };
    if (window.Hls && window.Hls.isSupported()) {
      hls = new window.Hls({ liveSyncDurationCount: 2 });
      hls.on(window.Hls.Events.ERROR, function (e, d) { if (d && d.fatal) TV.text(TV.id('cm-msg'), 'تصویر این دوربین باز نشد'); });
      hls.loadSource(c.hls);
      hls.attachMedia(v);
    } else {
      v.src = c.hls;   /* HLS بومی Tizen و webOS */
    }
    try { var p = v.play(); if (p && p['catch']) p['catch'](function () {}); } catch (e) {}
  }

  function closeFull() {
    stop();
    full = -1;
    TV.id('cm-full').style.display = 'none';
    TV.id('g-root').style.display = 'block';
    showList(listFocus);
  }

  TVG.mount(function (k) {
    if (full >= 0) {
      if (k === 'BACK' || k === 'EXIT') { closeFull(); return true; }
      if (k === 'LEFT' || k === 'CH_DOWN') { listFocus = (full + 1) % cams.length; openFull(listFocus); return true; }
      if (k === 'RIGHT' || k === 'CH_UP') { listFocus = (full - 1 + cams.length) % cams.length; openFull(listFocus); return true; }
      return true;
    }
    if (ui.nav(k)) return true;
    return false;
  });

  load();
  /* رله‌ای که اپراتور تازه روشن کرده، بی‌نیاز به بستن صفحه دیده شود */
  setInterval(function () { if (full < 0) load(); }, 60000);
})();
</script>
</body>
</html>
