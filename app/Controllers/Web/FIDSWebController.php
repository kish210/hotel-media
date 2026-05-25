<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\{Controller, Request, Auth};

class FIDSWebController extends Controller
{
    public function flights(Request $req): void
    {
        $tid    = Auth::tenantId();
        $today  = date('Y-m-d');
        $stats  = $this->db->row(
            "SELECT COUNT(*) AS total,
             SUM(type='departure') AS dep,
             SUM(type='arrival') AS arr,
             SUM(status='delayed') AS delayed_count,
             SUM(status='cancelled') AS cancelled_count,
             SUM(status='boarding') AS boarding_count
             FROM fids_flights WHERE tenant_id=? AND DATE(scheduled_time)=? AND is_active=1",
            [$tid, $today]
        );
        $flights = $this->db->rows(
            "SELECT * FROM fids_flights WHERE tenant_id=? ORDER BY scheduled_time ASC",
            [$tid]
        );
        $airlines = $this->db->rows("SELECT * FROM fids_airlines ORDER BY name_fa ASC", []);
        $this->view('admin.modules.fids', compact('stats', 'flights', 'airlines') + ['title' => 'پروازها (FIDS)']);
    }

    public function storeFlight(Request $req): void
    {
        $tid     = Auth::tenantId();
        $data    = $req->post();
        $airline = $this->db->row("SELECT * FROM fids_airlines WHERE code=?", [$data['airline_code'] ?? '']);

        $this->db->insert('fids_flights', [
            'tenant_id'          => $tid,
            'flight_number'      => strtoupper($data['flight_number'] ?? ''),
            'airline_code'       => $data['airline_code'] ?? '',
            'airline_name'       => $data['airline_name_custom'] ?: ($airline['name_fa'] ?? $data['airline_code'] ?? ''),
            'airline_name_en'    => $airline['name_en'] ?? '',
            'type'               => $data['type'] ?? 'departure',
            'destination'        => $data['destination'] ?? null,
            'destination_code'   => strtoupper($data['destination_code'] ?? ''),
            'scheduled_time'     => $data['scheduled_time'] ?? date('Y-m-d H:i:s'),
            'terminal'           => $data['terminal'] ?? null,
            'gate'               => strtoupper($data['gate'] ?? ''),
            'status'             => $data['status'] ?? 'scheduled',
            'delay_minutes'      => (int)($data['delay_minutes'] ?? 0),
            'remarks'            => $data['remarks'] ?? null,
            'is_active'          => 1,
        ]);
        $this->flash('success', 'پرواز ثبت شد');
        $this->redirect('/admin/modules/fids/manage');
    }

    public function updateFlight(Request $req, array $params): void
    {
        $data = $req->post();
        $this->db->update('fids_flights', [
            'flight_number' => strtoupper($data['flight_number'] ?? ''),
            'type'          => $data['type'] ?? 'departure',
            'destination'   => $data['destination'] ?? null,
            'scheduled_time'=> $data['scheduled_time'] ?? null,
            'terminal'      => $data['terminal'] ?? null,
            'gate'          => strtoupper($data['gate'] ?? ''),
            'status'        => $data['status'] ?? 'scheduled',
            'delay_minutes' => (int)($data['delay_minutes'] ?? 0),
            'remarks'       => $data['remarks'] ?? null,
        ], ['id' => (int)$params['id'], 'tenant_id' => Auth::tenantId()]);
        $this->flash('success', 'پرواز بروز شد');
        $this->redirect('/admin/modules/fids/manage');
    }

    public function deleteFlight(Request $req, array $params): void
    {
        $this->db->update('fids_flights',
            ['is_active' => 0],
            ['id' => (int)$params['id'], 'tenant_id' => Auth::tenantId()]
        );
        $this->flash('success', 'پرواز حذف شد');
        $this->redirect('/admin/modules/fids/manage');
    }

    private function statusFa(string $s): string
    {
        return ['scheduled'=>'زمان‌بندی','boarding'=>'سوارشوید','departed'=>'پرواز کرد',
                'arrived'=>'فرود آمد','delayed'=>'تأخیر','cancelled'=>'لغو شد',
                'diverted'=>'مسیر تغییر','gate_change'=>'تغییر دروازه'][$s] ?? $s;
    }
}
