<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\EpgSyncService;

/**
 * EPG Controller
 * راهنمای الکترونیکی برنامه‌ها — مدیریت منابع، جدول پخش، «الان و بعدی»
 * فاز ۲ نقشه‌راه — docs/TODO.md (۱.۲)
 */
class EpgController extends Controller
{
    private const SOURCE_TYPES = ['tvheadend', 'xmltv_url', 'xmltv_file'];

    /** حداکثر بازه‌ای که یک درخواست grid می‌تواند بخواهد (ساعت) */
    private const MAX_GRID_HOURS = 48;

    // ══════════════════════════════════════════════════════════════
    //  منابع EPG (محافظت‌شده)
    // ══════════════════════════════════════════════════════════════

    public function sources(Request $req): void
    {
        $tid = Auth::tenantId();

        Response::success($this->db->rows(
            'SELECT s.*, t.name AS tvh_name,
                    (SELECT COUNT(*) FROM epg_programs p
                      WHERE p.source_id = s.id AND p.ends_at >= NOW()) AS upcoming
               FROM epg_sources s
               LEFT JOIN tvheadend_sources t ON t.id = s.tvh_source_id
              WHERE s.tenant_id = ?
              ORDER BY s.id',
            [$tid]
        ));
    }

    public function storeSource(Request $req): void
    {
        $tid  = Auth::tenantId();
        $data = $req->json() ?: [];

        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') { Response::error('نام منبع الزامی است', 422); return; }

        $type = $data['source_type'] ?? 'tvheadend';
        if (!in_array($type, self::SOURCE_TYPES, true)) {
            Response::error('نوع منبع نامعتبر است', 422); return;
        }

        $tvhId = null;
        $url   = null;

        if ($type === 'tvheadend') {
            $tvhId = (int)($data['tvh_source_id'] ?? 0);
            if (!$this->db->exists('tvheadend_sources', ['id' => $tvhId, 'tenant_id' => $tid])) {
                Response::error('سرور TVHeadend انتخابی معتبر نیست', 422); return;
            }
        } else {
            $url = trim((string)($data['url'] ?? ''));
            if ($url === '') { Response::error('آدرس یا مسیر فایل XMLTV الزامی است', 422); return; }
            if ($type === 'xmltv_url' && !preg_match('#^https?://#i', $url)) {
                Response::error('آدرس باید با http یا https شروع شود', 422); return;
            }
        }

        $id = $this->db->insert('epg_sources', [
            'tenant_id'     => $tid,
            'name'          => $name,
            'source_type'   => $type,
            'tvh_source_id' => $tvhId,
            'url'           => $url,
            'days_ahead'    => max(1, min(14, (int)($data['days_ahead'] ?? 7))),
            'is_active'     => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
        ]);

        $this->log('epg_source.create', 'epg_source', (int)$id, [], ['name' => $name]);
        Response::success(['id' => (int)$id], 'منبع EPG ثبت شد', 201);
    }

    public function destroySource(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        if (!$this->db->exists('epg_sources', ['id' => $id, 'tenant_id' => $tid])) {
            Response::notFound('منبع یافت نشد'); return;
        }

        $this->db->query('DELETE FROM epg_programs WHERE source_id = ? AND tenant_id = ?', [$id, $tid]);
        $this->db->delete('epg_sources', ['id' => $id, 'tenant_id' => $tid]);

        $this->log('epg_source.delete', 'epg_source', $id);
        Response::success(null, 'منبع و برنامه‌های آن حذف شد');
    }

    /** POST /epg/sources/{id}/sync — همگام‌سازی دستی */
    public function syncSource(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        $source = $this->db->row('SELECT * FROM epg_sources WHERE id=? AND tenant_id=?', [$id, $tid]);
        if (!$source) { Response::notFound('منبع یافت نشد'); return; }

        // sync ممکن است چند ده ثانیه طول بکشد
        set_time_limit(180);

        $result = (new EpgSyncService($this->db))->sync($source);

        $this->log('epg_source.sync', 'epg_source', $id, [], ['count' => $result['count']]);

        if (!$result['ok']) { Response::error($result['message'], 502); return; }
        Response::success($result, $result['message']);
    }

