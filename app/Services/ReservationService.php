<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * رزرو رستوران و امکانات — TODO ۲.۱۳ و ۲.۱۴.
 *
 * رزرو روی «محل» می‌نشیند (venues). محلی که bookable باشد، روزش به
 * نوبت‌هایی با طول slot_minutes از ساعت باز شدن تا بسته شدن تقسیم
 * می‌شود و ظرفیت هر نوبت همان capacity محل است — برای رستوران تعداد
 * صندلی، برای استخر و باشگاه تعداد نفری که هم‌زمان جا می‌شوند.
 *
 * ── تصمیم‌های طراحی ──────────────────────────────────────────────
 *
 * ظرفیت با «هم‌پوشانی» حساب می‌شود نه با «همان ساعت شروع». اگر مدیر
 * طول نوبت را بعدا از ۶۰ به ۹۰ دقیقه عوض کند، رزروهای قدیمی دیگر روی
 * شبکه‌ی نوبت‌ها نمی‌افتند؛ مقایسه‌ی ساعت شروع آن‌ها را نمی‌دید و
 * رستوران بیش از ظرفیت پر می‌شد. هم‌پوشانی محافظه‌کار است، هرگز
 * بیش‌فروش نمی‌کند.
 *
 * ثبت با قفل ردیف محل (FOR UPDATE) انجام می‌شود. دو مهمان که هم‌زمان
 * آخرین میز را می‌گیرند وگرنه هر دو «جا هست» می‌خوانند و هر دو ثبت
 * می‌شوند — و این دقیقا ساعت شلوغ شام است، همان وقتی که اتفاق می‌افتد.
 *
 * هزینه موقع «تایید» روی صورتحساب می‌نشیند نه موقع ثبت: رزروی که
 * رستوران هنوز قبول نکرده نباید روی فاکتور مهمان باشد. با لغو، قلم
 * باطل می‌شود (حذف نه — صورتحساب باید قابل حسابرسی بماند). عدم حضور
 * قلم را نگه می‌دارد؛ جا برای مهمان نگه داشته شده بود.
 */
final class ReservationService
{
    public const STATUSES = ['pending', 'confirmed', 'completed', 'no_show', 'cancelled'];

    /** وضعیت‌هایی که جا اشغال می‌کنند */
    private const ACTIVE = ['pending', 'confirmed'];

    /** تغییر وضعیت‌های مجاز از پنل */
    private const TRANSITIONS = [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['completed', 'no_show', 'cancelled'],
    ];

    /** بیشترین رزرو فعال هم‌زمان برای یک اتاق — جلوی رزرو همه‌ی میزها از روی ریموت */
    public const MAX_ACTIVE_PER_ROOM = 3;

    /** رزرو تاییدشده را مهمان تا این چند دقیقه پیش از شروع خودش لغو می‌کند */
    public const GUEST_CANCEL_CUTOFF_MIN = 120;

    private Database $db;
    private FolioService $folio;

    public function __construct(?Database $db = null, ?FolioService $folio = null)
    {
        $this->db    = $db ?? Database::getInstance();
        $this->folio = $folio ?? new FolioService($this->db);
    }

    // ══════════════════════════════════════════════════════════════
    //  محل‌ها و نوبت‌ها
    // ══════════════════════════════════════════════════════════════

    /** محل‌هایی که مهمان می‌تواند رزرو کند */
    public function bookableVenues(int $tenantId): array
    {
        return $this->db->rows(
            "SELECT id, kind, name, name_en, floor, description, image_url, capacity,
                    open_from, open_to, slot_minutes, max_party, booking_price, days_ahead
               FROM venues
              WHERE tenant_id = ? AND is_active = 1 AND bookable = 1
                AND open_from IS NOT NULL AND open_to IS NOT NULL
              ORDER BY sort_order, name",
            [$tenantId]
        );
    }

