<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * دریافت خودکار پروازهای فرودگاه بین‌المللی کیش.
 *
 * صفحه‌ی kishairport.ir/flight-information یک اپ Angular است و HTMLش
 * خالی است — هر تلاشی برای خواندن جدول از HTML بی‌نتیجه می‌ماند. داده
 * از اندپوینت JSON خودِ سایت می‌آید که همان صفحه هم از آن می‌خواند:
 *
 *   GET /api/flight-info  →  data.fa.departure[] و data.fa.arrival[]
 *   GET /api/fa           →  data.weather (Open-Meteo برای مختصات کیش)
 *
 * یعنی دمای هوا هم از همین منبع درمی‌آید و نیازی به کلید API جداگانه
 * یا سرویس هواشناسی دیگری نیست.
 *
 * نکته‌ی مهم درباره‌ی هتل: فهرست فرودگاه پروازهای چند روز را با هم
 * می‌دهد (از جمله دیروز). تابلوی هتل فقط پنجره‌ی نزدیک را می‌خواهد،
 * پس فیلتر زمانی در خواندن (SignageContentService) انجام می‌شود و
 * اینجا همه چیز ذخیره می‌شود تا اگر بعدا پنجره عوض شد داده از دست
 * نرفته باشد.
 */
class KishAirportFetcher
{
    public const SOURCE = 'kishairport';

