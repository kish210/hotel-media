<?php declare(strict_types=1);
namespace App\Middleware;
use App\Core\{Auth, Request, Response};

class GuestMiddleware
{
    public function handle(Request $request, callable $next): void
    {
        if (Auth::check()) {
            Response::redirect('/admin/dashboard');
        }
        $next();
    }
}
