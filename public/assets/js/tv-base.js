/* ═══════════════════════════════════════════════════════════════════
   Hotel Media — کمک‌تابع‌های مشترک صفحه‌های تلویزیون
   ═══════════════════════════════════════════════════════════════════

   ES5 خالص. نه const/let، نه تابع arrow، نه رشته‌ی template، نه fetch،
   نه Promise — چون قدیمی‌ترین تلویزیونی که پشتیبانی می‌کنیم
   Samsung Tizen 2.3 روی Chromium 34 است و LG webOS 3 روی Chromium 38.

   نکته‌ای که آسان فراموش می‌شود: وقتی کد ES6 روی این مرورگرها اجرا شود
   خطای کنسول به جایی نمی‌رسد — کل اسکریپت با SyntaxError رد می‌شود و
   صفحه سیاه می‌ماند. برای همین بازرس tests/Support/tv-compat-lint.js
   این فایل را هم بررسی می‌کند.

   بازرس: node tests/Support/tv-compat-lint.js
   ═══════════════════════════════════════════════════════════════════ */
(function (global) {
  'use strict';

  var TV = {};

  /* ── اندازه‌گذاری ─────────────────────────────────────────────────
     به‌جای clamp() که از Chromium 79 است، اندازه‌ی فونت ریشه را از عرض
     واقعی صفحه حساب می‌کنیم و بقیه‌ی CSS بر حسب rem نوشته شده.
     مبنا: ۱۹۲۰ ⇒ 16px. پس ۱۳۶۶ ⇒ ~11.4 و ۳۸۴۰ ⇒ 32.

     سقف و کف دارد چون بعضی تلویزیون‌ها عرض عجیب گزارش می‌کنند؛ بدون
     کف، یک گزارش ۰ یا ۳۲۰ کل رابط را ناخوانا می‌کرد. */
  var BASE_W  = 1920;
  var BASE_PX = 16;
  var MIN_PX  = 9;
  var MAX_PX  = 34;

  TV.scale = function () {
    var w = 0;
    /* ترتیب مهم است: بعضی نسخه‌های webOS برای innerWidth صفر می‌دهند
       ولی documentElement.clientWidth درست است. */
    if (global.innerWidth)                    w = global.innerWidth;
    if (!w && document.documentElement)       w = document.documentElement.clientWidth;
    if (!w && document.body)                  w = document.body.clientWidth;
    if (!w && global.screen && global.screen.width) w = global.screen.width;
    if (!w) w = BASE_W;

    var px = (w / BASE_W) * BASE_PX;
    if (px < MIN_PX) px = MIN_PX;
    if (px > MAX_PX) px = MAX_PX;

    document.documentElement.style.fontSize = px.toFixed(2) + 'px';
    return px;
  };

  /* ── DOM ──────────────────────────────────────────────────────── */
  TV.id = function (x) { return document.getElementById(x); };

  TV.all = function (sel, root) {
    var list = (root || document).querySelectorAll(sel);
    var out = [], i;
    /* NodeList روی این مرورگرها آرایه نیست و forEach ندارد */
    for (i = 0; i < list.length; i++) out.push(list[i]);
    return out;
  };

  TV.on = function (el, ev, fn) {
    if (!el) return;
    if (el.addEventListener) el.addEventListener(ev, fn, false);
    else if (el.attachEvent) el.attachEvent('on' + ev, fn);   /* خیلی قدیمی */
  };

  TV.addClass = function (el, c) {
    if (!el || TV.hasClass(el, c)) return;
    el.className = el.className ? el.className + ' ' + c : c;
  };

  TV.removeClass = function (el, c) {
    if (!el) return;
    el.className = (' ' + el.className + ' ')
      .replace(' ' + c + ' ', ' ')
      .replace(/^\s+|\s+$/g, '');
  };

  TV.hasClass = function (el, c) {
    if (!el || !el.className) return false;
    return (' ' + el.className + ' ').indexOf(' ' + c + ' ') !== -1;
  };

  TV.toggleClass = function (el, c, want) {
    if (want) TV.addClass(el, c); else TV.removeClass(el, c);
  };

  TV.text = function (el, s) { if (el) el.textContent = s == null ? '' : String(s); };

  TV.show = function (el) { TV.removeClass(el, 'tv-hidden'); };
  TV.hide = function (el) { TV.addClass(el, 'tv-hidden'); };

  /* ── متن امن ──────────────────────────────────────────────────────
     هر چیزی که از API می‌آید (نام کانال، عنوان برنامه، نام هتل) ممکن
     است < یا & داشته باشد. چون innerHTML برای ساخت کاشی‌ها لازم است،
     این تابع باید همه‌جا دور مقدارهای سرور بپیچد. */
  TV.esc = function (s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  /* ── شبکه ─────────────────────────────────────────────────────────
     fetch از Chromium 42 است. XHR همه‌جا هست.
     نتیجه همیشه به صورت cb(err, data) برمی‌گردد. */
  function request(method, url, body, cb) {
    var xhr, done = false, timer;

    function finish(err, data) {
      if (done) return;
      done = true;
      if (timer) clearTimeout(timer);
      if (cb) cb(err, data);
    }

    try {
      xhr = new XMLHttpRequest();
    } catch (e) {
      finish(new Error('XHR در دسترس نیست'), null);
      return;
    }

    /* تلویزیون گاهی روی شبکه‌ی کند هتل درخواست را باز نگه می‌دارد و
       هیچ‌وقت readyState 4 نمی‌شود؛ بدون این نگهبان صفحه برای همیشه
       روی «در حال بارگذاری» می‌ماند. xhr.timeout روی بعضی نسخه‌های
       webOS نادیده گرفته می‌شود، پس تایمر دستی هم می‌گذاریم. */
    timer = setTimeout(function () {
      try { xhr.abort(); } catch (e2) {}
      finish(new Error('پاسخی نرسید'), null);
    }, 15000);

    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      var ok = (xhr.status >= 200 && xhr.status < 300) || xhr.status === 304;
      if (!ok) { finish(new Error('HTTP ' + xhr.status), null); return; }
      var data = null;
      try { data = xhr.responseText ? JSON.parse(xhr.responseText) : null; }
      catch (e3) { finish(new Error('پاسخ JSON نبود'), null); return; }
      finish(null, data);
    };

    try {
      xhr.open(method, url, true);
      xhr.setRequestHeader('Accept', 'application/json');
      if (body != null) xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.send(body == null ? null : JSON.stringify(body));
    } catch (e4) {
      finish(e4, null);
    }
  }

  TV.get  = function (url, cb) { request('GET', url, null, cb); };
  TV.post = function (url, body, cb) { request('POST', url, body || {}, cb); };

  /* ── کدهای کلید ───────────────────────────────────────────────────
     هر سازنده کد خودش را دارد و بعضی با هم تداخل دارند، پس نگاشت
     صریح لازم است. مرجع: راهنمای Pro:Centric و Tizen.

     تله‌ای که وقت زیادی گرفت: کد ۴۶۱ روی webOS «برگشت» است ولی روی
     Tizen «خروج» — پس هر دو را به BACK نگاشت می‌کنیم و خودِ صفحه
     تصمیم می‌گیرد. */
  var KEYS = {
    37: 'LEFT',   38: 'UP',    39: 'RIGHT', 40: 'DOWN',
    13: 'OK',     32: 'OK',
    8:  'BACK',   27: 'BACK',  461: 'BACK', 10009: 'BACK',
    48: 'D0', 49: 'D1', 50: 'D2', 51: 'D3', 52: 'D4',
    53: 'D5', 54: 'D6', 55: 'D7', 56: 'D8', 57: 'D9',
    /* صفحه‌کلید عددی جانبی */
    96: 'D0', 97: 'D1', 98: 'D2', 99: 'D3', 100: 'D4',
    101:'D5', 102:'D6', 103:'D7', 104:'D8', 105:'D9',
    33: 'CH_UP', 34: 'CH_DOWN',     /* PageUp/PageDown = کانال بالا/پایین */
    427:'CH_UP', 428:'CH_DOWN',     /* webOS */
    403:'RED',  404:'GREEN', 405:'YELLOW', 406:'BLUE',
    415:'PLAY', 19: 'PAUSE', 413:'STOP',
    412:'REWIND', 417:'FORWARD',
    10182:'EXIT',                   /* Tizen */

    /* زیرنویس — روی ریموت هتلی همیشه دکمه‌ی جدا ندارد، پس دکمه‌ی
       زرد هم همین کار را می‌کند. کد ۴۶۰ روی webOS و ۱۰۲۲۱ روی Tizen. */
    460: 'SUBTITLE', 10221: 'SUBTITLE',
    /* انتخاب صدا — فقط بعضی ریموت‌ها دارند */
    10190: 'AUDIO'
  };

  TV.keyName = function (ev) {
    var c = ev.keyCode || ev.which || 0;
    return KEYS[c] || '';
  };

  /* رقم ۰ تا ۹ از نام کلید، یا -1 */
  TV.keyDigit = function (name) {
    if (name.length === 2 && name.charAt(0) === 'D') {
      var d = parseInt(name.charAt(1), 10);
      return isNaN(d) ? -1 : d;
    }
    return -1;
  };

  /* ── ثبت کلیدهای ریموت ───────────────────────────────────────────
     بدون این، دکمه‌های رنگی و عددی ریموت اصلا به صفحه نمی‌رسند و
     تلویزیون خودش آن‌ها را می‌بلعد. هر دو API در try جدا هستند چون
     اگر یکی خطا بدهد نباید جلوی دیگری را بگیرد. */
  TV.registerKeys = function () {
    /* Tizen */
    try {
      if (global.tizen && global.tizen.tvinputdevice) {
        var want = ['ColorF0Red', 'ColorF1Green', 'ColorF2Yellow', 'ColorF3Blue',
                    'ChannelUp', 'ChannelDown', 'MediaPlay', 'MediaPause',
                    'MediaStop', 'MediaRewind', 'MediaFastForward', '0','1','2',
                    '3','4','5','6','7','8','9'];
        var i;
        for (i = 0; i < want.length; i++) {
          /* تک‌تک، چون یک کلید پشتیبانی‌نشده کل دسته را رد می‌کند */
          try { global.tizen.tvinputdevice.registerKey(want[i]); } catch (e1) {}
        }
      }
    } catch (e) {}

    /* webOS دکمه‌های عددی و رنگی را به‌صورت پیش‌فرض می‌فرستد و
       ثبت جدا لازم ندارد. */
  };

  /* ── پرش فوکوس در شبکه ────────────────────────────────────────────
     پیداکردن همسایه بر اساس مکان واقعی روی صفحه، نه ترتیب آرایه.
     ترتیب آرایه وقتی کاشی‌ها wrap می‌شوند غلط جواب می‌دهد: «پایین»
     باید کاشیِ زیرِ همین ستون را بدهد، نه کاشی بعدی در آرایه. */
  TV.findNeighbor = function (items, current, dir) {
    if (!items.length) return -1;
    if (current < 0 || current >= items.length) return 0;

    var cur  = items[current].getBoundingClientRect();
    var cx   = cur.left + cur.width  / 2;
    var cy   = cur.top  + cur.height / 2;
    var best = -1, bestScore = Infinity, i, r, dx, dy, score;

    for (i = 0; i < items.length; i++) {
      if (i === current) continue;
      r  = items[i].getBoundingClientRect();
      dx = (r.left + r.width  / 2) - cx;
      dy = (r.top  + r.height / 2) - cy;

      /* فقط همسایه‌های همان جهت. آستانه‌ی ۴ پیکسل چون هم‌ترازی
         کامل نیست و بدون آن، همان ردیف هم «پایین» حساب می‌شد. */
      if (dir === 'LEFT'  && dx >= -4) continue;
      if (dir === 'RIGHT' && dx <=  4) continue;
      if (dir === 'UP'    && dy >= -4) continue;
      if (dir === 'DOWN'  && dy <=  4) continue;

      /* فاصله در محور حرکت ارزان است، انحراف از محور گران —
         تا «راست» کاشیِ کنارِ دست را بدهد نه یکی دو ردیف پایین‌تر. */
      if (dir === 'LEFT' || dir === 'RIGHT') score = Math.abs(dx) + Math.abs(dy) * 3;
      else                                   score = Math.abs(dy) + Math.abs(dx) * 3;

      if (score < bestScore) { bestScore = score; best = i; }
    }
    return best;
  };

  /* ── ساعت ────────────────────────────────────────────────────────
     تاریخ شمسی از سرور می‌آید (PHP آن را می‌سازد)؛ اینجا فقط ساعت
     به‌روز می‌شود تا نیازی به کتابخانه‌ی تقویم روی تلویزیون نباشد. */
  TV.startClock = function (elTime, fmt24) {
    function tick() {
      var d = new Date();
      var h = d.getHours();
      var m = d.getMinutes();
      var suffix = '';
      if (!fmt24) {
        suffix = h < 12 ? ' AM' : ' PM';
        h = h % 12; if (h === 0) h = 12;
      }
      TV.text(elTime, (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m) + suffix);
    }
    tick();
    /* هر ۱۵ ثانیه، نه هر ثانیه — روی SoC تلویزیون بیدارشدن مکرر
       باعث تپق در پخش ویدیو می‌شود و ساعت دقیقه‌ای است. */
    setInterval(tick, 15000);
  };

  /* ── نمایش خطا ───────────────────────────────────────────────────
     مهمان نباید صفحه‌ی سیاه ببیند. هر شکستی باید متن فارسی بدهد. */
  TV.fail = function (title, text) {
    var box = TV.id('tv-center');
    if (!box) {
      box = document.createElement('div');
      box.id = 'tv-center';
      box.className = 'tv-center';
      document.body.appendChild(box);
    }
    box.innerHTML =
      '<div class="tv-center-title">' + TV.esc(title) + '</div>' +
      '<div class="tv-center-text">'  + TV.esc(text)  + '</div>';
    TV.show(box);
  };

  TV.loading = function (text) {
    var box = TV.id('tv-center');
    if (!box) return;
    box.innerHTML =
      '<div class="tv-spinner"></div>' +
      '<div class="tv-center-text">' + TV.esc(text || 'در حال بارگذاری…') + '</div>';
    TV.show(box);
  };

  TV.ready = function () { TV.hide(TV.id('tv-center')); };

  /* ── اجرای فرمان‌های پنل ─────────────────────────────────────────
     یک‌جا، چون قبلا هر پخش‌کننده فهرست خودش را داشت و از هم دور
     افتاده بودند: پنل اجازه‌ی open_url و clear_cache و message و
     volume را برای همه‌ی پلتفرم‌ها می‌داد (DeviceService::CAPABILITIES)
     ولی هیچ صفحه‌ای اجراشان نمی‌کرد — اپراتور «تحویل شد» می‌دید و
     هیچ اتفاقی نمی‌افتاد.

     `extra` فرمان‌های مخصوص همان صفحه است (مثل instant_media روی
     تابلو). اگر برگرداند true یعنی خودش رسیدگی کرد.

     ack را همین‌جا می‌فرستیم تا هیچ صفحه‌ای فراموشش نکند. */
  TV.runCommands = function (list, origin, code, extra) {
    if (!list || !list.length) return;
    var i, cmd, name, p;

    for (i = 0; i < list.length; i++) {
      cmd = list[i];
      /* صف جدید (جدول screen_commands) کلید cmd/payload دارد،
         پرچم‌های قدیمی کلید command/data */
      name = cmd.cmd || cmd.command || '';
      p    = cmd.payload || cmd.data || {};

      if (!(extra && extra(name, p, cmd) === true)) {
        TV.runCommand(name, p);
      }

      if (cmd.id && origin && code) {
        TV.post(origin + '/api/v1/device/' + code + '/ack',
                { id: cmd.id, ok: true }, function () {});
      }
    }
  };

  /* فرمان‌های مشترک همه‌ی صفحه‌ها */
  TV.runCommand = function (name, p) {
    p = p || {};

    if (name === 'reload' || name === 'refresh' || name === 'reboot') {
      /* روی webOS و Tizen «ریبوت» کار سرور Pro:Centric و LYNK است،
         نه این صفحه؛ بیشترین کاری که از دست صفحه برمی‌آید بارگذاری
         دوباره است. DeviceService هم برای همین reboot را به آن دو
         پلتفرم نمی‌دهد. */
      location.reload();
      return;
    }

    if (name === 'open_url' && p.url) {
      location.href = p.url;
      return;
    }

    if (name === 'clear_cache') {
      /* آنچه از داخل صفحه پاک‌کردنی است: ذخیره‌ی محلی و کوکی‌های
         غیرضروری. بعدش بارگذاری دوباره تا فایل‌ها تازه بیایند. */
      try { localStorage.clear(); }   catch (e) {}
      try { sessionStorage.clear(); } catch (e2) {}
      location.reload();
      return;
    }

    if (name === 'volume') {
      TV.setVolume(p.level);
      return;
    }

    if (name === 'message') {
      TV.notice(p.title || '', p.body || p.text || '', p.duration);
      return;
    }
  };

  /* صدا — روی همه‌ی المان‌های ویدیوی صفحه.
     ۰ تا ۱۰۰ از پنل می‌آید؛ المان ویدیو ۰ تا ۱ می‌خواهد. */
  TV.setVolume = function (level) {
    var n = Number(level);
    if (isNaN(n)) return;
    if (n > 1) n = n / 100;
    if (n < 0) n = 0;
    if (n > 1) n = 1;

    var vids = document.getElementsByTagName('video'), i;
    for (i = 0; i < vids.length; i++) {
      try {
        vids[i].volume = n;
        /* بدون این، صفر کردن و برگرداندن صدا بی‌اثر می‌ماند چون
           پخش خودکار المان را muted ساخته بود. */
        vids[i].muted = (n === 0);
      } catch (e) {}
    }
  };

  /* پیام کوتاه از پنل. صفحه‌هایی که نمایش پیام اختصاصی دارند
     (تابلو) خودشان رسیدگی می‌کنند و به اینجا نمی‌رسند. */
  TV.notice = function (title, body, seconds) {
    var box = TV.id('tv-notice');
    if (!box) {
      box = document.createElement('div');
      box.id = 'tv-notice';
      box.className = 'tv-notice';
      document.body.appendChild(box);
    }
    box.innerHTML =
      (title ? '<div class="tv-notice-title">' + TV.esc(title) + '</div>' : '') +
      '<div class="tv-notice-body">' + TV.esc(body) + '</div>';
    TV.removeClass(box, 'tv-hidden');

    var s = Number(seconds);
    if (!s || isNaN(s)) s = 15;
    clearTimeout(TV._noticeTimer);
    TV._noticeTimer = setTimeout(function () { TV.addClass(box, 'tv-hidden'); }, s * 1000);
  };

  /* ── ورودی‌های خارجی ──────────────────────────────────────────────
     مهمان می‌خواهد موبایل یا کنسول بازی‌اش را به تلویزیون وصل کند.
     بدون این، تنها راهش رفتن به منوی خود تلویزیون است — همان چیزی که
     در اتاق هتل قفل شده.

     هر پلتفرم راه خودش را دارد و هیچ‌کدام مثل بقیه نیست:

       اندروید  اپ ما: TvInputManager با passthrough input
                 (فقط روی تلویزیون واقعی؛ Mi Stick و Mi Box پورت
                  ورودی HDMI ندارند و فهرست خالی می‌دهند)

       webOS     ورودی‌ها خودشان اپ‌اند: com.webos.app.hdmi1 تا hdmi4
                 و externalinput.av1 — با applicationmanager/launch
                 باز می‌شوند (ACG لازم: application.launcher)

       Tizen     tizen.tvwindow.setSource با فهرستی که
                 systeminfo.getPropertyValue('VIDEOSOURCE') می‌دهد
                 (privilege لازم: http://tizen.org/privilege/tv.window)

     روی webOS و Tizen این‌ها برای اپِ نصب‌شده تعریف شده‌اند. صفحه‌ی
     وبِ از راه دور (Pro:Centric HTML و URL Launcher) ممکن است مجوز
     نداشته باشد. پس همه‌چیز در try است و اگر نشد، `available:false`
     برمی‌گردد تا رابط کاربر به مهمان راست بگوید به‌جای دکمه‌ی بی‌اثر. */
  TV.inputs = {};

  /** آیا اصلا می‌توانیم ورودی عوض کنیم؟ */
  TV.inputs.supported = function () {
    if (global.SignageBridge && global.SignageBridge.listInputs) return 'android';
    if (global.webOS && global.webOS.service) return 'webos';
    if (global.tizen && global.tizen.tvwindow) return 'tizen';
    return '';
  };

  /* روی webOS ورودی‌ها اپ‌اند و فهرست ثابتی دارند. کدام‌شان واقعا
     کابل دارد را از این مسیر نمی‌شود فهمید، پس connected را null
     می‌گذاریم و رابط کاربر ادعای اتصال نمی‌کند. */
  var WEBOS_INPUTS = [
    { id: 'com.webos.app.hdmi1', label: 'HDMI 1', type: 'HDMI' },
    { id: 'com.webos.app.hdmi2', label: 'HDMI 2', type: 'HDMI' },
    { id: 'com.webos.app.hdmi3', label: 'HDMI 3', type: 'HDMI' },
    { id: 'com.webos.app.hdmi4', label: 'HDMI 4', type: 'HDMI' },
    { id: 'com.webos.app.externalinput.av1', label: 'AV', type: 'AV' }
  ];

  /** cb(err, list) — هر آیتم {id,label,type,connected} */
  TV.inputs.list = function (cb) {
    var kind = TV.inputs.supported();

    if (kind === 'android') {
      var raw;
      try { raw = global.SignageBridge.listInputs(); }
      catch (e) { cb(e, []); return; }
      var arr = [];
      try { arr = JSON.parse(raw || '[]'); } catch (e2) { arr = []; }
      cb(null, arr);
      return;
    }

    if (kind === 'tizen') {
      try {
        global.tizen.systeminfo.getPropertyValue('VIDEOSOURCE', function (vs) {
          var out = [], i, s;
          var conn = (vs && vs.connected) ? vs.connected : [];
          for (i = 0; i < conn.length; i++) {
            s = conn[i];
            if (s.type === 'TV') continue;          /* تیونر، نه ورودی خارجی */
            out.push({
              id: s.type + ':' + s.number,
              label: (s.type === 'HDMI' ? 'HDMI ' + s.number : s.type),
              type: s.type,
              connected: true,                       /* از فهرست connected آمده */
              _raw: s
            });
          }
          cb(null, out);
        }, function (err) { cb(err, []); });
      } catch (e3) { cb(e3, []); }
      return;
    }

    if (kind === 'webos') {
      /* فهرست ثابت. کدام کابل دارد معلوم نیست، پس null. */
      var list = [], j;
      for (j = 0; j < WEBOS_INPUTS.length; j++) {
        list.push({ id: WEBOS_INPUTS[j].id, label: WEBOS_INPUTS[j].label,
                    type: WEBOS_INPUTS[j].type, connected: null });
      }
      cb(null, list);
      return;
    }

    cb(null, []);
  };

  /** cb(err, ok) */
  TV.inputs.switchTo = function (item, cb) {
    cb = cb || function () {};
    var kind = TV.inputs.supported();
    var id = (typeof item === 'string') ? item : (item && item.id);
    if (!id) { cb(new Error('ورودی مشخص نشده'), false); return; }

    if (kind === 'android') {
      var ok = false;
      try { ok = global.SignageBridge.switchInput(id); }
      catch (e) { cb(e, false); return; }
      cb(ok ? null : new Error('تعویض ورودی رد شد'), ok);
      return;
    }

    if (kind === 'tizen') {
      try {
        var src = (item && item._raw) ? item._raw : null;
        if (!src) { cb(new Error('اطلاعات ورودی ناقص است'), false); return; }
        global.tizen.tvwindow.setSource(src,
          function () { cb(null, true); },
          function (err) { cb(err, false); });
      } catch (e2) { cb(e2, false); }
      return;
    }

    if (kind === 'webos') {
      try {
        global.webOS.service.request('luna://com.webos.service.applicationmanager', {
          method: 'launch',
          parameters: { id: id },
          onSuccess: function () { cb(null, true); },
          onFailure: function (err) { cb(err, false); }
        });
      } catch (e3) { cb(e3, false); }
      return;
    }

    cb(new Error('این دستگاه تعویض ورودی را پشتیبانی نمی‌کند'), false);
  };

  /* ── زیرنویس و باند صوتی ──────────────────────────────────────────
     مهمان خارجی در هتل ایرانی فیلم فارسی می‌بیند و برعکس. هر دو به
     زیرنویس نیاز دارند و بعضی فیلم‌ها دوبله هم دارند.

     سه مسیر متفاوت، یک API:

       زیرنویس فایلی   تگ <track> — از Chromium 23 هست، یعنی حتی روی
                       Tizen 2.3 و webOS 3 کار می‌کند. تنها راهی که
                       همه‌جا جواب می‌دهد.

       زیرنویس داخل HLS  hls.subtitleTrack

       صدای فایلی      فایل ویدیوی جداگانه با صدای دوبله؛ منبع عوض
                       می‌شود و زمان فعلی نگه داشته می‌شود

       صدای داخل HLS   hls.audioTrack — بدون قطع تصویر

     چرا چند باند صوتی داخل یک MP4 پشتیبانی نمی‌شود: مرورگرهای
     Chromium خاصیت audioTracks را اصلا ندارند (فقط سافاری دارد) و از
     سمت صفحه هیچ راهی برای تعویض باند داخل MP4 وجود ندارد. */
  TV.tracks = {};

  /**
   * زیرنویس‌های فایلی را روی المان ویدیو سوار می‌کند.
   * list: [{id,lang,label,file_path,is_default}]
   */
  TV.tracks.attachSubtitles = function (video, list) {
    if (!video || !list || !list.length) return;

    var i, t, el;
    for (i = 0; i < list.length; i++) {
      t = list[i];
      if (!t.file_path) continue;

      el = document.createElement('track');
      el.kind    = 'subtitles';
      el.srclang = t.lang || '';
      el.label   = t.label || t.lang || '';
      el.src     = t.file_path;
      /* default فقط روی یکی — اگر روی چندتا باشد مرورگر دلبخواهی
         یکی را می‌گیرد و رفتار بین تلویزیون‌ها فرق می‌کند. */
      if (t.is_default) el.setAttribute('default', 'default');
      video.appendChild(el);
    }
  };

  /**
   * روشن‌کردن یک زیرنویس. index === -1 یعنی خاموش.
   *
   * mode روی textTracks ست می‌شود نه حذف المان: با حذف، برگرداندنش
   * نیاز به بارگذاری دوباره‌ی فایل دارد و روی شبکه‌ی کند هتل مکث
   * محسوسی می‌دهد.
   */
  TV.tracks.showSubtitle = function (video, index, hls) {
    /* اگر زیرنویس داخل جریان HLS است */
    if (hls && hls.subtitleTracks && hls.subtitleTracks.length) {
      try {
        hls.subtitleTrack   = index;
        hls.subtitleDisplay = index >= 0;
      } catch (e) {}
      return;
    }

    if (!video || !video.textTracks) return;
    var tt = video.textTracks, i;
    for (i = 0; i < tt.length; i++) {
      /* 'showing' و 'disabled' — نه 'hidden'. با hidden مرورگر
         همچنان رویداد cue می‌دهد ولی چیزی نشان نمی‌دهد، و بعضی
         تلویزیون‌ها آن را مثل showing رفتار می‌کنند. */
      try { tt[i].mode = (i === index) ? 'showing' : 'disabled'; } catch (e2) {}
    }
  };

  /** کدام زیرنویس الان روشن است؛ -1 یعنی هیچ‌کدام */
  TV.tracks.currentSubtitle = function (video, hls) {
    if (hls && hls.subtitleTracks && hls.subtitleTracks.length) {
      return (typeof hls.subtitleTrack === 'number') ? hls.subtitleTrack : -1;
    }
    if (!video || !video.textTracks) return -1;
    var tt = video.textTracks, i;
    for (i = 0; i < tt.length; i++) if (tt[i].mode === 'showing') return i;
    return -1;
  };

  /**
   * تعویض باند صوتی.
   *
   * برای kind='hls' فقط یک انتساب است. برای kind='file' منبع ویدیو
   * عوض می‌شود و زمان فعلی نگه داشته می‌شود — وگرنه مهمان وسط فیلم
   * به ابتدای آن پرت می‌شود.
   *
   * cb(err)
   */
  TV.tracks.selectAudio = function (video, item, hls, cb) {
    cb = cb || function () {};
    if (!item) { cb(new Error('باند صوتی مشخص نشده')); return; }

    if (item.kind === 'hls') {
      if (!hls) { cb(new Error('این جریان باند چندگانه ندارد')); return; }
      try {
        hls.audioTrack = Number(item.track_index) || 0;
        cb(null);
      } catch (e) { cb(e); }
      return;
    }

    if (!video || !item.file_path) { cb(new Error('فایل صوتی موجود نیست')); return; }

    var at     = video.currentTime || 0;
    var paused = video.paused;
    var done   = false;

    function resume() {
      if (done) return;
      done = true;
      try {
        /* پرش به زمان قبلی فقط وقتی ممکن است که متادیتا آمده باشد */
        if (at > 0) video.currentTime = at;
      } catch (e) {}
      if (!paused) {
        var pr;
        try { pr = video.play(); } catch (e2) {}
        if (pr && pr.catch) pr.catch(function () {});
      }
      cb(null);
    }

    /* loadedmetadata یک‌بار مصرف است؛ روی تلویزیون‌های قدیمی
       گزینه‌ی once پشتیبانی نمی‌شود پس دستی برمی‌داریم. */
    function onMeta() {
      if (video.removeEventListener) video.removeEventListener('loadedmetadata', onMeta, false);
      resume();
    }
    TV.on(video, 'loadedmetadata', onMeta);

    /* اگر متادیتا نیامد، صفحه نباید برای همیشه منتظر بماند */
    setTimeout(resume, 8000);

    video.src = item.file_path;
    try { video.load(); } catch (e3) {}
  };

  /* ── قفل‌گاه ──────────────────────────────────────────────────────
     فقط روی اپ اندروید معنی دارد. روی webOS و Tizen قفل‌کردن منوها
     کار تنظیمات خودِ تلویزیون است (Pro:Centric و Hospitality Mode)،
     نه چیزی که از صفحه‌ی وب بشود اعمال کرد. */
  TV.kiosk = {};

  TV.kiosk.state = function () {
    if (!global.SignageBridge || !global.SignageBridge.getKioskState) {
      return { available: false, deviceOwner: false, locked: false };
    }
    try {
      var s = JSON.parse(global.SignageBridge.getKioskState());
      s.available = true;
      return s;
    } catch (e) {
      return { available: false, deviceOwner: false, locked: false };
    }
  };

  TV.kiosk.enter = function () {
    if (!global.SignageBridge || !global.SignageBridge.enterKiosk) return false;
    try { return !!global.SignageBridge.enterKiosk(); } catch (e) { return false; }
  };

  /* ── راه‌اندازی ───────────────────────────────────────────────── */
  TV.boot = function () {
    TV.scale();
    TV.registerKeys();
    TV.on(global, 'resize', function () { TV.scale(); });
  };

  global.TV = TV;
})(window);
