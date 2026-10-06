/* ───────────────────────────────────────────────────────────────────
   زیرنویس ویدیوی تابلو — مشترک برای همهٔ پروفایل‌های پلیر
   ───────────────────────────────────────────────────────────────────

   مشکلی که این فایل حل می‌کند:

   جدول `media_subtitles` در migration 039 ساخته شد و هرگز وصل نشد —
   تنها ارجاعش در تمام مخزن، همان CREATE TABLE بود. پس زیرنویس روی
   رسانهٔ تابلو، که سفارش داده شده بود، فقط یک جدول خالی داشت.

   چرا `<track>` به‌کار نرفت:
   هیچ‌کدام از ده پروفایل پلیر از TextTrack استفاده نمی‌کند، و دلیلش
   خوب است — پروفایل Orsay روی سامسونگ ۲۰۱۳ با WebKit ~534 اجرا
   می‌شود که نه `<track>` را می‌شناسد و نه `video.textTracks` را. اگر
   زیرنویس را به مرورگر می‌سپردیم، روی همان تلویزیون‌هایی کار نمی‌کرد
   که این هتل بیشترشان را دارد.

   پس VTT را خودمان می‌خوانیم و با `video.currentTime` هم‌زمان
   می‌کنیم. همین روش روی هر پروفایلی کار می‌کند و رفتارش همه‌جا یکی
   است — چیزی که با TextTrack بومی هیچ‌وقت به‌دست نمی‌آمد.

   ES5 خالص: نه const/let، نه arrow، نه fetch، نه classList.
   ─────────────────────────────────────────────────────────────────── */

