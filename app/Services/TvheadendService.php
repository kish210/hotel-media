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

    /**
     * آدرس پخش برای دستگاه‌ها: از پروکسی خود برنامه (`/tv/stream/{uuid}`) می‌گذرد،
     * چون آدرس مستقیم TVHeadend روی loopback است و رمز می‌خواهد. امضا جلوی
     * حدس‌زدن uuid کانال قفل‌شده را می‌گیرد.
     */
    public static function sign(string $uuid): string
    {
        return substr(hash_hmac('sha256', 'tvh-stream:' . $uuid, (string)env('APP_KEY', 'hotel-media')), 0, 24);
    }

    public static function streamPath(string $uuid): string
    {
        return '/tv/stream/' . $uuid . '?sig=' . self::sign($uuid);
    }

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

        /* `channel/grid` نه `channel/list`.
           `channel/list` در TVHeadend 4.3 فقط فهرست انتخاب است و هر
           ورودی‌اش `{key, val}` است — نه `{uuid, name, number, icon}`.
           کد پایین `$ch['uuid']` را می‌خواند، پس همهٔ کانال‌ها را با
           `continue` رد می‌کرد و در پایان «هیچ کانالی تعریف نشده» می‌گفت،
           در حالی که ۴۵ کانال واقعی (IRIB TV1 تا TV4 و بقیه) در
           TVHeadend بود. روی سخت‌افزار واقعی دیده شد.

           تست قبلی این را پنهان می‌کرد: سرور ساختگی‌اش `uuid/name`
           برمی‌گرداند، شکلی که TVHeadend واقعی هرگز نمی‌دهد. */
        $res = $this->request(
            (string)$src['server_url'],
            '/api/channel/grid?limit=' . self::CHANNEL_LIMIT,
            (string)($src['username'] ?? ''),
            (string)($src['password'] ?? '')
        );
        if (!$res['ok']) {
            return ['ok' => false, 'imported' => 0, 'updated' => 0, 'total' => 0,
                    'message' => 'دریافت کانال‌ها ناموفق: ' . $res['error']];
        }

        $entries = json_decode($res['body'], true)['entries'] ?? [];

        /* تحمل نسخه‌ها: اگر نسخه‌ای `grid` را نداد یا شکل دیگری برگرداند،
           به `channel/list` برمی‌گردیم که `{key,val}` است (key=uuid، val=نام).
           هیچ تغییری در خود TVHeadend لازم نیست. */
        if (!$entries || !isset($entries[0]['uuid'])) {
            $alt = $this->request((string)$src['server_url'], '/api/channel/list?limit=' . self::CHANNEL_LIMIT,
                                  (string)($src['username'] ?? ''), (string)($src['password'] ?? ''));
            if ($alt['ok']) {
                $entries = [];
                foreach ((json_decode($alt['body'], true)['entries'] ?? []) as $e) {
                    if (isset($e['uuid'])) { $entries[] = $e; }
                    elseif (isset($e['key'])) { $entries[] = ['uuid' => (string)$e['key'], 'name' => (string)($e['val'] ?? '')]; }
                }
            }
        }
        if (!$entries) {
            return ['ok' => false, 'imported' => 0, 'updated' => 0, 'total' => 0,
                    'message' => 'هیچ کانالی در TVHeadend تعریف نشده — اول در خود TVHeadend اسکن کنید'];
        }

        $base     = rtrim((string)$src['server_url'], '/');
        $profile  = $src['stream_profile'] ?: 'pass';
        $imported = 0;
        $updated  = 0;

        /* رادیو را با برچسب TVHeadend می‌شناسیم. بی این، ۲۴ کانال رادیو در
           فهرست تلویزیون اتاق‌ها می‌آمدند و مهمان با زدن آن‌ها تصویر
           خالی می‌دید. کانال‌ها `tags` (uuid برچسب) دارند و نامشان از
           `channeltag/list` می‌آید؛ نام را یک‌بار می‌گیریم، نه به‌ازای هر
           کانال. اگر گرفته نشد، `$radioTags` خالی می‌ماند و هیچ کانالی
           اشتباها رادیو علامت نمی‌خورد. */
        $radioTags = [];
        $tagRes = $this->request((string)$src['server_url'], '/api/channeltag/list',
                                 (string)($src['username'] ?? ''), (string)($src['password'] ?? ''));
        if ($tagRes['ok']) {
            foreach ((json_decode($tagRes['body'], true)['entries'] ?? []) as $t) {
                if (stripos((string)($t['val'] ?? ''), 'radio') !== false) $radioTags[(string)$t['key']] = true;
            }
        }

        foreach ($entries as $ch) {
            $uuid = (string)($ch['uuid'] ?? '');
            if ($uuid === '') continue;

            /* کانال غیرفعال در TVHeadend (مثلا اپراتور آن را خاموش کرده)
               نباید وارد برنامه شود. */
            if (array_key_exists('enabled', $ch) && !$ch['enabled']) continue;

            $isRadio = 0;
            foreach ((array)($ch['tags'] ?? []) as $tg) {
                if (isset($radioTags[(string)$tg])) { $isRadio = 1; break; }
            }

            $name   = trim((string)($ch['name'] ?? '')) ?: 'بدون نام';
            $number = (int)($ch['number'] ?? 0);
            $icon   = (string)($ch['icon'] ?? '');

            $streamUrl = self::streamPath($uuid);

            // ‏imagecache داخلی TVHeadend از بیرون قابل دسترسی نیست
            $logo = null;
            if ($icon !== '' && !str_starts_with($icon, 'imagecache')) {
                $logo = str_starts_with($icon, 'http') ? $icon : $base . '/' . ltrim($icon, '/');
            }

            // TVHeadend برای کانال زمینی معمولا لوگو ندارد؛ از بستهٔ لوگوی ایرانی
            $logo ??= ChannelLogoService::find($name);

            $existing = $this->db->row(
                'SELECT id FROM iptv_channels WHERE tenant_id = ? AND tvh_uuid = ?',
                [$tenantId, $uuid]
            );

            if ($existing) {
                // آدرس multicast و شماره کانالی که اپراتور دستی داده دست‌نخورده
                // می‌ماند — وگرنه هر sync فهرست کانال تلویزیون‌ها را خراب می‌کند
                $upd = [
                    'name'        => $name,
                    'stream_url'  => $streamUrl,
                    'is_active'   => 1,
                    'is_radio'    => $isRadio,
                    'source_type' => 'tvheadend',
                    'source_id'   => $sourceId,
                ];
                // لوگویی که اپراتور گذاشته با null پاک نشود
                if ($logo !== null) { $upd['logo_url'] = $logo; }
                $this->db->update('iptv_channels', $upd, ['id' => (int)$existing['id']]);
                $updated++;
            } else {
                $this->db->insert('iptv_channels', [
                    'tenant_id'   => $tenantId,
                    'name'        => $name,
                    'stream_url'  => $streamUrl,
                    'logo_url'    => $logo,
                    'category'    => 'livetv',
                    /* گروه را همان‌جایی حل می‌کنیم که همهٔ مسیرهای دیگر —
                       این سرویس از cron هم اجرا می‌شود و کانالِ بی‌گروه
                       در فهرست مهمان زیر «سایر» می‌افتاد. */
                    'group_id'    => (new ChannelGroupService($this->db))->resolve($tenantId, 'livetv'),
                    'protocol'    => 'http',
                    'channel_no'  => $number ?: null,
                    'sort_order'  => $number,
                    'is_active'   => 1,
                    'is_radio'    => $isRadio,
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

    /**
     * فراخوانی API روی یک منبع ثبت‌شده. پارامتر آرایه‌ای به JSON تبدیل می‌شود
     * (TVHeadend `conf`/`node` را JSON می‌خواهد). همه‌چیز با GET است؛ روی
     * TVHeadend 4.3 برای create/save/scan هم همین جواب می‌دهد.
     *
     * @return array{ok:bool,data:?array,error:string}
     */
    public function call(array $src, string $path, array $params = []): array
    {
        foreach ($params as $k => $v) {
            if (is_array($v)) { $params[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
            elseif (is_bool($v)) { $params[$k] = $v ? '1' : '0'; }
        }
        $q = $params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '';
        $res = $this->request((string)$src['server_url'], '/api/' . ltrim($path, '/') . $q,
                              (string)($src['username'] ?? ''), (string)($src['password'] ?? ''));
        if (!$res['ok']) { return ['ok' => false, 'data' => null, 'error' => $res['error']]; }
        $data = json_decode($res['body'], true);
        return ['ok' => true, 'data' => is_array($data) ? $data : [], 'error' => ''];
    }

    /** @return array{ok:bool,body:string,error:string} */
    private function request(string $base, string $path, string $user, string $pass): array
    {
        $url = rtrim($base, '/') . $path;

        if (!preg_match('#^https?://#i', $url)) {
            return ['ok' => false, 'body' => '', 'error' => 'آدرس باید با http شروع شود'];
        }

        /* احراز هویت به HttpDigestClient سپرده می‌شود.
           تا پیش از این فقط Basic فرستاده می‌شد، ولی TVHeadend جدید با
           «digest: 1» نصب می‌شود و Basic را اصلا نمی‌پذیرد. نتیجه‌اش
           این بود که روی هر نصب تازه، اتصال برقرار نمی‌شد و پیام «نام
           کاربری یا رمز اشتباه است» می‌داد — در حالی که رمز درست بود.
           روی Ubuntu 24.04 تایید شد: basic ۴۰۱، digest ۲۰۰. */
        $res = (new HttpDigestClient(self::TIMEOUT))->get($url, $user, $pass);

        if ($res['ok']) {
            return ['ok' => true, 'body' => $res['body'], 'error' => ''];
        }
        if ($res['status'] === 401 || $res['status'] === 403) {
            return ['ok' => false, 'body' => $res['body'],
                    'error' => 'نام کاربری یا رمز TVHeadend پذیرفته نشد'];
        }
        if ($res['status'] === 0) {
            return ['ok' => false, 'body' => '',
                    'error' => 'اتصال به TVHeadend برقرار نشد — ' . $res['error']];
        }
        return ['ok' => false, 'body' => $res['body'], 'error' => $res['error']];
    }
}
