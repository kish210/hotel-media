<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};

class ReportController extends Controller
{
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();
        $stats = [
            'screens'   => (int)($this->db->value("SELECT COUNT(*) FROM screens WHERE tenant_id=?",   [$tid]) ?? 0),
            'playlists' => (int)($this->db->value("SELECT COUNT(*) FROM playlists WHERE tenant_id=?", [$tid]) ?? 0),
            'media'     => (int)($this->db->value("SELECT COUNT(*) FROM media WHERE tenant_id=? AND deleted_at IS NULL", [$tid]) ?? 0),
            'users'     => (int)($this->db->value("SELECT COUNT(*) FROM users WHERE tenant_id=? AND deleted_at IS NULL", [$tid]) ?? 0),
        ];
        $this->view('reports.index', ['title' => 'گزارش‌ها', 'stats' => $stats]);
    }
}
