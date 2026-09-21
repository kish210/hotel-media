<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-broadcast-tower" style="color:#ef4444;margin-left:10px;"></i>TVHeadend — کانال‌های پخش زنده
  </h1>
  <div style="display:flex;gap:8px;">
    <a href="/admin/help#tvheadend" class="btn-ghost text-sm flex items-center gap-1.5" target="_blank">
      <i class="fas fa-question-circle text-xs text-blue-400"></i> راهنما
    </a>
    <button onclick="document.getElementById('addModal').classList.remove('hidden')"
      class="btn-primary text-sm flex items-center gap-1.5">
      <i class="fas fa-plus text-xs"></i> افزودن سرور
    </button>
  </div>
</div>

<!-- توضیح -->
<div style="background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.2);border-radius:14px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:flex-start;gap:14px;">
  <i class="fas fa-info-circle" style="color:#f87171;font-size:20px;margin-top:2px;flex-shrink:0;"></i>
  <div>
    <div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:6px;">TVHeadend چیست؟</div>
    <div style="font-size:12px;color:#94a3b8;line-height:1.8;">
      TVHeadend یک سرور پخش زنده تلویزیون است که کانال‌های DVB-T، DVB-S، IPTV و HDHR را مدیریت می‌کند.
      با اتصال Hotel Media به TVHeadend، کانال‌های زنده به‌صورت خودکار وارد بخش IPTV می‌شوند
      و در پلیرها قابل نمایش خواهند بود.
    </div>
    <div style="margin-top:8px;font-size:11px;color:#64748b;">
      پورت پیش‌فرض TVHeadend: <code style="background:rgba(255,255,255,.07);padding:2px 6px;border-radius:4px;color:#f87171;">9981</code>
      — مثال: <code style="background:rgba(255,255,255,.07);padding:2px 6px;border-radius:4px;color:#94a3b8;">http://192.168.1.100:9981</code>
    </div>
  </div>
</div>

<?php if (empty($sources)): ?>
<!-- حالت خالی -->
<div style="background:#16161f;border:1px dashed rgba(255,255,255,.1);border-radius:16px;padding:60px 20px;text-align:center;">
  <i class="fas fa-broadcast-tower" style="font-size:48px;color:#1e293b;margin-bottom:16px;display:block;"></i>
  <div style="font-size:15px;font-weight:700;color:#334155;margin-bottom:8px;">هیچ سروری اضافه نشده</div>
  <div style="font-size:12px;color:#475569;margin-bottom:20px;">اطلاعات سرور TVHeadend خود را وارد کنید</div>
  <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="btn-primary">
    <i class="fas fa-plus"></i> افزودن سرور TVHeadend
  </button>
</div>

