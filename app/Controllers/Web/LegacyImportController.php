<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Auth, Controller, Request};
use App\Services\LegacyImportService;

/**
 * مهاجرت از سیستم قدیمی — تنها جایی که اپراتور دادهٔ VINAK را وارد می‌کند.
 *
 * پیش‌فرض «آزمایشی» است، نه اجرا: مهاجرت روی دیتابیس واقعی هتل انجام
 * می‌شود و اپراتور باید اول ببیند چند کانال و اتاق پیدا شده. اجرای
 * واقعی دکمهٔ جداگانه دارد.
 */
class LegacyImportController extends Controller
{
    /** GET /admin/legacy */
    public function index(Request $req): void
    {
        if (!$this->superOnly()) return;

        $svc = new LegacyImportService();
        $this->view('admin.legacy.index', [
            'title'   => 'مهاجرت از سیستم قدیمی',
            'runs'    => $svc->history(Auth::tenantId(), 10),
            'default' => '/opt/hotel-media-src/legacy-data',
        ]);
    }

    /** POST /admin/legacy/preview — کشف منبع بدون نوشتن */
    public function preview(Request $req): void
    {
        if (!$this->superOnly()) return;

        $path = trim((string)$req->input('path', ''));
        $res  = (new LegacyImportService())->run($path, [
            'mode'      => 'dry',
            'tenant_id' => Auth::tenantId(),
            'logos'     => true,
            'teletext'  => true,
        ]);

        $this->flash($res['ok'] ? 'success' : 'error', $res['message']);
        $_SESSION['legacy_preview'] = $res['report'];
        $this->redirect('/admin/legacy');
    }

    /** POST /admin/legacy/import — اجرای واقعی */
    public function import(Request $req): void
    {
        if (!$this->superOnly()) return;

        $path = trim((string)$req->input('path', ''));
        $res  = (new LegacyImportService())->run($path, [
            'mode'      => 'merge',
            'tenant_id' => Auth::tenantId(),
            'logos'     => true,
            'teletext'  => (bool)$req->input('with_teletext'),
        ]);

        $this->log('legacy.import', 'iptv_channels', null, [], $res['report']);
        $this->flash($res['ok'] ? 'success' : 'error', $res['message']);
        $this->redirect('/admin/legacy');
    }

    private function superOnly(): bool
    {
        $auth = \App\Core\Auth::user() ?? [];
        if (($auth['role'] ?? '') === 'super_admin') return true;

        $this->flash('error', 'این بخش فقط برای مدیر ارشد است');
        $this->redirect('/admin');
        return false;
    }
}
