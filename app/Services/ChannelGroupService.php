<?php

namespace App\Services;

use App\Core\Database;

/**
 * گروه کانال — حلقهٔ گم‌شدهٔ زنجیرهٔ منبع→کانال→گروه→EPG→پخش.
 *
 * چرا یک سرویس و نه چند خط در هر کنترلر: کانال از سه مسیر وارد
 * می‌شود — فرم پنل، import فایل M3U، و import از TVHeadend. هر سه
 * پیش از این مستقیم به ستون متنی `category` می‌نوشتند و نتیجه این
 * بود که «News» و «news» دو گروه جدا می‌شدند. حل‌کردن نام به یک
 * ردیفِ یکتا باید در **یک** جا باشد، وگرنه همان ناهمگونی با ظاهر
 * تازه برمی‌گردد.
 */
class ChannelGroupService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * نام را به slug یکتا تبدیل می‌کند — دقیقا همان قاعده‌ای که
     * migration 049 برای انتقال داده‌های قدیمی استفاده کرد، وگرنه
     * ردیف‌های منتقل‌شده و ردیف‌های تازه به هم نمی‌رسند.
     */
    public static function slug(string $name): string
    {
        return mb_strtolower(str_replace(' ', '-', trim($name)));
    }

    /** @return list<array<string,mixed>> */
    public function all(int $tenantId, bool $onlyActive = false): array
    {
        return $this->db->rows(
            'SELECT g.*,
                    (SELECT COUNT(*) FROM iptv_channels c
                      WHERE c.group_id = g.id AND c.is_active = 1) AS channel_count
               FROM iptv_channel_groups g
              WHERE g.tenant_id = ?' . ($onlyActive ? ' AND g.is_active = 1' : '') . '
              ORDER BY g.sort_order, g.name',
            [$tenantId]
        ) ?: [];
    }

    /**
     * گروه را پیدا می‌کند و اگر نبود می‌سازد.
     *
     * برای import است: هدِند نام گروه خودش را می‌دهد و ما نه می‌خواهیم
     * هر بار گروه تازه بسازیم، نه می‌خواهیم کانال بی‌گروه بماند.
     *
     * @return int|null شناسهٔ گروه؛ null اگر نام خالی باشد
     */
    public function resolve(int $tenantId, ?string $name): ?int
    {
        $name = trim((string)$name);
        if ($name === '') return null;

        $slug = self::slug($name);

        $id = $this->db->value(
            'SELECT id FROM iptv_channel_groups WHERE tenant_id = ? AND slug = ?',
            [$tenantId, $slug]
        );
        if ($id) return (int)$id;

        /* ترتیب: گروه تازه ته فهرست می‌نشیند. اگر صفر بگذاریم، هر
           گروهِ واردشده از هدِند بالای گروه‌هایی می‌پرد که اپراتور
           دستی مرتب کرده بود. */
        $next = (int)$this->db->value(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM iptv_channel_groups WHERE tenant_id = ?',
            [$tenantId]
        );

        return (int)$this->db->insert('iptv_channel_groups', [
            'tenant_id'  => $tenantId,
            'name'       => mb_substr($name, 0, 120),
            'slug'       => mb_substr($slug, 0, 140),
            'sort_order' => $next,
        ]);
    }

    public function save(int $tenantId, array $d, ?int $id = null): array
    {
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') return ['ok' => false, 'message' => 'نام گروه الزامی است'];

        $slug = self::slug($name);
        $dup  = $this->db->value(
            'SELECT id FROM iptv_channel_groups WHERE tenant_id = ? AND slug = ?' . ($id ? ' AND id <> ?' : ''),
            $id ? [$tenantId, $slug, $id] : [$tenantId, $slug]
        );
        if ($dup) return ['ok' => false, 'message' => 'گروهی با همین نام از قبل هست'];

        $row = [
            'name'       => mb_substr($name, 0, 120),
            'name_en'    => trim((string)($d['name_en'] ?? '')) ?: null,
            'slug'       => mb_substr($slug, 0, 140),
            'icon'       => trim((string)($d['icon'] ?? '')) ?: null,
            'color'      => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($d['color'] ?? '')) ? $d['color'] : null,
            'sort_order' => (int)($d['sort_order'] ?? 0),
            'is_adult'   => !empty($d['is_adult']) ? 1 : 0,
            'is_active'  => isset($d['is_active']) ? (int)(bool)$d['is_active'] : 1,
        ];

        if ($id) {
            $this->db->update('iptv_channel_groups', $row, ['id' => $id, 'tenant_id' => $tenantId]);
            return ['ok' => true, 'id' => $id, 'message' => 'گروه به‌روز شد'];
        }

        $row['tenant_id'] = $tenantId;
        return ['ok' => true, 'id' => (int)$this->db->insert('iptv_channel_groups', $row), 'message' => 'گروه ساخته شد'];
    }

    /**
     * حذف گروه، ولی هرگز حذف کانال‌هایش.
     * کانال بی‌گروه در فهرست مهمان زیر «سایر» می‌نشیند و پخشش قطع
     * نمی‌شود — حذف یک گروه نباید هتل را بی‌تلویزیون کند.
     */
    public function delete(int $tenantId, int $id): array
    {
        if (!$this->db->value('SELECT id FROM iptv_channel_groups WHERE id = ? AND tenant_id = ?', [$id, $tenantId])) {
            return ['ok' => false, 'message' => 'گروه پیدا نشد'];
        }
        $n = (int)$this->db->value('SELECT COUNT(*) FROM iptv_channels WHERE group_id = ? AND tenant_id = ?', [$id, $tenantId]);
        $this->db->query('UPDATE iptv_channels SET group_id = NULL WHERE group_id = ? AND tenant_id = ?', [$id, $tenantId]);
        $this->db->delete('iptv_channel_groups', ['id' => $id, 'tenant_id' => $tenantId]);

        return ['ok' => true, 'message' => $n > 0
            ? "گروه حذف شد؛ $n کانال بی‌گروه شد و زیر «سایر» دیده می‌شود"
            : 'گروه حذف شد'];
    }
}
