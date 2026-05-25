<?php declare(strict_types=1);

if (!function_exists('formatBytes')) {
    function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B','KB','MB','GB','TB'];
        for ($i = 0; $bytes >= 1024 && $i < 4; $i++) $bytes /= 1024;
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

if (!function_exists('timeAgo')) {
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
}

if (!function_exists('persianNumber')) {
    function persianNumber(int|float $n): string {
        return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)$n);
    }
}

if (!function_exists('formatPrice')) {
    function formatPrice(float $price, string $currency = 'IRR'): string {
        return number_format($price) . ($currency === 'IRR' ? ' تومان' : ' ' . $currency);
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string {
        $text = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $text);
        return strtolower(trim(preg_replace('/[\s-]+/', '-', $text), '-'));
    }
}

if (!function_exists('generateQrUrl')) {
    function generateQrUrl(string $data, int $size = 200, string $fgColor = 'f97316'): string {
        return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($data) . "&color={$fgColor}&bgcolor=111118&format=png";
    }
}

if (!function_exists('truncate')) {
    function truncate(string $text, int $len = 100, string $suffix = '...'): string {
        if (mb_strlen($text) <= $len) return $text;
        return mb_substr($text, 0, $len) . $suffix;
    }
}
