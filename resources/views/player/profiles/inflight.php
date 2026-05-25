<?php
/**
 * In-Flight Display Player Profile
 * Reads: $screen (array), $settings (array)
 */
$screenCode    = $screen['code']         ?? '';
$flightId      = (int)($screen['inflight_flight_id'] ?? 0);
$screenName    = $screen['name']         ?? '';
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($screenName) ?> — Inflight</title>
<style>
/* ── Reset & Base ───────────────────────────────────────────────── */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
html,body{width:100%;height:100%;overflow:hidden;font-family:'Segoe UI',system-ui,sans-serif;}

/* ── CSS Variables ──────────────────────────────────────────────── */
:root{
  --acc:#00b4d8;
  --acc-rgb:0,180,216;
  --bg1:#03041a;
  --bg2:#060b2e;
}

/* ── Canvas background ──────────────────────────────────────────── */
#bg-canvas{
  position:fixed;inset:0;width:100%;height:100%;
  z-index:0;
}
/* Overlay gradient to darken edges */
#overlay{
  position:fixed;inset:0;z-index:1;pointer-events:none;
  background:radial-gradient(ellipse 120% 80% at 50% 50%,transparent 40%,rgba(0,0,0,0.7) 100%);
}

/* ── Map canvas ────────────────────────────────────────────────── */
#map-canvas{
  position:fixed;
  top:50%;left:50%;
  transform:translate(-50%,-50%);
  z-index:2;
  opacity:0.85;
}

/* ── UI Panels ──────────────────────────────────────────────────── */
#top-bar{
  position:fixed;top:0;left:0;right:0;z-index:10;
  display:flex;align-items:center;justify-content:space-between;
  padding:16px 32px;
  background:linear-gradient(to bottom,rgba(0,0,0,0.7),transparent);
}
#bottom-bar{
  position:fixed;bottom:0;left:0;right:0;z-index:10;
  padding:16px 32px 20px;
  background:linear-gradient(to top,rgba(0,0,0,0.85),transparent 100%);
}
#origin-panel,#dest-panel{
  position:fixed;top:50%;transform:translateY(-50%);z-index:10;
  width:200px;
  padding:20px 16px;
  background:rgba(0,0,0,0.5);
  backdrop-filter:blur(12px);
  border:1px solid rgba(var(--acc-rgb),0.25);
  border-radius:16px;
}
#origin-panel{right:24px;}
#dest-panel{left:24px;}

/* ── Typography ────────────────────────────────────────────────── */
.iata{font-size:42px;font-weight:900;color:#fff;letter-spacing:2px;line-height:1;}
.city{font-size:14px;color:#94a3b8;margin-top:4px;}
.country{font-size:11px;color:#475569;margin-top:1px;}
.clock{font-size:26px;font-weight:700;color:#fff;font-variant-numeric:tabular-nums;letter-spacing:1px;margin-top:10px;}
.date-str{font-size:11px;color:#64748b;margin-top:2px;}
.panel-label{font-size:9px;font-weight:700;color:var(--acc);letter-spacing:1px;text-transform:uppercase;margin-bottom:12px;opacity:0.9;}

/* ── Flight number badge ────────────────────────────────────────── */
#flight-badge{
  display:flex;align-items:center;gap:10px;
  background:rgba(0,0,0,0.6);backdrop-filter:blur(8px);
  border:1px solid rgba(var(--acc-rgb),0.3);border-radius:12px;
  padding:8px 16px;
}
#flight-no{font-size:20px;font-weight:900;color:#fff;letter-spacing:2px;}
#airline-name{font-size:12px;color:#64748b;}

/* ── Phase badge ────────────────────────────────────────────────── */
#phase-badge{
  display:flex;align-items:center;gap:8px;
  background:rgba(var(--acc-rgb),0.12);
  border:1px solid rgba(var(--acc-rgb),0.35);
  border-radius:10px;padding:7px 14px;
  font-size:13px;font-weight:700;color:var(--acc);
  text-transform:uppercase;letter-spacing:0.5px;
}

/* ── Bottom stats ───────────────────────────────────────────────── */
#stats-row{
  display:flex;align-items:center;justify-content:center;gap:32px;
  margin-bottom:10px;
}
.stat-item{text-align:center;}
.stat-val{
  font-size:28px;font-weight:800;color:#fff;
  font-variant-numeric:tabular-nums;line-height:1;
}
.stat-unit{font-size:11px;color:var(--acc);margin-top:2px;font-weight:600;}
.stat-lbl{font-size:10px;color:#475569;margin-top:1px;}
.stat-divider{width:1px;height:44px;background:rgba(255,255,255,0.08);}

/* ── Progress bar ───────────────────────────────────────────────── */
#prog-wrap{
  max-width:700px;margin:0 auto;
}
#prog-track{
  height:4px;background:rgba(255,255,255,0.1);border-radius:2px;overflow:visible;position:relative;
}
#prog-fill{
  height:100%;border-radius:2px;background:var(--acc);
  transition:width 1.5s ease;position:relative;
}
#plane-icon{
  position:absolute;right:-10px;top:-7px;
  font-size:18px;color:var(--acc);
  filter:drop-shadow(0 0 6px rgba(var(--acc-rgb),0.8));
  transition:right 1.5s ease;
}
#prog-labels{
  display:flex;justify-content:space-between;margin-top:6px;
  font-size:10px;color:#475569;
}

