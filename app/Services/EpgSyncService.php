<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * EPG Sync Service
 * دریافت راهنمای برنامه‌ها از TVHeadend یا XMLTV و ذخیره در epg_programs.
 * فاز ۲ نقشه‌راه — docs/TODO.md (۱.۲)
 */
class EpgSyncService
{
    /** حداکثر رویدادی که در یک بار sync پردازش می‌شود */
    private const MAX_EVENTS = 50000;

    /** رویدادهای قدیمی‌تر از این تعداد روز پاک می‌شوند */
    private const KEEP_PAST_DAYS = 2;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * همه منابع فعال یک tenant (یا همه tenant ها) را همگام می‌کند.
     * @return array<int,array{source:string,ok:bool,count:int,message:string}>
     */
    public function syncAll(?int $tenantId = null): array
    {
        $sql    = 'SELECT * FROM epg_sources WHERE is_active = 1';
        $params = [];
        if ($tenantId !== null) { $sql .= ' AND tenant_id = ?'; $params[] = $tenantId; }

        $results = [];
        foreach ($this->db->rows($sql, $params) as $source) {
            $results[] = $this->sync($source);
        }

        return $results;
    }

    /**
     * یک منبع را همگام می‌کند.
     * @param array<string,mixed> $source ردیف epg_sources
     * @return array{source:string,ok:bool,count:int,message:string}
     */
    public function sync(array $source): array
    {
        $name = (string)$source['name'];

        try {
            $events = match ($source['source_type']) {
                'tvheadend'  => $this->fetchFromTvheadend($source),
                'xmltv_url'  => $this->fetchFromXmltv($this->httpGet((string)$source['url'])),
                'xmltv_file' => $this->fetchFromXmltv($this->readLocal((string)$source['url'])),
                default      => throw new \RuntimeException('نوع منبع پشتیبانی نمی‌شود: ' . $source['source_type']),
            };
        } catch (\Throwable $e) {
            $this->recordSync($source, false, 0, $e->getMessage());
            return ['source' => $name, 'ok' => false, 'count' => 0, 'message' => $e->getMessage()];
        }

        if (!$events) {
            $msg = 'هیچ برنامه‌ای دریافت نشد';
            $this->recordSync($source, false, 0, $msg);
            return ['source' => $name, 'ok' => false, 'count' => 0, 'message' => $msg];
        }

        $saved = $this->store((int)$source['tenant_id'], (int)$source['id'], $events);
        $this->pruneOld((int)$source['tenant_id']);
        $this->linkChannels((int)$source['tenant_id']);

        $msg = "$saved برنامه ذخیره شد";
        $this->recordSync($source, true, $saved, $msg);

        return ['source' => $name, 'ok' => true, 'count' => $saved, 'message' => $msg];
    }

    // ══════════════════════════════════════════════════════════════
    //  منابع
    // ══════════════════════════════════════════════════════════════

    /**
     * TVHeadend — /api/epg/events/grid
     * @return list<array<string,mixed>>
     */
    private function fetchFromTvheadend(array $source): array
    {
        $tvh = $this->db->row(
            'SELECT * FROM tvheadend_sources WHERE id = ? AND tenant_id = ?',
            [(int)$source['tvh_source_id'], (int)$source['tenant_id']]
        );
        if (!$tvh) throw new \RuntimeException('سرور TVHeadend مرتبط یافت نشد');

        $base  = rtrim((string)$tvh['server_url'], '/');
        $until = time() + ((int)$source['days_ahead'] * 86400);

        $events = [];
        $offset = 0;
        $limit  = 1000;

        // ‏TVHeadend صفحه‌بندی می‌کند؛ تا رسیدن به افق زمانی جلو می‌رویم
        while (count($events) < self::MAX_EVENTS) {
            $url  = "$base/api/epg/events/grid?start=$offset&limit=$limit";
            $body = $this->httpGet($url, (string)($tvh['username'] ?? ''), (string)($tvh['password'] ?? ''));
            $json = json_decode($body, true);

            $entries = $json['entries'] ?? [];
            if (!$entries) break;

            foreach ($entries as $e) {
                $start = (int)($e['start'] ?? 0);
                $stop  = (int)($e['stop']  ?? 0);
                if ($start <= 0 || $stop <= $start) continue;
                if ($start > $until) continue;

                $events[] = [
                    'channel_key' => (string)($e['channelUuid'] ?? ''),
                    'title'       => (string)($e['title'] ?? 'بدون عنوان'),
                    'subtitle'    => $this->nullable($e['subtitle'] ?? null),
                    'description' => $this->nullable($e['description'] ?? $e['summary'] ?? null),
                    'category'    => $this->firstCategory($e['genre'] ?? null),
                    'lang'        => 'fa',
                    'starts_at'   => date('Y-m-d H:i:s', $start),
                    'ends_at'     => date('Y-m-d H:i:s', $stop),
                    'season'      => $this->positiveOrNull($e['seasonNumber']  ?? null),
                    'episode'     => $this->positiveOrNull($e['episodeNumber'] ?? null),
                    'image'       => $this->nullable($e['image'] ?? null),
                    'rating'      => $this->nullable($e['ageRating'] ?? null),
                    'external_id' => $this->nullable($e['eventId'] ?? null),
                ];
            }

            $total  = (int)($json['totalCount'] ?? 0);
            $offset += $limit;
            if ($offset >= $total) break;
        }

        return $events;
    }

