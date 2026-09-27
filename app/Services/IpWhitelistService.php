<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * IP Whitelist — رنج‌های مورد اعتماد برای فعال‌سازی خودکار تلویزیون.
 *
 * چرا لازم است: تلویزیون هتلی سامسونگ/ال‌جی با ریموت نمی‌تواند کد
 * فعال‌سازی تایپ کند. اگر IP دستگاه در یک رنج مورد اعتماد باشد، خودکار
 * فعال می‌شود و صفحه‌ی کد اصلا نمایش داده نمی‌شود.
 *
 * پشتیبانی: CIDR (172.33.0.0/20)، رنج (172.16.100.1-172.16.100.254)،
 * تک‌IP (172.16.100.44). فقط IPv4 — تلویزیون‌های هتلی IPv4 هستند.
 *
 * docs/TODO.md — راه‌اندازی بدون کد برای رنج‌های whitelist
 */
class IpWhitelistService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** آیا این IP در یکی از رنج‌های فعالِ tenant است؟ */
    public function matches(string $ip, int $tenantId): bool
    {
        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        $rows = $this->db->rows(
            "SELECT range_spec FROM ip_whitelist WHERE tenant_id=? AND is_active=1",
            [$tenantId]
        );
        foreach ($rows as $r) {
            if (self::ipInSpec($ip, (string)$r['range_spec'])) return true;
        }
        return false;
    }

    /**
     * تطبیق یک IPv4 با یک spec: CIDR، رنج a-b، یا تک‌IP.
     * public و static تا در تست و جای دیگر هم قابل استفاده باشد.
     */
    public static function ipInSpec(string $ip, string $spec): bool
    {
        $ipL = ip2long($ip);
        if ($ipL === false) return false;
        $ipU = $ipL & 0xFFFFFFFF;
        $spec = trim($spec);
        if ($spec === '') return false;

        // CIDR: 172.33.0.0/20
        if (str_contains($spec, '/')) {
            [$net, $bits] = explode('/', $spec, 2);
            $netL = ip2long(trim($net));
            $bits = (int)$bits;
            if ($netL === false || $bits < 0 || $bits > 32) return false;
            if ($bits === 0) return true;
            $mask = ($bits === 32) ? 0xFFFFFFFF : (~((1 << (32 - $bits)) - 1) & 0xFFFFFFFF);
            return ($ipU & $mask) === ((($netL & 0xFFFFFFFF)) & $mask);
        }

        // رنج: 172.16.100.1-172.16.100.254
        if (str_contains($spec, '-')) {
            [$a, $b] = explode('-', $spec, 2);
            $aL = ip2long(trim($a));
            $bL = ip2long(trim($b));
            if ($aL === false || $bL === false) return false;
            $aU = $aL & 0xFFFFFFFF; $bU = $bL & 0xFFFFFFFF;
            if ($aU > $bU) { $t = $aU; $aU = $bU; $bU = $t; }
            return $ipU >= $aU && $ipU <= $bU;
        }

        // تک‌IP
        $sL = ip2long($spec);
        return $sL !== false && ($sL & 0xFFFFFFFF) === $ipU;
    }

    // ── CRUD برای پنل ────────────────────────────────────────────────
    public function all(int $tenantId): array
    {
        return $this->db->rows(
            "SELECT * FROM ip_whitelist WHERE tenant_id=? ORDER BY id DESC",
            [$tenantId]
        );
    }

    /** @return array{ok:bool,message:string,id?:int} */
    public function add(int $tenantId, string $label, string $spec): array
    {
        $label = trim($label);
        $spec  = trim($spec);
        if ($label === '') return ['ok' => false, 'message' => 'عنوان لازم است'];
        if (!self::validSpec($spec)) {
            return ['ok' => false, 'message' => 'قالب نامعتبر — CIDR، رنج a-b یا تک IP'];
        }
        $id = $this->db->insert('ip_whitelist', [
            'tenant_id'  => $tenantId,
            'label'      => mb_substr($label, 0, 120),
            'range_spec' => $spec,
            'is_active'  => 1,
        ]);
        return ['ok' => true, 'message' => 'رنج اضافه شد', 'id' => (int)$id];
    }

    public function delete(int $tenantId, int $id): void
    {
        $this->db->delete('ip_whitelist', ['id' => $id, 'tenant_id' => $tenantId]);
    }

    /** قالب spec را بدون تطبیق با IP خاص اعتبارسنجی می‌کند */
    public static function validSpec(string $spec): bool
    {
        $spec = trim($spec);
        if ($spec === '') return false;

        if (str_contains($spec, '/')) {
            [$net, $bits] = array_pad(explode('/', $spec, 2), 2, '');
            return filter_var(trim($net), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
                && ctype_digit(trim($bits)) && (int)$bits >= 0 && (int)$bits <= 32;
        }
        if (str_contains($spec, '-')) {
            [$a, $b] = array_pad(explode('-', $spec, 2), 2, '');
            return filter_var(trim($a), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
                && filter_var(trim($b), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        }
        return filter_var($spec, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }
}