/* ── Welcome message ────────────────────────────────────────────── */
#welcome-wrap{
  text-align:center;margin-top:8px;
  font-size:13px;color:rgba(255,255,255,0.5);
  letter-spacing:0.3px;
  display:none;
}

/* ── Error state ─────────────────────────────────────────────────── */
#error-state{
  position:fixed;inset:0;display:none;align-items:center;justify-content:center;
  flex-direction:column;gap:12px;z-index:50;
  background:#03041a;
  font-size:14px;color:#475569;
}
#error-state i{font-size:48px;color:#1e293b;}
</style>
</head>
<body>

<!-- Star field & background canvas -->
<canvas id="bg-canvas"></canvas>
<!-- Edge vignette -->
<div id="overlay"></div>
<!-- Map canvas -->
<canvas id="map-canvas"></canvas>

<!-- Top bar -->
<div id="top-bar">
  <div id="flight-badge">
    <i class="plane-icon" id="top-plane">✈</i>
    <div>
      <div id="flight-no">------</div>
      <div id="airline-name">---</div>
    </div>
  </div>
  <div id="phase-badge">
    <span id="phase-icon">⏱</span>
    <span id="phase-text">در انتظار</span>
  </div>
  <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:#475569;">
    <span id="utc-clock" style="font-variant-numeric:tabular-nums;color:#64748b;"></span>
    <span>UTC</span>
  </div>
</div>

<!-- Origin panel (right) -->
<div id="origin-panel">
  <div class="panel-label">✈ مبدأ</div>
  <div class="iata" id="orig-iata">---</div>
  <div class="city" id="orig-city">---</div>
  <div class="country" id="orig-country"></div>
  <div class="clock" id="orig-clock">--:--</div>
  <div class="date-str" id="orig-date"></div>
</div>

<!-- Destination panel (left) -->
<div id="dest-panel">
  <div class="panel-label">🛬 مقصد</div>
  <div class="iata" id="dest-iata">---</div>
  <div class="city" id="dest-city">---</div>
  <div class="country" id="dest-country"></div>
  <div class="clock" id="dest-clock">--:--</div>
  <div class="date-str" id="dest-date"></div>
</div>

