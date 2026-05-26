<?php
// ── Module renderer (برای iframe در پلیر)
$router->get('/player/module/{type}', function(\App\Core\Request $req, array $p) {
    $type     = preg_replace('/[^a-z_]/', '', $p['type'] ?? '');
    $settings = json_decode($req->get('settings', '{}'), true) ?: [];

    $classMap = [
        'fids'      => \App\Modules\FIDS\FIDSModule::class,
        'hotel'     => \App\Modules\Hotel\HotelModule::class,
        'menu'      => \App\Modules\Menu\MenuModule::class,
        'corporate' => \App\Modules\Corporate\CorporateModule::class,
        'retail'    => \App\Modules\Retail\RetailModule::class,
        'transport' => \App\Modules\Transport\TransportModule::class,
    ];

    if (!isset($classMap[$type])) {
        echo '<div style="color:#ef4444;padding:20px;font-family:sans-serif;">ماژول نامعتبر: ' . htmlspecialchars($type) . '</div>';
        exit;
    }

    try {
        $module = new $classMap[$type]();
        foreach ($settings as $k => $v) {
            if (method_exists($module, 'setSetting')) {
                $module->setSetting($k, $v);
            }
        }
        // تنظیم tenant
        if (method_exists($module, 'setTenantId')) {
            $module->setTenantId(\App\Core\Auth::tenantId() ?: 1);
        }

        header('Content-Type: text/html; charset=utf-8');
        // Standalone HTML wrapper
        echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head>';
        echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width">';
        echo '<style>*{margin:0;padding:0;box-sizing:border-box;}body{background:#000;overflow:hidden;width:100vw;height:100vh;}</style>';
        echo '</head><body>';
        $zoneType = $settings['zone_type'] ?? 'default';
        echo $module->renderPlayerWidget($zoneType, $settings);
        echo '</body></html>';
    } catch (\Throwable $e) {
        echo '<div style="color:#ef4444;padding:20px;font-family:sans-serif;">';
        echo 'خطا در ماژول: ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
    exit;
});

// ── Serve uploaded files (Docker volume access) ──────────────
$router->get('/uploads/{path}', function(\App\Core\Request $req, array $p) {
    $path = '/uploads/' . ($p['path'] ?? '');
    $file = PUBLIC_PATH . $path;

    // security: فقط uploads/ مجاز
    $real = realpath($file);
    $base = realpath(PUBLIC_PATH . '/uploads');
    if (!$real || !$base || !str_starts_with($real, $base)) {
        http_response_code(403); exit('Forbidden');
    }
    if (!is_file($real)) {
        http_response_code(404); exit('Not found: ' . $path);
    }

    $ext  = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'jpg','jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'ogv'  => 'video/ogg',
        'mov'  => 'video/quicktime',
        default => mime_content_type($real),
    };

    $size  = filesize($real);
    $mtime = filemtime($real);
    $etag  = '"' . md5($real . $size . $mtime) . '"';

    header("Content-Type: $mime");
    header("ETag: $etag");
    header("Cache-Control: public, max-age=86400");
    header("Last-Modified: " . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

    if (($req->header('If-None-Match') === trim($etag)) ||
        ($req->header('If-Modified-Since') === gmdate('D, d M Y H:i:s', $mtime) . ' GMT')) {
        http_response_code(304); exit;
    }

    // Range support برای ویدیو
    if (isset($_SERVER['HTTP_RANGE'])) {
        preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m);
        $start = (int)$m[1];
        $end   = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : $size - 1;
        $end   = min($end, $size - 1);
        $len   = $end - $start + 1;
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
        header("Content-Length: $len");
        header("Accept-Ranges: bytes");
        $fp = fopen($real, 'rb');
        fseek($fp, $start);
        $out = 0;
        while ($out < $len && !feof($fp)) {
            $chunk = min(65536, $len - $out);
            echo fread($fp, $chunk);
            $out += $chunk;
            flush();
        }
        fclose($fp);
    } else {
        header("Content-Length: $size");
        header("Accept-Ranges: bytes");
        readfile($real);
    }
    exit;
});

use App\Middleware\{AuthMiddleware, GuestMiddleware, CsrfMiddleware};
use App\Controllers\Web\{AuthController, DashboardController, ScreenController};

// ── Auth (guest only)
$router->group(['prefix' => '', 'middleware' => [GuestMiddleware::class]], function($r) {
    $r->get('/login',  [AuthController::class, 'showLogin']);
    $r->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
});

$router->get('/logout', [AuthController::class, 'logout']);

