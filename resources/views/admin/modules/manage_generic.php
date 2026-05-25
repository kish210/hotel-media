<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<div class="flex items-center gap-3 mb-6">
  <a href="/admin/modules" class="btn-ghost text-sm px-3"><i class="fas fa-arrow-right text-xs"></i></a>
  <h1 class="text-xl font-bold text-white"><?= e($title ?? 'مدیریت ماژول') ?></h1>
</div>
<div class="card text-center py-12">
  <i class="fas fa-puzzle-piece text-5xl text-slate-700 mb-4 block"></i>
  <p class="text-slate-400">صفحه مدیریت این ماژول در حال توسعه است.</p>
</div>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
