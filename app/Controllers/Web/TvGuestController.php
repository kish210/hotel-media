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
    private const VIEWS = ['reserve', 'services', 'folio', 'live', 'content'];

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

        /* همان صفحه‌ی زنده، فقط کانال‌های رادیویی */
        $radio = $req->get('radio') === '1';

        /* صفحه‌ی محتوا: خبر، قرآن، کتاب، دفترچه تلفن */
        $kind = in_array($req->get('kind'), ['news', 'quran', 'book', 'directory'], true) ? (string)$req->get('kind') : 'news';

        header('Cache-Control: no-store');
        include VIEWS_PATH . '/player/guest/' . $view . '.php';
    }

    /**
     * GET /tv/guest/{code}/book/{id}/{page} — یک صفحه‌ی PDF به‌صورت PNG.
     * مرورگر تلویزیون PDF باز نمی‌کند؛ BookService صفحه را تصویر می‌کند.
     */
    public function bookPage(Request $req, array $params): void
    {
        $code   = strtoupper(trim((string)($params['code'] ?? '')));
        $screen = $code !== '' ? (new Screen())->findByCode($code) : null;
        $item   = $screen ? $this->db->row(
            'SELECT id, file_url FROM content_items WHERE id = ? AND tenant_id = ? AND is_active = 1',
            [(int)($params['id'] ?? 0), (int)$screen['tenant_id']]
        ) : null;

        $books = new \App\Services\BookService();
        $pdf   = $item ? $books->localPdf($item['file_url']) : null;
        $png   = $pdf ? $books->page((int)$item['id'], $pdf, (int)($params['page'] ?? 0)) : null;
        if (!$png) { http_response_code(404); echo 'صفحه یافت نشد'; return; }

        header('Content-Type: image/png');
        header('Content-Length: ' . (string)filesize($png));
        header('Cache-Control: public, max-age=86400');
        readfile($png);
    }
}
