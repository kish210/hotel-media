<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Modules\Core\ModuleRegistry;

/**
 * صنف نصب — TODO ۵.۱۸
 *
 * همه‌ی مشتری‌ها هتل با تلویزیون اتاق نیستند: کافه‌ای که فقط منوبورد
 * می‌خواهد نباید در پنلش «اتاق‌ها»، «IPTV» و «صورتحساب» ببیند، و نصب
 * TVHeadend برایش فقط رم و دیسک هدر می‌دهد. اسکریپت نصب صنف را می‌پرسد
 * و این کلاس فقط ماژول‌های همان را روشن می‌کند. بعداً هم از پنل
 * «ماژول‌ها» یا با همین دستور عوض‌شدنی است؛ داده‌ای پاک نمی‌شود.
 */
final class InstallProfile
{
    /** @var array<string,array{label:string,modules:list<string>,tvheadend:bool}> */
    public const PROFILES = [
        'hotel'      => ['label' => 'هتل با تلویزیون اتاق (کامل)',             'modules' => ['hotel', 'iptv', 'vod', 'menu'], 'tvheadend' => true],
        'signage'    => ['label' => 'هتل — فقط تابلوی لابی، بدون تلویزیون اتاق', 'modules' => ['hotel', 'menu'],               'tvheadend' => false],
        'restaurant' => ['label' => 'رستوران / کافه — منوبورد دیجیتال',         'modules' => ['menu'],                         'tvheadend' => false],
        'iptv'       => ['label' => 'فقط IPTV — بیمارستان، خوابگاه، اقامتگاه',   'modules' => ['iptv', 'vod'],                  'tvheadend' => true],
    ];

    /**
     * ماژول‌های صنف را روشن و بقیه را خاموش می‌کند.
     * @return array{ok:bool,message:string,enabled:list<string>,disabled:list<string>}
     */
    public static function apply(string $profile, int $tenantId = 1): array
    {
        if (!isset(self::PROFILES[$profile])) {
            return ['ok' => false, 'message' => "صنف ناشناخته: $profile — یکی از: " . implode('، ', array_keys(self::PROFILES)),
                    'enabled' => [], 'disabled' => []];
        }

        ModuleRegistry::ensureTable();
        ModuleRegistry::boot($tenantId);
        $want = self::PROFILES[$profile]['modules'];
        $on = []; $off = [];

        foreach (ModuleRegistry::all() as $id => $mod) {
            if (in_array($id, $want, true)) {
                if (!$mod->isInstalled() && !$mod->install()) {
                    return ['ok' => false, 'message' => "نصب ماژول $id ناموفق بود", 'enabled' => $on, 'disabled' => $off];
                }
                $on[] = $id;
            } elseif ($mod->isInstalled()) {
                $mod->uninstall();
                $off[] = $id;
            }
        }

        /* صنف در تنظیمات مستاجر می‌ماند تا پنل بداند نصب برای چه بوده */
        $db  = Database::getInstance();
        $raw = $db->value('SELECT settings FROM tenants WHERE id = ?', [$tenantId]);
        $cfg = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        $cfg['install_profile'] = $profile;
        $db->update('tenants', ['settings' => json_encode($cfg, JSON_UNESCAPED_UNICODE)], ['id' => $tenantId]);

        return ['ok' => true, 'message' => 'صنف «' . self::PROFILES[$profile]['label'] . '» اعمال شد',
                'enabled' => $on, 'disabled' => $off];
    }

    public static function current(int $tenantId = 1): ?string
    {
        try {
            $raw = Database::getInstance()->value('SELECT settings FROM tenants WHERE id = ?', [$tenantId]);
            $v   = is_string($raw) ? (json_decode($raw, true)['install_profile'] ?? null) : null;
            return is_string($v) && isset(self::PROFILES[$v]) ? $v : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
