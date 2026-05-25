<?php
/**
 * SignageCMS Player — Minimal Profile
 * Ultra-lightweight: no animations, no JS framework
 * For: Raspberry Pi, weak CPUs, very old browsers
 */
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<title>Player — <?= e($screen['name'] ?? '') ?></title>
<style>
* { margin:0; padding:0; }
body { background:#000; overflow:hidden; width:100vw; height:100vh; }
#c { width:100%; height:100%; }
img, video { width:100%; height:100%; object-fit:cover; display:block; }
iframe { width:100%; height:100%; border:0; }
</style></head>
<body>
<div id="c">
  <?php if (($screen['status']??'') !== 'active'): ?>
  <p style="color:#fff;text-align:center;padding-top:40%;font-size:20px;">
    کد: <?= e($screen['code']??'') ?>
  </p>
  <?php else: ?>
  <p style="color:#888;text-align:center;padding-top:40%;">در حال بارگذاری...</p>
  <?php endif; ?>
</div>
<script>
var p=[], i=0, t=null;
var S='<?= e($screen['code']??'') ?>', U='<?= rtrim(env('APP_URL',''), '/') ?>';
function load(){
  fetch(U+'/api/v1/screens/'+S+'/playlist')
  .then(function(r){return r.json()})
  .then(function(d){
    if(d.success && d.data && d.data.items && d.data.items.length){p=d.data.items;show(0);}
    else setTimeout(load,30000);
  }).catch(function(){setTimeout(load,15000);});
}
function show(n){
  clearTimeout(t);
  var it=p[n], src=it.file_url||it.file_path||it.url||'', tp=it.type||'image';
  var c=document.getElementById('c'), dur=(it.duration||10)*1000;
  if(tp==='image'){c.innerHTML='<img src="'+src+'" onerror="next()">';t=setTimeout(next,dur);}
  else if(tp==='video'){c.innerHTML='<video src="'+src+'" autoplay muted playsinline onended="next()"></video>';if(dur>0)t=setTimeout(next,dur);}
  else{c.innerHTML='<iframe src="'+src+'"></iframe>';t=setTimeout(next,dur);}
}
function next(){i=(i+1)%p.length;show(i);}
setInterval(function(){
  fetch(U+'/api/v1/screens/'+S+'/heartbeat',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'})
  .then(function(r){return r.json()}).then(function(d){
    var cmds=((d.data||{}).commands)||[];
    cmds.forEach(function(c){if(c.command==='reload')load();});
  }).catch(function(){});
},20000);
<?php if (($screen['status']??'') === 'active'): ?>load();<?php endif; ?>
</script>
</body></html>
