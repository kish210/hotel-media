<?php
/**
 * Hotel Media — راه‌اندازی صفحه‌نمایش
 *
 * دو حالت دارد:
 *   ۱) دستگاه تازه متصل شد — تایید کوتاه و رفتن به پلیر
 *   ۲) هنوز کوکی ندارد — گرفتن کد فعال‌سازی
 *
 * این صفحه هم روی مرورگر تلویزیون باز می‌شود، پس ES5 خالص است.
 * بازرس: node tests/Support/tv-compat-lint.js
 */
$paired = isset($pairingScreen);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hotel Media — راه‌اندازی</title>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css<?= v() ?>">
<!-- در نسخه‌ی قبلی این لینک ته body بود، پس آیکون‌ها دیر می‌آمدند و
     دکمه یک لحظه بدون آیکون می‌پرید. -->
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-base.css<?= v() ?>">
<script src="/assets/js/tv-base.js<?= v() ?>"></script>
<style>
body {
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-orient: vertical; -webkit-box-direction: normal;
  -webkit-flex-direction: column; -ms-flex-direction: column; flex-direction: column;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
  -webkit-box-pack: center; -webkit-justify-content: center;
  -ms-flex-pack: center; justify-content: center;
  padding: 1.5rem;
}

/* شبکه‌ی پس‌زمینه با رنگ برند */
#grid-bg {
  position: fixed;
  top: 0; right: 0; bottom: 0; left: 0;
  z-index: 0;
  background-image:
    -webkit-linear-gradient(top, rgba(26,122,196,.045) 1px, transparent 1px),
    -webkit-linear-gradient(left, rgba(26,122,196,.045) 1px, transparent 1px);
  background-image:
    linear-gradient(to bottom, rgba(26,122,196,.045) 1px, transparent 1px),
    linear-gradient(to right,  rgba(26,122,196,.045) 1px, transparent 1px);
  background-size: 60px 60px;
  -webkit-animation: gridMove 26s linear infinite;
  animation: gridMove 26s linear infinite;
}
@-webkit-keyframes gridMove { to { background-position: 60px 60px; } }
@keyframes gridMove { to { background-position: 60px 60px; } }

.card {
  position: relative;
  z-index: 1;
  width: 27rem;
  max-width: 100%;
  padding: 2.8rem 2.5rem;
  border-radius: 1.5rem;
  background: rgba(255, 255, 255, .05);
  border: 1px solid rgba(255, 255, 255, .1);
  box-shadow: 0 1.6rem 4rem rgba(0, 0, 0, .55);
  text-align: center;
}

.logo { height: 3.2rem; width: auto; margin: 0 auto 1.4rem; display: block; }

.title { font-size: 1.4rem; font-weight: 800; margin-bottom: .4rem; }
.sub   { font-size: .92rem; font-weight: 400; color: #a8b4c6; line-height: 1.75; margin-bottom: 1.6rem; }

/* ── تایید اتصال ── */
.ring { position: relative; width: 5.6rem; height: 5.6rem; margin: 0 auto 1.3rem; }
.ring svg {
  width: 5.6rem; height: 5.6rem;
  -webkit-transform: rotate(-90deg); -ms-transform: rotate(-90deg); transform: rotate(-90deg);
}
.ring circle { fill: none; stroke-width: 4; }
.ring .bg   { stroke: rgba(26, 122, 196, .14); }
.ring .prog {
  stroke: #4098db;
  stroke-linecap: round;
  stroke-dasharray: 245;
  stroke-dashoffset: 245;
  -webkit-animation: ringFill 2.3s ease-out forwards;
  animation: ringFill 2.3s ease-out forwards;
}
@-webkit-keyframes ringFill { to { stroke-dashoffset: 0; } }
@keyframes ringFill { to { stroke-dashoffset: 0; } }
.ring .tick {
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
  -webkit-box-pack: center; -webkit-justify-content: center;
  -ms-flex-pack: center; justify-content: center;
  font-size: 1.9rem;
  color: #32d17a;
}
.paired-name { font-size: 1.4rem; font-weight: 800; margin-bottom: .3rem; }
.paired-code { font-size: .82rem; color: #7bb8e8; font-family: monospace; letter-spacing: .2em; margin-bottom: 1rem; }

/* ── ورود کد ── */
.badge {
  width: 4.6rem; height: 4.6rem;
  border-radius: 50%;
  background: rgba(26, 122, 196, .1);
  border: 2px solid rgba(26, 122, 196, .24);
  display: -webkit-box; display: -webkit-flex; display: -ms-flexbox; display: flex;
  -webkit-box-align: center; -webkit-align-items: center;
  -ms-flex-align: center; align-items: center;
  -webkit-box-pack: center; -webkit-justify-content: center;
  -ms-flex-pack: center; justify-content: center;
  margin: 0 auto 1.3rem;
  font-size: 1.8rem;
}
#code-input {
  width: 100%;
  padding: 1rem 1.2rem;
  border-radius: .85rem;
  background: rgba(255, 255, 255, .05);
  border: 2px solid rgba(255, 255, 255, .11);
  color: #fff;
  font-family: monospace;
  font-size: 1.6rem;
  letter-spacing: .35rem;
  text-align: center;
  text-transform: uppercase;
  outline: 0;
  -webkit-transition: border-color .2s ease, background .2s ease;
  transition: border-color .2s ease, background .2s ease;
}
#code-input:focus { border-color: #4098db; background: rgba(26, 122, 196, .06); }
#code-input::-webkit-input-placeholder { color: #4a5567; letter-spacing: .15rem; font-size: 1rem; }
#code-input::placeholder { color: #4a5567; letter-spacing: .15rem; font-size: 1rem; }

#go-btn {
  width: 100%;
  margin-top: .9rem;
  padding: .95rem;
  font-size: 1rem;
  background: #1668b3;
  border-color: #1a7ac4;
  -webkit-box-pack: center; -webkit-justify-content: center;
  -ms-flex-pack: center; justify-content: center;
}
#go-btn:disabled { opacity: .45; cursor: default; }

