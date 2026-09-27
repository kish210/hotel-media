<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\DvrService;

/**
 * DVR Controller
 * سمت مهمان: ضبط شخصی (NPVR)، فهرست ضبط‌های من، پخش، حذف، Catch-up.
 * سمت پنل: فهرست کامل، ضبط زمان‌بندی‌شده (PVR)، سهمیه، فعال‌سازی Catch-up.
 *
 * فاز ۹ نقشه‌راه — docs/TODO.md (۱.۳، ۱.۴، ۱.۱۲، ۱.۱۳)
 */
class DvrController extends Controller
{
    private DvrService $svc;

    public function __construct()
    {
        parent::__construct();
        $this->svc = new DvrService($this->db);
    }

    // ══════════════════════════════════════════════════════════════
    //  سمت مهمان (بدون JWT — کلید: کد صفحه)
    // ══════════════════════════════════════════════════════════════

    /** @return array{tenant_id:int,room_id:int}|null */
    private function room(string $code): ?array
    {
        if ($code === '') return null;
        $row = $this->db->row(
            'SELECT s.tenant_id, s.iptv_room_id AS room_id
               FROM screens s WHERE s.code = ?',
            [$code]
        );
        if (!$row || empty($row['room_id'])) return null;
        return ['tenant_id' => (int)$row['tenant_id'], 'room_id' => (int)$row['room_id']];
    }

    /** POST /api/v1/dvr/{code}/record */
    public function guestRecord(Request $req, array $params): void
    {
        $ctx = $this->room((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('اتاق یافت نشد'); return; }

        $data = $req->json() ?: $req->post() ?: [];
        $chId = (int)($data['channel_id'] ?? 0);
        if (!$chId) { Response::error('کانال لازم است', 422); return; }

        $res = $this->svc->schedule([
            'tenant_id'   => $ctx['tenant_id'],
            'channel_id'  => $chId,
            'event_id'    => (int)($data['event_id'] ?? 0),
            'start'       => (int)($data['start'] ?? 0) ?: null,
            'stop'        => (int)($data['stop'] ?? 0) ?: null,
            'title'       => (string)($data['title'] ?? ''),
            'kind'        => 'npvr',
            'room_id'     => $ctx['room_id'],
            'screen_code' => (string)($params['code'] ?? ''),
        ]);

        if (!$res['ok']) { Response::error($res['message'], 409); return; }
        Response::success(['id' => $res['id'] ?? null], $res['message'], 201);
    }

    /** GET /api/v1/dvr/{code}/recordings */
    public function guestList(Request $req, array $params): void
    {
        $ctx = $this->room((string)($params['code'] ?? ''));
        if (!$ctx) { Response::success([]); return; }
        Response::success($this->svc->listForRoom($ctx['tenant_id'], $ctx['room_id']));
    }

    /** DELETE /api/v1/dvr/{code}/recordings/{id} */
    public function guestDelete(Request $req, array $params): void
    {
        $ctx = $this->room((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('اتاق یافت نشد'); return; }

        $id  = (int)($params['id'] ?? 0);
        // فقط ضبط متعلق به همین اتاق
        $own = $this->db->exists('dvr_recordings',
            ['id' => $id, 'tenant_id' => $ctx['tenant_id'], 'room_id' => $ctx['room_id']]);
        if (!$own) { Response::notFound('ضبط یافت نشد'); return; }

        $res = $this->svc->remove($ctx['tenant_id'], $id);
        $res['ok'] ? Response::success(null, $res['message']) : Response::error($res['message']);
    }

    /** GET /api/v1/dvr/{code}/catchup?channel_id=&event_id= */
    public function guestCatchup(Request $req, array $params): void
    {
        $ctx = $this->room((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('اتاق یافت نشد'); return; }

        $chId    = (int)$req->get('channel_id', 0);
        $eventId = (int)$req->get('event_id', 0);
        if (!$chId || !$eventId) { Response::error('کانال و برنامه لازم است', 422); return; }

        $res = $this->svc->catchupUrl($ctx['tenant_id'], $chId, $eventId);
        $res['ok'] ? Response::success(['url' => $res['url']]) : Response::error($res['message'], 404);
    }

    // ══════════════════════════════════════════════════════════════
    //  سمت پنل (JWT / session)
    // ══════════════════════════════════════════════════════════════

    /** GET /api/v1/dvr */
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();
        Response::success($this->svc->listAll(
            $tid,
            $req->get('status') ?: null,
            $req->get('kind') ?: null,
            (int)$req->get('limit', 200)
        ));
    }

    /** POST /api/v1/dvr — ضبط زمان‌بندی‌شده توسط اپراتور */
    public function store(Request $req): void
    {
        $data = $req->json() ?: $req->post() ?: [];
        $res  = $this->svc->schedule([
            'tenant_id'  => Auth::tenantId(),
            'channel_id' => (int)($data['channel_id'] ?? 0),
            'event_id'   => (int)($data['event_id'] ?? 0),
            'start'      => (int)($data['start'] ?? 0) ?: null,
            'stop'       => (int)($data['stop'] ?? 0) ?: null,
            'title'      => (string)($data['title'] ?? ''),
            'kind'       => 'pvr',
            'created_by' => Auth::id(),
        ]);
        if (!$res['ok']) { Response::error($res['message'], 409); return; }
        $this->log('dvr.schedule', 'Dvr', $res['id'] ?? 0);
        Response::success(['id' => $res['id'] ?? null], $res['message'], 201);
    }

    /** DELETE /api/v1/dvr/{id} */
    public function destroy(Request $req, array $params): void
    {
        $res = $this->svc->remove(Auth::tenantId(), (int)($params['id'] ?? 0));
        $res['ok'] ? Response::success(null, $res['message']) : Response::error($res['message']);
    }

    /** POST /api/v1/dvr/sync — هماهنگی وضعیت با TVHeadend */
    public function sync(Request $req): void
    {
        $res = $this->svc->syncStatuses(Auth::tenantId());
        $res['ok'] ? Response::success(['updated' => $res['updated']], $res['message']) : Response::error($res['message']);
    }

    /** GET /api/v1/dvr/quota/{roomId} */
    public function quota(Request $req, array $params): void
    {
        Response::success($this->svc->quota(Auth::tenantId(), (int)($params['roomId'] ?? 0)));
    }

    /** POST /api/v1/dvr/quota/{roomId} */
    public function setQuota(Request $req, array $params): void
    {
        $data = $req->json() ?: $req->post() ?: [];
        $this->svc->setQuota(
            Auth::tenantId(),
            (int)($params['roomId'] ?? 0),
            max(0, (int)($data['max_recordings'] ?? DvrService::DEFAULT_MAX_RECORDINGS)),
            max(0, (int)($data['max_minutes'] ?? DvrService::DEFAULT_MAX_MINUTES))
        );
        Response::success(null, 'سهمیه ذخیره شد');
    }

    /** POST /api/v1/dvr/catchup — فعال/غیرفعال روی یک کانال */
    public function catchup(Request $req): void
    {
        $data = $req->json() ?: $req->post() ?: [];
        $res  = $this->svc->setCatchup(
            Auth::tenantId(),
            (int)($data['channel_id'] ?? 0),
            filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            max(1, (int)($data['hours'] ?? 24))
        );
        $res['ok'] ? Response::success(null, $res['message']) : Response::error($res['message']);
    }
}
