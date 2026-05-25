<?php
// SECURITY: این فایل رو بعد از debug حذف کن!
// فقط برای پیدا کردن مشکلات

define('ROOT_PATH',    dirname(__DIR__));
define('APP_PATH',     ROOT_PATH . '/app');
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('VIEWS_PATH',   ROOT_PATH . '/resources/views');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('PUBLIC_PATH',  __DIR__);

spl_autoload_register(function(string $class): void {
    $path = APP_PATH . '/' . str_replace(['App\\', '\\'], ['', '/'], $class) . '.php';
    if (file_exists($path)) require $path;
});

function env(string $key, mixed $default = null): mixed {
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null) return $default;
    if ($val === 'true') return true;
    if ($val === 'false') return false;
    return $val;
}

// Load .env
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $k = trim($k); $v = trim($v, " \t\n\r\"'");
    if (!isset($_ENV[$k])) { $_ENV[$k] = $v; putenv("$k=$v"); }
}

define('APP_DEBUG', true);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$checks = [];

// 1. DB connection
try {
    $db = \App\Core\Database::getInstance();
    $checks[] = ['✅', 'اتصال به پایگاه داده', 'OK'];
} catch (\Throwable $e) {
    $checks[] = ['❌', 'اتصال به پایگاه داده', $e->getMessage()];
}

// 2. Tables
$tables = ['tenants','users','screens','playlists','media','schedules','layouts','locations','modules',
           'fids_flights','hotel_events','hotel_amenities','corp_kpi','retail_products','transport_schedules'];
foreach ($tables as $t) {
    try {
        $db->query("SELECT 1 FROM `$t` LIMIT 1");
        $checks[] = ['✅', "جدول: $t", 'OK'];
    } catch (\Throwable $e) {
        $checks[] = ['❌', "جدول: $t", $e->getMessage()];
    }
}

// 3. Views
$views = ['dashboard/index','screens/index','screens/create','screens/show','playlists/index',
          'playlists/create','playlists/edit','media/index','layouts/index','schedules/index',
          'users/index','campaigns/index','settings/index','reports/index','menu/index',
          'auth/login','errors/404','errors/500','partials/layout','partials/layout_footer'];
foreach ($views as $v) {
    $path = VIEWS_PATH . '/' . $v . '.php';
    $checks[] = [file_exists($path) ? '✅' : '❌', "view: $v", file_exists($path) ? 'OK' : 'MISSING'];
}

// 4. Storage directories
foreach (['storage/sessions','storage/logs','storage/cache','public/uploads'] as $dir) {
    $full = ROOT_PATH . '/' . $dir;
    $ok   = is_dir($full) && is_writable($full);
    $checks[] = [$ok ? '✅' : '❌', "دایرکتوری: $dir", $ok ? 'writable' : 'NOT writable'];
}

// 5. PHP extensions
foreach (['pdo_mysql','gd','zip','mbstring','json','intl'] as $ext) {
    $checks[] = [extension_loaded($ext) ? '✅' : '❌', "PHP ext: $ext", extension_loaded($ext) ? 'loaded' : 'MISSING'];
}

// Output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html dir="rtl"><head><meta charset="UTF-8"><title>Debug Check</title>
<style>body{font-family:monospace;background:#0a0a0f;color:#e2e8f0;padding:20px;direction:rtl;}
table{border-collapse:collapse;width:100%;}td{padding:6px 10px;border-bottom:1px solid #333;}
.ok{color:#4ade80;}.err{color:#f87171;}.title{color:#f97316;font-size:18px;font-weight:bold;margin-bottom:16px;}
</style></head><body>
<div class="title">🔍 SignageCMS Debug Check</div>
<p style="color:#64748b;font-size:12px;">⚠ این صفحه رو بعد از debug حذف کن: <code>rm public/debug_check.php</code></p>
<table>
<tr><th>وضعیت</th><th>مورد</th><th>نتیجه</th></tr>
<?php foreach ($checks as $c): ?>
<tr>
  <td class="<?= $c[0]==='✅'?'ok':'err' ?>"><?= $c[0] ?></td>
  <td><?= htmlspecialchars($c[1]) ?></td>
  <td><?= htmlspecialchars($c[2]) ?></td>
</tr>
<?php endforeach; ?>
</table>
<hr style="border-color:#333;margin:20px 0">
<p style="color:#64748b;font-size:11px;">PHP <?= PHP_VERSION ?> | Server: <?= $_SERVER['SERVER_SOFTWARE'] ?? 'unknown' ?></p>
</body></html>
