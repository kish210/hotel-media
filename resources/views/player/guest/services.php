<?php
/**
 * خدمات مهمان روی تلویزیون اتاق — روم‌سرویس، صبحانه، خانه‌داری،
 * خشک‌شویی، تعمیرات، تاکسی، بیدارباش، نظرسنجی (TODO ۲.۲ تا ۲.۱۰)
 *
 * API این‌ها از فاز ۱ کامل بود و پنل کارکنان هم، ولی هیچ صفحه‌ای روی
 * تلویزیون صدایش نمی‌زد؛ کاشی «خدمات» پیام «ماژول نامعتبر» می‌داد.
 * این صفحه همان API را صدا می‌زند: /api/v1/guest/{code}/services|requests
 *
 * زمان بیدارباش و تاکسی از ساعت سرور حساب می‌شود، نه تلویزیون: ساعت
 * و منطقه‌ی زمانی تلویزیون هتلی اغلب غلط است و بیدارباشی که یک ساعت
 * دیر زنگ بزند بدتر از نبودنش است.
 *
 * @var array $screen
 * @var array $days
 * @var int   $serverTs
 * @var int   $serverTz
 */
$code = (string)($screen['code'] ?? '');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>خدمات</title>
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
  var API  = '/api/v1/guest/' + encodeURIComponent(CODE);

  /* ساعت دیواری سرور = زمان سرور در لحظه‌ی ساخت صفحه + زمان گذشته از
     آن طبق ساعت خود تلویزیون (فاصله‌ی دو لحظه حتی با ساعت غلط درست
     است) + اختلاف منطقه‌ی زمانی سرور. خروجی با getUTC* خوانده می‌شود. */
  var SERVER_TS = <?= (int)$serverTs ?> * 1000;
  var SERVER_TZ = <?= (int)$serverTz ?> * 1000;
  var LOADED_AT = new Date().getTime();
  function serverWall() { return new Date(SERVER_TS + (new Date().getTime() - LOADED_AT) + SERVER_TZ); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function wallStr(d) {
    return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()) + ' ' +
           pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ':00';
  }

  var fa = TVG.fa, money = TVG.money, hm = TVG.hm;
  var ui = TVG.ui(TV.id('g-root'));
  var btn = ui.btn;

  var CATS = {
    room_service: ['روم‌سرویس',  'fa-utensils'],
    breakfast:    ['صبحانه',     'fa-mug-hot'],
    housekeeping: ['خانه‌داری',   'fa-broom'],
    laundry:      ['خشک‌شویی',   'fa-shirt'],
    maintenance:  ['تعمیرات',    'fa-screwdriver-wrench'],
    taxi:         ['تاکسی',      'fa-taxi'],
    other:        ['سایر خدمات', 'fa-bell-concierge'],
    wakeup:       ['بیدارباش',   'fa-bell'],
    feedback:     ['نظرسنجی',    'fa-star']
  };
  var ORDER = ['room_service', 'breakfast', 'housekeeping', 'laundry', 'maintenance', 'other'];
  var STATUS = {
    pending:     ['در انتظار',     '#f59e0b'],
    accepted:    ['پذیرفته شد',    '#3b82f6'],
    in_progress: ['در حال انجام',  '#3b82f6'],
    done:        ['انجام شد',      '#22c55e'],
    cancelled:   ['لغو شد',        '#64748b']
  };

  var st = { screen: 'home', cats: {}, cat: '', qty: {}, mine: [], wake: { h: 7, m: 0 } };

  function label(cat) { return (CATS[cat] || CATS.other)[0]; }

  // ── خانه: دسته‌ها ───────────────────────────────────────────────
  function load() {
    TV.loading('در حال بارگذاری…');
    TV.get(API + '/services', function (err, d) {
      TV.ready();
      if (err || !d || !d.success) { TV.fail('خدمات در دسترس نیست', 'لطفاً کمی بعد دوباره امتحان کنید.'); return; }
      st.cats = d.data.categories || {};
      showHome();
    });
  }

  function tile(icon, name, meta, fn) {
    return btn('g-tile', '<i class="fas ' + icon + '"></i><div class="g-tile-name">' + name +
               '</div><div class="g-tile-meta">' + (meta || '') + '</div>', fn);
  }

  function showHome(msg) {
    st.screen = 'home';
    ui.paint(function () {
      var h = '<div class="g-title">خدمات اتاق</div><div class="g-sub">چه کاری برایتان انجام دهیم؟</div>';
      if (msg) h += '<div class="g-msg">' + TV.esc(msg) + '</div>';
      h += '<div class="g-wrap">';
      var i;
      for (i = 0; i < ORDER.length; i++) {
        (function (c) {
          if (!st.cats[c] || !st.cats[c].length) return;
          h += tile(CATS[c][1], CATS[c][0], fa(st.cats[c].length) + ' مورد', function () { openCat(c); });
        })(ORDER[i]);
      }
      if (st.cats.taxi && st.cats.taxi.length) h += tile(CATS.taxi[1], CATS.taxi[0], 'تا دم در هتل', showTaxi);
      h += tile(CATS.wakeup[1], CATS.wakeup[0], 'زنگ روی همین تلویزیون', showWake);
      h += tile(CATS.feedback[1], CATS.feedback[0], 'نظر شما درباره‌ی اقامت', showFeedback);
      h += tile('fa-list-check', 'درخواست‌های من', 'پیگیری و لغو', function () { loadMine(); });
      return h + '</div>' + ui.hint('جهت‌ها: حرکت  ·  OK: انتخاب  ·  BACK: بازگشت');
    });
  }

  // ── اقلام یک دسته ───────────────────────────────────────────────
  function openCat(c) { st.cat = c; st.qty = {}; showItems(0); }

  function total() {
    var list = st.cats[st.cat] || [], t = 0, i;
    for (i = 0; i < list.length; i++) t += (st.qty[list[i].id] || 0) * (+list[i].price || 0);
    return t;
  }
  function picked() {
    var out = [], id;
    for (id in st.qty) if (st.qty.hasOwnProperty(id) && st.qty[id] > 0) out.push({ service_id: +id, qty: st.qty[id] });
    return out;
  }

  function showItems(focusIdx, msg) {
    st.screen = 'items';
    var list = st.cats[st.cat] || [];
    ui.paint(function () {
      var h = '<div class="g-title">' + label(st.cat) + '</div><div class="g-sub">موارد را انتخاب کنید</div>';
      if (msg) h += '<div class="g-msg">' + TV.esc(msg) + '</div>';
      var i;
      for (i = 0; i < list.length; i++) {
        (function (s) {
          var q = st.qty[s.id] || 0;
          var price = +s.price > 0 ? money(s.price) + (s.unit ? ' / ' + TV.esc(s.unit) : '') : 'رایگان';
          if (!s.available_now) {
            h += '<div class="g-item is-off"><div class="g-item-head">' + TV.esc(s.name_fa) + '</div>' +
                 '<div class="g-item-meta">ارائه از ' + hm('0000-00-00 ' + s.available_from) + ' تا ' +
                 hm('0000-00-00 ' + s.available_to) + '</div></div>';
            return;
          }
          h += '<div class="g-item g-line"><div class="g-item-head">' + TV.esc(s.name_fa) + '</div>' +
               '<div class="g-item-meta">' + price + (s.description ? ' · ' + TV.esc(s.description) : '') + '</div>' +
               '<div class="g-line-ctl">';
          if (+s.is_orderable) {
            h += btn('g-chip g-sm', '−', function () { if (q > 0) { st.qty[s.id] = q - 1; showItems(ui.cur()); } }) +
                 '<span class="g-num">' + fa(q) + '</span>' +
                 btn('g-chip g-sm', '+', function () { if (q < 20) { st.qty[s.id] = q + 1; showItems(ui.cur()); } });
          } else {
            h += btn('g-chip g-sm' + (q ? ' is-on' : ''), q ? '✓ انتخاب شد' : 'انتخاب',
                     function () { st.qty[s.id] = q ? 0 : 1; showItems(ui.cur()); });
          }
          h += '</div></div>';
        })(list[i]);
      }
      var t = total();
      h += '<div class="g-total">' + (t > 0 ? 'جمع: ' + money(t) : '') + '</div>';
      /* «بازگشت» اول می‌آید تا در چیدمان راست‌به‌چپ سمت راست بنشیند و
         «ثبت» سمت چپ، زیر ستون شمارنده‌ها. برعکسش، پایین زدن از دکمه‌ی
         + مهمان را روی «بازگشت» می‌برد و OK سفارشش را دور می‌ریخت. */
      h += btn('g-chip', 'بازگشت', function () { showHome(); });
      h += btn('g-chip', '<i class="fas fa-check"></i> ثبت درخواست', function () {
        var items = picked();
        if (!items.length) { showItems(ui.cur(), 'حداقل یک مورد را انتخاب کنید'); return; }
        send({ category: st.cat, items: items });
      });
      return h;
    }, focusIdx);
  }

  // ── تاکسی ───────────────────────────────────────────────────────
  function showTaxi(msg) {
    st.screen = 'taxi';
    function at(min) {
      return function () {
        var d = serverWall();
        send({ category: 'taxi', scheduled_at: min ? wallStr(new Date(d.getTime() + min * 60000)) : null });
      };
    }
    ui.paint(function () {
      var h = '<div class="g-title">درخواست تاکسی</div><div class="g-sub">چه زمانی تاکسی دم در هتل باشد؟</div>';
      if (typeof msg === 'string') h += '<div class="g-msg">' + TV.esc(msg) + '</div>';
      return h + btn('g-chip', 'همین حالا', at(0)) +
             btn('g-chip', fa(30) + ' دقیقه‌ی دیگر', at(30)) +
             btn('g-chip', 'یک ساعت دیگر', at(60)) +
             btn('g-chip', 'دو ساعت دیگر', at(120)) +
             '<div style="margin-top:1rem">' + btn('g-chip', 'بازگشت', function () { showHome(); }) + '</div>' +
             ui.hint('مقصد را پذیرش تلفنی از شما می‌پرسد');
    });
  }

  // ── بیدارباش ────────────────────────────────────────────────────
  function wakeAt() {
    var now = serverWall();
    var d = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(), st.wake.h, st.wake.m));
    /* ساعتی که امروز گذشته یعنی فردا */
    if (d.getTime() <= now.getTime()) d = new Date(d.getTime() + 86400000);
    return { str: wallStr(d), tomorrow: d.getUTCDate() !== now.getUTCDate() };
  }

  function showWake(msg, focusIdx) {
    st.screen = 'wake';
    var w = wakeAt();
    ui.paint(function () {
      var h = '<div class="g-title">بیدارباش</div>' +
              '<div class="g-sub">در ساعت انتخابی، همین تلویزیون پیام و صدای بیدارباش پخش می‌کند</div>';
      if (typeof msg === 'string' && msg) h += '<div class="g-msg">' + TV.esc(msg) + '</div>';
      h += '<div class="g-label">ساعت</div><div class="g-row">' +
           btn('g-chip', '−', function () { st.wake.h = (st.wake.h + 23) % 24; showWake('', ui.cur()); }) +
           '<span class="g-num">' + fa(pad(st.wake.h)) + '</span>' +
           btn('g-chip', '+', function () { st.wake.h = (st.wake.h + 1) % 24; showWake('', ui.cur()); }) +
           '</div>';
      h += '<div class="g-label">دقیقه</div><div class="g-row">' +
           btn('g-chip', '−', function () { st.wake.m = (st.wake.m + 45) % 60; showWake('', ui.cur()); }) +
           '<span class="g-num">' + fa(pad(st.wake.m)) + '</span>' +
           btn('g-chip', '+', function () { st.wake.m = (st.wake.m + 15) % 60; showWake('', ui.cur()); }) +
           '</div>';
      h += '<div class="g-total">' + (w.tomorrow ? 'فردا' : 'امروز') + ' ساعت ' +
           fa(pad(st.wake.h) + ':' + pad(st.wake.m)) + '</div>';
      h += btn('g-chip', '<i class="fas fa-check"></i> ثبت بیدارباش', function () {
        send({ category: 'wakeup', scheduled_at: wakeAt().str });
      });
      h += btn('g-chip', 'بازگشت', function () { showHome(); });
      return h + ui.hint('تلویزیون باید روشن بماند؛ در حالت خاموش پیام نمایش داده نمی‌شود');
    }, focusIdx);
  }

  // ── نظرسنجی ─────────────────────────────────────────────────────
  function showFeedback(msg) {
    st.screen = 'feedback';
    ui.paint(function () {
      var h = '<div class="g-title">نظرسنجی</div><div class="g-sub">از اقامت خود چقدر راضی هستید؟</div>';
      if (typeof msg === 'string') h += '<div class="g-msg">' + TV.esc(msg) + '</div>';
      var i, words = ['', 'ضعیف', 'متوسط', 'خوب', 'خیلی خوب', 'عالی'];
      for (i = 5; i >= 1; i--) {
        (function (r) {
          var stars = '', k;
          for (k = 0; k < r; k++) stars += '★';
          h += btn('g-chip', stars + '<small>' + words[r] + '</small>', function () { send({ category: 'feedback', rating: r }); });
        })(i);
      }
      return h + '<div style="margin-top:1rem">' + btn('g-chip', 'بازگشت', function () { showHome(); }) + '</div>';
    });
  }

  // ── ارسال ───────────────────────────────────────────────────────
  function send(body) {
    var back = st.screen;
    ui.busy = true;
    TV.loading('در حال ثبت…');
    TV.post(API + '/requests', body, function (err, d) {
      ui.busy = false;
      TV.ready();
      if (err || !d || !d.success) {
        var m = TVG.errMsg(err, 'ثبت درخواست ناموفق بود');
        if (back === 'items') showItems(ui.cur(), m);
        else if (back === 'taxi') showTaxi(m);
        else if (back === 'wake') showWake(m);
        else if (back === 'feedback') showFeedback(m);
        else showHome(m);
        return;
      }
      showDone(body.category);
    });
  }

  function showDone(cat) {
    st.screen = 'done';
    var text = cat === 'feedback' ? 'از نظر شما سپاسگزاریم'
             : cat === 'wakeup'   ? 'بیدارباش ثبت شد'
             : 'درخواست شما ثبت شد';
    ui.paint(function () {
      return '<div class="g-ok"><i class="fas fa-circle-check"></i> ' + text + '</div>' +
             (cat === 'feedback' ? '' : '<div class="g-sub">وضعیت را در «درخواست‌های من» ببینید.</div>' +
                                        btn('g-chip', 'درخواست‌های من', function () { loadMine(); })) +
             btn('g-chip', 'بازگشت به خدمات', function () { showHome(); });
    });
  }

  // ── درخواست‌های من ──────────────────────────────────────────────
  function loadMine(msg) {
    TV.loading('در حال بارگذاری…');
    TV.get(API + '/requests', function (err, d) {
      TV.ready();
      st.mine = (!err && d && d.success) ? (d.data || []) : [];
      showMine(msg || (err ? 'دریافت فهرست ناموفق بود' : ''));
    });
  }

  function showMine(msg) {
    st.screen = 'mine';
    ui.paint(function () {
      var h = '<div class="g-title">درخواست‌های من</div><div class="g-sub">درخواست‌های این اقامت</div>';
      if (msg) h += '<div class="g-msg">' + TV.esc(msg) + '</div>';
      if (!st.mine.length) h += '<div class="g-msg">هنوز درخواستی ثبت نکرده‌اید.</div>';
      var i;
      for (i = 0; i < st.mine.length && i < 20; i++) {
        (function (r) {
          if (r.category === 'feedback') return;
          var s = STATUS[r.status] || [r.status, '#64748b'];
          var lines = [], k;
          for (k = 0; k < (r.items || []).length; k++) lines.push(TV.esc(r.items[k].name_snapshot) + ' × ' + fa(r.items[k].qty));
          var meta = lines.join('، ');
          if (r.scheduled_at) meta += (meta ? ' · ' : '') + 'ساعت ' + hm(r.scheduled_at);
          if (+r.total_price > 0) meta += ' · ' + money(r.total_price);
          h += '<div class="g-item"><div class="g-item-head">' + label(r.category) +
               '<span class="g-st" style="background:' + s[1] + '33;color:' + s[1] + '">' + s[0] + '</span></div>' +
               '<div class="g-item-meta">' + meta + '</div>';
          if (r.status === 'pending') {
            h += '<div style="margin-top:.6rem">' + btn('g-chip', 'لغو', function () { cancel(r); }) + '</div>';
          }
          h += '</div>';
        })(st.mine[i]);
      }
      return h + btn('g-chip', 'بازگشت', function () { showHome(); });
    });
  }

  function cancel(r) {
    ui.busy = true;
    TV.post(API + '/requests/' + r.id + '/cancel', {}, function (err, d) {
      ui.busy = false;
      loadMine((err || !d || !d.success) ? TVG.errMsg(err, 'لغو ناموفق بود') : 'درخواست لغو شد');
    });
  }

  // ── ریموت ───────────────────────────────────────────────────────
  TVG.mount(function (k) {
    if (ui.nav(k)) return true;
    if (k === 'BACK' || k === 'EXIT') {
      if (ui.busy) return true;
      if (st.screen !== 'home') { showHome(); return true; }
      return false;
    }
    return false;
  });

  load();
})();
</script>
</body>
</html>
