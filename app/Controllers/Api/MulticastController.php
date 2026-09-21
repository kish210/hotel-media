<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\MulticastService;

/**
 * Multicast Controller
 * تنظیم پخش زنده به‌صورت multicast و خروجی فهرست کانال برای تلویزیون‌ها.
 */
class MulticastController extends Controller
{
    private MulticastService $svc;

    public function __construct()
    {
        parent::__construct();
        $this->svc = new MulticastService($this->db);
    }

    /** GET /multicast/config */
    public function config(Request $req): void
    {
        $tid = Auth::tenantId();

        Response::success([
            'config'    => $this->svc->config($tid),
            'bandwidth' => $this->svc->bandwidthEstimate(
                $tid,
                max(1, min(5000, (int)$req->get('rooms', 300))),
                (string)$req->get('quality', 'hd')
            ),
        ]);
    }

    /** POST /multicast/config */
    public function saveConfig(Request $req): void
    {
        $res = $this->svc->saveConfig(Auth::tenantId(), $req->json() ?: []);
        if (!$res['ok']) { Response::error($res['message'], 422); return; }

        $this->log('multicast.config');
        Response::success(null, $res['message']);
    }

    /** POST /multicast/assign — تخصیص آدرس گروه به کانال‌ها */
    public function assign(Request $req): void
    {
        $data = $req->json() ?: [];
        $all  = filter_var($data['reassign_all'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $res = $this->svc->assignAddresses(Auth::tenantId(), $all);
        if (!$res['ok']) { Response::error($res['message'], 422); return; }

        $this->log('multicast.assign', null, null, [], ['assigned' => $res['assigned']]);
        Response::success(['assigned' => $res['assigned']], $res['message']);
    }

    /**
     * GET /multicast/playlist.m3u — فهرست کانال برای وارد کردن در تلویزیون
     * ?mode=multicast|unicast|udpxy
     */
    public function playlist(Request $req): void
    {
        $mode = (string)$req->get('mode', 'multicast');
        if (!in_array($mode, ['multicast', 'unicast', 'udpxy'], true)) $mode = 'multicast';

        $m3u = $this->svc->buildM3u(Auth::tenantId(), $mode);

        header('Content-Type: audio/x-mpegurl; charset=utf-8');
        header('Content-Disposition: attachment; filename="channels-' . $mode . '.m3u"');
        header('Cache-Control: no-store');
        echo $m3u;
        exit;
    }
}
