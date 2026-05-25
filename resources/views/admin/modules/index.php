<?php
include VIEWS_PATH . '/partials/layout.php';
use App\Modules\Core\ModuleRegistry;
ModuleRegistry::boot(\App\Core\Auth::tenantId());
$allModules = ModuleRegistry::all();
$categories = [
  'transport'   => ['label'=>'حمل‌ونقل',     'icon'=>'fas fa-plane',            'color'=>'sky'],
  'hospitality' => ['label'=>'مهمان‌نوازی',   'icon'=>'fas fa-hotel',            'color'=>'yellow'],
  'retail'      => ['label'=>'فروشگاه',       'icon'=>'fas fa-store',            'color'=>'pink'],
  'corporate'   => ['label'=>'سازمانی',       'icon'=>'fas fa-building-columns', 'color'=>'indigo'],
  'info'        => ['label'=>'اطلاع‌رسانی',   'icon'=>'fas fa-circle-info',      'color'=>'green'],
  'media'       => ['label'=>'رسانه / IPTV',  'icon'=>'fas fa-satellite-dish',   'color'=>'red'],
];
?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-bold text-white flex items-center gap-2">
      <i class="fas fa-puzzle-piece text-orange-400"></i> مدیریت ماژول‌های محتوا
    </h1>
    <p class="text-sm text-slate-500 mt-1">نصب، فعال‌سازی و مدیریت ماژول‌های نمایش محتوا</p>
  </div>
  <div class="flex items-center gap-2 text-sm text-slate-500 bg-white/5 border border-white/8 rounded-xl px-4 py-2">
    <i class="fas fa-check-circle text-green-400"></i>
    <span><?= count(array_filter($allModules, fn($m) => $m->isInstalled())) ?> از <?= count($allModules) ?> ماژول نصب‌شده</span>
  </div>
</div>

<!-- Category filter tabs -->
<div class="flex gap-2 flex-wrap mb-6">
  <button class="cat-filter-btn px-4 py-2 rounded-xl text-sm font-medium bg-orange-500 text-white" data-cat="all" onclick="filterCat('all')">همه</button>
  <?php foreach ($categories as $key => $cat): ?>
  <button class="cat-filter-btn px-4 py-2 rounded-xl text-sm font-medium bg-white/5 text-slate-400 hover:text-white transition-all" data-cat="<?= $key ?>" onclick="filterCat('<?= $key ?>')">
    <i class="<?= $cat['icon'] ?> ml-1 text-<?= $cat['color'] ?>-400 text-xs"></i><?= $cat['label'] ?>
  </button>
  <?php endforeach; ?>
</div>

