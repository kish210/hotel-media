<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\AiAssistantService;

/**
 * دستیار هوشمند — فقط مدیر ارشد.
 *
 * هیچ مسیری اینجا کلید API را برنمی‌گرداند و هیچ تغییری بدون تأیید
 * صریح اپراتور اعمال نمی‌شود (`apply` یک درخواست جدا است).
 */
class AiController extends Controller
{
    private function guard(bool $json = true): bool
    {
        if ((Auth::user()['role'] ?? '') === 'super_admin') return true;
        if ($json) Response::error('این بخش فقط برای مدیر ارشد است', 403);
        else { $this->flash('error', 'این بخش فقط برای مدیر ارشد است'); $this->redirect('/admin/dashboard'); }
        return false;
    }

    /** GET /admin/system/ai */
    public function index(Request $req): void
    {
        if (!$this->guard(false)) return;
        $svc = new AiAssistantService($this->db);
        $this->view('admin.system.ai', [
            'title'    => 'دستیار هوشمند',
            'status'   => $svc->status(),
            'problems' => $svc->enabled() ? $svc->problems((int)Auth::tenantId()) : [],
        ]);
    }

    /** GET /admin/system/ai/models */
    public function models(Request $req): void
    {
        if (!$this->guard()) return;
        $r = (new AiAssistantService($this->db))->models();
        $r['ok'] ? Response::success($r['models']) : Response::error($r['message'], 502);
    }

    /** POST /admin/system/ai/model — انتخاب مدل، در .env ذخیره می‌شود */
    public function setModel(Request $req): void
    {
        if (!$this->guard()) return;
        $m = trim((string)$req->post('model', ''));
        if ($m === '' || !preg_match('#^[A-Za-z0-9._/:\-]{1,120}$#', $m)) {
            Response::error('نام مدل معتبر نیست', 422); return;
        }
        $envFile = ROOT_PATH . '/.env';
        if (!is_writable($envFile)) { Response::error('فایل .env قابل نوشتن نیست', 500); return; }

        $txt = (string)file_get_contents($envFile);
        $txt = preg_match('/^AI_MODEL=.*$/m', $txt)
            ? (string)preg_replace('/^AI_MODEL=.*$/m', 'AI_MODEL=' . $m, $txt)
            : rtrim($txt) . "\nAI_MODEL=" . $m . "\n";
        file_put_contents($envFile, $txt, LOCK_EX);

        $this->log('ai.model', 'setting', null);
        Response::success(['model' => $m], 'مدل «' . $m . '» انتخاب شد');
    }

    /** POST /admin/system/ai/ask */
    public function ask(Request $req): void
    {
        if (!$this->guard()) return;
        $q = (string)$req->post('question', '');
        $r = (new AiAssistantService($this->db))->diagnose((int)Auth::tenantId(), $q);
        if (!$r['ok']) { Response::error($r['message'], 502); return; }
        $this->log('ai.diagnose', 'system', null);
        Response::success($r);
    }

    /** POST /admin/system/ai/apply — یک پیشنهاد، با تأیید اپراتور */
    public function apply(Request $req): void
    {
        if (!$this->guard()) return;
        $s = [
            'key'    => (string)$req->post('key', ''),
            'row_id' => (int)$req->post('row_id', 0),
            'value'  => $req->post('value'),
        ];
        $r = (new AiAssistantService($this->db))->apply((int)Auth::tenantId(), $s);
        if (!$r['ok']) { Response::error($r['message'], 422); return; }
        /* ثبت در تاریخچه: بعدا باید معلوم باشد چه چیزی با پیشنهاد دستیار
           عوض شده و مقدار قبلی‌اش چه بوده. */
        $this->log('ai.apply:' . $s['key'] . ':' . $s['row_id'] . ' از ' . (string)$r['before'], 'setting', $s['row_id']);
        Response::success($r, $r['message']);
    }
}
