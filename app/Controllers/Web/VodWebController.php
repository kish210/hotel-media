<?php
declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\{Controller, Request, Auth};

class VodWebController extends Controller
{
    public function index(Request $req): void
    {
        $tid = Auth::tenantId();

        // آمار
        $stats = ['total'=>0,'uploads'=>0,'total_size'=>0,'total_size_fmt'=>'0 B','categories'=>0,'total_views'=>0];
        try {
            $stats = $this->db->row(
                "SELECT COUNT(*) AS total, SUM(type='upload') AS uploads,
                        SUM(file_size) AS total_size, SUM(views) AS total_views
                 FROM vod_videos WHERE tenant_id=? AND is_active=1",
                [$tid]
            ) ?? $stats;
            $stats['total_size_fmt'] = $this->formatSize((int)($stats['total_size'] ?? 0));
            $stats['categories']     = (int)$this->db->query(
                "SELECT COUNT(*) FROM vod_categories WHERE tenant_id=? AND is_active=1", [$tid]
            )->fetchColumn();
        } catch (\Throwable $e) {}

        // دسته‌بندی‌ها
        $categories = [];
        try {
            $categories = $this->db->rows(
                "SELECT c.*, COUNT(v.id) AS video_count
                 FROM vod_categories c
                 LEFT JOIN vod_videos v ON v.category_id=c.id AND v.is_active=1
                 WHERE c.tenant_id=? AND c.is_active=1
                 GROUP BY c.id ORDER BY c.sort_order, c.name",
                [$tid]
            );
        } catch (\Throwable $e) {}

        $this->view('admin.vod.index', compact('stats','categories') + ['title' => 'مدیریت VOD']);
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024) . ' KB';
        return $bytes . ' B';
    }
}
