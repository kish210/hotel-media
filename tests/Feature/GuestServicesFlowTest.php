<?php
/**
 * تست end-to-end گردش‌کار خدمات مهمان روی MariaDB واقعی.
 * هر فراخوانی کنترلر در یک پروسه‌ی جدا اجرا می‌شود چون Response در پایان exit می‌زند.
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

// همان مفسری که این تست را اجرا می‌کند، با همان php.ini — تا افزونه‌ها یکسان باشند
$PHP    = getenv('TEST_PHP') ?: PHP_BINARY;
$INI    = getenv('TEST_INI') ?: (php_ini_loaded_file() ?: '');
$WORKER = ROOT_PATH . '/tests/Support/controller-worker.php';

/** کنترلر پورتال مهمان را در پروسه‌ی جدا صدا می‌زند */
function portal(string $method, array $params, array $body = []): array {
    global $PHP, $INI, $WORKER;
    $cmd = escapeshellarg($PHP)
         . ($INI ? ' -c ' . escapeshellarg($INI) : '')
         . ' ' . escapeshellarg($WORKER)
         . ' GuestPortalController ' . escapeshellarg($method)
         . ' ' . base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE))
         . ' ' . base64_encode(json_encode($body,   JSON_UNESCAPED_UNICODE))
         . ' 2>&1';

    $out = shell_exec($cmd) ?? '';
    if (preg_match('/##RESULT##(.*?)##END##/s', $out, $m)) {
        return json_decode($m[1], true) ?? ['status' => 0, 'body' => []];
    }
    return ['status' => 0, 'body' => ['raw' => substr($out, 0, 300)]];
}

$db = App\Core\Database::getInstance();

// ── آماده‌سازی ───────────────────────────────────────────────────
$db->query("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1,'Test Hotel','test')");
foreach (['guest_request_items', 'guest_request_log', 'guest_requests'] as $t) $db->query("DELETE FROM $t");
$db->query("DELETE FROM screens    WHERE code='TSCR01'");
$db->query("DELETE FROM iptv_rooms WHERE room_number='T101'");

$roomId = (int)$db->insert('iptv_rooms', [
    'tenant_id' => 1, 'room_number' => 'T101', 'room_name' => 'Test Suite',
    'status' => 'occupied', 'guest_name' => 'آقای تست', 'guest_lang' => 'fa',
    'check_in_at' => date('Y-m-d H:i:s', time() - 3600),
]);
$screenId = (int)$db->insert('screens', [
    'tenant_id' => 1, 'name' => 'TV اتاق ۱۰۱', 'code' => 'TSCR01', 'iptv_room_id' => $roomId,
]);

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✅ $label\n"; }
    else     { $fail++; echo "  ❌ $label" . ($detail ? "\n       → $detail" : '') . "\n"; }
}

echo "\n── ۱) کاتالوگ سرویس‌ها از تلویزیون اتاق ──\n";
$r    = portal('services', ['code' => 'TSCR01']);
$data = $r['body']['data'] ?? [];
$cats = $data['categories'] ?? [];
check('کاتالوگ برگشت', !empty($cats), json_encode($r, JSON_UNESCAPED_UNICODE));
check('نام مهمان درست است', ($data['guest_name'] ?? '') === 'آقای تست');
// داده اولیه: housekeeping، maintenance، laundry، taxi، breakfast
check('۵ دسته سرویس دارد', count($cats) === 5, 'تعداد=' . count($cats));
check('available_now محاسبه شد', isset($cats['housekeeping'][0]['available_now']));

echo "\n── ۲) ثبت سفارش خشک‌شویی (۲ پیراهن) ──\n";
$shirt = $db->row("SELECT id, price FROM guest_services WHERE category='laundry' AND name_fa LIKE '%پیراهن%'");
$r = portal('store', ['code' => 'TSCR01'], [
    'category' => 'laundry',
    'items'    => [['service_id' => (int)$shirt['id'], 'qty' => 2]],
    'note'     => 'تا فردا صبح',
]);
$reqId = (int)($r['body']['data']['id'] ?? 0);
check('سفارش ثبت شد (۲۰۱)', $r['status'] === 201 && $reqId > 0, json_encode($r, JSON_UNESCAPED_UNICODE));

$want = (float)$shirt['price'] * 2;
check("قیمت سمت سرور محاسبه شد ($want)", (float)($r['body']['data']['total_price'] ?? 0) === $want);
$items = $db->rows('SELECT * FROM guest_request_items WHERE request_id=?', [$reqId]);
check('قلم سفارش ذخیره شد با snapshot نام', count($items) === 1 && $items[0]['name_snapshot'] === $shirt ? true : count($items) === 1);
check('لاگ وضعیت اولیه ثبت شد', (int)$db->value('SELECT COUNT(*) FROM guest_request_log WHERE request_id=?', [$reqId]) === 1);

