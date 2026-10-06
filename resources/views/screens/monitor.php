<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<!-- ─── Header ─── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <div>
    <h1 style="font-size:20px;font-weight:800;color:#fff;">
      <i class="fas fa-display" style="color:#1a7ac4;margin-left:10px;"></i>مانیتورینگ صفحات نمایش
    </h1>
    <p style="font-size:12px;color:#475569;margin-top:4px;">
      آپدیت خودکار هر ۱۵ ثانیه
      <span id="last-update" style="color:#64748b;"></span>
    </p>
  </div>
  <div style="display:flex;align-items:center;gap:10px;">
    <div style="display:flex;gap:4px;background:rgba(0,0,0,0.3);border-radius:8px;padding:3px;">
      <button onclick="setView('grid')" id="view-grid"
        style="padding:7px 14px;border-radius:6px;border:none;cursor:pointer;font-size:12px;font-weight:600;
               font-family:'Vazirmatn',sans-serif;background:rgba(26,122,196,0.2);color:#1a7ac4;">
        <i class="fas fa-grip text-xs ml-1"></i>گرید
      </button>
      <button onclick="setView('table')" id="view-table"
        style="padding:7px 14px;border-radius:6px;border:none;cursor:pointer;font-size:12px;font-weight:600;
               font-family:'Vazirmatn',sans-serif;background:transparent;color:#64748b;">
        <i class="fas fa-table text-xs ml-1"></i>جدول
      </button>
    </div>
    <?php /* اعلان سراسری: ‏API آن (`/api/v1/broadcast/all`) از قبل بود
             و هیچ صفحه‌ای صدایش نمی‌زد. پخش فوری فقط per-screen در
             صفحهٔ هر صفحه‌نمایش بود — یعنی برای هشدار کل هتل باید
             تک‌تک صفحه‌ها را باز می‌کردید. دکمه اینجاست چون در یک
             حادثه، اپراتور روی همین صفحهٔ مانیتورینگ است. */ ?>
    <button onclick="openEmg()" class="btn-ghost text-sm flex items-center gap-1.5"
            style="background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3);color:#fca5a5;">
      <i class="fas fa-triangle-exclamation text-xs"></i> اعلان اضطراری همه‌ی صفحه‌ها
    </button>
    <a href="/admin/screens" class="btn-ghost text-sm flex items-center gap-1.5">
      <i class="fas fa-tv text-xs"></i> مدیریت صفحات
    </a>
  </div>
</div>

<!-- ─── اعلان اضطراری سراسری ───────────────────────────────────────── -->
<div id="emgModal" class="modal-overlay hidden">
  <div class="modal" style="max-width:520px;">
    <div class="modal-head">
      <h3 style="color:#fca5a5;">
        <i class="fas fa-triangle-exclamation"></i> اعلان اضطراری — همه‌ی صفحه‌ها
      </h3>
      <button onclick="closeEmg()" class="btn-ghost text-xs px-2"><i class="fas fa-times"></i></button>
    </div>
    <div style="display:grid;gap:10px;">
      <div>
        <label class="form-label">متن اعلان *</label>
        <textarea id="emg-text" class="form-input" rows="3"
                  placeholder="لطفا از نزدیک‌ترین راه خروج ساختمان را ترک کنید."></textarea>
      </div>
      <div>
        <label class="form-label">مدت نمایش</label>
        <select id="emg-dur" class="form-input">
          <?php /* صفر یعنی تا توقف دستی. برای اعلان تخلیه، ناپدیدشدن
                   خودکار بعد از نیم دقیقه از نبودنش بدتر است. */ ?>
          <option value="0">تا زمانی که خودم متوقف کنم</option>
          <option value="60">۱ دقیقه</option>
          <option value="300" selected>۵ دقیقه</option>
          <option value="900">۱۵ دقیقه</option>
        </select>
      </div>
      <div style="background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.2);
                  border-radius:9px;padding:10px 12px;font-size:11px;color:#94a3b8;line-height:1.9;">
        این اعلان روی <b style="color:#fca5a5;">همه‌ی صفحه‌ها و تلویزیون‌های اتاق</b>
        تمام‌صفحه نمایش داده می‌شود و میهمان نمی‌تواند آن را ببندد.
        هر صفحه در ضربان بعدی‌اش (حداکثر ۳۰ ثانیه) آن را می‌گیرد.
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px;">
      <button onclick="closeEmg()" class="btn-ghost text-sm">انصراف</button>
      <button onclick="stopEmg()" class="btn-ghost text-sm">توقف اعلان فعلی</button>
      <button onclick="sendEmg()" id="emg-send" class="btn-primary text-sm"
              style="background:#b91c1c;border-color:#b91c1c;">ارسال به همه</button>
    </div>
  </div>
