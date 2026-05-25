<?php
declare(strict_types=1);
namespace App\Modules\IPTV;

use App\Modules\Core\BaseModule;

/**
 * IPTV Module
 * مدیریت کانال‌های تلویزیونی، منوهای IPTV و اتاق‌های هتل
 */
class IPTVModule extends BaseModule
{
    public function id(): string          { return 'iptv'; }
    public function name(): string        { return 'IPTV و تلویزیون'; }
    public function nameEn(): string      { return 'IPTV & Live TV'; }
    public function description(): string { return 'مدیریت کانال‌های تلویزیونی زنده، منوهای IPTV، اتاق‌های هتل و سیستم PMS'; }
    public function version(): string     { return '1.1.0'; }
    public function icon(): string        { return 'fas fa-satellite-dish'; }
    public function color(): string       { return '#ef4444'; }
    public function category(): string    { return 'media'; }

    public function zoneTypes(): array
    {
        return [
            [
                'id'          => 'iptv_player',
                'label'       => 'پخش زنده IPTV',
                'label_en'    => 'Live IPTV Player',
                'icon'        => 'fas fa-satellite-dish',
                'description' => 'پخش جریان زنده تلویزیون از طریق HLS/RTSP/RTMP',
                'defaultSize' => ['w' => 1920, 'h' => 1080],
            ],
            [
                'id'          => 'iptv_room_menu',
                'label'       => 'منوی اتاق هتل',
                'label_en'    => 'Hotel Room Menu',
                'icon'        => 'fas fa-hotel',
                'description' => 'منوی تعاملی اتاق برای هتل‌ها با نمایش پیام‌ها',
                'defaultSize' => ['w' => 1920, 'h' => 1080],
            ],
        ];
    }

    public function migrations(): array { return []; }

    public function getDashboardStats(): array
    {
        try {
            $channels = (int)$this->db->value("SELECT COUNT(*) FROM iptv_channels WHERE tenant_id=? AND is_active=1", [$this->tenantId]);
            $rooms    = (int)$this->db->value("SELECT COUNT(*) FROM iptv_rooms WHERE tenant_id=? AND is_active=1", [$this->tenantId]);
            $occupied = (int)$this->db->value("SELECT COUNT(*) FROM iptv_rooms WHERE tenant_id=? AND status='occupied'", [$this->tenantId]);
            return ['channels' => $channels, 'rooms' => $rooms, 'occupied' => $occupied];
        } catch (\Throwable $e) { return []; }
    }

    public function renderPlayerWidget(string $zoneType, array $settings = []): string
    {
        $merged    = array_merge($this->config, $settings);
        $streamUrl = $merged['stream_url']   ?? '';
        $title     = $merged['channel_name'] ?? 'کانال زنده';
        $showInfo  = !empty($merged['show_info']);
        $proxyUrl  = $streamUrl ? ('/api/v1/iptv/proxy?url=' . urlencode($streamUrl) . '&format=hls') : '';

        $titleEsc  = htmlspecialchars($title, ENT_QUOTES);
        $urlEsc    = htmlspecialchars($streamUrl, ENT_QUOTES);
        $proxyEsc  = htmlspecialchars($proxyUrl, ENT_QUOTES);
        $infoStyle = $showInfo ? '' : 'display:none';

        return <<<HTML
<div style="width:100%;height:100%;background:#000;position:relative;overflow:hidden;">
  <video id="iptv-video" autoplay muted playsinline
    style="width:100%;height:100%;object-fit:contain;display:block;"
    onerror="iptvError()"></video>
  <div style="position:absolute;top:0;left:0;right:0;padding:12px 16px;
              background:linear-gradient(180deg,rgba(0,0,0,.7),transparent);{$infoStyle}">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="width:8px;height:8px;border-radius:50%;background:#ef4444;animation:livePulse 1s infinite;"></div>
      <span style="color:#fff;font-size:14px;font-weight:700;">{$titleEsc}</span>
      <span style="background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.5);
                   color:#f87171;font-size:10px;padding:2px 8px;border-radius:10px;">LIVE</span>
    </div>
  </div>
  <div id="iptv-error" style="display:none;position:absolute;inset:0;background:#000;
    align-items:center;justify-content:center;flex-direction:column;gap:12px;">
    <i class="fas fa-satellite-dish" style="font-size:48px;color:#374151;"></i>
    <div style="color:#6b7280;font-size:14px;">اتصال برقرار نشد</div>
    <button onclick="iptvRetry()" style="padding:8px 20px;background:#ef4444;border:0;
      border-radius:8px;color:#fff;cursor:pointer;font-size:13px;">تلاش مجدد</button>
  </div>
  <style>@keyframes livePulse{0%,100%{opacity:1}50%{opacity:.3}}</style>
  <script>
  (function(){
    var url='{$urlEsc}',proxy='{$proxyEsc}',vid=document.getElementById('iptv-video'),r=0;
    function load(u){
      if(!u){showErr();return;}
      if(u.match(/\.m3u8/i)){
        if(window.Hls&&Hls.isSupported()){var h=new Hls({lowLatencyMode:true,maxBufferLength:10});h.loadSource(u);h.attachMedia(vid);h.on(Hls.Events.ERROR,function(e,d){if(d.fatal)fall();});}
        else if(vid.canPlayType('application/vnd.apple.mpegurl')){vid.src=u;vid.play().catch(function(){});}
        else fall();
      }else if(u.match(/^rtsp:\/\/|^udp:\/\//i)){load(proxy);}
      else{vid.src=u;vid.play().catch(function(){fall();});}
    }
    function fall(){if(r++<3)setTimeout(function(){load(proxy||url);},3000);else showErr();}
    function showErr(){var e=document.getElementById('iptv-error');if(e)e.style.display='flex';}
    window.iptvError=showErr;
    window.iptvRetry=function(){var e=document.getElementById('iptv-error');if(e)e.style.display='none';r=0;load(url);};
    load(url);
  })();
  </script>
</div>
HTML;
    }
}
