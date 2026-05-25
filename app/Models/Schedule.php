<?php declare(strict_types=1);
namespace App\Models;
use App\Core\{Database, Auth};

class Schedule
{
    private Database $db;
    private int $tenantId;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->tenantId = Auth::tenantId();
    }

    public function all(): array
    {
        return $this->db->rows(
            "SELECT sc.*,p.name AS playlist_name,s.name AS screen_name FROM schedules sc JOIN playlists p ON p.id=sc.playlist_id LEFT JOIN screens s ON s.id=sc.screen_id WHERE sc.tenant_id=? AND sc.is_active=1 ORDER BY sc.priority DESC",
            [$this->tenantId]
        );
    }

    public function getActiveForScreen(int $screenId): ?array
    {
        $now = date('Y-m-d H:i:s');
        $day = (int)date('w');
        return $this->db->row(
            "SELECT sc.*,p.* FROM schedules sc JOIN playlists p ON p.id=sc.playlist_id
             WHERE (sc.screen_id=? OR sc.screen_id IS NULL) AND sc.tenant_id=? AND sc.is_active=1 AND p.is_active=1
             AND (sc.start_date IS NULL OR sc.start_date<=?) AND (sc.end_date IS NULL OR sc.end_date>=?)
             AND (sc.start_time IS NULL OR sc.start_time<=TIME(?)) AND (sc.end_time IS NULL OR sc.end_time>=TIME(?))
             AND (sc.weekdays IS NULL OR JSON_CONTAINS(sc.weekdays,?))
             ORDER BY sc.priority DESC LIMIT 1",
            [$screenId, $this->tenantId, date('Y-m-d'), date('Y-m-d'), $now, $now, json_encode($day)]
        );
    }
}
