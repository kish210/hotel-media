<?php
declare(strict_types=1);

namespace App\Services\Pms;

/**
 * Asa PMS Client — درایور سیستم هتلداری «آسا» (Asa_System).
 *
 * چرا جدا از PmsChargeService: آن سرویس قلم صورتحساب را با JSON عمومی
 * می‌فرستد. آسا این را نمی‌فهمد؛ پروتکلش از سیستم قدیمی VINAK آمده:
 *
 *   op + data(JSON) + sign
 *   sign  = base64(hmac_sha256(data, password))
 *   پاسخ  = base64(gzcompress(JSON))
 *
 * تأییدشده از `iptv/s/data/pms.php` سیستم قدیمی که با همین هتل
 * کار می‌کرد. مسیرها:
 *
 *   POST /hotel/agency/api/iptv/index.php                register_view | guest_view
 *   POST /hotel/agency/api/iptv/iptv_bill_mojafzar.php     ثبت فیلم پولی
 *
 * ── چرا خطا بلعیده نمی‌شود ولی استثنا هم پرتاب نمی‌شود ──────────
 * PMS هتل ممکن است ساعت‌ها خاموش باشد. `test()` از پنل صدا زده
 * می‌شود و باید پیام بدهد نه اینکه صفحه را بترکاند؛ ولی `charge()`
 * از صف cron می‌آید و باید بفهمد شکست خورده تا تلاش را تکرار کند.
 * پس همه‌جا آرایهٔ نتیجه با کلید `ok` برمی‌گردد.
 */
final class AsaPmsClient
{
    private const TIMEOUT = 12;

    private string $base;
    private string $user;
    private string $pass;

    public function __construct(string $base, string $user = '', string $pass = '')
    {
        $this->base = rtrim($base, '/');
        $this->user = $user;
        $this->pass = $pass;
    }

    /** ساخت از ردیف pms_integrations */
    public static function fromRow(array $pms): self
    {
        $base = (string)($pms['base_url'] ?? '');
        $user = '';
        $pass = '';

        $secret = (string)($pms['auth_secret'] ?? '');
        if (str_contains($secret, ':')) {
            [$user, $pass] = explode(':', $secret, 2);
        }

        return new self($base, $user, $pass);
    }

    /** اطلاعات مهمان/اتاق — register_view */
    public function guest(int $room): array
    {
        return $this->call('register_view', ['room_number' => (string)$room]);
    }

    /** صورتحساب — guest_view */
    public function bill(int $room): array
    {
        return $this->call('guest_view', ['room_number' => (string)$room]);
    }

    /** خروج/آزادسازی اتاق */
    public function checkout(int $room): array
    {
        return $this->call('register_view', ['room_number' => (string)$room, 'checkout' => 1]);
    }

    /** ثبت فیلم پولی روی صورتحساب */
    public function charge(int $room, float $amount, string $title, string $ref = ''): array
    {
        return $this->post('iptv_bill_mojafzar.php', [
            'room_number' => (string)$room,
            'amount'      => $amount,
            'title'       => $title,
            'reference'   => $ref !== '' ? $ref : 'hm-' . time(),
            'user'        => $this->user,
        ]);
    }

    /** آزمایش اتصال از پنل */
    public function test(): array
    {
        $res = $this->call('register_view', ['room_number' => 'TEST']);
        if (!$res['ok']) return $res;

        $data = $res['data'] ?? [];
        return [
            'ok'      => true,
            'message' => 'اتصال به PMS آسا برقرار است'
                       . (isset($data['Return_Code']) ? ' — ' . $data['Return_Code'] : ''),
            'data'    => $data,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    //  حمل‌ونقل
    // ══════════════════════════════════════════════════════════════

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,message:string,data:array<string,mixed>}
     */
    private function call(string $op, array $payload): array
    {
        return $this->post('index.php', ['op' => $op] + $payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,message:string,data:array<string,mixed>}
     */
    private function post(string $path, array $payload): array
    {
        if ($this->base === '' || !preg_match('#^https?://#i', $this->base)) {
            return ['ok' => false, 'message' => 'آدرس PMS آسا تنظیم نشده', 'data' => []];
        }

        $url  = $this->base . (str_contains($this->base, '/hotel/agency/api/iptv') ? '' : '/hotel/agency/api/iptv') . '/' . $path;
        $data = json_encode($payload, JSON_UNESCAPED_UNICODE);

        /* امضا روی همان رشتهٔ JSON است، نه آرایهٔ مرتب‌شده: سرور آسا
           بایت‌به‌بایت همان چیزی را که فرستادیم دوباره امضا می‌کند. */
        $sign = base64_encode(hash_hmac('sha256', (string)$data, $this->pass, true));

        $body = http_build_query(['user' => $this->user, 'data' => $data, 'sign' => $sign]);

        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n"
                             . "Accept: */*\r\nUser-Agent: HotelMedia-ASA\r\n",
            'content'       => $body,
            'timeout'       => self::TIMEOUT,
            'ignore_errors' => true,
        ]]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return ['ok' => false, 'message' => 'اتصال به PMS آسا برقرار نشد (' . $url . ')', 'data' => []];
        }

        $status = 0;
        if (isset($http_response_header)) {
            preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
            $status = (int)($m[1] ?? 0);
        }
        if ($status >= 400) {
            return ['ok' => false, 'message' => 'PMS آسا خطای HTTP ' . $status . ' داد', 'data' => []];
        }

        $decoded = $this->decode((string)$raw);
        if ($decoded === null) {
            return ['ok' => false, 'message' => 'پاسخ PMS آسا خوانده نشد (base64/gzip معتبر نبود)', 'data' => []];
        }

        /* آسا موفقیت را با Return_Code=ok یا Success=true اعلام می‌کند.
           اگر هیچ‌کدام نبود، موفق فرض نمی‌کنیم — تجربهٔ تلخ پروتکل‌های
           قدیمی: پاسخ HTML خطا با کد ۲۰۰. */
        $ok = true;
        if (isset($decoded['Return_Code'])) {
            $ok = strtolower((string)$decoded['Return_Code']) === 'ok';
        } elseif (isset($decoded['Success'])) {
            $ok = (bool)$decoded['Success'];
        }

        return [
            'ok'      => $ok,
            'message' => $ok ? 'پاسخ دریافت شد' : 'PMS آسا درخواست را رد کرد',
            'data'    => $decoded,
        ];
    }

    /** @return array<string,mixed>|null */
    private function decode(string $raw): ?array
    {
        $raw = trim($raw);

        /* پاسخ واقعی base64(gzcompress(json)) است، ولی اگر سرور نسخهٔ
           دیگری داشت، JSON خام هم پذیرفته می‌شود — بهتر از «هیچ». */
        $candidates = [];

        $bin = base64_decode($raw, true);
        if ($bin !== false) {
            $candidates[] = $bin;
            $un = @gzuncompress($bin);
            if ($un !== false) $candidates[] = $un;
        }
        $candidates[] = $raw;

        foreach ($candidates as $c) {
            $d = json_decode((string)$c, true);
            if (is_array($d)) return $d;
        }
        return null;
    }
}
