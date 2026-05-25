<?php
declare(strict_types=1);
namespace App\Modules\Transport;
use App\Modules\Core\BaseModule;

class TransportModule extends BaseModule
{
    public function id(): string          { return 'transport'; }
    public function name(): string        { return 'حمل‌ونقل عمومی'; }
    public function nameEn(): string      { return 'Public Transport'; }
    public function description(): string { return 'نمایش برنامه اتوبوس، مترو، تاکسی و حمل‌ونقل عمومی'; }
    public function version(): string     { return '1.0.0'; }
    public function icon(): string        { return 'fas fa-bus'; }
    public function color(): string       { return '#22c55e'; }
    public function category(): string    { return 'transport'; }

    public function zoneTypes(): array
    {
        return [
            ['id'=>'transport_bus',    'label'=>'برنامه اتوبوس',  'icon'=>'fas fa-bus',             'defaultSize'=>['w'=>1920,'h'=>1080],
             'settings'=>[['key'=>'station','label'=>'نام ایستگاه','type'=>'text','default'=>'ایستگاه مرکزی'],['key'=>'rows','label'=>'تعداد سطر','type'=>'number','default'=>10],['key'=>'refresh_sec','label'=>'بروزرسانی','type'=>'number','default'=>30]]],
            ['id'=>'transport_metro',  'label'=>'برنامه مترو',    'icon'=>'fas fa-train-subway',     'defaultSize'=>['w'=>1920,'h'=>1080],
             'settings'=>[['key'=>'station','label'=>'نام ایستگاه','type'=>'text','default'=>'ایستگاه'],['key'=>'line','label'=>'خط','type'=>'text','default'=>'خط ۱']]],
            ['id'=>'transport_map',    'label'=>'نقشه مسیر',      'icon'=>'fas fa-map',              'defaultSize'=>['w'=>1920,'h'=>1080],
             'settings'=>[['key'=>'map_url','label'=>'آدرس نقشه','type'=>'url','default'=>'']]],
            ['id'=>'transport_taxi',   'label'=>'سرویس تاکسی',   'icon'=>'fas fa-taxi',             'defaultSize'=>['w'=>1920,'h'=>600],
             'settings'=>[['key'=>'phone','label'=>'شماره تلفن','type'=>'text','default'=>'1234']]],
        ];
    }

