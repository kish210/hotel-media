<?php
/**
 * محتوای اتاق روی تلویزیون — خبر، قرآن، کتابخانه، دفترچه تلفن
 * (TODO ۱.۹، ۱.۱۰، ۱.۱۶، ۲.۱۵)
 *
 * API این‌ها از فاز ۳ آماده بود ولی هیچ صفحه‌ای روی تلویزیون صدایش
 * نمی‌زد؛ کاشی‌های «اخبار»، «قرآن»، «کتاب» و «دفترچه» پیام «ماژول
 * نامعتبر» می‌دادند.
 *
 * کتاب: اگر متن دارد، متن ورق می‌خورد؛ اگر PDF است، سرور هر صفحه را
 * تصویر می‌کند (BookService) چون مرورگر تلویزیون PDF باز نمی‌کند.
 * صفحه‌ی آخر هر کتاب به خاطر سپرده می‌شود.
 *
 * کلیدها در کتاب: چپ = صفحه‌ی بعد (کتاب فارسی راست‌به‌چپ ورق می‌خورد)،
 * راست = صفحه‌ی قبل، بالا/پایین = پیمایش، OK = پخش/توقف صوت.
 *
 * @var array  $screen
 * @var string $kind
 */
$code = (string)($screen['code'] ?? '');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>محتوا</title>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css<?= v() ?>">
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-base.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-guest.css<?= v() ?>">
<script src="/assets/js/tv-base.js<?= v() ?>"></script>
<script src="/assets/js/tv-guest.js<?= v() ?>"></script>
<style>
.ct-dir { position: relative; }
.ct-ext { position: absolute; left: 1.4rem; top: .9rem; font-size: 1.6rem; font-weight: 800; color: #fbbf24; }
.ct-thumb { float: left; width: 8rem; height: 4.8rem; margin-right: 1rem; border-radius: .6rem; background: #1e293b center / cover no-repeat; }
#ct-reader { position: absolute; top: 0; right: 0; bottom: 0; left: 0; background: #0b1220; display: none; }
#ct-head { padding: 1.6rem 3.2rem .8rem; }
#ct-title { font-size: 1.6rem; font-weight: 800; color: #fff; }
#ct-meta { font-size: .95rem; color: #94a3b8; margin-top: .3rem; }
#ct-body { position: absolute; top: 6.2rem; bottom: 4rem; right: 3.2rem; left: 3.2rem; overflow: hidden; }
#ct-text { font-size: 1.45rem; line-height: 2.6; color: #e2e8f0; white-space: pre-wrap; }
#ct-img { display: block; margin: 0 auto; max-width: 100%; }
#ct-hero { display: block; max-width: 40rem; max-height: 18rem; margin: 0 0 1.2rem; border-radius: .8rem; }
#ct-foot { position: absolute; bottom: 1.2rem; right: 3.2rem; left: 3.2rem; font-size: .95rem; color: #64748b; }
#ct-page { color: #fbbf24; font-weight: 700; margin-left: 1.4rem; }
#ct-audio-st { color: #14b8a6; margin-left: 1.4rem; }
</style>
</head>
<body>
<div id="g-root"></div>

<div id="ct-reader">
  <div id="ct-head"><div id="ct-title"></div><div id="ct-meta"></div></div>
  <div id="ct-body"><img id="ct-hero" alt="" style="display:none"><div id="ct-text"></div><img id="ct-img" alt="" style="display:none"></div>
  <div id="ct-foot"><span id="ct-page"></span><span id="ct-audio-st"></span><span id="ct-hint"></span></div>
</div>
<audio id="ct-audio" preload="none"></audio>
<div id="tv-center" class="tv-center tv-hidden"></div>

<script>
/* ES5 خالص — تلویزیون‌های هتلی. */
(function () {
  'use strict';

  var CODE = <?= json_encode($code) ?>;
  var KIND = <?= json_encode($kind) ?>;
  var API  = '/api/v1/guest/' + encodeURIComponent(CODE) + '/content/' + KIND;
  var TITLES = { news: 'اخبار', quran: 'قرآن کریم', book: 'کتابخانه', directory: 'دفترچه تلفن هتل' };

  var fa = TVG.fa;
  var ui = TVG.ui(TV.id('g-root'));
  var btn = ui.btn;
  var items = [], reading = null, page = 1, listFocus = 0;

  function store(k, v) { try { window.localStorage.setItem(k, v); } catch (e) {} }
  function recall(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } }

  // ── فهرست ───────────────────────────────────────────────────────
  function load() {
    TV.loading('در حال بارگذاری…');
    TV.get(API, function (err, d) {
      TV.ready();
      if (err || !d || !d.success) { TV.fail('محتوا در دسترس نیست', 'لطفاً کمی بعد دوباره امتحان کنید.'); return; }
      items = d.data || [];
      showList(0);
    });
  }

  function showList(focusIdx) {
    ui.paint(function () {
      var h = '<div class="g-title">' + TITLES[KIND] + '</div>';
      if (KIND === 'directory') h += '<div class="g-sub">برای تماس، شماره‌ی داخلی را با تلفن اتاق بگیرید</div>';
      else h += '<div class="g-sub">یکی را انتخاب کنید</div>';
      if (!items.length) return h + '<div class="g-msg">هنوز چیزی اضافه نشده است.</div>' + ui.hint('BACK: بازگشت');
      var i;
      for (i = 0; i < items.length; i++) {
        (function (it, idx) {
          var name = TV.esc(it.title);
          if (KIND === 'directory') {
            h += '<div class="g-item ct-dir"><div class="g-item-head">' + name + '</div>' +
                 (it.subtitle ? '<div class="g-item-meta">' + TV.esc(it.subtitle) + '</div>' : '') +
                 '<span class="ct-ext">' + fa(it.extra || '') + '</span></div>';
            return;
          }
          var meta = [];
          if (it.subtitle) meta.push(TV.esc(it.subtitle));
          if (it.extra) meta.push(TV.esc(it.extra));
          if (it.audio_url) meta.push('<i class="fas fa-volume-high"></i> صوتی');
          if (KIND === 'book' && recall('hm_book_' + it.id)) meta.push('ادامه از صفحه‌ی ' + fa(recall('hm_book_' + it.id)));
          h += btn('g-item', (it.image_url && KIND === 'news' ? '<span class="ct-thumb" style="background-image:url(' +
                   TV.esc(it.image_url) + ')"></span>' : '') +
                   '<div class="g-item-head">' + name + '</div><div class="g-item-meta">' + meta.join(' · ') + '</div>',
                   function () { listFocus = idx; open(it.id); });
        })(items[i], i);
      }
      return h + ui.hint('OK: باز کردن  ·  BACK: بازگشت');
    }, focusIdx);
  }

  // ── خواندن ──────────────────────────────────────────────────────
  function open(id) {
    TV.loading('در حال بارگذاری…');
    TV.get(API + '/' + id, function (err, d) {
      TV.ready();
      if (err || !d || !d.success) return;
      reading = d.data;
      page = reading.pages > 0 ? Math.max(1, Math.min(reading.pages, +(recall('hm_book_' + reading.id) || 1))) : 1;
      TV.text(TV.id('ct-title'), reading.title);
      TV.text(TV.id('ct-meta'), [reading.subtitle, reading.extra].filter(function (x) { return !!x; }).join(' · '));

      var hero = TV.id('ct-hero');
      if (reading.image_url && !reading.pages) { hero.src = reading.image_url; hero.style.display = 'block'; }
      else hero.style.display = 'none';

      var au = TV.id('ct-audio');
      au.pause();
      if (reading.audio_url) { au.src = reading.audio_url; TV.text(TV.id('ct-audio-st'), 'OK: پخش صوت'); }
      else { au.removeAttribute('src'); TV.text(TV.id('ct-audio-st'), ''); }

      if (reading.pages > 0) {
        TV.id('ct-text').style.display = 'none';
        showPage();
      } else {
        TV.id('ct-img').style.display = 'none';
        TV.id('ct-text').style.display = 'block';
        TV.text(TV.id('ct-text'), reading.body || (reading.audio_url ? '' : 'متنی برای نمایش نیست.'));
        TV.id('ct-body').scrollTop = 0;
      }
      TV.text(TV.id('ct-hint'), reading.pages > 0 ? 'چپ: صفحه‌ی بعد · راست: صفحه‌ی قبل · BACK: فهرست'
                                                    : 'بالا/پایین: ورق زدن · BACK: فهرست');
      TV.id('g-root').style.display = 'none';
      TV.id('ct-reader').style.display = 'block';
      /* اندازه‌ی متن فقط بعد از نمایش معلوم است؛ در حالت پنهان ارتفاع صفر بود */
      if (!(reading.pages > 0)) textPageLabel();
    });
  }

  function showPage() {
    var img = TV.id('ct-img');
    img.style.display = 'block';
    img.src = '/tv/guest/' + encodeURIComponent(CODE) + '/book/' + reading.id + '/' + page;
    /* ارتفاع صفحه به اندازه‌ی کادر، تا یک صفحه‌ی کامل بدون پیمایش دیده شود */
    img.style.height = TV.id('ct-body').clientHeight + 'px';
    img.style.width = 'auto';
    TV.id('ct-body').scrollTop = 0;
    TV.text(TV.id('ct-page'), 'صفحه‌ی ' + fa(page) + ' از ' + fa(reading.pages));
    store('hm_book_' + reading.id, String(page));
  }

  /* هر ورق ۹۰٪ کادر است (۱۰٪ تکرار تا خط آخر گم نشود)، پس شمارش هم با همان گام */
  function textPageLabel() {
    var b = TV.id('ct-body'), step = Math.max(1, b.clientHeight * 0.9);
    var total = Math.max(1, Math.ceil(Math.max(0, b.scrollHeight - b.clientHeight) / step) + 1);
    var cur = Math.min(total, Math.round(b.scrollTop / step) + 1);
    TV.text(TV.id('ct-page'), total > 1 ? 'صفحه‌ی ' + fa(cur) + ' از ' + fa(total) : '');
  }

  function closeReader() {
    TV.id('ct-audio').pause();
    reading = null;
    TV.id('ct-reader').style.display = 'none';
    TV.id('g-root').style.display = 'block';
    showList(listFocus);
  }

  function readerKey(k) {
    var b = TV.id('ct-body');
    if (reading.pages > 0) {
      if ((k === 'LEFT' || k === 'CH_DOWN') && page < reading.pages) { page++; showPage(); }
      else if ((k === 'RIGHT' || k === 'CH_UP') && page > 1) { page--; showPage(); }
      else if (k === 'DOWN') b.scrollTop += b.clientHeight * 0.5;
      else if (k === 'UP') b.scrollTop -= b.clientHeight * 0.5;
    } else if (k === 'DOWN' || k === 'LEFT' || k === 'CH_DOWN') { b.scrollTop += b.clientHeight * 0.9; textPageLabel(); }
    else if (k === 'UP' || k === 'RIGHT' || k === 'CH_UP')      { b.scrollTop -= b.clientHeight * 0.9; textPageLabel(); }

    if (k === 'OK' && reading.audio_url) {
      var au = TV.id('ct-audio');
      if (au.paused) { try { au.play(); } catch (e) {} TV.text(TV.id('ct-audio-st'), '▶ در حال پخش — OK: توقف'); }
      else { au.pause(); TV.text(TV.id('ct-audio-st'), 'OK: ادامه‌ی پخش'); }
    }
    return true;
  }

  TVG.mount(function (k) {
    if (reading) {
      if (k === 'BACK' || k === 'EXIT') { closeReader(); return true; }
      return readerKey(k);
    }
    if (ui.nav(k)) return true;
    return false;
  });

  load();
})();
</script>
</body>
</html>
