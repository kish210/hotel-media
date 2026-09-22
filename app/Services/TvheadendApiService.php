<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * سطح کامل API تی‌وی‌هدند.
 *
 * TvheadendService کار راه‌اندازی و همگام‌سازی کانال‌ها را می‌کند؛ این
 * کلاس بقیه‌ی توانایی‌های تی‌وی‌هدند را در دسترس می‌گذارد که تا امروز
 * استفاده نمی‌شد:
 *
 *   پخش multicast   کانال مستقیم روی گروه ۲۳۹.x — بار صفر روی سرور
 *   ضبط (DVR)       پایه‌ی catch-up و ضبط برنامه
 *   تایم‌شیفت       مکث و برگشت روی پخش زنده
 *   تگ کانال        دسته‌بندی که در تی‌وی‌هدند تعریف شده
 *   وضعیت           تیونرهای فعال و اشتراک‌های در حال پخش
 *
 * ── چرا تی‌وی‌هدند و نه dvblast یا MuMuDVB ─────────────────────────
 *
 * تا اکتبر ۲۰۲۲ تی‌وی‌هدند خروجی multicast نداشت و برای هتل باید
 * dvblast یا MuMuDVB کنارش می‌گذاشتید. حالا خودش دارد
 * (‏/udpstream/start) و دیگر دو نرم‌افزار لازم نیست.
 *
 * مهم‌تر: dvblast و MuMuDVB فقط جریان را پخش می‌کنند — نه EPG دارند،
 * نه ضبط، نه تایم‌شیفت. با آن‌ها catch-up و PVR از صفر باید ساخته
 * می‌شد. تی‌وی‌هدند هر سه را دارد و یک تیونر هم نمی‌تواند هم‌زمان
 * دست دو برنامه باشد.
 *
 * ── محدودیتی که باید بدانید ────────────────────────────────────────
 * هر جریان multicast یک اشتراک روی تیونر می‌گیرد. تعداد کانال‌های
 * هم‌زمانِ multicast از تعداد ترانسپوندرهایی که کارت‌های شما می‌توانند
 * قفل کنند بیشتر نمی‌شود — نه از تعداد تلویزیون‌ها. این همان دلیلی است
 * که multicast برای هتل مناسب است: ۳۰۰ اتاق روی یک کانال، یک اشتراک.
 */
final class TvheadendApiService
{
    private const TIMEOUT = 12;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // ══════════════════════════════════════════════════════════════
    //  پخش multicast
    // ══════════════════════════════════════════════════════════════

    /**
     * شروع پخش یک کانال روی گروه multicast.
     *
     * از این لحظه تی‌وی‌هدند بسته‌های MPEG-TS را روی آن آدرس می‌ریزد و
     * تلویزیون‌ها مستقیم از شبکه می‌خوانند. سرور در مسیر پخش نیست.
     *
     * شرط شبکه: روی VLAN تلویزیون‌ها باید IGMP snooping روشن و دقیقا
     * یک querier فعال باشد، وگرنه سوییچ یا هیچ‌چیز پخش نمی‌کند یا به
     * همه‌ی پورت‌ها flood می‌کند.
     *
     * @param string $uuid آی‌دی کانال در تی‌وی‌هدند (ستون tvh_uuid)
     * @return array{ok:bool,message:string,url:string}
     */
    public function startMulticast(array $src, string $uuid, string $group, int $port): array
    {
        $bad = $this->validateGroup($group, $port);
        if ($bad !== null) return ['ok' => false, 'message' => $bad, 'url' => ''];

        $res = $this->call($src, '/udpstream/start/channel/' . rawurlencode($uuid)
            . '?address=' . rawurlencode($group) . '&port=' . $port);

        if (!$res['ok']) return ['ok' => false, 'message' => $res['error'], 'url' => ''];

        return [
            'ok'      => true,
            'message' => 'پخش روی ' . $group . ':' . $port . ' شروع شد',
            'url'     => 'udp://@' . $group . ':' . $port,
        ];
    }

    /** @return array{ok:bool,message:string} */
    public function stopMulticast(array $src, string $uuid, string $group, int $port): array
    {
        $res = $this->call($src, '/udpstream/stop/channel/' . rawurlencode($uuid)
            . '?address=' . rawurlencode($group) . '&port=' . $port);

        return $res['ok']
            ? ['ok' => true,  'message' => 'پخش متوقف شد']
            : ['ok' => false, 'message' => $res['error']];
    }