    /**
     * XMLTV — استاندارد <programme start="..." stop="..." channel="...">
     * @return list<array<string,mixed>>
     */
    private function fetchFromXmltv(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        // LIBXML_NONET: از XXE و واکشی موجودیت بیرونی جلوگیری می‌کند
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($prev);

        if ($doc === false) throw new \RuntimeException('فایل XMLTV معتبر نیست');

        $events = [];
        foreach ($doc->programme as $p) {
            if (count($events) >= self::MAX_EVENTS) break;

            $start = $this->parseXmltvTime((string)$p['start']);
            $stop  = $this->parseXmltvTime((string)$p['stop']);
            if (!$start || !$stop || $stop <= $start) continue;

            $events[] = [
                'channel_key' => (string)$p['channel'],
                'title'       => trim((string)($p->title ?? '')) ?: 'بدون عنوان',
                'subtitle'    => $this->nullable(trim((string)($p->{'sub-title'} ?? ''))),
                'description' => $this->nullable(trim((string)($p->desc ?? ''))),
                'category'    => $this->nullable(trim((string)($p->category ?? ''))),
                'lang'        => (string)($p->title['lang'] ?? 'fa') ?: 'fa',
                'starts_at'   => date('Y-m-d H:i:s', $start),
                'ends_at'     => date('Y-m-d H:i:s', $stop),
                'season'      => null,
                'episode'     => $this->positiveOrNull((string)($p->{'episode-num'} ?? '')),
                'image'       => $this->nullable((string)($p->icon['src'] ?? '')),
                'rating'      => $this->nullable(trim((string)($p->rating->value ?? ''))),
                'external_id' => null,
            ];
        }

        return $events;
    }

    // ══════════════════════════════════════════════════════════════
    //  ذخیره‌سازی
    // ══════════════════════════════════════════════════════════════

