<?php
/**
 * SignageCMS — Monitor 3D Player Profile
 * برای نمایشگرهای تبلیغاتی سه‌بعدی فضای باز (LED Fan Hologram / Glasses-free 3D)
 */
$settings   = json_decode($screen['settings'] ?? '{}', true) ?: [];
$cfg3d      = $screen['cfg_3d'] ?? [];

$format3d   = $cfg3d['format_3d']          ?? 'normal';
$depthLevel = (int)($cfg3d['depth_level']  ?? 5);
$depthColor = $cfg3d['depth_color']        ?? '#00e5ff';
$bgColor    = $cfg3d['bg_color']           ?? '#000000';
$parallax   = (int)($cfg3d['parallax_intensity'] ?? 6);
$showBadge  = !empty($cfg3d['show_depth_badge']);
$isOutdoor  = !empty($cfg3d['is_outdoor']);
$autoRotate = !empty($cfg3d['auto_rotate']);
$rotateSpd  = (int)($cfg3d['rotate_speed'] ?? 5);

// محاسبه شدت parallax از 1-10 به px
$parallaxPx = $parallax * 8;   // 8px–80px

$depthLevels = [
    1 => '0.5s', 2 => '1s', 3 => '1.5s', 4 => '2s', 5 => '2.5s',
    6 => '3s',   7 => '3.5s', 8 => '4s', 9 => '4.5s', 10 => '5s',
];
$floatDuration = $depthLevels[$depthLevel] ?? '2.5s';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" style="margin:0;padding:0;overflow:hidden;background:<?= e($bgColor) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>3D Monitor — <?= e($screen['name'] ?? 'SignageCMS') ?></title>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"></script>
<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
  background:<?= e($bgColor) ?>;
  color:#fff;
  font-family:sans-serif;
  overflow:hidden;
  width:100vw;
  height:100vh;
  user-select:none;
}

/* ─── Scene wrapper ──────────────────────────────────────────────── */
#player-wrap {
  width:100%; height:100%;
  position:relative;
  background:<?= e($bgColor) ?>;
  display:flex; align-items:center; justify-content:center;
  perspective:1200px;
}

/* ─── 3D Stage ───────────────────────────────────────────────────── */
#stage-3d {
  position:relative;
  width:100%; height:100%;
  display:flex; align-items:center; justify-content:center;
  transform-style:preserve-3d;
}

/* ─── Media container ────────────────────────────────────────────── */
#media-container {
  width:100%; height:100%;
  position:relative;
  display:flex; align-items:center; justify-content:center;
  overflow:hidden;
  transform-style:preserve-3d;
}

/* ─── Media items ────────────────────────────────────────────────── */
.media-item {
  position:absolute;
  inset:0;
  display:flex;
  align-items:center;
  justify-content:center;
  opacity:0;
  transition:opacity 1s ease;
  background:<?= e($bgColor) ?>;
}
.media-item.active { opacity:1; z-index:2; }

/* Normal mode: float depth animation */
<?php if ($format3d === 'normal' || $format3d === 'hologram'): ?>
.media-item.active img,
.media-item.active video {
  animation:
    depth3dFloat <?= $floatDuration ?> ease-in-out infinite alternate,
    <?php if ($autoRotate): ?>
    depth3dRotate <?= (int)(60 / max(1, $rotateSpd)) ?>s linear infinite,
    <?php endif; ?>
    depth3dScale 6s ease-in-out infinite alternate;
}

@keyframes depth3dFloat {
  0%   { transform: translateZ(0px) translateY(0px); filter: drop-shadow(0 0 0 transparent); }
  100% { transform: translateZ(<?= $parallaxPx ?>px) translateY(-<?= (int)($parallaxPx * 0.15) ?>px);
         filter: drop-shadow(0 <?= (int)($parallaxPx * 0.3) ?>px <?= (int)($parallaxPx * 0.8) ?>px <?= e($depthColor) ?>88); }
}

@keyframes depth3dScale {
  0%   { transform: scale(0.97); }
  100% { transform: scale(1.03); }
}

<?php if ($autoRotate): ?>
@keyframes depth3dRotate {
  from { transform: rotateY(-5deg); }
  to   { transform: rotateY(5deg); }
}
<?php endif; ?>
<?php endif; ?>

