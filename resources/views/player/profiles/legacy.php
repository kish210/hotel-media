<?php
/**
 * SignageCMS Player — Legacy Profile
 * Compatible: Android 4+, old TVs, basic browsers
 * No HLS.js, No CSS animations, No Flexbox
 */
$settings = json_decode($screen['settings'] ?? '{}', true) ?: [];
$tickerText = $settings['ticker_text'] ?? '';
?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width">
<title>SignageCMS — <?= e($screen['name'] ?? '') ?></title>
<style>
body,html { margin:0; padding:0; background:#000; overflow:hidden; width:100%; height:100%; }
#wrap { position:relative; width:100%; height:100%; }
#slide { position:absolute; top:0; left:0; width:100%; height:100%; }
#slide img { width:100%; height:100%; }
#slide video { width:100%; height:100%; }
#slide iframe { width:100%; height:100%; border:0; }
#ticker { position:absolute; bottom:0; left:0; right:0; height:36px; background:#000; overflow:hidden; }
#ticker-inner { position:absolute; white-space:nowrap; font-size:16px; color:#fff; line-height:36px; padding:0 16px; }
#act { position:absolute; top:0; left:0; right:0; bottom:0; background:#111; text-align:center; padding-top:20%; }
#act input { font-size:24px; letter-spacing:8px; padding:12px; width:200px; text-align:center; }
#act button { display:block; margin:16px auto; padding:12px 40px; font-size:16px; background:#f97316; color:#fff; border:0; cursor:pointer; }
</style>
</head>
<body>
<div id="wrap">
  <?php if (($screen['status'] ?? '') !== 'active'): ?>
  <div id="act">
    <div style="color:#fff;font-size:20px;margin-bottom:20px;">SignageCMS · <?= e($screen['code'] ?? '') ?></div>
    <input type="text" id="code" maxlength="6" placeholder="کد فعال‌سازی">
    <button onclick="activate()">فعال‌سازی</button>
    <div id="msg" style="color:#ef4444;margin-top:12px;"></div>
  </div>
  <?php else: ?>
  <div id="slide"><div style="color:#fff;text-align:center;padding-top:40%;font-size:18px;">در حال بارگذاری...</div></div>
  <?php if ($tickerText): ?>
  <div id="ticker"><div id="ticker-inner"><?= e($tickerText) ?>&nbsp;&nbsp;&nbsp;<?= e($tickerText) ?></div></div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<script>
var playlist = [], idx = 0, timer = null;
var SCREEN  = '<?= e($screen['code'] ?? '') ?>';
var SERVER = window.location.originAPP_URL',''), '/') ?>';

function load() {
  var xhr = new XMLHttpRequest();
  xhr.open('GET', SERVER + '/api/v1/screens/' + SCREEN + '/playlist', true);
  xhr.onload = function() {
    if (xhr.status === 200) {
      var d = JSON.parse(xhr.responseText);
      if (d.success && d.data && d.data.items && d.data.items.length) {
        playlist = d.data.items;
        play(0);
      } else {
        setTimeout(load, 30000);
      }
    }
  };
  xhr.send();
}

function play(i) {
  if (!playlist.length) return;
  clearTimeout(timer);
  var item = playlist[i];
  var src  = item.file_url || item.file_path || item.url || '';
  var type = item.type || 'image';
  var dur  = (item.duration || 10) * 1000;
  var el   = document.getElementById('slide');

  if (type === 'image') {
    el.innerHTML = '<img src="' + src + '" onerror="next()">';
    timer = setTimeout(next, dur);
  } else if (type === 'video') {
    el.innerHTML = '<video src="' + src + '" autoplay muted onended="next()" onerror="next()"></video>';
    if (dur > 0) timer = setTimeout(next, dur);
  } else {
    el.innerHTML = '<iframe src="' + src + '"></iframe>';
    timer = setTimeout(next, dur);
  }
}

function next() { idx = (idx + 1) % playlist.length; play(idx); }

// Ticker scroll
<?php if ($tickerText): ?>
(function() {
  var t = document.getElementById('ticker-inner');
  var pos = window.innerWidth;
  t.style.left = pos + 'px';
  setInterval(function() {
    pos -= 2;
    if (pos < -t.offsetWidth) pos = window.innerWidth;
    t.style.left = pos + 'px';
  }, 30);
})();
<?php endif; ?>

// Heartbeat
setInterval(function() {
  var xhr = new XMLHttpRequest();
  xhr.open('POST', SERVER + '/api/v1/screens/' + SCREEN + '/heartbeat', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.onload = function() {
    try {
      var d = JSON.parse(xhr.responseText);
      var cmds = (d.data || {}).commands || [];
      cmds.forEach(function(cmd) {
        if (cmd.command === 'reload') load();
        if (cmd.command === 'instant_media' && cmd.data) {
          var ov = document.createElement('div');
          ov.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;z-index:999;background:#000;display:flex;align-items:center;justify-content:center;';
          if (cmd.data.type === 'image') ov.innerHTML = '<img src="' + cmd.data.content + '" style="max-width:100%;max-height:100%;">';
          else ov.innerHTML = '<div style="color:#fff;font-size:32px;text-align:center;padding:40px;">' + cmd.data.content + '</div>';
          document.body.appendChild(ov);
          if (cmd.data.duration > 0) setTimeout(function() { ov.remove(); }, cmd.data.duration * 1000);
        }
      });
    } catch(e) {}
  };
  xhr.send(JSON.stringify({version:'legacy'}));
}, 15000);

<?php if (($screen['status']??'') === 'active'): ?>
load();
<?php endif; ?>

function activate() {
  var code = document.getElementById('code').value.toUpperCase();
  var xhr = new XMLHttpRequest();
  xhr.open('POST', SERVER + '/player/activate', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.onload = function() {
    var d = JSON.parse(xhr.responseText);
    if (d.success) location.reload();
    else document.getElementById('msg').textContent = d.message;
  };
  xhr.send(JSON.stringify({activation_code: code, screen_code: SCREEN}));
}
</script>
</body>
</html>
