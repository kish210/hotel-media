<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\SystemUpdateService;

/**
 * به‌روزرسانی سرور از گیت‌هاب — فقط برای مدیر ارشد.
 *
 * زنجیره: گیت‌هاب (انتشار پایدار) → سرور هتل → ۳۰۰ تلویزیون
 *
 * چرا فقط super_admin: این عملیات فایل‌های کد را جایگزین و مهاجرت
 * دیتابیس اجرا می‌کند. اگر وسط کار قطع شود، هتل بدون تلویزیون
 * می‌ماند. کارمند پذیرش نباید دکمه‌اش را ببیند.
 */
class SystemUpdateController extends Controller
{
    private function guard(): bool
    {
        $u = Auth::user();
        if (($u['role'] ?? '') !== 'super_admin') {
            Response::error('این بخش فقط برای مدیر ارشد است', 403);
            return false;
        }
        return true;
    }

    /** GET /admin/system/update */
    public function index(Request $req): void
    {
        if (!$this->guard()) return;

        $svc = new SystemUpdateService($this->db);

        $title   = 'به‌روزرسانی سیستم';
        $current = $svc->current();
        $history = $svc->history(15);
        $backups = $svc->backups();

        include VIEWS_PATH . '/admin/system/update.php';
    }

    /**
     * GET /admin/system/update/check
     *
     * جدا از index است چون تماس با گیت‌هاب روی اینترنت کند هتل چند
     * ثانیه طول می‌کشد؛ اگر داخل بارگذاری صفحه بود، هر بار باز کردن
     * پنل معطلی داشت.
     */
    public function check(Request $req): void
    {
        if (!$this->guard()) return;
        Response::json((new SystemUpdateService($this->db))->check());
    }

    /** POST /admin/system/update/apply */
    public function apply(Request $req): void
    {
        if (!$this->guard()) return;

        $svc = new SystemUpdateService($this->db);

        /* اگر به‌روزرسانی دیگری در جریان است، دومی را شروع نکن — دو
           فرایند همزمان که فایل‌های کد را جایگزین می‌کنند نتیجه‌اش
           نصب نیمه‌کاره است. */
        $state = $svc->state();
        $busy  = !in_array($state['step'] ?? 'idle', ['idle', 'done', 'failed'], true);
        if ($busy && (time() - (int)($state['at'] ?? 0)) < 900) {
            Response::json([
                'ok' => false,
                'message' => 'یک به‌روزرسانی در حال اجراست (' . $state['step'] . ')',
            ], 409);
            return;
        }

        /* به‌روزرسانی چند دقیقه طول می‌کشد و از مهلت معمول وب بیشتر
           است. مرورگر پیشرفت را از مسیر state می‌پرسد. */
        @set_time_limit(1800);
        @ignore_user_abort(true);

        Response::json($svc->apply());
    }

    /** GET /admin/system/update/state — برای نوار پیشرفت */
    public function state(Request $req): void
    {
        if (!$this->guard()) return;
        Response::json((new SystemUpdateService($this->db))->state());
    }

    /** POST /admin/system/update/backup — پشتیبان دستی */
    public function backup(Request $req): void
    {
        if (!$this->guard()) return;
        Response::json((new SystemUpdateService($this->db))->backup());
    }
}
