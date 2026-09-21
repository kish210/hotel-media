<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * PMS Charge Service — ارسال اقلام صورتحساب به سیستم هتلداری
 *
 * تا امروز اتصال PMS یک‌طرفه بود: هتل به ما checkin و checkout می‌فرستاد.
 * مینی‌بار و خرید فیلم تا وقتی روی صورتحساب واقعی هتل ننشینند، برای
 * حسابداری هتل وجود ندارند. این سرویس آن طرف دیگر را می‌سازد.
 *
 * طراحی برای دنیای واقعی:
 *  • PMS هتل ممکن است ساعت‌ها قطع باشد. قلم در صف می‌ماند و بعدا می‌رود؛
 *    هیچ‌وقت یک قلم به‌خاطر شبکه گم نمی‌شود.
 *  • هر PMS نام فیلد خودش را دارد (room / roomNumber / RoomNo)، پس نگاشت
 *    نام‌ها در تنظیمات است نه در کد.
 *  • ارسال همیشه پس‌زمینه است. اگر ثبت مینی‌بار منتظر PMS بماند، خانه‌دار
 *    پشت یک صفحه‌ی بارگذاری گیر می‌کند.
 *
 * فاز ۵ نقشه‌راه — docs/TODO.md (۵.۳)
 */
class PmsChargeService
{
    /** بعد از این تعداد تلاش ناموفق، قلم failed می‌شود تا صف تمیز بماند */
    private const MAX_ATTEMPTS = 5;

    private const HTTP_TIMEOUT = 12;

    /** نام فیلدهای پیش‌فرض — با field_map قابل تغییر است */
    private const DEFAULT_MAP = [
        'room'        => 'room_number',
        'amount'      => 'amount',
        'description' => 'description',
        'quantity'    => 'quantity',
        'currency'    => 'currency',
        'reference'   => 'reference',
        'category'    => 'category',
        'posted_at'   => 'posted_at',
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * اقلام منتظر را به PMS می‌فرستد.
     * برای اجرای زمان‌بندی‌شده: php artisan pms:push
     *
     * @return array{sent:int,failed:int,skipped:int,message:string}
     */
    public function pushPending(?int $tenantId = null, int $limit = 100): array
    {
        $sql = "SELECT c.*, r.room_number, r.pms_room_id
                  FROM room_charges c
                  JOIN iptv_rooms r ON r.id = c.room_id
                 WHERE c.pms_status = 'pending'
                   AND c.status <> 'void'
                   AND c.amount > 0
                   AND c.pms_attempts < ?";
        $params = [self::MAX_ATTEMPTS];

        if ($tenantId !== null) { $sql .= ' AND c.tenant_id = ?'; $params[] = $tenantId; }

        $sql .= ' ORDER BY c.id LIMIT ' . max(1, min(500, $limit));

        $charges = $this->db->rows($sql, $params);
        if (!$charges) return ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'قلمی برای ارسال نیست'];

        $sent = 0; $failed = 0; $skipped = 0;
        $pmsCache = [];

        foreach ($charges as $charge) {
            $tid = (int)$charge['tenant_id'];

            if (!array_key_exists($tid, $pmsCache)) {
                $pmsCache[$tid] = $this->activePms($tid);
            }
            $pms = $pmsCache[$tid];

            if (!$pms) {
                // هتلی که اصلا PMS ندارد نباید صف را پر نگه دارد
                $this->db->update('room_charges', [
                    'pms_status' => 'skipped',
                    'pms_error'  => 'هیچ اتصال PMS فعالی با ارسال قلم تعریف نشده',
                ], ['id' => (int)$charge['id']]);
                $skipped++;
                continue;
            }

            $res = $this->pushOne($charge, $pms);
            $res['ok'] ? $sent++ : $failed++;
        }

        return [
            'sent' => $sent, 'failed' => $failed, 'skipped' => $skipped,
            'message' => "$sent ارسال شد" . ($failed ? "، $failed ناموفق" : '') . ($skipped ? "، $skipped رد شد" : ''),
        ];
    }

