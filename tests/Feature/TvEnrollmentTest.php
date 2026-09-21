<?php
/**
 * تست جریان واقعی نصب در هتل — همان کاری که تکنسین با منوی مخفی می‌کند.
 *
 * LG:      نگه‌داشتن MENU تا منو محو شود → 1105 → OK → Manual Pro:Centric
 * Samsung: MUTE → 1 → 1 → 9 → ENTER → URL Launcher
 * در هر دو، **یک آدرس ثابت** در همه‌ی تلویزیون‌ها وارد می‌شود، پس این تست
 * بررسی می‌کند که همان یک آدرس برای چند دستگاه مختلف کار کند.
 *
 * به سرور زنده روی TEST_BASE_URL و دیتابیس تست نیاز دارد.
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

$BASE = rtrim((string)(getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:18080'), '/');

$db = App\Core\Database::getInstance();

$pass = 0; $fail = 0; $skipped = false;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✅ $label\n"; }
    else     { $fail++; echo "  ❌ $label" . ($detail ? "\n       → $detail" : '') . "\n"; }
}

/** درخواست HTTP ساده */
function http(string $method, string $url, ?array $body = null, string $ua = ''): array {
    $opts = ['http' => [
        'method'        => $method,
        'timeout'       => 20,
        'ignore_errors' => true,
        'header'        => "Content-Type: application/json\r\n"
                         . ($ua ? "User-Agent: $ua\r\n" : ''),
    ]];
    if ($body !== null) $opts['http']['content'] = json_encode($body, JSON_UNESCAPED_UNICODE);

    $raw = @file_get_contents($url, false, stream_context_create($opts));
    $code = 0;
    if (isset($http_response_header)) {
        preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
        $code = (int)($m[1] ?? 0);
    }
    return ['status' => $code, 'body' => json_decode((string)$raw, true), 'raw' => (string)$raw];
}

// ── آیا سرور بالاست؟ ─────────────────────────────────────────────
$ping = http('GET', $BASE . '/tv');
if ($ping['status'] === 0) {
    echo "\n⏭  سرور تست روی $BASE در دسترس نیست — این تست رد شد.\n";
    echo "   برای اجرا: php -S 127.0.0.1:18080 -t public public/server-router.php\n";
    exit(0);
}

// ── آماده‌سازی ───────────────────────────────────────────────────
$db->query("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1,'Test Hotel','test')");
$db->query("DELETE FROM screen_commands WHERE tenant_id = 1");
$db->query("DELETE FROM screen_events   WHERE tenant_id = 1");
$db->query("DELETE FROM screens WHERE tenant_id = 1 AND name LIKE 'TV %'");
$db->query("DELETE FROM screens WHERE mac_address IN ('AA:BB:CC:00:00:01','AA:BB:CC:00:00:02','AA:BB:CC:00:00:03')");
$db->query("DELETE FROM enrollment_tokens WHERE tenant_id = 1 AND label LIKE 'TEST%'");

$tokenAuto = bin2hex(random_bytes(16));
$db->insert('enrollment_tokens', [
    'tenant_id' => 1, 'token' => $tokenAuto, 'label' => 'TEST auto-approve',
    'screen_type' => 'iptv', 'auto_approve' => 1, 'max_devices' => 3,
    'expires_at' => date('Y-m-d H:i:s', time() + 86400),
]);

$tokenManual = bin2hex(random_bytes(16));
$db->insert('enrollment_tokens', [
    'tenant_id' => 1, 'token' => $tokenManual, 'label' => 'TEST manual-approve',
    'screen_type' => 'iptv', 'auto_approve' => 0,
    'expires_at' => date('Y-m-d H:i:s', time() + 86400),
]);

// ── ۱) صفحه bootstrap ────────────────────────────────────────────
echo "\n── ۱) آدرسی که در منوی مخفی تلویزیون وارد می‌شود ──\n";
$page = $ping['raw'];
check('‏/tv پاسخ ۲۰۰ می‌دهد', $ping['status'] === 200);
check('توکن ثبت در صفحه تزریق شده', str_contains($page, 'TOKEN'));
check('بدون وابستگی به CDN اینترنتی',
    !str_contains($page, 'cdn.') && !str_contains($page, 'googleapis'),
    'صفحه به اینترنت وابسته است');

// سازگاری با webOS/Tizen قدیمی — این‌ها روی موتور ES5 خطای نحوی می‌دهند
$es6 = [];
if (preg_match('/=>\s*[\{\(]/', $page))          $es6[] = 'arrow function';
if (preg_match('/\bconst\s+\w+\s*=/', $page))    $es6[] = 'const';
if (preg_match('/\blet\s+\w+\s*=/', $page))      $es6[] = 'let';
if (str_contains($page, '`'))                    $es6[] = 'template literal';
if (preg_match('/\bfetch\s*\(/', $page))         $es6[] = 'fetch()';
check('کد ES5 است (سازگار با webOS 3 و Tizen 2)', $es6 === [], 'یافت شد: ' . implode('، ', $es6));

