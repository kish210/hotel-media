<?php declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
use App\Core\Auth;

class Playlist
{
    private Database $db;
    private int $tenantId;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->tenantId = Auth::tenantId();
    }

    public function all(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $sql = "SELECT p.*, u.name AS creator_name, l.name AS layout_name,
                (SELECT COUNT(*) FROM playlist_items pli WHERE pli.playlist_id=p.id AND pli.is_active=1) AS item_count
                FROM playlists p
                LEFT JOIN users u ON u.id=p.created_by
                LEFT JOIN layouts l ON l.id=p.layout_id
                WHERE p.tenant_id=?";
        $params = [$this->tenantId];

        if (!empty($filters['search'])) {
            $sql .= " AND p.name LIKE ?"; $params[] = "%{$filters['search']}%";
        }
        $sql .= " ORDER BY p.updated_at DESC";
        return $this->db->paginate($sql, $params, $page, $perPage);
    }

    public function find(int $id): ?array
    {
        $playlist = $this->db->row("SELECT p.*, l.name AS layout_name, l.zones, l.canvas_width, l.canvas_height FROM playlists p LEFT JOIN layouts l ON l.id=p.layout_id WHERE p.id=? AND p.tenant_id=?", [$id, $this->tenantId]);
        if ($playlist) {
            $playlist['items'] = $this->getItems($id);
            $playlist['zones'] = $playlist['zones'] ? json_decode($playlist['zones'], true) : null;
        }
        return $playlist;
    }

    public function create(array $data): int|string
    {
        $items = $data['items'] ?? [];
        unset($data['items']);
        $id = $this->db->insert('playlists', array_merge($data, ['tenant_id' => $this->tenantId, 'created_by' => Auth::id()]));
        if ($items) $this->syncItems((int)$id, $items);
        return $id;
    }

    private const ALLOWED_COLS = ['name','description','layout_id','transition','transition_duration',
        'default_duration','loop','shuffle','is_active','tags'];

    public function update(int $id, array $data): bool
    {
        $items = $data['items'] ?? null;
        // فقط ستون‌های مجاز
        $clean = array_intersect_key($data, array_flip(self::ALLOWED_COLS));
        if (empty($clean)) return false;
        $ok = $this->db->update('playlists', $clean, ['id' => $id, 'tenant_id' => $this->tenantId]) >= 0;
        if ($items !== null) $this->syncItems($id, $items);
        return $ok;
    }

    public function delete(int $id): bool
    {
        return $this->db->delete('playlists', ['id' => $id, 'tenant_id' => $this->tenantId]) > 0;
    }

    public function getItems(int $playlistId): array
    {
        return $this->db->rows(
            "SELECT pi.*, m.name AS media_name, m.type AS media_type, m.file_path, m.url, m.thumbnail_path, m.mime_type, m.duration AS media_duration
             FROM playlist_items pi
             LEFT JOIN media m ON m.id=pi.media_id
             WHERE pi.playlist_id=? AND pi.is_active=1
             ORDER BY pi.sort_order ASC",
            [$playlistId]
        );
    }

    public function syncItems(int $playlistId, array $items): void
    {
        $this->db->query("DELETE FROM playlist_items WHERE playlist_id=?", [$playlistId]);
        foreach ($items as $i => $item) {
            $this->db->insert('playlist_items', [
                'playlist_id' => $playlistId,
                'media_id'    => $item['media_id'] ?? null,
                'zone_id'     => $item['zone_id'] ?? null,
                'sort_order'  => $i,
                'duration'    => $item['duration'] ?? 10,
                'volume'      => $item['volume'] ?? 100,
                'settings'    => isset($item['settings']) ? json_encode($item['settings']) : null,
            ]);
        }
    }

    public function getForPlayer(int $playlistId): array
    {
        $playlist = $this->find($playlistId);
        if (!$playlist) return [];

        // از IP/host واقعی سرور استفاده کن (نه APP_URL که localhost هست)
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;

        foreach ($playlist['items'] as &$item) {
            $fp  = $item['file_path'] ?? '';
            $url = $item['url'] ?? '';

            // src برای player — با IP/host واقعی سرور
            if ($fp && !str_starts_with($fp, 'http')) {
                $item['src']      = $baseUrl . $fp;
                $item['file_url'] = $baseUrl . $fp;
            } elseif ($fp && str_starts_with($fp, 'http')) {
                // اگه localhost بود، با host واقعی جایگزین کن
                $fp2 = preg_replace('#^https?://(localhost|127\.0\.0\.1)(:\d+)?#', $baseUrl, $fp);
                $item['src']      = $fp2;
                $item['file_url'] = $fp2;
            } elseif ($url) {
                $url2 = preg_replace('#^https?://(localhost|127\.0\.0\.1)(:\d+)?#', $baseUrl, $url);
                $item['src']      = $url2;
                $item['file_url'] = $url2;
            }

            // thumbnail
            $thumb = $item['thumbnail_path'] ?? '';
            if ($thumb && !str_starts_with($thumb, 'http')) {
                $item['thumbnail_url'] = $baseUrl . $thumb;
            }

            // type
            $item['type'] = $item['media_type'] ?? $item['type'] ?? 'image';

            // module type - تشخیص از mime یا url
            $mime = $item['mime_type'] ?? '';
            $src  = $item['file_url'] ?? $item['src'] ?? '';
            if ($mime === 'application/x-signage-module' || str_contains($src, '/player/module/')) {
                $item['type'] = 'url'; // در player به عنوان iframe رندر می‌شه
            }

            // duration
            $item['duration'] = (int)($item['duration'] ?? $item['media_duration'] ?? 10);
        }

        return $playlist;
    }
}
