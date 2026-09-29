<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\ReservationService;

/**
 * رزرو رستوران و امکانات — TODO ۲.۱۳ و ۲.۱۴
 *
 * دو ورودی دارد مثل خدمات مهمان: تلویزیون اتاق بدون JWT و با کد صفحه،
 * و پنل کارکنان با نشست. منطق ظرفیت و صورتحساب در ReservationService
 * است تا هر دو مسیر یک قاعده را اجرا کنند.
 */
class ReservationController extends Controller
{
    private ReservationService $svc;

    public function __construct()
    {
        parent::__construct();
        $this->svc = new ReservationService($this->db);
    }

    // ══════════════════════════════════════════════════════════════
    //  تلویزیون اتاق
    // ══════════════════════════════════════════════════════════════

    /** GET /api/v1/guest/{code}/reservations/venues */
    public function guestVenues(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یا اتاق یافت نشد'); return; }

        Response::success([
            'room_number' => $ctx['room_number'],
            'occupied'    => $ctx['status'] === 'occupied',
            'venues'      => $this->svc->bookableVenues((int)$ctx['tenant_id']),
        ]);
    }

    /** GET /api/v1/guest/{code}/reservations/slots?venue_id=&date=Y-m-d */
    public function guestSlots(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یا اتاق یافت نشد'); return; }

        $res = $this->svc->slots((int)$ctx['tenant_id'], (int)$req->get('venue_id', 0),
                                 (string)$req->get('date', date('Y-m-d')));
        if (!$res['ok']) { Response::error($res['message'], 422); return; }
        Response::success($res['slots']);
    }

    /** GET /api/v1/guest/{code}/reservations */
    public function guestList(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یا اتاق یافت نشد'); return; }

        Response::success($this->svc->forRoom((int)$ctx['tenant_id'], (int)$ctx['room_id'],
                                              $ctx['check_in_at'] ?: null));
    }

    /** POST /api/v1/guest/{code}/reservations  body: {venue_id, start_at, party_size, note} */
    public function guestStore(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یا اتاق یافت نشد'); return; }

        $data = $req->json() ?: [];
        $res  = $this->svc->create((int)$ctx['tenant_id'], [
            'venue_id'   => $data['venue_id'] ?? 0,
            'start_at'   => $data['start_at'] ?? '',
            'party_size' => $data['party_size'] ?? 0,
            'note'       => $data['note'] ?? '',
            // اتاق از کد صفحه می‌آید، نه از بدنه‌ی درخواست
            'room_id'    => (int)$ctx['room_id'],
        ], 'tv');

        if (!$res['ok']) { Response::error($res['message'], $res['code']); return; }
        Response::success(['id' => $res['id'], 'status' => 'pending'], $res['message'], 201);
    }

    /** POST /api/v1/guest/{code}/reservations/{id}/cancel */
    public function guestCancel(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یا اتاق یافت نشد'); return; }

        $res = $this->svc->cancelByGuest((int)$ctx['tenant_id'], (int)$ctx['room_id'], (int)($params['id'] ?? 0));
        if (!$res['ok']) { Response::error($res['message'], $res['code']); return; }
        Response::success(null, $res['message']);
    }

    // ══════════════════════════════════════════════════════════════
    //  پنل کارکنان
    // ══════════════════════════════════════════════════════════════

    /** GET /api/v1/reservations?date=&venue_id=&status= */
    public function index(Request $req): void
    {
        $tid  = Auth::tenantId();
        $date = (string)$req->get('date', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { Response::error('تاریخ نامعتبر است', 422); return; }

        /* رزرو بعد از نیمه‌شب کافی‌شاپ به همان شب تعلق دارد، پس روز
           کاری تا ۶ صبح فردا ادامه دارد. */
        $sql = 'SELECT r.*, v.name AS venue_name, v.kind, rm.room_number
                  FROM reservations r
                  JOIN venues v ON v.id = r.venue_id
                  LEFT JOIN iptv_rooms rm ON rm.id = r.room_id
                 WHERE r.tenant_id = ? AND r.start_at >= ? AND r.start_at < DATE_ADD(?, INTERVAL 30 HOUR)';
        $par = [$tid, $date . ' 00:00:00', $date . ' 00:00:00'];

        if ($vid = (int)$req->get('venue_id', 0)) { $sql .= ' AND r.venue_id = ?'; $par[] = $vid; }
        if ($st = (string)$req->get('status', '')) {
            if (!in_array($st, ReservationService::STATUSES, true)) { Response::error('وضعیت نامعتبر است', 422); return; }
            $sql .= ' AND r.status = ?'; $par[] = $st;
        }

        $sql .= ' ORDER BY r.start_at, v.name LIMIT 500';
        Response::success($this->db->rows($sql, $par));
    }

    /** GET /api/v1/reservations/slots?venue_id=&date= */
    public function slots(Request $req): void
    {
        $res = $this->svc->slots(Auth::tenantId(), (int)$req->get('venue_id', 0),
                                 (string)$req->get('date', date('Y-m-d')));
        if (!$res['ok']) { Response::error($res['message'], 422); return; }
        Response::success($res['slots']);
    }

    /** POST /api/v1/reservations — ثبت تلفنی یا حضوری از پذیرش */
    public function store(Request $req): void
    {
        $tid  = Auth::tenantId();
        $data = $req->json() ?: [];

        /* پذیرش شماره‌ی اتاق را می‌داند، نه شناسه‌ی داخلی آن را */
        $roomId = null;
        $roomNo = trim((string)($data['room_number'] ?? ''));
        if ($roomNo !== '') {
            $roomId = (int)$this->db->value(
                'SELECT id FROM iptv_rooms WHERE tenant_id = ? AND room_number = ?',
                [$tid, $roomNo]
            );
            if (!$roomId) { Response::error("اتاق $roomNo یافت نشد", 404); return; }
        }

        $res = $this->svc->create($tid, [
            'venue_id'   => $data['venue_id'] ?? 0,
            'start_at'   => $data['start_at'] ?? '',
            'party_size' => $data['party_size'] ?? 0,
            'note'       => $data['note'] ?? '',
            'room_id'    => $roomId,
            'guest_name' => $data['guest_name'] ?? '',
        ], 'panel', Auth::id());

        if (!$res['ok']) { Response::error($res['message'], $res['code']); return; }
        $this->log('reservation.create', 'reservation', (int)$res['id']);
        Response::success(['id' => $res['id']], 'رزرو ثبت شد', 201);
    }

    /** PUT /api/v1/reservations/{id}/status  body: {status, staff_note} */
    public function updateStatus(Request $req, array $params): void
    {
        $data = $req->json() ?: [];
        $id   = (int)($params['id'] ?? 0);
        $to   = (string)($data['status'] ?? '');

        $res = $this->svc->setStatus(Auth::tenantId(), $id, $to, Auth::id(),
                                     isset($data['staff_note']) ? (string)$data['staff_note'] : null);
        if (!$res['ok']) { Response::error($res['message'], $res['code']); return; }

        $this->log('reservation.' . $to, 'reservation', $id);
        Response::success(null, $res['message']);
    }

    // ══════════════════════════════════════════════════════════════

    /** کد صفحه‌نمایش → اتاق متصل به آن. همان قاعده‌ی GuestPortalController. */
    private function resolveRoom(string $code): ?array
    {
        if ($code === '') return null;

        return $this->db->row(
            'SELECT s.tenant_id, r.id AS room_id, r.room_number, r.status, r.check_in_at
               FROM screens s
               JOIN iptv_rooms r ON r.id = s.iptv_room_id
              WHERE s.code = ?',
            [$code]
        ) ?: null;
    }
}
