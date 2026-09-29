/* ═══════════════════════════════════════════════════════════════════
   Hotel Media — پایه‌ی صفحه‌های تعاملی مهمان روی تلویزیون
   (رزرو، خدمات و سفارش، صورتحساب)
   ═══════════════════════════════════════════════════════════════════

   ES5 خالص — همان دلیل tv-base.js. به tv-base.js وابسته است.

   هر صفحه در iframe پورتال IPTV باز می‌شود. کلیدهای ریموت به پورتال
   می‌رسند و پورتال آن‌ها را با window.TVGuestKey(k) به صفحه می‌دهد.
   اگر این تابع false برگرداند (BACK در صفحه‌ی اول) پورتال iframe را
   می‌بندد. صفحه‌ای که مستقیم باز شده، keydown خودش را گوش می‌دهد.

   مدل فوکوس: هر عنصر قابل انتخاب کلاس fx و data-i دارد و acts[i]
   کاری است که OK روی آن انجام می‌دهد. جهت‌ها با TV.findNeighbor روی
   مکان واقعی عنصرها حرکت می‌کنند، نه ترتیب آرایه.

   بازرس: node tests/Support/tv-compat-lint.js
   ═══════════════════════════════════════════════════════════════════ */
(function (global) {
  'use strict';

  var TV  = global.TV;
  var TVG = {};

  // ── قالب‌بندی ────────────────────────────────────────────────────
  TVG.fa = function (s) {
    return String(s == null ? '' : s).replace(/[0-9]/g, function (d) {
      return '۰۱۲۳۴۵۶۷۸۹'.charAt(+d);
    });
  };

  /* toLocaleString روی مرورگر تلویزیون داده‌ی fa-IR ندارد */
  TVG.money = function (n) {
    var s = String(Math.round(+n || 0)), out = '';
    while (s.length > 3) { out = ',' + s.slice(-3) + out; s = s.slice(0, -3); }
    return TVG.fa(s + out) + ' ریال';
  };

  /* ساعت از «Y-m-d H:i:s» */
  TVG.hm = function (dt) { return TVG.fa(String(dt || '').substr(11, 5)); };

  /* پیام خطای سرور اگر رسیده باشد؛ tv-base.js آن را در err.data می‌گذارد */
  TVG.errMsg = function (err, fallback) {
    return (err && err.data && err.data.message) || fallback;
  };

  // ── صفحه ─────────────────────────────────────────────────────────
  TVG.ui = function (root) {
    var fx = [], acts = [], cur = 0;
    var ui = { busy: false };

    ui.btn = function (cls, html, fn) {
      acts.push(fn);
      return '<div class="fx ' + cls + '" data-i="' + (acts.length - 1) + '">' + html + '</div>';
    };

    ui.paint = function (build, focusIdx) {
      acts = [];
      root.innerHTML = build();
      fx = TV.all('.fx', root);
      ui.focus(focusIdx || 0);
    };

    ui.focus = function (i) {
      if (!fx.length) { cur = 0; return; }
      if (i < 0) i = 0;
      if (i >= fx.length) i = fx.length - 1;
      var k;
      for (k = 0; k < fx.length; k++) TV.toggleClass(fx[k], 'is-focused', k === i);
      cur = i;
      /* فهرست بلند از پایین صفحه بیرون می‌زند */
      if (fx[i].scrollIntoView) { try { fx[i].scrollIntoView(false); } catch (e) {} }
    };

    ui.cur = function () { return cur; };

    /* جهت‌ها و OK. true یعنی کلید مصرف شد. */
    ui.nav = function (k) {
      if (k === 'LEFT' || k === 'RIGHT' || k === 'UP' || k === 'DOWN') {
        var n = TV.findNeighbor(fx, cur, k);
        if (n >= 0) ui.focus(n);
        return true;
      }
      if (k === 'OK') {
        var el = fx[cur];
        if (el && !ui.busy) {
          var fn = acts[+el.getAttribute('data-i')];
          if (fn) fn();
        }
        return true;
      }
      return false;
    };

    ui.hint = function (t) { return '<div class="g-hint">' + t + '</div>'; };

    return ui;
  };

  /* صفحه را به پورتال (یا به keydown خودش) وصل می‌کند.
     handle(k) باید true برگرداند اگر کلید را مصرف کرد. */
  TVG.mount = function (handle) {
    global.TVGuestKey = handle;

    /* فقط وقتی مستقیم باز شده. در iframe فوکوس روی پورتال است و این
       شنونده صدا زده نمی‌شود. */
    TV.on(document, 'keydown', function (e) {
      var k = TV.keyName(e);
      if (!k) return;
      var used = handle(k);
      if (!used && (k === 'BACK' || k === 'EXIT') && global.parent === global) {
        try { global.history.back(); } catch (x) {}
      }
      if (used && e.preventDefault) e.preventDefault();
    });

    TV.boot();
  };

  global.TVG = TVG;
})(window);
