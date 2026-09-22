<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * News Feed Service
 * دریافت خبر از RSS و ذخیره در content_items.
 *
 * بدون این، اپراتور هتل باید هر روز خبرها را دستی وارد کند که در عمل
 * یعنی بعد از هفته‌ی اول، اخبار تلویزیون‌ها کهنه می‌ماند.
 *
 * نقشه‌راه: ۱.۹
 */
class NewsFeedService
{
    private const TIMEOUT = 20;

    /** خبر قدیمی‌تر از این تعداد روز پاک می‌شود */
    private const KEEP_DAYS = 14;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * همه‌ی منابع فعال را همگام می‌کند.
     * @return array{feeds:int,items:int,failed:int,message:string}
     */
    public function syncAll(?int $tenantId = null): array
    {
        $sql    = 'SELECT * FROM news_feeds WHERE is_active = 1';
        $params = [];
        if ($tenantId !== null) { $sql .= ' AND tenant_id = ?'; $params[] = $tenantId; }

        $feeds = $this->db->rows($sql, $params);
        if (!$feeds) return ['feeds' => 0, 'items' => 0, 'failed' => 0, 'message' => 'منبع خبری فعالی تعریف نشده'];

        $items = 0; $failed = 0;
        foreach ($feeds as $feed) {
            $res = $this->sync($feed);
            if ($res['ok']) $items += $res['count'];
            else            $failed++;
        }

        // پاک‌سازی بعد از همگام‌سازی، نه قبل — اگر منبع قطع باشد
        // دست‌کم خبرهای قبلی روی تلویزیون می‌مانند
        foreach (array_unique(array_map(static fn($f) => (int)$f['tenant_id'], $feeds)) as $tid) {
            $this->pruneOld($tid);
        }

        return [
            'feeds'   => count($feeds),
            'items'   => $items,
            'failed'  => $failed,
            'message' => "$items خبر از " . count($feeds) . ' منبع'
                       . ($failed ? "، $failed منبع ناموفق" : ''),
        ];
    }

    /**
     * یک منبع را همگام می‌کند.
     * @param array<string,mixed> $feed
     * @return array{ok:bool,count:int,message:string}
     */
    public function sync(array $feed): array
    {
        $tid = (int)$feed['tenant_id'];

        try {
            $xml = $this->fetch((string)$feed['url']);
        } catch (\Throwable $e) {
            $this->record($feed, false, $e->getMessage());
            return ['ok' => false, 'count' => 0, 'message' => $e->getMessage()];
        }

        $entries = $this->parse($xml, (int)$feed['max_items']);
        if (!$entries) {
            $this->record($feed, false, 'خبری در این منبع پیدا نشد');
            return ['ok' => false, 'count' => 0, 'message' => 'خبری در این منبع پیدا نشد'];
        }

        $saved = 0;
        foreach ($entries as $e) {
            // همان خبر دوبار وارد نشود — عنوان + منبع کلید یکتایی عملی است،
            // چون بسیاری از فیدهای فارسی guid پایدار ندارند
            $exists = $this->db->row(
                "SELECT id FROM content_items
                  WHERE tenant_id = ? AND kind = 'news' AND title = ?
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [$tid, $e['title']]
            );
            if ($exists) continue;

            $this->db->insert('content_items', [
                'tenant_id'    => $tid,
                'kind'         => 'news',
                'category'     => $feed['category'] ?: null,
                'title'        => mb_substr($e['title'], 0, 250),
                'subtitle'     => $e['subtitle'] !== null ? mb_substr($e['subtitle'], 0, 250) : null,
                'body'         => $e['body'],
                'image_url'    => $e['image'],
                'extra'        => mb_substr((string)$feed['name'], 0, 250),
                'lang'         => (string)$feed['lang'],
                'published_at' => $e['published_at'],
                'is_active'    => 1,
            ]);
            $saved++;
        }

        $this->record($feed, true, "$saved خبر تازه");

        return ['ok' => true, 'count' => $saved, 'message' => "$saved خبر تازه"];
    }

    // ══════════════════════════════════════════════════════════════
    //  پارس
    // ══════════════════════════════════════════════════════════════

