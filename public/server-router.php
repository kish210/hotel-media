<?php
/**
 * Router for the PHP built-in web server (native install).
 *
 * Used by the native Windows service:
 *   php -S 0.0.0.0:80 -t public public/server-router.php
 *
 * Behaviour:
 *   • Serves real files that exist under public/ directly (assets, uploads, …).
 *   • Everything else is handed to the front controller (index.php).
 *
 * This mirrors the nginx "try_files $uri /index.php" rule used in the
 * nginx setup, so routing is identical on either web server.
 */
declare(strict_types=1);

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = __DIR__ . $uri;

/* ویدیو و صدا از مسیر /uploads در index.php سرو می‌شوند چون سرور داخلی
   PHP درخواست Range را نمی‌فهمد و همیشه ۲۰۰ و کل فایل را می‌فرستد. بدون
   Range، تلویزیون نه جلو/عقب می‌رود و نه فیلم را از «ادامه‌ی تماشا» از
   وسط شروع می‌کند — روی نصب ویندوزی همین پیش می‌آمد. nginx خودش Range دارد. */
if (preg_match('#^/uploads/.+\.(mp4|m4v|webm|mov|mkv|ts|mp3|m4a|aac|ogg|ogv)$#i', $uri) && is_file($file)) {
    require __DIR__ . '/index.php';
    return true;
}

// Serve existing static files as-is (let the built-in server stream them).
if ($uri !== '/' && is_file($file)) {
    return false;
}

// Everything else → front controller.
require __DIR__ . '/index.php';
