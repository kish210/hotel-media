<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<div class="flex items-center justify-between mb-5">
  <h1 class="text-xl font-bold text-white flex items-center gap-2"><i class="fas fa-calendar text-orange-400"></i> زمان‌بندی محتوا</h1>
  <button onclick="document.getElementById('addScheduleModal').classList.remove('hidden')" class="btn-primary text-sm flex items-center gap-2"><i class="fas fa-plus text-xs"></i> زمان‌بندی جدید</button>
</div>

<?php if (!empty($clashes)): ?>
<div style="background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.3);
            border-radius:14px;padding:14px 18px;margin-bottom:16px;">
  <div style="font-size:13px;font-weight:700;color:#fbbf24;margin-bottom:8px;">
    <i class="fas fa-triangle-exclamation ml-1"></i>زمان‌بندی‌های هم‌پوشان
  </div>
  <div style="font-size:12px;color:#8593a8;line-height:2;margin-bottom:10px;">
    این‌ها روی یک هدف و با اولویت یکسان فعال‌اند، پس معلوم نیست کدام پخش می‌شود.
    یکی را خاموش کنید یا اولویتش را عوض کنید.
  </div>
  <?php foreach ($clashes as $c): ?>
    <div style="font-size:12.5px;color:#e2e8f0;padding:4px 0;">
      • <b><?= e($c['target']) ?></b> — اولویت <?= (int)$c['priority'] ?>:
      <?= (int)$c['n'] ?> زمان‌بندی (<?= e($c['names']) ?>)
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead><tr class="border-b border-white/5 text-xs text-slate-500 uppercase">
      <th class="text-right p-3">نام</th>
      <th class="text-right p-3">پلی‌لیست</th>
      <th class="text-right p-3">صفحه</th>
      <th class="text-right p-3">نوع</th>
      <th class="text-right p-3">اولویت</th>
      <th class="p-3"></th>
    </tr></thead>
    <tbody>
      <?php if (empty($schedules)): ?>
      <tr><td colspan="6" class="text-center py-12 text-slate-600"><i class="fas fa-calendar text-4xl mb-3 block opacity-20"></i>زمان‌بندی‌ای تنظیم نشده</td></tr>
      <?php else: foreach ($schedules as $sc): ?>
      <tr class="table-row border-b border-white/3">
        <?php
        /* ── جملهٔ خلاصه ────────────────────────────────────────────
           جدول نُه فیلد خام را در ستون‌های جدا نشان می‌داد و اپراتور
           باید خودش سر هم می‌کرد که «این کِی پخش می‌شود». یک جملهٔ
           فارسی همان را می‌گوید. */
        $DAY = ['شنبه','یک‌شنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنج‌شنبه','جمعه'];
        $bits = [];
        $ty = (string)($sc['type'] ?? '');
        if ($ty === 'always')      $bits[] = 'همیشه';
        elseif ($ty === 'daily')   $bits[] = 'هر روز';
        elseif ($ty === 'weekly') {
            $wd = json_decode((string)($sc['weekdays'] ?? ''), true);
            $bits[] = is_array($wd) && $wd
                ? implode('، ', array_map(fn($d) => $DAY[(int)$d] ?? '', $wd))
                : 'هفتگی';
        } elseif ($ty === 'once')  $bits[] = 'یک‌بار';

        if (!empty($sc['start_time'])) {
            $bits[] = 'از ' . substr((string)$sc['start_time'], 0, 5)
                    . (!empty($sc['end_time']) ? ' تا ' . substr((string)$sc['end_time'], 0, 5) : '');
        }
        if (!empty($sc['start_date'])) {
            $bits[] = 'از تاریخ ' . (string)$sc['start_date']
                    . (!empty($sc['end_date']) ? ' تا ' . (string)$sc['end_date'] : '');
        }
        $off = isset($sc['is_active']) && !(int)$sc['is_active'];
        ?>
        <td class="p-3 text-white font-medium"<?= $off ? ' style="opacity:.5;"' : '' ?>>
          <?=e($sc['name'])?>
          <?php if ($off): ?><span style="font-size:10px;color:#fbbf24;">(خاموش)</span><?php endif; ?>
          <div style="font-size:11px;color:#8593a8;font-weight:400;margin-top:3px;">
            <?= e(implode(' · ', $bits)) ?>
          </div>
        </td>
        <td class="p-3 text-slate-400"><?=e($sc['playlist_name'])?></td>
        <?php
        /* هدف واقعی برنامه. پیش از افزودن انتشار روی محل، این ستون هر
           ردیفِ بی‌صفحه را «همه صفحات» می‌خواند — پس یک برنامهٔ مخصوص
           لابی هم همگانی به‌نظر می‌رسید. */
        if (!empty($sc['screen_name'])) {
            $tgt = '<i class="fas fa-tv text-xs opacity-60"></i> ' . e($sc['screen_name']);
        } elseif (!empty($sc['venue_name'])) {
            $tgt = '<i class="fas fa-location-dot text-xs" style="color:#7dd3fc;"></i> '
                 . e($sc['venue_name'])
                 . ' <span class="text-slate-600 text-xs">(' . (int)$sc['venue_screens'] . ' صفحه)</span>';
        } elseif (!empty($sc['group_name'])) {
            $tgt = '<i class="fas fa-object-group text-xs" style="color:#fbbf24;"></i> '
                 . e($sc['group_name'])
                 . ' <span class="text-slate-600 text-xs">(' . (int)$sc['group_screens'] . ' صفحه)</span>';
        } else {
            $tgt = '<i class="fas fa-globe text-xs opacity-60"></i> همه‌ی صفحه‌ها';
        }
        ?>
        <td class="p-3 text-slate-400"><?= $tgt ?></td>
        <td class="p-3"><span class="badge-pending px-2 py-0.5 rounded-full text-xs border"><?= e(["daily" => "روزانه", "weekly" => "هفتگی", "once" => "یک‌بار", "always" => "همیشه"][$sc["type"] ?? ""] ?? $sc["type"]) ?></span></td>
        <td class="p-3 text-slate-400 font-mono"><?=e($sc['priority'])?></td>
        <td class="p-3">
          <form method="POST" action="/admin/schedules/<?=$sc['id']?>/toggle" class="inline">
            <?= csrf_field() ?><button type="submit" class="btn-ghost text-xs px-2 py-1"
              title="<?= $off ? 'روشن کردن' : 'خاموش کردن' ?>"><i class="fas fa-<?= $off ? 'play' : 'pause' ?>"></i></button>
          </form>
          <form method="POST" action="/admin/schedules/<?=$sc['id']?>/duplicate" class="inline">
            <?= csrf_field() ?><button type="submit" class="btn-ghost text-xs px-2 py-1"
              title="کپی (خاموش ساخته می‌شود)"><i class="fas fa-copy"></i></button>
          </form>
          <form method="POST" action="/admin/schedules/<?=$sc['id']?>/delete" class="inline">
            <?= csrf_field() ?><button type="submit" class="btn-danger text-xs px-2 py-1" onclick="return confirm('حذف شود؟')"><i class="fas fa-trash text-xs"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- Add Schedule Modal -->
