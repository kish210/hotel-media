<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};

/**
 * Guest Service Controller
 * کاتالوگ خدمات مهمان + صف درخواست‌ها برای کارکنان
 * فاز ۱ نقشه‌راه — docs/TODO.md
 */
class GuestServiceController extends Controller
{
    public const CATEGORIES = [
        'room_service', 'housekeeping', 'laundry', 'taxi',
        'breakfast', 'maintenance', 'wakeup', 'feedback', 'other',
    ];

    public const SERVICE_CATEGORIES = [
        'room_service', 'housekeeping', 'laundry', 'taxi',
        'breakfast', 'maintenance', 'other',
    ];

    public const STATUSES = ['pending', 'accepted', 'in_progress', 'done', 'cancelled'];

    /** وضعیت‌های مجاز بعدی برای هر وضعیت */
    public const TRANSITIONS = [
        'pending'     => ['accepted', 'cancelled'],
        'accepted'    => ['in_progress', 'done', 'cancelled'],
        'in_progress' => ['done', 'cancelled'],
        'done'        => [],
        'cancelled'   => [],
    ];

    // ══════════════════════════════════════════════════════════════
    //  کاتالوگ خدمات
    // ══════════════════════════════════════════════════════════════

    public function services(Request $req): void
    {
        $tid    = Auth::tenantId();
        $sql    = 'SELECT * FROM guest_services WHERE tenant_id = ?';
        $params = [$tid];

        if ($cat = $req->get('category')) {
            if (!in_array($cat, self::SERVICE_CATEGORIES, true)) { Response::error('دسته نامعتبر است', 422); return; }
            $sql .= ' AND category = ?'; $params[] = $cat;
        }
        $active = $req->get('active');
        if ($active !== null && $active !== '') {
            $sql .= ' AND is_active = ?'; $params[] = (int)(bool)$active;
        }

        $sql .= ' ORDER BY category ASC, sort_order ASC, id ASC';
        Response::success($this->db->rows($sql, $params));
    }

    public function storeService(Request $req): void
    {
        $tid  = Auth::tenantId();
        $data = $req->json() ?: [];

        $name = trim((string)($data['name_fa'] ?? ''));
        if ($name === '') { Response::error('نام سرویس الزامی است', 422); return; }

        $cat = $data['category'] ?? 'other';
        if (!in_array($cat, self::SERVICE_CATEGORIES, true)) { Response::error('دسته نامعتبر است', 422); return; }

        $id = $this->db->insert('guest_services', [
            'tenant_id'      => $tid,
            'category'       => $cat,
            'name_fa'        => $name,
            'name_en'        => trim((string)($data['name_en'] ?? '')) ?: null,
            'description'    => trim((string)($data['description'] ?? '')) ?: null,
            'icon'           => trim((string)($data['icon'] ?? '')) ?: null,
            'price'          => max(0, (float)($data['price'] ?? 0)),
            'currency'       => $data['currency'] ?? 'IRR',
            'unit'           => trim((string)($data['unit'] ?? '')) ?: null,
            'is_orderable'   => isset($data['is_orderable']) ? (int)(bool)$data['is_orderable'] : 1,
            'available_from' => $this->timeOrNull($data['available_from'] ?? null),
            'available_to'   => $this->timeOrNull($data['available_to'] ?? null),
            'sort_order'     => (int)($data['sort_order'] ?? 0),
            'is_active'      => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
        ]);

        $this->log('guest_service.create', 'guest_service', (int)$id, [], ['name' => $name]);
        Response::success(['id' => (int)$id], 'سرویس ثبت شد', 201);
    }

