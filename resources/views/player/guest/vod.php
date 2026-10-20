<?php
/**
 * فیلم‌ها روی تلویزیون اتاق (TODO ۳.۳)
 *
 * ردیف‌ها: «ادامه‌ی تماشا» (همین اقامت)، «پیشنهاد برای شما» (از دسته‌هایی
 * که این اتاق بیشتر دیده) و هر دسته. فیلم پولی با تأیید مهمان خریده و
 * روی صورتحساب اتاق نشسته می‌شود. جای پخش هر ۱۵ ثانیه و موقع خروج ثبت
 * می‌شود تا اگر مهمان تلویزیون را خاموش کرد، از همان‌جا ادامه دهد.
 *
 * کلیدها در پخش: چپ/راست ۱۰ ثانیه، FF/RW چندسرعته، OK مکث، BACK خروج.
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
<title>فیلم‌ها</title>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css<?= v() ?>">
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-base.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-guest.css<?= v() ?>">
<script src="/assets/vendor/hls/hls.min.js<?= v() ?>"></script>
<script src="/assets/js/tv-base.js<?= v() ?>"></script>
<script src="/assets/js/tv-guest.js<?= v() ?>"></script>
<script src="/assets/js/tv-trickplay.js<?= v() ?>"></script>
<style>
.vd-label { font-size: 1.15rem; font-weight: 800; color: #fff; margin: .4rem 0 .6rem; }
.vd-row { white-space: nowrap; overflow: hidden; margin-bottom: 1.2rem; padding: .3rem .2rem; }
.vd-card { display: inline-block; vertical-align: top; width: 13rem; margin-left: 1rem; border-radius: .8rem;
           background: #1e293b; border: 2px solid transparent; overflow: hidden; white-space: normal; position: relative; }
.vd-card.is-focused { border-color: #4098db; }
.vd-poster { height: 7.4rem; background: #273449 center / cover no-repeat; position: relative; }
.vd-poster i { position: absolute; top: 2.6rem; left: 0; right: 0; text-align: center; font-size: 2rem; color: #475569; }
.vd-name { font-size: .95rem; font-weight: 700; color: #fff; padding: .5rem .7rem .1rem; height: 1.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.vd-meta { font-size: .78rem; color: #94a3b8; padding: 0 .7rem .6rem; white-space: nowrap; overflow: hidden; }
.vd-bar { position: absolute; left: 0; right: 0; bottom: 0; height: .35rem; background: rgba(0,0,0,.5); }
.vd-bar span { display: block; height: 100%; background: #ef4444; }
.vd-price { position: absolute; top: .5rem; left: .5rem; font-size: .75rem; padding: .15rem .5rem; border-radius: .4rem;
            background: rgba(0,0,0,.72); color: #fbbf24; }
.vd-price.own { color: #4ade80; }
#vd-detail-img { float: left; width: 22rem; height: 13rem; margin-right: 2rem; border-radius: 1rem; background: #1e293b center / cover no-repeat; }
.vd-desc { font-size: 1.1rem; line-height: 2; color: #cbd5e1; max-width: 46rem; margin-bottom: 1.2rem; max-height: 8.8rem; overflow: hidden; }
#vd-player { position: absolute; top: 0; right: 0; bottom: 0; left: 0; background: #000; display: none; }
#vd-player video { width: 100%; height: 100%; background: #000; }
#vd-pmsg { position: absolute; top: 45%; left: 0; right: 0; text-align: center; color: #fff; font-size: 1.3rem; }
</style>
</head>
<body>
<div id="g-root"></div>
<div id="vd-player"><div id="vd-video"></div><div id="vd-pmsg"></div></div>
<div id="tv-center" class="tv-center tv-hidden"></div>

<script>
/* ES5 خالص — تلویزیون‌های هتلی. */
(function () {
  'use strict';

  var API = '/api/v1/guest/' + encodeURIComponent(<?= json_encode($code) ?>) + '/vod';
  var fa = TVG.fa, money = TVG.money;
  var ui = TVG.ui(TV.id('g-root'));
  var btn = ui.btn;

  var lib = null, detail = null, homeFocus = 0, confirmBuy = false, msg = '';
  var playing = false, vid = null, hls = null, trick = null, saveTimer = null;

  function clock(sec) {
    sec = Math.max(0, Math.floor(+sec || 0));
    var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
    var mm = (m < 10 ? '0' : '') + m, ss = (s < 10 ? '0' : '') + s;
    return fa(h > 0 ? h + ':' + mm + ':' + ss : mm + ':' + ss);
  }

  // ── صفحه‌ی اصلی ─────────────────────────────────────────────────
  function loadHome(focusIdx) {
    TV.loading('در حال بارگذاری…');
    TV.get(API, function (err, d) {
      TV.ready();
      if (err || !d || !d.success) { TV.fail('فیلم‌ها در دسترس نیستند', 'لطفاً کمی بعد دوباره امتحان کنید.'); return; }
      lib = d.data;
      showHome(focusIdx || 0);
    });
  }

  function card(v) {
    var p = v.progress, pct = 0, i;
    if (p && !p.completed && p.duration > 0) pct = Math.min(100, Math.round(p.position * 100 / p.duration));
    var meta = [];
    if (v.year) meta.push(fa(v.year));
    if (v.duration_fmt) meta.push(fa(v.duration_fmt));
    var price = v.price > 0 ? (v.allowed ? '<span class="vd-price own">خریده‌شده</span>' : '<span class="vd-price">' + money(v.price) + '</span>') : '';
    return btn('vd-card',
      '<div class="vd-poster"' + (v.thumbnail ? ' style="background-image:url(' + TV.esc(v.thumbnail) + ')"' : '') + '>' +
        (v.thumbnail ? '' : '<i class="fas fa-film"></i>') + price +
        (pct ? '<div class="vd-bar"><span style="width:' + pct + '%"></span></div>' : '') + '</div>' +
      '<div class="vd-name">' + TV.esc(v.title) + '</div><div class="vd-meta">' + (meta.join(' · ') || '&nbsp;') + '</div>',
      function () { homeFocus = ui.cur(); openDetail(v.id); });
  }

  function row(label, list) {
    if (!list || !list.length) return '';
    var h = '<div class="vd-label">' + label + '</div><div class="vd-row">', i;
    for (i = 0; i < list.length; i++) h += card(list[i]);
    return h + '</div>';
  }

  function showHome(focusIdx) {
    detail = null; confirmBuy = false;
    ui.paint(function () {
      var h = '<div class="g-title">فیلم‌ها</div>', i, any = false;
      if (lib.continue && lib.continue.length) { h += row('<i class="fas fa-rotate-left"></i> ادامه‌ی تماشا', lib.continue); any = true; }
      if (lib.recommended && lib.recommended.length) { h += row('<i class="fas fa-wand-magic-sparkles"></i> پیشنهاد برای شما', lib.recommended); any = true; }
      for (i = 0; i < lib.categories.length; i++) { h += row(TV.esc(lib.categories[i].name), lib.categories[i].videos); any = true; }
      if (!any) h += '<div class="g-msg">هنوز فیلمی اضافه نشده است.</div>';
      return h + ui.hint('OK: جزئیات  ·  BACK: بازگشت');
    }, focusIdx);
  }

  // ── جزئیات ──────────────────────────────────────────────────────
  function openDetail(id) {
    TV.loading('در حال بارگذاری…');
    TV.get(API + '/' + id, function (err, d) {
      TV.ready();
      if (err || !d || !d.success) { msg = TVG.errMsg(err, 'این فیلم در دسترس نیست'); showHome(homeFocus); return; }
      detail = d.data;
      confirmBuy = false;
      showDetail();
    });
  }

  function showDetail() {
    var v = detail;
    ui.paint(function () {
      var meta = [], h;
      if (v.year) meta.push(fa(v.year));
      if (v.duration_fmt) meta.push(fa(v.duration_fmt));
      if (v.language) meta.push(TV.esc(v.language.toUpperCase()));
      if (v.rating) meta.push('★ ' + fa(v.rating));

      h = '<div id="vd-detail-img"' + (v.thumbnail ? ' style="background-image:url(' + TV.esc(v.thumbnail) + ')"' : '') + '></div>' +
          '<div class="g-title">' + TV.esc(v.title) + '</div>' +
          '<div class="g-sub">' + meta.join(' · ') + (v.title_en ? ' · <span dir="ltr">' + TV.esc(v.title_en) + '</span>' : '') + '</div>';
      if (v.description) h += '<div class="vd-desc">' + TV.esc(v.description) + '</div>';
      if (msg) h += '<div class="g-msg">' + TV.esc(msg) + '</div>';

      if (confirmBuy) {
        h += '<div class="g-box"><div>خرید «' + TV.esc(v.title) + '» به مبلغ <b>' + money(v.price) + '</b></div>' +
             '<div>مبلغ روی صورتحساب اتاق شما اضافه می‌شود و تا ' + fa(v.access_hours) + ' ساعت قابل تماشاست.</div></div>';
        h += btn('g-chip', '<i class="fas fa-check"></i> تأیید خرید', buy);
        h += btn('g-chip', 'انصراف', function () { confirmBuy = false; showDetail(); });
        return h;
      }

      var p = v.progress;
      if (v.allowed) {
        if (p && !p.completed && p.position >= 30) {
          h += btn('g-chip', '<i class="fas fa-play"></i> ادامه از ' + clock(p.position), function () { play(p.position); });
          h += btn('g-chip', 'از ابتدا', function () { play(0); });
        } else {
          h += btn('g-chip', '<i class="fas fa-play"></i> پخش', function () { play(0); });
        }
        if (v.owned_until) h += '<div class="g-sub" style="margin-top:.8rem">خریده‌شده — تا ' + TVG.hm(v.owned_until) + ' ' + fa(String(v.owned_until).substr(5, 5).replace('-', '/')) + '</div>';
      } else if (lib && lib.occupied) {
        h += btn('g-chip', '<i class="fas fa-cart-shopping"></i> خرید — ' + money(v.price), function () { confirmBuy = true; msg = ''; showDetail(); });
      } else {
        h += '<div class="g-msg">خرید فیلم فقط برای مهمان ساکن اتاق ممکن است.</div>';
      }
      if (p && !p.completed && p.position >= 30) h += btn('g-chip', 'حذف از ادامه‌ی تماشا', hideIt);
      h += btn('g-chip', 'بازگشت', function () { msg = ''; loadHome(homeFocus); });
      return h + ui.hint('BACK: فهرست فیلم‌ها');
    }, 0);
  }

  function buy() {
    if (ui.busy) return;
    ui.busy = true;
    TV.post(API + '/' + detail.id + '/purchase', {}, function (err, d) {
      ui.busy = false;
      if (err || !d || !d.success) { msg = TVG.errMsg(err, 'خرید انجام نشد'); confirmBuy = false; showDetail(); return; }
      msg = '';
      openDetail(detail.id);
    });
  }

  function hideIt() {
    TV.post(API + '/' + detail.id + '/hide', {}, function () { msg = ''; loadHome(0); });
  }

  // ── پخش ─────────────────────────────────────────────────────────
  function play(from) {
    if (!detail || !detail.url) return;
    playing = true;
    TV.id('g-root').style.display = 'none';
    TV.id('vd-player').style.display = 'block';
    TV.text(TV.id('vd-pmsg'), '');
    var host = TV.id('vd-video');
    host.innerHTML = '';
    vid = document.createElement('video');
    vid.autoplay = true;
    host.appendChild(vid);
    TV.tracks.attachSubtitles(vid, detail.subtitles || []);
    vid.onerror = function () { TV.text(TV.id('vd-pmsg'), 'پخش این فیلم باز نشد'); };
    vid.onended = function () { save(true); stop(); };

    /* رفتن به جای قبلی فقط بعد از آمدن متادیتا ممکن است */
    var seeked = false;
    TV.on(vid, 'loadedmetadata', function () {
      if (seeked || !from) return;
      seeked = true;
      try { vid.currentTime = Math.min(from, (vid.duration || from + 1) - 5); } catch (e) {}
    });

    if (/\.m3u8(\?|$)/i.test(detail.url) && window.Hls && window.Hls.isSupported()) {
      hls = new window.Hls({ startPosition: from || -1 });
      hls.loadSource(detail.url);
      hls.attachMedia(vid);
      seeked = true;
    } else {
      vid.src = detail.url;
    }
    try { var p = vid.play(); if (p && p['catch']) p['catch'](function () {}); } catch (e2) {}
    if (window.TVTrick) { try { trick = window.TVTrick.attach(vid, host, { noKeys: true }); } catch (e3) { trick = null; } }

    clearInterval(saveTimer);
    saveTimer = setInterval(function () { if (vid && !vid.paused) save(false); }, 15000);
  }

  function save(ended) {
    if (!vid || !detail) return;
    var dur = vid.duration && isFinite(vid.duration) ? vid.duration : (detail.duration || 0);
    var pos = ended ? dur : (vid.currentTime || 0);
    TV.post(API + '/' + detail.id + '/progress', { position: pos, duration: dur }, function () {});
  }

  function stop() {
    clearInterval(saveTimer);
    if (trick) { try { trick.destroy(); } catch (e) {} trick = null; }
    if (hls) { try { hls.destroy(); } catch (e2) {} hls = null; }
    if (vid) { try { vid.pause(); } catch (e3) {} }
    vid = null;
    playing = false;
    TV.id('vd-video').innerHTML = '';
    TV.id('vd-player').style.display = 'none';
    TV.id('g-root').style.display = 'block';
    /* جزئیات تازه: دکمه‌ی «ادامه از …» با جای جدید */
    if (detail) openDetail(detail.id); else loadHome(homeFocus);
  }

  /* خاموش شدن تلویزیون یا بستن صفحه وسط فیلم: جای پخش گم نشود */
  TV.on(document, 'visibilitychange', function () { if (document.hidden && playing) save(false); });
  TV.on(window, 'pagehide', function () { if (playing) save(false); });

  TVG.mount(function (k) {
    if (playing) {
      if (k === 'BACK' || k === 'EXIT' || k === 'STOP') { save(false); stop(); return true; }
      if (trick && trick.key(k)) return true;
      return true;
    }
    if (detail && (k === 'BACK' || k === 'EXIT')) {
      if (confirmBuy) { confirmBuy = false; showDetail(); return true; }
      msg = ''; loadHome(homeFocus); return true;
    }
    if (ui.nav(k)) return true;
    return false;
  });

  loadHome(0);
})();
</script>
</body>
</html>
