<?php
/**
 * تست پورتال مهمان: برندینگ، منوی چندزبانه، میانبر عددی، خوش‌آمدگویی،
 * انتخاب منو (صفحه ← گروه ← پیش‌فرض) و کش داده‌های زنده.
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

$PHP    = getenv('TEST_PHP') ?: PHP_BINARY;
$INI    = getenv('TEST_INI') ?: (php_ini_loaded_file() ?: '');
$WORKER = ROOT_PATH . '/tests/Support/controller-worker.php';

/** کنترلر پورتال را در پروسه‌ی جدا صدا می‌زند (Response در پایان exit می‌زند) */
function portal(string $method, array $params, array $query = []): array {
    global $PHP, $INI, $WORKER;

    // پارامترهای GET از طریق محیط به worker می‌رسند
    $qs = $query ? ' ' . base64_encode(json_encode($query)) : '';

    $cmd = escapeshellarg($PHP)
         . ($INI ? ' -c ' . escapeshellarg($INI) : '')
         . ' ' . escapeshellarg($WORKER)
         . ' PortalController ' . escapeshellarg($method)
         . ' ' . base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE))
         . ' ' . base64_encode(json_encode([], JSON_UNESCAPED_UNICODE))
         . $qs . ' 2>&1';

    $out = shell_exec($cmd) ?? '';
    if (preg_match('/##RESULT##(.*?)##END##/s', $out, $m)) {
        return json_decode($m[1], true) ?? ['status' => 0, 'body' => []];
    }
    return ['status' => 0, 'body' => ['raw' => substr($out, 0, 400)]];
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
$db->query("DELETE FROM portal_live_cache WHERE tenant_id = 1");
$db->query("DELETE FROM screens    WHERE code IN ('PTEST01','PTEST02','PTEST03')");
$db->query("DELETE FROM iptv_rooms WHERE room_number = 'P101'");
foreach ($db->rows("SELECT id FROM iptv_menus WHERE tenant_id=1 AND name LIKE 'PTEST%'") as $m) {
    $db->query('DELETE FROM iptv_menu_items       WHERE menu_id=?', [(int)$m['id']]);
    $db->query('DELETE FROM iptv_menu_backgrounds WHERE menu_id=?', [(int)$m['id']]);
}
$db->query("DELETE FROM iptv_menus WHERE tenant_id=1 AND name LIKE 'PTEST%'");

$menuId = (int)$db->insert('iptv_menus', [
    'tenant_id' => 1, 'name' => 'PTEST منوی اصلی', 'is_active' => 1, 'sort_order' => 0,
    'logo_url' => '/uploads/logo.png', 'accent_color' => '#0ea5e9',
    'bg_image' => '/uploads/bg1.jpg', 'bg_dim' => 0.40, 'bg_blur' => 4,
    'welcome_title' => 'به هتل تست خوش آمدید', 'welcome_sub' => 'اقامتی خوش داشته باشید',
    'ticker_text' => 'صبحانه ۷ تا ۱۰ صبح', 'ticker_color' => '#ffffff',
    'ticker_bg' => '#111111', 'ticker_speed' => 30,
    'header_widgets' => 'clock,weather,prayer', 'show_guest_name' => 1, 'city' => 'Kish',
]);

// اسلایدشو
foreach (['/uploads/s1.jpg', '/uploads/s2.jpg', '/uploads/s3.jpg'] as $i => $img) {
    $db->insert('iptv_menu_backgrounds', ['menu_id' => $menuId, 'image_url' => $img, 'sort_order' => $i]);
}

// آیتم‌ها — شامل دو میانبر تکراری و یک عدد نامعتبر
$db->insert('iptv_menu_items', ['menu_id'=>$menuId,'type'=>'live','label'=>'تلویزیون زنده','label_en'=>'Live TV','icon'=>'fas fa-tv','color'=>'#ef4444','shortcut_key'=>1,'sort_order'=>1,'is_active'=>1]);
$db->insert('iptv_menu_items', ['menu_id'=>$menuId,'type'=>'vod','label'=>'فیلم و سریال','label_en'=>'Movies','icon'=>'fas fa-film','color'=>'#a855f7','shortcut_key'=>2,'sort_order'=>2,'is_active'=>1]);
$db->insert('iptv_menu_items', ['menu_id'=>$menuId,'type'=>'hotel','label'=>'خدمات اتاق','icon'=>'fas fa-bell','color'=>'#f59e0b','shortcut_key'=>2,'sort_order'=>3,'is_active'=>1]); // تکراری
$db->insert('iptv_menu_items', ['menu_id'=>$menuId,'type'=>'info','label'=>'اطلاعات','icon'=>'fas fa-info','color'=>'#22c55e','shortcut_key'=>42,'sort_order'=>4,'is_active'=>1]); // نامعتبر
$db->insert('iptv_menu_items', ['menu_id'=>$menuId,'type'=>'url','label'=>'غیرفعال','icon'=>'fas fa-x','color'=>'#000','sort_order'=>5,'is_active'=>0]);

