<?php
/**
 * SignageCMS — IPTV Player Profile  (v2 — Professional UI)
 * جدا از signage player — تغییرات اینجا به modern.php کاری ندارن
 * Appearance از API منو خوانده می‌شه (نه از screen settings)
 */
$screenCode = $screen['code']        ?? '';
$iptvMenuId = (int)($screen['iptv_menu_id'] ?? 0);
$screenName = htmlspecialchars($screen['name'] ?? 'IPTV', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>IPTV — <?= e($screen['name'] ?? 'IPTV') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;600;700;800;900&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"></script>
<style>
:root {
  --accent:     #ef4444;
  --accent-rgb: 239,68,68;
  --glass-bg:   rgba(255,255,255,0.06);
  --glass-border: rgba(255,255,255,0.10);
  --text:       #ffffff;
  --text-muted: #94a3b8;
  --text-dim:   #475569;
}
*,*::before,*::after { margin:0;padding:0;box-sizing:border-box; }

html,body {
  width:100vw; height:100vh; overflow:hidden;
  background:#09090f;
  font-family:'Vazirmatn',sans-serif;
  color:var(--text);
}

/* ════════════════════════════════════════════════
   BACKGROUND
════════════════════════════════════════════════ */
#bg-layer {
  position:fixed; inset:0; z-index:0;
  background:#09090f;
}
/* تصویر پس‌زمینه — کنترل شده توسط JS */
#bg-img {
  position:absolute; inset:0;
  background-size:cover; background-position:center;
  background-image:radial-gradient(ellipse 80% 60% at 20% 40%,
    rgba(239,68,68,.12) 0%, transparent 60%),
    radial-gradient(ellipse 60% 80% at 80% 80%,
    rgba(239,68,68,.07) 0%, transparent 50%);
  transition:opacity .4s ease;
}
/* dim overlay */
#bg-dim { position:fixed;inset:0;z-index:1;background:rgba(0,0,0,0.55); }
/* grain */
#bg-grain {
  position:fixed;inset:0;z-index:2;pointer-events:none;opacity:.035;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
  background-repeat:repeat; background-size:128px;
}

/* ════════════════════════════════════════════════
   LAYOUT
════════════════════════════════════════════════ */
#iptv-wrap { position:relative;z-index:10;width:100%;height:100%;display:flex;flex-direction:column; }

/* ════════════════════════════════════════════════
   HEADER
════════════════════════════════════════════════ */
#iptv-header {
  display:flex; align-items:flex-start; justify-content:space-between;
  padding:clamp(20px,3.5vw,52px) clamp(20px,4vw,60px) 0;
  flex-shrink:0;
  animation:fadeSlideDown .6s ease both;
}
#welcome-block { flex:1; min-width:0; }
#logo-wrap { display:none; margin-bottom:10px; }
#iptv-logo {
  height:clamp(28px,3.5vw,52px);
  object-fit:contain;
  opacity:.9;
  display:block;
}
#welcome-title {
  font-size:clamp(22px,3.5vw,52px);
  font-weight:900; letter-spacing:-.02em;
  color:#fff;
  line-height:1.15;
  text-shadow:0 2px 24px rgba(0,0,0,.6);
}
#welcome-sub {
  font-size:clamp(11px,1.3vw,18px);
  color:var(--text-muted);
  margin-top:clamp(4px,.5vw,8px);
  font-weight:400;
  display:none;
}

/* ── ساعت ── */
#clock-box {
  flex-shrink:0;
  background:var(--glass-bg);
  border:1px solid var(--glass-border);
  backdrop-filter:blur(16px) saturate(1.2);
  border-radius:clamp(12px,1.5vw,20px);
  padding:clamp(10px,1.2vw,18px) clamp(14px,1.8vw,28px);
  text-align:center; min-width:clamp(100px,12vw,180px);
}
#clock-time {
  font-size:clamp(26px,3.5vw,52px);
  font-weight:900; color:#fff;
  font-variant-numeric:tabular-nums;
  letter-spacing:.04em;
}
#clock-date { font-size:clamp(9px,1vw,13px); color:var(--text-dim); margin-top:4px; }

/* ── divider ── */
#iptv-divider {
  margin:clamp(14px,2vw,28px) clamp(20px,4vw,60px);
  height:1px;
  background:linear-gradient(90deg,
    transparent 0%,
    rgba(var(--accent-rgb),.4) 30%,
    rgba(var(--accent-rgb),.15) 70%,
    transparent 100%);
  flex-shrink:0;
  animation:fadeIn .8s .3s ease both;
}

/* ════════════════════════════════════════════════
   GRID
════════════════════════════════════════════════ */
#iptv-grid-wrap {
  flex:1; overflow:hidden;
  padding:0 clamp(20px,4vw,60px) clamp(10px,1.5vw,20px);
  animation:fadeSlideUp .6s .15s ease both;
}
#iptv-grid {
  display:flex; flex-wrap:wrap;
  gap:clamp(10px,1.3vw,20px);
  align-content:flex-start;
  direction:ltr;
  height:100%;
}

