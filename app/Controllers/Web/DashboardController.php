<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};
use App\Models\Screen;

class DashboardController extends Controller
{
    public function index(Request $req): void
    {
        $tid    = Auth::tenantId();
        $screen = new Screen();

        $screenStats = $screen->getStats();
        $mediaCount  = (int)$this->db->value("SELECT COUNT(*) FROM media WHERE tenant_id=? AND deleted_at IS NULL", [$tid]);

        $stats = array_merge($screenStats ?? [], ['media' => $mediaCount]);

        $online = $screen->getOnlineScreens();

        $recent = [];
        try {
            $recent = $this->db->rows(
                "SELECT al.*, u.name AS user_name FROM activity_logs al
                 LEFT JOIN users u ON u.id=al.user_id
                 WHERE al.tenant_id=? ORDER BY al.created_at DESC LIMIT 15",
                [$tid]
            );
        } catch (\Throwable) {}

        $notifications = [];
        try {
            $notifications = $this->db->rows(
                "SELECT * FROM notifications WHERE tenant_id=? AND read_at IS NULL ORDER BY created_at DESC LIMIT 10",
                [$tid]
            );
        } catch (\Throwable) {}

        $storageUsed  = (int)($this->db->value("SELECT SUM(file_size) FROM media WHERE tenant_id=? AND deleted_at IS NULL", [$tid]) ?? 0);
        $storageLimit = (int)($this->db->value("SELECT storage_limit FROM tenants WHERE id=?", [$tid]) ?? 5368709120);

        $this->view('dashboard.index', [
            'title'          => 'داشبورد',
            'stats'          => $stats,
            'online_screens' => $online,
            'recent_logs'    => $recent,
            'notifications'  => $notifications,
            'storage_used'   => $storageUsed,
            'storage_limit'  => $storageLimit,
        ]);
    }

    public function stats(Request $req): void
    {
        $tid = \App\Core\Auth::tenantId();
        $stats = [
            'screens_online'  => (int)$this->db->value("SELECT COUNT(*) FROM screens WHERE tenant_id=? AND is_online=1 AND status='active'", [$tid]),
            'screens_total'   => (int)$this->db->value("SELECT COUNT(*) FROM screens WHERE tenant_id=? AND status!='inactive'", [$tid]),
            'playlists'       => (int)$this->db->value("SELECT COUNT(*) FROM playlists WHERE tenant_id=? AND is_active=1", [$tid]),
            'media_files'     => (int)$this->db->value("SELECT COUNT(*) FROM media WHERE tenant_id=? AND deleted_at IS NULL", [$tid]),
        ];
        \App\Core\Response::json(['success'=>true,'data'=>$stats]);
    }
}