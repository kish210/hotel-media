<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};

class Monitor3dController extends Controller
{
    // ── لیست صفحات 3D ────────────────────────────────────────────────────────
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();
        $this->ensureTable();

        $screens = $this->db->rows(
            "SELECT s.*,
                    l.name AS location_name,
                    c.format_3d, c.depth_level, c.depth_color, c.bg_color,
                    c.is_outdoor, c.auto_rotate, c.parallax_intensity, c.show_depth_badge,
                    TIMESTAMPDIFF(SECOND, s.last_seen_at, NOW()) AS seconds_ago
             FROM screens s
             LEFT JOIN locations l ON l.id = s.location_id
             LEFT JOIN monitor_3d_configs c ON c.screen_id = s.id
             WHERE s.tenant_id = ? AND s.screen_type = 'monitor_3d'
             ORDER BY s.name ASC",
            [$tid]
        ) ?: [];

        $this->view('admin.monitor3d.index', [
            'title'   => 'مانیتورهای ۳D',
            'screens' => $screens,
        ]);
    }

    // ── ذخیره تنظیمات 3D برای یک صفحه ───────────────────────────────────────
    public function saveConfig(Request $req, array $params): void
    {
        $tid      = Auth::tenantId();
        $screenId = (int)$params['id'];
        $this->ensureTable();

        // اطمینان از اینکه صفحه متعلق به tenant است
        $screen = $this->db->row(
            "SELECT id FROM screens WHERE id=? AND tenant_id=? AND screen_type='monitor_3d'",
            [$screenId, $tid]
        );
        if (!$screen) {
            $this->flash('error', 'صفحه یافت نشد');
            $this->redirect('/admin/monitor3d');
            return;
        }

        $format3d  = in_array($req->post('format_3d'), ['normal','sbs','top_bottom','hologram','anaglyphic'])
            ? $req->post('format_3d') : 'normal';
        $depth     = max(1, min(10, (int)$req->post('depth_level', 5)));
        $parallax  = max(1, min(10, (int)$req->post('parallax_intensity', 6)));
        $color     = preg_match('/^#[0-9a-fA-F]{6}$/', $req->post('depth_color','')) ? $req->post('depth_color') : '#00e5ff';
        $bg        = preg_match('/^#[0-9a-fA-F]{6}$/', $req->post('bg_color','')) ? $req->post('bg_color') : '#000000';
        $rotSpd    = max(1, min(20, (int)$req->post('rotate_speed', 5)));

        $data = [
            'screen_id'          => $screenId,
            'tenant_id'          => $tid,
            'format_3d'          => $format3d,
            'depth_level'        => $depth,
            'depth_color'        => $color,
            'bg_color'           => $bg,
            'is_outdoor'         => (int)!empty($req->post('is_outdoor')),
            'auto_rotate'        => (int)!empty($req->post('auto_rotate')),
            'rotate_speed'       => $rotSpd,
            'parallax_intensity' => $parallax,
            'show_depth_badge'   => (int)!empty($req->post('show_depth_badge')),
        ];

        $existing = $this->db->value("SELECT id FROM monitor_3d_configs WHERE screen_id=?", [$screenId]);
        if ($existing) {
            $this->db->update('monitor_3d_configs', $data, ['screen_id' => $screenId]);
        } else {
            $this->db->insert('monitor_3d_configs', $data);
        }

        $this->flash('success', 'تنظیمات ۳D ذخیره شد');
        $this->redirect('/admin/monitor3d');
    }

    // ── پیش‌نمایش پلیر 3D (iframe-able) ─────────────────────────────────────
    public function preview(Request $req, array $params): void
    {
        $tid      = Auth::tenantId();
        $screenId = (int)$params['id'];
        $this->ensureTable();

        $screen = $this->db->row(
            "SELECT s.*, c.format_3d, c.depth_level, c.depth_color, c.bg_color,
                    c.is_outdoor, c.auto_rotate, c.rotate_speed, c.parallax_intensity, c.show_depth_badge
             FROM screens s
             LEFT JOIN monitor_3d_configs c ON c.screen_id = s.id
             WHERE s.id=? AND s.tenant_id=?",
            [$screenId, $tid]
        );
        if (!$screen) { http_response_code(404); echo 'Not found'; exit; }

        // بسازیم cfg_3d array برای پروفایل
        $screen['cfg_3d'] = [
            'format_3d'          => $screen['format_3d']          ?? 'normal',
            'depth_level'        => $screen['depth_level']        ?? 5,
            'depth_color'        => $screen['depth_color']        ?? '#00e5ff',
            'bg_color'           => $screen['bg_color']           ?? '#000000',
            'is_outdoor'         => $screen['is_outdoor']         ?? 0,
            'auto_rotate'        => $screen['auto_rotate']        ?? 0,
            'rotate_speed'       => $screen['rotate_speed']       ?? 5,
            'parallax_intensity' => $screen['parallax_intensity'] ?? 6,
            'show_depth_badge'   => $screen['show_depth_badge']   ?? 1,
        ];

        include VIEWS_PATH . '/player/profiles/monitor_3d.php';
        exit;
    }

    // ── آمار سریع (AJAX) ─────────────────────────────────────────────────────
    public function stats(Request $req): void
    {
        $tid = Auth::tenantId();
        $this->ensureTable();

        $rows = $this->db->rows(
            "SELECT s.id, s.name, s.is_online, s.last_seen_at,
                    c.format_3d, c.depth_color,
                    TIMESTAMPDIFF(SECOND, s.last_seen_at, NOW()) AS seconds_ago
             FROM screens s
             LEFT JOIN monitor_3d_configs c ON c.screen_id = s.id
             WHERE s.tenant_id=? AND s.screen_type='monitor_3d'",
            [$tid]
        ) ?: [];

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    // ── اطمینان از وجود جدول ─────────────────────────────────────────────────
    private function ensureTable(): void
    {
        try {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS `monitor_3d_configs` (
                    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `screen_id`          INT UNSIGNED NOT NULL,
                    `tenant_id`          INT UNSIGNED NOT NULL DEFAULT 1,
                    `format_3d`          ENUM('normal','sbs','top_bottom','hologram','anaglyphic') NOT NULL DEFAULT 'normal',
                    `depth_level`        TINYINT UNSIGNED NOT NULL DEFAULT 5,
                    `depth_color`        VARCHAR(20) NOT NULL DEFAULT '#00e5ff',
                    `is_outdoor`         TINYINT(1) NOT NULL DEFAULT 0,
                    `bg_color`           VARCHAR(20) NOT NULL DEFAULT '#000000',
                    `auto_rotate`        TINYINT(1) NOT NULL DEFAULT 0,
                    `rotate_speed`       INT UNSIGNED NOT NULL DEFAULT 5,
                    `aspect_ratio`       VARCHAR(20) NOT NULL DEFAULT '16:9',
                    `parallax_intensity` TINYINT UNSIGNED NOT NULL DEFAULT 6,
                    `show_depth_badge`   TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at`         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at`         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_screen_id` (`screen_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable $e) {}
    }
}