/* SBS mode: Side-by-Side stereoscopic */
<?php if ($format3d === 'sbs'): ?>
.media-item.active img,
.media-item.active video {
  width:50%; height:100%; object-fit:cover;
  /* نمایش فقط نیمه چپ (eye L) */
  object-position:left center;
}
.sbs-label {
  position:absolute; top:10px; left:10px;
  background:rgba(0,229,255,0.2); border:1px solid <?= e($depthColor) ?>;
  color:<?= e($depthColor) ?>; padding:4px 12px; border-radius:20px;
  font-size:11px; font-weight:700; letter-spacing:2px;
}
<?php endif; ?>

/* Anaglyphic: Red-Cyan overlay */
<?php if ($format3d === 'anaglyphic'): ?>
#media-container::after {
  content:'';
  position:absolute;
  inset:0;
  background:linear-gradient(90deg, rgba(255,0,0,0.05) 50%, rgba(0,255,255,0.05) 50%);
  pointer-events:none;
  z-index:10;
  animation:anaglyphShift 0.1s steps(1) infinite;
}
@keyframes anaglyphShift {
  0%  { background:linear-gradient(90deg, rgba(255,0,0,0.04) 50%, rgba(0,255,255,0.04) 50%); }
  50% { background:linear-gradient(90deg, rgba(0,255,255,0.04) 50%, rgba(255,0,0,0.04) 50%); }
}
.media-item.active img,
.media-item.active video {
  filter: drop-shadow(-2px 0 2px rgba(255,0,0,0.3)) drop-shadow(2px 0 2px rgba(0,255,255,0.3));
}
<?php endif; ?>

/* Hologram: extra glow effect */
<?php if ($format3d === 'hologram'): ?>
.media-item.active img,
.media-item.active video {
  filter:
    drop-shadow(0 0 6px <?= e($depthColor) ?>)
    drop-shadow(0 0 20px <?= e($depthColor) ?>88)
    drop-shadow(0 0 60px <?= e($depthColor) ?>44)
    brightness(1.2) saturate(1.4);
}

/* Scan lines overlay */
#media-container::before {
  content:'';
  position:absolute;
  inset:0;
  background:repeating-linear-gradient(
    0deg,
    transparent,
    transparent 2px,
    rgba(0,229,255,0.025) 2px,
    rgba(0,229,255,0.025) 4px
  );
  pointer-events:none;
  z-index:5;
  animation:scanMove 8s linear infinite;
}
@keyframes scanMove {
  from { background-position: 0 0; }
  to   { background-position: 0 100px; }
}

/* Hologram flicker */
#stage-3d {
  animation: holoFlicker 6s ease-in-out infinite;
}
@keyframes holoFlicker {
  0%, 90%, 100% { opacity:1; }
  92%            { opacity:0.95; }
  94%            { opacity:0.98; }
  96%            { opacity:0.92; }
  98%            { opacity:1; }
}
<?php endif; ?>

/* ─── Media fit ──────────────────────────────────────────────────── */
.media-item img   { max-width:100%; max-height:100%; object-fit:contain; }
.media-item video { width:100%; height:100%; object-fit:contain; }
.media-item iframe { width:100%; height:100%; border:none; }

/* ─── 3D Badge ───────────────────────────────────────────────────── */
#badge-3d {
  position:fixed;
  top:16px;
  right:16px;
  z-index:50;
  display:<?= $showBadge ? 'flex' : 'none' ?>;
  align-items:center;
  gap:6px;
  background:rgba(0,0,0,0.6);
  border:1px solid <?= e($depthColor) ?>;
  border-radius:20px;
  padding:6px 14px;
  font-size:11px;
  font-weight:700;
  color:<?= e($depthColor) ?>;
  letter-spacing:1px;
  text-transform:uppercase;
  animation:badgePulse 3s ease-in-out infinite;
}
@keyframes badgePulse {
  0%, 100% { box-shadow:0 0 4px <?= e($depthColor) ?>40; }
  50%       { box-shadow:0 0 16px <?= e($depthColor) ?>80; }
}

/* ─── Corner glow ────────────────────────────────────────────────── */
.corner-glow {
  position:fixed;
  width:200px; height:200px;
  border-radius:50%;
  pointer-events:none;
  z-index:1;
  opacity:0.12;
  background:radial-gradient(circle, <?= e($depthColor) ?> 0%, transparent 70%);
  animation:cornerPulse 4s ease-in-out infinite alternate;
}
.corner-glow.tl { top:-80px; right:-80px; }
.corner-glow.br { bottom:-80px; left:-80px; }
@keyframes cornerPulse {
  from { opacity:0.08; transform:scale(0.9); }
  to   { opacity:0.18; transform:scale(1.1); }
}

