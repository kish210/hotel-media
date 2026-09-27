<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};
use App\Services\CameraService;

/**
 * صفحه‌ی مدیریت دوربین مداربسته در پنل.
 * فاز ۱۰ نقشه‌راه — docs/TODO.md (۵.۷)
 */
class CameraWebController extends Controller
{
    public function index(Request $req): void
    {
        $svc = new CameraService($this->db);

        $cameras  = [];
        $ffmpegOk = $svc->ffmpegAvailable();
        try {
            $cameras = $svc->all(Auth::tenantId());
        } catch (\Throwable $e) {}

        $this->view('admin.cameras.index', [
            'title'    => 'دوربین مداربسته',
            'cameras'  => $cameras,
            'ffmpegOk' => $ffmpegOk,
        ]);
    }
}
