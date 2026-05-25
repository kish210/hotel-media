<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};
use App\Modules\Core\ModuleRegistry;

class ModuleWebController extends Controller
{
    /** ماژول‌هایی که صفحه مدیریت مستقل دارن */
    private const DIRECT_ROUTES = [
        'iptv'      => '/admin/iptv',
        'inflight'  => '/admin/inflight',
        'vod'       => '/admin/vod',
    ];

    public function index(Request $req): void
    {
        ModuleRegistry::ensureTable();
        ModuleRegistry::boot(Auth::tenantId());
        $this->view('admin.modules.index', ['title' => 'مدیریت ماژول‌ها']);
    }

    public function manage(Request $req, array $params): void
    {
        ModuleRegistry::ensureTable();
        ModuleRegistry::boot(Auth::tenantId());

        $id = $params['id'];

        // ماژول‌هایی که صفحه مستقل دارن → redirect
        if (isset(self::DIRECT_ROUTES[$id])) {
            $mod = ModuleRegistry::get($id);
            if (!$mod || !$mod->isInstalled()) {
                $this->flash('error', "ماژول «{$id}» هنوز نصب نشده — ابتدا نصب کنید");
                $this->redirect('/admin/modules');
                return;
            }
            $this->redirect(self::DIRECT_ROUTES[$id]);
            return;
        }

        $mod = ModuleRegistry::get($id);
        if (!$mod || !$mod->isInstalled()) {
            $this->flash('error', 'ماژول یافت نشد یا نصب نشده');
            $this->redirect('/admin/modules');
            return;
        }

        // صفحه اختصاصی هر ماژول
        $specificView = "admin.modules.manage_{$id}";
        $fallbackView = 'admin.modules.manage_generic';

        try {
            $this->view($specificView, ['title' => 'مدیریت: ' . $mod->name(), 'module' => $mod]);
        } catch (\Throwable $e) {
            $this->view($fallbackView, ['title' => 'مدیریت: ' . $mod->name(), 'module' => $mod]);
        }
    }

    public function saveSettings(Request $req, array $params): void
    {
        ModuleRegistry::ensureTable();
        ModuleRegistry::boot(Auth::tenantId());

        $mod = ModuleRegistry::get($params['id']);
        if (!$mod) {
            $this->redirect('/admin/modules');
            return;
        }

        $data = $req->post();
        unset($data['_token']);
        $mod->saveSettings($data);
        $this->flash('success', 'تنظیمات ماژول ذخیره شد');
        $this->redirect('/admin/modules/' . $params['id'] . '/manage');
    }
}
