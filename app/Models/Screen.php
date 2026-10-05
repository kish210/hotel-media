<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Auth;

class Screen
{
    private Database $db;
    private int $tenantId;

    public function __construct()
    {
        $this->db       = Database::getInstance();
        $this->tenantId = Auth::tenantId();
    }

    public function all(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $params = [$this->tenantId];

        // چک کردن ستون‌های اختیاری
        $hasGroupId    = false;
        $hasScreenType = false;
        $hasGroups     = false;

        try { $this->db->value("SELECT group_id FROM screens LIMIT 1");    $hasGroupId    = true; } catch (\Throwable $e) {}
        try { $this->db->value("SELECT screen_type FROM screens LIMIT 1"); $hasScreenType = true; } catch (\Throwable $e) {}
        try { $this->db->value("SELECT 1 FROM screen_groups LIMIT 1");     $hasGroups     = true; } catch (\Throwable $e) {}

        $groupFields  = $hasGroupId ? ", s.group_id" : ", NULL AS group_id";
        $groupFields .= ($hasGroups && $hasGroupId)
            ? ", g.name AS group_name, g.color AS group_color"
            : ", NULL AS group_name, NULL AS group_color";
        $typeField = $hasScreenType ? ", s.screen_type" : ", 'signage' AS screen_type";
        $groupJoin = ($hasGroups && $hasGroupId)
            ? "LEFT JOIN screen_groups g ON g.id = s.group_id"
            : "";

        $sql = "SELECT s.id, s.code, s.name, s.status, s.orientation,
                       s.resolution, s.location_id, s.current_playlist_id,
                       s.settings, s.last_seen_at, s.platform,
                       l.name AS location_name,
                       p.name AS playlist_name,
                       /* شماره‌ی آیتمی که پلیر گزارش کرده — تا اپراتور از
                          همین فهرست بفهمد تابلو واقعا در حال پخش است یا
                          فقط آنلاین است و روی چیزی گیر کرده */
                       (SELECT hb.current_item FROM heartbeats hb
                         WHERE hb.screen_id = s.id
                         ORDER BY hb.id DESC LIMIT 1) AS current_item,
                       /* همان فیلتری که Playlist::getForPlayer دارد:
                          آیتمِ رسانه‌دارِ در حال تبدیل به تلویزیون نمی‌رود،
                          پس اگر اینجا شمرده شود عددِ کارت با آنچه تابلو
                          واقعا پخش می‌کند نمی‌خواند. */
                       (SELECT COUNT(*) FROM playlist_items pi
                         LEFT JOIN media pm ON pm.id = pi.media_id
                         WHERE pi.playlist_id = s.current_playlist_id
                           AND pi.is_active = 1
                           AND (pi.media_id IS NULL
                                OR pm.status IS NULL
                                OR pm.status = 'ready')) AS playlist_items,
                       TIMESTAMPDIFF(SECOND, s.last_seen_at, NOW()) AS seconds_ago,
                       CASE WHEN s.last_seen_at IS NOT NULL
                            AND TIMESTAMPDIFF(SECOND, s.last_seen_at, NOW()) < 120
                            THEN 1 ELSE 0 END AS is_online
                       {$typeField} {$groupFields}
                FROM screens s
                LEFT JOIN locations l ON l.id = s.location_id
                LEFT JOIN playlists p ON p.id = s.current_playlist_id
                {$groupJoin}
                WHERE s.tenant_id = ? AND s.status != 'inactive'";

        if (!empty($filters['status'])) {
            $sql    .= " AND s.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['location_id'])) {
            $sql    .= " AND s.location_id = ?";
            $params[] = (int)$filters['location_id'];
        }
        /* تفکیک دنیا — فقط وقتی ستون وجود دارد (نصب‌های قدیمی ندارند) */
        if ($hasScreenType && !empty($filters['screen_type'])
            && in_array($filters['screen_type'], ['signage','iptv','inflight','monitor_3d'], true)) {
            $sql    .= " AND s.screen_type = ?";
            $params[] = $filters['screen_type'];
        }
        $sql .= " ORDER BY s.name ASC";

        $rows = $this->db->rows($sql, $params);
        return array_values(array_filter(
            is_array($rows) ? $rows : [],
            fn($r) => is_array($r)
        ));
    }
    public function find(int $id): ?array
    {
        return $this->db->row(
            "SELECT s.*, l.name AS location_name FROM screens s LEFT JOIN locations l ON l.id=s.location_id WHERE s.id=? AND s.tenant_id=?",
            [$id, $this->tenantId]
        );
    }