</div>

<!-- ─── Stats ─── -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;" id="stats-bar">
  <?php
  $statCards = [
    ['کل صفحات',   $stats['total'],   '#1a7ac4','fa-tv'],
    ['آنلاین',      $stats['online'],  '#22c55e','fa-circle-check'],
    ['آفلاین',      $stats['offline'], '#ef4444','fa-circle-xmark'],
    ['در انتظار',   $stats['pending'], '#f59e0b','fa-hourglass-half'],
  ];
  foreach ($statCards as [$label,$val,$clr,$icon]):
  ?>
  <div style="background:#16161f;border:1px solid rgba(255,255,255,0.07);border-radius:14px;
              padding:16px;display:flex;align-items:center;gap:14px;border-top:3px solid <?=$clr?>;">
    <div style="width:42px;height:42px;background:<?=$clr?>18;border-radius:12px;
                display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fas <?=$icon?>" style="color:<?=$clr?>;font-size:18px;"></i>
    </div>
    <div>
      <div style="font-size:28px;font-weight:900;color:#fff;line-height:1;"><?=$val?></div>
      <div style="font-size:12px;color:#64748b;margin-top:2px;"><?=$label?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ─── Grid View ─── -->
<div id="grid-view">
  <div id="screens-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;">
    <?php foreach ($screens as $s):
      $online   = (bool)($s['is_online'] ?? false);
      $active   = $s['status'] === 'active';
      $secsAgo  = (int)($s['seconds_ago'] ?? 9999);
      $isLive   = $online && $secsAgo < 120;

      // رنگ وضعیت
      $statusClr = $isLive ? '#22c55e' : ($active ? '#f59e0b' : '#64748b');
      $statusLbl = $isLive ? 'آنلاین' : ($active ? 'آفلاین' : 'غیرفعال');

      // CPU/Memory
      $cpu = $s['cpu_usage'] ? round((float)$s['cpu_usage']) : null;
      $mem = $s['memory_usage'] ? round((float)$s['memory_usage']) : null;
    ?>
    <div class="screen-card" data-id="<?=$s['id']?>" data-online="<?=$isLive?1:0?>"
      style="background:#16161f;border:1px solid rgba(<?=$isLive?'34,197,94':'255,255,255'?>,<?=$isLive?'.25':'.07'?>);
             border-radius:16px;overflow:hidden;transition:all 0.3s;">

      <!-- Preview area - شبیه‌ساز صفحه -->
      <div style="height:170px;background:#0a0a0f;position:relative;overflow:hidden;cursor:pointer;"
           onclick="window.open('/player/<?=e($s['code'])?>', '_blank')">

        <!-- آنلاین indicator -->
        <div style="position:absolute;top:10px;right:10px;z-index:5;
                    background:<?=$statusClr?>22;border:1px solid <?=$statusClr?>55;
                    border-radius:20px;padding:3px 10px;font-size:10px;font-weight:700;color:<?=$statusClr?>;
                    display:flex;align-items:center;gap:5px;">
          <span style="width:6px;height:6px;border-radius:50%;background:<?=$statusClr?>;
                       <?=$isLive?'animation:pulse-dot 1.5s infinite;':''?>"></span>
          <?=$statusLbl?>
        </div>

        <!-- صفحه TV preview -->
        <div style="position:absolute;inset:20px;background:#111;border-radius:6px;
                    border:1px solid rgba(255,255,255,0.1);display:flex;flex-direction:column;
                    align-items:center;justify-content:center;overflow:hidden;">
          <?php if ($isLive && $s['current_item']): ?>
            <!-- در حال پخش -->
            <div style="position:absolute;inset:0;background:linear-gradient(135deg,#0d0d1e,#111827);
                        display:flex;flex-direction:column;align-items:center;justify-content:center;">
              <i class="fas fa-play-circle" style="font-size:32px;color:#1a7ac4;margin-bottom:8px;"></i>
              <div style="font-size:11px;color:#94a3b8;text-align:center;padding:0 12px;max-width:200px;
                           overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= e($s['current_item']) ?>
              </div>
            </div>
          <?php elseif ($isLive): ?>
            <div style="text-align:center;">
              <i class="fas fa-tv" style="font-size:28px;color:#22c55e;display:block;margin-bottom:8px;"></i>
              <div style="font-size:11px;color:#475569;">متصل · بدون محتوا</div>
            </div>
          <?php elseif ($active): ?>
            <div style="text-align:center;">
              <i class="fas fa-moon" style="font-size:28px;color:#475569;display:block;margin-bottom:8px;"></i>
              <div style="font-size:11px;color:#2d2d40;">آفلاین</div>
            </div>
          <?php else: ?>
            <div style="text-align:center;">
              <i class="fas fa-hourglass-half" style="font-size:28px;color:#f59e0b;display:block;margin-bottom:8px;"></i>
              <div style="font-size:11px;color:#64748b;">در انتظار فعال‌سازی</div>
            </div>
          <?php endif; ?>
        </div>

        <!-- CPU/Memory bar -->
        <?php if ($cpu !== null || $mem !== null): ?>
        <div style="position:absolute;bottom:24px;left:24px;right:24px;">
          <?php if ($cpu !== null): ?>
          <div style="height:3px;background:rgba(255,255,255,0.1);border-radius:2px;margin-bottom:3px;overflow:hidden;">
            <div style="height:100%;width:<?=$cpu?>%;border-radius:2px;
                        background:<?=$cpu>80?'#ef4444':($cpu>60?'#f59e0b':'#22c55e')?>;"></div>
          </div>
          <?php endif; ?>
          <?php if ($mem !== null): ?>
          <div style="height:3px;background:rgba(255,255,255,0.1);border-radius:2px;overflow:hidden;">
            <div style="height:100%;width:<?=$mem?>%;border-radius:2px;
                        background:<?=$mem>85?'#ef4444':($mem>70?'#f59e0b':'#3b82f6')?>;"></div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- اطلاعات -->
      <div style="padding:14px 16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
          <h3 style="font-size:14px;font-weight:700;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:180px;">
            <?= e($s['name']) ?>
          </h3>
          <div style="display:flex;gap:4px;">
            <a href="/admin/screens/<?=$s['id']?>" style="width:28px;height:28px;background:rgba(255,255,255,0.05);
               border-radius:8px;display:flex;align-items:center;justify-content:center;text-decoration:none;" title="مدیریت">
              <i class="fas fa-gear" style="font-size:11px;color:#64748b;"></i>
            </a>
            <a href="/player/<?=e($s['code'])?>" target="_blank"
               style="width:28px;height:28px;background:rgba(34,197,94,0.08);border-radius:8px;
                      display:flex;align-items:center;justify-content:center;text-decoration:none;" title="پلیر">
              <i class="fas fa-play" style="font-size:10px;color:#22c55e;"></i>
            </a>
          </div>
        </div>

        <!-- اطلاعات ردیف -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:11px;">
          <div style="color:#64748b;">
            <i class="fas fa-list text-xs ml-1"></i>
            <?= e($s['playlist_name'] ?? '—') ?>
          </div>
          <div style="color:#64748b;text-align:left;">
            <i class="fas fa-clock text-xs ml-1"></i>
            <?= $secsAgo < 9999 ? timeAgo($s['last_seen_at'] ?? date('Y-m-d H:i:s')) : '—' ?>
          </div>
          <?php if ($s['location_name']): ?>
          <div style="color:#64748b;grid-column:1/-1;">
            <i class="fas fa-location-dot text-xs ml-1"></i><?= e($s['location_name']) ?>
          </div>
          <?php endif; ?>
          <?php if ($cpu !== null): ?>
          <div style="color:<?=$cpu>80?'#ef4444':($cpu>60?'#f59e0b':'#64748b')?>;">
            CPU: <?=$cpu?>%
          </div>
          <?php endif; ?>
          <?php if ($mem !== null): ?>
          <div style="color:<?=$mem>85?'#ef4444':($mem>70?'#f59e0b':'#64748b')?>;">
            RAM: <?=$mem?>%
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($screens)): ?>
    <div style="grid-column:1/-1;text-align:center;padding:60px;color:#475569;">
      <i class="fas fa-display" style="font-size:48px;display:block;margin-bottom:16px;opacity:0.2;"></i>
      <p>هیچ صفحه‌ای اضافه نشده</p>
      <a href="/admin/screens/create" class="btn-primary text-sm mt-4 inline-flex items-center gap-2">
        <i class="fas fa-plus text-xs"></i> افزودن صفحه
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ─── Table View ─── -->
<div id="table-view" style="display:none;">
  <div class="card overflow-hidden">
    <table class="w-full text-sm">
      <thead>
        <tr style="border-bottom:1px solid rgba(255,255,255,0.06);font-size:11px;color:#475569;text-transform:uppercase;">
          <th style="text-align:right;padding:12px 16px;">صفحه</th>
          <th style="text-align:right;padding:12px;">وضعیت</th>
          <th style="text-align:right;padding:12px;">در حال پخش</th>
          <th style="text-align:right;padding:12px;">پلی‌لیست</th>
          <th style="text-align:right;padding:12px;">آخرین ارتباط</th>
          <th style="text-align:right;padding:12px;">CPU</th>
          <th style="text-align:right;padding:12px;">RAM</th>
          <th style="padding:12px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($screens as $s):
          $online  = (bool)$s['is_online'];
          $secsAgo = (int)($s['seconds_ago'] ?? 9999);
          $isLive  = $online && $secsAgo < 120;
          $cpu = $s['cpu_usage'] ? round((float)$s['cpu_usage']) : null;
          $mem = $s['memory_usage'] ? round((float)$s['memory_usage']) : null;
        ?>
        <tr style="border-bottom:1px solid rgba(255,255,255,0.04);transition:background 0.15s;"
            onmouseenter="this.style.background='rgba(255,255,255,0.02)'"
            onmouseleave="this.style.background=''">
          <td style="padding:12px 16px;">
            <div style="font-weight:600;color:#fff;"><?= e($s['name']) ?></div>
            <div style="font-family:monospace;font-size:10px;color:#475569;"><?= e($s['code']) ?></div>
          </td>
          <td style="padding:12px;">
            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;
                         background:<?=$isLive?'rgba(34,197,94,.1)':($s['status']==='active'?'rgba(239,68,68,.1)':'rgba(100,116,139,.1)')?>;
                         color:<?=$isLive?'#4ade80':($s['status']==='active'?'#f87171':'#64748b')?>;
                         border:1px solid <?=$isLive?'rgba(34,197,94,.3)':($s['status']==='active'?'rgba(239,68,68,.3)':'rgba(100,116,139,.3)')?>;">
              <span style="width:5px;height:5px;border-radius:50%;background:currentColor;"></span>
              <?= $isLive ? 'آنلاین' : ($s['status']==='active' ? 'آفلاین' : 'غیرفعال') ?>
            </span>
          </td>
          <td style="padding:12px;color:#94a3b8;font-size:12px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= e($s['current_item'] ?? '—') ?>
          </td>
          <td style="padding:12px;color:#64748b;font-size:12px;"><?= e($s['playlist_name'] ?? '—') ?></td>
          <td style="padding:12px;color:#64748b;font-size:11px;font-family:monospace;">
            <?= $s['last_seen_at'] ? timeAgo($s['last_seen_at']) : '—' ?>
          </td>
          <td style="padding:12px;">
            <?php if ($cpu !== null): ?>
            <div style="display:flex;align-items:center;gap:6px;">
              <div style="width:40px;height:4px;background:rgba(255,255,255,0.08);border-radius:2px;overflow:hidden;">
                <div style="height:100%;width:<?=$cpu?>%;background:<?=$cpu>80?'#ef4444':($cpu>60?'#f59e0b':'#22c55e')?>;border-radius:2px;"></div>
              </div>
              <span style="font-size:11px;color:#64748b;"><?=$cpu?>%</span>
            </div>
            <?php else: ?><span style="color:#2d2d40;">—</span><?php endif; ?>
          </td>
          <td style="padding:12px;">
            <?php if ($mem !== null): ?>
            <div style="display:flex;align-items:center;gap:6px;">
              <div style="width:40px;height:4px;background:rgba(255,255,255,0.08);border-radius:2px;overflow:hidden;">
                <div style="height:100%;width:<?=$mem?>%;background:<?=$mem>85?'#ef4444':($mem>70?'#f59e0b':'#3b82f6')?>;border-radius:2px;"></div>
              </div>
              <span style="font-size:11px;color:#64748b;"><?=$mem?>%</span>
            </div>
            <?php else: ?><span style="color:#2d2d40;">—</span><?php endif; ?>
          </td>
          <td style="padding:12px;">
            <div style="display:flex;gap:4px;">
              <a href="/admin/screens/<?=$s['id']?>" class="btn-ghost text-xs px-2 py-1.5" title="مدیریت">
                <i class="fas fa-gear text-slate-400 text-xs"></i>
              </a>
              <a href="/player/<?=e($s['code'])?>" target="_blank" class="btn-ghost text-xs px-2 py-1.5" title="پلیر">
                <i class="fas fa-play text-green-400 text-xs"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
