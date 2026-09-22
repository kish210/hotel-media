<?php
/**
 * تست multicast: تخصیص آدرس گروه، فهرست کانال M3U، تبدیل udpxy،
 * و اینکه پورتال به هر پلتفرم آدرسی بدهد که واقعا می‌تواند پخش کند.
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

$PHP    = getenv('TEST_PHP') ?: PHP_BINARY;
$INI    = getenv('TEST_INI') ?: (php_ini_loaded_file() ?: '');
$WORKER = ROOT_PATH . '/tests/Support/controller-worker.php';

function portal(string $method, array $params, array $query = []): array {
    global $PHP, $INI, $WORKER;
    $cmd = escapeshellarg($PHP)
         . ($INI ? ' -c ' . escapeshellarg($INI) : '')
         . ' ' . escapeshellarg($WORKER)
         . ' PortalController ' . escapeshellarg($method)
         . ' ' . base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE))
         . ' ' . base64_encode(json_encode([], JSON_UNESCAPED_UNICODE))
         . ' ' . base64_encode(json_encode($query, JSON_UNESCAPED_UNICODE))
         . ' 2>&1';

    $out = shell_exec($cmd) ?? '';
    if (preg_match('/##RESULT##(.*?)##END##/s', $out, $m)) {
        return json_decode($m[1], true) ?? ['status' => 0, 'body' => []];
    }
    return ['status' => 0, 'body' => ['raw' => substr($out, 0, 300)]];
}

$db = App\Core\Database::getInstance();

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✅ $label\n"; }
    else     { $fail++; echo "  ❌ $label" . ($detail ? "\n       → $detail" : '') . "\n"; }
}

// ── آماده‌سازی ───────────────────────────────────────────────────
$db->query("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1,'Test Hotel','test')");
$db->query('DELETE FROM multicast_config WHERE tenant_id = 1');
$db->query("DELETE FROM iptv_channels WHERE tenant_id = 1 AND name LIKE 'MCTEST%'");
$db->query("DELETE FROM screens WHERE code IN ('MCAND01','MCLG01')");

$chIds = [];
foreach ([['MCTEST شبکه یک', 1], ['MCTEST شبکه دو', 2], ['MCTEST شبکه سه', 3]] as [$name, $no]) {
    $chIds[] = (int)$db->insert('iptv_channels', [
        'tenant_id' => 1, 'name' => $name, 'stream_url' => 'http://tvh.local/stream/' . $no,
        'channel_no' => $no, 'sort_order' => $no, 'is_active' => 1,
    ]);
}

$svc = new App\Services\MulticastService($db);

// ── ۱) تنظیمات ───────────────────────────────────────────────────
echo "\n── ۱) تنظیمات multicast ──\n";
$bad = $svc->saveConfig(1, ['base_group' => '10.0.0.1']);
check('آدرس غیر-multicast رد شد', !$bad['ok'], $bad['message']);

$bad2 = $svc->saveConfig(1, ['base_group' => '224.0.0.5']);
check('بازه‌ی غیرمحلی (224.x) رد شد', !$bad2['ok'], $bad2['message']);

$bad3 = $svc->saveConfig(1, ['base_group' => '239.1.1.1', 'udpxy_url' => 'ftp://x']);
check('آدرس udpxy نامعتبر رد شد', !$bad3['ok'], $bad3['message']);

$ok = $svc->saveConfig(1, [
    'base_group' => '239.1.1.10', 'base_port' => 5000, 'port_step' => 2,
    'udpxy_url'  => 'http://10.0.0.5:4022', 'is_active' => 1,
]);
check('تنظیمات معتبر ذخیره شد', $ok['ok'], $ok['message']);

$cfg = $svc->config(1);
check('آدرس پایه ذخیره شد', $cfg['base_group'] === '239.1.1.10', $cfg['base_group']);

// ── ۲) تخصیص آدرس ───────────────────────────────────────────────
echo "\n── ۲) تخصیص آدرس گروه به کانال‌ها ──\n";
$a = $svc->assignAddresses(1);
check('آدرس‌ها تخصیص یافت', $a['ok'] && $a['assigned'] === 3, json_encode($a, JSON_UNESCAPED_UNICODE));

$urls = array_column(
    $db->rows("SELECT multicast_url FROM iptv_channels WHERE tenant_id=1 AND name LIKE 'MCTEST%' ORDER BY channel_no"),
    'multicast_url'
);
check('آدرس اول درست است', $urls[0] === 'udp://@239.1.1.10:5000', 'url=' . ($urls[0] ?? '?'));
check('پورت با گام ۲ جلو رفت (RTP زوج می‌خواهد)',
    $urls[1] === 'udp://@239.1.1.11:5002', 'url=' . ($urls[1] ?? '?'));
check('هیچ آدرس تکراری نیست', count(array_unique($urls)) === 3);
check('delivery روی multicast تنظیم شد',
    (int)$db->value("SELECT COUNT(*) FROM iptv_channels WHERE tenant_id=1 AND name LIKE 'MCTEST%' AND delivery='multicast'") === 3);

// اجرای دوباره نباید فهرست کانال تلویزیون‌ها را به‌هم بریزد
$a2 = $svc->assignAddresses(1);
check('اجرای دوباره آدرس‌ها را عوض نکرد', $a2['assigned'] === 0, json_encode($a2, JSON_UNESCAPED_UNICODE));

$urls2 = array_column(
    $db->rows("SELECT multicast_url FROM iptv_channels WHERE tenant_id=1 AND name LIKE 'MCTEST%' ORDER BY channel_no"),
    'multicast_url'
);
check('آدرس‌ها پایدار ماندند', $urls === $urls2);

// ── ۳) فهرست کانال M3U ──────────────────────────────────────────
echo "\n── ۳) فهرست کانال برای تلویزیون ──\n";
$m3u = $svc->buildM3u(1, 'multicast');
check('با #EXTM3U شروع می‌شود', str_starts_with($m3u, '#EXTM3U'));
check('آدرس multicast در فهرست هست', str_contains($m3u, 'udp://@239.1.1.10:5000'));
check('شماره کانال برای ریموت هست', str_contains($m3u, 'tvg-chno="1"'));
check('هر سه کانال آمدند', substr_count($m3u, '#EXTINF') === 3, 'تعداد=' . substr_count($m3u, '#EXTINF'));

$m3uUdpxy = $svc->buildM3u(1, 'udpxy');
check('حالت udpxy آدرس HTTP می‌دهد',
    str_contains($m3uUdpxy, 'http://10.0.0.5:4022/udp/239.1.1.10:5000'),
    substr($m3uUdpxy, 0, 200));

$m3uUni = $svc->buildM3u(1, 'unicast');
check('حالت unicast آدرس اصلی را می‌دهد', str_contains($m3uUni, 'http://tvh.local/stream/1'));

// ── ۴) محاسبه بار شبکه ──────────────────────────────────────────
echo "\n── ۴) محاسبه بار شبکه ──\n";
$bw = $svc->bandwidthEstimate(1, 300, 'hd');
check('اوج unicast برای ۳۰۰ اتاق محاسبه شد',
    $bw['unicast_peak_mbps'] === 2400.0, 'mbps=' . $bw['unicast_peak_mbps']);
check('اوج multicast مستقل از تعداد اتاق است',
    $bw['multicast_peak_mbps'] === 24.0, 'mbps=' . $bw['multicast_peak_mbps']);
check('صرفه‌جویی ۹۹ درصد است', $bw['saving_percent'] === 99, 'percent=' . $bw['saving_percent']);

// ── ۵) پورتال — آدرس مناسب هر پلتفرم ────────────────────────────
echo "\n── ۵) پورتال به هر پلتفرم آدرس قابل‌پخش می‌دهد ──\n";
$roomless = null;
$db->insert('screens', ['tenant_id'=>1,'name'=>'TV اندروید','code'=>'MCAND01','platform'=>'android','status'=>'active']);
$db->insert('screens', ['tenant_id'=>1,'name'=>'TV ال‌جی','code'=>'MCLG01','platform'=>'webos','status'=>'active']);

$and = portal('home', ['code' => 'MCAND01']);
$chA = $and['body']['data']['channels'] ?? [];
$first = null;
foreach ($chA as $c) { if (str_starts_with((string)$c['name'], 'MCTEST')) { $first = $c; break; } }
check('پاسخ پورتال اندروید گرفته شد', $first !== null, json_encode($and, JSON_UNESCAPED_UNICODE));
if ($first) {
    check('Android TV آدرس multicast مستقیم گرفت',
        $first['via'] === 'multicast' && str_starts_with((string)$first['url'], 'udp://@'),
        json_encode($first, JSON_UNESCAPED_UNICODE));
    check('کانال برای اندروید قابل پخش است', $first['playable'] === true);
}

/* تلویزیون هتلی LG (webOS) در تست میدانی udp:// را با پخش‌کننده‌ی
   بومی خودش باز کرد، پس پیش‌فرض باید آدرس مستقیم باشد نه رله.
   اگر اینجا به udpxy برگردد یعنی سرور در هتل ۳۰۰ اتاقی ۳۰۰ جریان
   unicast می‌سازد — همان باری که multicast برای حذفش هست. */
