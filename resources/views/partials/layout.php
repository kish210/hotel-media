<?php
use App\Core\{Auth, Lang};
use App\Modules\Core\ModuleRegistry;

if (!Auth::check() && !str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/player')) {
    App\Core\Response::redirect('/login');
}

$authUser    = Auth::user() ?? [];
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$flash       = $_SESSION['_flash'] ?? [];
unset($_SESSION['_flash']);

// ── زبان ──────────────────────────────────────────────────────────────────────
$_uiLang   = Lang::current();
$_uiDir    = Lang::dir();
$_uiFont   = Lang::font();
$_langList = Lang::all();

// ── بارگذاری ماژول‌های فعال ─────────────────────────────────────────────────
$GLOBALS['_activeModules'] = [];
try {
    ModuleRegistry::ensureTable();
    ModuleRegistry::boot(Auth::tenantId());
    $GLOBALS['_activeModules'] = ModuleRegistry::activeIds();
} catch (\Throwable $e) {}

if (!function_exists('isActive')) {
    function isActive(string $path): string {
        global $currentPath;
        return str_starts_with($currentPath, $path) ? 'active' : '';
    }
}
if (!function_exists('modOn')) {
    function modOn(string $id): bool {
        return in_array($id, $GLOBALS['_activeModules'] ?? [], true);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_uiLang ?>" dir="<?= $_uiDir ?>" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<title><?= e($title ?? 'SignageCMS') ?> — SignageCMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&family=Tajawal:wght@300;400;500;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root { --ui-font: '<?= $_uiFont ?>', sans-serif; }
body { font-family: var(--ui-font) !important; }
.sidebar-nav, .card, .btn-primary, .btn-ghost, .form-input, .form-label, button, select, input, textarea { font-family: var(--ui-font) !important; }
</style>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    darkMode: 'class',
    theme: {
      extend: {
        fontFamily: { sans: ['Vazirmatn', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] },
        colors: {
          brand:   { 500:'#f97316', 600:'#ea6f10', 700:'#c2570b' },
          surface: { 900:'#0a0a0f', 800:'#111118', 750:'#16161f', 700:'#1c1c28' },
        }
      }
    }
  }
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Vazirmatn', sans-serif; background: #0a0a0f; color: #e2e8f0; direction: <?= $_uiDir ?>; }
  ::-webkit-scrollbar { width: 6px; }
  ::-webkit-scrollbar-track { background: #111118; }
  ::-webkit-scrollbar-thumb { background: #2d2d40; border-radius: 3px; }
  ::-webkit-scrollbar-thumb:hover { background: #f97316; }

  /* LTR/RTL layout switch */
  <?php if ($_uiDir === 'ltr'): ?>
  .sidebar { width:240px; background:#111118; border-right:1px solid rgba(255,255,255,0.06); height:100vh; position:fixed; left:0; top:0; overflow-y:auto; z-index:40; }
  .main { margin-left:240px; min-height:100vh; display:flex; flex-direction:column; }
  <?php else: ?>
  .sidebar { width:240px; background:#111118; border-left:1px solid rgba(255,255,255,0.06); height:100vh; position:fixed; right:0; top:0; overflow-y:auto; z-index:40; }
  .main { margin-right:240px; min-height:100vh; display:flex; flex-direction:column; }
  <?php endif; ?>
  .topbar { background:#111118; border-bottom:1px solid rgba(255,255,255,0.06); padding:12px 24px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:30; }

  .sidebar-link { display:flex; align-items:center; gap:10px; padding:9px 16px; color:#94a3b8; font-size:13.5px; font-weight:500; border-radius:10px; margin:2px 8px; text-decoration:none; transition:all 0.2s; }
  .sidebar-link:hover, .sidebar-link.active { background:rgba(249,115,22,0.12); color:#f97316; }
  .sidebar-link .icon { width:20px; text-align:center; font-size:14px; }
  .sidebar-section { font-size:10px; font-weight:700; color:#475569; letter-spacing:0.8px; text-transform:uppercase; padding:12px 24px 4px; }

  /* badge تعداد ماژول فعال */
  .mod-badge { margin-right:auto; background:rgba(249,115,22,0.15); color:#f97316; font-size:10px; font-weight:700; padding:1px 7px; border-radius:20px; border:1px solid rgba(249,115,22,0.25); }

  .card { background:#16161f; border:1px solid rgba(255,255,255,0.07); border-radius:16px; padding:20px; }
  .stat-card { background:#16161f; border:1px solid rgba(255,255,255,0.07); border-radius:16px; padding:18px; }
  .btn-primary { background:linear-gradient(135deg,#f97316,#c2570b); color:#fff; padding:8px 18px; border-radius:10px; font-size:13.5px; font-weight:600; border:none; cursor:pointer; transition:opacity 0.2s; text-decoration:none; display:inline-flex; align-items:center; }
  .btn-primary:hover { opacity:0.9; }
  .btn-ghost { background:rgba(255,255,255,0.05); color:#94a3b8; padding:7px 14px; border-radius:10px; font-size:13px; border:1px solid rgba(255,255,255,0.08); cursor:pointer; transition:all 0.2s; text-decoration:none; display:inline-flex; align-items:center; }
  .btn-ghost:hover { background:rgba(255,255,255,0.1); color:#fff; }
  .btn-danger { background:rgba(239,68,68,0.1); color:#f87171; padding:7px 14px; border-radius:10px; font-size:13px; border:1px solid rgba(239,68,68,0.3); cursor:pointer; transition:all 0.2s; }
  .btn-danger:hover { background:rgba(239,68,68,0.2); }

  .form-label { display:block; font-size:12px; font-weight:600; color:#94a3b8; margin-bottom:6px; }
  .form-input { width:100%; background:#0d0d14; border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:9px 14px; font-size:14px; color:#fff; outline:none; transition:border-color 0.2s; font-family:'Vazirmatn',sans-serif; }
  .form-input:focus { border-color:#f97316; }
  .form-input::placeholder { color:#475569; }
  select.form-input option { background:#16161f; }

  .badge-online  { background:rgba(34,197,94,0.12);  color:#4ade80; border:1px solid rgba(34,197,94,0.3);  padding:2px 8px; border-radius:20px; font-size:11px; }
  .badge-offline { background:rgba(239,68,68,0.12);  color:#f87171; border:1px solid rgba(239,68,68,0.3);  padding:2px 8px; border-radius:20px; font-size:11px; }
  .badge-pending { background:rgba(245,158,11,0.12); color:#fbbf24; border:1px solid rgba(245,158,11,0.3); padding:2px 8px; border-radius:20px; font-size:11px; }

  .online-dot { width:8px; height:8px; border-radius:50%; background:#4ade80; display:inline-block; animation:pulse 2s infinite; }

  .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:100; display:flex; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(4px); }
  .modal-overlay.hidden { display:none !important; }
  .hidden { display:none !important; }
  .modal { background:#16161f; border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:28px; width:100%; max-width:560px; max-height:90vh; overflow-y:auto; }

  .toast { position:fixed; bottom:24px; right:24px; background:#16161f; border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:12px 18px; z-index:9999; display:flex; align-items:center; gap:10px; font-size:13px; animation:slideUp 0.3s ease; max-width:360px; }
  .toast-success { border-color:rgba(34,197,94,0.5);  color:#4ade80; }
  .toast-error   { border-color:rgba(239,68,68,0.5);  color:#f87171; }

  .table-row:hover td { background:rgba(255,255,255,0.02); }

  @keyframes pulse   { 0%,100%{opacity:1} 50%{opacity:0.4} }
  @keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
  @keyframes spin    { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
  @keyframes fadeIn  { from{opacity:0} to{opacity:1} }
  .fa-spin { animation:spin 1s linear infinite; }
</style>
</head>
<body class="dark">

<!-- ═══ Sidebar ════════════════════════════════════════════════════════════ -->
<nav class="sidebar">

  <!-- Logo + Brand -->
  <div style="padding:16px;border-bottom:1px solid rgba(255,255,255,0.06);margin-bottom:8px;">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="width:36px;height:36px;background:linear-gradient(135deg,#f97316,#c2570b);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fas fa-tv" style="color:#fff;font-size:15px;"></i>
      </div>
      <div>
        <div style="font-size:14px;font-weight:800;color:#fff;line-height:1.2;">SignageCMS</div>
        <div style="font-size:10px;color:#475569;">v1.6.0</div>
      </div>
    </div>
    <!-- Sama Rayaneh Kish branding -->
    <a href="https://kishwifi.com" target="_blank" rel="noopener"
       style="display:flex;align-items:center;gap:7px;margin-top:10px;padding:7px 10px;
              background:rgba(44,74,140,0.12);border:1px solid rgba(44,74,140,0.25);
              border-radius:8px;text-decoration:none;transition:background .2s;"
       onmouseover="this.style.background='rgba(44,74,140,0.22)'"
       onmouseout="this.style.background='rgba(44,74,140,0.12)'">
      <img src="/assets/img/sama-logo.svg" alt="سماع رایانه کیش"
           style="height:26px;width:auto;object-fit:contain;"
           onerror="this.style.display='none'">
      <div style="line-height:1.3;">
        <div style="font-size:10px;font-weight:700;color:#7ba4e0;">سماع رایانه کیش</div>
        <div style="font-size:9px;color:#c8943a;letter-spacing:0.3px;">kishwifi.com</div>
      </div>
    </a>
  </div>

  <!-- ── اصلی ── -->
  <div class="sidebar-section"><?= __('nav.section.main') ?></div>
  <a href="/admin/dashboard" class="sidebar-link <?= isActive('/admin/dashboard') ?>">
    <span class="icon"><i class="fas fa-gauge"></i></span> <?= __('nav.dashboard') ?>
  </a>

  <!-- ── محتوا ── -->
  <div class="sidebar-section"><?= __('nav.section.content') ?></div>
  <a href="/admin/screens"   class="sidebar-link <?= isActive('/admin/screens') && !isActive('/admin/screens/monitor') ? 'active' : '' ?>">
    <span class="icon"><i class="fas fa-tv"></i></span> <?= __('nav.screens') ?>
  </a>
  <a href="/admin/playlists" class="sidebar-link <?= isActive('/admin/playlists') ?>">
    <span class="icon"><i class="fas fa-list"></i></span> <?= __('nav.playlists') ?>
  </a>
  <a href="/admin/media"     class="sidebar-link <?= isActive('/admin/media') ?>">
    <span class="icon"><i class="fas fa-photo-film"></i></span> <?= __('nav.media') ?>
  </a>
  <a href="/admin/layouts"   class="sidebar-link <?= isActive('/admin/layouts') ?>">
    <span class="icon"><i class="fas fa-table-cells-large"></i></span> <?= __('nav.layouts') ?>
  </a>
  <a href="/admin/schedules" class="sidebar-link <?= isActive('/admin/schedules') ?>">
    <span class="icon"><i class="fas fa-calendar"></i></span> <?= __('nav.schedules') ?>
  </a>
  <a href="/admin/messages" class="sidebar-link <?= isActive('/admin/messages') ?>">
    <span class="icon"><i class="fas fa-message" style="color:#a78bfa;"></i></span>
    <?= __('nav.messages') ?>
  </a>

  <!-- ── ماژول‌ها ── -->
  <div class="sidebar-section"><?= __('nav.modules') ?></div>

  <!-- مدیریت ماژول‌ها — همیشه نمایش داده می‌شود -->
  <a href="/admin/modules" class="sidebar-link <?= isActive('/admin/modules') ?>">
    <span class="icon"><i class="fas fa-puzzle-piece"></i></span>
    مدیریت ماژول‌ها
    <?php if (count($GLOBALS['_activeModules'] ?? []) > 0): ?>
      <span class="mod-badge"><?= count($GLOBALS['_activeModules']) ?></span>
    <?php endif; ?>
  </a>

  <!-- FIDS -->
  <?php if (modOn('fids')): ?>
  <a href="/admin/modules/fids/flights" class="sidebar-link <?= isActive('/admin/modules/fids') ?>">
    <span class="icon"><i class="fas fa-plane-departure" style="color:#38bdf8;"></i></span> پروازها (FIDS)
  </a>
  <?php endif; ?>

  <!-- Hotel -->
  <?php if (modOn('hotel')): ?>
  <a href="/admin/modules/hotel" class="sidebar-link <?= isActive('/admin/modules/hotel') ?>">
    <span class="icon"><i class="fas fa-hotel" style="color:#fbbf24;"></i></span> هتل
  </a>
  <?php endif; ?>

  <!-- Menu / Restaurant -->
  <?php if (modOn('menu')): ?>
  <a href="/admin/modules/menu" class="sidebar-link <?= isActive('/admin/modules/menu') ?>">
    <span class="icon"><i class="fas fa-utensils" style="color:#f97316;"></i></span> منوی رستوران
  </a>
  <?php endif; ?>

  <!-- Retail -->
  <?php if (modOn('retail')): ?>
  <a href="/admin/modules/retail" class="sidebar-link <?= isActive('/admin/modules/retail') ?>">
    <span class="icon"><i class="fas fa-store" style="color:#f472b6;"></i></span> فروشگاه
  </a>
  <?php endif; ?>

  <!-- Corporate -->
  <?php if (modOn('corporate')): ?>
  <a href="/admin/modules/corporate" class="sidebar-link <?= isActive('/admin/modules/corporate') ?>">
    <span class="icon"><i class="fas fa-building-columns" style="color:#818cf8;"></i></span> سازمانی
  </a>
  <?php endif; ?>

  <!-- Transport -->
  <?php if (modOn('transport')): ?>
  <a href="/admin/modules/transport" class="sidebar-link <?= isActive('/admin/modules/transport') ?>">
    <span class="icon"><i class="fas fa-bus" style="color:#34d399;"></i></span> حمل‌ونقل
  </a>
  <?php endif; ?>

  <!-- IPTV -->
  <?php if (modOn('iptv')): ?>
  <a href="/admin/iptv" class="sidebar-link <?= isActive('/admin/iptv') && !isActive('/admin/iptv/menus') && !isActive('/admin/iptv/rooms') && !isActive('/admin/iptv/tvheadend') ? 'active' : '' ?>">
    <span class="icon"><i class="fas fa-satellite-dish" style="color:#f87171;"></i></span> کانال‌های IPTV
  </a>
  <a href="/admin/iptv/tvheadend" class="sidebar-link <?= isActive('/admin/iptv/tvheadend') ?>">
    <span class="icon"><i class="fas fa-broadcast-tower" style="color:#f87171;"></i></span> TVHeadend
  </a>
  <a href="/admin/iptv/menus" class="sidebar-link <?= isActive('/admin/iptv/menus') ?>">
    <span class="icon"><i class="fas fa-bars" style="color:#f87171;"></i></span> منوهای IPTV
  </a>
  <a href="/admin/iptv/rooms" class="sidebar-link <?= isActive('/admin/iptv/rooms') ?>">
    <span class="icon"><i class="fas fa-door-open" style="color:#f87171;"></i></span> اتاق‌های IPTV
  </a>
  <?php endif; ?>

  <!-- Inflight -->
  <?php if (modOn('inflight')): ?>
  <a href="/admin/inflight" class="sidebar-link <?= isActive('/admin/inflight') ?>">
    <span class="icon"><i class="fas fa-plane" style="color:#00b4d8;"></i></span> نمایش پرواز ✈
  </a>
  <?php endif; ?>

  <!-- Monitor 3D -->
  <a href="/admin/monitor3d" class="sidebar-link <?= isActive('/admin/monitor3d') ?>">
    <span class="icon" style="font-size:14px;color:#00e5ff;">⬡</span> مانیتورهای ۳D
  </a>

  <!-- VOD -->
  <?php if (modOn('vod')): ?>
  <a href="/admin/vod" class="sidebar-link <?= isActive('/admin/vod') ?>">
    <span class="icon"><i class="fas fa-film" style="color:#ec4899;"></i></span> VOD / فیلم
  </a>
  <?php if (modOn('iptv')): // Transcoder نیاز به هر دو دارد ?>
  <a href="/admin/transcoder" class="sidebar-link <?= isActive('/admin/transcoder') ?>">
    <span class="icon"><i class="fas fa-microchip" style="color:#a855f7;"></i></span> Transcoder
  </a>
  <?php endif; ?>
  <?php endif; ?>

  <!-- ── ابزارها ── -->
  <div class="sidebar-section"><?= __('nav.section.tools') ?></div>
  <a href="/admin/screens/monitor" class="sidebar-link <?= isActive('/admin/screens/monitor') ?>">
    <span class="icon"><i class="fas fa-display" style="color:#4ade80;"></i></span> مانیتورینگ
  </a>
  <a href="/admin/app" class="sidebar-link <?= isActive('/admin/app') ?>">
    <span class="icon"><i class="fab fa-android" style="color:#4ade80;"></i></span> اپ Android
  </a>
  <a href="/docs" class="sidebar-link" target="_blank">
    <span class="icon"><i class="fas fa-book" style="color:#60a5fa;"></i></span> مستندات
  </a>

  <!-- ── مدیریت ── -->
  <div class="sidebar-section"><?= __('nav.section.admin') ?></div>
  <a href="/admin/campaigns" class="sidebar-link <?= isActive('/admin/campaigns') ?>">
    <span class="icon"><i class="fas fa-bullhorn"></i></span> کمپین‌ها
  </a>
  <a href="/admin/users"     class="sidebar-link <?= isActive('/admin/users') ?>">
    <span class="icon"><i class="fas fa-users"></i></span> کاربران
  </a>
  <a href="/admin/reports"   class="sidebar-link <?= isActive('/admin/reports') ?>">
    <span class="icon"><i class="fas fa-chart-bar"></i></span> گزارش‌ها
  </a>
  <a href="/admin/settings"  class="sidebar-link <?= isActive('/admin/settings') ?>">
    <span class="icon"><i class="fas fa-gear"></i></span> تنظیمات
  </a>
  <a href="/admin/help"  class="sidebar-link <?= isActive('/admin/help') ?>">
    <span class="icon"><i class="fas fa-circle-question" style="color:#818cf8;"></i></span> راهنما
  </a>

  <!-- فضای پایین -->
  <div style="height:24px;"></div>
</nav>

<!-- ═══ Topbar + Main ═══════════════════════════════════════════════════════ -->
<div class="main">
<div class="topbar">
  <div style="display:flex;align-items:center;gap:12px;">
    <span style="font-size:15px;font-weight:700;color:#fff;"><?= e($title ?? __('nav.dashboard')) ?></span>
  </div>
  <div style="display:flex;align-items:center;gap:12px;">
    <!-- WS indicator -->
    <div style="display:flex;align-items:center;gap:6px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:5px 10px;">
      <span id="ws-indicator" style="width:8px;height:8px;border-radius:50%;background:#f87171;display:inline-block;"></span>
      <span id="ws-status" style="font-size:11px;color:#64748b;">—</span>
    </div>

    <!-- Flash messages -->
    <?php if (!empty($flash)): ?>
    <?php foreach ($flash as $type => $msg): ?>
    <div id="flash-msg" style="background:<?= $type==='success'?'rgba(34,197,94,0.1)':'rgba(239,68,68,0.1)' ?>;border:1px solid <?= $type==='success'?'rgba(34,197,94,0.3)':'rgba(239,68,68,0.3)' ?>;border-radius:10px;padding:6px 12px;font-size:12px;color:<?= $type==='success'?'#4ade80':'#f87171' ?>;">
      <i class="fas fa-<?= $type==='success'?'check':'exclamation' ?>-circle ml-1"></i>
      <?= e($msg) ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- ── Language switcher ─────────────────────────── -->
    <div style="position:relative;" id="lang-menu-wrap">
      <button onclick="document.getElementById('lang-dropdown').style.display = document.getElementById('lang-dropdown').style.display==='block'?'none':'block'"
              style="display:flex;align-items:center;gap:6px;padding:5px 12px;
                     background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
                     border-radius:8px;color:#94a3b8;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;">
        <?= $_langList[$_uiLang]['flag'] ?? '🌐' ?>
        <span><?= $_langList[$_uiLang]['label'] ?? '' ?></span>
        <i class="fas fa-chevron-down" style="font-size:9px;"></i>
      </button>
      <div id="lang-dropdown"
           style="display:none;position:absolute;top:calc(100% + 6px);right:0;
                  background:#111118;border:1px solid rgba(255,255,255,.1);border-radius:12px;
                  padding:6px;min-width:130px;z-index:100;box-shadow:0 8px 32px rgba(0,0,0,.5);">
        <?php foreach ($_langList as $code => $info): ?>
        <a href="/lang/<?= $code ?>"
           style="display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:8px;
                  text-decoration:none;font-size:13px;
                  <?= $code === $_uiLang ? 'background:rgba(249,115,22,.12);color:#f97316;font-weight:700;' : 'color:#94a3b8;' ?>
                  ">
          <?= $info['flag'] ?> <?= $info['label'] ?>
          <?php if ($code === $_uiLang): ?><i class="fas fa-check text-xs" style="margin-<?= $_uiDir==='rtl'?'right':'left'?>:auto;color:#f97316;"></i><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- User menu -->
    <div style="display:flex;align-items:center;gap:8px;">
      <div style="width:32px;height:32px;background:linear-gradient(135deg,#f97316,#c2570b);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;">
        <?= mb_substr($authUser['name'] ?? 'A', 0, 1) ?>
      </div>
      <div style="font-size:12px;">
        <div style="color:#fff;font-weight:600;"><?= e($authUser['name'] ?? 'Admin') ?></div>
        <div style="color:#475569;"><?= e($authUser['role'] ?? '') ?></div>
      </div>
      <a href="/logout" style="margin-<?= $_uiDir==='rtl'?'right':'left'?>:6px;color:#475569;font-size:13px;text-decoration:none;" title="Logout / خروج">
        <i class="fas fa-right-from-bracket"></i>
      </a>
    </div>
  </div>
</div>

<!-- Main content -->
<main style="flex:1;padding:24px;max-width:1600px;width:100%;">
<script>
// ── بستن dropdown زبان با کلیک خارج ──────────────────────────────────
document.addEventListener('click', function(e) {
  const wrap = document.getElementById('lang-menu-wrap');
  const dd   = document.getElementById('lang-dropdown');
  if (wrap && dd && !wrap.contains(e.target)) {
    dd.style.display = 'none';
  }
});
</script>
