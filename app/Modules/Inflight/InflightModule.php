<?php
declare(strict_types=1);
namespace App\Modules\Inflight;

use App\Modules\Core\BaseModule;

/**
 * In-Flight Display Module
 * نمایشگر اطلاعات پرواز داخل هواپیما — مشابه AirGoGo
 */
class InflightModule extends BaseModule
{
    public function id(): string          { return 'inflight'; }
    public function name(): string        { return 'نمایش اطلاعات پرواز'; }
    public function nameEn(): string      { return 'In-Flight Display'; }
    public function description(): string { return 'نمایش اطلاعات پرواز روی مانیتورهای داخل هواپیما — نقشه مسیر، ارتفاع، سرعت، ساعت مبدا/مقصد'; }
    public function version(): string     { return '1.0.0'; }
    public function icon(): string        { return 'fas fa-plane'; }
    public function color(): string       { return '#00b4d8'; }
    public function category(): string    { return 'transport'; }

    public function zoneTypes(): array
    {
        return [
            [
                'id'          => 'inflight_map',
                'label'       => 'نقشه مسیر پرواز',
                'label_en'    => 'Flight Route Map',
                'icon'        => 'fas fa-route',
                'description' => 'نمایش نقشه جهانی با مسیر پرواز، موقعیت هواپیما و اطلاعات زنده',
                'defaultSize' => ['w' => 1920, 'h' => 1080],
            ],
        ];
    }

    public function migrations(): array { return []; }

    public function getDashboardStats(): array
    {
        try {
            $total   = (int)$this->db->value("SELECT COUNT(*) FROM inflight_flights WHERE tenant_id=? AND is_active=1", [$this->tenantId]);
            $flying  = (int)$this->db->value(
                "SELECT COUNT(*) FROM inflight_flights WHERE tenant_id=? AND is_active=1 AND phase NOT IN ('preflight','landed')",
                [$this->tenantId]
            );
            return ['total' => $total, 'flying' => $flying];
        } catch (\Throwable $e) { return []; }
    }

    public function renderPlayerWidget(string $zoneType, array $settings = []): string
    {
        $flightId = (int)($settings['flight_id'] ?? 0);
        return '<div style="width:100%;height:100%;background:#000;display:flex;align-items:center;justify-content:center;color:#00b4d8;font-size:18px;">'
             . '<i class="fas fa-plane" style="margin-left:10px;"></i> نمایش پرواز #' . $flightId
             . '</div>';
    }
}
