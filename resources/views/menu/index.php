<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<div class="flex items-center justify-between mb-5">
  <h1 class="text-xl font-bold text-white flex items-center gap-2">
    <i class="fas fa-utensils text-orange-400"></i> منوی رستوران
  </h1>
  <a href="/admin/modules" class="btn-ghost text-sm px-4">
    <i class="fas fa-puzzle-piece text-xs ml-1"></i> ماژول‌ها
  </a>
</div>
<div class="card p-6 text-center text-slate-500">
  <i class="fas fa-utensils text-5xl mb-4 block opacity-20 text-orange-400/30"></i>
  <p class="mb-4">مدیریت منو از طریق ماژول Menu انجام می‌شود</p>
  <a href="/admin/modules" class="btn-primary inline-flex items-center gap-2 text-sm">
    <i class="fas fa-puzzle-piece text-xs"></i> رفتن به ماژول‌ها
  </a>
</div>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
