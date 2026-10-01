<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-bug" style="color:#f59e0b;margin-left:10px;"></i>لاگ و عیب‌یابی
  </h1>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <form method="POST" action="/admin/system/logs/clear" onsubmit="return confirm('لاگ خطاهای PHP خالی شود؟')">
      <?= csrf_field() ?>
      <button type="submit" class="btn-ghost text-sm px-3" style="color:#f87171;">
        <i class="fas fa-trash text-xs ml-1"></i>خالی‌کردن لاگ خطا
      </button>
    </form>
    <form method="POST" action="/admin/system/logs/clear?what=cron" onsubmit="return confirm('لاگ کارهای زمان‌بندی‌شده خالی شود؟')">
      <?= csrf_field() ?>
      <button type="submit" class="btn-ghost text-sm px-3" style="color:#fbbf24;">
        <i class="fas fa-trash text-xs ml-1"></i>خالی‌کردن لاگ cron
      </button>
    </form>
  </div>
</div>

<!-- فیلتر -->
<form method="GET" action="/admin/system/logs"
  style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;align-items:end;">
  <div>
    <label class="form-label">سطح</label>
    <select name="level" class="form-input" style="min-width:150px;">
      <option value="">همه</option>
      <option value="error"   <?= $level === 'error'   ? 'selected' : '' ?>>خطا و Exception</option>
      <option value="warning" <?= $level === 'warning' ? 'selected' : '' ?>>هشدار</option>
    </select>
  </div>
  <div style="flex:1;min-width:200px;">
    <label class="form-label">جستجو در متن</label>
    <input name="q" value="<?= e($search) ?>" class="form-input" placeholder="مثلا نام فایل یا پیام خطا">
  </div>
  <button type="submit" class="btn-primary text-sm" style="padding:9px 16px;">اعمال</button>
  <?php if ($level || $search): ?>
    <a href="/admin/system/logs" class="btn-ghost text-sm" style="padding:9px 14px;">پاک‌کردن فیلتر</a>
  <?php endif; ?>
</form>

<!-- ══ خطاهای PHP ══ -->
<div class="card" style="margin-bottom:22px;">
  <div style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
    <h3 style="font-size:14px;font-weight:700;color:#fff;">
      <i class="fas fa-triangle-exclamation ml-2" style="color:#ef4444;"></i>خطاهای PHP و Exception
      <span style="font-size:11px;color:#64748b;font-weight:400;">(<?= count($errorLines) ?> خط — تازه‌ترین بالا)</span>
    </h3>
    <span style="font-size:11px;color:#64748b;font-family:monospace;direction:ltr;">
      storage/logs/php-errors.log · <?= number_format($logSize / 1024, 1) ?> KB
    </span>
  </div>

  <?php if (!$logExists): ?>
    <div style="padding:22px;text-align:center;color:#475569;font-size:12px;line-height:2;">
      فایل لاگ هنوز ساخته نشده — یعنی از آخرین راه‌اندازی خطایی ثبت نشده.<br>
      <span style="color:#64748b;">توجه: خطاها فقط وقتی <code>APP_DEBUG=false</code> باشد در این فایل نوشته می‌شوند.</span>
    </div>
  <?php elseif (empty($errorLines)): ?>
    <div style="padding:22px;text-align:center;color:#22c55e;font-size:12px;">
      <i class="fas fa-check-circle"></i> خطایی مطابق فیلتر پیدا نشد
    </div>
  <?php else: ?>
    <div style="max-height:460px;overflow:auto;background:#0b0b11;">
      <?php foreach ($errorLines as $line):
        $isExc  = stripos($line, 'EXCEPTION') !== false || stripos($line, 'Fatal') !== false;
        $isWarn = stripos($line, 'Warning') !== false || stripos($line, 'Deprecated') !== false;
        $color  = $isExc ? '#f87171' : ($isWarn ? '#fbbf24' : '#94a3b8');
      ?>
      <div style="padding:6px 14px;border-bottom:1px solid rgba(255,255,255,.03);font-family:ui-monospace,monospace;
                  font-size:11px;line-height:1.7;color:<?= $color ?>;direction:ltr;text-align:left;
                  white-space:pre-wrap;word-break:break-word;"><?= e($line) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ══ کارهای زمان‌بندی‌شده (cron) ══ -->
