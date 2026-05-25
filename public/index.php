<?php
declare(strict_types=1);

// CORS for player on network devices
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (str_starts_with($requestUri, '/player/') || str_starts_with($requestUri, '/api/')) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200); exit;
    }
}

define('ROOT_PATH',    dirname(__DIR__));
define('APP_PATH',     ROOT_PATH . '/app');
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('VIEWS_PATH',   ROOT_PATH . '/resources/views');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('PUBLIC_PATH',  __DIR__);

// ─── Autoloader (PSR-4) ─────────────────────────────────
spl_autoload_register(function(string $class): void {
    $path = APP_PATH . '/' . str_replace(['App\\', '\\'], ['', '/'], $class) . '.php';
    if (file_exists($path)) require $path;
});

// ─── Helpers ────────────────────────────────────────────
function env(string $key, mixed $default = null): mixed
{
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null) return $default;
    if ($val === 'true')  return true;
    if ($val === 'false') return false;
    if ($val === 'null')  return null;
    return $val;
}

function request(): \App\Core\Request
{
    static $req = null;
    if ($req === null) $req = new \App\Core\Request();
    return $req;
}

function csrf_token(): string { return \App\Middleware\CsrfMiddleware::generate(); }
function csrf_field(): string { return '<input type="hidden" name="_token" value="' . csrf_token() . '">'; }
function e(mixed $str): string { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
function asset(string $path): string { return env('APP_URL', '') . '/assets/' . ltrim($path, '/'); }
function url(string $path): string  { return env('APP_URL', '') . '/' . ltrim($path, '/'); }
function formatBytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes >= 1024 && $i < 4; $i++) $bytes /= 1024;
    return round($bytes, $precision) . ' ' . $units[$i];
}
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    return match(true) {
        $diff < 60     => 'همین الان',
        $diff < 3600   => floor($diff/60) . ' دقیقه پیش',
        $diff < 86400  => floor($diff/3600) . ' ساعت پیش',
        $diff < 604800 => floor($diff/86400) . ' روز پیش',
        default        => date('Y/m/d', strtotime($datetime)),
    };
}

// ─── Load .env ──────────────────────────────────────────
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val, " \t\n\r\"\\'");
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
}

define('APP_DEBUG', filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN));

// ─── Session با ذخیره‌سازی دائمی ────────────────────────
$sessionPath = STORAGE_PATH . '/sessions';
if (!is_dir($sessionPath)) @mkdir($sessionPath, 0755, true);

ini_set('session.save_handler',  'files');
ini_set('session.save_path',     $sessionPath);
ini_set('session.gc_maxlifetime',(string)(int)env('SESSION_LIFETIME', 7200));
ini_set('session.cookie_httponly','1');
ini_set('session.use_strict_mode','1');
ini_set('session.cookie_samesite','Lax');
ini_set('session.cookie_lifetime','0');  // تا بستن مرورگر

session_name('signage_session');
session_start();

// ─── Security headers ────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ─── Error handling ──────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', STORAGE_PATH . '/logs/php-errors.log');

    set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
        if (!($errno & error_reporting())) return false;
        error_log("[PHP $errno] $errstr in $errfile:$errline");
        return true;
    });

    set_exception_handler(function(\Throwable $e): void {
        error_log('[EXCEPTION] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        error_log('[TRACE] ' . $e->getTraceAsString());

        if (str_starts_with(request()->path(), '/api/') || request()->isJson()) {
            \App\Core\Response::error('خطای سرور داخلی', 500);
            return;
        }
        http_response_code(500);
        if (file_exists(VIEWS_PATH . '/errors/500.php')) {
            include VIEWS_PATH . '/errors/500.php';
        } else {
            echo '<h1>خطای سرور</h1><p>مشکلی رخ داده. لطفاً بعداً دوباره امتحان کنید.</p>';
        }
        exit;
    });
}

// ─── Bootstrap ──────────────────────────────────────────
$router = new \App\Core\Router();
require ROOT_PATH . '/routes/api.php';
require ROOT_PATH . '/routes/web.php';
$router->dispatch(request());
