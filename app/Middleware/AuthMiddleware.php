<?php declare(strict_types=1);
namespace App\Middleware;
use App\Core\{Auth, Request, Response};

class AuthMiddleware
{
    public function handle(Request $request, callable $next): void
    {
        if (!Auth::check()) {
            if ($request->isJson() || $request->isAjax() || str_starts_with($request->path(), '/api/')) {
                Response::unauthorized();
            }
            $_SESSION['intended'] = $request->path();
            Response::redirect('/login');
        }
        $next();
    }
}