$roomId = (int)$db->insert('iptv_rooms', [
    'tenant_id' => 1, 'room_number' => 'P101', 'room_name' => 'سوئیت تست',
    'status' => 'occupied', 'guest_name' => 'آقای مهمان', 'guest_lang' => 'en',
    'check_in_at' => date('Y-m-d H:i:s', time() - 3600),
]);
$db->insert('screens', ['tenant_id'=>1,'name'=>'TV اتاق P101','code'=>'PTEST01','iptv_room_id'=>$roomId,'iptv_menu_id'=>$menuId]);
// صفحه‌ای بدون اتاق و بدون منوی مستقیم — باید منوی پیش‌فرض tenant را بگیرد
$db->insert('screens', ['tenant_id'=>1,'name'=>'TV لابی','code'=>'PTEST02']);

// ── ۱) صفحه اصلی ─────────────────────────────────────────────────
echo "\n── ۱) صفحه اصلی تلویزیون اتاق ──\n";
$r = portal('home', ['code' => 'PTEST01']);
$d = $r['body']['data'] ?? [];
check('پاسخ ۲۰۰ برگشت', $r['status'] === 200, json_encode($r, JSON_UNESCAPED_UNICODE));
check('کد صفحه‌نمایش درست است', ($d['screen']['code'] ?? '') === 'PTEST01');

echo "\n── ۲) اتاق و مهمان ──\n";
check('شماره اتاق برگشت', ($d['room']['room_number'] ?? '') === 'P101');
check('اتاق اشغال تشخیص داده شد', ($d['room']['occupied'] ?? false) === true);
check('نام مهمان برای خوش‌آمدگویی آمد', ($d['room']['guest_name'] ?? '') === 'آقای مهمان');

echo "\n── ۳) برندینگ ──\n";
$b = $d['branding'] ?? [];
check('لوگو برگشت',        ($b['logo_url'] ?? '') === '/uploads/logo.png');
check('رنگ accent برگشت',  ($b['accent_color'] ?? '') === '#0ea5e9');
check('تاری و تیرگی پس‌زمینه', (float)($b['bg_dim'] ?? 0) === 0.40 && (int)($b['bg_blur'] ?? 0) === 4);
check('اسلایدشو ۳ تصویر دارد', count($b['backgrounds'] ?? []) === 3, 'تعداد=' . count($b['backgrounds'] ?? []));
check('تیکر با رنگ و سرعت برگشت',
    ($b['ticker']['text'] ?? '') === 'صبحانه ۷ تا ۱۰ صبح' && (int)($b['ticker']['speed'] ?? 0) === 30);

echo "\n── ۴) منو و میانبر عددی ──\n";
$items = $d['menu'] ?? [];
check('آیتم غیرفعال حذف شد (۴ آیتم)', count($items) === 4, 'تعداد=' . count($items));

$byLabel = [];
foreach ($items as $it) $byLabel[$it['label']] = $it;

// زبان مهمان en است، پس label_en باید استفاده شود
check('برچسب انگلیسی برای مهمان انگلیسی‌زبان', isset($byLabel['Live TV']), 'برچسب‌ها: ' . implode('، ', array_keys($byLabel)));
check('آیتم بدون label_en به فارسی ماند', isset($byLabel['خدمات اتاق']));

$shortcuts = array_column($items, 'shortcut_key');
check('میانبر ۱ به تلویزیون زنده',  ($byLabel['Live TV']['shortcut_key'] ?? null) === 1);
// نکته: ?? مقدار null را هم می‌گیرد، پس باید صریح با array_key_exists چک شود
check('میانبر تکراری ۲ حذف شد',
    array_key_exists('shortcut_key', $byLabel['خدمات اتاق']) && $byLabel['خدمات اتاق']['shortcut_key'] === null,
    'مقدار=' . var_export($byLabel['خدمات اتاق']['shortcut_key'] ?? 'missing', true));
check('عدد نامعتبر ۴۲ حذف شد',
    array_key_exists('shortcut_key', $byLabel['اطلاعات']) && $byLabel['اطلاعات']['shortcut_key'] === null,
    'مقدار=' . var_export($byLabel['اطلاعات']['shortcut_key'] ?? 'missing', true));
check('هیچ میانبر تکراری نماند',
    count(array_filter($shortcuts, 'is_int')) === count(array_unique(array_filter($shortcuts, 'is_int'))));

