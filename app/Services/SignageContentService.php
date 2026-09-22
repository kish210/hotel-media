<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Signage Content Service
 * محتوای پویای صفحه‌های محیط عمومی هتل — لابی، رستوران، جلوی سالن کنفرانس.
 *
 * چرا لازم بود: هرچه برای تلویزیون اتاق ساخته شد (منوی تصویری، اخبار،
 * دفترچه تلفن، نوار زنده) برای صفحه‌های محیط عمومی در دسترس نبود، چون
 * پلی‌لیست فقط فایل آپلودشده می‌پذیرفت. این سرویس همان محتوا را برای
 * پلی‌لیست هم قابل استفاده می‌کند.
 *
 * و مهم‌تر: صفحه حالا «محل» دارد. تابلوی جلوی سالن کنفرانس A خودکار
 * برنامه‌ی همان سالن را نشان می‌دهد، بدون اینکه اپراتور برای هر در یک
 * پلی‌لیست جدا بسازد.
 */
class SignageContentService
{
    /** انواع آیتم پویا — media فایل معمولی است و اینجا ساخته نمی‌شود */
    public const DYNAMIC_TYPES = [
        'event_board', 'menu_board', 'news', 'directory',
        'info_bar', 'venue_info', 'live_tv', 'weather',
        'flight_board',
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * محتوای یک آیتم پویا را می‌سازد.
     *
     * @param array<string,mixed> $item   ردیف playlist_items
     * @param array<string,mixed> $screen ردیف screens
     * @return array<string,mixed>|null  null یعنی این آیتم الان چیزی برای نمایش ندارد
     */
    public function resolve(array $item, array $screen): ?array
    {
        $tid     = (int)$screen['tenant_id'];
        $venueId = (int)($item['ref_id'] ?? 0) ?: (int)($screen['venue_id'] ?? 0);

        return match ($item['item_type']) {
            'event_board' => $this->eventBoard($tid, $venueId),
            'menu_board'  => $this->menuBoard($tid, (int)($item['ref_id'] ?? 0), (int)($screen['venue_id'] ?? 0)),
            'news'        => $this->news($tid, $item),
            'directory'   => $this->directory($tid),
            'venue_info'  => $this->venueInfo($tid, $venueId),
            'live_tv'     => $this->liveTv($tid, (int)($item['ref_id'] ?? 0)),
            'flight_board' => $this->flightBoard($tid, $item, $screen),
            'info_bar',
            'weather'     => $this->infoBar($tid, $item),
            default       => null,
        };
    }

    // ══════════════════════════════════════════════════════════════
    //  تابلوی پرواز — برای هتل نزدیک فرودگاه
    // ══════════════════════════════════════════════════════════════

    /**
     * فهرست پروازهای ورودی یا خروجی.
     *
     * عمدا ساده است: هتل به دروازه و تسمه و نوع هواپیما کاری ندارد —
     * مهمان فقط می‌خواهد بداند پروازش سر ساعت است یا نه.
     *
     * پروازهای گذشته حذف می‌شوند ولی یک پنجره‌ی کوتاه عقب‌تر نگه
     * داشته می‌شود: مهمانی که تازه رسیده هنوز دنبال پرواز خودش در
     * تابلو می‌گردد و اگر بلافاصله ناپدید شود فکر می‌کند اشتباه آمده.
     *
     * @return array<string,mixed>|null
     */
    private function flightBoard(int $tenantId, array $item, array $screen): ?array
    {
        $settings  = $this->settings($item);
        $direction = ($settings['direction'] ?? 'departure') === 'arrival' ? 'arrival' : 'departure';
        $limit     = max(1, min(20, (int)($settings['limit'] ?? 8)));

        /* پنجره: از ۳۰ دقیقه پیش تا ۱۲ ساعت بعد */
        $from = date('Y-m-d H:i:s', time() - 1800);
        $to   = date('Y-m-d H:i:s', time() + 43200);

        $sql = "SELECT flight_number, airline, airline_logo, city, city_en,
                       scheduled_at, estimated_at, status, terminal
                  FROM flights
                 WHERE tenant_id = ? AND is_active = 1
                   AND direction = ?
                   AND scheduled_at BETWEEN ? AND ?";
        $par = [$tenantId, $direction, $from, $to];

        /* در هتل زنجیره‌ای، هر شعبه تابلوی فرودگاه خودش را می‌خواهد */
        $locationId = (int)($screen['location_id'] ?? 0);
        if ($locationId > 0) {
            $sql .= ' AND (location_id = ? OR location_id IS NULL)';
            $par[] = $locationId;
        }

        $sql .= " ORDER BY scheduled_at LIMIT $limit";

        $rows = $this->db->rows($sql, $par);
        if (!$rows) return null;

        $out = [];
        foreach ($rows as $r) {
            $sched = (string)$r['scheduled_at'];
            $est   = $r['estimated_at'] !== null ? (string)$r['estimated_at'] : null;

            $out[] = [
                'flight_number' => $r['flight_number'],
                'airline'       => $r['airline'],
                'airline_logo'  => $r['airline_logo'],
                'city'          => $r['city'],
                'city_en'       => $r['city_en'],
                'scheduled_at'  => $sched,
                'estimated_at'  => $est,
                /* تاخیر را سرور حساب می‌کند نه تلویزیون: مرورگر
                   تلویزیون برای تفریق تاریخ قابل اعتماد نیست. */
                'delay_minutes' => ($est !== null && strtotime($est) > strtotime($sched))
                    ? (int)round((strtotime($est) - strtotime($sched)) / 60)
                    : 0,
                'status'        => $r['status'],
                'status_label'  => $this->flightStatusLabel((string)$r['status']),
                'terminal'      => $r['terminal'],
            ];
        }

        return [
            'kind'      => 'flight_board',
            'direction' => $direction,
            'title'     => $direction === 'arrival' ? 'پروازهای ورودی' : 'پروازهای خروجی',
            'flights'   => $out,
        ];
    }

    private function flightStatusLabel(string $status): string
    {
        return [
            'scheduled' => 'طبق برنامه',
            'boarding'  => 'سوار شوید',
            'departed'  => 'پرواز کرد',
            'arrived'   => 'نشست',
            'delayed'   => 'تاخیر',
            'cancelled' => 'لغو شد',
        ][$status] ?? $status;
    }

    // ══════════════════════════════════════════════════════════════
    //  تابلوی رویداد — مهم‌ترین نیاز محیط عمومی هتل
    // ══════════════════════════════════════════════════════════════

    /**
     * برنامه‌ی سالن. اگر صفحه به سالن خاصی وصل باشد فقط همان، وگرنه
     * همه‌ی سالن‌ها — که همان تابلوی لابی است.
     *
     * @return array<string,mixed>|null
     */
    private function eventBoard(int $tenantId, int $venueId): ?array
    {
        $sql = "SELECT e.id, e.title, e.title_en, e.description, e.organizer,
                       e.hall_name, e.floor, e.start_at, e.end_at, e.type,
                       e.color, e.image, e.status,
                       v.name AS venue_name, v.name_en AS venue_name_en, v.floor AS venue_floor
                  FROM hotel_events e
                  LEFT JOIN venues v ON v.id = e.venue_id
                 WHERE e.tenant_id = ? AND e.is_active = 1
                   AND e.status <> 'ended'
                   -- امروز و فردا: تابلوی در ورودی نباید برنامه‌ی هفته بعد را نشان دهد
                   AND e.start_at <= DATE_ADD(NOW(), INTERVAL 2 DAY)
                   AND COALESCE(e.end_at, DATE_ADD(e.start_at, INTERVAL 2 HOUR)) >= NOW()";
        $params = [$tenantId];

        if ($venueId > 0) { $sql .= ' AND e.venue_id = ?'; $params[] = $venueId; }

        $sql .= ' ORDER BY e.start_at LIMIT 12';

        $events = $this->db->rows($sql, $params);
        if (!$events) return null;   // چیزی برای نمایش نیست — آیتم رد می‌شود

        $now = time();
        foreach ($events as &$e) {
            $start = strtotime((string)$e['start_at']);
            $end   = $e['end_at'] ? strtotime((string)$e['end_at']) : $start + 7200;

            $e['is_now']       = $e['status'] !== 'cancelled' && $now >= $start && $now < $end;
            $e['is_cancelled'] = $e['status'] === 'cancelled';
            $e['minutes_away'] = $now < $start ? (int)round(($start - $now) / 60) : 0;
        }
        unset($e);

        $venue = $venueId > 0
            ? $this->db->row('SELECT name, name_en, floor, capacity FROM venues WHERE id = ?', [$venueId])
            : null;

        return [
            'kind'   => 'event_board',
            'venue'  => $venue,
            'events' => $events,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    //  منوی تصویری
    // ══════════════════════════════════════════════════════════════

    /**
     * منوی تصویری برای صفحه‌ی رستوران. اگر آیتم منوی خاصی تعیین نکرده،
     * منوی پیش‌فرض محل استفاده می‌شود.
     * @return array<string,mixed>|null
     */
    private function menuBoard(int $tenantId, int $boardId, int $venueId): ?array
    {
        if ($boardId <= 0 && $venueId > 0) {
            $boardId = (int)($this->db->value(
                'SELECT menu_board_id FROM venues WHERE id = ? AND tenant_id = ?',
                [$venueId, $tenantId]
            ) ?? 0);
        }
        if ($boardId <= 0) return null;

        $board = $this->db->row(
            'SELECT id, category, title, title_en, description
               FROM menu_boards WHERE id = ? AND tenant_id = ? AND is_active = 1',
            [$boardId, $tenantId]
        );
        if (!$board) return null;

        $pages = $this->db->rows(
            'SELECT image_url, caption FROM menu_board_pages WHERE board_id = ? ORDER BY sort_order, id',
            [$boardId]
        );
        if (!$pages) return null;   // منوی بدون صفحه چیزی برای نمایش ندارد

        $board['pages'] = $pages;
        $board['kind']  = 'menu_board';

        return $board;
    }

    // ══════════════════════════════════════════════════════════════
    //  بقیه‌ی انواع
    // ══════════════════════════════════════════════════════════════

    /** @return array<string,mixed>|null */
    private function news(int $tenantId, array $item): ?array
    {
        $settings = $this->settings($item);
        $limit    = max(1, min(20, (int)($settings['limit'] ?? 6)));

        $sql = "SELECT title, subtitle, body, image_url, extra, published_at
                  FROM content_items
                 WHERE tenant_id = ? AND kind = 'news' AND is_active = 1";
        $par = [$tenantId];

        if (!empty($settings['category'])) { $sql .= ' AND category = ?'; $par[] = $settings['category']; }

        $sql .= " ORDER BY published_at DESC, id DESC LIMIT $limit";

        $rows = $this->db->rows($sql, $par);
        return $rows ? ['kind' => 'news', 'items' => $rows] : null;
    }

    /** @return array<string,mixed>|null */
    private function directory(int $tenantId): ?array
    {
        $rows = $this->db->rows(
            "SELECT title, title_en, extra FROM content_items
              WHERE tenant_id = ? AND kind = 'directory' AND is_active = 1
              ORDER BY sort_order, id LIMIT 30",
            [$tenantId]
        );

        return $rows ? ['kind' => 'directory', 'items' => $rows] : null;
    }

    /** اطلاعات خود محل — ساعت کاری استخر، ظرفیت سالن @return array<string,mixed>|null */
    private function venueInfo(int $tenantId, int $venueId): ?array
    {
        if ($venueId <= 0) return null;

        $venue = $this->db->row(
            'SELECT name, name_en, kind, floor, description, image_url,
                    capacity, open_from, open_to
               FROM venues WHERE id = ? AND tenant_id = ? AND is_active = 1',
            [$venueId, $tenantId]
        );
        if (!$venue) return null;

        $now = date('H:i:s');
        $venue['open_now'] = ($venue['open_from'] && $venue['open_to'])
            ? ($venue['open_from'] <= $venue['open_to']
                ? ($now >= $venue['open_from'] && $now <= $venue['open_to'])
                : ($now >= $venue['open_from'] || $now <= $venue['open_to']))
            : null;
        $venue['kind'] = 'venue_info';

        // محل خودش رویداد جاری دارد؟
        $venue['now_playing'] = $this->db->row(
            "SELECT title, start_at, end_at FROM hotel_events
              WHERE tenant_id = ? AND venue_id = ? AND is_active = 1 AND status = 'ongoing'
              ORDER BY start_at LIMIT 1",
            [$tenantId, $venueId]
        );

        return $venue;
    }

    /** کانال زنده برای صفحه‌ی لابی @return array<string,mixed>|null */
    private function liveTv(int $tenantId, int $channelId): ?array
    {
        if ($channelId <= 0) return null;

        $ch = $this->db->row(
            'SELECT id, name, logo_url, stream_url, multicast_url, delivery
               FROM iptv_channels
              WHERE id = ? AND tenant_id = ? AND is_active = 1
                -- صفحه‌ی محیط عمومی هرگز نباید کانال بزرگسال بگیرد
                AND is_adult = 0',
            [$channelId, $tenantId]
        );
        if (!$ch) return null;

        // پلیر signage مرورگر است و multicast نمی‌فهمد
        if (empty($ch['stream_url'])) return null;

        $ch['kind'] = 'live_tv';
        $ch['now']  = $this->db->row(
            'SELECT title, starts_at, ends_at FROM epg_programs
              WHERE tenant_id = ? AND channel_id = ? AND starts_at <= NOW() AND ends_at > NOW()
              LIMIT 1',
            [$tenantId, $channelId]
        );

        return $ch;
    }

    /** نوار اطلاعات زنده — همان سرویس پورتال اتاق @return array<string,mixed>|null */
    private function infoBar(int $tenantId, array $item): ?array
    {
        $settings = $this->settings($item);

        $widgets = array_values(array_filter(
            array_map('trim', explode(',', (string)($settings['widgets'] ?? 'clock,weather'))),
            static fn($w) => in_array($w, ['clock', 'weather', 'currency', 'prayer'], true)
        ));
        if (!$widgets) $widgets = ['clock', 'weather'];

        $data = (new PortalLiveService($this->db))->get(
            $tenantId, $widgets, $settings['city'] ?? null
        );

        return ['kind' => 'info_bar', 'widgets' => $widgets, 'data' => $data];
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /** @return array<string,mixed> */
    private function settings(array $item): array
    {
        $raw = $item['settings'] ?? null;
        if (is_array($raw)) return $raw;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
        }

        return [];
    }
}
