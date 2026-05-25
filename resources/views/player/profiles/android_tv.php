<?php
/**
 * SignageCMS Player — Android TV Profile
 * Optimized: lightweight, no heavy libs, compatible with WebView
 */
$settings = json_decode($screen['settings'] ?? '{}', true) ?: [];
$tickerText = $settings['ticker_text'] ?? '';
$logoUrl    = $settings['logo_url']   ?? '';
$logoPos    = $settings['logo_position'] ?? 'bottom-right';
$showClock  = !empty($settings['show_clock']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>SignageCMS</title>
<style>
* { margin:0; padding:0; -webkit-box-sizing:border-box; box-sizing:border-box; }
html, body { width:100%; height:100%; background:#000; overflow:hidden; }

#wrap { position:relative; width:100%; height:100%; }
.slide { position:absolute; top:0; left:0; width:100%; height:100%;
         opacity:0; -webkit-transition:opacity 0.5s; transition:opacity 0.5s; }
.slide.show { opacity:1; }
.slide img { width:100%; height:100%; object-fit:cover; display:block; }
.slide video { width:100%; height:100%; display:block; background:#000; object-fit:contain; }
.slide iframe { width:100%; height:100%; border:0; }

/* Logo */
#logo {
  position:absolute; z-index:10; pointer-events:none;
  <?php
  $pos = ['bottom-right'=>'bottom:16px;right:16px','bottom-left'=>'bottom:16px;left:16px',
          'top-right'=>'top:16px;right:16px','top-left'=>'top:16px;left:16px'];
  echo $pos[$logoPos] ?? 'bottom:16px;right:16px';
  ?>;
  opacity:0.85;
  <?= $logoUrl ? '' : 'display:none;' ?>
}
#logo img { width:100px; max-width:100px; }

/* Ticker */
#ticker {
  position:absolute; bottom:0; left:0; right:0; height:38px;
  background:rgba(0,0,0,0.75); overflow:hidden; z-index:10;
  <?= $tickerText ? '' : 'display:none;' ?>
}
#ticker-track {
  position:absolute; top:0; height:100%; white-space:nowrap;
  font-size:17px; font-weight:600; color:#fff; line-height:38px;
  font-family:Arial,sans-serif;
}

/* Clock */
#clock {
  position:absolute; top:14px; right:14px; z-index:10;
  background:rgba(0,0,0,0.5); border-radius:8px; padding:6px 14px;
  font-size:26px; font-weight:700; color:#fff; font-family:monospace;
  <?= $showClock ? '' : 'display:none;' ?>
}

/* Loading */
#loading {
  position:absolute; inset:0; background:#000;
  display:-webkit-flex; display:flex;
  -webkit-align-items:center; align-items:center;
  -webkit-justify-content:center; justify-content:center;
  flex-direction:column; gap:16px; z-index:20;
}
#loading .dot { width:8px; height:8px; border-radius:50%; background:#f97316;
                display:inline-block; margin:0 4px; animation:bounce 1.2s infinite; }
#loading .dot:nth-child(2) { animation-delay:.2s; }
#loading .dot:nth-child(3) { animation-delay:.4s; }
@keyframes bounce { 0%,80%,100%{transform:scale(0)} 40%{transform:scale(1)} }

/* Activation */
#act {
  position:absolute; inset:0; background:#09090f;
  display:-webkit-flex; display:flex;
  -webkit-flex-direction:column; flex-direction:column;
  -webkit-align-items:center; align-items:center;
  -webkit-justify-content:center; justify-content:center;
  z-index:30;
}
#act-box { background:#111; border-radius:16px; padding:32px; width:320px; text-align:center; }
#act-code { font-size:26px; letter-spacing:8px; padding:12px; width:100%;
            background:#0d0d14; border:2px solid rgba(249,115,22,0.4);
            border-radius:10px; color:#fff; font-family:monospace;
            text-align:center; text-transform:uppercase; }