/* ─── Depth grid (outdoor mode) ─────────────────────────────────── */
<?php if ($isOutdoor): ?>
body::before {
  content:'';
  position:fixed;
  inset:0;
  background-image:
    linear-gradient(<?= e($depthColor) ?>08 1px, transparent 1px),
    linear-gradient(90deg, <?= e($depthColor) ?>08 1px, transparent 1px);
  background-size:60px 60px;
  pointer-events:none;
  z-index:0;
}
<?php endif; ?>

/* ─── Activation / Offline shared ───────────────────────────────── */
#activation-screen, #offline-screen {
  position:fixed; inset:0; z-index:200;
  display:flex; flex-direction:column;
  align-items:center; justify-content:center;
  background:<?= e($bgColor) ?>;
}
#activation-screen { gap:24px; }
.activate-card {
  background:#111118; border:1px solid <?= e($depthColor) ?>44;
  border-radius:20px; padding:40px; width:340px; text-align:center;
  box-shadow:0 0 40px <?= e($depthColor) ?>22;
}
.code-input {
  background:#0d0d14; border:2px solid <?= e($depthColor) ?>44;
  border-radius:12px; padding:16px; color:#fff;
  font-size:24px; text-align:center; width:100%;
  font-family:monospace; letter-spacing:8px; text-transform:uppercase; outline:none;
}
.code-input:focus { border-color:<?= e($depthColor) ?>; }
.activate-btn {
  width:100%;
  background:linear-gradient(135deg, <?= e($depthColor) ?>, <?= e($depthColor) ?>88);
  color:#000; border:none; border-radius:10px; padding:14px;
  font-size:15px; font-weight:700; cursor:pointer; margin-top:16px;
  font-family:sans-serif;
}
#error-msg { color:#ef4444; font-size:13px; margin-top:12px; min-height:20px; }

/* ─── Instant broadcast overlay ─────────────────────────────────── */
#instant-overlay {
  position:fixed; inset:0; z-index:99999;
  background:<?= e($bgColor) ?>;
  display:none; align-items:center; justify-content:center;
}
#instant-overlay.show { display:flex; animation:fadeIn3d 0.5s ease; }
@keyframes fadeIn3d { from{opacity:0;transform:scale(0.95)} to{opacity:1;transform:scale(1)} }
</style>
</head>
<body data-screen-code="<?= e($screen['code'] ?? '') ?>">

<!-- corner glows -->
<div class="corner-glow tl"></div>
<div class="corner-glow br"></div>

<!-- 3D badge -->
<div id="badge-3d">
  <span>⬡</span>
  <span>3D <?= strtoupper(str_replace('_',' ', $format3d)) ?></span>
</div>

<div id="player-wrap">
<div id="stage-3d">
<div id="media-container">

<?php if (($screen['status'] ?? '') !== 'active'): ?>
<!-- ─── Activation Screen ─── -->
<div id="activation-screen">
  <div class="activate-card">
    <div style="font-size:48px;margin-bottom:16px;">⬡</div>
    <h2 style="font-size:20px;font-weight:800;margin-bottom:6px;color:<?= e($depthColor) ?>">3D Monitor</h2>
    <p style="color:#64748b;font-size:13px;margin-bottom:20px;">SignageCMS — کد فعال‌سازی</p>
    <p style="color:#94a3b8;font-size:12px;margin-bottom:8px;">کد صفحه: <strong style="color:<?= e($depthColor) ?>;font-family:monospace;"><?= e($screen['code'] ?? '—') ?></strong></p>
    <input type="text" id="actCode" class="code-input" maxlength="6" placeholder="______"
      oninput="this.value=this.value.toUpperCase()" onkeydown="if(event.key==='Enter')doActivate()">
    <button class="activate-btn" onclick="doActivate()">فعال‌سازی</button>
    <div id="error-msg"></div>
  </div>
</div>

<?php else: ?>
<!-- ─── Active Player ─── -->
<div class="media-item active" id="loading-item"
     style="flex-direction:column;gap:20px;">
  <div style="font-size:72px;animation:depth3dFloat 2s ease-in-out infinite alternate;">⬡</div>
  <div style="font-size:16px;color:<?= e($depthColor) ?>;letter-spacing:2px;">در حال بارگذاری…</div>
</div>
<?php endif; ?>

</div><!-- /media-container -->
</div><!-- /stage-3d -->
</div><!-- /player-wrap -->

<!-- Instant Broadcast -->
<div id="instant-overlay">
  <div id="instant-content" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;"></div>
</div>

<!-- Offline -->
<div id="offline-screen" style="display:none;">
  <div style="font-size:64px;opacity:0.2;margin-bottom:16px;">⬡</div>
  <div style="font-size:18px;color:<?= e($depthColor) ?>80;font-weight:700;">اتصال قطع شده</div>
  <div style="font-size:12px;color:#475569;margin-top:8px;">در حال تلاش مجدد…</div>