<!-- Bottom bar -->
<div id="bottom-bar">
  <div id="stats-row">
    <div class="stat-item">
      <div class="stat-val" id="s-alt">0</div>
      <div class="stat-unit">ft</div>
      <div class="stat-lbl">ارتفاع</div>
    </div>
    <div class="stat-divider"></div>
    <div class="stat-item">
      <div class="stat-val" id="s-spd">0</div>
      <div class="stat-unit">km/h</div>
      <div class="stat-lbl">سرعت</div>
    </div>
    <div class="stat-divider"></div>
    <div class="stat-item">
      <div class="stat-val" id="s-dist">---</div>
      <div class="stat-unit">km</div>
      <div class="stat-lbl">فاصله باقی</div>
    </div>
    <div class="stat-divider"></div>
    <div class="stat-item">
      <div class="stat-val" id="s-eta">---</div>
      <div class="stat-unit">ETA</div>
      <div class="stat-lbl">زمان تا مقصد</div>
    </div>
    <div class="stat-divider"></div>
    <div class="stat-item">
      <div class="stat-val" id="s-pct">0</div>
      <div class="stat-unit">%</div>
      <div class="stat-lbl">مسیر طی‌شده</div>
    </div>
  </div>

  <!-- Progress arc -->
  <div id="prog-wrap">
    <div id="prog-track">
      <div id="prog-fill" style="width:0%;">
        <span id="plane-icon">✈</span>
      </div>
    </div>
    <div id="prog-labels">
      <span id="pl-orig">---</span>
      <span id="pl-dest">---</span>
    </div>
  </div>

  <div id="welcome-wrap" id="welcome-msg"></div>
</div>

<!-- Error state -->
<div id="error-state">
  <i>✈</i>
  <div id="error-text">در حال بارگذاری اطلاعات پرواز…</div>
</div>

<script>
// ── Config ──────────────────────────────────────────────────────
const FLIGHT_ID  = <?= $flightId ?>;
const SCREEN_CODE = <?= json_encode($screenCode) ?>;
const POLL_MS    = 30000;

// ── State ────────────────────────────────────────────────────────
let flightData   = null;
let animFrame    = null;
let stars        = [];

// ── Canvas setup ─────────────────────────────────────────────────
const bgCanvas  = document.getElementById('bg-canvas');
const bgCtx     = bgCanvas.getContext('2d');
const mapCanvas = document.getElementById('map-canvas');
const mapCtx    = mapCanvas.getContext('2d');

function resize() {
  bgCanvas.width  = window.innerWidth;
  bgCanvas.height = window.innerHeight;
  const mw = Math.min(window.innerWidth - 460, 900);
  const mh = Math.min(window.innerHeight - 220, 500);
  mapCanvas.width  = mw;
  mapCanvas.height = mh;
  generateStars();
  if (flightData) drawAll();
}
window.addEventListener('resize', resize);

// ── Starfield ─────────────────────────────────────────────────────
function generateStars() {
  stars = [];
  const n = Math.floor(bgCanvas.width * bgCanvas.height / 3000);
  for (let i = 0; i < n; i++) {
    stars.push({
      x: Math.random() * bgCanvas.width,
      y: Math.random() * bgCanvas.height,
      r: Math.random() * 1.5 + 0.2,
      alpha: Math.random() * 0.6 + 0.1,
      twinkle: Math.random() * Math.PI * 2,
      speed: Math.random() * 0.02 + 0.005,
    });
  }
}

let tick = 0;
function animateStars() {
  tick += 0.016;
  const bgStyle = flightData?.bg_style || 'space';
  drawBackground(bgStyle);
  stars.forEach(s => {
    s.twinkle += s.speed;
    const a = s.alpha * (0.6 + 0.4 * Math.sin(s.twinkle));
    bgCtx.beginPath();
    bgCtx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
    bgCtx.fillStyle = `rgba(255,255,255,${a})`;
    bgCtx.fill();
  });
  animFrame = requestAnimationFrame(animateStars);
}

