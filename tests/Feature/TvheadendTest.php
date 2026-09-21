<?php
/**
 * تست اتصال خودکار TVHeadend.
 * یک TVHeadend ساختگی روی HTTP بالا می‌آید تا probe، احراز هویت،
 * همگام‌سازی کانال و ساخت خودکار منبع EPG واقعا آزمایش شوند.
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
function request(): object { return new class { public function ip(): string { return '127.0.0.1'; } public function userAgent(): string { return 'test'; } }; }

spl_autoload_register(function (string $c): void {
    $p = APP_PATH . '/' . str_replace(['App\\', '\\'], ['', '/'], $c) . '.php';
    if (file_exists($p)) require $p;
});

$db = App\Core\Database::getInstance();

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✅ $label\n"; }
    else     { $fail++; echo "  ❌ $label" . ($detail ? "\n       → $detail" : '') . "\n"; }
}

// ── TVHeadend ساختگی ─────────────────────────────────────────────
$port = 19981;
$dir  = sys_get_temp_dir() . '/hm-tvh-test';
@mkdir($dir, 0777, true);

file_put_contents($dir . '/index.php', <<<'PHPSRC'
<?php
$mode = @file_get_contents(__DIR__ . '/mode.txt') ?: 'ok';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($mode === 'auth') {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($h !== 'Basic ' . base64_encode('tvhuser:tvhpass')) {
        http_response_code(401); echo '{"error":"unauthorized"}'; exit;
    }
}

header('Content-Type: application/json');

if (str_starts_with($path, '/api/serverinfo')) {
    echo json_encode(['sw_version' => '4.3-1980', 'api_version' => 19]);
    exit;
}

if (str_starts_with($path, '/api/channel/list')) {
    if ($mode === 'empty') { echo json_encode(['entries' => [], 'total' => 0]); exit; }
    echo json_encode(['total' => 3, 'entries' => [
        ['uuid' => 'uuid-aaa', 'name' => 'شبکه یک',  'number' => 1, 'icon' => 'http://logo/1.png'],
        ['uuid' => 'uuid-bbb', 'name' => 'شبکه دو',  'number' => 2, 'icon' => 'imagecache/42'],
        ['uuid' => 'uuid-ccc', 'name' => 'شبکه سه',  'number' => 3, 'icon' => ''],
    ]]);
    exit;
}

http_response_code(404); echo '{"error":"not found"}';
PHPSRC);
file_put_contents($dir . '/mode.txt', 'ok');

$cmd = escapeshellarg(PHP_BINARY)
     . (php_ini_loaded_file() ? ' -c ' . escapeshellarg(php_ini_loaded_file()) : '')
     . ' -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($dir);

$proc = proc_open($cmd, [1 => ['file', $dir . '/out.log', 'a'], 2 => ['file', $dir . '/out.log', 'a']], $pipes);
register_shutdown_function(function () use ($proc) {
    if (!is_resource($proc)) return;
    $st  = proc_get_status($proc);
    $pid = (int)($st['pid'] ?? 0);
    if ($pid > 0 && stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        exec('taskkill /F /T /PID ' . $pid . ' 2>NUL');
    }
    proc_terminate($proc);
    proc_close($proc);
});

$up = false;
for ($i = 0; $i < 30; $i++) {
    $fp = @fsockopen('127.0.0.1', $port, $e, $s, 0.5);
    if ($fp) { fclose($fp); $up = true; break; }
    usleep(200000);
}

$url = "http://127.0.0.1:$port";

// ── آماده‌سازی ───────────────────────────────────────────────────
$db->query("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1,'Test Hotel','test')");
$db->query("DELETE FROM iptv_channels  WHERE tenant_id = 1 AND tvh_uuid LIKE 'uuid-%'");
$db->query("DELETE FROM epg_sources    WHERE tenant_id = 1 AND name LIKE '%TVHeadend%'");
$db->query("DELETE FROM tvheadend_sources WHERE tenant_id = 1 AND server_url LIKE '%:19981%'");

$svc = new App\Services\TvheadendService($db);

// ── ۱) probe ─────────────────────────────────────────────────────
echo "\n── ۱) بررسی در دسترس بودن سرور ──\n";
check('سرور ساختگی بالا آمد', $up, "پورت $port پاسخ نداد");

if (!$up) {
    echo "\n❌ بدون سرور ساختگی ادامه ممکن نیست\n";
    exit(1);
}

$p = $svc->probe($url);
check('probe موفق بود', $p['ok'], $p['message']);
check('نسخه سرور خوانده شد', $p['version'] === '4.3-1980', 'version=' . $p['version']);

$pDown = $svc->probe('http://127.0.0.1:19982');
check('سرور خاموش تشخیص داده شد', !$pDown['ok'], $pDown['message']);

$pBad = $svc->probe('ftp://x');
check('آدرس نامعتبر رد شد', !$pBad['ok'], $pBad['message']);

// ── ۲) ثبت خودکار ───────────────────────────────────────────────
echo "\n── ۲) ثبت خودکار و همگام‌سازی ──\n";
$r = $svc->autoRegister(1, $url);
check('ثبت موفق بود', $r['ok'], $r['message']);
check('سه کانال وارد شد', $r['channels'] === 3, 'channels=' . $r['channels']);

$ch = $db->rows("SELECT * FROM iptv_channels WHERE tenant_id=1 AND tvh_uuid LIKE 'uuid-%' ORDER BY channel_no");
check('کانال‌ها در دیتابیس هستند', count($ch) === 3, 'تعداد=' . count($ch));
check('نام فارسی درست ذخیره شد', ($ch[0]['name'] ?? '') === 'شبکه یک');
check('آدرس استریم ساخته شد',
    str_contains((string)($ch[0]['stream_url'] ?? ''), '/stream/channel/uuid-aaa'),
    $ch[0]['stream_url'] ?? '');
check('شماره کانال برای ریموت ثبت شد', (int)($ch[0]['channel_no'] ?? 0) === 1);
check('uuid به‌عنوان کلید EPG ذخیره شد', ($ch[0]['epg_id'] ?? '') === 'uuid-aaa');
check('لوگوی http نگه داشته شد', ($ch[0]['logo_url'] ?? '') === 'http://logo/1.png');

// imagecache داخلی TVHeadend از بیرون قابل دسترسی نیست.
// ‏?? مقدار null را هم می‌گیرد، پس باید صریح چک شود.
check('لوگوی imagecache رد شد',
    array_key_exists('logo_url', $ch[1] ?? []) && $ch[1]['logo_url'] === null,
    'logo=' . var_export($ch[1]['logo_url'] ?? 'missing', true));

// ── ۳) منبع EPG خودکار ──────────────────────────────────────────
echo "\n── ۳) منبع EPG خودکار ──\n";
$epg = $db->row("SELECT * FROM epg_sources WHERE tenant_id=1 AND source_type='tvheadend'");
check('منبع EPG خودکار ساخته شد', $epg !== null);
check('به همان سرور TVHeadend وصل است',
    (int)($epg['tvh_source_id'] ?? 0) === (int)$r['source_id']);
check('منبع EPG فعال است', (int)($epg['is_active'] ?? 0) === 1);

// ── ۴) اجرای دوباره ─────────────────────────────────────────────
echo "\n── ۴) اجرای دوباره نباید تکراری بسازد ──\n";
$r2 = $svc->autoRegister(1, $url);
check('اجرای دوباره موفق بود', $r2['ok'], $r2['message']);
check('همان منبع استفاده شد', $r2['source_id'] === $r['source_id']);
check('منبع TVHeadend تکراری ساخته نشد',
    (int)$db->value("SELECT COUNT(*) FROM tvheadend_sources WHERE tenant_id=1 AND server_url=?", [$url]) === 1);
check('منبع EPG تکراری ساخته نشد',
    (int)$db->value("SELECT COUNT(*) FROM epg_sources WHERE tenant_id=1 AND source_type='tvheadend'") === 1);
check('کانال تکراری ساخته نشد',
    (int)$db->value("SELECT COUNT(*) FROM iptv_channels WHERE tenant_id=1 AND tvh_uuid LIKE 'uuid-%'") === 3);

// ── ۵) آدرس multicast دستی نباید پاک شود ────────────────────────
echo "\n── ۵) تنظیمات دستی اپراتور باید حفظ شود ──\n";
$db->update('iptv_channels',
    ['multicast_url' => 'udp://@239.9.9.9:5000', 'delivery' => 'multicast', 'channel_no' => 77],
    ['tvh_uuid' => 'uuid-aaa']
);
$svc->syncChannels(1, (int)$r['source_id']);

$after = $db->row("SELECT multicast_url, delivery, channel_no FROM iptv_channels WHERE tvh_uuid='uuid-aaa'");
check('آدرس multicast بعد از sync حفظ شد',
    ($after['multicast_url'] ?? '') === 'udp://@239.9.9.9:5000', json_encode($after, JSON_UNESCAPED_UNICODE));
check('شماره کانال دستی حفظ شد', (int)($after['channel_no'] ?? 0) === 77);

// ── ۶) احراز هویت ───────────────────────────────────────────────
echo "\n── ۶) احراز هویت ──\n";
file_put_contents($dir . '/mode.txt', 'auth');

$noAuth = $svc->probe($url);
check('بدون رمز، خطای ۴۰۱ گزارش شد', !$noAuth['ok'] && str_contains($noAuth['message'], 'رمز'), $noAuth['message']);

$withAuth = $svc->probe($url, 'tvhuser', 'tvhpass');
check('با رمز درست، اتصال برقرار شد', $withAuth['ok'], $withAuth['message']);

file_put_contents($dir . '/mode.txt', 'ok');

// ── ۷) TVHeadend بدون کانال ─────────────────────────────────────
echo "\n── ۷) TVHeadend تازه‌نصب بدون کانال ──\n";
file_put_contents($dir . '/mode.txt', 'empty');
$empty = $svc->syncChannels(1, (int)$r['source_id']);
check('پیام راهنما داده شد نه خطای مبهم',
    !$empty['ok'] && str_contains($empty['message'], 'اسکن'), $empty['message']);
file_put_contents($dir . '/mode.txt', 'ok');

// ── ۸) بررسی سلامت ──────────────────────────────────────────────
echo "\n── ۸) بررسی سلامت ──\n";
$health = $svc->health(1);
check('گزارش سلامت برگشت', count($health) > 0);
$h = $health[0] ?? [];
check('وضعیت آنلاین درست است', ($h['online'] ?? false) === true, json_encode($h, JSON_UNESCAPED_UNICODE));
check('تعداد کانال گزارش شد', (int)($h['channels'] ?? 0) === 3, 'channels=' . ($h['channels'] ?? '?'));

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";

// پاک‌سازی
$db->query("DELETE FROM iptv_channels  WHERE tenant_id = 1 AND tvh_uuid LIKE 'uuid-%'");
$db->query("DELETE FROM epg_sources    WHERE tenant_id = 1 AND source_type='tvheadend'");
$db->query("DELETE FROM tvheadend_sources WHERE tenant_id = 1 AND server_url LIKE '%:19981%'");

exit($fail === 0 ? 0 : 1);