    private const FLIGHTS_URL = 'https://kishairport.ir/api/flight-info';
    private const INFO_URL    = 'https://kishairport.ir/api/fa';
    private const LOGO_BASE   = 'https://kishairport.ir/assets/airlines/square-logos/';
    private const TIMEOUT     = 20;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * یک دور کامل: پروازها + دمای هوا.
     *
     * @return array{ok:bool,departures:int,arrivals:int,weather:bool,message:string}
     */
    public function sync(int $tenantId = 1, ?int $locationId = null): array
    {
        $dep = $arr = 0;
        $weatherOk = false;
        $notes = [];

        try {
            $json = $this->fetchJson(self::FLIGHTS_URL);
            $fa   = $json['data']['fa'] ?? [];

            $dep = $this->store($tenantId, $locationId, 'departure', $fa['departure'] ?? []);
            $arr = $this->store($tenantId, $locationId, 'arrival',   $fa['arrival']   ?? []);
        } catch (\Throwable $e) {
            /* شکستِ پرواز نباید مانع به‌روزرسانی دما شود و برعکس —
               اگر یکی از دو اندپوینت پایین باشد، آن یکی باید کار کند. */
            $notes[] = 'پرواز: ' . $e->getMessage();
        }

        try {
            $weatherOk = $this->syncWeather($tenantId);
        } catch (\Throwable $e) {
            $notes[] = 'هوا: ' . $e->getMessage();
        }

        $ok = ($dep + $arr) > 0 || $weatherOk;

        return [
            'ok'         => $ok,
            'departures' => $dep,
            'arrivals'   => $arr,
            'weather'    => $weatherOk,
            'message'    => $notes
                ? implode(' | ', $notes)
                : "خروجی: $dep، ورودی: $arr" . ($weatherOk ? '، دما به‌روز شد' : ''),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    //  پروازها
    // ══════════════════════════════════════════════════════════════

    /**
     * @param array<int,array<string,mixed>> $list
     */
    private function store(int $tenantId, ?int $locationId, string $direction, array $list): int
    {
        $n = 0;

        foreach ($list as $f) {
            $ref = (string)($f['flightId'] ?? '');
            if ($ref === '') continue;

            $scheduled = $this->toDateTime((string)($f['date'] ?? ''));
            if ($scheduled === null) continue;

            /* realTime فقط ساعت است («۲۱:۲۰») و تاریخ ندارد. با تاریخ
               همان پرواز ترکیب می‌شود؛ اگر ساعتِ واقعی خیلی کوچک‌تر از
               برنامه باشد یعنی از نیمه‌شب رد شده و یک روز جلو می‌رود. */
            $estimated = $this->realTimeToDateTime(
                $scheduled, isset($f['realTime']) ? (string)$f['realTime'] : ''
            );

            $statusText = trim((string)($f['description'] ?? ''));
            $status     = $this->mapStatus($statusText, $scheduled, $estimated);

            $iata = strtoupper(trim((string)($f['airlineIata'] ?? '')));

            $this->db->query(
                "INSERT INTO flights
                   (tenant_id, location_id, direction, flight_number, airline,
                    airline_logo, city, scheduled_at, estimated_at, status,
                    status_text, is_active, source, source_ref)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?,?)
                 ON DUPLICATE KEY UPDATE
                   direction     = VALUES(direction),
                   flight_number = VALUES(flight_number),
                   airline       = VALUES(airline),
                   airline_logo  = VALUES(airline_logo),
                   city          = VALUES(city),
                   scheduled_at  = VALUES(scheduled_at),
                   estimated_at  = VALUES(estimated_at),
                   status        = VALUES(status),
                   status_text   = VALUES(status_text),
                   location_id   = VALUES(location_id),
                   is_active     = 1",
                [
                    $tenantId,
                    $locationId,
                    $direction,
                    (string)($f['flightNumber'] ?? ''),
                    (string)($f['airline'] ?? ''),
                    $iata !== '' ? self::LOGO_BASE . $iata . '.png' : null,
                    (string)($f['city'] ?? ''),
                    $scheduled->format('Y-m-d H:i:s'),
                    $estimated?->format('Y-m-d H:i:s'),
                    $status,
                    $statusText !== '' ? mb_substr($statusText, 0, 160) : null,
                    self::SOURCE,
                    $ref,
                ]
            );

            $n++;
        }

        return $n;
    }

    /**
     * وضعیت فارسیِ فرودگاه را به ENUM ما نگاشت می‌کند.
     *
     * ترتیب بررسی مهم است: «لغو» باید قبل از هر چیز دیگر دیده شود،
     * چون توضیحِ یک پروازِ لغوشده ممکن است هنوز ساعت هم داشته باشد.
     */
    private function mapStatus(
        string $text,
        \DateTimeImmutable $scheduled,
        ?\DateTimeImmutable $estimated
    ): string {
        if ($text !== '') {
            if (mb_strpos($text, 'لغو') !== false)    return 'cancelled';
            if (mb_strpos($text, 'نشست') !== false)   return 'arrived';
            /* «پایان تحویل بار» یعنی پرواز خیلی وقت است نشسته — بدون
               این، رایج‌ترین وضعیتِ پروازهای ورودی «طبق برنامه» نشان
               داده می‌شد و مهمان منتظر هواپیمایی می‌ماند که رسیده. */
            if (mb_strpos($text, 'تحویل بار') !== false) return 'arrived';
            if (mb_strpos($text, 'برخاست') !== false) return 'departed';
            if (mb_strpos($text, 'پرواز کرد') !== false) return 'departed';
            if (mb_strpos($text, 'خروج') !== false)   return 'departed';
            if (mb_strpos($text, 'سوار') !== false)   return 'boarding';
            if (mb_strpos($text, 'پذیرش') !== false)  return 'boarding';
            if (mb_strpos($text, 'تاخیر') !== false)  return 'delayed';
            if (mb_strpos($text, 'تأخیر') !== false)  return 'delayed';
        }

        /* اگر متنی نبود ولی ساعت واقعی از برنامه جلوتر است، خودش
           تاخیر است — پنج دقیقه ارفاق تا نوسان ثبت، «تاخیر» نشود. */
        if ($estimated !== null
            && $estimated->getTimestamp() - $scheduled->getTimestamp() > 300) {
            return 'delayed';
        }

        return 'scheduled';
    }

    private function toDateTime(string $iso): ?\DateTimeImmutable
    {
        if ($iso === '') return null;

        try {
            /* ورودی با آفست +03:30 می‌آید. به منطقه‌ی زمانی سرور
               تبدیل می‌شود تا مقایسه با NOW() در SQL درست باشد. */
            return (new \DateTimeImmutable($iso))
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        } catch (\Throwable) {
            return null;
        }
    }

    private function realTimeToDateTime(\DateTimeImmutable $scheduled, string $hm): ?\DateTimeImmutable
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', $hm, $m)) return null;

        $est = $scheduled->setTime((int)$m[1], (int)$m[2], 0);

        /* پروازِ ۲۳:۵۰ که ۰۰:۱۵ بلند شده، بدون این تصحیح ۲۳ ساعت
           «زودتر» به نظر می‌رسد و تابلو تاخیر منفی نشان می‌دهد. */
        if ($est->getTimestamp() - $scheduled->getTimestamp() < -43200) {
            $est = $est->modify('+1 day');
        }

        return $est;
    }