/* ── تایل ── */
.iptv-tile {
  width:clamp(120px,14vw,190px);
  height:clamp(110px,12vw,168px);
  border-radius:clamp(12px,1.4vw,20px);
  background:var(--glass-bg);
  border:1.5px solid var(--glass-border);
  backdrop-filter:blur(12px) saturate(1.1);
  display:flex; flex-direction:column;
  align-items:center; justify-content:center;
  gap:clamp(8px,1vw,14px);
  cursor:pointer;
  transition:transform .2s ease, box-shadow .2s ease,
             background .2s ease, border-color .2s ease;
  flex-shrink:0;
  user-select:none;
  position:relative; overflow:hidden;
}
/* shimmer */
.iptv-tile::after {
  content:''; position:absolute; inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,.06) 0%,transparent 60%);
  pointer-events:none;
}
.iptv-tile:hover { transform:scale(1.04); }
.iptv-tile.focused {
  border-color:var(--tc,var(--accent));
  background:rgba(var(--tc-rgb,var(--accent-rgb)),.14);
  transform:scale(1.07);
  box-shadow:
    0 0 0 2px rgba(var(--tc-rgb,var(--accent-rgb)),.5),
    0 8px 32px rgba(var(--tc-rgb,var(--accent-rgb)),.35),
    0 0 60px rgba(var(--tc-rgb,var(--accent-rgb)),.15);
}
/* pulse on focused */
@keyframes tilePulse {
  0%,100%{ box-shadow:0 0 0 2px rgba(var(--tc-rgb,var(--accent-rgb)),.5), 0 8px 32px rgba(var(--tc-rgb,var(--accent-rgb)),.35); }
  50%    { box-shadow:0 0 0 4px rgba(var(--tc-rgb,var(--accent-rgb)),.3), 0 12px 40px rgba(var(--tc-rgb,var(--accent-rgb)),.45); }
}
.iptv-tile.focused { animation:tilePulse 2s ease-in-out infinite; }

.tile-icon-wrap {
  width:clamp(46px,5vw,72px); height:clamp(46px,5vw,72px);
  border-radius:clamp(12px,1.3vw,18px);
  display:flex; align-items:center; justify-content:center;
  flex-shrink:0;
}
.tile-icon { font-size:clamp(20px,2.4vw,34px); }
.tile-label {
  font-size:clamp(10px,1.1vw,15px);
  font-weight:700; color:#fff;
  text-align:center; padding:0 8px;
  line-height:1.3; letter-spacing:.01em;
}

/* ════════════════════════════════════════════════
   HINTS BAR
════════════════════════════════════════════════ */
#iptv-hints {
  flex-shrink:0;
  display:flex; align-items:center; justify-content:space-between;
  padding:0 clamp(20px,4vw,60px) clamp(10px,1.2vw,16px);
  animation:fadeIn .8s .4s ease both;
}
.hint-key {
  display:inline-flex; align-items:center; justify-content:center;
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.1);
  border-bottom:2px solid rgba(255,255,255,.14);
  border-radius:6px; padding:3px 9px;
  font-size:clamp(9px,0.9vw,12px); color:var(--text-dim);
  font-family:monospace;
}
.hint-text { font-size:clamp(9px,0.9vw,11px); color:#1e293b; margin:0 4px; }

/* ════════════════════════════════════════════════
   TICKER
════════════════════════════════════════════════ */
#iptv-ticker {
  flex-shrink:0;
  background:#000;
  border-top:1px solid rgba(var(--accent-rgb),.2);
  overflow:hidden; height:40px;
  display:none;
  align-items:center;
}
#iptv-ticker .t-dot {
  flex-shrink:0; margin:0 16px;
  width:8px; height:8px; border-radius:50%;
  background:var(--accent);
  box-shadow:0 0 8px rgba(var(--accent-rgb),.8);
  animation:dotPulse 1.2s ease-in-out infinite;
}
@keyframes dotPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.7)} }
.t-track {
  flex:1; overflow:hidden;
  mask-image:linear-gradient(90deg,transparent 0%,#000 5%,#000 95%,transparent 100%);
}
.t-inner {
  display:flex; white-space:nowrap; will-change:transform;
  animation:tickerMove 120s linear infinite;
}
.t-text { font-size:clamp(12px,1.3vw,16px); font-weight:600; color:#fff; padding:0 80px; }
.t-sep  { color:rgba(255,255,255,.2); padding:0 20px; font-size:18px; }
@keyframes tickerMove { 0%{transform:translateX(-50%)} 100%{transform:translateX(0)} }

/* ════════════════════════════════════════════════
   ROOM BADGE
════════════════════════════════════════════════ */
#room-badge {
  position:fixed; bottom:52px; left:clamp(20px,4vw,60px); z-index:20;
  background:var(--glass-bg); border:1px solid var(--glass-border);
  backdrop-filter:blur(12px); border-radius:10px;
  padding:6px 14px; display:none; align-items:center; gap:8px;
}
#room-badge .rb-num { font-size:clamp(11px,1.1vw,15px); font-weight:800; color:#fff; }
#room-badge .rb-lbl { font-size:clamp(9px,.9vw,11px); color:var(--text-dim); }

/* ════════════════════════════════════════════════
   MESSAGE OVERLAY
════════════════════════════════════════════════ */
/* banner — نوار پایین */
#msg-banner {
  position:fixed; bottom:40px; left:0; right:0; z-index:30;
  transform:translateY(120%); transition:transform .4s cubic-bezier(.2,.8,.2,1);
}
#msg-banner.visible { transform:translateY(0); }
#msg-banner .mb-inner {
  margin:0 clamp(20px,4vw,60px) 0;
  padding:14px 20px;
  border-radius:14px;
  display:flex; align-items:center; gap:14px;
  backdrop-filter:blur(20px);
}
/* popup — وسط صفحه */
#msg-popup {
  position:fixed; inset:0; z-index:40;
  display:none; align-items:center; justify-content:center;
  background:rgba(0,0,0,.65); backdrop-filter:blur(4px);
}
#msg-popup.visible { display:flex; }
#msg-popup .mp-card {
  background:#111118; border:1px solid rgba(255,255,255,.12);
  border-radius:20px; padding:clamp(28px,3vw,48px) clamp(24px,3vw,40px);
  max-width:clamp(300px,50vw,560px); width:90%; text-align:center;
  animation:fadeSlideUp .4s ease both;
}
#msg-popup .mp-icon { font-size:clamp(32px,4vw,52px); margin-bottom:16px; display:block; }
#msg-popup .mp-title { font-size:clamp(16px,2vw,26px); font-weight:900; color:#fff; margin-bottom:10px; }
#msg-popup .mp-body  { font-size:clamp(13px,1.4vw,18px); color:var(--text-muted); line-height:1.6; }
#msg-popup .mp-close {
  margin-top:24px; padding:12px 32px;
  background:var(--accent); color:#fff; border:none; border-radius:12px;
  font-size:clamp(12px,1.2vw,15px); font-weight:700; font-family:inherit; cursor:pointer;
  transition:opacity .15s;
}
#msg-popup .mp-close:hover { opacity:.85; }

