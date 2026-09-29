<?php
/**
 * تست راهنمای برنامه‌ی تلویزیون اتاق — داده‌ای که /tv/guest/{code}/guide
 * می‌خواند (TODO ۱.۲، ۱.۳، ۱.۱۳): برنامه‌های یک روز یک کانال، وضعیت
 * گذشته/الان/آینده، اینکه پخش دوباره یا ضبط ممکن است یا نه، و اینکه
 * راهنما قفل والدین و سطح دسترسی را دور نمی‌زند.
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

function cleanup(App\Core\Database $db): void {
    foreach ($db->rows("SELECT id FROM iptv_channels WHERE tenant_id=1 AND name LIKE 'GDTEST%'") as $c) {
        $db->query('DELETE FROM epg_programs WHERE channel_id=?', [(int)$c['id']]);
    }
    $db->query("DELETE FROM iptv_channels WHERE tenant_id=1 AND name LIKE 'GDTEST%'");
    $db->query("DELETE FROM screens WHERE code='GDTEST'");
    $db->query("DELETE FROM iptv_rooms WHERE tenant_id=1 AND room_number='GD1'");
}

$db->query("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1,'Test Hotel','test')");
cleanup($db);

$room = (int)$db->insert('iptv_rooms', ['tenant_id' => 1, 'room_number' => 'GD1', 'status' => 'occupied',
    'access_level' => 1, 'check_in_at' => date('Y-m-d H:i:s', time() - 3600)]);
$db->insert('screens', ['tenant_id' => 1, 'name' => 'GD', 'code' => 'GDTEST', 'screen_type' => 'iptv',
    'status' => 'active', 'iptv_room_id' => $room]);
$tv = (int)$db->insert('iptv_channels', ['tenant_id' => 1, 'name' => 'GDTEST شبکه', 'stream_url' => 'http://a/1.m3u8',
    'protocol' => 'http', 'channel_no' => 1, 'is_active' => 1, 'tvh_uuid' => 'uuid-gd-1',
    'catchup_enabled' => 1, 'catchup_window_hours' => 24]);
$plain = (int)$db->insert('iptv_channels', ['tenant_id' => 1, 'name' => 'GDTEST ساده', 'stream_url' => 'http://a/2.m3u8',
    'protocol' => 'http', 'channel_no' => 2, 'is_active' => 1]);
$vip = (int)$db->insert('iptv_channels', ['tenant_id' => 1, 'name' => 'GDTEST ویژه', 'stream_url' => 'http://a/3.m3u8',
    'protocol' => 'http', 'channel_no' => 3, 'is_active' => 1, 'access_level' => 5]);
$adult = (int)$db->insert('iptv_channels', ['tenant_id' => 1, 'name' => 'GDTEST فیلم', 'stream_url' => 'http://a/4.m3u8',
    'protocol' => 'http', 'channel_no' => 4, 'is_active' => 1, 'is_adult' => 1]);

$at = fn(int $off) => date('Y-m-d H:i:s', time() + $off);
$prog = function (int $ch, string $title, int $s, int $e, ?string $ext) use ($db, $at) {
    $db->insert('epg_programs', ['tenant_id' => 1, 'channel_id' => $ch, 'channel_key' => 'gd-' . $ch,
        'external_id' => $ext, 'title' => $title, 'starts_at' => $at($s), 'ends_at' => $at($e)]);
};
$prog($tv, 'قدیمی', -30 * 3600, -29 * 3600, '101');     // بیرون از پنجره‌ی ۲۴ ساعته
$prog($tv, 'گذشته', -7200, -3600, '102');
$prog($tv, 'بی‌شناسه', -3600, -1800, 'xmltv-abc');       // شناسه‌ی غیر TVHeadend
$prog($tv, 'الان', -1800, 1800, '103');
$prog($tv, 'آینده', 1800, 5400, '104');
$prog($plain, 'گذشته‌ی ساده', -7200, -3600, '201');
$prog($plain, 'آینده‌ی ساده', 1800, 5400, '202');
$prog($adult, 'فیلم شب', 1800, 5400, '401');

(new App\Services\ChannelAccessService($db))->setPin(1, $room, '4826');

$byTitle = function (array $r): array {
    $o = [];
    foreach ($r['body']['data']['programs'] ?? [] as $p) $o[$p['title']] = $p;
    return $o;
};

// ── ۱) برنامه‌های امروز و وضعیت هر کدام ───────────────────────────
echo "\n── ۱) برنامه‌های یک روز ──\n";
$today = date('Y-m-d');
$r = call('EpgController', 'guestDay', ['code' => 'GDTEST', 'channel' => $tv], ['date' => $today]);
check('پاسخ ۲۰۰', $r['status'] === 200, json_encode($r['body'], JSON_UNESCAPED_UNICODE));
check('نام کانال برگشت', ($r['body']['data']['channel']['name'] ?? '') === 'GDTEST شبکه');
/* نزدیک نیمه‌شب برنامه‌ی دو ساعت پیش مال دیروز است؛ هر دو روز خوانده می‌شود */
$yd = date('Y-m-d', time() - 7200);
$p = $byTitle($r) + ($yd === $today ? [] : $byTitle(call('EpgController', 'guestDay', ['code' => 'GDTEST', 'channel' => $tv], ['date' => $yd])));
check('برنامه‌ی دیروز در روز امروز نیامد', !isset($p['قدیمی']) || date('Y-m-d', time() - 29 * 3600) === $today);
check('وضعیت‌ها درست است', ($p['گذشته']['state'] ?? '') === 'past' && ($p['الان']['state'] ?? '') === 'now'
      && ($p['آینده']['state'] ?? '') === 'future', json_encode(array_map(fn($x) => $x['state'], $p), JSON_UNESCAPED_UNICODE));