@keyframes pulse-dot {
  0%,100% { opacity:1; transform:scale(1); }
  50%      { opacity:0.5; transform:scale(1.4); }
}
</style>

<script>
// ─── View toggle ──────────────────────────────────────────────
function setView(v) {
  document.getElementById('grid-view').style.display  = v==='grid'  ? '' : 'none';
  document.getElementById('table-view').style.display = v==='table' ? '' : 'none';
  document.getElementById('view-grid').style.background  = v==='grid'  ? 'rgba(26,122,196,0.2)' : 'transparent';
  document.getElementById('view-grid').style.color  = v==='grid'  ? '#1a7ac4' : '#64748b';
  document.getElementById('view-table').style.background = v==='table' ? 'rgba(26,122,196,0.2)' : 'transparent';
  document.getElementById('view-table').style.color = v==='table' ? '#1a7ac4' : '#64748b';
}

// ─── Auto refresh ─────────────────────────────────────────────
async function refreshStatus() {
  try {
    const r = await fetch('/api/v1/screens/status');
    const d = await r.json();
    if (!d.success) return;

    // بروزرسانی stats
    const stats = {total:0, online:0, offline:0, pending:0};
    d.data.forEach(s => {
      stats.total++;
      const secsAgo = s.seconds_ago ?? 9999;
      if (s.is_online && secsAgo < 120) stats.online++;
      else if (s.status === 'active') stats.offline++;
      else if (s.status === 'pending') stats.pending++;
    });

    // بروزرسانی هر کارت
    d.data.forEach(s => {
      const card = document.querySelector(`.screen-card[data-id="${s.id}"]`);
      if (!card) return;

      const secsAgo = s.seconds_ago ?? 9999;
      const isLive  = s.is_online && secsAgo < 120;

      // border color
      card.style.borderColor = isLive ? 'rgba(34,197,94,.25)' : 'rgba(255,255,255,.07)';

      // current item
      const previewInner = card.querySelector('[data-preview-item]');
      if (previewInner && s.current_item) {
        previewInner.textContent = s.current_item;
      }
    });

    // بروزرسانی زمان
    const now = new Date();
    document.getElementById('last-update').textContent = 
      ' · آخرین بروزرسانی: ' + now.toLocaleTimeString('fa-IR');

  } catch(e) {}
}

