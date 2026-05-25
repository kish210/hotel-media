<?php
use App\Middleware\{ApiAuthMiddleware, RateLimitMiddleware};
use App\Controllers\Api\{AuthController, ScreenController, MediaController};

$router->group(['prefix' => '/api/v1'], function($r) {

    // ── Public endpoints (no auth)
    $r->post('/auth/login',  [AuthController::class, 'login'],  [RateLimitMiddleware::class]);

    // ── Screen Player endpoints (screen-key auth, no JWT)
    $r->post('/screens/{code}/heartbeat', [ScreenController::class, 'heartbeat']);
    $r->get('/screens/{code}/playlist',   [ScreenController::class, 'getPlaylist']);

    // ── Protected (JWT)
    $r->group(['middleware' => [ApiAuthMiddleware::class]], function($r) {
        $r->get('/auth/me',      [AuthController::class, 'me']);
        $r->post('/auth/logout', [AuthController::class, 'logout']);

        // Screens
        $r->get('/screens',              [ScreenController::class, 'index']);
        $r->post('/screens',             [ScreenController::class, 'store']);
        $r->get('/screens/stats',        [ScreenController::class, 'stats']);
        $r->get('/screens/{id}',         [ScreenController::class, 'show']);
        $r->put('/screens/{id}',         [ScreenController::class, 'update']);
        $r->delete('/screens/{id}',      [ScreenController::class, 'destroy']);
        $r->post('/screens/{id}/command', [ScreenController::class, 'command']);
        $r->post('/screens/{id}/activation', [ScreenController::class, 'generateActivation']);

        // Media
        $r->get('/media',            [MediaController::class, 'index']);
        $r->post('/media/upload',    [MediaController::class, 'upload']);
        $r->post('/media/url',       [MediaController::class, 'addUrl']);
        $r->get('/media/storage',    [MediaController::class, 'storageInfo']);
        $r->get('/media/{id}',       [MediaController::class, 'show']);
        $r->delete('/media/{id}',    [MediaController::class, 'destroy']);

        // Playlists
        $r->get('/playlists',        [\App\Controllers\Api\PlaylistController::class, 'index']);
        $r->post('/playlists',       [\App\Controllers\Api\PlaylistController::class, 'store']);
        $r->get('/playlists/{id}',   [\App\Controllers\Api\PlaylistController::class, 'show']);
        $r->put('/playlists/{id}',   [\App\Controllers\Api\PlaylistController::class, 'update']);
        $r->delete('/playlists/{id}',[\App\Controllers\Api\PlaylistController::class, 'destroy']);

        // Schedules
        $r->get('/schedules',     [\App\Controllers\Api\ScheduleController::class, 'index']);
        $r->post('/schedules',    [\App\Controllers\Api\ScheduleController::class, 'store']);
        $r->delete('/schedules/{id}', [\App\Controllers\Api\ScheduleController::class, 'destroy']);

        // Menu Board
        $r->get('/menu/items',     [\App\Controllers\Api\MenuController::class, 'items']);
        $r->get('/menu/categories',[\App\Controllers\Api\MenuController::class, 'categories']);

        // Notifications
        $r->get('/notifications',  [\App\Controllers\Api\NotificationController::class, 'index']);
        $r->post('/notifications/{id}/read', [\App\Controllers\Api\NotificationController::class, 'markRead']);

        // Dashboard stats
        $r->get('/dashboard/stats', [\App\Controllers\Api\DashboardController::class, 'stats']);
    });
});
// Layouts (added)
$router->group(['prefix'=>'/api/v1','middleware'=>[\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    $r->get('/layouts',          [\App\Controllers\Api\LayoutController::class,'index']);
    $r->post('/layouts',         [\App\Controllers\Api\LayoutController::class,'store']);
    $r->put('/layouts/{id}',     [\App\Controllers\Api\LayoutController::class,'update']);
    $r->delete('/layouts/{id}',  [\App\Controllers\Api\LayoutController::class,'destroy']);
});

// ── Modules (protected)
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {

    // Module management
    $r->get('/modules',                    [\App\Controllers\Api\ModuleController::class, 'index']);
    $r->get('/modules/zone-types',         [\App\Controllers\Api\ModuleController::class, 'allZoneTypes']);
    $r->get('/modules/dashboard-stats',    [\App\Controllers\Api\ModuleController::class, 'dashboardStats']);
    $r->get('/modules/{id}',               [\App\Controllers\Api\ModuleController::class, 'show']);
    $r->post('/modules/{id}/install',      [\App\Controllers\Api\ModuleController::class, 'install']);
    $r->post('/modules/{id}/uninstall',    [\App\Controllers\Api\ModuleController::class, 'uninstall']);
    $r->post('/modules/{id}/toggle',       [\App\Controllers\Api\ModuleController::class, 'toggle']);
    $r->put('/modules/{id}/settings',      [\App\Controllers\Api\ModuleController::class, 'saveSettings']);
    $r->get('/modules/{id}/preview',       [\App\Controllers\Api\ModuleController::class, 'preview']);

    // FIDS — Flight Information Display
    $r->get('/fids/flights',               [\App\Controllers\Api\FIDSController::class, 'flights']);
    $r->post('/fids/flights',              [\App\Controllers\Api\FIDSController::class, 'storeFlight']);
    $r->put('/fids/flights/{id}',          [\App\Controllers\Api\FIDSController::class, 'updateFlight']);
    $r->delete('/fids/flights/{id}',       [\App\Controllers\Api\FIDSController::class, 'deleteFlight']);
    $r->post('/fids/flights/{id}/status',  [\App\Controllers\Api\FIDSController::class, 'updateStatus']);
    $r->get('/fids/airlines',              [\App\Controllers\Api\FIDSController::class, 'airlines']);
    $r->get('/fids/stats',                 [\App\Controllers\Api\FIDSController::class, 'stats']);
    // FIDS Live — proxy to fids.airport.ir
    $r->get('/fids/live',                  [\App\Controllers\Api\FIDSController::class, 'live']);
    $r->get('/fids/airports',              [\App\Controllers\Api\FIDSController::class, 'airportList']);
    $r->post('/fids/live/bust',            [\App\Controllers\Api\FIDSController::class, 'bustCache']);
    $r->post('/fids/sync-live',            [\App\Controllers\Api\FIDSController::class, 'syncLive']);

    // Hotel Information
    $r->get('/hotel/info',                 [\App\Controllers\Api\HotelController::class, 'info']);
    $r->post('/hotel/info',                [\App\Controllers\Api\HotelController::class, 'saveInfo']);
    $r->get('/hotel/events',               [\App\Controllers\Api\HotelController::class, 'events']);
    $r->post('/hotel/events',              [\App\Controllers\Api\HotelController::class, 'storeEvent']);
    $r->put('/hotel/events/{id}',          [\App\Controllers\Api\HotelController::class, 'updateEvent']);
    $r->delete('/hotel/events/{id}',       [\App\Controllers\Api\HotelController::class, 'deleteEvent']);
    $r->get('/hotel/amenities',            [\App\Controllers\Api\HotelController::class, 'amenities']);
    $r->post('/hotel/amenities',           [\App\Controllers\Api\HotelController::class, 'storeAmenity']);
    $r->put('/hotel/amenities/{id}',       [\App\Controllers\Api\HotelController::class, 'updateAmenity']);
    $r->delete('/hotel/amenities/{id}',    [\App\Controllers\Api\HotelController::class, 'deleteAmenity']);
    $r->get('/hotel/room-service',         [\App\Controllers\Api\HotelController::class, 'roomService']);
    $r->post('/hotel/room-service',        [\App\Controllers\Api\HotelController::class, 'storeRoomService']);
    $r->get('/hotel/attractions',          [\App\Controllers\Api\HotelController::class, 'attractions']);
    $r->post('/hotel/attractions',         [\App\Controllers\Api\HotelController::class, 'storeAttraction']);
    $r->get('/hotel/weather',              [\App\Controllers\Api\HotelController::class, 'weather']);

    // Corporate Information
    $r->get('/corporate/kpi',              [\App\Controllers\Api\CorporateController::class, 'kpi']);
    $r->post('/corporate/kpi',             [\App\Controllers\Api\CorporateController::class, 'storeKpi']);
    $r->put('/corporate/kpi/{id}',         [\App\Controllers\Api\CorporateController::class, 'updateKpi']);
    $r->delete('/corporate/kpi/{id}',      [\App\Controllers\Api\CorporateController::class, 'deleteKpi']);
    $r->get('/corporate/news',             [\App\Controllers\Api\CorporateController::class, 'news']);
    $r->post('/corporate/news',            [\App\Controllers\Api\CorporateController::class, 'storeNews']);
    $r->put('/corporate/news/{id}',        [\App\Controllers\Api\CorporateController::class, 'updateNews']);
    $r->delete('/corporate/news/{id}',     [\App\Controllers\Api\CorporateController::class, 'deleteNews']);
    $r->get('/corporate/departments',      [\App\Controllers\Api\CorporateController::class, 'departments']);
    $r->post('/corporate/departments',     [\App\Controllers\Api\CorporateController::class, 'storeDept']);
    $r->put('/corporate/departments/{id}', [\App\Controllers\Api\CorporateController::class, 'updateDept']);
    $r->delete('/corporate/departments/{id}', [\App\Controllers\Api\CorporateController::class, 'deleteDept']);

    // Retail & Shopping
    $r->get('/retail/products',            [\App\Controllers\Api\RetailController::class, 'products']);
    $r->post('/retail/products',           [\App\Controllers\Api\RetailController::class, 'storeProduct']);
    $r->put('/retail/products/{id}',       [\App\Controllers\Api\RetailController::class, 'updateProduct']);
    $r->delete('/retail/products/{id}',    [\App\Controllers\Api\RetailController::class, 'deleteProduct']);
    $r->get('/retail/queue',               [\App\Controllers\Api\RetailController::class, 'queue']);
    $r->post('/retail/queue/call',         [\App\Controllers\Api\RetailController::class, 'callNext']);
    $r->get('/retail/currency',            [\App\Controllers\Api\RetailController::class, 'currency']);

    // Transport
    $r->get('/transport/schedules',        [\App\Controllers\Api\TransportController::class, 'schedules']);
    $r->post('/transport/schedules',       [\App\Controllers\Api\TransportController::class, 'storeSchedule']);
    $r->put('/transport/schedules/{id}',   [\App\Controllers\Api\TransportController::class, 'updateSchedule']);
    $r->delete('/transport/schedules/{id}',[\App\Controllers\Api\TransportController::class, 'deleteSchedule']);
});

