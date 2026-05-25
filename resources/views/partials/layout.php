<?php
use App\Core\Auth;
use App\Modules\Core\ModuleRegistry;

if (!Auth::check() && !str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/player')) {
    App\Core\Response::redirect('/login');
}

$authUser    = Auth::user() ?? [];
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$flash       = $_SESSION['_flash'] ?? [];
unset($_SESSION['_flash']);

// ── بارگذاری ماژول‌های فعال ─────────────────────────────────────────────────
// از GLOBALS استفاده می‌کنیم چون layout داخل متد include میشه
$GLOBALS['_activeModules'] = [];
try {
    ModuleRegistry::ensureTable();
    ModuleRegistry::boot(Auth::tenantId());
    $GLOBALS['_activeModules'] = ModuleRegistry::activeIds();
} catch (\Throwable $e) {}

function isActive(string $path): string {
    global $currentPath;
    return str_starts_with($currentPath, $path) ? 'active' : '';
}
function modOn(string $id): bool {
    return in_array($id, $GLOBALS['_activeModules'] ?? [], true);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<title><?= e($title ?? 'SignageCMS') ?> — سیستم مدیریت تابلو دیجیتال</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
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
  body { font-family: 'Vazirmatn', sans-serif; background: #0a0a0f; color: #e2e8f0; direction: rtl; }
  ::-webkit-scrollbar { width: 6px; }
  ::-webkit-scrollbar-track { background: #111118; }
  ::-webkit-scrollbar-thumb { background: #2d2d40; border-radius: 3px; }
  ::-webkit-scrollbar-thumb:hover { background: #f97316; }

  .sidebar { width:240px; background:#111118; border-left:1px solid rgba(255,255,255,0.06); height:100vh; position:fixed; right:0; top:0; overflow-y:auto; z-index:40; }
  .main { margin-right:240px; min-height:100vh; display:flex; flex-direction:column; }
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

  <!-- Logo -->
  <div style="padding:18px 16px;border-bottom:1px solid rgba(255,255,255,0.06);margin-bottom:8px;">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="width:34px;height:34px;background:linear-gradient(135deg,#f97316,#c2570b);border-radius:10px;display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-tv" style="color:#fff;font-size:15px;"></i>
      </div>
      <div>
        <div style="font-size:14px;font-weight:800;color:#fff;">SignageCMS</div>
        <div style="font-size:10px;color:#475569;">v1.4.1</div>
      </div>
    </div>
  </div>

  <!-- ── اصلی ── -->
  <div class="sidebar-section">اصلی</div>
  <a href="/admin/dashboard" class="sidebar-link <?= isActive('/admin/dashboard') ?>">
    <span class="icon"><i class="fas fa-gauge"></i></span> داشبورد
  </a>

  <!-- ── محتوا ── -->
  <div class="sidebar-section">محتوا</div>
  <a href="/admin/screens"   class="sidebar-link <?= isActive('/admin/screens') ?>">
    <span class="icon"><i class="fas fa-tv"></i></span> صفحات نمایش
  </a>
  <a href="/admin/playlists" class="sidebar-link <?= isActive('/admin/playlists') ?>">
    <span class="icon"><i class="fas fa-list"></i></span> پلی‌لیست
  </a>
  <a href="/admin/media"     class="sidebar-link <?= isActive('/admin/media') ?>">
    <span class="icon"><i class="fas fa-photo-film"></i></span> رسانه‌ها
  </a>
  <a href="/admin/layouts"   class="sidebar-link <?= isActive('/admin/layouts') ?>">
    <span class="icon"><i class="fas fa-table-cells-large"></i></span> چیدمان
  </a>
  <a href="/admin/schedules" class="sidebar-link <?= isActive('/admin/schedules') ?>">
    <span class="icon"><i class="fas fa-calendar"></i></span> زمان‌بندی
  </a>

  <!-- ── ماژول‌ها ── -->
  <div class="sidebar-section">ماژول‌ها</div>

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
  <a href="/admin/iptv" class="sidebar-link <?= isActive('/admin/iptv') && !isActive('/admin/iptv/menus') && !isActive('/admin/iptv/rooms') ? 'active' : '' ?>">
    <span class="icon"><i class="fas fa-satellite-dish" style="color:#f87171;"></i></span> کانال‌های IPTV
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
  <div class="sidebar-section">ابزارها</div>
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
  <div class="sidebar-section">مدیریت</div>
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

  <!-- فضای پایین -->
  <div style="height:24px;"></div>
</nav>

<!-- ═══ Topbar + Main ═══════════════════════════════════════════════════════ -->
<div class="main">
<div class="topbar">
  <div style="display:flex;align-items:center;gap:12px;">
    <span style="font-size:15px;font-weight:700;color:#fff;"><?= e($title ?? 'داشبورد') ?></span>
  </div>
  <div style="display:flex;align-items:center;gap:12px;">
    <!-- WS indicator -->
    <div style="display:flex;align-items:center;gap:6px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:5px 10px;">
      <span id="ws-indicator" style="width:8px;height:8px;border-radius:50%;background:#f87171;display:inline-block;"></span>
      <span id="ws-status" style="font-size:11px;color:#64748b;">قطع</span>
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

    <!-- User menu -->
    <div style="display:flex;align-items:center;gap:8px;">
      <div style="width:32px;height:32px;background:linear-gradient(135deg,#f97316,#c2570b);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;">
        <?= mb_substr($authUser['name'] ?? 'A', 0, 1) ?>
      </div>
      <div style="font-size:12px;">
        <div style="color:#fff;font-weight:600;"><?= e($authUser['name'] ?? 'مدیر') ?></div>
        <div style="color:#475569;"><?= e($authUser['role'] ?? '') ?></div>
      </div>
      <a href="/logout" style="margin-right:6px;color:#475569;font-size:13px;text-decoration:none;" title="خروج">
        <i class="fas fa-right-from-bracket"></i>
      </a>
    </div>
  </div>
</div>

<!-- Main content -->
<main style="flex:1;padding:24px;max-width:1600px;width:100%;">