    public function findByCode(string $code): ?array
    {
        return $this->db->row("SELECT * FROM screens WHERE code=?", [$code]);
    }

    public function create(array $data): int|string
    {
        $code = $this->generateCode();
        return $this->db->insert('screens', array_merge($data, [
            'tenant_id' => $this->tenantId,
            'code'      => $code,
            'status'    => 'pending',
        ]));
    }

    public function update(int $id, array $data): bool
    {
        return $this->db->update('screens', $data, ['id' => $id, 'tenant_id' => $this->tenantId]) > 0;
    }

    public function delete(int $id): bool
    {
        // soft delete: فقط status رو inactive کن (screens جدول is_active نداره)
        return $this->db->update('screens',
            ['status' => 'inactive'],
            ['id' => $id, 'tenant_id' => $this->tenantId]
        ) > 0;
    }

    public function generateActivationCode(int $id): string
    {
        // ۶ رقمی (نه هگز) تا با ریموتِ عددیِ تلویزیون قابل‌تایپ باشد.
        // یکتا میان کدهای فعالِ منقضی‌نشده تا دو صفحه یک کد نگیرند.
        $code = '';
        for ($i = 0; $i < 30; $i++) {
            $candidate = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $taken = $this->db->value(
                "SELECT 1 FROM screens WHERE activation_code=? AND activation_expires_at > NOW() LIMIT 1",
                [$candidate]
            );
            if (!$taken) { $code = $candidate; break; }
        }
        if ($code === '') $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->db->update('screens', [
            'activation_code'       => $code,
            'activation_expires_at' => date('Y-m-d H:i:s', time() + 86400), // 24 hours
        ], ['id' => $id]);
        return $code;
    }

    public function activateByCode(string $activationCode): ?array
    {
        $screen = $this->db->row(
            "SELECT * FROM screens WHERE activation_code=? AND activation_expires_at > NOW()",
            [$activationCode]
        );
        if (!$screen) return null;

        $this->db->update('screens', [
            'status'                => 'active',
            'activation_code'       => null,
            'activation_expires_at' => null,
        ], ['id' => $screen['id']]);

        return $this->db->row("SELECT * FROM screens WHERE id=?", [$screen['id']]);
    }

    public function activate(string $activationCode, string $screenCode): ?array
    {
        $screen = $this->db->row(
            "SELECT * FROM screens WHERE activation_code=? AND code=? AND activation_expires_at > NOW()",
            [$activationCode, $screenCode]
        );
        if (!$screen) return null;

        $this->db->update('screens', [
            'status'          => 'active',
            'activation_code' => null,
            'activation_expires_at' => null,
        ], ['id' => $screen['id']]);

        return $screen;
    }

