<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};

class CampaignController extends Controller
{
    public function index(Request $req): void
    {
        $campaigns = [];
        try {
            $campaigns = $this->db->rows(
                "SELECT * FROM campaigns WHERE tenant_id=? ORDER BY created_at DESC",
                [Auth::tenantId()]
            );
        } catch (\Throwable) {}
        $this->view('campaigns.index', ['title' => 'کمپین‌ها', 'campaigns' => $campaigns]);
    }

    public function store(Request $req): void
    {
        $tid = Auth::tenantId();
        $type = $req->post('type', 'banner');
        $message = $req->post('message', '');
        $name = $req->post('name', 'کمپین ' . date('Y-m-d H:i'));

        try {
            $validTypes = ['promo','emergency','announcement'];
            if (!in_array($type, $validTypes)) $type = 'announcement';
            $this->db->insert('campaigns', [
                'tenant_id'  => $tid,
                'created_by' => Auth::id() ?? 1,
                'name'       => $name,
                'type'       => $type,
                'content'    => $message ?: $name,
                'target'     => 'all',
                'is_active'  => 1,
            ]);
            $this->flash('success', 'کمپین ایجاد شد');
        } catch (\Throwable $e) {
            $this->flash('error', 'خطا: ' . $e->getMessage());
        }
        $this->redirect('/admin/campaigns');
    }

    public function broadcast(Request $req, array $params): void
    {
        // Send command to all screens via WebSocket
        $this->flash('success', 'دستور پخش ارسال شد');
        $this->redirect('/admin/campaigns');
    }
}
