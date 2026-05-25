<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <div>
    <h1 style="font-size:20px;font-weight:800;color:#fff;">
      <i class="fas fa-microchip" style="color:#ef4444;margin-left:10px;"></i>Transcoder — RTSP / RTMP → HLS
    </h1>
    <p style="font-size:12px;color:#475569;margin-top:4px;">
      تبدیل استریم‌های زنده به HLS برای نمایش در مرورگر و TV
    </p>
  </div>
  <?php if ($ffmpegOk): ?>
  <button onclick="document.getElementById('newStreamModal').classList.remove('hidden')"
    class="btn-primary text-sm flex items-center gap-2">
    <i class="fas fa-play text-xs"></i> استریم جدید
  </button>
  <?php endif; ?>
</div>

<!-- FFmpeg Status -->
<div style="border-radius:14px;padding:18px 20px;margin-bottom:20px;display:flex;align-items:center;gap:16px;
  <?= $ffmpegOk
    ? 'background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.25);'
    : 'background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.25);' ?>">
  <div style="width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
    <?= $ffmpegOk ? 'background:rgba(34,197,94,.12);' : 'background:rgba(239,68,68,.12);' ?>">
    <i class="fas fa-microchip" style="font-size:22px;color:<?= $ffmpegOk ? '#4ade80' : '#f87171' ?>;"></i>
  </div>
  <div style="flex:1;">
    <div style="font-size:15px;font-weight:700;color:#fff;margin-bottom:4px;">
      <?= $ffmpegOk ? '✅ FFmpeg نصب است' : '❌ FFmpeg نصب نیست' ?>
    </div>
    <?php if ($ffmpegOk): ?>
    <div style="font-size:12px;color:#64748b;">
      نسخه: <code style="color:#4ade80;"><?= e($ffmpegVersion) ?></code> ·
      مسیر: <code style="color:#64748b;"><?= e($ffmpegBin) ?></code>
    </div>
    <?php else: ?>
    <div style="font-size:12px;color:#94a3b8;margin-bottom:8px;">
      برای فعال کردن Transcoder باید Docker را rebuild کنید:
    </div>
    <pre style="background:#0a0a14;border-radius:8px;padding:10px;font-size:12px;color:#f97316;margin:0;direction:ltr;text-align:left;">docker compose down && docker compose up -d --build</pre>
    <?php endif; ?>
  </div>
</div>

<?php if ($ffmpegOk): ?>

<!-- آمار سریع -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
  <?php
  $active = count($sessions);
  $total  = count($channels);
  foreach ([
    ['استریم فعال', $active, 'fa-signal', '#ef4444'],
    ['کانال RTSP', $total, 'fa-satellite-dish', '#f97316'],
    ['HLS segments', count(glob($hlsDir . '/*/*.ts') ?: []), 'fa-film', '#a855f7'],
    ['CPU FFmpeg', function_exists('sys_getloadavg') ? round(sys_getloadavg()[0]*100/4) . '%' : '—', 'fa-microchip', '#22c55e'],
  ] as [$l,$v,$ic,$c]):
  ?>
  <div style="background:#16161f;border:1px solid rgba(255,255,255,.07);border-top:3px solid <?=$c?>;border-radius:14px;padding:14px;display:flex;align-items:center;gap:12px;">
    <i class="fas <?=$ic?>" style="color:<?=$c?>;font-size:20px;"></i>
    <div><div style="font-size:22px;font-weight:900;color:#fff;"><?=$v?></div><div style="font-size:11px;color:#64748b;"><?=$l?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- استریم‌های فعال -->
