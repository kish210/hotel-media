<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};

class IptvRoomWebController extends Controller
{
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();

        $iptvGroups = $this->db->rows(
            "SELECT * FROM screen_groups WHERE tenant_id=? AND type='iptv' AND is_active=1 ORDER BY sort_order, name",
            [$tid]
        ) ?: [];

        $rooms = $this->db->rows(
            "SELECT r.*, g.name AS group_name,
                    s.name  AS screen_name, s.code AS screen_code,
                    (SELECT COUNT(*) FROM iptv_room_messages m
                     WHERE (m.room_id=r.id OR m.room_id IS NULL) AND m.tenant_id=r.tenant_id
                       AND m.is_active=1 AND (m.expires_at IS NULL OR m.expires_at > NOW())) AS active_msgs
             FROM iptv_rooms r
             LEFT JOIN screen_groups g ON g.id = r.group_id
             LEFT JOIN screens       s ON s.iptv_room_id = r.id AND s.tenant_id = r.tenant_id
             WHERE r.tenant_id = ?
             ORDER BY r.floor ASC, r.room_number ASC",
            [$tid]
        ) ?: [];

        $pmsIntegrations = $this->db->rows(
            'SELECT id, name, api_key, pms_type, is_active, last_used_at FROM pms_integrations WHERE tenant_id=? ORDER BY created_at DESC',
            [$tid]
        ) ?: [];

        $this->view('admin.iptv.rooms', [
            'title'           => 'مدیریت اتاق‌های IPTV',
            'iptvGroups'      => $iptvGroups,
            'rooms'           => $rooms,
            'pmsIntegrations' => $pmsIntegrations,
        ]);
    }
}