    /** @param list<array<string,mixed>> $events */
    private function store(int $tenantId, int $sourceId, array $events): int
    {
        // UNIQUE روی (tenant, channel_key, starts_at) است، پس sync دوباره
        // برنامه‌ها را به‌روز می‌کند نه تکراری.
        $sql = 'INSERT INTO epg_programs
                (tenant_id, channel_key, title, subtitle, description, category, lang,
                 starts_at, ends_at, season, episode, image, rating, source_id, external_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    title       = VALUES(title),
                    subtitle    = VALUES(subtitle),
                    description = VALUES(description),
                    category    = VALUES(category),
                    ends_at     = VALUES(ends_at),
                    season      = VALUES(season),
                    episode     = VALUES(episode),
                    image       = VALUES(image),
                    rating      = VALUES(rating),
                    source_id   = VALUES(source_id)';

        $saved = 0;
        $this->db->beginTransaction();
        try {
            foreach ($events as $e) {
                if ($e['channel_key'] === '') continue;

                $this->db->query($sql, [
                    $tenantId,
                    mb_substr($e['channel_key'], 0, 120),
                    mb_substr($e['title'], 0, 300),
                    $e['subtitle'] !== null ? mb_substr($e['subtitle'], 0, 300) : null,
                    $e['description'],
                    $e['category'] !== null ? mb_substr($e['category'], 0, 80) : null,
                    mb_substr($e['lang'], 0, 10),
                    $e['starts_at'],
                    $e['ends_at'],
                    $e['season'],
                    $e['episode'],
                    $e['image'] !== null ? mb_substr($e['image'], 0, 500) : null,
                    $e['rating'] !== null ? mb_substr($e['rating'], 0, 20) : null,
                    $sourceId,
                    $e['external_id'] !== null ? mb_substr((string)$e['external_id'], 0, 120) : null,
                ]);
                $saved++;
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw new \RuntimeException('ذخیره EPG ناموفق بود: ' . $e->getMessage(), 0, $e);
        }

        return $saved;
    }

    /**
     * تطبیق channel_key با کانال داخلی.
     * اولویت: نگاشت دستی ← epg_key ← tvh_uuid ← نام یکسان
     */
    public function linkChannels(int $tenantId): int
    {
        $queries = [
            // ۱) نگاشت دستی
            'UPDATE epg_programs p
               JOIN epg_channel_map m
                 ON m.tenant_id = p.tenant_id AND m.channel_key = p.channel_key
                SET p.channel_id = m.channel_id
              WHERE p.tenant_id = ? AND (p.channel_id IS NULL OR p.channel_id <> m.channel_id)',

            // ۲) کلید صریح روی کانال (ستون epg_id که از قبل در 004 وجود دارد)
            'UPDATE epg_programs p
               JOIN iptv_channels c
                 ON c.tenant_id = p.tenant_id AND c.epg_id = p.channel_key
                SET p.channel_id = c.id
              WHERE p.tenant_id = ? AND p.channel_id IS NULL',

            // ۳) uuid تی‌وی‌هدند
            'UPDATE epg_programs p
               JOIN iptv_channels c
                 ON c.tenant_id = p.tenant_id AND c.tvh_uuid = p.channel_key
                SET p.channel_id = c.id
              WHERE p.tenant_id = ? AND p.channel_id IS NULL',

            // ۴) نام یکسان (آخرین راه)
            'UPDATE epg_programs p
               JOIN iptv_channels c
                 ON c.tenant_id = p.tenant_id AND c.name = p.channel_key
                SET p.channel_id = c.id
              WHERE p.tenant_id = ? AND p.channel_id IS NULL',
        ];

        $linked = 0;
        foreach ($queries as $q) {
            $linked += $this->db->query($q, [$tenantId])->rowCount();
        }

        return $linked;
    }

    /** رویدادهای گذشته را پاک می‌کند تا جدول رشد بی‌پایان نکند */
    private function pruneOld(int $tenantId): int
    {
        return $this->db->query(
            'DELETE FROM epg_programs WHERE tenant_id = ? AND ends_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$tenantId, self::KEEP_PAST_DAYS]
        )->rowCount();
    }

    private function recordSync(array $source, bool $ok, int $count, string $msg): void
    {
        $this->db->update('epg_sources', [
            'last_sync_at'  => date('Y-m-d H:i:s'),
            'last_sync_msg' => mb_substr(($ok ? '✓ ' : '✗ ') . $msg, 0, 300),
            'last_count'    => $ok ? $count : 0,
        ], ['id' => (int)$source['id']]);
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /**
     * دریافت از منبع، با احراز هویت خودکار.
     *
     * تا پیش از این فقط سرآیند Basic ساخته و فرستاده می‌شد. TVHeadend
     * جدید با «digest: 1» نصب می‌شود و Basic را اصلا نمی‌پذیرد، پس
     * همگام‌سازی EPG روی هر نصب تازه با «رمز اشتباه است» شکست می‌خورد
     * در حالی که رمز درست بود. HttpDigestClient هر دو روش را می‌فهمد.
     */
    private function httpGet(string $url, string $user = '', string $pass = ''): string
    {
        $res = (new HttpDigestClient(30))->get($url, $user, $pass);

        if ($res['ok']) return $res['body'];

        if ($res['status'] === 401 || $res['status'] === 403) {
            throw new \RuntimeException('نام کاربری یا رمز منبع پذیرفته نشد');
        }
        if ($res['status'] === 0) {
            throw new \RuntimeException('اتصال به منبع برقرار نشد: ' . $res['error']);
        }
        throw new \RuntimeException($res['error']);
    }

    private function readLocal(string $path): string
    {
        // فقط از داخل storage — جلوگیری از خواندن فایل دلخواه سیستم
        $base = realpath(STORAGE_PATH);
        $real = realpath($path) ?: realpath(STORAGE_PATH . '/' . ltrim($path, '/\\'));

        if (!$real || !$base || !str_starts_with($real, $base)) {
            throw new \RuntimeException('فایل XMLTV باید داخل پوشه storage باشد');
        }
        if (!is_file($real)) throw new \RuntimeException('فایل XMLTV یافت نشد');

        return (string)file_get_contents($real);
    }

    /** XMLTV: «20260921183000 +0330» */
    private function parseXmltvTime(string $v): ?int
    {
        $v = trim($v);
        if ($v === '') return null;

        if (preg_match('/^(\d{14})(?:\s*([+-]\d{4}))?$/', $v, $m)) {
            $ts = \DateTime::createFromFormat('YmdHis', $m[1], new \DateTimeZone($m[2] ?? 'UTC'));
            return $ts ? $ts->getTimestamp() : null;
        }

        $ts = strtotime($v);
        return $ts ?: null;
    }

    private function firstCategory(mixed $genre): ?string
    {
        if (is_array($genre) && $genre) return $this->nullable((string)reset($genre));
        return $this->nullable(is_scalar($genre) ? (string)$genre : null);
    }

    private function nullable(mixed $v): ?string
    {
        $v = trim((string)($v ?? ''));
        return $v === '' ? null : $v;
    }

    private function positiveOrNull(mixed $v): ?int
    {
        // «S02E05» یا «1 . 4 .» در XMLTV — اولین عدد را برمی‌داریم
        if (is_string($v) && preg_match('/(\d+)/', $v, $m)) $v = $m[1];
        $n = (int)$v;
        return $n > 0 ? $n : null;
    }
}