// FIDS — public (for screens without auth)
$router->get('/api/v1/fids/flights',      [\App\Controllers\Api\FIDSController::class, 'flights']);
$router->get('/api/v1/fids/live',         [\App\Controllers\Api\FIDSController::class, 'live']);
$router->get('/api/v1/fids/airports',     [\App\Controllers\Api\FIDSController::class, 'airportList']);
$router->get('/api/v1/fids/ping',         [\App\Controllers\Api\FIDSController::class, 'ping']);
// FIDS Cron — public with token auth (no JWT needed)
$router->get('/api/v1/fids/cron-sync',   [\App\Controllers\Api\FIDSController::class, 'cronSync']);
$router->get('/api/v1/hotel/events',    [\App\Controllers\Api\HotelController::class, 'events']);
$router->get('/api/v1/hotel/amenities', [\App\Controllers\Api\HotelController::class, 'amenities']);
$router->get('/api/v1/hotel/info',      [\App\Controllers\Api\HotelController::class, 'info']);
$router->get('/api/v1/hotel/room-service', [\App\Controllers\Api\HotelController::class, 'roomService']);
$router->get('/api/v1/hotel/attractions',  [\App\Controllers\Api\HotelController::class, 'attractions']);
$router->get('/api/v1/hotel/weather',      [\App\Controllers\Api\HotelController::class, 'weather']);
$router->get('/api/v1/corporate/kpi',      [\App\Controllers\Api\CorporateController::class, 'kpi']);
$router->get('/api/v1/corporate/news',     [\App\Controllers\Api\CorporateController::class, 'news']);
$router->get('/api/v1/corporate/departments', [\App\Controllers\Api\CorporateController::class, 'departments']);
$router->get('/api/v1/retail/products',    [\App\Controllers\Api\RetailController::class, 'products']);
$router->get('/api/v1/retail/queue',       [\App\Controllers\Api\RetailController::class, 'queue']);
$router->get('/api/v1/retail/currency',    [\App\Controllers\Api\RetailController::class, 'currency']);
$router->get('/api/v1/transport/schedules',[\App\Controllers\Api\TransportController::class, 'schedules']);

