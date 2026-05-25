<?php
/**
 * SignageCMS Player — Enhanced
 * Features: HLS/RTSP streams, Logo overlay, Subtitles/Ticker, Instant broadcast
 */

// تنظیمات screen از JSON settings
$settings      = json_decode($screen['settings'] ?? '{}', true) ?: [];
$logoUrl       = $settings['logo_url']      ?? '';
$logoPos       = $settings['logo_position'] ?? 'bottom-right';
$logoOpacity   = (float)($settings['logo_opacity']   ?? 0.8);
$logoSize      = (int)($settings['logo_size']        ?? 120);
$tickerText    = $settings['ticker_text']    ?? '';
$tickerEnabled = !empty($tickerText);
$tickerSpeed   = (int)($settings['ticker_speed'] ?? 40);
$tickerBg      = $settings['ticker_bg']      ?? 'rgba(0,0,0,0.7)';
$tickerColor   = $settings['ticker_color']   ?? '#ffffff';
$subtitleText  = $settings['subtitle_text']  ?? '';
$clockEnabled  = !empty($settings['show_clock']);
$clockPos      = $settings['clock_position'] ?? 'top-right';
$clockFmt      = $settings['clock_format']   ?? '24h';

$logoPositions = [
    'top-left'     => 'top:16px;left:16px',
    'top-right'    => 'top:16px;right:16px',
    'bottom-left'  => 'bottom:60px;left:16px',
    'bottom-right' => 'bottom:60px;right:16px',
    'center'       => 'top:50%;left:50%;transform:translate(-50%,-50%)',
];
$logoStyle = $logoPositions[$logoPos] ?? $logoPositions['bottom-right'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" style="margin:0;padding:0;overflow:hidden;background:#000">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SignageCMS Player — <?= e($screen['name'] ?? 'Signage') ?></title>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"></script>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { background:#000; color:#fff; font-family:sans-serif; overflow:hidden; width:100vw; height:100vh; }

/* ─── Player Wrap ─── */
#player-wrap { width:100%; height:100%; position:relative; background:#000; }
#media-container { width:100%; height:100%; position:relative; overflow:hidden; }

/* ─── Media items ─── */
.media-item { position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
              opacity:0; transition:opacity 0.7s ease; background:#000; }
.media-item.active { opacity:1; z-index:2; }
.media-item img   { width:100%; height:100%; object-fit:cover; }
.media-item video { width:100%; height:100%; object-fit:cover; }
.media-item iframe { width:100%; height:100%; border:none; }
.media-item .stream-label { position:absolute; top:12px; left:12px;
  background:rgba(239,68,68,0.9); color:#fff; padding:4px 10px; border-radius:6px;
  font-size:12px; font-weight:700; letter-spacing:1px; }

/* ─── Logo overlay ─── */
#logo-overlay {
  position:absolute; z-index:20; pointer-events:none;
  <?= $logoStyle ?>;
  opacity:<?= $logoOpacity ?>;
  display:<?= $logoUrl ? 'block' : 'none' ?>;
}
#logo-overlay img { width:<?= $logoSize ?>px; max-width:<?= $logoSize ?>px; object-fit:contain; }

/* ─── Ticker ─── */
#ticker-bar {
  position:absolute; bottom:0; left:0; right:0; z-index:25;
  background:<?= e($tickerBg) ?>; overflow:hidden;
  height:42px; display:<?= $tickerEnabled ? 'flex' : 'none' ?>;
  align-items:center;
}
.ticker-inner {
  display:flex; align-items:center; white-space:nowrap;
  animation:tickerScroll linear infinite;
  animation-duration:<?= max(10, (int)(strlen($tickerText) * (100/$tickerSpeed))) ?>s;
}
.ticker-text {
  font-size:18px; font-weight:600; color:<?= e($tickerColor) ?>;
  padding:0 60px; direction:rtl;
}
.ticker-sep { color:rgba(255,255,255,0.3); padding:0 20px; font-size:20px; }
@keyframes tickerScroll {
  0%   { transform: translateX(-50%); }
  100% { transform: translateX(0%); }
}

