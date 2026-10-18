<?php declare(strict_types=1);
namespace App\Middleware;
use App\Core\{Request, Response};

class CsrfMiddleware
{
    public function handle(Request $request, callable $next): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            $next(); return;
        }

        // Skip for API routes (they use JWT)
        if (str_starts_with($request->path(), '/api/')) {
            $next(); return;
        }

        $token = $request->post('_token') ?? $request->header('X-CSRF-Token') ?? '';
        if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
            /* فرم معمولی (نه fetch): تا امروز تکنسین یک صفحهٔ JSON خام می‌دید و
               هرچه نوشته بود می‌پرید. حالا برمی‌گردد به همان صفحه با پیام فارسی. */
            if (!$request->isJson() && !$request->isAjax()) {
                $ref  = (string)($_SERVER['HTTP_REFERER'] ?? '');
                $host = (string)($_SERVER['HTTP_HOST'] ?? '');
                $back = ($ref !== '' && parse_url($ref, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) ? $ref : '/admin';
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['_flash']['error'] = 'نشست شما تمام شده بود؛ دوباره وارد شوید یا یک بار دیگر امتحان کنید.';
                }
                Response::redirect($back);
                return;
            }
            Response::error('CSRF token نامعتبر', 419);
        }

        $next();
    }

    public static function generate(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}
