<?php declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Controller, Response, Request, Auth};

class NotificationController extends Controller
{
    public function index(Request $req): void
    {
        $rows = $this->db->rows("SELECT * FROM notifications WHERE tenant_id=? ORDER BY created_at DESC LIMIT 50",[Auth::tenantId()]);
        Response::success($rows);
    }

    public function markRead(Request $req, array $params): void
    {
        $this->db->update('notifications',['read_at'=>date('Y-m-d H:i:s')],['id'=>(int)$params['id'],'tenant_id'=>Auth::tenantId()]);
        Response::success(null,'خوانده شد');
    }
}