/* ════════════════════════════════════════════════
   PLAYER OVERLAY (هنگام پخش محتوا)
════════════════════════════════════════════════ */
#iptv-player {
  position:fixed; inset:0; z-index:50;
  background:#000;
  display:none;
}
#iptv-player video,
#iptv-player iframe { width:100%; height:100%; object-fit:cover; border:none; }
#iptv-player.active { display:block; }

/* ── دکمه بازگشت ── */
#back-btn {
  position:fixed; top:20px; left:20px; z-index:60;
  display:none; align-items:center; gap:10px;
  background:rgba(0,0,0,0.8); backdrop-filter:blur(16px);
  border:1px solid rgba(255,255,255,.15);
  color:#fff; padding:12px 20px; border-radius:14px;
  cursor:pointer; font-family:inherit;
  font-size:clamp(12px,1.2vw,15px);
  transition:background .15s;
  animation:fadeIn .3s ease;
}
#back-btn:hover { background:rgba(255,255,255,.1); }
#back-btn.active { display:flex; }

/* ── loading inline ── */
#iptv-loading {
  position:fixed; inset:0; z-index:80;
  background:#09090f;
  display:flex; flex-direction:column;
  align-items:center; justify-content:center; gap:20px;
}
#iptv-loading .spinner {
  width:48px; height:48px;
  border:3px solid rgba(var(--accent-rgb),.2);
  border-top-color:var(--accent);
  border-radius:50%;
  animation:spin .8s linear infinite;
}
@keyframes spin { to{transform:rotate(360deg)} }

