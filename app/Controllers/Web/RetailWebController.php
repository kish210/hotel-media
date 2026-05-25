<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};

class RetailWebController extends Controller
{
    private function tid(): int { return Auth::tenantId(); }

    public function index(Request $req): void
    {
        $products = $this->db->rows("SELECT * FROM retail_products WHERE tenant_id=? ORDER BY category,sort_order,id", [$this->tid()]);
        $this->view('admin.modules.retail', ['title' => 'مدیریت فروشگاه', 'products' => $products]);
    }

    public function storeProduct(Request $req): void
    {
        $data = array_merge($req->post(), ['tenant_id' => $this->tid(), 'is_active' => 1]); unset($data['_token']);
        $data['is_offer']    = isset($data['is_offer'])    ? 1 : 0;
        $data['is_featured'] = isset($data['is_featured']) ? 1 : 0;
        $this->db->insert('retail_products', $data);
        $this->flash('success', 'محصول ثبت شد');
        $this->redirect('/admin/modules/retail');
    }

    public function updateProduct(Request $req, array $params): void
    {
        $data = $req->post(); unset($data['_token']);
        $data['is_offer']    = isset($data['is_offer'])    ? 1 : 0;
        $data['is_featured'] = isset($data['is_featured']) ? 1 : 0;
        $this->db->update('retail_products', $data, ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        $this->flash('success', 'محصول به‌روز شد');
        $this->redirect('/admin/modules/retail');
    }
}
