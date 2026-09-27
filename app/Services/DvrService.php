<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * DVR Service — ضبط (PVR)، ضبط شخصی مهمان (NPVR)، و Catch-up.
 *
 * زیرساخت پخش‌کننده‌اش TVHeadend است و کارِ سنگین (ضبط، تایم‌شیفت،
 * autorec) را TvheadendApiService انجام می‌دهد. این سرویس فقط
 * هماهنگ‌کننده است: نگاشت کانالِ داخلی به uuid تی‌وی‌هدند، اعمال
 * سهمیه‌ی NPVR، و نگه‌داری وضعیت در `dvr_recordings`.
 *
 * فاز ۹ نقشه‌راه — docs/TODO.md (۱.۳ Catch-up، ۱.۴ PVR، ۱.۱۳ NPVR)
 */
class DvrService
{
    /** پیش‌فرض سهمیه‌ی NPVR وقتی برای اتاق ردیفی در dvr_quotas نیست */
    public const DEFAULT_MAX_RECORDINGS = 20;
    public const DEFAULT_MAX_MINUTES    = 1200;

    private Database $db;
    private TvheadendApiService $tvh;

    public function __construct(?Database $db = null)
    {
        $this->db  = $db ?? Database::getInstance();
        $this->tvh = new TvheadendApiService($this->db);
    }

    /** منبع TVHeadend فعالِ tenant، یا null اگر تنظیم نشده */
    private function src(int $tenantId): ?array
    {
        return $this->tvh->source($tenantId);
    }

    private function channel(int $id, int $tenantId): ?array
    {
        return $this->db->row(
            "SELECT * FROM iptv_channels WHERE id=? AND tenant_id=?",
            [$id, $tenantId]
        );
    }

    // ══════════════════════════════════════════════════════════════
    //  سهمیه‌ی NPVR
    // ══════════════════════════════════════════════════════════════

    /**
     * سهمیه و مصرف فعلیِ یک اتاق.
     * @return array{max_recordings:int,max_minutes:int,used_recordings:int,used_minutes:int}
     */
    public function quota(int $tenantId, int $roomId): array
    {
        $q = $this->db->row(
            "SELECT max_recordings, max_minutes FROM dvr_quotas WHERE tenant_id=? AND room_id=?",
            [$tenantId, $roomId]
        );
        $maxRec = (int)($q['max_recordings'] ?? self::DEFAULT_MAX_RECORDINGS);
        $maxMin = (int)($q['max_minutes']    ?? self::DEFAULT_MAX_MINUTES);

        $used = $this->db->row(
            "SELECT COUNT(*) AS n,
                    COALESCE(SUM(TIMESTAMPDIFF(MINUTE, starts_at, stops_at)),0) AS mins
               FROM dvr_recordings
              WHERE tenant_id=? AND room_id=? AND kind='npvr'
                AND status IN ('scheduled','recording','finished')",
            [$tenantId, $roomId]
        );

        return [
            'max_recordings' => $maxRec,
            'max_minutes'    => $maxMin,
            'used_recordings'=> (int)($used['n'] ?? 0),
            'used_minutes'   => (int)($used['mins'] ?? 0),
        ];
    }