function drawBackground(style) {
  const w = bgCanvas.width, h = bgCanvas.height;
  let grad;
  switch (style) {
    case 'clouds':
      grad = bgCtx.createLinearGradient(0, 0, 0, h);
      grad.addColorStop(0, '#0a1628');
      grad.addColorStop(0.4, '#1a3a5c');
      grad.addColorStop(0.8, '#4a7faa');
      grad.addColorStop(1, '#87b8d4');
      break;
    case 'ocean':
      grad = bgCtx.createLinearGradient(0, 0, 0, h);
      grad.addColorStop(0, '#020b18');
      grad.addColorStop(0.5, '#023a5c');
      grad.addColorStop(1, '#0077b6');
      break;
    case 'dusk':
      grad = bgCtx.createLinearGradient(0, 0, 0, h);
      grad.addColorStop(0, '#0d0621');
      grad.addColorStop(0.3, '#4a0e4e');
      grad.addColorStop(0.6, '#c0392b');
      grad.addColorStop(0.85, '#e76f51');
      grad.addColorStop(1, '#f4a261');
      break;
    default: // space
      grad = bgCtx.createRadialGradient(w/2, h/2, 0, w/2, h/2, Math.max(w,h)*0.7);
      grad.addColorStop(0, '#070d2a');
      grad.addColorStop(0.5, '#040919');
      grad.addColorStop(1, '#020510');
  }
  bgCtx.fillStyle = grad;
  bgCtx.fillRect(0, 0, w, h);
}

// ── World Map Polygons (simplified) ───────────────────────────────
// lon/lat pairs — simplified continent outlines
const CONTINENTS = [
  // North America
  [[-168,71],[-140,70],[-120,60],[-105,49],[-83,46],[-70,47],[-67,44],[-80,25],[-90,15],
   [-87,16],[-85,10],[-77,8],[-77,7],[-80,9],[-75,11],[-72,12],[-65,18],[-60,15],
   [-62,17],[-73,11],[-74,11],[-80,5],[-78,4],[-80,8],[-85,9],[-90,16],[-92,19],
   [-90,21],[-88,22],[-87,25],[-90,29],[-97,26],[-97,22],[-105,20],[-105,22],
   [-110,23],[-115,29],[-117,32],[-120,36],[-122,37],[-124,41],[-125,49],
   [-130,55],[-140,60],[-145,60],[-150,60],[-158,57],[-162,60],[-165,64],[-168,66]],
  // South America
  [[-80,10],[-73,12],[-62,11],[-60,7],[-52,4],[-51,4],[-50,1],[-44,2],
   [-37,5],[-35,6],[-35,9],[-37,11],[-39,14],[-40,20],[-43,23],[-44,23],
   [-48,26],[-49,26],[-51,30],[-51,33],[-53,33],[-55,34],[-58,34],[-58,38],
   [-62,38],[-65,42],[-66,44],[-66,47],[-67,55],[-69,54],[-72,50],[-73,44],
   [-72,42],[-70,38],[-70,30],[-70,20],[-75,12],[-77,8],[-80,9]],
  // Europe
  [[-9,36],[0,36],[5,36],[10,38],[12,38],[16,38],[18,37],[22,38],[26,38],[30,36],
   [32,36],[36,38],[40,40],[42,42],[44,43],[42,45],[40,47],[38,48],[36,49],
   [34,50],[32,52],[30,54],[28,55],[26,56],[25,60],[26,65],[28,68],[30,70],
   [28,71],[24,71],[20,70],[16,70],[14,70],[10,63],[8,58],[7,58],[6,58],
   [5,58],[4,54],[3,52],[2,52],[0,50],[-2,50],[-5,48],[-8,42],[-9,39],[-9,36]],
  // Africa
  [[-17,14],[-15,11],[-13,9],[-11,8],[-8,5],[-4,5],[1,5],[5,5],[8,4],[9,3],
   [10,1],[12,0],[14,-1],[16,-2],[18,-3],[20,-4],[22,-5],[24,-6],[26,-8],
   [28,-10],[30,-12],[32,-14],[34,-16],[35,-18],[36,-20],[36,-22],[34,-24],
   [32,-26],[30,-28],[28,-30],[27,-34],[18,-34],[16,-30],[14,-26],[12,-22],
   [10,-18],[8,-14],[6,-10],[4,-6],[2,-4],[0,-1],[-2,3],[-4,5],[-8,5],
   [-10,7],[-12,8],[-14,10],[-16,12],[-17,14]],
  // Asia (east)
  [[140,72],[130,70],[120,68],[110,66],[100,72],[90,72],[80,72],[70,68],[60,66],
   [50,68],[44,68],[42,65],[40,60],[38,58],[36,56],[34,52],[36,49],[38,48],
   [40,47],[42,43],[44,42],[46,42],[50,44],[55,44],[60,44],[65,42],[70,40],
   [72,38],[74,36],[72,34],[70,32],[68,28],[66,24],[62,22],[58,20],[54,18],
   [50,16],[50,12],[46,10],[44,12],[42,14],[38,14],[36,14],[34,12],[32,12],
   [30,10],[28,10],[26,10],[24,12],[22,13],[20,15],[18,15],[16,14],[14,12],
   [12,10],[10,10],[8,8],[8,5],[10,4],[12,3],[14,3],[16,2],[18,2],[20,2],
   [22,2],[24,2],[26,2],[28,3],[30,3],[32,4],[34,4],[36,4],[38,8],[40,10],
   [42,12],[44,14],[46,14],[48,16],[50,16],[52,18],[54,22],[56,24],[58,26],
   [62,28],[66,30],[70,30],[72,34],[76,34],[80,28],[84,26],[88,26],[92,24],
   [96,22],[100,20],[102,20],[104,22],[106,22],[108,18],[110,16],[112,16],
   [114,18],[116,22],[118,22],[120,24],[122,26],[122,30],[120,30],[118,34],
   [120,38],[122,40],[124,42],[126,44],[128,44],[130,42],[132,40],[134,42],
   [136,42],[138,40],[140,38],[142,42],[144,44],[144,48],[142,52],[140,54],
   [140,60],[138,64],[140,68],[140,72]],
  // Australia
  [[114,-22],[116,-20],[120,-18],[124,-16],[128,-14],[130,-12],[132,-12],[134,-12],
   [136,-12],[138,-14],[140,-16],[142,-16],[144,-14],[146,-14],[148,-16],[150,-18],
   [152,-22],[154,-24],[152,-26],[152,-28],[152,-30],[152,-32],[150,-34],[148,-36],
   [146,-38],[144,-38],[142,-38],[140,-36],[138,-34],[136,-34],[134,-32],[132,-32],
   [130,-30],[128,-28],[124,-26],[122,-24],[120,-22],[116,-22],[114,-22]],
];

