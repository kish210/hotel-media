<?php
/**
 * SignageCMS Player — Kiosk Profile
 * Touch screen optimized with idle/active states
 */
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kiosk — <?= e($screen['name'] ?? '') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;900&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
body{background:#000;overflow:hidden;width:100vw;height:100vh;font-family:'Vazirmatn',sans-serif;cursor:none;}
#slide{position:absolute;inset:0;transition:opacity 0.5s;}
#slide img,#slide video{width:100%;height:100%;object-fit:cover;}
#idle-overlay{position:absolute;inset:0;background:rgba(0,0,0,0.7);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:10;transition:opacity 0.5s;}
#idle-overlay h1{font-size:clamp(32px,6vw,72px);font-weight:900;color:#fff;margin-bottom:16px;}
#idle-overlay p{font-size:clamp(16px,3vw,28px);color:rgba(255,255,255,0.5);}
#tap-hint{position:absolute;bottom:40px;left:50%;transform:translateX(-50%);font-size:18px;color:rgba(255,255,255,0.3);animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{opacity:0.3}50%{opacity:0.8}}
#clock{position:absolute;top:20px;right:20px;font-size:32px;font-weight:900;color:rgba(255,255,255,0.8);font-family:monospace;z-index:5;}
</style>
</head>
<body>
<div id="slide"><div style="color:#555;text-align:center;padding-top:40%;font-size:18px;">در حال بارگذاری...</div></div>
<div id="idle-overlay">
  <h1>به سیستم خوش آمدید</h1>
  <p>برای شروع لمس کنید</p>
</div>
<div id="tap-hint">👆 برای شروع لمس کنید</div>
<div id="clock">--:--</div>

<script>
var p=[], ci=0, t=null, idle=true, idleTimer=null;
var S='<?= e($screen['code']??'') ?>', U='<?= rtrim(env('APP_URL',''), '/') ?>';

// Clock
setInterval(function(){
  var n=new Date();
  document.getElementById('clock').textContent=
    String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0');
},1000);

// Touch/click handler
document.addEventListener('click',function(){
  if(idle){idle=false;document.getElementById('idle-overlay').style.opacity='0';setTimeout(function(){document.getElementById('idle-overlay').style.display='none';},500);}
  resetIdleTimer();
});

function resetIdleTimer(){
  clearTimeout(idleTimer);
  idleTimer=setTimeout(function(){
    idle=true;
    var o=document.getElementById('idle-overlay');
    o.style.display='flex';
    setTimeout(function(){o.style.opacity='1';},50);
  },120000); // 2 minutes
}

// Playlist
function load(){
  fetch(U+'/api/v1/screens/'+S+'/playlist')
  .then(function(r){return r.json()})
  .then(function(d){
    if(d.success&&d.data&&d.data.items&&d.data.items.length){p=d.data.items;show(0);}
    else setTimeout(load,30000);
  }).catch(function(){setTimeout(load,15000);});
}

function show(i){
  clearTimeout(t);
  var it=p[i],src=it.file_url||it.file_path||it.url||'',tp=it.type||'image';
  var el=document.getElementById('slide'),dur=(it.duration||10)*1000;
  if(tp==='image'){el.innerHTML='<img src="'+src+'" onerror="next()">';t=setTimeout(next,dur);}
  else if(tp==='video'){el.innerHTML='<video src="'+src+'" autoplay muted playsinline onended="next()"></video>';if(dur>0)t=setTimeout(next,dur);}
  else{el.innerHTML='<iframe src="'+src+'"></iframe>';t=setTimeout(next,dur);}
}
function next(){ci=(ci+1)%p.length;show(ci);}

setInterval(function(){
  fetch(U+'/api/v1/screens/'+S+'/heartbeat',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'})
  .then(function(r){return r.json()}).catch(function(){});
},15000);

<?php if(($screen['status']??'')==='active'): ?>load();resetIdleTimer();<?php endif; ?>
</script>
</body>
</html>