// ── Admin Panel (auth required)
$router->group(['prefix' => '/admin', 'middleware' => [AuthMiddleware::class]], function($r) {
    // Dashboard
    $r->get('/dashboard', [DashboardController::class, 'index']);
    $r->get('/',          [DashboardController::class, 'index']);

    // Screens
    $r->get('/screens',                        [ScreenController::class, 'index']);
    $r->get('/screens/monitor',                [ScreenController::class, 'monitor']);
    $r->get('/screens/create',                 [ScreenController::class, 'create']);
    $r->post('/screens',                       [ScreenController::class, 'store']);
    // ── Groups — باید قبل از /screens/{id} باشه تا conflict نشه ──
    $r->post('/screens/groups',                [ScreenController::class, 'storeGroup']);
    $r->post('/screens/groups/{id}/delete',    [ScreenController::class, 'deleteGroup']);
    // ── Single screen ──
    $r->get('/screens/{id}',                   [ScreenController::class, 'show']);
    $r->post('/screens/{id}',                  [ScreenController::class, 'update']);
    $r->post('/screens/{id}/delete',           [ScreenController::class, 'destroy']);

    // Playlists
    $r->get('/playlists',             [\App\Controllers\Web\PlaylistController::class, 'index']);
    $r->get('/playlists/create',      [\App\Controllers\Web\PlaylistController::class, 'create']);
    $r->post('/playlists',            [\App\Controllers\Web\PlaylistController::class, 'store']);
    $r->get('/playlists/{id}',        [\App\Controllers\Web\PlaylistController::class, 'show']);
    $r->get('/playlists/{id}/edit',   [\App\Controllers\Web\PlaylistController::class, 'edit']);
    $r->post('/playlists/{id}',       [\App\Controllers\Web\PlaylistController::class, 'update']);
    $r->post('/playlists/{id}/delete',      [\App\Controllers\Web\PlaylistController::class, 'destroy']);
    $r->post('/playlists/{id}/items',       [\App\Controllers\Web\PlaylistController::class, 'addItem']);
    $r->post('/playlists/{id}/items/{iid}/delete', [\App\Controllers\Web\PlaylistController::class, 'removeItem']);
    $r->post('/playlists/{id}/items/reorder',      [\App\Controllers\Web\PlaylistController::class, 'reorderItems']);
    $r->post('/playlists/{id}/items/{iid}/edit',   [\App\Controllers\Web\PlaylistController::class, 'editItem']);

    // Media
    $r->get('/media',          [\App\Controllers\Web\MediaController::class, 'index']);
    $r->post('/media/upload',  [\App\Controllers\Web\MediaController::class, 'upload']);
    $r->post('/media/url',     [\App\Controllers\Web\MediaController::class, 'addUrl']);
    $r->post('/media/{id}/delete', [\App\Controllers\Web\MediaController::class, 'destroy']);

    // Layouts Designer
    $r->get('/layouts',           [\App\Controllers\Web\LayoutController::class, 'index']);
    $r->get('/layouts/create',    [\App\Controllers\Web\LayoutController::class, 'create']);
    $r->post('/layouts',          [\App\Controllers\Web\LayoutController::class, 'store']);
    $r->get('/layouts/{id}/edit', [\App\Controllers\Web\LayoutController::class, 'edit']);
    $r->post('/layouts/{id}',          [\App\Controllers\Web\LayoutController::class, 'update']);
    $r->post('/layouts/{id}/delete',  [\App\Controllers\Web\LayoutController::class, 'destroy']);

    // Schedules
    $r->get('/schedules',      [\App\Controllers\Web\ScheduleController::class, 'index']);
    $r->post('/schedules',     [\App\Controllers\Web\ScheduleController::class, 'store']);
    $r->post('/schedules/{id}/delete', [\App\Controllers\Web\ScheduleController::class, 'destroy']);

    // Menu Board
    $r->get('/menu',                         [\App\Controllers\Web\MenuController::class, 'index']);
    $r->post('/menu/categories',             [\App\Controllers\Web\MenuController::class, 'storeCategory']);
    $r->post('/menu/categories/{id}',        [\App\Controllers\Web\MenuController::class, 'updateCategory']);
    $r->post('/menu/categories/{id}/delete', [\App\Controllers\Web\MenuController::class, 'deleteCategory']);
    $r->post('/menu/items',                  [\App\Controllers\Web\MenuController::class, 'storeItem']);
    $r->post('/menu/items/{id}',             [\App\Controllers\Web\MenuController::class, 'updateItem']);
    $r->post('/menu/items/{id}/delete',      [\App\Controllers\Web\MenuController::class, 'deleteItem']);

    // Users
    $r->get('/users',         [\App\Controllers\Web\UserController::class, 'index']);
    $r->post('/users',        [\App\Controllers\Web\UserController::class, 'store']);
    $r->post('/users/{id}',   [\App\Controllers\Web\UserController::class, 'update']);

    // Campaigns / Emergency
    $r->get('/campaigns',         [\App\Controllers\Web\CampaignController::class, 'index']);
    $r->post('/campaigns',        [\App\Controllers\Web\CampaignController::class, 'store']);
    $r->post('/campaigns/{id}/broadcast', [\App\Controllers\Web\CampaignController::class, 'broadcast']);

    // Settings
    $r->get('/settings',   [\App\Controllers\Web\SettingsController::class, 'index']);
    $r->post('/settings',  [\App\Controllers\Web\SettingsController::class, 'update']);

    // Reports
    $r->get('/reports', [\App\Controllers\Web\ReportController::class, 'index']);

    // Notifications
    $r->post('/notifications/{id}/read', [\App\Controllers\Web\NotificationController::class, 'markRead']);
    $r->post('/notifications/read-all',  [\App\Controllers\Web\NotificationController::class, 'markAllRead']);
});

