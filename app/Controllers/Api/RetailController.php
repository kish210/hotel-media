<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Controller, Response, Request, Auth};

class RetailController extends Controller
{
    private function tid(): int { return Auth::check() ? Auth::tenantId() : 1; }

    public function products(Request $req): void
    {
        $sql = "SELECT * FROM retail_products WHERE tenant_id=? AND is_active=1";
        $p   = [$this->tid()];
        if ($req->get('offer'))    { $sql .= " AND is_offer=1 AND (offer_ends IS NULL OR offer_ends>NOW())"; }
        if ($req->get('featured')) { $sql .= " AND is_featured=1"; }
        if ($req->get('category')) { $sql .= " AND category=?"; $p[] = $req->get('category'); }
        $sql .= " ORDER BY sort_order,id";
        Response::success($this->db->rows($sql, $p));
    }

    public function storeProduct(Request $req): void
    {
        $errors = $req->validate(['name' => 'required', 'price' => 'required|numeric', 'category' => 'required']);
        if ($errors) Response::error('نامعتبر', 422, $errors);
        $data = array_merge($req->input(), ['tenant_id' => $this->tid()]); unset($data['_token']);
        $id = $this->db->insert('retail_products', $data);
        Response::success($this->db->row("SELECT * FROM retail_products WHERE id=?", [$id]), 'محصول ثبت شد', 201);
    }

    public function updateProduct(Request $req, array $params): void
    {
        $data = $req->input(); unset($data['_token']);
        $this->db->update('retail_products', $data, ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'به‌روز شد');
    }

    public function deleteProduct(Request $req, array $params): void
    {
        $this->db->update('retail_products', ['is_active' => 0], ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'حذف شد');
    }

    public function queue(Request $req): void
    {
        $counter = $req->get('counter', '');
        $latest  = $this->db->row(
            "SELECT q.*, (SELECT COUNT(*) FROM retail_queue WHERE tenant_id=? AND status='waiting') AS waiting
             FROM retail_queue q WHERE q.tenant_id=? AND q.counter=? ORDER BY q.called_at DESC LIMIT 1",
            [$this->tid(), $this->tid(), $counter]
        );
        Response::success($latest ? ['current' => $latest['ticket_number'], 'waiting' => $latest['waiting']] : null);
    }

    public function callNext(Request $req): void
    {
        $errors = $req->validate(['counter' => 'required', 'ticket_number' => 'required|numeric']);
        if ($errors) Response::error('نامعتبر', 422, $errors);
        $id = $this->db->insert('retail_queue', [
            'tenant_id'     => $this->tid(),
            'counter'       => $req->input('counter'),
            'ticket_number' => (int)$req->input('ticket_number'),
            'status'        => 'serving',
        ]);
        Response::success(['id' => $id], 'نوبت فراخوانده شد', 201);
    }

    public function currency(Request $req): void
    {
        // Mock data — in production connect to a real exchange rate API
        $pairs = array_filter(array_map('trim', explode(',', $req->get('pairs', 'USD,EUR,GBP'))));
        $rates = ['USD' => 580000, 'EUR' => 640000, 'GBP' => 740000, 'AED' => 158000, 'TRY' => 18000, 'CNY' => 80000];
        $data  = [];
        foreach ($pairs as $p) {
            $data[$p]          = $rates[$p] ?? 0;
            $data[$p.'_change']= round(rand(-50, 80) / 10, 1);
        }
        Response::success($data);
    }
}