    /**
     * ‏RSS 2.0 و Atom هر دو پشتیبانی می‌شوند — فیدهای فارسی هر دو را دارند.
     * @return list<array<string,mixed>>
     */
    private function parse(string $xml, int $max): array
    {
        $prev = libxml_use_internal_errors(true);
        // LIBXML_NONET جلوی واکشی موجودیت بیرونی (XXE) را می‌گیرد
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($prev);

        if ($doc === false) return [];

        $max   = max(1, min(100, $max));
        $items = [];

        // RSS 2.0
        if (isset($doc->channel->item)) {
            foreach ($doc->channel->item as $it) {
                if (count($items) >= $max) break;

                $title = $this->clean((string)($it->title ?? ''));
                if ($title === '') continue;

                $items[] = [
                    'title'        => $title,
                    'subtitle'     => null,
                    'body'         => $this->clean(strip_tags((string)($it->description ?? ''))) ?: null,
                    'image'        => $this->imageFrom($it),
                    'published_at' => $this->parseDate((string)($it->pubDate ?? '')),
                ];
            }
            return $items;
        }

        // Atom
        if (isset($doc->entry)) {
            foreach ($doc->entry as $it) {
                if (count($items) >= $max) break;

                $title = $this->clean((string)($it->title ?? ''));
                if ($title === '') continue;

                $items[] = [
                    'title'        => $title,
                    'subtitle'     => null,
                    'body'         => $this->clean(strip_tags((string)($it->summary ?? $it->content ?? ''))) ?: null,
                    'image'        => null,
                    'published_at' => $this->parseDate((string)($it->updated ?? $it->published ?? '')),
                ];
            }
        }

        return $items;
    }

    private function imageFrom(\SimpleXMLElement $item): ?string
    {
        // enclosure استاندارد RSS
        if (isset($item->enclosure['url'])) {
            $url  = (string)$item->enclosure['url'];
            $type = (string)($item->enclosure['type'] ?? '');
            if ($url !== '' && (str_starts_with($type, 'image/') || $type === '')) {
                return $this->safeUrl($url);
            }
        }

        // media:content و media:thumbnail — فیدهای خبری فارسی زیاد استفاده می‌کنند.
        // دو نکته‌ی ظریف SimpleXML اینجا مهم است:
        //  • آکولاد لازم است، چون $media->$tag['url'] را PHP به‌صورت
        //    $media->{$tag['url']} می‌خواند
        //  • بعد از children($ns)، دسترسی ['url'] هم در همان namespace
        //    جستجو می‌شود؛ ولی خودِ url بدون prefix است. پس باید از
        //    attributes() بدون آرگومان خوانده شود، وگرنه همیشه خالی است.
        $media = $item->children('http://search.yahoo.com/mrss/');
        foreach (['content', 'thumbnail'] as $tag) {
            if (!isset($media->{$tag})) continue;

            $url = (string)($media->{$tag}->attributes()['url'] ?? '');
            $u   = $url !== '' ? $this->safeUrl($url) : null;
            if ($u !== null) return $u;
        }

        // آخرین راه: اولین <img> داخل متن خبر
        $desc = (string)($item->description ?? '');
        if ($desc !== '' && preg_match('#<img[^>]+src=["\']([^"\']+)#i', $desc, $m)) {
            return $this->safeUrl($m[1]);
        }

        return null;
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    private function fetch(string $url): string
    {
        if (!preg_match('#^https?://#i', $url)) {
            throw new \RuntimeException('آدرس فید باید با http یا https شروع شود');
        }

        $ctx = stream_context_create(['http' => [
            'timeout'       => self::TIMEOUT,
            'header'        => "User-Agent: HotelMedia-News\r\nAccept: application/rss+xml, application/xml, text/xml\r\n",
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) throw new \RuntimeException('اتصال به منبع خبر برقرار نشد');

        if (isset($http_response_header)) {
            preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
            $code = (int)($m[1] ?? 0);
            if ($code >= 400) throw new \RuntimeException("منبع خبر خطای HTTP $code داد");
        }

        return $body;
    }

    private function record(array $feed, bool $ok, string $msg): void
    {
        $this->db->update('news_feeds', [
            'last_sync_at'  => date('Y-m-d H:i:s'),
            'last_sync_msg' => mb_substr(($ok ? '✓ ' : '✗ ') . $msg, 0, 300),
        ], ['id' => (int)$feed['id']]);
    }

    private function pruneOld(int $tenantId): int
    {
        return $this->db->query(
            "DELETE FROM content_items
              WHERE tenant_id = ? AND kind = 'news'
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$tenantId, self::KEEP_DAYS]
        )->rowCount();
    }

    private function parseDate(string $v): string
    {
        $v = trim($v);
        if ($v === '') return date('Y-m-d H:i:s');

        $ts = strtotime($v);
        // تاریخ آینده معمولا اشتباه منطقه‌ی زمانی فید است، نه خبر فردا
        if (!$ts || $ts > time() + 86400) return date('Y-m-d H:i:s');

        return date('Y-m-d H:i:s', $ts);
    }

    private function clean(string $v): string
    {
        return trim(html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** فقط http(s) — جلوی javascript: و data: که روی تلویزیون اجرا می‌شوند */
    private function safeUrl(string $v): ?string
    {
        $v = trim($v);
        return preg_match('#^https?://#i', $v) ? mb_substr($v, 0, 500) : null;
    }
}
