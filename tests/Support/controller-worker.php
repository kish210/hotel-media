<?php
/**
 * یک فراخوانی کنترلر را اجرا می‌کند و نتیجه را روی stderr به شکل
 * ##RESULT##{status,body} برمی‌گرداند (چون Response در پایان exit می‌زند).
 * argv: [1]=controller [2]=method [3]=params-json [4]=body-json
 */
define('ROOT_PATH',    dirname(__DIR__, 2));
define('APP_PATH',     ROOT_PATH . '/app');
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('VIEWS_PATH',   ROOT_PATH . '/resources/views');
define('PUBLIC_PATH',  ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('APP_DEBUG',    true);

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

$ENV = [];
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) continue;
    [$k, $v] = explode('=', $l, 2);
    $ENV[trim($k)] = trim($v, " \t\"'");
}
function env(string $k, mixed $d = null): mixed { global $ENV; return $ENV[$k] ?? $d; }

date_default_timezone_set((string)env('APP_TIMEZONE', 'Asia/Tehran'));
function request(): object { return new class { public function ip(): string { return '127.0.0.1'; } public function userAgent(): string { return 'test'; } }; }

spl_autoload_register(function (string $c): void {
    $p = APP_PATH . '/' . str_replace(['App\\', '\\'], ['', '/'], $c) . '.php';
    if (file_exists($p)) require $p;
});

$controller = $argv[1];
$method     = $argv[2];
// base64 چون escapeshellarg روی ویندوز کوتیشن‌های JSON را خراب می‌کند
$params     = json_decode(base64_decode($argv[3] ?? ''), true) ?: [];
$body       = json_decode(base64_decode($argv[4] ?? ''), true) ?: [];
$query      = json_decode(base64_decode($argv[5] ?? ''), true) ?: [];

// پارامترهای GET را در همان جایی می‌گذاریم که Request::get از آن می‌خواند
$_GET = $query;

// هرچه کنترلر چاپ کند را جمع می‌کنیم و در shutdown گزارش می‌دهیم
ob_start();
register_shutdown_function(function () {
    $out  = ob_get_clean() ?: '';
    $code = http_response_code();
    fwrite(STDERR, "\n##RESULT##" . json_encode([
        'status' => is_int($code) ? $code : 200,
        'body'   => json_decode($out, true) ?? ['raw' => $out],
    ], JSON_UNESCAPED_UNICODE) . "##END##\n");
});

$req = new class($body) extends App\Core\Request {
    public function __construct(private array $b) {}
    public function json(): array { return $this->b; }
};

$class = "App\\Controllers\\Api\\$controller";
(new $class())->$method($req, $params);
