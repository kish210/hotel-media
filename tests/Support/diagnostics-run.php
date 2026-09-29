<?php
/**
 * اجرای DiagnosticsService از خط فرمان.
 *
 * چرا جدا از پنل: وقتی خودِ پنل بالا نمی‌آید — همان لحظه‌ای که بیش از
 * همیشه به عیب‌یابی نیاز است — صفحه‌ی وب در دسترس نیست. این همان
 * بررسی‌ها را روی SSH اجرا می‌کند.
 *
 * باید با کاربر وب اجرا شود، وگرنه نتیجه‌ی دسترسی‌ها گمراه‌کننده است:
 *   sudo -u www-data php tests/Support/diagnostics-run.php
 */
define('ROOT_PATH',    dirname(__DIR__, 2));
define('APP_PATH',     ROOT_PATH . '/app');
define('PUBLIC_PATH',  ROOT_PATH . '/public');
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('VIEWS_PATH',   ROOT_PATH . '/resources/views');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require ROOT_PATH . '/app/Helpers/helpers.php';

spl_autoload_register(static function (string $class): void {
    $rel = str_replace('\\', '/', $class);
    if (str_starts_with($rel, 'App/')) $rel = substr($rel, 4);
    $p = APP_PATH . '/' . $rel . '.php';
    if (file_exists($p)) require $p;
});

/* .env دستی خوانده می‌شود چون بوت‌استرپ کامل برنامه برای یک گزارش
   خط‌فرمانی لازم نیست — و اگر بوت‌استرپ خودش خراب باشد، این ابزار
   باید باز هم کار کند. */
foreach (file(ROOT_PATH . '/.env') ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $v = trim($v);
    putenv("$k=$v");
    $_ENV[$k] = $v;
}

/* env() در public/index.php تعریف شده، یعنی فقط در مسیر وب وجود دارد.
   config/database.php به آن نیاز دارد، پس هر ابزار خط‌فرمانی باید
   نسخه‌ی خودش را داشته باشد — با همان رفتارِ تبدیلِ true/false/null. */
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $val = $_ENV[$key] ?? getenv($key);
        if ($val === false || $val === null) return $default;
        if ($val === 'true')  return true;
        if ($val === 'false') return false;
        if ($val === 'null')  return null;
        return $val;
    }
}

$color = [
    'ok'      => "\033[32m",
    'warn'    => "\033[33m",
    'fail'    => "\033[31m",
    'unknown' => "\033[90m",
];
$mark = ['ok' => '✅', 'warn' => '⚠️ ', 'fail' => '❌', 'unknown' => '❔'];

$sections = (new App\Services\DiagnosticsService())->all();

$worst = 'ok';
foreach ($sections as $sec) {
    $c = $color[$sec['status']] ?? '';
    echo "\n\033[1m" . $sec['title'] . "\033[0m  {$c}[" . $sec['status'] . "]\033[0m\n";

    foreach ($sec['checks'] as $chk) {
        printf(
            "  %s %-32s %s%s\033[0m\n",
            $mark[$chk['status']] ?? '?',
            $chk['name'],
            $color[$chk['status']] ?? '',
            $chk['detail']
        );
        if (($chk['fix'] ?? '') !== '') echo "       ↳ " . $chk['fix'] . "\n";
    }

    if ($sec['status'] === 'fail') $worst = 'fail';
    elseif ($sec['status'] === 'warn' && $worst === 'ok') $worst = 'warn';
}

echo "\n";
exit($worst === 'fail' ? 1 : 0);
