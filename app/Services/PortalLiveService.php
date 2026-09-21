<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Portal Live Service
 * داده‌های زنده‌ی نوار بالای پلیر: آب‌وهوا، نرخ ارز، اوقات شرعی.
 *
 * هر تلویزیون اتاق این داده‌ها را می‌خواهد، پس بدون کش در یک هتل ۲۰۰ اتاقه
 * صدها درخواست بیرونی در دقیقه ساخته می‌شود و سرویس مبدأ ما را می‌بندد.
 * نتیجه در portal_live_cache ذخیره و تا انقضا از همان‌جا سرو می‌شود.
 *
 * فاز ۳ نقشه‌راه — docs/TODO.md (۳.۶)
 */
class PortalLiveService
{
    /** مدت اعتبار کش هر نوع داده (ثانیه) */
    private const TTL = [
        'weather'  => 900,    // ۱۵ دقیقه
        'currency' => 600,    // ۱۰ دقیقه
        'prayer'   => 43200,  // ۱۲ ساعت — اوقات شرعی روزانه تغییر می‌کند
    ];

    private const HTTP_TIMEOUT = 8;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * داده‌های خواسته‌شده را برمی‌گرداند. هر بخشی که در دسترس نباشد null است،
     * تا یک سرویس خراب کل نوار بالا را از کار نیندازد.
     *
     * @param list<string> $widgets مثلا ['clock','weather','prayer']
     * @return array<string,mixed>
     */
    public function get(int $tenantId, array $widgets, ?string $city = null): array
    {
        $city = trim((string)($city ?: env('WEATHER_DEFAULT_CITY', 'Tehran')));
        $out  = ['server_time' => date('c')];

        foreach ($widgets as $w) {
            $out[$w] = match ($w) {
                'weather'  => $this->cached($tenantId, 'weather',  $city, fn() => $this->fetchWeather($city)),
                'currency' => $this->cached($tenantId, 'currency', 'default', fn() => $this->fetchCurrency()),
                'prayer'   => $this->cached($tenantId, 'prayer',   $city . '|' . date('Y-m-d'), fn() => $this->fetchPrayer($city)),
                'clock'    => null,   // ساعت سمت کلاینت محاسبه می‌شود
                default    => null,
            };
        }

        return $out;
    }

    /** کش را برای یک tenant پاک می‌کند (مثلا بعد از تغییر شهر) */
    public function forget(int $tenantId, ?string $kind = null): int
    {
        $sql    = 'DELETE FROM portal_live_cache WHERE tenant_id = ?';
        $params = [$tenantId];

        if ($kind !== null) { $sql .= ' AND kind = ?'; $params[] = $kind; }

        return $this->db->query($sql, $params)->rowCount();
    }

    // ══════════════════════════════════════════════════════════════
    //  کش
    // ══════════════════════════════════════════════════════════════

    /**
     * مقدار کش‌شده را برمی‌گرداند، و اگر منقضی شده باشد دوباره می‌گیرد.
     * اگر واکشی تازه شکست بخورد، مقدار قدیمی برگردانده می‌شود — یک نوار
     * بالای کمی کهنه بهتر از نوار خالی است.
     */
    private function cached(int $tenantId, string $kind, string $key, callable $fetch): mixed
    {
        $key = mb_substr($key, 0, 120);
        $row = $this->db->row(
            'SELECT payload, expires_at FROM portal_live_cache
              WHERE tenant_id = ? AND kind = ? AND cache_key = ?',
            [$tenantId, $kind, $key]
        );

        if ($row && strtotime((string)$row['expires_at']) > time()) {
            return json_decode((string)$row['payload'], true);
        }

        try {
            $fresh = $fetch();
        } catch (\Throwable $e) {
            error_log("[PORTAL LIVE] $kind failed: " . $e->getMessage());
            // کش منقضی بهتر از هیچ
            return $row ? json_decode((string)$row['payload'], true) : null;
        }

        if ($fresh === null) return $row ? json_decode((string)$row['payload'], true) : null;

        $this->db->query(
            'INSERT INTO portal_live_cache (tenant_id, kind, cache_key, payload, fetched_at, expires_at)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload),
                                     fetched_at = VALUES(fetched_at),
                                     expires_at = VALUES(expires_at)',
            [
                $tenantId, $kind, $key,
                json_encode($fresh, JSON_UNESCAPED_UNICODE),
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s', time() + (self::TTL[$kind] ?? 600)),
            ]
        );

        return $fresh;
    }

