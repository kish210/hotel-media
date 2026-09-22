<?php
/**
 * تست محیط عمومی هتل: محل‌ها، تابلوی رویداد، منوی تصویری روی signage،
 * تداخل رزرو سالن و محتوای پویا در پلی‌لیست.
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
$db->query("DELETE FROM hotel_events WHERE tenant_id=1 AND title LIKE 'SVTEST%'");
$db->query("DELETE FROM screens WHERE code IN ('SVHALL','SVREST')");
foreach ($db->rows("SELECT id FROM venues WHERE tenant_id=1 AND name LIKE 'SVTEST%'") as $v) {
    $db->query('UPDATE screens SET venue_id=NULL WHERE venue_id=?', [(int)$v['id']]);
}
$db->query("DELETE FROM venues WHERE tenant_id=1 AND name LIKE 'SVTEST%'");
foreach ($db->rows("SELECT id FROM menu_boards WHERE tenant_id=1 AND title LIKE 'SVTEST%'") as $m) {
    $db->query('DELETE FROM menu_board_pages WHERE board_id=?', [(int)$m['id']]);
}
$db->query("DELETE FROM menu_boards WHERE tenant_id=1 AND title LIKE 'SVTEST%'");

// محل‌ها
$hall = (int)$db->insert('venues', [
    'tenant_id'=>1,'kind'=>'hall','name'=>'SVTEST سالن A','floor'=>'۱','capacity'=>120,'is_active'=>1,
]);
$rest = (int)$db->insert('venues', [
    'tenant_id'=>1,'kind'=>'restaurant','name'=>'SVTEST رستوران','floor'=>'همکف',
    'open_from'=>'07:00:00','open_to'=>'23:00:00','is_active'=>1,
]);

// منوی تصویری برای رستوران
$board = (int)$db->insert('menu_boards', [
    'tenant_id'=>1,'category'=>'restaurant','title'=>'SVTEST منوی شام','is_active'=>1,
]);
$db->insert('menu_board_pages', ['board_id'=>$board,'image_url'=>'/uploads/menus/1/p1.jpg','sort_order'=>1]);
$db->insert('menu_board_pages', ['board_id'=>$board,'image_url'=>'/uploads/menus/1/p2.jpg','sort_order'=>2]);
$db->update('venues', ['menu_board_id'=>$board], ['id'=>$rest]);

// صفحه‌نمایش‌ها
$scrHall = (int)$db->insert('screens', [
    'tenant_id'=>1,'name'=>'TV جلوی سالن','code'=>'SVHALL','screen_type'=>'signage',
    'status'=>'active','venue_id'=>$hall,
]);
$scrRest = (int)$db->insert('screens', [
    'tenant_id'=>1,'name'=>'TV رستوران','code'=>'SVREST','screen_type'=>'signage',
    'status'=>'active','venue_id'=>$rest,
]);

$svc = new App\Services\SignageContentService($db);
$screenHall = $db->row('SELECT * FROM screens WHERE id=?', [$scrHall]);
$screenRest = $db->row('SELECT * FROM screens WHERE id=?', [$scrRest]);

// ── ۱) تابلوی رویداد خالی ───────────────────────────────────────
echo "\n── ۱) سالن بدون رویداد ──\n";
$empty = $svc->resolve(['item_type'=>'event_board','ref_id'=>null], $screenHall);
check('سالن بدون رویداد چیزی برنمی‌گرداند (آیتم رد می‌شود)', $empty === null);

// ── ۲) تابلوی رویداد ────────────────────────────────────────────
echo "\n── ۲) تابلوی جلوی سالن ──\n";
$nowStart = date('Y-m-d H:i:s', time() - 1800);
$nowEnd   = date('Y-m-d H:i:s', time() + 3600);
$db->insert('hotel_events', [
    'tenant_id'=>1,'venue_id'=>$hall,'title'=>'SVTEST جلسه هیئت مدیره',
    'organizer'=>'شرکت الف','start_at'=>$nowStart,'end_at'=>$nowEnd,
    'type'=>'conference','status'=>'scheduled','is_active'=>1,
]);
$db->insert('hotel_events', [
    'tenant_id'=>1,'venue_id'=>$hall,'title'=>'SVTEST سمینار عصر',
    'start_at'=>date('Y-m-d H:i:s', time() + 7200),'end_at'=>date('Y-m-d H:i:s', time() + 14400),
    'type'=>'seminar','status'=>'scheduled','is_active'=>1,
]);
// رویداد سالن دیگر — نباید روی تابلوی این سالن بیاید
$db->insert('hotel_events', [
    'tenant_id'=>1,'venue_id'=>$rest,'title'=>'SVTEST مهمانی رستوران',
    'start_at'=>date('Y-m-d H:i:s', time() + 3600),'is_active'=>1,
]);
// رویداد هفته‌ی بعد — تابلوی در ورودی نباید نشان دهد
$db->insert('hotel_events', [
    'tenant_id'=>1,'venue_id'=>$hall,'title'=>'SVTEST هفته بعد',
    'start_at'=>date('Y-m-d H:i:s', time() + 7*86400),'is_active'=>1,
]);

$board1 = $svc->resolve(['item_type'=>'event_board','ref_id'=>null], $screenHall);
check('تابلو ساخته شد', $board1 !== null);
$titles = array_column($board1['events'] ?? [], 'title');
check('فقط رویدادهای همین سالن آمدند',
    !in_array('SVTEST مهمانی رستوران', $titles, true), implode('، ', $titles));
check('رویداد هفته بعد نیامد', !in_array('SVTEST هفته بعد', $titles, true));
check('دو رویداد امروز آمدند', count($titles) === 2, 'تعداد=' . count($titles));

$first = $board1['events'][0] ?? [];
check('رویداد جاری is_now دارد', ($first['is_now'] ?? false) === true, json_encode($first, JSON_UNESCAPED_UNICODE));
check('نام سالن همراه تابلو آمد', ($board1['venue']['name'] ?? '') === 'SVTEST سالن A');

$second = $board1['events'][1] ?? [];
check('رویداد بعدی is_now ندارد', ($second['is_now'] ?? true) === false);
check('فاصله تا شروع محاسبه شد', (int)($second['minutes_away'] ?? 0) > 100);

// لغو
$db->query("UPDATE hotel_events SET status='cancelled' WHERE title='SVTEST سمینار عصر'");
$board2 = $svc->resolve(['item_type'=>'event_board','ref_id'=>null], $screenHall);
$cancelled = array_values(array_filter($board2['events'], fn($e) => $e['title'] === 'SVTEST سمینار عصر'))[0] ?? [];
check('رویداد لغوشده هنوز نمایش داده می‌شود', $cancelled !== []);
check('ولی با علامت لغو', ($cancelled['is_cancelled'] ?? false) === true);

// ── ۳) تابلوی لابی (بدون محل) ───────────────────────────────────
echo "\n── ۳) تابلوی لابی — همه سالن‌ها ──\n";
$lobbyScreen = $screenHall;
$lobbyScreen['venue_id'] = null;
$lobby = $svc->resolve(['item_type'=>'event_board','ref_id'=>null], $lobbyScreen);
$lobbyTitles = array_column($lobby['events'] ?? [], 'title');
check('تابلوی لابی رویداد همه سالن‌ها را نشان می‌دهد',
    in_array('SVTEST مهمانی رستوران', $lobbyTitles, true), implode('، ', $lobbyTitles));
check('نام سالن کنار هر رویداد آمد',
    !empty(array_values(array_filter($lobby['events'], fn($e) => $e['title'] === 'SVTEST مهمانی رستوران'))[0]['venue_name'] ?? ''));

// ── ۴) منوی تصویری روی صفحه رستوران ─────────────────────────────
echo "\n── ۴) منوی تصویری روی signage ──\n";
$menu = $svc->resolve(['item_type'=>'menu_board','ref_id'=>null], $screenRest);
check('منوی پیش‌فرض محل پیدا شد', $menu !== null, 'null برگشت');
check('عنوان منو درست است', ($menu['title'] ?? '') === 'SVTEST منوی شام');
check('هر دو صفحه آمدند', count($menu['pages'] ?? []) === 2, 'تعداد=' . count($menu['pages'] ?? []));

$noMenu = $svc->resolve(['item_type'=>'menu_board','ref_id'=>null], $screenHall);
check('صفحه بدون منو چیزی برنمی‌گرداند', $noMenu === null);

// ── ۵) اطلاعات محل ──────────────────────────────────────────────
echo "\n── ۵) اطلاعات محل ──\n";
$info = $svc->resolve(['item_type'=>'venue_info','ref_id'=>null], $screenRest);
check('اطلاعات رستوران برگشت', $info !== null);
check('ساعت کاری آمد', ($info['open_from'] ?? '') === '07:00:00');
check('وضعیت باز/بسته محاسبه شد', array_key_exists('open_now', $info ?? []));

// ── ۶) دفترچه تلفن روی signage ──────────────────────────────────
echo "\n── ۶) دفترچه تلفن ──\n";
$dir = $svc->resolve(['item_type'=>'directory','ref_id'=>null], $screenRest);
check('دفترچه تلفن روی صفحه عمومی در دسترس است', $dir !== null && count($dir['items'] ?? []) > 0);

// ── ۷) تداخل رزرو سالن ──────────────────────────────────────────
echo "\n── ۷) تداخل رزرو سالن ──\n";
$overlapSql = "SELECT id, title FROM hotel_events
                WHERE tenant_id=1 AND venue_id=? AND id<>0
                  AND is_active=1 AND status NOT IN ('cancelled','ended')
                  AND start_at < ?
                  AND COALESCE(end_at, DATE_ADD(start_at, INTERVAL 2 HOUR)) > ?";
// بازه‌ای که با جلسه هیئت مدیره هم‌پوشانی دارد
$clash = $db->row($overlapSql, [$hall, date('Y-m-d H:i:s', time() + 600), date('Y-m-d H:i:s', time() - 600)]);
check('تداخل با رویداد جاری تشخیص داده شد', $clash !== null, json_encode($clash, JSON_UNESCAPED_UNICODE));

// بازه‌ای که تداخل ندارد
$free = $db->row($overlapSql, [$hall, date('Y-m-d H:i:s', time() + 86400 + 3600), date('Y-m-d H:i:s', time() + 86400)]);
check('بازه آزاد تداخل ندارد', $free === null);

// ── ۸) محتوای پویا در پلی‌لیست ──────────────────────────────────
echo "\n── ۸) پلی‌لیست با آیتم پویا ──\n";
$db->query("DELETE FROM playlists WHERE tenant_id=1 AND name LIKE 'SVTEST%'");
// playlists.created_by مقدار پیش‌فرض ندارد
$anyUser = (int)($db->value('SELECT id FROM users WHERE tenant_id=1 ORDER BY id LIMIT 1') ?? 0);
if (!$anyUser) {
    $anyUser = (int)$db->insert('users', [
        'tenant_id'=>1,'name'=>'SVTEST user','email'=>'svtest@local',
        'password'=>password_hash('x', PASSWORD_BCRYPT),'role'=>'admin','is_active'=>1,
    ]);
}
$plId = (int)$db->insert('playlists', [
    'tenant_id'=>1,'name'=>'SVTEST پلی‌لیست لابی','is_active'=>1,'created_by'=>$anyUser,
]);

$db->insert('playlist_items', ['playlist_id'=>$plId,'item_type'=>'event_board','duration'=>20,'sort_order'=>1,'is_active'=>1]);
$db->insert('playlist_items', ['playlist_id'=>$plId,'item_type'=>'directory','duration'=>15,'sort_order'=>2,'is_active'=>1]);
// آیتمی که چیزی برای نمایش ندارد — باید حذف شود
$db->insert('playlist_items', ['playlist_id'=>$plId,'item_type'=>'menu_board','duration'=>15,'sort_order'=>3,'is_active'=>1]);

$model = new App\Models\Playlist();
$out   = $model->getForPlayer($plId, $screenHall);

$types = array_column($out['items'] ?? [], 'item_type');
check('پلی‌لیست ساخته شد', !empty($out['items']), json_encode($out, JSON_UNESCAPED_UNICODE));
check('تابلوی رویداد در خروجی هست', in_array('event_board', $types, true), implode('، ', $types));
check('دفترچه تلفن در خروجی هست', in_array('directory', $types, true));
check('آیتم بدون محتوا حذف شد (منو برای سالن وجود ندارد)',
    !in_array('menu_board', $types, true), implode('، ', $types));

$evItem = array_values(array_filter($out['items'], fn($i) => $i['item_type'] === 'event_board'))[0] ?? [];
check('آیتم پویا type=dynamic دارد', ($evItem['type'] ?? '') === 'dynamic');
check('محتوای آیتم همراهش آمد', !empty($evItem['content']['events'] ?? []));

// همان پلی‌لیست روی صفحه رستوران — منو باید بیاید
$outRest = $model->getForPlayer($plId, $screenRest);
$typesRest = array_column($outRest['items'] ?? [], 'item_type');
check('همان پلی‌لیست روی رستوران، منو را نشان می‌دهد',
    in_array('menu_board', $typesRest, true), implode('، ', $typesRest));

// بدون صفحه — آیتم پویا نباید کرش کند
$outNoScreen = $model->getForPlayer($plId, null);
check('بدون صفحه، آیتم‌های پویا حذف می‌شوند نه کرش',
    count(array_filter($outNoScreen['items'] ?? [], fn($i) => ($i['item_type'] ?? '') !== 'media')) === 0);

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";

// پاک‌سازی
$db->query('DELETE FROM playlist_items WHERE playlist_id=?', [$plId]);
$db->query('DELETE FROM playlists WHERE id=?', [$plId]);
$db->query("DELETE FROM hotel_events WHERE tenant_id=1 AND title LIKE 'SVTEST%'");
$db->query("DELETE FROM screens WHERE code IN ('SVHALL','SVREST')");
$db->query('DELETE FROM menu_board_pages WHERE board_id=?', [$board]);
$db->query('DELETE FROM menu_boards WHERE id=?', [$board]);
$db->query('DELETE FROM venues WHERE id IN (?, ?)', [$hall, $rest]);

exit($fail === 0 ? 0 : 1);
