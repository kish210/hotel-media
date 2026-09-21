<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\{FolioService, PmsChargeService};

/**
 * Folio Controller
 * صورتحساب اتاق، مینی‌بار، محتوای پولی و خروج سریع.
 *
 * سمت مهمان بدون JWT با کد صفحه‌نمایش؛ سمت کارکنان با session یا JWT.
 * فاز ۴ و ۵ نقشه‌راه — docs/TODO.md
 */
class FolioController extends Controller
{
    private FolioService $folio;

    public function __construct()
    {
        parent::__construct();
        $this->folio = new FolioService($this->db);
    }

    // ══════════════════════════════════════════════════════════════
    //  سمت مهمان — تلویزیون اتاق
    // ══════════════════════════════════════════════════════════════

    /** GET /api/v1/guest/{code}/folio — صورتحساب روی تلویزیون */
    public function guestFolio(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یا اتاق یافت نشد'); return; }

        $folio = $this->folio->folio((int)$ctx['tenant_id'], (int)$ctx['room_id']);

        // اقلام باطل‌شده و جزئیات داخلی به مهمان نشان داده نمی‌شوند
        $items = array_map(static fn(array $i): array => [
            'title'      => $i['title'],
            'qty'        => (int)$i['qty'],
            'unit_price' => (float)$i['unit_price'],
            'amount'     => (float)$i['amount'],
            'source'     => $i['source'],
            'created_at' => $i['created_at'],
        ], $folio['items']);

        Response::success([
            'room_number' => $ctx['room_number'],
            'guest_name'  => $ctx['guest_name'],
            'items'       => $items,
            'total'       => $folio['total'],
            'currency'    => $folio['currency'],
            'count'       => $folio['count'],
        ]);
    }

    /** POST /api/v1/guest/{code}/checkout — خروج سریع از روی تلویزیون */
    public function guestCheckout(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یا اتاق یافت نشد'); return; }

        $res = $this->folio->requestCheckout((int)$ctx['tenant_id'], (int)$ctx['room_id']);
        if (!$res['ok']) { Response::error($res['message'], 409); return; }

        Response::success(
            ['id' => $res['id'], 'total' => $res['total']],
            $res['message'],
            201
        );
    }

    /** POST /api/v1/guest/{code}/vod/{id}/purchase — خرید فیلم */
    public function guestPurchase(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یا اتاق یافت نشد'); return; }

        $res = $this->folio->purchaseVideo(
            (int)$ctx['tenant_id'],
            (int)$ctx['room_id'],
            (int)($params['id'] ?? 0)
        );
        if (!$res['ok']) { Response::error($res['message'], 409); return; }

        Response::success(['expires_at' => $res['expires_at']], $res['message']);
    }

    /** GET /api/v1/guest/{code}/vod/{id}/access — آیا اجازه پخش دارد؟ */
    public function guestAccess(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یا اتاق یافت نشد'); return; }

        $videoId = (int)($params['id'] ?? 0);
        $video   = $this->db->row(
            'SELECT price, currency, access_hours FROM vod_videos WHERE id = ? AND tenant_id = ?',
            [$videoId, (int)$ctx['tenant_id']]
        );
        if (!$video) { Response::notFound('ویدیو یافت نشد'); return; }

        Response::success([
            'allowed'      => $this->folio->hasVideoAccess((int)$ctx['tenant_id'], (int)$ctx['room_id'], $videoId),
            'price'        => (float)$video['price'],
            'currency'     => $video['currency'] ?? 'IRR',
            'access_hours' => (int)$video['access_hours'],
        ]);
    }

    /** GET /api/v1/guest/{code}/menus — منوهای تصویری بارگذاری‌شده */
    public function guestMenus(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یا اتاق یافت نشد'); return; }

        $tid = (int)$ctx['tenant_id'];

        $sql    = 'SELECT id, category, title, title_en, description
                     FROM menu_boards WHERE tenant_id = ? AND is_active = 1';
        $sqlPar = [$tid];

        if ($cat = $req->get('category')) { $sql .= ' AND category = ?'; $sqlPar[] = $cat; }
        $sql .= ' ORDER BY sort_order, id';

        $boards = $this->db->rows($sql, $sqlPar);
        foreach ($boards as &$b) {
            $b['pages'] = $this->db->rows(
                'SELECT image_url, caption FROM menu_board_pages WHERE board_id = ? ORDER BY sort_order, id',
                [(int)$b['id']]
            );
        }
        unset($b);

        Response::success($boards);
    }

