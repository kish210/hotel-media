<?php
declare(strict_types=1);
namespace App\Core;

abstract class Controller
{
    protected Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    protected function view(string $view, array $data = []): void
    {
        Response::view($view, array_merge($data, [
            'auth'  => Auth::user(),
        ]));
    }

    protected function redirect(string $url): void
    {
        Response::redirect($url);
    }

    protected function flash(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_flash'][$type] = $message;
        }
    }

    /**
     * دسترسی لازم را بررسی می‌کند و در صورت نبود، درخواست را می‌بندد.
     *
     * چرا این متد لازم شد: نقشهٔ `Auth::$permissions` وجود داشت و
     * هیچ‌کس صدایش نمی‌زد، پس نقش‌ها در عمل بی‌اثر بودند. در عوض چند
     * کنترلر حساس همین بررسی را دستی و هر یک با متن پیام خودش تکرار
     * می‌کردند.
     *
     * پاسخ به شکل درخواست بستگی دارد: درخواست API باید ۴۰۳ با JSON
     * بگیرد (پلیر تلویزیون و اسکریپت‌ها HTML نمی‌فهمند) و درخواست پنل
     * باید با پیام به داشبورد برگردد، نه یک صفحهٔ خالی.
     *
     * @param string $permission مثل `emergency.send` یا `users.*`
     * @param bool   $json       پاسخ JSON بده حتی اگر مسیر زیر /api نباشد.
     *                           لازم است چون چند نقطهٔ `/admin/...` با
     *                           ‏fetch صدا زده می‌شوند و JSON برمی‌گردانند؛
     *                           هدایت ۳۰۲ به داشبورد، آن کد JS را گیج
     *                           می‌کند و خطای واقعی را پنهان.
     */
    protected function authorize(string $permission, bool $json = false): bool
    {
        if (Auth::can($permission)) return true;

        $isApi = $json
              || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')
              || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if ($isApi) {
            Response::error('برای این کار دسترسی ندارید', 403);
            return false;   // Response::error خودش خارج می‌شود
        }

        $this->flash('error', 'برای این بخش دسترسی ندارید');
        $this->redirect('/admin');
        return false;
    }

    protected function log(string $action, string $subjectType = null, int $subjectId = null, array $old = [], array $new = []): void
    {
        try {
            $user = Auth::user();
            $this->db->insert('activity_logs', [
                'tenant_id'    => $user['tenant_id'] ?? 1,
                'user_id'      => $user['id'] ?? null,
                'action'       => $action,
                'subject_type' => $subjectType,
                'subject_id'   => $subjectId,
                'old_values'   => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                'new_values'   => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'   => request()->ip(),
                'user_agent'   => substr(request()->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            // log نباید باعث crash صفحه بشه
            error_log('[LOG ERROR] ' . $e->getMessage());
        }
    }
}
