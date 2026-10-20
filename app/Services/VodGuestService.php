<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * کتابخانه‌ی فیلم روی تلویزیون اتاق — TODO ۳.۳
 *
 * «ادامه‌ی تماشا»، پیشنهاد بر اساس علاقه و خرید فیلم پولی. پیش از این
 * کاشی «فیلم‌ها» روی تلویزیون فقط یک متن ثابت نشان می‌داد و API خرید
 * (PPV) را هیچ صفحه‌ای صدا نمی‌زد.
 *
 * تاریخچه به اقامت بسته است (check_in_at اتاق): مهمان بعدی همان اتاق
 * فیلم‌های مهمان قبلی را نمی‌بیند. اتاق خالی یا تلویزیون لابی فقط فهرست
 * را می‌بیند؛ تاریخچه و خرید ندارد.
 */
final class VodGuestService
{
    /** یوتیوب و ویمئو اینترنت می‌خواهند؛ تلویزیون هتل فقط محلی پخش می‌کند */
    private const PLAYABLE = "v.type IN ('upload','url') AND v.status = 'ready' AND v.is_active = 1";

    /* کمتر از این یعنی فقط تیتراژ را دیده؛ «ادامه» برایش معنی ندارد */
    public const MIN_RESUME = 30;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * صفحه‌ی اصلی: ادامه‌ی تماشا، پیشنهاد، و هر دسته.
     * @param array{tenant_id:int,room_id:?int,stay:?string} $ctx
     * @return array<string,mixed>
     */
    public function library(array $ctx): array
    {
        $tid    = (int)$ctx['tenant_id'];
        $videos = $this->db->rows(
            'SELECT v.id, v.category_id, v.title, v.title_en, v.year, v.duration, v.duration_fmt, v.thumbnail,
                    v.language, v.rating, v.price, v.currency, v.access_hours, v.is_featured, v.views,
                    LEFT(v.description, 400) AS description
               FROM vod_videos v
              WHERE v.tenant_id = ? AND ' . self::PLAYABLE . '
              ORDER BY v.is_featured DESC, v.sort_order, v.id DESC',
            [$tid]
        );

        $hist   = $this->history($ctx);
        $owned  = $this->owned($ctx);
        $byId   = [];
        foreach ($videos as $v) {
            $byId[(int)$v['id']] = $this->card($v, $hist[(int)$v['id']] ?? null, $owned);
        }

        // ادامه‌ی تماشا — تازه‌ترین اول
        $continue = [];
        foreach ($hist as $vid => $h) {
            if (!isset($byId[$vid]) || (int)$h['completed'] || (int)$h['hidden']) continue;
            if ((int)$h['position'] < self::MIN_RESUME) continue;
            $continue[] = $byId[$vid];
        }
        usort($continue, fn($a, $b) => strcmp((string)$b['progress']['updated_at'], (string)$a['progress']['updated_at']));

        $cats = $this->db->rows(
            'SELECT id, name, color FROM vod_categories WHERE tenant_id = ? AND is_active = 1 ORDER BY sort_order, name',
            [$tid]
        );
        $rows = [];
        foreach ($cats as $c) {
            $list = array_values(array_filter($byId, fn($v) => (int)$v['category_id'] === (int)$c['id']));
            if ($list) $rows[] = ['id' => (int)$c['id'], 'name' => $c['name'], 'videos' => $list];
        }
        $loose = array_values(array_filter($byId, fn($v) => !$v['category_id']));
        if ($loose) $rows[] = ['id' => 0, 'name' => 'سایر', 'videos' => $loose];

        return [
            'occupied'    => !empty($ctx['stay']),
            'continue'    => array_slice($continue, 0, 12),
            'recommended' => $this->recommend($byId, $hist),
            'categories'  => $rows,
        ];
    }

