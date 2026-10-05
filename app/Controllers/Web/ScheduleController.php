<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};

class ScheduleController extends Controller
{
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();
        /* محل و گروه هم هدف برنامه‌اند، نه فقط صفحه.
           تا پیش از این فقط `screen_id` خوانده می‌شد، پس برنامه‌ای که
           روی لابی منتشر شده بود در این فهرست «همه صفحات» دیده می‌شد —
           یعنی اپراتور فکر می‌کرد تمام هتل را عوض کرده. */
        $schedules = $this->db->rows(
            "SELECT sc.*, p.name AS playlist_name,
                    s.name AS screen_name,
                    v.name AS venue_name,
                    g.name AS group_name,
                    (SELECT COUNT(*) FROM screens x WHERE x.venue_id = sc.venue_id) AS venue_screens,
                    (SELECT COUNT(*) FROM screens x WHERE x.group_id = sc.group_id) AS group_screens
             FROM schedules sc
             JOIN playlists p ON p.id = sc.playlist_id
             LEFT JOIN screens s       ON s.id = sc.screen_id
             LEFT JOIN venues v        ON v.id = sc.venue_id
             LEFT JOIN screen_groups g ON g.id = sc.group_id
             WHERE sc.tenant_id=? ORDER BY sc.priority DESC, sc.created_at DESC",
            [$tid]
        );
        $playlists = $this->db->rows("SELECT id,name FROM playlists WHERE tenant_id=? AND is_active=1 ORDER BY name", [$tid]);
        $screens   = $this->db->rows("SELECT id,name,code FROM screens WHERE tenant_id=? AND status != 'inactive' ORDER BY name", [$tid]);
        $venues    = $this->db->rows("SELECT id,name FROM venues WHERE tenant_id=? AND is_active=1 ORDER BY sort_order,name", [$tid]) ?: [];
        $groups    = $this->db->rows("SELECT id,name FROM screen_groups WHERE tenant_id=? ORDER BY name", [$tid]) ?: [];

        $this->view('schedules.index', compact('schedules','playlists','screens','venues','groups') + ['title' => 'زمان‌بندی محتوا']);
    }

    public function store(Request $req): void
    {
        $errors = $req->validate(['playlist_id' => 'required', 'name' => 'required']);
        if ($errors) { $this->flash('error', 'خطا در اطلاعات'); $this->redirect('/admin/schedules'); return; }

        $weekdays = $req->post('weekdays') ? json_encode($req->post('weekdays')) : null;

        /* یک هدف و فقط یک هدف.
           فرم با یک select هدف را می‌گیرد («screen:7»، «venue:1»،
           «group:2» یا خالی برای همگانی) چون سه فیلد موازی یعنی
           اپراتور می‌تواند هم صفحه و هم محل را پر کند و بعد هیچ‌کس
           نداند کدام برنده است. `Screen::getCurrentPlaylist()` هم بر
           همین فرض ساخته شده. */
        [$tKind, $tId] = array_pad(explode(':', (string)$req->post('target', ''), 2), 2, '');
        $tId = (int)$tId;

        $this->db->insert('schedules', [
            'tenant_id'   => Auth::tenantId(),
            'playlist_id' => (int)$req->post('playlist_id'),
            'screen_id'   => $tKind === 'screen' && $tId ? $tId : null,
            'venue_id'    => $tKind === 'venue'  && $tId ? $tId : null,
            'group_id'    => $tKind === 'group'  && $tId ? $tId : null,
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
