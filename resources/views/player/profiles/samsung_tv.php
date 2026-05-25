<?php
/**
 * SignageCMS Player — Samsung Tizen Profile
 * Optimized for Samsung Smart TV (Tizen 3.x, 4.x, 5.x, 6.x+)
 * Uses Samsung AVPlay API where available + HTML5 fallback
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
<meta name="viewport" content="width=1920">
<title>SignageCMS — Samsung</title>
<!-- Samsung Tizen APIs -->
<script type="text/javascript" src="$WEBAPIS/webapis/webapis.js" onerror=""></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body,html{width:1920px;height:1080px;overflow:hidden;background:#000;}
#c{position:relative;width:1920px;height:1080px;}
.slide{position:absolute;top:0;left:0;width:1920px;height:1080px;opacity:0;-webkit-transition:opacity .5s;transition:opacity .5s;}
.slide.on{opacity:1;}
.slide img{width:1920px;height:1080px;object-fit:cover;display:block;}
.slide video{width:1920px;height:1080px;display:block;background:#000;object-fit:contain;}
.slide iframe{width:1920px;height:1080px;border:0;}
#ticker{position:absolute;bottom:0;left:0;width:1920px;height:40px;background:rgba(0,0,0,.8);overflow:hidden;<?= $ticker?'':'display:none;'?>}
#tick{position:absolute;top:0;height:40px;white-space:nowrap;font:600 19px/40px Arial,sans-serif;color:#fff;padding-right:1920px;}
#logo{position:absolute;<?= $posMap[$logoPos]??$posMap['bottom-right'] ?>;opacity:.85;pointer-events:none;<?= $logoUrl?'':'display:none;'?>}
#logo img{width:120px;}
#clock{position:absolute;top:14px;right:14px;padding:7px 16px;background:rgba(0,0,0,.55);border-radius:8px;font:700 28px/1 monospace;color:#fff;<?= $clk?'':'display:none;'?>}
#act{position:absolute;top:0;left:0;width:1920px;height:1080px;background:#09090f;display:flex;align-items:center;justify-content:center;}
#act-box{background:#111;border-radius:16px;padding:40px;text-align:center;width:380px;}
#act-inp{font:700 28px/1 monospace;letter-spacing:12px;padding:14px;width:100%;background:#0d0d14;border:2px solid rgba(249,115,22,.4);border-radius:12px;color:#fff;text-align:center;text-transform:uppercase;}
#act-btn{width:100%;margin-top:14px;padding:15px;font-size:17px;background:linear-gradient(135deg,#f97316,#c2570b);color:#fff;border:0;border-radius:12px;cursor:pointer;}
#act-msg{font-size:13px;margin-top:12px;min-height:20px;color:#ef4444;}
</style>
</head>
<body>
<div id="c">
  <?php if (($screen['status']??'') !== 'active'): ?>
  <div id="act">
    <div id="act-box">
      <div style="font-size:40px;margin-bottom:12px;">📺</div>
      <div style="font-size:20px;font-weight:700;color:#fff;margin-bottom:6px;">SignageCMS</div>
      <div style="font-size:12px;color:#64748b;margin-bottom:20px;">کد فعال‌سازی را وارد کنید</div>
      <div style="font-size:13px;color:#94a3b8;margin-bottom:14px;font-family:monospace;"><?= e($screen['code']??'') ?></div>
      <input id="act-inp" type="text" maxlength="6" placeholder="______">
      <button id="act-btn" onclick="doActivate()">فعال‌سازی</button>
      <div id="act-msg"></div>
    </div>
  </div>
  <?php else: ?>
  <div id="logo"><?= $logoUrl ? '<img src="'.e($logoUrl).'" alt="" onerror="this.parentNode.style.display=\'none\'">' : '' ?></div>
  <div id="clock">--:--</div>
  <div id="ticker"><div id="tick"><?= e($ticker).'&nbsp;&nbsp;&nbsp;&nbsp;'.e($ticker) ?></div></div>
  <?php endif; ?>
</div>

<script>
var SERVER = window.location.origin;
var CODE   = '<?= e($screen['code']??'') ?>';
var pl = [], ci = 0, tm = null, curSlide = null;
var _serverOffset = 0;
// Samsung Tizen: تشخیص نسخه
var isTizen = (navigator.userAgent.indexOf('Tizen') > -1);
var tizenVer = 0;
if (isTizen) {
  var uaMatch = navigator.userAgent.match(/Tizen (\d+\.?\d*)/i);
  if (uaMatch) tizenVer = parseFloat(uaMatch[1]);
}

// ─── Server time ─────────────────────────────────────────────
function syncTime() {
  var x = new XMLHttpRequest();
  x.open('GET', SERVER + '/api/v1/time', true);
  x.onload = function() {
    try { var d=JSON.parse(x.responseText); if(d.success) _serverOffset=d.timestamp*1000-Date.now(); } catch(e) {}
  };
  x.send();
}
<?php if ($clk): ?>
function updateClock() {
  var d = new Date(Date.now() + _serverOffset);
  var h=d.getHours(), m=d.getMinutes(), s=d.getSeconds();
  var el=document.getElementById('clock');
  if(el) el.textContent=(h<10?'0':'')+h+':'+(m<10?'0':'')+m+':'+(s<10?'0':'')+s;
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
    if(pos < -t.offsetWidth/2) pos = 0;
    t.style.left = pos + 'px';
  }, 16);
})();
<?php endif; ?>

// ─── URL fix ─────────────────────────────────────────────────
function fixUrl(u) {
  if (!u) return '';
  return u.replace(/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/g, SERVER);
}

// ─── Playlist ─────────────────────────────────────────────────
function loadPlaylist() {
  var x = new XMLHttpRequest();
  x.open('GET', SERVER + '/api/v1/screens/' + CODE + '/playlist', true);
  x.timeout = 10000;
  x.onload = function() {
    try {
      var d = JSON.parse(x.responseText);
      if (d.success && d.data && d.data.items && d.data.items.length) {
        pl = d.data.items; ci = 0; play(0);
      } else { setTimeout(loadPlaylist, 30000); }
    } catch(e) { setTimeout(loadPlaylist, 15000); }
  };
  x.ontimeout = x.onerror = function() { setTimeout(loadPlaylist, 15000); };
  x.send();
}

function makeSlide() {
  var div = document.createElement('div');
  div.className = 'slide';
  return div;
}

function swapSlide(newDiv) {
  var c = document.getElementById('c');
  c.appendChild(newDiv);
  // تأخیر کوتاه برای render
  setTimeout(function(){ newDiv.classList.add('on'); }, 50);
  if (curSlide) {
    var old = curSlide;
    setTimeout(function(){
      old.classList.remove('on');
      setTimeout(function(){ if(old.parentNode) old.parentNode.removeChild(old); }, 600);
    }, 50);
  }
  curSlide = newDiv;
}

function play(i) {
  clearTimeout(tm);
  if (!pl.length) return;
  var item = pl[i];
  var src  = fixUrl(item.file_url || item.src || item.file_path || item.url || '');
  var type = item.type || 'image';
  var dur  = (item.duration || 10) * 1000;
  var div  = makeSlide();

  // ─ IMAGE ─
  if (type === 'image' || src.match(/\.(jpg|jpeg|png|gif|webp)(\?|$)/i)) {
    var img = new Image();
    img.style.cssText = 'width:1920px;height:1080px;object-fit:cover;display:block;';
    img.onerror = function() { setTimeout(nextItem, 500); };
    img.onload  = function() { div.appendChild(img); swapSlide(div); };
    img.src = src;
    tm = setTimeout(nextItem, dur);

  // ─ VIDEO ─
  } else if (type === 'video' || src.match(/\.(mp4|webm|ogv|mov)(\?|$)/i) ||
             src.match(/\.m3u8(\?|$)/i)) {

    var vid = document.createElement('video');
    vid.style.cssText = 'width:1920px;height:1080px;display:block;background:#000;';
    // Samsung Tizen critical attributes
    vid.setAttribute('autoplay',       '');
    vid.setAttribute('muted',          '');
    vid.setAttribute('playsinline',    '');
    vid.setAttribute('webkit-playsinline', '');
    vid.setAttribute('preload',        'auto');
    // Samsung: این attribute مهمه
    vid.setAttribute('crossorigin',    'anonymous');
    vid.muted   = true;
    vid.loop    = false;

    // Samsung Tizen video events
    vid.oncanplaythrough = function() {
      vid.play().catch(function(){});
    };
    vid.onended = nextItem;
    vid.onerror = function() {
      // Samsung: retry با delay
      setTimeout(nextItem, 1000);
    };

    // Samsung HLS: اگه native support باشه
    if (src.match(/\.m3u8/i) && vid.canPlayType('application/vnd.apple.mpegurl')) {
      vid.src = src;
    } else if (src.match(/\.m3u8/i)) {
      // برای Samsung قدیمی HLS - skip
      setTimeout(nextItem, 500);
      return;
    } else {
      vid.src = src;
    }

    div.appendChild(vid);
    swapSlide(div);
    vid.load();
    vid.play().catch(function(){
      // Samsung: autoplay blocked → silent play
      setTimeout(function(){ vid.play().catch(function(){}); }, 500);
    });

    if (dur > 0 && dur < 7200000) tm = setTimeout(nextItem, dur);

  // ─ IFRAME ─
  } else {
    var ifr = document.createElement('iframe');
    ifr.src = src;
    ifr.style.cssText = 'width:1920px;height:1080px;border:0;background:#000;';
    ifr.setAttribute('sandbox', 'allow-scripts allow-same-origin');
    div.appendChild(ifr);
    swapSlide(div);
    tm = setTimeout(nextItem, dur);
  }
}

function nextItem() { ci = (ci+1) % pl.length; play(ci); }

// ─── Heartbeat ────────────────────────────────────────────────
function heartbeat() {
  var x = new XMLHttpRequest();
  x.open('POST', SERVER+'/api/v1/screens/'+CODE+'/heartbeat', true);
  x.setRequestHeader('Content-Type', 'application/json');
  x.timeout = 8000;
  x.onload = function() {
    try {
      var d = JSON.parse(x.responseText);
      var cmds = (d.data && d.data.commands) ? d.data.commands : [];
      for (var i=0; i<cmds.length; i++) {
        if (cmds[i].command==='reload') loadPlaylist();
        if (cmds[i].command==='reboot') window.location.reload();
        if (cmds[i].command==='instant_media') showInstant(cmds[i].data);
        if (cmds[i].command==='clear_instant') clearInstant();
      }
    } catch(e) {}
  };
  x.send(JSON.stringify({version:'samsung-tizen-'+tizenVer, item:ci}));
}
setInterval(heartbeat, 15000);

// ─── Instant broadcast ────────────────────────────────────────
var instTm = null;
function showInstant(data) {
  clearInstant();
  var ov = document.createElement('div');
  ov.id = 'inst-ov';
  ov.style.cssText = 'position:fixed;top:0;left:0;width:1920px;height:1080px;z-index:9999;background:#000;display:flex;align-items:center;justify-content:center;';
  var c = document.getElementById('c');

  if (data.type==='image') {
    ov.innerHTML = '<img src="'+fixUrl(data.content)+'" style="max-width:1920px;max-height:1080px;object-fit:contain;">';
  } else if (data.type==='video') {
    ov.innerHTML = '<video src="'+fixUrl(data.content)+'" autoplay muted playsinline style="max-width:1920px;max-height:1080px;" onended="clearInstant()"></video>';
  } else if (data.type==='text') {
    var t={text:'',color:'#fff',bg:'#000'};
    try { t=JSON.parse(data.content); } catch(e) { t.text=data.content; }
    ov.style.background=t.bg||'#000';
    ov.innerHTML='<div style="font-size:80px;font-weight:900;color:'+(t.color||'#fff')+';text-align:center;padding:60px;">'+(t.text||'')+'</div>';
  }
  c.appendChild(ov);
  var dur=parseInt(data.duration)||30;
  if(dur>0) instTm=setTimeout(clearInstant, dur*1000);
}
function clearInstant() {
  clearTimeout(instTm);
  var el=document.getElementById('inst-ov');
  if(el && el.parentNode) el.parentNode.removeChild(el);
}

// ─── Activation ──────────────────────────────────────────────
function doActivate() {
  var code=(document.getElementById('act-inp').value||'').toUpperCase().trim();
  var msg=document.getElementById('act-msg');
  if(!code||code.length!==6){msg.textContent='کد ۶ کاراکتر وارد کنید';return;}
  msg.style.color='#94a3b8'; msg.textContent='در حال اتصال...';
  var x=new XMLHttpRequest();
  x.open('POST',SERVER+'/player/activate',true);
  x.setRequestHeader('Content-Type','application/json');
  x.timeout=10000;
  x.onload=function(){
    var d={}; try{d=JSON.parse(x.responseText);}catch(e){}
    if(d.success){
      msg.style.color='#22c55e'; msg.textContent='✅ موفق!';
      setTimeout(function(){window.location.reload();},1200);
    } else {
      msg.style.color='#ef4444'; msg.textContent=d.message||'کد نامعتبر';
    }
  };
  x.ontimeout=x.onerror=function(){
    msg.style.color='#f59e0b'; msg.textContent='خطا: '+SERVER;
  };
  x.send(JSON.stringify({activation_code:code, screen_code:CODE}));
}

// Samsung Remote: Enter (keyCode 13) + Return (keyCode 10009)
document.addEventListener('keydown', function(e) {
  if (e.keyCode===13 || e.keyCode===10009) {
    var a=document.getElementById('act');
    if(a) doActivate();
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
