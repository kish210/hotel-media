<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response};
use App\Services\VodGuestService;

/**
 * فیلم‌ها روی تلویزیون اتاق (TODO ۳.۳) — بدون JWT، هویت با کد صفحه.
 * خرید همان /guest/{code}/vod/{id}/purchase در FolioController است.
 */
class GuestVodController extends Controller
{
    private VodGuestService $svc;

    public function __construct()
    {
        parent::__construct();
        $this->svc = new VodGuestService($this->db);
    }

    /** GET /api/v1/guest/{code}/vod */
    public function index(Request $req, array $params): void
    {
        $ctx = $this->ctx((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یافت نشد'); return; }
        Response::success($this->svc->library($ctx));
    }

    /** GET /api/v1/guest/{code}/vod/{id} */
    public function show(Request $req, array $params): void
    {
        $ctx = $this->ctx((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یافت نشد'); return; }
        $v = $this->svc->detail($ctx, (int)($params['id'] ?? 0));
        if (!$v) { Response::notFound('فیلم یافت نشد'); return; }
        Response::success($v);
    }

    /** POST /api/v1/guest/{code}/vod/{id}/progress  body: {position, duration} */
    public function progress(Request $req, array $params): void
    {
        $ctx = $this->ctx((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یافت نشد'); return; }
        $d  = $req->json() ?: [];
        $ok = $this->svc->saveProgress($ctx, (int)($params['id'] ?? 0),
                                       (int)round((float)($d['position'] ?? 0)), (int)round((float)($d['duration'] ?? 0)));
        Response::success(['saved' => $ok]);
    }

    /** POST /api/v1/guest/{code}/vod/{id}/hide — برداشتن از «ادامه‌ی تماشا» */
    public function hide(Request $req, array $params): void
    {
        $ctx = $this->ctx((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یافت نشد'); return; }
        Response::success(['hidden' => $this->svc->hide($ctx, (int)($params['id'] ?? 0))]);
    }

    /** @return array{tenant_id:int,room_id:?int,stay:?string}|null */
    private function ctx(string $code): ?array
    {
        if ($code === '') return null;
        $r = $this->db->row(
            'SELECT s.tenant_id, r.id AS room_id, r.status, r.check_in_at
               FROM screens s LEFT JOIN iptv_rooms r ON r.id = s.iptv_room_id
              WHERE s.code = ?',
            [$code]
        );
        if (!$r) return null;
        /* بدون مهمان ساکن (لابی، اتاق خالی): فهرست بله، تاریخچه نه */
        $stay = ($r['room_id'] && $r['status'] === 'occupied' && $r['check_in_at'] && !str_starts_with((string)$r['check_in_at'], '0000'))
              ? (string)$r['check_in_at'] : null;
        return ['tenant_id' => (int)$r['tenant_id'], 'room_id' => $r['room_id'] ? (int)$r['room_id'] : null, 'stay' => $stay];
    }
}
