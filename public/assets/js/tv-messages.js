/* ───────────────────────────────────────────────────────────────────
   نمایش پیام و تبلیغ روی تلویزیون — مشترک برای همهٔ پروفایل‌های پلیر
   ───────────────────────────────────────────────────────────────────

   مشکلی که این فایل حل می‌کند:

   سرور در هر heartbeat کلید `messages` را می‌فرستد (از جدول
   `screen_messages`، با هدف، بازهٔ زمانی و تکرار). ولی از ده پروفایل
   پلیر، فقط `modern` آن را می‌خواند. تلویزیون‌های واقعی هتل روی
   `samsung_tv` و `orsay_tv` و `lg_tv` و `android_tv` اجرا می‌شوند —
   یعنی اپراتور اعلان می‌ساخت، پنل «ثبت شد» می‌گفت، و هیچ تلویزیونی
   نشانش نمی‌داد. خطای بی‌صدا، بدترین نوعش.

   چرا یک فایل جدا و نه کپی‌کردن کد `modern`:
   کد آن پروفایل به `TV.*` از `tv-base.js` و تابع محلی `esc` و یک
   عنصر `#msg-ov` در HTML و کلاس‌های CSS همان صفحه وابسته است. هیچ‌کدام
   در پروفایل‌های دیگر نیست. پس این فایل خودبسنده است: کمک‌کننده‌های
   کوچک خودش، CSS خودش، و عنصر خودش را می‌سازد. پروفایل فقط یک خط
   صدا زدن لازم دارد.

   ES5 خالص: پروفایل Orsay روی سامسونگ ۲۰۱۳ اجرا می‌شود (WebKit ~534).
   نه const/let، نه arrow، نه template literal، نه classList، نه
   flexbox جدید.

   ‏نکته‌ای که در `modern` اشتباه بود: دکمهٔ «بستن» فقط onclick داشت.
   روی تلویزیون نشانگر ماوس وجود ندارد، پس آن دکمه در عمل تزئینی بود و
   تنها راه بسته‌شدن، پایان مهلت بود. اینجا OK و Back ریموت هم پیام را
   می‌بندند.
   ─────────────────────────────────────────────────────────────────── */

