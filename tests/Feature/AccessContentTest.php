<?php
/**
 * تست کنترل دسترسی کانال، قفل والدین، محتوای جانبی، خبر RSS و بیدارباش.
 * یک فید RSS ساختگی واقعی بالا می‌آید تا پارس و ذخیره آزمایش شود.
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

// ── آماده‌سازی ───────────────────────────────────────────────────
$db->query("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1,'Test Hotel','test')");
$db->query("DELETE FROM iptv_channels WHERE tenant_id=1 AND name LIKE 'ACTEST%'");
$db->query("DELETE FROM iptv_rooms    WHERE room_number IN ('A101','A102')");
$db->query("DELETE FROM content_items WHERE tenant_id=1 AND title LIKE 'ACTEST%'");
$db->query("DELETE FROM news_feeds    WHERE tenant_id=1 AND name LIKE 'ACTEST%'");
$db->query("DELETE FROM rate_limits   WHERE `key` LIKE 'parental:%'");

// کانال‌ها: عمومی، VIP، بزرگسال، رادیو
$chPublic = (int)$db->insert('iptv_channels', ['tenant_id'=>1,'name'=>'ACTEST عمومی','stream_url'=>'http://x/1','channel_no'=>1,'is_active'=>1,'access_level'=>0]);
$chVip    = (int)$db->insert('iptv_channels', ['tenant_id'=>1,'name'=>'ACTEST ویژه','stream_url'=>'http://x/2','channel_no'=>2,'is_active'=>1,'access_level'=>5]);
$chAdult  = (int)$db->insert('iptv_channels', ['tenant_id'=>1,'name'=>'ACTEST بزرگسال','stream_url'=>'http://x/3','channel_no'=>3,'is_active'=>1,'access_level'=>0,'is_adult'=>1]);
$chRadio  = (int)$db->insert('iptv_channels', ['tenant_id'=>1,'name'=>'ACTEST رادیو','stream_url'=>'http://x/4','channel_no'=>4,'is_active'=>1,'is_radio'=>1]);

$roomStd = (int)$db->insert('iptv_rooms', [
    'tenant_id'=>1,'room_number'=>'A101','status'=>'occupied','guest_name'=>'مهمان عادی',
    'check_in_at'=>date('Y-m-d H:i:s'),'access_level'=>0,
]);
$roomVip = (int)$db->insert('iptv_rooms', [
    'tenant_id'=>1,'room_number'=>'A102','status'=>'occupied','guest_name'=>'مهمان سوئیت',
    'check_in_at'=>date('Y-m-d H:i:s'),'access_level'=>5,
]);

$svc = new App\Services\ChannelAccessService($db);

// ── ۱) سطح دسترسی اتاق ──────────────────────────────────────────
echo "\n── ۱) کانال ویژه فقط برای سوئیت ──\n";
$std = $svc->visibleChannels(1, $db->row('SELECT * FROM iptv_rooms WHERE id=?', [$roomStd]));
$vip = $svc->visibleChannels(1, $db->row('SELECT * FROM iptv_rooms WHERE id=?', [$roomVip]));

$stdNames = array_column($std, 'name');
$vipNames = array_column($vip, 'name');

check('اتاق عادی کانال عمومی را می‌بیند', in_array('ACTEST عمومی', $stdNames, true));
check('اتاق عادی کانال ویژه را **اصلا نمی‌بیند**',
    !in_array('ACTEST ویژه', $stdNames, true), implode('، ', $stdNames));
check('سوئیت کانال ویژه را می‌بیند', in_array('ACTEST ویژه', $vipNames, true));
check('canView برای اتاق عادی روی کانال ویژه false است',
    !$svc->canView(1, $db->row('SELECT * FROM iptv_rooms WHERE id=?', [$roomStd]), $chVip));
check('canView برای سوئیت true است',
    $svc->canView(1, $db->row('SELECT * FROM iptv_rooms WHERE id=?', [$roomVip]), $chVip));

check('کانال رادیویی علامت‌گذاری شد',
    (bool)(array_values(array_filter($std, fn($c) => $c['name'] === 'ACTEST رادیو'))[0]['is_radio'] ?? false));

// ── ۲) قفل والدین ───────────────────────────────────────────────
echo "\n── ۲) قفل والدین ──\n";
$noLock = array_values(array_filter($std, fn($c) => $c['name'] === 'ACTEST بزرگسال'))[0] ?? [];
check('بدون تنظیم رمز، کانال بزرگسال قفل نیست', ($noLock['locked'] ?? true) === false);

$weak = $svc->setPin(1, $roomStd, '1234');
check('رمز ساده رد شد', !$weak['ok'], $weak['message']);

$short = $svc->setPin(1, $roomStd, '12');
check('رمز غیر ۴ رقمی رد شد', !$short['ok'], $short['message']);

$set = $svc->setPin(1, $roomStd, '7391');
check('رمز معتبر پذیرفته شد', $set['ok'], $set['message']);

$stored = (string)$db->value('SELECT parental_pin FROM iptv_rooms WHERE id=?', [$roomStd]);
check('رمز به‌صورت hash ذخیره شد (نه متن ساده)',
    $stored !== '7391' && str_starts_with($stored, '$2y$'), substr($stored, 0, 10));

$std2 = $svc->visibleChannels(1, $db->row('SELECT * FROM iptv_rooms WHERE id=?', [$roomStd]));
$locked = array_values(array_filter($std2, fn($c) => $c['name'] === 'ACTEST بزرگسال'))[0] ?? [];
check('حالا کانال بزرگسال قفل است', ($locked['locked'] ?? false) === true);
check('کانال عادی قفل نشد',
    (array_values(array_filter($std2, fn($c) => $c['name'] === 'ACTEST عمومی'))[0]['locked'] ?? true) === false);

// تغییر رمز بدون رمز فعلی نباید ممکن باشد
$noCur = $svc->setPin(1, $roomStd, '8888');
check('تغییر رمز بدون رمز فعلی رد شد', !$noCur['ok'], $noCur['message']);

$withCur = $svc->setPin(1, $roomStd, '8842', '7391');
check('تغییر رمز با رمز فعلی درست انجام شد', $withCur['ok'], $withCur['message']);

// ── ۳) باز کردن قفل ─────────────────────────────────────────────
echo "\n── ۳) باز کردن قفل با رمز ──\n";
$bad = $svc->unlock(1, $roomStd, '0000');
check('رمز اشتباه رد شد', !$bad['ok'], $bad['message']);

$good = $svc->unlock(1, $roomStd, '8842');
check('رمز درست، قفل را باز کرد', $good['ok'], $good['message']);
check('توکن صادر شد', !empty($good['token']));
check('توکن معتبر است', $svc->verifyToken(1, $roomStd, (string)$good['token']));
check('توکن برای اتاق دیگر معتبر نیست', !$svc->verifyToken(1, $roomVip, (string)$good['token']));
check('توکن دستکاری‌شده رد شد', !$svc->verifyToken(1, $roomStd, $good['token'] . 'x'));
check('توکن خالی رد شد', !$svc->verifyToken(1, $roomStd, ''));

// ── ۴) محدودیت تلاش ─────────────────────────────────────────────
echo "\n── ۴) محدودیت تلاش رمز ──\n";
$db->query("DELETE FROM rate_limits WHERE `key` = ?", ['parental:' . $roomStd]);
for ($i = 0; $i < 5; $i++) $svc->unlock(1, $roomStd, '0001');

$blocked = $svc->unlock(1, $roomStd, '8842');
check('بعد از ۵ تلاش ناموفق، ورود رمز قفل شد',
    !$blocked['ok'] && str_contains($blocked['message'], 'ناموفق'), $blocked['message']);

$db->query("DELETE FROM rate_limits WHERE `key` = ?", ['parental:' . $roomStd]);
check('بعد از پاک شدن محدودیت، رمز درست کار می‌کند', $svc->unlock(1, $roomStd, '8842')['ok']);

// ── ۵) خاموش کردن قفل ───────────────────────────────────────────
echo "\n── ۵) خاموش کردن قفل ──\n";
check('خاموش کردن با رمز اشتباه رد شد', !$svc->disablePin(1, $roomStd, '1111')['ok']);
check('خاموش کردن با رمز درست انجام شد', $svc->disablePin(1, $roomStd, '8842')['ok']);
check('رمز از دیتابیس پاک شد',
    $db->value('SELECT parental_pin FROM iptv_rooms WHERE id=?', [$roomStd]) === null);

// ── ۶) خبر از RSS ───────────────────────────────────────────────
echo "\n── ۶) دریافت خبر از RSS ──\n";
$rssPort = 19077;
$rssDir  = sys_get_temp_dir() . '/hm-rss-test';
@mkdir($rssDir, 0777, true);

$now = date('D, d M Y H:i:s O');
file_put_contents($rssDir . '/feed.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/">
<channel>
  <title>ACTEST Feed</title>
  <item>
    <title>خبر اول تست</title>
    <description><![CDATA[<p>متن خبر اول با <b>تگ</b></p><img src="http://img/1.jpg">]]></description>
    <pubDate>$now</pubDate>
  </item>
  <item>
    <title>خبر دوم تست</title>
    <description>متن ساده</description>
    <pubDate>$now</pubDate>
    <media:thumbnail url="http://img/2.jpg"/>
  </item>
  <item>
    <title></title>
    <description>بدون عنوان — باید رد شود</description>
  </item>
</channel>
</rss>
XML);

$cmd = escapeshellarg(PHP_BINARY)
     . (php_ini_loaded_file() ? ' -c ' . escapeshellarg(php_ini_loaded_file()) : '')
     . ' -S 127.0.0.1:' . $rssPort . ' -t ' . escapeshellarg($rssDir);
$proc = proc_open($cmd, [1 => ['file', $rssDir . '/out.log', 'a'], 2 => ['file', $rssDir . '/out.log', 'a']], $pipes);
register_shutdown_function(function () use ($proc) {
    if (!is_resource($proc)) return;
    $st = proc_get_status($proc);
    $pid = (int)($st['pid'] ?? 0);
    if ($pid > 0 && stripos(PHP_OS_FAMILY, 'Windows') !== false) exec('taskkill /F /T /PID ' . $pid . ' 2>NUL');
    proc_terminate($proc); proc_close($proc);
});

$rssUp = false;
for ($i = 0; $i < 30; $i++) {
    $fp = @fsockopen('127.0.0.1', $rssPort, $e, $s, 0.5);
    if ($fp) { fclose($fp); $rssUp = true; break; }
    usleep(200000);
}
check('فید ساختگی بالا آمد', $rssUp);

if ($rssUp) {
    $feedId = (int)$db->insert('news_feeds', [
        'tenant_id' => 1, 'name' => 'ACTEST منبع', 'url' => "http://127.0.0.1:$rssPort/feed.xml",
        'category' => 'general', 'lang' => 'fa', 'max_items' => 20, 'is_active' => 1,
    ]);

    $news = new App\Services\NewsFeedService($db);
    $r = $news->syncAll(1);
    check('خبرها دریافت شدند', $r['items'] === 2, json_encode($r, JSON_UNESCAPED_UNICODE));

    $items = $db->rows("SELECT * FROM content_items WHERE tenant_id=1 AND kind='news' ORDER BY id");
    check('دو خبر ذخیره شد (خبر بی‌عنوان رد شد)', count($items) === 2, 'تعداد=' . count($items));

    $first = $items[0] ?? [];
    check('عنوان فارسی درست ذخیره شد', ($first['title'] ?? '') === 'خبر اول تست');
    check('تگ HTML از متن حذف شد',
        !str_contains((string)($first['body'] ?? ''), '<b>'), $first['body'] ?? '');
    check('تصویر از داخل متن استخراج شد', ($first['image_url'] ?? '') === 'http://img/1.jpg');
    check('نام منبع ثبت شد', ($first['extra'] ?? '') === 'ACTEST منبع');
    check('تصویر media:thumbnail خوانده شد', ($items[1]['image_url'] ?? '') === 'http://img/2.jpg');

    // اجرای دوباره نباید خبر تکراری بسازد
    $r2 = $news->syncAll(1);
    check('اجرای دوباره خبر تکراری نساخت', $r2['items'] === 0, json_encode($r2, JSON_UNESCAPED_UNICODE));
    check('تعداد خبرها ثابت ماند',
        (int)$db->value("SELECT COUNT(*) FROM content_items WHERE tenant_id=1 AND kind='news'") === 2);

    // منبع خراب
    $db->update('news_feeds', ['url' => 'http://127.0.0.1:19078/none.xml'], ['id' => $feedId]);
    $r3 = $news->syncAll(1);
    check('منبع در دسترس نبودن گزارش شد', $r3['failed'] === 1, json_encode($r3, JSON_UNESCAPED_UNICODE));
    check('خبرهای قبلی پاک نشدند',
        (int)$db->value("SELECT COUNT(*) FROM content_items WHERE tenant_id=1 AND kind='news'") === 2);
}

// ── ۷) دفترچه تلفن ──────────────────────────────────────────────
echo "\n── ۷) دفترچه تلفن ──\n";
$dir = $db->rows("SELECT * FROM content_items WHERE tenant_id=1 AND kind='directory' ORDER BY sort_order");
check('دفترچه تلفن نمونه ساخته شد', count($dir) >= 5, 'تعداد=' . count($dir));
check('شماره داخلی در extra ذخیره شده', ($dir[0]['extra'] ?? '') === '9', json_encode($dir[0] ?? [], JSON_UNESCAPED_UNICODE));

// ── ۸) بیدارباش روی تلویزیون ────────────────────────────────────
echo "\n── ۸) بیدارباش ──\n";
$db->query('DELETE FROM guest_requests WHERE room_id = ?', [$roomStd]);

// یکی سررسیده، یکی برای فردا
$dueId = (int)$db->insert('guest_requests', [
    'tenant_id'=>1,'room_id'=>$roomStd,'category'=>'wakeup','status'=>'pending',
    'scheduled_at'=>date('Y-m-d H:i:s', time() - 60),
]);
$db->insert('guest_requests', [
    'tenant_id'=>1,'room_id'=>$roomStd,'category'=>'wakeup','status'=>'pending',
    'scheduled_at'=>date('Y-m-d H:i:s', time() + 86400),
]);
// یکی خیلی قدیمی — نباید ناگهان ظاهر شود
$db->insert('guest_requests', [
    'tenant_id'=>1,'room_id'=>$roomStd,'category'=>'wakeup','status'=>'pending',
    'scheduled_at'=>date('Y-m-d H:i:s', time() - 7200),
]);

$due = $db->rows(
    "SELECT id FROM guest_requests
      WHERE tenant_id=1 AND room_id=? AND category='wakeup'
        AND status IN ('pending','accepted','in_progress') AND acknowledged_at IS NULL
        AND scheduled_at <= NOW() AND scheduled_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
    [$roomStd]
);
check('فقط بیدارباش سررسیده در پنجره ۵ دقیقه‌ای برگشت', count($due) === 1, 'تعداد=' . count($due));
check('همان بیدارباش درست است', (int)($due[0]['id'] ?? 0) === $dueId);

$db->query("UPDATE guest_requests SET acknowledged_at=NOW(), status='done' WHERE id=?", [$dueId]);
$after = $db->value('SELECT acknowledged_at FROM guest_requests WHERE id=?', [$dueId]);
check('بستن بیدارباش ثبت شد', $after !== null);

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";

// پاک‌سازی
$db->query('DELETE FROM guest_requests WHERE room_id IN (?, ?)', [$roomStd, $roomVip]);
$db->query("DELETE FROM iptv_channels WHERE tenant_id=1 AND name LIKE 'ACTEST%'");
$db->query('DELETE FROM iptv_rooms    WHERE id IN (?, ?)', [$roomStd, $roomVip]);
$db->query("DELETE FROM content_items WHERE tenant_id=1 AND kind='news'");
$db->query("DELETE FROM news_feeds    WHERE tenant_id=1 AND name LIKE 'ACTEST%'");
$db->query("DELETE FROM rate_limits   WHERE `key` LIKE 'parental:%'");

exit($fail === 0 ? 0 : 1);