<!-- Module cards -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5" id="moduleGrid">
  <?php foreach ($allModules as $mod):
    $installed = $mod->isInstalled();
    $stats     = $installed ? $mod->getDashboardStats() : [];
  ?>
  <div class="module-card card p-5 flex flex-col gap-4 hover:border-white/15 transition-all"
    data-cat="<?= $mod->category() ?>" data-id="<?= $mod->id() ?>"
    style="border:1px solid rgba(255,255,255,0.06);transition:opacity .3s;">

    <!-- Header -->
    <div class="flex items-start justify-between">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 border"
          style="background:<?= $mod->color() ?>18;border-color:<?= $mod->color() ?>33;">
          <i class="<?= $mod->icon() ?>" style="color:<?= $mod->color() ?>;font-size:20px;"></i>
        </div>
        <div>
          <h3 class="font-bold text-white text-sm leading-tight"><?= e($mod->name()) ?></h3>
          <p class="text-xs text-slate-600 mt-0.5"><?= e($mod->nameEn()) ?> · v<?= $mod->version() ?></p>
        </div>
      </div>
      <div class="flex flex-col items-end gap-1.5">
        <?php if ($installed): ?>
        <span class="badge-online px-2 py-1 rounded-full text-xs font-medium flex items-center gap-1">
          <span class="online-dot w-1.5 h-1.5"></span> نصب‌شده
        </span>
        <?php else: ?>
        <span class="badge-offline px-2 py-1 rounded-full text-xs font-medium">نصب نشده</span>
        <?php endif; ?>
        <?php
        $catInfo = $categories[$mod->category()] ?? null;
        if ($catInfo): ?>
        <span class="text-xs px-2 py-0.5 rounded-full bg-white/5 text-slate-500"><?= $catInfo['label'] ?></span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Description -->
    <p class="text-xs text-slate-500 leading-relaxed"><?= e($mod->description()) ?></p>

    <!-- Zone types -->
    <div>
      <p class="text-xs text-slate-600 font-semibold uppercase tracking-wider mb-2">زون‌های قابل نمایش</p>
      <div class="flex flex-wrap gap-1.5">
        <?php foreach ($mod->zoneTypes() as $zt): ?>
        <span class="text-xs px-2 py-1 rounded-lg flex items-center gap-1.5"
          style="background:<?= $mod->color() ?>12;color:<?= $mod->color() ?>;border:1px solid <?= $mod->color() ?>25;">
          <i class="<?= $zt['icon'] ?> text-xs"></i> <?= e($zt['label']) ?>
        </span>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Stats (if installed) -->
    <?php if ($installed && !empty($stats)): ?>
    <div class="grid grid-cols-<?= min(4, count($stats)) ?> gap-2 pt-2 border-t border-white/5">
      <?php foreach ($stats as $key => $val): ?>
      <div class="text-center bg-white/3 rounded-xl py-2">
        <div class="text-lg font-bold text-white"><?= $val ?></div>
        <div class="text-xs text-slate-600 mt-0.5"><?= str_replace(['_today','_items','s','_'],['امروز','','‌ها',' '], $key) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <?php
    $directRoutes = ['iptv'=>'/admin/iptv','inflight'=>'/admin/inflight','vod'=>'/admin/vod'];
    $manageUrl    = $directRoutes[$mod->id()] ?? ('/admin/modules/' . $mod->id() . '/manage');
    $manageLabel  = isset($directRoutes[$mod->id()]) ? 'رفتن به پنل' : 'مدیریت';
    $manageIcon   = isset($directRoutes[$mod->id()]) ? 'fas fa-external-link-alt' : 'fas fa-gear';
    ?>
    <div style="border-top:1px solid rgba(255,255,255,0.05);padding-top:12px;display:flex;flex-direction:column;gap:8px;">

      <!-- Toggle enable/disable row -->
      <div style="display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:8px 12px;">
        <div style="display:flex;align-items:center;gap:8px;">
          <?php if ($installed): ?>
            <span style="width:8px;height:8px;border-radius:50%;background:#4ade80;display:inline-block;animation:pulse 2s infinite;"></span>
            <span style="font-size:12px;font-weight:600;color:#4ade80;">فعال</span>
          <?php else: ?>
            <span style="width:8px;height:8px;border-radius:50%;background:#475569;display:inline-block;"></span>
            <span style="font-size:12px;font-weight:600;color:#475569;">غیرفعال</span>
          <?php endif; ?>
        </div>
        <!-- Toggle switch -->
        <label class="mod-toggle" style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer;" title="<?= $installed ? 'غیرفعال کردن ماژول' : 'فعال کردن ماژول' ?>">
          <input type="checkbox" <?= $installed ? 'checked' : '' ?>
            onchange="toggleModule('<?= $mod->id() ?>', this.checked)"
            style="opacity:0;width:0;height:0;position:absolute;">
          <span style="
            position:absolute;inset:0;border-radius:24px;transition:background .3s;
            background:<?= $installed ? '#f97316' : '#1e293b' ?>;
            border:1px solid <?= $installed ? 'rgba(249,115,22,.5)' : 'rgba(255,255,255,.1)' ?>;
          "></span>
          <span style="
            position:absolute;top:3px;<?= $installed ? 'right:3px' : 'left:3px' ?>;
            width:16px;height:16px;border-radius:50%;background:#fff;
            transition:all .3s;box-shadow:0 1px 3px rgba(0,0,0,.4);
          "></span>
        </label>
      </div>

      <!-- Manage + Preview buttons (only if installed) -->
      <?php if ($installed): ?>
      <div style="display:flex;gap:8px;">
        <a href="<?= $manageUrl ?>" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:8px 12px;background:linear-gradient(135deg,#f97316,#c2570b);color:#fff;border-radius:10px;font-size:12px;font-weight:600;text-decoration:none;border:none;cursor:pointer;">
          <i class="<?= $manageIcon ?> text-xs"></i> <?= $manageLabel ?>
        </a>
        <button onclick="openZoneDemo('<?= $mod->id() ?>')"
          style="padding:8px 12px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:10px;color:#4ade80;cursor:pointer;font-size:13px;"
          title="پیش‌نمایش زون‌ها">
          <i class="fas fa-eye"></i>
        </button>
      </div>
      <?php else: ?>
      <button onclick="installModule('<?= $mod->id() ?>')"
        id="install-<?= $mod->id() ?>"
        style="width:100%;padding:9px;background:rgba(249,115,22,.08);border:1px dashed rgba(249,115,22,.3);border-radius:10px;color:#f97316;font-size:12px;font-weight:600;cursor:pointer;font-family:Vazirmatn,sans-serif;display:flex;align-items:center;justify-content:center;gap:6px;">
        <i class="fas fa-download text-xs"></i> نصب و فعال‌سازی
      </button>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Zone Demo Modal -->
<div id="zoneDemoModal" class="modal-overlay hidden">
  <div class="modal max-w-4xl p-0 overflow-hidden" style="max-height:90vh;">
    <div class="flex items-center justify-between px-5 py-3 border-b border-white/5">
      <h3 class="font-bold text-white" id="demoTitle">پیش‌نمایش زون</h3>
      <button onclick="document.getElementById('zoneDemoModal').classList.add('hidden')" class="text-slate-500 hover:text-white">
        <i class="fas fa-xmark text-lg"></i>
      </button>
    </div>
    <div class="flex gap-3 px-5 py-3 border-b border-white/5 overflow-x-auto" id="demoZoneTabs"></div>
    <div style="height:480px;background:#000;position:relative;" id="demoFrame">
      <div class="text-center py-16 text-slate-600">زونی انتخاب نشده</div>
    </div>
    <div class="px-5 py-3 border-t border-white/5 flex justify-between items-center">
      <p class="text-xs text-slate-500"><i class="fas fa-info-circle ml-1"></i>این پیش‌نمایش شبیه‌سازی‌شده است</p>
      <button onclick="document.getElementById('zoneDemoModal').classList.add('hidden')" class="btn-ghost text-sm px-5">بستن</button>
    </div>
  </div>
</div>

<?php
$extraScript = <<<'JS'
function filterCat(cat) {
  document.querySelectorAll('.cat-filter-btn').forEach(b => {
    b.className = `cat-filter-btn px-4 py-2 rounded-xl text-sm font-medium transition-all ${b.dataset.cat === cat ? 'bg-orange-500 text-white' : 'bg-white/5 text-slate-400 hover:text-white'}`;
  });
  document.querySelectorAll('.module-card').forEach(c => {
    c.style.display = (cat === 'all' || c.dataset.cat === cat) ? '' : 'none';
  });
}

async function installModule(id) {
  const btn = document.getElementById('install-' + id);
  btn.innerHTML = '<i class="fas fa-circle-notch fa-spin text-xs"></i> در حال نصب...';
  btn.disabled = true;
  try {
    const r = await fetch('/api/v1/modules/' + id + '/install', {
      method: 'POST',
      headers: { 'Authorization': 'Bearer ' + (localStorage.getItem('signage_token') || '') }
    });
    const d = await r.json();
    if (d.success) {
      showToast('success', 'ماژول نصب شد');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast('error', d.message);
      btn.innerHTML = '<i class="fas fa-download text-xs"></i> نصب ماژول';
      btn.disabled = false;
    }
  } catch(e) {
    showToast('error', 'خطا در نصب ماژول');
    btn.innerHTML = '<i class="fas fa-download text-xs"></i> نصب ماژول';
    btn.disabled = false;
  }
}

async function toggleModule(id, enable) {
  // پیدا کردن کارت ماژول برای نمایش وضعیت لودینگ
  const card = document.querySelector(`.module-card[data-id="${id}"]`);
  if (card) card.style.opacity = '0.5';

  try {
    const r = await fetch('/api/v1/modules/' + id + '/toggle', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + (localStorage.getItem('signage_token') || '')
      },
      body: JSON.stringify({ enable })
    });
    const d = await r.json();
    if (d.success) {
      showToast('success', enable ? 'ماژول فعال شد ✓' : 'ماژول غیرفعال شد');
      setTimeout(() => location.reload(), 900);
    } else {
      showToast('error', d.message || 'خطا در تغییر وضعیت');
      if (card) card.style.opacity = '1';
      // برگرداندن وضعیت toggle
      const chk = card ? card.querySelector('input[type=checkbox]') : null;
      if (chk) chk.checked = !enable;
    }
  } catch(e) {
    showToast('error', 'خطا در اتصال به سرور');
    if (card) card.style.opacity = '1';
  }
}