/* ─── Clock ─── */
#clock-overlay {
  position:absolute; z-index:20; pointer-events:none;
  <?php
  $clockPositions = [
    'top-right'    => 'top:16px;right:16px',
    'top-left'     => 'top:16px;left:16px',
    'bottom-right' => 'bottom:50px;right:16px',
    'bottom-left'  => 'bottom:50px;left:16px',
  ];
  echo $clockPositions[$clockPos] ?? 'top:16px;right:16px';
  ?>;
  display:<?= $clockEnabled ? 'block' : 'none' ?>;
  background:rgba(0,0,0,0.5); border-radius:10px; padding:8px 16px;
  text-align:center; min-width:100px;
}
#clock-time { font-size:28px; font-weight:800; font-family:monospace; color:#fff; }
#clock-date { font-size:12px; color:rgba(255,255,255,0.6); margin-top:2px; }

/* ─── Subtitle ─── */
#subtitle-overlay {
  position:absolute; bottom:<?= $tickerEnabled ? '50px' : '20px' ?>; left:50%; transform:translateX(-50%);
  z-index:22; max-width:80%; text-align:center; pointer-events:none;
}
#subtitle-text {
  background:rgba(0,0,0,0.75); color:#fff; font-size:22px; font-weight:600;
  padding:8px 20px; border-radius:8px; display:none; line-height:1.5;
}

/* ─── Instant broadcast overlay ─── */
#instant-overlay {
  position:fixed; inset:0; z-index:99999; background:#000;
  display:none; align-items:center; justify-content:center;
}
#instant-overlay.show { display:flex; animation:fadeIn 0.4s ease; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }

/* ─── Activation screen ─── */
#activation-screen {
  position:absolute; inset:0; background:#09090f;
  display:flex; flex-direction:column; align-items:center; justify-content:center; z-index:100;
}
.activate-card { background:#111118; border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:40px; width:340px; text-align:center; }
.code-input { background:#0d0d14; border:2px solid rgba(249,115,22,0.3); border-radius:12px; padding:16px;
              color:#fff; font-size:24px; text-align:center; width:100%; font-family:monospace;
              letter-spacing:8px; text-transform:uppercase; outline:none; }
.code-input:focus { border-color:#f97316; }
.activate-btn { width:100%; background:linear-gradient(135deg,#f97316,#c2570b); color:#fff;
                border:none; border-radius:10px; padding:14px; font-size:15px; font-weight:700;
                cursor:pointer; margin-top:16px; font-family:sans-serif; }
#error-msg { color:#ef4444; font-size:13px; margin-top:12px; min-height:20px; }

/* ─── Offline ─── */
#offline-screen {
  position:absolute; inset:0; background:#000;
  display:none; flex-direction:column; align-items:center; justify-content:center; z-index:50;
}

</style>
</head>
<body data-screen-code="<?= e($screen['code'] ?? '') ?>">
<div id="player-wrap">

  <?php if (($screen['status'] ?? '') !== 'active'): ?>
  <!-- ─── Activation Screen ─── -->
  <div id="activation-screen">
    <div class="activate-card">
      <div style="width:60px;height:60px;background:linear-gradient(135deg,#f97316,#c2570b);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
        <span style="font-size:28px">📺</span>
      </div>
      <h2 style="font-size:20px;font-weight:800;margin-bottom:8px;">SignageCMS</h2>
      <p style="color:#64748b;font-size:13px;margin-bottom:24px;">کد فعال‌سازی را وارد کنید</p>
      <p style="color:#94a3b8;font-size:12px;margin-bottom:8px;">کد صفحه: <strong style="color:#f97316;font-family:monospace;"><?= e($screen['code'] ?? '—') ?></strong></p>
      <input type="text" id="actCode" class="code-input" maxlength="6" placeholder="______"
        oninput="this.value=this.value.toUpperCase()" onkeydown="if(event.key==='Enter')doActivate()">
      <button class="activate-btn" onclick="doActivate()">فعال‌سازی صفحه</button>
      <div id="error-msg"></div>
    </div>
  </div>

  <?php else: ?>
  <!-- ─── Active Player ─── -->
  <div id="media-container">
    <div class="media-item active" id="loading-item" style="flex-direction:column;gap:16px;">
      <div style="font-size:60px;">📺</div>
      <div style="font-size:18px;color:#94a3b8;">در حال بارگذاری محتوا...</div>
    </div>
  </div>

  <!-- ─── Logo overlay ─── -->
  <div id="logo-overlay">
    <?php if ($logoUrl): ?>
    <img src="<?= e($logoUrl) ?>" alt="logo" onerror="this.parentNode.style.display='none'">
    <?php endif; ?>
  </div>

  <!-- ─── Clock ─── -->
  <div id="clock-overlay">
    <div id="clock-time">--:--</div>
    <div id="clock-date"></div>
  </div>

  <!-- ─── Ticker Bar ─── -->
  <div id="ticker-bar">
    <?php if ($tickerEnabled): ?>
    <div class="ticker-inner">
      <span class="ticker-text"><?= e($tickerText) ?></span>
      <span class="ticker-sep">◆</span>
      <span class="ticker-text"><?= e($tickerText) ?></span>
      <span class="ticker-sep">◆</span>
    </div>
    <?php endif; ?>
  </div>

  <!-- ─── Subtitle ─── -->
  <div id="subtitle-overlay">
    <div id="subtitle-text"></div>
  </div>

  <!-- ─── Offline screen ─── -->
  <div id="offline-screen">
    <div style="font-size:64px;opacity:0.3;margin-bottom:16px;">📡</div>
    <div style="font-size:20px;color:#ef4444;font-weight:700;">اتصال قطع شده</div>
    <div style="font-size:13px;color:#475569;margin-top:8px;" id="offline-msg">در حال تلاش مجدد...</div>
  </div>
  <?php endif; ?>

  <!-- ─── Instant Broadcast Overlay ─── -->
  <div id="instant-overlay">
    <div id="instant-content" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;"></div>
  </div>

</div><!-- /player-wrap -->

<script>
'use strict';

// ─── Config ─────────────────────────────────────────────────────────────
const SCREEN_CODE  = '<?= e($screen['code'] ?? '') ?>';
const SERVER_URL   = window.location.origin; // auto-detect server
const WS_PORT      = <?= (int)env('WS_PORT', 8080) ?>;
const CLOCK_FMT    = '<?= $clockFmt ?>';
const CLOCK_ON     = <?= $clockEnabled ? 'true' : 'false' ?>;

// ─── State ──────────────────────────────────────────────────────────────
let playlist   = [];
let currentIdx = 0;
let playTimer  = null;
let hlsInstances = {};

// ─── Clock ──────────────────────────────────────────────────────────────
if (CLOCK_ON) {
  let _serverOffset = 0;

  function syncTime() {
    fetch(SERVER_URL + '/api/v1/time')
      .then(r => r.json())
      .then(d => { if (d.success) _serverOffset = d.timestamp * 1000 - Date.now(); })
      .catch(() => {});
  }

  function serverNow() { return new Date(Date.now() + _serverOffset); }

  function updateClock() {
    const now  = serverNow();
    const h    = String(now.getHours()).padStart(2,'0');
    const m    = String(now.getMinutes()).padStart(2,'0');
    const s    = String(now.getSeconds()).padStart(2,'0');
    const time = CLOCK_FMT === '12h'
      ? ((now.getHours() % 12) || 12) + ':' + m + ':' + s + (now.getHours() >= 12 ? ' PM' : ' AM')
      : h + ':' + m + ':' + s;

    const el = document.getElementById('clock-time');
    const de = document.getElementById('clock-date');
    if (el) el.textContent = time;
    if (de) de.textContent = now.toLocaleDateString('fa-IR', {weekday:'long', month:'long', day:'numeric'});
  }

  syncTime();
  setInterval(syncTime, 300000);
  setInterval(updateClock, 1000);
  updateClock();
}

// ─── Media Type Detection ────────────────────────────────────────────────
function detectMediaType(item) {
  const src  = item.file_url || item.src || item.file_path || '';
  const type = item.type || item.media_type || '';
  const mime = item.mime_type || '';

  if (type === 'url' || (!type && src.startsWith('http') && !src.match(/\.(jpg|jpeg|png|gif|webp|mp4|webm|m3u8|ts)(\?|$)/i))) return 'webpage';
  if (src.match(/\.m3u8(\?|$)/i) || mime.includes('mpegurl')) return 'hls';
  if (src.startsWith('rtsp://') || src.startsWith('rtp://')) return 'rtsp';
  if (src.startsWith('rtmp://')) return 'rtmp';
  if (type === 'video' || src.match(/\.(mp4|webm|ogv|mov|avi)(\?|$)/i)) return 'video';
  if (type === 'image' || src.match(/\.(jpg|jpeg|png|gif|webp|svg)(\?|$)/i)) return 'image';
  return 'image';
}

// ─── Render Media Item ───────────────────────────────────────────────────
function renderItem(item) {
  const src       = item.file_url || item.src || item.url || '';
  const mediaType = detectMediaType(item);
  const div       = document.createElement('div');
  div.className   = 'media-item';

  switch (mediaType) {

    case 'image': {
      const img    = document.createElement('img');
      img.src      = src;
      img.alt      = item.media_name || '';
      img.onerror  = () => nextItem();
      div.appendChild(img);
      break;
    }

    case 'video': {
      const vid        = document.createElement('video');
      vid.src          = src;
      vid.autoplay     = true;
      vid.muted        = true;
      vid.loop         = false;
      vid.playsInline  = true;
      vid.preload      = 'auto';
      vid.style.cssText = 'width:100%;height:100%;object-fit:cover;';
      vid.onended      = () => nextItem();
      vid.onerror      = () => nextItem();
      div.appendChild(vid);
      break;
    }

    case 'hls': {
      const vid = document.createElement('video');
      vid.autoplay   = true;
      vid.muted      = true;
      vid.loop       = true;
      vid.playsInline = true;
      vid.style.cssText = 'width:100%;height:100%;object-fit:cover;';
      div.appendChild(vid);

      const lbl = document.createElement('div');
      lbl.className = 'stream-label';
      lbl.textContent = '● LIVE';
      div.appendChild(lbl);

      if (Hls.isSupported()) {
        const hls = new Hls({ enableWorker:true, lowLatencyMode:true });
        hls.loadSource(src);
        hls.attachMedia(vid);
        hls.on(Hls.Events.ERROR, (event, data) => {
          if (data.fatal) { hls.destroy(); nextItem(); }
        });
        hlsInstances[src] = hls;
      } else if (vid.canPlayType('application/vnd.apple.mpegurl')) {
        vid.src = src;
      } else {
        nextItem();
      }
      break;
    }

    case 'rtsp': {
      // RTSP: نیاز به relay دارد — تلاش برای پخش از طریق relay endpoint
      const proxyUrl = SERVER_URL + '/api/v1/stream/proxy?url=' + encodeURIComponent(src);
      const vid = document.createElement('video');
      vid.autoplay   = true;
      vid.muted      = true;
      vid.loop       = true;
      vid.playsInline = true;
      vid.style.cssText = 'width:100%;height:100%;object-fit:cover;';

      const lbl = document.createElement('div');
      lbl.className = 'stream-label';
      lbl.innerHTML = '● RTSP';
      div.appendChild(lbl);

      // تلاش با HLS proxy
      if (Hls.isSupported()) {
        const hls = new Hls();
        hls.loadSource(proxyUrl + '&format=hls');
        hls.attachMedia(vid);
        hls.on(Hls.Events.ERROR, (e, d) => {
          if (d.fatal) {
            lbl.innerHTML = '⚠ RTSP';
            lbl.style.background = 'rgba(245,158,11,0.9)';
          }
        });
      } else {
        vid.src = proxyUrl;
      }
      div.appendChild(vid);

      // fallback: نمایش پیام
      setTimeout(() => {
        if (vid.readyState === 0) {
          div.innerHTML = `
            <div style="text-align:center;color:#94a3b8;">
              <div style="font-size:48px;margin-bottom:16px;">📡</div>
              <div style="font-size:18px;font-weight:700;color:#f97316;">جریان زنده RTSP</div>
              <div style="font-size:13px;margin-top:8px;opacity:0.6;">${src}</div>
              <div style="font-size:11px;margin-top:12px;color:#475569;">نیاز به RTSP relay server</div>
            </div>`;
        }
      }, 5000);
      break;
    }

    case 'module': {
      const iframe = document.createElement('iframe');
      iframe.src           = src;
      iframe.allow         = 'autoplay; fullscreen';
      iframe.style.cssText = 'width:100%;height:100%;border:none;background:#000;';
      div.appendChild(iframe);
      break;
    }

    case 'webpage': {
      const iframe = document.createElement('iframe');
      iframe.src              = src;
      iframe.sandbox          = 'allow-scripts allow-same-origin allow-forms allow-popups';
      iframe.allow            = 'autoplay; fullscreen';
      iframe.style.cssText    = 'width:100%;height:100%;border:none;';
      div.appendChild(iframe);
      break;
    }

    default: {
      const img = document.createElement('img');
      img.src = src;
      img.onerror = () => nextItem();
      div.appendChild(img);
    }
  }

  return div;
}

// ─── Playlist Playback ───────────────────────────────────────────────────
async function loadPlaylist() {
  try {
    const r = await fetch(`${SERVER_URL}/api/v1/screens/${SCREEN_CODE}/playlist`);
    const d = await r.json();

    if (d.success && d.data?.items?.length) {
      playlist = d.data.items;
      playItem(0);
    } else {
      showNoContent();
    }
  } catch(e) {
    showOffline();
    setTimeout(loadPlaylist, 15000);
  }
}

function playItem(idx) {
  if (!playlist.length) return;
  clearTimeout(playTimer);

  // پاک کردن HLS قدیمی
  Object.values(hlsInstances).forEach(h => h.destroy());
  hlsInstances = {};

  const item       = playlist[idx];
  const container  = document.getElementById('media-container');
  const duration   = (item.duration || 10) * 1000;
  const mediaType  = detectMediaType(item);

  // پاک کردن آیتم‌های قبلی
  container.querySelectorAll('.media-item:not(#loading-item)').forEach(e => e.remove());
  document.getElementById('loading-item').style.display = 'none';

  // ساختن آیتم جدید
  const el = renderItem(item);
  el.classList.add('active');
  container.appendChild(el);

  // Subtitle
  updateSubtitle(item.subtitle_text || item.caption || '');

  // زمان‌بندی بعدی — ویدیو: onended، بقیه: timer
  if (mediaType === 'video') {
    const vid = el.querySelector('video');
    if (vid) {
      vid.addEventListener('ended', () => nextItem(), { once: true });
      // اگه ویدیو طولانی‌تر از مدت تنظیم‌شده بود
      if (duration > 0 && duration < 3600000) {
        playTimer = setTimeout(() => nextItem(), duration);
      }
    } else {
      playTimer = setTimeout(() => nextItem(), duration);
    }
  } else if (mediaType === 'hls' || mediaType === 'rtsp') {
    // استریم — تا دستور بعدی نگه دار
    if (duration > 0) playTimer = setTimeout(() => nextItem(), duration);
  } else {
    playTimer = setTimeout(() => nextItem(), duration);
  }
}

function nextItem() {
  currentIdx = (currentIdx + 1) % playlist.length;
  playItem(currentIdx);
}

// ─── Subtitle ────────────────────────────────────────────────────────────
function updateSubtitle(text) {
  const el = document.getElementById('subtitle-text');
  if (!el) return;
  if (text) {
    el.textContent = text;
    el.style.display = 'inline-block';
    setTimeout(() => { el.style.display = 'none'; }, 6000);
  } else {
    el.style.display = 'none';
  }
}

// ─── Show subtitle via API (for dynamic captions) ────────────────────────
window.showCaption = function(text, duration=5000) {
  const el = document.getElementById('subtitle-text');
  if (!el) return;
  el.textContent = text;
  el.style.display = 'inline-block';
  setTimeout(() => { el.style.display = 'none'; }, duration);
};

// ─── Update ticker text ───────────────────────────────────────────────────
window.updateTicker = function(text) {
  const bar = document.getElementById('ticker-bar');
  if (!bar) return;
  if (text) {
    bar.style.display = 'flex';
    bar.innerHTML = `
      <div class="ticker-inner">
        <span class="ticker-text">${text}</span>
        <span class="ticker-sep">◆</span>
        <span class="ticker-text">${text}</span>
        <span class="ticker-sep">◆</span>
      </div>`;
  } else {
    bar.style.display = 'none';
  }
};

// ─── Logo control ─────────────────────────────────────────────────────────
window.updateLogo = function(url, pos, opacity) {
  const el = document.getElementById('logo-overlay');
  if (!el) return;
  if (url) {
    el.style.display = 'block';
    el.style.opacity = opacity || 0.8;
    el.innerHTML = `<img src="${url}" style="width:120px;object-fit:contain;">`;
  } else {
    el.style.display = 'none';
  }
};

// ─── No content / Offline ────────────────────────────────────────────────
function showNoContent() {
  const container = document.getElementById('media-container');
  container.innerHTML = `
    <div class="media-item active" style="flex-direction:column;gap:16px;">
      <div style="font-size:60px;opacity:0.3;">📺</div>
      <div style="font-size:18px;color:#475569;text-align:center;">
        هیچ محتوایی تنظیم نشده<br>
        <span style="font-size:13px;color:#334155;margin-top:8px;display:block;">
          از پنل مدیریت، پلی‌لیست و زمان‌بندی را تنظیم کنید
        </span>
      </div>
      <div style="font-size:12px;color:#1e293b;font-family:monospace;">${SCREEN_CODE}</div>
    </div>`;
  setTimeout(loadPlaylist, 30000);
}

function showOffline() {
  document.getElementById('offline-screen').style.display = 'flex';
  setTimeout(() => {
    document.getElementById('offline-screen').style.display = 'none';
    loadPlaylist();
  }, 10000);
}

// ─── Instant Broadcast ────────────────────────────────────────────────────
const InstantPlayer = {
  timer: null,

  show(data) {
    clearTimeout(this.timer);
    const overlay = document.getElementById('instant-overlay');
    const content = document.getElementById('instant-content');
    const type     = data.type    || 'image';
    const src      = data.content || '';
    const duration = parseInt(data.duration) || 30;

    content.innerHTML = '';
    overlay.classList.add('show');

    if (type === 'image') {
      const img = document.createElement('img');
      img.src = src;
      img.style.cssText = 'max-width:100%;max-height:100%;object-fit:contain;';
      img.onerror = () => this.clear();
      content.appendChild(img);

    } else if (type === 'video') {
      const vid = document.createElement('video');
      vid.src = src; vid.autoplay = true; vid.controls = false;
      vid.muted = false; vid.style.cssText = 'width:100%;height:100%;object-fit:contain;';
      vid.onended = () => this.clear();
      vid.onerror = () => this.clear();
      content.appendChild(vid);

    } else if (type === 'hls') {
      const vid = document.createElement('video');
      vid.autoplay = true; vid.muted = true;
      vid.style.cssText = 'width:100%;height:100%;object-fit:contain;';
      content.appendChild(vid);
      if (Hls.isSupported()) {
        const hls = new Hls();
        hls.loadSource(src);
        hls.attachMedia(vid);
      } else { vid.src = src; }

    } else if (type === 'url') {
      const iframe = document.createElement('iframe');
      iframe.src = src;
      iframe.style.cssText = 'width:100%;height:100%;border:none;';
      content.appendChild(iframe);

    } else if (type === 'text') {
      let txtData = {};
      try { txtData = JSON.parse(src); } catch(e) { txtData = {text:src,color:'#fff',bg:'#000'}; }
      content.style.background = txtData.bg || '#000';
      content.innerHTML = `
        <div style="max-width:80%;text-align:center;">
          <p style="font-size:clamp(32px,7vw,96px);font-weight:900;
                     color:${txtData.color||'#fff'};line-height:1.3;
                     text-shadow:0 4px 40px rgba(0,0,0,0.5);">
            ${txtData.text || ''}
          </p>
        </div>`;
    }

    if (duration > 0) {
      this.timer = setTimeout(() => this.clear(), duration * 1000);
    }
  },

  clear() {
    clearTimeout(this.timer);
    const overlay = document.getElementById('instant-overlay');
    const content = document.getElementById('instant-content');
    overlay.style.animation = 'fadeOut 0.4s ease forwards';
    setTimeout(() => {
      overlay.classList.remove('show');
      overlay.style.animation = '';
      content.innerHTML = '';
      content.style.background = '';
    }, 400);
  }
};

// ─── Heartbeat (هر ۱۵ ثانیه) ─────────────────────────────────────────────
async function heartbeat() {
  try {
    const r = await fetch(`${SERVER_URL}/api/v1/screens/${SCREEN_CODE}/heartbeat`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ version: '2.0', current_item: currentIdx }),
    });
    const d = await r.json();
    const cmds = d.data?.commands || [];

    cmds.forEach(cmd => {
      switch(cmd.command) {
        case 'instant_media':
          if (cmd.data) InstantPlayer.show(cmd.data);
          break;
        case 'reload':
          loadPlaylist();
          break;
        case 'reboot':
          window.location.reload();
          break;
        case 'subtitle':
          showCaption(cmd.data?.text || '', (cmd.data?.duration || 5) * 1000);
          break;
        case 'ticker':
          updateTicker(cmd.data?.text || '');
          break;
        case 'logo':
          updateLogo(cmd.data?.url, cmd.data?.position, cmd.data?.opacity);
          break;
        case 'clear_instant':
          InstantPlayer.clear();
          break;
      }
    });
  } catch(e) {}
}