#err { font-size: .88rem; color: #ff5f57; margin-top: .8rem; min-height: 1.3rem; }
#err.is-busy { color: #7bb8e8; }

.help {
  margin-top: 1.4rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(255, 255, 255, .07);
  /* رنگ قبلی #334155 روی زمینه‌ی تیره تقریبا خوانده نمی‌شد */
  font-size: .8rem;
  font-weight: 400;
  color: #8c99ad;
  line-height: 1.9;
}
.help code {
  color: #7bb8e8;
  background: rgba(26, 122, 196, .1);
  padding: .05rem .35rem;
  border-radius: .25rem;
  font-family: monospace;
}
.help b { color: #c2cbd8; }

.foot {
  position: relative; z-index: 1;
  margin-top: 1.6rem;
  font-size: .78rem;
  color: #79849a;
  letter-spacing: .06em;
}
</style>
</head>
<body>
<div id="grid-bg"></div>

<?php if ($paired): ?>
  <div class="card">
    <div class="ring">
      <svg viewBox="0 0 90 90">
        <circle class="bg"   cx="45" cy="45" r="39"/>
        <circle class="prog" cx="45" cy="45" r="39"/>
      </svg>
      <div class="tick">✓</div>
    </div>
    <div class="paired-name"><?= e($pairingScreen['name'] ?? 'صفحه‌نمایش') ?></div>
    <div class="paired-code"><?= e($pairingScreen['code'] ?? '') ?></div>
    <div class="sub" style="margin-bottom:0">این دستگاه متصل شد — در حال بارگذاری پخش‌کننده…</div>
  </div>

<?php else: ?>
  <div class="card">
    <img class="logo" src="/assets/img/sama-logo.svg<?= v() ?>" alt="">
    <div class="title">راه‌اندازی صفحه‌نمایش</div>
    <div class="sub">کد فعال‌سازی را از پنل مدیریت دریافت و وارد کنید</div>

    <input id="code-input" type="text" maxlength="20" placeholder="کد فعال‌سازی"
           autocomplete="off" autocorrect="off" spellcheck="false">

    <button type="button" class="tv-btn" id="go-btn">
      <i class="fas fa-link" style="margin-left:.5rem"></i><span id="go-label">اتصال</span>
    </button>
    <div id="err"></div>

    <div class="help">
      کد را از <code>مدیریت ← صفحات ← فعال‌سازی</code> بگیرید.<br>
      آدرس این صفحه برای <b>همه‌ی</b> صفحه‌نمایش‌ها یکسان است:<br>
      <code id="this-url"></code>
    </div>
  </div>
<?php endif; ?>

<div class="foot">Hotel Media Player</div>

<script>
(function () {
  'use strict';
  TV.boot();

<?php if ($paired): ?>
  var code = <?= json_encode((string)($pairingScreen['code'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
  var name = <?= json_encode((string)($pairingScreen['name'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
  var goTo = <?= json_encode((string)($pairingRedirect ?? '/player/'), JSON_UNESCAPED_UNICODE) ?>;

  /* localStorage روی بعضی تلویزیون‌ها با حالت خصوصی یا سهمیه‌ی پر
     پرتاب می‌کند؛ کوکی سمت سرور مرجع اصلی است و این فقط کمکی است. */
  try { localStorage.setItem('signage_scr',  code); } catch (e) {}
  try { localStorage.setItem('signage_name', name); } catch (e2) {}

  setTimeout(function () { location.href = goTo; }, 2400);

<?php else: ?>
  var inp  = TV.id('code-input');
  var btn  = TV.id('go-btn');
  var lbl  = TV.id('go-label');
  var err  = TV.id('err');
  var busy = false;

  TV.text(TV.id('this-url'), window.location.protocol + '//' + window.location.host + '/player/');

  function fail(msg) {
    err.className = '';
    TV.text(err, msg);
  }

  function setBusy(on) {
    busy = on;
    btn.disabled = on;
    TV.text(lbl, on ? 'در حال اتصال…' : 'اتصال');
    if (on) { err.className = 'is-busy'; TV.text(err, ''); }
  }

  function go() {
    if (busy) return;
    var raw = (inp.value || '').replace(/\s+/g, '').toUpperCase();

    if (!raw)                          { fail('کد نمی‌تواند خالی باشد'); return; }
    if (!/^[A-Z0-9]{4,20}$/.test(raw)) { fail('قالب کد نامعتبر است'); return; }

    /* کد صفحه (SCR…) مستقیم است؛ کد فعال‌سازی باید از سرور تبدیل شود */
    if (/^SCR[A-Z0-9]+$/.test(raw)) {
      location.href = '/player/' + encodeURIComponent(raw);
      return;
    }

    setBusy(true);
    TV.post('/player/activate', { activation_code: raw }, function (e, d) {
      if (!e && d && d.success && d.data && d.data.screen_code) {
        location.href = '/player/' + encodeURIComponent(d.data.screen_code);
        return;
      }
      setBusy(false);
      fail((d && d.message) ? d.message : 'کد نامعتبر است یا منقضی شده');
    });
  }

  TV.on(btn, 'click', go);
  TV.on(inp, 'input', function () { inp.value = inp.value.toUpperCase(); });
  TV.on(document, 'keydown', function (ev) { if (TV.keyName(ev) === 'OK') go(); });
  if (inp.focus) inp.focus();
<?php endif; ?>
})();
</script>
</body>
</html>
