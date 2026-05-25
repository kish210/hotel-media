<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};

class IptvMenuWebController extends Controller
{
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();

        // گروه‌های IPTV
        $iptvGroups = [];
        try {
            $iptvGroups = $this->db->rows(
                "SELECT * FROM screen_groups WHERE tenant_id=? AND type='iptv' AND is_active=1 ORDER BY sort_order, name",
                [$tid]
            ) ?: [];
        } catch (\Throwable $e) {}

        // همه منوها با تعداد آیتم
        $allMenus = [];
        try {
            $allMenus = $this->db->rows(
                "SELECT m.*, g.name AS group_name,
                        COUNT(i.id) AS item_count
                 FROM iptv_menus m
                 LEFT JOIN screen_groups g  ON g.id = m.group_id
                 LEFT JOIN iptv_menu_items i ON i.menu_id = m.id AND i.is_active = 1
                 WHERE m.tenant_id = ?
                 GROUP BY m.id
                 ORDER BY m.sort_order, m.name",
                [$tid]
            ) ?: [];
        } catch (\Throwable $e) {}

        $this->view('admin.iptv.menus', [
            'title'      => 'منوهای IPTV',
            'iptvGroups' => $iptvGroups,
            'allMenus'   => $allMenus,
        ]);
    }
}