// ─── WebSocket ────────────────────────────────────────────────────────────
function connectWS() {
  try {
    const ws = new WebSocket(`ws://${window.location.hostname}:${WS_PORT}`);
    ws.onopen = () => {
      ws.send(JSON.stringify({ type: 'subscribe', channel: `screen_${SCREEN_CODE}` }));
    };
    ws.onmessage = (e) => {
      try {
        const msg = JSON.parse(e.data);
        if ((msg.type === 'broadcast' || msg.type === 'instant_media') && msg.data) {
          InstantPlayer.show(msg.data);
        }
        if (msg.type === 'reload')  loadPlaylist();
        if (msg.type === 'reboot')  window.location.reload();
        if (msg.type === 'subtitle') showCaption(msg.data?.text, msg.data?.duration * 1000);
        if (msg.type === 'ticker')  updateTicker(msg.data?.text);
        if (msg.type === 'clear')   InstantPlayer.clear();
      } catch(err) {}
    };
    ws.onclose = () => setTimeout(connectWS, 5000);
    ws.onerror = () => ws.close();
  } catch(e) {}
}

// ─── Activation ───────────────────────────────────────────────────────────
window.doActivate = async function() {
  const code = document.getElementById('actCode')?.value?.trim()?.toUpperCase() || '';
  const err  = document.getElementById('error-msg');
  if (!code || code.length !== 6) { err.textContent = 'کد باید ۶ کاراکتر باشد'; return; }

  err.textContent = 'در حال فعال‌سازی...';
  try {
    const r = await fetch(`${SERVER_URL}/player/activate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ activation_code: code, screen_code: SCREEN_CODE }),
    });
    const d = await r.json();
    if (d.success) {
      err.style.color = '#22c55e';
      err.textContent = '✅ فعال‌سازی موفق';
      setTimeout(() => window.location.reload(), 1000);
    } else {
      err.textContent = d.message || 'کد نامعتبر است';
    }
  } catch(e) {
    err.textContent = 'خطا در اتصال به سرور';
  }
};

// ─── Init ─────────────────────────────────────────────────────────────────
<?php if (($screen['status'] ?? '') === 'active'): ?>
document.addEventListener('DOMContentLoaded', () => {
  loadPlaylist();
  connectWS();
  setInterval(heartbeat, 15000);
  heartbeat();
});
<?php endif; ?>

// fadeOut animation
const s = document.createElement('style');
s.textContent = '@keyframes fadeOut{from{opacity:1}to{opacity:0}}';
document.head.appendChild(s);
</script>
</body>
</html>