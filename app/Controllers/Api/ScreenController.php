<?php declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Controller, Response, Request};
use App\Models\Screen;
use App\Services\WebSocketService;

class ScreenController extends Controller
{
    private Screen $screen;

    public function __construct()
    {
        parent::__construct();
        $this->screen = new Screen();
    }

    public function index(Request $req): void
    {
        $result = $this->screen->all($req->get(), (int)$req->get('page', 1));
        Response::paginated($result);
    }

    public function show(Request $req, array $params): void
    {
        $screen = $this->screen->find((int)$params['id']);
        if (!$screen) Response::notFound('صفحه یافت نشد');
        Response::success($screen);
    }

    public function store(Request $req): void
    {
        $errors = $req->validate(['name' => 'required|max:255', 'orientation' => 'in:landscape,portrait']);
        if ($errors) Response::error('داده‌ها نامعتبر', 422, $errors);

        $id = $this->screen->create($req->post());
        $this->log('screen.create', 'Screen', (int)$id);
        Response::success($this->screen->find((int)$id), 'صفحه ایجاد شد', 201);
    }

    public function update(Request $req, array $params): void
    {
        $screen = $this->screen->find((int)$params['id']);
        if (!$screen) Response::notFound();

        $this->screen->update((int)$params['id'], $req->input());
        $this->log('screen.update', 'Screen', (int)$params['id']);
        Response::success($this->screen->find((int)$params['id']), 'صفحه به‌روز شد');
    }

    public function destroy(Request $req, array $params): void
    {
        $this->screen->delete((int)$params['id']);
        $this->log('screen.delete', 'Screen', (int)$params['id']);
        Response::success(null, 'صفحه حذف شد');
    }

    public function generateActivation(Request $req, array $params): void
    {
        $code = $this->screen->generateActivationCode((int)$params['id']);
        Response::success(['activation_code' => $code, 'expires_in' => 600]);
    }