// ── ۲) سه تلویزیون مختلف با یک آدرس ─────────────────────────────
echo "\n── ۲) سه تلویزیون مختلف، یک آدرس واحد ──\n";

$devices = [
    ['name' => 'LG webOS',      'mac' => 'AA:BB:CC:00:00:01', 'model' => '43UT570H',
     'ua' => 'Mozilla/5.0 (Web0S; Linux/SmartTV) AppleWebKit/537.36', 'expect' => 'webos'],
    ['name' => 'Samsung Tizen', 'mac' => 'AA:BB:CC:00:00:02', 'model' => 'HG43AU800',
     'ua' => 'Mozilla/5.0 (SMART-TV; Linux; Tizen 6.0) AppleWebKit/538.1', 'expect' => 'tizen'],
    ['name' => 'Android TV',    'mac' => 'AA:BB:CC:00:00:03', 'model' => 'MiBox4',
     'ua' => 'Mozilla/5.0 (Linux; Android 11; MiBOX4) AppleWebKit/537.36', 'expect' => 'android'],
];

$codes = [];
foreach ($devices as $d) {
    $r = http('POST', $BASE . '/api/v1/device/enroll', [
        'token' => $tokenAuto, 'mac' => $d['mac'], 'model' => $d['model'],
        'firmware' => '1.0', 'resolution' => '1920x1080',
    ], $d['ua']);

    $code = $r['body']['data']['code'] ?? null;
    $codes[$d['name']] = $code;

    check("{$d['name']} ثبت شد", $r['status'] === 201 && $code !== null,
        json_encode($r['body'], JSON_UNESCAPED_UNICODE));

    if ($code) {
        $row = $db->row('SELECT platform, model FROM screens WHERE code = ?', [$code]);
        check("  پلتفرم {$d['name']} از User-Agent تشخیص داده شد",
            ($row['platform'] ?? '') === $d['expect'],
            'تشخیص=' . ($row['platform'] ?? '?') . ' انتظار=' . $d['expect']);
    }
}

check('هر سه دستگاه کد یکتا گرفتند',
    count(array_unique(array_filter($codes))) === 3, json_encode($codes));
check('auto_approve دستگاه را فعال کرد',
    ($db->row('SELECT status FROM screens WHERE code=?', [$codes['LG webOS']])['status'] ?? '') === 'active');

// ── ۳) ثبت دوباره‌ی همان دستگاه ─────────────────────────────────
echo "\n── ۳) ریست کارخانه‌ی تلویزیون نباید رکورد تکراری بسازد ──\n";
$again = http('POST', $BASE . '/api/v1/device/enroll', [
    'token' => $tokenAuto, 'mac' => 'AA:BB:CC:00:00:01', 'model' => '43UT570H',
], $devices[0]['ua']);
check('همان کد قبلی برگشت',
    ($again['body']['data']['code'] ?? '') === $codes['LG webOS'],
    'کد=' . ($again['body']['data']['code'] ?? '?'));
check('رکورد تکراری ساخته نشد',
    (int)$db->value("SELECT COUNT(*) FROM screens WHERE mac_address='AA:BB:CC:00:00:01'") === 1);

// ── ۴) سقف توکن ─────────────────────────────────────────────────
echo "\n── ۴) سقف تعداد دستگاه ──\n";
$over = http('POST', $BASE . '/api/v1/device/enroll', [
    'token' => $tokenAuto, 'mac' => 'AA:BB:CC:00:00:99', 'model' => 'X',
]);
check('ثبت بیش از سقف رد شد (۴۰۹)', $over['status'] === 409, "status={$over['status']}");

// ── ۵) توکن نامعتبر ─────────────────────────────────────────────
echo "\n── ۵) توکن نامعتبر ──\n";
$bad = http('POST', $BASE . '/api/v1/device/enroll', ['token' => 'nope', 'mac' => 'AA:BB:CC:11:11:11']);
check('توکن اشتباه ۴۰۳ داد (دستگاه می‌فهمد تکرار بی‌فایده است)',
    $bad['status'] === 403, "status={$bad['status']}");

// ── ۶) تایید دستی ───────────────────────────────────────────────
echo "\n── ۶) حالت «منتظر تایید مدیر» ──\n";
$pend = http('POST', $BASE . '/api/v1/device/enroll', [
    'token' => $tokenManual, 'mac' => 'AA:BB:CC:22:22:22', 'model' => 'PendingTV',
]);
$pendCode = $pend['body']['data']['code'] ?? '';
check('دستگاه در حالت pending ثبت شد',
    ($pend['body']['data']['status'] ?? '') === 'pending', json_encode($pend['body'], JSON_UNESCAPED_UNICODE));