    // ══════════════════════════════════════════════════════════════
    //  جدول پخش
    // ══════════════════════════════════════════════════════════════

    /**
     * GET /epg/grid — جدول زمانی برای چند کانال
     * پارامترها: from, hours, channel_id (اختیاری، چندتایی با کاما)
     */
    public function grid(Request $req): void
    {
        $tid   = Auth::tenantId();
        $from  = $this->parseTime($req->get('from')) ?? date('Y-m-d H:i:s');
        $hours = max(1, min(self::MAX_GRID_HOURS, (int)$req->get('hours', 6)));
        $to    = date('Y-m-d H:i:s', strtotime($from) + $hours * 3600);

        $sql = "SELECT p.id, p.channel_id, p.title, p.subtitle, p.category,
                       p.starts_at, p.ends_at, p.image,
                       TIMESTAMPDIFF(MINUTE, p.starts_at, p.ends_at) AS minutes,
                       c.name AS channel_name, c.logo_url, c.sort_order
                  FROM epg_programs p
                  JOIN iptv_channels c ON c.id = p.channel_id
                 WHERE p.tenant_id = ?
                   AND p.ends_at   > ?
                   AND p.starts_at < ?";
        $sqlParams = [$tid, $from, $to];

        if ($ids = $this->parseIdList($req->get('channel_id'))) {
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND p.channel_id IN ($ph)";
            $sqlParams = array_merge($sqlParams, $ids);
        }

        $sql .= ' ORDER BY c.sort_order, c.name, p.starts_at';

        // گروه‌بندی بر اساس کانال — همان شکلی که رابط جدول EPG می‌خواهد
        $byChannel = [];
        foreach ($this->db->rows($sql, $sqlParams) as $row) {
            $cid = (int)$row['channel_id'];
            if (!isset($byChannel[$cid])) {
                $byChannel[$cid] = [
                    'channel_id'     => $cid,
                    'channel_name'   => $row['channel_name'],
                    'sort_order'     => $row['sort_order'],
                    'logo_url'       => $row['logo_url'],
                    'programs'       => [],
                ];
            }
            unset($row['channel_name'], $row['logo_url'], $row['sort_order']);
            $byChannel[$cid]['programs'][] = $row;
        }

        Response::success([
            'from'     => $from,
            'to'       => $to,
            'channels' => array_values($byChannel),
        ]);
    }

    /** GET /epg/channel/{id} — برنامه‌های یک کانال */
    public function channel(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $cid = (int)($params['id'] ?? 0);

        if (!$this->db->exists('iptv_channels', ['id' => $cid, 'tenant_id' => $tid])) {
            Response::notFound('کانال یافت نشد'); return;
        }

        $days = max(1, min(14, (int)$req->get('days', 2)));

        Response::success($this->db->rows(
            'SELECT id, title, subtitle, description, category, starts_at, ends_at,
                    season, episode, image, rating,
                    TIMESTAMPDIFF(MINUTE, starts_at, ends_at) AS minutes
               FROM epg_programs
              WHERE tenant_id = ? AND channel_id = ?
                AND ends_at   >= NOW()
                AND starts_at <= DATE_ADD(NOW(), INTERVAL ? DAY)
              ORDER BY starts_at',
            [$tid, $cid, $days]
        ));
    }

    /** GET /epg/now — «الان پخش / بعدی» برای همه کانال‌ها */
    public function now(Request $req): void
    {
        Response::success($this->nowNext(Auth::tenantId(), $this->parseIdList($req->get('channel_id'))));
    }