/* ════════════════════════════════════════════════
   صفحه فعال‌سازی
════════════════════════════════════════════════ */
#activation-screen {
  position:fixed; inset:0; z-index:200;
  background:radial-gradient(ellipse at center, #111118 0%, #09090f 100%);
  display:flex; align-items:center; justify-content:center;
}
.act-card {
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.1);
  backdrop-filter:blur(20px);
  border-radius:24px; padding:48px 40px;
  width:clamp(300px,90vw,360px); text-align:center;
}
.act-icon {
  width:72px; height:72px; border-radius:20px;
  background:linear-gradient(135deg,var(--accent),color-mix(in srgb,var(--accent) 70%,#000));
  display:flex; align-items:center; justify-content:center;
  margin:0 auto 24px; font-size:34px;
  box-shadow:0 8px 32px rgba(var(--accent-rgb),.4);
}
.act-code {
  background:rgba(0,0,0,.4); border:2px solid rgba(var(--accent-rgb),.3);
  border-radius:14px; padding:16px; color:#fff;
  font-size:clamp(20px,4vw,28px); text-align:center; width:100%;
  font-family:monospace; letter-spacing:10px; text-transform:uppercase; outline:none;
}
.act-code:focus { border-color:var(--accent); }
.act-btn {
  width:100%; margin-top:16px; padding:16px;
  background:linear-gradient(135deg,var(--accent),color-mix(in srgb,var(--accent) 60%,#000));
  color:#fff; border:none; border-radius:12px;
  font-size:16px; font-weight:700; font-family:inherit; cursor:pointer;
  box-shadow:0 4px 20px rgba(var(--accent-rgb),.4);
  transition:transform .15s, box-shadow .15s;
}
.act-btn:hover { transform:translateY(-1px); box-shadow:0 8px 28px rgba(var(--accent-rgb),.5); }

/* ════════════════════════════════════════════════
   ANIMATIONS
════════════════════════════════════════════════ */
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
@keyframes fadeSlideDown { from{opacity:0;transform:translateY(-16px)} to{opacity:1;transform:none} }
@keyframes fadeSlideUp   { from{opacity:0;transform:translateY(16px)}  to{opacity:1;transform:none} }
</style>
</head>
<body>

<!-- ── لایه‌های پس‌زمینه ── -->
<div id="bg-layer"><div id="bg-img"></div></div>
<div id="bg-dim"></div>
<div id="bg-grain"></div>

<?php if (($screen['status'] ?? '') !== 'active'): ?>
<!-- ════════ صفحه فعال‌سازی ════════ -->
<div id="activation-screen">
  <div class="act-card">
    <div class="act-icon">📡</div>
    <h2 style="font-size:22px;font-weight:900;margin-bottom:8px;">SignageCMS IPTV</h2>
    <p style="color:var(--text-muted);font-size:13px;margin-bottom:20px;">کد فعال‌سازی را وارد کنید</p>
    <p style="color:var(--text-dim);font-size:12px;margin-bottom:10px;">
      کد صفحه: <strong style="color:var(--accent);font-family:monospace;"><?= e($screenCode) ?></strong>
    </p>
    <input type="text" id="actCode" class="act-code" maxlength="6" placeholder="_ _ _ _ _ _"
      oninput="this.value=this.value.toUpperCase()" onkeydown="if(event.key==='Enter')doActivate()">
    <button class="act-btn" onclick="doActivate()">
      <i class="fas fa-satellite-dish" style="margin-left:8px;"></i>فعال‌سازی
    </button>
    <div id="err-msg" style="color:#ef4444;font-size:13px;margin-top:14px;min-height:20px;"></div>
  </div>
</div>

<?php else: ?>
<!-- ════════ پلیر اصلی ════════ -->

<!-- Loading -->
<div id="iptv-loading">
  <div class="spinner"></div>
  <div style="font-size:15px;color:var(--text-muted);">در حال بارگذاری منوی IPTV...</div>
</div>

<!-- منوی اصلی -->
<div id="iptv-wrap" style="display:none;">

  <!-- Header -->
  <div id="iptv-header">
    <div id="welcome-block">
      <div id="logo-wrap"><img id="iptv-logo" src="" alt="logo" onerror="this.style.display='none'"></div>
      <div id="welcome-title"><?= $screenName ?></div>
      <div id="welcome-sub"></div>
    </div>
    <div id="clock-box">
      <div id="clock-time">--:--</div>
      <div id="clock-date"></div>
    </div>
  </div>

  <!-- Divider -->
  <div id="iptv-divider"></div>

  <!-- Grid -->
  <div id="iptv-grid-wrap">
    <div id="iptv-grid"></div>
  </div>

  <!-- Hints -->
  <div id="iptv-hints">
    <div style="display:flex;align-items:center;gap:6px;">
      <span class="hint-key">← → ↑ ↓</span>
      <span class="hint-text">ناوبری</span>
      <span class="hint-key" style="margin-right:8px;">OK / Enter</span>
      <span class="hint-text">انتخاب</span>
    </div>
    <span style="font-size:10px;color:#1e293b;font-family:monospace;"><?= e($screenCode) ?></span>
  </div>

  <!-- Ticker — محتوا از JS پر می‌شه -->
  <div id="iptv-ticker">
    <div class="t-dot"></div>
    <div class="t-track">
      <div class="t-inner"></div>
    </div>
  </div>

</div><!-- /iptv-wrap -->

<!-- نشان اتاق -->
<div id="room-badge">
  <i class="fas fa-door-open" style="color:var(--accent);font-size:14px;"></i>
  <div>
    <div class="rb-num" id="rb-number"></div>
    <div class="rb-lbl" id="rb-guest"></div>
  </div>
</div>

<!-- banner پیام -->
<div id="msg-banner">
  <div class="mb-inner" id="msg-banner-inner">
    <span id="mb-icon" style="font-size:22px;flex-shrink:0;"></span>
    <div style="flex:1;min-width:0;">
      <div id="mb-title" style="font-size:clamp(11px,1.1vw,15px);font-weight:800;color:#fff;display:none;"></div>
      <div id="mb-body" style="font-size:clamp(11px,1.1vw,14px);color:var(--text-muted);"></div>
    </div>
    <button onclick="dismissBanner()" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:var(--text-dim);padding:4px 10px;border-radius:7px;cursor:pointer;font-size:11px;font-family:inherit;flex-shrink:0;">
      <i class="fas fa-times"></i>
    </button>
  </div>
</div>

<!-- popup پیام -->
<div id="msg-popup">
  <div class="mp-card">
    <span class="mp-icon" id="mp-icon"></span>
    <div class="mp-title" id="mp-title"></div>
    <div class="mp-body"  id="mp-body"></div>
    <button class="mp-close" onclick="dismissPopup()">متوجه شدم</button>
  </div>
</div>

<!-- پلیر محتوا (فول‌اسکرین) -->
<div id="iptv-player"></div>

<!-- دکمه بازگشت -->
<button id="back-btn" onclick="goBack()">
  <span id="back-icon"></span>
  <span id="back-label"></span>
  <i class="fas fa-chevron-right" style="font-size:11px;color:var(--text-dim);margin-right:6px;"></i>
  <span style="color:var(--text-dim);font-size:11px;">بازگشت</span>
</button>

<?php endif; ?>

<script>
'use strict';

// ─── Constants (PHP → JS) ──────────────────────────────────────
const SCREEN_CODE  = '<?= e($screenCode) ?>';
const IPTV_MENU_ID = <?= $iptvMenuId ?>;
const SCREEN_NAME  = '<?= $screenName ?>';
const SERVER_URL   = window.location.origin;
const WS_PORT      = <?= (int)env('WS_PORT', 8080) ?>;

// ─── State ────────────────────────────────────────────────────
let menuData  = null;
let focusIdx  = 0;
let autoTimer = null;
let hlsInst   = null;

// ─── hex → rgb helper ──────────────────────────────────────────
function hexRgb(h) {
  const r = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(h);
  return r ? `${parseInt(r[1],16)},${parseInt(r[2],16)},${parseInt(r[3],16)}` : '239,68,68';
}

// ══════════════════════════════════════════════════════════════
//  اعمال تنظیمات ظاهری از منو
// ══════════════════════════════════════════════════════════════
function applyAppearance(d) {
  // ── Accent color ──
  const accent = /^#[0-9a-f]{6}$/i.test(d.accent_color||'') ? d.accent_color : '#ef4444';
  const rgb    = hexRgb(accent);
  document.documentElement.style.setProperty('--accent',     accent);
  document.documentElement.style.setProperty('--accent-rgb', rgb);

  // ── Background ──
  const bgImg  = document.getElementById('bg-img');
  const bgDim  = Math.max(0, Math.min(1, parseFloat(d.bg_dim ?? 0.55)));
  const bgBlur = Math.max(0, Math.min(20, parseInt(d.bg_blur ?? 0)));
  document.getElementById('bg-dim').style.background = `rgba(0,0,0,${bgDim})`;
  if (d.bg_image) {
    bgImg.style.backgroundImage    = `url('${esc(d.bg_image)}')`;
    bgImg.style.backgroundSize     = 'cover';
    bgImg.style.backgroundPosition = 'center';
    bgImg.style.filter             = bgBlur > 0 ? `blur(${bgBlur}px)` : '';
    bgImg.style.transform          = bgBlur > 0 ? 'scale(1.08)' : '';
  } else {
    bgImg.style.backgroundImage = `radial-gradient(ellipse 80% 60% at 20% 40%,rgba(${rgb},.12) 0%,transparent 60%),radial-gradient(ellipse 60% 80% at 80% 80%,rgba(${rgb},.07) 0%,transparent 50%)`;
    bgImg.style.filter    = '';
    bgImg.style.transform = '';
  }

  // ── Welcome ──
  document.getElementById('welcome-title').textContent = d.welcome_title || SCREEN_NAME;
  const subEl = document.getElementById('welcome-sub');
  subEl.textContent  = d.welcome_sub || '';
  subEl.style.display = d.welcome_sub ? 'block' : 'none';

  // ── Logo ──
  const logoWrap = document.getElementById('logo-wrap');
  const logoImg  = document.getElementById('iptv-logo');
  if (d.logo_url) {
    logoImg.src         = d.logo_url;
    logoWrap.style.display = 'block';
  } else {
    logoWrap.style.display = 'none';
  }

  // ── Ticker ──
  const ticker = document.getElementById('iptv-ticker');
  if (d.ticker_text) {
    const spd   = Math.max(5, parseInt(d.ticker_speed || 40));
    const secs  = Math.max(10, Math.ceil(d.ticker_text.length * 100 / spd));
    const color = d.ticker_color || '#ffffff';
    const bg    = d.ticker_bg    || '#000000';
    ticker.style.display    = 'flex';
    ticker.style.background = bg;
    const dot = ticker.querySelector('.t-dot');
    if (dot) { dot.style.background = accent; dot.style.boxShadow = `0 0 8px rgba(${rgb},.8)`; }
    const inner = ticker.querySelector('.t-inner');
    inner.innerHTML = `<span class="t-text" style="color:${esc(color)}">${esc(d.ticker_text)}</span><span class="t-sep">◆</span><span class="t-text" style="color:${esc(color)}">${esc(d.ticker_text)}</span><span class="t-sep">◆</span>`;
    inner.style.animationDuration = secs + 's';
  } else {
    ticker.style.display = 'none';
  }
}

// ══════════════════════════════════════════════════════════════
//  بارگذاری منو
// ══════════════════════════════════════════════════════════════
async function loadMenu() {
  if (!IPTV_MENU_ID) { showNoMenu(); return; }
  try {
    const r = await fetch(`${SERVER_URL}/api/v1/player/iptv-menu/${IPTV_MENU_ID}`);
    const d = await r.json();
    if (d.success && d.data?.items?.length) {
      menuData = d.data;
      applyAppearance(menuData);   // ← اعمال ظاهر از تنظیمات منو
      buildGrid();
      showMenu();
    } else {
      showNoMenu();
    }
  } catch(e) {
    console.warn('IPTV menu load failed, retry in 10s');
    setTimeout(loadMenu, 10000);
  }
}

function buildGrid() {
  const grid = document.getElementById('iptv-grid');
  grid.innerHTML = menuData.items.map((item, i) => {
    const rgb = hexRgb(item.color || '#ef4444');
    return `
      <div class="iptv-tile" id="tile-${i}" data-idx="${i}"
           style="--tc:${esc(item.color)};--tc-rgb:${rgb};"
           onclick="selectItem(${i})">
        <div class="tile-icon-wrap"
             style="background:rgba(${rgb},.18);border:2px solid rgba(${rgb},.35);">
          <i class="${esc(item.icon)} tile-icon" style="color:${esc(item.color)};"></i>
        </div>
        <div class="tile-label">${esc(item.label)}</div>
      </div>`;
  }).join('');
}

function showMenu() {
  document.getElementById('iptv-loading').style.display = 'none';
  document.getElementById('iptv-wrap').style.display   = 'flex';
  document.getElementById('iptv-wrap').style.flexDirection = 'column';
  document.getElementById('iptv-wrap').style.height    = '100%';

  focusIdx = 0;
  focusTile(0);
  tickClock();

  // اجرای خودکار اولین آیتم با URL بعد از ۸ ثانیه
  const first = menuData.items.findIndex(i => i.target_url);
  if (first >= 0) autoTimer = setTimeout(() => selectItem(first), 8000);
}

function showNoMenu() {
  document.getElementById('iptv-loading').innerHTML = `
    <div style="font-size:56px;opacity:.2;margin-bottom:8px;">📡</div>
    <div style="font-size:16px;color:var(--text-muted);text-align:center;">
      منویی تنظیم نشده<br>
      <span style="font-size:13px;color:var(--text-dim);margin-top:8px;display:block;">
        از پنل مدیریت صفحه، منوی IPTV را انتخاب کنید
      </span>
    </div>
    <div style="font-size:11px;color:#1e293b;font-family:monospace;margin-top:16px;">${SCREEN_CODE}</div>`;
  setTimeout(loadMenu, 30000);
}

// ══════════════════════════════════════════════════════════════
//  انتخاب آیتم
// ══════════════════════════════════════════════════════════════
function selectItem(idx) {
  clearTimeout(autoTimer);
  const item = menuData?.items?.[idx];
  if (!item) return;
  playContent(item);
}

function playContent(item) {
  // مخفی کردن منو
  document.getElementById('iptv-wrap').style.display = 'none';

  // دکمه بازگشت
  document.getElementById('back-icon').innerHTML =
    `<i class="${esc(item.icon)}" style="color:${esc(item.color)};font-size:16px;"></i>`;
  document.getElementById('back-label').textContent = item.label;
  document.getElementById('back-btn').classList.add('active');

  // ساخت پلیر
  if (hlsInst) { hlsInst.destroy(); hlsInst = null; }
  const playerEl = document.getElementById('iptv-player');
  playerEl.innerHTML = '';
  playerEl.classList.add('active');

  const src = item.target_url || '';

  if (!src) {
    // ماژول داخلی
    buildIframe(`${SERVER_URL}/player/module/${encodeURIComponent(item.type)}`, playerEl);
  } else if (src.match(/\.m3u8(\?|$)/i)) {
    buildHls(src, playerEl);
  } else if (src.startsWith('rtsp://')) {
    playerEl.innerHTML = noSrcMsg('📡', 'RTSP Stream', src);
  } else if (src.match(/\.(mp4|webm|mov)(\?|$)/i)) {
    buildVideo(src, playerEl);
  } else {
    buildIframe(src, playerEl);
  }

  // بازگشت خودکار ۵ دقیقه
  autoTimer = setTimeout(goBack, 300000);
}

function buildHls(src, el) {
  const vid = mkVid();
  el.appendChild(vid);
  if (Hls.isSupported()) {
    hlsInst = new Hls({ enableWorker:true, lowLatencyMode:true });
    hlsInst.loadSource(src);
    hlsInst.attachMedia(vid);
    hlsInst.on(Hls.Events.ERROR, (e,d) => { if(d.fatal) goBack(); });
  } else if (vid.canPlayType('application/vnd.apple.mpegurl')) {
    vid.src = src;
  } else {
    el.innerHTML = noSrcMsg('⚠','HLS پشتیبانی نمی‌شود','');
  }
}
function buildVideo(src, el) {
  const vid = mkVid(false);
  vid.src = src;
  vid.onended = goBack;
  vid.onerror = goBack;
  el.appendChild(vid);
}
function buildIframe(src, el) {
  const f = document.createElement('iframe');
  f.src = src; f.allow = 'autoplay;fullscreen';
  el.appendChild(f);
}
function mkVid(loop=true) {
  const v = document.createElement('video');
  v.autoplay = true; v.muted = true; v.loop = loop; v.playsInline = true;
  return v;
}
function noSrcMsg(icon, title, sub) {
  return `<div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;
                       justify-content:center;gap:16px;background:#09090f;">
    <div style="font-size:52px;">${icon}</div>
    <div style="font-size:17px;color:var(--text-muted);">${title}</div>
    ${sub ? `<div style="font-size:12px;color:var(--text-dim);">${esc(sub)}</div>` : ''}
  </div>`;
}

function goBack() {
  clearTimeout(autoTimer);
  if (hlsInst) { hlsInst.destroy(); hlsInst = null; }
  const playerEl = document.getElementById('iptv-player');
  playerEl.classList.remove('active');
  playerEl.innerHTML = '';
  document.getElementById('back-btn').classList.remove('active');
  document.getElementById('iptv-wrap').style.display = 'flex';
  focusTile(focusIdx);

  // اجرای خودکار مجدد
  const first = menuData?.items.findIndex(i => i.target_url) ?? -1;
  if (first >= 0) autoTimer = setTimeout(() => selectItem(first), 8000);
}

// ══════════════════════════════════════════════════════════════
//  فوکوس / ناوبری
// ══════════════════════════════════════════════════════════════
function focusTile(idx) {
  if (!menuData?.items) return;
  document.querySelectorAll('.iptv-tile').forEach((t, i) => {
    if (i === idx) {
      t.classList.add('focused');
    } else {
      t.classList.remove('focused');
      t.style.borderColor = '';
      t.style.background  = '';
      t.style.transform   = '';
      t.style.boxShadow   = '';
    }
  });
  focusIdx = idx;
}

function getTileCols() {
  const grid = document.getElementById('iptv-grid');
  if (!grid) return 5;
  const tileW = Math.min(190, Math.max(120, window.innerWidth * 0.14)) + 20;
  return Math.max(1, Math.round(grid.offsetWidth / tileW));
}

document.addEventListener('keydown', e => {
  // در حین پخش محتوا
  if (document.getElementById('iptv-player').classList.contains('active')) {
    if (['Backspace','Escape','BrowserBack'].includes(e.key)) {
      goBack(); e.preventDefault();
    }
    return;
  }
  if (!menuData?.items) return;
  const len  = menuData.items.length;
  const cols = getTileCols();
  switch(e.key) {
    case 'ArrowRight': focusTile(Math.min(len-1, focusIdx+1)); e.preventDefault(); break;
    case 'ArrowLeft':  focusTile(Math.max(0,     focusIdx-1)); e.preventDefault(); break;
    case 'ArrowDown':  focusTile(Math.min(len-1, focusIdx+cols)); e.preventDefault(); break;
    case 'ArrowUp':    focusTile(Math.max(0,     focusIdx-cols)); e.preventDefault(); break;
    case 'Enter': case 'OK': selectItem(focusIdx); break;
    case 'Backspace': case 'Escape': case 'BrowserBack': goBack(); break;
  }
});

// ══════════════════════════════════════════════════════════════
//  ساعت
// ══════════════════════════════════════════════════════════════
function tickClock() {
  const te = document.getElementById('clock-time');
  const de = document.getElementById('clock-date');
  if (!te) return;
  const now = new Date();
  te.textContent = [now.getHours(),now.getMinutes(),now.getSeconds()]
    .map(n => String(n).padStart(2,'0')).join(':');
  if (de) de.textContent = now.toLocaleDateString('fa-IR',{weekday:'long',month:'long',day:'numeric'});
  setTimeout(tickClock, 1000);
}

// ══════════════════════════════════════════════════════════════
//  اطلاعات اتاق + پیام‌ها
// ══════════════════════════════════════════════════════════════
let roomMsgQueue   = [];
let bannerTimer    = null;
let popupDismissed = new Set();
let bannerDismissed= new Set();

async function loadRoomInfo() {
  if (!SCREEN_CODE) return;
  try {
    const r = await fetch(`${SERVER_URL}/api/v1/player/room-info/${SCREEN_CODE}`);
    if (!r.ok) return;
    const d = await r.json();
    if (!d.success || !d.data) return;
    const info = d.data;

    // نشان اتاق
    const badge = document.getElementById('room-badge');
    if (info.room_number) {
      document.getElementById('rb-number').textContent = 'اتاق ' + info.room_number;
      const lbl = document.getElementById('rb-guest');
      lbl.textContent = info.guest_name || info.room_name || '';
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }

    // اگر مهمان خاصی داره، خوش‌آمدگویی رو override کن
    if (info.guest_name) {
      const titleEl = document.getElementById('welcome-title');
      if (titleEl && !titleEl.dataset.menuSet) {
        titleEl.textContent = 'خوش آمدید، ' + info.guest_name;
      }
    }

    // پیام‌ها
    if (info.messages?.length) {
      processMessages(info.messages);
    }
  } catch(_) {}
}

function processMessages(msgs) {
  const banners = msgs.filter(m => m.display_mode === 'banner' && !bannerDismissed.has(m.id));
  const popups  = msgs.filter(m => m.display_mode === 'popup'  && !popupDismissed.has(m.id));
  const tickers = msgs.filter(m => m.display_mode === 'ticker');

  // Ticker پیام‌ها رو به تیکر اضافه کن
  if (tickers.length) {
    const ticker = document.getElementById('iptv-ticker');
    const inner  = ticker.querySelector('.t-inner');
    if (inner && ticker.style.display === 'none') {
      ticker.style.display = 'flex';
      inner.innerHTML = tickers.map(m =>
        `<span class="t-text">${esc(m.title ? m.title + ': ' + m.body : m.body)}</span><span class="t-sep">◆</span>`
      ).join('');
      inner.style.animationDuration = '60s';
    }
  }

  // نمایش اولین popup
  if (popups.length) {
    showPopup(popups[0]);
  } else if (banners.length) {
    showBanner(banners[0]);
  }
}

const MSG_ICONS = {welcome:'🌟',info:'💡',urgent:'⚠️',promo:'🎁',custom:'📢'};
const MSG_COLORS= {welcome:'#22c55e',info:'#3b82f6',urgent:'#ef4444',promo:'#f59e0b',custom:'#8b5cf6'};

function showBanner(msg) {
  const col = MSG_COLORS[msg.msg_type] || '#3b82f6';
  const inner = document.getElementById('msg-banner-inner');
  inner.style.background = col + '22';
  inner.style.border      = `1px solid ${col}44`;
  document.getElementById('mb-icon').textContent   = MSG_ICONS[msg.msg_type] || '💡';
  const titleEl = document.getElementById('mb-title');
  if (msg.title) { titleEl.textContent = msg.title; titleEl.style.display = 'block'; }
  else            { titleEl.style.display = 'none'; }
  document.getElementById('mb-body').textContent = msg.body;
  document.getElementById('msg-banner').classList.add('visible');
  bannerTimer = setTimeout(() => dismissBanner(), 12000);
  bannerDismissed.add(msg.id);
}

function dismissBanner() {
  clearTimeout(bannerTimer);
  document.getElementById('msg-banner').classList.remove('visible');
}

function showPopup(msg) {
  document.getElementById('mp-icon').textContent  = MSG_ICONS[msg.msg_type] || '💡';
  document.getElementById('mp-title').textContent = msg.title || '';
  document.getElementById('mp-body').textContent  = msg.body;
  const card = document.querySelector('#msg-popup .mp-card');
  const col  = MSG_COLORS[msg.msg_type] || 'var(--accent)';
  card.style.borderColor = col + '44';
  const icon = document.getElementById('mp-icon');
  icon.style.textShadow  = `0 0 30px ${col}`;
  document.getElementById('msg-popup').classList.add('visible');
  // auto-dismiss بعد از ۳۰ ثانیه
  setTimeout(dismissPopup, 30000);
  popupDismissed.add(msg.id);
}

function dismissPopup() {
  document.getElementById('msg-popup').classList.remove('visible');
}

// ══════════════════════════════════════════════════════════════
//  Heartbeat
// ══════════════════════════════════════════════════════════════
async function heartbeat() {
  try {
    const r = await fetch(`${SERVER_URL}/api/v1/screens/${SCREEN_CODE}/heartbeat`, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({ version:'2.0', screen_type:'iptv' }),
    });
    const d = await r.json();
    (d.data?.commands || []).forEach(cmd => {
      if (cmd.command==='reload'||cmd.command==='refresh') { clearTimeout(autoTimer); loadMenu(); }
      if (cmd.command==='reboot') window.location.reload();
    });
  } catch(_) {}
}

// ══════════════════════════════════════════════════════════════
//  WebSocket
// ══════════════════════════════════════════════════════════════
function connectWS() {
  try {
    const ws = new WebSocket(`ws://${location.hostname}:${WS_PORT}`);
    ws.onopen    = ()=> ws.send(JSON.stringify({type:'subscribe',channel:`screen_${SCREEN_CODE}`}));
    ws.onmessage = e => {
      try {
        const m = JSON.parse(e.data);
        if (m.type==='reload') loadMenu();
        if (m.type==='reboot') location.reload();
      } catch(_) {}
    };
    ws.onclose = ()=> setTimeout(connectWS, 5000);
    ws.onerror = ()=> ws.close();
  } catch(_) {}
}

// ══════════════════════════════════════════════════════════════
//  فعال‌سازی
// ══════════════════════════════════════════════════════════════
window.doActivate = async function() {
  const code = document.getElementById('actCode')?.value?.trim()?.toUpperCase() || '';
  const err  = document.getElementById('err-msg');
  if (code.length !== 6) { err.textContent = 'کد باید ۶ کاراکتر باشد'; return; }
  err.textContent = 'در حال فعال‌سازی...'; err.style.color = '#94a3b8';
  try {
    const r = await fetch(`${SERVER_URL}/player/activate`, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({activation_code:code, screen_code:SCREEN_CODE}),
    });
    const d = await r.json();
    if (d.success) {
      err.style.color = '#22c55e';
      err.textContent = '✅ فعال‌سازی موفق';
      setTimeout(() => location.reload(), 1000);
    } else {
      err.style.color = '#ef4444';
      err.textContent = d.message || 'کد نامعتبر است';
    }
  } catch(e) {
    err.style.color = '#ef4444';
    err.textContent = 'خطا در اتصال به سرور';
  }
};

// ══════════════════════════════════════════════════════════════
//  Helpers
// ══════════════════════════════════════════════════════════════
function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ══════════════════════════════════════════════════════════════
//  Init
// ══════════════════════════════════════════════════════════════
<?php if (($screen['status'] ?? '') === 'active'): ?>
document.addEventListener('DOMContentLoaded', () => {
  loadMenu();
  loadRoomInfo();
  connectWS();
  setInterval(heartbeat,     15000);
  setInterval(loadRoomInfo,  30000);  // بررسی پیام‌های جدید هر ۳۰ ثانیه
  heartbeat();
});
<?php endif; ?>
</script>
</body>
</html>