    /**
     * همه‌ی کانال‌هایی که آدرس multicast دارند را روشن کن.
     *
     * بعد از ریستارت سرور یا تی‌وی‌هدند، جریان‌ها از بین می‌روند و
     * تلویزیون‌ها تصویر سیاه می‌بینند بدون اینکه کسی بفهمد چرا. این
     * متد از کرون صدا می‌شود.
     *
     * @return array{ok:bool,started:int,failed:int,errors:list<string>}
     */
    public function startAll(int $tenantId): array
    {
        $out = ['ok' => true, 'started' => 0, 'failed' => 0, 'errors' => []];

        $src = $this->source($tenantId);
        if (!$src) {
            return ['ok' => false, 'started' => 0, 'failed' => 0,
                    'errors' => ['منبع تی‌وی‌هدند تنظیم نشده است']];
        }

        $rows = $this->db->rows(
            "SELECT id, name, tvh_uuid, multicast_url
               FROM iptv_channels
              WHERE tenant_id = ?
                AND is_active = 1
                AND tvh_uuid IS NOT NULL AND tvh_uuid <> ''
                AND multicast_url IS NOT NULL AND multicast_url <> ''
                AND delivery IN ('multicast', 'both')",
            [$tenantId]
        );

        foreach ($rows as $r) {
            $parsed = $this->parseUdp((string)$r['multicast_url']);
            if ($parsed === null) {
                $out['failed']++;
                $out['errors'][] = $r['name'] . ': آدرس multicast نامعتبر';
                continue;
            }

            $res = $this->startMulticast($src, (string)$r['tvh_uuid'], $parsed[0], $parsed[1]);
            if ($res['ok']) {
                $out['started']++;
            } else {
                $out['failed']++;
                $out['errors'][] = $r['name'] . ': ' . $res['message'];
            }
        }

        $out['ok'] = $out['failed'] === 0;
        return $out;
    }

