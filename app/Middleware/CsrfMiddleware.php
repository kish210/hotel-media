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