    public function heartbeat(Request $req, array $params): void
    {
        $screen = $this->screen->findByCode($params['code']);
        if (!$screen) Response::notFound('صفحه یافت نشد');

        /* هر پخش‌کننده ضربان را با بدنه‌ی JSON می‌فرستد، ولی post()
           فقط $_POST را می‌خواند که برای Content-Type: application/json
           خالی است. نتیجه: نسخه‌ی پلیر، آیتم جاری و اندازه‌های دستگاه
           هیچ‌وقت ذخیره نمی‌شدند — ستون player_version همیشه NULL بود
           و کسی متوجه نمی‌شد چون خطایی رخ نمی‌داد. */
        $body = $req->post() ?: [];
        if (!$body) $body = $req->json();

        $this->screen->heartbeat($screen['id'], array_merge($body, ['ip' => $req->ip()]));

        /* هر فرمان با هر دو کلید فرستاده می‌شود.
           سرور cmd می‌دهد ولی پخش‌کننده‌هایی که همین حالا روی
           تلویزیون‌های هتل در حال اجرا هستند دنبال command می‌گردند —
           و چون آن‌ها را نمی‌شود از راه دور به‌روز کرد مگر با همین
           فرمان‌ها، بدون این سازگاری هیچ راهی برای نجاتشان نیست. */
        $cmd = static fn(string $name, mixed $data = null): array => array_filter([
            'cmd'     => $name,
            'command' => $name,
            'data'    => $data,
            'payload' => $data,
        ], static fn($v) => $v !== null);

        $cmds = [];
        if ($screen['reboot_requested']) {
            $cmds[] = $cmd('reboot');
            $this->screen->update($screen['id'], ['reboot_requested' => 0]);
        }
        if ($screen['refresh_requested']) {
            /* پخش‌کننده‌های قدیمی فقط reload را می‌شناسند، نه refresh */
            $cmds[] = $cmd('reload');
            $this->screen->update($screen['id'], ['refresh_requested' => 0]);
        }
        if ($screen['emergency_broadcast']) {
            $cmds[] = $cmd('emergency', $screen['emergency_broadcast']);
            $this->screen->update($screen['id'], ['emergency_broadcast' => null]);
        }

        $playlist = $this->screen->getCurrentPlaylist($screen['id']);

        // برای صفحات IPTV: شناسه منو رو برمیگردونیم
        $iptvMenuId = null;
        if (($screen['screen_type'] ?? 'signage') === 'iptv' && !empty($screen['iptv_menu_id'])) {
            $iptvMenuId = (int)$screen['iptv_menu_id'];
        }

        // پیام‌های زمان‌بندی‌شده
        $pendingMessages = [];
        try {
            $pendingMessages = \App\Controllers\Web\MessagesController::getPendingForScreen(
                $screen['id'],
                $screen['tenant_id'] ?? 1
            );
        } catch (\Throwable $e) {}

        // تنظیمات 3D
        $cfg3d = null;
        if (($screen['screen_type'] ?? 'signage') === 'monitor_3d') {
            try {
                $cfg3d = $this->db->row(
                    "SELECT format_3d, depth_level, depth_color, bg_color, is_outdoor,
                            auto_rotate, rotate_speed, parallax_intensity, show_depth_badge
                     FROM monitor_3d_configs WHERE screen_id=?",
                    [$screen['id']]
                );
            } catch (\Throwable $e) {}
        }

        // ── صف فرمان جدید (screen_commands) ────────────────────────
        // پرچم‌های بولی بالا برای پلیرهای قدیمی حفظ شده‌اند؛ دستگاه‌های
        // جدید فرمان‌های دارای پارامتر و قابل‌تایید را از این صف می‌گیرند.
        try {
            $svc = new \App\Services\DeviceService($this->db);

            /* از همان بدنه‌ای خوانده می‌شود که بالا حل شد — نه $_POST،
               چون پلیر JSON می‌فرستد. */
            $info = array_filter([
                'platform'    => $body['platform']    ?? null,
                'model'       => $body['model']       ?? null,
                'firmware'    => $body['firmware']    ?? null,
                'serial'      => $body['serial']      ?? null,
                'mac'         => $body['mac']         ?? null,
                'app_version' => $body['app_version'] ?? null,
                'resolution'  => $body['resolution']  ?? null,
                'user_agent'  => $req->userAgent(),
            ], static fn($v) => $v !== null && $v !== '');

            if (count($info) > 1) $svc->updateDeviceInfo((int)$screen['id'], $info);

            /* فرمان‌های صف هم با هر دو کلید، به همان دلیل بالا */
            foreach ($svc->pullCommands((int)$screen['id']) as $queued) {
                if (isset($queued['cmd']) && !isset($queued['command'])) {
                    $queued['command'] = $queued['cmd'];
                }
                if (isset($queued['payload']) && !isset($queued['data'])) {
                    $queued['data'] = $queued['payload'];
                }
                $cmds[] = $queued;
            }
        } catch (\Throwable $e) {
            // heartbeat هرگز نباید به‌خاطر صف فرمان شکست بخورد — پلیر
            // در این صورت کل پخش را از دست می‌دهد.
            error_log('[HEARTBEAT COMMANDS] ' . $e->getMessage());
        }

        /* شناسه‌ی نسخه‌ی پلی‌لیست.
           ‏playlist_id تنها کافی نیست: وقتی اپراتور آیتمی به همان
           پلی‌لیست اضافه می‌کند یا مدت را عوض می‌کند، شناسه ثابت می‌ماند
           و تلویزیون هرگز نمی‌فهمد چیزی عوض شده. پس بزرگ‌ترین زمان
           تغییر بین خود پلی‌لیست، آیتم‌هایش، و رسانه‌های آن‌ها را
           می‌دهیم — رسانه هم لازم است چون پایان تبدیل یک ویدیو آن را
           از حالت processing به قابل‌پخش می‌برد. */
        $playlistRev = null;
        if (!empty($playlist['id'])) {
            try {
                $playlistRev = (int)$this->db->value(
                    "SELECT UNIX_TIMESTAMP(GREATEST(
                        p.updated_at,
                        COALESCE((SELECT MAX(pi.updated_at) FROM playlist_items pi
                                   WHERE pi.playlist_id = p.id), p.updated_at),
                        COALESCE((SELECT MAX(m.updated_at) FROM playlist_items pi2
                                   JOIN media m ON m.id = pi2.media_id
                                  WHERE pi2.playlist_id = p.id), p.updated_at)
                     )) FROM playlists p WHERE p.id = ?",
                    [(int)$playlist['id']]
                );
            } catch (\Throwable $e) {}
        }

        Response::success([
            'commands'         => $cmds,
            'playlist_id'      => $playlist['id'] ?? null,
            'playlist_rev'     => $playlistRev,
            'screen_type'      => $screen['screen_type'] ?? 'signage',
            'iptv_menu_id'     => $iptvMenuId,
            'cfg_3d'           => $cfg3d,
            'messages'         => $pendingMessages,
            // پلیرها این مقدار را می‌خوانند و فاصله‌ی heartbeat خود را با آن
            // تنظیم می‌کنند. در هتل ۳۰۰ اتاقه، هر ثانیه کم‌کردن این عدد
            // مستقیم به بار وب‌سرور اضافه می‌شود، پس از .env قابل تنظیم است.
            'sync_interval'    => max(10, min(300, (int)env('PLAYER_SYNC_INTERVAL', 30))),
        ]);
    }

    public function getPlaylist(Request $req, array $params): void
    {
        $screen = $this->screen->findByCode($params['code']);
        if (!$screen) Response::notFound();
        $playlist = $this->screen->getCurrentPlaylist($screen['id']);
        if (!$playlist) Response::success(null, 'هیچ پلی‌لیستی تنظیم نشده');

        $model = new \App\Models\Playlist();
        Response::success($model->getForPlayer((int)$playlist['id'], $screen));
    }

    public function command(Request $req, array $params): void
    {
        $cmd = $req->post('command');
        if (!in_array($cmd, ['reboot', 'refresh', 'emergency', 'screenshot'])) {
            Response::error('دستور نامعتبر', 400);
        }
        $this->screen->sendCommand((int)$params['id'], $cmd, $req->post('payload'));
        $this->log("screen.command.$cmd", 'Screen', (int)$params['id']);
        Response::success(null, 'دستور ارسال شد');
    }

    public function stats(Request $req): void
    {
        Response::success($this->screen->getStats());
    }

    public function allStatus(Request $req): void
    {
        $tid = Auth::tenantId();
        $screens = $this->db->rows(
            "SELECT s.id, s.code, s.name, s.is_online, s.status, s.last_seen_at,
                    s.current_playlist_id, p.name AS playlist_name,
                    hb.current_item, hb.cpu_usage, hb.memory_usage,
                    hb.created_at AS last_heartbeat_at,
                    TIMESTAMPDIFF(SECOND, s.last_seen_at, NOW()) AS seconds_ago
             FROM screens s
             LEFT JOIN playlists p ON p.id = s.current_playlist_id
             LEFT JOIN heartbeats hb ON hb.id = (
                 SELECT MAX(id) FROM heartbeats WHERE screen_id = s.id
             )
             WHERE s.tenant_id=? AND s.status != 'inactive'
             ORDER BY s.is_online DESC, s.name ASC",
            [$tid]
        );
        Response::success($screens);
    }

}