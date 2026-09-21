<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\DeviceService;

/**
 * Device Controller
 * سمت دستگاه: ثبت خودکار، دریافت فرمان، گزارش اجرا.
 * سمت پنل: لیست زنده، تایید، فرمان تکی و گروهی، توکن ثبت.
 *
 * فاز ۴ نقشه‌راه — docs/TODO.md (۵.۴)
 */
class DeviceController extends Controller
{
    /** سقف دستگاه در یک فرمان گروهی — جلوی خطای انسانی روی کل هتل */
    private const MAX_BULK = 500;

    private DeviceService $svc;

    public function __construct()
    {
        parent::__construct();
        $this->svc = new DeviceService($this->db);
    }

    // ══════════════════════════════════════════════════════════════
    //  سمت دستگاه (بدون JWT)
    // ══════════════════════════════════════════════════════════════

    /**
     * POST /api/v1/device/enroll
     * تلویزیون با توکن ثبت خودش را معرفی می‌کند.
     * body: { token, mac, model, firmware, serial, platform, app_version, resolution, name }
     */
    public function enroll(Request $req): void
    {
        $data  = $req->json() ?: $req->post() ?: [];
        $token = trim((string)($data['token'] ?? ''));

        if ($token === '') { Response::error('توکن ثبت الزامی است', 422); return; }

        $data['user_agent'] = $req->userAgent();
        $result = $this->svc->enroll($token, $data);

        if (!$result['ok']) {
            // توکن نامعتبر نباید با ۴۰۰ عمومی قاطی شود — دستگاه باید بفهمد
            // دوباره تلاش بی‌فایده است یا باید صبر کند.
            Response::error($result['message'], $result['status'] === 'invalid' ? 403 : 409);
            return;
        }

        $screen = $result['screen'];

        Response::success([
            'code'        => $screen['code'],
            'name'        => $screen['name'],
            'status'      => $result['status'],   // approved | pending
            'screen_type' => $screen['screen_type'] ?? 'iptv',
            'portal_url'  => '/player/' . $screen['code'],
        ], $result['message'], 201);
    }

    /**
     * GET /api/v1/device/{code}/commands
     * دستگاه فرمان‌های منتظر را می‌گیرد. هر بار که خوانده شوند sent می‌شوند.
     */
    public function commands(Request $req, array $params): void
    {
        $screen = $this->findByCode((string)($params['code'] ?? ''));
        if (!$screen) { Response::notFound('دستگاه یافت نشد'); return; }

        Response::success([
            'approved' => ($screen['status'] ?? '') === 'active',
            'commands' => $this->svc->pullCommands((int)$screen['id']),
        ]);
    }

    /**
     * POST /api/v1/device/{code}/ack
     * body: { id, ok, result }
     */
    public function ack(Request $req, array $params): void
    {
        $screen = $this->findByCode((string)($params['code'] ?? ''));
        if (!$screen) { Response::notFound('دستگاه یافت نشد'); return; }

        $data = $req->json() ?: $req->post() ?: [];
        $id   = (int)($data['id'] ?? 0);
        if (!$id) { Response::error('شناسه فرمان لازم است', 422); return; }

        $ok = $this->svc->ack(
            (int)$screen['id'],
            $id,
            filter_var($data['ok'] ?? true, FILTER_VALIDATE_BOOLEAN),
            (string)($data['result'] ?? '')
        );

        if (!$ok) { Response::error('فرمان یافت نشد یا قبلا بسته شده', 404); return; }
        Response::success(null, 'ثبت شد');
    }

    // ══════════════════════════════════════════════════════════════
    //  سمت پنل (JWT یا session)
    // ══════════════════════════════════════════════════════════════

    /** GET /devices — لیست زنده */
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();

        $sql = "SELECT s.id, s.name, s.code, s.status, s.screen_type, s.platform,
                       s.model, s.firmware, s.app_version, s.mac_address, s.serial_number,
                       s.last_ip, s.last_seen_at, s.enrolled_at, s.volume, s.brightness,
                       s.group_id, s.iptv_menu_id,
                       r.room_number, r.room_name, r.floor,
                       g.name AS group_name,
                       TIMESTAMPDIFF(SECOND, s.last_seen_at, NOW()) AS seconds_ago,
                       (SELECT COUNT(*) FROM screen_commands c
                         WHERE c.screen_id = s.id AND c.status IN ('pending','sent')) AS pending_commands
                  FROM screens s
                  LEFT JOIN iptv_rooms   r ON r.id = s.iptv_room_id
                  LEFT JOIN screen_groups g ON g.id = s.group_id
                 WHERE s.tenant_id = ?";
        $params = [$tid];