    public function setQuota(int $tenantId, int $roomId, int $maxRec, int $maxMin): void
    {
        $exists = $this->db->exists('dvr_quotas', ['tenant_id' => $tenantId, 'room_id' => $roomId]);
        if ($exists) {
            $this->db->update('dvr_quotas',
                ['max_recordings' => $maxRec, 'max_minutes' => $maxMin],
                ['tenant_id' => $tenantId, 'room_id' => $roomId]);
        } else {
            $this->db->insert('dvr_quotas', [
                'tenant_id' => $tenantId, 'room_id' => $roomId,
                'max_recordings' => $maxRec, 'max_minutes' => $maxMin,
            ]);
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  ثبت ضبط (PVR و NPVR)
    // ══════════════════════════════════════════════════════════════

    /**
     * یک ضبط ثبت می‌کند.
     *
     * @param array{channel_id:int,title?:string,event_id?:int,start?:int,stop?:int,
     *              kind?:string,room_id?:int,screen_code?:string,created_by?:int} $o
     * @return array{ok:bool,message:string,id?:int}
     */
    public function schedule(array $o): array
    {
        $tenantId = (int)($o['tenant_id'] ?? 1);
        $chId     = (int)($o['channel_id'] ?? 0);
        $kind     = in_array($o['kind'] ?? 'pvr', ['pvr','npvr','catchup'], true) ? $o['kind'] : 'pvr';

        $ch = $this->channel($chId, $tenantId);
        if (!$ch)               return ['ok' => false, 'message' => 'کانال یافت نشد'];
        $uuid = (string)($ch['tvh_uuid'] ?? '');
        if ($uuid === '')       return ['ok' => false, 'message' => 'این کانال به TVHeadend نگاشت نشده (tvh_uuid خالی است)'];

        $src = $this->src($tenantId);
        if (!$src)              return ['ok' => false, 'message' => 'منبع TVHeadend تنظیم نشده'];

        $roomId = isset($o['room_id']) ? (int)$o['room_id'] : null;

        // سهمیه فقط برای NPVR (ضبط شخصی مهمان)
        if ($kind === 'npvr' && $roomId) {
            $q = $this->quota($tenantId, $roomId);
            if ($q['used_recordings'] >= $q['max_recordings']) {
                return ['ok' => false, 'message' => 'به سقف تعداد ضبط رسیده‌اید'];
            }
            if ($q['used_minutes'] >= $q['max_minutes']) {
                return ['ok' => false, 'message' => 'به سقف زمان ضبط رسیده‌اید'];
            }
        }

        $eventId = (int)($o['event_id'] ?? 0);
        $title   = trim((string)($o['title'] ?? '')) ?: (string)$ch['name'];

        // از روی رویداد EPG دقیق‌تر است (تغییر زمان پخش را دنبال می‌کند)
        if ($eventId > 0) {
            $r = $this->tvh->recordEvent($src, $eventId);
            // زمان از EPG محلی برای ثبت در جدول
            $ev = $this->db->row("SELECT title, starts_at, ends_at FROM epg_programs WHERE external_id=? OR id=? LIMIT 1",
                                 [(string)$eventId, $eventId]);
            $startTs = isset($ev['starts_at']) ? strtotime((string)$ev['starts_at']) : time();
            $stopTs  = isset($ev['ends_at'])   ? strtotime((string)$ev['ends_at'])   : time() + 3600;
            if (!empty($ev['title'])) $title = (string)$ev['title'];
        } else {
            $startTs = (int)($o['start'] ?? time());
            $stopTs  = (int)($o['stop']  ?? ($startTs + 3600));
            $r = $this->tvh->record($src, $uuid, $startTs, $stopTs, $title);
        }

        if (!$r['ok']) return ['ok' => false, 'message' => $r['message']];

        $id = $this->db->insert('dvr_recordings', [
            'tenant_id'   => $tenantId,
            'kind'        => $kind,
            'channel_id'  => $chId,
            'room_id'     => $roomId,
            'screen_code' => $o['screen_code'] ?? null,
            'tvh_uuid'    => $r['uuid'] ?: null,
            'event_id'    => $eventId ?: null,
            'title'       => $title,
            'starts_at'   => date('Y-m-d H:i:s', $startTs),
            'stops_at'    => date('Y-m-d H:i:s', $stopTs),
            'status'      => 'scheduled',
            'created_by'  => $o['created_by'] ?? null,
        ]);

        return ['ok' => true, 'message' => 'ضبط ثبت شد', 'id' => (int)$id];
    }

    // ══════════════════════════════════════════════════════════════
    //  فهرست، حذف، همگام‌سازی وضعیت
    // ══════════════════════════════════════════════════════════════

    public function listAll(int $tenantId, ?string $status = null, ?string $kind = null, int $limit = 200): array
    {
        $sql = "SELECT d.*, c.name AS channel_name, r.room_number
                  FROM dvr_recordings d
                  LEFT JOIN iptv_channels c ON c.id = d.channel_id
                  LEFT JOIN iptv_rooms r    ON r.id = d.room_id
                 WHERE d.tenant_id=?";
        $p = [$tenantId];
        if ($status) { $sql .= " AND d.status=?"; $p[] = $status; }
        if ($kind)   { $sql .= " AND d.kind=?";   $p[] = $kind; }
        $sql .= " ORDER BY d.starts_at DESC LIMIT " . max(1, min(500, $limit));
        return $this->db->rows($sql, $p);
    }

    public function listForRoom(int $tenantId, int $roomId): array
    {
        return $this->db->rows(
            "SELECT d.*, c.name AS channel_name
               FROM dvr_recordings d
               LEFT JOIN iptv_channels c ON c.id = d.channel_id
              WHERE d.tenant_id=? AND d.room_id=? AND d.kind='npvr'
                AND d.status <> 'removed'
              ORDER BY d.starts_at DESC LIMIT 200",
            [$tenantId, $roomId]
        );
    }

    /** @return array{ok:bool,message:string} */
    public function remove(int $tenantId, int $id): array
    {
        $rec = $this->db->row("SELECT * FROM dvr_recordings WHERE id=? AND tenant_id=?", [$id, $tenantId]);
        if (!$rec) return ['ok' => false, 'message' => 'ضبط یافت نشد'];

        $src = $this->src($tenantId);
        if ($src && !empty($rec['tvh_uuid'])) {
            $this->tvh->deleteRecording($src, (string)$rec['tvh_uuid']);
        }
        $this->db->update('dvr_recordings', ['status' => 'removed'], ['id' => $id, 'tenant_id' => $tenantId]);
        return ['ok' => true, 'message' => 'ضبط حذف شد'];
    }

    /**
     * وضعیت ضبط‌های محلی را با TVHeadend هماهنگ می‌کند: تمام‌شده‌ها را
     * finished و آدرس فایل را پر می‌کند، شکست‌ها را failed.
     * @return array{ok:bool,message:string,updated:int}
     */
    public function syncStatuses(int $tenantId): array
    {
        $src = $this->src($tenantId);
        if (!$src) return ['ok' => false, 'message' => 'منبع TVHeadend تنظیم نشده', 'updated' => 0];

        $updated = 0;
        $map = [];  // tvh_uuid => [status, url, size]
        foreach (['finished', 'upcoming', 'failed'] as $which) {
            $res = $this->tvh->recordings($src, $which, 500);
            if (!$res['ok']) continue;
            $st = $which === 'finished' ? 'finished' : ($which === 'failed' ? 'failed' : 'recording');
            foreach ($res['items'] as $e) {
                $u = (string)($e['uuid'] ?? '');
                if ($u === '') continue;
                $map[$u] = [
                    'status' => $st,
                    'url'    => $st === 'finished' ? $this->tvh->recordingUrl($src, $u) : null,
                    'size'   => (int)($e['filesize'] ?? 0),
                ];
            }
        }

        $rows = $this->db->rows(
            "SELECT id, tvh_uuid, status FROM dvr_recordings
              WHERE tenant_id=? AND tvh_uuid IS NOT NULL AND status NOT IN ('removed')",
            [$tenantId]
        );
        foreach ($rows as $row) {
            $u = (string)$row['tvh_uuid'];
            if (!isset($map[$u])) continue;
            $m = $map[$u];
            if ($m['status'] === $row['status'] && $m['status'] !== 'finished') continue;
            $this->db->update('dvr_recordings', [
                'status'     => $m['status'],
                'file_url'   => $m['url'],
                'size_bytes' => $m['size'],
            ], ['id' => (int)$row['id']]);
            $updated++;
        }

        return ['ok' => true, 'message' => "$updated ضبط به‌روز شد", 'updated' => $updated];
    }

    // ══════════════════════════════════════════════════════════════
    //  Catch-up — ضبط خودکار کانال + پخش برنامه‌ی گذشته
    // ══════════════════════════════════════════════════════════════

    /**
     * catch-up را روی یک کانال روشن/خاموش می‌کند. روشن‌کردن یعنی یک
     * قانون autorec در TVHeadend می‌سازد که همه‌ی برنامه‌ها را تا
     * $hours ساعت نگه می‌دارد.
     * @return array{ok:bool,message:string}
     */
    public function setCatchup(int $tenantId, int $channelId, bool $enabled, int $hours = 24): array
    {
        $ch = $this->channel($channelId, $tenantId);
        if (!$ch) return ['ok' => false, 'message' => 'کانال یافت نشد'];

        $uuid = (string)($ch['tvh_uuid'] ?? '');
        if ($enabled && $uuid === '') {
            return ['ok' => false, 'message' => 'ابتدا کانال را به TVHeadend نگاشت کنید'];
        }

        $src = $this->src($tenantId);
        if (!$src) return ['ok' => false, 'message' => 'منبع TVHeadend تنظیم نشده'];

        $days = max(1, (int)ceil($hours / 24));

        if ($enabled) {
            // اگر قبلا autorec داشت، اول پاکش کن تا تکراری نشود
            if (!empty($ch['catchup_autorec_uuid'])) {
                $this->tvh->deleteAutorec($src, (string)$ch['catchup_autorec_uuid']);
            }
            $r = $this->tvh->createAutorec($src, $uuid, 'catchup-' . $channelId, $days);
            if (!$r['ok']) return ['ok' => false, 'message' => $r['message']];
            $this->db->update('iptv_channels', [
                'catchup_enabled'      => 1,
                'catchup_window_hours' => $hours,
                'catchup_autorec_uuid' => $r['uuid'] ?: null,
            ], ['id' => $channelId, 'tenant_id' => $tenantId]);
            return ['ok' => true, 'message' => 'Catch-up روشن شد'];
        }

        if (!empty($ch['catchup_autorec_uuid'])) {
            $this->tvh->deleteAutorec($src, (string)$ch['catchup_autorec_uuid']);
        }
        $this->db->update('iptv_channels', [
            'catchup_enabled'      => 0,
            'catchup_autorec_uuid' => null,
        ], ['id' => $channelId, 'tenant_id' => $tenantId]);
        return ['ok' => true, 'message' => 'Catch-up خاموش شد'];
    }

    /**
     * آدرس پخش یک برنامه‌ی گذشته (catch-up). فقط اگر کانال catch-up
     * روشن داشته و برنامه ضبط شده باشد.
     * @return array{ok:bool,message:string,url?:string}
     */
    public function catchupUrl(int $tenantId, int $channelId, int $eventId): array
    {
        $ch = $this->channel($channelId, $tenantId);
        if (!$ch || empty($ch['catchup_enabled'])) {
            return ['ok' => false, 'message' => 'Catch-up روی این کانال فعال نیست'];
        }
        $src = $this->src($tenantId);
        if (!$src) return ['ok' => false, 'message' => 'منبع TVHeadend تنظیم نشده'];

        $f = $this->tvh->findRecordingByEvent($src, $eventId);
        if (!$f['ok']) return ['ok' => false, 'message' => $f['message']];

        return ['ok' => true, 'message' => '', 'url' => $this->tvh->recordingUrl($src, $f['uuid'])];
    }
}
