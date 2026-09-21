<?php
/**
 * تست صورتحساب اتاق: مینی‌بار، محتوای پولی، خروج سریع و ارسال به PMS.
 * یک PMS ساختگی واقعی بالا می‌آید تا ارسال HTTP واقعا آزمایش شود.
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

// ── PMS ساختگی ───────────────────────────────────────────────────
// یک سرور واقعی روی پورت محلی که اقلام دریافتی را در فایل می‌نویسد،
// تا ارسال HTTP واقعا آزمایش شود نه شبیه‌سازی.
$pmsPort = 19099;
$pmsDir  = sys_get_temp_dir() . '/hm-pms-test';
@mkdir($pmsDir, 0777, true);
$pmsLog  = $pmsDir . '/received.jsonl';
@unlink($pmsLog);

file_put_contents($pmsDir . '/index.php', <<<'PHPSRC'
<?php
$log  = __DIR__ . '/received.jsonl';
$mode = @file_get_contents(__DIR__ . '/mode.txt') ?: 'ok';
$body = file_get_contents('php://input');

file_put_contents($log, $body . "\n", FILE_APPEND);

if ($mode === 'fail') { http_response_code(500); echo '{"error":"PMS down"}'; exit; }
if ($mode === 'auth') {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($h !== 'Bearer SECRET123') { http_response_code(401); echo '{"error":"bad token"}'; exit; }
}
header('Content-Type: application/json');
echo json_encode(['postingId' => 'PMS-' . substr(md5($body), 0, 8)]);
PHPSRC);
file_put_contents($pmsDir . '/mode.txt', 'ok');

$phpBin = PHP_BINARY;
$ini    = php_ini_loaded_file();
$cmd    = escapeshellarg($phpBin) . ($ini ? ' -c ' . escapeshellarg($ini) : '')
        . ' -S 127.0.0.1:' . $pmsPort . ' -t ' . escapeshellarg($pmsDir);

$proc = proc_open($cmd, [1 => ['file', $pmsDir . '/out.log', 'a'], 2 => ['file', $pmsDir . '/out.log', 'a']], $pipes);

register_shutdown_function(function () use ($proc) {
    if (!is_resource($proc)) return;

    // روی ویندوز، proc_terminate فرایند فرزند php -S را نمی‌کشد و تست
    // برای همیشه معلق می‌ماند. کل درخت فرایند باید بسته شود.
    $status = proc_get_status($proc);
    $pid    = (int)($status['pid'] ?? 0);

    if ($pid > 0 && stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        exec('taskkill /F /T /PID ' . $pid . ' 2>NUL');
    }

    proc_terminate($proc);
    proc_close($proc);
});

// صبر تا بالا آمدن
$pmsUp = false;
for ($i = 0; $i < 30; $i++) {
    $fp = @fsockopen('127.0.0.1', $pmsPort, $e, $s, 0.5);
    if ($fp) { fclose($fp); $pmsUp = true; break; }
    usleep(200000);
}

// ── آماده‌سازی ───────────────────────────────────────────────────
$db->query("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1,'Test Hotel','test')");
foreach (['pms_push_log','room_charges','minibar_checks','ppv_purchases','checkout_requests'] as $t) {
    $db->query("DELETE FROM $t WHERE tenant_id = 1");
}
$db->query("DELETE FROM pms_integrations WHERE tenant_id = 1 AND name LIKE 'TEST%'");
$db->query("DELETE FROM iptv_rooms WHERE room_number IN ('F101','F102')");
$db->query("DELETE FROM vod_videos WHERE title LIKE 'FOLIOTEST%'");
$db->query("DELETE FROM minibar_items WHERE tenant_id = 1 AND name_fa LIKE 'TEST%'");

$roomId = (int)$db->insert('iptv_rooms', [
    'tenant_id' => 1, 'room_number' => 'F101', 'status' => 'occupied',
    'guest_name' => 'آقای صورتحساب', 'guest_lang' => 'fa',
    'check_in_at' => date('Y-m-d H:i:s', time() - 7200),
]);
$emptyRoom = (int)$db->insert('iptv_rooms', [
    'tenant_id' => 1, 'room_number' => 'F102', 'status' => 'available',
]);

$water = (int)$db->insert('minibar_items', [
    'tenant_id' => 1, 'name_fa' => 'TEST آب معدنی', 'price' => 50000, 'par_level' => 2, 'is_active' => 1,
]);
$choc = (int)$db->insert('minibar_items', [
    'tenant_id' => 1, 'name_fa' => 'TEST شکلات', 'price' => 90000, 'par_level' => 2, 'is_active' => 1,
]);

$folio = new App\Services\FolioService($db);

// ── ۱) ثبت قلم ───────────────────────────────────────────────────
echo "\n── ۱) ثبت قلم روی صورتحساب ──\n";
$r = $folio->post(1, $roomId, ['title' => 'روم‌سرویس', 'qty' => 2, 'unit_price' => 150000, 'source' => 'room_service']);
check('قلم ثبت شد', $r['ok'], $r['message']);

$f = $folio->folio(1, $roomId);
check('مبلغ درست محاسبه شد (۳۰۰,۰۰۰)', $f['total'] === 300000.0, 'total=' . $f['total']);

$bad = $folio->post(1, $emptyRoom, ['title' => 'چیزی', 'unit_price' => 1000]);
check('اتاق بدون مهمان قلم نمی‌گیرد', !$bad['ok'], $bad['message']);

// ── ۲) مینی‌بار ─────────────────────────────────────────────────
echo "\n── ۲) شمارش مینی‌بار توسط خانه‌داری ──\n";
// par=2، یافت‌شده: ۰ آب (۲ مصرف) و ۲ شکلات (۰ مصرف)
$m = $folio->recordMinibar(1, $roomId, [$water => 0, $choc => 2]);
check('شمارش ثبت شد', $m['ok'], $m['message']);
check('فقط قلم مصرف‌شده هزینه شد', $m['charged'] === 1, 'charged=' . $m['charged']);
check('مبلغ مینی‌بار درست است (۱۰۰,۰۰۰)', $m['amount'] === 100000.0, 'amount=' . $m['amount']);

$checks = (int)$db->value('SELECT COUNT(*) FROM minibar_checks WHERE room_id = ?', [$roomId]);
check('هر دو قلم در تاریخچه شمارش ثبت شدند', $checks === 2, 'تعداد=' . $checks);

$mEmpty = $folio->recordMinibar(1, $emptyRoom, [$water => 0]);
check('اتاق خالی هزینه‌ی مینی‌بار نمی‌گیرد', !$mEmpty['ok'], $mEmpty['message']);

// ── ۳) محتوای پولی ──────────────────────────────────────────────
echo "\n── ۳) خرید فیلم ──\n";
$freeVid = (int)$db->insert('vod_videos', [
    'tenant_id' => 1, 'title' => 'FOLIOTEST رایگان', 'file_path' => '/x.mp4', 'price' => 0,
]);
$paidVid = (int)$db->insert('vod_videos', [
    'tenant_id' => 1, 'title' => 'FOLIOTEST پولی', 'file_path' => '/y.mp4',
    'price' => 250000, 'access_hours' => 24,
]);

check('فیلم رایگان بدون خرید قابل پخش است', $folio->hasVideoAccess(1, $roomId, $freeVid));
check('فیلم پولی قبل از خرید قابل پخش نیست', !$folio->hasVideoAccess(1, $roomId, $paidVid));

$p = $folio->purchaseVideo(1, $roomId, $paidVid);
check('خرید ثبت شد', $p['ok'], $p['message']);
check('بعد از خرید قابل پخش است', $folio->hasVideoAccess(1, $roomId, $paidVid));

$p2 = $folio->purchaseVideo(1, $roomId, $paidVid);
check('خرید دوباره هزینه‌ی مجدد ندارد', $p2['ok'], $p2['message']);
check('فقط یک قلم PPV ثبت شد',
    (int)$db->value("SELECT COUNT(*) FROM room_charges WHERE room_id=? AND source='ppv'", [$roomId]) === 1);

$pEmpty = $folio->purchaseVideo(1, $emptyRoom, $paidVid);
check('اتاق بدون مهمان نمی‌تواند بخرد', !$pEmpty['ok'], $pEmpty['message']);

// ── ۴) جداسازی اقامت ────────────────────────────────────────────
echo "\n── ۴) صورتحساب مهمان قبلی نباید دیده شود ──\n";
$before = $folio->folio(1, $roomId)['total'];
// شبیه‌سازی اقامت قبلی
$db->query(
    "INSERT INTO room_charges (tenant_id, room_id, stay_started_at, source, title, qty, unit_price, amount)
     VALUES (1, ?, DATE_SUB(NOW(), INTERVAL 30 DAY), 'manual', 'مهمان قبلی', 1, 999000, 999000)",
    [$roomId]
);
$after = $folio->folio(1, $roomId)['total'];
check('قلم اقامت قبلی در صورتحساب جاری نیامد', $after === $before, "قبل=$before بعد=$after");

// ── ۵) ابطال ────────────────────────────────────────────────────
echo "\n── ۵) ابطال قلم ──\n";
$cid = (int)$db->value("SELECT id FROM room_charges WHERE room_id=? AND source='ppv' LIMIT 1", [$roomId]);
$v = $folio->void(1, $cid, null, 'اشتباه ثبت شد');
check('قلم باطل شد', $v['ok'], $v['message']);
check('قلم باطل‌شده از مجموع کم شد', $folio->folio(1, $roomId)['total'] === $after - 250000.0);
check('ابطال دوباره رد شد', !$folio->void(1, $cid, null)['ok']);

// ── ۶) ارسال به PMS ─────────────────────────────────────────────
echo "\n── ۶) ارسال اقلام به PMS هتلداری ──\n";
if (!$pmsUp) {
    check('سرور PMS ساختگی بالا آمد', false, 'روی پورت ' . $pmsPort . ' بالا نیامد');
} else {
    check('سرور PMS ساختگی بالا آمد', true);

    $pmsId = (int)$db->insert('pms_integrations', [
        'tenant_id'   => 1, 'name' => 'TEST PMS', 'api_key' => bin2hex(random_bytes(16)),
        'pms_type'    => 'custom', 'is_active' => 1, 'push_charges' => 1,
        'charge_url'  => "http://127.0.0.1:$pmsPort/index.php",
        'auth_type'   => 'bearer', 'auth_secret' => 'SECRET123',
        'field_map'   => json_encode(['room' => 'RoomNo', 'amount' => 'Amount']),
    ]);

    $svc = new App\Services\PmsChargeService($db);
    $res = $svc->pushPending(1);

    check('اقلام ارسال شدند', $res['sent'] > 0, json_encode($res, JSON_UNESCAPED_UNICODE));
    check('هیچ ارسالی ناموفق نبود', $res['failed'] === 0, 'failed=' . $res['failed']);

    $lines = array_filter(explode("\n", (string)@file_get_contents($pmsLog)));
    check('PMS واقعا داده دریافت کرد', count($lines) > 0, 'تعداد=' . count($lines));

    $firstPayload = json_decode($lines[0] ?? '{}', true) ?: [];
    check('نگاشت نام فیلد اعمال شد (RoomNo)',
        array_key_exists('RoomNo', $firstPayload), json_encode($firstPayload, JSON_UNESCAPED_UNICODE));
    check('شماره اتاق درست فرستاده شد', ($firstPayload['RoomNo'] ?? '') === 'F101');
    check('ارجاع یکتا برای جلوگیری از تکرار هست',
        str_starts_with((string)($firstPayload['reference'] ?? ''), 'hm-charge-'));

    check('شناسه بازگشتی PMS ذخیره شد',
        (int)$db->value("SELECT COUNT(*) FROM room_charges WHERE pms_ref LIKE 'PMS-%'") > 0);
    check('قلم باطل‌شده به PMS نرفت',
        (string)$db->value('SELECT pms_status FROM room_charges WHERE id=?', [$cid]) !== 'sent');

    $again = $svc->pushPending(1);
    check('ارسال دوباره قلم تکراری نمی‌فرستد', $again['sent'] === 0, 'sent=' . $again['sent']);

    // ── PMS خراب ──
    echo "\n── ۷) وقتی PMS هتل قطع است ──\n";
    file_put_contents($pmsDir . '/mode.txt', 'fail');

    $folio->post(1, $roomId, ['title' => 'قلم هنگام قطعی', 'unit_price' => 60000, 'source' => 'manual']);
    $down = $svc->pushPending(1);
    check('ارسال ناموفق گزارش شد', $down['failed'] > 0, json_encode($down, JSON_UNESCAPED_UNICODE));
    check('قلم در صف ماند (گم نشد)',
        (int)$db->value("SELECT COUNT(*) FROM room_charges WHERE tenant_id=1 AND pms_status='pending'") > 0);
    check('تلاش ناموفق لاگ شد',
        (int)$db->value('SELECT COUNT(*) FROM pms_push_log WHERE ok=0') > 0);

    file_put_contents($pmsDir . '/mode.txt', 'ok');
    $recover = $svc->pushPending(1);
    check('بعد از برگشتن PMS، قلم معوق ارسال شد', $recover['sent'] > 0, json_encode($recover, JSON_UNESCAPED_UNICODE));

    // ── احراز هویت ──
    echo "\n── ۸) احراز هویت PMS ──\n";
    file_put_contents($pmsDir . '/mode.txt', 'auth');
    $t = $svc->test(1);
    check('با توکن درست، اتصال برقرار شد', $t['ok'], $t['message']);

    $db->update('pms_integrations', ['auth_secret' => 'WRONG'], ['id' => $pmsId]);
    $t2 = $svc->test(1);
    check('با توکن اشتباه، خطا گزارش شد', !$t2['ok'], $t2['message']);
    file_put_contents($pmsDir . '/mode.txt', 'ok');
}

// ── ۹) خروج سریع ────────────────────────────────────────────────
echo "\n── ۹) خروج سریع ──\n";
$co = $folio->requestCheckout(1, $roomId);
check('درخواست خروج ثبت شد', $co['ok'], $co['message']);
check('مبلغ در لحظه درخواست ذخیره شد', $co['total'] > 0, 'total=' . $co['total']);

$dupe = $folio->requestCheckout(1, $roomId);
check('درخواست تکراری رکورد جدید نساخت', $dupe['id'] === $co['id']);

$conf = $folio->confirmCheckout(1, (int)$co['id'], null, true);
check('پذیرش خروج را تایید کرد', $conf['ok'], $conf['message']);

$room = $db->row('SELECT status, guest_name, check_in_at FROM iptv_rooms WHERE id=?', [$roomId]);
check('اتاق آزاد شد', ($room['status'] ?? '') === 'available', 'status=' . ($room['status'] ?? '?'));
// ?? مقدار null را هم می‌گیرد، پس باید صریح چک شود
check('نام مهمان پاک شد',
    array_key_exists('guest_name', $room) && $room['guest_name'] === null,
    'مقدار=' . var_export($room['guest_name'] ?? 'missing', true));
check('اقلام تسویه شدند',
    (int)$db->value("SELECT COUNT(*) FROM room_charges WHERE room_id=? AND status='settled'", [$roomId]) > 0);
check('دسترسی فیلم خریداری‌شده با خروج تمام شد', !$folio->hasVideoAccess(1, $roomId, $paidVid));
check('رسیدگی دوباره به همان درخواست رد شد', !$folio->confirmCheckout(1, (int)$co['id'], null, true)['ok']);

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";

// پاک‌سازی
foreach (['pms_push_log','room_charges','minibar_checks','ppv_purchases','checkout_requests'] as $t) {
    $db->query("DELETE FROM $t WHERE tenant_id = 1");
}
$db->query("DELETE FROM pms_integrations WHERE tenant_id = 1 AND name LIKE 'TEST%'");
$db->query('DELETE FROM iptv_rooms WHERE id IN (?, ?)', [$roomId, $emptyRoom]);
$db->query("DELETE FROM vod_videos WHERE title LIKE 'FOLIOTEST%'");
$db->query("DELETE FROM minibar_items WHERE tenant_id = 1 AND name_fa LIKE 'TEST%'");

exit($fail === 0 ? 0 : 1);