echo "\n── ۵) زبان و ترجمه ──\n";
check('زبان مهمان (en) اعمال شد', ($d['lang'] ?? '') === 'en', 'lang=' . ($d['lang'] ?? '?'));
check('متن انگلیسی برگشت', ($d['strings']['welcome'] ?? '') === 'Welcome', json_encode($d['strings'] ?? [], JSON_UNESCAPED_UNICODE));

$rFa = portal('home', ['code' => 'PTEST01'], ['lang' => 'fa']);
$dFa = $rFa['body']['data'] ?? [];
check('پارامتر lang زبان اتاق را override می‌کند', ($dFa['lang'] ?? '') === 'fa', 'lang=' . ($dFa['lang'] ?? '?'));
check('متن فارسی برگشت', ($dFa['strings']['welcome'] ?? '') === 'خوش آمدید');
check('برچسب فارسی منو برگشت', in_array('تلویزیون زنده', array_column($dFa['menu'] ?? [], 'label'), true));

$rAr = portal('home', ['code' => 'PTEST01'], ['lang' => 'ar']);
$dAr = $rAr['body']['data'] ?? [];
check('عربی پشتیبانی می‌شود', ($dAr['strings']['welcome'] ?? '') === 'أهلا وسهلا');

// زبانی که ترجمه ندارد باید به فارسی برگردد نه خالی بماند
$rDe = portal('home', ['code' => 'PTEST01'], ['lang' => 'de']);
$dDe = $rDe['body']['data'] ?? [];
check('زبان بدون ترجمه به فارسی fallback شد', ($dDe['strings']['welcome'] ?? '') === 'خوش آمدید');

echo "\n── ۶) ویجت‌های نوار بالا ──\n";
$w = $d['header']['widgets'] ?? [];
check('سه ویجت تنظیم‌شده برگشت', $w === ['clock', 'weather', 'prayer'], json_encode($w));
check('ساعت سرور برگشت', !empty($d['header']['data']['server_time']));

echo "\n── ۷) صفحه بدون اتاق ──\n";
$r2 = portal('home', ['code' => 'PTEST02']);
$d2 = $r2['body']['data'] ?? [];
check('پاسخ ۲۰۰ برگشت', $r2['status'] === 200);
check('اتاق خالی است ولی خطا نداد', ($d2['room']['occupied'] ?? true) === false);
check('نام مهمان null است',
    array_key_exists('guest_name', $d2['room'] ?? []) && $d2['room']['guest_name'] === null,
    'مقدار=' . var_export($d2['room']['guest_name'] ?? 'missing', true));
check('منوی پیش‌فرض tenant پیدا شد', count($d2['menu'] ?? []) === 4, 'تعداد=' . count($d2['menu'] ?? []));

echo "\n── ۸) کد نامعتبر ──\n";
$r3 = portal('home', ['code' => 'NOSUCH']);
check('کد ناشناس ۴۰۴ داد', $r3['status'] === 404, "status={$r3['status']}");

echo "\n── ۹) endpoint داده زنده ──\n";
$r4 = portal('live', ['code' => 'PTEST01']);
check('live پاسخ ۲۰۰ داد', $r4['status'] === 200);
check('server_time دارد', !empty($r4['body']['data']['server_time']));

echo "\n── ۱۰) خاموش کردن خوش‌آمدگویی با نام ──\n";
$db->update('iptv_menus', ['show_guest_name' => 0], ['id' => $menuId]);
$r5 = portal('home', ['code' => 'PTEST01']);
$room5 = $r5['body']['data']['room'] ?? [];
check('با خاموش بودن گزینه، نام مهمان فرستاده نشد',
    array_key_exists('guest_name', $room5) && $room5['guest_name'] === null,
    'مقدار=' . var_export($room5['guest_name'] ?? 'missing', true));
check('ولی شماره اتاق هنوز هست', ($r5['body']['data']['room']['room_number'] ?? '') === 'P101');

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";

// پاک‌سازی
$db->query('DELETE FROM iptv_menu_items       WHERE menu_id=?', [$menuId]);
$db->query('DELETE FROM iptv_menu_backgrounds WHERE menu_id=?', [$menuId]);
$db->query('DELETE FROM iptv_menus            WHERE id=?',      [$menuId]);
$db->query("DELETE FROM screens    WHERE code IN ('PTEST01','PTEST02','PTEST03')");
$db->query('DELETE FROM iptv_rooms WHERE id=?', [$roomId]);
$db->query('DELETE FROM portal_live_cache WHERE tenant_id = 1');

exit($fail === 0 ? 0 : 1);