    /**
     * بررسی آدرس گروه.
     *
     * محدوده‌ی ۲۲۴.۰.۰.x رزرو شده و برای پخش استفاده نمی‌شود؛ اگر
     * اپراتور اشتباهی آن را بگذارد سوییچ ترافیک را به همه‌ی پورت‌ها
     * flood می‌کند و کل شبکه‌ی هتل کند می‌شود.
     */
    private function validateGroup(string $group, int $port): ?string
    {
        if (!filter_var($group, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return 'آدرس گروه معتبر نیست';
        }

        $first = (int)explode('.', $group)[0];
        if ($first < 224 || $first > 239) {
            return 'آدرس باید در محدوده‌ی multicast باشد (۲۲۴ تا ۲۳۹)';
        }
        if (str_starts_with($group, '224.0.0.')) {
            return 'محدوده‌ی ۲۲۴.۰.۰.x رزرو شده است؛ از ۲۳۹.x.x.x استفاده کنید';
        }
        if ($port < 1024 || $port > 65535) {
            return 'پورت باید بین ۱۰۲۴ تا ۶۵۵۳۵ باشد';
        }
        /* RTP پورت زوج می‌خواهد و فرد را برای RTCP نگه می‌دارد */
        if ($port % 2 !== 0) {
            return 'پورت باید زوج باشد (فرد برای RTCP رزرو است)';
        }
        return null;
    }

    /** «udp://@239.1.1.5:5000» → ['239.1.1.5', 5000] */
    private function parseUdp(string $url): ?array
    {
        if (!preg_match('#^(?:udp|rtp)://@?([\d.]+):(\d+)#', $url, $m)) return null;
        return [$m[1], (int)$m[2]];
    }

    // ══════════════════════════════════════════════════════════════
    //  ضبط — پایه‌ی catch-up و PVR
    // ══════════════════════════════════════════════════════════════

    /**
     * ضبط یک بازه‌ی زمانی از یک کانال.
     *
     * برای catch-up: هر برنامه‌ی EPG را می‌شود از قبل صف کرد. برای
     * ضبط دستی هم همین کافی است.
     *
     * @return array{ok:bool,message:string,uuid:string}
     */
    public function record(array $src, string $channelUuid, int $startTs, int $stopTs,
                           string $title, string $comment = ''): array
    {
        if ($stopTs <= $startTs) {
            return ['ok' => false, 'message' => 'زمان پایان باید بعد از شروع باشد', 'uuid' => ''];
        }

        $conf = json_encode([
            'start'          => $startTs,
            'stop'           => $stopTs,
            'channel'        => $channelUuid,
            'title'          => ['fa' => $title],
            'comment'        => $comment,
            /* بدون این، تی‌وی‌هدند با تنظیم پیش‌فرض ضبط می‌کند که
               ممکن است پاک‌کردن خودکار داشته باشد */
            'enabled'        => true,
        ], JSON_UNESCAPED_UNICODE);

        $res = $this->call($src, '/api/dvr/entry/create?conf=' . rawurlencode((string)$conf));
        if (!$res['ok']) return ['ok' => false, 'message' => $res['error'], 'uuid' => ''];

        $d = json_decode($res['body'], true);
        return [
            'ok'      => true,
            'message' => 'ضبط ثبت شد',
            'uuid'    => (string)($d['uuid'] ?? ''),
        ];
    }

    /**
     * ضبط یک برنامه از روی EPG — دقیق‌تر از بازه‌ی دستی، چون
     * تی‌وی‌هدند تغییر زمان پخش را دنبال می‌کند.
     *
     * @return array{ok:bool,message:string,uuid:string}
     */
    public function recordEvent(array $src, int $eventId): array
    {
        $res = $this->call($src, '/api/dvr/entry/create_by_event?event_id=' . $eventId);
        if (!$res['ok']) return ['ok' => false, 'message' => $res['error'], 'uuid' => ''];

        $d = json_decode($res['body'], true);
        return ['ok' => true, 'message' => 'ضبط برنامه ثبت شد',
                'uuid' => (string)($d['uuid'] ?? '')];
    }

    /**
     * فهرست ضبط‌ها.
     *
     * @param string $which upcoming | finished | failed | removed
     * @return array{ok:bool,message:string,items:list<array<string,mixed>>}
     */
    public function recordings(array $src, string $which = 'finished', int $limit = 100): array
    {
        $valid = ['upcoming', 'finished', 'failed', 'removed'];
        if (!in_array($which, $valid, true)) $which = 'finished';

        $res = $this->call($src, '/api/dvr/entry/grid_' . $which . '?limit=' . max(1, min(500, $limit)));
        if (!$res['ok']) return ['ok' => false, 'message' => $res['error'], 'items' => []];

        $d = json_decode($res['body'], true);
        return ['ok' => true, 'message' => '', 'items' => $d['entries'] ?? []];
    }

    /** @return array{ok:bool,message:string} */
    public function deleteRecording(array $src, string $uuid): array
    {
        $res = $this->call($src, '/api/dvr/entry/remove?uuid=' . rawurlencode($uuid));
        return $res['ok']
            ? ['ok' => true,  'message' => 'ضبط حذف شد']
            : ['ok' => false, 'message' => $res['error']];
    }

    /** @return array{ok:bool,message:string} */
    public function stopRecording(array $src, string $uuid): array
    {
        /* stop یعنی «همین‌جا تمامش کن و نگه دار»؛ cancel یعنی دور
           بینداز. برای مهمان اولی درست است. */
        $res = $this->call($src, '/api/dvr/entry/stop?uuid=' . rawurlencode($uuid));
        return $res['ok']
            ? ['ok' => true,  'message' => 'ضبط متوقف شد']
            : ['ok' => false, 'message' => $res['error']];
    }

    /**
     * آدرس پخش یک فایل ضبط‌شده.
     *
     * توجه: این یونی‌کست است و از سرور تی‌وی‌هدند می‌آید. برای
     * catch-up که چند مهمان هم‌زمان می‌بینند مشکلی ندارد — بر خلاف
     * پخش زنده، هر کسی جای متفاوتی از فایل است و multicast معنا ندارد.
     */
    public function recordingUrl(array $src, string $uuid): string
    {
        return rtrim((string)$src['url'], '/') . '/dvrfile/' . rawurlencode($uuid);
    }

    // ══════════════════════════════════════════════════════════════
    //  تایم‌شیفت — مکث و برگشت روی پخش زنده
    // ══════════════════════════════════════════════════════════════

    /**
     * وضعیت تایم‌شیفت.
     *
     * تی‌وی‌هدند باید با تایم‌شیفت فعال و فضای دیسک کافی تنظیم شده
     * باشد، وگرنه دکمه‌ی مکث روی پخش زنده کار نمی‌کند و مهمان فکر
     * می‌کند تلویزیون خراب است.
     *
     * @return array{ok:bool,message:string,enabled:bool,config:array<string,mixed>}
     */
    public function timeshiftStatus(array $src): array
    {
        $res = $this->call($src, '/api/timeshift/config/load');
        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['error'], 'enabled' => false, 'config' => []];
        }

        $d   = json_decode($res['body'], true);
        $cfg = $d['entries'][0]['params'] ?? [];

        $enabled = false;
        foreach ($cfg as $p) {
            if (($p['id'] ?? '') === 'enabled') { $enabled = !empty($p['value']); break; }
        }