// ── Geo projection (equirectangular) ─────────────────────────────
function project(lng, lat, cx, cy, scale) {
  const x = cx + (lng / 180) * scale;
  const y = cy - (lat / 90) * (scale * 0.5);
  return {x, y};
}

// ── Great circle intermediate point ──────────────────────────────
function gcPoint(lat1, lng1, lat2, lng2, t) {
  // Angular distance
  const toR = Math.PI/180;
  const φ1=lat1*toR, λ1=lng1*toR, φ2=lat2*toR, λ2=lng2*toR;
  const d = 2*Math.asin(Math.sqrt(
    Math.sin((φ2-φ1)/2)**2 + Math.cos(φ1)*Math.cos(φ2)*Math.sin((λ2-λ1)/2)**2
  ));
  if (d < 0.0001) return {lat:lat1, lng:lng1};
  const A = Math.sin((1-t)*d)/Math.sin(d);
  const B = Math.sin(t*d)/Math.sin(d);
  const x = A*Math.cos(φ1)*Math.cos(λ1) + B*Math.cos(φ2)*Math.cos(λ2);
  const y = A*Math.cos(φ1)*Math.sin(λ1) + B*Math.cos(φ2)*Math.sin(λ2);
  const z = A*Math.sin(φ1) + B*Math.sin(φ2);
  return {lat:Math.atan2(z,Math.sqrt(x*x+y*y))/toR, lng:Math.atan2(y,x)/toR};
}

