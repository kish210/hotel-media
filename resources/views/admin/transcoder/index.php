<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<?php
/**
 * ترنسکدر — معادل «رامند» کاماسیستم.
 * کارها از /api/v1/transcoder/* خوانده می‌شوند و هر ۵ ثانیه تازه می‌شوند.
 */
$caps     = $caps     ?? [];
$channels = $channels ?? [];
$files    = $files    ?? [];
$images   = $images   ?? [];
$enc      = $caps['encoders'] ?? [];
$has      = fn(string $e) => in_array($e, $enc, true);
?>
<style>
  .tc-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:16px;}
  .tc-chip{display:inline-flex;align-items:center;gap:6px;font-size:11px;padding:3px 10px;border-radius:999px;margin:0 0 6px 6px;}
  .tc-on{background:rgba(34,197,94,.12);color:#4ade80;} .tc-off{background:rgba(100,116,139,.12);color:#64748b;}
  .tc-warn{background:rgba(245,158,11,.12);color:#fbbf24;}
  .tc-table{width:100%;border-collapse:collapse;font-size:12px;}
  .tc-table th{color:#64748b;font-weight:600;text-align:right;padding:8px;border-bottom:1px solid rgba(255,255,255,.07);white-space:nowrap;}
  .tc-table td{color:#cbd5e1;padding:9px 8px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:top;}
  .tc-badge{font-size:10px;padding:2px 8px;border-radius:8px;white-space:nowrap;}
  .tc-sec{font-size:12px;font-weight:700;color:#94a3b8;margin:18px 0 8px;border-bottom:1px solid rgba(255,255,255,.06);padding-bottom:6px;}
  .tc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px;}
  .tc-grid label{font-size:11px;color:#94a3b8;display:block;}
  .tc-row{display:flex;gap:8px;align-items:center;margin-bottom:6px;flex-wrap:wrap;}
  .tc-mono{font-family:ui-monospace,monospace;direction:ltr;text-align:left;}
  .tc-bar{height:6px;background:rgba(255,255,255,.08);border-radius:4px;overflow:hidden;margin-top:4px;}
  .tc-bar>div{height:100%;background:#22c55e;}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
  <div>
    <h1 style="font-size:20px;font-weight:800;color:#fff;">
      <i class="fas fa-microchip" style="color:#ef4444;margin-left:10px;"></i>ترنسکدر
    </h1>
    <p style="font-size:12px;color:#64748b;margin-top:4px;">
      تبدیل زنده و آفلاین — ورودی ماهواره، دوربین، کارت کپچر یا فایل؛ خروجی HLS چندکیفیتی، UDP multicast، RTMP و SRT
    </p>
  </div>
  <?php if (!empty($caps['ffmpeg'])): ?>
  <button onclick="openEditor()" class="btn-primary text-sm"><i class="fas fa-plus text-xs ml-1"></i>کار جدید</button>
  <?php endif; ?>
</div>

<!-- قابلیت‌های سرور -->
<div class="tc-card" style="margin-bottom:16px;">
  <?php if (empty($caps['ffmpeg'])): ?>
    <div style="color:#f87171;font-weight:700;">ffmpeg روی سرور نصب نیست</div>
    <div style="font-size:12px;color:#94a3b8;margin-top:6px;">روی سرور اجرا کنید: <code class="tc-mono">sudo apt install ffmpeg</code></div>
  <?php else: ?>
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
    <div>
      <div style="font-size:12px;color:#94a3b8;margin-bottom:8px;">
        ffmpeg <code class="tc-mono" style="color:#4ade80;"><?= e($caps['version'] ?? '') ?></code> — قابلیت‌های همین سرور:
      </div>
      <div>
        <?php
        $chips = [
          ['H.264',  $has('libx264')], ['H.265 / HEVC', $has('libx265')], ['MPEG-2', $has('mpeg2video')],
          ['AAC', $has('aac')], ['MP3', $has('libmp3lame')], ['MP2', $has('mp2')],
        ];
        foreach ($chips as [$l, $ok]): ?>
          <span class="tc-chip <?= $ok ? 'tc-on' : 'tc-off' ?>"><i class="fas fa-<?= $ok ? 'check' : 'xmark' ?>"></i><?= $l ?></span>
        <?php endforeach; ?>
      </div>
      <div>
        <?php
        $hw = [
          ['NVIDIA NVENC', $has('h264_nvenc'), !empty($caps['nvidia']), 'کارت NVIDIA پیدا نشد'],
          ['Intel QSV',    $has('h264_qsv'),   !empty($caps['dri']),    '/dev/dri نیست'],
          ['VAAPI',        $has('h264_vaapi'), !empty($caps['dri']),    '/dev/dri نیست'],
        ];
        foreach ($hw as [$l, $built, $hwOk, $why]):
          $cls = $built && $hwOk ? 'tc-on' : ($built ? 'tc-warn' : 'tc-off'); ?>
          <span class="tc-chip <?= $cls ?>" title="<?= $built && !$hwOk ? e($why) : '' ?>">
            <i class="fas fa-bolt"></i><?= $l ?><?= $built && !$hwOk ? ' — سخت‌افزار نیست' : '' ?>
          </span>
        <?php endforeach; ?>
        <span class="tc-chip <?= in_array('v4l2', $caps['devices'] ?? [], true) ? 'tc-on' : 'tc-off' ?>">
          <i class="fas fa-video"></i>کپچر HDMI/کامپوزیت (v4l2)<?= !empty($caps['video_devices']) ? ': ' . e(implode('، ', $caps['video_devices'])) : '' ?>
        </span>
        <span class="tc-chip <?= in_array('decklink', $caps['devices'] ?? [], true) ? 'tc-on' : 'tc-off' ?>">
          <i class="fas fa-video"></i>SDI (DeckLink)
        </span>
      </div>
    </div>
    <button class="btn-ghost text-xs px-3" onclick="refreshCaps()"><i class="fas fa-rotate"></i> بررسی دوباره</button>
  </div>
  <?php endif; ?>
</div>

<!-- کارها -->
<div class="tc-card" style="overflow-x:auto;">
  <table class="tc-table">
    <thead><tr>
      <th>کار</th><th>ورودی</th><th>خروجی</th><th>وضعیت</th><th>کارکرد</th><th></th>
    </tr></thead>
    <tbody id="jobs"><tr><td colspan="6" style="text-align:center;color:#475569;padding:30px;">در حال بارگذاری…</td></tr></tbody>
  </table>
  <p style="font-size:11px;color:#64748b;margin-top:10px;line-height:1.9;">
    کانال زنده تا وقتی متوقفش نکنید روشن می‌ماند: اگر ffmpeg بیفتد یا ورودی ۳۰ ثانیه تصویر ندهد، ناظر (هر ۵ ثانیه) آن را
    دوباره راه می‌اندازد — بعد از قطع برق سرور هم. هر کار فرایند جدای خودش را دارد و خرابی یکی روی بقیه اثر ندارد.
  </p>
</div>

<!-- ویرایشگر -->
<div id="editor" class="hidden" style="position:fixed;top:0;right:0;bottom:0;left:0;background:rgba(0,0,0,.72);z-index:90;overflow-y:auto;padding:24px;">
  <div class="tc-card" style="background:#0f172a;max-width:980px;margin:0 auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <h3 id="edTitle" style="color:#fff;font-weight:800;font-size:16px;">کار جدید</h3>
      <button class="btn-ghost text-xs px-2" onclick="closeEl('editor')"><i class="fas fa-xmark"></i></button>
    </div>

    <div class="tc-sec">عمومی</div>
    <div class="tc-grid">
      <label>نام<input id="f_name" class="form-input" maxlength="120" placeholder="شبکه ۳ — HD"></label>
      <label>نام مسیر (انگلیسی)<input id="f_slug" class="form-input tc-mono" maxlength="50" placeholder="irinn"></label>
      <label>حالت
        <select id="f_mode" class="form-input" onchange="modeChanged()">
          <option value="live">زنده (Live Transcoding)</option>
          <option value="vod">تبدیل فایل (VOD / Offline)</option>
        </select>
      </label>
    </div>

    <div class="tc-sec">ورودی</div>
    <div class="tc-grid">
      <label>نوع ورودی
        <select id="f_kind" class="form-input" onchange="kindChanged()">
          <option value="url">استریم شبکه (UDP، RTP، RTSP، RTMP، HTTP/HLS/DASH، SRT)</option>
          <option value="file">فایل از کتابخانه</option>
          <option value="v4l2">کارت کپچر HDMI / کامپوزیت / DVI</option>
          <option value="decklink">کارت کپچر SDI (DeckLink)</option>
        </select>
      </label>
      <label id="w_url" style="grid-column:span 2;">آدرس ورودی
        <input id="f_url" class="form-input tc-mono" placeholder="udp://@239.1.1.1:1234">
      </label>
      <label id="w_file" style="grid-column:span 2;" class="hidden">فایل
        <select id="f_file" class="form-input">
          <?php foreach ($files as $f): ?>
            <option value="<?= e($f['file_path']) ?>"><?= e($f['name']) ?></option>
          <?php endforeach; ?>
          <?php if (!$files): ?><option value="">ویدیویی در کتابخانه نیست</option><?php endif; ?>
        </select>
      </label>
      <label id="w_dev" style="grid-column:span 2;" class="hidden">دستگاه
        <input id="f_dev" class="form-input tc-mono" list="devlist" placeholder="/dev/video0">
        <datalist id="devlist"><?php foreach ($caps['video_devices'] ?? [] as $d): ?><option value="<?= e($d) ?>"><?php endforeach; ?></datalist>
      </label>
      <label id="w_ch">پر کردن از کانال
        <select id="f_fromch" class="form-input" onchange="fromChannel()">
          <option value="">—</option>
          <?php foreach ($channels as $c): ?>
            <option value="<?= e((string)$c['stream_url']) ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label id="w_rtsp">RTSP از طریق
        <select id="f_rtsp" class="form-input"><option value="tcp">TCP (پایدارتر)</option><option value="udp">UDP</option></select>
      </label>
      <label id="w_loop" class="hidden" style="padding-top:18px;"><input type="checkbox" id="f_loop"> پخش تکراری فایل به‌عنوان کانال</label>
      <label id="w_alsa" class="hidden">صدای کارت (ALSA)<input id="f_alsa" class="form-input tc-mono" placeholder="hw:1,0"></label>
    </div>
    <div style="margin-top:8px;">
      <button class="btn-ghost text-xs px-3" onclick="probe()"><i class="fas fa-magnifying-glass"></i> بررسی ورودی</button>
      <span id="probeMsg" style="font-size:11px;color:#94a3b8;margin-right:8px;"></span>
    </div>
    <div id="probeOut" style="margin-top:8px;"></div>

    <div class="tc-sec">تصویر</div>
    <div class="tc-grid">
      <label>کدک
        <select id="f_vcodec" class="form-input" onchange="codecChanged()">
          <option value="h264">H.264 / MPEG-4 AVC</option>
          <option value="hevc">H.265 / HEVC</option>
          <option value="mpeg2">MPEG-2</option>
          <option value="copy">بدون تغییر (copy)</option>
        </select>
      </label>
      <label>پردازشگر
        <select id="f_hw" class="form-input">
          <option value="cpu">پردازنده (CPU)</option>
          <option value="nvenc" <?= $has('h264_nvenc') ? '' : 'disabled' ?>>NVIDIA NVENC</option>
          <option value="qsv" <?= $has('h264_qsv') ? '' : 'disabled' ?>>Intel Quick Sync</option>
          <option value="vaapi" <?= $has('h264_vaapi') ? '' : 'disabled' ?>>VAAPI</option>
        </select>
      </label>
      <label>Preset (سرعت/کیفیت)
        <select id="f_preset" class="form-input">
          <option value="ultrafast">ultrafast — کمترین CPU</option><option value="superfast">superfast</option>
          <option value="veryfast" selected>veryfast — پیشنهادی</option><option value="faster">faster</option>
          <option value="fast">fast</option><option value="medium">medium — بهترین کیفیت</option>
        </select>
      </label>
      <label>نرخ فریم
        <select id="f_fps" class="form-input">
          <option value="0">مثل ورودی</option><option>24</option><option>25</option><option>30</option><option>50</option><option>60</option>
        </select>
      </label>
      <label>فاصله‌ی فریم کلیدی (ثانیه)<input id="f_gop" type="number" min="1" max="10" value="2" class="form-input"></label>
      <label style="padding-top:18px;"><input type="checkbox" id="f_deint"> حذف درهم‌رفتگی (ماهواره SD/1080i)</label>
      <label style="padding-top:18px;"><input type="checkbox" id="f_lowlat"> تأخیر کم</label>
    </div>

    <div id="w_rend">
      <div class="tc-sec">کیفیت‌ها (اندازه و بیت‌ریت) — چند کیفیت یعنی HLS تطبیقی (ABR)</div>
      <div id="rends"></div>
      <button class="btn-ghost text-xs px-3" onclick="addRend(720,2500)"><i class="fas fa-plus"></i> کیفیت</button>
      <button class="btn-ghost text-xs px-3" onclick="setLadder()">نردبان پیشنهادی ۱۰۸۰/۷۲۰/۴۸۰</button>
    </div>

    <div class="tc-sec">صدا</div>
    <div class="tc-grid">
      <label>کدک
        <select id="f_acodec" class="form-input">
          <option value="aac">AAC</option><option value="mp3">MP3</option><option value="mp2">MPEG Audio Layer 2 (MP2)</option>
          <option value="copy">بدون تغییر (copy)</option><option value="none">بدون صدا</option>
        </select>
      </label>
      <label>بیت‌ریت (kbps)
        <select id="f_abr" class="form-input"><option>64</option><option>96</option><option selected>128</option><option>160</option><option>192</option><option>256</option><option>320</option></select>
      </label>
      <label>کانال<select id="f_ach" class="form-input"><option value="2">استریو</option><option value="1">مونو</option></select></label>
      <label>نرخ نمونه<select id="f_asr" class="form-input"><option value="48000">48000</option><option value="44100">44100</option></select></label>
      <label style="grid-column:span 2;">زبان‌ها (دوبله) — به ترتیب، اولی پیش‌فرض
        <input id="f_alang" class="form-input tc-mono" placeholder="fas,eng — خالی: اولین صدا">
      </label>
    </div>

    <div class="tc-sec">زیرنویس</div>
    <div class="tc-grid">
      <label>زیرنویس ورودی
        <select id="f_smode" class="form-input"><option value="none">حذف</option><option value="copy">عبور (روی خروجی UDP/SRT)</option></select>
      </label>
      <label style="grid-column:span 2;">فقط این زبان‌ها<input id="f_slang" class="form-input tc-mono" placeholder="fas,eng — خالی: همه"></label>
    </div>

    <div class="tc-sec">لوگو روی تصویر</div>
    <div class="tc-grid">
      <label style="grid-column:span 2;">تصویر (PNG شفاف)
        <select id="f_logo" class="form-input">
          <option value="">بدون لوگو</option>
          <?php foreach ($images as $im): ?><option value="<?= e($im['file_path']) ?>"><?= e($im['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>جای لوگو
        <select id="f_lpos" class="form-input"><option value="tr">بالا راست</option><option value="tl">بالا چپ</option><option value="br">پایین راست</option><option value="bl">پایین چپ</option></select>
      </label>
      <label>اندازه (٪ ارتفاع)<input id="f_lsize" type="number" min="3" max="25" value="8" class="form-input"></label>
    </div>

    <div class="tc-sec">خروجی‌ها — هم‌زمان</div>
    <div id="outs"></div>
    <button class="btn-ghost text-xs px-3" id="addOutBtn" onclick="addOut('udp')"><i class="fas fa-plus"></i> خروجی</button>
    <p style="font-size:11px;color:#64748b;margin-top:6px;line-height:1.8;">
      UDP multicast همان چیزی است که تلویزیون‌های webOS و Tizen مستقیم از شبکه می‌خوانند و بار سرور را صفر می‌کند.
      خروجی‌های UDP/SRT/RTMP بالاترین کیفیت را می‌گیرند. مقصدی که قطع شود بقیه را نمی‌اندازد.
    </p>

    <div id="edErr" style="color:#f87171;font-size:12px;margin-top:10px;"></div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;">
      <button class="btn-ghost text-sm px-3" onclick="closeEl('editor')">انصراف</button>
      <button class="btn-primary text-sm px-4" onclick="save()"><i class="fas fa-floppy-disk"></i> ذخیره</button>
    </div>
  </div>
</div>

<!-- لاگ -->
<div id="logBox" class="hidden" style="position:fixed;top:0;right:0;bottom:0;left:0;background:rgba(0,0,0,.72);z-index:91;padding:30px;">
  <div class="tc-card" style="background:#0b1220;max-width:980px;margin:0 auto;">
    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
      <b id="logTitle" style="color:#fff;"></b>
      <button class="btn-ghost text-xs px-2" onclick="closeEl('logBox')"><i class="fas fa-xmark"></i></button>
    </div>
    <pre id="logText" class="tc-mono" style="font-size:11px;color:#cbd5e1;max-height:70vh;overflow:auto;white-space:pre-wrap;"></pre>
  </div>
</div>

<!-- پیش‌نمایش -->
<div id="preview" class="hidden" style="position:fixed;top:0;right:0;bottom:0;left:0;background:rgba(0,0,0,.8);z-index:91;padding:30px;">
  <div class="tc-card" style="background:#000;max-width:900px;margin:0 auto;">
    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
      <b id="pvTitle" style="color:#fff;"></b>
      <button class="btn-ghost text-xs px-2" onclick="closePreview()"><i class="fas fa-xmark"></i></button>
    </div>
    <video id="pvVideo" controls autoplay muted style="width:100%;background:#000;border-radius:8px;"></video>
    <div id="pvUrl" class="tc-mono" style="font-size:11px;color:#64748b;margin-top:6px;"></div>
  </div>
</div>

<!-- انتشار -->
<div id="pubBox" class="hidden" style="position:fixed;top:0;right:0;bottom:0;left:0;background:rgba(0,0,0,.72);z-index:91;display:flex;align-items:center;justify-content:center;padding:20px;">
  <div class="tc-card" style="background:#0f172a;width:100%;max-width:440px;">
    <h3 style="color:#fff;font-weight:800;margin-bottom:10px;">پخش روی کانال IPTV</h3>
    <p style="font-size:12px;color:#94a3b8;line-height:1.9;margin-bottom:10px;">
      آدرس پخش کانال به خروجی HLS این کار (و اگر UDP دارد، آدرس multicast) تغییر می‌کند و تلویزیون‌های اتاق از این به بعد
      نسخه‌ی ترنسکدشده را پخش می‌کنند.
    </p>
    <select id="pubCh" class="form-input">
      <?php foreach ($channels as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;">
      <button class="btn-ghost text-sm px-3" onclick="closeEl('pubBox')">انصراف</button>
      <button class="btn-primary text-sm px-3" onclick="doPublish()">وصل کن</button>
    </div>
  </div>
</div>

<script src="/assets/vendor/hls/hls.min.js"></script>
<script>
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let JOBS = [], editing = null, pubJob = null, hlsInst = null;

const STATUS = {
  running:  ['در حال اجرا', '#22c55e'], starting: ['در حال شروع', '#3b82f6'],
  error:    ['خطا — در حال تلاش دوباره', '#ef4444'], stopped: ['متوقف', '#64748b'],
  finished: ['تمام شد', '#14b8a6'],
};
const KIND = { url: 'شبکه', file: 'فایل', v4l2: 'کپچر HDMI', decklink: 'کپچر SDI' };

// ── فهرست ──────────────────────────────────────────────────────
async function load() {
  try { const d = await api('/api/v1/transcoder/jobs'); JOBS = d.data || []; render(); }
  catch (e) { $('jobs').innerHTML = `<tr><td colspan="6" style="color:#f87171;padding:20px;">${esc(e.message)}</td></tr>`; }
}

function render() {
  if (!JOBS.length) {
    $('jobs').innerHTML = '<tr><td colspan="6" style="text-align:center;color:#475569;padding:30px;">هنوز کاری ساخته نشده — «کار جدید»</td></tr>';
    return;
  }
  $('jobs').innerHTML = JOBS.map(j => {
    const s = j.settings || {};
    let [sl, sc] = STATUS[j.status] || [j.status, '#64748b'];
    if (j.status === 'error' && j.desired !== 'running') sl = 'خطا';
    const outs = (s.outputs || []).map(o => o.type === 'hls'
        ? `<div>HLS ${(s.renditions || []).map(r => r.height + 'p').join('/') || 'copy'}</div>`
        : `<div class="tc-mono">${esc(o.type.toUpperCase())} ${esc(o.url)}</div>`).join('');
    const v = s.video || {}, a = s.audio || {};
    const codec = `${(v.codec || '').toUpperCase()}${v.hw && v.hw !== 'cpu' ? ' · ' + v.hw.toUpperCase() : ''} / ${(a.codec || '').toUpperCase()}`;
    let run = '—';
    if (j.stats) {
      const st = j.stats;
      run = `<div>${st.fps ? st.fps.toFixed(0) + ' fps' : ''} ${st.speed ? '· ' + esc(st.speed) : ''}</div>
             <div style="color:#64748b">${esc(st.bitrate || '')} ${st.out_time ? '· ' + esc(st.out_time) : ''}</div>
             ${st.drop ? `<div style="color:#fbbf24">فریم افتاده: ${st.drop}</div>` : ''}`;
    }
    if (j.mode === 'vod' && j.progress !== null) {
      run += `<div class="tc-bar"><div style="width:${j.progress}%"></div></div><div style="color:#64748b">${j.progress}%</div>`;
    }
    const on = j.desired === 'running';
    return `<tr>
      <td><div style="font-weight:700;color:#fff;">${esc(j.name)}</div>
          <div class="tc-mono" style="color:#64748b;font-size:11px;">${esc(j.slug)} · ${j.mode === 'vod' ? 'فایل' : 'زنده'}</div>
          <div style="color:#94a3b8;font-size:11px;">${esc(codec)}</div></td>
      <td><div style="color:#94a3b8;font-size:11px;">${KIND[j.input_kind] || ''}</div>
          <div class="tc-mono" style="font-size:11px;max-width:260px;word-break:break-all;">${esc(j.input_url)}</div></td>
      <td style="font-size:11px;">${outs}</td>
      <td><span class="tc-badge" style="background:${sc}22;color:${sc};">${sl}</span>
          ${j.restarts ? `<div style="color:#fbbf24;font-size:11px;margin-top:4px;">${j.restarts} بار راه‌اندازی دوباره</div>` : ''}
          ${j.last_error ? `<div style="color:#f87171;font-size:11px;margin-top:4px;max-width:220px;">${esc(j.last_error)}</div>` : ''}</td>
      <td style="font-size:11px;">${run}</td>
      <td style="white-space:nowrap;">
        ${on ? `<button class="btn-ghost text-xs px-2" onclick="act(${j.id},'stop')" title="توقف"><i class="fas fa-stop"></i></button>`
             : `<button class="btn-ghost text-xs px-2" onclick="act(${j.id},'start')" title="شروع"><i class="fas fa-play" style="color:#4ade80"></i></button>`}
        ${(s.outputs || []).some(o => o.type === 'hls') ? `<button class="btn-ghost text-xs px-2" onclick="preview(${j.id})" title="پیش‌نمایش"><i class="fas fa-eye"></i></button>` : ''}
        <button class="btn-ghost text-xs px-2" onclick="showLog(${j.id})" title="لاگ"><i class="fas fa-file-lines"></i></button>
        ${j.mode === 'live' ? `<button class="btn-ghost text-xs px-2" onclick="openPublish(${j.id})" title="پخش روی کانال"><i class="fas fa-tv"></i></button>` : ''}
        <button class="btn-ghost text-xs px-2" onclick="openEditor(${j.id})" title="ویرایش"><i class="fas fa-pen"></i></button>
        <button class="btn-ghost text-xs px-2" onclick="del(${j.id})" title="حذف"><i class="fas fa-trash" style="color:#f87171"></i></button>
      </td></tr>`;
  }).join('');
}

async function act(id, what) {
  try { const d = await api(`/api/v1/transcoder/jobs/${id}/${what}`, 'POST', {}); showToast('success', d.message); }
  catch (e) { showToast('error', e.message); }
  load();
}
async function del(id) {
  if (!confirm('این کار و خروجی‌هایش حذف شود؟')) return;
  try { await api(`/api/v1/transcoder/jobs/${id}`, 'DELETE'); showToast('success', 'حذف شد'); } catch (e) { showToast('error', e.message); }
  load();
}
async function showLog(id) {
  const j = JOBS.find(x => x.id === id);
  $('logTitle').textContent = 'لاگ ffmpeg — ' + (j ? j.name : '');
  $('logText').textContent = '…';
  $('logBox').classList.remove('hidden');
  try { const d = await api(`/api/v1/transcoder/jobs/${id}/log`); $('logText').textContent = d.data.log || 'لاگ خالی است — یعنی ffmpeg هشداری نداده.'; }
  catch (e) { $('logText').textContent = e.message; }
}
function preview(id) {
  const j = JOBS.find(x => x.id === id); if (!j) return;
  $('pvTitle').textContent = j.name; $('pvUrl').textContent = location.origin + j.hls_url;
  const v = $('pvVideo');
  if (window.Hls && Hls.isSupported()) { hlsInst = new Hls(); hlsInst.loadSource(j.hls_url); hlsInst.attachMedia(v); }
  else v.src = j.hls_url;
  $('preview').classList.remove('hidden');
}
function closePreview() {
  if (hlsInst) { hlsInst.destroy(); hlsInst = null; }
  $('pvVideo').removeAttribute('src'); $('pvVideo').load(); closeEl('preview');
}
function openPublish(id) { pubJob = id; $('pubBox').classList.remove('hidden'); }
async function doPublish() {
  try { const d = await api(`/api/v1/transcoder/jobs/${pubJob}/publish`, 'POST', { channel_id: Number($('pubCh').value) });
        showToast('success', d.message); closeEl('pubBox'); }
  catch (e) { showToast('error', e.message); }
}
async function refreshCaps() {
  try { await api('/api/v1/transcoder/capabilities/refresh', 'POST', {}); location.reload(); } catch (e) { showToast('error', e.message); }
}

// ── ویرایشگر ───────────────────────────────────────────────────
function openEditor(id) {
  editing = id ? JOBS.find(x => x.id === id) : null;
  const j = editing || { mode: 'live', input_kind: 'url', input_url: '', settings: {
      video: { codec: 'h264', hw: 'cpu', preset: 'veryfast', fps: 0, gop_seconds: 2 },
      renditions: [{ height: 720, bitrate: 2500 }], audio: { codec: 'aac', bitrate: 128, channels: 2, sample_rate: 48000, languages: [] },
      subtitles: { mode: 'none', languages: [] }, overlay: { image: '', position: 'tr', scale_pct: 8 }, input: { rtsp_transport: 'tcp' },
      outputs: [{ type: 'hls', segment: 4, window: 6 }] } };
  const s = j.settings, v = s.video || {}, a = s.audio || {}, o = s.overlay || {}, inp = s.input || {};
  $('edTitle').textContent = editing ? 'ویرایش: ' + j.name : 'کار جدید';
  $('f_name').value = j.name || ''; $('f_slug').value = j.slug || '';
  $('f_mode').value = j.mode; $('f_kind').value = j.input_kind;
  $('f_url').value = j.input_kind === 'url' ? j.input_url : '';
  $('f_dev').value = ['v4l2', 'decklink'].includes(j.input_kind) ? j.input_url : '';
  if (j.input_kind === 'file') $('f_file').value = j.input_url;
  $('f_rtsp').value = inp.rtsp_transport || 'tcp'; $('f_loop').checked = !!inp.loop; $('f_alsa').value = inp.alsa || '';
  $('f_vcodec').value = v.codec || 'h264'; $('f_hw').value = v.hw || 'cpu'; $('f_preset').value = v.preset || 'veryfast';
  $('f_fps').value = String(v.fps || 0); $('f_gop').value = v.gop_seconds || 2;
  $('f_deint').checked = !!v.deinterlace; $('f_lowlat').checked = !!v.low_latency;
  $('rends').innerHTML = ''; (s.renditions || []).forEach(r => addRend(r.height, r.bitrate));
  $('f_acodec').value = a.codec || 'aac'; $('f_abr').value = String(a.bitrate || 128);
  $('f_ach').value = String(a.channels || 2); $('f_asr').value = String(a.sample_rate || 48000);
  $('f_alang').value = (a.languages || []).join(',');
  $('f_smode').value = (s.subtitles || {}).mode || 'none'; $('f_slang').value = ((s.subtitles || {}).languages || []).join(',');
  $('f_logo').value = o.image || ''; $('f_lpos').value = o.position || 'tr'; $('f_lsize').value = o.scale_pct || 8;
  $('outs').innerHTML = ''; (s.outputs || []).forEach(x => addOut(x.type, x));
  $('probeOut').innerHTML = ''; $('probeMsg').textContent = ''; $('edErr').textContent = '';
  kindChanged(); modeChanged(); codecChanged();
  $('editor').classList.remove('hidden');
}

function kindChanged() {
  const k = $('f_kind').value, url = $('f_url').value;
  $('w_url').classList.toggle('hidden', k !== 'url'); $('w_ch').classList.toggle('hidden', k !== 'url');
  $('w_file').classList.toggle('hidden', k !== 'file');
  $('w_dev').classList.toggle('hidden', !['v4l2', 'decklink'].includes(k));
  $('f_dev').placeholder = k === 'decklink' ? 'DeckLink Mini Recorder' : '/dev/video0';
  $('w_rtsp').classList.toggle('hidden', !(k === 'url' && /^rtsps?:/i.test(url)));
  $('w_alsa').classList.toggle('hidden', k !== 'v4l2');
  $('w_loop').classList.toggle('hidden', !(k === 'file' && $('f_mode').value === 'live'));
}
$('f_url') && $('f_url').addEventListener('input', kindChanged);
function modeChanged() {
  const vod = $('f_mode').value === 'vod';
  if (vod) $('f_kind').value = 'file';
  [...$('f_kind').options].forEach(o => o.disabled = vod && o.value !== 'file');
  $('addOutBtn').classList.toggle('hidden', vod);
  if (vod) { $('outs').innerHTML = ''; addOut('hls'); }
  kindChanged();
}
function codecChanged() { $('w_rend').classList.toggle('hidden', $('f_vcodec').value === 'copy'); }
function fromChannel() { if ($('f_fromch').value) { $('f_url').value = $('f_fromch').value; kindChanged(); } }

const HEIGHTS = [2160, 1440, 1080, 720, 576, 480, 360, 240];
function addRend(h, br) {
  const d = document.createElement('div'); d.className = 'tc-row rend';
  d.innerHTML = `<select class="form-input r-h" style="width:120px">${HEIGHTS.map(x => `<option value="${x}" ${x === h ? 'selected' : ''}>${x}p</option>`).join('')}</select>
    <input type="number" class="form-input r-b" style="width:120px" min="200" max="50000" step="100" value="${br}"> <span style="font-size:11px;color:#64748b">kbps</span>
    <button class="btn-ghost text-xs px-2" onclick="this.parentNode.remove()"><i class="fas fa-xmark"></i></button>`;
  $('rends').appendChild(d);
}
function setLadder() { $('rends').innerHTML = ''; addRend(1080, 5000); addRend(720, 2800); addRend(480, 1200); }

function addOut(type, o = {}) {
  const d = document.createElement('div'); d.className = 'tc-row out';
  const ph = { udp: 'udp://239.10.0.1:1234', rtmp: 'rtmp://server/live/key', srt: 'srt://server:9000?mode=caller' };
  d.innerHTML = `<select class="form-input o-t" style="width:150px">
      ${['hls', 'udp', 'rtmp', 'srt'].map(t => `<option value="${t}" ${t === type ? 'selected' : ''}>${t === 'udp' ? 'UDP multicast' : t.toUpperCase()}</option>`).join('')}
    </select>
    <span class="o-hls" style="font-size:11px;color:#94a3b8">قطعه <input type="number" class="form-input o-seg" style="width:64px;display:inline-block" min="1" max="10" value="${o.segment || 4}"> ثانیه،
      پنجره <input type="number" class="form-input o-win" style="width:64px;display:inline-block" min="3" max="30" value="${o.window || 6}"> قطعه</span>
    <input class="form-input tc-mono o-url" style="flex:1;min-width:240px" value="${esc(o.url || '')}" placeholder="${ph[type] || ''}">
    <span class="o-ttlw" style="font-size:11px;color:#94a3b8">TTL <input type="number" class="form-input o-ttl" style="width:60px;display:inline-block" min="1" max="32" value="${o.ttl || 4}"></span>
    <button class="btn-ghost text-xs px-2" onclick="this.parentNode.remove()"><i class="fas fa-xmark"></i></button>`;
  const sync = () => {
    const t = d.querySelector('.o-t').value;
    d.querySelector('.o-hls').style.display = t === 'hls' ? '' : 'none';
    d.querySelector('.o-url').style.display = t === 'hls' ? 'none' : '';
    d.querySelector('.o-ttlw').style.display = t === 'udp' ? '' : 'none';
    d.querySelector('.o-url').placeholder = ph[t] || '';
  };
  d.querySelector('.o-t').addEventListener('change', sync); sync();
  $('outs').appendChild(d);
}

const langs = s => s.split(/[,،\s]+/).map(x => x.trim().toLowerCase()).filter(Boolean);
function collect() {
  const kind = $('f_kind').value;
  const url = kind === 'url' ? $('f_url').value.trim() : kind === 'file' ? $('f_file').value : $('f_dev').value.trim();
  return {
    name: $('f_name').value.trim(), slug: $('f_slug').value.trim(), mode: $('f_mode').value,
    input_kind: kind, input_url: url,
    settings: {
      input: { rtsp_transport: $('f_rtsp').value, loop: $('f_loop').checked ? 1 : 0, alsa: $('f_alsa').value.trim() },
      video: { codec: $('f_vcodec').value, hw: $('f_hw').value, preset: $('f_preset').value, fps: Number($('f_fps').value),
               gop_seconds: Number($('f_gop').value), deinterlace: $('f_deint').checked ? 1 : 0, low_latency: $('f_lowlat').checked ? 1 : 0 },
      renditions: [...document.querySelectorAll('#rends .rend')].map(r => ({ height: Number(r.querySelector('.r-h').value), bitrate: Number(r.querySelector('.r-b').value) })),
      audio: { codec: $('f_acodec').value, bitrate: Number($('f_abr').value), channels: Number($('f_ach').value),
               sample_rate: Number($('f_asr').value), languages: langs($('f_alang').value) },
      subtitles: { mode: $('f_smode').value, languages: langs($('f_slang').value) },
      overlay: { image: $('f_logo').value, position: $('f_lpos').value, scale_pct: Number($('f_lsize').value) },
      outputs: [...document.querySelectorAll('#outs .out')].map(o => ({
        type: o.querySelector('.o-t').value, url: o.querySelector('.o-url').value.trim(),
        segment: Number(o.querySelector('.o-seg').value), window: Number(o.querySelector('.o-win').value),
        ttl: Number(o.querySelector('.o-ttl').value) })),
    },
  };
}

async function save() {
  $('edErr').textContent = '';
  const body = collect();
  try {
    const d = editing ? await api(`/api/v1/transcoder/jobs/${editing.id}`, 'PUT', body)
                      : await api('/api/v1/transcoder/jobs', 'POST', body);
    showToast('success', d.message); closeEl('editor'); load();
  } catch (e) { $('edErr').textContent = e.message; }
}

async function probe() {
  const b = collect();
  $('probeMsg').textContent = 'در حال خواندن ورودی (تا ۱۵ ثانیه)…'; $('probeOut').innerHTML = '';
  try {
    const d = await api('/api/v1/transcoder/probe', 'POST', { input_kind: b.input_kind, input_url: b.input_url, mode: b.mode });
    const r = d.data; $('probeMsg').textContent = r.format || '';
    const TYPE = { video: 'تصویر', audio: 'صدا', subtitle: 'زیرنویس', data: 'داده' };
    $('probeOut').innerHTML = `<table class="tc-table"><thead><tr><th>نوع</th><th>کدک</th><th>اندازه</th><th>فریم</th><th>کانال صدا</th><th>زبان</th></tr></thead><tbody>` +
      r.streams.map(s => `<tr><td>${TYPE[s.type] || esc(s.type)}</td><td class="tc-mono">${esc(s.codec)}</td>
        <td>${s.width ? s.width + '×' + s.height : ''}${s.field && !['progressive', 'unknown', ''].includes(s.field) ? ' <span style="color:#fbbf24">درهم (interlaced)</span>' : ''}</td>
        <td>${s.fps || ''}</td><td>${s.channels || ''}</td><td class="tc-mono">${esc(s.language)}</td></tr>`).join('') + '</tbody></table>';
    /* کیفیت بالاتر از ورودی بزرگ‌نمایی نمی‌شود و فقط یک نسخه‌ی تکراری
       است که CPU می‌سوزاند — اپراتور باید بداند */
    const srcH = Math.max(0, ...r.streams.filter(s => s.type === 'video').map(s => s.height || 0));
    const over = collect().settings.renditions.filter(x => srcH && x.height > srcH).map(x => x.height + 'p');
    if (over.length) $('probeMsg').innerHTML = esc(r.format || '') +
      ` <span style="color:#fbbf24">— ورودی ${srcH}p است؛ ${esc(over.join('، '))} بزرگ‌تر از ورودی است و فقط یک نسخه‌ی تکراری می‌سازد. حذفش کنید.</span>`;
    /* ورودی درهم؟ حذف درهم‌رفتگی را خودکار روشن کن */
    if (r.streams.some(s => s.type === 'video' && s.field && !['progressive', 'unknown', ''].includes(s.field))) $('f_deint').checked = true;
  } catch (e) { $('probeMsg').textContent = e.message; }
}

// ── کمکی ───────────────────────────────────────────────────────
function closeEl(id) { $(id).classList.add('hidden'); }
async function api(url, method = 'GET', body = null) {
  const opts = { method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, credentials: 'same-origin' };
  if (body) opts.body = JSON.stringify(body);
  const r = await fetch(url, opts);
  let d = {}; try { d = await r.json(); } catch (_) {}
  if (!r.ok || d.success === false) throw new Error(d.message || `خطا (${r.status})`);
  return d;
}

load();
setInterval(() => { if (!document.hidden && $('editor').classList.contains('hidden')) load(); }, 5000);
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