    // ══════════════════════════════════════════════════════════════
    //  سمت کارکنان — صورتحساب
    // ══════════════════════════════════════════════════════════════

    /** GET /rooms/{id}/folio */
    public function roomFolio(Request $req, array $params): void
    {
        $tid    = Auth::tenantId();
        $roomId = (int)($params['id'] ?? 0);

        if (!$this->db->exists('iptv_rooms', ['id' => $roomId, 'tenant_id' => $tid])) {
            Response::notFound('اتاق یافت نشد'); return;
        }

        Response::success($this->folio->folio($tid, $roomId));
    }

    /** POST /rooms/{id}/charges */
    public function addCharge(Request $req, array $params): void
    {
        $tid    = Auth::tenantId();
        $roomId = (int)($params['id'] ?? 0);

        $res = $this->folio->post($tid, $roomId, $req->json() ?: [], Auth::user()['id'] ?? null);
        if (!$res['ok']) { Response::error($res['message'], 422); return; }

        $this->log('folio.charge', 'room', $roomId, [], ['charge_id' => $res['id']]);
        Response::success(['id' => $res['id']], $res['message'], 201);
    }

    /** POST /charges/{id}/void */
    public function voidCharge(Request $req, array $params): void
    {
        $tid  = Auth::tenantId();
        $data = $req->json() ?: [];

        $res = $this->folio->void(
            $tid, (int)($params['id'] ?? 0),
            Auth::user()['id'] ?? null,
            (string)($data['reason'] ?? '')
        );
        if (!$res['ok']) { Response::error($res['message'], 422); return; }

        $this->log('folio.void', 'charge', (int)$params['id']);
        Response::success(null, $res['message']);
    }

    // ══════════════════════════════════════════════════════════════
    //  مینی‌بار
    // ══════════════════════════════════════════════════════════════

    public function minibarItems(Request $req): void
    {
        $tid = Auth::tenantId();

        Response::success($this->db->rows(
            'SELECT * FROM minibar_items WHERE tenant_id = ? ORDER BY sort_order, id',
            [$tid]
        ));
    }

    public function storeMinibarItem(Request $req): void
    {
        $tid  = Auth::tenantId();
        $data = $req->json() ?: [];

        $name = trim((string)($data['name_fa'] ?? ''));
        if ($name === '') { Response::error('نام قلم الزامی است', 422); return; }

        $id = $this->db->insert('minibar_items', [
            'tenant_id'  => $tid,
            'name_fa'    => mb_substr($name, 0, 120),
            'name_en'    => trim((string)($data['name_en'] ?? '')) ?: null,
            'price'      => max(0, (float)($data['price'] ?? 0)),
            'currency'   => $data['currency'] ?? 'IRR',
            'par_level'  => max(0, min(255, (int)($data['par_level'] ?? 2))),
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'is_active'  => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
        ]);

        Response::success(['id' => (int)$id], 'قلم مینی‌بار اضافه شد', 201);
    }

    public function updateMinibarItem(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        if (!$this->db->exists('minibar_items', ['id' => $id, 'tenant_id' => $tid])) {
            Response::notFound('قلم یافت نشد'); return;
        }

        $data   = $req->json() ?: [];
        $fields = [];

        if (array_key_exists('name_fa', $data)) {
            $n = trim((string)$data['name_fa']);
            if ($n === '') { Response::error('نام نمی‌تواند خالی باشد', 422); return; }
            $fields['name_fa'] = mb_substr($n, 0, 120);
        }
        if (array_key_exists('name_en', $data))  $fields['name_en']   = trim((string)$data['name_en']) ?: null;
        if (isset($data['price']))               $fields['price']     = max(0, (float)$data['price']);
        if (isset($data['par_level']))           $fields['par_level'] = max(0, min(255, (int)$data['par_level']));
        if (isset($data['sort_order']))          $fields['sort_order']= (int)$data['sort_order'];
        if (isset($data['is_active']))           $fields['is_active'] = (int)(bool)$data['is_active'];

        if (!$fields) { Response::error('چیزی برای به‌روزرسانی ارسال نشده', 422); return; }

        $this->db->update('minibar_items', $fields, ['id' => $id, 'tenant_id' => $tid]);
        Response::success(null, 'به‌روزرسانی شد');
    }