    /**
     * یک قلم را می‌فرستد و نتیجه را ثبت می‌کند.
     * @param array<string,mixed> $charge
     * @param array<string,mixed> $pms
     * @return array{ok:bool,message:string}
     */
    public function pushOne(array $charge, array $pms): array
    {
        $chargeId = (int)$charge['id'];
        $attempt  = (int)$charge['pms_attempts'] + 1;

        $payload = $this->buildPayload($charge, $pms);

        try {
            [$status, $body] = $this->post((string)$pms['charge_url'], $payload, $pms);
            $ok = $status >= 200 && $status < 300;
        } catch (\Throwable $e) {
            $status = 0;
            $body   = $e->getMessage();
            $ok     = false;
        }

        $this->db->insert('pms_push_log', [
            'tenant_id'   => (int)$charge['tenant_id'],
            'charge_id'   => $chargeId,
            'pms_id'      => (int)$pms['id'],
            'attempt'     => min(255, $attempt),
            'http_status' => $status ?: null,
            'ok'          => $ok ? 1 : 0,
            'response'    => mb_substr((string)$body, 0, 500) ?: null,
        ]);

        if ($ok) {
            $this->db->update('room_charges', [
                'pms_status'   => 'sent',
                'pms_attempts' => $attempt,
                'pms_ref'      => $this->extractRef($body),
                'pms_error'    => null,
            ], ['id' => $chargeId]);

            $this->db->update('pms_integrations', [
                'last_push_at'  => date('Y-m-d H:i:s'),
                'last_push_msg' => '✓ آخرین ارسال موفق',
            ], ['id' => (int)$pms['id']]);

            return ['ok' => true, 'message' => 'ارسال شد'];
        }

        // بعد از سقف تلاش، دیگر تکرار نمی‌کنیم — اپراتور باید دستی ببیند
        $exhausted = $attempt >= self::MAX_ATTEMPTS;

        $this->db->update('room_charges', [
            'pms_status'   => $exhausted ? 'failed' : 'pending',
            'pms_attempts' => $attempt,
            'pms_error'    => mb_substr("HTTP $status — " . (string)$body, 0, 300),
        ], ['id' => $chargeId]);

        $this->db->update('pms_integrations', [
            'last_push_at'  => date('Y-m-d H:i:s'),
            'last_push_msg' => mb_substr("✗ خطا ($status) در قلم #$chargeId", 0, 300),
        ], ['id' => (int)$pms['id']]);

        return ['ok' => false, 'message' => "ارسال ناموفق (HTTP $status)"];
    }

    /** ارسال دستی یک قلم از پنل — برای وقتی اپراتور خطا را رفع کرده */
    public function retry(int $tenantId, int $chargeId): array
    {
        $charge = $this->db->row(
            'SELECT c.*, r.room_number, r.pms_room_id
               FROM room_charges c JOIN iptv_rooms r ON r.id = c.room_id
              WHERE c.id = ? AND c.tenant_id = ?',
            [$chargeId, $tenantId]
        );
        if (!$charge) return ['ok' => false, 'message' => 'قلم یافت نشد'];

        $pms = $this->activePms($tenantId);
        if (!$pms) return ['ok' => false, 'message' => 'هیچ اتصال PMS فعالی تعریف نشده'];

        // شمارنده صفر می‌شود تا تلاش دستی مستقل از تلاش‌های قبلی باشد
        $this->db->update('room_charges', ['pms_attempts' => 0], ['id' => $chargeId]);
        $charge['pms_attempts'] = 0;

        return $this->pushOne($charge, $pms);
    }

    /** آزمایش اتصال بدون ثبت قلم واقعی */
    public function test(int $tenantId): array
    {
        $pms = $this->activePms($tenantId);
        if (!$pms) return ['ok' => false, 'message' => 'هیچ اتصال PMS با ارسال قلم فعال نیست'];

        $map = $this->fieldMap($pms);
        $payload = [
            $map['room']        => 'TEST',
            $map['amount']      => 0,
            $map['description'] => 'Hotel Media — آزمایش اتصال',
            $map['quantity']    => 1,
            $map['currency']    => 'IRR',
            $map['reference']   => 'test-' . time(),
            $map['category']    => 'test',
            $map['posted_at']   => date('c'),
            'test'              => true,
        ];

        try {
            [$status, $body] = $this->post((string)$pms['charge_url'], $payload, $pms);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'اتصال برقرار نشد: ' . $e->getMessage()];
        }

