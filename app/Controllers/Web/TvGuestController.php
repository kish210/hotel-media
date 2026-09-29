<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request};
use App\Models\Screen;

/**
 * صفحه‌های تعاملی مهمان روی تلویزیون اتاق.
 *
 * پورتال IPTV (profiles/iptv.php) این صفحه‌ها را در iframe باز می‌کند
 * و کلیدهای ریموت را به آن می‌دهد. جدا از پورتال‌اند چون هرکدام
 * چند مرحله‌ی خودش را دارد (انتخاب محل، روز، نوبت، تایید) و جا دادنشان
 * در یک فایل ۱۳۰۰ خطی، آن را نگه‌نداشتنی می‌کرد.
 *
 * هویت همان کد صفحه‌نمایش است، مثل /api/v1/guest/{code}/…
 */
class TvGuestController extends Controller
{
    private const VIEWS = ['reserve', 'services', 'folio'];

    /** GET /tv/guest/{code}/{view} */
    public function show(Request $req, array $params): void
    {
        $code = strtoupper(trim((string)($params['code'] ?? '')));
        $view = (string)($params['view'] ?? '');

        $screen = $code !== '' ? (new Screen())->findByCode($code) : null;
        if (!$screen || !in_array($view, self::VIEWS, true)) {
            http_response_code(404);
            echo 'صفحه یافت نشد';
            return;
        }

        /* تاریخ شمسی را سرور می‌سازد: مرورگر تلویزیون داده‌ی Intl برای
           fa-IR ندارد و آنجا تاریخ میلادی نشان می‌دهد. ۶۰ روز کافی است،
           سقف days_ahead هم همین است. */
        $days = [];
        $base = strtotime('today');
        for ($i = 0; $i <= 60; $i++) {
            $ts = strtotime("+$i day", $base);
            $days[] = [
                'date'  => date('Y-m-d', $ts),
                'label' => $i === 0 ? 'امروز' : ($i === 1 ? 'فردا' : jalaliDate($ts, true)),
                'short' => jalaliDate($ts, false),
            ];
        }

        /* ساعت سرور برای بیدارباش و تاکسی — ساعت تلویزیون قابل اعتماد نیست */
        $serverTs = time();
        $serverTz = (int)date('Z');

        header('Cache-Control: no-store');
        include VIEWS_PATH . '/player/guest/' . $view . '.php';
    }
}