    public function updateService(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        $svc = $this->db->row('SELECT * FROM guest_services WHERE id=? AND tenant_id=?', [$id, $tid]);
        if (!$svc) { Response::notFound('سرویس یافت نشد'); return; }

        $data   = $req->json() ?: [];
        $fields = [];

        foreach (['name_en', 'description', 'icon', 'unit'] as $f) {
            if (array_key_exists($f, $data)) $fields[$f] = trim((string)$data[$f]) ?: null;
        }
        if (array_key_exists('name_fa', $data)) {
            $n = trim((string)$data['name_fa']);
            if ($n === '') { Response::error('نام سرویس نمی‌تواند خالی باشد', 422); return; }
            $fields['name_fa'] = $n;
        }
        if (array_key_exists('currency', $data)) {
            $fields['currency'] = trim((string)$data['currency']) ?: 'IRR';
        }
        if (isset($data['category'])) {
            if (!in_array($data['category'], self::SERVICE_CATEGORIES, true)) { Response::error('دسته نامعتبر است', 422); return; }
            $fields['category'] = $data['category'];
        }
        if (isset($data['price']))        $fields['price']        = max(0, (float)$data['price']);
        if (isset($data['sort_order']))   $fields['sort_order']   = (int)$data['sort_order'];
        if (isset($data['is_active']))    $fields['is_active']    = (int)(bool)$data['is_active'];
        if (isset($data['is_orderable'])) $fields['is_orderable'] = (int)(bool)$data['is_orderable'];
        if (array_key_exists('available_from', $data)) $fields['available_from'] = $this->timeOrNull($data['available_from']);
        if (array_key_exists('available_to', $data))   $fields['available_to']   = $this->timeOrNull($data['available_to']);

        if (!$fields) { Response::error('چیزی برای به‌روزرسانی ارسال نشده', 422); return; }

        $this->db->update('guest_services', $fields, ['id' => $id, 'tenant_id' => $tid]);
        $this->log('guest_service.update', 'guest_service', $id, $svc, $fields);
        Response::success(null, 'به‌روزرسانی شد');
    }

    public function destroyService(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        if (!$this->db->exists('guest_services', ['id' => $id, 'tenant_id' => $tid])) {
            Response::notFound('سرویس یافت نشد'); return;
        }

        // سفارش‌های قبلی باید سالم بمانند — فقط ارجاع خالی می‌شود
        $this->db->update('guest_request_items', ['service_id' => null], ['service_id' => $id]);
        $this->db->delete('guest_services', ['id' => $id, 'tenant_id' => $tid]);

        $this->log('guest_service.delete', 'guest_service', $id);
        Response::success(null, 'سرویس حذف شد');
    }

    // ══════════════════════════════════════════════════════════════
    //  صف درخواست‌ها (کارکنان)
    // ══════════════════════════════════════════════════════════════

    public function requests(Request $req): void
    {
        $tid = Auth::tenantId();

        $sql = "SELECT r.*, rm.room_number, rm.room_name, rm.floor,
                       u.name AS assigned_name,
                       TIMESTAMPDIFF(MINUTE, r.created_at, NOW()) AS age_minutes
                FROM guest_requests r
                JOIN iptv_rooms rm ON rm.id = r.room_id
                LEFT JOIN users  u  ON u.id = r.assigned_to
                WHERE r.tenant_id = ?";
        $params = [$tid];

        $status = $req->get('status');
        if ($status === 'open') {
            $sql .= " AND r.status IN ('pending','accepted','in_progress')";
        } elseif ($status) {
            if (!in_array($status, self::STATUSES, true)) { Response::error('وضعیت نامعتبر است', 422); return; }
            $sql .= ' AND r.status = ?'; $params[] = $status;
        }
        if ($cat = $req->get('category')) {
            if (!in_array($cat, self::CATEGORIES, true)) { Response::error('دسته نامعتبر است', 422); return; }
            $sql .= ' AND r.category = ?'; $params[] = $cat;
        }
        if ($room = $req->get('room')) { $sql .= ' AND rm.room_number = ?';  $params[] = $room; }
        if ($from = $req->get('from')) { $sql .= ' AND r.created_at >= ?';   $params[] = $from . ' 00:00:00'; }
        if ($to   = $req->get('to'))   { $sql .= ' AND r.created_at <= ?';   $params[] = $to . ' 23:59:59'; }

        $sql .= " ORDER BY FIELD(r.status,'pending','accepted','in_progress','done','cancelled'), r.created_at ASC";

        $page    = max(1, (int)$req->get('page', 1));
        $perPage = min(100, max(1, (int)$req->get('per_page', 30)));
        $result  = $this->db->paginate($sql, $params, $page, $perPage);

        foreach ($result['data'] as &$row) {
            $row['items'] = $this->db->rows(
                'SELECT * FROM guest_request_items WHERE request_id = ? ORDER BY id',
                [(int)$row['id']]
            );
        }
        unset($row);

        Response::paginated($result);
    }

