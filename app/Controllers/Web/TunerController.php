<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\{TvheadendService, TvheadendTunerService};

/**
 * تیونر و اسکن کانال — فقط super_admin.
 *
 * هر بار که تیونر عوض یا دوباره وصل می‌شود، همین‌جا «راه‌اندازی» و «اسکن»
 * کافی است؛ نیازی به نصب مجدد یا SSH نیست. فقط API رسمی TVHeadend استفاده
 * می‌شود (نگاه کنید به TvheadendTunerService).
 */
class TunerController extends Controller
{
    private function guard(bool $json = true): bool
    {
        if ((Auth::user()['role'] ?? '') === 'super_admin') return true;
        if ($json) Response::error('این بخش فقط برای مدیر ارشد است', 403);
        else { $this->flash('error', 'این بخش فقط برای مدیر ارشد است'); $this->redirect('/admin/dashboard'); }
        return false;
    }

    private function src(): ?array
    {
        return (new TvheadendTunerService())->source((int)Auth::tenantId());
    }

    /** GET /admin/system/tuner */
    public function index(Request $req): void
    {
        if (!$this->guard(false)) return;
        $this->view('admin.system.tuner', [
            'title'   => 'تیونر و اسکن کانال',
            'hasSrc'  => $this->src() !== null,
            'presets' => TvheadendTunerService::presets(),
        ]);
    }

    /** GET /admin/system/tuner/status */
    public function status(Request $req): void
    {
        if (!$this->guard()) return;
        $src = $this->src();
        if (!$src) { Response::error('TVHeadend در برنامه ثبت نیست', 404); return; }
        Response::success((new TvheadendTunerService())->status($src));
    }

    /** POST /admin/system/tuner/setup — preset=kish|iran_uhf یا freqs="578,682" */
    public function setup(Request $req): void
    {
        if (!$this->guard()) return;
        $src = $this->src();
        if (!$src) { Response::error('TVHeadend در برنامه ثبت نیست', 404); return; }

        $presets = TvheadendTunerService::presets();
        $key     = (string)$req->input('preset', '');
        $custom  = trim((string)$req->input('freqs', ''));
        if ($custom !== '') {
            $mhz = array_map('intval', preg_split('/[\s,،;]+/', $custom, -1, PREG_SPLIT_NO_EMPTY) ?: []);
            $t2  = [];
        } elseif (isset($presets[$key])) {
            $mhz = $presets[$key]['mhz'];
            $t2  = $presets[$key]['t2'] ?? [];
        } else {
            Response::error('یک پیش‌تنظیم یا فهرست فرکانس بدهید', 422);
            return;
        }
        $r = (new TvheadendTunerService())->setup($src, $mhz, $t2);
        $r['ok'] ? Response::success($r, $r['message']) : Response::error($r['message'], 422);
    }

    /** POST /admin/system/tuner/scan */
    public function scan(Request $req): void
    {
        if (!$this->guard()) return;
        $src = $this->src();
        if (!$src) { Response::error('TVHeadend در برنامه ثبت نیست', 404); return; }
        $r = (new TvheadendTunerService())->scan($src);
        $r['ok'] ? Response::success($r, $r['message']) : Response::error($r['message'], 422);
    }

    /** POST /admin/system/tuner/import — سرویس‌ها را کانال کن، بعد وارد برنامه کن */
    public function import(Request $req): void
    {
        if (!$this->guard()) return;
        $src = $this->src();
        if (!$src) { Response::error('TVHeadend در برنامه ثبت نیست', 404); return; }

        $svc = new TvheadendTunerService();
        $map = $svc->mapServices($src);
        if (!$map['ok']) { Response::error('تبدیل سرویس به کانال ناموفق: ' . $map['message'], 422); return; }

        $sync = (new TvheadendService())->syncChannels((int)Auth::tenantId(), (int)$src['id']);
        $sync['ok']
            ? Response::success($sync + ['mapped' => $map['services']], $sync['message'])
            : Response::error($sync['message'], 422);
    }
}
