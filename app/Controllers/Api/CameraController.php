<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\CameraService;

/**
 * Camera Controller — دوربین مداربسته.
 * سمت پنل: CRUD، شروع/توقف رله، لاگ ffmpeg.
 * سمت مهمان: فهرست دوربین‌های مجاز با آدرس HLS (بدون rtsp_url).
 *
 * فاز ۱۰ نقشه‌راه — docs/TODO.md (۵.۷)
 */
class CameraController extends Controller
{
    private CameraService $svc;

    public function __construct()
    {
        parent::__construct();
        $this->svc = new CameraService($this->db);
    }

    // ══════════════════════════════════════════════════════════════
    //  پنل
    // ══════════════════════════════════════════════════════════════

    /** GET /api/v1/cameras */
    public function index(Request $req): void
    {
        $tid  = Auth::tenantId();
        $out  = [];
        foreach ($this->svc->all($tid) as $c) {
            $st = $this->svc->status($c);
            // rtsp_url فقط برای اپراتور، و ماسک‌شده تا رمز در لاگ مرورگر نیفتد
            $out[] = [
                'id'            => (int)$c['id'],
                'name'          => $c['name'],
                'location'      => $c['location'],
                'rtsp_masked'   => $this->mask((string)$c['rtsp_url']),
                'stream_name'   => $c['stream_name'],
                'quality'       => $c['quality'],
                'audio'         => $c['audio'],
                'guest_visible' => (int)$c['guest_visible'],
                'access_level'  => (int)$c['access_level'],
                'is_active'     => (int)$c['is_active'],
                'hls'           => $this->svc->hlsUrl($c),
                'running'       => $st['running'],
                'fresh'         => $st['fresh'],
                'age'           => $st['age'],
            ];
        }
        Response::success($out);
    }

    /** POST /api/v1/cameras */
    public function store(Request $req): void
    {
        $d   = $req->json() ?: $req->post() ?: [];
        $res = $this->svc->create(Auth::tenantId(), $d);
        if (!$res['ok']) { Response::error($res['message'], 422); return; }
        $this->log('camera.create', 'Camera', $res['id'] ?? 0);
        Response::success(['id' => $res['id'] ?? null], $res['message'], 201);
    }

    /** PUT /api/v1/cameras/{id} */
    public function update(Request $req, array $params): void
    {
        $d   = $req->json() ?: $req->post() ?: [];
        $res = $this->svc->update(Auth::tenantId(), (int)($params['id'] ?? 0), $d);
        $res['ok'] ? Response::success(null, $res['message']) : Response::error($res['message'], 422);
    }

    /** DELETE /api/v1/cameras/{id} */
    public function destroy(Request $req, array $params): void
    {
        $res = $this->svc->delete(Auth::tenantId(), (int)($params['id'] ?? 0));
        if ($res['ok']) $this->log('camera.delete', 'Camera', (int)($params['id'] ?? 0));
        $res['ok'] ? Response::success(null, $res['message']) : Response::error($res['message'], 404);
    }

    /** POST /api/v1/cameras/{id}/start */
    public function start(Request $req, array $params): void
    {
        $cam = $this->svc->find(Auth::tenantId(), (int)($params['id'] ?? 0));
        if (!$cam) { Response::notFound('دوربین یافت نشد'); return; }

        $res = $this->svc->start($cam);
        if ($res['ok']) $this->log('camera.start', 'Camera', (int)$cam['id']);
        $res['ok'] ? Response::success(['pid' => $res['pid'] ?? 0], $res['message']) : Response::error($res['message']);
    }

    /** POST /api/v1/cameras/{id}/stop */
    public function stop(Request $req, array $params): void
    {
        $cam = $this->svc->find(Auth::tenantId(), (int)($params['id'] ?? 0));
        if (!$cam) { Response::notFound('دوربین یافت نشد'); return; }

        $res = $this->svc->stop($cam);
        $this->log('camera.stop', 'Camera', (int)$cam['id']);
        Response::success(null, $res['message']);
    }

    /** GET /api/v1/cameras/{id}/log */
    public function logTail(Request $req, array $params): void
    {
        $cam = $this->svc->find(Auth::tenantId(), (int)($params['id'] ?? 0));
        if (!$cam) { Response::notFound('دوربین یافت نشد'); return; }
        Response::success(['log' => $this->svc->log($cam, 60)]);
    }

    // ══════════════════════════════════════════════════════════════
    //  مهمان (بدون JWT — هویت با کد صفحه)
    // ══════════════════════════════════════════════════════════════

    /** GET /api/v1/guest/{code}/cameras */
    public function guestList(Request $req, array $params): void
    {
        $code = (string)($params['code'] ?? '');
        if ($code === '') { Response::success([]); return; }

        $row = $this->db->row(
            'SELECT s.tenant_id, COALESCE(r.access_level, 0) AS access_level
               FROM screens s
               LEFT JOIN iptv_rooms r ON r.id = s.iptv_room_id
              WHERE s.code = ?',
            [$code]
        );
        if (!$row) { Response::success([]); return; }

        Response::success(
            $this->svc->forGuest((int)$row['tenant_id'], (int)$row['access_level'])
        );
    }

    /** رمز داخل آدرس RTSP را می‌پوشاند: rtsp://user:***@host/path */
    private function mask(string $url): string
    {
        return (string)preg_replace('#(://[^:/@]+):[^@]*@#', '$1:***@', $url);
    }
}
