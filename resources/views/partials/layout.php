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
<title><?= e($title ?? 'Hotel Media') ?> — Hotel Media</title>
<?php
// همه‌ی دارایی‌ها محلی‌اند. سرور هتل معمولا روی شبکه‌ی بسته است و با
// CDN، پنل مدیریت بدون هیچ استایلی بالا می‌آمد.
?>
<link rel="stylesheet" href="/assets/vendor/vazirmatn/vazirmatn.css">
<link rel="stylesheet" href="/assets/vendor/inter/inter.css">
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="/assets/css/design-system.css">
<script src="/assets/vendor/tailwind/tailwind.min.js"></script>
<script>
  // ‏Tailwind برای کلاس‌های کمکی در نماهای موجود می‌ماند؛ رنگ‌ها و
  // کنترل‌ها از design-system.css می‌آیند تا یک منبع حقیقت باشد.
  tailwind.config = {
    darkMode: ['class', '[data-theme="dark"]'],
    theme: {
      extend: {
        fontFamily: { sans: ['Vazirmatn', 'Inter', 'sans-serif'], mono: ['Inter', 'monospace'] },
        colors: {
          brand: {
            50:'#eef6fd', 100:'#d6e9f9', 200:'#b0d4f2', 300:'#7bb8e8', 400:'#4098db',
            500:'#1a7ac4', 600:'#1668b3', 700:'#12558f', 800:'#104670', 900:'#0d3a5c',
          },
        },
      },
    },
  };
</script>
<style>
  /* زبان فعال فونت خودش را دارد — عربی و انگلیسی با وزیرمتن خوب نیستند */
  :root { --font-ui: '<?= $_uiFont ?>', 'Vazirmatn', 'Inter', sans-serif; }
</style>
</head>
<body class="dark">

