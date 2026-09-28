<?php
/**
 * Hotel Media Player — Samsung Orsay/Maple Profile (نسل ۲۰۱۳، پیش از Tizen)
 *
 * چرا جدا از samsung_tv.php:
 *   تلویزیون‌های هتلیِ خانواده‌ی HG..AE690 (ProductCap = Y2013) موتور Maple
 *   روی WebKit ~۵۳۴ دارند، نه Tizen WebEngine. سه فرقِ مرگبار با پروفایل Tizen:
 *     • object-fit از WebKit 537 آمده — اینجا نیست. برای همین تصویر با
 *       background-size:cover (که از WebKit 527 هست) نمایش داده می‌شود، نه <img object-fit>.
 *     • HLS/m3u8 و udp/rtp و AVPlay نیست — فقط تصویر و MP4/WebM مستقیم.
 *     • webapis.js/AVPlay وجود ندارد — حذف شد.
 *   ES5 خالص و بدون classList (برای اطمینان opacity مستقیم ست می‌شود).
 *   مرجع تفکیک دو خانواده: docs/TV-COMPAT-SIGNAGE.md
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
<?php /* width=device-width نه یک عدد ثابت.
        این تلویزیون‌ها رزولوشن یکسانی ندارند: همین مدل در سایت واقعی
        screen.width را ۱۲۸۰ گزارش می‌کند، نه ۱۹۲۰. با چیدمان ثابتِ
        ۱۹۲۰ روی صفحه‌ی ۱۲۸۰ و overflow:hidden، فقط گوشه‌ی بالا-چپِ
        تصویر دیده می‌شود و ویدیو بریده به نظر می‌رسد. */ ?>
<meta name="viewport" content="width=device-width">
<title>Hotel Media — Samsung Orsay</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
/* درصد به‌جای پیکسل ثابت — چیدمان با هر رزولوشنی که تلویزیون
   گزارش کند جور درمی‌آید. درصد از WebKit خیلی قدیمی هم کار می‌کند. */