// ── VOD ──────────────────────────────────────────────────────
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    $r->get('/vod/stats',                    [\App\Controllers\Api\VodController::class, 'stats']);
    $r->get('/vod/categories',               [\App\Controllers\Api\VodController::class, 'categories']);
    $r->post('/vod/categories',              [\App\Controllers\Api\VodController::class, 'storeCategory']);
    $r->put('/vod/categories/{id}',          [\App\Controllers\Api\VodController::class, 'updateCategory']);
    $r->delete('/vod/categories/{id}',       [\App\Controllers\Api\VodController::class, 'deleteCategory']);
    $r->get('/vod/videos',                   [\App\Controllers\Api\VodController::class, 'videos']);
    $r->post('/vod/upload',                  [\App\Controllers\Api\VodController::class, 'upload']);
    $r->post('/vod/videos',                  [\App\Controllers\Api\VodController::class, 'storeUrl']);
    $r->get('/vod/videos/{id}',              [\App\Controllers\Api\VodController::class, 'showVideo']);
    $r->put('/vod/videos/{id}',              [\App\Controllers\Api\VodController::class, 'updateVideo']);
    $r->delete('/vod/videos/{id}',           [\App\Controllers\Api\VodController::class, 'deleteVideo']);
    $r->post('/vod/videos/bulk-delete',      [\App\Controllers\Api\VodController::class, 'bulkDelete']);
    $r->post('/vod/videos/{id}/thumbnail',   [\App\Controllers\Api\VodController::class, 'uploadThumbnail']);
});
// VOD public (for screens)
$router->get('/api/v1/vod/videos',      [\App\Controllers\Api\VodController::class, 'videos']);
$router->get('/api/v1/vod/categories',  [\App\Controllers\Api\VodController::class, 'categories']);

