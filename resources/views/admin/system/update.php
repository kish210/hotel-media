<?php
/**
 * به‌روزرسانی سیستم — پنل مدیر ارشد.
 *
 * زنجیره: گیت‌هاب (انتشار پایدار) → سرور هتل → ۳۰۰ تلویزیون
 *
 * @var string $current  نسخه‌ی نصب‌شده
 * @var array  $history  تاریخچه‌ی به‌روزرسانی
 * @var array  $backups  نسخه‌های پشتیبان موجود
 */
include VIEWS_PATH . '/partials/layout.php';
?>

<div class="content">

  <div class="card" style="margin-bottom:var(--s5)">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--s4)">
      <div>
        <h2 style="margin:0;font-size:18px;font-weight:600">نسخه نصب‌شده</h2>
        <div class="num" style="font-size:30px;font-weight:700;color:var(--brand-400);line-height:1.2;margin-top:4px">
          <?= e($current) ?>
        </div>
        <div style="font-size:12.5px;color:var(--text-3);margin-top:6px">
          به‌روزرسانی از مخزن رسمی گرفته می‌شود و فقط انتشارهای پایدار دیده می‌شوند.
        </div>
      </div>
      <button class="btn btn-primary" id="btn-check">
        <i class="fas fa-rotate"></i> بررسی نسخه جدید
      </button>
    </div>

    <div id="check-result" style="margin-top:var(--s5)"></div>
  </div>

  <!-- نوار پیشرفت، فقط هنگام به‌روزرسانی -->
  <div class="card hidden" id="progress-card" style="margin-bottom:var(--s5)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--s3)">
      <strong id="progress-note">در حال آماده‌سازی…</strong>
      <span class="num" id="progress-pct">۰٪</span>
    </div>
    <div style="height:8px;border-radius:99px;background:var(--surface-3);overflow:hidden">
      <div id="progress-bar" style="height:100%;width:0;background:var(--brand-500);
           transition:width .4s var(--ease)"></div>
    </div>
    <div class="alert alert-warn" style="margin-top:var(--s4);margin-bottom:0">
      <i class="fas fa-triangle-exclamation"></i>
      <span>تا پایان کار صفحه را نبندید و سرور را خاموش نکنید.</span>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:var(--s5)">

    <!-- تاریخچه -->
    <div class="card">
      <h3 style="margin:0 0 var(--s4);font-size:15px;font-weight:600">تاریخچه به‌روزرسانی</h3>
      <?php if (!$history): ?>
        <div class="empty-state">هنوز به‌روزرسانی‌ای انجام نشده است</div>
      <?php else: ?>
        <table style="width:100%;border-collapse:collapse">
          <tbody>
          <?php foreach ($history as $h): ?>
            <tr class="table-row" style="border-bottom:1px solid var(--line-1)">
              <td style="padding:9px 4px">
                <span class="num"><?= e($h['from_version']) ?></span>
                <i class="fas fa-arrow-left" style="font-size:10px;color:var(--text-4);margin:0 6px"></i>
                <span class="num"><?= e($h['to_version']) ?></span>
              </td>
              <td style="padding:9px 4px;text-align:left">
                <?php if ((int)$h['success'] === 1): ?>
                  <span class="badge-online">موفق</span>
                <?php else: ?>
                  <span class="badge-offline" title="<?= e($h['note'] ?? '') ?>">ناموفق</span>
                <?php endif; ?>
              </td>
              <td style="padding:9px 4px;text-align:left;font-size:12px;color:var(--text-3);white-space:nowrap">
                <?= e(function_exists('jalaliDate')
                      ? jalaliDate(strtotime((string)$h['created_at']), false)
                      : (string)$h['created_at']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- پشتیبان -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--s4)">
        <h3 style="margin:0;font-size:15px;font-weight:600">نسخه‌های پشتیبان</h3>
        <button class="btn btn-ghost" id="btn-backup">
          <i class="fas fa-box-archive"></i> پشتیبان جدید
        </button>
      </div>

      <div style="font-size:12.5px;color:var(--text-3);line-height:1.9;margin-bottom:var(--s4)">
        پیش از هر به‌روزرسانی خودکار یک پشتیبان گرفته می‌شود (کد + دیتابیس).
        محتوای آپلودی پشتیبان نمی‌شود — آن کار جداگانه‌ی پشتیبان شبانه است.
      </div>

      <?php if (!$backups): ?>
        <div class="empty-state">پشتیبانی موجود نیست</div>
      <?php else: ?>
        <?php foreach ($backups as $b): ?>
          <div class="table-row" style="display:flex;align-items:center;justify-content:space-between;
               padding:8px 4px;border-bottom:1px solid var(--line-1)">
            <span style="font-family:monospace;font-size:12px"><?= e($b['name']) ?></span>
            <span class="num" style="font-size:12px;color:var(--text-3)">
              <?= e(function_exists('formatBytes') ? formatBytes($b['size']) : (string)$b['size']) ?>
            </span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>

  <!-- توضیح زنجیره -->
  <div class="card" style="margin-top:var(--s5)">
    <h3 style="margin:0 0 var(--s4);font-size:15px;font-weight:600">تلویزیون‌ها چطور به‌روز می‌شوند</h3>
    <div style="font-size:13px;color:var(--text-2);line-height:2">
      <div><strong style="color:var(--brand-400)">گیت‌هاب</strong> — انتشار پایدار</div>
      <div style="color:var(--text-4);padding-right:8px">↓ سرور هتل می‌کشد</div>
      <div><strong style="color:var(--brand-400)">سرور هتل</strong> — همین دستگاه</div>
      <div style="color:var(--text-4);padding-right:8px">↓ تلویزیون‌ها می‌کشند</div>
      <div><strong style="color:var(--brand-400)">تلویزیون‌ها</strong></div>
    </div>
    <div style="font-size:12.5px;color:var(--text-3);line-height:1.95;margin-top:var(--s4)">
      روی <b>LG</b> و <b>سامسونگ</b>، برنامه‌ی تلویزیون همان صفحه‌ای است که همین سرور
      می‌دهد؛ بعد از به‌روزرسانی به همه‌ی صفحه‌ها فرمان بارگذاری دوباره فرستاده می‌شود و
      نسخه‌ی تازه بلافاصله می‌آید.
      روی <b>اندروید</b>، فایل نصبی هم از همان انتشار روی این سرور کپی می‌شود تا
      تلویزیون‌های اتاق — که به اینترنت وصل نیستند — آن را از خود سرور بگیرند.
    </div>
  </div>

