<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\DriverService;

/**
 * درایورهای ترنسکدر و کارت کپچر — فقط super_admin.
 *
 * بارگذاری دستی یعنی نصب بسته با root روی سرور هتل. این کمتر از
 * «به‌روزرسانی سیستم» خطرناک نیست، پس همان سطح دسترسی را دارد.
 */
class DriverController extends Controller
{
    private function guard(bool $json = true): bool
    {
        if ((Auth::user()['role'] ?? '') === 'super_admin') return true;
        if ($json) Response::error('این بخش فقط برای مدیر ارشد است', 403);
        else { $this->flash('error', 'این بخش فقط برای مدیر ارشد است'); $this->redirect('/admin/dashboard'); }
        return false;
    }

    /** GET /admin/system/drivers */
    public function index(Request $req): void
    {
        if (!$this->guard(false)) return;
        $svc = new DriverService();
        $this->view('admin.system.drivers', [
            'title'     => 'درایورها',
            'supported' => $svc->supported(),
            'ready'     => $svc->helperReady(),
            'drivers'   => $svc->all(),
            'appDir'    => ROOT_PATH,
        ]);
    }

    /** GET /admin/system/drivers/status */
    public function status(Request $req): void
    {
        if (!$this->guard()) return;
        Response::success((new DriverService())->all());
    }

    /** POST /admin/system/drivers/{id}/install */
    public function install(Request $req, array $params): void
    {
        if (!$this->guard()) return;
        $id  = (string)($params['id'] ?? '');
        $res = (new DriverService())->installOnline($id);
        if (!$res['ok']) { Response::error($res['message'], 422); return; }
        $this->log('driver.install', 'driver', null, [], ['id' => $id, 'source' => 'online']);
        Response::success(null, $res['message']);
    }

    /** POST /admin/system/drivers/{id}/upload  (multipart: package) */
    public function upload(Request $req, array $params): void
    {
        if (!$this->guard()) return;
        $id   = (string)($params['id'] ?? '');
        $file = $_FILES['package'] ?? null;
        if (!$file) { Response::error('فایلی ارسال نشد', 422); return; }

        $res = (new DriverService())->installFile($id, $file);
        if (!$res['ok']) { Response::error($res['message'], 422); return; }
        $this->log('driver.install', 'driver', null, [], ['id' => $id, 'source' => 'file', 'name' => (string)($file['name'] ?? '')]);
        Response::success(null, $res['message']);
    }

    /** GET /admin/system/drivers/{id}/log */
    public function jobLog(Request $req, array $params): void
    {
        if (!$this->guard()) return;
        $svc = new DriverService();
        $id  = (string)($params['id'] ?? '');
        Response::success(['log' => $svc->log($id), 'job' => $svc->job($id)]);
    }
}
