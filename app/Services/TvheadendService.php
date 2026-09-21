<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * TVHeadend Service
 * اتصال، بررسی سلامت، همگام‌سازی کانال‌ها و ثبت خودکار منبع.
 *
 * ‏TVHeadend در استک ما همان نقشی را دارد که کاماسیستم با «سهند» می‌فروشد:
 * سیگنال ماهواره و زمینی (DVB-S/S2/T/T2/C) را می‌گیرد و روی IP می‌دهد.
 * هدف این سرویس این است که بعد از نصب سرور، اپراتور **هیچ کار دستی**
 * برای وصل کردن آن نداشته باشد.
 */
class TvheadendService
{
    private const TIMEOUT = 15;

    /** کانال‌های دریافتی در یک بار — TVHeadend صفحه‌بندی می‌کند */
    private const CHANNEL_LIMIT = 2000;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // ══════════════════════════════════════════════════════════════
    //  ثبت خودکار
    // ══════════════════════════════════════════════════════════════

    /**
     * ‏TVHeadend محلی را پیدا و ثبت می‌کند، و یک منبع EPG هم برایش می‌سازد.
     * بی‌خطر برای اجرای مجدد: منبع موجود به‌روز می‌شود، تکراری ساخته نمی‌شود.
     *
     * @return array{ok:bool,source_id:?int,channels:int,message:string}
     */
    public function autoRegister(
        int $tenantId,
        string $url = 'http://127.0.0.1:9981',
        string $user = '',
        string $pass = '',
        bool $syncNow = true
    ): array {
        $url = rtrim(trim($url), '/');

        $probe = $this->probe($url, $user, $pass);
        if (!$probe['ok']) {
            return ['ok' => false, 'source_id' => null, 'channels' => 0, 'message' => $probe['message']];
        }

        $existing = $this->db->row(
            'SELECT * FROM tvheadend_sources WHERE tenant_id = ? AND server_url = ?',
            [$tenantId, $url]
        );

        if ($existing) {
            $this->db->update('tvheadend_sources', [
                'username'  => $user ?: null,
                'password'  => $pass ?: null,
                'is_active' => 1,
            ], ['id' => (int)$existing['id']]);
            $sourceId = (int)$existing['id'];
        } else {
            $sourceId = (int)$this->db->insert('tvheadend_sources', [
                'tenant_id'      => $tenantId,
                'name'           => 'TVHeadend (' . parse_url($url, PHP_URL_HOST) . ')',
                'server_url'     => $url,
                'username'       => $user ?: null,
                'password'       => $pass ?: null,
                'stream_profile' => 'pass',
                'is_active'      => 1,
            ]);
        }

        // منبع EPG هم خودکار ساخته شود — وگرنه اپراتور کانال دارد ولی
        // راهنمای برنامه خالی است و فکر می‌کند EPG کار نمی‌کند
        $epgExists = $this->db->row(
            "SELECT id FROM epg_sources
              WHERE tenant_id = ? AND source_type = 'tvheadend' AND tvh_source_id = ?",
            [$tenantId, $sourceId]
        );
        if (!$epgExists) {
            $this->db->insert('epg_sources', [
                'tenant_id'     => $tenantId,
                'name'          => 'EPG از TVHeadend',
                'source_type'   => 'tvheadend',
                'tvh_source_id' => $sourceId,
                'days_ahead'    => 7,
                'is_active'     => 1,
            ]);
        }

        $channels = 0;
        if ($syncNow) {
            $sync     = $this->syncChannels($tenantId, $sourceId);
            $channels = $sync['imported'] + $sync['updated'];
        }

        return [
            'ok'        => true,
            'source_id' => $sourceId,
            'channels'  => $channels,
            'message'   => 'TVHeadend ثبت شد'
                         . ($channels ? " — $channels کانال همگام شد" : '')
                         . ($epgExists ? '' : ' و منبع EPG ساخته شد'),
        ];
    }

    /**
     * آیا سروری اینجا هست و پاسخ می‌دهد؟
     * @return array{ok:bool,version:?string,message:string}
     */
    public function probe(string $url, string $user = '', string $pass = ''): array
    {
        $res = $this->request($url, '/api/serverinfo', $user, $pass);

        if (!$res['ok']) {
            return ['ok' => false, 'version' => null, 'message' => $res['error']];
        }

        $info = json_decode($res['body'], true);
        if (!is_array($info)) {
            return ['ok' => false, 'version' => null, 'message' => 'پاسخ سرور قابل خواندن نیست'];
        }

        return [
            'ok'      => true,
            'version' => (string)($info['sw_version'] ?? $info['version'] ?? '?'),
            'message' => 'TVHeadend نسخه ' . ($info['sw_version'] ?? '?') . ' پاسخ داد',
        ];
    }

    // ══════════════════════════════════════════════════════════════
    //  همگام‌سازی کانال
    // ══════════════════════════════════════════════════════════════