</div>

<script>
(function () {
  'use strict';

  var token = document.querySelector('meta[name="csrf-token"]');
  token = token ? token.getAttribute('content') : '';

  var latest = null;

  function el(id) { return document.getElementById(id); }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // ── بررسی نسخه ─────────────────────────────────────────────────
  el('btn-check').addEventListener('click', function () {
    var b = el('btn-check');
    b.disabled = true;
    el('check-result').innerHTML =
      '<div class="alert alert-info"><i class="fas fa-circle-notch fa-spin"></i>' +
      '<span>در حال تماس با گیت‌هاب…</span></div>';

    fetch('/admin/system/update/check', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        b.disabled = false;
        if (!d.ok) {
          el('check-result').innerHTML =
            '<div class="alert alert-error"><i class="fas fa-circle-xmark"></i><span>' +
            esc(d.message) + '</span></div>';
          return;
        }
        if (!d.available) {
          el('check-result').innerHTML =
            '<div class="alert alert-success"><i class="fas fa-circle-check"></i><span>' +
            esc(d.message) + '</span></div>';
          return;
        }

        latest = d;
        var notes = d.notes ? d.notes.slice(0, 1200) : '';
        el('check-result').innerHTML =
          '<div class="alert alert-info" style="align-items:flex-start">' +
            '<i class="fas fa-circle-arrow-down"></i>' +
            '<div style="flex:1">' +
              '<div style="font-weight:700;margin-bottom:6px">' + esc(d.message) + '</div>' +
              (notes
                ? '<pre style="white-space:pre-wrap;font-family:inherit;font-size:12.5px;' +
                  'color:var(--text-2);margin:0 0 10px;max-height:220px;overflow:auto">' +
                  esc(notes) + '</pre>'
                : '') +
              '<button class="btn btn-primary" id="btn-apply">' +
                '<i class="fas fa-download"></i> نصب نسخه ' + esc(d.latest) +
              '</button>' +
            '</div>' +
          '</div>';

        el('btn-apply').addEventListener('click', startUpdate);
      })
      .catch(function () {
        b.disabled = false;
        el('check-result').innerHTML =
          '<div class="alert alert-error"><i class="fas fa-circle-xmark"></i>' +
          '<span>ارتباط با سرور برقرار نشد</span></div>';
      });
  });

  // ── اجرای به‌روزرسانی ──────────────────────────────────────────
  function startUpdate() {
    if (!confirm(
      'نصب نسخه ' + latest.latest + ' شروع شود؟\n\n' +
      'پیش از نصب یک پشتیبان گرفته می‌شود. در حین کار سرویس ممکن است ' +
      'چند لحظه در دسترس نباشد.'
    )) return;

    el('btn-apply').disabled = true;
    el('progress-card').hidden = false;
    el('progress-card').classList.remove('hidden');
    poll();

    fetch('/admin/system/update/apply', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        stopPoll();
        if (d.ok) {
          setBar(100, d.message);
          setTimeout(function () { location.reload(); }, 2500);
        } else {
          el('progress-note').textContent = d.message || 'به‌روزرسانی ناموفق بود';
          el('progress-bar').style.background = 'var(--error)';
        }
      })
      .catch(function () {
        stopPoll();
        /* قطع ارتباط لزوما یعنی شکست نیست — ممکن است سرور وسط
           جایگزینی فایل‌ها باشد. پس به‌جای «ناموفق»، راست می‌گوییم. */
        el('progress-note').textContent =
          'ارتباط قطع شد. اگر سرور در حال نصب بود، چند دقیقه بعد صفحه را تازه کنید.';
      });
  }

  // ── نوار پیشرفت ────────────────────────────────────────────────
  var timer = null;

  function setBar(pct, note) {
    el('progress-bar').style.width = pct + '%';
    el('progress-pct').textContent = toFa(pct) + '٪';
    if (note) el('progress-note').textContent = note;
  }

  function toFa(n) {
    return String(n).replace(/[0-9]/g, function (d) {
      return '۰۱۲۳۴۵۶۷۸۹'.charAt(+d);
    });
  }

  function poll() {
    stopPoll();
    timer = setInterval(function () {
      fetch('/admin/system/update/state', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (s) { setBar(s.percent || 0, s.note || ''); })
        .catch(function () { /* وسط نصب، سرور لحظه‌ای پاسخ نمی‌دهد */ });
    }, 1500);
  }

  function stopPoll() { if (timer) { clearInterval(timer); timer = null; } }

  // ── پشتیبان دستی ───────────────────────────────────────────────
  el('btn-backup').addEventListener('click', function () {
    var b = el('btn-backup');
    b.disabled = true;
    b.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> در حال تهیه…';

    fetch('/admin/system/update/backup', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok) { location.reload(); return; }
        b.disabled = false;
        b.innerHTML = '<i class="fas fa-box-archive"></i> پشتیبان جدید';
        alert(d.message || 'پشتیبان‌گیری ناموفق بود');
      })
      .catch(function () {
        b.disabled = false;
        b.innerHTML = '<i class="fas fa-box-archive"></i> پشتیبان جدید';
      });
  });
})();
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