    // ══════════════════════════════════════════════════════════════
    //  دمای هوا
    // ══════════════════════════════════════════════════════════════

    /**
     * دمای ساعت جاری کیش را از همان سایت می‌گیرد و در تنظیمات مستاجر
     * نگه می‌دارد. چرا آنجا: تلویزیون نباید مستقیم به اینترنت وصل شود
     * (و در بیشتر هتل‌ها اصلا نمی‌تواند)، پس سرور هتل واسطه می‌شود.
     */
    private function syncWeather(int $tenantId): bool
    {
        $json = $this->fetchJson(self::INFO_URL);
        $w    = $json['data']['weather'] ?? null;
        if (!$w) return false;

        $times = $w['hourly']['time'] ?? [];
        $temps = $w['hourly']['temperature_2m'] ?? [];
        $codes = $w['hourly']['weather_code'] ?? [];
        if (!$times || !$temps) return false;

        /* نزدیک‌ترین ساعت به «الان» — نه اولین ردیف. فهرست از ساعت ۰۰
           شروع می‌شود و اولین ردیف یعنی دمای نیمه‌شب. */
        $now  = time();
        $best = 0;
        $diff = PHP_INT_MAX;

        foreach ($times as $i => $t) {
            $ts = strtotime((string)$t);
            if ($ts === false) continue;
            $d = abs($ts - $now);
            if ($d < $diff) { $diff = $d; $best = $i; }
        }

        $payload = [
            'temp'       => isset($temps[$best]) ? (float)$temps[$best] : null,
            'code'       => isset($codes[$best]) ? (int)$codes[$best] : null,
            'label'      => $this->wmoLabel(isset($codes[$best]) ? (int)$codes[$best] : -1),
            'city'       => 'کیش',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($payload['temp'] === null) return false;

        $row = $this->db->row('SELECT settings FROM tenants WHERE id=?', [$tenantId]);
        $cur = json_decode((string)($row['settings'] ?? ''), true);
        if (!is_array($cur)) $cur = [];

        $cur['weather'] = $payload;

        $this->db->update(
            'tenants',
            ['settings' => json_encode($cur, JSON_UNESCAPED_UNICODE)],
            ['id' => $tenantId]
        );

        return true;
    }

    /** کد WMO که Open-Meteo می‌دهد را به یک عبارت فارسی کوتاه تبدیل می‌کند */
    private function wmoLabel(int $code): string
    {
        return match (true) {
            $code === 0                    => 'صاف',
            $code >= 1  && $code <= 3      => 'نیمه‌ابری',
            $code >= 45 && $code <= 48     => 'مه',
            $code >= 51 && $code <= 57     => 'نم‌نم باران',
            $code >= 61 && $code <= 67     => 'باران',
            $code >= 71 && $code <= 77     => 'برف',
            $code >= 80 && $code <= 82     => 'رگبار',
            $code >= 95                    => 'رعدوبرق',
            default                        => '',
        };
    }

    // ══════════════════════════════════════════════════════════════

    /** @return array<string,mixed> */
    private function fetchJson(string $url): array
    {
        $ctx = stream_context_create(['http' => [
            'timeout'       => self::TIMEOUT,
            'header'        => "User-Agent: HotelMedia-Flights\r\nAccept: application/json\r\n",
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) throw new \RuntimeException('اتصال به سایت فرودگاه برقرار نشد');

        if (isset($http_response_header)) {
            preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
            $code = (int)($m[1] ?? 0);
            if ($code >= 400) throw new \RuntimeException("سایت فرودگاه خطای HTTP $code داد");
        }

        $json = json_decode($body, true);
        if (!is_array($json)) throw new \RuntimeException('پاسخ سایت فرودگاه JSON معتبر نبود');

        return $json;
    }
}
