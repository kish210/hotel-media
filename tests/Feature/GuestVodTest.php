<?php
/**
 * تست فیلم‌ها روی تلویزیون اتاق (TODO ۳.۳): کتابخانه فقط فیلم قابل پخش
 * محلی، ادامه‌ی تماشا، پیشنهاد بر اساس دسته‌ی دیده‌شده، فیلم پولی بدون
 * آدرس تا خرید، و اینکه مهمان بعدی همان اتاق تاریخچه‌ی قبلی را نمی‌بیند.
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
    $ids = array_column($db->rows("SELECT id FROM vod_videos WHERE title LIKE 'VDTEST%'"), 'id');
    foreach ($ids as $id) {
        $db->query('DELETE FROM vod_watch_history WHERE video_id=?', [$id]);
        $db->query('DELETE FROM ppv_purchases WHERE video_id=?', [$id]);
    }
    $db->query("DELETE FROM room_charges WHERE room_id IN (SELECT id FROM iptv_rooms WHERE room_number='VD1')");
    $db->query("DELETE FROM vod_videos WHERE title LIKE 'VDTEST%'");
    $db->query("DELETE FROM vod_categories WHERE name LIKE 'VDTEST%'");
    $db->query("DELETE FROM screens WHERE code IN ('VDTEST','VDLOBBY')");
    $db->query("DELETE FROM iptv_rooms WHERE room_number='VD1'");
}

$db->query("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1,'Test Hotel','test')");
cleanup($db);

$checkin = date('Y-m-d H:i:s', time() - 3600);
$room = (int)$db->insert('iptv_rooms', ['tenant_id' => 1, 'room_number' => 'VD1', 'status' => 'occupied', 'check_in_at' => $checkin]);
$db->insert('screens', ['tenant_id' => 1, 'name' => 'VD', 'code' => 'VDTEST', 'screen_type' => 'iptv', 'status' => 'active', 'iptv_room_id' => $room]);
$db->insert('screens', ['tenant_id' => 1, 'name' => 'Lobby', 'code' => 'VDLOBBY', 'screen_type' => 'iptv', 'status' => 'active']);
$act   = (int)$db->insert('vod_categories', ['tenant_id' => 1, 'name' => 'VDTEST اکشن', 'slug' => 'vdtest-action', 'is_active' => 1]);
$drama = (int)$db->insert('vod_categories', ['tenant_id' => 1, 'name' => 'VDTEST درام', 'slug' => 'vdtest-drama', 'is_active' => 1]);
$mk = function (array $d) use ($db) {
    return (int)$db->insert('vod_videos', $d + ['tenant_id' => 1, 'type' => 'upload', 'file_path' => '/uploads/vod/x.mp4',
        'status' => 'ready', 'is_active' => 1, 'duration' => 5400, 'price' => 0, 'access_hours' => 24, 'currency' => 'IRR']);
};
$a1 = $mk(['title' => 'VDTEST اکشن ۱', 'category_id' => $act]);
$a2 = $mk(['title' => 'VDTEST اکشن ۲', 'category_id' => $act, 'views' => 5]);
$a3 = $mk(['title' => 'VDTEST اکشن ۳', 'category_id' => $act, 'views' => 1]);
$d1 = $mk(['title' => 'VDTEST درام ۱', 'category_id' => $drama, 'is_featured' => 1, 'views' => 50]);
$paid = $mk(['title' => 'VDTEST پولی', 'category_id' => $drama, 'price' => 500000]);
$yt   = $mk(['title' => 'VDTEST یوتیوب', 'category_id' => $act, 'type' => 'youtube', 'file_path' => null, 'stream_url' => 'https://youtube.com/x']);
$off  = $mk(['title' => 'VDTEST خاموش', 'category_id' => $act, 'is_active' => 0]);

$all = function (array $data): array {
    $o = [];
    foreach ($data['categories'] ?? [] as $c) foreach ($c['videos'] as $v) $o[$v['id']] = $v;
    return $o;
};
$ids = fn(array $list) => array_map(fn($v) => (int)$v['id'], $list);

// ── ۱) کتابخانه ─────────────────────────────────────────────────
echo "\n── ۱) کتابخانه ──\n";
$r = call('GuestVodController', 'index', ['code' => 'VDTEST']);
$lib = $r['body']['data'] ?? [];
$v = $all($lib);
check('پاسخ ۲۰۰ و اتاق ساکن', $r['status'] === 200 && !empty($lib['occupied']), json_encode($r['body'], JSON_UNESCAPED_UNICODE));
check('فیلم‌های محلی در دسته‌ی خودشان آمدند', isset($v[$a1], $v[$d1], $v[$paid]));
check('یوتیوب (اینترنت می‌خواهد) و فیلم خاموش نیامدند', !isset($v[$yt]) && !isset($v[$off]));
check('فیلم پولی قفل است', isset($v[$paid]) && $v[$paid]['allowed'] === false && $v[$paid]['price'] == 500000);
check('آدرس فایل در فهرست نیست', !array_key_exists('url', $v[$a1] ?? []) && !array_key_exists('file_path', $v[$a1] ?? []));
check('بدون تاریخچه: ادامه‌ی تماشا خالی، پیشنهاد با فیلم ویژه شروع می‌شود',
      empty($lib['continue']) && ($ids($lib['recommended'])[0] ?? 0) === $d1, json_encode($ids($lib['recommended'])));

// ── ۲) ادامه‌ی تماشا ───────────────────────────────────────────────
echo "\n── ۲) ادامه‌ی تماشا ──\n";
$views0 = (int)$db->value('SELECT views FROM vod_videos WHERE id=?', [$a1]);
$r = call('GuestVodController', 'progress', ['code' => 'VDTEST', 'id' => $a1], [], ['position' => 600.4, 'duration' => 5400]);
check('جای پخش ثبت شد', ($r['body']['data']['saved'] ?? false) === true);
call('GuestVodController', 'progress', ['code' => 'VDTEST', 'id' => $a1], [], ['position' => 900, 'duration' => 5400]);
check('بازدید فقط یک بار برای این اقامت شمرده شد', (int)$db->value('SELECT views FROM vod_videos WHERE id=?', [$a1]) === $views0 + 1);
$lib = call('GuestVodController', 'index', ['code' => 'VDTEST'])['body']['data'];
check('در «ادامه‌ی تماشا» با جای آخر', ($lib['continue'][0]['id'] ?? 0) === $a1 && ($lib['continue'][0]['progress']['position'] ?? 0) === 900);
$rec = $ids($lib['recommended']);
check('پیشنهاد: اول همان دسته‌ی دیده‌شده، پربیننده‌تر جلوتر، خود فیلم نه',
      array_slice($rec, 0, 2) === [$a2, $a3] && !in_array($a1, $rec, true), json_encode($rec));
$det = call('GuestVodController', 'show', ['code' => 'VDTEST', 'id' => $a1])['body']['data'] ?? [];
check('جزئیات: آدرس پخش و جای ادامه', ($det['url'] ?? '') === '/uploads/vod/x.mp4' && ($det['progress']['position'] ?? 0) === 900);

call('GuestVodController', 'progress', ['code' => 'VDTEST', 'id' => $a2], [], ['position' => 5380, 'duration' => 5400]);
$lib = call('GuestVodController', 'index', ['code' => 'VDTEST'])['body']['data'];
check('فیلم تا آخر دیده‌شده در «ادامه» نیست', !in_array($a2, $ids($lib['continue']), true)
      && (int)$db->value('SELECT completed FROM vod_watch_history WHERE video_id=?', [$a2]) === 1);

call('GuestVodController', 'hide', ['code' => 'VDTEST', 'id' => $a1]);
$lib = call('GuestVodController', 'index', ['code' => 'VDTEST'])['body']['data'];
check('برداشتن از «ادامه‌ی تماشا»', !in_array($a1, $ids($lib['continue']), true));
call('GuestVodController', 'progress', ['code' => 'VDTEST', 'id' => $a1], [], ['position' => 1000, 'duration' => 5400]);
$lib = call('GuestVodController', 'index', ['code' => 'VDTEST'])['body']['data'];
check('دوباره دیدن، دوباره در «ادامه»', in_array($a1, $ids($lib['continue']), true));

// ── ۳) فیلم پولی ───────────────────────────────────────────────
echo "\n── ۳) فیلم پولی ──\n";
$det = call('GuestVodController', 'show', ['code' => 'VDTEST', 'id' => $paid])['body']['data'] ?? [];
check('پیش از خرید آدرس پخش داده نمی‌شود', array_key_exists('url', $det) && $det['url'] === null && $det['allowed'] === false);
$r = call('FolioController', 'guestPurchase', ['code' => 'VDTEST', 'id' => $paid]);
check('خرید روی صورتحساب', $r['status'] === 200, json_encode($r['body'], JSON_UNESCAPED_UNICODE));
$det = call('GuestVodController', 'show', ['code' => 'VDTEST', 'id' => $paid])['body']['data'] ?? [];
check('بعد از خرید: آدرس و مهلت', $det['allowed'] === true && $det['url'] === '/uploads/vod/x.mp4' && !empty($det['owned_until']));

// ── ۴) حریم مهمان بعدی و لابی ─────────────────────────────────────
echo "\n── ۴) مهمان بعدی و لابی ──\n";
$db->update('iptv_rooms', ['check_in_at' => date('Y-m-d H:i:s')], ['id' => $room]);
$lib = call('GuestVodController', 'index', ['code' => 'VDTEST'])['body']['data'];
check('مهمان بعدی همان اتاق «ادامه‌ی تماشا»ی قبلی را نمی‌بیند', empty($lib['continue']));
$db->query('UPDATE ppv_purchases SET cancelled_at = NOW() WHERE room_id = ?', [$room]);

$r = call('GuestVodController', 'index', ['code' => 'VDLOBBY']);
check('لابی: فهرست بله، اتاق ساکن نه', $r['status'] === 200 && empty($r['body']['data']['occupied']) && !empty($r['body']['data']['categories']));
$r = call('GuestVodController', 'progress', ['code' => 'VDLOBBY', 'id' => $a1], [], ['position' => 100, 'duration' => 5400]);
check('لابی تاریخچه ثبت نمی‌کند', ($r['body']['data']['saved'] ?? true) === false);
$r = call('GuestVodController', 'show', ['code' => 'VDTEST', 'id' => $yt]);
check('فیلم یوتیوب با شناسه هم باز نمی‌شود', $r['status'] === 404);

$top = (new App\Services\VodGuestService($db))->topWatched(1);
check('آمار پربیننده برای پنل', in_array($a1, array_map('intval', array_column($top, 'id')), true));

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";
cleanup($db);
exit($fail === 0 ? 0 : 1);
