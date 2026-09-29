<?php
/**
 * تست رزرو رستوران و امکانات — TODO ۲.۱۳ و ۲.۱۴
 *
 * چیزهایی که اگر غلط باشند مهمان یا رستوران آسیب می‌بیند:
 * بیش‌فروش ظرفیت، رزرو ساعت گذشته، قلم صورتحساب که با لغو باطل نشود،
 * و نوبت بعد از نیمه‌شب که به روز اشتباه بچسبد.
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
function cleanup(App\Core\Database $db): void {
    foreach ($db->rows("SELECT id FROM venues WHERE tenant_id=1 AND name LIKE 'RSTEST%'") as $v) {
        $db->query('DELETE FROM reservations WHERE venue_id=?', [(int)$v['id']]);
    }
    $db->query("DELETE FROM venues WHERE tenant_id=1 AND name LIKE 'RSTEST%'");
    foreach ($db->rows("SELECT id FROM iptv_rooms WHERE tenant_id=1 AND room_number LIKE 'RS%'") as $r) {
        $db->query('DELETE FROM room_charges WHERE room_id=?', [(int)$r['id']]);
    }
    $db->query("DELETE FROM iptv_rooms WHERE tenant_id=1 AND room_number LIKE 'RS%'");
}

$db->query("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1,'Test Hotel','test')");
cleanup($db);

$checkIn = date('Y-m-d H:i:s', time() - 86400);
$room  = (int)$db->insert('iptv_rooms', ['tenant_id'=>1,'room_number'=>'RS101','status'=>'occupied','guest_name'=>'آقای رزرو','check_in_at'=>$checkIn]);
$room2 = (int)$db->insert('iptv_rooms', ['tenant_id'=>1,'room_number'=>'RS102','status'=>'occupied','guest_name'=>'خانم دوم','check_in_at'=>$checkIn]);
$empty = (int)$db->insert('iptv_rooms', ['tenant_id'=>1,'room_number'=>'RS103','status'=>'available']);

// رستوران ۴ نفره، ناهار ۱۲ تا ۱۵، نوبت یک‌ساعته، نفری ۱۰۰ هزار
$rest = (int)$db->insert('venues', [
    'tenant_id'=>1,'kind'=>'restaurant','name'=>'RSTEST رستوران','capacity'=>4,
    'open_from'=>'12:00:00','open_to'=>'15:00:00','is_active'=>1,
    'bookable'=>1,'slot_minutes'=>60,'max_party'=>4,'booking_price'=>100000,'days_ahead'=>3,
]);
// کافی‌شاپ ۱۸ تا ۲ بامداد — از نیمه‌شب رد می‌شود
$cafe = (int)$db->insert('venues', [
    'tenant_id'=>1,'kind'=>'cafe','name'=>'RSTEST کافه','capacity'=>10,
    'open_from'=>'18:00:00','open_to'=>'02:00:00','is_active'=>1,
    'bookable'=>1,'slot_minutes'=>120,'max_party'=>6,'days_ahead'=>3,
]);
// محل رزرونپذیر
$lobby = (int)$db->insert('venues', [
    'tenant_id'=>1,'kind'=>'lobby','name'=>'RSTEST لابی','open_from'=>'00:00:00','open_to'=>'23:00:00','is_active'=>1,
]);

$svc  = new App\Services\ReservationService($db);
$now  = new DateTimeImmutable('today 08:00');
$tmrw = $now->modify('+1 day')->format('Y-m-d');

// ── ۱) نوبت‌ها ──────────────────────────────────────────────────
echo "\n── ۱) نوبت‌ها ──\n";
$s = $svc->slots(1, $rest, $tmrw, $now);
check('سه نوبت ناهار ساخته شد', $s['ok'] && count($s['slots']) === 3, json_encode($s, JSON_UNESCAPED_UNICODE));
check('نوبت اول ۱۲:۰۰ است', ($s['slots'][0]['label'] ?? '') === '12:00');
check('ظرفیت خالی ۴ است', ($s['slots'][0]['remaining'] ?? null) === 4);

$c = $svc->slots(1, $cafe, $tmrw, $now);
$labels = array_column($c['slots'], 'label');
check('کافه‌ی شبانه نوبت ۰۰:۰۰ دارد', in_array('00:00', $labels, true), implode(',', $labels));
check('نوبت کافه تا ساعت بسته شدن ادامه دارد نه بعد از آن', count($c['slots']) === 4, implode(',', $labels));

check('محل رزرونپذیر نوبت ندارد', !$svc->slots(1, $lobby, $tmrw, $now)['ok']);
check('روز گذشته رد شد', !$svc->slots(1, $rest, $now->modify('-1 day')->format('Y-m-d'), $now)['ok']);
check('بیش از days_ahead رد شد', !$svc->slots(1, $rest, $now->modify('+4 days')->format('Y-m-d'), $now)['ok']);

// ── ۲) ظرفیت ────────────────────────────────────────────────────
echo "\n── ۲) ظرفیت ──\n";
$at = "$tmrw 12:00:00";
$r1 = $svc->create(1, ['venue_id'=>$rest,'start_at'=>$at,'party_size'=>3,'room_id'=>$room], 'tv', null, $now);
check('رزرو سه‌نفره ثبت شد', $r1['ok'], $r1['message']);

$r2 = $svc->create(1, ['venue_id'=>$rest,'start_at'=>$at,'party_size'=>2,'room_id'=>$room2], 'tv', null, $now);
check('دو نفر دیگر در همان نوبت رد شد', !$r2['ok'] && $r2['code'] === 409, $r2['message']);
check('پیام تعداد جای باقی‌مانده را می‌گوید', str_contains($r2['message'], '1'), $r2['message']);

$r3 = $svc->create(1, ['venue_id'=>$rest,'start_at'=>$at,'party_size'=>1,'room_id'=>$room2], 'tv', null, $now);
check('یک نفر آخر جا گرفت', $r3['ok'], $r3['message']);
$s = $svc->slots(1, $rest, $tmrw, $now);
check('نوبت پر، available=false', ($s['slots'][0]['available'] ?? true) === false && $s['slots'][0]['remaining'] === 0);
check('نوبت بعدی دست‌نخورده', ($s['slots'][1]['remaining'] ?? null) === 4);

/* مدیر طول نوبت را به ۹۰ دقیقه عوض می‌کند. رزرو ۱۲:۰۰–۱۳:۰۰ حالا با
   نوبت ۱۲:۰۰–۱۳:۳۰ هم‌پوشانی دارد و باید همچنان حساب شود. */
