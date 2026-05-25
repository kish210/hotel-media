<?php declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Controller, Response, Request, Auth};

class LayoutController extends Controller
{
    public function index(Request $req): void
    {
        $rows = $this->db->rows("SELECT * FROM layouts WHERE tenant_id=? AND is_active=1 ORDER BY name",[Auth::tenantId()]);
        Response::paginated(['data'=>$rows,'total'=>count($rows),'per_page'=>100,'current_page'=>1,'last_page'=>1,'from'=>1,'to'=>count($rows)]);
    }

    public function store(Request $req): void
    {
        $errors = $req->validate(['name'=>'required']);
        if ($errors) Response::error('نامعتبر',422,$errors);
        $id = $this->db->insert('layouts',['tenant_id'=>Auth::tenantId(),'created_by'=>Auth::id(),'name'=>$req->input('name'),'canvas_width'=>(int)$req->input('canvas_width',1920),'canvas_height'=>(int)$req->input('canvas_height',1080),'zones'=>$req->input('zones','[]'),'description'=>$req->input('description','')]);
        Response::success($this->db->row("SELECT * FROM layouts WHERE id=?",[$id]),'ایجاد شد',201);
    }

    public function update(Request $req, array $params): void
    {
        $this->db->update('layouts',['name'=>$req->input('name'),'zones'=>$req->input('zones','[]'),'canvas_width'=>(int)$req->input('canvas_width',1920),'canvas_height'=>(int)$req->input('canvas_height',1080)],['id'=>(int)$params['id'],'tenant_id'=>Auth::tenantId()]);
        Response::success($this->db->row("SELECT * FROM layouts WHERE id=?",[(int)$params['id']]),'به‌روز شد');
    }

    public function destroy(Request $req, array $params): void
    {
        $this->db->update('layouts',['is_active'=>0],['id'=>(int)$params['id'],'tenant_id'=>Auth::tenantId()]);
        Response::success(null,'حذف شد');
    }
}
