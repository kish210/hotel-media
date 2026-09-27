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

        /* فهرست را خودِ صفحه از /api/v1/cameras می‌گیرد، چون وضعیت زنده‌ی
           رله هر ۲۰ ثانیه تازه می‌شود. پس اینجا کوئری نمی‌زنیم. */
        $this->view('admin.cameras.index', [
            'title'    => 'دوربین مداربسته',
            'ffmpegOk' => $svc->ffmpegAvailable(),
        ]);
    }
}
