<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};
use App\Services\OfferService;

/**
 * تخفیف کسب‌وکارهای محلی (TODO ۴.۳) — صفحه‌ی پنل و صفحه‌ی کسب‌وکار.
 *
 * صفحه‌ی کسب‌وکار (/partner/offer/{token}) بدون ورود است: صندوق‌دار
 * رستوران حساب پنل هتل ندارد. لینک هر پیشنهاد جداست و فقط کدهای همان
 * پیشنهاد را تأیید می‌کند؛ اگر لو رفت، از پنل عوضش کنید.
 */
class OfferWebController extends Controller
{
    /** GET /admin/offers */
    public function index(Request $req): void
    {
        $this->view('admin.iptv.offers', [
            'title'  => 'تخفیف کسب‌وکارهای محلی',
            'role'   => Auth::role(),
        ]);
    }

    /** GET|POST /partner/offer/{token} */
    public function partner(Request $req, array $params): void
    {
        $svc   = new OfferService($this->db);
        $token = (string)($params['token'] ?? '');
        $offer = $svc->partnerOffer($token);

        $result = null;
        if ($offer && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $result = $svc->partnerRedeem($token, (string)($_POST['code'] ?? ''));
        }

        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        header('Referrer-Policy: no-referrer');
        if (!$offer) http_response_code(404);
        include VIEWS_PATH . '/partner/offer.php';
    }
}