    /**
     * @return array{ok:bool,imported:int,updated:int,total:int,message:string}
     */
    public function syncChannels(int $tenantId, int $sourceId): array
    {
        $src = $this->db->row(
            'SELECT * FROM tvheadend_sources WHERE id = ? AND tenant_id = ?',
            [$sourceId, $tenantId]
        );
        if (!$src) {
            return ['ok' => false, 'imported' => 0, 'updated' => 0, 'total' => 0, 'message' => 'منبع یافت نشد'];
        }

        $res = $this->request(
            (string)$src['server_url'],
            '/api/channel/list?limit=' . self::CHANNEL_LIMIT,
            (string)($src['username'] ?? ''),
            (string)($src['password'] ?? '')
        );
        if (!$res['ok']) {
            return ['ok' => false, 'imported' => 0, 'updated' => 0, 'total' => 0,
                    'message' => 'دریافت کانال‌ها ناموفق: ' . $res['error']];
        }

        $entries = json_decode($res['body'], true)['entries'] ?? [];
        if (!$entries) {
            return ['ok' => false, 'imported' => 0, 'updated' => 0, 'total' => 0,
                    'message' => 'هیچ کانالی در TVHeadend تعریف نشده — اول در خود TVHeadend اسکن کنید'];
        }

        $base     = rtrim((string)$src['server_url'], '/');
        $profile  = $src['stream_profile'] ?: 'pass';
        $imported = 0;
        $updated  = 0;

        foreach ($entries as $ch) {
            $uuid = (string)($ch['uuid'] ?? '');
            if ($uuid === '') continue;

            $name   = trim((string)($ch['name'] ?? '')) ?: 'بدون نام';
            $number = (int)($ch['number'] ?? 0);
            $icon   = (string)($ch['icon'] ?? '');

            $streamUrl = "$base/stream/channel/$uuid?profile=" . rawurlencode($profile);

            // ‏imagecache داخلی TVHeadend از بیرون قابل دسترسی نیست
            $logo = null;
            if ($icon !== '' && !str_starts_with($icon, 'imagecache')) {
                $logo = str_starts_with($icon, 'http') ? $icon : $base . '/' . ltrim($icon, '/');
            }

            $existing = $this->db->row(
                'SELECT id FROM iptv_channels WHERE tenant_id = ? AND tvh_uuid = ?',
                [$tenantId, $uuid]
            );

            if ($existing) {
                // آدرس multicast و شماره کانالی که اپراتور دستی داده دست‌نخورده
                // می‌ماند — وگرنه هر sync فهرست کانال تلویزیون‌ها را خراب می‌کند
                $this->db->update('iptv_channels', [
                    'name'        => $name,
                    'stream_url'  => $streamUrl,
                    'logo_url'    => $logo,
                    'is_active'   => 1,
                    'source_type' => 'tvheadend',
                    'source_id'   => $sourceId,
                ], ['id' => (int)$existing['id']]);
                $updated++;
            } else {
                $this->db->insert('iptv_channels', [
                    'tenant_id'   => $tenantId,
                    'name'        => $name,
                    'stream_url'  => $streamUrl,
                    'logo_url'    => $logo,
                    'category'    => 'livetv',
                    'protocol'    => 'http',
                    'channel_no'  => $number ?: null,
                    'sort_order'  => $number,
                    'is_active'   => 1,
                    'source_type' => 'tvheadend',
                    'source_id'   => $sourceId,
                    'tvh_uuid'    => $uuid,
                    // ‏uuid کلید تطبیق EPG هم هست
                    'epg_id'      => $uuid,
                ]);
                $imported++;
            }
        }

        $this->db->update('tvheadend_sources', [
            'last_sync'  => date('Y-m-d H:i:s'),
            'sync_count' => (int)$src['sync_count'] + $imported + $updated,
        ], ['id' => $sourceId]);

        return [
            'ok'       => true,
            'imported' => $imported,
            'updated'  => $updated,
            'total'    => count($entries),
            'message'  => "$imported کانال جدید، $updated به‌روزرسانی",
        ];
    }

    /**
     * بررسی سلامت همه‌ی منابع — برای پایش و عیب‌یابی.
     * @return list<array<string,mixed>>
     */
    public function health(?int $tenantId = null): array
    {
        $sql    = 'SELECT * FROM tvheadend_sources WHERE is_active = 1';
        $params = [];
        if ($tenantId !== null) { $sql .= ' AND tenant_id = ?'; $params[] = $tenantId; }

        $out = [];
        foreach ($this->db->rows($sql, $params) as $src) {
            $probe = $this->probe(
                (string)$src['server_url'],
                (string)($src['username'] ?? ''),
                (string)($src['password'] ?? '')
            );

            $channels = (int)$this->db->value(
                'SELECT COUNT(*) FROM iptv_channels WHERE source_id = ? AND is_active = 1',
                [(int)$src['id']]
            );

            $out[] = [
                'id'        => (int)$src['id'],
                'name'      => $src['name'],
                'url'       => $src['server_url'],
                'online'    => $probe['ok'],
                'version'   => $probe['version'],
                'message'   => $probe['message'],
                'channels'  => $channels,
                'last_sync' => $src['last_sync'],
            ];
        }

        return $out;
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /** @return array{ok:bool,body:string,error:string} */
    private function request(string $base, string $path, string $user, string $pass): array
    {
        $url = rtrim($base, '/') . $path;

        if (!preg_match('#^https?://#i', $url)) {
            return ['ok' => false, 'body' => '', 'error' => 'آدرس باید با http شروع شود'];
        }

        $header = "Accept: application/json\r\nUser-Agent: HotelMedia\r\n";
        if ($user !== '') {
            $header .= 'Authorization: Basic ' . base64_encode("$user:$pass") . "\r\n";
        }

        $ctx = stream_context_create(['http' => [
            'timeout'       => self::TIMEOUT,
            'header'        => $header,
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return ['ok' => false, 'body' => '', 'error' => 'اتصال به TVHeadend برقرار نشد'];
        }

        $code = 0;
        if (isset($http_response_header)) {
            preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
            $code = (int)($m[1] ?? 0);
        }

        if ($code === 401) return ['ok' => false, 'body' => $body, 'error' => 'نام کاربری یا رمز TVHeadend اشتباه است'];
        if ($code >= 400)  return ['ok' => false, 'body' => $body, 'error' => "TVHeadend خطای HTTP $code داد"];

        return ['ok' => true, 'body' => $body, 'error' => ''];
    }
}