        if ($p = $req->get('platform')) {
            if (!in_array($p, DeviceService::PLATFORMS, true)) { Response::error('پلتفرم نامعتبر است', 422); return; }
            $sql .= ' AND s.platform = ?'; $params[] = $p;
        }
        if ($st = $req->get('status')) {
            $sql .= ' AND s.status = ?'; $params[] = $st;
        }
        if (($online = $req->get('online')) !== null && $online !== '') {
            // آستانه‌ی آنلاین بودن: ۹۰ ثانیه از آخرین heartbeat
            $sql .= filter_var($online, FILTER_VALIDATE_BOOLEAN)
                ? ' AND s.last_seen_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)'
                : ' AND (s.last_seen_at IS NULL OR s.last_seen_at < DATE_SUB(NOW(), INTERVAL 90 SECOND))';
        }
        if ($floor = $req->get('floor')) {
            $sql .= ' AND r.floor = ?'; $params[] = (int)$floor;
        }
        if ($q = trim((string)$req->get('q', ''))) {
            $sql .= ' AND (s.name LIKE ? OR s.code LIKE ? OR s.mac_address LIKE ?
                           OR s.model LIKE ? OR r.room_number LIKE ?)';
            $like   = "%$q%";
            $params = array_merge($params, [$like, $like, $like, $like, $like]);
        }

        $sql .= ' ORDER BY r.floor IS NULL, r.floor, r.room_number, s.name';

        $rows = $this->db->rows($sql, $params);
        foreach ($rows as &$row) {
            $row['online']       = $row['seconds_ago'] !== null && (int)$row['seconds_ago'] <= 90;
            $row['capabilities'] = DeviceService::CAPABILITIES[$row['platform']] ?? [];
        }
        unset($row);

        Response::success($rows);
    }

    /** GET /devices/stats */
    public function stats(Request $req): void
    {
        $tid = Auth::tenantId();

        Response::success([
            'total'    => (int)$this->db->value('SELECT COUNT(*) FROM screens WHERE tenant_id=?', [$tid]),
            'online'   => (int)$this->db->value(
                'SELECT COUNT(*) FROM screens WHERE tenant_id=? AND last_seen_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)',
                [$tid]
            ),
            'pending'  => (int)$this->db->value("SELECT COUNT(*) FROM screens WHERE tenant_id=? AND status='pending'", [$tid]),
            'by_platform' => $this->db->rows(
                'SELECT platform, COUNT(*) AS n FROM screens WHERE tenant_id=? GROUP BY platform ORDER BY n DESC',
                [$tid]
            ),
            'queued_commands' => (int)$this->db->value(
                "SELECT COUNT(*) FROM screen_commands WHERE tenant_id=? AND status IN ('pending','sent')",
                [$tid]
            ),
        ]);
    }

    /** POST /devices/{id}/approve */
    public function approve(Request $req, array $params): void
    {
        $tid    = Auth::tenantId();
        $id     = (int)($params['id'] ?? 0);
        $screen = $this->db->row('SELECT * FROM screens WHERE id=? AND tenant_id=?', [$id, $tid]);

        if (!$screen) { Response::notFound('دستگاه یافت نشد'); return; }

        $this->db->update('screens', ['status' => 'active'], ['id' => $id, 'tenant_id' => $tid]);
        $this->svc->logEvent($tid, $id, 'approved', 'تایید توسط ' . (Auth::user()['name'] ?? 'مدیر'));
        $this->log('device.approve', 'screen', $id);

        Response::success(null, 'دستگاه تایید شد');
    }

    /**
     * POST /devices/{id}/assign-room
     * body: { room_id }  — room_id خالی یعنی جدا کردن از اتاق
     */
    public function assignRoom(Request $req, array $params): void
    {
        $tid    = Auth::tenantId();
        $id     = (int)($params['id'] ?? 0);
        $screen = $this->db->row('SELECT id FROM screens WHERE id=? AND tenant_id=?', [$id, $tid]);

        if (!$screen) { Response::notFound('دستگاه یافت نشد'); return; }

        $data   = $req->json() ?: [];
        $roomId = (int)($data['room_id'] ?? 0);

        if ($roomId) {
            if (!$this->db->exists('iptv_rooms', ['id' => $roomId, 'tenant_id' => $tid])) {
                Response::error('اتاق انتخابی معتبر نیست', 422); return;
            }
            // یک اتاق نباید دو تلویزیون فعال داشته باشد که هر دو خود را «TV اتاق» بدانند
            $taken = $this->db->row(
                'SELECT id, name FROM screens WHERE tenant_id=? AND iptv_room_id=? AND id<>?',
                [$tid, $roomId, $id]
            );
            if ($taken) {
                Response::error("این اتاق قبلا به «{$taken['name']}» متصل است", 409); return;
            }
        }

        $this->db->update('screens', ['iptv_room_id' => $roomId ?: null], ['id' => $id, 'tenant_id' => $tid]);
        $this->log('device.assign_room', 'screen', $id, [], ['room_id' => $roomId]);

        Response::success(null, $roomId ? 'اتاق تخصیص داده شد' : 'از اتاق جدا شد');
    }

    /**
     * POST /devices/{id}/command
     * body: { command, payload }
     */
    public function command(Request $req, array $params): void
    {
        $tid    = Auth::tenantId();
        $id     = (int)($params['id'] ?? 0);
        $screen = $this->db->row('SELECT * FROM screens WHERE id=? AND tenant_id=?', [$id, $tid]);

        if (!$screen) { Response::notFound('دستگاه یافت نشد'); return; }

        $data    = $req->json() ?: [];
        $command = trim((string)($data['command'] ?? ''));
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        $res = $this->svc->queue($screen, $command, $payload, Auth::user()['id'] ?? null);
        if (!$res['ok']) { Response::error($res['message'], 422); return; }

        $this->log('device.command', 'screen', $id, [], ['command' => $command]);
        Response::success(['command_id' => $res['id']], $res['message']);
    }

    /**
     * POST /devices/bulk-command — همان فرمان روی چند دستگاه
     * body: { screen_ids: [...] | filter: {platform,floor,group_id}, command, payload }
     */
    public function bulkCommand(Request $req): void
    {
        $tid     = Auth::tenantId();
        $data    = $req->json() ?: [];
        $command = trim((string)($data['command'] ?? ''));
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        if ($command === '') { Response::error('نام فرمان لازم است', 422); return; }

        $screens = $this->resolveBulkTargets($tid, $data);
        if (!$screens) { Response::error('هیچ دستگاهی با این شرایط پیدا نشد', 422); return; }
        if (count($screens) > self::MAX_BULK) {
            Response::error('تعداد دستگاه‌ها بیش از حد مجاز است (' . self::MAX_BULK . ')', 422); return;
        }

        $userId  = Auth::user()['id'] ?? null;
        $queued  = 0;
        $skipped = [];

        foreach ($screens as $screen) {
            $res = $this->svc->queue($screen, $command, $payload, $userId);
            if ($res['ok']) { $queued++; continue; }

            // دستگاه‌هایی که پلتفرمشان این فرمان را ندارد، به‌جای شکست کل
            // عملیات، جداگانه گزارش می‌شوند
            $skipped[] = ['code' => $screen['code'], 'reason' => $res['message']];
        }

        $this->log('device.bulk_command', null, null, [], ['command' => $command, 'queued' => $queued]);

        Response::success([
            'queued'  => $queued,
            'skipped' => array_slice($skipped, 0, 50),
            'total'   => count($screens),
        ], "$queued دستگاه فرمان گرفت" . ($skipped ? '، ' . count($skipped) . ' دستگاه پشتیبانی نمی‌کند' : ''));
    }

    /** GET /devices/{id}/history — فرمان‌ها و رویدادهای اخیر */
    public function history(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        if (!$this->db->exists('screens', ['id' => $id, 'tenant_id' => $tid])) {
            Response::notFound('دستگاه یافت نشد'); return;
        }

        Response::success([
            'commands' => $this->db->rows(
                'SELECT c.id, c.command, c.payload, c.status, c.result, c.created_at, c.acked_at,
                        u.name AS issued_by_name
                   FROM screen_commands c
                   LEFT JOIN users u ON u.id = c.issued_by
                  WHERE c.screen_id = ? ORDER BY c.id DESC LIMIT 50',
                [$id]
            ),
            'events' => $this->db->rows(
                'SELECT event, detail, created_at FROM screen_events
                  WHERE screen_id = ? ORDER BY id DESC LIMIT 50',
                [$id]
            ),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  توکن ثبت
    // ══════════════════════════════════════════════════════════════

    public function tokens(Request $req): void
    {
        $tid = Auth::tenantId();

        Response::success($this->db->rows(
            'SELECT t.*, g.name AS group_name, m.name AS menu_name
               FROM enrollment_tokens t
               LEFT JOIN screen_groups g ON g.id = t.group_id
               LEFT JOIN iptv_menus    m ON m.id = t.menu_id
              WHERE t.tenant_id = ?
              ORDER BY t.id DESC',
            [$tid]
        ));
    }

    public function storeToken(Request $req): void
    {
        $tid  = Auth::tenantId();
        $data = $req->json() ?: [];

        $label = trim((string)($data['label'] ?? ''));
        if ($label === '') { Response::error('عنوان توکن الزامی است', 422); return; }

        $groupId = (int)($data['group_id'] ?? 0) ?: null;
        if ($groupId && !$this->db->exists('screen_groups', ['id' => $groupId, 'tenant_id' => $tid])) {
            Response::error('گروه انتخابی معتبر نیست', 422); return;
        }

        $menuId = (int)($data['menu_id'] ?? 0) ?: null;
        if ($menuId && !$this->db->exists('iptv_menus', ['id' => $menuId, 'tenant_id' => $tid])) {
            Response::error('منوی انتخابی معتبر نیست', 422); return;
        }

        $days = max(1, min(365, (int)($data['valid_days'] ?? 30)));

        $id = $this->db->insert('enrollment_tokens', [
            'tenant_id'    => $tid,
            'token'        => bin2hex(random_bytes(16)),
            'label'        => mb_substr($label, 0, 120),
            'group_id'     => $groupId,
            'menu_id'      => $menuId,
            'screen_type'  => in_array($data['screen_type'] ?? 'iptv', ['iptv', 'signage'], true)
                                ? $data['screen_type'] : 'iptv',
            'auto_approve' => isset($data['auto_approve']) ? (int)(bool)$data['auto_approve'] : 0,
            'max_devices'  => (int)($data['max_devices'] ?? 0) ?: null,
            'expires_at'   => date('Y-m-d H:i:s', time() + $days * 86400),
        ]);

        $this->log('enrollment_token.create', 'enrollment_token', (int)$id, [], ['label' => $label]);

        Response::success(
            $this->db->row('SELECT * FROM enrollment_tokens WHERE id = ?', [(int)$id]),
            'توکن ثبت ساخته شد',
            201
        );
    }

    public function destroyToken(Request $req, array $params): void
    {
        $tid = Auth::tenantId();
        $id  = (int)($params['id'] ?? 0);

        if (!$this->db->exists('enrollment_tokens', ['id' => $id, 'tenant_id' => $tid])) {
            Response::notFound('توکن یافت نشد'); return;
        }

        // غیرفعال می‌شود نه حذف — تاریخچه‌ی اینکه هر دستگاه با کدام توکن آمده حفظ شود
        $this->db->update('enrollment_tokens', ['is_active' => 0], ['id' => $id, 'tenant_id' => $tid]);
        $this->log('enrollment_token.disable', 'enrollment_token', $id);

        Response::success(null, 'توکن غیرفعال شد');
    }

    // ══════════════════════════════════════════════════════════════
    //  Helpers
    // ══════════════════════════════════════════════════════════════

    /** @return array<string,mixed>|null */
    private function findByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') return null;

        return $this->db->row('SELECT * FROM screens WHERE code = ?', [$code]) ?: null;
    }

    /**
     * هدف‌های فرمان گروهی: یا فهرست صریح شناسه، یا فیلتر.
     * @return list<array<string,mixed>>
     */
    private function resolveBulkTargets(int $tenantId, array $data): array
    {
        $ids = $data['screen_ids'] ?? null;

        if (is_array($ids) && $ids) {
            $ids = array_values(array_filter(array_map('intval', $ids), static fn($n) => $n > 0));
            if (!$ids) return [];

            $ph = implode(',', array_fill(0, count($ids), '?'));
            return $this->db->rows(
                "SELECT * FROM screens WHERE tenant_id = ? AND id IN ($ph)",
                array_merge([$tenantId], $ids)
            );
        }

        $filter = is_array($data['filter'] ?? null) ? $data['filter'] : [];
        $sql    = 'SELECT s.* FROM screens s LEFT JOIN iptv_rooms r ON r.id = s.iptv_room_id
                    WHERE s.tenant_id = ?';
        $params = [$tenantId];

        if (!empty($filter['platform'])) {
            if (!in_array($filter['platform'], DeviceService::PLATFORMS, true)) return [];
            $sql .= ' AND s.platform = ?'; $params[] = $filter['platform'];
        }
        if (!empty($filter['group_id'])) { $sql .= ' AND s.group_id = ?'; $params[] = (int)$filter['group_id']; }
        if (isset($filter['floor']) && $filter['floor'] !== '') {
            $sql .= ' AND r.floor = ?'; $params[] = (int)$filter['floor'];
        }
        if (!empty($filter['online'])) {
            $sql .= ' AND s.last_seen_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)';
        }

        // فیلتر خالی یعنی «همه‌ی هتل» — باید صریح خواسته شده باشد
        if (count($params) === 1 && empty($filter['all'])) return [];

        return $this->db->rows($sql, $params);
    }
}
