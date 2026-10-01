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

if (!function_exists('binPath')) {
    /**
     * مسیر یک باینری سیستم را پیدا می‌کند.
     *
     * دو تله‌ای که این تابع برای آن‌هاست، هر دو فقط زیر PHP-FPM دیده
     * می‌شوند و در خط فرمان سالم به نظر می‌رسند:
     *
     *  ۱. ‏PHP_BINARY زیر FPM برابر /usr/sbin/php-fpm8.3 است، نه php.
     *     صدا زدنش با آرگومان‌های artisan فقط راهنمای php-fpm را چاپ
     *     می‌کند و کد خروج غیرصفر می‌دهد.
     *  ۲. ‏open_basedir روی pool تنظیم است، پس is_executable() برای
     *     مسیرهای بیرون از آن — مثل /usr/bin/php — false می‌دهد، هرچند
     *     همان باینری با shell کامل اجرا می‌شود.
     *
     * برای همین تشخیص با `command -v` انجام می‌شود، نه بررسی فایل.
     */
    function binPath(string $bin): string {
        static $cache = [];
        if (isset($cache[$bin])) return $cache[$bin];

        $out = trim((string)@shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null'));
        if ($out !== '' && $out[0] === '/') return $cache[$bin] = $out;

        foreach (['/usr/bin/', '/usr/local/bin/'] as $dir) {
            $ok = trim((string)@shell_exec('test -x ' . escapeshellarg($dir . $bin) . ' && echo ok 2>/dev/null'));
            if ($ok === 'ok') return $cache[$bin] = $dir . $bin;
        }
        return $cache[$bin] = '';
    }
}

if (!function_exists('phpCliPath')) {
    /** باینری خط‌فرمان php — هرگز PHP_BINARY، به دلیل بالا */
    function phpCliPath(): string {
        return binPath('php') ?: binPath('php8.3') ?: binPath('php8');
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

/* ── تاریخ شمسی ──────────────────────────────────────────────────────
   پورتال تلویزیون قبلا تاریخ را با toLocaleDateString('fa-IR') در خود
   مرورگر می‌ساخت. روی webOS و Tizen داده‌ی Intl برای fa-IR وجود ندارد،
   پس یا تاریخ میلادی نشان داده می‌شد یا رشته‌ی خالی — مهمان ایرانی
   تاریخ اشتباه می‌دید. حالا سرور آن را می‌سازد و تلویزیون فقط چاپ
   می‌کند. به هیچ افزونه‌ی PHP (intl/calendar) هم نیاز نیست. */

if (!function_exists('gregorianToJalali')) {
    /** @return array{0:int,1:int,2:int} سال، ماه، روز شمسی */
    function gregorianToJalali(int $gy, int $gm, int $gd): array {
        $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
        $gy2 = ($gm > 2) ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + (int)(($gy2 + 3) / 4)
              - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400)
              + $gd + $g_d_m[$gm - 1];

        $jy    = -1595 + (33 * (int)($days / 12053));
        $days %= 12053;
        $jy   += 4 * (int)($days / 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy   += (int)(($days - 1) / 365);
            $days  = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + (int)($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int)(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }
}

if (!function_exists('jalaliDate')) {
    /**
     * تاریخ شمسی خوانا، مثل: «یکشنبه ۱ مهر ۱۴۰۵»
     * $ts پیش‌فرض now است و منطقه‌ی زمانی همان چیزی که bootstrap ست کرده.
     */
    function jalaliDate(?int $ts = null, bool $withWeekday = true): string {
        $ts ??= time();
        [$jy, $jm, $jd] = gregorianToJalali(
            (int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts)
        );

        $months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور',
                   'مهر','آبان','آذر','دی','بهمن','اسفند'];
        // date('w') یکشنبه را ۰ می‌دهد و هفته‌ی ایرانی از شنبه شروع می‌شود
        $days   = ['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'];

        $out = persianNumber($jd) . ' ' . $months[$jm - 1] . ' ' . persianNumber($jy);
        return $withWeekday ? $days[(int)date('w', $ts)] . ' ' . $out : $out;
    }
}
