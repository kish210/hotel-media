<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\{Controller, Request, Response, Auth};
use App\Services\DiagnosticsService;

/**
 * عیب‌یابی سرور — فقط برای مدیر ارشد.
 *
 * چرا فقط super_admin: خروجی این صفحه نشانی‌های شبکه، پورت‌های باز و
 * ساختار سرویس‌ها را نشان می‌دهد. این‌ها برای عیب‌یابی لازم‌اند و
 * برای کسی که بخواهد به سرور نفوذ کند هم همان‌قدر مفیدند.
 *
 * این کنترلر فقط می‌خواند. عمدا هیچ دکمه‌ای برای روشن/خاموش کردن
 * سرویس ندارد: اجرای systemctl از دلِ وب یعنی دادن دسترسی ریشه به
 * www-data، و آن دسترسی بعدا جای دیگری هم استفاده می‌شود. به‌جایش
 * فرمان درست را نشان می‌دهد تا آدم پای SSH اجرایش کند.
 */
class DiagnosticsController extends Controller
{
    private function guard(): bool
    {
        if ((Auth::user()['role'] ?? '') !== 'super_admin') {
            Response::error('این بخش فقط برای مدیر ارشد است', 403);
            return false;
        }
        return true;
    }

    /** GET /admin/system/diagnostics */
    public function index(Request $req): void
    {
        if (!$this->guard()) return;

        $title    = 'عیب‌یابی سرور';
        /* بررسی‌ها چند ثانیه طول می‌کشند (تماس شبکه‌ای و systemctl).
           همان اول اجرا می‌شوند تا صفحه یک‌باره کامل بیاید؛ نسخه‌ی
           JSON برای تازه‌سازی بدون بارگذاری دوباره است. */
        $sections = (new DiagnosticsService($this->db))->all();

        include VIEWS_PATH . '/admin/system/diagnostics.php';
    }

    /**
     * GET /admin/system/diagnostics/json
     *
     * برای دکمه‌ی «بررسی دوباره» — بدون بارگذاری دوباره‌ی کل صفحه.
     */
    public function json(Request $req): void
    {
        if (!$this->guard()) return;
        Response::success((new DiagnosticsService($this->db))->all());
    }
}
