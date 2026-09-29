<?php
/**
 * رزرو رستوران و امکانات — روی تلویزیون اتاق (TODO ۲.۱۳ و ۲.۱۴)
 *
 * در iframe پورتال IPTV باز می‌شود. کلیدهای ریموت به صفحه‌ی پدر
 * می‌رسند و پدر آن‌ها را با window.TVGuestKey به اینجا می‌دهد؛ اگر
 * این تابع false برگرداند (BACK در صفحه‌ی اول) پدر iframe را می‌بندد.
 * اگر صفحه مستقیم باز شده باشد، keydown خودش را هم گوش می‌دهد.
 *
 * ES5 خالص، بدون flex و gap — همان دلیل iptv.php. بازرس:
 * node tests/Support/tv-compat-lint.js
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
<script src="/assets/js/tv-base.js<?= v() ?>"></script>
<style>
body { background: #0b1220; color: #e2e8f0; overflow: hidden; }
#rs { position: absolute; top: 0; right: 0; bottom: 0; left: 0; padding: 2.4rem 3.2rem; overflow: hidden; }
.rs-title { font-size: 1.9rem; font-weight: 800; color: #fff; margin-bottom: .3rem; }
.rs-sub   { font-size: 1rem; color: #94a3b8; margin-bottom: 1.6rem; }
.rs-row   { margin-bottom: 1.4rem; white-space: nowrap; overflow: hidden; }
.rs-label { font-size: .95rem; color: #64748b; margin-bottom: .5rem; }
.rs-wrap  { white-space: normal; }

.rs-tile {
  display: inline-block; vertical-align: top;
  width: 15rem; min-height: 8.5rem; margin: 0 0 1rem 1rem; padding: 1.1rem;
  border-radius: 1rem; background: rgba(255,255,255,.06);
  border: 2px solid rgba(255,255,255,.1); box-sizing: border-box;
}
.rs-tile i { font-size: 1.8rem; color: #14b8a6; }
.rs-tile-name { font-size: 1.15rem; font-weight: 700; color: #fff; margin: .6rem 0 .3rem; }
.rs-tile-meta { font-size: .85rem; color: #94a3b8; line-height: 1.6; }

.rs-chip {
  display: inline-block; vertical-align: middle;
  margin: 0 0 .7rem .7rem; padding: .6rem 1.1rem; min-width: 5.5rem; text-align: center;
  border-radius: .8rem; background: rgba(255,255,255,.06);
  border: 2px solid rgba(255,255,255,.1); font-size: 1.05rem; color: #e2e8f0;
}
.rs-chip small { display: block; font-size: .75rem; color: #94a3b8; }
.rs-chip.is-on { background: rgba(20,184,166,.25); border-color: #14b8a6; }
.rs-chip.is-off { opacity: .35; }
.rs-party { font-size: 1.5rem; font-weight: 800; color: #fff; display: inline-block; min-width: 6rem; text-align: center; vertical-align: middle; }

.fx.is-focused {
  background: rgba(26,122,196,.3); border-color: #4098db;
  box-shadow: 0 0 0 3px rgba(64,152,219,.45);
}

.rs-box { background: rgba(255,255,255,.05); border-radius: 1rem; padding: 1.4rem 1.6rem; margin-bottom: 1.4rem; max-width: 40rem; }
.rs-box div { font-size: 1.15rem; line-height: 2.1; }
.rs-box b { color: #fff; }

.rs-item { background: rgba(255,255,255,.05); border-radius: .9rem; padding: .9rem 1.2rem; margin-bottom: .8rem; max-width: 52rem; }
.rs-item-head { font-size: 1.1rem; color: #fff; font-weight: 700; }
.rs-item-meta { font-size: .9rem; color: #94a3b8; margin-top: .2rem; }
.rs-st { display: inline-block; font-size: .8rem; padding: .15rem .6rem; border-radius: .5rem; margin-right: .6rem; }

.rs-msg { font-size: 1.15rem; color: #fbbf24; margin-bottom: 1.2rem; }
.rs-ok  { font-size: 1.5rem; color: #22c55e; font-weight: 800; margin-bottom: 1rem; }
.rs-hint { position: absolute; bottom: 1.4rem; left: 3.2rem; right: 3.2rem; font-size: .9rem; color: #64748b; }
</style>
</head>
<body>
<div id="rs"></div>
<div id="tv-center" class="tv-center tv-hidden"></div>

<script>
/* ES5 خالص — تلویزیون‌های هتلی. */
(function () {
  'use strict';

  var CODE = <?= json_encode($code) ?>;
  var DAYS = <?= json_encode($days, JSON_UNESCAPED_UNICODE) ?>;
  var API  = '/api/v1/guest/' + encodeURIComponent(CODE) + '/reservations';

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
    mine: [], msg: '', busy: false
  };
  var fx = [], acts = [], cur = 0;

  // ── کمکی ───────────────────────────────────────────────────────
  function fa(s) {
    return String(s).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.charAt(+d); });
  }
  function money(n) {
    var s = String(Math.round(+n || 0)), out = '';
    while (s.length > 3) { out = ',' + s.slice(-3) + out; s = s.slice(0, -3); }
    return fa(s + out) + ' ریال';
  }
  function hm(dt) { return fa(String(dt).substr(11, 5)); }
  function dayOf(date) {
    var i;
    for (i = 0; i < DAYS.length; i++) if (DAYS[i].date === date) return DAYS[i].label;
    return fa(date);
  }
  function errMsg(err, fallback) {
    return (err && err.data && err.data.message) || fallback;
  }

  /* هر عنصر قابل انتخاب یک کلاس fx و یک data-i دارد؛ acts[i] کاری است
     که OK روی آن انجام می‌دهد. */
  function btn(cls, html, fn) {
    acts.push(fn);
    return '<div class="fx ' + cls + '" data-i="' + (acts.length - 1) + '">' + html + '</div>';
  }

  function paint(html, focusIdx) {
    acts = [];
    TV.id('rs').innerHTML = html();
    fx = TV.all('.fx', TV.id('rs'));
    focus(focusIdx || 0);
  }

  function focus(i) {
    if (!fx.length) { cur = 0; return; }
    if (i < 0) i = 0;
    if (i >= fx.length) i = fx.length - 1;
    var k;
    for (k = 0; k < fx.length; k++) TV.toggleClass(fx[k], 'is-focused', k === i);
    cur = i;
    /* فهرست بلند رزروها از پایین صفحه بیرون می‌زند */
    if (fx[i].scrollIntoView) { try { fx[i].scrollIntoView(false); } catch (e) {} }
  }

  function hint(t) { return '<div class="rs-hint">' + t + '</div>'; }

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

  function showVenues(msg) {
    st.screen = 'venues';
    paint(function () {
      var h = '<div class="rs-title">رزرو رستوران و امکانات</div>' +
              '<div class="rs-sub">محل موردنظر را انتخاب کنید</div>';
      if (msg) h += '<div class="rs-msg">' + TV.esc(msg) + '</div>';
      if (!st.occupied) {
        return h + '<div class="rs-msg">رزرو برای این اتاق فعال نیست. لطفاً با پذیرش تماس بگیرید.</div>' +
               hint('BACK: بازگشت');
      }
      if (!st.venues.length) {
        return h + '<div class="rs-msg">در حال حاضر محلی برای رزرو تعریف نشده است.</div>' + hint('BACK: بازگشت');
      }
      h += '<div class="rs-wrap">';
      var i;
      for (i = 0; i < st.venues.length; i++) {
        (function (v) {
          var meta = fa(String(v.open_from).substr(0, 5)) + ' تا ' + fa(String(v.open_to).substr(0, 5));
          if (+v.booking_price > 0) meta += '<br>هر نفر ' + money(v.booking_price);
          else meta += '<br>بدون هزینه';
          h += btn('rs-tile',
            '<i class="fas ' + (KIND_ICON[v.kind] || 'fa-location-dot') + '"></i>' +
            '<div class="rs-tile-name">' + TV.esc(v.name) + '</div>' +
            '<div class="rs-tile-meta">' + meta + '</div>',
            function () { openVenue(v); });
        })(st.venues[i]);
      }
      h += btn('rs-tile',
        '<i class="fas fa-list-check"></i><div class="rs-tile-name">رزروهای من</div>' +
        '<div class="rs-tile-meta">مشاهده و لغو</div>', loadMine);
      return h + '</div>' + hint('جهت‌ها: حرکت  ·  OK: انتخاب  ·  BACK: بازگشت');
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
      if (err || !d || !d.success) { st.slots = []; st.msg = errMsg(err, 'دریافت نوبت‌ها ناموفق بود'); }
      else { st.slots = d.data || []; st.msg = st.slots.length ? '' : 'این روز نوبتی ندارد'; }
      showSlots(keepFocus);
    });
  }

  function slotOk(s) { return s.available && (s.remaining === null || s.remaining >= st.party); }

  function showSlots(focusIdx) {
    var v = st.venue;
    var maxDay = Math.min(+v.days_ahead || 0, 7);
    paint(function () {
      var h = '<div class="rs-title">' + TV.esc(v.name) + '</div>' +
              '<div class="rs-sub">روز، تعداد نفرات و ساعت را انتخاب کنید</div>';

      h += '<div class="rs-label">روز</div><div class="rs-row">';
      var i;
      for (i = 0; i <= maxDay && i < DAYS.length; i++) {
        (function (idx) {
          h += btn('rs-chip' + (idx === st.dayIdx ? ' is-on' : ''),
                   TV.esc(DAYS[idx].label) + (idx > 1 ? '' : '<small>' + TV.esc(DAYS[idx].short) + '</small>'),
                   function () { st.dayIdx = idx; loadSlots(idx); });
        })(i);
      }
      h += '</div>';

      h += '<div class="rs-label">تعداد نفرات (حداکثر ' + fa(v.max_party) + ')</div><div class="rs-row">';
      h += btn('rs-chip', '−', function () { if (st.party > 1) { st.party--; showSlots(cur); } });
      h += '<span class="rs-party">' + fa(st.party) + ' نفر</span>';
      h += btn('rs-chip', '+', function () { if (st.party < +v.max_party) { st.party++; showSlots(cur); } });
      h += '</div>';

      h += '<div class="rs-label">ساعت</div>';
      if (st.msg) h += '<div class="rs-msg">' + TV.esc(st.msg) + '</div>';
      h += '<div class="rs-wrap">';
      for (i = 0; i < st.slots.length; i++) {
        (function (s) {
          var sub = !s.available ? 'پر یا گذشته'
                  : (s.remaining === null ? 'آزاد' : fa(s.remaining) + ' جا');
          if (slotOk(s)) {
            h += btn('rs-chip', fa(s.label) + '<small>' + sub + '</small>', function () { st.slot = s; showConfirm(); });
          } else {
            /* نوبت پر اصلا فوکوس نمی‌گیرد — مهمان روی چیزی که کار
               نمی‌کند OK نمی‌زند و گیج نمی‌شود. */
            h += '<div class="rs-chip is-off">' + fa(s.label) + '<small>' +
                 (s.available ? 'جا برای ' + fa(st.party) + ' نفر نیست' : sub) + '</small></div>';
          }
        })(st.slots[i]);
      }
      return h + '</div>' + hint('OK: انتخاب  ·  BACK: فهرست محل‌ها');
    }, focusIdx);
  }

  // ── صفحه‌ی ۳: تایید ─────────────────────────────────────────────
  function showConfirm(msg) {
    st.screen = 'confirm';
    var v = st.venue, s = st.slot;
    var price = (+v.booking_price || 0) * st.party;
    paint(function () {
      var h = '<div class="rs-title">تایید رزرو</div><div class="rs-sub">اطلاعات را بررسی کنید</div>' +
              '<div class="rs-box">' +
              '<div>محل: <b>' + TV.esc(v.name) + '</b></div>' +
              '<div>روز: <b>' + TV.esc(DAYS[st.dayIdx].label) + '</b></div>' +
              '<div>ساعت: <b>' + hm(s.start) + ' تا ' + hm(s.end) + '</b></div>' +
              '<div>تعداد: <b>' + fa(st.party) + ' نفر</b></div>' +
              '<div>هزینه: <b>' + (price > 0 ? money(price) + ' — پس از تایید روی صورتحساب اتاق' : 'رایگان') + '</b></div>' +
              '</div>';
      if (msg) h += '<div class="rs-msg">' + TV.esc(msg) + '</div>';
      h += btn('rs-chip', '<i class="fas fa-check"></i> ثبت رزرو', submit);
      h += btn('rs-chip', 'بازگشت', function () { loadSlots(0); });
      return h + hint('رزرو پس از تایید رستوران قطعی می‌شود');
    });
  }

  function submit() {
    if (st.busy) return;
    st.busy = true;
    TV.loading('در حال ثبت…');
    TV.post(API, { venue_id: st.venue.id, start_at: st.slot.start, party_size: st.party }, function (err, d) {
      st.busy = false;
      TV.ready();
      if (err || !d || !d.success) { showConfirm(errMsg(err, 'ثبت رزرو ناموفق بود')); return; }
      showDone();
    });
  }

  function showDone() {
    st.screen = 'done';
    paint(function () {
      return '<div class="rs-ok"><i class="fas fa-circle-check"></i> رزرو شما ثبت شد</div>' +
             '<div class="rs-sub">پس از تایید رستوران، وضعیت در «رزروهای من» تغییر می‌کند.</div>' +
             btn('rs-chip', 'رزروهای من', loadMine) +
             btn('rs-chip', 'رزرو دیگر', function () { showVenues(); });
    });
  }

  // ── صفحه‌ی ۴: رزروهای من ────────────────────────────────────────
  function loadMine(msg) {
    TV.loading('در حال بارگذاری…');
    TV.get(API, function (err, d) {
      TV.ready();
      st.mine = (!err && d && d.success) ? (d.data || []) : [];
      showMine(typeof msg === 'string' ? msg : (err ? 'دریافت فهرست ناموفق بود' : ''));
    });
  }

  function showMine(msg) {
    st.screen = 'mine';
    paint(function () {
      var h = '<div class="rs-title">رزروهای من</div><div class="rs-sub">رزروهای این اقامت</div>';
      if (msg) h += '<div class="rs-msg">' + TV.esc(msg) + '</div>';
      if (!st.mine.length) h += '<div class="rs-msg">هنوز رزروی ثبت نکرده‌اید.</div>';
      var i;
      for (i = 0; i < st.mine.length; i++) {
        (function (r) {
          var s = STATUS[r.status] || [r.status, '#64748b'];
          h += '<div class="rs-item"><div class="rs-item-head">' + TV.esc(r.venue_name) +
               '<span class="rs-st" style="background:' + s[1] + '33;color:' + s[1] + '">' + s[0] + '</span></div>' +
               '<div class="rs-item-meta">' + TV.esc(dayOf(String(r.start_at).substr(0, 10))) + ' · ساعت ' +
               hm(r.start_at) + ' · ' + fa(r.party_size) + ' نفر' +
               (+r.price > 0 ? ' · ' + money(r.price) : '') + '</div>';
          if (r.status === 'pending' || r.status === 'confirmed') {
            h += '<div style="margin-top:.6rem">' + btn('rs-chip', 'لغو این رزرو', function () { cancel(r); }) + '</div>';
          }
          h += '</div>';
        })(st.mine[i]);
      }
      h += btn('rs-chip', 'بازگشت', function () { showVenues(); });
      return h;
    });
  }

  function cancel(r) {
    if (st.busy) return;
    st.busy = true;
    TV.post(API + '/' + r.id + '/cancel', {}, function (err, d) {
      st.busy = false;
      loadMine((err || !d || !d.success) ? errMsg(err, 'لغو ناموفق بود') : 'رزرو لغو شد');
    });
  }

  // ── ریموت ───────────────────────────────────────────────────────
  /* true یعنی کلید مصرف شد. false روی BACK صفحه‌ی اول یعنی «مرا ببند». */
  function handle(k) {
    if (k === 'LEFT' || k === 'RIGHT' || k === 'UP' || k === 'DOWN') {
      var n = TV.findNeighbor(fx, cur, k);
      if (n >= 0) focus(n);
      return true;
    }
    if (k === 'OK') {
      var el = fx[cur];
      if (el && !st.busy) { var fn = acts[+el.getAttribute('data-i')]; if (fn) fn(); }
      return true;
    }
    if (k === 'BACK' || k === 'EXIT') {
      if (st.busy) return true;
      if (st.screen === 'confirm') { loadSlots(0); return true; }
      if (st.screen !== 'venues')  { showVenues(); return true; }
      return false;
    }
    return false;
  }

  window.TVGuestKey = handle;

  /* فقط وقتی مستقیم باز شده. در iframe فوکوس روی صفحه‌ی پدر است و
     این شنونده اصلا صدا زده نمی‌شود. */
  TV.on(document, 'keydown', function (e) {
    var k = TV.keyName(e);
    if (!k) return;
    var used = handle(k);
    if (!used && (k === 'BACK' || k === 'EXIT') && window.parent === window) {
      try { window.history.back(); } catch (x) {}
    }
    if (used && e.preventDefault) e.preventDefault();
  });

  TV.boot();
  loadVenues();
})();
</script>
</body>
</html>
