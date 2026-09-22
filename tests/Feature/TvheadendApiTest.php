<?php
/**
 * تست سطح کامل API تی‌وی‌هدند.
 *
 * یک سرور ساختگی روی لوکال بالا می‌آید و دقیقا همان مسیرها و
 * پارامترهایی را که تی‌وی‌هدند واقعی انتظار دارد بررسی می‌کند.
 *
 * چرا این مهم است: اگر مسیر یا نام پارامتر یکی غلط باشد، تی‌وی‌هدند
 * خطای ۴۰۴ می‌دهد و پخش multicast شروع نمی‌شود — ولی از بیرون شبیه
 * «مشکل شبکه» به نظر می‌رسد و ساعت‌ها دنبال سوییچ می‌گردید.
 *
 * مسیرها از خود سورس تی‌وی‌هدند گرفته شده‌اند (src/webui/webui.c و
 * src/api/*.c)، نه از حافظه.
 */
define('ROOT_PATH',    dirname(__DIR__, 2));
define('APP_PATH',     ROOT_PATH . '/app');
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');

error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);

spl_autoload_register(function (string $c): void {
    $p = APP_PATH . '/' . str_replace(['App\\', '\\'], ['', '/'], $c) . '.php';
    if (file_exists($p)) require $p;
});

$pass = 0;
$fail = 0;

