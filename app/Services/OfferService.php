<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * تخفیف کسب‌وکارهای محلی — TODO ۴.۳
 *
 * مهمان از تلویزیون اتاق یک کد شخصی می‌گیرد و در رستوران یا فروشگاه
 * طرف قرارداد نشان می‌دهد. کسب‌وکار کد را با لینک اختصاصی خودش (بدون
 * حساب پنل) یا پذیرش هتل از پنل «استفاده‌شده» می‌کند.
 *
 * کد شخصی است، نه یک کد عمومی روی پوستر: عمومی را هرکسی بیرون از هتل
 * هم استفاده می‌کرد و هتل نمی‌فهمید کدام معرفی به خرید رسید.
 */
final class OfferService
{
    public const CATEGORIES = ['food', 'shopping', 'tour', 'beauty', 'fun', 'other'];

    /* بدون 0/O و 1/I/L که روی تلویزیون و پشت تلفن با هم اشتباه می‌شوند */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /* لینک کسب‌وکار عمومی است؛ بدون سقف، حدس زدن کد ممکن می‌شد */
    public const PARTNER_MAX_FAILS   = 10;
    public const PARTNER_FAIL_WINDOW = 900;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // ══════════════════════════════════════════════════════════════
    //  مهمان
    // ══════════════════════════════════════════════════════════════

    /**
     * پیشنهادهای معتبر امروز، با کدهایی که همین اقامت گرفته.
     * @return list<array<string,mixed>>
     */
    public function forGuest(int $tenantId, ?int $roomId, ?string $checkInAt): array
    {
        $today = date('Y-m-d');
        $rows = $this->db->rows(
            "SELECT o.id, o.business_name, o.category, o.title, o.description, o.terms, o.address, o.phone,
                    o.distance, o.image, o.valid_to, o.per_stay_limit, o.total_limit,
                    (SELECT COUNT(*) FROM offer_claims c WHERE c.offer_id = o.id AND c.status <> 'void') AS issued
               FROM local_offers o
              WHERE o.tenant_id = ? AND o.is_active = 1
                AND (o.valid_from IS NULL OR o.valid_from <= ?)
                AND (o.valid_to   IS NULL OR o.valid_to   >= ?)
              ORDER BY o.sort_order, o.id",
            [$tenantId, $today, $today]
        );

        $mine = [];
        if ($roomId) {
            foreach ($this->stayClaims($tenantId, $roomId, $checkInAt) as $c) {
                $mine[(int)$c['offer_id']][] = ['code' => $c['code'], 'status' => $c['status'], 'created_at' => $c['created_at']];
            }
        }

        $out = [];
        foreach ($rows as $o) {
            $id    = (int)$o['id'];
            $codes = $mine[$id] ?? [];
            $soldOut = $o['total_limit'] !== null && (int)$o['issued'] >= (int)$o['total_limit'];
            /* پیشنهاد تمام‌شده فقط برای کسی دیده می‌شود که کدش را دارد */
            if ($soldOut && !$codes) continue;

            $out[] = [
                'id'            => $id,
                'business_name' => $o['business_name'],
                'category'      => $o['category'],
                'title'         => $o['title'],
                'description'   => (string)($o['description'] ?? ''),
                'terms'         => (string)($o['terms'] ?? ''),
                'address'       => (string)($o['address'] ?? ''),
                'phone'         => (string)($o['phone'] ?? ''),
                'distance'      => (string)($o['distance'] ?? ''),
                'image'         => (string)($o['image'] ?? ''),
                'valid_to'      => $o['valid_to'],
                'codes'         => $codes,
                'can_claim'     => $roomId !== null && !$soldOut && count($codes) < max(1, (int)$o['per_stay_limit']),
            ];
        }
        return $out;
    }

