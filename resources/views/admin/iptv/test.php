<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<style>
  .tt-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:16px;margin-bottom:14px;}
  .tt-mono{font-family:ui-monospace,monospace;direction:ltr;text-align:left;}
  .tt-tv{background:#000;border-radius:12px;border:6px solid #1e1e2a;max-width:760px;aspect-ratio:16/9;width:100%;}
  .tt-tv video{width:100%;height:100%;display:block;border-radius:6px;background:#000;}
  .tt-note{font-size:12px;color:#94a3b8;line-height:2;}
</style>

<div style="margin-bottom:18px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-tv" style="color:#22c55e;margin-left:10px;"></i>تلویزیون آزمایشی IPTV
  </h1>
  <p class="tt-note" style="margin-top:4px;">آدرس استریم را بدهید؛ در همین صفحه پخش می‌شود و سرور هم مشخصات آن را می‌خواند. اگر خوب بود، کانالش کنید.</p>
</div>

<div class="tt-card">
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <input id="tt-url" class="form-input tt-mono" style="flex:1;min-width:260px;"
           placeholder="http://…/stream.m3u8  یا  http://…/stream/channel/…  یا  udp://@239.1.1.2:3200">
    <button class="btn-primary text-sm" onclick="play()"><i class="fas fa-play ml-1"></i>پخش</button>
    <button class="btn-ghost text-sm" onclick="probe()"><i class="fas fa-magnifying-glass ml-1"></i>مشخصات (سرور)</button>
  </div>
  <div id="tt-msg" class="tt-note" style="margin-top:8px;"></div>
</div>

<div class="tt-card">
  <div class="tt-tv"><video id="tt-vid" controls autoplay muted playsinline></video></div>
  <div id="tt-info" class="tt-note tt-mono" style="margin-top:10px;"></div>
</div>

<div class="tt-card" id="tt-save" style="display:none;">
  <div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:8px;">افزودن به کانال‌های IPTV</div>
  <form method="post" action="/admin/iptv" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
    <?= csrf_field() ?>
    <input type="hidden" name="stream_url" id="tt-surl">
    <input type="hidden" name="protocol" id="tt-proto" value="hls">
    <input name="name" class="form-input" style="max-width:260px;" placeholder="نام کانال" required>
    <input name="category" class="form-input" style="max-width:180px;" placeholder="گروه (مثلا اخبار)">
    <button class="btn-primary text-sm">افزودن</button>
  </form>
</div>

<script src="/assets/vendor/hls/hls.min.js"></script>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const $ = id => document.getElementById(id);
let hls = null;

function guessProto(u) { return /\.m3u8(\?|$)/i.test(u) ? 'hls' : /^rtsp:/i.test(u) ? 'rtsp' : /^rtmp:/i.test(u) ? 'rtmp' : /^(udp|rtp):/i.test(u) ? 'udp' : 'http'; }

function play() {
  const u = $('tt-url').value.trim(); if (!u) return;
  const v = $('tt-vid'); $('tt-msg').textContent = '';
  if (hls) { hls.destroy(); hls = null; }
  if (/^(udp|rtp|rtsp|rtmp):/i.test(u)) {
    $('tt-msg').textContent = 'مرورگر این پروتکل را پخش نمی‌کند — «مشخصات (سرور)» را بزنید تا سرور امتحان کند.';
    return;
  }
  if (/\.m3u8(\?|$)/i.test(u) && window.Hls && Hls.isSupported()) {
    hls = new Hls({ lowLatencyMode: true });
    hls.on(Hls.Events.ERROR, (e, d) => { if (d.fatal) $('tt-msg').textContent = 'خطای پخش HLS: ' + d.details; });
    hls.loadSource(u); hls.attachMedia(v);
  } else {
    v.src = u;
  }
  v.onerror = () => { $('tt-msg').textContent = 'مرورگر نتوانست پخش کند. اگر استریم MPEG-TS خام (مثل پروفایل pass تی‌وی‌هدند) است، مرورگر نمی‌پخشد؛ «مشخصات (سرور)» را بزنید.'; };
  v.play().catch(() => {});
  $('tt-surl').value = u; $('tt-proto').value = guessProto(u); $('tt-save').style.display = '';
}

async function probe() {
  const u = $('tt-url').value.trim(); if (!u) return;
  $('tt-info').textContent = 'در حال آزمایش روی سرور…';
  try {
    const r = await fetch('/admin/iptv/test/probe', { method: 'POST', credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF, 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ url: u }) });
    const d = await r.json().catch(() => ({}));
    if (!r.ok || d.success === false) throw new Error(d.message || 'خطا');
    const x = d.data || {};
    if (x.available === false) { $('tt-info').textContent = d.message; return; }
    $('tt-info').innerHTML = 'format: ' + (x.format || '?') + '<br>' + (x.streams || []).map(s =>
      `${s.type}: ${s.codec}` + (s.width ? ` ${s.width}x${s.height}` : '') + (s.fps && s.fps !== '0/0' ? ` @${s.fps}` : '') +
      (s.bitrate ? ` ${Math.round(s.bitrate / 1000)}kbps` : '')).join('<br>');
    $('tt-surl').value = u; $('tt-proto').value = guessProto(u); $('tt-save').style.display = '';
  } catch (e) { $('tt-info').textContent = '✗ ' + e.message; }
}
</script>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
