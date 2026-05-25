<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-satellite-dish" style="color:#ef4444;margin-left:10px;"></i>مدیریت IPTV
  </h1>
  <div style="display:flex;gap:8px;">
    <button onclick="document.getElementById('importModal').classList.remove('hidden')"
      class="btn-ghost text-sm flex items-center gap-1.5">
      <i class="fas fa-file-import text-xs text-yellow-400"></i> ایمپورت M3U
    </button>
    <button onclick="document.getElementById('addModal').classList.remove('hidden')"
      class="btn-primary text-sm flex items-center gap-1.5">
      <i class="fas fa-plus text-xs"></i> کانال جدید
    </button>
  </div>
</div>

<!-- آمار -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
  <?php
  $cats = count(array_unique(array_column($channels ?? [],'category')));
  foreach([
    ['کل کانال‌ها', count($channels??[]), 'fa-tv','#f97316'],
    ['RTSP/RTMP', count(array_filter($channels??[],fn($c)=>in_array($c['protocol']??'',['rtsp','rtmp']))), 'fa-signal','#ef4444'],
    ['HLS', count(array_filter($channels??[],fn($c)=>($c['protocol']??'')==='hls')), 'fa-play-circle','#22c55e'],
    ['دسته‌بندی', $cats, 'fa-folder','#a855f7'],
  ] as [$l,$v,$ic,$c]):
  ?>
  <div style="background:#16161f;border:1px solid rgba(255,255,255,.07);border-top:3px solid <?=$c?>;border-radius:14px;padding:14px;display:flex;align-items:center;gap:12px;">
    <i class="fas <?=$ic?>" style="color:<?=$c?>;font-size:22px;"></i>
    <div><div style="font-size:24px;font-weight:900;color:#fff;"><?=$v?></div>
    <div style="font-size:11px;color:#64748b;"><?=$l?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Transcoder status -->
<div style="background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.15);border-radius:12px;padding:14px;margin-bottom:16px;display:flex;align-items:center;gap:12px;">
  <i class="fas fa-microchip" style="color:#ef4444;font-size:20px;"></i>
  <div>
    <div style="font-size:13px;font-weight:700;color:#fff;">Transcoder (RTSP → HLS)</div>
    <div style="font-size:11px;color:#64748b;">
      RTSP و RTMP به HLS تبدیل می‌شن — نیاز به FFmpeg در سرور دارد.
      <a href="/admin/transcoder" style="color:#f87171;text-decoration:none;">تنظیمات ←</a>
    </div>
  </div>
  <div id="ffmpeg-status" style="margin-right:auto;font-size:11px;color:#64748b;">در حال بررسی...</div>
</div>

