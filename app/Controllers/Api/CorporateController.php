<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Controller, Response, Request, Auth};

class CorporateController extends Controller
{
    private function tid(): int { return Auth::check() ? Auth::tenantId() : 1; }

    public function kpi(Request $req): void
    {
        Response::success($this->db->rows("SELECT * FROM corp_kpi WHERE tenant_id=? AND is_active=1 ORDER BY sort_order,id", [$this->tid()]));
    }

    public function storeKpi(Request $req): void
    {
        $errors = $req->validate(['name' => 'required', 'value' => 'required']);
        if ($errors) Response::error('نامعتبر', 422, $errors);
        $data = array_merge($req->input(), ['tenant_id' => $this->tid()]); unset($data['_token']);
        $id = $this->db->insert('corp_kpi', $data);
        Response::success($this->db->row("SELECT * FROM corp_kpi WHERE id=?", [$id]), 'KPI ثبت شد', 201);
    }

    public function updateKpi(Request $req, array $params): void
    {
        $data = $req->input(); unset($data['_token']);
        $this->db->update('corp_kpi', $data, ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'به‌روز شد');
    }

    public function deleteKpi(Request $req, array $params): void
    {
        $this->db->update('corp_kpi', ['is_active' => 0], ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'حذف شد');
    }

    public function news(Request $req): void
    {
        Response::success($this->db->rows(
            "SELECT * FROM corp_news WHERE tenant_id=? AND is_active=1
             AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY is_pinned DESC, priority DESC, published_at DESC LIMIT 30",
            [$this->tid()]
        ));
    }

    public function storeNews(Request $req): void
    {
        $errors = $req->validate(['title' => 'required']);
        if ($errors) Response::error('نامعتبر', 422, $errors);
        $data = array_merge($req->input(), ['tenant_id' => $this->tid()]); unset($data['_token']);
        $id = $this->db->insert('corp_news', $data);
        Response::success($this->db->row("SELECT * FROM corp_news WHERE id=?", [$id]), 'خبر ثبت شد', 201);
    }

    public function updateNews(Request $req, array $params): void
    {
        $data = $req->input(); unset($data['_token']);
        $this->db->update('corp_news', $data, ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'به‌روز شد');
    }

    public function deleteNews(Request $req, array $params): void
    {
        $this->db->update('corp_news', ['is_active' => 0], ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'حذف شد');
    }

    public function departments(Request $req): void
    {
        Response::success($this->db->rows("SELECT * FROM corp_departments WHERE tenant_id=? AND is_active=1 ORDER BY sort_order,id", [$this->tid()]));
    }

    public function storeDept(Request $req): void
    {
        $errors = $req->validate(['name' => 'required']);
        if ($errors) Response::error('نامعتبر', 422, $errors);
        $data = array_merge($req->input(), ['tenant_id' => $this->tid()]); unset($data['_token']);
        $id = $this->db->insert('corp_departments', $data);
        Response::success($this->db->row("SELECT * FROM corp_departments WHERE id=?", [$id]), 'دپارتمان ثبت شد', 201);
    }

    public function updateDept(Request $req, array $params): void
    {
        $data = $req->input(); unset($data['_token']);
        $this->db->update('corp_departments', $data, ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'به‌روز شد');
    }

    public function deleteDept(Request $req, array $params): void
    {
        $this->db->update('corp_departments', ['is_active' => 0], ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'حذف شد');
    }
}