// ── Draw map ──────────────────────────────────────────────────────
function drawMap(fd) {
  const w = mapCanvas.width, h = mapCanvas.height;
  const cx = w/2, cy = h/2;
  const scale = w * 0.52;

  mapCtx.clearRect(0, 0, w, h);

  // Ocean background
  mapCtx.fillStyle = 'rgba(0,30,60,0.4)';
  mapCtx.roundRect(0, 0, w, h, 12);
  mapCtx.fill();

  // Continent fill
  const acc = fd?.accent_color || '#00b4d8';
  CONTINENTS.forEach(poly => {
    mapCtx.beginPath();
    poly.forEach(([lng, lat], i) => {
      const {x,y} = project(lng,lat,cx,cy,scale);
      i===0 ? mapCtx.moveTo(x,y) : mapCtx.lineTo(x,y);
    });
    mapCtx.closePath();
    mapCtx.fillStyle   = 'rgba(30,58,100,0.75)';
    mapCtx.strokeStyle = 'rgba(56,100,160,0.6)';
    mapCtx.lineWidth   = 0.8;
    mapCtx.fill();
    mapCtx.stroke();
  });

  // Lat/lng grid lines (subtle)
  mapCtx.strokeStyle = 'rgba(255,255,255,0.04)';
  mapCtx.lineWidth   = 0.5;
  for (let lat = -90; lat <= 90; lat += 30) {
    mapCtx.beginPath();
    for (let lng = -180; lng <= 180; lng += 5) {
      const {x,y} = project(lng,lat,cx,cy,scale);
      lng===-180 ? mapCtx.moveTo(x,y) : mapCtx.lineTo(x,y);
    }
    mapCtx.stroke();
  }
  for (let lng = -180; lng <= 180; lng += 30) {
    mapCtx.beginPath();
    for (let lat = -90; lat <= 90; lat += 5) {
      const {x,y} = project(lng,lat,cx,cy,scale);
      lat===-90 ? mapCtx.moveTo(x,y) : mapCtx.lineTo(x,y);
    }
    mapCtx.stroke();
  }

  if (!fd?.origin_lat || !fd?.dest_lat) return;

  const olat = parseFloat(fd.origin_lat), olng = parseFloat(fd.origin_lng);
  const dlat = parseFloat(fd.dest_lat),   dlng = parseFloat(fd.dest_lng);
  const pct  = (fd.progress_pct || 0) / 100;

  // Great circle path (dashed — behind plane)
  const gcPts = [];
  for (let t = 0; t <= 1; t += 0.01) {
    gcPts.push(gcPoint(olat,olng,dlat,dlng,t));
  }

  // Flown portion
  mapCtx.beginPath();
  gcPts.slice(0, Math.floor(pct*100)+1).forEach((p,i) => {
    const {x,y} = project(p.lng,p.lat,cx,cy,scale);
    i===0 ? mapCtx.moveTo(x,y) : mapCtx.lineTo(x,y);
  });
  mapCtx.strokeStyle = acc;
  mapCtx.lineWidth   = 2.5;
  mapCtx.setLineDash([]);
  mapCtx.stroke();

  // Remaining (dashed)
  mapCtx.beginPath();
  gcPts.slice(Math.floor(pct*100)).forEach((p,i) => {
    const {x,y} = project(p.lng,p.lat,cx,cy,scale);
    i===0 ? mapCtx.moveTo(x,y) : mapCtx.lineTo(x,y);
  });
  mapCtx.strokeStyle = 'rgba(255,255,255,0.18)';
  mapCtx.lineWidth   = 1.5;
  mapCtx.setLineDash([5,6]);
  mapCtx.stroke();
  mapCtx.setLineDash([]);

  // Origin dot
  const op = project(olng,olat,cx,cy,scale);
  mapCtx.beginPath();
  mapCtx.arc(op.x,op.y,5,0,Math.PI*2);
  mapCtx.fillStyle = acc;
  mapCtx.fill();
  mapCtx.beginPath();
  mapCtx.arc(op.x,op.y,9,0,Math.PI*2);
  mapCtx.strokeStyle = 'rgba(0,180,216,0.35)';
  mapCtx.lineWidth = 1.5;
  mapCtx.stroke();

  // Dest dot
  const dp = project(dlng,dlat,cx,cy,scale);
  mapCtx.beginPath();
  mapCtx.arc(dp.x,dp.y,5,0,Math.PI*2);
  mapCtx.fillStyle = '#a78bfa';
  mapCtx.fill();
  mapCtx.beginPath();
  mapCtx.arc(dp.x,dp.y,9,0,Math.PI*2);
  mapCtx.strokeStyle = 'rgba(167,139,250,0.35)';
  mapCtx.lineWidth = 1.5;
  mapCtx.stroke();

  // Animated plane icon along arc
  const planePt = gcPoint(olat,olng,dlat,dlng,pct);
  const {x:px,y:py} = project(planePt.lng,planePt.lat,cx,cy,scale);

  // Heading from derivative
  const eps = 0.005;
  const ahead = gcPoint(olat,olng,dlat,dlng, Math.min(pct+eps,1));
  const {x:ax,y:ay} = project(ahead.lng,ahead.lat,cx,cy,scale);
  const angle = Math.atan2(ay-py, ax-px);

  mapCtx.save();
  mapCtx.translate(px, py);
  mapCtx.rotate(angle + Math.PI/2);
  // Glow
  mapCtx.shadowColor   = acc;
  mapCtx.shadowBlur    = 16;
  // Plane symbol
  mapCtx.font = 'bold 20px sans-serif';
  mapCtx.textAlign    = 'center';
  mapCtx.textBaseline = 'middle';
  mapCtx.fillStyle    = '#fff';
  mapCtx.fillText('✈', 0, 0);
  mapCtx.restore();
}

