<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\{Controller, Response, Request, Auth};
use App\Modules\Core\ModuleRegistry;

class ModuleController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        ModuleRegistry::ensureTable();
        ModuleRegistry::boot(Auth::tenantId());
    }

    /** GET /api/v1/modules — list all */
    public function index(Request $req): void
    {
        $list = array_map(fn($m) => [
            'id'          => $m->id(),
            'name'        => $m->name(),
            'name_en'     => $m->nameEn(),
            'description' => $m->description(),
            'version'     => $m->version(),
            'icon'        => $m->icon(),
            'color'       => $m->color(),
            'category'    => $m->category(),
            'installed'   => $m->isInstalled(),
            'zone_types'  => $m->zoneTypes(),
            'stats'       => $m->isInstalled() ? $m->getDashboardStats() : [],
            'settings'    => $m->isInstalled() ? $m->getSettings() : [],
        ], ModuleRegistry::all());

        Response::success(array_values($list));
    }

    /** GET /api/v1/modules/{id} — single module info */
    public function show(Request $req, array $params): void
    {
        $mod = ModuleRegistry::get($params['id']);
        if (!$mod) Response::notFound('ماژول یافت نشد');

        Response::success([
            'id'          => $mod->id(),
            'name'        => $mod->name(),
            'description' => $mod->description(),
            'version'     => $mod->version(),
            'installed'   => $mod->isInstalled(),
            'zone_types'  => $mod->zoneTypes(),
            'stats'       => $mod->isInstalled() ? $mod->getDashboardStats() : [],
            'settings'    => $mod->isInstalled() ? $mod->getSettings() : [],
        ]);
    }

    /** POST /api/v1/modules/{id}/install */
    public function install(Request $req, array $params): void
    {
        $mod = ModuleRegistry::get($params['id']);
        if (!$mod) Response::notFound('ماژول یافت نشد');
        if ($mod->isInstalled()) Response::error('ماژول قبلاً نصب شده', 409);

        $ok = $mod->install();
        if ($ok) {
            $this->log('module.install', 'Module', null, [], ['module' => $params['id']]);
            Response::success(['module' => $params['id']], 'ماژول با موفقیت نصب شد', 201);
        } else {
            Response::error('خطا در نصب ماژول', 500);
        }
    }

    /** POST /api/v1/modules/{id}/uninstall */
    public function uninstall(Request $req, array $params): void
    {
        $mod = ModuleRegistry::get($params['id']);
        if (!$mod) Response::notFound('ماژول یافت نشد');

        $mod->uninstall();
        $this->log('module.uninstall', 'Module', null, ['module' => $params['id']], []);
        Response::success(null, 'ماژول غیرفعال شد');
    }

    /** POST /api/v1/modules/{id}/toggle */
    public function toggle(Request $req, array $params): void
    {
        $mod    = ModuleRegistry::get($params['id']);
        if (!$mod) Response::notFound();
        $enable = (bool)$req->input('enable', true);

        if ($enable && !$mod->isInstalled()) {
            $mod->install();
            Response::success(null, 'ماژول فعال شد');
        } else {
            $mod->uninstall();
            Response::success(null, 'ماژول غیرفعال شد');
        }
    }

    /** PUT /api/v1/modules/{id}/settings */
    public function saveSettings(Request $req, array $params): void
    {
        $mod = ModuleRegistry::get($params['id']);
        if (!$mod || !$mod->isInstalled()) Response::notFound('ماژول یافت نشد یا نصب نشده');

        $new = $req->input();
        unset($new['_token']);

        // Merge with existing settings so partial updates (e.g. cron_token only) don't wipe other keys
        $merged = array_merge($mod->getSettings(), $new);
        $mod->saveSettings($merged);
        $this->log('module.settings', 'Module', null, [], $new);
        Response::success($mod->getSettings(), 'تنظیمات ذخیره شد');
    }

    /** GET /api/v1/modules/{id}/preview?zone=zone_type — render widget HTML */
    public function preview(Request $req, array $params): void
    {
        $mod      = ModuleRegistry::get($params['id']);
        if (!$mod) Response::notFound('ماژول یافت نشد');

        $zoneType = $req->get('zone', '');
        $settings = $req->get('settings') ? json_decode($req->get('settings'), true) : [];

        // Validate zone type belongs to module
        $validZones = array_column($mod->zoneTypes(), 'id');
        if (!in_array($zoneType, $validZones)) {
            Response::error("زون نامعتبر: $zoneType", 400);
        }

        $html = $mod->renderPlayerWidget($zoneType, $settings ?: $mod->getSettings());
        Response::success(['html' => $html, 'zone_type' => $zoneType]);
    }

    /** GET /api/v1/modules/zone-types — all zone types from installed modules */
    public function allZoneTypes(Request $req): void
    {
        Response::success(ModuleRegistry::allZoneTypes());
    }

    /** GET /api/v1/modules/dashboard-stats */
    public function dashboardStats(Request $req): void
    {
        Response::success(ModuleRegistry::getDashboardStats());
    }
}
