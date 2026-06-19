<?php
/**
 * Router for the PHP built-in web server (no-Docker / native install).
 *
 * Used by the native Windows service:
 *   php -S 0.0.0.0:80 -t public public/server-router.php
 *
 * Behaviour:
 *   • Serves real files that exist under public/ directly (assets, uploads, …).
 *   • Everything else is handed to the front controller (index.php).
 *
 * This mirrors the nginx "try_files $uri /index.php" rule used in the
 * Docker setup, so routing is identical with or without Docker.
 */
declare(strict_types=1);

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = __DIR__ . $uri;

// Serve existing static files as-is (let the built-in server stream them).
if ($uri !== '/' && is_file($file)) {
    return false;
}

// Everything else → front controller.
require __DIR__ . '/index.php';