        $ok = $status >= 200 && $status < 300;
        return [
            'ok'      => $ok,
            'message' => $ok
                ? "اتصال موفق (HTTP $status)"
                : "‏PMS پاسخ داد HTTP $status — " . mb_substr((string)$body, 0, 200),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /** @return array<string,mixed>|null */
    private function activePms(int $tenantId): ?array
    {
        return $this->db->row(
            "SELECT * FROM pms_integrations
              WHERE tenant_id = ? AND is_active = 1 AND push_charges = 1
                AND charge_url IS NOT NULL AND charge_url <> ''
              ORDER BY id LIMIT 1",
            [$tenantId]
        ) ?: null;
    }

    /** @return array<string,string> */
    private function fieldMap(array $pms): array
    {
        $map = self::DEFAULT_MAP;

        $custom = $pms['field_map'] ?? null;
        if (is_string($custom) && $custom !== '') {
            $decoded = json_decode($custom, true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    if (isset($map[$k]) && is_string($v) && $v !== '') $map[$k] = $v;
                }
            }
        }

        return $map;
    }

    /** @return array<string,mixed> */
    private function buildPayload(array $charge, array $pms): array
    {
        $map = $this->fieldMap($pms);

        // اگر هتل برای اتاق کد جدا در PMS دارد، همان ارجح است
        $room = $charge['pms_room_id'] ?: $charge['room_number'];

        return [
            $map['room']        => (string)$room,
            $map['amount']      => (float)$charge['amount'],
            $map['description'] => (string)$charge['title'],
            $map['quantity']    => (int)$charge['qty'],
            $map['currency']    => (string)$charge['currency'],
            // ارجاع یکتا تا اگر پاسخ گم شد و دوباره فرستادیم، PMS بتواند
            // تکراری را تشخیص دهد
            $map['reference']   => 'hm-charge-' . (int)$charge['id'],
            $map['category']    => (string)$charge['source'],
            $map['posted_at']   => date('c', strtotime((string)$charge['created_at'])),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $pms
     * @return array{0:int,1:string}
     */
    private function post(string $url, array $payload, array $pms): array
    {
        if (!preg_match('#^https?://#i', $url)) {
            throw new \RuntimeException('آدرس PMS باید با http یا https شروع شود');
        }

        $headers = "Content-Type: application/json\r\n"
                 . "Accept: application/json\r\n"
                 . "User-Agent: HotelMedia-PMS\r\n";

        $secret = (string)($pms['auth_secret'] ?? '');
        switch ($pms['auth_type'] ?? 'none') {
            case 'bearer':
                if ($secret !== '') $headers .= "Authorization: Bearer $secret\r\n";
                break;
            case 'basic':
                if ($secret !== '') $headers .= 'Authorization: Basic ' . base64_encode($secret) . "\r\n";
                break;
            case 'header':
                $name = trim((string)($pms['auth_header'] ?? ''));
                if ($name !== '' && $secret !== '') $headers .= "$name: $secret\r\n";
                break;
        }

        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => $headers,
            'content'       => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout'       => self::HTTP_TIMEOUT,
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) throw new \RuntimeException('اتصال به PMS برقرار نشد');

        $status = 0;
        if (isset($http_response_header)) {
            preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
            $status = (int)($m[1] ?? 0);
        }

        return [$status, (string)$body];
    }

    /** شناسه‌ای که PMS برگردانده، برای پیگیری بعدی */
    private function extractRef(mixed $body): ?string
    {
        $data = json_decode((string)$body, true);
        if (!is_array($data)) return null;

        foreach (['id', 'reference', 'ref', 'transaction_id', 'postingId', 'folioId'] as $k) {
            if (isset($data[$k]) && is_scalar($data[$k])) return mb_substr((string)$data[$k], 0, 80);
            if (isset($data['data'][$k]) && is_scalar($data['data'][$k])) {
                return mb_substr((string)$data['data'][$k], 0, 80);
            }
        }

        return null;
    }
}
