<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth, Response};

class BroadcastWebController extends Controller
{
    /** GET /admin/screens/{id}/media-list — لیست رسانه‌ها برای modal پخش فوری */
    public function mediaList(Request $req, array $params): void
    {
        $tid  = Auth::tenantId();
        $type = $req->get('type', '');

        $sql    = "SELECT id,name,type,file_path,thumbnail_path,url FROM media WHERE tenant_id=? AND deleted_at IS NULL";
        $params2 = [$tid];
        if ($type) { $sql .= " AND type=?"; $params2[] = $type; }
        $sql .= " ORDER BY created_at DESC LIMIT 100";

        Response::json(['success' => true, 'data' => $this->db->rows($sql, $params2)]);
    }

    /** POST /admin/screens/{id}/broadcast — پخش فوری از web */
    public function send(Request $req, array $params): void
    {
        $screenId = (int)$params['id'];
        $tid      = Auth::tenantId();

        $screen = $this->db->row(
            "SELECT * FROM screens WHERE id=? AND tenant_id=?",
            [$screenId, $tid]
        );
        if (!$screen) Response::error('صفحه یافت نشد', 404);

        $type     = $req->post('type', 'image');
        $content  = $req->post('content', '');
        $duration = (int)$req->post('duration', 30);
        $mediaId  = (int)$req->post('media_id', 0);
        $target   = $req->post('target', 'this');

        if ($mediaId) {
            $media = $this->db->row(
                "SELECT * FROM media WHERE id=? AND tenant_id=?",
                [$mediaId, $tid]
            );
            if ($media) {
                $fp      = $media['file_path'] ?? '';
                $content = ($fp && !str_starts_with($fp, 'http'))
                    ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $fp
                    : ($fp ?: $media['url'] ?? '');
                $type    = $media['type'];
            }
        }

        if (!$content) {
            Response::error('محتوا الزامی است — رسانه انتخاب کنید یا URL وارد کنید', 400);
        }

        $payload = json_encode([
            'type'       => $type,
            'content'    => $content,
            'duration'   => $duration,
            'sent_at'    => time(),
            'expires_at' => $duration > 0 ? time() + $duration + 30 : PHP_INT_MAX,
        ]);

        if ($target === 'all') {
            $screens = $this->db->rows(
                "SELECT id FROM screens WHERE tenant_id=? AND status='active'",
                [$tid]
            );
            foreach ($screens as $s) {
                $this->db->update('screens', ['emergency_broadcast' => $payload], ['id' => $s['id']]);
            }
            Response::json(['success' => true, 'message' => count($screens) . ' صفحه به‌روز شد']);
        } else {
            $this->db->update('screens', ['emergency_broadcast' => $payload], ['id' => $screenId]);
            Response::json(['success' => true, 'message' => 'پخش فوری ارسال شد']);
        }
    }

    /** POST /admin/screens/{id}/broadcast/clear */
    public function clear(Request $req, array $params): void
    {
        $this->db->update('screens',
            ['emergency_broadcast' => null],
            ['id' => (int)$params['id'], 'tenant_id' => Auth::tenantId()]
        );
        Response::json(['success' => true, 'message' => 'پخش فوری متوقف شد']);
    }
}
