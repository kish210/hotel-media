<?php
/**
 * تست EPG: پارس XMLTV، تطبیق کانال، «الان و بعدی»، و idempotency.
 * به یک دیتابیس تست زنده نیاز دارد (.env).
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
$db->query("DELETE FROM epg_programs    WHERE tenant_id = 1");
$db->query("DELETE FROM epg_channel_map WHERE tenant_id = 1");
$db->query("DELETE FROM epg_sources     WHERE tenant_id = 1");
$db->query("DELETE FROM iptv_channels   WHERE tenant_id = 1 AND name LIKE 'EPGTEST%'");

// سه کانال با سه روش تطبیق متفاوت
$chByEpgId = (int)$db->insert('iptv_channels', [
    'tenant_id' => 1, 'name' => 'EPGTEST شبکه یک', 'stream_url' => 'http://x/1',
    'epg_id' => 'ch1.test', 'sort_order' => 1, 'is_active' => 1,
]);
$chByName = (int)$db->insert('iptv_channels', [
    'tenant_id' => 1, 'name' => 'EPGTEST-ByName', 'stream_url' => 'http://x/2',
    'sort_order' => 2, 'is_active' => 1,
]);
$chByMap = (int)$db->insert('iptv_channels', [
    'tenant_id' => 1, 'name' => 'EPGTEST نگاشت‌دستی', 'stream_url' => 'http://x/3',
    'sort_order' => 3, 'is_active' => 1,
]);
$db->insert('epg_channel_map', ['tenant_id' => 1, 'channel_key' => 'mapped.key', 'channel_id' => $chByMap]);

// ── فایل XMLTV نمونه ─────────────────────────────────────────────
$tz    = date('O');                       // مثلا +0330
$now   = time();
$fmt   = static fn(int $ts): string => date('YmdHis', $ts) . ' ' . date('O', $ts);

// برنامه‌ای که الان در حال پخش است: ۱۰ دقیقه پیش شروع، ۵۰ دقیقه دیگر تمام
$curStart = $now - 600;
$curEnd   = $now + 3000;
$nextEnd  = $curEnd + 3600;

$xml = '<?xml version="1.0" encoding="UTF-8"?>
<tv>
  <programme start="' . $fmt($curStart) . '" stop="' . $fmt($curEnd) . '" channel="ch1.test">
    <title lang="fa">اخبار ساعت ۲۰</title>
    <sub-title>بخش ویژه</sub-title>
    <desc>خلاصه رویدادهای روز</desc>
    <category>خبر</category>
    <episode-num system="xmltv_ns">1 . 4 .</episode-num>
    <rating><value>12+</value></rating>
  </programme>
  <programme start="' . $fmt($curEnd) . '" stop="' . $fmt($nextEnd) . '" channel="ch1.test">
    <title lang="fa">فیلم سینمایی</title>
    <category>فیلم</category>
  </programme>
  <programme start="' . $fmt($curStart) . '" stop="' . $fmt($curEnd) . '" channel="EPGTEST-ByName">
    <title>برنامه تطبیق با نام</title>
  </programme>
  <programme start="' . $fmt($curStart) . '" stop="' . $fmt($curEnd) . '" channel="mapped.key">
    <title>برنامه نگاشت دستی</title>
  </programme>
  <programme start="' . $fmt($now - 10 * 86400) . '" stop="' . $fmt($now - 10 * 86400 + 3600) . '" channel="ch1.test">
    <title>برنامه خیلی قدیمی</title>
  </programme>
  <programme start="' . $fmt($curEnd) . '" stop="' . $fmt($curStart) . '" channel="ch1.test">
    <title>بازه معکوس - باید رد شود</title>
  </programme>
</tv>';

$xmlPath = STORAGE_PATH . '/epg-test.xml';
file_put_contents($xmlPath, $xml);

$sourceId = (int)$db->insert('epg_sources', [
    'tenant_id' => 1, 'name' => 'XMLTV تست', 'source_type' => 'xmltv_file',
    'url' => $xmlPath, 'days_ahead' => 7, 'is_active' => 1,
]);
$source = $db->row('SELECT * FROM epg_sources WHERE id=?', [$sourceId]);

// ── ۱) همگام‌سازی ────────────────────────────────────────────────
echo "\n── ۱) همگام‌سازی XMLTV ──\n";
$svc = new App\Services\EpgSyncService($db);
$res = $svc->sync($source);
check('sync موفق بود', $res['ok'], $res['message']);

// بازه معکوس رد، قدیمی هم prune می‌شود → ۴ برنامه می‌ماند
$total = (int)$db->value('SELECT COUNT(*) FROM epg_programs WHERE tenant_id=1');
check('۴ برنامه ذخیره شد (معکوس و قدیمی حذف)', $total === 4, "تعداد=$total");

echo "\n── ۲) تطبیق کانال ──\n";
$linkedEpgId = (int)$db->value('SELECT COUNT(*) FROM epg_programs WHERE tenant_id=1 AND channel_id=?', [$chByEpgId]);
check('تطبیق با epg_id کار کرد', $linkedEpgId === 2, "تعداد=$linkedEpgId");
check('تطبیق با نام کانال کار کرد',
    (int)$db->value('SELECT COUNT(*) FROM epg_programs WHERE tenant_id=1 AND channel_id=?', [$chByName]) === 1);
check('تطبیق با نگاشت دستی کار کرد',
    (int)$db->value('SELECT COUNT(*) FROM epg_programs WHERE tenant_id=1 AND channel_id=?', [$chByMap]) === 1);
check('هیچ برنامه‌ای بدون کانال نماند',
    (int)$db->value('SELECT COUNT(*) FROM epg_programs WHERE tenant_id=1 AND channel_id IS NULL') === 0);

echo "\n── ۳) جزئیات پارس ──\n";
$p = $db->row("SELECT * FROM epg_programs WHERE tenant_id=1 AND title='اخبار ساعت ۲۰'");
check('عنوان فارسی درست ذخیره شد', $p !== null);
check('زیرعنوان خوانده شد',  ($p['subtitle'] ?? '') === 'بخش ویژه');
check('دسته خوانده شد',      ($p['category'] ?? '') === 'خبر');
check('رده سنی خوانده شد',   ($p['rating']   ?? '') === '12+');
check('شماره قسمت از «1 . 4 .» استخراج شد', (int)($p['episode'] ?? 0) === 1, 'episode=' . ($p['episode'] ?? 'null'));

echo "\n── ۴) idempotency: sync دوباره نباید تکراری بسازد ──\n";
$res2 = $svc->sync($db->row('SELECT * FROM epg_sources WHERE id=?', [$sourceId]));
$total2 = (int)$db->value('SELECT COUNT(*) FROM epg_programs WHERE tenant_id=1');
check('sync دوم هم موفق بود', $res2['ok'], $res2['message']);
check('تعداد ثابت ماند (بدون تکرار)', $total2 === 4, "تعداد=$total2");

echo "\n── ۵) «الان و بعدی» ──\n";
$rows = $db->rows(
    'SELECT title, starts_at, ends_at FROM epg_programs
      WHERE tenant_id=1 AND channel_id=? AND starts_at <= NOW() AND ends_at > NOW()',
    [$chByEpgId]
);
check('یک برنامه در حال پخش پیدا شد', count($rows) === 1, 'تعداد=' . count($rows));
check('برنامه در حال پخش، اخبار است', ($rows[0]['title'] ?? '') === 'اخبار ساعت ۲۰');

$nextRow = $db->row(
    'SELECT title FROM epg_programs
      WHERE tenant_id=1 AND channel_id=? AND starts_at > NOW()
      ORDER BY starts_at LIMIT 1',
    [$chByEpgId]
);
check('برنامه بعدی، فیلم سینمایی است', ($nextRow['title'] ?? '') === 'فیلم سینمایی');

echo "\n── ۶) امنیت: فایل بیرون از storage نباید خوانده شود ──\n";
$db->update('epg_sources', ['url' => 'C:/Windows/win.ini'], ['id' => $sourceId]);
$res3 = $svc->sync($db->row('SELECT * FROM epg_sources WHERE id=?', [$sourceId]));
check('مسیر خارج از storage رد شد', !$res3['ok'], $res3['message']);

echo "\n── ۷) وضعیت sync روی منبع ثبت شد ──\n";
$src = $db->row('SELECT last_sync_at, last_sync_msg, last_count FROM epg_sources WHERE id=?', [$sourceId]);
check('زمان آخرین sync ثبت شد', !empty($src['last_sync_at']));
check('پیام خطای آخرین sync ثبت شد', str_starts_with((string)$src['last_sync_msg'], '✗'));

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";

// پاک‌سازی
@unlink($xmlPath);
$db->query('DELETE FROM epg_programs    WHERE tenant_id = 1');
$db->query('DELETE FROM epg_channel_map WHERE tenant_id = 1');
$db->query('DELETE FROM epg_sources     WHERE tenant_id = 1');
$db->query("DELETE FROM iptv_channels   WHERE tenant_id = 1 AND name LIKE 'EPGTEST%'");

exit($fail === 0 ? 0 : 1);