$ts = array_map(fn($x) => $x['starts_at'], array_values($byTitle($r)));
$sorted = $ts; sort($sorted);
check('به ترتیب زمان شروع', $ts === $sorted);

// ── ۲) پخش دوباره و ضبط ──────────────────────────────────────────
echo "\n── ۲) پخش دوباره و ضبط ──\n";
check('برنامه‌ی گذشته قابل پخش دوباره است', !empty($p['گذشته']['can_catchup']));
check('برنامه‌ی بی‌شناسه‌ی TVHeadend پخش دوباره ندارد', isset($p['بی‌شناسه']) && empty($p['بی‌شناسه']['can_catchup']));
check('برنامه‌ی در حال پخش نه پخش دوباره دارد نه ضبط', empty($p['الان']['can_catchup']) && empty($p['الان']['can_record']));
check('برنامه‌ی آینده قابل ضبط است', !empty($p['آینده']['can_record']) && empty($p['آینده']['can_catchup']));

$y = date('Y-m-d', time() - 30 * 3600);
$r = call('EpgController', 'guestDay', ['code' => 'GDTEST', 'channel' => $tv], ['date' => $y]);
$old = $byTitle($r)['قدیمی'] ?? null;
check('برنامه‌ی بیرون از پنجره‌ی نگهداری پخش دوباره ندارد', $old !== null && empty($old['can_catchup']), json_encode($old, JSON_UNESCAPED_UNICODE));

$r = call('EpgController', 'guestDay', ['code' => 'GDTEST', 'channel' => $plain], ['date' => $today]);
$q = $byTitle($r) + ($yd === $today ? [] : $byTitle(call('EpgController', 'guestDay', ['code' => 'GDTEST', 'channel' => $plain], ['date' => $yd])));
check('کانال بدون TVHeadend: نه پخش دوباره نه ضبط',
      isset($q['گذشته‌ی ساده'], $q['آینده‌ی ساده']) && empty($q['گذشته‌ی ساده']['can_catchup']) && empty($q['آینده‌ی ساده']['can_record']));

// ── ۳) دسترسی و قفل ─────────────────────────────────────────────
echo "\n── ۳) دسترسی و قفل والدین ──\n";
$r = call('EpgController', 'guestDay', ['code' => 'GDTEST', 'channel' => $vip], ['date' => $today]);
check('کانال بالاتر از سطح اتاق دیده نمی‌شود', $r['status'] === 404);
$r = call('EpgController', 'guestDay', ['code' => 'GDTEST', 'channel' => $adult], ['date' => $today]);
check('کانال قفل بدون توکن ۴۰۳ می‌دهد', $r['status'] === 403);
$r = call('EpgController', 'guestDay', ['code' => 'GDTEST', 'channel' => $adult], ['date' => $today, 't' => 'forged.token']);
check('توکن جعلی هم ۴۰۳', $r['status'] === 403);
$tok = (string)(call('ContentController', 'unlock', ['code' => 'GDTEST'], [], ['pin' => '4826'])['body']['data']['token'] ?? '');
$r = call('EpgController', 'guestDay', ['code' => 'GDTEST', 'channel' => $adult], ['date' => date('Y-m-d', time() + 1800), 't' => $tok]);
check('با توکن درست برنامه‌ها آمد', $r['status'] === 200 && isset($byTitle($r)['فیلم شب']));

// ── ۴) ورودی نامعتبر ─────────────────────────────────────────────
echo "\n── ۴) ورودی نامعتبر ──\n";
$r = call('EpgController', 'guestDay', ['code' => 'GDTEST', 'channel' => $tv], ['date' => "2026-01-01' OR 1=1"]);
check('تاریخ نامعتبر ۴۲۲', $r['status'] === 422);
$r = call('EpgController', 'guestDay', ['code' => 'NOPE-XX', 'channel' => $tv], ['date' => $today]);
check('صفحه‌نمایش ناشناخته ۴۰۴', $r['status'] === 404);

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";
cleanup($db);
exit($fail === 0 ? 0 : 1);
