<?php
use App\Core\Lang;
$lang = Lang::current();
$isRtl = Lang::isRtl();
include VIEWS_PATH . '/partials/layout.php';

// آیکون‌های نوع پیام
$typeIcons = [
    'welcome'       => ['🤝', '#22c55e', __('messages.type.welcome')],
    'congratulation'=> ['🎉', '#1a7ac4', __('messages.type.congrats')],
    'announcement'  => ['📢', '#818cf8', __('messages.type.announce')],
    'warning'       => ['⚠️', '#f59e0b', __('messages.type.warning')],
    'info'          => ['ℹ️', '#00e5ff', __('messages.type.info')],
];
$stateColors = [
    'live'      => ['#22c55e', 'rgba(34,197,94,.12)'],
    'scheduled' => ['#f59e0b', 'rgba(245,158,11,.12)'],
    'ended'     => ['#64748b', 'rgba(100,116,139,.1)'],
];
$stateLabels = [
    'live'      => __('messages.live_now'),
    'scheduled' => __('messages.scheduled'),
    'ended'     => __('messages.ended'),
];
?>

<style>
.msg-card{background:#111118;border:1px solid rgba(255,255,255,.07);border-radius:16px;overflow:hidden;transition:border-color .2s;}
.msg-card:hover{border-color:rgba(26,122,196,.3);}
.msg-card.live{border-color:rgba(34,197,94,.25);box-shadow:0 0 20px rgba(34,197,94,.06);}
.tab-btn{padding:9px 20px;border-radius:9px;font-size:13px;font-weight:700;text-decoration:none;
         display:flex;align-items:center;gap:6px;transition:all .2s;cursor:pointer;
         background:transparent;color:#64748b;border:none;font-family:inherit;}
.tab-btn.active{background:rgba(26,122,196,.18);color:#1a7ac4;}
</style>

<!-- ─── Header ────────────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
  <div>
    <h1 style="font-size:22px;font-weight:800;color:#fff;display:flex;align-items:center;gap:10px;">
      <span style="font-size:26px;">💬</span> <?= __('messages.title') ?>
    </h1>
    <p style="font-size:13px;color:#475569;margin-top:4px;"><?= __('messages.desc') ?></p>
  </div>
  <button onclick="openModal()"
          style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;
                 background:linear-gradient(135deg,#1a7ac4,#12558f);color:#fff;
                 border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;">
    <i class="fas fa-plus text-xs"></i> <?= __('messages.new') ?>
  </button>
</div>

<!-- ─── آمار ─────────────────────────────────────────────────────── -->
<?php
$total     = count($messages ?? []);
$live      = count(array_filter($messages ?? [], fn($m) => $m['state'] === 'live'));
$scheduled = count(array_filter($messages ?? [], fn($m) => $m['state'] === 'scheduled'));
$ended     = count(array_filter($messages ?? [], fn($m) => $m['state'] === 'ended'));
?>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px;">
  <?php foreach([
    [__('messages.total'),     $total,     '#94a3b8', 'fas fa-message'],
    [__('messages.live_now'),  $live,      '#22c55e', 'fas fa-circle'],
    [__('messages.scheduled'), $scheduled, '#f59e0b', 'fas fa-clock'],
    [__('messages.ended'),     $ended,     '#64748b', 'fas fa-circle-check'],
  ] as [$label, $val, $col, $icon]): ?>
  <div style="background:#111118;border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:16px 20px;">
    <div style="font-size:11px;color:#475569;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
      <i class="<?=$icon?>" style="color:<?=$col?>;font-size:10px;"></i><?=$label?>
    </div>
    <div style="font-size:26px;font-weight:800;color:<?=$col?>;"><?=$val?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ─── لیست پیام‌ها ──────────────────────────────────────────────── -->
<?php if (empty($messages)): ?>
<div style="text-align:center;padding:80px 20px;">
  <div style="font-size:72px;opacity:.12;margin-bottom:20px;">💬</div>
  <div style="font-size:18px;color:#475569;margin-bottom:8px;"><?= __('messages.no_messages') ?></div>
  <button onclick="openModal()"
          style="display:inline-flex;align-items:center;gap:8px;padding:12px 24px;
                 background:linear-gradient(135deg,#1a7ac4,#12558f);color:#fff;
                 border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;margin-top:12px;">
    <i class="fas fa-plus"></i> <?= __('messages.new') ?>
  </button>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;">
<?php foreach ($messages as $msg):
  $state = $msg['state'] ?? 'ended';
  [$stateCol, $stateBg] = $stateColors[$state] ?? ['#64748b','rgba(100,116,139,.1)'];
  $stateLabel = $stateLabels[$state] ?? $state;
  [$typeIcon, $typeCol, $typeLabel] = $typeIcons[$msg['type']] ?? ['📢','#818cf8',''];

  // نمایش عنوان بر اساس زبان فعلی
  $displayTitle = match($lang) {
    'en' => $msg['title_en'] ?: $msg['title'],
    'ar' => $msg['title_ar'] ?: $msg['title'],
    default => $msg['title'],
  };
  $displayBody = match($lang) {
    'en' => $msg['body_en'] ?: $msg['body'],
    'ar' => $msg['body_ar'] ?: $msg['body'],
    default => $msg['body'],
  };
?>
<div class="msg-card <?= $state === 'live' ? 'live' : '' ?>">
  <div style="padding:16px 20px;display:flex;align-items:flex-start;gap:14px;">
    <!-- آیکون -->
    <div style="width:46px;height:46px;border-radius:12px;flex-shrink:0;
                background:<?=$typeCol?>18;border:1px solid <?=$typeCol?>44;
                display:flex;align-items:center;justify-content:center;font-size:22px;">
      <?= $msg['icon'] ?: $typeIcon ?>
    </div>

    <!-- محتوا -->
    <div style="flex:1;min-width:0;">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
        <span style="font-size:15px;font-weight:700;color:#fff;"><?= e($displayTitle) ?></span>
        <!-- state badge -->
        <span style="padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700;
                     background:<?=$stateBg?>;color:<?=$stateCol?>;border:1px solid <?=$stateCol?>44;">
          <?= $state === 'live' ? '● ' : '' ?><?= $stateLabel ?>
        </span>
        <!-- type badge -->
        <span style="padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700;
                     background:<?=$typeCol?>15;color:<?=$typeCol?>;border:1px solid <?=$typeCol?>30;">
          <?=$typeLabel?>
        </span>
        <!-- active toggle -->
        <label style="display:flex;align-items:center;gap:5px;cursor:pointer;margin-<?=$isRtl?'right':'left'?>:auto;">
          <span style="font-size:11px;color:#475569;"><?= __('messages.active') ?></span>
          <input type="checkbox" <?= $msg['is_active'] ? 'checked' : '' ?>
                 onchange="toggleMsg(<?=$msg['id']?>)"
                 style="accent-color:#1a7ac4;width:16px;height:16px;cursor:pointer;">
        </label>
      </div>

      <p style="font-size:13px;color:#64748b;margin-bottom:10px;line-height:1.5;">
        <?= e(mb_substr($displayBody, 0, 120)) ?><?= mb_strlen($displayBody) > 120 ? '…' : '' ?>
      </p>

      <!-- متادیتا -->
      <div style="display:flex;flex-wrap:wrap;gap:14px;font-size:11px;color:#334155;">
        <span><i class="fas fa-calendar-alt text-xs ml-1"></i>
          <?= date('Y/m/d H:i', strtotime($msg['start_at'])) ?>
          <?= $msg['end_at'] ? ' ← ' . date('Y/m/d H:i', strtotime($msg['end_at'])) : '' ?>
        </span>
        <span><i class="fas fa-clock text-xs ml-1"></i><?=$msg['duration']?>s</span>
        <span><i class="fas fa-repeat text-xs ml-1"></i><?=$msg['repeat_type']?></span>
        <span><i class="fas fa-bullseye text-xs ml-1"></i>
          <?= $msg['target'] === 'all' ? __('messages.target.all') : ucfirst($msg['target']) ?>
        </span>
        <?php if ($msg['show_count'] > 0): ?>
        <span><i class="fas fa-eye text-xs ml-1" style="color:#1a7ac4;"></i><?=$msg['show_count']?> بار نمایش</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- دکمه‌ها -->
    <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
      <form method="POST" action="/admin/messages/<?=$msg['id']?>/delete"
            onsubmit="return confirm('<?= e(__('messages.confirm_delete')) ?>')">
        <?= csrf_field() ?>
        <button type="submit"
                style="padding:7px 14px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);
                       border-radius:8px;color:#ef4444;font-size:11px;cursor:pointer;font-family:inherit;
                       display:flex;align-items:center;gap:5px;white-space:nowrap;">
          <i class="fas fa-trash text-xs"></i> <?= __('btn.delete') ?>
        </button>
      </form>
    </div>
  </div>

  <!-- نوار رنگ پایین -->
  <div style="height:3px;background:linear-gradient(90deg,<?=$typeCol?>,<?=$typeCol?>44);"></div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>


<!-- ═══ Modal: پیام جدید ══════════════════════════════════════════════ -->
<div id="msg-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;
            align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);">
  <div style="background:#111118;border:1px solid rgba(255,255,255,.1);border-radius:20px;
              width:100%;max-width:720px;max-height:90vh;overflow-y:auto;">

    <!-- modal header -->
    <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,.06);
                display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;
                background:#111118;z-index:2;border-radius:20px 20px 0 0;">
      <h2 style="font-size:16px;font-weight:800;color:#fff;display:flex;align-items:center;gap:8px;">
        <span>💬</span> <?= __('messages.new') ?>
      </h2>
      <button onclick="closeModal()"
              style="background:rgba(255,255,255,.08);border:none;color:#94a3b8;
                     width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:16px;">×</button>
    </div>

    <form method="POST" action="/admin/messages" style="padding:24px;">
      <?= csrf_field() ?>

      <!-- ── زبان‌های پیام ── -->
      <div style="background:rgba(26,122,196,.05);border:1px solid rgba(26,122,196,.2);
                  border-radius:12px;padding:16px;margin-bottom:20px;">
        <div style="font-size:11px;font-weight:700;color:#1a7ac4;margin-bottom:14px;
                    display:flex;align-items:center;gap:6px;">
          <i class="fas fa-language text-xs"></i> محتوای چندزبانه
        </div>

        <!-- tabs -->
        <div style="display:flex;gap:4px;background:rgba(0,0,0,.3);border-radius:10px;padding:3px;margin-bottom:16px;width:fit-content;">
          <button type="button" onclick="showLangTab('fa')" id="ltab-fa"
                  class="tab-btn active">🇮🇷 فارسی</button>
          <button type="button" onclick="showLangTab('en')" id="ltab-en"
                  class="tab-btn">🇬🇧 English</button>
          <button type="button" onclick="showLangTab('ar')" id="ltab-ar"
                  class="tab-btn">🇸🇦 عربي</button>
        </div>

        <!-- فارسی -->
        <div id="ltab-content-fa">
          <div style="margin-bottom:10px;">
            <label class="form-label"><?= __('messages.lang.fa') ?> *</label>
            <input type="text" name="title" class="form-input" required
                   placeholder="مثلاً: خوش آمدید به هتل رویال">
          </div>
          <div>
            <label class="form-label">متن فارسی *</label>
            <textarea name="body" class="form-input" rows="3" required
                      placeholder="متن پیامی که روی صفحه نمایش داده می‌شود..."></textarea>
          </div>
        </div>

        <!-- انگلیسی -->
        <div id="ltab-content-en" style="display:none;">
          <div style="margin-bottom:10px;">
            <label class="form-label"><?= __('messages.lang.en') ?></label>
            <input type="text" name="title_en" class="form-input"
                   placeholder="e.g. Welcome to Royal Hotel">
          </div>
          <div>
            <label class="form-label">English Text</label>
            <textarea name="body_en" class="form-input" rows="3" dir="ltr"
                      placeholder="Message text shown on screen..."></textarea>
          </div>
        </div>

        <!-- عربی -->
        <div id="ltab-content-ar" style="display:none;">
          <div style="margin-bottom:10px;">
            <label class="form-label"><?= __('messages.lang.ar') ?></label>
            <input type="text" name="title_ar" class="form-input"
                   placeholder="مثلاً: مرحباً بكم في فندق رويال">
          </div>
          <div>
            <label class="form-label">النص العربي</label>
            <textarea name="body_ar" class="form-input" rows="3"
                      placeholder="النص الذي يظهر على الشاشة..."></textarea>
          </div>
        </div>
      </div>

      <!-- ── تنظیمات ── -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
        <div>
          <label class="form-label"><?= __('messages.type') ?></label>
          <select name="type" class="form-input" onchange="updateIcon(this.value)">
            <option value="welcome">🤝 <?= __('messages.type.welcome') ?></option>
            <option value="congratulation">🎉 <?= __('messages.type.congrats') ?></option>
            <option value="announcement" selected>📢 <?= __('messages.type.announce') ?></option>
            <option value="warning">⚠️ <?= __('messages.type.warning') ?></option>
            <option value="info">ℹ️ <?= __('messages.type.info') ?></option>
          </select>
        </div>
        <div>
          <label class="form-label"><?= __('messages.style') ?></label>
          <select name="style" class="form-input">
            <option value="overlay"><?= __('messages.style.overlay') ?></option>
            <option value="fullscreen"><?= __('messages.style.fullscreen') ?></option>
            <option value="popup"><?= __('messages.style.popup') ?></option>
            <option value="banner"><?= __('messages.style.banner') ?></option>
          </select>
        </div>
        <div>
          <label class="form-label">آیکون (Emoji)</label>
          <input type="text" name="icon" id="msg-icon" class="form-input"
                 placeholder="🤝" maxlength="4" style="font-size:20px;text-align:center;">
        </div>
        <div>
          <label class="form-label"><?= __('messages.duration') ?></label>
          <input type="number" name="duration" class="form-input" value="15" min="5" max="300">
        </div>
      </div>

      <!-- ── رنگ‌ها ── -->
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:16px;">
        <?php foreach([
          ['bg_color','#1a1a2e','رنگ پس‌زمینه'],
          ['text_color','#ffffff','رنگ متن'],
          ['accent_color','#1a7ac4','رنگ تأکید'],
        ] as [$n,$v,$l]): ?>
        <div>
          <label class="form-label" style="font-size:11px;"><?=$l?></label>
          <div style="display:flex;gap:6px;align-items:center;">
            <input type="color" name="<?=$n?>" value="<?=$v?>"
                   style="width:40px;height:34px;border:none;background:transparent;cursor:pointer;border-radius:6px;">
            <span style="font-size:11px;color:#64748b;font-family:monospace;"><?=$v?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- ── هدف ── -->
      <div style="margin-bottom:16px;">
        <label class="form-label"><?= __('messages.target') ?></label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
          <?php foreach(['all'=>__('messages.target.all'),'screen'=>__('messages.target.screen'),'group'=>__('messages.target.group')] as $tv=>$tl): ?>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
            <input type="radio" name="target" value="<?=$tv?>"
                   <?= $tv==='all'?'checked':'' ?>
                   onchange="toggleTargetSelect(this.value)"
                   style="accent-color:#1a7ac4;">
            <span style="font-size:13px;color:#94a3b8;"><?=$tl?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <!-- انتخاب صفحه -->
        <div id="target-screen-sel" style="display:none;">
          <select name="target_ids[]" class="form-input" multiple size="4">
            <?php foreach ($screens ?? [] as $sc): ?>
            <option value="<?=$sc['id']?>"><?=e($sc['name'])?> (<?=e($sc['code'])?>)</option>
            <?php endforeach; ?>
          </select>
          <div style="font-size:10px;color:#475569;margin-top:4px;">Ctrl+کلیک برای چند انتخاب</div>
        </div>
        <!-- انتخاب گروه -->
        <div id="target-group-sel" style="display:none;">
          <select name="target_ids[]" class="form-input" multiple size="3">
            <?php foreach ($groups ?? [] as $g): ?>
            <option value="<?=$g['id']?>"><?=e($g['name'])?> (<?=$g['type']?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- ── زمان‌بندی ── -->
      <div style="background:rgba(129,140,248,.05);border:1px solid rgba(129,140,248,.2);
                  border-radius:12px;padding:16px;margin-bottom:20px;">
        <div style="font-size:11px;font-weight:700;color:#818cf8;margin-bottom:12px;">
          <i class="fas fa-clock text-xs ml-1"></i> <?= __('messages.start_at') ?> و تکرار
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
          <div>
            <label class="form-label"><?= __('messages.start_at') ?> *</label>
            <input type="datetime-local" name="start_at" class="form-input" required
                   value="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div>
            <label class="form-label"><?= __('messages.end_at') ?> <span style="color:#475569;">(اختیاری)</span></label>
            <input type="datetime-local" name="end_at" class="form-input">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div>
            <label class="form-label"><?= __('messages.repeat') ?></label>
            <select name="repeat_type" class="form-input" onchange="toggleRepeatDays(this.value)">
              <option value="once"><?= __('messages.repeat.once') ?></option>
              <option value="daily"><?= __('messages.repeat.daily') ?></option>
              <option value="weekly"><?= __('messages.repeat.weekly') ?></option>
              <option value="monthly"><?= __('messages.repeat.monthly') ?></option>
            </select>
          </div>
          <div id="repeat-time-div" style="display:none;">
            <label class="form-label">ساعت تکرار</label>
            <input type="time" name="repeat_time" class="form-input" value="09:00">
          </div>
        </div>
        <!-- روزهای هفته -->
        <div id="repeat-days-div" style="display:none;margin-top:10px;">
          <label class="form-label" style="font-size:11px;">روزهای هفته</label>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px;">
            <?php foreach(['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6'] as $label=>$val): ?>
            <?php $dayNames=['شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه'][(int)$val]; ?>
            <label style="display:flex;flex-direction:column;align-items:center;gap:3px;cursor:pointer;">
              <input type="checkbox" name="repeat_days[]" value="<?=$val?>"
                     style="accent-color:#818cf8;">
              <span style="font-size:10px;color:#64748b;"><?=$dayNames?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- ── دکمه‌ها ── -->
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" onclick="closeModal()"
                style="padding:10px 20px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
                       color:#94a3b8;border-radius:10px;font-size:13px;cursor:pointer;font-family:inherit;">
          <?= __('btn.cancel') ?>
        </button>
        <button type="submit"
                style="padding:10px 24px;background:linear-gradient(135deg,#1a7ac4,#12558f);
                       color:#fff;border:none;border-radius:10px;font-size:13px;font-weight:700;
                       cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-paper-plane text-xs"></i> <?= __('btn.save') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Modal ─────────────────────────────────────────────────────────────
function openModal() {
  document.getElementById('msg-modal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeModal() {
  document.getElementById('msg-modal').style.display = 'none';
  document.body.style.overflow = '';
}
document.getElementById('msg-modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// ── Language tabs ─────────────────────────────────────────────────────
function showLangTab(lang) {
  ['fa','en','ar'].forEach(l => {
    document.getElementById('ltab-' + l).classList.toggle('active', l === lang);
    document.getElementById('ltab-content-' + l).style.display = l === lang ? 'block' : 'none';
  });
}

// ── Icon auto-fill ────────────────────────────────────────────────────
const typeIcons = { welcome:'🤝', congratulation:'🎉', announcement:'📢', warning:'⚠️', info:'ℹ️' };
function updateIcon(type) {
  const el = document.getElementById('msg-icon');
  if (!el.value || Object.values(typeIcons).includes(el.value)) {
    el.value = typeIcons[type] || '📢';
  }
}

// ── Target select ─────────────────────────────────────────────────────
function toggleTargetSelect(val) {
  document.getElementById('target-screen-sel').style.display = val === 'screen' ? 'block' : 'none';
  document.getElementById('target-group-sel').style.display  = val === 'group'  ? 'block' : 'none';
}

// ── Repeat days ───────────────────────────────────────────────────────
function toggleRepeatDays(val) {
  const days = document.getElementById('repeat-days-div');
  const time = document.getElementById('repeat-time-div');
  days.style.display = val === 'weekly' ? 'block' : 'none';
  time.style.display = (val !== 'once') ? 'block' : 'none';
}

// ── Toggle active ─────────────────────────────────────────────────────
async function toggleMsg(id) {
  await fetch(`/admin/messages/${id}/toggle`, {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]')?.content||''},
    body:JSON.stringify({}),
  });
}
</script>
