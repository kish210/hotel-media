/* ═══════════════════════════════════════════════════════════════════
   Hotel Media — صفحه‌کلید عددیِ فعال‌سازی برای ریموت تلویزیون
   ═══════════════════════════════════════════════════════════════════

   مشکلی که حل می‌کند: روی تلویزیون هتلی سامسونگ (هم Tizen هم Orsay
   ۲۰۱۳) و ال‌جی، صفحه‌ی «کد فعال‌سازی را وارد کنید» با یک <input> ساده
   غیرقابل‌استفاده است:
     • صفحه‌کلید نرم روی URL Launcher باز نمی‌شود یا فوکوس نمی‌گیرد.
     • روی Tizen کلیدهای عددیِ ریموت تا وقتی registerKey نشوند اصلا به
       وب‌اپ تحویل داده نمی‌شوند — به نظر می‌رسد «ریموت کار نمی‌کند».
     • کدکلیدِ اعداد بین Orsay و Tizen و webOS یکسان نیست.

   راه‌حلی که همه‌جا کار می‌کند: پایه بر «کلیدهای جهتی + OK» است، چون
   این‌ها روی هر سه پلتفرم همیشه تحویل داده می‌شوند. ورود مستقیم با
   اعداد هم به‌عنوان میان‌بر پشتیبانی می‌شود (و روی Tizen با registerKey
   فعال می‌گردد).

   این فایل هر صفحه‌ی فعال‌سازیِ موجود را «ارتقا» می‌دهد: به `#act-inp`
   مقدار می‌دهد و در پایان `doActivate()` خودِ پروفایل را صدا می‌زند —
   پس منطق ارسال هر پروفایل دست‌نخورده می‌ماند.

   ES5 خالص: نه const/let، نه arrow، نه template literal، نه fetch.
   بازرس: node tests/Support/tv-compat-lint.js
   ═══════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var CODE_LEN = 6;

  /* چیدمان کیبورد — ۳ ستون. back و ok در ردیف آخر. */
  var KEYS = ['1','2','3','4','5','6','7','8','9','back','0','ok'];
  var COLS = 3;

  var code = '';
  var focus = 0;          /* خانه‌ی فوکوس‌شده در KEYS */
  var cells = [];         /* عناصر DOM خانه‌ها */
  var display = null;
  var input = null;

  function id(x) { return document.getElementById(x); }

  /* ── ساخت رابط ────────────────────────────────────────────────── */
  function build() {
    var box = id('act-box') || id('act');
    if (!box) return false;

    input = id('act-inp');

    /* ‏input اصلی دیگر تایپ‌شدنی نیست: روی تلویزیون فایده ندارد و
       صفحه‌کلید نرم را بی‌خود باز می‌کند. فقط نگه‌دارنده‌ی مقدار است. */
    if (input) {
      input.setAttribute('readonly', 'readonly');
      input.style.display = 'none';
    }
    /* دکمه‌ی «فعال‌سازی» قدیمی هم پنهان می‌شود — تایید در کیبورد است */
    var oldBtn = id('act-btn');
    if (oldBtn) oldBtn.style.display = 'none';

    /* نمایش کد */
    display = document.createElement('div');
    display.id = 'tva-display';
    display.style.cssText =
      'font:700 32px/1.2 monospace;letter-spacing:12px;padding:14px 10px;' +
      'background:#0d0d14;border:2px solid rgba(26,122,196,.45);border-radius:12px;' +
      'color:#fff;text-align:center;direction:ltr;margin-bottom:14px;';

    /* کیبورد */
    var pad = document.createElement('div');
    pad.id = 'tva-pad';
    pad.style.cssText =
      'display:-webkit-box;display:-webkit-flex;display:flex;' +
      '-webkit-flex-wrap:wrap;flex-wrap:wrap;';

    var i, cell;
    for (i = 0; i < KEYS.length; i++) {
      cell = document.createElement('div');
      cell.setAttribute('data-k', KEYS[i]);
      cell.style.cssText = baseCellCss(KEYS[i]);
      cell.innerHTML = label(KEYS[i]);
      /* کلیک برای تلویزیون‌هایی که اشاره‌گر دارند */
      cell.onclick = (function (n) {
        return function () { focus = n; render(); press(KEYS[n]); };
      })(i);
      pad.appendChild(cell);
      cells.push(cell);
    }

    var hint = document.createElement('div');
    hint.style.cssText = 'font-size:12px;color:#64748b;margin-top:12px;line-height:1.8;';
    hint.innerHTML = 'با دکمه‌های <b>جهت‌دار</b> حرکت کنید و <b>OK</b> را بزنید' +
                     '<br>اگر ریموت عدد دارد، مستقیم عدد را بزنید';

    /* جای درج: قبل از پیام خطا اگر بود، وگرنه ته جعبه */
    var msg = id('act-msg');
    if (msg && msg.parentNode) {
      msg.parentNode.insertBefore(display, msg);
      msg.parentNode.insertBefore(pad, msg);
      msg.parentNode.insertBefore(hint, msg.nextSibling);
    } else {
      box.appendChild(display);
      box.appendChild(pad);
      box.appendChild(hint);
    }

    render();
    return true;
  }

  function baseCellCss(k) {
    var w = (k === 'ok' || k === 'back') ? '48%' : '31.33%';
    return 'box-sizing:border-box;width:' + w + ';margin:1%;padding:16px 0;' +
           'text-align:center;font:700 22px/1 sans-serif;color:#fff;' +
           'background:rgba(255,255,255,.07);border:2px solid transparent;' +
           'border-radius:10px;cursor:pointer;';
  }

  function label(k) {
    if (k === 'back') return '&#9003; حذف';
    if (k === 'ok')   return 'تایید';
    return k;
  }

  /* ── رندر وضعیت ───────────────────────────────────────────────── */
  function render() {
    var shown = '', i;
    for (i = 0; i < CODE_LEN; i++) shown += (i < code.length ? code.charAt(i) : '–');
    if (display) display.innerHTML = shown;

    for (i = 0; i < cells.length; i++) {
      var on = (i === focus);
      var k  = KEYS[i];
      cells[i].style.borderColor = on ? '#4098db' : 'transparent';
      cells[i].style.background  = on
        ? 'rgba(64,152,219,.30)'
        : (k === 'ok' ? 'rgba(18,85,143,.55)'
          : (k === 'back' ? 'rgba(120,60,80,.35)' : 'rgba(255,255,255,.07)'));
    }
    if (input) input.value = code;
  }

  /* ── عمل یک خانه ──────────────────────────────────────────────── */
  function press(k) {
    if (k === 'back') { code = code.substring(0, code.length - 1); render(); return; }
    if (k === 'ok')   { submit(); return; }
    if (code.length < CODE_LEN) { code += k; render(); }
    /* با کامل شدن کد خودش ارسال می‌شود — یک دکمه کمتر برای مهمان */
    if (code.length === CODE_LEN) { submit(); }
  }

  function submit() {
    var msg = id('act-msg');
    if (code.length !== CODE_LEN) {
      if (msg) { msg.style.color = '#f59e0b'; msg.innerHTML = 'کد ' + CODE_LEN + ' رقمی را کامل کنید'; }
      return;
    }
    if (input) input.value = code;
    /* منطق ارسالِ خودِ پروفایل — دست‌نخورده */
    if (typeof window.doActivate === 'function') {
      window.doActivate();
    } else if (msg) {
      msg.style.color = '#ef4444';
      msg.innerHTML = 'تابع فعال‌سازی پیدا نشد';
    }
  }

  /* ── حرکت فوکوس ───────────────────────────────────────────────── */
  function move(dx, dy) {
    var n = KEYS.length;
    var row = Math.floor(focus / COLS), col = focus % COLS;
    var rows = Math.ceil(n / COLS);

    if (dx) {
      col += dx;
      if (col < 0) col = COLS - 1;
      if (col > COLS - 1) col = 0;
    }
    if (dy) {
      row += dy;
      if (row < 0) row = rows - 1;
      if (row > rows - 1) row = 0;
    }
    var next = row * COLS + col;
    /* ردیف آخر ممکن است کوتاه‌تر باشد */
    if (next >= n) next = n - 1;
    focus = next;
    render();
  }

  /* ── نگاشت کلید ───────────────────────────────────────────────── */
  function digitOf(e) {
    /* e.key مدرن‌ترین راه است و روی Tizen جدید هست */
    if (e.key && e.key.length === 1 && e.key >= '0' && e.key <= '9') return e.key;
    var c = e.keyCode || e.which || 0;
    if (c >= 48 && c <= 57)   return String(c - 48);   /* ردیف بالا */
    if (c >= 96 && c <= 105)  return String(c - 96);   /* numpad */
    return null;
  }

  function onKey(e) {
    /* فقط وقتی صفحه‌ی فعال‌سازی روی صفحه است */
    if (!id('act')) return;

    var c = e.keyCode || e.which || 0;
    var d = digitOf(e);

    if (d !== null) { press(d); stop(e); return; }

    switch (c) {
      case 37: move(-1, 0); stop(e); return;                 /* چپ */
      case 39: move(1, 0);  stop(e); return;                 /* راست */
      case 38: move(0, -1); stop(e); return;                 /* بالا */
      case 40: move(0, 1);  stop(e); return;                 /* پایین */
      case 13: press(KEYS[focus]); stop(e); return;          /* OK */
      case 8:                                                /* Backspace */
      case 10009:                                            /* Return — Tizen */
      case 461:                                              /* Back — webOS */
      case 88:                                               /* Orsay/برخی مدل‌ها */
        press('back'); stop(e); return;
    }
  }

  function stop(e) {
    if (e.preventDefault) e.preventDefault();
    if (e.stopPropagation) e.stopPropagation();
    e.returnValue = false;
  }

  /* ── ثبت کلیدهای عددی روی Tizen ───────────────────────────────
     بدون این، ریموتِ Tizen اعداد را به وب‌اپ نمی‌دهد. جهت‌دارها و
     OK همیشه می‌آیند، پس اگر این هم شکست بخورد رابط کار می‌کند. */
  function registerTizenKeys() {
    try {
      if (window.tizen && window.tizen.tvinputdevice) {
        var want = ['0','1','2','3','4','5','6','7','8','9'], i;
        for (i = 0; i < want.length; i++) {
          try { window.tizen.tvinputdevice.registerKey(want[i]); } catch (e1) {}
        }
      }
    } catch (e2) {}
  }

  /* ── راه‌اندازی ───────────────────────────────────────────────── */
  function init() {
    if (!id('act')) return;          /* صفحه فعال است، کاری نداریم */
    registerTizenKeys();
    if (!build()) return;
    /* از این لحظه کلیدها مال ماست — هندلرِ قدیمیِ Enter در پروفایل‌ها
       با دیدن این پرچم کنار می‌کشد تا کدِ نیمه‌کاره ارسال نشود. */
    window.__tvaOwnsKeys = true;
    /* هم keydown هم keypress: بعضی مدل‌های قدیمی فقط یکی را می‌دهند */
    if (document.addEventListener) {
      document.addEventListener('keydown', onKey, false);
    } else if (document.attachEvent) {
      document.attachEvent('onkeydown', onKey);
    }
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    init();
  } else if (document.addEventListener) {
    document.addEventListener('DOMContentLoaded', init, false);
  } else {
    window.onload = init;
  }
})();