</div>

<script>
'use strict';

const SCREEN_CODE = '<?= e($screen['code'] ?? '') ?>';
const SERVER_URL  = window.location.origin;
const WS_PORT     = <?= (int)env('WS_PORT', 8080) ?>;
const FORMAT_3D   = '<?= e($format3d) ?>';
const DEPTH_COLOR = '<?= e($depthColor) ?>';

let playlist    = [];
let currentIdx  = 0;
let playTimer   = null;
let hlsInstances = {};

// ─── Media type detection ────────────────────────────────────────────
function detectType(item) {
  const src  = item.file_url || item.src || item.file_path || '';
  const type = item.type || item.media_type || '';
  if (type === 'url' || (!type && src.startsWith('http') && !src.match(/\.(jpg|jpeg|png|gif|webp|mp4|webm|m3u8)(\?|$)/i))) return 'webpage';
  if (src.match(/\.m3u8(\?|$)/i)) return 'hls';
  if (type === 'video' || src.match(/\.(mp4|webm|ogv|mov)(\?|$)/i)) return 'video';
  return 'image';
}

// ─── Render item ─────────────────────────────────────────────────────
function renderItem(item) {
  const src  = item.file_url || item.src || item.url || '';
  const type = detectType(item);
  const div  = document.createElement('div');
  div.className = 'media-item';

  // SBS label
  if (FORMAT_3D === 'sbs') {
    const lbl = document.createElement('div');
    lbl.className = 'sbs-label';
    lbl.textContent = 'SBS 3D';
    div.appendChild(lbl);
  }

  if (type === 'image') {
    const img = document.createElement('img');
    img.src = src;
    img.onerror = () => nextItem();
    div.appendChild(img);

  } else if (type === 'video') {
    const vid       = document.createElement('video');
    vid.src         = src;
    vid.autoplay    = true;
    vid.muted       = true;
    vid.loop        = false;
    vid.playsInline = true;
    vid.preload     = 'auto';
    vid.style.cssText = 'width:100%;height:100%;object-fit:contain;';
    vid.onended = () => nextItem();
    vid.onerror = () => nextItem();
    div.appendChild(vid);

  } else if (type === 'hls') {
    const vid = document.createElement('video');
    vid.autoplay = true; vid.muted = true; vid.playsInline = true;
    vid.style.cssText = 'width:100%;height:100%;object-fit:contain;';
    div.appendChild(vid);
    if (Hls.isSupported()) {
      const hls = new Hls({ enableWorker:true, lowLatencyMode:true });
      hls.loadSource(src);
      hls.attachMedia(vid);
      hls.on(Hls.Events.ERROR, (e, d) => { if (d.fatal) { hls.destroy(); nextItem(); } });
      hlsInstances[src] = hls;
    } else if (vid.canPlayType('application/vnd.apple.mpegurl')) {
      vid.src = src;
    } else { nextItem(); }

  } else {
    const iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.style.cssText = 'width:100%;height:100%;border:none;';
    div.appendChild(iframe);
  }

  return div;
}

// ─── Playlist ────────────────────────────────────────────────────────
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
  Object.values(hlsInstances).forEach(h => h.destroy());
  hlsInstances = {};

  const item      = playlist[idx];
  const container = document.getElementById('media-container');
  const duration  = (item.duration || 10) * 1000;
  const mtype     = detectType(item);

  container.querySelectorAll('.media-item:not(#loading-item)').forEach(e => e.remove());
  const li = document.getElementById('loading-item');
  if (li) li.style.display = 'none';

  const el = renderItem(item);
  el.classList.add('active');
  container.appendChild(el);

  if (mtype === 'video') {
    const vid = el.querySelector('video');
    if (vid) {
      vid.addEventListener('ended', () => nextItem(), { once: true });
      if (duration > 0 && duration < 3600000) playTimer = setTimeout(() => nextItem(), duration);
    } else {
      playTimer = setTimeout(() => nextItem(), duration);
    }
  } else if (mtype === 'hls') {
    if (duration > 0) playTimer = setTimeout(() => nextItem(), duration);
  } else {
    playTimer = setTimeout(() => nextItem(), duration);
  }
}

function nextItem() {
  currentIdx = (currentIdx + 1) % playlist.length;
  playItem(currentIdx);
}