<?php else: ?>
<!-- لیست سرورها -->
<div style="display:flex;flex-direction:column;gap:14px;">
  <?php foreach ($sources as $src): ?>
  <div class="card" style="padding:20px;position:relative;" id="src-<?= $src['id'] ?>">
    <!-- هدر -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
      <div style="width:42px;height:42px;border-radius:12px;background:rgba(239,68,68,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fas fa-server" style="color:#ef4444;font-size:18px;"></i>
      </div>
      <div style="flex:1;">
        <div style="font-size:14px;font-weight:700;color:#fff;"><?= htmlspecialchars($src['name']) ?></div>
        <div style="font-size:12px;color:#64748b;margin-top:2px;">
          <a href="<?= htmlspecialchars($src['server_url']) ?>" target="_blank"
             style="color:#94a3b8;text-decoration:none;">
            <?= htmlspecialchars($src['server_url']) ?>
          </a>
        </div>
      </div>
      <!-- وضعیت اتصال -->
      <div id="status-<?= $src['id'] ?>" style="display:flex;align-items:center;gap:6px;font-size:11px;color:#64748b;cursor:pointer;"
           onclick="testConn(<?= $src['id'] ?>)" title="تست اتصال">
        <span style="width:8px;height:8px;border-radius:50%;background:#334155;display:inline-block;" id="dot-<?= $src['id'] ?>"></span>
        <span id="status-text-<?= $src['id'] ?>">تست اتصال</span>
      </div>
    </div>

    <!-- اطلاعات -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">
      <div style="background:#0f0f17;border-radius:10px;padding:10px 14px;">
        <div style="font-size:10px;color:#475569;margin-bottom:3px;">پروفایل استریم</div>
        <div style="font-size:13px;font-weight:600;color:#fff;"><?= htmlspecialchars($src['stream_profile'] ?: 'pass') ?></div>
      </div>
      <div style="background:#0f0f17;border-radius:10px;padding:10px 14px;">
        <div style="font-size:10px;color:#475569;margin-bottom:3px;">آخرین سینک</div>
        <div style="font-size:13px;font-weight:600;color:#fff;">
          <?= $src['last_sync'] ? date('H:i — d/m', strtotime($src['last_sync'])) : '—' ?>
        </div>
      </div>
      <div style="background:#0f0f17;border-radius:10px;padding:10px 14px;">
        <div style="font-size:10px;color:#475569;margin-bottom:3px;">کل کانال‌های سینک</div>
        <div style="font-size:13px;font-weight:600;color:#fff;"><?= number_format($src['sync_count']) ?></div>
      </div>
    </div>

    <!-- دکمه‌های عمل -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <button onclick="testConn(<?= $src['id'] ?>)"
        class="btn-ghost text-sm" style="flex:1;min-width:120px;">
        <i class="fas fa-plug text-xs"></i> تست اتصال
      </button>
      <button onclick="syncChannels(<?= $src['id'] ?>)"
        class="btn-ghost text-sm" style="flex:1;min-width:120px;color:#22c55e;border-color:rgba(34,197,94,.3);">
        <i class="fas fa-sync-alt text-xs"></i> سینک کانال‌ها (API)
      </button>
      <button onclick="importM3u(<?= $src['id'] ?>)"
        class="btn-ghost text-sm" style="flex:1;min-width:120px;color:#f59e0b;border-color:rgba(245,158,11,.3);">
        <i class="fas fa-file-import text-xs"></i> ایمپورت M3U
      </button>
      <form method="post" action="/admin/iptv/tvheadend/<?= $src['id'] ?>/delete"
            onsubmit="return confirm('سرور حذف شود؟')" style="flex:0;">
        <?php csrf() ?>
        <button type="submit" class="btn-ghost text-sm" style="color:#ef4444;border-color:rgba(239,68,68,.3);">
          <i class="fas fa-trash text-xs"></i>
        </button>
      </form>
    </div>

    <!-- Progress bar هنگام سینک -->
    <div id="prog-<?= $src['id'] ?>" style="display:none;margin-top:12px;">
      <div style="background:#0f0f17;border-radius:8px;padding:12px;font-size:12px;color:#94a3b8;">
        <i class="fas fa-spinner fa-spin" style="color:#ef4444;margin-left:8px;"></i>
        <span id="prog-text-<?= $src['id'] ?>">در حال دریافت کانال‌ها...</span>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- لینک به کانال‌های IPTV -->
<div style="margin-top:20px;text-align:center;">
  <a href="/admin/iptv" style="color:#64748b;font-size:12px;text-decoration:none;">
    <i class="fas fa-satellite-dish" style="margin-left:6px;"></i>مشاهده همه کانال‌های IPTV ←
  </a>
</div>
<?php endif; ?>