$db->update('venues', ['slot_minutes'=>90], ['id'=>$rest]);
$s = $svc->slots(1, $rest, $tmrw, $now);
check('بعد از تغییر طول نوبت، رزروهای قبلی هنوز جا می‌گیرند', ($s['slots'][0]['remaining'] ?? null) === 0,
      json_encode($s['slots'][0] ?? [], JSON_UNESCAPED_UNICODE));
$db->update('venues', ['slot_minutes'=>60], ['id'=>$rest]);

// ── ۳) اعتبارسنجی ───────────────────────────────────────────────
echo "\n── ۳) اعتبارسنجی ──\n";
$bad = fn(array $d, string $src = 'tv') => $svc->create(1, $d + ['venue_id'=>$rest,'party_size'=>1,'room_id'=>$room], $src, null, $now);

check('ساعت خارج از شبکه (۱۲:۳۰) رد شد', !$bad(['start_at'=>"$tmrw 12:30:00"])['ok']);
check('نوبت امروزِ گذشته رد شد',
      !$svc->create(1, ['venue_id'=>$rest,'start_at'=>$now->format('Y-m-d') . ' 12:00:00','party_size'=>1,'room_id'=>$room],
                    'tv', null, $now->setTime(13, 30))['ok']);
check('بیش از max_party رد شد', !$bad(['start_at'=>"$tmrw 13:00:00",'party_size'=>5])['ok']);
check('صفر نفر رد شد', !$bad(['start_at'=>"$tmrw 13:00:00",'party_size'=>0])['ok']);
check('اتاق خالی رد شد', ($bad(['start_at'=>"$tmrw 13:00:00",'room_id'=>$empty])['code'] ?? 0) === 409);
check('محل رزرونپذیر رد شد', !$bad(['start_at'=>"$tmrw 13:00:00",'venue_id'=>$lobby])['ok']);
check('رزرو تکراری همان اتاق در همان نوبت رد شد', !$bad(['start_at'=>$at])['ok']);
check('از تلویزیون بدون اتاق رد شد',
      !$svc->create(1, ['venue_id'=>$rest,'start_at'=>"$tmrw 13:00:00",'party_size'=>1], 'tv', null, $now)['ok']);