    // ══════════════════════════════════════════════════════════════
    //  منابع بیرونی
    // ══════════════════════════════════════════════════════════════

    /** @return array<string,mixed>|null */
    private function fetchWeather(string $city): ?array
    {
        $key = (string)env('WEATHER_API_KEY', '');
        if ($key === '') return null;   // بدون کلید، ویجت نمایش داده نمی‌شود

        $url = 'https://api.openweathermap.org/data/2.5/weather?q=' . rawurlencode($city)
             . '&appid=' . rawurlencode($key) . '&units=metric&lang=fa';

        $data = $this->getJson($url);
        if (!isset($data['main']['temp'])) return null;

        return [
            'city'        => $data['name'] ?? $city,
            'temp'        => (int)round((float)$data['main']['temp']),
            'feels_like'  => (int)round((float)($data['main']['feels_like'] ?? 0)),
            'humidity'    => (int)($data['main']['humidity'] ?? 0),
            'description' => (string)($data['weather'][0]['description'] ?? ''),
            'icon'        => (string)($data['weather'][0]['icon'] ?? ''),
        ];
    }

    /**
     * نرخ ارز. آدرس از .env می‌آید چون منبع معتبر در ایران متفاوت است
     * و نمی‌خواهیم یک سرویس خاص را در کد سفت کنیم.
     * انتظار: JSON به شکل {"usd": 000, "eur": 000, ...}
     * @return array<string,mixed>|null
     */
    private function fetchCurrency(): ?array
    {
        $url = trim((string)env('CURRENCY_API_URL', ''));
        if ($url === '') return null;

        $data = $this->getJson($url);
        if (!is_array($data)) return null;

        // فقط کلیدهای عددی ساده را نگه می‌داریم تا ساختار ناشناخته وارد پلیر نشود
        $rates = [];
        foreach ($data as $code => $value) {
            if (!is_string($code) || !is_numeric($value)) continue;
            $rates[mb_strtolower(mb_substr($code, 0, 10))] = (float)$value;
            if (count($rates) >= 12) break;
        }

        return $rates ?: null;
    }

    /**
     * اوقات شرعی از Aladhan — رایگان و بدون کلید.
     * method=7 یعنی محاسبه‌ی مؤسسه ژئوفیزیک تهران، استاندارد ایران.
     * @return array<string,mixed>|null
     */
    private function fetchPrayer(string $city): ?array
    {
        $country = (string)env('PRAYER_COUNTRY', 'Iran');
        $url = 'https://api.aladhan.com/v1/timingsByCity?city=' . rawurlencode($city)
             . '&country=' . rawurlencode($country) . '&method=7';

        $data = $this->getJson($url);
        $t    = $data['data']['timings'] ?? null;
        if (!is_array($t)) return null;

        // فقط اوقات شرعی مرسوم در ایران
        return [
            'fajr'    => $this->hhmm($t['Fajr']    ?? ''),
            'sunrise' => $this->hhmm($t['Sunrise'] ?? ''),
            'dhuhr'   => $this->hhmm($t['Dhuhr']   ?? ''),
            'maghrib' => $this->hhmm($t['Maghrib'] ?? ''),
            'isha'    => $this->hhmm($t['Isha']    ?? ''),
            'date'    => $data['data']['date']['readable'] ?? date('Y-m-d'),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /** @return array<mixed>|null */
    private function getJson(string $url): ?array
    {
        if (!preg_match('#^https://#i', $url)) {
            throw new \RuntimeException('منبع باید https باشد');
        }

        $ctx = stream_context_create(['http' => [
            'timeout'       => self::HTTP_TIMEOUT,
            'header'        => "User-Agent: Hotel Media-Portal\r\n",
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) throw new \RuntimeException('اتصال برقرار نشد');

        if (isset($http_response_header)) {
            preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
            if ((int)($m[1] ?? 0) >= 400) {
                throw new \RuntimeException('پاسخ HTTP ' . ($m[1] ?? '?'));
            }
        }

        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    /** «04:52 (+0330)» → «04:52» */
    private function hhmm(string $v): string
    {
        return preg_match('/(\d{1,2}:\d{2})/', $v, $m) ? $m[1] : '';
    }
}
