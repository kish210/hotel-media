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

        $this->screen->heartbeat($screen['id'], array_merge($req->post(), ['ip' => $req->ip()]));

        // Return any pending commands
        $cmds = [];
        if ($screen['reboot_requested'])  { $cmds[] = ['cmd' => 'reboot'];  $this->screen->update($screen['id'], ['reboot_requested' => 0]); }
        if ($screen['refresh_requested']) { $cmds[] = ['cmd' => 'refresh']; $this->screen->update($screen['id'], ['refresh_requested' => 0]); }
        if ($screen['emergency_broadcast']) {
            $cmds[] = ['cmd' => 'emergency', 'data' => $screen['emergency_broadcast']];
            $this->screen->update($screen['id'], ['emergency_broadcast' => null]);
        }

        $playlist = $this->screen->getCurrentPlaylist($screen['id']);

        // برای صفحات IPTV: شناسه منو رو برمیگردونیم
        $iptvMenuId = null;
        if (($screen['screen_type'] ?? 'signage') === 'iptv' && !empty($screen['iptv_menu_id'])) {
            $iptvMenuId = (int)$screen['iptv_menu_id'];
        }

        Response::success([
            'commands'      => $cmds,
            'playlist_id'   => $playlist['id'] ?? null,
            'screen_type'   => $screen['screen_type'] ?? 'signage',
            'iptv_menu_id'  => $iptvMenuId,
            'sync_interval' => 30,
        ]);
    }

    public function getPlaylist(Request $req, array $params): void
    {
        $screen = $this->screen->findByCode($params['code']);
        if (!$screen) Response::notFound();
        $playlist = $this->screen->getCurrentPlaylist($screen['id']);
        if (!$playlist) Response::success(null, 'هیچ پلی‌لیستی تنظیم نشده');

        $model = new \App\Models\Playlist();
        Response::success($model->getForPlayer((int)$playlist['id']));
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