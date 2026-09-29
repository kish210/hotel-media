<?php
/**
 * رزرو رستوران و امکانات — روی تلویزیون اتاق (TODO ۲.۱۳ و ۲.۱۴)
 *
 * در iframe پورتال IPTV باز می‌شود؛ ارتباط با ریموت، فوکوس و قالب‌بندی
 * در public/assets/js/tv-guest.js است.
 *
 * چیزی که عمدا نیست: کادر یادداشت. تایپ با ریموت عذاب است و مهمانی
 * که صندلی کودک می‌خواهد با پذیرش تماس می‌گیرد؛ پذیرش در پنل یادداشت
 * می‌گذارد.
 *
 * @var array $screen
 * @var array $days
 */
$code = (string)($screen['code'] ?? '');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>رزرو</title>
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

  var CODE = <?= json_encode($code) ?>;
  var DAYS = <?= json_encode($days, JSON_UNESCAPED_UNICODE) ?>;
  var API  = '/api/v1/guest/' + encodeURIComponent(CODE) + '/reservations';

  var fa = TVG.fa, money = TVG.money, hm = TVG.hm;
  var ui = TVG.ui(TV.id('g-root'));
  var btn = ui.btn;

  var KIND_ICON = {
    restaurant: 'fa-utensils', cafe: 'fa-mug-hot', pool: 'fa-water-ladder',
    spa: 'fa-spa', gym: 'fa-dumbbell', hall: 'fa-people-roof'
  };
  var STATUS = {
    pending:   ['در انتظار تایید', '#f59e0b'],
    confirmed: ['تایید شد',        '#22c55e'],
    completed: ['انجام شد',        '#64748b'],
    no_show:   ['حاضر نشدید',      '#a855f7'],
    cancelled: ['لغو شد',          '#64748b']
  };

  var st = {
    screen: 'venues', venues: [], occupied: true,
    venue: null, dayIdx: 0, party: 2, slots: [], slot: null,
    mine: [], msg: ''
  };

  function dayOf(date) {
    var i;
    for (i = 0; i < DAYS.length; i++) if (DAYS[i].date === date) return DAYS[i].label;
    return fa(date);
  }

  // ── صفحه‌ی ۱: محل‌ها ────────────────────────────────────────────
  function loadVenues() {
    TV.loading('در حال بارگذاری…');
    TV.get(API + '/venues', function (err, d) {
      TV.ready();
      if (err || !d || !d.success) { TV.fail('رزرو در دسترس نیست', 'لطفاً کمی بعد دوباره امتحان کنید.'); return; }
      st.venues   = d.data.venues || [];
      st.occupied = !!d.data.occupied;
      showVenues();
    });
  }

  function showVenues() {
    st.screen = 'venues';
    ui.paint(function () {
      var h = '<div class="g-title">رزرو رستوران و امکانات</div>' +
              '<div class="g-sub">محل موردنظر را انتخاب کنید</div>';
      if (!st.occupied) {
        return h + '<div class="g-msg">رزرو برای این اتاق فعال نیست. لطفاً با پذیرش تماس بگیرید.</div>' +
               ui.hint('BACK: بازگشت');
      }
      if (!st.venues.length) {
        return h + '<div class="g-msg">در حال حاضر محلی برای رزرو تعریف نشده است.</div>' + ui.hint('BACK: بازگشت');
      }
      h += '<div class="g-wrap">';
      var i;
      for (i = 0; i < st.venues.length; i++) {
        (function (v) {
          var meta = fa(String(v.open_from).substr(0, 5)) + ' تا ' + fa(String(v.open_to).substr(0, 5));
          meta += +v.booking_price > 0 ? '<br>هر نفر ' + money(v.booking_price) : '<br>بدون هزینه';
          h += btn('g-tile',
            '<i class="fas ' + (KIND_ICON[v.kind] || 'fa-location-dot') + '"></i>' +
            '<div class="g-tile-name">' + TV.esc(v.name) + '</div>' +
            '<div class="g-tile-meta">' + meta + '</div>',
            function () { openVenue(v); });
        })(st.venues[i]);
      }
      h += btn('g-tile',
        '<i class="fas fa-list-check"></i><div class="g-tile-name">رزروهای من</div>' +
        '<div class="g-tile-meta">مشاهده و لغو</div>', function () { loadMine(); });
      return h + '</div>' + ui.hint('جهت‌ها: حرکت  ·  OK: انتخاب  ·  BACK: بازگشت');
    });
  }

  // ── صفحه‌ی ۲: روز، نفرات، نوبت ─────────────────────────────────
  function openVenue(v) {
    st.venue  = v;
    st.dayIdx = 0;
    st.party  = Math.min(2, +v.max_party || 1);
    st.slots  = [];
    loadSlots(0);
  }

  function loadSlots(keepFocus) {
    var day = DAYS[st.dayIdx].date;
    st.screen = 'slots';
    st.msg = 'در حال دریافت نوبت‌ها…';
    showSlots(keepFocus);
    TV.get(API + '/slots?venue_id=' + st.venue.id + '&date=' + day, function (err, d) {
      if (st.screen !== 'slots' || DAYS[st.dayIdx].date !== day) return; // مهمان جای دیگری رفته
      if (err || !d || !d.success) { st.slots = []; st.msg = TVG.errMsg(err, 'دریافت نوبت‌ها ناموفق بود'); }
      else { st.slots = d.data || []; st.msg = st.slots.length ? '' : 'این روز نوبتی ندارد'; }
      showSlots(keepFocus);
    });
  }

  function slotOk(s) { return s.available && (s.remaining === null || s.remaining >= st.party); }

  function showSlots(focusIdx) {
    var v = st.venue;
    var maxDay = Math.min(+v.days_ahead || 0, 7);
    ui.paint(function () {
      var h = '<div class="g-title">' + TV.esc(v.name) + '</div>' +
              '<div class="g-sub">روز، تعداد نفرات و ساعت را انتخاب کنید</div>';

      h += '<div class="g-label">روز</div><div class="g-row">';
      var i;
      for (i = 0; i <= maxDay && i < DAYS.length; i++) {
        (function (idx) {
          h += btn('g-chip' + (idx === st.dayIdx ? ' is-on' : ''),
                   TV.esc(DAYS[idx].label) + (idx > 1 ? '' : '<small>' + TV.esc(DAYS[idx].short) + '</small>'),
                   function () { st.dayIdx = idx; loadSlots(idx); });
        })(i);
      }
      h += '</div>';

      h += '<div class="g-label">تعداد نفرات (حداکثر ' + fa(v.max_party) + ')</div><div class="g-row">';
      h += btn('g-chip', '−', function () { if (st.party > 1) { st.party--; showSlots(ui.cur()); } });
      h += '<span class="g-num">' + fa(st.party) + ' نفر</span>';
      h += btn('g-chip', '+', function () { if (st.party < +v.max_party) { st.party++; showSlots(ui.cur()); } });
      h += '</div>';

      h += '<div class="g-label">ساعت</div>';
      if (st.msg) h += '<div class="g-msg">' + TV.esc(st.msg) + '</div>';
      h += '<div class="g-wrap">';
      for (i = 0; i < st.slots.length; i++) {
        (function (s) {
          var sub = !s.available ? 'پر یا گذشته'
                  : (s.remaining === null ? 'آزاد' : fa(s.remaining) + ' جا');
          if (slotOk(s)) {
            h += btn('g-chip', fa(s.label) + '<small>' + sub + '</small>', function () { st.slot = s; showConfirm(); });
          } else {
            /* نوبت پر اصلا فوکوس نمی‌گیرد — مهمان روی چیزی که کار
               نمی‌کند OK نمی‌زند و گیج نمی‌شود. */
            h += '<div class="g-chip is-off">' + fa(s.label) + '<small>' +
                 (s.available ? 'جا برای ' + fa(st.party) + ' نفر نیست' : sub) + '</small></div>';
          }
        })(st.slots[i]);
      }
      return h + '</div>' + ui.hint('OK: انتخاب  ·  BACK: فهرست محل‌ها');
    }, focusIdx);
  }

  // ── صفحه‌ی ۳: تایید ─────────────────────────────────────────────
  function showConfirm(msg) {
    st.screen = 'confirm';
    var v = st.venue, s = st.slot;
    var price = (+v.booking_price || 0) * st.party;
    ui.paint(function () {
      var h = '<div class="g-title">تایید رزرو</div><div class="g-sub">اطلاعات را بررسی کنید</div>' +
              '<div class="g-box">' +
              '<div>محل: <b>' + TV.esc(v.name) + '</b></div>' +
              '<div>روز: <b>' + TV.esc(DAYS[st.dayIdx].label) + '</b></div>' +
              '<div>ساعت: <b>' + hm(s.start) + ' تا ' + hm(s.end) + '</b></div>' +
              '<div>تعداد: <b>' + fa(st.party) + ' نفر</b></div>' +
              '<div>هزینه: <b>' + (price > 0 ? money(price) + ' — پس از تایید روی صورتحساب اتاق' : 'رایگان') + '</b></div>' +
              '</div>';
      if (msg) h += '<div class="g-msg">' + TV.esc(msg) + '</div>';
      h += btn('g-chip', '<i class="fas fa-check"></i> ثبت رزرو', submit);
      h += btn('g-chip', 'بازگشت', function () { loadSlots(0); });
      return h + ui.hint('رزرو پس از تایید رستوران قطعی می‌شود');
    });
  }

  function submit() {
    ui.busy = true;
    TV.loading('در حال ثبت…');
    TV.post(API, { venue_id: st.venue.id, start_at: st.slot.start, party_size: st.party }, function (err, d) {
      ui.busy = false;
      TV.ready();
      if (err || !d || !d.success) { showConfirm(TVG.errMsg(err, 'ثبت رزرو ناموفق بود')); return; }
      showDone();
    });
  }

  function showDone() {
    st.screen = 'done';
    ui.paint(function () {
      return '<div class="g-ok"><i class="fas fa-circle-check"></i> رزرو شما ثبت شد</div>' +
             '<div class="g-sub">پس از تایید رستوران، وضعیت در «رزروهای من» تغییر می‌کند.</div>' +
             btn('g-chip', 'رزروهای من', function () { loadMine(); }) +
             btn('g-chip', 'رزرو دیگر', showVenues);
    });
  }

  // ── صفحه‌ی ۴: رزروهای من ────────────────────────────────────────
  function loadMine(msg) {
    TV.loading('در حال بارگذاری…');
    TV.get(API, function (err, d) {
      TV.ready();
      st.mine = (!err && d && d.success) ? (d.data || []) : [];
      showMine(msg || (err ? 'دریافت فهرست ناموفق بود' : ''));
    });
  }

  function showMine(msg) {
    st.screen = 'mine';
    ui.paint(function () {
      var h = '<div class="g-title">رزروهای من</div><div class="g-sub">رزروهای این اقامت</div>';
      if (msg) h += '<div class="g-msg">' + TV.esc(msg) + '</div>';
      if (!st.mine.length) h += '<div class="g-msg">هنوز رزروی ثبت نکرده‌اید.</div>';
      var i;
      for (i = 0; i < st.mine.length; i++) {
        (function (r) {
          var s = STATUS[r.status] || [r.status, '#64748b'];
          h += '<div class="g-item"><div class="g-item-head">' + TV.esc(r.venue_name) +
               '<span class="g-st" style="background:' + s[1] + '33;color:' + s[1] + '">' + s[0] + '</span></div>' +
               '<div class="g-item-meta">' + TV.esc(dayOf(String(r.start_at).substr(0, 10))) + ' · ساعت ' +
               hm(r.start_at) + ' · ' + fa(r.party_size) + ' نفر' +
               (+r.price > 0 ? ' · ' + money(r.price) : '') + '</div>';
          if (r.status === 'pending' || r.status === 'confirmed') {
            h += '<div style="margin-top:.6rem">' + btn('g-chip', 'لغو این رزرو', function () { cancel(r); }) + '</div>';
          }
          h += '</div>';
        })(st.mine[i]);
      }
      h += btn('g-chip', 'بازگشت', showVenues);
      return h;
    });
  }

  function cancel(r) {
    ui.busy = true;
    TV.post(API + '/' + r.id + '/cancel', {}, function (err, d) {
      ui.busy = false;
      loadMine((err || !d || !d.success) ? TVG.errMsg(err, 'لغو ناموفق بود') : 'رزرو لغو شد');
    });
  }

  // ── ریموت ───────────────────────────────────────────────────────
  TVG.mount(function (k) {
    if (ui.nav(k)) return true;
    if (k === 'BACK' || k === 'EXIT') {
      if (ui.busy) return true;
      if (st.screen === 'confirm') { loadSlots(0); return true; }
      if (st.screen !== 'venues')  { showVenues(); return true; }
      return false;
    }
    return false;
  });

  loadVenues();
})();
</script>
</body>
</html>
