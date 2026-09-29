<?php
/**
 * تست تلویزیون زنده‌ی اتاق — داده‌ای که صفحه‌ی /tv/guest/{code}/live می‌خواند:
 * فهرست کانال با منبع پشتیبان (TODO ۵.۲۰)، قفل والدین با توکن در query،
 * و «الان/بعدی» EPG که تا این تغییر همیشه خطای ۵۰۰ می‌داد.
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

$PHP    = getenv('TEST_PHP') ?: PHP_BINARY;
$INI    = getenv('TEST_INI') ?: (php_ini_loaded_file() ?: '');
$WORKER = ROOT_PATH . '/tests/Support/controller-worker.php';

function call(string $ctrl, string $method, array $params, array $query = [], array $body = []): array {
    global $PHP, $INI, $WORKER;
    $cmd = escapeshellarg($PHP) . ($INI ? ' -c ' . escapeshellarg($INI) : '') . ' ' . escapeshellarg($WORKER)
         . ' ' . escapeshellarg($ctrl) . ' ' . escapeshellarg($method)
         . ' ' . base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE))
         . ' ' . base64_encode(json_encode($body, JSON_UNESCAPED_UNICODE))
         . ' ' . base64_encode(json_encode($query, JSON_UNESCAPED_UNICODE)) . ' 2>&1';
    $out = shell_exec($cmd) ?? '';
    if (preg_match('/##RESULT##(.*?)##END##/s', $out, $m)) return json_decode($m[1], true) ?? ['status' => 0, 'body' => []];
    return ['status' => 0, 'body' => ['raw' => substr($out, 0, 300)]];
}

$db = App\Core\Database::getInstance();
$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✅ $label\n"; }
    else     { $fail++; echo "  ❌ $label" . ($detail ? "\n       → $detail" : '') . "\n"; }
}

/* «?? 'x'» اینجا به کار نمی‌آید: null را هم نبودن حساب می‌کند */
function isNull(array $a, string $k): bool { return array_key_exists($k, $a) && $a[$k] === null; }

function cleanup(App\Core\Database $db): void {
    foreach ($db->rows("SELECT id FROM iptv_channels WHERE tenant_id=1 AND name LIKE 'LTTEST%'") as $c) {
        $db->query('DELETE FROM epg_programs WHERE channel_id=?', [(int)$c['id']]);
    }
    $db->query("DELETE FROM iptv_channels WHERE tenant_id=1 AND name LIKE 'LTTEST%'");
    $db->query("DELETE FROM screens WHERE code='LTTEST'");
    $db->query("DELETE FROM iptv_rooms WHERE tenant_id=1 AND room_number='LT1'");
}

$db->query("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1,'Test Hotel','test')");
cleanup($db);

$room = (int)$db->insert('iptv_rooms', ['tenant_id' => 1, 'room_number' => 'LT1', 'status' => 'occupied',
    'check_in_at' => date('Y-m-d H:i:s', time() - 3600)]);
$db->insert('screens', ['tenant_id' => 1, 'name' => 'LT', 'code' => 'LTTEST', 'screen_type' => 'iptv',
    'status' => 'active', 'iptv_room_id' => $room]);
$news = (int)$db->insert('iptv_channels', ['tenant_id' => 1, 'name' => 'LTTEST خبر', 'stream_url' => 'http://a/1.m3u8',
    'backup_stream_url' => 'http://b/1.m3u8', 'protocol' => 'http', 'channel_no' => 1, 'is_active' => 1]);
$adult = (int)$db->insert('iptv_channels', ['tenant_id' => 1, 'name' => 'LTTEST فیلم', 'stream_url' => 'http://a/2.m3u8',
    'backup_stream_url' => 'http://b/2.m3u8', 'multicast_url' => 'udp://239.1.1.2:1234', 'protocol' => 'http',
    'channel_no' => 2, 'is_adult' => 1, 'is_active' => 1]);
$db->insert('epg_programs', ['tenant_id' => 1, 'channel_id' => $news, 'channel_key' => 'lt-news', 'title' => 'اخبار ساعت',
    'starts_at' => date('Y-m-d H:i:s', time() - 600), 'ends_at' => date('Y-m-d H:i:s', time() + 600)]);
