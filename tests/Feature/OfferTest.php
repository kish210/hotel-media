<?php
/**
 * تست تخفیف کسب‌وکارهای محلی (TODO ۴.۳): صدور کد از تلویزیون، سقف هر
 * اقامت و سقف کل، تأیید یک‌باره، لینک کسب‌وکار که فقط کدهای خودش را
 * می‌پذیرد و بعد از حدس‌های اشتباه قفل می‌شود.
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
    $db->query("DELETE c FROM offer_claims c JOIN local_offers o ON o.id=c.offer_id WHERE o.business_name LIKE 'OFTEST%'");
    $db->query("DELETE FROM local_offers WHERE business_name LIKE 'OFTEST%'");
    $db->query("DELETE FROM screens WHERE code IN ('OFTEST','OFLOBBY')");
    $db->query("DELETE FROM iptv_rooms WHERE tenant_id=1 AND room_number IN ('OF1','OF2')");
}

$db->query("INSERT IGNORE INTO tenants (id, name, slug) VALUES (1,'Test Hotel','test')");
cleanup($db);

$svc  = new App\Services\OfferService($db);
$room = (int)$db->insert('iptv_rooms', ['tenant_id' => 1, 'room_number' => 'OF1', 'status' => 'occupied',
    'guest_name' => 'مهمان تست', 'check_in_at' => date('Y-m-d H:i:s', time() - 3600)]);
$db->insert('screens', ['tenant_id' => 1, 'name' => 'OF', 'code' => 'OFTEST', 'screen_type' => 'iptv', 'status' => 'active', 'iptv_room_id' => $room]);
$db->insert('screens', ['tenant_id' => 1, 'name' => 'Lobby', 'code' => 'OFLOBBY', 'screen_type' => 'iptv', 'status' => 'active']);

$mk = function (array $d) use ($svc) {
    $r = $svc->save(1, $d + ['is_active' => 1]);
    return (int)($r['id'] ?? 0);
};
$dinner  = $mk(['business_name' => 'OFTEST رستوران', 'category' => 'food', 'title' => '۲۰٪ شام', 'per_stay_limit' => 1]);
$limited = $mk(['business_name' => 'OFTEST تور', 'category' => 'tour', 'title' => 'تور جزیره', 'total_limit' => 1]);
$future  = $mk(['business_name' => 'OFTEST آینده', 'title' => 'بعداً', 'valid_from' => date('Y-m-d', time() + 5 * 86400)]);
$expired = $mk(['business_name' => 'OFTEST منقضی', 'title' => 'قدیمی', 'valid_to' => date('Y-m-d', time() - 86400)]);
$off     = $mk(['business_name' => 'OFTEST خاموش', 'title' => 'خاموش', 'is_active' => 0]);
$db->update('local_offers', ['is_active' => 0], ['id' => $off]);

$ids = fn(array $r) => array_map(fn($o) => (int)$o['id'], $r['body']['data']['offers'] ?? []);

// ── ۱) ثبت و اعتبارسنجی ─────────────────────────────────────────
echo "\n── ۱) ثبت تخفیف ──\n";
check('تخفیف‌ها ثبت شدند', $dinner && $limited && $future && $expired);
check('لینک کسب‌وکار ساخته شد', (bool)preg_match('/^[a-f0-9]{32}$/', (string)$db->value('SELECT partner_token FROM local_offers WHERE id=?', [$dinner])));
check('بدون عنوان رد شد', !$svc->save(1, ['business_name' => 'OFTEST x'])['ok']);
check('تصویر javascript: رد شد', !$svc->save(1, ['business_name' => 'OFTEST x', 'title' => 't', 'image' => 'javascript:alert(1)'])['ok']);
check('پایان پیش از شروع رد شد', !$svc->save(1, ['business_name' => 'OFTEST x', 'title' => 't', 'valid_from' => '2026-05-02', 'valid_to' => '2026-05-01'])['ok']);

// ── ۲) فهرست روی تلویزیون ───────────────────────────────────────
echo "\n── ۲) فهرست مهمان ──\n";
$r = call('OfferController', 'guestList', ['code' => 'OFTEST']);
$l = $ids($r);
check('فهرست ۲۰۰', $r['status'] === 200, json_encode($r['body'], JSON_UNESCAPED_UNICODE));
check('تخفیف فعال امروز دیده می‌شود', in_array($dinner, $l, true) && in_array($limited, $l, true));
check('آینده، منقضی و خاموش دیده نمی‌شوند', !array_intersect([$future, $expired, $off], $l));
$row = array_values(array_filter($r['body']['data']['offers'], fn($o) => (int)$o['id'] === $dinner))[0] ?? [];
check('لینک کسب‌وکار به مهمان نمی‌رسد', !array_key_exists('partner_token', $row));
check('اتاق ساکن می‌تواند کد بگیرد', !empty($row['can_claim']) && !empty($r['body']['data']['occupied']));

$r = call('OfferController', 'guestList', ['code' => 'OFLOBBY']);
$row = $r['body']['data']['offers'][0] ?? [];
check('تلویزیون لابی فهرست را می‌بیند ولی کد نمی‌گیرد', $r['status'] === 200 && $row && empty($row['can_claim']));
$r = call('OfferController', 'guestClaim', ['code' => 'OFLOBBY', 'id' => $dinner]);
check('صدور کد از لابی ۴۰۴', $r['status'] === 404);

// ── ۳) صدور کد ─────────────────────────────────────────────────
echo "\n── ۳) صدور کد ──\n";
$r = call('OfferController', 'guestClaim', ['code' => 'OFTEST', 'id' => $dinner]);
$code = (string)($r['body']['data']['code'] ?? '');
check('کد صادر شد', $r['status'] === 201 && (bool)preg_match('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code), json_encode($r['body'], JSON_UNESCAPED_UNICODE));
check('نام مهمان روی کد ثبت شد', $db->value('SELECT guest_name FROM offer_claims WHERE code=?', [$code]) === 'مهمان تست');
$r = call('OfferController', 'guestClaim', ['code' => 'OFTEST', 'id' => $dinner]);
check('کد دوم در همان اقامت رد شد و کد قبلی را گفت', $r['status'] === 409 && str_contains((string)$r['body']['message'], $code));
$r = call('OfferController', 'guestList', ['code' => 'OFTEST']);
$row = array_values(array_filter($r['body']['data']['offers'], fn($o) => (int)$o['id'] === $dinner))[0] ?? [];
check('کد گرفته‌شده در فهرست هست و دکمه‌ی دریافت نیست', ($row['codes'][0]['code'] ?? '') === $code && empty($row['can_claim']));
$r = call('OfferController', 'guestClaim', ['code' => 'OFTEST', 'id' => $future]);
check('تخفیف هنوز شروع‌نشده کد نمی‌دهد', $r['status'] === 404);

$r = call('OfferController', 'guestClaim', ['code' => 'OFTEST', 'id' => $limited]);
check('سقف کل: کد اول صادر شد', $r['status'] === 201);
$room2 = (int)$db->insert('iptv_rooms', ['tenant_id' => 1, 'room_number' => 'OF2', 'status' => 'occupied', 'check_in_at' => date('Y-m-d H:i:s')]);
$res = $svc->claim(['tenant_id' => 1, 'room_id' => $room2, 'status' => 'occupied', 'check_in_at' => date('Y-m-d H:i:s'), 'guest_name' => null], $limited);
check('سقف کل: اتاق دیگر رد شد', !$res['ok'] && $res['code'] === 409);
$l2 = array_map(fn($o) => (int)$o['id'], $svc->forGuest(1, $room2, date('Y-m-d H:i:s')));
check('تخفیف تمام‌شده برای اتاق دیگر پنهان شد', !in_array($limited, $l2, true));

/* مهمان بعدی همان اتاق سهمیه‌ی تازه دارد */
$db->query('UPDATE offer_claims SET created_at = NOW() - INTERVAL 2 DAY WHERE room_id = ?', [$room]);
$db->update('iptv_rooms', ['check_in_at' => date('Y-m-d H:i:s', time() - 60)], ['id' => $room]);
$r = call('OfferController', 'guestClaim', ['code' => 'OFTEST', 'id' => $dinner]);
check('مهمان تازه‌ی همان اتاق کد تازه می‌گیرد', $r['status'] === 201, json_encode($r['body'], JSON_UNESCAPED_UNICODE));
$code2 = (string)($r['body']['data']['code'] ?? '');

