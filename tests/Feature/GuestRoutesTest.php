<?php
/**
 * تست: هر URL واقعی به کدام route/کنترلر می‌رسد؟
 * منطق تطبیق دقیقاً همان حلقه‌ی Router::dispatch است (اولین match برنده).
 */
define('ROOT_PATH',    dirname(__DIR__, 2));
define('APP_PATH',    ROOT_PATH . '/app');
define('VIEWS_PATH',  ROOT_PATH . '/resources/views');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('APP_DEBUG',   true);

spl_autoload_register(function (string $class): void {
    $p = APP_PATH . '/' . str_replace(['App\\', '\\'], ['', '/'], $class) . '.php';
    if (file_exists($p)) require $p;
});
function env(string $k, mixed $d = null): mixed { return $d; }

$router = new App\Core\Router();
require ROOT_PATH . '/routes/api.php';

$prop = (new ReflectionObject($router))->getProperty('routes');
$prop->setAccessible(true);
$routes = $prop->getValue($router);

/** همان الگوریتم dispatch: اولین route با method و pattern منطبق */
function resolve(array $routes, string $method, string $uri): ?array {
    foreach ($routes as $r) {
        if ($r['method'] !== $method) continue;
        if (preg_match($r['pattern'], $uri, $m)) {
            array_shift($m);
            return [
                'action' => is_array($r['action'])
                    ? (basename(str_replace('\\', '/', $r['action'][0])) . '::' . $r['action'][1])
                    : 'closure',
                'params' => array_combine($r['params'], $m) ?: [],
                'auth'   => !empty($r['middleware']),
            ];
        }
    }
    return null;
}

$cases = [
    // [method, uri, کنترلر::متد مورد انتظار, باید auth داشته باشد؟, پارامترهای مورد انتظار]
    ['GET',    '/api/v1/guest/services',                  'GuestServiceController::services',     true,  []],
    ['POST',   '/api/v1/guest/services',                  'GuestServiceController::storeService', true,  []],
    ['PUT',    '/api/v1/guest/services/12',               'GuestServiceController::updateService',true,  ['id' => '12']],
    ['DELETE', '/api/v1/guest/services/12',               'GuestServiceController::destroyService',true, ['id' => '12']],
    ['GET',    '/api/v1/guest/requests/stats',            'GuestServiceController::stats',        true,  []],
    ['GET',    '/api/v1/guest/requests',                  'GuestServiceController::requests',     true,  []],
    ['GET',    '/api/v1/guest/requests/45',               'GuestServiceController::showRequest',  true,  ['id' => '45']],
    ['PUT',    '/api/v1/guest/requests/45/status',        'GuestServiceController::updateStatus', true,  ['id' => '45']],
    // پورتال مهمان — بدون auth
    ['GET',    '/api/v1/guest/SCR001/services',           'GuestPortalController::services',      false, ['code' => 'SCR001']],
    ['GET',    '/api/v1/guest/SCR001/requests',           'GuestPortalController::myRequests',    false, ['code' => 'SCR001']],
    ['POST',   '/api/v1/guest/SCR001/requests',           'GuestPortalController::store',         false, ['code' => 'SCR001']],
    ['POST',   '/api/v1/guest/SCR001/requests/7/cancel',  'GuestPortalController::cancel',        false, ['code' => 'SCR001', 'id' => '7']],
    // EPG — فاز ۲
    ['GET',    '/api/v1/epg/sources',                     'EpgController::sources',               true,  []],
    ['POST',   '/api/v1/epg/sources',                     'EpgController::storeSource',           true,  []],
    ['DELETE', '/api/v1/epg/sources/3',                   'EpgController::destroySource',         true,  ['id' => '3']],
    ['POST',   '/api/v1/epg/sources/3/sync',              'EpgController::syncSource',            true,  ['id' => '3']],
    ['GET',    '/api/v1/epg/now',                         'EpgController::now',                   true,  []],
    ['GET',    '/api/v1/epg/grid',                        'EpgController::grid',                  true,  []],
    ['GET',    '/api/v1/epg/channel/9',                   'EpgController::channel',               true,  ['id' => '9']],
    ['GET',    '/api/v1/player/epg/TSCR01',               'EpgController::playerNow',             false, ['code' => 'TSCR01']],
    // پورتال — فاز ۳
    ['GET',    '/api/v1/portal/PTEST01',                  'PortalController::home',               false, ['code' => 'PTEST01']],
    ['GET',    '/api/v1/portal/PTEST01/live',             'PortalController::live',               false, ['code' => 'PTEST01']],
    // رگرسیون: مسیرهای موجود نباید خراب شده باشند
    ['GET',    '/api/v1/iptv/rooms',                      'IptvRoomController::index',            true,  []],
    ['POST',   '/api/v1/pms/checkin',                     'IptvRoomController::pmsCheckin',       false, []],
];

$fail = 0;
foreach ($cases as [$method, $uri, $wantAction, $wantAuth, $wantParams]) {
    $got = resolve($routes, $method, $uri);

    if ($got === null) {
        echo "  ❌ 404  $method $uri\n";
        $fail++; continue;
    }
    $errs = [];
    if ($got['action'] !== $wantAction)  $errs[] = "کنترلر: {$got['action']} ≠ $wantAction";
    if ($got['auth']   !== $wantAuth)    $errs[] = 'auth: ' . var_export($got['auth'], true) . ' ≠ ' . var_export($wantAuth, true);
    if ($got['params'] !== $wantParams)  $errs[] = 'params: ' . json_encode($got['params']) . ' ≠ ' . json_encode($wantParams);

    if ($errs) { echo "  ❌ $method $uri\n       " . implode("\n       ", $errs) . "\n"; $fail++; }
    else       { echo "  ✅ $method $uri  →  {$got['action']}" . ($got['auth'] ? ' [auth]' : ' [public]') . "\n"; }
}

echo "\n" . ($fail === 0
    ? "✅ هر " . count($cases) . " مسیر درست resolve شد — تداخلی بین /guest/services و /guest/{code}/… نیست\n"
    : "❌ $fail از " . count($cases) . " مورد شکست خورد\n");

exit($fail === 0 ? 0 : 1);
