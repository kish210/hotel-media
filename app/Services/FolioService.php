<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Folio Service — صورتحساب اتاق
 *
 * مینی‌بار، سفارش روم‌سرویس، خشک‌شویی و خرید فیلم همه به یک دفتر بدهکاری
 * مشترک می‌نشینند تا مهمان یک صورتحساب ببیند و خروج سریع معنا پیدا کند.
 *
 * قاعده‌ی محوری: هر قلم به **اقامت** گره می‌خورد، نه فقط به اتاق. بدون این،
 * مهمان تازه صورتحساب مهمان قبلی را روی تلویزیون می‌دید.
 *
 * فاز ۴ و ۵ نقشه‌راه — docs/TODO.md
 */
class FolioService
{
    public const SOURCES = ['minibar', 'room_service', 'laundry', 'ppv', 'service', 'manual', 'pms'];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // ══════════════════════════════════════════════════════════════
    //  ثبت قلم
    // ══════════════════════════════════════════════════════════════

    /**
     * یک قلم روی صورتحساب اقامت جاری می‌نشاند.
     *
     * @param array<string,mixed> $data title, qty, unit_price, source, reference_id, note
     * @return array{ok:bool,id:?int,message:string}
     */
    public function post(int $tenantId, int $roomId, array $data, ?int $userId = null): array
    {
        $room = $this->db->row(
            'SELECT id, status, check_in_at, guest_name FROM iptv_rooms WHERE id = ? AND tenant_id = ?',
            [$roomId, $tenantId]
        );
        if (!$room) return ['ok' => false, 'id' => null, 'message' => 'اتاق یافت نشد'];

        $stayStart = $this->stayStart($room);
        if ($stayStart === null) {
            return ['ok' => false, 'id' => null, 'message' => 'این اتاق مهمان ندارد — قلمی ثبت نمی‌شود'];
        }

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') return ['ok' => false, 'id' => null, 'message' => 'شرح قلم الزامی است'];

        $source = $data['source'] ?? 'manual';
        if (!in_array($source, self::SOURCES, true)) {
            return ['ok' => false, 'id' => null, 'message' => 'منبع قلم نامعتبر است'];
        }

        $qty   = max(1, min(999, (int)($data['qty'] ?? 1)));
        $unit  = round(max(0, (float)($data['unit_price'] ?? 0)), 2);
        $total = round($unit * $qty, 2);

        $id = (int)$this->db->insert('room_charges', [
            'tenant_id'       => $tenantId,
            'room_id'         => $roomId,
            'stay_started_at' => $stayStart,
            'source'          => $source,
            'reference_id'    => isset($data['reference_id']) ? (int)$data['reference_id'] ?: null : null,
            'title'           => mb_substr($title, 0, 200),
            'qty'             => $qty,
            'unit_price'      => $unit,
            'amount'          => $total,
            'currency'        => $data['currency'] ?? 'IRR',
            'note'            => isset($data['note']) ? mb_substr((string)$data['note'], 0, 300) ?: null : null,
            'created_by'      => $userId,
            // قلم رایگان لازم نیست به PMS برود
            'pms_status'      => $total > 0 ? 'pending' : 'skipped',
        ]);

        return ['ok' => true, 'id' => $id, 'message' => 'قلم ثبت شد'];
    }

    /**
     * ابطال قلم. حذف نمی‌کنیم چون صورتحساب باید قابل حسابرسی بماند.
     */
    public function void(int $tenantId, int $chargeId, ?int $userId, string $reason = ''): array
    {
        $row = $this->db->row(
            'SELECT id, status FROM room_charges WHERE id = ? AND tenant_id = ?',
            [$chargeId, $tenantId]
        );
        if (!$row)                      return ['ok' => false, 'message' => 'قلم یافت نشد'];
        if ($row['status'] === 'void')  return ['ok' => false, 'message' => 'این قلم قبلا باطل شده'];
        if ($row['status'] === 'settled') return ['ok' => false, 'message' => 'قلم تسویه‌شده قابل ابطال نیست'];

        $this->db->update('room_charges', [
            'status'    => 'void',
            'voided_by' => $userId,
            'voided_at' => date('Y-m-d H:i:s'),
            'note'      => mb_substr(trim($reason), 0, 300) ?: null,
        ], ['id' => $chargeId, 'tenant_id' => $tenantId]);

        return ['ok' => true, 'message' => 'قلم باطل شد'];
    }

    // ══════════════════════════════════════════════════════════════
    //  صورتحساب
    // ══════════════════════════════════════════════════════════════

