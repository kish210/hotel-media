<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Auth, Request, Database};

class AuthController extends Controller
{
    public function showLogin(Request $req): void
    {
        // اگه وارد شده بود به dashboard برو
        if (Auth::check()) {
            $this->redirect('/admin/dashboard');
            return;
        }
        $this->view('auth.login', ['title' => 'ورود به سیستم']);
    }

    public function login(Request $req): void
    {
        $email    = trim($req->post('email', ''));
        $password = $req->post('password', '');

        if (!$email || !$password) {
            $this->view('auth.login', [
                'title' => 'ورود',
                'error' => 'ایمیل و رمز عبور لازم است',
                'old'   => ['email' => $email],
            ]);
            return;
        }

        try {
            $db   = Database::getInstance();
            $user = $db->row(
                "SELECT u.*, t.slug AS tenant_slug FROM users u
                 JOIN tenants t ON t.id = u.tenant_id
                 WHERE u.email = ? AND u.is_active = 1 AND u.deleted_at IS NULL
                 LIMIT 1",
                [$email]
            );

            if (!$user || !Auth::verifyPassword($password, $user['password'])) {
                // لاگ تلاش‌های ناموفق
                error_log("[AUTH] Failed login attempt for: $email from " . $req->ip());
                $this->view('auth.login', [
                    'title' => 'ورود',
                    'error' => 'ایمیل یا رمز عبور اشتباه است',
                    'old'   => ['email' => $email],
                ]);
                return;
            }

            // ذخیره زمان آخرین ورود
            $db->update('users', ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => $req->ip()], ['id' => $user['id']]);

            // ورود
            Auth::login($user);

            $intended = $_SESSION['intended'] ?? '/admin/dashboard';
            unset($_SESSION['intended']);
            $this->redirect($intended);

        } catch (\Throwable $e) {
            error_log('[AUTH ERROR] ' . $e->getMessage());
            $this->view('auth.login', [
                'title' => 'ورود',
                'error' => 'خطای سرور. لطفاً دوباره تلاش کنید.',
                'old'   => ['email' => $email],
            ]);
        }
    }

    public function logout(Request $req): void
    {
        Auth::logout();
        $this->redirect('/login');
    }
}
