<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};

class CorporateWebController extends Controller
{
    private function tid(): int { return Auth::tenantId(); }

    public function index(Request $req): void
    {
        $kpis  = $this->db->rows("SELECT * FROM corp_kpi WHERE tenant_id=? AND is_active=1 ORDER BY sort_order", [$this->tid()]);
        $news  = $this->db->rows("SELECT * FROM corp_news WHERE tenant_id=? AND is_active=1 ORDER BY is_pinned DESC,published_at DESC", [$this->tid()]);
        $depts = $this->db->rows("SELECT * FROM corp_departments WHERE tenant_id=? AND is_active=1 ORDER BY sort_order", [$this->tid()]);
        $this->view('admin.modules.corporate', compact('kpis','news','depts') + ['title' => 'مدیریت اطلاع‌رسانی سازمانی']);
    }

    public function storeKpi(Request $req): void
    {
        $data = array_merge($req->post(), ['tenant_id' => $this->tid()]); unset($data['_token']);
        $this->db->insert('corp_kpi', $data);
        $this->flash('success', 'KPI ثبت شد');
        $this->redirect('/admin/modules/corporate');
    }

    public function updateKpi(Request $req, array $params): void
    {
        $data = $req->post(); unset($data['_token']);
        $this->db->update('corp_kpi', $data, ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        $this->flash('success', 'KPI به‌روز شد');
        $this->redirect('/admin/modules/corporate');
    }

    public function storeNews(Request $req): void
    {
        $data = array_merge($req->post(), ['tenant_id' => $this->tid(), 'is_active' => 1]); unset($data['_token']);
        $data['is_pinned'] = isset($data['is_pinned']) ? 1 : 0;
        $this->db->insert('corp_news', $data);
        $this->flash('success', 'خبر ثبت شد');
        $this->redirect('/admin/modules/corporate');
    }
}