<div class="card mb-5">
  <h2 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:14px;">
    <i class="fas fa-signal text-red-400 ml-2"></i>استریم‌های در حال اجرا
    <span id="refresh-badge" style="font-size:10px;font-weight:400;color:#64748b;margin-right:8px;">بروزرسانی خودکار ۵ ثانیه</span>
  </h2>
  <div id="sessions-list">
    <?php if (empty($sessions)): ?>
    <div style="text-align:center;padding:32px;color:#475569;">
      <i class="fas fa-satellite-dish" style="font-size:40px;display:block;margin-bottom:12px;opacity:.2;"></i>
      هیچ استریمی در حال اجرا نیست
    </div>
    <?php else: ?>
    <?php foreach ($sessions as $name => $s): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid rgba(255,255,255,.05);">
      <div style="width:8px;height:8px;border-radius:50%;background:#ef4444;animation:livePulse 1.5s infinite;flex-shrink:0;"></div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:700;color:#fff;font-size:14px;"><?= e($name) ?></div>
        <div style="font-size:11px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($s['input']) ?></div>
        <div style="font-size:10px;color:#475569;margin-top:2px;">شروع: <?= e($s['started_at']) ?> · PID: <?= $s['pid'] ?> · کیفیت: <?= e($s['quality']) ?></div>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0;">
        <button onclick="previewStream('<?= e($s['m3u8']) ?>','<?= e($name) ?>')"
          class="btn-ghost text-xs px-3 py-1.5">
          <i class="fas fa-play text-green-400 text-xs"></i> پیش‌نمایش
        </button>
        <button onclick="copyHls('<?= e(($_SERVER['HTTP_HOST']??'localhost') . $s['m3u8']) ?>')"
          class="btn-ghost text-xs px-3 py-1.5">
          <i class="fas fa-copy text-blue-400 text-xs"></i> لینک HLS
        </button>
        <button onclick="viewLog('<?= e($name) ?>')"
          class="btn-ghost text-xs px-3 py-1.5">
          <i class="fas fa-file-lines text-yellow-400 text-xs"></i> لاگ
        </button>
        <form method="POST" action="/admin/transcoder/stop/<?= e($name) ?>" class="inline">
          <?= csrf_field() ?>
          <button type="submit" class="btn-ghost text-xs px-3 py-1.5" onclick="return confirm('توقف «<?= e($name) ?>»؟')">
            <i class="fas fa-stop text-red-400 text-xs"></i> توقف
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- کانال‌های RTSP/RTMP -->
<?php if (!empty($channels)): ?>
<div class="card mb-5">
  <h2 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:14px;">
    <i class="fas fa-list text-orange-400 ml-2"></i>کانال‌های RTSP/RTMP قابل Transcode
  </h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:10px;">
    <?php foreach ($channels as $ch): ?>
    <div style="background:#0d0d14;border:1px solid rgba(255,255,255,.06);border-radius:10px;padding:14px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <div style="font-weight:600;color:#fff;font-size:13px;"><?= e($ch['name']) ?></div>
        <span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:rgba(239,68,68,.1);color:#f87171;border:1px solid rgba(239,68,68,.3);"><?= strtoupper(e($ch['protocol'])) ?></span>
      </div>
      <div style="font-size:11px;color:#475569;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:10px;"><?= e($ch['stream_url']) ?></div>
      <button onclick="quickTranscode('<?= e(addslashes($ch['stream_url'])) ?>','<?= e(addslashes($ch['name'])) ?>')"
        style="width:100%;padding:7px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.3);border-radius:8px;color:#f97316;cursor:pointer;font-size:12px;font-weight:600;font-family:'Vazirmatn',sans-serif;">
        <i class="fas fa-play text-xs ml-1"></i> Transcode این کانال
      </button>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php endif; /* ffmpegOk */ ?>

<!-- راهنمای نصب FFmpeg -->
<?php if (!$ffmpegOk): ?>
<div class="card">
  <h2 style="font-size:14px;font-weight:700;color:#fff;margin-bottom:14px;">
    <i class="fas fa-wrench text-yellow-400 ml-2"></i>نحوه نصب FFmpeg
  </h2>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
    <div style="background:#0d0d14;border:1px solid rgba(255,255,255,.06);border-radius:10px;padding:14px;">
      <h3 style="font-size:12px;font-weight:700;color:#f97316;margin-bottom:10px;">روش ۱: Rebuild Docker (پیشنهادی)</h3>
      <pre style="font-size:11px;color:#e2e8f0;direction:ltr;text-align:left;margin:0;background:transparent;">cd D:\duc\signage-cms