    public function showRequest(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        $r = $this->db->row(
            'SELECT r.*, rm.room_number, rm.room_name, u.name AS assigned_name
             FROM guest_requests r
             JOIN iptv_rooms rm ON rm.id = r.room_id
             LEFT JOIN users u  ON u.id = r.assigned_to
             WHERE r.id = ? AND r.tenant_id = ?',
            [$id, $tid]
        );
        if (!$r) { Response::notFound('درخواست یافت نشد'); return; }

        $r['items'] = $this->db->rows('SELECT * FROM guest_request_items WHERE request_id=? ORDER BY id', [$id]);
        $r['log']   = $this->db->rows(
            'SELECT l.*, u.name AS user_name FROM guest_request_log l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.request_id = ? ORDER BY l.id',
            [$id]
        );

        Response::success($r);
    }

    /** PUT /guest/requests/{id}/status */
    public function updateStatus(Request $req, array $params): void
    {
        $tid  = Auth::tenantId();
        $id   = (int)($params['id'] ?? 0);
        $data = $req->json() ?: [];

        $r = $this->db->row('SELECT * FROM guest_requests WHERE id=? AND tenant_id=?', [$id, $tid]);
        if (!$r) { Response::notFound('درخواست یافت نشد'); return; }

        $to   = $data['status'] ?? '';
        $from = $r['status'];
        if (!in_array($to, self::STATUSES, true)) { Response::error('وضعیت نامعتبر است', 422); return; }
        if ($to !== $from && !in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            Response::error("تغییر وضعیت از «{$from}» به «{$to}» مجاز نیست", 422); return;
        }

        $fields = ['status' => $to];
        if ($to === 'accepted' && !$r['accepted_at']) {
            $fields['accepted_at'] = date('Y-m-d H:i:s');
        }
        if (($to === 'done' || $to === 'cancelled') && !$r['done_at']) {
            $fields['done_at'] = date('Y-m-d H:i:s');
        }

        if (array_key_exists('assigned_to', $data)) {
            $assignee = $data['assigned_to'] ? (int)$data['assigned_to'] : null;
            if ($assignee && !$this->db->exists('users', ['id' => $assignee, 'tenant_id' => $tid])) {
                Response::error('کارمند انتخابی معتبر نیست', 422); return;
            }
            $fields['assigned_to'] = $assignee;
        }
        if (array_key_exists('staff_note', $data)) {
            $fields['staff_note'] = trim((string)$data['staff_note']) ?: null;
        }

        $this->db->update('guest_requests', $fields, ['id' => $id, 'tenant_id' => $tid]);

        $this->db->insert('guest_request_log', [
            'request_id'  => $id,
            'from_status' => $from,
            'to_status'   => $to,
            'user_id'     => Auth::user()['id'] ?? null,
            'note'        => $fields['staff_note'] ?? null,
        ]);

        // سفارش انجام‌شده باید روی صورتحساب اتاق بنشیند، وگرنه مهمان
        // روم‌سرویس می‌گیرد و هتل هیچ‌وقت پولش را نمی‌بیند.
        $billed = 0;
        if ($to === 'done' && (float)$r['total_price'] > 0) {
            $billed = $this->billRequest($tid, $r);
        }

        $this->log('guest_request.status', 'guest_request', $id, ['status' => $from], ['status' => $to]);
        Response::success(
            $billed ? ['billed' => true] : null,
            $billed ? 'انجام شد و به صورتحساب اتاق اضافه شد' : 'وضعیت به‌روزرسانی شد'
        );
    }