// هر ۱۵ ثانیه refresh
setInterval(refreshStatus, 15000);

// بروزرسانی اولیه بعد از ۲ ثانیه
setTimeout(refreshStatus, 2000);

// ─── اعلان اضطراری سراسری ───────────────────────────────────────────
function emgHdr() {
  return { 'Content-Type': 'application/json',
           'X-CSRF-Token': document.querySelector('meta[name=csrf-token]')?.content || '' };
}
function openEmg()  { document.getElementById('emgModal').classList.remove('hidden'); }
function closeEmg() { document.getElementById('emgModal').classList.add('hidden'); }

function sendEmg() {
  const text = document.getElementById('emg-text').value.trim();
  if (!text) { alert('متن اعلان الزامی است'); return; }

  /* تایید صریح: این دکمه روی تلویزیون هر اتاق هتل تمام‌صفحه می‌رود و
     میهمان نمی‌تواند ببنددش — زدن تصادفی‌اش گران است. */
  if (!confirm('این اعلان روی همه‌ی صفحه‌ها و تلویزیون اتاق‌ها نمایش داده می‌شود. ادامه؟')) return;

  const btn = document.getElementById('emg-send');
  btn.disabled = true;
  fetch('/api/v1/broadcast/all', {
    method: 'POST', headers: emgHdr(),
    body: JSON.stringify({
      type: 'text',
      content: text,
      duration: parseInt(document.getElementById('emg-dur').value, 10)
    })
  }).then(r => r.json()).then(r => {
    btn.disabled = false;
    alert(r.message || (r.success ? 'ارسال شد' : 'ارسال نشد'));
    if (r.success) closeEmg();
  }).catch(() => { btn.disabled = false; alert('ارتباط با سرور برقرار نشد'); });
}

function stopEmg() {
  if (!confirm('اعلان اضطراری روی همه‌ی صفحه‌ها متوقف شود؟')) return;
  fetch('/api/v1/broadcast/all', {
    method: 'POST', headers: emgHdr(),
    body: JSON.stringify({ clear: 1 })
  }).then(r => r.json()).then(r => {
    alert(r.message || 'توقف ارسال شد');
    closeEmg();
  }).catch(() => alert('ارتباط با سرور برقرار نشد'));
}
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
