<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};

/**
 * Log Viewer — نمایشگر لاگ برای عیب‌یابی (فقط مدیر ارشد).
 *
 * دو منبع را کنار هم می‌گذارد، چون برای debug هر دو لازم‌اند:
 *   • خطاهای PHP/Exception — از storage/logs/php-errors.log که
 *     set_error_handler و set_exception_handler در public/index.php
 *     می‌نویسند (فقط وقتی APP_DEBUG=false، یعنی روی سرور تولید).
 *   • رویدادهای برنامه — جدول activity_logs که Controller::log()
 *     و DeviceService::logEvent() پر می‌کنند.
 *
 * چرا فقط super_admin: لاگ خطا مسیر فایل‌ها، کوئری و گاهی داده‌ی
 * مهمان را نشان می‌دهد.
 */
class LogViewerController extends Controller
{
    /** بیشتر از این خط خوانده نمی‌شود تا صفحه و حافظه منفجر نشود */
    private const MAX_LINES = 400;

    /** بیشتر از این حجم از انتهای فایل خوانده نمی‌شود */
    private const TAIL_BYTES = 512 * 1024;

    private function gate(): bool
    {
        if ((Auth::user()['role'] ?? '') !== 'super_admin') {
            $this->flash('error', 'این بخش فقط برای مدیر ارشد است');
            $this->redirect('/admin/dashboard');
            return false;
        }
        return true;
    }

    public function index(Request $req): void
    {
        if (!$this->gate()) return;

        $level  = (string)$req->get('level', '');      // error | warning | ''
        $search = trim((string)$req->get('q', ''));

        $errorLines = $this->tail(STORAGE_PATH . '/logs/php-errors.log');

        // فیلتر ساده روی متن خط
        if ($level === 'error') {
            $errorLines = array_values(array_filter($errorLines, static fn($l) =>
                stripos($l, 'EXCEPTION') !== false || stripos($l, 'Fatal') !== false
                || stripos($l, 'PHP 1') !== false || stripos($l, 'Error') !== false));
        } elseif ($level === 'warning') {
            $errorLines = array_values(array_filter($errorLines, static fn($l) =>
                stripos($l, 'Warning') !== false || stripos($l, 'Deprecated') !== false));
        }
        if ($search !== '') {
            $errorLines = array_values(array_filter($errorLines, static fn($l) =>
                mb_stripos($l, $search) !== false));
        }

        $events = [];
        try {
            $sql = "SELECT a.*, u.name AS user_name
                      FROM activity_logs a
                      LEFT JOIN users u ON u.id = a.user_id
                     WHERE a.tenant_id = ?";
            $p = [Auth::tenantId()];
            if ($search !== '') { $sql .= " AND (a.action LIKE ? OR a.description LIKE ?)"; $p[] = "%$search%"; $p[] = "%$search%"; }
            $sql .= " ORDER BY a.id DESC LIMIT 200";
            $events = $this->db->rows($sql, $p);
        } catch (\Throwable $e) {}

        $logPath = STORAGE_PATH . '/logs/php-errors.log';
        $logSize = is_file($logPath) ? (int)filesize($logPath) : 0;

        $this->view('admin.system.logs', [
            'title'      => 'لاگ و عیب‌یابی',
            'errorLines' => $errorLines,
            'events'     => $events,
            'level'      => $level,
            'search'     => $search,
            'logSize'    => $logSize,
            'logExists'  => is_file($logPath),
        ]);
    }

    /** خالی‌کردن فایل لاگ خطا — بعد از رفع اشکال به‌کار می‌آید */
    public function clear(Request $req): void
    {
        if (!$this->gate()) return;

        $path = STORAGE_PATH . '/logs/php-errors.log';
        if (is_file($path) && is_writable($path)) {
            file_put_contents($path, '');
            $this->log('logs.clear');
            $this->flash('success', 'فایل لاگ خطا خالی شد');
        } else {
            $this->flash('error', 'فایل لاگ قابل نوشتن نیست');
        }
        $this->redirect('/admin/system/logs');
    }

    /**
     * انتهای فایل را می‌خواند. کل فایل خوانده نمی‌شود چون روی سروری
     * که چند روز خطا داده، می‌تواند چند ده مگابایت باشد.
     * @return list<string>
     */
    private function tail(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) return [];

        $size = (int)filesize($path);
        if ($size === 0) return [];

        $fh = @fopen($path, 'rb');
        if (!$fh) return [];

        if ($size > self::TAIL_BYTES) fseek($fh, -self::TAIL_BYTES, SEEK_END);
        $data = (string)stream_get_contents($fh);
        fclose($fh);

        $lines = preg_split('/\r?\n/', $data) ?: [];
        // خط اول ممکن است نیمه باشد چون از وسط فایل شروع کرده‌ایم
        if ($size > self::TAIL_BYTES && count($lines) > 1) array_shift($lines);

        $lines = array_values(array_filter($lines, static fn($l) => trim($l) !== ''));
        if (count($lines) > self::MAX_LINES) {
            $lines = array_slice($lines, -self::MAX_LINES);
        }
        return array_reverse($lines);   // تازه‌ترین بالا
    }
}