$lg = portal('home', ['code' => 'MCLG01']);
$chL = $lg['body']['data']['channels'] ?? [];
$firstL = null;
foreach ($chL as $c) { if (str_starts_with((string)$c['name'], 'MCTEST')) { $firstL = $c; break; } }
check('پاسخ پورتال ال‌جی گرفته شد', $firstL !== null);
if ($firstL) {
    check('webOS آدرس multicast مستقیم گرفت (بار صفر روی سرور)',
        $firstL['via'] === 'multicast' && str_starts_with((string)$firstL['url'], 'udp://@'),
        json_encode($firstL, JSON_UNESCAPED_UNICODE));
    check('کانال روی webOS قابل پخش است', $firstL['playable'] === true);
}

/* هتلی که تلویزیون قدیمی‌تر دارد webos را از فهرست برمی‌دارد؛ آن
   دستگاه‌ها باید به udpxy برگردند. */
$db->update('multicast_config', ['native_platforms' => 'android,windows'], ['tenant_id' => 1]);
$lg2 = portal('home', ['code' => 'MCLG01']);
$firstL2 = null;
foreach (($lg2['body']['data']['channels'] ?? []) as $c) {
    if (str_starts_with((string)$c['name'], 'MCTEST')) { $firstL2 = $c; break; }
}
if ($firstL2) {
    check('با برداشتن webos از فهرست، به udpxy برگشت',
        $firstL2['via'] === 'udpxy' && str_starts_with((string)$firstL2['url'], 'http://'),
        json_encode($firstL2, JSON_UNESCAPED_UNICODE));
}

