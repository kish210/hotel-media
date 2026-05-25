<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};

class ScheduleController extends Controller
{
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();
        $schedules = $this->db->rows(
            "SELECT sc.*, p.name AS playlist_name, s.name AS screen_name
             FROM schedules sc
             JOIN playlists p ON p.id = sc.playlist_id
             LEFT JOIN screens s ON s.id = sc.screen_id
             WHERE sc.tenant_id=? ORDER BY sc.priority DESC, sc.created_at DESC",
            [$tid]
        );
        $playlists = $this->db->rows("SELECT id,name FROM playlists WHERE tenant_id=? AND is_active=1 ORDER BY name", [$tid]);
        $screens   = $this->db->rows("SELECT id,name,code FROM screens WHERE tenant_id=? AND status != 'inactive' ORDER BY name", [$tid]);
        $this->view('schedules.index', compact('schedules','playlists','screens') + ['title' => 'زمان‌بندی محتوا']);
    }

    public function store(Request $req): void
    {
        $errors = $req->validate(['playlist_id' => 'required', 'name' => 'required']);
        if ($errors) { $this->flash('error', 'خطا در اطلاعات'); $this->redirect('/admin/schedules'); return; }

        $weekdays = $req->post('weekdays') ? json_encode($req->post('weekdays')) : null;
        $this->db->insert('schedules', [
            'tenant_id'   => Auth::tenantId(),
            'playlist_id' => (int)$req->post('playlist_id'),
            'screen_id'   => $req->post('screen_id') ?: null,
            'name'        => $req->post('name'),
            'type'        => $req->post('type', 'always'),
            'start_date'  => $req->post('start_date') ?: null,
            'end_date'    => $req->post('end_date') ?: null,
            'start_time'  => $req->post('start_time') ?: null,
            'end_time'    => $req->post('end_time') ?: null,
            'weekdays'    => $weekdays,
            'priority'    => (int)$req->post('priority', 5),
            'is_active'   => 1,
        ]);
        $this->flash('success', 'زمان‌بندی ایجاد شد');
        $this->log('schedule.create', 'Schedule');
        $this->redirect('/admin/schedules');
    }

    public function destroy(Request $req, array $params): void
    {
        $this->db->delete('schedules', ['id' => (int)$params['id'], 'tenant_id' => Auth::tenantId()]);
        $this->flash('success', 'زمان‌بندی حذف شد');
        $this->redirect('/admin/schedules');
    }
}