(function (w, d) {
  'use strict';

  if (w.TVSUB) return;

  var BOX_ID = 'tvsub-box';

  function injectCss() {
    if (d.getElementById('tvsub-css')) return;
    var st = d.createElement('style');
    st.id = 'tvsub-css';
    /* ‏text-shadow و نه پس‌زمینهٔ تمام‌عرض: زیرنویس نباید تصویر را
       بپوشاند، ولی روی قاب روشن هم باید خوانده شود. */
    st.appendChild(d.createTextNode(
      '#' + BOX_ID + '{position:fixed;left:6%;right:6%;bottom:7%;z-index:2147482000;' +
      'text-align:center;pointer-events:none;display:none;}' +
      '#' + BOX_ID + '.on{display:block;}' +
      '#' + BOX_ID + ' span{display:inline-block;padding:6px 14px;border-radius:6px;' +
      'background:rgba(0,0,0,.62);color:#fff;font-size:30px;line-height:1.5;' +
      'font-family:inherit;text-shadow:0 2px 4px rgba(0,0,0,.9);}'
    ));
    (d.head || d.getElementsByTagName('head')[0]).appendChild(st);
  }

  /* قاب موجودِ پروفایل مقدم است.
     پروفایل `modern` از قبل `#subtitle-text` و CSS خودش را دارد (برای
     فرمان زیرنویس از پنل). اگر قاب خودمان را هم بسازیم، روی آن
     پروفایل دو زیرنویس هم‌زمان روی هم می‌نشیند. */
  function box() {
    var own = d.getElementById('subtitle-text');
    if (own) return { el: own, native: true };

    var b = d.getElementById(BOX_ID);
    if (!b) {
      injectCss();
      b = d.createElement('div');
      b.id = BOX_ID;
      d.body.appendChild(b);
    }
    return { el: b, native: false };
  }

  function paint(html) {
    var r = box();
    if (html === '') {
      r.el.innerHTML = '';
      r.el.className = r.native ? '' : '';
      return;
    }
    /* قاب خودمان یک <span> داخلی می‌خواهد؛ قاب `modern` خودش استایل
       دارد و کلاسش `is-on` است. */
    r.el.innerHTML = r.native ? html : '<span>' + html + '</span>';
    r.el.className = r.native ? 'is-on' : 'on';
  }

  function esc(s) {
    return String(s === null || s === undefined ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  /** «00:01:02.500» یا «01:02.500» → ثانیه */
  function toSec(t) {
    var p = String(t).trim().replace(',', '.').split(':');
    var s = 0, i;
    for (i = 0; i < p.length; i++) s = s * 60 + parseFloat(p[i]);
    return isNaN(s) ? 0 : s;
  }

  /**
   * خواندن VTT. فقط آنچه لازم است: زمان شروع و پایان و متن.
   * ‏cue با موقعیت و استایل (align، line، WEBVTT CSS) نادیده گرفته
   * می‌شود — روی تلویزیون هتل همیشه پایین‌وسط درست است و پیاده‌سازی
   * کاملِ مشخصات، کدی می‌شد که هیچ‌وقت آزموده نمی‌شود.
   */
  function parseVtt(text) {
    var out = [];
    var lines = String(text).replace(/\r/g, '').split('\n');
    var i, line, times, cue = null;

    for (i = 0; i < lines.length; i++) {
      line = lines[i];

      if (line.indexOf('-->') !== -1) {
        times = line.split('-->');
        cue = {
          from: toSec(times[0]),
          to:   toSec((times[1] || '').split(' ')[0] === '' ? times[1] : times[1].trim().split(' ')[0]),
          text: ''
        };
        out.push(cue);
        continue;
      }

      if (cue) {
        if (line.trim() === '') { cue = null; continue; }
        cue.text += (cue.text ? '\n' : '') + line;
      }
    }

    /* مرتب‌سازی لازم است: فایل‌های دست‌ساز همیشه مرتب نیستند و جستجوی
       خطیِ ما به ترتیب تکیه می‌کند. */
    out.sort(function (a, b) { return a.from - b.from; });
    return out;
  }

  var TVSUB = {
    cues:  [],
    video: null,
    timer: null,
    shown: null,

    /**
     * زیرنویس یک آیتم را روی عنصر ویدیو می‌نشاند.
     *
     * @param {HTMLVideoElement} video
     * @param {Array} subs فهرست {lang,label,src,is_default} از خودِ آیتم
     * @param {string} [prefer] کد زبان ترجیحی (زبان اتاق)
     */
    attach: function (video, subs, prefer) {
      this.detach();
      if (!video || !subs || !subs.length) return;

      var pick = null, i;
      if (prefer) {
        for (i = 0; i < subs.length; i++) {
          if (subs[i].lang === prefer) { pick = subs[i]; break; }
        }
      }
      if (!pick) {
        for (i = 0; i < subs.length; i++) {
          if (subs[i].is_default) { pick = subs[i]; break; }
        }
      }
      /* بی پیش‌فرض و بی زبان ترجیحی، زیرنویس خودکار روشن نمی‌شود:
         زیرنویسی که کسی نخواسته، روی تابلوی تبلیغاتی فقط تصویر را
         خراب می‌کند. */
      if (!pick) return;

      this.video = video;
      var self = this;

      var x = new XMLHttpRequest();
      x.open('GET', pick.src, true);
      x.onload = function () {
        if (x.status >= 200 && x.status < 300) {
          self.cues = parseVtt(x.responseText);
          if (self.cues.length) self.start();
        }
      };
      /* شکست دانلود زیرنویس نباید پخش را متوقف کند */
      x.onerror = function () {};
      try { x.send(null); } catch (e) {}
    },

    start: function () {
      var self = this;
      clearInterval(this.timer);
      /* ۴ بار در ثانیه: برای زیرنویس کافی است و روی پردازندهٔ ضعیف
         تلویزیون ۲۰۱۳ بار محسوسی نمی‌گذارد. */
      this.timer = setInterval(function () { self.tick(); }, 250);
    },

    tick: function () {
      if (!this.video) return;
      var t = this.video.currentTime || 0;
      var i, c, hit = null;

      for (i = 0; i < this.cues.length; i++) {
        c = this.cues[i];
        if (c.from <= t && t <= c.to) { hit = c; break; }
        if (c.from > t) break;   // مرتب است، جلوتر نرو
      }

      var txt = hit ? hit.text : '';
      if (txt === this.shown) return;   // ‏DOM بی‌دلیل دست‌کاری نشود
      this.shown = txt;

      paint(txt === '' ? '' : esc(txt).replace(/\n/g, '<br>'));
    },

    detach: function () {
      clearInterval(this.timer);
      this.timer = null;
      this.cues  = [];
      this.video = null;
      this.shown = null;
      paint('');
    }
  };

  w.TVSUB = TVSUB;
})(window, document);