    public function heartbeat(int $id, array $data): void
    {
        $fields = [
            'is_online'    => 1,
            'last_seen_at' => date('Y-m-d H:i:s'),
            'last_ip'      => $data['ip'] ?? null,
        ];

        /* اندازه‌های واقعی دستگاه را نگه می‌داریم.
           هنگام ثبت فقط screen.width ذخیره می‌شد، که اندازه‌ی پنل است
           نه بومِ چیدمان مرورگر. وقتی این دو فرق کنند — که روی
           تلویزیون‌های قدیمی زیاد پیش می‌آید — چیدمانِ تمام‌عرض روی
           بومِ کوچک‌تر رسم می‌شود و بقیه‌ی پنل سیاه می‌ماند. بدون این
           عددها، تشخیص علت از راه دور ممکن نیست. */
        if (!empty($data['metrics']) && is_array($data['metrics'])) {
            $row  = $this->db->row('SELECT device_info FROM screens WHERE id = ?', [$id]);
            $info = json_decode((string)($row['device_info'] ?? '{}'), true);
            if (!is_array($info)) $info = [];

            $info['metrics']    = $data['metrics'];
            $info['metrics_at'] = date('Y-m-d H:i:s');

            $fields['device_info'] = json_encode($info, JSON_UNESCAPED_UNICODE);
        }

        $this->db->update('screens', $fields, ['id' => $id]);

        $this->db->insert('heartbeats', [
            'screen_id'      => $id,
            'ip_address'     => $data['ip'] ?? '0.0.0.0',
            'cpu_usage'      => $data['cpu'] ?? null,
            'memory_usage'   => $data['memory'] ?? null,
            'disk_usage'     => $data['disk'] ?? null,
            'uptime'         => $data['uptime'] ?? null,
            /* پلیرها کلید item می‌فرستند (هر ده پروفایل)، ولی اینجا فقط
               current_item خوانده می‌شد — پس ستون همیشه NULL می‌ماند و
               پنل هیچ‌وقت نمی‌دانست چه آیتمی روی تلویزیون پخش می‌شود. */
            'current_item'   => $data['current_item'] ?? $data['item'] ?? null,
            'player_version' => $data['version'] ?? null,
        ]);
    }

    public function getStats(): array
    {
        return $this->db->row(
            "SELECT COUNT(*) AS total,
             SUM(is_online=1) AS online,
             SUM(is_online=0) AS offline,
             SUM(status='active') AS active,
             SUM(status='error') AS error
             FROM screens WHERE tenant_id=?",
            [$this->tenantId]
        ) ?? [];
    }

    public function getOnlineScreens(): array
    {
        // Mark screens offline if no heartbeat for 2 minutes
        $this->db->query(
            "UPDATE screens SET is_online=0 WHERE is_online=1 AND (last_seen_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE) OR last_seen_at IS NULL) AND tenant_id=?",
            [$this->tenantId]
        );
        return $this->db->rows("SELECT * FROM screens WHERE tenant_id=? AND is_online=1", [$this->tenantId]);
    }

    public function sendCommand(int $id, string $command, mixed $payload = null): void
    {
        // ذخیره command در DB - پلیر در heartbeat دریافت می‌کنه
        $allowed = ['reload','reboot','refresh','screenshot','restart','emergency','clear'];
        if (!in_array($command, $allowed)) {
            // unknown command رو ignore کن نه throw
            error_log("[Screen::sendCommand] Unknown command: $command");
            return;
        }

        // ذخیره command در pending_commands
        try {
            $existing = json_decode(
                $this->db->value("SELECT pending_commands FROM screens WHERE id=?", [$id]) ?? '[]',
                true
            ) ?: [];
            $existing[] = ['command' => $command, 'data' => $payload, 'sent_at' => time()];
            $this->db->update('screens',
                ['pending_commands' => json_encode($existing)],
                ['id' => $id, 'tenant_id' => $this->tenantId]
            );
        } catch (\Throwable $e) {
            // اگه ستون pending_commands نبود، log کن و ادامه بده
            error_log("[sendCommand] " . $e->getMessage());
        }
    }

    /** ستون‌های مشترک هر سه حالت انتخاب پلی‌لیست */
    private const PL_COLS = "p.*,
             COALESCE(p.transition, 'fade') AS transition,
             COALESCE(p.transition_duration, 0.5) AS transition_duration,
             COALESCE(p.shuffle, 0) AS shuffle,
             COALESCE(p.`loop`, 1) AS playlist_loop";

