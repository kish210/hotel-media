<?php
use App\Core\{Auth, Database};
$db  = Database::getInstance();
$tid = Auth::tenantId();
include VIEWS_PATH . '/partials/layout.php';
?>
<div class="flex items-center gap-3 mb-6">
  <h1 class="text-xl font-bold text-white flex items-center gap-2">
    مدیریت ماژول <?= e($module->name()) ?>
  </h1>
</div>
<div class="card">
  <p class="text-slate-400 text-sm text-center py-8">
    <i class="fas fa-puzzle-piece text-4xl block mb-3 opacity-30"></i>
    صفحه مدیریت ماژول در حال توسعه است.<br>
    از API endpoint های زیر استفاده کنید:
  </p>
  <div style="background:#0d0d14;border-radius:10px;padding:16px;font-family:monospace;font-size:12px;color:#60a5fa;margin-top:12px;">
    GET /api/v1/corporate/...<br>
    POST /api/v1/corporate/...
  </div>
</div>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
