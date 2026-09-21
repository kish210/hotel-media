<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request};

/**
 * TV Bootstrap
 * یک آدرس ثابت برای همه‌ی تلویزیون‌های هتل.
 *
 * چرا لازم است: در منوی مخفی LG (نگه‌داشتن MENU → 1105 → Manual Pro:Centric)
 * و منوی هتلی سامسونگ (MUTE → 1 → 1 → 9 → ENTER → URL Launcher) تکنسین
 * **یک آدرس واحد** را در همه‌ی دستگاه‌ها وارد می‌کند. پس آدرسی مثل
 * /player/{code} که برای هر تلویزیون فرق دارد، برای نصب انبوه بی‌فایده است.
 *
 * این صفحه به‌جای آن، خود دستگاه را می‌شناسد: MAC و مدل را از API پلتفرم
 * می‌خواند، یک بار با توکن ثبت می‌شود، کد اختصاصی می‌گیرد و از آن به بعد
 * مستقیم به پلیر خودش می‌رود.
 *
 * فاز ۴ نقشه‌راه — docs/TODO.md (۵.۴)
 */
class TvBootstrapController extends Controller
{
    public function index(Request $req): void
    {
        // توکن از query (اگر منوی تلویزیون اجازه‌ی query بدهد)، وگرنه
        // توکن پیش‌فرض فعال — چون Pro:Centric فقط IP و پورت می‌گیرد.
        $token = trim((string)$req->get('t', ''));

        if ($token === '') {
            $row = $this->db->row(
                "SELECT token FROM enrollment_tokens
                  WHERE is_active = 1
                    AND (expires_at IS NULL OR expires_at > NOW())
                    AND (max_devices IS NULL OR used_count < max_devices)
                  ORDER BY id DESC LIMIT 1"
            );
            $token = (string)($row['token'] ?? '');
        }

        // اگر قبلا در همین مرورگر ثبت شده، صفحه خودش redirect می‌کند
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');

        include VIEWS_PATH . '/player/bootstrap.php';
    }
}
