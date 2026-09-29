<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request};
use App\Services\BookService;

/**
 * پنل محتوای اتاق — خبر، قرآن، کتاب، دفترچه تلفن.
 *
 * API این‌ها (/api/v1/content) از فاز ۳ بود ولی هیچ صفحه‌ای در پنل
 * نداشت؛ اپراتور فقط با ابزار API می‌توانست کتاب یا شماره‌ی داخلی
 * اضافه کند. صفحه فقط اسکلت است و داده را از همان API می‌خواند.
 */
class ContentWebController extends Controller
{
    public function index(Request $req): void
    {
        $this->view('admin.content.index', [
            'title'    => 'محتوای اتاق',
            'pdfReady' => (new BookService())->available(),
        ]);
    }
}