// Zone type demo modal
const moduleZones = {
  fids:      [['fids_departures','پروازهای عزیمت'],['fids_arrivals','پروازهای ورود'],['fids_gate','دروازه'],['fids_splitflap','Split-Flap']],
  hotel:     [['hotel_welcome','خوش‌آمدگویی'],['hotel_events','رویدادها'],['hotel_amenities','امکانات'],['hotel_checkin','ورود/خروج'],['hotel_directory','راهنما']],
  menu:      [['menu_full','منوی کامل'],['menu_featured','آیتم ویژه'],['menu_daily','منوی روز'],['menu_ticker','تیکر']],
  transport: [['transport_bus','اتوبوس'],['transport_metro','مترو'],['transport_taxi','تاکسی']],
  retail:    [['retail_priceboard','تابلوی قیمت'],['retail_offers','آفرها'],['retail_featured','محصول ویژه'],['retail_currency','نرخ ارز'],['retail_queue','صف نوبت']],
  corporate: [['corp_lobby','لابی'],['corp_kpi','KPI'],['corp_news','اخبار'],['corp_directory','راهنمای ساختمان'],['corp_countdown','شمارش معکوس']],
  iptv:      [['iptv_player','پخش زنده IPTV'],['iptv_room_menu','منوی اتاق هتل']],
  inflight:  [['inflight_map','نقشه مسیر پرواز']],
  vod:       [['vod_player','پخش VOD']],
};