$db->insert('epg_programs', ['tenant_id' => 1, 'channel_id' => $news, 'channel_key' => 'lt-news', 'title' => 'مستند',
    'starts_at' => date('Y-m-d H:i:s', time() + 600), 'ends_at' => date('Y-m-d H:i:s', time() + 3000)]);

(new App\Services\ChannelAccessService($db))->setPin(1, $room, '4826');

// ── ۱) EPG الان/بعدی ─────────────────────────────────────────────
echo "\n── ۱) EPG الان/بعدی ──\n";
$r = call('EpgController', 'playerNow', ['code' => 'LTTEST']);
check('پاسخ ۲۰۰ است (پیش از این همیشه ۵۰۰ بود)', $r['status'] === 200, json_encode($r['body'], JSON_UNESCAPED_UNICODE));
$row = array_values(array_filter($r['body']['data'] ?? [], fn($x) => (int)$x['channel_id'] === $news))[0] ?? [];
check('برنامه‌ی الان آمد', ($row['now']['title'] ?? '') === 'اخبار ساعت');
check('برنامه‌ی بعدی آمد', ($row['next']['title'] ?? '') === 'مستند');
check('درصد پیشرفت حساب شد', isset($row['progress']) && $row['progress'] >= 40 && $row['progress'] <= 60, (string)($row['progress'] ?? 'null'));

// ── ۲) فهرست کانال و منبع پشتیبان ────────────────────────────────
echo "\n── ۲) کانال‌ها و افزونگی ──\n";
$r = call('ContentController', 'guestChannels', ['code' => 'LTTEST']);
$ch = [];
foreach ($r['body']['data']['channels'] ?? [] as $c) $ch[(int)$c['id']] = $c;
check('منبع پشتیبان برای صفحه‌ی زنده فرستاده شد', ($ch[$news]['backup_stream_url'] ?? '') === 'http://b/1.m3u8');
check('کانال بزرگسال قفل است', !empty($ch[$adult]['locked']));
check('آدرس اصلی، multicast و پشتیبانِ کانال قفل پنهان است',
      isNull($ch[$adult] ?? [], 'stream_url') && isNull($ch[$adult] ?? [], 'multicast_url')
      && isNull($ch[$adult] ?? [], 'backup_stream_url'), json_encode($ch[$adult] ?? [], JSON_UNESCAPED_UNICODE));

// ── ۳) باز کردن قفل با توکن در query ─────────────────────────────
echo "\n── ۳) قفل والدین ──\n";
$bad = call('ContentController', 'unlock', ['code' => 'LTTEST'], [], ['pin' => '1111']);
check('رمز اشتباه رد شد', empty($bad['body']['data']['token']));
$ok = call('ContentController', 'unlock', ['code' => 'LTTEST'], [], ['pin' => '4826']);
$token = (string)($ok['body']['data']['token'] ?? '');
check('رمز درست توکن داد', $token !== '');

$r = call('ContentController', 'guestChannels', ['code' => 'LTTEST'], ['t' => $token]);
$u = array_values(array_filter($r['body']['data']['channels'] ?? [], fn($c) => (int)$c['id'] === $adult))[0] ?? [];
check('با توکن در query کانال باز شد', empty($u['locked']) && ($u['stream_url'] ?? '') === 'http://a/2.m3u8'
      && ($u['backup_stream_url'] ?? '') === 'http://b/2.m3u8', json_encode($u, JSON_UNESCAPED_UNICODE));
$r = call('ContentController', 'guestChannels', ['code' => 'LTTEST'], ['t' => 'forged.token']);
$f = array_values(array_filter($r['body']['data']['channels'] ?? [], fn($c) => (int)$c['id'] === $adult))[0] ?? [];
check('توکن جعلی قفل را باز نمی‌کند', !empty($f['locked']) && isNull($f, 'stream_url'));

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";
cleanup($db);
exit($fail === 0 ? 0 : 1);
