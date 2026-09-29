<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};
use App\Services\TranscoderService;

/**
 * صفحه‌ی ترنسکدر در پنل و سرو خروجی HLS زنده.
 *
 * کارها و اجرا از /api/v1/transcoder/* می‌آیند (Api\TranscoderController)؛
 * این صفحه فقط اسکلت، قابلیت‌های سرور و فهرست کانال‌ها و فایل‌ها را
 * می‌دهد.
 */
class TranscoderController extends Controller
{
    /** GET /admin/transcoder */
    public function index(Request $req): void
    {
        if (!in_array(Auth::role(), ['super_admin', 'admin'], true)) {
            $this->flash('error', 'فقط مدیر سامانه به ترنسکدر دسترسی دارد');
            $this->redirect('/admin/dashboard');
            return;
        }

        $tid  = Auth::tenantId();
        $svc  = new TranscoderService($this->db);

        $channels = $this->db->rows(
            'SELECT id, name, stream_url, protocol FROM iptv_channels WHERE tenant_id = ? AND is_active = 1 ORDER BY sort_order, name',
            [$tid]
        ) ?: [];
        $files = $this->db->rows(
            "SELECT id, name, file_path FROM media
              WHERE tenant_id = ? AND type = 'video' AND deleted_at IS NULL AND file_path LIKE '/uploads/%'
              ORDER BY created_at DESC LIMIT 300",
            [$tid]
        ) ?: [];

        /* لوگو: PNG شفاف از کتابخانه یا برندینگ */
        $images = $this->db->rows(
            "SELECT id, name, file_path FROM media
              WHERE tenant_id = ? AND type = 'image' AND deleted_at IS NULL
                AND file_path LIKE '/uploads/%' AND (file_path LIKE '%.png' OR file_path LIKE '%.jpg' OR file_path LIKE '%.jpeg')
              ORDER BY created_at DESC LIMIT 200",
            [$tid]
        ) ?: [];

        $this->view('admin.transcoder.index', [
            'title'    => 'ترنسکدر',
            'caps'     => $svc->capabilities(),
            'channels' => $channels,
            'files'    => $files,
            'images'   => $images,
        ]);
    }

    /** GET /hls/{name}/{file} — خروجی زنده‌ی ترنسکدر و دوربین‌ها */
    public function serveHls(Request $req, array $params): void
    {
        $name = preg_replace('/[^a-z0-9_\-]/', '', (string)($params['name'] ?? ''));
        $file = basename((string)($params['file'] ?? 'index.m3u8'));

        /* فقط پلی‌لیست و قطعه — نه لاگ، cmd.txt یا progress.txt که آدرس
           ورودی (گاهی با رمز دوربین) در آن‌هاست */
        if (!preg_match('/^[A-Za-z0-9_\-]+\.(m3u8|ts)$/', $file)) { http_response_code(404); echo 'Not found'; exit; }

        $path = TranscoderService::LIVE_ROOT . '/' . $name . '/' . $file;
        if ($name === '' || !is_file($path)) { http_response_code(404); echo 'Not found'; exit; }

        $isList = str_ends_with($file, '.m3u8');
        header('Content-Type: ' . ($isList ? 'application/vnd.apple.mpegurl' : 'video/MP2T'));
        header('Cache-Control: ' . ($isList ? 'no-cache' : 'max-age=60'));
        header('Access-Control-Allow-Origin: *');
        /* بدون طول، پخش‌کننده‌ی بعضی تلویزیون‌ها پایان قطعه را نمی‌فهمد */
        header('Content-Length: ' . (string)filesize($path));
        readfile($path);
        exit;
    }
}
