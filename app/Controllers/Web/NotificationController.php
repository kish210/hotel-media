<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth, Response};

class NotificationController extends Controller
{
    public function index(Request $req): void
    {
        $tid   = Auth::tenantId();
        $notifs = $this->db->rows("SELECT * FROM notifications WHERE tenant_id=? ORDER BY created_at DESC LIMIT 50", [$tid]);
        $this->view('admin.notifications', compact('notifs') + ['title' => 'اعلان‌ها']);
    }
    public function markAllRead(Request $req): void
    {
        $this->db->query("UPDATE notifications SET is_read=1 WHERE tenant_id=?", [Auth::tenantId()]);
        Response::json(['success'=>true]);
    }
}
