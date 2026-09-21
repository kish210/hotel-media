<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};
use App\Services\DeviceService;

/**
 * پنل مدیریت دستگاه‌ها — چیزی که IT هتل جلویش می‌نشیند.
 * فاز ۴ نقشه‌راه — docs/TODO.md (۵.۴)
 */
class DeviceWebController extends Controller
{
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();

        $devices = $this->db->rows(
            "SELECT s.*, r.room_number, r.room_name, r.floor, g.name AS group_name,
                    TIMESTAMPDIFF(SECOND, s.last_seen_at, NOW()) AS seconds_ago,
                    (SELECT COUNT(*) FROM screen_commands c
                      WHERE c.screen_id = s.id AND c.status IN ('pending','sent')) AS pending_commands
               FROM screens s
               LEFT JOIN iptv_rooms    r ON r.id = s.iptv_room_id
               LEFT JOIN screen_groups g ON g.id = s.group_id
              WHERE s.tenant_id = ?
              ORDER BY r.floor IS NULL, r.floor, r.room_number, s.name",
            [$tid]
        ) ?: [];

        $tokens = $this->db->rows(
            'SELECT t.*, g.name AS group_name, m.name AS menu_name
               FROM enrollment_tokens t
               LEFT JOIN screen_groups g ON g.id = t.group_id
               LEFT JOIN iptv_menus    m ON m.id = t.menu_id
              WHERE t.tenant_id = ? AND t.is_active = 1
              ORDER BY t.id DESC',
            [$tid]
        ) ?: [];

        $rooms = $this->db->rows(
            'SELECT id, room_number, room_name, floor FROM iptv_rooms
              WHERE tenant_id = ? ORDER BY floor, room_number',
            [$tid]
        ) ?: [];

        $groups = $this->db->rows(
            'SELECT id, name FROM screen_groups WHERE tenant_id = ? AND is_active = 1 ORDER BY name',
            [$tid]
        ) ?: [];

        $menus = $this->db->rows(
            'SELECT id, name FROM iptv_menus WHERE tenant_id = ? AND is_active = 1 ORDER BY sort_order, name',
            [$tid]
        ) ?: [];

        $online = 0;
        foreach ($devices as &$d) {
            $d['online'] = $d['seconds_ago'] !== null && (int)$d['seconds_ago'] <= 90;
            if ($d['online']) $online++;
        }
        unset($d);

        $stats = [
            'total'   => count($devices),
            'online'  => $online,
            'offline' => count($devices) - $online,
            'pending' => count(array_filter($devices, static fn($d) => $d['status'] === 'pending')),
        ];

        // آدرسی که تکنسین در منوی مخفی تلویزیون وارد می‌کند
        $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme    = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
        $portalUrl = "$scheme://$host/tv";

        $this->view('admin.devices.index', [
            'title'        => 'مدیریت تلویزیون‌ها',
            'devices'      => $devices,
            'tokens'       => $tokens,
            'rooms'        => $rooms,
            'groups'       => $groups,
            'menus'        => $menus,
            'stats'        => $stats,
            'portalUrl'    => $portalUrl,
            'capabilities' => DeviceService::CAPABILITIES,
        ]);
    }

    /** GET /admin/devices/feed — به‌روزرسانی خودکار وضعیت زنده */
    public function feed(Request $req): void
    {
        $tid = Auth::tenantId();

        $rows = $this->db->rows(
            "SELECT s.id, s.code, s.status, s.platform, s.app_version, s.last_ip,
                    TIMESTAMPDIFF(SECOND, s.last_seen_at, NOW()) AS seconds_ago,
                    (SELECT COUNT(*) FROM screen_commands c
                      WHERE c.screen_id = s.id AND c.status IN ('pending','sent')) AS pending_commands
               FROM screens s WHERE s.tenant_id = ?",
            [$tid]
        ) ?: [];

        $online = 0;
        foreach ($rows as &$r) {
            $r['online'] = $r['seconds_ago'] !== null && (int)$r['seconds_ago'] <= 90;
            if ($r['online']) $online++;
        }
        unset($r);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'online'  => $online,
            'total'   => count($rows),
            'data'    => $rows,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