function openZoneDemo(moduleId) {
  const zones = moduleZones[moduleId] || [];
  document.getElementById('demoTitle').textContent = 'پیش‌نمایش — ' + moduleId;
  const tabs = document.getElementById('demoZoneTabs');
  tabs.innerHTML = zones.map(([zid, zlabel]) =>
    `<button onclick="loadZoneDemo('${moduleId}','${zid}')" class="demo-tab px-3 py-1.5 rounded-lg text-xs transition-all bg-white/5 text-slate-400 hover:text-white whitespace-nowrap" data-zone="${zid}">${zlabel}</button>`
  ).join('');
  document.getElementById('demoFrame').innerHTML = '<div class="text-center py-16 text-slate-600 text-sm">یک زون انتخاب کنید</div>';
  document.getElementById('zoneDemoModal').classList.remove('hidden');
  if (zones.length) loadZoneDemo(moduleId, zones[0][0]);
}

async function loadZoneDemo(moduleId, zoneType) {
  document.querySelectorAll('.demo-tab').forEach(t => {
    t.className = `demo-tab px-3 py-1.5 rounded-lg text-xs transition-all whitespace-nowrap ${t.dataset.zone === zoneType ? 'bg-orange-500 text-white' : 'bg-white/5 text-slate-400 hover:text-white'}`;
  });
  const frame = document.getElementById('demoFrame');
  frame.innerHTML = '<div class="flex items-center justify-center h-full text-slate-500"><i class="fas fa-circle-notch fa-spin text-2xl"></i></div>';
  try {
    const r = await fetch(`/api/v1/modules/${moduleId}/preview?zone=${zoneType}`, {
      headers: { 'Authorization': 'Bearer ' + (localStorage.getItem('signage_token') || '') }
    });
    const d = await r.json();
    if (d.success && d.data?.html) {
      frame.innerHTML = d.data.html;
      // Load FA icons and scripts
      if (!document.querySelector('link[href*="font-awesome"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css';
        document.head.appendChild(link);
      }
    } else {
      frame.innerHTML = `<div class="text-center py-16 text-slate-500 text-sm">${d.message || 'پیش‌نمایش در دسترس نیست'}</div>`;
    }
  } catch(e) {
    frame.innerHTML = '<div class="text-center py-16 text-red-400 text-sm"><i class="fas fa-exclamation-triangle block mb-2 text-2xl"></i>خطا در بارگذاری پیش‌نمایش</div>';
  }
}
JS;
?>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
