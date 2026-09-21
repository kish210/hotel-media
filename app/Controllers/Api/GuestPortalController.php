<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response};

/**
 * Guest Portal Controller
 * نقطه ورود تلویزیون اتاق (STB) — بدون JWT، هویت با کد صفحه‌نمایش
 * مهمان از اینجا سرویس‌ها را می‌بیند، سفارش ثبت می‌کند و وضعیتش را دنبال می‌کند.
 * فاز ۱ نقشه‌راه — docs/TODO.md
 */
class GuestPortalController extends Controller
{
    /** حداکثر تعداد درخواست باز همزمان برای هر اتاق — جلوی spam از روی ریموت */
    private const MAX_OPEN_PER_ROOM = 10;

    /** حداکثر تعداد درخواست در ۱۰ دقیقه گذشته برای هر اتاق */
    private const MAX_PER_10MIN = 5;

    /** GET /api/v1/guest/{code}/services — کاتالوگ قابل سفارش برای این اتاق */
    public function services(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یا اتاق یافت نشد'); return; }

        $rows = $this->db->rows(
            'SELECT id, category, name_fa, name_en, description, icon, image,
                    price, currency, unit, is_orderable, available_from, available_to
             FROM guest_services
             WHERE tenant_id = ? AND is_active = 1
             ORDER BY category ASC, sort_order ASC, id ASC',
            [$ctx['tenant_id']]
        );

        $now      = date('H:i:s');
        $grouped  = [];
        foreach ($rows as $row) {
            $row['available_now'] = $this->isAvailableNow($row['available_from'], $row['available_to'], $now);
            $grouped[$row['category']][] = $row;
        }

        Response::success([
            'room_number' => $ctx['room_number'],
            'guest_name'  => $ctx['guest_name'],
            'guest_lang'  => $ctx['guest_lang'],
            'categories'  => $grouped,
        ]);
    }

    /**
     * POST /api/v1/guest/{code}/requests — ثبت درخواست/سفارش توسط مهمان
     * body: { category, items:[{service_id, qty}], note, scheduled_at, rating }
     */
    public function store(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یا اتاق یافت نشد'); return; }

        if ($ctx['status'] !== 'occupied') {
            Response::error('این اتاق در حال حاضر مهمان ندارد', 409); return;
        }

        $tid    = (int)$ctx['tenant_id'];
        $roomId = (int)$ctx['room_id'];
        $data   = $req->json() ?: [];

        $category = $data['category'] ?? 'other';
        if (!in_array($category, GuestServiceController::CATEGORIES, true)) {
            Response::error('دسته درخواست نامعتبر است', 422); return;
        }

        if ($this->tooManyRequests($tid, $roomId)) {
            Response::error('تعداد درخواست‌های باز شما زیاد است — لطفاً منتظر رسیدگی بمانید', 429); return;
        }

        $note        = mb_substr(trim((string)($data['note'] ?? '')), 0, 500) ?: null;
        $scheduledAt = $this->parseDateTime($data['scheduled_at'] ?? null);
        $rating      = null;

        // ── اعتبارسنجی مخصوص هر دسته ──────────────────────────────
        if ($category === 'wakeup') {
            if (!$scheduledAt)              { Response::error('زمان بیدارباش الزامی است', 422); return; }
            if (strtotime($scheduledAt) <= time()) { Response::error('زمان بیدارباش باید در آینده باشد', 422); return; }
        }
        if ($category === 'feedback') {
            $rating = (int)($data['rating'] ?? 0);
            if ($rating < 1 || $rating > 5) { Response::error('امتیاز باید بین ۱ تا ۵ باشد', 422); return; }
        }

        // ── اقلام سفارش ───────────────────────────────────────────
        $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];
        $lines    = [];
        $total    = 0.0;
        $currency = 'IRR';
        $nowTime  = date('H:i:s');

        foreach (array_slice($rawItems, 0, 40) as $item) {
            $sid = (int)($item['service_id'] ?? 0);
            $qty = max(1, min(99, (int)($item['qty'] ?? 1)));
            if (!$sid) continue;

            $svc = $this->db->row(
                'SELECT * FROM guest_services WHERE id=? AND tenant_id=? AND is_active=1',
                [$sid, $tid]
            );
            if (!$svc) { Response::error('یکی از اقلام انتخابی در دسترس نیست', 422); return; }
            if (!$this->isAvailableNow($svc['available_from'], $svc['available_to'], $nowTime)) {
                Response::error("سرویس «{$svc['name_fa']}» در این ساعت ارائه نمی‌شود", 422); return;
            }
            if (!(int)$svc['is_orderable']) $qty = 1;

            $unitPrice = (float)$svc['price'];
            $lineTotal = $unitPrice * $qty;
            $total    += $lineTotal;
            $currency  = $svc['currency'] ?: $currency;

            $lines[] = [
                'service_id'    => $sid,
                'name_snapshot' => $svc['name_fa'],
                'qty'           => $qty,
                'unit_price'    => $unitPrice,
                'line_total'    => $lineTotal,
            ];
        }

        // سفارش غذا/خشک‌شویی بدون قلم بی‌معناست
        if (!$lines && in_array($category, ['room_service', 'laundry', 'breakfast'], true)) {
            Response::error('حداقل یک مورد را انتخاب کنید', 422); return;
        }
        // درخواست آزاد هم باید یا قلم داشته باشد یا توضیح
        if (!$lines && !$note && !in_array($category, ['wakeup', 'feedback', 'taxi'], true)) {
            Response::error('توضیح درخواست را وارد کنید', 422); return;
        }