    /**
     * GET /api/v1/player/epg/{code} — نسخه عمومی برای تلویزیون اتاق.
     * هویت با کد صفحه‌نمایش؛ tenant از روی خود صفحه گرفته می‌شود.
     */
    public function playerNow(Request $req, array $params): void
    {
        $screen = $this->db->row(
            'SELECT tenant_id, iptv_channel_id FROM screens WHERE code = ?',
            [(string)($params['code'] ?? '')]
        );
        if (!$screen) { Response::notFound('صفحه‌نمایش یافت نشد'); return; }

        $only = null;
        if ($req->get('current_only') && $screen['iptv_channel_id']) {
            $only = [(int)$screen['iptv_channel_id']];
        }

        Response::success($this->nowNext((int)$screen['tenant_id'], $only));
    }

    /**
     * GET /api/v1/guest/{code}/epg/{channel}?date=Y-m-d[&t=token]
     * برنامه‌های یک روز یک کانال برای راهنمای تلویزیون اتاق، با اینکه
     * هر برنامه قابل پخش دوباره (catch-up) یا ضبط (NPVR) هست یا نه.
     *
     * همان قاعده‌ی فهرست کانال: کانال بالاتر از سطح اتاق دیده نمی‌شود و
     * کانال قفل‌شده بدون توکن قفل والدین جوابی نمی‌دهد — وگرنه راهنما
     * راه دور زدن قفل بود.
     */
    public function guestDay(Request $req, array $params): void
    {
        $ctx = $this->db->row(
            'SELECT s.tenant_id, r.id AS room_id, r.access_level, r.parental_enabled, r.parental_pin
               FROM screens s LEFT JOIN iptv_rooms r ON r.id = s.iptv_room_id
              WHERE s.code = ?',
            [(string)($params['code'] ?? '')]
        );
        if (!$ctx) { Response::notFound('صفحه‌نمایش یافت نشد'); return; }
        $tid = (int)$ctx['tenant_id'];

        $ch = $this->db->row(
            'SELECT id, name, access_level, is_adult, tvh_uuid, catchup_enabled, catchup_window_hours
               FROM iptv_channels WHERE id = ? AND tenant_id = ? AND is_active = 1',
            [(int)($params['channel'] ?? 0), $tid]
        );
        if (!$ch || (int)$ch['access_level'] > (int)($ctx['access_level'] ?? 0)) {
            Response::notFound('کانال یافت نشد'); return;
        }

        $locked = (int)$ch['is_adult'] === 1 && (int)($ctx['parental_enabled'] ?? 0) === 1 && !empty($ctx['parental_pin']);
        if ($locked) {
            $t = (string)$req->get('t', '');
            $acc = new \App\Services\ChannelAccessService($this->db);
            if ($t === '' || !$ctx['room_id'] || !$acc->verifyToken($tid, (int)$ctx['room_id'], $t)) {
                Response::error('این کانال قفل است', 403); return;
            }
        }

        $date = (string)$req->get('date', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { Response::error('تاریخ نامعتبر است', 422); return; }

        $rows = $this->db->rows(
            'SELECT id, external_id, title, subtitle, description, category, starts_at, ends_at
               FROM epg_programs
              WHERE tenant_id = ? AND channel_id = ? AND ends_at > ? AND starts_at < DATE_ADD(?, INTERVAL 1 DAY)
              ORDER BY starts_at',
            [$tid, (int)$ch['id'], $date . ' 00:00:00', $date . ' 00:00:00']
        );

        $now      = time();
        $window   = max(0, (int)$ch['catchup_window_hours']) * 3600;
        $hasTvh   = trim((string)$ch['tvh_uuid']) !== '';
        foreach ($rows as &$p) {
            $s = strtotime((string)$p['starts_at']); $e = strtotime((string)$p['ends_at']);
            $p['state'] = $e <= $now ? 'past' : ($s <= $now ? 'now' : 'future');
            /* پخش دوباره فقط برنامه‌ی تمام‌شده، در پنجره‌ی نگهداری، و
               فقط وقتی شناسه‌ی رویداد TVHeadend داریم */
            $p['can_catchup'] = $p['state'] === 'past' && (int)$ch['catchup_enabled'] === 1 && $hasTvh
                             && ctype_digit((string)$p['external_id']) && $e >= $now - $window;
            $p['can_record']  = $p['state'] === 'future' && $hasTvh && !empty($ctx['room_id']);
        }
        unset($p);

        Response::success(['channel' => ['id' => (int)$ch['id'], 'name' => $ch['name']], 'programs' => $rows]);
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /**
     * برای هر کانال، برنامه‌ی در حال پخش و برنامه‌ی بعدی.
     * @param list<int>|null $channelIds
     * @return list<array<string,mixed>>
     */
    private function nowNext(int $tenantId, ?array $channelIds = null): array
    {
        $where  = 'c.tenant_id = ? AND c.is_active = 1';
        $params = [$tenantId];

        if ($channelIds) {
            $ph     = implode(',', array_fill(0, count($channelIds), '?'));
            $where .= " AND c.id IN ($ph)";
            $params = array_merge($params, $channelIds);
        }

        $channels = $this->db->rows(
            /* نام مستعار c لازم است: $where با c. ساخته می‌شود. بدونش این
               کوئری همیشه خطا می‌داد و «الان/بعدی» پنل و تلویزیون ۵۰۰ بود. */
            "SELECT c.id, c.name, c.logo_url, c.sort_order FROM iptv_channels c
              WHERE $where ORDER BY c.sort_order, c.name",
            $params
        );
        if (!$channels) return [];

        $ids = array_map(static fn($c) => (int)$c['id'], $channels);
        $ph  = implode(',', array_fill(0, count($ids), '?'));

        // در حال پخش
        $current = [];
        foreach ($this->db->rows(
            "SELECT channel_id, title, subtitle, description, category, starts_at, ends_at, image,
                    TIMESTAMPDIFF(MINUTE, starts_at, ends_at)  AS minutes,
                    TIMESTAMPDIFF(SECOND, starts_at, NOW())    AS elapsed_sec
               FROM epg_programs
              WHERE tenant_id = ? AND channel_id IN ($ph)
                AND starts_at <= NOW() AND ends_at > NOW()",
            array_merge([$tenantId], $ids)
        ) as $row) {
            $current[(int)$row['channel_id']] = $row;
        }

        // بعدی: اولین برنامه‌ی هر کانال بعد از الان
        $next = [];
        foreach ($this->db->rows(
            "SELECT p.channel_id, p.title, p.subtitle, p.starts_at, p.ends_at
               FROM epg_programs p
               JOIN (SELECT channel_id, MIN(starts_at) AS s
                       FROM epg_programs
                      WHERE tenant_id = ? AND channel_id IN ($ph) AND starts_at > NOW()
                      GROUP BY channel_id) m
                 ON m.channel_id = p.channel_id AND m.s = p.starts_at
              WHERE p.tenant_id = ?",
            array_merge([$tenantId], $ids, [$tenantId])
        ) as $row) {
            $next[(int)$row['channel_id']] = $row;
        }

        $out = [];
        foreach ($channels as $c) {
            $cid = (int)$c['id'];
            $cur = $current[$cid] ?? null;

            // درصد پیشرفت برنامه — برای نوار پیشرفت در پلیر
            $progress = null;
            if ($cur && (int)$cur['minutes'] > 0) {
                $progress = (int)round(((int)$cur['elapsed_sec'] / ((int)$cur['minutes'] * 60)) * 100);
                $progress = max(0, min(100, $progress));
            }

            $out[] = [
                'channel_id'     => $cid,
                'channel_name'   => $c['name'],
                'sort_order'     => $c['sort_order'],
                'logo_url'       => $c['logo_url'],
                'now'            => $cur,
                'next'           => $next[$cid] ?? null,
                'progress'       => $progress,
            ];
        }

        return $out;
    }

    /** @return list<int> */
    private function parseIdList(mixed $raw): array
    {
        $raw = trim((string)($raw ?? ''));
        if ($raw === '') return [];

        $ids = array_filter(array_map('intval', explode(',', $raw)), static fn($n) => $n > 0);
        return array_values(array_slice(array_unique($ids), 0, 200));
    }

    private function parseTime(mixed $v): ?string
    {
        $v = trim((string)($v ?? ''));
        if ($v === '') return null;
        $ts = strtotime($v);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