<!-- ─── Modal افزودن سرور ──────────────────────────────────── -->
<div id="addModal" class="hidden fixed inset-0 z-50 flex items-center justify-center"
     style="background:rgba(0,0,0,.7);backdrop-filter:blur(4px);">
  <div class="card" style="width:100%;max-width:480px;padding:28px;margin:20px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <h3 style="font-size:16px;font-weight:700;color:#fff;">
        <i class="fas fa-server" style="color:#ef4444;margin-left:8px;"></i>افزودن سرور TVHeadend
      </h3>
      <button onclick="document.getElementById('addModal').classList.add('hidden')"
        style="background:none;border:none;color:#64748b;cursor:pointer;font-size:18px;">✕</button>
    </div>

    <form method="post" action="/admin/iptv/tvheadend">
      <?php csrf() ?>
      <div style="display:flex;flex-direction:column;gap:14px;">

        <div>
          <label style="font-size:12px;color:#94a3b8;display:block;margin-bottom:6px;">نام سرور</label>
          <input type="text" name="name" class="form-input" value="TVHeadend Server" placeholder="مثال: سرور اصلی">
        </div>

        <div>
          <label style="font-size:12px;color:#94a3b8;display:block;margin-bottom:6px;">
            آدرس سرور <span style="color:#ef4444;">*</span>
          </label>
          <input type="url" name="server_url" class="form-input" required
                 placeholder="http://192.168.1.100:9981">
          <div style="font-size:11px;color:#475569;margin-top:4px;">پورت پیش‌فرض TVHeadend: 9981</div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label style="font-size:12px;color:#94a3b8;display:block;margin-bottom:6px;">نام کاربری</label>
            <input type="text" name="username" class="form-input" placeholder="اختیاری">
          </div>
          <div>
            <label style="font-size:12px;color:#94a3b8;display:block;margin-bottom:6px;">رمز عبور</label>
            <input type="password" name="password" class="form-input" placeholder="اختیاری">
          </div>
        </div>

        <div>
          <label style="font-size:12px;color:#94a3b8;display:block;margin-bottom:6px;">پروفایل استریم</label>
          <select name="stream_profile" class="form-input">
            <option value="pass">pass (بدون تبدیل — پیشنهادی)</option>
            <option value="htsp">htsp</option>
            <option value="hls">hls (HLS)</option>
            <option value="webtv-h264-aac-matroska">webtv-h264-aac-matroska</option>
          </select>
          <div style="font-size:11px;color:#475569;margin-top:4px;">
            برای نمایش مستقیم در مرورگر، پروفایل <strong>hls</strong> یا <strong>pass</strong> مناسب‌تر است
          </div>
        </div>

      </div>

      <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end;">
        <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
          class="btn-ghost">انصراف</button>
        <button type="submit" class="btn-primary">
          <i class="fas fa-plus text-xs"></i> افزودن سرور
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// تست اتصال
async function testConn(id) {
  const dot = document.getElementById('dot-' + id);
  const txt = document.getElementById('status-text-' + id);
  dot.style.background = '#f59e0b';
  txt.textContent = 'در حال بررسی...';
  try {
    const r = await fetch('/admin/iptv/tvheadend/' + id + '/test');
    const d = await r.json();
    if (d.ok) {
      dot.style.background = '#22c55e';
      txt.textContent = 'متصل — ' + (d.name || 'TVHeadend') + ' v' + (d.version || '?');
    } else {
      dot.style.background = '#ef4444';
      txt.textContent = d.msg || 'خطا';
    }
  } catch(e) {
    dot.style.background = '#ef4444';
    txt.textContent = 'خطا در اتصال';
  }
}

// سینک کانال‌ها از API
async function syncChannels(id) {
  const prog = document.getElementById('prog-' + id);
  const txt  = document.getElementById('prog-text-' + id);
  prog.style.display = 'block';
  txt.textContent = 'در حال دریافت کانال‌ها از TVHeadend API...';
  try {
    const r = await fetch('/admin/iptv/tvheadend/' + id + '/sync', {method:'POST',
      headers:{'X-Requested-With':'XMLHttpRequest'}});
    const d = await r.json();
    prog.style.display = 'none';
    if (d.ok) {
      showToast('✅ ' + d.msg, 'success');
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast('❌ ' + d.msg, 'error');
    }
  } catch(e) {
    prog.style.display = 'none';
    showToast('خطا در ارتباط با سرور', 'error');
  }
}

// ایمپورت M3U
async function importM3u(id) {
  const prog = document.getElementById('prog-' + id);
  const txt  = document.getElementById('prog-text-' + id);
  prog.style.display = 'block';
  txt.textContent = 'در حال دریافت M3U playlist از TVHeadend...';
  try {
    const r = await fetch('/admin/iptv/tvheadend/' + id + '/m3u', {method:'POST',
      headers:{'X-Requested-With':'XMLHttpRequest'}});
    const d = await r.json();
    prog.style.display = 'none';
    if (d.ok) {
      showToast('✅ ' + d.msg, 'success');
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast('❌ ' + d.msg, 'error');
    }
  } catch(e) {
    prog.style.display = 'none';
    showToast('خطا در ارتباط با سرور', 'error');
  }
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:9999;' +
    'padding:12px 24px;border-radius:12px;font-size:13px;font-family:Vazirmatn,sans-serif;' +
    'background:' + (type==='success'?'#166534':'#7f1d1d') + ';color:#fff;box-shadow:0 4px 20px rgba(0,0,0,.4);';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

// تست خودکار هنگام بارگذاری
document.addEventListener('DOMContentLoaded', () => {
  <?php foreach ($sources as $src): ?>
  testConn(<?= $src['id'] ?>);
  <?php endforeach; ?>
});
</script>