/* نه بومی، نه udpxy — باید صریحا غیرقابل‌پخش علامت بخورد، نه اینکه
   پورتال یک آدرس مرده به تلویزیون بدهد. */
$db->update('multicast_config', ['udpxy_url' => null], ['tenant_id' => 1]);
$lg3 = portal('home', ['code' => 'MCLG01']);
$firstL3 = null;
foreach (($lg3['body']['data']['channels'] ?? []) as $c) {
    if (str_starts_with((string)$c['name'], 'MCTEST')) { $firstL3 = $c; break; }
}
if ($firstL3) {
    check('بدون بومی و بدون udpxy، صریحا غیرقابل‌پخش علامت خورد',
        $firstL3['playable'] === false && $firstL3['via'] === 'tv_tuner',
        json_encode($firstL3, JSON_UNESCAPED_UNICODE));
}

// فهرست را برای تست‌های بعدی به پیش‌فرض برگردان
$db->update('multicast_config',
    ['native_platforms' => 'android,windows,webos,tizen', 'udpxy_url' => 'http://10.0.0.5:4022'],
    ['tenant_id' => 1]);

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";

// پاک‌سازی
$db->query('DELETE FROM multicast_config WHERE tenant_id = 1');
$db->query("DELETE FROM iptv_channels WHERE tenant_id = 1 AND name LIKE 'MCTEST%'");
$db->query("DELETE FROM screens WHERE code IN ('MCAND01','MCLG01')");

exit($fail === 0 ? 0 : 1);
