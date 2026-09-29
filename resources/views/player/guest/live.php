<?php
/**
 * تلویزیون زنده روی تلویزیون اتاق — فهرست کانال، زپ، شماره‌ی کانال،
 * راهنمای برنامه (الان/بعدی)، قفل والدین و افزونگی منبع.
 *
 * تا امروز پورتال IPTV فقط کاشی‌های منو را نشان می‌داد و هیچ صفحه‌ای
 * /api/v1/guest/{code}/channels را صدا نمی‌زد؛ یعنی سطح دسترسی اتاق،
 * قفل والدین، multicast و EPG از روی تلویزیون قابل استفاده نبودند
 * مگر اپراتور هر کانال را جدا کاشی می‌کرد.
 *
 * ── افزونگی (TODO ۵.۲۰) ────────────────────────────────────────────
 * هر کانال تا سه منبع دارد: multicast (فقط webOS و Tizen که پخش‌کننده‌ی
 * بومی‌شان UDP می‌خواند)، stream_url، و backup_stream_url. اگر منبع
 * خطا بدهد، ۱۲ ثانیه شروع نشود، یا وسط پخش ۱۲ ثانیه تصویر جلو نرود،
 * منبع بعدی امتحان می‌شود — مهمان به‌جای تصویر یخ‌زده، کانال را از
 * مسیر پشتیبان می‌بیند. اگر هیچ‌کدام نشد، هر ۳۰ ثانیه دوباره.
 *
 * کلیدها: بالا/پایین و CH±: کانال بعد/قبل · OK: فهرست کانال‌ها ·
 * اعداد: رفتن به شماره‌ی کانال · BACK: بستن فهرست، بعد خروج.
 *
 * @var array $screen
 * @var bool  $radio
 */
$code = (string)($screen['code'] ?? '');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>تلویزیون زنده</title>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css<?= v() ?>">
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-base.css<?= v() ?>">
<script src="/assets/vendor/hls/hls.min.js<?= v() ?>"></script>
<script src="/assets/js/tv-base.js<?= v() ?>"></script>
<style>
body { background: #000; color: #e2e8f0; overflow: hidden; }
#lv-video { position: absolute; top: 0; right: 0; bottom: 0; left: 0; background: #000; }
#lv-video video { width: 100%; height: 100%; background: #000; }

#lv-radio { position: absolute; top: 0; right: 0; bottom: 0; left: 0; text-align: center; padding-top: 12rem; display: none;
            background: #0b1220; }
#lv-radio i { font-size: 6rem; color: #14b8a6; }
#lv-radio div { font-size: 2.2rem; font-weight: 800; color: #fff; margin-top: 1.4rem; }

#lv-bar { position: absolute; left: 2rem; right: 2rem; bottom: 2rem; padding: 1.2rem 1.6rem; border-radius: 1.2rem;
          background: rgba(5,10,20,.86); display: none; }