body,html{width:100%;height:100%;overflow:hidden;background:#000;}
#c{position:relative;width:100%;height:100%;}
/* opacity مستقیم ست می‌شود؛ transition خودش انیمیت می‌کند (بدون classList) */
.slide{position:absolute;top:0;left:0;width:100%;height:100%;opacity:0;background:#000;
       -webkit-transition:opacity .5s;transition:opacity .5s;}
/* تصویر با background-size:cover — چون object-fit روی Maple نیست */
.slide-img{width:100%;height:100%;background-repeat:no-repeat;background-position:center center;
           -webkit-background-size:cover;background-size:cover;}
.slide video{width:100%;height:100%;display:block;background:#000;}
.slide iframe{width:100%;height:100%;border:0;}
#ticker{position:absolute;bottom:0;left:0;width:100%;height:40px;background:rgba(0,0,0,.8);overflow:hidden;<?= $ticker?'':'display:none;'?>}
<?php /* فاصله‌ی پیش از تکرار متن تیکر را JS از عرض واقعی صفحه ست
        می‌کند؛ عدد ثابت روی صفحه‌ی باریک‌تر شکاف بی‌جا می‌ساخت. */ ?>
#tick{position:absolute;top:0;height:40px;white-space:nowrap;font:600 19px/40px Arial,sans-serif;color:#fff;}
#logo{position:absolute;<?= $posMap[$logoPos]??$posMap['bottom-right'] ?>;opacity:.85;<?= $logoUrl?'':'display:none;'?>}
#logo img{width:120px;}
#clock{position:absolute;top:14px;right:14px;padding:7px 16px;background:rgba(0,0,0,.55);border-radius:8px;font:700 28px/1 monospace;color:#fff;<?= $clk?'':'display:none;'?>}
#act{position:absolute;top:0;left:0;width:100%;height:100%;background:#09090f;text-align:center;}
#act-box{position:absolute;top:50%;left:50%;margin:-160px 0 0 -190px;width:380px;background:#111;border-radius:16px;padding:40px;}
#act-inp{font:700 28px/1 monospace;letter-spacing:12px;padding:14px;width:100%;background:#0d0d14;border:2px solid rgba(26,122,196,.4);border-radius:12px;color:#fff;text-align:center;text-transform:uppercase;}
#act-btn{width:100%;margin-top:14px;padding:15px;font-size:17px;background:#1a7ac4;color:#fff;border:0;border-radius:12px;cursor:pointer;}
#act-msg{font-size:13px;margin-top:12px;min-height:20px;color:#ef4444;}
</style>
</head>
<body>
<div id="c">
  <?php if (($screen['status']??'') !== 'active'): ?>
  <div id="act">
    <div id="act-box">
      <div style="font-size:40px;margin-bottom:12px;">📺</div>
      <div style="font-size:20px;font-weight:700;color:#fff;margin-bottom:6px;">Hotel Media</div>
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
/* ES5 خالص — Maple/WebKit 534. نه const/let، نه arrow، نه template literal،
   نه fetch/Promise، نه classList. */
function tvPlay(el) {
  if (!el || !el.play) return;
  try { el.play(); } catch (e) {}
}

var SERVER = window.location.origin;
var CODE   = '<?= e($screen['code']??'') ?>';
var pl = [], ci = 0, tm = null, curSlide = null;
var _serverOffset = 0;

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
setInterval(syncTime, 300000);
setInterval(updateClock, 1000);
<?php endif; ?>

// ─── Ticker ───────────────────────────────────────────────────
<?php if ($ticker): ?>
(function(){
  var t = document.getElementById('tick');
  /* فاصله‌ی پیش از تکرار = یک عرض صفحه، هرچقدر که هست. با عدد ثابت
     ۱۹۲۰ روی تلویزیون ۱۲۸۰ یک شکاف خالیِ طولانی وسط تیکر می‌افتاد. */
  var vw = document.getElementById('c').offsetWidth || 1280;
  t.style.paddingRight = vw + 'px';

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

/* بدون classList: opacity را مستقیم ست می‌کنیم و transition انیمیت می‌کند */
function swapSlide(newDiv) {
  var c = document.getElementById('c');
  c.appendChild(newDiv);
  setTimeout(function(){ newDiv.style.opacity = '1'; }, 50);
  if (curSlide) {
    var old = curSlide;
    setTimeout(function(){
      old.style.opacity = '0';
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

  // ─ IMAGE ─ (background-size:cover به‌جای object-fit)
  if (type === 'image' || src.match(/\.(jpg|jpeg|png|gif|webp)(\?|$)/i)) {
    // پیش‌بارگذاری تا موقع سوییچ، فریمِ خالی دیده نشود
    var pre = new Image();
    pre.onerror = function() { setTimeout(nextItem, 500); };
    pre.onload  = function() {
      var box = document.createElement('div');
      box.className = 'slide-img';
      box.style.backgroundImage = 'url("' + src + '")';
      div.appendChild(box);
      swapSlide(div);
    };
    pre.src = src;
    tm = setTimeout(nextItem, dur);

  // ─ VIDEO ─ فقط MP4/WebM مستقیم. HLS/udp/rtp روی Maple پخش نمی‌شود.
  } else if (type === 'video' || src.match(/\.(mp4|webm|ogv|mov)(\?|$)/i)) {
    // پروتکل‌ها/فرمت‌های ناسازگار را رد کن تا صفحه سیاه نماند
    if (src.match(/\.m3u8(\?|$)/i) || src.indexOf('udp://') === 0 || src.indexOf('rtp://') === 0) {
      setTimeout(nextItem, 500);
      return;
    }
    var vid = document.createElement('video');
    vid.setAttribute('autoplay', '');
    vid.setAttribute('muted', '');
    vid.setAttribute('playsinline', '');
    vid.setAttribute('webkit-playsinline', '');
    vid.setAttribute('preload', 'auto');
    vid.muted = true;
    vid.loop  = false;
    vid.onended = nextItem;
    vid.onerror = function() { setTimeout(nextItem, 1000); };
    vid.oncanplay = function() { tvPlay(vid); };
    vid.src = src;

    div.appendChild(vid);
    swapSlide(div);
    try { vid.load(); } catch(e) {}
    tvPlay(vid);
    setTimeout(function(){ tvPlay(vid); }, 500);

    if (dur > 0 && dur < 7200000) tm = setTimeout(nextItem, dur);

  // ─ IFRAME (صفحه وب) ─ best-effort روی Maple
  } else {
    var ifr = document.createElement('iframe');
    ifr.src = src;
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
  x.send(JSON.stringify({version:'samsung-orsay', item:ci}));
}
setInterval(heartbeat, 15000);

// ─── Instant broadcast ────────────────────────────────────────
var instTm = null;
function showInstant(data) {
  clearInstant();
  var ov = document.createElement('div');
  ov.id = 'inst-ov';
  ov.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;z-index:9999;background:#000;';
  var c = document.getElementById('c');

  if (data.type==='image') {
    var box = document.createElement('div');
    box.style.cssText = 'width:100%;height:100%;background-repeat:no-repeat;background-position:center center;-webkit-background-size:contain;background-size:contain;';
    box.style.backgroundImage = 'url("' + fixUrl(data.content) + '")';
    ov.appendChild(box);
  } else if (data.type==='video') {
    ov.innerHTML = '<video src="'+fixUrl(data.content)+'" autoplay muted playsinline style="width:100%;height:100%;background:#000;" onended="clearInstant()"></video>';
  } else if (data.type==='text') {
    var t={text:'',color:'#fff',bg:'#000'};
    try { t=JSON.parse(data.content); } catch(e) { t.text=data.content; }
    ov.style.background=t.bg||'#000';
    ov.innerHTML='<div style="position:absolute;top:50%;left:0;width:100%;margin-top:-60px;font-size:80px;font-weight:900;color:'+(t.color||'#fff')+';text-align:center;padding:0 60px;">'+(t.text||'')+'</div>';
  }
  c.appendChild(ov);
  var dur=parseInt(data.duration,10)||30;
  if(dur>0) instTm=setTimeout(clearInstant, dur*1000);
}
function clearInstant() {
  clearTimeout(instTm);
  var el=document.getElementById('inst-ov');
  if(el && el.parentNode) el.parentNode.removeChild(el);
}

// ─── Activation ──────────────────────────────────────────────
function doActivate() {
  var code=(document.getElementById('act-inp').value||'').toUpperCase();
  code = code.replace(/^\s+|\s+$/g,'');
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

// Samsung ریموت: Enter (13) + Return (10009 روی مدل‌های جدیدتر، 88/461 روی Orsay)
document.addEventListener('keydown', function(e) {
  if (window.__tvaOwnsKeys) return;
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
<?php else: ?>
<?php if ($clk): ?>syncTime();<?php endif; ?>
<?php endif; ?>
</script>
<!-- صفحه‌کلید عددی فعال‌سازی با ریموت (همه‌ی مدل‌ها) -->
<script src="/assets/js/tv-activate.js"></script>
</body>
</html>
