<?php declare(strict_types=1);
namespace App\Services;
use App\Core\Database;

/**
 * Run via cron: * * * * * php /var/www/html/artisan monitor:screens
 */
class ScreenMonitorService
{
    private Database $db;
    private NotificationService $notif;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->notif = new NotificationService();
    }

    public function run(): void
    {
        $this->checkOfflineScreens();
        $this->checkExpiredCampaigns();
        $this->checkStorageWarnings();
        echo "[" . date('Y-m-d H:i:s') . "] Monitor cycle complete\n";
    }

    private function checkOfflineScreens(): void
    {
        // Find screens that were online but missed last 2 heartbeats (>2 min)
        $gone = $this->db->rows(
            "SELECT s.*, t.id AS tid FROM screens s JOIN tenants t ON t.id=s.tenant_id
             WHERE s.is_online=1 AND s.last_seen_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE)"
        );

        foreach ($gone as $screen) {
            $this->db->update('screens', ['is_online' => 0], ['id' => $screen['id']]);
            $this->notif->screenOffline((int)$screen['tenant_id'], $screen['name'], (int)$screen['id']);
            echo "  [OFFLINE] Screen: {$screen['name']} (#{$screen['id']})\n";
        }
    }

    /**
     * کمپین و اعلانِ به‌پایان‌رسیده را غیرفعال می‌کند و خبر می‌دهد.
     *
     * تا فاز ۷ این تابع جدول `campaigns` را می‌خواند — جدولی که هیچ
     * صفحه‌ای مصرفش نمی‌کرد، پس در عمل انقضای چیزی را اعلام می‌کرد که
     * هرگز پخش نشده بود. حالا روی `screen_messages` کار می‌کند، همان
     * جدولی که واقعا در هر heartbeat به تلویزیون می‌رسد.
     *
     * غیرفعال‌کردن پس از پایان، آرایشی نیست: کوئری تحویل بر اساس
     * `end_at` فیلتر می‌کند، ولی ردیفِ همیشه‌فعال در فهرست اپراتور
     * می‌ماند و معلوم نیست تمام شده یا نه.
     */
    private function checkExpiredCampaigns(): void
    {
        try {
            $expired = $this->db->rows(
                "SELECT id, tenant_id, title, is_ad FROM screen_messages
                  WHERE is_active = 1 AND end_at IS NOT NULL AND end_at < NOW()"
            ) ?: [];
        } catch (\Throwable $e) {
            return;  // پیش از migration 050
        }

        foreach ($expired as $c) {
            $this->db->update('screen_messages', ['is_active' => 0], ['id' => $c['id']]);
            $this->notif->campaignExpired((int)$c['tenant_id'], (string)$c['title']);
            echo "  [EXPIRED] " . (!empty($c['is_ad']) ? 'Campaign' : 'Message') . ": {$c['title']}\n";
        }
    }

    private function checkStorageWarnings(): void
    {
        $tenants = $this->db->rows("SELECT id, storage_limit FROM tenants WHERE is_active=1");
        foreach ($tenants as $t) {
            $used = (int)$this->db->value("SELECT SUM(file_size) FROM media WHERE tenant_id=? AND deleted_at IS NULL", [$t['id']]);
            $pct  = $t['storage_limit'] > 0 ? ($used / $t['storage_limit'] * 100) : 0;
            if ($pct >= 80) {
                $this->notif->storageWarning((int)$t['id'], $pct);
                echo "  [STORAGE] Tenant #{$t['id']}: {$pct}%\n";
            }
        }
    }
}
