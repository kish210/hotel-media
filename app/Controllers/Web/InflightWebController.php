<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};

class InflightWebController extends Controller
{
    public function index(Request $req): void
    {
        $tid     = Auth::tenantId();
        $flights = $this->db->rows(
            "SELECT f.*,
                    (SELECT COUNT(*) FROM screens WHERE inflight_flight_id=f.id AND tenant_id=f.tenant_id) AS screen_count
             FROM inflight_flights f
             WHERE f.tenant_id=?
             ORDER BY f.updated_at DESC",
            [$tid]
        ) ?: [];

        // screens using inflight type
        $screens = $this->db->rows(
            "SELECT id, name, code, inflight_flight_id FROM screens WHERE tenant_id=? AND screen_type='inflight' ORDER BY name",
            [$tid]
        ) ?: [];

        $this->view('admin.inflight.index', [
            'title'   => 'نمایش اطلاعات پرواز',
            'flights' => $flights,
            'screens' => $screens,
        ]);
    }
}
