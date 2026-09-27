<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};
use App\Services\DvrService;

/**
 * DVR Web Controller — صفحه‌ی مدیریت ضبط و Catch-up در پنل.
 * فهرست ضبط‌ها، فعال‌سازی Catch-up روی کانال‌ها، و سهمیه‌ی NPVR.
 * فاز ۹ نقشه‌راه — docs/TODO.md (۱.۳، ۱.۴، ۱.۱۳)
 */
class DvrWebController extends Controller
{
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();
        $svc = new DvrService($this->db);

        $recordings = [];
        $channels   = [];
        $rooms      = [];
        $tvhReady   = false;

        try {
            $recordings = $svc->listAll($tid, null, null, 200);
            $channels   = $this->db->rows(
                "SELECT id, name, tvh_uuid, catchup_enabled, catchup_window_hours
                   FROM iptv_channels WHERE tenant_id=? AND is_active=1 ORDER BY sort_order, name",
                [$tid]
            );
            $rooms = $this->db->rows(
                "SELECT id, room_number, room_name FROM iptv_rooms WHERE tenant_id=? ORDER BY room_number LIMIT 500",
                [$tid]
            );
            $tvhReady = (bool)$this->db->value(
                "SELECT 1 FROM tvheadend_sources WHERE tenant_id=? AND is_active=1 LIMIT 1", [$tid]
            );
        } catch (\Throwable $e) {}

        $this->view('admin.iptv.dvr', compact('recordings','channels','rooms','tvhReady') + [
            'title' => 'ضبط و Catch-up',
        ]);
    }
}
