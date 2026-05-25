<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Controller, Response, Request, Auth};

class TransportController extends Controller
{
    private function tid(): int { return Auth::check() ? Auth::tenantId() : 1; }

    public function schedules(Request $req): void
    {
        $type  = $req->get('type', 'bus');
        $limit = min(50, (int)$req->get('limit', 15));
        $sql   = "SELECT * FROM transport_schedules WHERE tenant_id=? AND is_active=1 AND type=?";
        $p     = [$this->tid(), $type];

        if ($req->get('station')) { $sql .= " AND station=?"; $p[] = $req->get('station'); }

        $now = date('H:i:s');
        $sql .= " AND departure >= ? ORDER BY departure ASC LIMIT $limit";
        $p[] = $now;

        Response::success($this->db->rows($sql, $p));
    }

    public function storeSchedule(Request $req): void
    {
        $errors = $req->validate(['line' => 'required', 'direction' => 'required', 'station' => 'required', 'departure' => 'required', 'type' => 'required']);
        if ($errors) Response::error('نامعتبر', 422, $errors);
        $data = array_merge($req->input(), ['tenant_id' => $this->tid()]); unset($data['_token']);
        if (isset($data['days']) && is_array($data['days'])) $data['days'] = json_encode($data['days']);
        $id = $this->db->insert('transport_schedules', $data);
        Response::success($this->db->row("SELECT * FROM transport_schedules WHERE id=?", [$id]), 'برنامه ثبت شد', 201);
    }

    public function updateSchedule(Request $req, array $params): void
    {
        $data = $req->input(); unset($data['_token']);
        $this->db->update('transport_schedules', $data, ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'به‌روز شد');
    }

    public function deleteSchedule(Request $req, array $params): void
    {
        $this->db->update('transport_schedules', ['is_active' => 0], ['id' => (int)$params['id'], 'tenant_id' => $this->tid()]);
        Response::success(null, 'حذف شد');
    }
}
