<?php declare(strict_types=1);
namespace App\Services;
use App\Core\Database;

class NotificationService
{
    private Database $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public function send(int $tenantId, string $type, string $title, string $body, string $severity = 'info', ?int $userId = null, array $data = []): int|string
    {
        return $this->db->insert('notifications', [
            'tenant_id' => $tenantId,
            'user_id'   => $userId,
            'type'      => $type,
            'title'     => $title,
            'body'      => $body,
            'severity'  => $severity,
            'data'      => $data ? json_encode($data) : null,
        ]);
    }

    public function screenOffline(int $tenantId, string $screenName, int $screenId): void
    {
        // Avoid duplicate alerts within 10 minutes
        $exists = $this->db->value(
            "SELECT id FROM notifications WHERE tenant_id=? AND type='screen_offline' AND data LIKE ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
            [$tenantId, "%\"screen_id\":$screenId%"]
        );
        if ($exists) return;

        $this->send($tenantId, 'screen_offline', "صفحه آفلاین شد",
            "صفحه \"$screenName\" آفلاین شده است", 'warning', null, ['screen_id' => $screenId]);
    }

    public function storageWarning(int $tenantId, float $percent): void
    {
        if ($percent < 80) return;
        $severity = $percent >= 95 ? 'critical' : 'warning';
        $exists = $this->db->value(
            "SELECT id FROM notifications WHERE tenant_id=? AND type='storage_warning' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$tenantId]
        );
        if ($exists) return;
        $this->send($tenantId, 'storage_warning', 'هشدار فضای ذخیره‌سازی',
            round($percent, 1) . '٪ فضا استفاده شده', $severity);
    }

    public function campaignExpired(int $tenantId, string $campaignName): void
    {
        $this->send($tenantId, 'campaign_expired', 'کمپین منقضی شد',
            "کمپین \"$campaignName\" به پایان رسیده است", 'info');
    }

    public function getUnread(int $tenantId): array
    {
        return $this->db->rows(
            "SELECT * FROM notifications WHERE tenant_id=? AND read_at IS NULL ORDER BY created_at DESC LIMIT 20",
            [$tenantId]
        );
    }
}
