<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Auth, Controller, Request};
use App\Services\TeletextService;

/**
 * تله‌تکست — پنل ویرایش صفحه‌های ۴۰×۲۳.
 *
 * چرا ۴۰ ستون: همان محدودیتی که سیستم قدیمی داشت و روی Orsay/Tizen
 * ۲۰۱۳ قابل نمایش است. اگر متن بلندتر باشد بریده می‌شود و اپراتور
 * همان‌جا در پیش‌نمایش می‌بیند، نه اینکه ذخیره رد شود.
 */
class TeletextController extends Controller
{
    /** GET /admin/teletext */
    public function index(Request $req): void
    {
        $svc  = new TeletextService();
        $lang = in_array($req->get('lang'), ['fa', 'en', 'ar'], true) ? (string)$req->get('lang') : 'fa';

        $this->view('admin.teletext.index', [
            'title'   => 'تله‌تکست',
            'pages'   => $svc->pages(Auth::tenantId(), $lang),
            'lang'    => $lang,
            'cols'    => TeletextService::COLS,
            'rows'    => TeletextService::ROWS,
        ]);
    }

    /** POST /admin/teletext — ساخت یا ویرایش */
    public function save(Request $req): void
    {
        $svc = new TeletextService();
        $res = $svc->save([
            'page_no'    => $req->input('page_no'),
            'lang'       => $req->input('lang', 'fa'),
            'title'      => $req->input('title', ''),
            'body'       => $req->input('body', ''),
            'color'      => $req->input('color'),
            'sort_order' => $req->input('sort_order', 0),
            'is_active'  => $req->input('is_active') ? 1 : 0,
        ], Auth::tenantId());

        $this->flash($res['ok'] ? 'success' : 'error', $res['message']);
        $this->redirect('/admin/teletext?lang=' . urlencode((string)$req->input('lang', 'fa')));
    }

    /** POST /admin/teletext/{id}/delete */
    public function delete(Request $req, array $params): void
    {
        $ok = (new TeletextService())->delete((int)($params['id'] ?? 0), Auth::tenantId());
        $this->flash($ok ? 'success' : 'error', $ok ? 'صفحه حذف شد' : 'صفحه پیدا نشد');
        $this->redirect('/admin/teletext');
    }

    /** POST /admin/teletext/import — ورود از فایل‌های متنی سیستم قدیمی */
    public function import(Request $req): void
    {
        $dir = trim((string)$req->input('dir', ''));
        if ($dir === '' || !is_dir($dir)) {
            $this->flash('error', 'مسیر پوشهٔ conf سیستم قدیمی را بدهید');
            $this->redirect('/admin/teletext');
            return;
        }

        $res = (new TeletextService())->importDir($dir, Auth::tenantId());
        $this->flash('success', sprintf('%d صفحه از %d فایل وارد شد', $res['pages'], $res['files']));
        $this->redirect('/admin/teletext');
    }
}
