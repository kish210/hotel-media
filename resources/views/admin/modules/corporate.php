<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<div class="flex items-center justify-between mb-6">
  <h1 class="text-xl font-bold text-white flex items-center gap-2"><i class="fas fa-building-columns text-indigo-400"></i> اطلاع‌رسانی سازمانی</h1>
  <a href="/admin/modules" class="btn-ghost text-sm px-4"><i class="fas fa-arrow-right text-xs ml-1"></i> ماژول‌ها</a>
</div>

<div class="flex gap-1 bg-white/5 rounded-xl p-1 mb-6 w-fit">
  <?php foreach ([['kpi','داشبورد KPI','chart-line'],['news','اخبار','newspaper'],['dept','دپارتمان‌ها','sitemap']] as [$tab,$label,$icon]): ?>
  <button onclick="showCorpTab('<?=$tab?>')" id="ctab-<?=$tab?>"
    class="corp-tab px-4 py-2 rounded-lg text-sm transition-all <?=$tab==='kpi'?'bg-indigo-500 text-white font-semibold':'text-slate-400 hover:text-white'?>">
    <i class="fas fa-<?=$icon?> ml-1 text-xs"></i><?=$label?>
  </button>
  <?php endforeach; ?>
</div>

<!-- KPI Tab -->
<div id="cpanel-kpi">
  <div class="flex justify-end mb-4">
    <button onclick="document.getElementById('addKpiModal').classList.remove('hidden')" class="btn-primary text-sm flex items-center gap-2"><i class="fas fa-plus text-xs"></i> KPI جدید</button>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    <?php foreach ($kpis as $k): ?>
    <div class="card p-5 border-t-2" style="border-top-color:<?=e($k['color']??'#6366f1')?>;">
      <div class="flex items-start justify-between mb-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:<?=e($k['color']??'#6366f1')?>18;border:1px solid <?=e($k['color']??'#6366f1')?>33;">
          <i class="<?=e($k['icon']??'fas fa-chart-bar')?>" style="color:<?=e($k['color']??'#6366f1')?>"></i>
        </div>
        <button onclick="editKpi(<?=htmlspecialchars(json_encode($k))?>,this)" class="text-slate-600 hover:text-blue-400"><i class="fas fa-pencil text-xs"></i></button>
      </div>
      <div class="text-xs text-slate-500 mb-1"><?=e($k['name'])?></div>
      <div class="text-2xl font-bold text-white"><?=e($k['value'])?> <span class="text-sm font-normal text-slate-500"><?=e($k['unit']??'')?></span></div>
      <?php if ($k['target']): ?><div class="text-xs text-slate-600 mt-1">هدف: <?=e($k['target'])?></div><?php endif; ?>
      <?php if ($k['change_pct'] !== null): ?>
      <div class="text-xs mt-2 <?=$k['change_pct']>=0?'text-green-400':'text-red-400'?>">
        <i class="fas fa-arrow-<?=$k['change_pct']>=0?'up':'down'?> text-xs"></i> <?=abs($k['change_pct'])?>%
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($kpis)): ?><div class="col-span-full text-center py-10 text-slate-600">KPI ثبت نشده</div><?php endif; ?>
  </div>
</div>

<!-- News Tab -->
<div id="cpanel-news" class="hidden">
  <div class="flex justify-end mb-4">
    <button onclick="document.getElementById('addNewsModal').classList.remove('hidden')" class="btn-primary text-sm flex items-center gap-2"><i class="fas fa-plus text-xs"></i> خبر جدید</button>
  </div>
  <div class="space-y-3">
    <?php foreach ($news as $n): ?>
    <div class="card p-4 flex items-start gap-4">
      <?php if ($n['is_pinned']): ?><div class="text-indigo-400 mt-1 flex-shrink-0"><i class="fas fa-thumbtack"></i></div><?php endif; ?>
      <div class="flex-1">
        <div class="flex items-center gap-2 mb-1">
          <p class="font-bold text-white"><?=e($n['title'])?></p>
          <?php if ($n['category']): ?><span class="text-xs bg-white/5 text-slate-400 px-2 py-0.5 rounded-full"><?=e($n['category'])?></span><?php endif; ?>
        </div>
        <?php if ($n['body']): ?><p class="text-sm text-slate-500 line-clamp-2"><?=e($n['body'])?></p><?php endif; ?>
        <p class="text-xs text-slate-600 mt-2"><?=date('Y/m/d H:i',strtotime($n['published_at']))?></p>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($news)): ?><div class="text-center py-10 text-slate-600">خبری ثبت نشده</div><?php endif; ?>
  </div>
</div>