$db->update('iptv_rooms', ['status' => 'available'], ['id' => $room]);
$r = call('OfferController', 'guestClaim', ['code' => 'OFTEST', 'id' => $limited]);
check('اتاق خالی کد نمی‌گیرد', $r['status'] === 403, (string)$r['status']);
$db->update('iptv_rooms', ['status' => 'occupied'], ['id' => $room]);

// ── ۴) تأیید کد ────────────────────────────────────────────────
echo "\n── ۴) تأیید کد ──\n";
check('نرمال‌سازی کد: حروف کوچک و فاصله', App\Services\OfferService::normalize(strtolower(str_replace('-', ' ', $code))) === $code);
check('نرمال‌سازی کد: ارقام فارسی', App\Services\OfferService::normalize(strtr('AB-CD-2345', ['2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵'])) === 'ABCD-2345');
check('کد با حرف مبهم (O/0) نامعتبر است', App\Services\OfferService::normalize('ABCO-2345') === '');

$token  = (string)$db->value('SELECT partner_token FROM local_offers WHERE id=?', [$dinner]);
$token2 = (string)$db->value('SELECT partner_token FROM local_offers WHERE id=?', [$limited]);
$tourCode = (string)$db->value('SELECT code FROM offer_claims WHERE offer_id=? LIMIT 1', [$limited]);
$res = $svc->partnerRedeem($token, $tourCode);
check('لینک رستوران کد تور را نمی‌پذیرد', !$res['ok'] && $res['code'] === 404);
$res = $svc->partnerRedeem($token, strtolower($code));
check('لینک کسب‌وکار کد خودش را تأیید کرد', $res['ok'], $res['message']);
check('روش تأیید «کسب‌وکار» ثبت شد', $db->value('SELECT redeemed_via FROM offer_claims WHERE code=?', [$code]) === 'partner');
$res = $svc->partnerRedeem($token, $code);
check('تأیید دوباره رد شد', !$res['ok'] && $res['code'] === 409);
$res = $svc->redeem(1, $code2, 'panel', 7);
check('پذیرش از پنل تأیید کرد', $res['ok'] && $db->value('SELECT redeemed_by FROM offer_claims WHERE code=?', [$code2]) == 7);