    /**
     * اقلام سفارش را روی صورتحساب اتاق می‌نشاند.
     * @param array<string,mixed> $request ردیف guest_requests
     * @return int تعداد اقلام ثبت‌شده
     */
    private function billRequest(int $tenantId, array $request): int
    {
        $requestId = (int)$request['id'];

        // ثبت دوباره‌ی همان سفارش نباید مهمان را دوبار بدهکار کند
        $already = (int)$this->db->value(
            "SELECT COUNT(*) FROM room_charges
              WHERE tenant_id = ? AND source IN ('room_service','laundry','service')
                AND reference_id = ? AND status <> 'void'",
            [$tenantId, $requestId]
        );
        if ($already > 0) return 0;

        $source = match ($request['category']) {
            'room_service', 'breakfast' => 'room_service',
            'laundry'                   => 'laundry',
            default                     => 'service',
        };

        $items = $this->db->rows(
            'SELECT name_snapshot, qty, unit_price FROM guest_request_items WHERE request_id = ?',
            [$requestId]
        );

        $folio  = new \App\Services\FolioService($this->db);
        $userId = Auth::user()['id'] ?? null;
        $n      = 0;

        foreach ($items as $item) {
            if ((float)$item['unit_price'] <= 0) continue;

            $res = $folio->post($tenantId, (int)$request['room_id'], [
                'source'       => $source,
                'reference_id' => $requestId,
                'title'        => (string)$item['name_snapshot'],
                'qty'          => (int)$item['qty'],
                'unit_price'   => (float)$item['unit_price'],
            ], $userId);

            if ($res['ok']) $n++;
        }

        return $n;
    }

    /** GET /guest/requests/stats */
    public function stats(Request $req): void
    {
        $tid = Auth::tenantId();

        Response::success([
            'pending'       => (int)$this->db->value("SELECT COUNT(*) FROM guest_requests WHERE tenant_id=? AND status='pending'", [$tid]),
            'in_progress'   => (int)$this->db->value("SELECT COUNT(*) FROM guest_requests WHERE tenant_id=? AND status IN ('accepted','in_progress')", [$tid]),
            'done_today'    => (int)$this->db->value("SELECT COUNT(*) FROM guest_requests WHERE tenant_id=? AND status='done' AND DATE(done_at)=CURDATE()", [$tid]),
            'revenue_today' => (float)$this->db->value("SELECT COALESCE(SUM(total_price),0) FROM guest_requests WHERE tenant_id=? AND status='done' AND DATE(done_at)=CURDATE()", [$tid]),
            'avg_minutes'   => round((float)$this->db->value(
                "SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, created_at, done_at)),0)
                 FROM guest_requests WHERE tenant_id=? AND status='done' AND done_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$tid]
            ), 1),
            'avg_rating'    => round((float)$this->db->value(
                "SELECT COALESCE(AVG(rating),0) FROM guest_requests WHERE tenant_id=? AND rating IS NOT NULL",
                [$tid]
            ), 2),
            'by_category'   => $this->db->rows(
                "SELECT category, COUNT(*) AS total FROM guest_requests
                 WHERE tenant_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY category ORDER BY total DESC",
                [$tid]
            ),
            'wakeups_next'  => $this->db->rows(
                "SELECT r.id, r.scheduled_at, rm.room_number
                 FROM guest_requests r JOIN iptv_rooms rm ON rm.id = r.room_id
                 WHERE r.tenant_id=? AND r.category='wakeup' AND r.status IN ('pending','accepted')
                   AND r.scheduled_at >= NOW()
                 ORDER BY r.scheduled_at ASC LIMIT 10",
                [$tid]
            ),
        ]);
    }

    private function timeOrNull(mixed $v): ?string
    {
        $v = trim((string)$v);
        return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $v) ? $v : null;
    }
}
