<?php
declare(strict_types=1);

namespace App\Core;

class Auth
{
    private static ?array $user = null;

    /**
     * نقشهٔ دسترسی نقش‌ها.
     *
     * تا فاز ۹ این نقشه **تزئینی** بود: `can()` وجود داشت و هیچ مسیری
     * صدایش نمی‌زد. صفحات حساس (کاربران، به‌روزرسانی، لاگ، ترنسکد)
     * گیت دستی `super_admin` داشتند، ولی سطوحی که در فازهای ۲ تا ۸
     * ساخته شد هیچ گیتی نداشتند — از جمله اعلان اضطراری، که یعنی هر
     * حساب `viewer` می‌توانست روی تلویزیون تمام اتاق‌های هتل دستور
     * تخلیه بفرستد.
     *
     * ‏`zones` و `emergency` و `vod` و `iptv` اینجا اضافه شدند چون پیش
     * از اعمالِ نقشه باید کامل باشد، وگرنه اعمالش دسترسی موجودِ
     * ‏`admin` را می‌گرفت — بدتر از نبودنش.
     */
    private static array $permissions = [
        'super_admin' => ['*'],
        'admin'       => ['dashboard', 'screens.*', 'zones.*', 'playlists.*', 'media.*', 'layouts.*',
                          'schedules.*', 'vod.*', 'iptv.*', 'users.*', 'settings.*', 'messages.*',
                          'emergency.*', 'reports.*', 'modules.*'],
        /* مدیر شعبه: محتوا و صفحه‌ها بله، کاربر و تنظیمات نه.
           اعلان اضطراری بله — او کسی است که شب در هتل حاضر است. */
        'manager'     => ['dashboard', 'screens.*', 'zones.*', 'playlists.*', 'media.*', 'layouts.*',
                          'schedules.*', 'vod.*', 'iptv.view', 'messages.*', 'emergency.*',
                          'reports.view', 'modules.view'],
        /* تولیدکنندهٔ محتوا: می‌سازد و منتشر می‌کند، ولی اعلان اضطراری
           و تنظیم دستگاه کار او نیست. */
        'editor'      => ['dashboard', 'media.*', 'playlists.*', 'layouts.*', 'vod.*', 'messages.view'],
        'viewer'      => ['dashboard', 'reports.view', 'screens.view'],
    ];

    public static function check(): bool
    {
        if (self::$user !== null) return true;

        // ── JWT (API requests) ──
        $token = request()->bearerToken();
        if ($token) {
            $payload = JWT::decode($token);
            if ($payload && isset($payload['sub'])) {
                try {
                    $db   = Database::getInstance();
                    $user = $db->row(
                        "SELECT u.*, t.slug AS tenant_slug, t.name AS tenant_name
                         FROM users u JOIN tenants t ON t.id = u.tenant_id
                         WHERE u.id=? AND u.is_active=1 AND u.deleted_at IS NULL",
                        [$payload['sub']]
                    );
                    if ($user) {
                        self::$user = $user;
                        return true;
                    }
                } catch (\Throwable) {}
            }
            return false;
        }

        // ── Session (web requests) ──
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();

        if (!empty($_SESSION['user_id'])) {
            try {
                $db   = Database::getInstance();
                $user = $db->row(
                    "SELECT u.*, t.slug AS tenant_slug, t.name AS tenant_name
                     FROM users u JOIN tenants t ON t.id = u.tenant_id
                     WHERE u.id=? AND u.is_active=1 AND u.deleted_at IS NULL",
                    [$_SESSION['user_id']]
                );
                if ($user) {
                    // تجدید session برای جلوگیری از fixation
                    if (isset($_SESSION['_last_regeneration']) &&
                        time() - $_SESSION['_last_regeneration'] > 300) {
                        session_regenerate_id(true);
                        $_SESSION['_last_regeneration'] = time();
                    }
                    self::$user = $user;
                    return true;
                }
            } catch (\Throwable) {}
            // user حذف شده — پاک کردن session
            unset($_SESSION['user_id']);
        }

        return false;
    }

    public static function login(array $user): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        session_regenerate_id(true);
        $_SESSION['user_id']             = $user['id'];
        $_SESSION['_last_regeneration']  = time();
        $_SESSION['_login_time']         = time();
        self::$user = $user;
    }

    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        self::$user = null;
        $_SESSION   = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public static function user(): ?array   { return self::$user; }
    public static function id(): ?int       { return self::$user ? (int)self::$user['id'] : null; }
    public static function tenantId(): int  { return (int)(self::$user['tenant_id'] ?? 1); }
    public static function role(): string   { return self::$user['role'] ?? 'viewer'; }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => (int)env('BCRYPT_ROUNDS', 12)]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function can(string $permission): bool
    {
        $role  = self::role();
        $perms = self::$permissions[$role] ?? [];

        if (in_array('*', $perms)) return true;

        foreach ($perms as $perm) {
            if ($perm === $permission) return true;
            if (str_ends_with($perm, '.*')) {
                $prefix = rtrim($perm, '.*');
                if (str_starts_with($permission, $prefix)) return true;
            }
        }
        return false;
    }

    public static function generateToken(int $userId, int $tenantId): string
    {
        return JWT::encode([
            'sub'       => $userId,
            'tenant_id' => $tenantId,
            'iat'       => time(),
            'exp'       => time() + (int)env('JWT_EXPIRY', 86400),
        ]);
    }
}
