<?php
/**
 * تست صنف نصب (TODO ۵.۱۸): فقط ماژول‌های صنف روشن می‌ماند، داده و
 * تنظیمات دیگر مستاجر دست نمی‌خورد، و ماژولی که خاموش شده با نصب دوباره
 * واقعا روشن می‌شود (پیش از این INSERT IGNORE خاموش نگهش می‌داشت).
 */
define('ROOT_PATH',    dirname(__DIR__, 2));
define('APP_PATH',     ROOT_PATH . '/app');
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('VIEWS_PATH',   ROOT_PATH . '/resources/views');
define('PUBLIC_PATH',  ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('APP_DEBUG',    true);

error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);

$ENV = [];
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) continue;
    [$k, $v] = explode('=', $l, 2);
    $ENV[trim($k)] = trim($v, " \t\"'");
}
function env(string $k, mixed $d = null): mixed { global $ENV; return $ENV[$k] ?? $d; }
date_default_timezone_set((string)env('APP_TIMEZONE', 'Asia/Tehran'));

spl_autoload_register(function (string $c): void {
    $p = APP_PATH . '/' . str_replace(['App\\', '\\'], ['', '/'], $c) . '.php';
    if (file_exists($p)) require $p;
});

use App\Modules\Core\ModuleRegistry;
use App\Services\InstallProfile;

$db = App\Core\Database::getInstance();
$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✅ $label\n"; }
    else     { $fail++; echo "  ❌ $label" . ($detail ? "\n       → $detail" : '') . "\n"; }
}
function active(App\Core\Database $db): array {
    $ids = array_column($db->rows("SELECT id FROM modules WHERE tenant_id=1 AND is_active=1 ORDER BY id"), 'id');
    return $ids;
}

$db->query("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1,'Test Hotel','test')");
ModuleRegistry::ensureTable();
$before   = active($db);
$settings = $db->value('SELECT settings FROM tenants WHERE id=1');
$db->update('tenants', ['settings' => json_encode(['timezone' => 'Asia/Tehran'])], ['id' => 1]);

echo "\n── صنف‌ها ──\n";
$r = InstallProfile::apply('restaurant');
check('منوبورد: فقط منو روشن است', $r['ok'] && active($db) === ['menu'], implode(',', active($db)));
check('صنف در تنظیمات مستاجر ثبت شد', InstallProfile::current() === 'restaurant');
$cfg = json_decode((string)$db->value('SELECT settings FROM tenants WHERE id=1'), true);
check('تنظیمات دیگر مستاجر دست نخورد', ($cfg['timezone'] ?? '') === 'Asia/Tehran');

$r = InstallProfile::apply('hotel');
check('هتل کامل: ماژول خاموش‌شده دوباره روشن شد', $r['ok'] && active($db) === ['hotel', 'iptv', 'menu', 'vod'], implode(',', active($db)));
$r = InstallProfile::apply('iptv');
check('فقط IPTV', active($db) === ['iptv', 'vod'] && in_array('hotel', $r['disabled'], true));
$r = InstallProfile::apply('signage');
check('تابلوی لابی', active($db) === ['hotel', 'menu']);
$r = InstallProfile::apply('nope');
check('صنف ناشناخته رد شد و چیزی عوض نشد', !$r['ok'] && active($db) === ['hotel', 'menu']);

/* ModuleController::install همان BaseModule::install است */
ModuleRegistry::boot(1);
ModuleRegistry::get('vod')->install();
check('نصب دوباره‌ی ماژول خاموش از پنل هم روشنش می‌کند', in_array('vod', active($db), true));

// برگرداندن وضعیت قبلی
foreach (['hotel', 'iptv', 'vod', 'menu'] as $id) {
    $db->query('UPDATE modules SET is_active=? WHERE id=? AND tenant_id=1', [in_array($id, $before, true) ? 1 : 0, $id]);
}
$db->update('tenants', ['settings' => $settings], ['id' => 1]);

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";
exit($fail === 0 ? 0 : 1);
