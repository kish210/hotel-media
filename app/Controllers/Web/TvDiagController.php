<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Response};

/**
 * عیب‌یابی پخش روی خودِ تلویزیون.
 *
 * چرا: مرورگر تلویزیون سامسونگ ۲۰۱۳ کنسول ندارد و از راه دور قابل
 * بازرسی نیست. وقتی تابلو «ویدیو سیاه است» می‌دهد، هیچ راهی نبود جز
 * حدس‌زدن. این صفحه را خودِ دستگاه باز می‌کند، قابلیت‌هایش را می‌سنجد
 * و نتیجه را هم روی صفحه می‌نویسد و هم اینجا می‌فرستد.
 *
 * بدون ورود کار می‌کند، چون تلویزیون نمی‌تواند وارد پنل شود — مثل خود
 * پلیر که با کد صفحه شناخته می‌شود. چیزی هم تغییر نمی‌دهد.
 */
class TvDiagController extends Controller
{
    /** GET /tv/diag/{code} */
    public function show(Request $req, array $p): void
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($p['code'] ?? '')));
        if ($code === '') { http_response_code(404); echo 'bad code'; return; }

        /* ویدیوهای واقعی همین هتل، نه فایل نمونه: مشکل ممکن است فقط با
           قالب یا رزولوشن همین فایل‌ها باشد. */
        $videos = [];
        try {
            $screen = $this->db->row('SELECT tenant_id FROM screens WHERE code = ?', [$code]);
            $tid    = (int)($screen['tenant_id'] ?? 1);
            $rows   = $this->db->rows(
                "SELECT name, file_path, width, height FROM media
                  WHERE tenant_id = ? AND type = 'video' AND deleted_at IS NULL
                    AND file_path LIKE '/uploads/%'
                  ORDER BY id LIMIT 3",
                [$tid]
            ) ?: [];
            $pub = defined('PUBLIC_PATH') ? PUBLIC_PATH : ROOT_PATH . '/public';
            foreach ($rows as $r) {
                if (!is_file($pub . $r['file_path'])) continue;
                $videos[] = [
                    'name' => mb_substr((string)$r['name'], 0, 40)
                            . ' (' . (int)$r['width'] . '×' . (int)$r['height'] . ')',
                    'url'  => (string)$r['file_path'],
                ];
            }
        } catch (\Throwable) {}

        if (!$videos) {
            $videos[] = ['name' => 'هیچ ویدیویی روی این سرور نبود', 'url' => ''];
        }

        include VIEWS_PATH . '/player/diag.php';
    }

    /**
     * POST /tv/diag/{code} — گزارش دستگاه.
     * در لاگ می‌نشیند تا از پنل یا SSH خوانده شود؛ جدول تازه‌ای لازم ندارد.
     */
    public function report(Request $req, array $p): void
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($p['code'] ?? '')));
        $raw  = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);

        if (!is_array($data)) { Response::error('گزارش نامعتبر', 422); return; }

        $out = "[TV-DIAG] $code ua=" . mb_substr((string)($data['ua'] ?? ''), 0, 160) . "\n";
        foreach ((array)($data['steps'] ?? []) as $s) {
            $out .= '    ' . mb_substr((string)($s['k'] ?? ''), 0, 60)
                  . ' = ' . mb_substr((string)($s['v'] ?? ''), 0, 120) . "\n";
        }
        error_log($out);

        $file = ROOT_PATH . '/storage/logs/tv-diag.log';
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . "] $out", FILE_APPEND | LOCK_EX);

        Response::success(null, 'دریافت شد');
    }
}
