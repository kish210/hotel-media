<?php declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Controller, Response, Request, Auth};

class ScheduleController extends Controller
{
    public function index(Request $req): void
    {
        $tid  = Auth::tenantId();
        $rows = $this->db->rows("SELECT sc.*,p.name AS playlist_name,s.name AS screen_name FROM schedules sc JOIN playlists p ON p.id=sc.playlist_id LEFT JOIN screens s ON s.id=sc.screen_id WHERE sc.tenant_id=? ORDER BY sc.priority DESC", [$tid]);
        Response::success($rows);
    }

    public function store(Request $req): void
    {
        $errors = $req->validate(['playlist_id'=>'required','name'=>'required']);
        if ($errors) Response::error('داده‌ها نامعتبر',422,$errors);
        $id = $this->db->insert('schedules', array_merge($req->input(), ['tenant_id'=>Auth::tenantId()]));
        Response::success($this->db->row("SELECT * FROM schedules WHERE id=?",[$id]),'ایجاد شد',201);
    }

    public function destroy(Request $req, array $params): void
    {
        $this->db->delete('schedules',['id'=>(int)$params['id'],'tenant_id'=>Auth::tenantId()]);
        Response::success(null,'حذف شد');
    }
}
