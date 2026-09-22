<?php
use App\Middleware\{ApiAuthMiddleware, RateLimitMiddleware};
use App\Controllers\Api\{AuthController, ScreenController, MediaController};

$router->group(['prefix' => '/api/v1'], function($r) {

    // ── Public endpoints (no auth)
    $r->post('/auth/login',  [AuthController::class, 'login'],  [RateLimitMiddleware::class]);

    // ── Screen Player endpoints (screen-key auth, no JWT)
    $r->post('/screens/{code}/heartbeat', [ScreenController::class, 'heartbeat']);
    $r->get('/screens/{code}/playlist',   [ScreenController::class, 'getPlaylist']);

    /* زمان سرور. تابلوها قبلا این را صدا می‌زدند ولی مسیرش وجود نداشت،
       پس همگام‌سازی همیشه بی‌صدا شکست می‌خورد و ساعتِ لابی همان ساعتِ
       خود تلویزیون بود — که بعد از قطعی برق معمولا عقب است.
       تاریخ شمسی هم اینجا ساخته می‌شود چون مرورگر تلویزیون داده‌ی Intl
       برای fa-IR ندارد. */
    $r->get('/time', function () {
        \App\Core\Response::json([
            'success'   => true,
            'timestamp' => time(),
            'iso'       => date('c'),
            'jalali'    => function_exists('jalaliDate') ? jalaliDate() : '',
            'timezone'  => date_default_timezone_get(),
        ]);
    });

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
    // FIDS Live — proxy to fids.airport.ir

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

    // Retail & Shopping

    // Transport
});

// FIDS — public (for screens without auth)
// FIDS Cron — public with token auth (no JWT needed)
$router->get('/api/v1/hotel/events',    [\App\Controllers\Api\HotelController::class, 'events']);
$router->get('/api/v1/hotel/amenities', [\App\Controllers\Api\HotelController::class, 'amenities']);
$router->get('/api/v1/hotel/info',      [\App\Controllers\Api\HotelController::class, 'info']);
$router->get('/api/v1/hotel/room-service', [\App\Controllers\Api\HotelController::class, 'roomService']);
$router->get('/api/v1/hotel/attractions',  [\App\Controllers\Api\HotelController::class, 'attractions']);
$router->get('/api/v1/hotel/weather',      [\App\Controllers\Api\HotelController::class, 'weather']);

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
    // RPi bridge endpoints
});
// Inflight public — player endpoint (no auth)

// ── Broadcast — پخش فوری
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    $r->post('/screens/{id}/broadcast',       [\App\Controllers\Api\BroadcastController::class, 'send']);
    $r->post('/screens/{id}/broadcast/clear', [\App\Controllers\Api\BroadcastController::class, 'clear']);
    $r->post('/broadcast/all',                [\App\Controllers\Api\BroadcastController::class, 'sendAll']);
});

// Heartbeat endpoint برای پلیر (پخش فوری رو برمی‌گردونه)
$router->post('/api/v1/screens/{code}/heartbeat', [\App\Controllers\Api\ScreenController::class, 'heartbeat']);

// ── Guest Services — خدمات مهمان (پنل کارکنان، protected) ────────
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    // کاتالوگ خدمات
    $r->get('/guest/services',              [\App\Controllers\Api\GuestServiceController::class, 'services']);
    $r->post('/guest/services',             [\App\Controllers\Api\GuestServiceController::class, 'storeService']);
    $r->put('/guest/services/{id}',         [\App\Controllers\Api\GuestServiceController::class, 'updateService']);
    $r->delete('/guest/services/{id}',      [\App\Controllers\Api\GuestServiceController::class, 'destroyService']);
    // صف درخواست‌ها
    $r->get('/guest/requests/stats',        [\App\Controllers\Api\GuestServiceController::class, 'stats']);
    $r->get('/guest/requests',              [\App\Controllers\Api\GuestServiceController::class, 'requests']);
    $r->get('/guest/requests/{id}',         [\App\Controllers\Api\GuestServiceController::class, 'showRequest']);
    $r->put('/guest/requests/{id}/status',  [\App\Controllers\Api\GuestServiceController::class, 'updateStatus']);
});

// ── Guest Portal — تلویزیون اتاق (بدون JWT، هویت با کد صفحه‌نمایش)
$router->get('/api/v1/guest/{code}/services',                 [\App\Controllers\Api\GuestPortalController::class, 'services']);
$router->get('/api/v1/guest/{code}/requests',                 [\App\Controllers\Api\GuestPortalController::class, 'myRequests']);
$router->post('/api/v1/guest/{code}/requests',                [\App\Controllers\Api\GuestPortalController::class, 'store']);
$router->post('/api/v1/guest/{code}/requests/{id}/cancel',    [\App\Controllers\Api\GuestPortalController::class, 'cancel']);