#lv-bar.is-on { display: block; }
.lv-no { display: inline-block; vertical-align: middle; font-size: 2.4rem; font-weight: 800; color: #fbbf24; min-width: 4.5rem; }
.lv-logo { display: inline-block; vertical-align: middle; height: 3rem; max-width: 6rem; margin: 0 1rem; }
.lv-name { display: inline-block; vertical-align: middle; font-size: 1.6rem; font-weight: 800; color: #fff; }
.lv-badge { display: inline-block; vertical-align: middle; font-size: .8rem; padding: .2rem .7rem; border-radius: .5rem;
            background: rgba(245,158,11,.2); color: #fbbf24; margin-right: 1rem; }
.lv-now { font-size: 1.15rem; color: #e2e8f0; margin-top: .7rem; }
.lv-next { font-size: .95rem; color: #94a3b8; margin-top: .3rem; }
.lv-prog { height: .35rem; background: rgba(255,255,255,.12); border-radius: .3rem; margin-top: .6rem; overflow: hidden; }
.lv-prog div { height: 100%; background: #14b8a6; }

#lv-list { position: absolute; top: 0; right: 0; bottom: 0; width: 34rem; background: rgba(5,10,20,.94); display: none;
           padding: 1.6rem 0; }
#lv-list.is-on { display: block; }
.lv-list-title { font-size: 1.3rem; font-weight: 800; color: #fff; padding: 0 1.6rem 1rem; }
#lv-rows { position: absolute; top: 4rem; bottom: 1rem; right: 0; left: 0; overflow: hidden; }
.lv-row { padding: .7rem 1.6rem; border-right: .3rem solid transparent; white-space: nowrap; overflow: hidden; }
.lv-row.is-focused { background: rgba(26,122,196,.35); border-right-color: #4098db; }
.lv-row.is-cur .lv-row-name { color: #14b8a6; }
.lv-row-no { display: inline-block; width: 3rem; color: #94a3b8; font-weight: 700; }
.lv-row-name { font-size: 1.1rem; color: #fff; font-weight: 700; }
.lv-row-now { font-size: .85rem; color: #94a3b8; margin-right: 3rem; overflow: hidden; text-overflow: ellipsis; }

#lv-num { position: absolute; top: 2rem; left: 2rem; font-size: 3.2rem; font-weight: 800; color: #fbbf24;
          background: rgba(5,10,20,.85); padding: .4rem 1.4rem; border-radius: 1rem; display: none; }
#lv-msg { position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 1.4rem; color: #fff; display: none; }
#lv-msg small { display: block; font-size: 1rem; color: #94a3b8; margin-top: .6rem; }

#lv-pin { position: absolute; top: 0; right: 0; bottom: 0; left: 0; background: rgba(0,0,0,.88); text-align: center;
          padding-top: 11rem; display: none; }
#lv-pin.is-on { display: block; }
#lv-pin-title { font-size: 1.6rem; color: #fff; font-weight: 800; }
#lv-pin-dots { font-size: 3rem; letter-spacing: 1.2rem; color: #fbbf24; margin: 1.6rem 0; }
#lv-pin-msg { font-size: 1.05rem; color: #f87171; min-height: 1.6rem; }
.lv-hint { font-size: .95rem; color: #64748b; margin-top: 1rem; }
</style>
</head>
<body>
<div id="lv-video"></div>
<div id="lv-radio"><i class="fas fa-radio"></i><div id="lv-radio-name"></div></div>
<div id="lv-msg"></div>
<div id="lv-num"></div>

<div id="lv-bar">
  <span class="lv-no" id="lv-bar-no"></span>
  <img class="lv-logo" id="lv-bar-logo" alt="">
  <span class="lv-name" id="lv-bar-name"></span>
  <span class="lv-badge" id="lv-bar-badge" style="display:none">منبع پشتیبان</span>
  <div class="lv-now" id="lv-bar-now"></div>
  <div class="lv-prog" id="lv-bar-prog" style="display:none"><div id="lv-bar-progv"></div></div>
  <div class="lv-next" id="lv-bar-next"></div>
</div>

<div id="lv-list">
  <div class="lv-list-title" id="lv-list-title">کانال‌ها</div>
  <div id="lv-rows"></div>
</div>

<div id="lv-pin">
  <div id="lv-pin-title">این کانال قفل است</div>
  <div class="lv-hint">رمز چهار رقمی قفل والدین را با دکمه‌های عددی ریموت وارد کنید</div>
  <div id="lv-pin-dots">····</div>
  <div id="lv-pin-msg"></div>
  <div class="lv-hint">BACK: انصراف</div>
</div>

<div id="tv-center" class="tv-center tv-hidden"></div>

<script>
/* ES5 خالص — تلویزیون‌های هتلی. */
(function () {
  'use strict';

  var CODE  = <?= json_encode($code) ?>;
  var RADIO = <?= $radio ? 'true' : 'false' ?>;
  var API   = '/api/v1/guest/' + encodeURIComponent(CODE);
  var STORE = 'hm_live_' + CODE + (RADIO ? '_radio' : '');

  var PLATFORM = (function () {
    var ua = (navigator.userAgent || '').toLowerCase();
    if (ua.indexOf('webos') !== -1 || ua.indexOf('web0s') !== -1) return 'webos';
    if (ua.indexOf('tizen') !== -1) return 'tizen';
    return 'other';
  })();

  var channels = [], epg = {}, token = '';
  var cur = -1, listOpen = false, listFocus = 0;
  var pinFor = -1, pinDigits = '';
  var numBuf = '', numTimer = null, barTimer = null;

  var player = { v: null, hls: null, sources: [], idx: 0, settle: null, watch: null, lastT: -1, stuck: 0, retry: null };

  function fa(s) { return String(s == null ? '' : s).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.charAt(+d); }); }
  function hm(dt) { return fa(String(dt || '').substr(11, 5)); }
  function store(k, v) { try { window.localStorage.setItem(k, v); } catch (e) {} }
  function recall(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } }

  // ── داده ────────────────────────────────────────────────────────
  function loadChannels(then) {
    TV.get(API + '/channels' + (token ? '?t=' + encodeURIComponent(token) : ''), function (err, d) {
      if (err || !d || !d.success) { TV.fail('کانال‌ها در دسترس نیستند', 'لطفاً کمی بعد دوباره امتحان کنید.'); return; }
      var all = d.data.channels || [], out = [], i;
      for (i = 0; i < all.length; i++) if (!!all[i].is_radio === RADIO) out.push(all[i]);
      channels = out;
      if (then) then();
    });
  }

  function loadEpg() {
    TV.get('/api/v1/player/epg/' + encodeURIComponent(CODE), function (err, d) {
      if (err || !d || !d.success) return;
      var m = {}, i;
      for (i = 0; i < d.data.length; i++) m[d.data[i].channel_id] = d.data[i];
      epg = m;
      if (cur >= 0 && TV.hasClass(TV.id('lv-bar'), 'is-on')) paintBar();
      if (listOpen) paintList();
    });
  }

  // ── پخش و افزونگی ──────────────────────────────────────────────
  function sourcesOf(ch) {
    var s = [], seen = {};
    function add(url, kind) { if (url && !seen[url]) { seen[url] = 1; s.push({ url: url, kind: kind }); } }
    if (ch.multicast_url && ch.delivery !== 'unicast' && (PLATFORM === 'webos' || PLATFORM === 'tizen')) add(ch.multicast_url, 'native');
    function kindOf(u) { return /\.m3u8(\?|$)/i.test(u) ? 'hls' : 'native'; }
    add(ch.stream_url, kindOf(ch.stream_url || ''));
    add(ch.backup_stream_url, kindOf(ch.backup_stream_url || ''));
    return s;
  }

  function stopPlayer() {
    clearTimeout(player.settle); clearInterval(player.watch); clearTimeout(player.retry);
    if (player.hls) { try { player.hls.destroy(); } catch (e) {} player.hls = null; }
    if (player.v) { try { player.v.pause(); player.v.removeAttribute('src'); player.v.load(); } catch (e2) {} }
    TV.id('lv-video').innerHTML = '';
    player.v = null;
  }

  function msg(t, sub) {
    var m = TV.id('lv-msg');
    if (!t) { m.style.display = 'none'; return; }
    m.innerHTML = TV.esc(t) + (sub ? '<small>' + TV.esc(sub) + '</small>' : '');
    m.style.display = 'block';
  }

  function play(idx) {
    if (idx < 0 || idx >= channels.length) return;
    var ch = channels[idx];
    if (ch.locked && !ch.stream_url && !ch.multicast_url) { openPin(idx); return; }

    stopPlayer();
    cur = idx;
    store(STORE, String(ch.id));
    TV.id('lv-radio').style.display = RADIO ? 'block' : 'none';
    TV.text(TV.id('lv-radio-name'), ch.name);
    player.sources = sourcesOf(ch);
    player.idx = 0;
    showBar();
    trySource();
  }

  function next() {
    player.idx++;
    trySource();
  }

  function trySource() {
    var ch = channels[cur];
    clearTimeout(player.settle); clearInterval(player.watch);
    if (player.hls) { try { player.hls.destroy(); } catch (e) {} player.hls = null; }
    TV.id('lv-video').innerHTML = '';

    if (player.idx >= player.sources.length) {
      msg('این کانال الان در دسترس نیست', 'هر ۳۰ ثانیه دوباره امتحان می‌شود');
      var keep = cur;
      player.retry = setTimeout(function () { if (cur === keep) play(keep); }, 30000);
      return;
    }
    msg('');
    TV.id('lv-bar-badge').style.display = player.idx > 0 ? 'inline-block' : 'none';

    var src = player.sources[player.idx];
    var v = document.createElement('video');
    v.autoplay = true;
    v.setAttribute('playsinline', 'playsinline');
    TV.id('lv-video').appendChild(v);
    player.v = v;
    var started = false;

    v.onerror = function () { next(); };
    v.onplaying = function () { started = true; };

    if (src.kind === 'hls' && window.Hls && window.Hls.isSupported()) {
      var h = new window.Hls({ liveSyncDurationCount: 3, manifestLoadingMaxRetry: 2, levelLoadingMaxRetry: 2 });
      player.hls = h;
      h.on(window.Hls.Events.ERROR, function (ev, data) { if (data && data.fatal) next(); });
      h.loadSource(src.url);
      h.attachMedia(v);
    } else {
      /* پخش‌کننده‌ی بومی: HLS روی Tizen و webOS، و UDP multicast */
      v.src = src.url;
    }
    try { var p = v.play(); if (p && p['catch']) p['catch'](function () {}); } catch (e3) {}

    /* منبعی که نه خطا می‌دهد نه پخش می‌کند — روی بعضی تلویزیون‌ها
       آدرس بد همین‌طور می‌ماند و صفحه سیاه */
    player.settle = setTimeout(function () { if (!started) next(); }, 12000);

    /* تصویر یخ‌زده: پخش شروع شده ولی زمان جلو نمی‌رود. همان چیزی که
       قطعی ماهواره یا افتادن ترنسکدر روی تلویزیون می‌سازد. */
    player.lastT = -1; player.stuck = 0;
    player.watch = setInterval(function () {
      if (!started || !player.v) return;
      var t = player.v.currentTime || 0;
      if (t === player.lastT && !player.v.paused) player.stuck += 3; else player.stuck = 0;
      player.lastT = t;
      if (player.stuck >= 12) next();
    }, 3000);
  }

  function zap(dir) {
    if (!channels.length) return;
    var n = cur < 0 ? 0 : (cur + dir + channels.length) % channels.length;
    play(n);
  }

  // ── نوار اطلاعات ────────────────────────────────────────────────
  function showBar() {
    paintBar();
    TV.addClass(TV.id('lv-bar'), 'is-on');
    clearTimeout(barTimer);
    barTimer = setTimeout(function () { TV.removeClass(TV.id('lv-bar'), 'is-on'); }, 6000);
  }

  function paintBar() {
    var ch = channels[cur];
    if (!ch) return;
    TV.text(TV.id('lv-bar-no'), ch.channel_no ? fa(ch.channel_no) : '');
    TV.text(TV.id('lv-bar-name'), ch.name);
    var logo = TV.id('lv-bar-logo');
    if (ch.logo_url) { logo.src = ch.logo_url; logo.style.display = 'inline-block'; } else logo.style.display = 'none';
    var e = epg[ch.id] || {};
    TV.text(TV.id('lv-bar-now'), e.now ? 'الان: ' + e.now.title + '  (' + hm(e.now.starts_at) + ' تا ' + hm(e.now.ends_at) + ')' : '');
    TV.text(TV.id('lv-bar-next'), e.next ? 'بعدی: ' + hm(e.next.starts_at) + ' ' + e.next.title : '');
    var pr = TV.id('lv-bar-prog');
    if (e.progress !== null && e.progress !== undefined && e.now) {
      pr.style.display = 'block';
      TV.id('lv-bar-progv').style.width = e.progress + '%';
    } else pr.style.display = 'none';
  }

  // ── فهرست کانال ─────────────────────────────────────────────────
  function openList() {
    listOpen = true;
    listFocus = cur < 0 ? 0 : cur;
    TV.text(TV.id('lv-list-title'), RADIO ? 'رادیو' : 'کانال‌ها');
    paintList();
    TV.addClass(TV.id('lv-list'), 'is-on');
  }
  function closeList() { listOpen = false; TV.removeClass(TV.id('lv-list'), 'is-on'); }

  function paintList() {
    var h = '', i, c, e;
    /* فقط پنجره‌ی اطراف فوکوس — صد ردیف DOM روی تلویزیون قدیمی کند است */
    var from = Math.max(0, listFocus - 6), to = Math.min(channels.length, from + 13);
    for (i = from; i < to; i++) {
      c = channels[i]; e = epg[c.id] || {};
      h += '<div class="lv-row' + (i === listFocus ? ' is-focused' : '') + (i === cur ? ' is-cur' : '') + '">' +
           '<span class="lv-row-no">' + (c.channel_no ? fa(c.channel_no) : '') + '</span>' +
           '<span class="lv-row-name">' + TV.esc(c.name) + (c.locked ? ' 🔒' : '') + '</span>' +
           (e.now ? '<div class="lv-row-now">' + TV.esc(e.now.title) + '</div>' : '') + '</div>';
    }
    if (!channels.length) h = '<div class="lv-row">کانالی برای این اتاق تعریف نشده است</div>';
    TV.id('lv-rows').innerHTML = h;
  }

  // ── شماره‌ی کانال ───────────────────────────────────────────────
  function digit(d) {
    numBuf += String(d);
    var box = TV.id('lv-num');
    box.textContent = fa(numBuf);
    box.style.display = 'block';
    clearTimeout(numTimer);
    numTimer = setTimeout(commitNum, numBuf.length >= 3 ? 400 : 2000);
  }
  function commitNum() {
    var n = parseInt(numBuf, 10), i;
    numBuf = '';
    TV.id('lv-num').style.display = 'none';
    for (i = 0; i < channels.length; i++) {
      if (+channels[i].channel_no === n) { play(i); return; }
    }
    msg('کانال ' + fa(n) + ' وجود ندارد');
    setTimeout(function () { msg(''); }, 2500);
  }

  // ── قفل والدین ──────────────────────────────────────────────────
  function openPin(idx) {
    pinFor = idx; pinDigits = '';
    TV.text(TV.id('lv-pin-dots'), '····');
    TV.text(TV.id('lv-pin-msg'), '');
    TV.addClass(TV.id('lv-pin'), 'is-on');
  }
  function closePin() { pinFor = -1; TV.removeClass(TV.id('lv-pin'), 'is-on'); }
  function pinDigit(d) {
    if (pinDigits.length >= 4) return;
    pinDigits += String(d);
    TV.text(TV.id('lv-pin-dots'), '●●●●'.substr(0, pinDigits.length) + '····'.substr(pinDigits.length));
    if (pinDigits.length < 4) return;
    var target = channels[pinFor] ? channels[pinFor].id : 0;
    TV.post(API + '/parental/unlock', { pin: pinDigits }, function (err, d) {
      if (err || !d || !d.success || !d.data || !d.data.token) {
        pinDigits = '';
        TV.text(TV.id('lv-pin-dots'), '····');
        TV.text(TV.id('lv-pin-msg'), (err && err.data && err.data.message) || 'رمز اشتباه است');
        return;
      }
      token = d.data.token;
      closePin();
      /* با توکن، آدرس کانال‌های قفل هم می‌آید */
      loadChannels(function () {
        var i;
        for (i = 0; i < channels.length; i++) if (channels[i].id === target) { play(i); return; }
      });
    });
  }

  // ── ریموت ───────────────────────────────────────────────────────
  window.TVGuestKey = function (k) {
    var d = TV.keyDigit(k);

    if (pinFor >= 0) {
      if (d >= 0) pinDigit(d);
      else if (k === 'BACK' || k === 'EXIT') closePin();
      return true;
    }

    if (listOpen) {
      if (k === 'UP')   { if (listFocus > 0) { listFocus--; paintList(); } return true; }
      if (k === 'DOWN') { if (listFocus < channels.length - 1) { listFocus++; paintList(); } return true; }
      if (k === 'CH_UP')   { listFocus = Math.max(0, listFocus - 10); paintList(); return true; }
      if (k === 'CH_DOWN') { listFocus = Math.min(channels.length - 1, listFocus + 10); paintList(); return true; }
      if (k === 'OK')   { closeList(); play(listFocus); return true; }
      if (k === 'BACK' || k === 'EXIT' || k === 'LEFT' || k === 'RIGHT') { closeList(); return true; }
      return true;
    }

    if (d >= 0) { digit(d); return true; }
    if (k === 'UP' || k === 'CH_UP')     { zap(1); return true; }
    if (k === 'DOWN' || k === 'CH_DOWN') { zap(-1); return true; }
    if (k === 'OK')    { openList(); return true; }
    if (k === 'LEFT' || k === 'RIGHT') { showBar(); return true; }
    if (k === 'BACK' || k === 'EXIT') {
      if (TV.hasClass(TV.id('lv-bar'), 'is-on')) { clearTimeout(barTimer); TV.removeClass(TV.id('lv-bar'), 'is-on'); return true; }
      stopPlayer();
      return false;
    }
    return false;
  };

  TV.on(document, 'keydown', function (e) {
    var k = TV.keyName(e);
    if (!k) return;
    var used = window.TVGuestKey(k);
    if (!used && (k === 'BACK' || k === 'EXIT') && window.parent === window) {
      try { window.history.back(); } catch (x) {}
    }
    if (used && e.preventDefault) e.preventDefault();
  });

  TV.boot();
  TV.loading('در حال بارگذاری کانال‌ها…');
  loadChannels(function () {
    TV.ready();
    if (!channels.length) { msg(RADIO ? 'رادیویی برای این اتاق تعریف نشده است' : 'کانالی برای این اتاق تعریف نشده است'); return; }
    var last = recall(STORE), i, start = 0;
    for (i = 0; i < channels.length; i++) if (String(channels[i].id) === last && !channels[i].locked) start = i;
    /* کانال قفل هرگز خودکار باز نمی‌شود، حتی اگر آخرین کانال بوده */
    if (channels[start].locked) { for (i = 0; i < channels.length; i++) if (!channels[i].locked) { start = i; break; } }
    play(start);
    loadEpg();
  });
  setInterval(loadEpg, 60000);
})();
</script>
</body>
</html>
