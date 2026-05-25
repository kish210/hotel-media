<?php
/**
 * Upload file server — runs via PHP-FPM when nginx can't find file
 * nginx passes UPLOAD_PATH = /uploads/media/1/filename.jpg
 */

// مسیر از nginx
$uri = $_SERVER['UPLOAD_PATH'] ?? $_SERVER['REQUEST_URI'] ?? '';

// حذف query string
if (($q = strpos($uri, '?')) !== false) $uri = substr($uri, 0, $q);

// امنیت: فقط uploads/ مجاز
$uri = '/' . ltrim(str_replace(['..', "\0"], '', $uri), '/');

if (!str_starts_with($uri, '/uploads/')) {
    http_response_code(403); exit('Forbidden');
}

$filePath = '/var/www/html/public' . $uri;

if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit("File not found: $uri\nLooked at: $filePath");
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimes = [
    'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
    'gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml',
    'mp4'=>'video/mp4','webm'=>'video/webm','ogv'=>'video/ogg',
    'mov'=>'video/quicktime','avi'=>'video/x-msvideo',
];
$mime = $mimes[$ext] ?? 'application/octet-stream';

$size  = filesize($filePath);
$mtime = filemtime($filePath);
$etag  = '"' . md5($uri . $size . $mtime) . '"';

header("Content-Type: $mime");
header("Content-Length: $size");
header("ETag: $etag");
header("Cache-Control: public, max-age=86400");
header("Last-Modified: " . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header("Accept-Ranges: bytes");

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304); exit;
}

// پشتیبانی از Range برای ویدیو
if (isset($_SERVER['HTTP_RANGE'])) {
    preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m);
    $start = (int)$m[1];
    $end   = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : $size - 1;
    $end   = min($end, $size - 1);
    $len   = $end - $start + 1;

    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
    header("Content-Length: $len");

    $fp = fopen($filePath, 'rb');
    fseek($fp, $start);
    $sent = 0;
    while ($sent < $len && !feof($fp)) {
        $chunk = min(8192, $len - $sent);
        echo fread($fp, $chunk);
        $sent += $chunk;
    }
    fclose($fp);
} else {
    readfile($filePath);
}
