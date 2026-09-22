<?php
return [
    'name'     => env('APP_NAME', 'Hotel Media'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => env('APP_DEBUG', false),
    'url'      => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Tehran'),
    'locale'   => 'fa',
    /* یک منبع حقیقت برای نسخه: فایل VERSION در ریشه.
       پیش از این نسخه در سه جا hardcode بود و از هم دور افتاده بودند
       (اینجا 1.0.0، نوار کناری و فوتر 1.6.0). به‌روزرسانی همین فایل
       را می‌نویسد، پس هر جا که عدد را hardcode کند بعد از به‌روزرسانی
       عدد قدیمی نشان می‌دهد. */

    'version'  => trim(@file_get_contents(dirname(__DIR__) . '/VERSION') ?: '0.0.0'),
    'ws_port'  => env('WS_PORT', 8080),
];