// ── IPTV Menus ───────────────────────────────────────────────────
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    $r->get('/iptv/menus',                          [\App\Controllers\Api\IptvMenuController::class, 'index']);
    $r->post('/iptv/menus',                         [\App\Controllers\Api\IptvMenuController::class, 'store']);
    $r->get('/iptv/menus/{id}',                     [\App\Controllers\Api\IptvMenuController::class, 'show']);
    $r->put('/iptv/menus/{id}',                     [\App\Controllers\Api\IptvMenuController::class, 'update']);
    $r->delete('/iptv/menus/{id}',                  [\App\Controllers\Api\IptvMenuController::class, 'destroy']);
    $r->post('/iptv/menus/{id}/items',              [\App\Controllers\Api\IptvMenuController::class, 'storeItem']);
    $r->put('/iptv/menus/{id}/items/{itemId}',      [\App\Controllers\Api\IptvMenuController::class, 'updateItem']);
    $r->delete('/iptv/menus/{id}/items/{itemId}',   [\App\Controllers\Api\IptvMenuController::class, 'destroyItem']);
    $r->post('/iptv/menus/{id}/items/sort',         [\App\Controllers\Api\IptvMenuController::class, 'sortItems']);
    $r->post('/iptv/menus/{id}/upload-image',       [\App\Controllers\Api\IptvMenuController::class, 'uploadImage']);
    $r->post('/iptv/menus/{id}/remove-image',       [\App\Controllers\Api\IptvMenuController::class, 'removeImage']);
});
// IPTV menus public — مسیر مخصوص پلیر (بدون auth، مسیر متفاوت)
$router->get('/api/v1/player/iptv-menu/{id}',       [\App\Controllers\Api\IptvMenuController::class,  'playerMenu']);
$router->get('/api/v1/player/room-info/{code}',     [\App\Controllers\Api\IptvRoomController::class,  'playerRoomInfo']);

