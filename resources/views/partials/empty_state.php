<?php
/**
 * حالت خالی مشترک.
 *
 * چرا یکی: هر صفحه نسخهٔ خودش را داشت — یکی داخل کارت، یکی بدون کارت،
 * یکی فقط یک جمله بی‌دکمه. نتیجه این بود که اپراتورِ تازه در بعضی
 * صفحه‌ها نمی‌فهمید قدم بعدی چیست.
 *
 * استفاده:
 *   <?php $empty = ['icon' => 'fa-list', 'title' => '…', 'text' => '…',
 *                   'cta' => ['href' => '/admin/…', 'label' => '…'],
 *                   'help' => '/admin/help#…'];
 *         include VIEWS_PATH . '/partials/empty_state.php'; ?>
 *
 * فقط `title` لازم است؛ بقیه اختیاری‌اند.
 */
$e = $empty ?? [];
?>
<div style="background:rgba(255,255,255,.02);border:1px dashed rgba(255,255,255,.12);
            border-radius:16px;padding:46px 22px;text-align:center;">
  <i class="fas <?= e($e['icon'] ?? 'fa-inbox') ?>"
     style="font-size:40px;color:#334155;display:block;margin-bottom:14px;"></i>
  <div style="font-size:15px;font-weight:700;color:#cbd5e1;margin-bottom:6px;">
    <?= e($e['title'] ?? '') ?>
  </div>
  <?php if (!empty($e['text'])): ?>
    <div style="font-size:12.5px;color:#8593a8;line-height:2;max-width:460px;margin:0 auto 18px;">
      <?= e($e['text']) ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($e['cta']['href'])): ?>
    <a href="<?= e($e['cta']['href']) ?>" class="btn-primary text-sm">
      <i class="fas fa-plus ml-1"></i><?= e($e['cta']['label'] ?? 'شروع') ?>
    </a>
  <?php endif; ?>
  <?php if (!empty($e['help'])): ?>
    <a href="<?= e($e['help']) ?>" target="_blank"
       style="display:inline-block;margin-right:10px;font-size:12px;color:#60a5fa;text-decoration:none;">راهنما</a>
  <?php endif; ?>
</div>
<?php unset($empty); ?>