    public function destroyMinibarItem(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        if (!$this->db->exists('minibar_items', ['id' => $id, 'tenant_id' => $tid])) {
            Response::notFound('قلم یافت نشد'); return;
        }

        // شمارش‌های قبلی باید بمانند — قلم فقط غیرفعال می‌شود
        $this->db->update('minibar_items', ['is_active' => 0], ['id' => $id, 'tenant_id' => $tid]);
        Response::success(null, 'قلم غیرفعال شد');
    }

    /**
     * POST /rooms/{id}/minibar — ثبت شمارش خانه‌داری
     * body: { items: { "<item_id>": found_qty } }
     */
    public function recordMinibar(Request $req, array $params): void
    {
        $tid    = Auth::tenantId();
        $roomId = (int)($params['id'] ?? 0);
        $data   = $req->json() ?: [];

        $items = $data['items'] ?? null;
        if (!is_array($items) || !$items) { Response::error('شمارش اقلام ارسال نشده', 422); return; }

        $res = $this->folio->recordMinibar($tid, $roomId, $items, Auth::user()['id'] ?? null);
        if (!$res['ok']) { Response::error($res['message'], 409); return; }

        $this->log('minibar.record', 'room', $roomId, [], ['charged' => $res['charged']]);
        Response::success(['charged' => $res['charged'], 'amount' => $res['amount']], $res['message']);
    }

    // ══════════════════════════════════════════════════════════════
    //  خروج سریع — سمت پذیرش
    // ══════════════════════════════════════════════════════════════

    public function checkoutRequests(Request $req): void
    {
        $tid = Auth::tenantId();

        $sql = "SELECT c.*, r.room_number, r.room_name, u.name AS handled_by_name
                  FROM checkout_requests c
                  JOIN iptv_rooms r ON r.id = c.room_id
                  LEFT JOIN users u ON u.id = c.handled_by
                 WHERE c.tenant_id = ?";
        $par = [$tid];

        $status = $req->get('status', 'requested');
        if ($status && $status !== 'all') { $sql .= ' AND c.status = ?'; $par[] = $status; }

        $sql .= ' ORDER BY c.created_at DESC LIMIT 200';
        Response::success($this->db->rows($sql, $par));
    }

    public function handleCheckout(Request $req, array $params): void
    {
        $tid  = Auth::tenantId();
        $data = $req->json() ?: [];

        $res = $this->folio->confirmCheckout(
            $tid,
            (int)($params['id'] ?? 0),
            Auth::user()['id'] ?? null,
            filter_var($data['approve'] ?? true, FILTER_VALIDATE_BOOLEAN),
            (string)($data['note'] ?? '')
        );
        if (!$res['ok']) { Response::error($res['message'], 422); return; }

        $this->log('checkout.handle', 'checkout_request', (int)$params['id']);
        Response::success(null, $res['message']);
    }

    // ══════════════════════════════════════════════════════════════
    //  ارسال به PMS
    // ══════════════════════════════════════════════════════════════

    /** POST /pms/push — ارسال دستی صف */
    public function pmsPush(Request $req): void
    {
        set_time_limit(180);
        $res = (new PmsChargeService($this->db))->pushPending(Auth::tenantId());
        Response::success($res, $res['message']);
    }

    /** POST /pms/test */
    public function pmsTest(Request $req): void
    {
        $res = (new PmsChargeService($this->db))->test(Auth::tenantId());
        if (!$res['ok']) { Response::error($res['message'], 502); return; }
        Response::success(null, $res['message']);
    }

    /** POST /charges/{id}/pms-retry */
    public function pmsRetry(Request $req, array $params): void
    {
        $res = (new PmsChargeService($this->db))->retry(Auth::tenantId(), (int)($params['id'] ?? 0));
        if (!$res['ok']) { Response::error($res['message'], 502); return; }
        Response::success(null, $res['message']);
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /** @return array<string,mixed>|null */
    private function resolveRoom(string $code): ?array
    {
        if ($code === '') return null;

        return $this->db->row(
            'SELECT s.tenant_id, r.id AS room_id, r.room_number, r.guest_name, r.status
               FROM screens s
               JOIN iptv_rooms r ON r.id = s.iptv_room_id
              WHERE s.code = ?',
            [$code]
        ) ?: null;
    }
}