function check(string $name, bool $ok, string $extra = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  \033[32m✅\033[0m $name\n"; return; }
    $fail++;
    echo "  \033[31m❌\033[0m $name\n";
    if ($extra !== '') echo "       → " . $extra . "\n";
}

// ══════════════════════════════════════════════════════════════════
//  سرور ساختگی تی‌وی‌هدند
// ══════════════════════════════════════════════════════════════════

$port  = 19087;
$docRoot = sys_get_temp_dir() . '/tvhfake';
@mkdir($docRoot, 0775, true);

/* روتر ساختگی: هر درخواست را در فایل لاگ می‌نویسد و پاسخ مناسب
   می‌دهد، تا بتوانیم دقیقا ببینیم چه مسیری صدا شده. */
file_put_contents($docRoot . '/router.php', <<<'PHP'
<?php
$log = sys_get_temp_dir() . '/tvhfake/requests.log';
$uri = $_SERVER['REQUEST_URI'] ?? '';
file_put_contents($log, $uri . "\n", FILE_APPEND);

header('Content-Type: application/json');

if (str_starts_with($uri, '/udpstream/start') || str_starts_with($uri, '/udpstream/stop')) {
    echo '{"success":1}'; return true;
}
if (str_starts_with($uri, '/api/dvr/entry/create_by_event')) {
    echo '{"uuid":"evt-rec-1"}'; return true;
}
if (str_starts_with($uri, '/api/dvr/entry/create')) {
    echo '{"uuid":"rec-abc-123"}'; return true;
}
if (str_starts_with($uri, '/api/dvr/entry/grid_')) {
    echo '{"entries":[{"uuid":"r1","disp_title":"برنامه یک"}],"total":1}'; return true;
}
if (str_starts_with($uri, '/api/dvr/entry/remove')
 || str_starts_with($uri, '/api/dvr/entry/stop')) {
    echo '{"success":1}'; return true;
}
if (str_starts_with($uri, '/api/timeshift/config/load')) {
    echo '{"entries":[{"params":[{"id":"enabled","value":true},{"id":"max_size","value":10240}]}]}';
    return true;
}
if (str_starts_with($uri, '/api/channeltag/grid')) {
    echo '{"entries":[{"uuid":"t1","name":"خبری"},{"uuid":"t2","name":"ورزشی"}],"total":2}';
    return true;
}
if (str_starts_with($uri, '/api/status/inputs')) {
    echo '{"entries":[{"uuid":"in1","input":"DVB-S2 #0","weight":10}]}'; return true;
}
if (str_starts_with($uri, '/api/status/subscriptions')) {
    echo '{"entries":[{"id":1,"channel":"شبکه یک","state":"Running"}]}'; return true;
}
if (str_starts_with($uri, '/api/notfound')) {
    http_response_code(404); echo '{}'; return true;
}
http_response_code(404);
echo '{}';
return true;
PHP);

@unlink($docRoot . '/requests.log');

$php = PHP_BINARY ?: 'php';
$cmd = sprintf('%s -S 127.0.0.1:%d -t %s %s',
    escapeshellarg($php), $port, escapeshellarg($docRoot),
    escapeshellarg($docRoot . '/router.php'));

$proc = @proc_open($cmd, [1 => ['file', $docRoot . '/out.log', 'a'],
                          2 => ['file', $docRoot . '/out.log', 'a']], $pipes);

/* صبر تا سرور بالا بیاید */
$up = false;
for ($i = 0; $i < 40; $i++) {
    $s = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2);
    if ($s) { fclose($s); $up = true; break; }
    usleep(150000);
}

if (!$up) {
    echo "\033[33m⏭  سرور ساختگی بالا نیامد — این تست رد شد.\033[0m\n";
    if (is_resource($proc)) proc_terminate($proc);
    exit(0);
}

$src = ['url' => 'http://127.0.0.1:' . $port, 'username' => '', 'password' => ''];
$svc = (new ReflectionClass(App\Services\TvheadendApiService::class))->newInstanceWithoutConstructor();

/** آخرین مسیری که سرور ساختگی دریافت کرده */
function lastReq(): string
{
    $log = sys_get_temp_dir() . '/tvhfake/requests.log';
    $l = @file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    return end($l) ?: '';
}

// ══════════════════════════════════════════════════════════════════

echo "\n\033[36m── پخش multicast ──\033[0m\n";

$r = $svc->startMulticast($src, 'ch-uuid-1', '239.1.1.5', 5000);
check('شروع پخش موفق بود', $r['ok'], $r['message']);
check('آدرس udp درست ساخته شد', $r['url'] === 'udp://@239.1.1.5:5000', $r['url']);
$req = lastReq();
check('مسیر udpstream/start/channel درست است',
    str_contains($req, '/udpstream/start/channel/ch-uuid-1'), $req);
check('پارامتر address فرستاده شد', str_contains($req, 'address=239.1.1.5'), $req);
check('پارامتر port فرستاده شد',    str_contains($req, 'port=5000'), $req);

$r = $svc->stopMulticast($src, 'ch-uuid-1', '239.1.1.5', 5000);
check('توقف پخش موفق بود', $r['ok'], $r['message']);
check('مسیر udpstream/stop درست است',
    str_contains(lastReq(), '/udpstream/stop/channel/ch-uuid-1'), lastReq());

echo "\n\033[36m── بررسی آدرس گروه ──\033[0m\n";

/* هر کدام از این‌ها اگر رد نشود، شبکه‌ی هتل آسیب می‌بیند */
$bad = [
    ['نه‌آی‌پی',        'not-an-ip',  5000],
    ['خارج محدوده',     '192.168.1.5', 5000],
    ['محدوده رزرو',     '224.0.0.1',  5000],
    ['پورت فرد',        '239.1.1.5',  5001],
    ['پورت خیلی کم',    '239.1.1.5',  80],
];
foreach ($bad as [$why, $g, $p]) {
    $r = $svc->startMulticast($src, 'x', $g, $p);
    check('رد شد: ' . $why, !$r['ok'], $r['message']);
}

echo "\n\033[36m── ضبط ──\033[0m\n";

$now = time();
$r = $svc->record($src, 'ch-uuid-1', $now, $now + 3600, 'فیلم شب');
check('ثبت ضبط موفق بود', $r['ok'], $r['message']);
check('شناسه ضبط برگشت', $r['uuid'] === 'rec-abc-123', $r['uuid']);
check('مسیر dvr/entry/create درست است',
    str_contains(lastReq(), '/api/dvr/entry/create?conf='), lastReq());

/* تنظیمات باید JSON معتبر و با عنوان فارسی سالم باشد */
$req  = lastReq();
$conf = urldecode(substr($req, strpos($req, 'conf=') + 5));
$json = json_decode($conf, true);
check('تنظیمات JSON معتبر است', is_array($json), substr($conf, 0, 120));
check('عنوان فارسی سالم ماند',
    ($json['title']['fa'] ?? '') === 'فیلم شب', json_encode($json['title'] ?? null, JSON_UNESCAPED_UNICODE));
check('زمان شروع و پایان درست است',
    ($json['start'] ?? 0) === $now && ($json['stop'] ?? 0) === $now + 3600);

$r = $svc->record($src, 'ch', $now + 100, $now, 'بازه وارونه');
check('بازه‌ی وارونه رد شد', !$r['ok'], $r['message']);

$r = $svc->recordEvent($src, 4242);
check('ضبط از روی EPG موفق بود', $r['ok'] && $r['uuid'] === 'evt-rec-1', $r['message']);
check('مسیر create_by_event درست است',
    str_contains(lastReq(), '/api/dvr/entry/create_by_event?event_id=4242'), lastReq());

$r = $svc->recordings($src, 'finished');
check('فهرست ضبط‌ها گرفته شد', $r['ok'] && count($r['items']) === 1, $r['message']);
check('مسیر grid_finished درست است',
    str_contains(lastReq(), '/api/dvr/entry/grid_finished'), lastReq());

$svc->recordings($src, 'چیز-نامعتبر');
check('فهرست نامعتبر به finished برگشت',
    str_contains(lastReq(), 'grid_finished'), lastReq());

$r = $svc->stopRecording($src, 'r1');
check('توقف ضبط موفق بود', $r['ok'], $r['message']);
$r = $svc->deleteRecording($src, 'r1');
check('حذف ضبط موفق بود', $r['ok'], $r['message']);

check('آدرس فایل ضبط درست است',
    $svc->recordingUrl($src, 'r1') === $src['url'] . '/dvrfile/r1',
    $svc->recordingUrl($src, 'r1'));

echo "\n\033[36m── تایم‌شیفت ──\033[0m\n";

$r = $svc->timeshiftStatus($src);
check('وضعیت تایم‌شیفت گرفته شد', $r['ok'], $r['message']);
check('فعال بودن درست خوانده شد', $r['enabled'] === true);

echo "\n\033[36m── تگ کانال ──\033[0m\n";

$r = $svc->channelTags($src);
check('تگ‌ها گرفته شد', $r['ok'] && count($r['tags']) === 2, $r['message']);

echo "\n\033[36m── وضعیت زنده ──\033[0m\n";

$r = $svc->liveStatus($src);
check('وضعیت تیونر و اشتراک گرفته شد',
    $r['ok'] && count($r['inputs']) === 1 && count($r['subscriptions']) === 1, $r['message']);

echo "\n\033[36m── نسخه‌ی قدیمی تی‌وی‌هدند ──\033[0m\n";

/* udpstream از اکتبر ۲۰۲۲ اضافه شده؛ روی نسخه‌ی قدیمی‌تر ۴۰۴ می‌دهد
   و پیام باید همین را بگوید، نه «یافت نشد» که اپراتور را گمراه کند. */
$src404 = ['url' => 'http://127.0.0.1:' . $port . '/api/notfound', 'username' => '', 'password' => ''];
$r = $svc->channelTags($src404);
check('۴۰۴ به پیام «به‌روزرسانی کنید» تبدیل شد',
    !$r['ok'] && str_contains($r['message'], 'به‌روزرسانی'), $r['message']);

echo "\n\033[36m── سرور در دسترس نیست ──\033[0m\n";

$dead = ['url' => 'http://127.0.0.1:1', 'username' => '', 'password' => ''];
$r = $svc->channelTags($dead);
check('قطعی ارتباط پیام فارسی داد',
    !$r['ok'] && str_contains($r['message'], 'اتصال'), $r['message']);

// ══════════════════════════════════════════════════════════════════

if (is_resource($proc)) proc_terminate($proc);

echo "\n" . str_repeat('─', 58) . "\n";
if ($fail === 0) {
    echo "\033[32m✅ هر $pass تست پاس شد\033[0m\n";
    exit(0);
}
echo "\033[31m❌ $fail شکست از " . ($pass + $fail) . " تست\033[0m\n";
exit(1);