// ── EPG — راهنمای الکترونیکی برنامه‌ها (پنل، protected) ──────────
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    // منابع
    $r->get('/epg/sources',                 [\App\Controllers\Api\EpgController::class, 'sources']);
    $r->post('/epg/sources',                [\App\Controllers\Api\EpgController::class, 'storeSource']);
    $r->delete('/epg/sources/{id}',         [\App\Controllers\Api\EpgController::class, 'destroySource']);
    $r->post('/epg/sources/{id}/sync',      [\App\Controllers\Api\EpgController::class, 'syncSource']);
    // جدول پخش
    $r->get('/epg/now',                     [\App\Controllers\Api\EpgController::class, 'now']);
    $r->get('/epg/grid',                    [\App\Controllers\Api\EpgController::class, 'grid']);
    $r->get('/epg/channel/{id}',            [\App\Controllers\Api\EpgController::class, 'channel']);
});

// ── EPG عمومی — تلویزیون اتاق (بدون JWT، هویت با کد صفحه‌نمایش)
$router->get('/api/v1/player/epg/{code}',   [\App\Controllers\Api\EpgController::class, 'playerNow']);

// ── Portal — صفحه اصلی تلویزیون اتاق (بدون JWT، هویت با کد صفحه‌نمایش)
$router->get('/api/v1/portal/{code}',      [\App\Controllers\Api\PortalController::class, 'home']);
$router->get('/api/v1/portal/{code}/live', [\App\Controllers\Api\PortalController::class, 'live']);

// ── Device — سمت تلویزیون (بدون JWT) ─────────────────────────────
$router->post('/api/v1/device/enroll',            [\App\Controllers\Api\DeviceController::class, 'enroll']);
$router->get('/api/v1/device/{code}/commands',    [\App\Controllers\Api\DeviceController::class, 'commands']);
$router->post('/api/v1/device/{code}/ack',        [\App\Controllers\Api\DeviceController::class, 'ack']);

// ── Device — پنل مدیریت (protected) ──────────────────────────────
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    $r->get('/devices/stats',                 [\App\Controllers\Api\DeviceController::class, 'stats']);
    $r->get('/devices/tokens',                [\App\Controllers\Api\DeviceController::class, 'tokens']);
    $r->post('/devices/tokens',               [\App\Controllers\Api\DeviceController::class, 'storeToken']);
    $r->delete('/devices/tokens/{id}',        [\App\Controllers\Api\DeviceController::class, 'destroyToken']);
    $r->post('/devices/bulk-command',         [\App\Controllers\Api\DeviceController::class, 'bulkCommand']);
    $r->get('/devices',                       [\App\Controllers\Api\DeviceController::class, 'index']);
    $r->get('/devices/{id}/history',          [\App\Controllers\Api\DeviceController::class, 'history']);
    $r->post('/devices/{id}/approve',         [\App\Controllers\Api\DeviceController::class, 'approve']);
    $r->post('/devices/{id}/assign-room',     [\App\Controllers\Api\DeviceController::class, 'assignRoom']);
    $r->post('/devices/{id}/command',         [\App\Controllers\Api\DeviceController::class, 'command']);
});

// ── Folio / مینی‌بار / PPV / خروج سریع — مهمان (بدون JWT) ────────
$router->get('/api/v1/guest/{code}/folio',                [\App\Controllers\Api\FolioController::class, 'guestFolio']);
$router->get('/api/v1/guest/{code}/menus',                [\App\Controllers\Api\FolioController::class, 'guestMenus']);
$router->post('/api/v1/guest/{code}/checkout',            [\App\Controllers\Api\FolioController::class, 'guestCheckout']);
$router->get('/api/v1/guest/{code}/vod/{id}/access',      [\App\Controllers\Api\FolioController::class, 'guestAccess']);
$router->post('/api/v1/guest/{code}/vod/{id}/purchase',   [\App\Controllers\Api\FolioController::class, 'guestPurchase']);

// ── Folio — کارکنان (protected) ─────────────────────────────────
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    // صورتحساب
    $r->get('/rooms/{id}/folio',        [\App\Controllers\Api\FolioController::class, 'roomFolio']);
    $r->post('/rooms/{id}/charges',     [\App\Controllers\Api\FolioController::class, 'addCharge']);
    $r->post('/charges/{id}/void',      [\App\Controllers\Api\FolioController::class, 'voidCharge']);
    // مینی‌بار
    $r->get('/minibar/items',           [\App\Controllers\Api\FolioController::class, 'minibarItems']);
    $r->post('/minibar/items',          [\App\Controllers\Api\FolioController::class, 'storeMinibarItem']);
    $r->put('/minibar/items/{id}',      [\App\Controllers\Api\FolioController::class, 'updateMinibarItem']);
    $r->delete('/minibar/items/{id}',   [\App\Controllers\Api\FolioController::class, 'destroyMinibarItem']);
    $r->post('/rooms/{id}/minibar',     [\App\Controllers\Api\FolioController::class, 'recordMinibar']);
    // خروج سریع
    $r->get('/checkout-requests',       [\App\Controllers\Api\FolioController::class, 'checkoutRequests']);
    $r->post('/checkout-requests/{id}', [\App\Controllers\Api\FolioController::class, 'handleCheckout']);
    // ارسال به PMS
    $r->post('/pms/push',               [\App\Controllers\Api\FolioController::class, 'pmsPush']);
    $r->post('/pms/test',               [\App\Controllers\Api\FolioController::class, 'pmsTest']);
    $r->post('/charges/{id}/pms-retry', [\App\Controllers\Api\FolioController::class, 'pmsRetry']);
});

