<?php
declare(strict_types=1);

namespace App\Modules\Core;

use App\Core\Database;

/**
 * ModuleRegistry — مرکز مدیریت تمام ماژول‌ها
 */
class ModuleRegistry
{
    private static array $modules  = [];
    private static bool  $booted   = false;
    private static int   $tenantId = 1;

    /**
     * ماژول‌های داخلی.
     *
     * این شاخه فقط هتل است. ماژول‌های صنف‌های دیگر — فرودگاه (FIDS)،
     * حمل‌ونقل، فروشگاه، سازمانی و داخل‌پرواز — به شاخه‌ی
     * non-hotel-verticals منتقل شدند تا نصب هتل سبک بماند و کد
     * بی‌ربط جلوی چشم اپراتور هتل نباشد.
     */
    private static array $moduleClasses = [
        \App\Modules\Hotel\HotelModule::class,
        \App\Modules\IPTV\IPTVModule::class,
        \App\Modules\VOD\VODModule::class,
        \App\Modules\Menu\MenuModule::class,
    ];

    public static function boot(int $tenantId = 1): void
    {
        if (self::$booted && self::$tenantId === $tenantId) return;
        self::$tenantId = $tenantId;
        self::$modules  = [];

        foreach (self::$moduleClasses as $class) {
            if (!class_exists($class)) continue;
            /** @var BaseModule $mod */
            $mod = new $class($tenantId);
            self::$modules[$mod->id()] = $mod;
        }
        self::$booted = true;
    }

    /** @return BaseModule[] */
    public static function all(): array { return self::$modules; }

    /** @return BaseModule[] ماژول‌های نصب‌شده و فعال */
    public static function installed(): array
    {
        return array_filter(self::$modules, fn($m) => $m->isInstalled());
    }

    /** آیدی‌های ماژول‌های فعال به صورت آرایه ساده */
    public static function activeIds(): array
    {
        return array_keys(array_filter(self::$modules, fn($m) => $m->isInstalled()));
    }

    /** آیا ماژول مشخص فعال است؟ */
    public static function isActive(string $id): bool
    {
        return self::get($id)?->isInstalled() ?? false;
    }

    public static function get(string $id): ?BaseModule
    {
        return self::$modules[$id] ?? null;
    }

    public static function isInstalled(string $id): bool
    {
        return self::get($id)?->isInstalled() ?? false;
    }

    /** تمام zone type ها از ماژول‌های نصب‌شده */
    public static function allZoneTypes(): array
    {
        $types = [];
        foreach (self::installed() as $mod) {
            foreach ($mod->zoneTypes() as $zt) {
                $zt['module_id']   = $mod->id();
                $zt['module_name'] = $mod->name();
                $types[] = $zt;
            }
        }
        return $types;
    }

    /** رندر widget برای پلیر */
    public static function renderZone(string $moduleId, string $zoneType, array $settings = []): string
    {
        $mod = self::get($moduleId);
        if (!$mod || !$mod->isInstalled()) {
            return '<div style="color:#f87171;padding:16px;font-family:sans-serif;">ماژول نصب نشده: ' . htmlspecialchars($moduleId) . '</div>';
        }
        return $mod->renderPlayerWidget($zoneType, $settings);
    }

    /** آمار داشبورد از همه ماژول‌های فعال */
    public static function getDashboardStats(): array
    {
        $stats = [];
        foreach (self::installed() as $mod) {
            $s = $mod->getDashboardStats();
            if ($s) $stats[$mod->id()] = array_merge(['name' => $mod->name(), 'icon' => $mod->icon(), 'color' => $mod->color()], $s);
        }
        return $stats;
    }

    /** اطمینان از وجود جدول modules */
    public static function ensureTable(): void
    {
        $db = Database::getInstance();
        $db->query("CREATE TABLE IF NOT EXISTS `modules` (
            `id`           VARCHAR(50)  NOT NULL,
            `tenant_id`    INT UNSIGNED NOT NULL,
            `name`         VARCHAR(255) NOT NULL,
            `version`      VARCHAR(20)  DEFAULT '1.0.0',
            `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
            `settings`     JSON         DEFAULT NULL,
            `installed_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`, `tenant_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function make(string $type): ?object
    {
        /* فقط ماژول‌های هتل — بقیه در شاخه‌ی non-hotel-verticals */
        $map = [
            'hotel' => \App\Modules\Hotel\HotelModule::class,
            'iptv'  => \App\Modules\IPTV\IPTVModule::class,
            'vod'   => \App\Modules\VOD\VODModule::class,
            'menu'  => \App\Modules\Menu\MenuModule::class,
        ];
        if (!isset($map[$type])) return null;
        return new $map[$type]();
    }
}