    /**
     * پلی‌لیستی که همین حالا باید روی این صفحه پخش شود.
     *
     * ترتیب اولویت — و دلیلش:
     *   ۱. برنامه‌ی زمان‌بندی‌شده‌ی مخصوص همین صفحه. مشخص‌ترین حالت و
     *      زمان‌آگاه است، پس بر همه مقدم است.
     *   ۲. پلی‌لیستی که مستقیم روی خود صفحه انتخاب شده
     *      (`current_playlist_id`). تا پیش از این، این ستون خوانده
     *      **نمی‌شد**: اپراتور پلی‌لیست را روی صفحه ست می‌کرد و هیچ اتفاقی
     *      نمی‌افتاد، چون فقط جدول schedules ملاک بود.
     *   ۳. برنامه‌ی زمان‌بندی‌شده‌ی Zone (محل این صفحه در هتل) و بعد
     *      گروه صفحه‌ها. این‌ها از صفحه عمومی‌ترند و از همگانی خاص‌تر،
     *      پس درست همین‌جا می‌نشینند: «همهٔ صفحه‌های لابی» باید
     *      برنامه‌ی «همهٔ هتل» را کنار بزند، ولی نباید پلی‌لیستی را که
     *      اپراتور صریحا روی یک صفحه گذاشته باطل کند.
     *   ۴. برنامه‌ی همگانی (هر سه هدف NULL). عمدا آخر است.
     */
    public function getCurrentPlaylist(int $screenId): ?array
    {
        $row = $this->scheduleFor('s.screen_id = ?', [$screenId]);
        if ($row) return $row;

        $pinned = $this->db->row(
            "SELECT " . self::PL_COLS . "
               FROM screens s
               JOIN playlists p ON p.id = s.current_playlist_id
              WHERE s.id = ? AND p.is_active = 1",
            [$screenId]
        );
        if ($pinned) return $pinned;

        /* یک کوئری برای Zone و گروه با هم: ترتیب بین این دو را priority
           تعیین می‌کند، نه ترتیب اجرای ما — دو کوئری پشت‌سرهم یعنی
           برنامهٔ Zone با priority پایین بر گروهِ با priority بالا مقدم
           می‌شد، که برای اپراتور بی‌معناست. */
        $row = $this->scheduleFor(
            '(s.venue_id = (SELECT venue_id FROM screens WHERE id = ?)
              OR s.group_id = (SELECT group_id FROM screens WHERE id = ?))',
            [$screenId, $screenId]
        );
        if ($row) return $row;

        return $this->scheduleFor('s.screen_id IS NULL AND s.venue_id IS NULL AND s.group_id IS NULL', []);
    }

    /**
     * برنامهٔ فعالِ همین لحظه برای یک هدف مشخص.
     *
     * @param string $match شرط هدف (صفحه، Zone، گروه، یا همگانی)
     * @param array  $params پارامترهای همان شرط
     */
    private function scheduleFor(string $match, array $params): ?array
    {
        $now = date('Y-m-d H:i:s');

        array_push($params, date('Y-m-d'), date('Y-m-d'), $now, $now, json_encode((int)date('w')));

        return $this->db->row(
            "SELECT " . self::PL_COLS . "
             FROM schedules s
             JOIN playlists p ON p.id=s.playlist_id
             WHERE ($match)
             AND (s.start_date IS NULL OR s.start_date <= ?)
             AND (s.end_date IS NULL OR s.end_date >= ?)
             AND (s.start_time IS NULL OR s.start_time <= TIME(?))
             AND (s.end_time IS NULL OR s.end_time >= TIME(?))
             AND s.is_active=1 AND p.is_active=1
             AND (s.weekdays IS NULL OR JSON_CONTAINS(s.weekdays, ?))
             ORDER BY s.priority DESC LIMIT 1",
            $params
        );
    }

    private function generateCode(): string
    {
        do {
            $code = 'SCR' . strtoupper(substr(md5(uniqid('', true)), 0, 5));
        } while ($this->db->value("SELECT id FROM screens WHERE code=?", [$code]));
        return $code;
    }
}