// ── منوهای تصویری (protected) ───────────────────────────────────
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    $r->get('/menu-boards',                       [\App\Controllers\Api\MenuBoardController::class, 'index']);
    $r->post('/menu-boards',                      [\App\Controllers\Api\MenuBoardController::class, 'store']);
    $r->put('/menu-boards/{id}',                  [\App\Controllers\Api\MenuBoardController::class, 'update']);
    $r->delete('/menu-boards/{id}',               [\App\Controllers\Api\MenuBoardController::class, 'destroy']);
    $r->post('/menu-boards/{id}/pages',           [\App\Controllers\Api\MenuBoardController::class, 'uploadPage']);
    $r->post('/menu-boards/{id}/pages/sort',      [\App\Controllers\Api\MenuBoardController::class, 'sortPages']);
    $r->delete('/menu-board-pages/{pageId}',      [\App\Controllers\Api\MenuBoardController::class, 'destroyPage']);
});

// ── Multicast — پخش زنده یک‌باره روی شبکه ────────────────────────
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    $r->get('/multicast/config',       [\App\Controllers\Api\MulticastController::class, 'config']);
    $r->post('/multicast/config',      [\App\Controllers\Api\MulticastController::class, 'saveConfig']);
    $r->post('/multicast/assign',      [\App\Controllers\Api\MulticastController::class, 'assign']);
    $r->get('/multicast/playlist.m3u', [\App\Controllers\Api\MulticastController::class, 'playlist']);
});

// ── محتوای جانبی و قفل والدین — مهمان (بدون JWT) ────────────────
$router->get('/api/v1/guest/{code}/channels',              [\App\Controllers\Api\ContentController::class, 'guestChannels']);
$router->get('/api/v1/guest/{code}/content/{kind}',        [\App\Controllers\Api\ContentController::class, 'guestContent']);
$router->get('/api/v1/guest/{code}/content/{kind}/{id}',   [\App\Controllers\Api\ContentController::class, 'guestContentItem']);
$router->post('/api/v1/guest/{code}/parental/unlock',      [\App\Controllers\Api\ContentController::class, 'unlock']);
$router->post('/api/v1/guest/{code}/parental/pin',         [\App\Controllers\Api\ContentController::class, 'setPin']);
$router->delete('/api/v1/guest/{code}/parental/pin',       [\App\Controllers\Api\ContentController::class, 'disablePin']);
$router->get('/api/v1/guest/{code}/wakeups',               [\App\Controllers\Api\ContentController::class, 'dueWakeups']);
$router->post('/api/v1/guest/{code}/wakeups/{id}/ack',     [\App\Controllers\Api\ContentController::class, 'ackWakeup']);

// ── محتوای جانبی — پنل (protected) ──────────────────────────────
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    $r->get('/content',            [\App\Controllers\Api\ContentController::class, 'index']);
    $r->post('/content',           [\App\Controllers\Api\ContentController::class, 'store']);
    $r->put('/content/{id}',       [\App\Controllers\Api\ContentController::class, 'update']);
    $r->delete('/content/{id}',    [\App\Controllers\Api\ContentController::class, 'destroy']);
});

// ── محل‌های هتل و رویدادها — محیط عمومی (protected) ──────────────
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuthMiddleware::class]], function($r) {
    $r->get('/venues',                    [\App\Controllers\Api\VenueController::class, 'index']);
    $r->post('/venues',                   [\App\Controllers\Api\VenueController::class, 'store']);
    $r->put('/venues/{id}',               [\App\Controllers\Api\VenueController::class, 'update']);
    $r->delete('/venues/{id}',            [\App\Controllers\Api\VenueController::class, 'destroy']);
    $r->post('/venues/{id}/assign-screen',[\App\Controllers\Api\VenueController::class, 'assignScreen']);
    // رویدادهای سالن
    $r->get('/events',                    [\App\Controllers\Api\VenueController::class, 'events']);
    $r->post('/events',                   [\App\Controllers\Api\VenueController::class, 'storeEvent']);
    $r->put('/events/{id}',               [\App\Controllers\Api\VenueController::class, 'updateEvent']);
    $r->delete('/events/{id}',            [\App\Controllers\Api\VenueController::class, 'destroyEvent']);
});