    public function migrations(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS `transport_schedules` (
                `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id`    INT UNSIGNED NOT NULL,
                `type`         ENUM('bus','metro','train','tram') NOT NULL DEFAULT 'bus',
                `line`         VARCHAR(100) NOT NULL,
                `direction`    VARCHAR(255) NOT NULL,
                `station`      VARCHAR(255) NOT NULL,
                `departure`    TIME NOT NULL,
                `frequency_min` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'دقیقه',
                `days`         JSON DEFAULT NULL,
                `notes`        TEXT DEFAULT NULL,
                `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                KEY `idx_trans_tenant` (`tenant_id`),
                KEY `idx_trans_type`   (`type`),
                KEY `idx_trans_dep`    (`departure`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }

    public function renderPlayerWidget(string $zoneType, array $settings = []): string
    {
        $station = htmlspecialchars($settings['station'] ?? 'ایستگاه');
        $rows    = (int)($settings['rows'] ?? 10);
        $refresh = (int)($settings['refresh_sec'] ?? 30);
        $type    = match($zoneType) { 'transport_metro'=>'metro', 'transport_taxi'=>'taxi', default=>'bus' };
        $icons   = ['bus'=>'fas fa-bus','metro'=>'fas fa-train-subway','taxi'=>'fas fa-taxi'];
        $colors  = ['bus'=>'#22c55e','metro'=>'#3b82f6','taxi'=>'#f59e0b'];
        $icon    = $icons[$type]  ?? 'fas fa-bus';
        $color   = $colors[$type] ?? '#22c55e';

        if ($zoneType === 'transport_taxi') return $this->renderTaxi($settings, $color);

        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#050d0f;font-family:'Segoe UI',sans-serif;direction:rtl;display:flex;flex-direction:column;">
  <div style="background:__VAR_COLOR__22;border-bottom:2px solid __VAR_COLOR__;padding:16px 28px;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:14px;">
      <div style="width:48px;height:48px;background:__VAR_COLOR__33;border-radius:14px;display:flex;align-items:center;justify-content:center;border:1px solid __VAR_COLOR__66;">
        <i class="__VAR_ICON__" style="font-size:22px;color:__VAR_COLOR__;"></i>
      </div>
      <div>
        <div style="font-size:20px;font-weight:800;color:#fff;">__VAR_STATION__</div>
        <div style="font-size:12px;color:#64748b;">برنامه حرکت · Departures</div>
      </div>
    </div>
    <div style="text-align:left;">
      <div id="trans-clock" style="font-size:28px;font-weight:700;color:#fff;font-family:monospace;"></div>
      <div style="font-size:11px;color:#475569;text-align:center;margin-top:2px;">بروزرسانی هر __VAR_REFRESH__ث</div>
    </div>
  </div>

  <!-- Column Headers -->
  <div style="display:grid;grid-template-columns:80px 1fr 100px 100px 100px;padding:10px 28px;background:__VAR_COLOR__11;font-size:11px;font-weight:700;color:__VAR_COLOR__;letter-spacing:0.5px;text-transform:uppercase;gap:8px;">
    <div>خط</div><div>مقصد</div><div>حرکت</div><div>سکو</div><div>وضعیت</div>
  </div>

  <!-- Rows -->
  <div id="trans-rows" style="flex:1;overflow:hidden;">
    <div style="text-align:center;padding:40px;color:#475569;"><i class="__VAR_ICON__" style="font-size:40px;opacity:0.2;display:block;margin-bottom:12px;"></i>در حال بارگذاری...</div>
  </div>
</div>
<script>
(function(){
  setInterval(()=>{const e=document.getElementById('trans-clock');if(e)e.textContent=new Date().toLocaleTimeString('fa-IR',{hour:"2-digit",minute:"2-digit",second:"2-digit"});},1000);
  async function load(){
    try{
      const r=await fetch('/api/v1/transport/schedules?type=__VAR_TYPE__&limit=__VAR_ROWS__');
      const d=await r.json();
      render(d.data||[]);
    }catch(e){document.getElementById('trans-rows').innerHTML='<div style="color:#f87171;text-align:center;padding:40px;">خطا در بارگذاری</div>';}
  }
  function render(items){
    const el=document.getElementById('trans-rows');
    const now=new Date();
    if(!items.length){el.innerHTML='<div style="color:#475569;text-align:center;padding:60px;"><i class="__VAR_ICON__" style="font-size:48px;opacity:0.2;display:block;margin-bottom:12px;"></i>برنامه‌ای موجود نیست</div>';return;}
    el.innerHTML=items.map((s,i)=>{
      const dep=new Date(); const[h,m]=s.departure.split(':'); dep.setHours(parseInt(h),parseInt(m),0,0);
      const diff=Math.round((dep-now)/60000);
      const timeStr=dep.toLocaleTimeString('fa-IR',{hour:"2-digit",minute:"2-digit"});
      const statusColor=diff<2?'#ef4444':diff<10?'#f59e0b':'__VAR_COLOR__';
      const statusLabel=diff<0?'رفت':diff<2?'اکنون':diff+'دقیقه';
      const bg=i%2===0?'rgba(255,255,255,0.02)':'rgba(255,255,255,0.01)';
      return `<div style="display:grid;grid-template-columns:80px 1fr 100px 100px 100px;padding:14px 28px;background:\${bg};border-bottom:1px solid rgba(255,255,255,0.04);gap:8px;align-items:center;min-height:56px;">
        <div style="font-size:16px;font-weight:800;color:__VAR_COLOR__;font-family:monospace;">\${s.line}</div>
        <div style="font-size:15px;font-weight:600;color:#f1f5f9;">\${s.direction}</div>
        <div style="font-size:16px;font-weight:700;color:#fff;font-family:monospace;">\${timeStr}</div>
        <div style="font-size:14px;color:#94a3b8;">\\${s.notes||'—'}</div>
        <div><span style="background:\${statusColor}22;color:\${statusColor};border:1px solid \${statusColor}44;padding:4px 10px;border-radius:20px;font-size:13px;font-weight:700;">\${statusLabel}</span></div>
      </div>`;
    }).join('');
  }
  load();setInterval(load,__VAR_REFRESH__000);
})();
</script>
HTML;
        return str_replace(
            ['__VAR_COLOR__', '__VAR_ICON__', '__VAR_STATION__', '__VAR_REFRESH__', '__VAR_TYPE__', '__VAR_ROWS__'],
            [$color, $icon, $station, $refresh, $type, $rows],
            $__tpl
        );

        return str_replace(
            ['__VAR_COLOR__', '__VAR_ICON__', '__VAR_STATION__', '__VAR_REFRESH__', '__VAR_TYPE__', '__VAR_ROWS__'],
            [$color, $icon, $station, $refresh, $type, $rows],
            $__tpl
        );
    }

    private function renderTaxi(array $s, string $color): string
    {
        $phone = htmlspecialchars($s['phone'] ?? '1234');
        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#050d0f;display:flex;align-items:center;justify-content:center;font-family:'Segoe UI',sans-serif;direction:rtl;gap:40px;">
  <div style="text-align:center;">
    <div style="width:120px;height:120px;border-radius:28px;background:__VAR_COLOR__22;border:2px solid __VAR_COLOR__44;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
      <i class="fas fa-taxi" style="font-size:52px;color:__VAR_COLOR__;"></i>
    </div>
    <div style="font-size:22px;font-weight:800;color:#fff;">سرویس تاکسی</div>
    <div style="font-size:14px;color:#64748b;margin-top:4px;">Taxi Service</div>
  </div>
  <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:24px;padding:32px 48px;text-align:center;">
    <div style="font-size:14px;color:#94a3b8;margin-bottom:8px;">برای رزرو با شماره زیر تماس بگیرید</div>
    <div style="font-size:56px;font-weight:900;color:__VAR_COLOR__;letter-spacing:4px;font-family:monospace;">__VAR_PHONE__</div>
    <div style="font-size:12px;color:#475569;margin-top:8px;">۲۴ ساعته · ۷ روز هفته</div>
  </div>
</div>
HTML;
        return str_replace(
            ['__VAR_COLOR__', '__VAR_PHONE__'],
            [$color, $phone],
            $__tpl
        );

        return str_replace(
            ['__VAR_COLOR__', '__VAR_PHONE__'],
            [$color, $phone],
            $__tpl
        );
    }

    public function getDashboardStats(): array { return ['schedules'=>(int)$this->db->value("SELECT COUNT(*) FROM transport_schedules WHERE tenant_id=? AND is_active=1",[$this->tenantId])]; }
}