// ── Update UI ─────────────────────────────────────────────────────
function applyData(fd) {
  const acc     = fd.accent_color || '#00b4d8';
  const accRgb  = hexToRgb(acc);
  document.documentElement.style.setProperty('--acc', acc);
  document.documentElement.style.setProperty('--acc-rgb', accRgb);

  // Flight info
  document.getElementById('flight-no').textContent   = fd.flight_number || '------';
  document.getElementById('airline-name').textContent = fd.airline_name  || '';

  // Phase
  const phaseInfo = PHASES[fd.phase] || PHASES['preflight'];
  document.getElementById('phase-icon').textContent = phaseInfo.icon;
  document.getElementById('phase-text').textContent = phaseInfo.label;

  // Airports
  document.getElementById('orig-iata').textContent    = fd.origin_iata    || '---';
  document.getElementById('orig-city').textContent    = fd.origin_city    || '';
  document.getElementById('orig-country').textContent = fd.origin_country || '';
  document.getElementById('dest-iata').textContent    = fd.dest_iata      || '---';
  document.getElementById('dest-city').textContent    = fd.dest_city      || '';
  document.getElementById('dest-country').textContent = fd.dest_country   || '';

  // Progress
  const pct = fd.progress_pct || 0;
  document.getElementById('prog-fill').style.width = pct + '%';
  document.getElementById('pl-orig').textContent   = fd.origin_iata || '---';
  document.getElementById('pl-dest').textContent   = fd.dest_iata   || '---';

  // Stats
  document.getElementById('s-alt').textContent = parseInt(fd.altitude_ft||0).toLocaleString();
  document.getElementById('s-spd').textContent = fd.speed_kmh || 0;
  document.getElementById('s-pct').textContent = pct;

  const dist = fd.dist_km;
  const remaining = dist ? Math.round(dist * (1 - pct/100)) : null;
  document.getElementById('s-dist').textContent = remaining ? remaining.toLocaleString() : '---';
  document.getElementById('s-eta').textContent  = fd.eta_mins ? fmtEta(fd.eta_mins) : '---';

  // Welcome
  const wEl = document.getElementById('welcome-wrap');
  if (fd.welcome_msg) { wEl.textContent = fd.welcome_msg; wEl.style.display='block'; }
  else wEl.style.display='none';

  // Draw map
  drawMap(fd);

  // Hide error
  document.getElementById('error-state').style.display = 'none';
}

