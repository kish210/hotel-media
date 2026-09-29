<?php
/**
 * عیب‌یابی سرور — پنل مدیر ارشد.
 *
 * هدف صفحه: کسی که پای دستگاه است و SSH ندارد بتواند در یک نگاه
 * بفهمد کدام قطعه خراب است، و فرمانِ درستش را کپی کند.
 *
 * @var array $sections خروجی DiagnosticsService::all()
 */
include VIEWS_PATH . '/partials/layout.php';

/** رنگ و آیکن هر وضعیت — یک جا تعریف تا سرفصل و ردیف یکی باشند */
$style = [
    'ok'      => ['#22c55e', 'fa-circle-check',        'سالم'],
    'warn'    => ['#f59e0b', 'fa-triangle-exclamation','هشدار'],
    'fail'    => ['#ef4444', 'fa-circle-xmark',        'خراب'],
    'unknown' => ['#64748b', 'fa-circle-question',     'نامعلوم'],
];

/* شمارش کلی برای نوار بالا — اپراتور اول این را می‌بیند */
$tally = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'unknown' => 0];
foreach ($sections as $sec) {
    foreach ($sec['checks'] as $c) {
        $tally[$c['status']] = ($tally[$c['status']] ?? 0) + 1;
    }
}
?>

<div class="content">

  <div class="card" style="margin-bottom:var(--s5)">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--s4)">
      <div>
        <h2 style="margin:0;font-size:18px;font-weight:600">وضعیت سرور</h2>
        <div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:10px">
          <?php foreach (['fail','warn','unknown','ok'] as $k): ?>
            <?php if (!$tally[$k]) continue; ?>
            <span style="display:inline-flex;align-items:center;gap:6px;font-size:13.5px">
              <i class="fas <?= $style[$k][1] ?>" style="color:<?= $style[$k][0] ?>"></i>
              <span style="font-weight:700"><?= $tally[$k] ?></span>
              <span style="color:var(--text-3)"><?= $style[$k][2] ?></span>
            </span>
          <?php endforeach; ?>
        </div>
        <div style="font-size:12.5px;color:var(--text-3);margin-top:8px">
          این صفحه فقط می‌خواند و هیچ چیزی را تغییر نمی‌دهد. برای رفع هر مورد،
          فرمانِ نشان‌داده‌شده را روی سرور اجرا کنید.
        </div>
      </div>
      <button class="btn btn-primary" id="btn-recheck">
        <i class="fas fa-rotate"></i> بررسی دوباره
      </button>
    </div>
  </div>

  <div id="dg-body">
    <?php foreach ($sections as $key => $sec): ?>
      <?php [$col, $ico, $lbl] = $style[$sec['status']] ?? $style['unknown']; ?>
      <div class="card" style="margin-bottom:var(--s4)">
        <h3 style="margin:0 0 14px;font-size:15.5px;font-weight:600;display:flex;align-items:center;gap:8px">
          <i class="fas <?= $ico ?>" style="color:<?= $col ?>"></i>
          <?= e($sec['title']) ?>
        </h3>

        <div style="display:flex;flex-direction:column;gap:2px">
          <?php foreach ($sec['checks'] as $c): ?>
            <?php [$ccol, $cico] = $style[$c['status']] ?? $style['unknown']; ?>
            <div style="display:flex;align-items:flex-start;gap:10px;padding:9px 0;
                        border-bottom:1px solid rgba(255,255,255,.05)">
              <i class="fas <?= $cico ?>" style="color:<?= $ccol ?>;margin-top:3px;flex:none"></i>
              <div style="flex:1;min-width:0">
                <div style="font-size:13.5px;font-weight:500"><?= e($c['name']) ?></div>
                <?php if ($c['detail'] !== ''): ?>
                  <div style="font-size:12.5px;color:var(--text-3);margin-top:2px;
                              word-break:break-word"><?= e($c['detail']) ?></div>
                <?php endif; ?>
                <?php if (($c['fix'] ?? '') !== ''): ?>
                  <?php /* فرمان جدا و قابل انتخاب — کسی که پای SSH است
                           باید بتواند مستقیم کپی کند، نه از متن درش بیاورد */ ?>
                  <code style="display:inline-block;margin-top:6px;padding:4px 9px;
                               background:rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.08);
                               border-radius:7px;font-size:12px;direction:ltr;text-align:left;
                               user-select:all;color:#cbd5e1"><?= e($c['fix']) ?></code>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

</div>

<script>
(function () {
  var btn = document.getElementById('btn-recheck');
  if (!btn) return;

  btn.addEventListener('click', function () {
    var old = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-rotate fa-spin"></i> در حال بررسی…';

    /* بارگذاری دوباره‌ی کل صفحه ساده‌ترین راه است و همان HTML سرور را
       می‌آورد — نگه‌داشتن دو مسیرِ رندر (PHP و JS) برای یک جدول،
       جایی است که خروجی‌ها با هم فرق می‌کنند و کسی متوجه نمی‌شود. */
    fetch('/admin/system/diagnostics/json', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function () { window.location.reload(); })
      .catch(function () {
        btn.disabled = false;
        btn.innerHTML = old;
        alert('بررسی انجام نشد — اتصال به سرور برقرار نشد');
      });
  });
})();
</script>
