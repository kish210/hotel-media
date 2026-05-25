<?php declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Controller, Auth, Response, Request};
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $req): void
    {
        $errors = $req->validate(['email' => 'required|email', 'password' => 'required|min:6']);
        if ($errors) Response::error('داده‌های ورودی نامعتبر', 422, $errors);

        $user = Auth::login(['email' => $req->post('email'), 'password' => $req->post('password')]);
        if (!$user) Response::error('ایمیل یا رمز عبور اشتباه است', 401);

        $token = Auth::loginWithToken($user);
        Response::success([
            'token'      => $token,
            'expires_in' => (int)env('JWT_EXPIRY', 86400),
            'user'       => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ], 'ورود موفق');
    }

    public function me(Request $req): void
    {
        $user = Auth::user();
        Response::success([
            'id'        => $user['id'],
            'name'      => $user['name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
            'language'  => $user['language'],
            'tenant_id' => $user['tenant_id'],
        ]);
    }

    public function logout(Request $req): void
    {
        Auth::logout();
        Response::success(null, 'خروج موفق');
    }
}
