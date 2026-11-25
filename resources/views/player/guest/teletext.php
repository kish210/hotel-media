<?php
/**
 * تله‌تکست روی تلویزیون اتاق.
 *
 * همان چیزی که سیستم قدیمی داشت: متن ۴۰×۲۳، بدون ویدیو و بدون تصویر.
 * روی Orsay/Tizen ۲۰۱۳ هم کار می‌کند چون فقط متن و یک جدول ساده است.
 *
 * کلیدها: چپ/راست = صفحهٔ قبل/بعد · اعداد = پرش به صفحه · BACK = بازگشت
 * (BACK را خود پورتال مدیریت می‌کند؛ این صفحه فقط داخلی است.)
 *
 * @var array       $screen
 * @var list<array> $teletextIndex
 * @var array|null  $teletextPage
 */
$code  = (string)($screen['code'] ?? '');
$lines = is_array($teletextPage) ? ($teletextPage['lines'] ?? []) : [];
$no    = (int)($teletextPage['page_no'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>تله‌تکست</title>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-base.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-guest.css<?= v() ?>">
<script src="/assets/js/tv-base.js<?= v() ?>"></script>
<script src="/assets/js/tv-guest.js<?= v() ?>"></script>
<style>
  /* رنگ‌ها عمدا نزدیک تله‌تکست واقعی‌اند: مشکی، زرد، فیروزه‌ای.
     روی پنل تلویزیون با فاصلهٔ زیاد خوانده می‌شود. */
  #tt-wrap { position: absolute; top: 0; right: 0; bottom: 0; left: 0; background: #000; padding: 2.6rem 3.4rem; }
  #tt-head { display: flex; justify-content: space-between; align-items: baseline;
             border-bottom: 2px solid #1e3a5f; padding-bottom: .6rem; margin-bottom: 1rem; }
  #tt-title { font-size: 1.5rem; font-weight: 800; color: #fbbf24; }
  #tt-no { font-family: monospace; font-size: 1.7rem; font-weight: 800; color: #22d3ee; letter-spacing: .2rem; }
  #tt-body { font-family: monospace; font-size: 1.5rem; line-height: 2.05; color: #e2e8f0;
             white-space: pre; direction: rtl; }
  #tt-nav { position: absolute; bottom: 1.4rem; right: 3.4rem; left: 3.4rem;
            display: flex; gap: .6rem; flex-wrap: wrap; align-items: center; }
  .tt-pill { font-family: monospace; font-size: 1.1rem; color: #94a3b8;
             border: 1px solid #1e3a5f; border-radius: .4rem; padding: .2rem .7rem; }
  .tt-pill.on { color: #0b1220; background: #22d3ee; border-color: #22d3ee; font-weight: 800; }
  #tt-hint { margin-right: auto; font-size: .95rem; color: #475569; }
</style>
</head>
<body>
<div id="tt-wrap">
  <div id="tt-head">
    <div id="tt-title"><?= e($teletextPage['title'] ?? 'تله‌تکست هتل') ?></div>
    <div id="tt-no">P<?= str_pad((string)$no, 3, '0', STR_PAD_LEFT) ?></div>
  </div>
  <div id="tt-body"><?= e(implode("\n", $lines)) ?></div>

  <div id="tt-nav">
    <?php foreach ($teletextIndex as $p): ?>
      <span class="tt-pill <?= (int)$p['page_no'] === $no ? 'on' : '' ?>"
            data-p="<?= (int)$p['page_no'] ?>"><?= (int)$p['page_no'] ?></span>
    <?php endforeach; ?>
    <span id="tt-hint">اعداد: پرش به صفحه · چپ/راست: صفحهٔ قبل/بعد</span>
  </div>
</div>

<script>
/* ES5 — تلویزیون هتلی. ریموت کلیدهای عددی و جهت‌دار می‌فرستد. */
(function () {
  'use strict';

  var CODE  = <?= json_encode($code) ?>;
  var PAGES = <?= json_encode(array_map(static fn($p) => (int)$p['page_no'], $teletextIndex)) ?>;
  var CUR   = <?= json_encode($no) ?>;

  function go(n) {
    window.location.href = '/tv/guest/' + encodeURIComponent(CODE) + '/teletext?p=' + n;
  }
  function move(step) {
    if (!PAGES.length) return;
    var i = PAGES.indexOf(CUR);
    if (i < 0) i = 0;
    var next = (i + step + PAGES.length) % PAGES.length;
    go(PAGES[next]);
  }

  /* تایپ سه‌رقمی مثل تله‌تکست واقعی: ۱۰۱ می‌رود به صفحهٔ ۱۰۱ */
  var typed = '';
  var timer = null;
  document.addEventListener('keydown', function (e) {
    var k = e.keyCode || e.which;

    if (k >= 48 && k <= 57) {
      typed += String(k - 48);
      if (timer) clearTimeout(timer);
      timer = setTimeout(function () { typed = ''; }, 1500);
      if (typed.length === 3) {
        var n = parseInt(typed, 10);
        typed = '';
        if (PAGES.indexOf(n) >= 0) go(n);
      }
      return;
    }
    if (k === 37) { move(-1); return; }   // چپ
    if (k === 39) { move(1);  return; }   // راست
    if (k === 38 || k === 40) {           // بالا/پایین = همان قبل/بعد
      move(k === 38 ? -1 : 1);
    }
  });

  /* کلیک با ماوس/لمس روی شماره‌ها هم کار کند */
  var pills = document.querySelectorAll('.tt-pill');
  for (var i = 0; i < pills.length; i++) {
    pills[i].onclick = function () { go(parseInt(this.getAttribute('data-p'), 10)); };
  }
})();
</script>
</body>
</html>