(function (w, d) {
  'use strict';

  if (w.TVMSG) return;   // دوبار بارگذاری بی‌خطر باشد

  var OV_ID = 'tvmsg-ov';
  var ICONS = {
    welcome: '🤝', congratulation: '🎉',
    announcement: '📢', warning: '⚠', info: 'ℹ'
  };

  function esc(s) {
    return String(s === null || s === undefined ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function isColor(c) { return /^#[0-9a-fA-F]{6}$/.test(c || ''); }

  function injectCss() {
    if (d.getElementById('tvmsg-css')) return;
    var st = d.createElement('style');
    st.id = 'tvmsg-css';
    /* عمدا position:absolute و بدون flexbox: چیدمان مرکزی با
       translate روی WebKit 534 هم کار می‌کند، ولی flex جدید نه. */
    st.appendChild(d.createTextNode(
      '#' + OV_ID + '{position:fixed;left:0;top:0;right:0;bottom:0;z-index:2147483000;' +
      'display:none;background:rgba(0,0,0,.45);}' +
      '#' + OV_ID + '.on{display:block;}' +
      '#' + OV_ID + ' .tvmsg-card{position:absolute;box-sizing:border-box;' +
      'border:2px solid #4098db;border-radius:14px;padding:22px 26px;' +
      'font-family:inherit;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,.6);}' +
      /* overlay و popup: وسط صفحه */
      '#' + OV_ID + ' .as-overlay,#' + OV_ID + ' .as-popup{left:50%;top:50%;' +
      'width:62%;margin-left:-31%;-webkit-transform:translateY(-50%);transform:translateY(-50%);}' +
      /* fullscreen: تمام صفحه، برای هشدار */
      '#' + OV_ID + ' .as-fullscreen{left:0;top:0;right:0;bottom:0;width:auto;' +
      'border-radius:0;border-width:0;padding:10% 8%;}' +
      /* banner: نوار پایین، تا پخش را نپوشاند */
      '#' + OV_ID + ' .as-banner{left:0;right:0;bottom:0;width:auto;border-radius:0;' +
      'border-width:2px 0 0 0;padding:14px 22px;text-align:center;}' +
      /* رسانه: بدون object-fit — پروفایل Orsay روی WebKit 534 اجرا
         می‌شود و آن ویژگی را ندارد؛ max-width/max-height همان نتیجه را
         بی کشیدگی می‌دهد. */
      '#' + OV_ID + ' .tvmsg-media{display:block;margin:0 auto;max-width:100%;' +
      'max-height:52vh;border-radius:8px;}' +
      '#' + OV_ID + ' .as-fullscreen .tvmsg-media{max-height:72vh;}' +
      '#' + OV_ID + ' .as-banner .tvmsg-media{max-height:18vh;}' +
      '#' + OV_ID + ' .tvmsg-icon{font-size:40px;line-height:1.2;display:block;}' +
      '#' + OV_ID + ' .tvmsg-title{font-size:30px;font-weight:700;margin:8px 0;}' +
      '#' + OV_ID + ' .as-banner .tvmsg-title{font-size:22px;margin:0;}' +
      '#' + OV_ID + ' .tvmsg-rule{height:3px;width:70px;margin:12px auto;border-radius:2px;}' +
      '#' + OV_ID + ' .tvmsg-body{font-size:21px;line-height:1.7;}' +
      '#' + OV_ID + ' .as-banner .tvmsg-body{font-size:17px;}' +
      '#' + OV_ID + ' .tvmsg-hint{margin-top:16px;font-size:14px;opacity:.75;}'
    ));
    (d.head || d.getElementsByTagName('head')[0]).appendChild(st);
  }

  function overlay() {
    var ov = d.getElementById(OV_ID);
    if (!ov) {
      injectCss();
      ov = d.createElement('div');
      ov.id = OV_ID;
      d.body.appendChild(ov);
    }
    return ov;
  }

  var TVMSG = {
    queue: [],
    seen:  {},
    now:   null,
    timer: null,

    /* متن به زبان دستگاه. تلویزیون اتاق روی زبان میهمان تنظیم می‌شود،
       پس اگر ترجمه هست همان را نشان بده. */
    pick: function (m, f) {
      var lang = (navigator.language || 'fa').substring(0, 2);
      if (lang === 'en' && m[f + '_en']) return m[f + '_en'];
      if (lang === 'ar' && m[f + '_ar']) return m[f + '_ar'];
      return m[f] || '';
    },

    /**
     * پیام‌های تازه را به صف اضافه می‌کند.
     * ‏heartbeat هر ۳۰ ثانیه همان پیام را دوباره می‌فرستد (تا پایان
     * بازه‌اش)، پس بی حافظهٔ `seen` هر نیم‌دقیقه یک‌بار روی صورت میهمان
     * باز می‌شد.
     */
    enqueue: function (list) {
      if (!list || !list.length) return;
      var i, m;
      for (i = 0; i < list.length; i++) {
        m = list[i];
        if (!m || this.seen[m.id]) continue;
        this.seen[m.id] = 1;
        this.queue.push(m);
      }
      if (!this.now) this.showNext();
    },

    showNext: function () {
      if (!this.queue.length) { this.now = null; this.hide(); return; }
      this.now = this.queue.shift();
      this.render(this.now);
    },

    render: function (m) {
      var ov = overlay();
      var style  = m.style || 'overlay';
      var title  = this.pick(m, 'title');
      var body   = this.pick(m, 'body');
      var accent = isColor(m.accent_color) ? m.accent_color : '#4098db';
      var bg     = isColor(m.bg_color)     ? m.bg_color     : '#131a2b';
      var fg     = isColor(m.text_color)   ? m.text_color   : '#ffffff';

      var secs = parseInt(m.duration, 10);
      if (!secs || isNaN(secs) || secs < 2) secs = 15;

      /* جهت از خودِ متن حدس زده می‌شود: پیام انگلیسی در قاب راست‌چین
         بد می‌نشیند و برعکس. */
      var rtl = /[؀-ۿ]/.test(title + body);

      var html = '<div class="tvmsg-card as-' + esc(style) + '" dir="' + (rtl ? 'rtl' : 'ltr') + '"' +
                 ' style="background:' + esc(bg) + ';color:' + esc(fg) +
                 ';border-color:' + esc(accent) + '">';
      /* تبلیغ تصویری: اگر رسانه‌ای هست، خودش محتوای اصلی است و جای
         آیکن می‌نشیند. ویدیو با autoplay و بی‌صدا پخش می‌شود — صدای
         ناگهانی روی تلویزیون اتاق میهمان را می‌پراند، و پروفایل Orsay
         هم بیش از یک منبع صوتی همزمان را خوب اداره نمی‌کند. */
      if (m.media_url) {
        if (m.media_type === 'video') {
          html += '<video class="tvmsg-media" src="' + esc(m.media_url) +
                  '" autoplay muted playsinline></video>';
        } else {
          html += '<img class="tvmsg-media" src="' + esc(m.media_url) + '" alt="">';
        }
      } else if (style !== 'banner') {
        html += '<span class="tvmsg-icon">' + esc(m.icon || ICONS[m.type] || ICONS.announcement) + '</span>';
      }
      if (title) html += '<div class="tvmsg-title">' + esc(title) + '</div>';
      if (style !== 'banner') {
        html += '<div class="tvmsg-rule" style="background:' + esc(accent) + '"></div>';
      }
      if (body) html += '<div class="tvmsg-body">' + esc(body) + '</div>';
      /* راهنما جای دکمه: روی تلویزیون نشانگری نیست که دکمه را بزند */
      if (style !== 'banner') {
        html += '<div class="tvmsg-hint">' + (rtl ? 'برای بستن OK را بزنید' : 'Press OK to close') + '</div>';
      }
      html += '</div>';

      ov.innerHTML = html;
      ov.className = 'on';

      var self = this;
      clearTimeout(this.timer);
      this.timer = setTimeout(function () { self.dismiss(); }, secs * 1000);
    },

    hide: function () {
      var ov = d.getElementById(OV_ID);
      if (ov) { ov.className = ''; ov.innerHTML = ''; }
    },

    dismiss: function () {
      clearTimeout(this.timer);
      this.now = null;
      this.hide();
      /* فاصله بین دو پیام، وگرنه سه اعلان پشت‌سرهم مثل یک پرش دیده
         می‌شود و میهمان هیچ‌کدام را نمی‌خواند */
      var self = this;
      if (this.queue.length) setTimeout(function () { self.showNext(); }, 800);
    },

    /** آیا پیامی روی صفحه است — پروفایل می‌تواند بپرسد تا کلید را ندزدد */
    isOpen: function () { return !!this.now; }
  };

  /* کلیدهای ریموت: OK/Enter و Back/Escape پیام را می‌بندند.
     روی تلویزیون سامسونگ کد Back برابر ۱۰۰۰۹ است و روی LG برابر ۴۶۱؛
     هر دو پذیرفته می‌شوند چون یک فایل روی هر دو اجرا می‌شود. */
  function onKey(e) {
    if (!TVMSG.now) return;
    var k = e.keyCode || e.which;
    if (k === 13 || k === 27 || k === 10009 || k === 461) {
      TVMSG.dismiss();
      if (e.preventDefault) e.preventDefault();
      if (e.stopPropagation) e.stopPropagation();
    }
  }

  if (d.addEventListener) d.addEventListener('keydown', onKey, true);
  else if (d.attachEvent) d.attachEvent('onkeydown', onKey);

  w.TVMSG = TVMSG;
})(window, document);