<?php $cronLines = $cronLines ?? []; ?>
<div class="card" style="margin-bottom:22px;">
  <div style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
    <h3 style="font-size:14px;font-weight:700;color:#fff;">
      <i class="fas fa-clock-rotate-left ml-2" style="color:#fbbf24;"></i>کارهای زمان‌بندی‌شده
      <span style="font-size:11px;color:#64748b;font-weight:400;">
        (<?= count($cronLines) ?> خط — فقط شکست‌ها ثبت می‌شوند)
      </span>
    </h3>
    <span style="font-size:11px;color:#64748b;font-family:monospace;direction:ltr;">
      storage/logs/cron.log · <?= number_format(($cronSize ?? 0) / 1024, 1) ?> KB
    </span>
  </div>

  <?php if (empty($cronLines)): ?>
    <div style="padding:20px;text-align:center;color:#22c55e;font-size:12px;line-height:2;">
      <i class="fas fa-check-circle"></i>
      هیچ کار زمان‌بندی‌شده‌ای شکست نخورده است
      <?php if (empty($cronExists)): ?>
        <br><span style="color:#64748b;">
          (فایل لاگ هنوز ساخته نشده — تا نخستین شکست ساخته نمی‌شود)
        </span>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div style="max-height:320px;overflow:auto;background:#0b0b11;">
      <?php foreach ($cronLines as $line):
        // سرخط هر رکورد با [تاریخ] شروع می‌شود؛ خطوط خروجی تورفته‌اند
        $isHead = (bool)preg_match('/^\[\d{4}-/', $line);
      ?>
      <div style="padding:5px 14px;font-family:ui-monospace,monospace;font-size:11px;line-height:1.7;
                  direction:ltr;text-align:left;white-space:pre-wrap;word-break:break-word;
                  <?= $isHead
                      ? 'color:#fbbf24;border-top:1px solid rgba(255,255,255,.06);font-weight:600;'
                      : 'color:#94a3b8;' ?>"><?= e($line) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ══ رویدادهای برنامه ══ -->
<div class="card">
  <div style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.06);">
    <h3 style="font-size:14px;font-weight:700;color:#fff;">
      <i class="fas fa-list-check ml-2" style="color:#60a5fa;"></i>رویدادهای برنامه
      <span style="font-size:11px;color:#64748b;font-weight:400;">(<?= count($events) ?> مورد اخیر)</span>
    </h3>
  </div>
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
      <thead>
        <tr style="color:#64748b;text-align:right;">
          <th style="padding:9px 12px;font-weight:600;">زمان</th>
          <th style="padding:9px 12px;font-weight:600;">رویداد</th>
          <th style="padding:9px 12px;font-weight:600;">موضوع</th>
          <th style="padding:9px 12px;font-weight:600;">کاربر</th>
          <th style="padding:9px 12px;font-weight:600;">IP</th>
          <th style="padding:9px 12px;font-weight:600;">توضیح</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($events)): ?>
        <tr><td colspan="6" style="padding:26px;text-align:center;color:#475569;">رویدادی ثبت نشده</td></tr>
      <?php else: foreach ($events as $ev): ?>
        <tr style="border-top:1px solid rgba(255,255,255,.04);">
          <td style="padding:8px 12px;color:#64748b;font-family:monospace;font-size:11px;direction:ltr;">
            <?= e(substr((string)$ev['created_at'], 0, 19)) ?>
          </td>
          <td style="padding:8px 12px;color:#fff;font-family:monospace;font-size:11px;direction:ltr;">
            <?= e((string)$ev['action']) ?>
          </td>
          <td style="padding:8px 12px;color:#94a3b8;font-size:11px;">
            <?= e((string)($ev['subject_type'] ?? '—')) ?><?= $ev['subject_id'] ? ' #' . (int)$ev['subject_id'] : '' ?>
          </td>
          <td style="padding:8px 12px;color:#94a3b8;"><?= e((string)($ev['user_name'] ?? '—')) ?></td>
          <td style="padding:8px 12px;color:#64748b;font-family:monospace;font-size:11px;direction:ltr;">
            <?= e((string)($ev['ip_address'] ?? '—')) ?>
          </td>
          <td style="padding:8px 12px;color:#94a3b8;font-size:11px;max-width:320px;overflow:hidden;text-overflow:ellipsis;">
            <?= e((string)($ev['description'] ?? '')) ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