function showNoContent() {
  const c = document.getElementById('media-container');
  c.innerHTML = `<div class="media-item active" style="flex-direction:column;gap:20px;text-align:center;">
    <div style="font-size:80px;opacity:0.15;">⬡</div>
    <div style="font-size:16px;color:${DEPTH_COLOR}80;letter-spacing:1px;">هیچ محتوایی یافت نشد</div>
    <div style="font-size:12px;color:#334155;">از پنل مدیریت پلی‌لیست تنظیم کنید</div>
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

// ─── Instant broadcast ───────────────────────────────────────────────
const InstantPlayer = {
  timer: null,
  show(data) {
    clearTimeout(this.timer);
    const overlay = document.getElementById('instant-overlay');
    const content = document.getElementById('instant-content');
    const type    = data.type || 'image';
    const src     = data.content || '';
    const dur     = parseInt(data.duration) || 30;
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
      vid.src = src; vid.autoplay = true; vid.muted = false;
      vid.style.cssText = 'width:100%;height:100%;object-fit:contain;';
      vid.onended = () => this.clear();
      content.appendChild(vid);
    }
    if (dur > 0) this.timer = setTimeout(() => this.clear(), dur * 1000);
  },
  clear() {
    clearTimeout(this.timer);
    const overlay = document.getElementById('instant-overlay');
    overlay.style.animation = 'fadeOut3d 0.4s ease forwards';
    setTimeout(() => {
      overlay.classList.remove('show');
      overlay.style.animation = '';
      document.getElementById('instant-content').innerHTML = '';
    }, 400);
  }
};

// ─── Heartbeat ───────────────────────────────────────────────────────
async function heartbeat() {
  try {
    const r = await fetch(`${SERVER_URL}/api/v1/screens/${SCREEN_CODE}/heartbeat`, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({ version:'2.0', current_item:currentIdx }),
    });
    const d = await r.json();
    (d.data?.commands || []).forEach(cmd => {
      if (cmd.command === 'instant_media' && cmd.data) InstantPlayer.show(cmd.data);
      if (cmd.command === 'reload')  loadPlaylist();
      if (cmd.command === 'reboot')  window.location.reload();
    });
  } catch(e) {}
}

// ─── WebSocket ───────────────────────────────────────────────────────
function connectWS() {
  try {
    const ws = new WebSocket(`ws://${window.location.hostname}:${WS_PORT}`);
    ws.onopen = () => ws.send(JSON.stringify({ type:'subscribe', channel:`screen_${SCREEN_CODE}` }));
    ws.onmessage = (e) => {
      try {
        const msg = JSON.parse(e.data);
        if ((msg.type==='broadcast'||msg.type==='instant_media') && msg.data) InstantPlayer.show(msg.data);
        if (msg.type==='reload')  loadPlaylist();
        if (msg.type==='reboot')  window.location.reload();
        if (msg.type==='clear')   InstantPlayer.clear();
      } catch(err) {}
    };
    ws.onclose = () => setTimeout(connectWS, 5000);
    ws.onerror = () => ws.close();
  } catch(e) {}
}

// ─── Activation ──────────────────────────────────────────────────────
window.doActivate = async function() {
  const code = (document.getElementById('actCode')?.value||'').trim().toUpperCase();
  const err  = document.getElementById('error-msg');
  if (!code || code.length !== 6) { err.textContent = 'کد ۶ کاراکتری را وارد کنید'; return; }
  err.style.color = '#94a3b8'; err.textContent = '⏳ در حال اتصال...';
  try {
    const r = await fetch(SERVER_URL + '/player/activate', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({ activation_code:code, screen_code: SCREEN_CODE || '' }),
    });
    let d = {}; try { d = await r.json(); } catch(e){}
    if (d.success) {
      err.style.color = '#22c55e'; err.textContent = '✅ فعال‌سازی موفق';
      setTimeout(() => window.location.reload(), 1200);
    } else {
      err.style.color = '#ef4444'; err.textContent = '❌ ' + (d.message || 'کد نامعتبر');
    }
  } catch(e) {
    err.style.color = '#f59e0b'; err.textContent = '⚠ خطا در اتصال';
  }
};

// ─── Init ────────────────────────────────────────────────────────────
<?php if (($screen['status'] ?? '') === 'active'): ?>
document.addEventListener('DOMContentLoaded', () => {
  loadPlaylist();
  connectWS();
  setInterval(heartbeat, 15000);
  heartbeat();
});
<?php endif; ?>

const s = document.createElement('style');
s.textContent = '@keyframes fadeOut3d{from{opacity:1;transform:scale(1)}to{opacity:0;transform:scale(0.95)}}';
document.head.appendChild(s);
</script>
</body>
</html>