$cmds = http('GET', $BASE . '/api/v1/device/' . $pendCode . '/commands');
check('صفحه می‌فهمد هنوز تایید نشده', ($cmds['body']['data']['approved'] ?? true) === false);

$db->update('screens', ['status' => 'active'], ['code' => $pendCode]);
$cmds2 = http('GET', $BASE . '/api/v1/device/' . $pendCode . '/commands');
check('بعد از تایید، approved می‌شود', ($cmds2['body']['data']['approved'] ?? false) === true);

// ── ۷) صف فرمان و محدودیت پلتفرم ────────────────────────────────
echo "\n── ۷) فرمان زنده و محدودیت واقعی پلتفرم‌ها ──\n";
$svc    = new App\Services\DeviceService($db);
$lg     = $db->row('SELECT * FROM screens WHERE code=?', [$codes['LG webOS']]);
$tv     = $db->row('SELECT * FROM screens WHERE code=?', [$codes['Android TV']]);

$r1 = $svc->queue($lg, 'refresh');
check('refresh روی LG پذیرفته شد', $r1['ok'], $r1['message']);

// ریبوت از راه دور روی webOS کار سرور Pro:Centric است، نه صفحه‌ی HTML ما
$r2 = $svc->queue($lg, 'reboot');
check('reboot روی LG رد شد (محدودیت واقعی webOS)', !$r2['ok'], $r2['message']);

$r3 = $svc->queue($tv, 'reboot');
check('reboot روی Android TV پذیرفته شد', $r3['ok'], $r3['message']);

$r4 = $svc->queue($tv, 'volume', ['value' => 150]);
check('صدای خارج از بازه رد شد', !$r4['ok'], $r4['message']);

$r5 = $svc->queue($tv, 'volume', ['value' => 30]);
check('صدای معتبر پذیرفته شد', $r5['ok'], $r5['message']);

$dupe = $svc->queue($tv, 'volume', ['value' => 40]);
check('فرمان تکراری دوباره صف نشد', $dupe['id'] === $r5['id'], 'id=' . var_export($dupe['id'], true));

// ── ۸) دریافت و تایید فرمان توسط دستگاه ─────────────────────────
echo "\n── ۸) دستگاه فرمان را می‌گیرد و نتیجه را گزارش می‌دهد ──\n";
$pull = http('GET', $BASE . '/api/v1/device/' . $codes['Android TV'] . '/commands');
$list = $pull['body']['data']['commands'] ?? [];
check('دستگاه ۲ فرمان گرفت', count($list) === 2, 'تعداد=' . count($list));

$first = $list[0] ?? null;
check('فرمان شامل پارامتر است',
    $first !== null && isset($first['cmd']),
    json_encode($first, JSON_UNESCAPED_UNICODE));

$again2 = http('GET', $BASE . '/api/v1/device/' . $codes['Android TV'] . '/commands');
check('فرمان دوبار تحویل داده نمی‌شود',
    count($again2['body']['data']['commands'] ?? []) === 0,
    'تعداد=' . count($again2['body']['data']['commands'] ?? []));

if ($first) {
    $ack = http('POST', $BASE . '/api/v1/device/' . $codes['Android TV'] . '/ack',
        ['id' => $first['id'], 'ok' => true, 'result' => 'انجام شد']);
    check('گزارش اجرا ثبت شد', $ack['status'] === 200, json_encode($ack['body'], JSON_UNESCAPED_UNICODE));
    check('وضعیت فرمان done شد',
        ($db->row('SELECT status FROM screen_commands WHERE id=?', [(int)$first['id']])['status'] ?? '') === 'done');
}

// ── ۹) دارایی‌های محلی ──────────────────────────────────────────
echo "\n── ۹) دارایی‌های محلی (هتل بدون اینترنت) ──\n";
foreach ([
    '/assets/vendor/hls/hls.min.js'                     => 'hls.js (بدون آن پخش زنده کار نمی‌کند)',
    '/assets/vendor/fontawesome/css/all.min.css'        => 'Font Awesome CSS',
    '/assets/vendor/fontawesome/webfonts/fa-solid-900.woff2' => 'آیکون‌ها',
    '/assets/vendor/vazirmatn/vazirmatn.css'            => 'CSS فونت فارسی',
    '/assets/vendor/vazirmatn/vazirmatn-400.woff2'      => 'فونت فارسی',
] as $path => $label) {
    $res = http('GET', $BASE . $path);
    check("$label از سرور خودمان سرو می‌شود", $res['status'] === 200, "status={$res['status']} $path");
}

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";

// پاک‌سازی
$db->query("DELETE FROM screen_commands WHERE tenant_id = 1");
$db->query("DELETE FROM screen_events   WHERE tenant_id = 1");
$db->query("DELETE FROM screens WHERE mac_address LIKE 'AA:BB:CC:%'");
$db->query("DELETE FROM enrollment_tokens WHERE tenant_id = 1 AND label LIKE 'TEST%'");

exit($fail === 0 ? 0 : 1);
