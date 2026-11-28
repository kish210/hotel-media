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

    /**
     * POST /admin/system/ai/config — تنظیم اتصال از خود پنل.
     *
     * پیش از این تنها راه، ویرایش دستی .env روی SSH بود؛ روی همین سرور
     * همین باعث شد بخش «تنظیم نشده» بماند و هیچ‌کس نتواند فعالش کند.
     * کلید نوشته می‌شود ولی هیچ‌وقت بازخوانی و نمایش داده نمی‌شود —
     * فقط طول و ۴ کاراکتر آخرش (در status) دیده می‌شود.
     */
    public function saveConfig(Request $req): void
    {
        if (!$this->guard()) return;

        $base   = trim((string)$req->post('base_url', ''));
        $key    = trim((string)$req->post('api_key', ''));
        $model  = trim((string)$req->post('model', ''));
        $on     = (string)$req->post('enabled', '') === '1';
        $clear  = (string)$req->post('clear_key', '') === '1';

        if ($base !== '' && !preg_match('#^https?://[^\s//]{3,200}(/\S*)?$#', $base)) {
            Response::error('آدرس سرویس معتبر نیست (باید با http:// یا https:// شروع شود)', 422); return;
        }
        if ($key !== '' && !preg_match('#^[\x21-\x7e]{8,200}$#', $key)) {
            Response::error('کلید معتبر نیست (۸ تا ۲۰۰ نویسه، بدون فاصله)', 422); return;
        }
        if ($model !== '' && !preg_match('#^[A-Za-z0-9._/:\-]{1,120}$#', $model)) {
            Response::error('نام مدل معتبر نیست', 422); return;
        }

        $curKey = $clear ? '' : trim((string)env('AI_API_KEY', ''));
        $pairs  = [
            'AI_BASE_URL' => $base,
            'AI_MODEL'    => $model,
            // کلید فقط وقتی نوشته می‌شود که اپراتور مقدار تازه بدهد؛
            // فیلد در فرم همیشه خالی نمایش داده می‌شود.
            'AI_ENABLED'  => ($on && $base !== '' && ($key !== '' || $curKey !== '')) ? 'true' : 'false',
        ];
        if ($key !== '')     $pairs['AI_API_KEY'] = $key;
        elseif ($clear)      $pairs['AI_API_KEY'] = '';

        if (!$this->envPut($pairs)) {
            Response::error('فایل .env قابل نوشتن نیست — مجوز فایل را بررسی کنید', 500); return;
        }

        $this->log('ai.config', 'setting', null);
        Response::success(
            ['enabled' => $pairs['AI_ENABLED'] === 'true'],
            $pairs['AI_ENABLED'] === 'true' ? 'اتصال ذخیره شد' : 'ذخیره شد؛ دستیار خاموش است'
        );
    }

    /** نوشتن/جایگزینی چند کلید در .env بدون دست‌زدن به بقیهٔ خطوط. */
    private function envPut(array $pairs): bool
    {
        $envFile = ROOT_PATH . '/.env';
        if (!is_writable($envFile)) return false;

        $txt = (string)file_get_contents($envFile);
        foreach ($pairs as $k => $v) {
            $line = $k . '=' . $v;
            $re   = '/^' . preg_quote((string)$k, '/') . '=.*$/m';
            $txt  = preg_match($re, $txt)
                ? (string)preg_replace($re, $line, $txt)
                : rtrim($txt) . "\n" . $line . "\n";
        }
        return file_put_contents($envFile, $txt, LOCK_EX) !== false;
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
