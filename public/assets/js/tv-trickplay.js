/* ═══════════════════════════════════════════════════════════════════
   Hotel Media — Trick-play: جلو/عقب چندسرعته روی VOD و Catch-up
   ═══════════════════════════════════════════════════════════════════

   چرا با «پرش زمانی» و نه playbackRate:
     playbackRate بالای ۲x روی مرورگر تلویزیون یا نادیده گرفته می‌شود یا
     صدا را خرد می‌کند و تصویر جا می‌ماند. روش استاندارد خودِ تلویزیون‌ها
     هم همین است: ویدیو مکث می‌شود و currentTime پله‌ای جلو می‌رود، پس
     ۳۲x هم روی SoC ضعیف نرم است.

   فقط روی منبع قابل‌جست‌وجو فعال می‌شود. پخش زنده (multicast/کانال)
   duration بی‌نهایت دارد و trick-play رویش بی‌معنی است — عمدا رد می‌شود.

   ES5 خالص: نه const/let، نه arrow، نه template literal، نه classList.
   بازرس: node tests/Support/tv-compat-lint.js
   ═══════════════════════════════════════════════════════════════════ */
(function (global) {
  'use strict';

  var SPEEDS   = [2, 4, 8, 16, 32];   /* کاماسیستم تا ۳۲x دارد */
  var TICK_MS  = 250;                 /* هر پله؛ کوچک‌تر = نرم‌تر، پرهزینه‌تر */
  var JUMP_SEC = 10;                  /* پرش کوتاه با کلید چپ/راست */
  var HIDE_MS  = 2500;                /* بعد از این مدت بی‌حرکتی نوار محو شود */

  var T = {};

  function fmt(sec) {
    if (!isFinite(sec) || sec < 0) sec = 0;
    sec = Math.floor(sec);
    var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
    var mm = (m < 10 ? '0' : '') + m, ss = (s < 10 ? '0' : '') + s;
    return h > 0 ? (h + ':' + mm + ':' + ss) : (mm + ':' + ss);
  }

  /* ── ساخت نوار وضعیت ─────────────────────────────────────────── */
  function makeBar() {
    var wrap = document.createElement('div');
    wrap.style.cssText =
      'position:absolute;left:0;right:0;bottom:0;padding:18px 26px 22px;' +
      'background:-webkit-linear-gradient(top,rgba(0,0,0,0),rgba(0,0,0,.85));' +
      'background:linear-gradient(to bottom,rgba(0,0,0,0),rgba(0,0,0,.85));' +
      'color:#fff;font-family:Tahoma,Arial,sans-serif;z-index:60;opacity:0;' +
      '-webkit-transition:opacity .25s;transition:opacity .25s;';

    var top = document.createElement('div');
    top.style.cssText = 'display:-webkit-box;display:flex;-webkit-box-pack:justify;' +
                        'justify-content:space-between;font-size:20px;margin-bottom:10px;';

    var state = document.createElement('div');
    state.style.cssText = 'font-weight:700;';

    var time = document.createElement('div');
    time.style.cssText = 'font-family:monospace;direction:ltr;';

    top.appendChild(state);
    top.appendChild(time);

    var track = document.createElement('div');
    track.style.cssText = 'height:8px;background:rgba(255,255,255,.22);border-radius:4px;overflow:hidden;';

    var fill = document.createElement('div');
    fill.style.cssText = 'height:8px;width:0;background:#4098db;';
    track.appendChild(fill);

    wrap.appendChild(top);
    wrap.appendChild(track);

    return { wrap: wrap, state: state, time: time, fill: fill };
  }

  /* ── ثبت کلیدهای رسانه روی Tizen ─────────────────────────────────
     مثل کلیدهای عددی، این‌ها هم تا registerKey نشوند به وب‌اپ نمی‌رسند
     و به نظر می‌رسد ریموت کار نمی‌کند. */
  function registerMediaKeys() {
    try {
      if (global.tizen && global.tizen.tvinputdevice) {
        var want = ['MediaPlayPause','MediaPlay','MediaPause','MediaStop',
                    'MediaRewind','MediaFastForward'], i;
        for (i = 0; i < want.length; i++) {
          try { global.tizen.tvinputdevice.registerKey(want[i]); } catch (e1) {}
        }
      }
    } catch (e2) {}
  }

  /**
   * trick-play را روی یک ویدیو فعال می‌کند.
   * @param {HTMLVideoElement} v
   * @param {HTMLElement} host ظرفی که نوار داخلش می‌نشیند (باید position دار باشد)
   * @return {object|null} کنترلر، یا null اگر منبع قابل جست‌وجو نبود
   */
  T.attach = function (v, host) {
    if (!v || !host) return null;

    registerMediaKeys();

    var ui      = makeBar();
    var dir     = 0;            /* 0 = عادی، 1 = جلو، -1 = عقب */
    var sIdx    = 0;            /* اندیس در SPEEDS */
    var timer   = null;
    var hideTm  = null;
    var alive   = true;

    host.appendChild(ui.wrap);

    function seekable() {
      var d = v.duration;
      return isFinite(d) && d > 0;
    }

    function show() {
      ui.wrap.style.opacity = '1';
      clearTimeout(hideTm);
      hideTm = setTimeout(function () {
        if (dir === 0 && !v.paused) ui.wrap.style.opacity = '0';
      }, HIDE_MS);
    }

    function render() {
      var d = seekable() ? v.duration : 0;
      var c = v.currentTime || 0;
      ui.fill.style.width = (d > 0 ? Math.min(100, (c / d) * 100) : 0) + '%';
      ui.time.innerHTML = fmt(c) + ' / ' + (d > 0 ? fmt(d) : '--:--');

      if (dir === 0) {
        ui.state.innerHTML = v.paused ? '&#10073;&#10073; مکث' : '&#9654; پخش';
      } else {
        ui.state.innerHTML = (dir > 0 ? '&#9193; ' : '&#9194; ') + SPEEDS[sIdx] + 'x';
      }
    }

    function normal() {
      dir = 0; sIdx = 0;
      if (timer) { clearInterval(timer); timer = null; }
      render(); show();
    }

    function step() {
      if (!alive || !seekable()) { normal(); return; }
      var delta = SPEEDS[sIdx] * (TICK_MS / 1000) * dir;
      var next  = (v.currentTime || 0) + delta;

      if (next <= 0)            { v.currentTime = 0; playNormal(); return; }
      if (next >= v.duration)   { v.currentTime = Math.max(0, v.duration - 0.5); playNormal(); return; }

      v.currentTime = next;
      render();
    }

    function shuttle(d) {
      if (!seekable()) return;
      if (dir === d) {
        /* فشار دوباره در همان جهت = سرعت بعدی، و بعد از ۳۲x برگشت به ۲x */
        sIdx = (sIdx + 1) % SPEEDS.length;
      } else {
        dir = d; sIdx = 0;
      }
      try { v.pause(); } catch (e) {}   /* صدا در حالت پرش معنی ندارد */
      if (timer) clearInterval(timer);
      timer = setInterval(step, TICK_MS);
      render(); show();
    }

    function playNormal() {
      normal();
      try { v.play(); } catch (e) {}
      render();
    }

    function toggle() {
      if (dir !== 0) { playNormal(); return; }
      if (v.paused) { try { v.play(); } catch (e) {} }
      else          { try { v.pause(); } catch (e) {} }
      render(); show();
    }

    function jump(sec) {
      if (!seekable()) return;
      var n = (v.currentTime || 0) + sec;
      v.currentTime = Math.max(0, Math.min(v.duration - 0.5, n));
      render(); show();
    }

    function onKey(e) {
      if (!alive) return;
      var c = e.keyCode || e.which || 0;
      var handled = true;

      switch (c) {
        case 417: case 228:              shuttle(1);  break;   /* FF — Tizen/webOS */
        case 412: case 227:              shuttle(-1); break;   /* RW */
        case 415:                        playNormal(); break;   /* Play */
        case 19:  case 10252:            toggle();     break;   /* Pause / PlayPause */
        case 413:                        /* Stop */
          try { v.pause(); } catch (e2) {}
          normal();
          break;
        case 13:                         toggle();     break;   /* OK */
        case 39:                         jump(JUMP_SEC);  break; /* راست */
        case 37:                         jump(-JUMP_SEC); break; /* چپ */
        default: handled = false;
      }

      if (handled) {
        if (e.preventDefault)  e.preventDefault();
        if (e.stopPropagation) e.stopPropagation();
      }
    }

    /* نوار با پیشرفت پخش تازه می‌شود */
    function onTime() { if (dir === 0) render(); }

    if (document.addEventListener) document.addEventListener('keydown', onKey, false);
    if (v.addEventListener) {
      v.addEventListener('timeupdate', onTime, false);
      v.addEventListener('loadedmetadata', function () { render(); show(); }, false);
      v.addEventListener('pause', render, false);
      v.addEventListener('play',  render, false);
    }

    render();
    show();

    return {
      destroy: function () {
        alive = false;
        if (timer) clearInterval(timer);
        clearTimeout(hideTm);
        if (document.removeEventListener) document.removeEventListener('keydown', onKey, false);
        if (v.removeEventListener) v.removeEventListener('timeupdate', onTime, false);
        if (ui.wrap.parentNode) ui.wrap.parentNode.removeChild(ui.wrap);
      },
      isSeekable: seekable
    };
  };

  global.TVTrick = T;
})(window);
