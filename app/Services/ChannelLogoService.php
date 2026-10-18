<?php
declare(strict_types=1);
namespace App\Services;

/**
 * لوگوی کانال‌های ایرانی از روی نام کانال.
 *
 * فایل‌ها در public/assets/channel-logos هستند و نامشان نام کانال به حروف
 * کوچک و بدون فاصله/علامت است («IRIB TV3» → iribtv3.png). تطبیق بی‌حساسیت
 * به حروف و فاصله است، چون نام کانال از TVHeadend می‌آید و هر بار به شکلی
 * نوشته شده (IRIB-Kish / IRIB-kish / IRIB Kish).
 */
final class ChannelLogoService
{
    public const URL_BASE = '/assets/channel-logos/';

    private static ?array $files = null;

    public static function slug(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name)) ?? '';
    }

    private static function files(): array
    {
        if (self::$files === null) {
            self::$files = [];
            $dir = dirname(__DIR__, 2) . '/public/assets/channel-logos';
            foreach (glob($dir . '/*.png') ?: [] as $f) {
                self::$files[basename($f, '.png')] = true;
            }
        }
        return self::$files;
    }

    /** آدرس لوگو یا null اگر چیزی نبود. */
    public static function find(string $name): ?string
    {
        $s = self::slug($name);
        if ($s === '') return null;
        $files = self::files();

        $cands = [$s, 'irib' . $s];
        // «HD» و «Radio» پسوند/پیشوند نام‌اند نه هویت کانال
        $noHd = preg_replace('/hd$/', '', $s);
        if ($noHd !== '' && $noHd !== $s) { $cands[] = $noHd; $cands[] = 'irib' . $noHd; }
        $noRadio = preg_replace('/^radio/', '', $s);
        if ($noRadio !== '' && $noRadio !== $s) { $cands[] = 'radio' . $noRadio; }

        foreach ($cands as $c) {
            if (isset($files[$c])) return self::URL_BASE . $c . '.png';
        }

        // «TAMASHA» ↔ tamashahd: پیشوند، ولی فقط اگر نام کافی مشخص (۵+ حرف)
        // و **یک** فایل داشته باشد؛ وگرنه لوگوی کانال دیگر نشان داده می‌شد.
        if (strlen($s) >= 5) {
            $hit = [];
            foreach ($files as $name => $_) {
                if (str_starts_with((string)$name, $s)) $hit[] = $name;
            }
            if (count($hit) === 1) return self::URL_BASE . $hit[0] . '.png';
        }
        return null;
    }
}
