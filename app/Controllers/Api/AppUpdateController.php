<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Controller, Request, Response, Auth};

/**
 * OTA Update Controller
 * مدیریت بروزرسانی خودکار اپ Android
 */
class AppUpdateController extends Controller
{
    /** GET /api/v1/app/version — بررسی نسخه جدید */
    public function version(Request $req): void
    {
        $latest = $this->db->row(
            "SELECT * FROM apk_versions WHERE is_active=1 ORDER BY version_code DESC LIMIT 1"
        );

        if (!$latest) {
            Response::json(['success'=>true,'version_code'=>0,'apk_url'=>'','changelog'=>'']);
            return;
        }

        $scheme  = (!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;

        Response::json([
            'success'      => true,
            'version_code' => (int)$latest['version_code'],
            'version_name' => $latest['version_name'],
            'apk_url'      => $baseUrl . '/apk/' . $latest['apk_filename'],
            'changelog'    => $latest['changelog'] ?? '',
            'force'        => (bool)$latest['force_update'],
            'min_version'  => (int)($latest['min_version'] ?? 0),
            'file_size'    => (int)$latest['file_size'],
            'released_at'  => $latest['created_at'],
        ]);
    }

    /** GET /api/v1/app/download/{filename} — دانلود APK */
    public function download(Request $req, array $params): void
    {
        $filename = basename($params['filename'] ?? '');
        $path     = BASE_PATH . '/public/apk/' . $filename;

        if (!$filename || !file_exists($path) || !str_ends_with($filename, '.apk')) {
            Response::error('فایل یافت نشد', 404); return;
        }

        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache');
        readfile($path);
        exit;
    }
}