docker compose down
docker compose up -d --build</pre>
      <p style="font-size:11px;color:#64748b;margin-top:8px;">Dockerfile به‌روز شده FFmpeg دارد ✅</p>
    </div>
    <div style="background:#0d0d14;border:1px solid rgba(255,255,255,.06);border-radius:10px;padding:14px;">
      <h3 style="font-size:12px;font-weight:700;color:#60a5fa;margin-bottom:10px;">روش ۲: نصب سریع (بدون rebuild)</h3>
      <pre style="font-size:11px;color:#e2e8f0;direction:ltr;text-align:left;margin:0;background:transparent;">docker exec signage_php \
  apk add --no-cache ffmpeg</pre>
      <p style="font-size:11px;color:#64748b;margin-top:8px;">موقت است — بعد از restart از بین می‌رود</p>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal استریم جدید -->
<div id="newStreamModal" class="modal-overlay hidden">
  <div class="modal max-w-lg">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="font-weight:700;color:#fff;"><i class="fas fa-play text-red-400 ml-2"></i>استریم جدید</h3>
      <button onclick="document.getElementById('newStreamModal').classList.add('hidden')" style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;">&times;</button>
    </div>
    <form method="POST" action="/admin/transcoder/start" class="space-y-3">
      <?= csrf_field() ?>
      <div>
        <label class="form-label">آدرس استریم *</label>
        <input type="text" id="inp-url" name="input_url" class="form-input" required
          placeholder="rtsp://192.168.1.100:554/stream" style="font-family:monospace;font-size:13px;">
      </div>
      <div>
        <label class="form-label">نام استریم (انگلیسی، بدون فاصله)</label>
        <input type="text" name="stream_name" class="form-input"
          placeholder="lobby-cam1" pattern="[a-z0-9_\-]+">
        <p style="font-size:11px;color:#64748b;margin-top:4px;">آدرس HLS: /hls/<strong>stream-name</strong>/index.m3u8</p>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="form-label">کیفیت خروجی</label>
          <select name="quality" class="form-input">
            <option value="low">پایین (480p · 800kbps)</option>
            <option value="medium" selected>متوسط (720p · 2.5Mbps)</option>
            <option value="high">بالا (1080p · 5Mbps)</option>
            <option value="copy">کپی بدون تبدیل</option>
          </select>
        </div>
        <div>
          <label class="form-label">صدا</label>
          <select name="audio" class="form-input">
            <option value="include">پخش صدا</option>
            <option value="mute">بدون صدا</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px;padding-top:8px;">
        <button type="submit" class="btn-primary flex-1 py-2.5">
          <i class="fas fa-play text-xs ml-1"></i> شروع Transcode
        </button>
        <button type="button" onclick="document.getElementById('newStreamModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal پیش‌نمایش -->
<div id="previewModal" class="modal-overlay hidden">
  <div class="modal" style="max-width:900px;background:#000;padding:0;overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:#111;">
      <span id="preview-title" style="color:#fff;font-size:13px;font-weight:600;"></span>
      <button onclick="closePreview()" style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;">&times;</button>
    </div>
    <div style="aspect-ratio:16/9;background:#000;">
      <video id="preview-vid" autoplay muted controls style="width:100%;height:100%;background:#000;"></video>
    </div>
    <div style="padding:10px 16px;background:#111;display:flex;align-items:center;gap:10px;">
      <code id="preview-url" style="font-size:11px;color:#64748b;flex:1;direction:ltr;"></code>
      <button onclick="copyPreviewUrl()" style="padding:6px 12px;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);border-radius:8px;color:#60a5fa;cursor:pointer;font-size:11px;">
        <i class="fas fa-copy text-xs ml-1"></i>کپی
      </button>
    </div>
  </div>
