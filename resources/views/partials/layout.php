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

  <?php include VIEWS_PATH . '/partials/nav.php'; ?>
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

    <!-- ── شعبه (هتل زنجیره‌ای) ─────────────────────────── -->
    <?php
      $_branchLocked = \App\Core\Branch::locked();
      $_branches = [];
      try { $_branches = \App\Core\Database::getInstance()->rows('SELECT id, name FROM locations WHERE tenant_id=? AND is_active=1 ORDER BY name', [\App\Core\Auth::tenantId()]); } catch (\Throwable $e) {}
      $_branchNow = \App\Core\Branch::current();
    ?>
    <?php if ($_branchLocked !== null): ?>
      <?php foreach ($_branches as $_b) if ((int)$_b['id'] === $_branchLocked): ?>
      <span title="حساب شما به این شعبه محدود است" style="font-size:12px;color:#fbbf24;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.25);border-radius:8px;padding:5px 10px;">
        <i class="fas fa-building ml-1"></i><?= e($_b['name']) ?>
      </span>
      <?php endif; ?>
    <?php elseif (count($_branches) > 1): ?>
    <form method="POST" action="/admin/branch" style="margin:0;">
      <?= csrf_field() ?>
      <select name="location_id" onchange="this.form.submit()" title="نمایش فقط یک شعبه"
              style="font-size:12px;background:<?= $_branchNow ? 'rgba(251,191,36,.08)' : 'rgba(255,255,255,0.04)' ?>;color:<?= $_branchNow ? '#fbbf24' : '#cbd5e1' ?>;border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:5px 8px;">
        <option value="0">همه‌ی شعبه‌ها</option>
        <?php foreach ($_branches as $_b): ?>
        <option value="<?= (int)$_b['id'] ?>" <?= $_branchNow === (int)$_b['id'] ? 'selected' : '' ?>><?= e($_b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
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
