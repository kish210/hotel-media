<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth, Response};

class MenuController extends Controller
{
    private function tid(): int { return Auth::tenantId(); }
    private function back(): void { $this->redirect('/admin/modules/menu'); }
    // همچنین مسیر قدیمی /admin/menu هم redirect می‌شه

    public function index(Request $req): void
    {
        $tid        = $this->tid();
        $categories = $this->db->rows(
            "SELECT * FROM menu_categories WHERE tenant_id=? ORDER BY sort_order, name",
            [$tid]
        ) ?: [];
        $items = $this->db->rows(
            "SELECT mi.*, mc.name AS cat_name
             FROM menu_items mi
             LEFT JOIN menu_categories mc ON mc.id = mi.category_id
             WHERE mi.tenant_id=?
             ORDER BY mc.sort_order, mi.sort_order, mi.name",
            [$tid]
        ) ?: [];

        $this->view('admin.modules.manage_menu', compact('categories','items') + ['title' => 'منوی رستوران']);
    }

    // ── Categories ──────────────────────────────────────────────────────────

    public function storeCategory(Request $req): void
    {
        $name = trim($req->post('name', ''));
        if (!$name) { $this->flash('error', 'نام دسته الزامی است'); $this->back(); return; }

        $this->db->insert('menu_categories', [
            'tenant_id'  => $this->tid(),
            'name'       => $name,
            'name_en'    => trim($req->post('name_en', '')) ?: null,
            'icon'       => $req->post('icon', 'fas fa-utensils'),
            'color'      => $req->post('color', '#f97316'),
            'sort_order' => (int)$req->post('sort_order', 0),
            'is_active'  => 1,
        ]);
        $this->flash('success', 'دسته اضافه شد');
        $this->back();
    }

    public function updateCategory(Request $req, array $params): void
    {
        $this->db->update('menu_categories',
            [
                'name'       => trim($req->post('name', '')),
                'name_en'    => trim($req->post('name_en', '')) ?: null,
                'icon'       => $req->post('icon', 'fas fa-utensils'),
                'color'      => $req->post('color', '#f97316'),
                'sort_order' => (int)$req->post('sort_order', 0),
                'is_active'  => $req->post('is_active', 1) ? 1 : 0,
            ],
            ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]
        );
        $this->flash('success', 'دسته ویرایش شد');
        $this->back();
    }

    public function deleteCategory(Request $req, array $params): void
    {
        $id  = (int)$params['id'];
        $tid = $this->tid();
        // move items to uncategorized
        $this->db->query("UPDATE menu_items SET category_id=NULL WHERE category_id=? AND tenant_id=?", [$id, $tid]);
        $this->db->delete('menu_categories', ['id' => $id, 'tenant_id' => $tid]);
        $this->flash('success', 'دسته حذف شد');
        $this->back();
    }

    // ── Items ────────────────────────────────────────────────────────────────

    public function storeItem(Request $req): void
    {
        $name = trim($req->post('name', ''));
        if (!$name) { $this->flash('error', 'نام آیتم الزامی است'); $this->back(); return; }

        $this->db->insert('menu_items', [
            'tenant_id'      => $this->tid(),
            'category_id'    => (int)$req->post('category_id', 0) ?: null,
            'name'           => $name,
            'name_en'        => trim($req->post('name_en', '')) ?: null,
            'description'    => trim($req->post('description', '')) ?: null,
            'price'          => (float)$req->post('price', 0),
            'original_price' => $req->post('old_price') !== '' ? (float)$req->post('old_price') : null,
            'image'          => trim($req->post('image_url', '')) ?: null,
            'badge'          => trim($req->post('badge', '')) ?: null,
            'is_available'   => 1,
            'is_active'      => 1,
            'sort_order'     => 0,
        ]);
        $this->flash('success', 'آیتم اضافه شد');
        $this->back();
    }

    public function updateItem(Request $req, array $params): void
    {
        $this->db->update('menu_items',
            [
                'category_id'    => (int)$req->post('category_id', 0) ?: null,
                'name'           => trim($req->post('name', '')),
                'name_en'        => trim($req->post('name_en', '')) ?: null,
                'description'    => trim($req->post('description', '')) ?: null,
                'price'          => (float)$req->post('price', 0),
                'original_price' => $req->post('old_price') !== '' ? (float)$req->post('old_price') : null,
                'image'          => trim($req->post('image_url', '')) ?: null,
                'badge'          => trim($req->post('badge', '')) ?: null,
                'is_available'   => $req->post('is_available', 1) ? 1 : 0,
                'is_active'      => $req->post('is_active', 1) ? 1 : 0,
            ],
            ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]
        );
        $this->flash('success', 'آیتم ویرایش شد');
        $this->back();
    }

    public function deleteItem(Request $req, array $params): void
    {
        $this->db->delete('menu_items', ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        $this->flash('success', 'آیتم حذف شد');
        $this->back();
    }
}