    /**
     * پیشنهاد: فیلم‌های دیده‌نشده از دسته‌هایی که همین اقامت بیشتر دیده،
     * به ترتیب محبوبیت. بدون تاریخچه، فیلم‌های ویژه و پربیننده.
     * @param array<int,array<string,mixed>> $byId
     * @param array<int,array<string,mixed>> $hist
     * @return list<array<string,mixed>>
     */
    private function recommend(array $byId, array $hist): array
    {
        $weight = [];
        foreach ($hist as $vid => $h) {
            $cat = (int)($byId[$vid]['category_id'] ?? 0);
            if ($cat) $weight[$cat] = ($weight[$cat] ?? 0) + ((int)$h['completed'] ? 2 : 1);
        }
        $pool = array_filter($byId, fn($v) => !isset($hist[$v['id']]));
        uasort($pool, function ($a, $b) use ($weight) {
            $wa = $weight[(int)$a['category_id']] ?? 0;
            $wb = $weight[(int)$b['category_id']] ?? 0;
            if ($wa !== $wb) return $wb <=> $wa;
            if ($a['is_featured'] !== $b['is_featured']) return $b['is_featured'] <=> $a['is_featured'];
            return $b['views'] <=> $a['views'];
        });
        return array_values(array_slice($pool, 0, 12));
    }

    /**
     * جزئیات یک فیلم؛ آدرس پخش فقط وقتی اجازه دارد.
     * @return array<string,mixed>|null
     */
    public function detail(array $ctx, int $id): ?array
    {
        $tid = (int)$ctx['tenant_id'];
        $v = $this->db->row(
            'SELECT v.* FROM vod_videos v WHERE v.id = ? AND v.tenant_id = ? AND ' . self::PLAYABLE,
            [$id, $tid]
        );
        if (!$v) return null;

        $hist  = $this->history($ctx);
        $owned = $this->owned($ctx);
        $out   = $this->card($v, $hist[$id] ?? null, $owned);
        $out['description'] = (string)($v['description'] ?? '');
        $out['url'] = $out['allowed'] ? ($v['type'] === 'upload' ? (string)$v['file_path'] : (string)$v['stream_url']) : null;
        $out['subtitles'] = $out['allowed'] ? $this->db->rows(
            'SELECT id, lang, label, file_path, is_default FROM vod_subtitles WHERE vod_id = ? AND tenant_id = ? ORDER BY sort_order, id',
            [$id, $tid]
        ) : [];
        return $out;
    }

    /**
     * جای پخش را ثبت می‌کند. true اگر ثبت شد.
     * نزدیک پایان = دیده‌شده؛ دفعه‌ی بعد از اول پخش می‌شود.
     */
    public function saveProgress(array $ctx, int $id, int $position, int $duration): bool
    {
        if (empty($ctx['stay']) || empty($ctx['room_id'])) return false;
        $tid = (int)$ctx['tenant_id'];
        $known = $this->db->value('SELECT duration FROM vod_videos WHERE id = ? AND tenant_id = ?', [$id, $tid]);
        if ($known === null) return false;

        $duration = $duration > 0 ? min($duration, 86400) : (int)$known;
        $position = max(0, min($position, $duration > 0 ? $duration : 86400));
        $done     = $duration > 0 && ($position >= $duration * 0.95 || $duration - $position < 60) ? 1 : 0;
        if ($done) $position = 0;

        $n = $this->db->query(
            'INSERT INTO vod_watch_history (tenant_id, room_id, stay_started_at, video_id, position, duration, completed)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE position = VALUES(position), duration = VALUES(duration),
                                     completed = GREATEST(completed, VALUES(completed)), hidden = 0',
            [$tid, (int)$ctx['room_id'], $ctx['stay'], $id, $position, $duration, $done]
        )->rowCount();

        /* ۱ یعنی ردیف تازه: اولین تماشای این اقامت، شمارش بازدید */
        if ($n === 1) $this->db->query('UPDATE vod_videos SET views = views + 1 WHERE id = ?', [$id]);
        return true;
    }