// ── Screen Player (public, screen-facing)
// /player          → cookie-based: اگر cookie داره پلیر لود می‌کنه، وگرنه pair page
// /player/{code}   → bind device به این screen از طریق cookie + لود پلیر
$router->get('/player',          [\App\Controllers\Web\PlayerController::class, 'index']);
$router->get('/player/{code}',   [\App\Controllers\Web\PlayerController::class, 'show']);
$router->post('/player/activate',[\App\Controllers\Web\PlayerController::class, 'activate']);

// Redirect root
$router->get('/', function() { \App\Core\Response::redirect('/admin/dashboard'); });

// ── Module management routes
$router->group(['prefix' => '/admin', 'middleware' => [\App\Middleware\AuthMiddleware::class]], function($r) {
    $r->get('/modules', [\App\Controllers\Web\ModuleWebController::class, 'index']);
    $r->get('/modules/{id}/manage', [\App\Controllers\Web\ModuleWebController::class, 'manage']);
    $r->post('/modules/{id}/settings', [\App\Controllers\Web\ModuleWebController::class, 'saveSettings']);

    // FIDS Admin
    $r->get('/modules/fids/flights',               [\App\Controllers\Web\FIDSWebController::class, 'flights']);
    $r->post('/modules/fids/flights',              [\App\Controllers\Web\FIDSWebController::class, 'storeFlight']);
    $r->post('/modules/fids/flights/{id}',         [\App\Controllers\Web\FIDSWebController::class, 'updateFlight']);
    $r->post('/modules/fids/flights/{id}/delete',  [\App\Controllers\Web\FIDSWebController::class, 'deleteFlight']);

    // Hotel Admin
    $r->get('/modules/hotel',                      [\App\Controllers\Web\HotelWebController::class, 'index']);
    $r->post('/modules/hotel/info',                [\App\Controllers\Web\HotelWebController::class, 'saveInfo']);
    $r->post('/modules/hotel/events',              [\App\Controllers\Web\HotelWebController::class, 'storeEvent']);
    $r->post('/modules/hotel/events/{id}/delete',  [\App\Controllers\Web\HotelWebController::class, 'deleteEvent']);
    $r->post('/modules/hotel/amenities',           [\App\Controllers\Web\HotelWebController::class, 'storeAmenity']);

    // Retail Admin
    $r->get('/modules/retail',                     [\App\Controllers\Web\RetailWebController::class, 'index']);
    $r->post('/modules/retail/products',           [\App\Controllers\Web\RetailWebController::class, 'storeProduct']);
    $r->post('/modules/retail/products/{id}',      [\App\Controllers\Web\RetailWebController::class, 'updateProduct']);

    // Corporate Admin
    $r->get('/modules/corporate',                  [\App\Controllers\Web\CorporateWebController::class, 'index']);
    $r->post('/modules/corporate/kpi',             [\App\Controllers\Web\CorporateWebController::class, 'storeKpi']);
    $r->post('/modules/corporate/kpi/{id}',        [\App\Controllers\Web\CorporateWebController::class, 'updateKpi']);
    $r->post('/modules/corporate/news',            [\App\Controllers\Web\CorporateWebController::class, 'storeNews']);

    // Menu Admin
    $r->get('/modules/menu',                         [\App\Controllers\Web\MenuController::class, 'index']);
    $r->post('/modules/menu/categories',             [\App\Controllers\Web\MenuController::class, 'storeCategory']);
    $r->post('/modules/menu/categories/{id}',        [\App\Controllers\Web\MenuController::class, 'updateCategory']);
    $r->post('/modules/menu/categories/{id}/delete', [\App\Controllers\Web\MenuController::class, 'deleteCategory']);
    $r->post('/modules/menu/items',                  [\App\Controllers\Web\MenuController::class, 'storeItem']);
    $r->post('/modules/menu/items/{id}',             [\App\Controllers\Web\MenuController::class, 'updateItem']);
    $r->post('/modules/menu/items/{id}/delete',      [\App\Controllers\Web\MenuController::class, 'deleteItem']);

    // ── Broadcast Web (session auth) ──────────────────────────────
    $r->get('/screens/{id}/media-list',      [\App\Controllers\Web\BroadcastWebController::class, 'mediaList']);
    $r->post('/screens/{id}/broadcast',      [\App\Controllers\Web\BroadcastWebController::class, 'send']);
    $r->post('/screens/{id}/broadcast/clear',[\App\Controllers\Web\BroadcastWebController::class, 'clear']);



    // ── Transcoder ────────────────────────────────────────────
    $r->get('/transcoder',              [\App\Controllers\Web\TranscoderController::class, 'index']);
    $r->post('/transcoder/start',       [\App\Controllers\Web\TranscoderController::class, 'start']);
    $r->post('/transcoder/stop/{name}', [\App\Controllers\Web\TranscoderController::class, 'stop']);
    $r->get('/transcoder/log/{name}',   [\App\Controllers\Web\TranscoderController::class, 'streamLog']);

    // ── IPTV & Transcoder ──────────────────────────────────────
    $r->get('/iptv',                [\App\Controllers\Web\IPTVController::class, 'index']);
    $r->post('/iptv',               [\App\Controllers\Web\IPTVController::class, 'store']);
    $r->post('/iptv/{id}/delete',   [\App\Controllers\Web\IPTVController::class, 'delete']);
    $r->post('/iptv/import',        [\App\Controllers\Web\IPTVController::class, 'import']);
    // ── IPTV Menus ─────────────────────────────────────────────
    $r->get('/iptv/menus',          [\App\Controllers\Web\IptvMenuWebController::class, 'index']);
    // ── IPTV Rooms ─────────────────────────────────────────────
    $r->get('/iptv/rooms',          [\App\Controllers\Web\IptvRoomWebController::class, 'index']);
    // ── TVHeadend Live TV ──────────────────────────────────────
    $r->get('/iptv/tvheadend',                    [\App\Controllers\Web\TvheadendController::class, 'index']);
    $r->post('/iptv/tvheadend',                   [\App\Controllers\Web\TvheadendController::class, 'store']);
    $r->post('/iptv/tvheadend/{id}/delete',       [\App\Controllers\Web\TvheadendController::class, 'delete']);
    $r->get('/iptv/tvheadend/{id}/test',          [\App\Controllers\Web\TvheadendController::class, 'testConnection']);
    $r->post('/iptv/tvheadend/{id}/sync',         [\App\Controllers\Web\TvheadendController::class, 'syncChannels']);
    $r->post('/iptv/tvheadend/{id}/m3u',          [\App\Controllers\Web\TvheadendController::class, 'importM3u']);
    // ── Monitor 3D ─────────────────────────────────────────────
    $r->get('/monitor3d',                [\App\Controllers\Web\Monitor3dController::class, 'index']);
    $r->get('/monitor3d/stats',          [\App\Controllers\Web\Monitor3dController::class, 'stats']);
    $r->post('/monitor3d/{id}/config',   [\App\Controllers\Web\Monitor3dController::class, 'saveConfig']);
    $r->get('/monitor3d/{id}/preview',   [\App\Controllers\Web\Monitor3dController::class, 'preview']);
    // ── Help ───────────────────────────────────────────────────
    $r->get('/help',                              [\App\Controllers\Web\HelpController::class, 'index']);
    // ── In-Flight Display ──────────────────────────────────────
    $r->get('/inflight',            [\App\Controllers\Web\InflightWebController::class, 'index']);

    // ── VOD ────────────────────────────────────────────────────
    $r->get('/vod', [\App\Controllers\Web\VodWebController::class, 'index']);

    // ── App OTA ────────────────────────────────────────────────
    $r->get('/app',               [\App\Controllers\Web\AppController::class, 'index']);
    $r->post('/app/upload',       [\App\Controllers\Web\AppController::class, 'upload']);
    $r->post('/app/{id}/delete',  [\App\Controllers\Web\AppController::class, 'delete']);

    // ── Dashboard stats ────────────────────────────────────────
    $r->get('/dashboard/stats', [\App\Controllers\Web\DashboardController::class, 'stats']);

    // ── Notifications ──────────────────────────────────────────
    $r->get('/notifications',                [\App\Controllers\Web\NotificationController::class, 'index']);
    $r->post('/notifications/mark-all-read', [\App\Controllers\Web\NotificationController::class, 'markAllRead']);


});
// ── HLS Stream serve (public)
$router->get('/hls/{name}/{file}', [\App\Controllers\Web\TranscoderController::class, 'serveHls']);

// ── Documentation ───────────────────────────────────────────────
$router->get('/docs', function() {
    $file = dirname(__DIR__) . '/docs/index.html';
    if (file_exists($file)) { header('Content-Type: text/html; charset=utf-8'); readfile($file); } 
    else { echo '<h1>Docs not found</h1>'; }
    exit;
});
