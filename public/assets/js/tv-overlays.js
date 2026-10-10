/* ───────────────────────────────────────────────────────────────────
   overlay پنجره‌دار تابلو — فاز ۵/۱۰ استودیو
   ───────────────────────────────────────────────────────────────────

   مشکلی که این فایل حل می‌کند:

   استودیوی تایم‌لاین اجازه می‌داد اپراتور یک لوگو یا تصویر را از
   ثانیهٔ ۱۰ تا ۲۰ بگذارد. ولی `TimelineService::compile()` آن پنجره را
   به `logo_path` تمام‌مدت فشرده می‌کرد و هشدار می‌داد «پلیر پنجره‌دار
   را پشتیبانی نمی‌کند». پس اپراتور چیزی را تنظیم می‌کرد، ذخیره می‌شد،
   و روی تابلو رفتار دیگری می‌دید — همان الگویی که در این بازسازی
   مکررا پیدا شد.

   حالا پنجره‌ها خودشان به پلیر می‌رسند (`playlist.overlays`) و اینجا
   بر اساس زمان سپری‌شده از شروع دور نشان داده و پنهان می‌شوند.

   چرا زمان را خودمان می‌شماریم و از ویدیو نمی‌گیریم: دور پلی‌لیست از
   تصویر و ویدیو و محتوای پویا ساخته شده؛ `video.currentTime` فقط
   داخل یک کلیپ ویدیویی معنا دارد و روی تصویر هیچ. پس ساعتِ دور را
   پلیر با گزارش «آیتم چندم شروع شد» به ما می‌دهد.

   ES5 خالص: پروفایل Orsay روی سامسونگ ۲۰۱۳ (WebKit ~534) اجرا می‌شود.
   نه const/let، نه arrow، نه classList، نه flexbox جدید.
   ─────────────────────────────────────────────────────────────────── */

(function (w, d) {
  'use strict';

  if (w.TVOV) return;

  var BOX_ID = 'tvov-layer';

  function injectCss() {
    if (d.getElementById('tvov-css')) return;
    var st = d.createElement('style');
    st.id = 'tvov-css';
    /* ‏pointer-events:none روی لایه: overlay نباید جلوی کلیک و کلید
       بقیهٔ پلیر را بگیرد. */
    st.appendChild(d.createTextNode(
      '#' + BOX_ID + '{position:fixed;left:0;top:0;right:0;bottom:0;' +
      'z-index:2147481000;pointer-events:none;overflow:hidden;}' +
      '#' + BOX_ID + ' img{position:absolute;display:block;}'
    ));
    (d.head || d.getElementsByTagName('head')[0]).appendChild(st);
  }

  function layer() {
    var b = d.getElementById(BOX_ID);
    if (!b) {
      injectCss();
      b = d.createElement('div');
      b.id = BOX_ID;
      d.body.appendChild(b);
    }
    return b;
  }

  /* جای overlay. درصد و نه پیکسل: تابلوها ۱۰۸۰p و ۷۲۰p و گاهی عمودی
     هستند و مقدار پیکسلی روی یکی درست می‌نشیند و روی دیگری بیرون
     می‌زند. */
  function place(el, o) {
    var pos = o.position || 'top-right';

    if (pos === 'free' && o.x !== null && o.y !== null) {
      el.style.left = o.x + '%';
      el.style.top  = o.y + '%';
      return;
    }

    var v = pos.indexOf('top') === 0 ? 'top' : (pos.indexOf('bottom') === 0 ? 'bottom' : 'middle');
    var h = pos.indexOf('left') !== -1 ? 'left' : (pos.indexOf('right') !== -1 ? 'right' : 'center');

    if (v === 'top')         el.style.top = '3%';
    else if (v === 'bottom') el.style.bottom = '3%';
    else                     el.style.top = '42%';

    if (h === 'left')        el.style.left = '3%';
    else if (h === 'right')  el.style.right = '3%';
    else                   { el.style.left = '50%'; el.style.marginLeft = '-10%'; }
  }

  var TVOV = {
    items: [],
    loop:  0,
    base:  0,     // زمان شروع دور (میلی‌ثانیه)
    timer: null,
    shown: {},

    /**
     * overlayها را از بستهٔ پلی‌لیست می‌گیرد.
     * @param {object} ov  مقدار playlist.overlays : {loop, items}
     */
    load: function (ov) {
      this.stop();
      this.items = (ov && ov.items) ? ov.items : [];
      this.loop  = (ov && ov.loop)  ? Number(ov.loop) : 0;
      this.shown = {};
      layer().innerHTML = '';
      if (this.items.length) { this.base = (new Date()).getTime(); this.start(); }
    },

    /**
     * پلیر وقتی آیتم شماره‌ی idx را شروع می‌کند این را صدا می‌زند و
     * ساعت دور هم‌تراز می‌شود.
     *
     * بی این هم‌ترازی، ساعت ما و پخش واقعی کم‌کم از هم دور می‌افتند:
     * ویدیویی که بافر می‌شود یا تصویری که دیر لود می‌شود، چند صدم
     * ثانیه عقب می‌اندازد و پس از یک ساعت پنجره‌ها جابه‌جا می‌شوند.
     *
     * @param {number} startedAtSec شروع این آیتم روی تایم‌لاین (ثانیه)
     */
    sync: function (startedAtSec) {
      var s = Number(startedAtSec);
      if (isNaN(s) || s < 0) return;
      this.base = (new Date()).getTime() - (s * 1000);
    },

    start: function () {
      var self = this;
      clearInterval(this.timer);
      /* دو بار در ثانیه: پنجره‌ها واحدشان ثانیه است و این دقت کافی
         است؛ بیشتر از این روی پردازندهٔ تلویزیون ۲۰۱۳ بی‌دلیل است. */
      this.timer = setInterval(function () { self.tick(); }, 500);
      this.tick();
    },

    tick: function () {
      if (!this.items.length) return;

      var t = ((new Date()).getTime() - this.base) / 1000;
      if (this.loop > 0) t = t % this.loop;

      var i, o, key, on;
      for (i = 0; i < this.items.length; i++) {
        o   = this.items[i];
        key = 'o' + i;
        on  = (t >= o.start && t < o.end);

        if (on && !this.shown[key])      this.show(key, o);
        else if (!on && this.shown[key]) this.hide(key);
      }
    },

    show: function (key, o) {
      var el = d.createElement('img');
      el.id  = 'tvov-' + key;
      el.src = o.src;
      el.style.opacity = (o.opacity === 0 ? 0 : (o.opacity || 100)) / 100;
      /* اندازه با درصدِ عرض صفحه: scale در استودیو ۱۰۰ یعنی «اندازه‌ی
         طبیعی»، که روی تابلو حدود یک‌پنجم عرض است. */
      el.style.width = ((o.scale || 100) * 0.18) + '%';
      if (o.rotation) {
        el.style.webkitTransform = 'rotate(' + o.rotation + 'deg)';
        el.style.transform       = 'rotate(' + o.rotation + 'deg)';
      }
      place(el, o);
      layer().appendChild(el);
      this.shown[key] = 1;
    },

    hide: function (key) {
      var el = d.getElementById('tvov-' + key);
      if (el && el.parentNode) el.parentNode.removeChild(el);
      delete this.shown[key];
    },

    stop: function () {
      clearInterval(this.timer);
      this.timer = null;
      this.items = [];
      this.shown = {};
      var b = d.getElementById(BOX_ID);
      if (b) b.innerHTML = '';
    }
  };

  w.TVOV = TVOV;
})(window, document);