// ── Clock ticks ────────────────────────────────────────────────────
function tickClocks() {
  const now = new Date();
  // UTC clock
  document.getElementById('utc-clock').textContent = padTime(now.getUTCHours())+':'+padTime(now.getUTCMinutes());

  if (!flightData) return;

  // Origin TZ clock
  try {
    const origTz = flightData.origin_timezone || 'UTC';
    const destTz = flightData.dest_timezone   || 'UTC';
    const fmt = {hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false};
    const fmtDate = {weekday:'short',month:'short',day:'numeric'};
    document.getElementById('orig-clock').textContent = now.toLocaleTimeString('fa-IR',{...fmt,timeZone:origTz});
    document.getElementById('orig-date').textContent  = now.toLocaleDateString('fa-IR',{...fmtDate,timeZone:origTz});
    document.getElementById('dest-clock').textContent = now.toLocaleTimeString('fa-IR',{...fmt,timeZone:destTz});
    document.getElementById('dest-date').textContent  = now.toLocaleDateString('fa-IR',{...fmtDate,timeZone:destTz});
  } catch(e) {}
}
function padTime(n){ return n<10?'0'+n:n; }
setInterval(tickClocks, 1000);

// ── Data polling ──────────────────────────────────────────────────
async function loadFlight() {
  if (!FLIGHT_ID) {
    document.getElementById('error-state').style.display='flex';
    document.getElementById('error-text').textContent = 'هیچ پروازی به این نمایشگر اختصاص داده نشده است';
    return;
  }
  try {
    const res = await fetch(`/api/v1/inflight/player/${FLIGHT_ID}`);
    const data = await res.json();
    if (data.success && data.data) {
      flightData = data.data;
      applyData(flightData);
    }
  } catch(e) {
    console.warn('Inflight fetch failed:', e);
  }
}

// ── Heartbeat to server ───────────────────────────────────────────
async function heartbeat() {
  if (!SCREEN_CODE) return;
  try {
    await fetch(`/api/v1/screens/${SCREEN_CODE}/heartbeat`, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({player_version:'inflight-1.0'})
    });
  } catch(e) {}
}

// ── Helpers ────────────────────────────────────────────────────────
const PHASES = {
  preflight: {icon:'⏱', label:'قبل از پرواز'},
  taxi:      {icon:'🚗', label:'در حال حرکت روی باند'},
  takeoff:   {icon:'↗', label:'برخاستن'},
  climb:     {icon:'📈', label:'صعود'},
  cruise:    {icon:'✈', label:'پرواز'},
  descent:   {icon:'📉', label:'نزول'},
  approach:  {icon:'🛬', label:'در حال فرود'},
  landing:   {icon:'🛬', label:'نشست'},
  landed:    {icon:'🏁', label:'فرود کرده'},
};
function fmtEta(m) {
  if (!m||m<0) return '---';
  const h=Math.floor(m/60), mn=m%60;
  return h>0?`${h}h ${mn}m`:`${mn}m`;
}
function hexToRgb(hex) {
  const r=parseInt(hex.slice(1,3),16),g=parseInt(hex.slice(3,5),16),b=parseInt(hex.slice(5,7),16);
  return `${r},${g},${b}`;
}

// ── Init ────────────────────────────────────────────────────────────
resize();
animateStars();
loadFlight();
heartbeat();
setInterval(loadFlight, POLL_MS);
setInterval(heartbeat, 15000);
// Redraw map periodically (for smooth progress indicator)
setInterval(() => { if (flightData) drawMap(flightData); }, 2000);
tickClocks();
</script>
</body>
</html>