check('مهمان بیرونی بدون نام رد شد',
      !$svc->create(1, ['venue_id'=>$rest,'start_at'=>"$tmrw 13:00:00",'party_size'=>1], 'panel', null, $now)['ok']);
$walk = $svc->create(1, ['venue_id'=>$rest,'start_at'=>"$tmrw 13:00:00",'party_size'=>2,'guest_name'=>'مهمان بیرونی'], 'panel', null, $now);
check('مهمان بیرونی با نام از پنل ثبت شد', $walk['ok'], $walk['message']);

$night = $svc->create(1, ['venue_id'=>$cafe,'start_at'=>$now->modify('+2 day')->format('Y-m-d') . ' 00:00:00',
                          'party_size'=>2,'room_id'=>$room], 'tv', null, $now);
check('نوبت بعد از نیمه‌شب کافه ثبت شد', $night['ok'], $night['message']);

// سقف رزرو فعال هر اتاق
$svc->create(1, ['venue_id'=>$cafe,'start_at'=>"$tmrw 18:00:00",'party_size'=>1,'room_id'=>$room], 'tv', null, $now);
$over = $svc->create(1, ['venue_id'=>$cafe,'start_at'=>"$tmrw 20:00:00",'party_size'=>1,'room_id'=>$room], 'tv', null, $now);
check('رزرو چهارم فعال از تلویزیون رد شد', !$over['ok'] && $over['code'] === 429, $over['message']);

// ── ۴) تایید، صورتحساب و لغو ────────────────────────────────────
echo "\n── ۴) صورتحساب ──\n";
$id1 = (int)$r1['id'];
check('pending → completed مستقیم مجاز نیست', !$svc->setStatus(1, $id1, 'completed')['ok']);

$ok = $svc->setStatus(1, $id1, 'confirmed');
check('تایید شد', $ok['ok'], $ok['message']);
$row = $db->row('SELECT * FROM reservations WHERE id=?', [$id1]);
$charge = $row['charge_id'] ? $db->row('SELECT * FROM room_charges WHERE id=?', [(int)$row['charge_id']]) : null;
check('قلم صورتحساب ثبت شد', $charge !== null);
check('مبلغ = نفری ۱۰۰ هزار × ۳', (float)($charge['amount'] ?? 0) === 300000.0, (string)($charge['amount'] ?? ''));
check('منبع قلم reservation است', ($charge['source'] ?? '') === 'reservation');

// لغو مهمان در دو ساعت آخر
$late = $svc->cancelByGuest(1, $room, $id1, new DateTimeImmutable("$tmrw 11:00"));
check('لغو رزرو تاییدشده در کمتر از دو ساعت توسط مهمان رد شد', !$late['ok'], $late['message']);

$c1 = $svc->cancelByGuest(1, $room, $id1, $now);
check('لغو زودهنگام توسط مهمان پذیرفته شد', $c1['ok'], $c1['message']);
$voided = $db->value('SELECT status FROM room_charges WHERE id=?', [(int)$charge['id']]);
check('قلم صورتحساب باطل شد', $voided === 'void', (string)$voided);
check('مهمان دیگر نمی‌تواند رزرو دیگری را لغو کند',
      !$svc->cancelByGuest(1, $room, (int)$r3['id'], $now)['ok']);

$s = $svc->slots(1, $rest, $tmrw, $now);
check('بعد از لغو، سه جای آن رزرو آزاد شد', ($s['slots'][0]['remaining'] ?? null) === 3,
      json_encode($s['slots'][0] ?? [], JSON_UNESCAPED_UNICODE));

// رزرو رایگان قلم نمی‌سازد
$svc->setStatus(1, (int)$night['id'], 'confirmed');
check('رزرو رایگان قلمی نساخت',
      $db->value('SELECT charge_id FROM reservations WHERE id=?', [(int)$night['id']]) === null);

// ── ۵) فهرست اتاق ──────────────────────────────────────────────
echo "\n── ۵) فهرست اتاق ──\n";
$mine = $svc->forRoom(1, $room, $checkIn);
check('مهمان رزروهای خودش را می‌بیند', count($mine) >= 3, (string)count($mine));
check('رزرو اتاق دیگر در فهرست نیست',
      !in_array((int)$r3['id'], array_map('intval', array_column($mine, 'id')), true));
check('مهمان قبلی دیده نمی‌شود', count($svc->forRoom(1, $room, date('Y-m-d H:i:s', time() + 60))) === 0);

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";

cleanup($db);
exit($fail === 0 ? 0 : 1);