<!-- جدول -->
<div class="card overflow-hidden">
  <div style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;gap:8px;">
    <input type="text" id="search-ch" class="form-input" style="max-width:200px;font-size:12px;" placeholder="🔍 جستجو..." oninput="searchChannels(this.value)">
    <div style="display:flex;gap:4px;margin-right:auto;">
      <?php foreach(array_unique(array_column($channels??[],'category')) as $cat): ?>
      <button onclick="filterCat('<?= e($cat) ?>')" data-cat="<?= e($cat) ?>"
        style="padding:4px 10px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:transparent;color:#64748b;cursor:pointer;font-size:11px;font-family:'Vazirmatn',sans-serif;">
        <?= e($cat) ?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
  <table class="w-full text-sm" id="ch-table">
    <thead><tr style="border-bottom:1px solid rgba(255,255,255,.06);font-size:11px;color:#475569;text-transform:uppercase;">
      <th style="text-align:right;padding:10px 16px;width:40px;">#</th>
      <th style="text-align:right;padding:10px;">کانال</th>
      <th style="text-align:right;padding:10px;">پروتکل</th>
      <th style="text-align:right;padding:10px;">دسته</th>
      <th style="text-align:right;padding:10px;">آدرس</th>
      <th style="padding:10px;"></th>
    </tr></thead>
    <tbody>
      <?php foreach ($channels ?? [] as $i => $ch): ?>
      <tr class="ch-row" data-name="<?= strtolower(e($ch['name'])) ?>" data-cat="<?= e($ch['category']??'') ?>"
          style="border-bottom:1px solid rgba(255,255,255,.04);"
          onmouseenter="this.style.background='rgba(255,255,255,.02)'"
          onmouseleave="this.style.background=''">
        <td style="padding:10px 16px;color:#475569;font-size:11px;"><?=$i+1?></td>
        <td style="padding:10px;">
          <div style="display:flex;align-items:center;gap:10px;">
            <?php if (!empty($ch['logo_url'])): ?>
            <img src="<?=e($ch['logo_url'])?>" style="width:30px;height:30px;object-fit:contain;border-radius:5px;background:#111;" onerror="this.style.display='none'">
            <?php else: ?>
            <div style="width:30px;height:30px;border-radius:5px;background:rgba(239,68,68,.1);display:flex;align-items:center;justify-content:center;"><i class="fas fa-tv" style="color:#ef4444;font-size:12px;"></i></div>
            <?php endif; ?>
            <span style="font-weight:600;color:#fff;"><?=e($ch['name'])?></span>
          </div>
        </td>
        <td style="padding:10px;">
          <?php $p=$ch['protocol']??'hls'; $pc=match($p){'rtsp','rtmp'=>'#ef4444','hls'=>'#22c55e',default=>'#64748b'}; ?>
          <span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:<?=$pc?>18;color:<?=$pc?>;border:1px solid <?=$pc?>44;"><?=strtoupper($p)?></span>
        </td>
        <td style="padding:10px;color:#64748b;font-size:12px;"><?=e($ch['category']??'—')?></td>
        <td style="padding:10px;color:#475569;font-size:11px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=e($ch['stream_url'])?></td>
        <td style="padding:10px;">
          <div style="display:flex;gap:3px;">
            <button onclick="testStream('<?=e(addslashes($ch['stream_url']))?>','<?=e(addslashes($ch['name']))?>')"
              class="btn-ghost text-xs px-2 py-1" title="تست"><i class="fas fa-play text-green-400 text-xs"></i></button>
            <form method="POST" action="/admin/iptv/<?=$ch['id']?>/delete" class="inline">
              <?=csrf_field()?><button type="submit" class="btn-ghost text-xs px-2 py-1" onclick="return confirm('حذف؟')"><i class="fas fa-trash text-red-400 text-xs"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($channels)): ?>
      <tr><td colspan="6" style="text-align:center;padding:48px;color:#475569;">
        <i class="fas fa-satellite-dish" style="font-size:40px;display:block;margin-bottom:12px;opacity:.2;"></i>
        کانالی اضافه نشده
      </td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal کانال جدید -->
<div id="addModal" class="modal-overlay hidden">
  <div class="modal max-w-md">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="font-weight:700;color:#fff;">کانال جدید</h3>
      <button onclick="document.getElementById('addModal').classList.add('hidden')" style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;">&times;</button>
    </div>
    <form method="POST" action="/admin/iptv" class="space-y-3">
      <?=csrf_field()?>
      <div><label class="form-label">نام کانال *</label><input type="text" name="name" class="form-input" required></div>
      <div><label class="form-label">آدرس استریم *</label>
        <input type="text" name="stream_url" class="form-input" required placeholder="https://stream.m3u8 یا rtsp://...">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">پروتکل</label>
          <select name="protocol" class="form-input">
            <option value="hls">HLS (m3u8)</option>
            <option value="rtsp">RTSP</option>
            <option value="rtmp">RTMP</option>
            <option value="http">HTTP</option>
          </select>
        </div>
        <div><label class="form-label">دسته</label><input type="text" name="category" class="form-input" placeholder="news"></div>
      </div>
      <div><label class="form-label">لوگو URL</label><input type="url" name="logo_url" class="form-input"></div>
      <div style="display:flex;gap:10px;padding-top:8px;">
        <button type="submit" class="btn-primary flex-1 py-2.5">افزودن</button>
        <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal ایمپورت M3U -->
