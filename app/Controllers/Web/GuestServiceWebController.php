<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};

/**
 * پنل کارکنان — صف درخواست‌های مهمان و کاتالوگ خدمات
 * فاز ۱ نقشه‌راه — docs/TODO.md
 */
class GuestServiceWebController extends Controller
{
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();

        $requests = $this->db->rows(
            "SELECT r.*, rm.room_number, rm.room_name, rm.floor,
                    u.name AS assigned_name,
                    TIMESTAMPDIFF(MINUTE, r.created_at, NOW()) AS age_minutes
             FROM guest_requests r
             JOIN iptv_rooms rm ON rm.id = r.room_id
             LEFT JOIN users  u  ON u.id = r.assigned_to
             WHERE r.tenant_id = ?
               AND (r.status IN ('pending','accepted','in_progress')
                    OR r.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY))
             ORDER BY FIELD(r.status,'pending','accepted','in_progress','done','cancelled'),
                      r.created_at ASC
             LIMIT 200",
            [$tid]
        ) ?: [];

        // اقلام همه درخواست‌ها در یک کوئری — جلوگیری از N+1
        $ids = array_column($requests, 'id');
        $itemsByRequest = [];
        if ($ids) {
            $ph   = implode(',', array_fill(0, count($ids), '?'));
            $rows = $this->db->rows(
                "SELECT * FROM guest_request_items WHERE request_id IN ($ph) ORDER BY id",
                array_map('intval', $ids)
            ) ?: [];
            foreach ($rows as $row) {
                $itemsByRequest[(int)$row['request_id']][] = $row;
            }
        }

        $services = $this->db->rows(
            'SELECT * FROM guest_services WHERE tenant_id = ? ORDER BY category ASC, sort_order ASC, id ASC',
            [$tid]
        ) ?: [];

        $staff = $this->db->rows(
            'SELECT id, name FROM users WHERE tenant_id = ? AND is_active = 1 ORDER BY name',
            [$tid]
        ) ?: [];

        $stats = [
            'pending'     => (int)$this->db->value("SELECT COUNT(*) FROM guest_requests WHERE tenant_id=? AND status='pending'", [$tid]),
            'in_progress' => (int)$this->db->value("SELECT COUNT(*) FROM guest_requests WHERE tenant_id=? AND status IN ('accepted','in_progress')", [$tid]),
            'done_today'  => (int)$this->db->value("SELECT COUNT(*) FROM guest_requests WHERE tenant_id=? AND status='done' AND DATE(done_at)=CURDATE()", [$tid]),
            'revenue'     => (float)$this->db->value("SELECT COALESCE(SUM(total_price),0) FROM guest_requests WHERE tenant_id=? AND status='done' AND DATE(done_at)=CURDATE()", [$tid]),
            'avg_minutes' => round((float)$this->db->value(
                "SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, created_at, done_at)),0)
                 FROM guest_requests WHERE tenant_id=? AND status='done' AND done_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$tid]
            ), 1),
        ];

        $this->view('admin.iptv.guest-services', [
            'title'          => 'خدمات مهمان',
            'requests'       => $requests,
            'itemsByRequest' => $itemsByRequest,
            'services'       => $services,
            'staff'          => $staff,
            'stats'          => $stats,
        ]);
    }

    /** GET /admin/guest-services/feed — برای به‌روزرسانی خودکار صف (JSON) */
    public function feed(Request $req): void
    {
        $tid = Auth::tenantId();

        $rows = $this->db->rows(
            "SELECT r.id, r.category, r.status, r.note, r.scheduled_at, r.total_price,
                    r.created_at, rm.room_number,
                    TIMESTAMPDIFF(MINUTE, r.created_at, NOW()) AS age_minutes
             FROM guest_requests r
             JOIN iptv_rooms rm ON rm.id = r.room_id
             WHERE r.tenant_id = ? AND r.status IN ('pending','accepted','in_progress')
             ORDER BY r.created_at ASC
             LIMIT 100",
            [$tid]
        ) ?: [];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'pending' => count(array_filter($rows, fn($r) => $r['status'] === 'pending')),
            'data'    => $rows,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