#act-btn { width:100%; margin-top:12px; padding:13px; font-size:15px;
           background:linear-gradient(135deg,#f97316,#c2570b);
           color:#fff; border:0; border-radius:10px; cursor:pointer; }
#act-err { color:#ef4444; font-size:13px; margin-top:10px; min-height:20px; }
#act-url { font-size:11px; color:#64748b; margin-top:14px; word-break:break-all; }
</style>
</head>
<body>
<div id="wrap">

  <?php if (($screen['status'] ?? '') !== 'active'): ?>
  <!-- Activation -->
  <div id="act">
    <div id="act-box">
      <div style="font-size:36px;margin-bottom:12px;">📺</div>
      <div style="font-size:18px;font-weight:700;color:#fff;margin-bottom:6px;">SignageCMS</div>
      <div style="font-size:12px;color:#64748b;margin-bottom:20px;">کد فعال‌سازی را وارد کنید</div>
      <div style="font-size:12px;color:#94a3b8;margin-bottom:12px;font-family:monospace;">
        <?= e($screen['code'] ?? '') ?>
      </div>
      <input type="text" id="act-code" placeholder="______" maxlength="6" autocomplete="off">
      <button id="act-btn" onclick="doActivate()">فعال‌سازی</button>
      <div id="act-err"></div>
      <div id="act-url">آدرس: <span id="act-server"></span></div>
    </div>
  </div>

  <?php else: ?>

  <!-- Loading -->
  <div id="loading">
    <div><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
    <div style="color:#475569;font-size:14px;">در حال بارگذاری...</div>
  </div>

  <!-- Slides -->
  <div id="slides"></div>

  <!-- Logo -->
  <div id="logo">
    <?php if ($logoUrl): ?>
    <img src="<?= e($logoUrl) ?>" alt="">
    <?php endif; ?>
  </div>

  <!-- Clock -->
  <div id="clock">00:00</div>

  <!-- Ticker -->
  <div id="ticker">
    <div id="ticker-track"><?= e($tickerText) ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?= e($tickerText) ?></div>
  </div>

  <?php endif; ?>
</div>

<script>
var SCREEN_CODE  = '<?= e($screen['code'] ?? '') ?>';
var SCREEN_TYPE  = '<?= e($screen['screen_type'] ?? 'signage') ?>';
var IPTV_MENU_ID = <?= (int)($screen['iptv_menu_id'] ?? 0) ?>;
var SERVER = window.location.origin;
var playlist = [], idx = 0, slideTimer = null;
var currentSlide = null, nextSlide = null;

// ─── Clock ──────────────────────────────────────────────────────
<?php if ($showClock): ?>
var _serverOffset = 0; // تفاوت زمان client و server (milliseconds)

function syncServerTime() {
  var xhr = new XMLHttpRequest();
  xhr.open('GET', SERVER + '/api/v1/time', true);
  xhr.timeout = 5000;
  xhr.onload = function() {
    try {
      var d = JSON.parse(xhr.responseText);
      if (d.success) {
        _serverOffset = (d.timestamp * 1000) - Date.now();
      }
    } catch(e) {}
  };
  xhr.send();
}

function serverNow() {
  return new Date(Date.now() + _serverOffset);
}

function updateClock() {
  var d = serverNow();
  var h = d.getHours(), m = d.getMinutes(), s = d.getSeconds();
  var el = document.getElementById('clock');
  if (el) el.textContent = (h<10?'0':'')+h+':'+(m<10?'0':'')+m+':'+(s<10?'0':'')+s;
}

syncServerTime();                    // sync اول
setInterval(syncServerTime, 300000); // هر ۵ دقیقه دوباره sync
setInterval(updateClock, 1000);
updateClock();
<?php endif; ?>

// ─── Ticker scroll ───────────────────────────────────────────────
<?php if ($tickerText): ?>
(function() {
  var track = document.getElementById('ticker-track');
  var pos = window.innerWidth || 1280;
  track.style.left = pos + 'px';
  setInterval(function() {
    pos -= 1.5;
    if (pos < -track.offsetWidth / 2) pos = window.innerWidth;
    track.style.left = pos + 'px';
  }, 16);
})();
<?php endif; ?>

// ─── Playlist load ───────────────────────────────────────────────
function loadPlaylist() {
  var xhr = new XMLHttpRequest();
  xhr.open('GET', SERVER + '/api/v1/screens/' + SCREEN_CODE + '/playlist', true);
  xhr.timeout = 10000;
  xhr.onload = function() {
    if (xhr.status === 200) {
      try {
        var d = JSON.parse(xhr.responseText);
        if (d.success && d.data && d.data.items && d.data.items.length > 0) {
          playlist = d.data.items;
          document.getElementById('loading').style.display = 'none';
          playIdx(0);
        } else {
          showNoContent();
        }
      } catch(e) { showNoContent(); }
    } else {
      showNoContent();
    }
  };
  xhr.ontimeout = xhr.onerror = function() {
    setTimeout(loadPlaylist, 15000);
  };
  xhr.send();
}

function showNoContent() {
  var ld = document.getElementById('loading');
  ld.innerHTML = '<div style="color:#1e2a3a;text-align:center;">' +
    '<div style="font-size:64px;margin-bottom:16px;">📺</div>' +
    '<div style="color:#2d3748;font-size:16px;">منتظر محتوا...</div>' +
    '</div>';
  setTimeout(loadPlaylist, 30000);
}

// ─── Play ─────────────────────────────────────────────────────────
function playIdx(i) {
  if (!playlist.length) return;
  clearTimeout(slideTimer);

  var item = playlist[i];
  var rawSrc = item.file_url || item.src || item.file_path || item.url || '';
  // اگه localhost بود با IP واقعی سرور جایگزین کن
  var src = rawSrc.replace(/https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/g, SERVER);
  var type = item.type || 'image';
  var dur  = ((item.duration || 10) * 1000);

  // cleanup
  var old = document.getElementById('slide-current');
  if (old) { old.id = 'slide-old'; setTimeout(function() { if(old.parentNode) old.parentNode.removeChild(old); }, 600); }

  var slides = document.getElementById('slides');
  var div = document.createElement('div');
  div.className = 'slide';
  div.id = 'slide-current';

  if (type === 'image') {
    var img = new Image();
    img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';
    img.onerror = function() { nextItem(); };
    img.onload  = function() {
      div.appendChild(img);
      slides.appendChild(div);
      requestAnimationFrame(function() { div.classList.add('show'); });
    };
    img.src = src;
    slideTimer = setTimeout(nextItem, dur);

  } else if (type === 'video') {
    var vid = document.createElement('video');
    vid.src = src;
    vid.autoplay = true;
    vid.muted = true;
    vid.setAttribute('muted', '');          // Android TV نیاز داره
    vid.setAttribute('playsinline', '');    // iOS/Android
    vid.setAttribute('webkit-playsinline', ''); // قدیمی
    vid.controls = false;
    vid.loop = false;
    vid.preload = 'auto';
    vid.style.cssText = 'width:100%;height:100%;display:block;background:#000;';
    // نمایش فوری بدون oncanplay
    div.appendChild(vid);
    slides.appendChild(div);
    requestAnimationFrame(function(){ div.classList.add('show'); });
    // شروع پخش
    var playPromise = vid.play();
    if (playPromise !== undefined) {
      playPromise.catch(function() {
        // autoplay block شد - کلیک simulate
        document.addEventListener('click', function once() {
          vid.play();
          document.removeEventListener('click', once);
        }, { once: true });
      });
    }
    vid.onended = nextItem;
    vid.onerror = function() { setTimeout(nextItem, 1000); };
    if (dur > 0 && dur < 3600000) slideTimer = setTimeout(nextItem, dur);

  } else {
    // url / module / iframe
    var iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.style.cssText = 'width:100%;height:100%;border:0;background:#000;';
    iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin');
    div.appendChild(iframe);
    slides.appendChild(div);
    requestAnimationFrame(function() { div.classList.add('show'); });
    slideTimer = setTimeout(nextItem, dur);
  }
}

function nextItem() {
  idx = (idx + 1) % playlist.length;
  playIdx(idx);
}

// ─── Heartbeat ─────────────────────────────────────────────────────
function heartbeat() {
  var xhr = new XMLHttpRequest();
  xhr.open('POST', SERVER + '/api/v1/screens/' + SCREEN_CODE + '/heartbeat', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.timeout = 8000;
  xhr.onload = function() {
    try {
      var d = JSON.parse(xhr.responseText);
      var cmds = (d.data && d.data.commands) ? d.data.commands : [];
      for (var ci = 0; ci < cmds.length; ci++) {
        var cmd = cmds[ci];
        if (cmd.command === 'reload') loadPlaylist();
        if (cmd.command === 'reboot') window.location.reload();
        if (cmd.command === 'instant_media' && cmd.data) showInstant(cmd.data);
        if (cmd.command === 'clear_instant') clearInstant();
      }
    } catch(e) {}
  };
  xhr.send(JSON.stringify({ version: 'android-tv', item: idx }));
}
setInterval(heartbeat, 15000);

// ─── Instant broadcast ─────────────────────────────────────────────
var instantTimer = null;
function showInstant(data) {
  clearInstant();
  var overlay = document.createElement('div');
  overlay.id = 'instant';
  overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:#000;display:flex;align-items:center;justify-content:center;';

  if (data.type === 'image') {
    overlay.innerHTML = '<img src="'+data.content+'" style="max-width:100%;max-height:100%;object-fit:contain;">';
  } else if (data.type === 'video') {
    overlay.innerHTML = '<video src="'+data.content+'" autoplay muted playsinline style="max-width:100%;max-height:100%;"></video>';
  } else if (data.type === 'text') {
    var t = {}; try { t = JSON.parse(data.content); } catch(e) { t = {text:data.content,color:'#fff',bg:'#000'}; }
    overlay.style.background = t.bg || '#000';
    overlay.innerHTML = '<div style="font-size:clamp(28px,6vw,80px);font-weight:900;color:'+(t.color||'#fff')+';text-align:center;padding:40px;">'+t.text+'</div>';
  }

  document.body.appendChild(overlay);
  var dur = parseInt(data.duration) || 30;
  if (dur > 0) instantTimer = setTimeout(clearInstant, dur * 1000);
}

function clearInstant() {
  clearTimeout(instantTimer);
  var el = document.getElementById('instant');
  if (el) el.parentNode.removeChild(el);
}

// ─── Activation ──────────────────────────────────────────────────
function doActivate() {
  var code = document.getElementById('act-code').value.trim().toUpperCase();
  var err = document.getElementById('act-err');
  if (!code || code.length !== 6) { err.textContent = 'کد ۶ کاراکتر وارد کنید'; return; }
  err.style.color = '#94a3b8';
  err.textContent = 'در حال اتصال...';

  var xhr = new XMLHttpRequest();
  xhr.open('POST', SERVER + '/player/activate', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.timeout = 10000;
  xhr.onload = function() {
    var d = {}; try { d = JSON.parse(xhr.responseText); } catch(e) {}
    if (d.success) {
      err.style.color = '#22c55e';
      err.textContent = '✅ موفق! در حال بارگذاری...';
      setTimeout(function() { window.location.reload(); }, 1200);
    } else {
      err.style.color = '#ef4444';
      err.textContent = d.message || 'کد نامعتبر است';
    }
  };
  xhr.ontimeout = xhr.onerror = function() {
    err.style.color = '#f59e0b';
    err.textContent = 'خطا در اتصال — ' + SERVER;
  };
  xhr.send(JSON.stringify({ activation_code: code, screen_code: SCREEN_CODE }));
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    var act = document.getElementById('act');
    if (act && act.style.display !== 'none') doActivate();
  }
});

// server URL display
var su = document.getElementById('act-server');
if (su) su.textContent = SERVER;

// ─── Unlock autoplay on first interaction ────────────────────────
// Android TV ممکنه autoplay رو block کنه تا interaction اول
var _unlocked = false;
function unlockAutoplay() {
  if (_unlocked) return;
  _unlocked = true;
  var slide = document.getElementById('slide-current');
  if (slide) {
    var v = slide.querySelector('video');
    if (v) v.play().catch(function(){});
  }
}
document.addEventListener('click', unlockAutoplay);
document.addEventListener('keydown', unlockAutoplay);
// TV remote OK button
document.addEventListener('keyup', function(e) {
  if (e.keyCode === 13 || e.keyCode === 32) unlockAutoplay();
});

// ─── Start ───────────────────────────────────────────────────────
<?php if (($screen['status'] ?? '') === 'active'): ?>
loadPlaylist();
heartbeat();
<?php endif; ?>
</script>
</body>
</html>