// ── IPTV Rooms (protected) ───────────────────────────────────────
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    $r->get('/iptv/rooms',                              [\App\Controllers\Api\IptvRoomController::class, 'index']);
    $r->post('/iptv/rooms',                             [\App\Controllers\Api\IptvRoomController::class, 'store']);
    $r->get('/iptv/rooms/{id}',                         [\App\Controllers\Api\IptvRoomController::class, 'show']);
    $r->put('/iptv/rooms/{id}',                         [\App\Controllers\Api\IptvRoomController::class, 'update']);
    $r->delete('/iptv/rooms/{id}',                      [\App\Controllers\Api\IptvRoomController::class, 'destroy']);
    $r->post('/iptv/rooms/{id}/checkin',                [\App\Controllers\Api\IptvRoomController::class, 'checkin']);
    $r->post('/iptv/rooms/{id}/checkout',               [\App\Controllers\Api\IptvRoomController::class, 'checkout']);
    $r->post('/iptv/rooms/{id}/message',                [\App\Controllers\Api\IptvRoomController::class, 'sendMessage']);
    $r->get('/iptv/rooms/{id}/messages',                [\App\Controllers\Api\IptvRoomController::class, 'roomMessages']);
    $r->delete('/iptv/room-messages/{msgId}',           [\App\Controllers\Api\IptvRoomController::class, 'deleteMessage']);
    $r->post('/iptv/room-messages/{msgId}/deactivate',  [\App\Controllers\Api\IptvRoomController::class, 'deactivateMessage']);
    $r->post('/iptv/rooms/broadcast',                   [\App\Controllers\Api\IptvRoomController::class, 'broadcastMessage']);
    // PMS integrations management
    $r->get('/iptv/pms',                                [\App\Controllers\Api\IptvRoomController::class, 'getPmsIntegrations']);
    $r->post('/iptv/pms',                               [\App\Controllers\Api\IptvRoomController::class, 'createPmsIntegration']);
    $r->delete('/iptv/pms/{pmsId}',                     [\App\Controllers\Api\IptvRoomController::class, 'deletePmsIntegration']);
});

// ── PMS External API (api_key auth — no JWT) ─────────────────────
$router->post('/api/v1/pms/checkin',   [\App\Controllers\Api\IptvRoomController::class, 'pmsCheckin']);
$router->post('/api/v1/pms/checkout',  [\App\Controllers\Api\IptvRoomController::class, 'pmsCheckout']);
$router->post('/api/v1/pms/message',   [\App\Controllers\Api\IptvRoomController::class, 'pmsSendMessage']);

// ── Inflight Display (protected) ─────────────────────────────────────────────
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    $r->get('/inflight',            [\App\Controllers\Api\InflightController::class, 'index']);
    $r->post('/inflight',           [\App\Controllers\Api\InflightController::class, 'store']);
    $r->get('/inflight/{id}',       [\App\Controllers\Api\InflightController::class, 'show']);
    $r->put('/inflight/{id}',       [\App\Controllers\Api\InflightController::class, 'update']);
    $r->put('/inflight/{id}/live',         [\App\Controllers\Api\InflightController::class, 'updateLive']);
    $r->delete('/inflight/{id}',           [\App\Controllers\Api\InflightController::class, 'destroy']);
    // RPi bridge endpoints
    $r->post('/inflight/{id}/rpi-save',        [\App\Controllers\Api\InflightController::class, 'rpiSave']);
    $r->get('/inflight/{id}/rpi-status',       [\App\Controllers\Api\InflightController::class, 'rpiStatus']);
    $r->post('/inflight/{id}/rpi-sync',        [\App\Controllers\Api\InflightController::class, 'rpiSync']);
    $r->post('/inflight/{id}/rpi-push-config', [\App\Controllers\Api\InflightController::class, 'rpiPushConfig']);
});
// Inflight public — player endpoint (no auth)
$router->get('/api/v1/inflight/player/{id}', [\App\Controllers\Api\InflightController::class, 'playerFlight']);

// ── Broadcast — پخش فوری
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    $r->post('/screens/{id}/broadcast',       [\App\Controllers\Api\BroadcastController::class, 'send']);
    $r->post('/screens/{id}/broadcast/clear', [\App\Controllers\Api\BroadcastController::class, 'clear']);
    $r->post('/broadcast/all',                [\App\Controllers\Api\BroadcastController::class, 'sendAll']);
});

// Heartbeat endpoint برای پلیر (پخش فوری رو برمی‌گردونه)
$router->post('/api/v1/screens/{code}/heartbeat', [\App\Controllers\Api\ScreenController::class, 'heartbeat']);
