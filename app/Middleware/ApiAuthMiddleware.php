<?php declare(strict_types=1);
namespace App\Middleware;
use App\Core\{Auth, Request, Response};

class ApiAuthMiddleware
{
    public function handle(Request $request, callable $next): void
    {
        if (!Auth::check()) {
            Response::error('احراز هویت لازم است', 401);
        }
        $next();
    }
}