    /**
     * صورتحساب اقامت جاری.
     * @return array{items:list<array<string,mixed>>,total:float,currency:string,count:int,stay_started_at:?string}
     */
    public function folio(int $tenantId, int $roomId): array
    {
        $room = $this->db->row(
            'SELECT id, status, check_in_at FROM iptv_rooms WHERE id = ? AND tenant_id = ?',
            [$roomId, $tenantId]
        );

        $empty = ['items' => [], 'total' => 0.0, 'currency' => 'IRR', 'count' => 0, 'stay_started_at' => null];
        if (!$room) return $empty;

        $stayStart = $this->stayStart($room);
        if ($stayStart === null) return $empty;

        $items = $this->db->rows(
            "SELECT id, source, title, qty, unit_price, amount, currency, created_at
               FROM room_charges
              WHERE tenant_id = ? AND room_id = ?
                AND stay_started_at = ? AND status <> 'void'
              ORDER BY created_at, id",
            [$tenantId, $roomId, $stayStart]
        );

        $total    = 0.0;
        $currency = 'IRR';
        foreach ($items as $i) {
            $total   += (float)$i['amount'];
            $currency = $i['currency'] ?: $currency;
        }

        return [
            'items'           => $items,
            'total'           => round($total, 2),
            'currency'        => $currency,
            'count'           => count($items),
            'stay_started_at' => $stayStart,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    //  مینی‌بار
    // ══════════════════════════════════════════════════════════════

    /**
     * خانه‌دار موجودی فعلی یخچال را ثبت می‌کند؛ تفاضل با par_level روی
     * صورتحساب می‌نشیند.
     *
     * @param array<int,int> $found  [item_id => تعداد یافت‌شده]
     * @return array{ok:bool,charged:int,amount:float,message:string}
     */
    public function recordMinibar(int $tenantId, int $roomId, array $found, ?int $userId = null): array
    {
        $room = $this->db->row(
            'SELECT id, status, check_in_at FROM iptv_rooms WHERE id = ? AND tenant_id = ?',
            [$roomId, $tenantId]
        );
        if (!$room) return ['ok' => false, 'charged' => 0, 'amount' => 0.0, 'message' => 'اتاق یافت نشد'];

        $stayStart = $this->stayStart($room);
        if ($stayStart === null) {
            // اتاق خالی: شمارش ثبت می‌شود ولی به کسی بدهکار نمی‌شود
            return ['ok' => false, 'charged' => 0, 'amount' => 0.0,
                    'message' => 'اتاق مهمان ندارد — مصرفی ثبت نشد'];
        }

        $charged = 0;
        $amount  = 0.0;

        $this->db->beginTransaction();
        try {
            foreach ($found as $itemId => $qty) {
                $itemId = (int)$itemId;
                $qty    = max(0, (int)$qty);

                $item = $this->db->row(
                    'SELECT * FROM minibar_items WHERE id = ? AND tenant_id = ? AND is_active = 1',
                    [$itemId, $tenantId]
                );
                if (!$item) continue;

                $par      = (int)$item['par_level'];
                $consumed = max(0, $par - $qty);

                $chargeId = null;
                if ($consumed > 0) {
                    $unit = (float)$item['price'];
                    $chargeId = (int)$this->db->insert('room_charges', [
                        'tenant_id'       => $tenantId,
                        'room_id'         => $roomId,
                        'stay_started_at' => $stayStart,
                        'source'          => 'minibar',
                        'reference_id'    => $itemId,
                        'title'           => mb_substr((string)$item['name_fa'], 0, 200),
                        'qty'             => $consumed,
                        'unit_price'      => $unit,
                        'amount'          => round($unit * $consumed, 2),
                        'currency'        => $item['currency'] ?: 'IRR',
                        'created_by'      => $userId,
                        'pms_status'      => $unit > 0 ? 'pending' : 'skipped',
                    ]);
                    $charged++;
                    $amount += $unit * $consumed;
                }

                $this->db->insert('minibar_checks', [
                    'tenant_id'    => $tenantId,
                    'room_id'      => $roomId,
                    'item_id'      => $itemId,
                    'found_qty'    => min(255, $qty),
                    'consumed_qty' => min(255, $consumed),
                    'charge_id'    => $chargeId,
                    'checked_by'   => $userId,
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            error_log('[MINIBAR] ' . $e->getMessage());
            return ['ok' => false, 'charged' => 0, 'amount' => 0.0, 'message' => 'ثبت مینی‌بار ناموفق بود'];
        }

        return [
            'ok'      => true,
            'charged' => $charged,
            'amount'  => round($amount, 2),
            'message' => $charged
                ? "$charged قلم مصرفی ثبت شد"
                : 'مصرفی ثبت نشد — مینی‌بار کامل بود',
        ];
    }

    // ══════════════════════════════════════════════════════════════
    //  محتوای پولی
    // ══════════════════════════════════════════════════════════════

    /**
     * آیا این اتاق الان به این ویدیو دسترسی دارد؟
     * ویدیوی رایگان همیشه، ویدیوی پولی فقط با خرید معتبر.
     */
    public function hasVideoAccess(int $tenantId, int $roomId, int $videoId): bool
    {
        $price = $this->db->value(
            'SELECT price FROM vod_videos WHERE id = ? AND tenant_id = ?',
            [$videoId, $tenantId]
        );
        if ($price === null)       return false;   // ویدیو وجود ندارد
        if ((float)$price <= 0)    return true;    // رایگان

        return (int)$this->db->value(
            'SELECT COUNT(*) FROM ppv_purchases
              WHERE tenant_id = ? AND room_id = ? AND video_id = ?
                AND cancelled_at IS NULL AND expires_at > NOW()',
            [$tenantId, $roomId, $videoId]
        ) > 0;
    }

    /**
     * خرید ویدیو و نشاندن مبلغ روی صورتحساب اتاق.
     * @return array{ok:bool,expires_at:?string,message:string}
     */
    public function purchaseVideo(int $tenantId, int $roomId, int $videoId): array
    {
        $video = $this->db->row(
            'SELECT id, title, price, currency, access_hours FROM vod_videos WHERE id = ? AND tenant_id = ?',
            [$videoId, $tenantId]
        );
        if (!$video) return ['ok' => false, 'expires_at' => null, 'message' => 'ویدیو یافت نشد'];

        $price = (float)$video['price'];
        if ($price <= 0) {
            return ['ok' => true, 'expires_at' => null, 'message' => 'این ویدیو رایگان است'];
        }

        $room = $this->db->row(
            'SELECT id, status, check_in_at FROM iptv_rooms WHERE id = ? AND tenant_id = ?',
            [$roomId, $tenantId]
        );
        if (!$room) return ['ok' => false, 'expires_at' => null, 'message' => 'اتاق یافت نشد'];

        $stayStart = $this->stayStart($room);
        if ($stayStart === null) {
            return ['ok' => false, 'expires_at' => null, 'message' => 'خرید فقط برای اتاق دارای مهمان ممکن است'];
        }

        // خرید فعال موجود دوباره هزینه نمی‌شود
        $active = $this->db->row(
            'SELECT expires_at FROM ppv_purchases
              WHERE tenant_id = ? AND room_id = ? AND video_id = ?
                AND cancelled_at IS NULL AND expires_at > NOW()
              ORDER BY expires_at DESC LIMIT 1',
            [$tenantId, $roomId, $videoId]
        );
        if ($active) {
            return ['ok' => true, 'expires_at' => $active['expires_at'],
                    'message' => 'این فیلم از قبل خریداری شده است'];
        }

        $hours   = max(1, min(720, (int)($video['access_hours'] ?: 24)));
        $expires = date('Y-m-d H:i:s', time() + $hours * 3600);

        $this->db->beginTransaction();
        try {
            $chargeId = (int)$this->db->insert('room_charges', [
                'tenant_id'       => $tenantId,
                'room_id'         => $roomId,
                'stay_started_at' => $stayStart,
                'source'          => 'ppv',
                'reference_id'    => $videoId,
                'title'           => mb_substr('فیلم: ' . (string)$video['title'], 0, 200),
                'qty'             => 1,
                'unit_price'      => $price,
                'amount'          => $price,
                'currency'        => $video['currency'] ?? 'IRR',
            ]);

            $this->db->insert('ppv_purchases', [
                'tenant_id'  => $tenantId,
                'room_id'    => $roomId,
                'video_id'   => $videoId,
                'charge_id'  => $chargeId,
                'price'      => $price,
                'currency'   => $video['currency'] ?? 'IRR',
                'expires_at' => $expires,
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            error_log('[PPV] ' . $e->getMessage());
            return ['ok' => false, 'expires_at' => null, 'message' => 'ثبت خرید ناموفق بود'];
        }

        return ['ok' => true, 'expires_at' => $expires, 'message' => 'خرید ثبت و به صورتحساب اتاق اضافه شد'];
    }

    // ══════════════════════════════════════════════════════════════
    //  خروج سریع
    // ══════════════════════════════════════════════════════════════

    /** @return array{ok:bool,id:?int,total:float,message:string} */
    public function requestCheckout(int $tenantId, int $roomId): array
    {
        $room = $this->db->row(
            'SELECT id, status, check_in_at, guest_name FROM iptv_rooms WHERE id = ? AND tenant_id = ?',
            [$roomId, $tenantId]
        );
        if (!$room) return ['ok' => false, 'id' => null, 'total' => 0.0, 'message' => 'اتاق یافت نشد'];

        if ($this->stayStart($room) === null) {
            return ['ok' => false, 'id' => null, 'total' => 0.0, 'message' => 'این اتاق مهمان ندارد'];
        }

        $open = $this->db->row(
            "SELECT id FROM checkout_requests
              WHERE tenant_id = ? AND room_id = ? AND status = 'requested'",
            [$tenantId, $roomId]
        );
        if ($open) {
            return ['ok' => true, 'id' => (int)$open['id'], 'total' => 0.0,
                    'message' => 'درخواست خروج شما قبلا ثبت شده است'];
        }

        $folio = $this->folio($tenantId, $roomId);

        $id = (int)$this->db->insert('checkout_requests', [
            'tenant_id'    => $tenantId,
            'room_id'      => $roomId,
            'guest_name'   => $room['guest_name'] ?: null,
            'total_amount' => $folio['total'],
            'currency'     => $folio['currency'],
        ]);

        return ['ok' => true, 'id' => $id, 'total' => $folio['total'],
                'message' => 'درخواست خروج ثبت شد — پذیرش با شما تماس می‌گیرد'];
    }

    /**
     * پذیرش، خروج را تایید می‌کند: اقلام تسویه و اتاق آزاد می‌شود.
     */
    public function confirmCheckout(int $tenantId, int $requestId, ?int $userId, bool $approve, string $note = ''): array
    {
        $req = $this->db->row(
            'SELECT * FROM checkout_requests WHERE id = ? AND tenant_id = ?',
            [$requestId, $tenantId]
        );
        if (!$req)                          return ['ok' => false, 'message' => 'درخواست یافت نشد'];
        if ($req['status'] !== 'requested') return ['ok' => false, 'message' => 'این درخواست قبلا رسیدگی شده'];

        $roomId = (int)$req['room_id'];

        $this->db->beginTransaction();
        try {
            $this->db->update('checkout_requests', [
                'status'     => $approve ? 'confirmed' : 'rejected',
                'handled_by' => $userId,
                'handled_at' => date('Y-m-d H:i:s'),
                'note'       => mb_substr(trim($note), 0, 300) ?: null,
            ], ['id' => $requestId, 'tenant_id' => $tenantId]);

            if ($approve) {
                $room = $this->db->row('SELECT check_in_at FROM iptv_rooms WHERE id = ?', [$roomId]);
                $stay = $room['check_in_at'] ?? null;

                if ($stay) {
                    $this->db->query(
                        "UPDATE room_charges SET status = 'settled'
                          WHERE tenant_id = ? AND room_id = ? AND stay_started_at = ? AND status = 'posted'",
                        [$tenantId, $roomId, $stay]
                    );
                }

                // اتاق آزاد می‌شود — همان کاری که checkout عادی می‌کند
                $this->db->update('iptv_rooms', [
                    'status'       => 'available',
                    'guest_name'   => null,
                    'check_in_at'  => null,
                    'check_out_at' => date('Y-m-d H:i:s'),
                ], ['id' => $roomId, 'tenant_id' => $tenantId]);

                // دسترسی فیلم‌های خریداری‌شده با خروج مهمان تمام می‌شود
                $this->db->query(
                    'UPDATE ppv_purchases SET cancelled_at = NOW()
                      WHERE tenant_id = ? AND room_id = ? AND cancelled_at IS NULL',
                    [$tenantId, $roomId]
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            error_log('[CHECKOUT] ' . $e->getMessage());
            return ['ok' => false, 'message' => 'ثبت خروج ناموفق بود'];
        }

        return ['ok' => true, 'message' => $approve ? 'خروج تایید و اتاق آزاد شد' : 'درخواست خروج رد شد'];
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /**
     * شروع اقامت جاری، یا null اگر اتاق مهمان ندارد.
     * همه‌ی اقلام به این زمان گره می‌خورند تا صورتحساب بین مهمان‌ها نشت نکند.
     */
    private function stayStart(array $room): ?string
    {
        if (($room['status'] ?? '') !== 'occupied') return null;

        $v = $room['check_in_at'] ?? null;
        return ($v === null || $v === '' || str_starts_with((string)$v, '0000')) ? null : (string)$v;
    }
}