echo "\n── ۳) دستکاری قیمت توسط کلاینت نباید اثر کند ──\n";
$r = portal('store', ['code' => 'TSCR01'], [
    'category' => 'laundry',
    'items'    => [['service_id' => (int)$shirt['id'], 'qty' => 1, 'unit_price' => 1, 'line_total' => 1]],
]);
check('قیمت جعلی نادیده گرفته شد', (float)($r['body']['data']['total_price'] ?? 0) === (float)$shirt['price'],
    'total=' . ($r['body']['data']['total_price'] ?? '?'));

echo "\n── ۴) اعتبارسنجی ──\n";
$db->query('DELETE FROM guest_requests WHERE room_id=?', [$roomId]);

$cases = [
    ['خشک‌شویی بدون قلم رد شد',      ['category' => 'laundry', 'items' => []], 422],
    ['بیدارباش بدون زمان رد شد',      ['category' => 'wakeup'], 422],
    ['بیدارباش در گذشته رد شد',       ['category' => 'wakeup', 'scheduled_at' => date('Y-m-d H:i:s', time() - 7200)], 422],
    ['امتیاز خارج از ۱-۵ رد شد',      ['category' => 'feedback', 'rating' => 9], 422],
    ['دسته نامعتبر رد شد',            ['category' => 'hacking'], 422],
    ['service_id ناموجود رد شد',      ['category' => 'laundry', 'items' => [['service_id' => 99999, 'qty' => 1]]], 422],
];
foreach ($cases as [$label, $body, $expect]) {
    $rr = portal('store', ['code' => 'TSCR01'], $body);
    check("$label ($expect)", $rr['status'] === $expect, "status={$rr['status']}");
}

$rr = portal('store', ['code' => 'NOPE'], ['category' => 'taxi', 'note' => 'x']);
check('کد صفحه‌نمایش نامعتبر ۴۰۴ داد', $rr['status'] === 404, "status={$rr['status']}");

echo "\n── ۵) اتاق بدون مهمان نباید سفارش بدهد ──\n";
$db->update('iptv_rooms', ['status' => 'available'], ['id' => $roomId]);
$rr = portal('store', ['code' => 'TSCR01'], ['category' => 'taxi', 'note' => 'x']);
check('اتاق خالی رد شد (409)', $rr['status'] === 409, "status={$rr['status']}");
$db->update('iptv_rooms', ['status' => 'occupied'], ['id' => $roomId]);

echo "\n── ۶) محدودیت spam ──\n";
$db->query('DELETE FROM guest_requests WHERE room_id=?', [$roomId]);
$codes = [];
for ($i = 0; $i < 7; $i++) {
    $rr = portal('store', ['code' => 'TSCR01'], ['category' => 'taxi', 'note' => "req $i"]);
    $codes[] = $rr['status'];
}
check('بعد از ۵ درخواست در ۱۰ دقیقه ۴۲۹ برگشت', in_array(429, $codes, true), implode(',', $codes));

echo "\n── ۷) لغو توسط مهمان ──\n";
$db->query('DELETE FROM guest_requests WHERE room_id=?', [$roomId]);
$cid = (int)$db->insert('guest_requests', ['tenant_id' => 1, 'room_id' => $roomId, 'category' => 'taxi', 'status' => 'pending']);
$rr  = portal('cancel', ['code' => 'TSCR01', 'id' => $cid]);
$row = $db->row('SELECT status FROM guest_requests WHERE id=?', [$cid]);
check('لغو درخواست pending کار کرد', ($row['status'] ?? '') === 'cancelled', json_encode($rr, JSON_UNESCAPED_UNICODE));

$db->update('guest_requests', ['status' => 'in_progress'], ['id' => $cid]);
$rr = portal('cancel', ['code' => 'TSCR01', 'id' => $cid]);
check('لغو درخواستِ در حال انجام رد شد (409)', $rr['status'] === 409, "status={$rr['status']}");

echo "\n── ۸) جداسازی اقامت ──\n";
$db->query('DELETE FROM guest_requests WHERE room_id=?', [$roomId]);
$db->query("INSERT INTO guest_requests (tenant_id, room_id, category, status, created_at)
            VALUES (1, ?, 'taxi', 'done', DATE_SUB(NOW(), INTERVAL 5 DAY))", [$roomId]);
$db->insert('guest_requests', ['tenant_id' => 1, 'room_id' => $roomId, 'category' => 'taxi', 'status' => 'pending']);
$rr = portal('myRequests', ['code' => 'TSCR01']);
$n  = count($rr['body']['data'] ?? []);
check('سفارش مهمان قبلی دیده نشد (۱ از ۲)', $n === 1, "تعداد=$n");

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";

// پاک‌سازی
$db->query('DELETE FROM guest_requests WHERE room_id=?', [$roomId]);
$db->query('DELETE FROM screens WHERE id=?',    [$screenId]);
$db->query('DELETE FROM iptv_rooms WHERE id=?', [$roomId]);

exit($fail === 0 ? 0 : 1);
