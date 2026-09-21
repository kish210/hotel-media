<?php
/**
 * TV Bootstrap — صفحه‌ی خودشناس دستگاه
 *
 * عمداً ES5 خالص و بدون هیچ فایل بیرونی نوشته شده:
 *  • webOS 3.x و Tizen 2.x/3.x که هنوز در هتل‌ها زیادند، arrow function،
 *    const/let، template literal، fetch و Promise را پشتیبانی نمی‌کنند.
 *  • تلویزیون هتل معمولا روی VLAN بدون اینترنت است، پس CDN در دسترس نیست.
 * هر تغییری در این فایل باید همین دو قید را رعایت کند.
 *
 * @var string $token توکن ثبت
 */
$enrollToken = htmlspecialchars((string)($token ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>راه‌اندازی تلویزیون</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
html, body {
  width:100%; height:100%; overflow:hidden;
  background:#0b0f1a; color:#fff;
  font-family:Tahoma, Arial, sans-serif;
}
.wrap {
  width:100%; height:100%;
  display:-webkit-box; display:-webkit-flex; display:flex;
  -webkit-box-orient:vertical; -webkit-flex-direction:column; flex-direction:column;
  -webkit-box-pack:center; -webkit-justify-content:center; justify-content:center;
  -webkit-box-align:center; -webkit-align-items:center; align-items:center;
  text-align:center; padding:40px;
}
.spinner {
  width:64px; height:64px; margin-bottom:28px;
  border:5px solid rgba(255,255,255,.12);
  border-top-color:#38bdf8; border-radius:50%;
  -webkit-animation:spin 1s linear infinite; animation:spin 1s linear infinite;
}
@-webkit-keyframes spin { to { -webkit-transform:rotate(360deg); } }
@keyframes spin { to { transform:rotate(360deg); } }
h1 { font-size:30px; font-weight:bold; margin-bottom:14px; }
p  { font-size:19px; color:#94a3b8; line-height:1.9; max-width:760px; }
.code {
  margin-top:26px; padding:16px 36px;
  background:rgba(56,189,248,.10); border:2px solid rgba(56,189,248,.35);
  border-radius:14px; font-size:40px; letter-spacing:5px;
  font-family:'Courier New', monospace; color:#38bdf8;
}
.err   { color:#f87171; }
.hint  { margin-top:22px; font-size:15px; color:#475569; }
.hide  { display:none; }
</style>
</head>
<body>
<div class="wrap">
  <div id="spinner" class="spinner"></div>
  <h1 id="title">در حال شناسایی دستگاه…</h1>
  <p  id="msg">لطفاً چند لحظه صبر کنید</p>
  <div id="codeBox" class="code hide"></div>
  <p  id="hint" class="hint hide"></p>
</div>

<script>
/* ES5 only — بدون const/let، arrow، template literal، fetch یا Promise */
(function () {
  'use strict';

  var TOKEN      = '<?= $enrollToken ?>';
  var STORE_KEY  = 'signagecms_screen_code';
  var RETRY_MS   = 15000;
  var POLL_MS    = 10000;

  function el(id) { return document.getElementById(id); }

  function setState(title, msg, isError) {
    el('title').innerHTML = title;
    el('msg').innerHTML   = msg;
    el('msg').className   = isError ? 'err' : '';
    if (isError) { el('spinner').className = 'spinner hide'; }
  }

  function showCode(code, hint) {
    el('codeBox').innerHTML = code;
    el('codeBox').className = 'code';
    if (hint) { el('hint').innerHTML = hint; el('hint').className = 'hint'; }
  }

  /* ── ذخیره‌سازی پایدار ────────────────────────────────────────
     تلویزیون هتل ممکن است localStorage را پاک کند؛ کوکی را هم
     موازی می‌نویسیم تا کد دستگاه بین ریبوت‌ها گم نشود. */
  function saveCode(code) {
    try { window.localStorage.setItem(STORE_KEY, code); } catch (e) {}
    try {
      document.cookie = STORE_KEY + '=' + code + ';path=/;max-age=' + (10 * 365 * 24 * 3600);
    } catch (e) {}
  }

  function loadCode() {
    var v = null;
    try { v = window.localStorage.getItem(STORE_KEY); } catch (e) {}
    if (v) { return v; }

    var m = document.cookie.match(new RegExp('(?:^|; )' + STORE_KEY + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
  }

  /* ── شناسایی دستگاه ───────────────────────────────────────────
     هر پلتفرم API خودش را دارد و هیچ‌کدام تضمینی نیست، پس همه را
     امتحان می‌کنیم و در بدترین حالت یک شناسه‌ی تصادفیِ ماندگار
     می‌سازیم تا دستگاه بعد از ریبوت همان رکورد قبلی را بگیرد. */
  function deviceInfo(done) {
    var info = { platform: '', mac: '', model: '', firmware: '', serial: '',
                 resolution: screen.width + 'x' + screen.height,
                 user_agent: navigator.userAgent };

    var ua = (navigator.userAgent || '').toLowerCase();
    if (ua.indexOf('webos') >= 0 || ua.indexOf('web0s') >= 0) { info.platform = 'webos'; }
    else if (ua.indexOf('tizen') >= 0)                        { info.platform = 'tizen'; }
    else if (ua.indexOf('android') >= 0)                      { info.platform = 'android'; }

    var pending = 1;
    var settled = false;

    function finish() {
      if (settled) { return; }
      pending--;
      if (pending <= 0) { settled = true; done(info); }
    }

    /* محافظ نهایی: اگر API تلویزیون قول بدهد و هرگز callback نزند — که روی
       بعضی فریم‌ورهای LG و Samsung واقعا اتفاق می‌افتد — بعد از ۴ ثانیه با
       هر چه جمع شده ادامه می‌دهیم. تلویزیونی که بالا نیاید بدتر از
       تلویزیونی است که بدون MAC ثبت شود. */
    setTimeout(function () {
      if (settled) { return; }
      settled = true;
      done(info);
    }, 4000);

    /* ── LG webOS ── */
    if (typeof window.webOS !== 'undefined' && window.webOS.deviceInfo) {
      pending++;
      try {
        window.webOS.deviceInfo(function (d) {
          if (d) {
            info.platform = 'webos';
            info.model    = d.modelName    || d.modelNameAscii || '';
            info.firmware = d.version      || d.sdkVersion     || '';
            info.serial   = d.serialNumber || '';
          }
          finish();
        });
      } catch (e) { finish(); }

      /* MAC از سرویس Luna — روی همه‌ی نسخه‌ها در دسترس نیست */
      if (window.webOS.service && window.webOS.service.request) {
        pending++;
        try {
          window.webOS.service.request('luna://com.webos.service.connectionmanager', {
            method: 'getinfo',
            parameters: {},
            onSuccess: function (res) {
              if (res && res.wiredInfo && res.wiredInfo.macAddress) {
                info.mac = res.wiredInfo.macAddress;
              } else if (res && res.wifiInfo && res.wifiInfo.macAddress) {
                info.mac = res.wifiInfo.macAddress;
              }
              finish();
            },
            onFailure: function () { finish(); }
          });
        } catch (e) { finish(); }
      }
    }

    /* ── Samsung Tizen ── */
    if (typeof window.tizen !== 'undefined') {
      info.platform = 'tizen';

      /* هر API جدا try/catch دارد و در شکست حتما finish() صدا می‌شود.
         اگر pending زیاد شود ولی callback نیاید، صفحه تا ابد روی
         «در حال شناسایی» می‌ماند و تلویزیون هیچ‌وقت بالا نمی‌آید. */
      if (window.tizen.systeminfo) {
        pending++;
        var macDone = false;
        var macFinish = function () {
          if (macDone) { return; }   /* دو بار صدا شدن نباید pending را منفی کند */
          macDone = true;
          finish();
        };
        try {
          window.tizen.systeminfo.getPropertyValue('ETHERNET_NETWORK', function (n) {
            if (n && n.macAddress) { info.mac = n.macAddress; }
            macFinish();
          }, macFinish);
        } catch (e) { macFinish(); }
      }

      /* B2B API تلویزیون‌های هتلی سامسونگ — روی مدل‌های خانگی وجود ندارد */
      if (window.b2bapis && window.b2bapis.b2bcontrol) {
        try { info.serial = window.b2bapis.b2bcontrol.getSerialNumber() || ''; } catch (e) {}
      }

      if (window.webapis && window.webapis.productinfo) {
        /* هر getter جدا، چون روی بعضی فریم‌ورها یکی هست و بقیه نیست */
        try { info.model    = window.webapis.productinfo.getModel()    || ''; } catch (e) {}
        try { info.firmware = window.webapis.productinfo.getFirmware() || ''; } catch (e) {}
        if (!info.serial) {
          try { info.serial = window.webapis.productinfo.getDuid()     || ''; } catch (e) {}
        }
      }
    }

    /* ── Android WebView (اپ ما رابط تزریق می‌کند) ── */
    if (typeof window.SignageNative !== 'undefined') {
      info.platform = 'android';
      try {
        if (window.SignageNative.getMac)      { info.mac      = window.SignageNative.getMac(); }
        if (window.SignageNative.getModel)    { info.model    = window.SignageNative.getModel(); }
        if (window.SignageNative.getSerial)   { info.serial   = window.SignageNative.getSerial(); }
        if (window.SignageNative.getVersion)  { info.app_version = window.SignageNative.getVersion(); }
      } catch (e) {}
    }

    finish();
  }

  /* شناسه‌ی جایگزین وقتی هیچ MAC واقعی در دسترس نیست */
  function fallbackId() {
    var key = 'signagecms_device_uid';
    var v = null;
    try { v = window.localStorage.getItem(key); } catch (e) {}
    if (v) { return v; }

    var hex = '0123456789ABCDEF';
    var s = '02';                       /* بیت locally-administered */
    for (var i = 0; i < 10; i++) { s += hex.charAt(Math.floor(Math.random() * 16)); }

    try { window.localStorage.setItem(key, s); } catch (e) {}
    return s;
  }

  /* ── XHR ساده — fetch روی webOS 3 وجود ندارد ── */
  function post(url, data, cb) {
    var x = new XMLHttpRequest();
    x.open('POST', url, true);
    x.setRequestHeader('Content-Type', 'application/json');
    x.timeout = 20000;
    x.onreadystatechange = function () {
      if (x.readyState !== 4) { return; }
      var body = null;
      try { body = JSON.parse(x.responseText); } catch (e) {}
      cb(x.status, body);
    };
    x.ontimeout = function () { cb(0, null); };
    x.onerror   = function () { cb(0, null); };
    try { x.send(JSON.stringify(data)); } catch (e) { cb(0, null); }
  }

  function get(url, cb) {
    var x = new XMLHttpRequest();
    x.open('GET', url, true);
    x.timeout = 20000;
    x.onreadystatechange = function () {
      if (x.readyState !== 4) { return; }
      var body = null;
      try { body = JSON.parse(x.responseText); } catch (e) {}
      cb(x.status, body);
    };
    x.ontimeout = function () { cb(0, null); };
    x.onerror   = function () { cb(0, null); };
    x.send();
  }

  function goToPlayer(code) {
    saveCode(code);
    window.location.href = '/player/' + code;
  }

  /* منتظر تایید مدیر — کد روی صفحه می‌ماند تا IT بتواند تطبیق دهد */
  function waitForApproval(code) {
    setState('منتظر تایید مدیر سیستم',
             'این دستگاه ثبت شد. پس از تایید در پنل، به‌صورت خودکار وارد می‌شود.');
    showCode(code, 'کد دستگاه را در پنل ← دستگاه‌ها پیدا کنید');

    setInterval(function () {
      get('/api/v1/device/' + code + '/commands', function (status, body) {
        if (status === 200 && body && body.data && body.data.approved) {
          goToPlayer(code);
        }
      });
    }, POLL_MS);
  }

  function enroll() {
    if (!TOKEN) {
      setState('توکن ثبت تعریف نشده',
               'در پنل مدیریت به بخش <b>دستگاه‌ها ← توکن ثبت</b> بروید و یک توکن بسازید،' +
               '<br>سپس این تلویزیون را دوباره روشن کنید.', true);
      return;
    }

    setState('در حال ثبت دستگاه…', 'اتصال به سرور هتل');

    deviceInfo(function (info) {
      if (!info.mac) { info.mac = fallbackId(); }
      info.token = TOKEN;

      post('/api/v1/device/enroll', info, function (status, body) {
        if (status === 0) {
          setState('سرور هتل در دسترس نیست',
                   'اتصال شبکه‌ی تلویزیون را بررسی کنید — تلاش مجدد تا چند لحظه دیگر', true);
          setTimeout(enroll, RETRY_MS);
          return;
        }

        if (status === 403) {
          setState('توکن ثبت نامعتبر است',
                   'آدرس پورتال در منوی نصب تلویزیون را با پنل مدیریت تطبیق دهید.', true);
          return;
        }

        if (!body || !body.data || !body.data.code) {
          setState('ثبت دستگاه ناموفق بود',
                   (body && body.message) ? body.message : 'خطای ناشناخته', true);
          setTimeout(enroll, RETRY_MS);
          return;
        }

        var code = body.data.code;
        saveCode(code);

        if (body.data.status === 'approved') { goToPlayer(code); }
        else                                 { waitForApproval(code); }
      });
    });
  }

  /* ── شروع ──────────────────────────────────────────────────── */
  var known = loadCode();
  if (known) {
    /* دستگاه از قبل ثبت شده — مستقیم به پلیر خودش */
    setState('در حال ورود…', 'دستگاه شناسایی شد');
    goToPlayer(known);
  } else {
    enroll();
  }
})();
</script>
</body>
</html>