    /**
     * نوبت‌های یک روز با ظرفیت باقی‌مانده.
     *
     * @return array{ok:bool,message:string,slots:array<int,array<string,mixed>>}
     */
    public function slots(int $tenantId, int $venueId, string $date, ?\DateTimeImmutable $now = null): array
    {
        $now   = $now ?? new \DateTimeImmutable('now');
        $venue = $this->venue($tenantId, $venueId);
        if (!$venue) return ['ok' => false, 'message' => 'این محل رزرو نمی‌پذیرد', 'slots' => []];

        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$day || $day->format('Y-m-d') !== $date) {
            return ['ok' => false, 'message' => 'تاریخ نامعتبر است', 'slots' => []];
        }

        $today = $now->setTime(0, 0);
        if ($day < $today) return ['ok' => false, 'message' => 'تاریخ گذشته است', 'slots' => []];
        if ($day > $today->modify('+' . (int)$venue['days_ahead'] . ' days')) {
            return ['ok' => false, 'message' => 'این تاریخ هنوز برای رزرو باز نشده', 'slots' => []];
        }

        $grid = $this->grid($venue, $day);
        if (!$grid) return ['ok' => true, 'message' => '', 'slots' => []];

        $used = $this->usage($venueId, $grid[0][0], $grid[count($grid) - 1][1]);
        $cap  = (int)($venue['capacity'] ?? 0);

        $out = [];
        foreach ($grid as [$start, $end]) {
            $taken     = $this->takenIn($used, $start, $end);
            $remaining = $cap > 0 ? max(0, $cap - $taken) : null;

            $out[] = [
                'start'     => $start->format('Y-m-d H:i:s'),
                'end'       => $end->format('Y-m-d H:i:s'),
                'label'     => $start->format('H:i'),
                'remaining' => $remaining,
                /* نوبتی که شروع شده دیگر قابل رزرو نیست، حتی اگر جا
                   داشته باشد — مهمان تا برسد نصفش گذشته است. */
                'available' => $start > $now && ($remaining === null || $remaining > 0),
            ];
        }