    /** مهمان فیلم را از «ادامه‌ی تماشا» برمی‌دارد */
    public function hide(array $ctx, int $id): bool
    {
        if (empty($ctx['stay'])) return false;
        return $this->db->query(
            'UPDATE vod_watch_history SET hidden = 1 WHERE room_id = ? AND stay_started_at = ? AND video_id = ?',
            [(int)$ctx['room_id'], $ctx['stay'], $id]
        )->rowCount() > 0;
    }

    /** پربیننده‌ترین‌ها برای پنل (همه‌ی اقامت‌ها) */
    public function topWatched(int $tenantId, int $days = 30, int $limit = 10): array
    {
        return $this->db->rows(
            'SELECT v.id, v.title, COUNT(*) AS stays, SUM(h.completed) AS finished
               FROM vod_watch_history h JOIN vod_videos v ON v.id = h.video_id
              WHERE h.tenant_id = ? AND h.started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY v.id, v.title ORDER BY stays DESC LIMIT ' . max(1, min(50, $limit)),
            [$tenantId, max(1, $days)]
        );
    }

    // ─────────────────────────────────────────────────────────────

    /** @return array<int,array<string,mixed>> video_id => ردیف تاریخچه‌ی همین اقامت */
    private function history(array $ctx): array
    {
        if (empty($ctx['stay']) || empty($ctx['room_id'])) return [];
        $out = [];
        foreach ($this->db->rows(
            'SELECT video_id, position, duration, completed, hidden, updated_at FROM vod_watch_history
              WHERE room_id = ? AND stay_started_at = ?',
            [(int)$ctx['room_id'], $ctx['stay']]
        ) as $h) $out[(int)$h['video_id']] = $h;
        return $out;
    }

    /** @return array<int,string> فیلم‌های پولیِ خریده‌شده‌ی فعال → انقضا */
    private function owned(array $ctx): array
    {
        if (empty($ctx['room_id'])) return [];
        $out = [];
        foreach ($this->db->rows(
            'SELECT video_id, MAX(expires_at) AS exp FROM ppv_purchases
              WHERE tenant_id = ? AND room_id = ? AND cancelled_at IS NULL AND expires_at > NOW()
              GROUP BY video_id',
            [(int)$ctx['tenant_id'], (int)$ctx['room_id']]
        ) as $p) $out[(int)$p['video_id']] = (string)$p['exp'];
        return $out;
    }

    /** @param array<string,mixed>|null $h */
    private function card(array $v, ?array $h, array $owned): array
    {
        $id    = (int)$v['id'];
        $price = (float)$v['price'];
        return [
            'id'           => $id,
            'category_id'  => $v['category_id'] !== null ? (int)$v['category_id'] : null,
            'title'        => (string)$v['title'],
            'title_en'     => (string)($v['title_en'] ?? ''),
            'year'         => $v['year'] ? (int)$v['year'] : null,
            'duration'     => (int)$v['duration'],
            'duration_fmt' => (string)($v['duration_fmt'] ?? ''),
            'thumbnail'    => (string)($v['thumbnail'] ?? ''),
            'language'     => (string)($v['language'] ?? ''),
            'rating'       => $v['rating'] !== null ? (int)$v['rating'] : null,
            'description'  => (string)($v['description'] ?? ''),
            'is_featured'  => (int)$v['is_featured'],
            'views'        => (int)$v['views'],
            'price'        => $price,
            'currency'     => (string)($v['currency'] ?? 'IRR'),
            'access_hours' => (int)$v['access_hours'],
            'allowed'      => $price <= 0 || isset($owned[$id]),
            'owned_until'  => $owned[$id] ?? null,
            'progress'     => $h ? [
                'position'   => (int)$h['position'],
                'duration'   => (int)$h['duration'],
                'completed'  => (bool)(int)$h['completed'],
                'updated_at' => (string)$h['updated_at'],
            ] : null,
        ];
    }
}
