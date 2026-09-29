<?php
/**
 * تخفیف کسب‌وکارهای اطراف روی تلویزیون اتاق (TODO ۴.۳)
 *
 * فهرست پیشنهادها → جزئیات → «دریافت کد». کد شخصی را مهمان در
 * رستوران یا فروشگاه نشان می‌دهد؛ کسب‌وکار آن را با لینک خودش تأیید
 * می‌کند. کدی که گرفته شده همیشه روی همان پیشنهاد دیده می‌شود تا مهمان
 * لازم نباشد یادداشتش کند.
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
<title>تخفیف‌های اطراف</title>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css<?= v() ?>">
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-base.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-guest.css<?= v() ?>">
<script src="/assets/js/tv-base.js<?= v() ?>"></script>
<script src="/assets/js/tv-guest.js<?= v() ?>"></script>
<style>
.of-img { float: left; width: 9rem; height: 5.4rem; margin-right: 1rem; border-radius: .6rem; background: #1e293b center / cover no-repeat; }
.of-badge { display: inline-block; font-size: .8rem; padding: .15rem .6rem; border-radius: .5rem; margin-right: .6rem;
            background: rgba(225,29,72,.18); color: #fb7185; }
.of-badge.has { background: rgba(34,197,94,.18); color: #4ade80; }
.of-code { display: inline-block; direction: ltr; font-size: 3rem; font-weight: 800; letter-spacing: .4rem; color: #fff;
           background: rgba(255,255,255,.07); border: 2px dashed #fbbf24; border-radius: 1rem; padding: .6rem 2rem; margin: .4rem 0 1rem; }
.of-code.is-used { color: #64748b; border-color: #475569; text-decoration: line-through; }
.of-desc { font-size: 1.1rem; line-height: 2; color: #cbd5e1; max-width: 52rem; margin-bottom: 1rem; white-space: pre-wrap; }
.of-meta { font-size: 1rem; color: #94a3b8; line-height: 2; margin-bottom: 1rem; }
.of-meta i { color: #14b8a6; width: 1.6rem; }
</style>
</head>
<body>
<div id="g-root"></div>
<div id="tv-center" class="tv-center tv-hidden"></div>

<script>
/* ES5 خالص — تلویزیون‌های هتلی. */
(function () {
  'use strict';

  var API = '/api/v1/guest/' + encodeURIComponent(<?= json_encode($code) ?>) + '/offers';
  var CAT = { food: ['رستوران و کافه', 'utensils'], shopping: ['خرید', 'bag-shopping'], tour: ['تور و گردش', 'route'],
              beauty: ['آرایش و اسپا', 'spa'], fun: ['تفریح', 'masks-theater'], other: ['سایر', 'tag'] };

  var fa = TVG.fa;
  var ui = TVG.ui(TV.id('g-root'));
  var btn = ui.btn;
  var offers = [], occupied = false, open = null, listFocus = 0, msg = '';

  function load(then) {
    TV.loading('در حال بارگذاری…');
    TV.get(API, function (err, d) {
      TV.ready();
      if (err || !d || !d.success) { TV.fail('تخفیف‌ها در دسترس نیستند', 'لطفاً کمی بعد دوباره امتحان کنید.'); return; }
      offers = d.data.offers || [];
      occupied = !!d.data.occupied;
      if (then) then(); else showList(listFocus);
    });
  }

  function byId(id) {
    var i;
    for (i = 0; i < offers.length; i++) if (offers[i].id === id) return offers[i];
    return null;
  }

  function lastCode(o) { return o.codes && o.codes.length ? o.codes[o.codes.length - 1] : null; }

  // ── فهرست ───────────────────────────────────────────────────────
  function showList(focusIdx) {
    open = null;
    ui.paint(function () {
      var h = '<div class="g-title">تخفیف‌های اطراف</div>' +
              '<div class="g-sub">پیشنهاد ویژه‌ی کسب‌وکارهای نزدیک هتل برای مهمانان ما</div>';
      if (!offers.length) return h + '<div class="g-msg">فعلاً تخفیفی ثبت نشده است.</div>' + ui.hint('BACK: بازگشت');
      var i;
      for (i = 0; i < offers.length; i++) {
        (function (o, idx) {
          var c = CAT[o.category] || CAT.other, got = lastCode(o);
          var meta = ['<i class="fas fa-' + c[1] + '"></i> ' + c[0]];
          if (o.distance) meta.push(TV.esc(o.distance));
          h += btn('g-item', (o.image ? '<span class="of-img" style="background-image:url(' + TV.esc(o.image) + ')"></span>' : '') +
                 '<div class="g-item-head">' + TV.esc(o.business_name) +
                 (got ? '<span class="of-badge has">کد شما: ' + TV.esc(got.code) + '</span>' : '') + '</div>' +
                 '<div class="g-item-meta"><b style="color:#fbbf24">' + TV.esc(o.title) + '</b> · ' + meta.join(' · ') + '</div>',
                 function () { listFocus = idx; showOffer(o.id); });
        })(offers[i], i);
      }
      return h + ui.hint('OK: جزئیات و دریافت کد  ·  BACK: بازگشت');
    }, focusIdx);
  }

  // ── جزئیات ──────────────────────────────────────────────────────
  function showOffer(id) {
    var o = byId(id);
    if (!o) { showList(0); return; }
    open = id;
    ui.paint(function () {
      var c = CAT[o.category] || CAT.other, got = lastCode(o), i;
      var h = '<div class="g-title">' + TV.esc(o.business_name) + '</div>' +
              '<div class="g-sub" style="color:#fbbf24;font-size:1.3rem;font-weight:700">' + TV.esc(o.title) + '</div>';
      if (o.description) h += '<div class="of-desc">' + TV.esc(o.description) + '</div>';

      var meta = '<div><i class="fas fa-' + c[1] + '"></i>' + c[0] + (o.distance ? ' · ' + TV.esc(o.distance) : '') + '</div>';
      if (o.address) meta += '<div><i class="fas fa-location-dot"></i>' + TV.esc(o.address) + '</div>';
      if (o.phone)   meta += '<div><i class="fas fa-phone"></i><span style="direction:ltr;display:inline-block">' + fa(o.phone) + '</span></div>';
      if (o.valid_to) meta += '<div><i class="fas fa-hourglass-half"></i>معتبر تا ' + fa(o.valid_to.replace(/-/g, '/')) + '</div>';
      if (o.terms)   meta += '<div><i class="fas fa-circle-info"></i>' + TV.esc(o.terms) + '</div>';
      h += '<div class="of-meta">' + meta + '</div>';

      if (o.codes && o.codes.length) {
        h += '<div class="g-label">کد شما — در محل نشان دهید</div>';
        for (i = 0; i < o.codes.length; i++) {
          h += '<div><span class="of-code' + (o.codes[i].status === 'redeemed' ? ' is-used' : '') + '">' +
               TV.esc(o.codes[i].code) + '</span>' + (o.codes[i].status === 'redeemed' ? ' <span class="g-st" style="background:#33415599;color:#94a3b8">استفاده شد</span>' : '') + '</div>';
        }
      }
      if (msg) h += '<div class="g-msg">' + TV.esc(msg) + '</div>';

      if (o.can_claim) h += btn('g-chip', '<i class="fas fa-ticket"></i> ' + (got ? 'یک کد دیگر' : 'دریافت کد تخفیف'), function () { claim(o); });
      else if (!occupied && !got) h += '<div class="g-msg">کد تخفیف فقط برای مهمان ساکن اتاق صادر می‌شود.</div>';
      h += btn('g-chip', 'بازگشت', function () { msg = ''; showList(listFocus); });
      return h + ui.hint('BACK: فهرست');
    }, 0);
  }

  function claim(o) {
    if (ui.busy) return;
    ui.busy = true;
    TV.post(API + '/' + o.id + '/claim', {}, function (err, d) {
      ui.busy = false;
      msg = (err || !d || !d.success) ? TVG.errMsg(err, 'دریافت کد ناموفق بود') : '';
      /* فهرست تازه تا کد جدید و ظرفیت درست نشان داده شود */
      load(function () { showOffer(o.id); });
    });
  }

  TVG.mount(function (k) {
    if (open !== null && (k === 'BACK' || k === 'EXIT')) { msg = ''; showList(listFocus); return true; }
    if (ui.nav(k)) return true;
    return false;
  });

  load();
})();
</script>
</body>
</html>