        return ['ok' => true, 'message' => '', 'slots' => $out];
    }

    // ══════════════════════════════════════════════════════════════
    //  ثبت
    // ══════════════════════════════════════════════════════════════

    /**
     * @param array<string,mixed> $data venue_id, start_at, party_size, note, room_id, guest_name
     * @return array{ok:bool,id:?int,message:string,code:int}
     */
    public function create(int $tenantId, array $data, string $source = 'tv',
                           ?int $userId = null, ?\DateTimeImmutable $now = null): array
    {
        $now     = $now ?? new \DateTimeImmutable('now');
        $venueId = (int)($data['venue_id'] ?? 0);
        $venue   = $this->venue($tenantId, $venueId);
        if (!$venue) return $this->fail('این محل رزرو نمی‌پذیرد', 404);

        $party = (int)($data['party_size'] ?? 0);
        if ($party < 1 || $party > (int)$venue['max_party']) {
            return $this->fail('تعداد نفرات باید بین ۱ تا ' . (int)$venue['max_party'] . ' باشد', 422);
        }

        $start = $this->parse((string)($data['start_at'] ?? ''));
        if (!$start) return $this->fail('زمان رزرو نامعتبر است', 422);

        /* فقط ساعت‌هایی که روی شبکه‌ی نوبت‌ها هستند. بدون این، ۱۹:۰۷
           هم ثبت می‌شد و نوبت‌های کناری را نیمه‌پر نشان می‌داد. */
        $slot = null;
        $bookDay = $start;
        /* نوبتی بعد از نیمه‌شب (کافی‌شاپی که تا ۲ باز است) به روز قبل تعلق دارد */
        foreach ([$start->setTime(0, 0), $start->setTime(0, 0)->modify('-1 day')] as $d) {
            foreach ($this->grid($venue, $d) as [$s, $e]) {
                if ($s == $start) { $slot = [$s, $e]; $bookDay = $d; break 2; }
            }
        }
        if (!$slot) return $this->fail('این ساعت جزو نوبت‌های رزرو نیست', 422);
        if ($slot[0] <= $now) return $this->fail('زمان این نوبت گذشته است', 422);
        if ($bookDay > $now->setTime(0, 0)->modify('+' . (int)$venue['days_ahead'] . ' days')) {
            return $this->fail('این تاریخ هنوز برای رزرو باز نشده', 422);
        }

        // ── مهمان ───────────────────────────────────────────────────
        $roomId    = isset($data['room_id']) ? ((int)$data['room_id'] ?: null) : null;
        $guestName = trim((string)($data['guest_name'] ?? '')) ?: null;

        if ($roomId !== null) {
            $room = $this->db->row(
                'SELECT id, status, guest_name FROM iptv_rooms WHERE id = ? AND tenant_id = ?',
                [$roomId, $tenantId]
            );
            if (!$room) return $this->fail('اتاق یافت نشد', 404);
            if ($room['status'] !== 'occupied') return $this->fail('این اتاق در حال حاضر مهمان ندارد', 409);
            $guestName = $guestName ?? ($room['guest_name'] ?: null);

            $active = (int)$this->db->value(
                "SELECT COUNT(*) FROM reservations
                  WHERE tenant_id = ? AND room_id = ? AND status IN ('pending','confirmed') AND end_at > ?",
                [$tenantId, $roomId, $now->format('Y-m-d H:i:s')]
            );
            if ($source === 'tv' && $active >= self::MAX_ACTIVE_PER_ROOM) {
                return $this->fail('رزروهای فعال شما به سقف رسیده — برای رزرو بیشتر با پذیرش تماس بگیرید', 429);
            }
        } elseif ($source === 'tv') {
            return $this->fail('اتاق مشخص نیست', 422);
        } elseif ($guestName === null) {
            return $this->fail('برای مهمان بیرونی نام لازم است', 422);
        }

        $note  = mb_substr(trim((string)($data['note'] ?? '')), 0, 300) ?: null;
        $price = round((float)$venue['booking_price'] * $party, 2);

        // ── ظرفیت، زیر قفل ─────────────────────────────────────────
        $this->db->beginTransaction();
        try {
            $this->db->row('SELECT id FROM venues WHERE id = ? FOR UPDATE', [$venueId]);

            if ($roomId !== null) {
                $dup = $this->db->value(
                    "SELECT id FROM reservations
                      WHERE venue_id = ? AND room_id = ? AND status IN ('pending','confirmed')
                        AND start_at < ? AND end_at > ? LIMIT 1",
                    [$venueId, $roomId, $slot[1]->format('Y-m-d H:i:s'), $slot[0]->format('Y-m-d H:i:s')]
                );
                if ($dup) {
                    $this->db->rollback();
                    return $this->fail('برای همین نوبت قبلا رزرو دارید', 409);
                }
            }

            $cap = (int)($venue['capacity'] ?? 0);
            if ($cap > 0) {
                $taken = $this->takenIn($this->usage($venueId, $slot[0], $slot[1]), $slot[0], $slot[1]);
                if ($taken + $party > $cap) {
                    $this->db->rollback();
                    $left = max(0, $cap - $taken);
                    return $this->fail($left > 0
                        ? "در این نوبت فقط برای $left نفر جا مانده"
                        : 'این نوبت پر شده است', 409);
                }
            }

            $id = (int)$this->db->insert('reservations', [
                'tenant_id'  => $tenantId,
                'venue_id'   => $venueId,
                'room_id'    => $roomId,
                'guest_name' => $guestName !== null ? mb_substr($guestName, 0, 100) : null,
                'party_size' => $party,
                'start_at'   => $slot[0]->format('Y-m-d H:i:s'),
                'end_at'     => $slot[1]->format('Y-m-d H:i:s'),
                'status'     => 'pending',
                'note'       => $note,
                'price'      => $price,
                'source'     => $source === 'panel' ? 'panel' : 'tv',
                'created_by' => $userId,
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            error_log('[RESERVATION] ' . $e->getMessage());
            return $this->fail('ثبت رزرو ناموفق بود', 500);
        }

        return ['ok' => true, 'id' => $id, 'message' => 'رزرو ثبت شد و منتظر تایید است', 'code' => 201];
    }

    // ══════════════════════════════════════════════════════════════
    //  وضعیت
    // ══════════════════════════════════════════════════════════════

    /** @return array{ok:bool,message:string,code:int} */
    public function setStatus(int $tenantId, int $id, string $to, ?int $userId = null, ?string $staffNote = null): array
    {
        $r = $this->db->row(
            'SELECT r.*, v.name AS venue_name FROM reservations r
               JOIN venues v ON v.id = r.venue_id
              WHERE r.id = ? AND r.tenant_id = ?',
            [$id, $tenantId]
        );
        if (!$r) return ['ok' => false, 'message' => 'رزرو یافت نشد', 'code' => 404];

        $from = (string)$r['status'];
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            return ['ok' => false, 'message' => 'این تغییر وضعیت مجاز نیست', 'code' => 409];
        }

        $fields = ['status' => $to];
        if ($staffNote !== null) $fields['staff_note'] = mb_substr(trim($staffNote), 0, 300) ?: null;

        if ($to === 'confirmed') {
            $fields['confirmed_at'] = date('Y-m-d H:i:s');

            if ((float)$r['price'] > 0 && $r['room_id'] !== null) {
                $res = $this->folio->post($tenantId, (int)$r['room_id'], [
                    'title'        => 'رزرو ' . $r['venue_name'] . ' — ' . (int)$r['party_size'] . ' نفر',
                    'qty'          => 1,
                    'unit_price'   => (float)$r['price'],
                    'source'       => 'reservation',
                    'reference_id' => $id,
                ], $userId);
                if (!$res['ok']) return ['ok' => false, 'message' => $res['message'], 'code' => 409];
                $fields['charge_id'] = $res['id'];
            }
        }

        if ($to === 'cancelled') {
            $fields['cancelled_at'] = date('Y-m-d H:i:s');
            $this->voidCharge($tenantId, $r, $userId, 'لغو رزرو');
            $fields['charge_id'] = null;
        }

        $this->db->update('reservations', $fields, ['id' => $id, 'tenant_id' => $tenantId]);
        return ['ok' => true, 'message' => 'وضعیت رزرو به‌روز شد', 'code' => 200];
    }

    /** لغو توسط خود مهمان از تلویزیون */
    public function cancelByGuest(int $tenantId, int $roomId, int $id, ?\DateTimeImmutable $now = null): array
    {
        $now = $now ?? new \DateTimeImmutable('now');
        $r   = $this->db->row(
            'SELECT * FROM reservations WHERE id = ? AND tenant_id = ? AND room_id = ?',
            [$id, $tenantId, $roomId]
        );
        if (!$r) return ['ok' => false, 'message' => 'رزرو یافت نشد', 'code' => 404];
        if (!in_array($r['status'], self::ACTIVE, true)) {
            return ['ok' => false, 'message' => 'این رزرو دیگر فعال نیست', 'code' => 409];
        }

        /* رزرو تاییدشده یعنی رستوران برایش جا کنار گذاشته. لغو لحظه‌ی
           آخر از روی ریموت یعنی میز خالی در شلوغ‌ترین ساعت؛ آن را به
           پذیرش می‌سپاریم که بتواند با مهمان حرف بزند. */
        $start = new \DateTimeImmutable((string)$r['start_at']);
        if ($r['status'] === 'confirmed'
            && $start->getTimestamp() - $now->getTimestamp() < self::GUEST_CANCEL_CUTOFF_MIN * 60) {
            return ['ok' => false, 'message' => 'زمان زیادی تا این رزرو نمانده — لطفاً با پذیرش تماس بگیرید', 'code' => 409];
        }

        $this->voidCharge($tenantId, $r, null, 'لغو رزرو توسط مهمان');
        $this->db->update('reservations', [
            'status'       => 'cancelled',
            'cancelled_at' => $now->format('Y-m-d H:i:s'),
            'charge_id'    => null,
        ], ['id' => $id, 'tenant_id' => $tenantId]);

        return ['ok' => true, 'message' => 'رزرو لغو شد', 'code' => 200];
    }

    /** رزروهای اقامت جاری یک اتاق */
    public function forRoom(int $tenantId, int $roomId, ?string $since): array
    {
        $sql = 'SELECT r.id, r.venue_id, v.name AS venue_name, v.kind, r.party_size, r.start_at,
                       r.end_at, r.status, r.note, r.price
                  FROM reservations r
                  JOIN venues v ON v.id = r.venue_id
                 WHERE r.tenant_id = ? AND r.room_id = ?';
        $par = [$tenantId, $roomId];
        /* مهمان قبلی همین اتاق نباید رزروهای خودش را اینجا ببیند */
        if ($since) { $sql .= ' AND r.created_at >= ?'; $par[] = $since; }
        $sql .= ' ORDER BY r.start_at DESC LIMIT 30';

        return $this->db->rows($sql, $par);
    }

    // ══════════════════════════════════════════════════════════════
    //  کمکی
    // ══════════════════════════════════════════════════════════════

    private function venue(int $tenantId, int $venueId): ?array
    {
        if ($venueId <= 0) return null;
        return $this->db->row(
            'SELECT * FROM venues
              WHERE id = ? AND tenant_id = ? AND is_active = 1 AND bookable = 1
                AND open_from IS NOT NULL AND open_to IS NOT NULL',
            [$venueId, $tenantId]
        );
    }

    /**
     * شبکه‌ی نوبت‌های یک روز.
     *
     * ساعت بسته شدنی که پیش از باز شدن است یعنی از نیمه‌شب رد می‌شود
     * (کافی‌شاپ ۱۸ تا ۲). باز و بسته‌ی برابر یعنی شبانه‌روزی.
     *
     * @return array<int,array{0:\DateTimeImmutable,1:\DateTimeImmutable}>
     */
    private function grid(array $venue, \DateTimeImmutable $day): array
    {
        $len = max(15, (int)$venue['slot_minutes']);
        [$fh, $fm] = array_map('intval', explode(':', (string)$venue['open_from']));
        [$th, $tm] = array_map('intval', explode(':', (string)$venue['open_to']));

        $open  = $day->setTime($fh, $fm);
        $close = $day->setTime($th, $tm);
        if ($close <= $open) $close = $close->modify('+1 day');

        $out = [];
        for ($s = $open; ; $s = $s->modify("+$len minutes")) {
            $e = $s->modify("+$len minutes");
            if ($e > $close) break;
            $out[] = [$s, $e];
            if (count($out) >= 96) break; // نگهبان: نوبت ۱۵ دقیقه‌ای شبانه‌روزی
        }
        return $out;
    }

    /** رزروهای فعالی که با بازه تلاقی دارند — یک کوئری برای کل روز */
    private function usage(int $venueId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->db->rows(
            "SELECT start_at, end_at, party_size FROM reservations
              WHERE venue_id = ? AND status IN ('pending','confirmed')
                AND start_at < ? AND end_at > ?",
            [$venueId, $to->format('Y-m-d H:i:s'), $from->format('Y-m-d H:i:s')]
        );
    }

    private function takenIn(array $used, \DateTimeImmutable $s, \DateTimeImmutable $e): int
    {
        $sS = $s->format('Y-m-d H:i:s');
        $eS = $e->format('Y-m-d H:i:s');
        $n  = 0;
        foreach ($used as $u) {
            if ($u['start_at'] < $eS && $u['end_at'] > $sS) $n += (int)$u['party_size'];
        }
        return $n;
    }

    private function voidCharge(int $tenantId, array $r, ?int $userId, string $reason): void
    {
        if (empty($r['charge_id'])) return;
        $res = $this->folio->void($tenantId, (int)$r['charge_id'], $userId, $reason);
        if (!$res['ok']) error_log('[RESERVATION] void charge ' . $r['charge_id'] . ': ' . $res['message']);
    }

    private function parse(string $v): ?\DateTimeImmutable
    {
        $v = trim($v);
        if ($v === '') return null;
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $f) {
            $d = \DateTimeImmutable::createFromFormat('!' . $f, $v);
            if ($d && $d->format($f) === $v) return $d;
        }
        return null;
    }

    private function fail(string $message, int $code): array
    {
        return ['ok' => false, 'id' => null, 'message' => $message, 'code' => $code];
    }
}