<!-- ═══ Sidebar ════════════════════════════════════════════════════════════ -->
<nav class="sidebar">

  <!-- نشان: یک بلوک واحد. قبلا دو نشان رقیب اینجا بود (کاشی نارنجی + کارت
       سما با رنگ‌های نامربوط)؛ حالا لوگوی واقعی سما نشانِ اصلی است. -->
  <div class="brand">
    <img class="brand-mark" src="/assets/img/sama-logo.svg" alt="سماع رایانه کیش">
    <div class="brand-text">
      <div class="brand-name">Hotel Media</div>
      <a class="brand-sub" href="https://kishwifi.com" target="_blank" rel="noopener">سماع رایانه کیش</a>
    </div>
    <span class="brand-ver">v<?= e(config('app.version')) ?></span>
  </div>

  <!-- ── اصلی ── -->
  <div class="sidebar-section"><?= __('nav.section.main') ?></div>
  <a href="/admin/dashboard" class="sidebar-link <?= isActive('/admin/dashboard') ?>">
    <span class="icon"><i class="fas fa-gauge"></i></span> <?= __('nav.dashboard') ?>
  </a>

  <?php
  /* ══ دو دنیای جدا ═══════════════════════════════════════════════
     تابلوی دیجیتال و IPTV اتاق دو کار کاملا متفاوت‌اند و اپراتورشان
     هم معمولا یک نفر نیست:

       تابلو  → لابی، رستوران، آسانسور. محتوا پلی‌لیست است و اپراتور
                مارکتینگ یا روابط‌عمومی به آن دست می‌زند.
       IPTV   → تلویزیون داخل اتاق. محتوا کانال و منوی مهمان است و
                IT هتل به آن دست می‌زند.

     پیش از این هر دو زیر یک عنوان «محتوا» و «ماژول‌ها» قاطی بودند و
     اپراتور نمی‌دانست «پلی‌لیست» به کدام‌یک مربوط است. حالا هر کدام
     عنوان خودش را دارد و چیزی بینشان مشترک نیست. */
  ?>

  <!-- ══ تابلوی دیجیتال ══ -->
  <div class="sidebar-section">
    <i class="fas fa-display" style="margin-left:5px;opacity:.6"></i> تابلوی دیجیتال
  </div>
  <a href="/admin/screens" class="sidebar-link <?= isActive('/admin/screens') && !isActive('/admin/screens/monitor') ? 'active' : '' ?>">
    <span class="icon"><i class="fas fa-tv"></i></span> صفحه‌نمایش‌ها
  </a>
  <a href="/admin/playlists" class="sidebar-link <?= isActive('/admin/playlists') ?>">
    <span class="icon"><i class="fas fa-list"></i></span> پلی‌لیست‌ها
  </a>
  <a href="/admin/media" class="sidebar-link <?= isActive('/admin/media') ?>">
    <span class="icon"><i class="fas fa-photo-film"></i></span> رسانه‌ها
  </a>
  <a href="/admin/layouts" class="sidebar-link <?= isActive('/admin/layouts') ?>">
    <span class="icon"><i class="fas fa-table-cells-large"></i></span> طراح چیدمان
  </a>
  <a href="/admin/schedules" class="sidebar-link <?= isActive('/admin/schedules') ?>">
    <span class="icon"><i class="fas fa-calendar"></i></span> زمان‌بندی
  </a>
  <?php /* همان صفحه‌ای که «کمپین‌ها و اعلان اضطراری» نام داشت. اسمش
           عوض شد چون اپراتور هتل «کمپین» را نمی‌شناسد — کاری که
           می‌کند پخش فوری یک پیام روی تابلوهاست. */ ?>
  <a href="/admin/campaigns" class="sidebar-link <?= isActive('/admin/campaigns') ?>">
    <span class="icon"><i class="fas fa-bullhorn"></i></span> پخش فوری و اعلان
  </a>
  <a href="/admin/monitor3d" class="sidebar-link <?= isActive('/admin/monitor3d') ?>">
    <span class="icon"><i class="fas fa-cube"></i></span> مانیتور سه‌بعدی
  </a>

  <!-- ══ تلویزیون اتاق (IPTV) ══ -->
  <?php if (modOn('iptv')): ?>
  <div class="sidebar-section">
    <i class="fas fa-satellite-dish" style="margin-left:5px;opacity:.6"></i> تلویزیون اتاق
  </div>
  <a href="/admin/iptv/rooms" class="sidebar-link <?= isActive('/admin/iptv/rooms') ?>">
    <span class="icon"><i class="fas fa-door-open"></i></span> اتاق‌ها
  </a>
  <a href="/admin/devices" class="sidebar-link <?= isActive('/admin/devices') ?>">
    <span class="icon"><i class="fas fa-tv"></i></span> تلویزیون‌ها
  </a>
  <a href="/admin/iptv" class="sidebar-link <?= isActive('/admin/iptv') && !isActive('/admin/iptv/menus') && !isActive('/admin/iptv/rooms') && !isActive('/admin/iptv/tvheadend') ? 'active' : '' ?>">
    <span class="icon"><i class="fas fa-list-ol"></i></span> کانال‌ها
  </a>
  <a href="/admin/iptv/menus" class="sidebar-link <?= isActive('/admin/iptv/menus') ?>">
    <span class="icon"><i class="fas fa-bars"></i></span> منوی مهمان
  </a>
  <a href="/admin/epg" class="sidebar-link <?= isActive('/admin/epg') ?>">
    <span class="icon"><i class="fas fa-calendar-days"></i></span> راهنمای برنامه‌ها
  </a>
  <a href="/admin/guest-services" class="sidebar-link <?= isActive('/admin/guest-services') ?>">
    <span class="icon"><i class="fas fa-concierge-bell"></i></span> خدمات مهمان
  </a>
  <a href="/admin/messages" class="sidebar-link <?= isActive('/admin/messages') ?>">
    <span class="icon"><i class="fas fa-message"></i></span> پیام به اتاق
  </a>
  <?php endif; ?>

  <!-- ══ محتوای هتل — بین هر دو مشترک است ══ -->
  <?php if (modOn('hotel') || modOn('menu') || modOn('vod')): ?>
  <div class="sidebar-section">
    <i class="fas fa-hotel" style="margin-left:5px;opacity:.6"></i> محتوای هتل
  </div>
  <?php if (modOn('hotel')): ?>
  <a href="/admin/modules/hotel" class="sidebar-link <?= isActive('/admin/modules/hotel') ?>">
    <span class="icon"><i class="fas fa-circle-info"></i></span> اطلاعات و رویدادها
  </a>
  <?php endif; ?>
  <?php if (modOn('menu')): ?>
  <a href="/admin/modules/menu" class="sidebar-link <?= isActive('/admin/modules/menu') ?>">
    <span class="icon"><i class="fas fa-utensils"></i></span> منوی رستوران
  </a>
  <?php endif; ?>
  <?php if (modOn('vod')): ?>
  <a href="/admin/vod" class="sidebar-link <?= isActive('/admin/vod') ?>">
    <span class="icon"><i class="fas fa-film"></i></span> فیلم و سریال
  </a>
  <?php endif; ?>
  <?php endif; ?>

  <!-- ══ زیرساخت پخش ══ -->
  <?php if (modOn('iptv')): ?>
  <div class="sidebar-section">
    <i class="fas fa-server" style="margin-left:5px;opacity:.6"></i> زیرساخت پخش
  </div>
  <a href="/admin/iptv/tvheadend" class="sidebar-link <?= isActive('/admin/iptv/tvheadend') ?>">
    <span class="icon"><i class="fas fa-tower-broadcast"></i></span> هدِند (TVHeadend)
  </a>
  <?php if (modOn('vod')): ?>
  <a href="/admin/transcoder" class="sidebar-link <?= isActive('/admin/transcoder') ?>">
    <span class="icon"><i class="fas fa-wand-magic-sparkles"></i></span> ترنسکد
  </a>
  <?php endif; ?>
  <?php endif; ?>
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
  <a href="/admin/users"     class="sidebar-link <?= isActive('/admin/users') ?>">
    <span class="icon"><i class="fas fa-users"></i></span> کاربران
  </a>
  <a href="/admin/reports"   class="sidebar-link <?= isActive('/admin/reports') ?>">
    <span class="icon"><i class="fas fa-chart-bar"></i></span> گزارش‌ها
  </a>
  <?php /* مدیریت ماژول‌ها جای واقعی‌اش اینجاست نه بالای فهرست: کاری
           است که یک‌بار موقع راه‌اندازی انجام می‌شود، نه هر روز. */ ?>
  <a href="/admin/modules" class="sidebar-link <?= isActive('/admin/modules') ?>">
    <span class="icon"><i class="fas fa-puzzle-piece"></i></span>
    ماژول‌ها
    <?php if (count($GLOBALS['_activeModules'] ?? []) > 0): ?>
      <span class="mod-badge"><?= count($GLOBALS['_activeModules']) ?></span>
    <?php endif; ?>
  </a>
  <a href="/admin/settings"  class="sidebar-link <?= isActive('/admin/settings') ?>">
    <span class="icon"><i class="fas fa-gear"></i></span> تنظیمات
  </a>
  <?php /* به‌روزرسانی سرور فایل‌های کد را جایگزین و مهاجرت دیتابیس
           اجرا می‌کند. کارمند پذیرش نباید حتی دکمه‌اش را ببیند. */ ?>
  <?php if (($authUser['role'] ?? '') === 'super_admin'): ?>
  <a href="/admin/system/update" class="sidebar-link <?= isActive('/admin/system/update') ?>">
    <span class="icon"><i class="fas fa-cloud-arrow-down" style="color:#4098db;"></i></span>
    به‌روزرسانی سیستم
  </a>
  <?php endif; ?>
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
                  <?= $code === $_uiLang ? 'background:rgba(26,122,196,.12);color:#1a7ac4;font-weight:700;' : 'color:#94a3b8;' ?>
                  ">
          <?= $info['flag'] ?> <?= $info['label'] ?>
          <?php if ($code === $_uiLang): ?><i class="fas fa-check text-xs" style="margin-<?= $_uiDir==='rtl'?'right':'left'?>:auto;color:#1a7ac4;"></i><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- User menu -->
    <div style="display:flex;align-items:center;gap:8px;">
      <div style="width:32px;height:32px;background:linear-gradient(135deg,#1a7ac4,#12558f);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;">
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
