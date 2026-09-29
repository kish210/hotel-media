<?php
/**
 * صورتحساب اتاق و خروج سریع روی تلویزیون (TODO ۲.۸ و ۲.۹)
 *
 * API از فاز ۵ آماده بود — /api/v1/guest/{code}/folio و /checkout —
 * ولی کاشی «صورتحساب» روی تلویزیون «ماژول نامعتبر» نشان می‌داد.
 *
 * @var array $screen
 */
$code = (string)($screen['code'] ?? '');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>صورتحساب</title>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css<?= v() ?>">
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-base.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-guest.css<?= v() ?>">
<script src="/assets/js/tv-base.js<?= v() ?>"></script>
<script src="/assets/js/tv-guest.js<?= v() ?>"></script>
</head>
<body>
<div id="g-root"></div>
<div id="tv-center" class="tv-center tv-hidden"></div>

<script>
/* ES5 خالص — تلویزیون‌های هتلی. */
(function () {
  'use strict';

  var API = '/api/v1/guest/' + encodeURIComponent(<?= json_encode($code) ?>);
  var fa = TVG.fa, money = TVG.money;
  var ui = TVG.ui(TV.id('g-root'));
  var btn = ui.btn;

  var st = { screen: 'folio', data: null };

  function load(msg) {
    TV.loading('در حال بارگذاری…');
    TV.get(API + '/folio', function (err, d) {
      TV.ready();
      if (err || !d || !d.success) { TV.fail('صورتحساب در دسترس نیست', 'لطفاً کمی بعد دوباره امتحان کنید.'); return; }
      st.data = d.data;
      show(msg);
    });
  }

  function show(msg) {
    st.screen = 'folio';
    var f = st.data;
    ui.paint(function () {
      var h = '<div class="g-title">صورتحساب اتاق ' + TV.esc(fa(f.room_number || '')) + '</div>' +
              '<div class="g-sub">' + (f.guest_name ? TV.esc(f.guest_name) + ' · ' : '') + 'هزینه‌های این اقامت</div>';
      if (msg) h += '<div class="g-msg">' + TV.esc(msg) + '</div>';
      if (!f.items || !f.items.length) h += '<div class="g-msg">تا این لحظه هزینه‌ای ثبت نشده است.</div>';
      var i, it;
      /* فقط ۱۲ قلم آخر روی صفحه جا می‌شود؛ جمع کل همه را حساب می‌کند */
      var from = f.items ? Math.max(0, f.items.length - 12) : 0;
      for (i = from; f.items && i < f.items.length; i++) {
        it = f.items[i];
        h += '<div class="g-item g-line"><div class="g-item-head">' + TV.esc(it.title) +
             (it.qty > 1 ? ' × ' + fa(it.qty) : '') + '</div>' +
             '<div class="g-item-meta">' + TV.esc(it.created_fa || '') + '</div>' +
             '<div class="g-line-ctl"><span class="g-num">' + money(it.amount) + '</span></div></div>';
      }
      h += '<div class="g-total">جمع کل: ' + money(f.total) + '</div>';
      h += btn('g-chip', '<i class="fas fa-door-open"></i> خروج سریع (Express Check-out)', confirmOut);
      return h + ui.hint('BACK: بازگشت');
    });
  }

  function confirmOut() {
    st.screen = 'confirm';
    ui.paint(function () {
      return '<div class="g-title">خروج سریع</div>' +
             '<div class="g-box"><div>جمع صورتحساب: <b>' + money(st.data.total) + '</b></div>' +
             '<div>پذیرش درخواست شما را بررسی و برای تسویه با شما تماس می‌گیرد.</div></div>' +
             btn('g-chip', '<i class="fas fa-check"></i> ثبت درخواست خروج', checkout) +
             btn('g-chip', 'انصراف', function () { show(); });
    }, 1);
  }

  function checkout() {
    ui.busy = true;
    TV.loading('در حال ثبت…');
    TV.post(API + '/checkout', {}, function (err, d) {
      ui.busy = false;
      TV.ready();
      load((err || !d || !d.success) ? TVG.errMsg(err, 'ثبت درخواست خروج ناموفق بود') : d.message);
    });
  }

  TVG.mount(function (k) {
    if (ui.nav(k)) return true;
    if (k === 'BACK' || k === 'EXIT') {
      if (ui.busy) return true;
      if (st.screen === 'confirm') { show(); return true; }
      return false;
    }
    return false;
  });

  load();
})();
</script>
</body>
</html>
