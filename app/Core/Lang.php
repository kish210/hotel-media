<?php
declare(strict_types=1);
namespace App\Core;

/**
 * Hotel Media — Multilingual Helper
 * Supports: fa (فارسی), en (English), ar (عربي)
 */
class Lang
{
    private static string $current = 'fa';
    private static array  $strings = [];
    private static bool   $loaded  = false;

    // ── زبان‌های پشتیبانی‌شده ───────────────────────────────────────────
    public const LANGS = [
        'fa' => ['label' => 'فارسی',  'dir' => 'rtl', 'flag' => '🇮🇷', 'font' => 'Vazirmatn'],
        'en' => ['label' => 'English', 'dir' => 'ltr', 'flag' => '🇬🇧', 'font' => 'Inter'],
        'ar' => ['label' => 'عربي',   'dir' => 'rtl', 'flag' => '🇸🇦', 'font' => 'Tajawal'],
    ];

    // ── بارگذاری زبان از session ─────────────────────────────────────────
    public static function init(): void
    {
        if (self::$loaded) return;

        if (session_status() !== PHP_SESSION_ACTIVE) session_start();

        $lang = $_SESSION['lang'] ?? 'fa';
        if (!array_key_exists($lang, self::LANGS)) $lang = 'fa';
        self::$current = $lang;

        $file = ROOT_PATH . '/resources/lang/' . $lang . '.php';
        self::$strings = file_exists($file) ? (require $file) : [];
        self::$loaded  = true;
    }

    // ── تغییر زبان ───────────────────────────────────────────────────────
    public static function set(string $lang): void
    {
        if (!array_key_exists($lang, self::LANGS)) $lang = 'fa';
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['lang'] = $lang;
        self::$current    = $lang;
        self::$loaded     = false;
        self::$strings    = [];
        self::init();
    }

    // ── ترجمه یک کلید ────────────────────────────────────────────────────
    public static function get(string $key, array $replace = []): string
    {
        if (!self::$loaded) self::init();
        $str = self::$strings[$key] ?? self::fallback($key);
        foreach ($replace as $k => $v) {
            $str = str_replace(':' . $k, (string)$v, $str);
        }
        return $str;
    }

    // ── اگر ترجمه نبود از fa بگیر، اگر نبود کلید بده ─────────────────
    private static function fallback(string $key): string
    {
        if (self::$current !== 'fa') {
            $faFile = ROOT_PATH . '/resources/lang/fa.php';
            if (file_exists($faFile)) {
                $fa = require $faFile;
                if (isset($fa[$key])) return $fa[$key];
            }
        }
        // آخرین fallback: خود کلید به فرم خوانا
        return ucfirst(str_replace(['.', '_', '-'], ' ', $key));
    }

    // ── اطلاعات زبان فعلی ──────────────────────────────────────────────
    public static function current(): string { if (!self::$loaded) self::init(); return self::$current; }
    public static function dir(): string     { return self::LANGS[self::current()]['dir'] ?? 'rtl'; }
    public static function isRtl(): bool     { return self::dir() === 'rtl'; }
    public static function font(): string    { return self::LANGS[self::current()]['font'] ?? 'Vazirmatn'; }
    public static function label(): string   { return self::LANGS[self::current()]['label'] ?? ''; }
    public static function flag(): string    { return self::LANGS[self::current()]['flag'] ?? ''; }
    public static function all(): array      { return self::LANGS; }
}