<div id="importModal" class="modal-overlay hidden">
  <div class="modal max-w-md">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="font-weight:700;color:#fff;"><i class="fas fa-file-import text-yellow-400 ml-2"></i>ایمپورت M3U</h3>
      <button onclick="document.getElementById('importModal').classList.add('hidden')" style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;">&times;</button>
    </div>
    <form method="POST" action="/admin/iptv/import" class="space-y-3" enctype="multipart/form-data" id="importForm">
      <?=csrf_field()?>
      <div style="display:flex;gap:4px;background:rgba(0,0,0,.3);border-radius:8px;padding:3px;">
        <button type="button" onclick="impType('url')" id="ibt-url" style="flex:1;padding:7px;border-radius:6px;border:none;cursor:pointer;font-size:12px;font-weight:600;font-family:'Vazirmatn',sans-serif;background:rgba(249,115,22,.2);color:#f97316;">🔗 از URL</button>
        <button type="button" onclick="impType('file')" id="ibt-file" style="flex:1;padding:7px;border-radius:6px;border:none;cursor:pointer;font-size:12px;font-weight:600;font-family:'Vazirmatn',sans-serif;background:transparent;color:#64748b;">📁 فایل</button>
      </div>
      <div id="isc-url"><label class="form-label">آدرس M3U</label>
        <input type="url" name="m3u_url" class="form-input" placeholder="http://example.com/playlist.m3u8"></div>
      <div id="isc-file" style="display:none;"><label class="form-label">فایل M3U</label>
        <input type="file" name="m3u_file" class="form-input" accept=".m3u,.m3u8,.txt"></div>
      <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:8px;padding:10px;font-size:11px;color:#94a3b8;">
        ⚠ حداکثر ۵۰۰ کانال ایمپورت می‌شه.
      </div>
      <div style="display:flex;gap:10px;padding-top:8px;">
        <button type="submit" class="btn-primary flex-1 py-2.5">شروع ایمپورت</button>
        <button type="button" onclick="document.getElementById('importModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal تست -->
<div id="testModal" class="modal-overlay hidden">
  <div class="modal" style="max-width:800px;background:#000;padding:0;overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:#111;">
      <span id="test-ch-name" style="color:#fff;font-size:13px;font-weight:600;"></span>
      <button onclick="closeTest()" style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;">&times;</button>
    </div>
    <div style="aspect-ratio:16/9;"><video id="test-vid" autoplay muted controls style="width:100%;height:100%;background:#000;"></video></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"></script>
<script>
// فیلتر و جستجو
function searchChannels(q) {
  document.querySelectorAll('.ch-row').forEach(r => {
    r.style.display = r.dataset.name.includes(q.toLowerCase()) ? '' : 'none';
  });
}
function filterCat(cat) {
  document.querySelectorAll('.ch-row').forEach(r => {
    r.style.display = (!cat || r.dataset.cat === cat) ? '' : 'none';
  });
}

// ایمپورت type
function impType(t) {
  document.getElementById('isc-url').style.display = t==='url' ? '' : 'none';
  document.getElementById('isc-file').style.display = t==='file' ? '' : 'none';
  ['url','file'].forEach(x => {
    const b = document.getElementById('ibt-'+x);
    b.style.background = x===t ? 'rgba(249,115,22,.2)' : 'transparent';
    b.style.color = x===t ? '#f97316' : '#64748b';
  });
}

// تست پخش
var _hls = null;
function testStream(url, name) {
  document.getElementById('testModal').classList.remove('hidden');
  document.getElementById('test-ch-name').textContent = '📺 ' + name;
  var vid = document.getElementById('test-vid');
  if (_hls) { _hls.destroy(); _hls = null; }
  if (url.match(/\.m3u8/i) && Hls.isSupported()) {
    _hls = new Hls({ lowLatencyMode: true });
    _hls.loadSource(url);
    _hls.attachMedia(vid);
  } else {
    vid.src = url;
    vid.play().catch(function(){});
  }
}
function closeTest() {
  document.getElementById('testModal').classList.add('hidden');
  var vid = document.getElementById('test-vid');
  vid.pause(); vid.src = '';
  if (_hls) { _hls.destroy(); _hls = null; }
}

// بررسی FFmpeg
fetch('/api/v1/iptv/status').then(r => r.json()).then(d => {
  const el = document.getElementById('ffmpeg-status');
  if (d.ffmpeg) {
    el.innerHTML = '<span style="color:#22c55e">✅ FFmpeg فعال</span>';
  } else {
    el.innerHTML = '<span style="color:#f59e0b">⚠ FFmpeg نصب نشده (RTSP→HLS کار نمی‌کند)</span>';
  }
}).catch(function() {});
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