    /**
     * یک کد برای اتاق صادر می‌کند.
     * @param array{tenant_id:int,room_id:int,status:string,check_in_at:?string,guest_name:?string} $room
     * @return array{ok:bool,message:string,code:int,claim_code?:string}
     */
    public function claim(array $room, int $offerId, string $screenCode = ''): array
    {
        $tid = (int)$room['tenant_id'];
        if (($room['status'] ?? '') !== 'occupied') {
            return ['ok' => false, 'message' => 'کد تخفیف فقط برای مهمان ساکن صادر می‌شود', 'code' => 403];
        }

        $this->db->beginTransaction();
        try {
            /* قفل روی پیشنهاد تا دو درخواست هم‌زمان از سقف رد نشوند */
            $o = $this->db->row('SELECT * FROM local_offers WHERE id = ? AND tenant_id = ? FOR UPDATE', [$offerId, $tid]);
            $today = date('Y-m-d');
            if (!$o || !(int)$o['is_active']
                || ($o['valid_from'] && $o['valid_from'] > $today) || ($o['valid_to'] && $o['valid_to'] < $today)) {
                $this->db->rollback();
                return ['ok' => false, 'message' => 'این تخفیف در دسترس نیست', 'code' => 404];
            }

            $mine = array_filter($this->stayClaims($tid, (int)$room['room_id'], $room['check_in_at'] ?? null),
                                 fn($c) => (int)$c['offer_id'] === $offerId);
            if (count($mine) >= max(1, (int)$o['per_stay_limit'])) {
                $this->db->rollback();
                $last = end($mine);
                return ['ok' => false, 'message' => 'کد این تخفیف را قبلاً گرفته‌اید: ' . $last['code'], 'code' => 409];
            }

            if ($o['total_limit'] !== null) {
                $issued = (int)$this->db->value("SELECT COUNT(*) FROM offer_claims WHERE offer_id = ? AND status <> 'void'", [$offerId]);
                if ($issued >= (int)$o['total_limit']) {
                    $this->db->rollback();
                    return ['ok' => false, 'message' => 'ظرفیت این تخفیف تمام شده است', 'code' => 409];
                }
            }

            $code = '';
            for ($try = 0; $try < 8 && $code === ''; $try++) {
                $c = self::newCode();
                if (!$this->db->exists('offer_claims', ['tenant_id' => $tid, 'code' => $c])) $code = $c;
            }
            if ($code === '') { $this->db->rollback(); return ['ok' => false, 'message' => 'صدور کد ناموفق بود', 'code' => 500]; }

            $this->db->insert('offer_claims', [
                'tenant_id'   => $tid,
                'offer_id'    => $offerId,
                'room_id'     => (int)$room['room_id'],
                'screen_code' => $screenCode !== '' ? mb_substr($screenCode, 0, 32) : null,
                'guest_name'  => $room['guest_name'] ?? null,
                'code'        => $code,
            ]);
            $this->db->commit();
            return ['ok' => true, 'message' => 'کد تخفیف صادر شد', 'code' => 201, 'claim_code' => $code];
        } catch (\Throwable $e) {
            if ($this->db->pdo()->inTransaction()) $this->db->rollback();
            throw $e;
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  تأیید کد
    // ══════════════════════════════════════════════════════════════

    /**
     * کد را «استفاده‌شده» می‌کند. $offerId برای لینک کسب‌وکار است: هر
     * کسب‌وکار فقط کدهای پیشنهاد خودش را می‌بیند.
     * @return array{ok:bool,message:string,code:int,claim?:array<string,mixed>}
     */
    public function redeem(int $tenantId, string $input, string $via, ?int $userId = null, ?int $offerId = null): array
    {
        $code = self::normalize($input);
        if ($code === '') return ['ok' => false, 'message' => 'کد نامعتبر است', 'code' => 422];

        $sql = 'SELECT c.*, o.title, o.business_name, o.valid_to
                  FROM offer_claims c JOIN local_offers o ON o.id = c.offer_id
                 WHERE c.tenant_id = ? AND c.code = ?';
        $par = [$tenantId, $code];
        if ($offerId !== null) { $sql .= ' AND c.offer_id = ?'; $par[] = $offerId; }
        $c = $this->db->row($sql, $par);

        if (!$c) return ['ok' => false, 'message' => 'این کد پیدا نشد', 'code' => 404];
        $info = ['code' => $c['code'], 'title' => $c['title'], 'business_name' => $c['business_name'],
                 'created_at' => $c['created_at'], 'redeemed_at' => $c['redeemed_at']];

        if ($c['status'] === 'redeemed') {
            return ['ok' => false, 'message' => 'این کد قبلاً استفاده شده است', 'code' => 409, 'claim' => $info];
        }
        if ($c['status'] === 'void') {
            return ['ok' => false, 'message' => 'این کد باطل شده است', 'code' => 409, 'claim' => $info];
        }
        if ($c['valid_to'] && $c['valid_to'] < date('Y-m-d')) {
            return ['ok' => false, 'message' => 'مهلت این تخفیف تمام شده است', 'code' => 409, 'claim' => $info];
        }

        /* شرط status در UPDATE: دو تأیید هم‌زمان فقط یکی موفق شود */
        $n = $this->db->query(
            "UPDATE offer_claims SET status = 'redeemed', redeemed_at = NOW(), redeemed_via = ?, redeemed_by = ?
              WHERE id = ? AND status = 'issued'",
            [$via === 'partner' ? 'partner' : 'panel', $userId, (int)$c['id']]
        )->rowCount();
        if ($n !== 1) return ['ok' => false, 'message' => 'این کد قبلاً استفاده شده است', 'code' => 409, 'claim' => $info];

        $info['redeemed_at'] = date('Y-m-d H:i:s');
        return ['ok' => true, 'message' => 'کد تأیید شد — تخفیف را اعمال کنید', 'code' => 200, 'claim' => $info];
    }

    /**
     * تأیید از لینک کسب‌وکار، با سقف تلاش ناموفق.
     * @return array{ok:bool,message:string,code:int,claim?:array<string,mixed>,offer?:array<string,mixed>}
     */
    public function partnerRedeem(string $token, string $input): array
    {
        $o = $this->partnerOffer($token);
        if (!$o) return ['ok' => false, 'message' => 'لینک نامعتبر است', 'code' => 404];

        if ($this->partnerLocked($o)) {
            return ['ok' => false, 'message' => 'تلاش ناموفق زیاد بود؛ ۱۵ دقیقه‌ی دیگر امتحان کنید', 'code' => 429, 'offer' => $o];
        }

        $res = $this->redeem((int)$o['tenant_id'], $input, 'partner', null, (int)$o['id']);
        /* فقط «پیدا نشد» حدس اشتباه است؛ کد تکراری یا منقضی حدس نیست */
        if ($res['code'] === 404 || $res['code'] === 422) {
            $fresh = $o['partner_fail_at'] && strtotime((string)$o['partner_fail_at']) > time() - self::PARTNER_FAIL_WINDOW;
            $this->db->query(
                'UPDATE local_offers SET partner_fails = ?, partner_fail_at = NOW() WHERE id = ?',
                [$fresh ? (int)$o['partner_fails'] + 1 : 1, (int)$o['id']]
            );
        }
        $res['offer'] = $o;
        return $res;
    }

    /** @return array<string,mixed>|null */
    public function partnerOffer(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
        return $this->db->row(
            'SELECT id, tenant_id, business_name, title, valid_to, is_active, partner_fails, partner_fail_at
               FROM local_offers WHERE partner_token = ?',
            [$token]
        ) ?: null;
    }

    private function partnerLocked(array $o): bool
    {
        return (int)$o['partner_fails'] >= self::PARTNER_MAX_FAILS && $o['partner_fail_at']
            && strtotime((string)$o['partner_fail_at']) > time() - self::PARTNER_FAIL_WINDOW;
    }

    // ══════════════════════════════════════════════════════════════
    //  پنل
    // ══════════════════════════════════════════════════════════════

    /** @return list<array<string,mixed>> */
    public function listForPanel(int $tenantId): array
    {
        return $this->db->rows(
            "SELECT o.*,
                    (SELECT COUNT(*) FROM offer_claims c WHERE c.offer_id = o.id AND c.status <> 'void')     AS issued,
                    (SELECT COUNT(*) FROM offer_claims c WHERE c.offer_id = o.id AND c.status = 'redeemed')  AS redeemed
               FROM local_offers o WHERE o.tenant_id = ? ORDER BY o.is_active DESC, o.sort_order, o.id",
            [$tenantId]
        );
    }

    /**
     * @param array<string,mixed> $d
     * @return array{ok:bool,message:string,code:int,id?:int}
     */
    public function save(int $tenantId, array $d, ?int $id = null): array
    {
        $row = [
            'business_name'  => trim((string)($d['business_name'] ?? '')),
            'category'       => in_array($d['category'] ?? '', self::CATEGORIES, true) ? $d['category'] : 'other',
            'title'          => trim((string)($d['title'] ?? '')),
            'description'    => self::opt($d['description'] ?? null, 5000),
            'terms'          => self::opt($d['terms'] ?? null, 500),
            'address'        => self::opt($d['address'] ?? null, 300),
            'phone'          => self::opt($d['phone'] ?? null, 40),
            'distance'       => self::opt($d['distance'] ?? null, 50),
            'image'          => self::opt($d['image'] ?? null, 500),
            'valid_from'     => self::date($d['valid_from'] ?? null),
            'valid_to'       => self::date($d['valid_to'] ?? null),
            'per_stay_limit' => max(1, min(20, (int)($d['per_stay_limit'] ?? 1))),
            'total_limit'    => isset($d['total_limit']) && $d['total_limit'] !== '' && $d['total_limit'] !== null
                                ? max(1, (int)$d['total_limit']) : null,
            'is_active'      => !empty($d['is_active']) ? 1 : 0,
            'sort_order'     => max(0, min(9999, (int)($d['sort_order'] ?? 0))),
        ];
        if ($row['business_name'] === '' || $row['title'] === '') {
            return ['ok' => false, 'message' => 'نام کسب‌وکار و عنوان تخفیف لازم است', 'code' => 422];
        }
        $row['business_name'] = mb_substr($row['business_name'], 0, 150);
        $row['title']         = mb_substr($row['title'], 0, 200);
        if ($row['valid_from'] && $row['valid_to'] && $row['valid_to'] < $row['valid_from']) {
            return ['ok' => false, 'message' => 'تاریخ پایان پیش از تاریخ شروع است', 'code' => 422];
        }
        /* تصویر فقط از فایل‌های خود سامانه یا https؛ javascript: و مانند آن نه */
        if ($row['image'] !== null && !preg_match('#^(/uploads/|https?://)#i', $row['image'])) {
            return ['ok' => false, 'message' => 'آدرس تصویر نامعتبر است', 'code' => 422];
        }

        if ($id === null) {
            $row['tenant_id']     = $tenantId;
            $row['partner_token'] = bin2hex(random_bytes(16));
            $id = (int)$this->db->insert('local_offers', $row);
            return ['ok' => true, 'message' => 'تخفیف ثبت شد', 'code' => 201, 'id' => $id];
        }

        if (!$this->db->exists('local_offers', ['id' => $id, 'tenant_id' => $tenantId])) {
            return ['ok' => false, 'message' => 'تخفیف یافت نشد', 'code' => 404];
        }
        $this->db->update('local_offers', $row, ['id' => $id, 'tenant_id' => $tenantId]);
        return ['ok' => true, 'message' => 'ذخیره شد', 'code' => 200, 'id' => $id];
    }

    /** لینک لورفته را باطل می‌کند */
    public function rotateToken(int $tenantId, int $id): ?string
    {
        $t = bin2hex(random_bytes(16));
        $n = $this->db->update('local_offers', ['partner_token' => $t, 'partner_fails' => 0, 'partner_fail_at' => null],
                               ['id' => $id, 'tenant_id' => $tenantId]);
        return $n ? $t : null;
    }

    /** @return list<array<string,mixed>> */
    public function claims(int $tenantId, ?int $offerId = null, int $limit = 300): array
    {
        $sql = 'SELECT c.id, c.offer_id, c.code, c.status, c.guest_name, c.created_at, c.redeemed_at, c.redeemed_via,
                       o.business_name, o.title, r.room_number
                  FROM offer_claims c
                  JOIN local_offers o ON o.id = c.offer_id
                  LEFT JOIN iptv_rooms r ON r.id = c.room_id
                 WHERE c.tenant_id = ?';
        $par = [$tenantId];
        if ($offerId) { $sql .= ' AND c.offer_id = ?'; $par[] = $offerId; }
        $sql .= ' ORDER BY c.id DESC LIMIT ' . max(1, min(1000, $limit));
        return $this->db->rows($sql, $par);
    }

    public function void(int $tenantId, int $claimId): bool
    {
        return $this->db->query(
            "UPDATE offer_claims SET status = 'void' WHERE id = ? AND tenant_id = ? AND status = 'issued'",
            [$claimId, $tenantId]
        )->rowCount() === 1;
    }

    // ══════════════════════════════════════════════════════════════

    /** @return list<array<string,mixed>> */
    private function stayClaims(int $tenantId, int $roomId, ?string $checkInAt): array
    {
        /* بدون تاریخ ورود، ۳۰ روز اخیر؛ وگرنه مهمان قبلی سهمیه‌ی مهمان بعدی را می‌خورد */
        $since = $checkInAt ?: date('Y-m-d H:i:s', time() - 30 * 86400);
        return $this->db->rows(
            "SELECT offer_id, code, status, created_at FROM offer_claims
              WHERE tenant_id = ? AND room_id = ? AND created_at >= ? AND status <> 'void'
              ORDER BY id",
            [$tenantId, $roomId, $since]
        );
    }

    public static function newCode(): string
    {
        $a = self::ALPHABET; $n = strlen($a); $s = '';
        for ($i = 0; $i < 8; $i++) $s .= $a[random_int(0, $n - 1)];
        return substr($s, 0, 4) . '-' . substr($s, 4);
    }

    /** «abcd 2345»، «ABCD2345» و «۲۳۴۵» همه به «ABCD-2345» */
    public static function normalize(string $in): string
    {
        $in = strtr($in, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
                          '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        $s = strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $in));
        if (strlen($s) !== 8 || strspn($s, self::ALPHABET) !== 8) return '';
        return substr($s, 0, 4) . '-' . substr($s, 4);
    }

    private static function opt(mixed $v, int $len): ?string
    {
        $v = trim((string)($v ?? ''));
        return $v === '' ? null : mb_substr($v, 0, $len);
    }

    private static function date(mixed $v): ?string
    {
        $v = trim((string)($v ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
