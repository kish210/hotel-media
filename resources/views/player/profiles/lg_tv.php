<?php
/**
 * SignageCMS Player — LG WebOS Profile
 * Optimized for LG Smart TV (webOS 3.x, 4.x, 5.x, 6.x)
 * Uses native HLS where available, minimal JS, no heavy libs
 */
$s = json_decode($screen['settings'] ?? '{}', true) ?: [];
$ticker  = $s['ticker_text'] ?? '';
$logoUrl = $s['logo_url']    ?? '';
$logoPos = $s['logo_position'] ?? 'bottom-right';
$clk     = !empty($s['show_clock']);
$posMap  = ['bottom-right'=>'bottom:14px;right:14px','bottom-left'=>'bottom:14px;left:14px',
            'top-right'=>'top:14px;right:14px','top-left'=>'top:14px;left:14px'];
?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=1280">
<title>SignageCMS — LG</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body,html{width:1280px;height:720px;overflow:hidden;background:#000;}
#c{position:relative;width:1280px;height:720px;background:#000;}
.slide{position:absolute;top:0;left:0;width:1280px;height:720px;opacity:0;-webkit-transition:opacity .6s linear;transition:opacity .6s linear;}
.slide.on{opacity:1;}
.slide img{width:1280px;height:720px;object-fit:cover;display:block;}
.slide video{width:1280px;height:720px;display:block;background:#000;}
.slide iframe{width:1280px;height:720px;border:0;}
#ticker{position:absolute;bottom:0;left:0;width:1280px;height:36px;background:rgba(0,0,0,.8);overflow:hidden;<?= $ticker?'':'display:none;'?>}
#tick{position:absolute;top:0;height:36px;white-space:nowrap;font:600 17px/36px Arial,sans-serif;color:#fff;padding-right:1280px;}
#logo{position:absolute;<?= $posMap[$logoPos]??$posMap['bottom-right'] ?>;opacity:.85;<?= $logoUrl?'':'display:none;'?>}
#logo img{width:100px;}
#clock{position:absolute;top:14px;right:14px;padding:6px 14px;background:rgba(0,0,0,.55);border-radius:8px;font:700 26px/1 monospace;color:#fff;<?= $clk?'':'display:none;'?>}
#act{position:absolute;top:0;left:0;width:1280px;height:720px;background:#09090f;display:flex;align-items:center;justify-content:center;}
#act-box{background:#111;border-radius:14px;padding:32px 40px;text-align:center;width:320px;}
#act-inp{font:700 24px/1 monospace;letter-spacing:10px;padding:12px;width:100%;background:#0d0d14;border:2px solid rgba(249,115,22,.4);border-radius:10px;color:#fff;text-align:center;text-transform:uppercase;}
#act-btn{width:100%;margin-top:12px;padding:13px;font-size:15px;background:linear-gradient(135deg,#f97316,#c2570b);color:#fff;border:0;border-radius:10px;cursor:pointer;}
#act-msg{font-size:12px;margin-top:10px;min-height:18px;color:#ef4444;}
</style>
</head>
<body>
<div id="c">
  <?php if (($screen['status']??'') !== 'active'): ?>
  <div id="act">
    <div id="act-box">
      <div style="font-size:32px;margin-bottom:10px;">📺</div>
      <div style="font-size:17px;font-weight:700;color:#fff;margin-bottom:4px;">SignageCMS</div>
      <div style="font-size:11px;color:#64748b;margin-bottom:18px;">کد فعال‌سازی را وارد کنید</div>
      <div style="font-size:12px;color:#94a3b8;margin-bottom:12px;font-family:monospace;"><?= e($screen['code']??'') ?></div>
      <input id="act-inp" type="text" maxlength="6" placeholder="______">
      <button id="act-btn" onclick="doActivate()">فعال‌سازی</button>
      <div id="act-msg"></div>
    </div>
  </div>
  <?php else: ?>
  <!-- Logo -->
  <div id="logo"><?= $logoUrl ? '<img src="'.e($logoUrl).'" alt="" onerror="this.parentNode.style.display=\'none\'">' : '' ?></div>
  <!-- Clock -->
  <div id="clock">--:--</div>
  <!-- Ticker -->
  <div id="ticker"><div id="tick"><?= e($ticker).'&nbsp;&nbsp;&nbsp;&nbsp;'.e($ticker) ?></div></div>
  <?php endif; ?>
</div>

<script>
var SERVER = window.location.origin;
var CODE   = '<?= e($screen['code']??'') ?>';
var pl = [], ci = 0, tm = null, curSlide = null;
var _serverOffset = 0;

// ─── Server time sync ─────────────────────────────────────────
function syncTime() {
  var x = new XMLHttpRequest();
  x.open('GET', SERVER + '/api/v1/time', true);
  x.onload = function() {
    try { var d=JSON.parse(x.responseText); if(d.success) _serverOffset = d.timestamp*1000 - Date.now(); } catch(e) {}
  };
  x.send();
}
<?php if ($clk): ?>
function updateClock() {
  var d = new Date(Date.now() + _serverOffset);
  var h=d.getHours(), m=d.getMinutes(), s=d.getSeconds();
  var el = document.getElementById('clock');
  if(el) el.textContent = (h<10?'0':'')+h+':'+(m<10?'0':'')+m+':'+(s<10?'0':'')+s;
}
syncTime();
setInterval(syncTime, 300000);
setInterval(updateClock, 1000);
updateClock();
<?php endif; ?>

// ─── Ticker ───────────────────────────────────────────────────
<?php if ($ticker): ?>
(function(){
  var t = document.getElementById('tick');
  var pos = 0;
  setInterval(function(){
    pos -= 1.5;
    if (pos < -t.offsetWidth/2) pos = 0;
    t.style.left = pos + 'px';
  }, 16);
})();
<?php endif; ?>

// ─── Playlist ─────────────────────────────────────────────────
function loadPlaylist() {
  var x = new XMLHttpRequest();
  x.open('GET', SERVER + '/api/v1/screens/' + CODE + '/playlist', true);
  x.timeout = 10000;
  x.onload = function() {
    try {
      var d = JSON.parse(x.responseText);
      if (d.success && d.data && d.data.items && d.data.items.length) {
        pl = d.data.items;
        ci = 0;
        play(0);
      } else { setTimeout(loadPlaylist, 30000); }
    } catch(e) { setTimeout(loadPlaylist, 15000); }
  };
  x.ontimeout = x.onerror = function() { setTimeout(loadPlaylist, 15000); };
  x.send();
}

function fixUrl(u) {
  if (!u) return '';
  return u.replace(/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/g, SERVER);
}

function play(i) {
  clearTimeout(tm);
  if (!pl.length) return;
  var item = pl[i];
  var src  = fixUrl(item.file_url || item.src || item.file_path || item.url || '');
  var type = item.type || 'image';
  var dur  = (item.duration || 10) * 1000;
  var c    = document.getElementById('c');

  // ساخت slide جدید
  var div = document.createElement('div');
  div.className = 'slide';

  if (type === 'image' || src.match(/\.(jpg|jpeg|png|gif|webp)(\?|$)/i)) {
    var img = new Image();
    img.onload = function() {
      div.appendChild(img);
      c.appendChild(div);
      // fade in
      setTimeout(function(){ div.classList.add('on'); }, 30);
      // fade out old
      if (curSlide) {
        var old = curSlide;
        setTimeout(function(){ if(old.parentNode) old.parentNode.removeChild(old); }, 700);
      }
      curSlide = div;
    };
    img.onerror = function() { setTimeout(nextItem, 1000); };
    img.src = src;
    img.style.cssText = 'width:1280px;height:720px;object-fit:cover;display:block;';
    tm = setTimeout(nextItem, dur);

  } else if (type === 'video' || src.match(/\.(mp4|webm|ogv|mov)(\?|$)/i)) {
    var vid = document.createElement('video');
    vid.style.cssText = 'width:1280px;height:720px;display:block;background:#000;';
    // LG WebOS specific attributes
    vid.setAttribute('autoplay', '');
    vid.setAttribute('muted', '');
    vid.setAttribute('playsinline', '');
    vid.setAttribute('webkit-playsinline', '');
    vid.setAttribute('preload', 'auto');
    vid.muted = true;

    div.appendChild(vid);
    c.appendChild(div);
    setTimeout(function(){ div.classList.add('on'); }, 30);
    if (curSlide) {
      var old2 = curSlide;
      setTimeout(function(){ if(old2.parentNode) old2.parentNode.removeChild(old2); }, 700);
    }
    curSlide = div;

    vid.src = src;
    // LG WebOS: باید load() بعد از src set بشه
    vid.load();
    vid.play().catch(function(){});

    vid.onended = nextItem;
    vid.onerror = function() { setTimeout(nextItem, 1000); };
    // timeout برای ویدیوهای طولانی
    if (dur > 0 && dur < 7200000) tm = setTimeout(nextItem, dur);

  } else if (src.match(/\.m3u8(\?|$)/i)) {
    // HLS — LG webOS 4+ نیتیو support داره
    var vid2 = document.createElement('video');
    vid2.style.cssText = 'width:1280px;height:720px;display:block;background:#000;';
    vid2.setAttribute('autoplay', '');
    vid2.setAttribute('muted', '');
    vid2.muted = true;
    vid2.src = src;
    vid2.load();
    vid2.play().catch(function(){});
    vid2.onerror = nextItem;

    div.appendChild(vid2);
    c.appendChild(div);
    setTimeout(function(){ div.classList.add('on'); }, 30);
    if (curSlide) { var o3=curSlide; setTimeout(function(){ if(o3.parentNode)o3.parentNode.removeChild(o3); },700); }
    curSlide = div;
    if (dur > 0) tm = setTimeout(nextItem, dur);

  } else {
    // iframe / URL
    var ifr = document.createElement('iframe');
    ifr.src = src;
    ifr.style.cssText = 'width:1280px;height:720px;border:0;';
    div.appendChild(ifr);
    c.appendChild(div);
    setTimeout(function(){ div.classList.add('on'); }, 30);
    if (curSlide) { var o4=curSlide; setTimeout(function(){ if(o4.parentNode)o4.parentNode.removeChild(o4); },700); }
    curSlide = div;
    tm = setTimeout(nextItem, dur);
  }
}

function nextItem() {
  ci = (ci + 1) % pl.length;
  play(ci);
}

// ─── Heartbeat ────────────────────────────────────────────────
function heartbeat() {
  var x = new XMLHttpRequest();
  x.open('POST', SERVER + '/api/v1/screens/' + CODE + '/heartbeat', true);
  x.setRequestHeader('Content-Type', 'application/json');
  x.timeout = 8000;
  x.onload = function() {
    try {
      var d = JSON.parse(x.responseText);
      var cmds = (d.data && d.data.commands) ? d.data.commands : [];
      for (var i=0; i<cmds.length; i++) {
        if (cmds[i].command === 'reload') loadPlaylist();
        if (cmds[i].command === 'reboot') window.location.reload();
        if (cmds[i].command === 'instant_media') showInstant(cmds[i].data);
        if (cmds[i].command === 'clear_instant') clearInstant();
      }
    } catch(e) {}
  };
  x.send(JSON.stringify({version:'lg-webos',item:ci}));
}
setInterval(heartbeat, 15000);

// ─── Instant broadcast ───────────────────────────────────────
var instTimer = null;
function showInstant(data) {
  clearInstant();
  var ov = document.createElement('div');
  ov.id = 'instant-ov';
  ov.style.cssText = 'position:fixed;top:0;left:0;width:1280px;height:720px;z-index:9999;background:#000;display:flex;align-items:center;justify-content:center;';

  if (data.type === 'image') {
    ov.innerHTML = '<img src="'+fixUrl(data.content)+'" style="max-width:1280px;max-height:720px;object-fit:contain;">';
  } else if (data.type === 'video') {
    ov.innerHTML = '<video src="'+fixUrl(data.content)+'" autoplay muted playsinline style="max-width:1280px;max-height:720px;" onended="clearInstant()"></video>';
  } else if (data.type === 'text') {
    var t={text:'',color:'#fff',bg:'#000'};
    try { t = JSON.parse(data.content); } catch(e) { t.text = data.content; }
    ov.style.background = t.bg || '#000';
    ov.innerHTML = '<div style="font-size:72px;font-weight:900;color:'+(t.color||'#fff')+';text-align:center;padding:40px;">'+(t.text||'')+'</div>';
  }

  document.getElementById('c').appendChild(ov);
  var dur = parseInt(data.duration) || 30;
  if (dur > 0) instTimer = setTimeout(clearInstant, dur * 1000);
}
function clearInstant() {
  clearTimeout(instTimer);
  var el = document.getElementById('instant-ov');
  if (el && el.parentNode) el.parentNode.removeChild(el);
}

// ─── Activation ──────────────────────────────────────────────
function doActivate() {
  var code = (document.getElementById('act-inp').value || '').toUpperCase().trim();
  var msg  = document.getElementById('act-msg');
  if (!code || code.length !== 6) { msg.textContent = 'کد ۶ کاراکتر وارد کنید'; return; }
  msg.style.color = '#94a3b8';
  msg.textContent = 'در حال اتصال...';
  var x = new XMLHttpRequest();
  x.open('POST', SERVER + '/player/activate', true);
  x.setRequestHeader('Content-Type', 'application/json');
  x.timeout = 10000;
  x.onload = function() {
    var d = {}; try { d = JSON.parse(x.responseText); } catch(e) {}
    if (d.success) {
      msg.style.color = '#22c55e';
      msg.textContent = '✅ موفق! در حال راه‌اندازی...';
      setTimeout(function(){ window.location.reload(); }, 1200);
    } else {
      msg.style.color = '#ef4444';
      msg.textContent = d.message || 'کد نامعتبر است';
    }
  };
  x.ontimeout = x.onerror = function() {
    msg.style.color = '#f59e0b';
    msg.textContent = 'خطا در اتصال — ' + SERVER;
  };
  x.send(JSON.stringify({activation_code: code, screen_code: CODE}));
}

// LG remote OK button (Enter key)
document.addEventListener('keydown', function(e) {
  if (e.keyCode === 13 || e.keyCode === 461) {
    var actDiv = document.getElementById('act');
    if (actDiv) doActivate();
  }
});

// ─── Start ────────────────────────────────────────────────────
<?php if (($screen['status']??'') === 'active'): ?>
syncTime();
loadPlaylist();
heartbeat();
<?php endif; ?>
</script>
</body>
</html>