        // ── ثبت ───────────────────────────────────────────────────
        $this->db->beginTransaction();
        try {
            $reqId = (int)$this->db->insert('guest_requests', [
                'tenant_id'    => $tid,
                'room_id'      => $roomId,
                'category'     => $category,
                'status'       => 'pending',
                'guest_name'   => $ctx['guest_name'],
                'guest_lang'   => $ctx['guest_lang'] ?: 'fa',
                'note'         => $note,
                'scheduled_at' => $scheduledAt,
                'total_price'  => $total,
                'currency'     => $currency,
                'rating'       => $rating,
                'source'       => 'tv',
            ]);

            foreach ($lines as $line) {
                $this->db->insert('guest_request_items', array_merge($line, ['request_id' => $reqId]));
            }

            $this->db->insert('guest_request_log', [
                'request_id' => $reqId,
                'to_status'  => 'pending',
                'note'       => 'ثبت توسط مهمان از تلویزیون اتاق',
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            error_log('[GUEST REQUEST] ' . $e->getMessage());
            Response::error('ثبت درخواست ناموفق بود', 500);
            return;
        }

        Response::success([
            'id'          => $reqId,
            'status'      => 'pending',
            'total_price' => $total,
            'currency'    => $currency,
        ], 'درخواست شما ثبت شد', 201);
    }

    /** GET /api/v1/guest/{code}/requests — درخواست‌های همین اقامت */
    public function myRequests(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یا اتاق یافت نشد'); return; }

        $sql = 'SELECT id, category, status, note, scheduled_at, total_price, currency,
                       rating, created_at, accepted_at, done_at
                FROM guest_requests
                WHERE tenant_id = ? AND room_id = ?';
        $sqlParams = [$ctx['tenant_id'], $ctx['room_id']];

        // فقط درخواست‌های اقامت جاری — مهمان قبلی نباید دیده شود
        if (!empty($ctx['check_in_at'])) {
            $sql .= ' AND created_at >= ?';
            $sqlParams[] = $ctx['check_in_at'];
        }

        $sql .= ' ORDER BY created_at DESC LIMIT 50';
        $rows = $this->db->rows($sql, $sqlParams);

        foreach ($rows as &$row) {
            $row['items'] = $this->db->rows(
                'SELECT name_snapshot, qty, unit_price, line_total FROM guest_request_items WHERE request_id=? ORDER BY id',
                [(int)$row['id']]
            );
        }
        unset($row);

        Response::success($rows);
    }

    /** POST /api/v1/guest/{code}/requests/{id}/cancel — لغو توسط مهمان */
    public function cancel(Request $req, array $params): void
    {
        $ctx = $this->resolveRoom((string)($params['code'] ?? ''));
        if (!$ctx) { Response::notFound('صفحه‌نمایش یا اتاق یافت نشد'); return; }

        $id = (int)($params['id'] ?? 0);
        $r  = $this->db->row(
            'SELECT * FROM guest_requests WHERE id=? AND tenant_id=? AND room_id=?',
            [$id, $ctx['tenant_id'], $ctx['room_id']]
        );
        if (!$r) { Response::notFound('درخواست یافت نشد'); return; }

        // بعد از شروع کار، لغو فقط از پنل کارکنان ممکن است
        if ($r['status'] !== 'pending') {
            Response::error('این درخواست در حال رسیدگی است — لطفاً با پذیرش تماس بگیرید', 409); return;
        }

        $this->db->update('guest_requests', [
            'status'  => 'cancelled',
            'done_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id, 'tenant_id' => $ctx['tenant_id']]);

        $this->db->insert('guest_request_log', [
            'request_id'  => $id,
            'from_status' => 'pending',
            'to_status'   => 'cancelled',
            'note'        => 'لغو توسط مهمان',
        ]);

        Response::success(null, 'درخواست لغو شد');
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /** کد صفحه‌نمایش → اتاق متصل به آن */
    private function resolveRoom(string $code): ?array
    {
        if ($code === '') return null;

        $row = $this->db->row(
            'SELECT s.tenant_id, r.id AS room_id, r.room_number, r.status,
                    r.guest_name, r.guest_lang, r.check_in_at
             FROM screens s
             JOIN iptv_rooms r ON r.id = s.iptv_room_id
             WHERE s.code = ?',
            [$code]
        );

        return $row ?: null;
    }

    private function isAvailableNow(?string $from, ?string $to, string $now): bool
    {
        if (!$from || !$to) return true;
        // بازه‌ای که از نیمه‌شب رد می‌شود، مثلا ۲۲:۰۰ تا ۰۲:۰۰
        if ($from > $to) return $now >= $from || $now <= $to;
        return $now >= $from && $now <= $to;
    }

    private function parseDateTime(mixed $v): ?string
    {
        $v = trim((string)$v);
        if ($v === '') return null;
        $ts = strtotime($v);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function tooManyRequests(int $tid, int $roomId): bool
    {
        $open = (int)$this->db->value(
            "SELECT COUNT(*) FROM guest_requests
             WHERE tenant_id=? AND room_id=? AND status IN ('pending','accepted','in_progress')",
            [$tid, $roomId]
        );
        if ($open >= self::MAX_OPEN_PER_ROOM) return true;

        $recent = (int)$this->db->value(
            'SELECT COUNT(*) FROM guest_requests
             WHERE tenant_id=? AND room_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)',
            [$tid, $roomId]
        );

        return $recent >= self::MAX_PER_10MIN;
    }
}