<div id="addScheduleModal" class="modal-overlay hidden">
  <div class="modal max-w-lg" style="max-height:90vh;overflow-y:auto;">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-bold text-white">زمان‌بندی جدید</h3>
      <button onclick="document.getElementById('addScheduleModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fas fa-xmark"></i></button>
    </div>
    <form method="POST" action="/admin/schedules" class="space-y-3">
      <?= csrf_field() ?>
      <div><label class="form-label">نام *</label><input type="text" name="name" class="form-input" required></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">پلی‌لیست *</label>
          <select name="playlist_id" class="form-input" required>
            <option value="">انتخاب کنید</option>
            <?php foreach ($playlists as $p): ?><option value="<?=$p['id']?>"><?=e($p['name'])?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="form-label">هدف</label>
          <?php /* یک select برای هر سه نوع هدف — سه فیلد موازی یعنی
                   اپراتور می‌تواند هم صفحه و هم محل را پر کند و بعد
                   نداند کدام برنده است. */ ?>
          <select name="target" class="form-input">
            <option value="">همه‌ی صفحه‌ها (همگانی)</option>
            <?php if (!empty($venues)): ?>
            <optgroup label="محل در هتل (Zone)">
              <?php foreach ($venues as $v): ?>
              <option value="venue:<?= (int)$v['id'] ?>"><?= e($v['name']) ?></option>
              <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
            <?php if (!empty($groups)): ?>
            <optgroup label="گروه صفحه‌ها">
              <?php foreach ($groups as $g): ?>
              <option value="group:<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option>
              <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
            <optgroup label="یک صفحه">
              <?php foreach ($screens as $s): ?>
              <option value="screen:<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          </select>
          <div class="text-xs text-slate-500 mt-1">
            هدف خاص‌تر مقدم است: صفحه ← محل یا گروه ← همگانی.
          </div>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">نوع</label>
          <select name="type" class="form-input">
            <?php foreach (['always'=>'همیشه','daily'=>'روزانه','weekly'=>'هفتگی','once'=>'یک‌بار'] as $v=>$l): ?>
            <option value="<?=$v?>"><?=$l?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="form-label">اولویت (۱-۱۰)</label><input type="number" name="priority" class="form-input" value="5" min="1" max="10"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">تاریخ شروع</label><input type="date" name="start_date" class="form-input"></div>
        <div><label class="form-label">تاریخ پایان</label><input type="date" name="end_date" class="form-input"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">ساعت شروع</label><input type="time" name="start_time" class="form-input"></div>
        <div><label class="form-label">ساعت پایان</label><input type="time" name="end_time" class="form-input"></div>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn-primary flex-1 py-2.5">ایجاد زمان‌بندی</button>
        <button type="button" onclick="document.getElementById('addScheduleModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button>
      </div>
    </form>
  </div>
</div>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