        return ['ok' => true, 'message' => '', 'enabled' => $enabled, 'config' => $cfg];
    }

    // ══════════════════════════════════════════════════════════════
    //  تگ کانال — دسته‌بندی که در تی‌وی‌هدند تعریف شده
    // ══════════════════════════════════════════════════════════════

    /**
     * تگ‌ها معمولا در تی‌وی‌هدند از قبل مرتب شده‌اند (خبری، ورزشی،
     * کودک). بدون این، اپراتور هتل باید همان دسته‌بندی را دوباره
     * دستی بسازد.
     *
     * @return array{ok:bool,message:string,tags:list<array<string,mixed>>}
     */
    public function channelTags(array $src): array
    {
        $res = $this->call($src, '/api/channeltag/grid?limit=500');
        if (!$res['ok']) return ['ok' => false, 'message' => $res['error'], 'tags' => []];

        $d = json_decode($res['body'], true);
        return ['ok' => true, 'message' => '', 'tags' => $d['entries'] ?? []];
    }

    // ══════════════════════════════════════════════════════════════
    //  وضعیت زنده
    // ══════════════════════════════════════════════════════════════

    /**
     * تیونرها و اشتراک‌های در حال پخش.
     *
     * مهم‌ترین عددی که اپراتور هتل لازم دارد: چند تیونر آزاد مانده.
     * وقتی همه مشغول‌اند، کانال بعدی پخش نمی‌شود و علتش از بیرون
     * شبیه خرابی شبکه به نظر می‌رسد.
     *
     * @return array{ok:bool,message:string,inputs:list<array<string,mixed>>,
     *               subscriptions:list<array<string,mixed>>}
     */
    public function liveStatus(array $src): array
    {
        $out = ['ok' => false, 'message' => '', 'inputs' => [], 'subscriptions' => []];

        $in = $this->call($src, '/api/status/inputs');
        if (!$in['ok']) { $out['message'] = $in['error']; return $out; }

        $sub = $this->call($src, '/api/status/subscriptions');
        if (!$sub['ok']) { $out['message'] = $sub['error']; return $out; }

        $di = json_decode($in['body'], true);
        $ds = json_decode($sub['body'], true);

        $out['ok']            = true;
        $out['inputs']        = $di['entries'] ?? [];
        $out['subscriptions'] = $ds['entries'] ?? [];
        return $out;
    }

    // ══════════════════════════════════════════════════════════════
    //  ابزار
    // ══════════════════════════════════════════════════════════════

    /**
     * منبع فعال تی‌وی‌هدند این مستاجر.
     *
     * @return array{url:string,username:string,password:string}|null
     */
    public function source(int $tenantId): ?array
    {
        $row = $this->db->row(
            'SELECT server_url, username, password
               FROM tvheadend_sources
              WHERE tenant_id = ? AND is_active = 1
              ORDER BY id LIMIT 1',
            [$tenantId]
        );
        if (!$row) return null;

        return [
            'url'      => (string)$row['server_url'],
            'username' => (string)($row['username'] ?? ''),
            'password' => (string)($row['password'] ?? ''),
        ];
    }

    /**
     * درخواست به تی‌وی‌هدند.
     *
     * @param array{url:string,username:string,password:string} $src
     * @return array{ok:bool,body:string,error:string}
     */
    private function call(array $src, string $path): array
    {
        $url = rtrim($src['url'], '/') . $path;

        if (!preg_match('#^https?://#i', $url)) {
            return ['ok' => false, 'body' => '', 'error' => 'آدرس تی‌وی‌هدند باید با http شروع شود'];
        }

        $header = "Accept: application/json\r\nUser-Agent: HotelMedia\r\n";
        if (($src['username'] ?? '') !== '') {
            $header .= 'Authorization: Basic '
                     . base64_encode($src['username'] . ':' . ($src['password'] ?? '')) . "\r\n";
        }

        $ctx = stream_context_create(['http' => [
            'timeout'       => self::TIMEOUT,
            'header'        => $header,
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return ['ok' => false, 'body' => '', 'error' => 'اتصال به تی‌وی‌هدند برقرار نشد'];
        }

        $code = 0;
        if (isset($http_response_header)) {
            preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
            $code = (int)($m[1] ?? 0);
        }

        if ($code === 401) {
            return ['ok' => false, 'body' => $body,
                    'error' => 'نام کاربری یا رمز تی‌وی‌هدند اشتباه است'];
        }
        if ($code === 404) {
            /* ‏udpstream از نسخه‌ی اکتبر ۲۰۲۲ اضافه شده؛ روی نسخه‌ی
               قدیمی‌تر این مسیر اصلا وجود ندارد و پیام باید همین را
               بگوید نه «یافت نشد». */
            return ['ok' => false, 'body' => $body,
                    'error' => 'این قابلیت در نسخه‌ی تی‌وی‌هدند شما نیست — به‌روزرسانی کنید'];
        }
        if ($code >= 400) {
            return ['ok' => false, 'body' => $body, 'error' => "تی‌وی‌هدند خطای HTTP $code داد"];
        }

        return ['ok' => true, 'body' => (string)$body, 'error' => ''];
    }
}