$cid = (int)$db->value("SELECT id FROM offer_claims WHERE offer_id=? AND status='issued' LIMIT 1", [$limited]);
check('باطل کردن کد استفاده‌نشده', $svc->void(1, $cid));
check('کد باطل پذیرفته نمی‌شود', !$svc->redeem(1, $tourCode, 'panel')['ok']);
check('کد استفاده‌شده باطل نمی‌شود', !$svc->void(1, (int)$db->value('SELECT id FROM offer_claims WHERE code=?', [$code])));

$db->update('local_offers', ['valid_to' => date('Y-m-d', time() - 86400)], ['id' => $limited]);
$db->update('offer_claims', ['status' => 'issued'], ['id' => $cid]);
$res = $svc->redeem(1, $tourCode, 'panel');
check('کد تخفیف منقضی پذیرفته نمی‌شود', !$res['ok'] && str_contains($res['message'], 'مهلت'));

// ── ۵) قفل لینک کسب‌وکار ──────────────────────────────────────────
echo "\n── ۵) قفل حدس زدن ──\n";
for ($i = 0; $i < App\Services\OfferService::PARTNER_MAX_FAILS; $i++) $svc->partnerRedeem($token2, App\Services\OfferService::newCode());
$db->update('local_offers', ['valid_to' => null], ['id' => $limited]);
$res = $svc->partnerRedeem($token2, $tourCode);
check('بعد از ۱۰ حدس اشتباه، حتی کد درست هم ۴۲۹', !$res['ok'] && $res['code'] === 429, $res['message']);
$new = $svc->rotateToken(1, $limited);
check('لینک تازه قفل را برمی‌دارد', $new && $svc->partnerRedeem($new, $tourCode)['ok']);
check('لینک قبلی دیگر کار نمی‌کند', $svc->partnerOffer($token2) === null);
check('توکن بدشکل رد می‌شود', $svc->partnerOffer("' OR 1=1 --") === null);

echo "\n" . str_repeat('─', 58) . "\n";
echo $fail === 0 ? "✅ هر $pass تست پاس شد\n" : "❌ $fail شکست از " . ($pass + $fail) . " تست\n";
cleanup($db);
exit($fail === 0 ? 0 : 1);
