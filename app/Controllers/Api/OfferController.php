<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\OfferService;

/**
 * تخفیف کسب‌وکارهای محلی — TODO ۴.۳
 *
 * تلویزیون اتاق با کد صفحه (بدون JWT) فهرست را می‌بیند و کد می‌گیرد؛
 * پنل با نشست پیشنهادها را می‌سازد و کدها را تأیید یا باطل می‌کند.
 */
class OfferController extends Controller
{
    private OfferService $svc;

    public function __construct()
    {
        parent::__construct();
        $this->svc = new OfferService($this->db);
    }

    // ── تلویزیون اتاق ─────────────────────────────────────────────

    /** GET /api/v1/guest/{code}/offers */
    public function guestList(Request $req, array $params): void
    {
        $ctx = $this->resolveScreen((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یافت نشد'); return; }

        /* تلویزیون لابی اتاق ندارد: فهرست را می‌بیند ولی کد نمی‌گیرد */
        $room = $ctx['room_id'] && $ctx['status'] === 'occupied' ? (int)$ctx['room_id'] : null;
        Response::success([
            'occupied' => $room !== null,
            'offers'   => $this->svc->forGuest((int)$ctx['tenant_id'], $room, $ctx['check_in_at'] ?: null),
        ]);
    }

    /** POST /api/v1/guest/{code}/offers/{id}/claim */
    public function guestClaim(Request $req, array $params): void
    {
        $code = (string)($params['code'] ?? '');
        $ctx  = $this->resolveScreen($code);
        if (!$ctx || !$ctx['room_id']) { Response::notFound('اتاق یافت نشد'); return; }

        $res = $this->svc->claim($ctx, (int)($params['id'] ?? 0), $code);
        if (!$res['ok']) { Response::error($res['message'], $res['code']); return; }
        Response::success(['code' => $res['claim_code']], $res['message'], 201);
    }

    // ── پنل ──────────────────────────────────────────────────────

    /** GET /api/v1/offers */
    public function index(Request $req): void
    {
        $rows = $this->svc->listForPanel(Auth::tenantId());
        /* لینک کسب‌وکار خودش اجازه‌ی تأیید کد است؛ فقط مدیر آن را می‌بیند */
        if (!in_array(Auth::role(), self::MANAGE, true)) {
            foreach ($rows as &$r) unset($r['partner_token'], $r['partner_fails'], $r['partner_fail_at']);
            unset($r);
        }
        Response::success($rows);
    }

    /** POST /api/v1/offers */
    public function store(Request $req): void
    {
        if (!$this->can(self::MANAGE)) return;
        $res = $this->svc->save(Auth::tenantId(), $req->json() ?: []);
        if (!$res['ok']) { Response::error($res['message'], $res['code']); return; }
        $this->log('offer.create', 'local_offer', (int)$res['id']);
        Response::success(['id' => $res['id']], $res['message'], 201);
    }

    /** PUT /api/v1/offers/{id} */
    public function update(Request $req, array $params): void
    {
        if (!$this->can(self::MANAGE)) return;
        $id  = (int)($params['id'] ?? 0);
        $res = $this->svc->save(Auth::tenantId(), $req->json() ?: [], $id);
        if (!$res['ok']) { Response::error($res['message'], $res['code']); return; }
        $this->log('offer.update', 'local_offer', $id);
        Response::success(['id' => $id], $res['message']);
    }

    /** POST /api/v1/offers/{id}/rotate — لینک کسب‌وکار را عوض می‌کند */
    public function rotate(Request $req, array $params): void
    {
        if (!$this->can(self::MANAGE)) return;
        $id = (int)($params['id'] ?? 0);
        $t  = $this->svc->rotateToken(Auth::tenantId(), $id);
        if (!$t) { Response::notFound('تخفیف یافت نشد'); return; }
        $this->log('offer.rotate_link', 'local_offer', $id);
        Response::success(['partner_token' => $t], 'لینک تازه ساخته شد؛ لینک قبلی دیگر کار نمی‌کند');
    }

    /** GET /api/v1/offers/claims?offer_id= */
    public function claims(Request $req): void
    {
        $oid = (int)$req->get('offer_id', 0);
        Response::success($this->svc->claims(Auth::tenantId(), $oid ?: null));
    }

    /** POST /api/v1/offers/redeem  body: {code} */
    public function redeem(Request $req): void
    {
        if (!$this->can(self::REDEEM)) return;
        $d   = $req->json() ?: [];
        $res = $this->svc->redeem(Auth::tenantId(), (string)($d['code'] ?? ''), 'panel', Auth::id());
        if (!$res['ok']) { Response::error($res['message'], $res['code'], $res['claim'] ?? []); return; }
        $this->log('offer.redeem', 'offer_claim');
        Response::success($res['claim'], $res['message']);
    }

    /** POST /api/v1/offers/claims/{id}/void */
    public function void(Request $req, array $params): void
    {
        if (!$this->can(self::MANAGE)) return;
        $id = (int)($params['id'] ?? 0);
        if (!$this->svc->void(Auth::tenantId(), $id)) { Response::error('فقط کد استفاده‌نشده باطل می‌شود', 409); return; }
        $this->log('offer.void', 'offer_claim', $id);
        Response::success(null, 'کد باطل شد');
    }

    // ─────────────────────────────────────────────────────────────

    /* پذیرش کد را تأیید می‌کند؛ ساختن تخفیف و عوض کردن لینک کسب‌وکار
       کار مدیر است چون قرارداد با کسب‌وکار پشتش است */
    private const MANAGE = ['super_admin', 'admin', 'manager'];
    private const REDEEM = ['super_admin', 'admin', 'manager', 'editor'];

    private function can(array $roles): bool
    {
        if (in_array(Auth::role(), $roles, true)) return true;
        Response::error('دسترسی ندارید', 403);
        return false;
    }

    private function resolveScreen(string $code): ?array
    {
        if ($code === '') return null;
        return $this->db->row(
            'SELECT s.tenant_id, r.id AS room_id, r.status, r.check_in_at, r.guest_name
               FROM screens s LEFT JOIN iptv_rooms r ON r.id = s.iptv_room_id
              WHERE s.code = ?',
            [$code]
        ) ?: null;
    }
}
