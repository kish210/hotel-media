<?php declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Controller, Response, Request, Auth};
use App\Models\Screen;

class DashboardController extends Controller
{
    public function stats(Request $req): void
    {
        $tid = Auth::tenantId();
        $screenModel = new Screen();
        $screens = $screenModel->getStats();
        $playlists = $this->db->value("SELECT COUNT(*) FROM playlists WHERE tenant_id=? AND is_active=1",[$tid]);
        $schedules = $this->db->value("SELECT COUNT(*) FROM schedules WHERE tenant_id=? AND is_active=1",[$tid]);
        $storage   = $this->db->row("SELECT SUM(file_size) AS used FROM media WHERE tenant_id=? AND deleted_at IS NULL",[$tid]);
        $tenant    = $this->db->row("SELECT storage_limit FROM tenants WHERE id=?",[$tid]);
        $used      = (int)($storage['used']??0);
        $limit     = (int)($tenant['storage_limit']??5368709120);
        Response::success([
            'screens'   => $screens,
            'playlists' => (int)$playlists,
            'schedules' => (int)$schedules,
            'storage'   => ['used'=>$used,'limit'=>$limit,'percent'=>$limit?round($used/$limit*100,1):0],
        ]);
    }
}