<!-- Departments Tab -->
<div id="cpanel-dept" class="hidden">
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php $colors=['#6366f1','#0ea5e9','#22c55e','#f59e0b','#ec4899','#f97316'];
    foreach ($depts as $i=>$d): ?>
    <div class="card p-4 flex items-center gap-3 border-r-2" style="border-right-color:<?=$colors[$i%count($colors)]?>">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:<?=$colors[$i%count($colors)]?>18;">
        <i class="<?=e($d['icon']??'fas fa-door-open')?>" style="color:<?=$colors[$i%count($colors)]?>"></i>
      </div>
      <div class="flex-1 min-w-0">
        <p class="font-bold text-white text-sm"><?=e($d['name'])?></p>
        <?php if ($d['manager']): ?><p class="text-xs text-slate-500"><?=e($d['manager'])?></p><?php endif; ?>
        <div class="flex gap-3 mt-1 text-xs text-slate-600">
          <?php if ($d['floor']): ?><span><i class="fas fa-stairs ml-1"></i>ط <?=e($d['floor'])?></span><?php endif; ?>
          <?php if ($d['phone']): ?><span class="text-indigo-400 font-mono"><?=e($d['phone'])?></span><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($depts)): ?><div class="col-span-full text-center py-10 text-slate-600">دپارتمانی ثبت نشده</div><?php endif; ?>
  </div>
</div>

<!-- Modals -->
<div id="addKpiModal" class="modal-overlay hidden">
  <div class="modal max-w-md">
    <div class="flex items-center justify-between mb-4"><h3 class="font-bold text-white" id="kpiModalTitle">KPI جدید</h3><button onclick="document.getElementById('addKpiModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fas fa-xmark"></i></button></div>
    <form id="kpiForm" method="POST" action="/admin/modules/corporate/kpi" class="space-y-3">
      <?= csrf_field() ?>
      <div><label class="form-label">نام KPI *</label><input type="text" name="name" id="kName" class="form-input" required></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">مقدار *</label><input type="text" name="value" id="kVal" class="form-input" required placeholder="۱۲۵,۰۰۰"></div>
        <div><label class="form-label">هدف</label><input type="text" name="target" id="kTarget" class="form-input"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">واحد</label><input type="text" name="unit" id="kUnit" class="form-input" placeholder="تومان / نفر / %"></div>
        <div><label class="form-label">تغییر (%)</label><input type="number" name="change_pct" id="kChange" class="form-input" step="0.1" placeholder="+8.3"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">آیکون FA</label><input type="text" name="icon" id="kIcon" class="form-input" value="fas fa-chart-line"></div>
        <div><label class="form-label">رنگ</label><input type="color" name="color" id="kColor" class="form-input h-10" value="#6366f1"></div>
      </div>
      <div class="flex gap-3 pt-2"><button type="submit" class="btn-primary flex-1" id="kpiSubmitBtn">ثبت KPI</button><button type="button" onclick="document.getElementById('addKpiModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button></div>
    </form>
  </div>
</div>

<div id="addNewsModal" class="modal-overlay hidden">
  <div class="modal max-w-lg">
    <div class="flex items-center justify-between mb-4"><h3 class="font-bold text-white">خبر / اطلاعیه جدید</h3><button onclick="document.getElementById('addNewsModal').classList.add('hidden')" class="text-slate-500 hover:text-white"><i class="fas fa-xmark"></i></button></div>
    <form method="POST" action="/admin/modules/corporate/news" class="space-y-3">
      <?= csrf_field() ?>
      <div><label class="form-label">عنوان *</label><input type="text" name="title" class="form-input" required></div>
      <div><label class="form-label">متن</label><textarea name="body" class="form-input" rows="3"></textarea></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">دسته</label><input type="text" name="category" class="form-input" placeholder="اطلاعیه / مهم / ..."></div>
        <div><label class="form-label">اولویت (1-10)</label><input type="number" name="priority" class="form-input" value="5" min="1" max="10"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="form-label">انقضا</label><input type="datetime-local" name="expires_at" class="form-input"></div>
        <div class="flex items-center pt-5"><label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_pinned" value="1" class="accent-indigo-500 w-4 h-4"><span class="text-sm text-slate-400">📌 سنجاق‌شده</span></label></div>
      </div>
      <div class="flex gap-3 pt-2"><button type="submit" class="btn-primary flex-1">ثبت خبر</button><button type="button" onclick="document.getElementById('addNewsModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button></div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
function showCorpTab(tab) {
  ['kpi','news','dept'].forEach(t => {
    document.getElementById('cpanel-'+t).classList.toggle('hidden', t!==tab);
    const btn = document.getElementById('ctab-'+t);
    if (btn) btn.className = `corp-tab px-4 py-2 rounded-lg text-sm transition-all ${t===tab?'bg-indigo-500 text-white font-semibold':'text-slate-400 hover:text-white'}`;
  });
}
function editKpi(k) {
  document.getElementById('kpiModalTitle').textContent = 'ویرایش KPI';
  document.getElementById('kpiForm').action = '/admin/modules/corporate/kpi/' + k.id;
  document.getElementById('kpiSubmitBtn').textContent = 'ذخیره';
  document.getElementById('kName').value   = k.name || '';
  document.getElementById('kVal').value    = k.value || '';
  document.getElementById('kTarget').value = k.target || '';
  document.getElementById('kUnit').value   = k.unit || '';
  document.getElementById('kChange').value = k.change_pct || '';
  document.getElementById('kIcon').value   = k.icon || 'fas fa-chart-line';
  document.getElementById('kColor').value  = k.color || '#6366f1';
  document.getElementById('addKpiModal').classList.remove('hidden');
}
JS;
?>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