</div>

<!-- Modal لاگ -->
<div id="logModal" class="modal-overlay hidden">
  <div class="modal" style="max-width:800px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <h3 style="font-weight:700;color:#fff;">لاگ FFmpeg</h3>
      <button onclick="document.getElementById('logModal').classList.add('hidden')" style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;">&times;</button>
    </div>
    <pre id="log-content" style="background:#0a0a14;border-radius:8px;padding:14px;font-size:11px;color:#e2e8f0;max-height:400px;overflow-y:auto;direction:ltr;text-align:left;white-space:pre-wrap;word-break:break-all;"></pre>
    <button onclick="refreshLog()" class="btn-ghost text-xs mt-3"><i class="fas fa-rotate text-xs ml-1"></i> بروزرسانی</button>
  </div>
</div>

<style>
@keyframes livePulse { 0%,100%{opacity:1}50%{opacity:.3} }
</style>

<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"></script>
<script>
var _hlsInstance = null;
var _currentLogName = null;

// ─── پیش‌نمایش HLS ────────────────────────────────────────────
function previewStream(url, name) {
  var base = window.location.protocol + '//' + window.location.host;
  var fullUrl = url.startsWith('http') ? url : base + url;
  document.getElementById('previewModal').classList.remove('hidden');
  document.getElementById('preview-title').textContent = '📡 ' + name;
  document.getElementById('preview-url').textContent = fullUrl;
  var vid = document.getElementById('preview-vid');
  if (_hlsInstance) { _hlsInstance.destroy(); _hlsInstance = null; }
  if (Hls.isSupported()) {
    _hlsInstance = new Hls({ lowLatencyMode: true });
    _hlsInstance.loadSource(fullUrl);
    _hlsInstance.attachMedia(vid);
  } else {
    vid.src = fullUrl;
    vid.play().catch(function(){});
  }
}

function closePreview() {
  document.getElementById('previewModal').classList.add('hidden');
  var vid = document.getElementById('preview-vid');
  vid.pause(); vid.src = '';
  if (_hlsInstance) { _hlsInstance.destroy(); _hlsInstance = null; }
}

function copyPreviewUrl() {
  navigator.clipboard.writeText(document.getElementById('preview-url').textContent)
    .then(function(){ showToast('success','لینک HLS کپی شد'); });
}

function copyHls(url) {
  var base = window.location.protocol + '//' + window.location.host;
  var fullUrl = url.startsWith('http') ? url : base + '/hls/' + url + '/index.m3u8';
  navigator.clipboard.writeText(fullUrl).then(function(){ showToast('success','لینک HLS کپی شد'); });
}

// ─── لاگ ─────────────────────────────────────────────────────
function viewLog(name) {
  _currentLogName = name;
  document.getElementById('logModal').classList.remove('hidden');
  refreshLog();
}

function refreshLog() {
  if (!_currentLogName) return;
  fetch('/admin/transcoder/log/' + _currentLogName)
    .then(function(r){ return r.text(); })
    .then(function(t){
      var el = document.getElementById('log-content');
      el.textContent = t || 'لاگ خالی است';
      el.scrollTop = el.scrollHeight;
    });
}

// ─── شروع سریع از کانال ──────────────────────────────────────
function quickTranscode(url, name) {
  document.getElementById('inp-url').value = url;
  document.querySelector('[name="stream_name"]').value = name
    .toLowerCase().replace(/[^a-z0-9]/g,'-').replace(/-+/g,'-').slice(0,20);
  document.getElementById('newStreamModal').classList.remove('hidden');
}

// ─── بروزرسانی خودکار ────────────────────────────────────────
setInterval(function() {
  var badge = document.getElementById('refresh-badge');
  if (badge) badge.style.color = '#f97316';
  setTimeout(function(){
    location.reload();
  }, 500);
}, 30000);
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
