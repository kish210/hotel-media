<?php
declare(strict_types=1);
namespace App\Modules\VOD;

use App\Modules\Core\BaseModule;

/**
 * VOD Module — Video on Demand
 * کتابخانه فیلم و ویدیو
 */
class VODModule extends BaseModule
{
    public function id(): string          { return 'vod'; }
    public function name(): string        { return 'ویدیو درخواستی (VOD)'; }
    public function nameEn(): string      { return 'Video on Demand (VOD)'; }
    public function description(): string { return 'مدیریت کتابخانه فیلم، سریال و محتوای ویدیویی برای نمایش روی صفحات IPTV'; }
    public function version(): string     { return '1.0.0'; }
    public function icon(): string        { return 'fas fa-film'; }
    public function color(): string       { return '#ec4899'; }
    public function category(): string    { return 'media'; }

    public function zoneTypes(): array
    {
        return [
            [
                'id'          => 'vod_player',
                'label'       => 'پخش VOD',
                'label_en'    => 'VOD Player',
                'icon'        => 'fas fa-film',
                'description' => 'پخش فیلم و سریال از کتابخانه محتوا',
                'defaultSize' => ['w' => 1920, 'h' => 1080],
            ],
        ];
    }

    public function migrations(): array { return []; }

    public function getDashboardStats(): array
    {
        try {
            $movies  = (int)$this->db->value("SELECT COUNT(*) FROM vod_movies WHERE tenant_id=? AND is_active=1", [$this->tenantId]);
            $series  = (int)$this->db->value("SELECT COUNT(*) FROM vod_series WHERE tenant_id=? AND is_active=1", [$this->tenantId]);
            return ['movies' => $movies, 'series' => $series];
        } catch (\Throwable $e) { return []; }
    }

    public function renderPlayerWidget(string $zoneType, array $settings = []): string
    {
        return '<div style="width:100%;height:100%;background:#000;display:flex;align-items:center;justify-content:center;color:#ec4899;font-size:18px;">'
             . '<i class="fas fa-film" style="margin-left:10px;"></i> پخش VOD</div>';
    }
}
