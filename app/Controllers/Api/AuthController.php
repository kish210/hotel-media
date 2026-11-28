<?php declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Controller, Auth, JWT, Response, Request};
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $req): void
    {
        /* بدنه را هم فرم و هم JSON می‌خوانیم: کلاینت‌ها (ویندوز/اندروید/رزبری‌پای)
           JSON می‌فرستند و $req->post() برای آن‌ها خالی است. */
        $data     = $req->post() ?: ($req->json() ?: []);
        $email    = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        $errors = [];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'ایمیل معتبر الزامی است';
        }
        if (strlen($password) < 6) {
            $errors['password'] = 'رمز عبور باید حداقل ۶ کاراکتر باشد';
        }
        if ($errors) { Response::error('داده‌های ورودی نامعتبر', 422, $errors); return; }

        /* ⚠ این متد پیش‌تر `Auth::login([...credentials])` را صدا می‌زد،
           ولی Auth::login() اصلاً رمز را بررسی نمی‌کند: پارامترش ردیفِ
           کاربر است و فقط نشست می‌سازد (void). نتیجه این بود که
           POST /api/v1/auth/login هرگز توکن نمی‌داد و همیشه ۴۰۱ برمی‌گرداند
           (Auth::loginWithToken هم اصلاً وجود نداشت). حالا مثل ورود وب،
           کاربر خوانده و رمز با password_verify بررسی می‌شود. */
        $user = $this->db->row(
            "SELECT u.*, t.slug AS tenant_slug FROM users u
             JOIN tenants t ON t.id = u.tenant_id
             WHERE u.email = ? AND u.is_active = 1 AND u.deleted_at IS NULL
             LIMIT 1",
            [$email]
        );

        if (!$user || !Auth::verifyPassword($password, (string)$user['password'])) {
            error_log("[AUTH API] Failed login attempt for: $email from " . $req->ip());
            Response::error('ایمیل یا رمز عبور اشتباه است', 401);
            return;
        }

        $this->db->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $req->ip(),
        ], ['id' => $user['id']]);

        // ‏ApiAuthMiddleware فقط «sub» را می‌خواند و بقیه را از دیتابیس
        // تازه می‌کند؛ tenant_id و role برای کلاینت‌ها مفیدند.
        $token = JWT::encode([
            'sub'       => (int)$user['id'],
            'tenant_id' => (int)$user['tenant_id'],
            'role'      => $user['role'],
        ]);
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
