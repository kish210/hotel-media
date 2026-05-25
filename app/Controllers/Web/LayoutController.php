<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};

class LayoutController extends Controller
{
    private function tid(): int { return Auth::tenantId(); }

    public function index(Request $req): void
    {
        $layouts = $this->db->rows(
            "SELECT * FROM layouts WHERE tenant_id=? AND is_active=1 ORDER BY updated_at DESC",
            [$this->tid()]
        );
        $this->view('layouts.index', ['title' => 'طراح چیدمان', 'layouts' => $layouts]);
    }

    public function create(Request $req): void
    {
        $layouts = $this->db->rows("SELECT * FROM layouts WHERE tenant_id=? AND is_active=1 ORDER BY name", [$this->tid()]);
        $this->view('layouts.index', ['title' => 'چیدمان جدید', 'layouts' => $layouts]);
    }

    public function store(Request $req): void
    {
        $name = trim($req->post('name', ''));
        if (!$name) { $this->flash('error', 'نام چیدمان الزامی است'); $this->redirect('/admin/layouts'); return; }

        $this->db->insert('layouts', [
            'tenant_id'     => $this->tid(),
            'created_by'    => Auth::id() ?? 1,
            'name'          => $name,
            'canvas_width'  => (int)$req->post('canvas_width', 1920),
            'canvas_height' => (int)$req->post('canvas_height', 1080),
            'zones'         => $req->post('zones', '[]'),
            'description'   => $req->post('description', ''),
            'is_active'     => 1,
        ]);
        $this->flash('success', 'چیدمان ذخیره شد');
        $this->redirect('/admin/layouts');
    }

    public function edit(Request $req, array $params): void
    {
        $layout  = $this->db->row("SELECT * FROM layouts WHERE id=? AND tenant_id=?", [(int)$params['id'], $this->tid()]);
        if (!$layout) { $this->redirect('/admin/layouts'); return; }
        $layouts = $this->db->rows("SELECT * FROM layouts WHERE tenant_id=? AND is_active=1 ORDER BY name", [$this->tid()]);
        $this->view('layouts.index', ['title' => 'ویرایش: ' . $layout['name'], 'editLayout' => $layout, 'layouts' => $layouts]);
    }

    public function update(Request $req, array $params): void
    {
        $this->db->update('layouts', [
            'name'          => $req->post('name'),
            'canvas_width'  => (int)$req->post('canvas_width', 1920),
            'canvas_height' => (int)$req->post('canvas_height', 1080),
            'zones'         => $req->post('zones', '[]'),
        ], ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        $this->flash('success', 'چیدمان به‌روز شد');
        $this->redirect('/admin/layouts');
    }

    public function destroy(Request $req, array $params): void
    {
        $this->db->update('layouts', ['is_active' => 0], ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        $this->flash('success', 'چیدمان حذف شد');
        $this->redirect('/admin/layouts');
    }
}
