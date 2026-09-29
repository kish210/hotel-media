<?php
/**
 * راهنمای برنامه‌ها، پخش دوباره (catch-up) و ضبط شخصی (NPVR) روی
 * تلویزیون اتاق — TODO ۱.۲، ۱.۳، ۱.۱۳
 *
 * API های ضبط و catch-up از فاز ۹ بودند ولی هیچ صفحه‌ای روی تلویزیون
 * صدایشان نمی‌زد. اینجا: ستون کانال‌ها، ستون برنامه‌های روز انتخابی،
 * و روی هر برنامه: گذشته ← پخش دوباره، آینده ← ضبط.
 *
 * کلیدها: بالا/پایین در ستون · چپ/راست بین ستون‌ها و ردیف روزها ·
 * OK کار روی برنامه · BACK برگشت.
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
<title>راهنمای برنامه‌ها</title>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css<?= v() ?>">
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-base.css<?= v() ?>">
<link rel="stylesheet" href="/assets/css/tv-guest.css<?= v() ?>">
<script src="/assets/vendor/hls/hls.min.js<?= v() ?>"></script>
<script src="/assets/js/tv-base.js<?= v() ?>"></script>
<script src="/assets/js/tv-guest.js<?= v() ?>"></script>
<style>
#gd { position: absolute; top: 0; right: 0; bottom: 0; left: 0; padding: 1.6rem 2.4rem 3.4rem; }
#gd-days { white-space: nowrap; margin: .6rem 0 1rem; }
#gd-cols { position: absolute; top: 9.4rem; bottom: 3.4rem; right: 2.4rem; left: 2.4rem; }
#gd-ch  { position: absolute; top: 0; bottom: 0; right: 0; width: 18rem; overflow: hidden; }
#gd-pr  { position: absolute; top: 0; bottom: 0; right: 19.4rem; left: 0; overflow: hidden; }
.gd-row { padding: .6rem 1rem; border-radius: .6rem; margin-bottom: .3rem; white-space: nowrap; overflow: hidden;
          border: 2px solid transparent; }
.gd-row.is-sel { background: rgba(255,255,255,.07); }
.gd-row.is-focused { background: rgba(26,122,196,.35); border-color: #4098db; }
.gd-no { display: inline-block; width: 2.6rem; color: #94a3b8; font-weight: 700; }
.gd-when { display: inline-block; width: 7rem; color: #94a3b8; font-size: .9rem; }
.gd-time { display: inline-block; width: 6rem; color: #fbbf24; font-weight: 700; }
.gd-title { color: #fff; font-size: 1.05rem; }
.gd-tag { font-size: .75rem; padding: .1rem .5rem; border-radius: .4rem; margin-right: .5rem; }
.gd-past .gd-title { color: #94a3b8; }
.gd-now .gd-time { color: #22c55e; }
#gd-desc { position: absolute; bottom: .8rem; right: 2.4rem; left: 2.4rem; font-size: .95rem; color: #94a3b8;
           white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#gd-player { position: absolute; top: 0; right: 0; bottom: 0; left: 0; background: #000; display: none; }
#gd-player video { width: 100%; height: 100%; }
#gd-pmsg { position: absolute; top: 45%; left: 0; right: 0; text-align: center; color: #fff; font-size: 1.3rem; }
#gd-dlg { position: absolute; top: 0; right: 0; bottom: 0; left: 0; background: rgba(0,0,0,.8); display: none; text-align: center; padding-top: 12rem; }
#gd-dlg-box { display: inline-block; background: #0f172a; border-radius: 1rem; padding: 1.6rem 2.4rem; min-width: 30rem; }
#gd-dlg-title { font-size: 1.3rem; color: #fff; font-weight: 800; margin-bottom: 1rem; }
#gd-dlg-msg { color: #fbbf24; min-height: 1.4rem; margin-bottom: 1rem; }
</style>
</head>
<body>
<div id="gd">
  <div class="g-title">راهنمای برنامه‌ها</div>
  <div id="gd-days"></div>
  <div id="gd-cols"><div id="gd-ch"></div><div id="gd-pr"></div></div>
  <div id="gd-desc"></div>
</div>
<div id="g-root" style="display:none"></div>
<div id="gd-player"><div id="gd-video"></div><div id="gd-pmsg"></div></div>
<div id="gd-dlg"><div id="gd-dlg-box"><div id="gd-dlg-title"></div><div id="gd-dlg-msg"></div><div id="gd-dlg-btns"></div></div></div>
<div id="tv-center" class="tv-center tv-hidden"></div>

<script>
/* ES5 خالص — تلویزیون‌های هتلی. */
(function () {
  'use strict';

  var CODE = <?= json_encode($code) ?>;
  var DAYS = <?= json_encode(array_slice($days, 0, 2), JSON_UNESCAPED_UNICODE) ?>;
  var YESTERDAY = <?= json_encode(date('Y-m-d', strtotime('-1 day'))) ?>;
  var G = '/api/v1/guest/' + encodeURIComponent(CODE);
  var D = '/api/v1/dvr/room/' + encodeURIComponent(CODE);
  var fa = TVG.fa, hm = TVG.hm;

  var dayList = [{ date: YESTERDAY, label: 'دیروز' }].concat(DAYS).concat([{ date: '', label: 'ضبط‌های من' }]);
  var st = { zone: 'ch', day: 1, ch: 0, pr: 0, channels: [], programs: [], recs: [], mode: 'guide' };
  var dlg = null, hls = null, playing = false;

  // ── داده ────────────────────────────────────────────────────────
  function loadChannels() {
    TV.loading('در حال بارگذاری…');
    TV.get(G + '/channels', function (err, d) {
      TV.ready();
      if (err || !d || !d.success) { TV.fail('راهنما در دسترس نیست', 'لطفاً کمی بعد دوباره امتحان کنید.'); return; }
      var all = d.data.channels || [], i;
      st.channels = [];
      for (i = 0; i < all.length; i++) if (!all[i].is_radio) st.channels.push(all[i]);
      paint(); loadPrograms();
    });
  }

  function loadPrograms() {
    var ch = st.channels[st.ch];
    st.programs = []; st.pr = 0;
    if (!ch) { paint(); return; }
    var want = ch.id + '|' + st.day;
    TV.get(G + '/epg/' + ch.id + '?date=' + dayList[st.day].date, function (err, d) {
      if (want !== (st.channels[st.ch] || {}).id + '|' + st.day) return;
      if (err) { st.programs = []; st.prMsg = (err.status === 403) ? 'این کانال قفل است' : 'راهنمای این کانال در دسترس نیست'; }
      else {
        st.programs = d.data.programs || []; st.prMsg = st.programs.length ? '' : 'برای این روز برنامه‌ای ثبت نشده';
        var i; for (i = 0; i < st.programs.length; i++) if (st.programs[i].state === 'now') st.pr = i;
      }
      paint();
    });
  }

  function loadRecs() {
    TV.get(D + '/recordings', function (err, d) {
      st.recs = (!err && d && d.success) ? (d.data || []) : [];
      st.pr = 0; paint();
    });
  }

  // ── نمایش ───────────────────────────────────────────────────────
  /* ضبط ممکن است مال چند روز پیش باشد؛ ساعت تنها کافی نیست */
  function dayOf(dt) {
    var d = String(dt || '').substr(0, 10), i;
    for (i = 0; i < dayList.length; i++) if (dayList[i].date === d) return dayList[i].label;
    return fa(d.substr(5).replace('-', '/'));
  }
  function win(list, focus, size) { var f = Math.max(0, focus - Math.floor(size / 2)); return [f, Math.min(list.length, f + size)]; }

  function paint() {
    var h = '', i;
    for (i = 0; i < dayList.length; i++) {
      h += '<span class="g-chip' + (i === st.day ? ' is-on' : '') + (st.zone === 'day' && i === st.day ? ' fx is-focused' : '') + '">' +
           TV.esc(dayList[i].label) + '</span>';
    }
    TV.id('gd-days').innerHTML = h;

    var isRec = dayList[st.day].date === '';
    TV.id('gd-ch').style.display = isRec ? 'none' : 'block';
    TV.id('gd-pr').style.right = isRec ? '0' : '19.4rem';

    h = '';
    var w = win(st.channels, st.ch, 14);
    for (i = w[0]; i < w[1]; i++) {
      var c = st.channels[i];
      h += '<div class="gd-row' + (i === st.ch ? (st.zone === 'ch' ? ' is-focused' : ' is-sel') : '') + '">' +
           '<span class="gd-no">' + (c.channel_no ? fa(c.channel_no) : '') + '</span>' + TV.esc(c.name) + (c.locked ? ' 🔒' : '') + '</div>';
    }
    TV.id('gd-ch').innerHTML = h;

    h = ''; var desc = '';
    if (isRec) {
      if (!st.recs.length) h = '<div class="g-msg">هنوز ضبطی ندارید. در راهنما روی برنامه‌ی آینده OK بزنید.</div>';
      w = win(st.recs, st.pr, 13);
      for (i = w[0]; i < w[1]; i++) {
        var r = st.recs[i];
        var tag = { scheduled: ['در انتظار', '#3b82f6'], recording: ['در حال ضبط', '#ef4444'], finished: ['آماده', '#22c55e'],
                    failed: ['ناموفق', '#64748b'] }[r.status] || [r.status, '#64748b'];
        h += '<div class="gd-row' + (i === st.pr && st.zone === 'pr' ? ' is-focused' : '') + '">' +
             '<span class="gd-when">' + dayOf(r.starts_at) + '</span>' +
             '<span class="gd-time">' + hm(r.starts_at) + '</span><span class="gd-title">' + TV.esc(r.title) + '</span>' +
             '<span class="gd-tag" style="background:' + tag[1] + '33;color:' + tag[1] + '">' + tag[0] + '</span>' +
             '<span style="color:#64748b;font-size:.85rem;margin-right:.6rem">' + TV.esc(r.channel_name || '') + '</span></div>';
      }
      if (st.recs[st.pr]) desc = st.recs[st.pr].status === 'finished' ? 'OK: پخش · راست: حذف' : 'راست: حذف';
    } else {
      if (st.prMsg) h = '<div class="g-msg">' + TV.esc(st.prMsg) + '</div>';
      w = win(st.programs, st.pr, 13);
      for (i = w[0]; i < w[1]; i++) {
        var p = st.programs[i];
        h += '<div class="gd-row gd-' + p.state + (i === st.pr && st.zone === 'pr' ? ' is-focused' : '') + '">' +
             '<span class="gd-time">' + hm(p.starts_at) + '</span><span class="gd-title">' + TV.esc(p.title) + '</span>' +
             (p.state === 'now' ? '<span class="gd-tag" style="background:#22c55e33;color:#22c55e">الان</span>' : '') +
             (p.can_catchup ? '<span class="gd-tag" style="background:#14b8a633;color:#14b8a6">پخش دوباره</span>' : '') +
             (p.can_record ? '<span class="gd-tag" style="background:#ef444433;color:#f87171">● ضبط</span>' : '') + '</div>';
      }
      var cur = st.programs[st.pr];
      if (cur) desc = (cur.description || cur.subtitle || '') ;
    }
    TV.id('gd-pr').innerHTML = h;
    TV.text(TV.id('gd-desc'), desc);
  }

  // ── پخش ─────────────────────────────────────────────────────────
  function play(url, label) {
    playing = true;
    TV.id('gd-player').style.display = 'block';
    TV.text(TV.id('gd-pmsg'), '');
    var v = document.createElement('video');
    v.autoplay = true; v.controls = false;
    TV.id('gd-video').innerHTML = ''; TV.id('gd-video').appendChild(v);
    v.onerror = function () { TV.text(TV.id('gd-pmsg'), 'پخش باز نشد'); };
    if (/\.m3u8(\?|$)/i.test(url) && window.Hls && window.Hls.isSupported()) {
      hls = new window.Hls(); hls.loadSource(url); hls.attachMedia(v);
    } else { v.src = url; }   /* فایل ضبط TVHeadend (TS) با پخش‌کننده‌ی بومی تلویزیون */
    try { var p = v.play(); if (p && p['catch']) p['catch'](function () {}); } catch (e) {}
    if (window.TVTrick) { try { window.TVTrick.attach(v, TV.id('gd-video')); } catch (e2) {} }
  }
  function stopPlay() {
    playing = false;
    if (hls) { try { hls.destroy(); } catch (e) {} hls = null; }
    TV.id('gd-video').innerHTML = '';
    TV.id('gd-player').style.display = 'none';
  }

  // ── پنجره‌ی تایید ─────────────────────────────────────────────────
  /* onOk خالی یعنی پیام است، نه سؤال: فقط دکمه‌ی «باشه» */
  function openDlg(title, okLabel, onOk) {
    dlg = { onOk: onOk || closeDlg, focus: 0, single: !onOk };
    TV.text(TV.id('gd-dlg-title'), title);
    TV.text(TV.id('gd-dlg-msg'), '');
    paintDlg(okLabel);
    TV.id('gd-dlg').style.display = 'block';
  }
  function paintDlg(okLabel) {
    if (okLabel) dlg.okLabel = okLabel;
    TV.id('gd-dlg-btns').innerHTML =
      '<span class="g-chip' + (dlg.focus === 0 ? ' fx is-focused' : '') + '">' + TV.esc(dlg.okLabel) + '</span>' +
      (dlg.single ? '' : '<span class="g-chip' + (dlg.focus === 1 ? ' fx is-focused' : '') + '">انصراف</span>');
  }
  function closeDlg() { dlg = null; TV.id('gd-dlg').style.display = 'none'; }
  function dlgMsg(t) { TV.text(TV.id('gd-dlg-msg'), t); }

  function act() {
    var ch = st.channels[st.ch];
    if (dayList[st.day].date === '') {
      var r = st.recs[st.pr];
      if (r && r.status === 'finished' && r.file_url) play(r.file_url, r.title);
      return;
    }
    var p = st.programs[st.pr];
    if (!p) return;
    if (p.can_catchup) {
      TV.loading('در حال آماده‌سازی…');
      TV.get(D + '/catchup?channel_id=' + ch.id + '&event_id=' + encodeURIComponent(p.external_id), function (err, d) {
        TV.ready();
        if (err || !d || !d.success) { openDlg(TVG.errMsg(err, 'پخش دوباره‌ی این برنامه در دسترس نیست'), 'باشه'); return; }
        play(d.data.url, p.title);
      });
    } else if (p.can_record) {
      openDlg('ضبط «' + p.title + '» ساعت ' + hm(p.starts_at) + '؟', 'ضبط کن', function () {
        TV.post(D + '/record', { channel_id: ch.id, event_id: +p.external_id || 0, title: p.title,
                                 start: 0, stop: 0 }, function (err, d) {
          if (err || !d || !d.success) { dlgMsg(TVG.errMsg(err, 'ثبت ضبط ناموفق بود')); return; }
          dlgMsg('ضبط ثبت شد — در «ضبط‌های من» ببینید');
          setTimeout(closeDlg, 1600);
        });
      });
    } else if (p.state === 'now') {
      openDlg('این برنامه همین حالا پخش می‌شود — از «پخش زنده» ببینید', 'باشه');
    }
  }

  function delRec() {
    var r = st.recs[st.pr];
    if (!r) return;
    openDlg('ضبط «' + r.title + '» حذف شود؟', 'حذف', function () {
      TV.post(D + '/recordings/' + r.id + '/delete', {}, function (err, d) {
        closeDlg();
        loadRecs();
      });
    });
  }

  // ── ریموت ───────────────────────────────────────────────────────
  TVG.mount(function (k) {
    if (playing) { if (k === 'BACK' || k === 'EXIT') { stopPlay(); return true; } return false; }
    if (dlg) {
      if ((k === 'LEFT' || k === 'RIGHT') && !dlg.single) { dlg.focus = 1 - dlg.focus; paintDlg(); return true; }
      if (k === 'OK') { if (dlg.focus === 0) dlg.onOk(); else closeDlg(); return true; }
      if (k === 'BACK' || k === 'EXIT') { closeDlg(); return true; }
      return true;
    }
    var isRec = dayList[st.day].date === '';
    if (st.zone === 'day') {
      if (k === 'LEFT' && st.day < dayList.length - 1) st.day++;
      else if (k === 'RIGHT' && st.day > 0) st.day--;
      else if (k === 'DOWN' || k === 'OK') { st.zone = isRec ? 'pr' : 'ch'; paint(); return true; }
      else if (k === 'BACK' || k === 'EXIT') return false;
      else return true;
      if (dayList[st.day].date === '') loadRecs(); else loadPrograms();
      paint(); return true;
    }
    if (st.zone === 'ch') {
      if (k === 'UP')   { if (st.ch > 0) { st.ch--; loadPrograms(); } else { st.zone = 'day'; } paint(); return true; }
      if (k === 'DOWN') { if (st.ch < st.channels.length - 1) { st.ch++; loadPrograms(); paint(); } return true; }
      if (k === 'LEFT' || k === 'OK') { if (st.programs.length) { st.zone = 'pr'; paint(); } return true; }
      if (k === 'BACK' || k === 'EXIT') return false;
      return true;
    }
    // ستون برنامه‌ها یا ضبط‌ها
    var list = isRec ? st.recs : st.programs;
    if (k === 'UP')   { if (st.pr > 0) st.pr--; else st.zone = 'day'; paint(); return true; }
    if (k === 'DOWN') { if (st.pr < list.length - 1) st.pr++; paint(); return true; }
    if (k === 'OK')   { act(); return true; }
    if (k === 'RIGHT') { if (isRec) delRec(); else { st.zone = 'ch'; paint(); } return true; }
    if (k === 'BACK' || k === 'EXIT') { st.zone = isRec ? 'day' : 'ch'; paint(); return true; }
    return true;
  });

  loadChannels();
})();
</script>
<script src="/assets/js/tv-trickplay.js<?= v() ?>"></script>
</body>
</html>
