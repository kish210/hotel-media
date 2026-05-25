<?php
declare(strict_types=1);

namespace App\Modules\Hotel;

use App\Modules\Core\BaseModule;

/**
 * HotelModule — اطلاع‌رسانی هتل
 * خوش‌آمدگویی، سرویس اتاق، رویدادها، امکانات، جاذبه‌های محلی
 */
class HotelModule extends BaseModule
{
    public function id(): string          { return 'hotel'; }
    public function name(): string        { return 'اطلاع‌رسانی هتل'; }
    public function nameEn(): string      { return 'Hotel Information Display'; }
    public function description(): string { return 'نمایش خوش‌آمدگویی، اطلاعات اتاق، منوی سرویس، رویدادها و امکانات هتل'; }
    public function version(): string     { return '1.1.0'; }
    public function icon(): string        { return 'fas fa-hotel'; }
    public function color(): string       { return '#d4af37'; }
    public function category(): string    { return 'hospitality'; }

    public function zoneTypes(): array
    {
        return [
            ['id'=>'hotel_welcome',    'label'=>'خوش‌آمدگویی',       'label_en'=>'Welcome Screen', 'icon'=>'fas fa-star', 'description'=>'صفحه خوش‌آمدگویی با نام مهمان', 'defaultSize'=>['w'=>1920,'h'=>1080],'settings'=>[['key'=>'hotel_name','label'=>'نام هتل','type'=>'text','default'=>'هتل گرند'],['key'=>'slogan','label'=>'شعار','type'=>'text','default'=>'خوش آمدید'],['key'=>'bg_color','label'=>'رنگ زمینه','type'=>'color','default'=>'#0a0a0f'],['key'=>'show_clock','label'=>'نمایش ساعت','type'=>'bool','default'=>true],['key'=>'show_weather','label'=>'نمایش آب‌وهوا','type'=>'bool','default'=>true]]],
            ['id'=>'hotel_room_service','label'=>'سرویس اتاق',       'label_en'=>'Room Service',   'icon'=>'fas fa-concierge-bell','description'=>'منوی سرویس اتاق','defaultSize'=>['w'=>1920,'h'=>1080],'settings'=>[['key'=>'currency','label'=>'واحد پول','type'=>'select','options'=>['IRR'=>'ریال','USD'=>'دلار','EUR'=>'یورو'],'default'=>'IRR'],['key'=>'phone','label'=>'شماره سرویس','type'=>'text','default'=>'9']]],
            ['id'=>'hotel_events',     'label'=>'رویدادها و کنفرانس','label_en'=>'Events',         'icon'=>'fas fa-calendar-star','description'=>'نمایش رویدادها و کنفرانس‌های هتل','defaultSize'=>['w'=>1920,'h'=>1080],'settings'=>[['key'=>'show_map','label'=>'نقشه سالن','type'=>'bool','default'=>true]]],
            ['id'=>'hotel_amenities',  'label'=>'امکانات هتل',       'label_en'=>'Amenities',      'icon'=>'fas fa-spa','description'=>'معرفی امکانات و خدمات هتل','defaultSize'=>['w'=>1920,'h'=>1080],'settings'=>[['key'=>'layout','label'=>'چیدمان','type'=>'select','options'=>['grid'=>'شبکه','list'=>'لیست'],'default'=>'grid']]],
            ['id'=>'hotel_directory',  'label'=>'دایرکتوری هتل',     'label_en'=>'Directory',      'icon'=>'fas fa-map-signs','description'=>'راهنمای طبقات و بخش‌ها','defaultSize'=>['w'=>1080,'h'=>1920],'settings'=>[]],
            ['id'=>'hotel_checkin',    'label'=>'اطلاعات ورود/خروج',  'label_en'=>'Check-in Info',  'icon'=>'fas fa-key','description'=>'اطلاعات check-in و check-out','defaultSize'=>['w'=>1920,'h'=>1080],'settings'=>[['key'=>'checkin_time','label'=>'ساعت ورود','type'=>'time','default'=>'14:00'],['key'=>'checkout_time','label'=>'ساعت خروج','type'=>'time','default'=>'12:00']]],
            ['id'=>'hotel_attractions','label'=>'جاذبه‌های محلی',    'label_en'=>'Local Attractions','icon'=>'fas fa-map-location-dot','description'=>'معرفی جاذبه‌های گردشگری اطراف','defaultSize'=>['w'=>1920,'h'=>1080],'settings'=>[['key'=>'show_map','label'=>'نقشه','type'=>'bool','default'=>true]]],
            ['id'=>'hotel_promo',      'label'=>'پیشنهادات ویژه',    'label_en'=>'Promotions',     'icon'=>'fas fa-percent','description'=>'نمایش تخفیف‌ها و پیشنهادات ویژه','defaultSize'=>['w'=>1920,'h'=>1080],'settings'=>[]],
        ];
    }

    public function migrations(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS `hotel_info` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id`     INT UNSIGNED NOT NULL,
                `hotel_name`    VARCHAR(255) NOT NULL,
                `hotel_name_en` VARCHAR(255) DEFAULT NULL,
                `slogan`        VARCHAR(500) DEFAULT NULL,
                `logo`          VARCHAR(500) DEFAULT NULL,
                `address`       TEXT DEFAULT NULL,
                `phone`         VARCHAR(20) DEFAULT NULL,
                `email`         VARCHAR(255) DEFAULT NULL,
                `website`       VARCHAR(500) DEFAULT NULL,
                `checkin_time`  TIME DEFAULT '14:00:00',
                `checkout_time` TIME DEFAULT '12:00:00',
                `wifi_name`     VARCHAR(100) DEFAULT NULL,
                `wifi_pass`     VARCHAR(100) DEFAULT NULL,
                `stars`         TINYINT UNSIGNED DEFAULT 5,
                `settings`      JSON DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_hotel_tenant` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `hotel_events` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id`   INT UNSIGNED NOT NULL,
                `title`       VARCHAR(255) NOT NULL,
                `title_en`    VARCHAR(255) DEFAULT NULL,
                `description` TEXT DEFAULT NULL,
                `location`    VARCHAR(255) DEFAULT NULL,
                `hall_name`   VARCHAR(100) DEFAULT NULL,
                `floor`       VARCHAR(20) DEFAULT NULL,
                `start_at`    DATETIME NOT NULL,
                `end_at`      DATETIME DEFAULT NULL,
                `organizer`   VARCHAR(255) DEFAULT NULL,
                `capacity`    INT UNSIGNED DEFAULT NULL,
                `type`        ENUM('conference','wedding','seminar','party','exhibition','other') DEFAULT 'conference',
                `image`       VARCHAR(500) DEFAULT NULL,
                `color`       VARCHAR(7) DEFAULT '#d4af37',
                `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
                `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_hotel_events_tenant` (`tenant_id`),
                KEY `idx_hotel_events_date`   (`start_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `hotel_amenities` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id`   INT UNSIGNED NOT NULL,
                `name`        VARCHAR(255) NOT NULL,
                `name_en`     VARCHAR(255) DEFAULT NULL,
                `description` TEXT DEFAULT NULL,
                `icon`        VARCHAR(100) DEFAULT 'fas fa-star',
                `floor`       VARCHAR(20) DEFAULT NULL,
                `hours`       VARCHAR(100) DEFAULT NULL,
                `phone`       VARCHAR(20) DEFAULT NULL,
                `sort_order`  SMALLINT UNSIGNED DEFAULT 0,
                `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                KEY `idx_hotel_amenities_tenant` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `hotel_room_service` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id`   INT UNSIGNED NOT NULL,
                `category`    VARCHAR(100) NOT NULL,
                `category_en` VARCHAR(100) DEFAULT NULL,
                `name`        VARCHAR(255) NOT NULL,
                `name_en`     VARCHAR(255) DEFAULT NULL,
                `description` TEXT DEFAULT NULL,
                `price`       DECIMAL(12,2) NOT NULL DEFAULT 0,
                `currency`    VARCHAR(3) DEFAULT 'IRR',
                `image`       VARCHAR(500) DEFAULT NULL,
                `is_available` TINYINT(1) NOT NULL DEFAULT 1,
                `available_from` TIME DEFAULT NULL,
                `available_to`   TIME DEFAULT NULL,
                `sort_order`  SMALLINT UNSIGNED DEFAULT 0,
                `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                KEY `idx_hotel_rs_tenant` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `hotel_attractions` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id`   INT UNSIGNED NOT NULL,
                `name`        VARCHAR(255) NOT NULL,
                `name_en`     VARCHAR(255) DEFAULT NULL,
                `description` TEXT DEFAULT NULL,
                `category`    VARCHAR(100) DEFAULT NULL,
                `distance`    VARCHAR(50) DEFAULT NULL,
                `image`       VARCHAR(500) DEFAULT NULL,
                `map_url`     VARCHAR(1000) DEFAULT NULL,
                `sort_order`  SMALLINT UNSIGNED DEFAULT 0,
                `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                KEY `idx_hotel_attr_tenant` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "INSERT IGNORE INTO `hotel_amenities` (`tenant_id`,`name`,`name_en`,`icon`,`floor`,`hours`,`sort_order`) VALUES
                (1,'رستوران اصلی','Main Restaurant','fas fa-utensils','طبقه همکف','07:00 - 23:00',1),
                (1,'استخر','Swimming Pool','fas fa-person-swimming','طبقه پنجم','06:00 - 22:00',2),
                (1,'سالن ورزشی','Fitness Center','fas fa-dumbbell','طبقه زیرزمین','06:00 - 22:00',3),
                (1,'اسپا','Spa & Wellness','fas fa-spa','طبقه پنجم','09:00 - 21:00',4),
                (1,'تالار کنفرانس','Conference Hall','fas fa-microphone','طبقه اول','24 ساعت',5),
                (1,'کافه بام','Rooftop Cafe','fas fa-mug-hot','طبقه دوازدهم','08:00 - 24:00',6),
                (1,'پارکینگ','Parking','fas fa-parking','زیرزمین','24 ساعت',7),
                (1,'دسترسی به اینترنت','Free WiFi','fas fa-wifi','همه طبقات','24 ساعت',8)"
        ];
    }

    public function getDashboardStats(): array
    {
        return [
            'events_today' => (int)$this->db->value("SELECT COUNT(*) FROM hotel_events WHERE tenant_id=? AND DATE(start_at)=CURDATE() AND is_active=1", [$this->tenantId]),
            'amenities'    => (int)$this->db->value("SELECT COUNT(*) FROM hotel_amenities WHERE tenant_id=? AND is_active=1", [$this->tenantId]),
            'rs_items'     => (int)$this->db->value("SELECT COUNT(*) FROM hotel_room_service WHERE tenant_id=? AND is_active=1", [$this->tenantId]),
        ];
    }

    public function renderPlayerWidget(string $zoneType, array $settings = []): string
    {
        return match($zoneType) {
            'hotel_welcome'     => $this->renderWelcome($settings),
            'hotel_room_service'=> $this->renderRoomService($settings),
            'hotel_events'      => $this->renderEvents($settings),
            'hotel_amenities'   => $this->renderAmenities($settings),
            'hotel_directory'   => $this->renderDirectory($settings),
            'hotel_checkin'     => $this->renderCheckin($settings),
            'hotel_attractions' => $this->renderAttractions($settings),
            'hotel_promo'       => $this->renderPromo($settings),
            default             => '<div style="color:#f87171;padding:20px;">زون نامعتبر: '.$zoneType.'</div>',
        };
    }

    private function renderWelcome(array $s): string
    {
        $hotelName  = $s['hotel_name']  ?? 'هتل گرند';
        $slogan     = $s['slogan']      ?? 'خوش آمدید';
        $bgColor    = $s['bg_color']    ?? '#070710';
        $showClock  = !isset($s['show_clock'])   || $s['show_clock'];
        $showWeather= !isset($s['show_weather']) || $s['show_weather'];

        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:__VAR_BGCOLOR__;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:'Segoe UI',Tahoma,sans-serif;direction:rtl;position:relative;overflow:hidden;">

  <!-- Decorative background -->
  <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 30% 50%,rgba(212,175,55,0.12) 0%,transparent 60%),radial-gradient(ellipse at 70% 50%,rgba(212,175,55,0.06) 0%,transparent 60%);"></div>
  <div style="position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,#d4af37,transparent);"></div>
  <div style="position:absolute;bottom:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,#d4af37,transparent);"></div>

  <!-- Stars -->
  <div style="position:relative;display:flex;gap:6px;margin-bottom:24px;">
    <i class="fas fa-star" style="color:#d4af37;font-size:18px;"></i>
    <i class="fas fa-star" style="color:#d4af37;font-size:18px;"></i>
    <i class="fas fa-star" style="color:#d4af37;font-size:18px;"></i>
    <i class="fas fa-star" style="color:#d4af37;font-size:18px;"></i>
    <i class="fas fa-star" style="color:#d4af37;font-size:18px;"></i>
  </div>

  <!-- Hotel Name -->
  <div style="position:relative;text-align:center;margin-bottom:20px;">
    <h1 style="font-size:clamp(36px,6vw,80px);font-weight:800;color:#fff;margin:0;letter-spacing:3px;text-shadow:0 0 40px rgba(212,175,55,0.3);">__VAR_HOTELNAME__</h1>
    <div style="width:120px;height:2px;background:linear-gradient(90deg,transparent,#d4af37,transparent);margin:16px auto;"></div>
    <p style="font-size:clamp(18px,2.5vw,32px);color:#d4af37;margin:0;font-weight:300;letter-spacing:8px;text-transform:uppercase;">__VAR_SLOGAN__</p>
  </div>

  <!-- Clock & Date -->
  {$this->hotelClockHTML($showClock)}

  <!-- Weather -->
  {$this->hotelWeatherHTML($showWeather)}

  <!-- WiFi info -->
  <div style="position:absolute;bottom:32px;right:40px;background:rgba(255,255,255,0.05);border:1px solid rgba(212,175,55,0.2);border-radius:12px;padding:12px 20px;display:flex;align-items:center;gap:10px;" id="hotel-wifi">
    <i class="fas fa-wifi" style="color:#d4af37;font-size:18px;"></i>
    <div><div style="font-size:11px;color:#94a3b8;">Wi-Fi رایگان</div><div id="hotel-wifi-name" style="color:#fff;font-size:14px;font-weight:600;">در حال بارگذاری...</div></div>
  </div>
</div>

<script>
(function() {
  function updateTime() {
    const now = new Date();
    const ce = document.getElementById('hotel-clock');
    const de = document.getElementById('hotel-date');
    if (ce) ce.textContent = now.toLocaleTimeString('fa-IR', {hour:"2-digit",minute:"2-digit",second:"2-digit"});
    if (de) de.textContent = now.toLocaleDateString('fa-IR', {weekday:"long",year:"numeric",month:"long",day:"numeric"});
  }
  setInterval(updateTime, 1000); updateTime();

  // Load hotel wifi info
  fetch('/api/v1/hotel/info').then(r=>r.json()).then(d => {
    if (d.data?.wifi_name) {
      document.getElementById('hotel-wifi-name').textContent = d.data.wifi_name + (d.data.wifi_pass ? '  ·  ' + d.data.wifi_pass : '');
    }
  }).catch(()=>{});

  // Load weather
  const city = document.getElementById('hotel-weather-city');
  if (city) {
    fetch('/api/v1/hotel/weather').then(r=>r.json()).then(d=>{
      if (d.data) {
        document.getElementById('hotel-weather-temp').textContent = Math.round(d.data.temp) + '°';
        city.textContent = d.data.city;
        document.getElementById('hotel-weather-desc').textContent = d.data.description;
      }
    }).catch(()=>{});
  }
})();
</script>
HTML;
        return str_replace(
            ['__VAR_BGCOLOR__', '__VAR_HOTELNAME__', '__VAR_SLOGAN__'],
            [$bgColor, $hotelName, $slogan],
            $__tpl
        );

        return str_replace(
            ['__VAR_BGCOLOR__', '__VAR_HOTELNAME__', '__VAR_SLOGAN__'],
            [$bgColor, $hotelName, $slogan],
            $__tpl
        );
    }

    private function hotelClockHTML(bool $show): string
    {
        if (!$show) return '';
        $__tpl = <<<'HTML'
<div style="text-align:center;margin:20px 0;">
  <div id="hotel-clock" style="font-size:clamp(48px,8vw,100px);font-weight:700;color:#fff;font-family:monospace;letter-spacing:4px;text-shadow:0 0 30px rgba(212,175,55,0.2);">00:00:00</div>
  <div id="hotel-date"  style="font-size:clamp(14px,2vw,22px);color:#94a3b8;margin-top:6px;"></div>
</div>
HTML;
        return $__tpl;

        return $__tpl;
    }

    private function hotelWeatherHTML(bool $show): string
    {
        if (!$show) return '';
        $__tpl = <<<'HTML'
<div style="display:flex;align-items:center;gap:16px;background:rgba(255,255,255,0.04);border:1px solid rgba(212,175,55,0.15);border-radius:16px;padding:14px 28px;margin-top:16px;">
  <i class="fas fa-cloud-sun" style="color:#d4af37;font-size:28px;"></i>
  <div><div id="hotel-weather-city" style="font-size:12px;color:#64748b;">آب‌وهوا</div><div id="hotel-weather-temp" style="font-size:28px;font-weight:700;color:#fff;">—°</div></div>
  <div id="hotel-weather-desc" style="font-size:14px;color:#94a3b8;border-right:1px solid rgba(255,255,255,0.1);padding-right:16px;margin-right:4px;">—</div>
</div>
HTML;
        return $__tpl;

        return $__tpl;
    }

    private function renderEvents(array $s): string
    {
        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#06060f;font-family:'Segoe UI',sans-serif;direction:rtl;display:flex;flex-direction:column;">
  <div style="background:linear-gradient(135deg,#d4af37,#b8960c);padding:20px 32px;display:flex;align-items:center;gap:14px;">
    <i class="fas fa-calendar-star" style="font-size:24px;color:#fff;"></i>
    <div>
      <div style="font-size:22px;font-weight:800;color:#fff;">رویدادها و کنفرانس‌ها</div>
      <div style="font-size:12px;color:rgba(255,255,255,0.7);">برنامه امروز و فردا</div>
    </div>
    <div style="margin-right:auto;text-align:left;">
      <div id="hotel-ev-clock" style="font-size:24px;font-weight:700;color:#fff;font-family:monospace;"></div>
    </div>
  </div>
  <div id="hotel-events-list" style="flex:1;overflow-y:auto;padding:16px 24px;scrollbar-width:none;">
    <div style="text-align:center;padding:40px;color:#475569;">در حال بارگذاری...</div>
  </div>
</div>
<script>
(function() {
  setInterval(()=>{ const e=document.getElementById('hotel-ev-clock'); if(e) e.textContent=new Date().toLocaleTimeString('fa-IR',{hour:"2-digit",minute:"2-digit"}); },1000);
  async function load() {
    const r = await fetch('/api/v1/hotel/events');
    const d = await r.json();
    const events = d.data || [];
    const el = document.getElementById('hotel-events-list');
    if (!events.length) { el.innerHTML = '<div style="text-align:center;padding:60px;color:#475569;"><i class="fas fa-calendar-xmark" style="font-size:48px;opacity:0.3;display:block;margin-bottom:12px;"></i>رویدادی برنامه‌ریزی نشده</div>'; return; }
    const typeIcons = {conference:"fas fa-microphone",wedding:"fas fa-rings-wedding",seminar:"fas fa-chalkboard-teacher",party:"fas fa-champagne-glasses",exhibition:"fas fa-images",other:"fas fa-calendar"};
    el.innerHTML = events.map(ev => {
      const start = new Date(ev.start_at);
      const end   = ev.end_at ? new Date(ev.end_at) : null;
      const timeStr = start.toLocaleTimeString('fa-IR',{hour:"2-digit",minute:"2-digit"}) + (end ? ' — '+end.toLocaleTimeString('fa-IR',{hour:"2-digit",minute:"2-digit"}) : '');
      const dateStr = start.toLocaleDateString('fa-IR',{month:"long",day:"numeric"});
      const isToday = new Date().toDateString() === start.toDateString();
      const icon    = typeIcons[ev.type] || 'fas fa-calendar';
      return \`<div style="display:flex;gap:16px;padding:18px;margin-bottom:12px;border-radius:16px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);\\${isToday?'border-color:'+ev.color+'44;':''}">
        <div style="width:56px;height:56px;border-radius:14px;background:\${ev.color}22;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid \${ev.color}44;">
          <i class="\${icon}" style="color:\${ev.color};font-size:22px;"></i>
        </div>
        <div style="flex:1;">
          <div style="font-size:18px;font-weight:700;color:#fff;">\${ev.title}</div>
          \${ev.description ? \`<div style="font-size:13px;color:#94a3b8;margin-top:4px;">\${ev.description}</div>\` : ''}
          <div style="display:flex;gap:16px;margin-top:8px;font-size:12px;">
            <span style="color:#64748b;"><i class="fas fa-clock ml-1"></i>\${timeStr}</span>
            <span style="color:#64748b;"><i class="fas fa-location-dot ml-1"></i>\\${ev.hall_name||ev.location||'—'} \${ev.floor?'· '+ev.floor:''}</span>
            \${ev.capacity ? \`<span style="color:#64748b;"><i class="fas fa-users ml-1"></i>\${ev.capacity} نفر</span>\` : ''}
          </div>
          \${isToday ? \`<div style="display:inline-block;margin-top:6px;background:\${ev.color}22;color:\${ev.color};border:1px solid \${ev.color}44;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">امروز</div>\` : \`<div style="display:inline-block;margin-top:6px;background:rgba(100,116,139,0.15);color:#64748b;border:1px solid rgba(100,116,139,0.2);padding:2px 10px;border-radius:20px;font-size:11px;">\${dateStr}</div>\`}
        </div>
      </div>\`;
    }).join('');
  }
  load(); setInterval(load, 60000);
})();
</script>
HTML;
        return $__tpl;

        return $__tpl;
    }

    private function renderAmenities(array $s): string
    {
        $layout = $s['layout'] ?? 'grid';
        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#06060f;font-family:'Segoe UI',sans-serif;direction:rtl;overflow:hidden;">
  <div style="background:linear-gradient(135deg,#d4af37,#b8960c);padding:18px 32px;display:flex;align-items:center;gap:12px;">
    <i class="fas fa-spa" style="font-size:22px;color:#fff;"></i>
    <div style="font-size:20px;font-weight:800;color:#fff;">امکانات و خدمات هتل</div>
    <div style="font-size:13px;color:rgba(255,255,255,0.7);margin-right:8px;">Hotel Amenities & Services</div>
  </div>
  <div id="amenities-grid" style="padding:24px;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;overflow-y:auto;max-height:calc(100% - 70px);">
    <div style="color:#475569;text-align:center;grid-column:1/-1;padding:40px;">در حال بارگذاری...</div>
  </div>
</div>
<script>
(async function() {
  const r = await fetch('/api/v1/hotel/amenities');
  const d = await r.json();
  const items = d.data || [];
  const grid = document.getElementById('amenities-grid');
  if (!items.length) { grid.innerHTML = '<div style="color:#475569;text-align:center;padding:40px;">امکاناتی ثبت نشده</div>'; return; }
  const colors = ['#d4af37','#0ea5e9','#22c55e','#a855f7','#f97316','#ec4899','#14b8a6','#f59e0b'];
  grid.innerHTML = items.map((a,i) => {
    const c = colors[i % colors.length];
    return \`<div style="background:rgba(255,255,255,0.03);border:1px solid \${c}22;border-radius:16px;padding:20px;display:flex;gap:16px;align-items:flex-start;transition:border-color 0.3s;">
      <div style="width:52px;height:52px;border-radius:14px;background:\${c}15;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid \${c}33;">
        <i class="\\${a.icon||'fas fa-star'}" style="color:\${c};font-size:22px;"></i>
      </div>
      <div>
        <div style="font-size:17px;font-weight:700;color:#fff;">\${a.name}</div>
        \${a.name_en ? \`<div style="font-size:12px;color:#64748b;">\${a.name_en}</div>\` : ''}
        \${a.floor ? \`<div style="font-size:12px;color:#94a3b8;margin-top:6px;"><i class="fas fa-stairs ml-1" style="color:\${c};"></i>\${a.floor}</div>\` : ''}
        \${a.hours ? \`<div style="font-size:12px;color:#94a3b8;margin-top:3px;"><i class="fas fa-clock ml-1" style="color:\${c};"></i>\${a.hours}</div>\` : ''}
        \${a.phone ? \`<div style="font-size:12px;color:\${c};margin-top:3px;font-weight:600;"><i class="fas fa-phone ml-1"></i>\${a.phone}</div>\` : ''}
      </div>
    </div>\`;
  }).join('');
})();
</script>
HTML;
        return $__tpl;

        return $__tpl;
    }

    private function renderCheckin(array $s): string
    {
        $ci = $s['checkin_time'] ?? '14:00';
        $co = $s['checkout_time'] ?? '12:00';
        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#06060f;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:'Segoe UI',sans-serif;direction:rtl;gap:32px;">
  <div style="text-align:center;">
    <div style="font-size:48px;font-weight:900;color:#d4af37;margin-bottom:8px;">اطلاعات ورود و خروج</div>
    <div style="font-size:18px;color:#64748b;letter-spacing:2px;">CHECK-IN & CHECK-OUT</div>
  </div>
  <div style="display:flex;gap:40px;">
    <div style="background:rgba(34,197,94,0.08);border:2px solid rgba(34,197,94,0.3);border-radius:24px;padding:32px 48px;text-align:center;">
      <i class="fas fa-right-to-bracket" style="font-size:40px;color:#22c55e;margin-bottom:16px;display:block;"></i>
      <div style="font-size:16px;color:#94a3b8;margin-bottom:8px;">ورود · Check-in</div>
      <div style="font-size:56px;font-weight:900;color:#22c55e;font-family:monospace;">__VAR_CI__</div>
    </div>
    <div style="background:rgba(239,68,68,0.08);border:2px solid rgba(239,68,68,0.3);border-radius:24px;padding:32px 48px;text-align:center;">
      <i class="fas fa-right-from-bracket" style="font-size:40px;color:#ef4444;margin-bottom:16px;display:block;"></i>
      <div style="font-size:16px;color:#94a3b8;margin-bottom:8px;">خروج · Check-out</div>
      <div style="font-size:56px;font-weight:900;color:#ef4444;font-family:monospace;">__VAR_CO__</div>
    </div>
  </div>
  <div style="display:flex;gap:24px;flex-wrap:wrap;justify-content:center;">
    <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:16px 24px;text-align:center;">
      <i class="fas fa-phone" style="color:#d4af37;display:block;font-size:20px;margin-bottom:8px;"></i>
      <div style="font-size:12px;color:#64748b;">پذیرش</div>
      <div id="hotel-ci-phone" style="font-size:18px;color:#fff;font-weight:700;">0</div>
    </div>
    <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:16px 24px;text-align:center;">
      <i class="fas fa-wifi" style="color:#d4af37;display:block;font-size:20px;margin-bottom:8px;"></i>
      <div style="font-size:12px;color:#64748b;">Wi-Fi</div>
      <div id="hotel-ci-wifi" style="font-size:16px;color:#fff;font-weight:600;">—</div>
    </div>
    <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:16px 24px;text-align:center;">
      <i class="fas fa-concierge-bell" style="color:#d4af37;display:block;font-size:20px;margin-bottom:8px;"></i>
      <div style="font-size:12px;color:#64748b;">سرویس اتاق</div>
      <div style="font-size:18px;color:#fff;font-weight:700;">9</div>
    </div>
  </div>
</div>
<script>
fetch('/api/v1/hotel/info').then(r=>r.json()).then(d=>{
  if (!d.data) return;
  const el = document.getElementById('hotel-ci-phone'); if(el) el.textContent = d.data.phone||'0';
  const wf = document.getElementById('hotel-ci-wifi');  if(wf) wf.textContent = (d.data.wifi_name||'—') + (d.data.wifi_pass?' | '+d.data.wifi_pass:'');
}).catch(()=>{});
</script>
HTML;
        return str_replace(
            ['__VAR_CI__', '__VAR_CO__'],
            [$ci, $co],
            $__tpl
        );

        return str_replace(
            ['__VAR_CI__', '__VAR_CO__'],
            [$ci, $co],
            $__tpl
        );
    }

    private function renderDirectory(array $s): string
    {
        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#06060f;font-family:'Segoe UI',sans-serif;direction:rtl;display:flex;flex-direction:column;">
  <div style="background:linear-gradient(135deg,#d4af37,#b8960c);padding:20px 24px;text-align:center;">
    <div style="font-size:24px;font-weight:900;color:#fff;">راهنمای هتل</div>
    <div style="font-size:13px;color:rgba(255,255,255,0.7);margin-top:4px;">Hotel Directory</div>
  </div>
  <div id="hotel-dir" style="flex:1;overflow-y:auto;padding:12px;scrollbar-width:none;"></div>
</div>
<script>
(async function() {
  const r = await fetch('/api/v1/hotel/amenities');
  const d = await r.json();
  const items = d.data || [];
  const el = document.getElementById('hotel-dir');
  const colors = ['#d4af37','#0ea5e9','#22c55e','#a855f7','#f97316','#ec4899'];
  el.innerHTML = items.map((a,i) => \`
    <div style="display:flex;align-items:center;gap:14px;padding:14px 16px;margin-bottom:6px;border-radius:12px;background:rgba(255,255,255,0.03);border-right:3px solid \${colors[i%colors.length]};">
      <i class="\\${a.icon||'fas fa-circle'}" style="color:\${colors[i%colors.length]};font-size:20px;width:24px;text-align:center;"></i>
      <div style="flex:1;"><div style="font-size:16px;font-weight:700;color:#fff;">\${a.name}</div>\${a.floor?\`<div style="font-size:12px;color:#64748b;">\${a.floor}</div>\`:''}</div>
      \${a.hours?\`<div style="font-size:12px;color:#94a3b8;text-align:left;">\${a.hours}</div>\`:''}
    </div>
  \`).join('');
})();
</script>
HTML;
        return $__tpl;

        return $__tpl;
    }

    private function renderRoomService(array $s): string { return '<div style="color:#fff;padding:20px;background:#06060f;width:100%;height:100%;direction:rtl;"><div style="font-size:24px;font-weight:700;color:#d4af37;margin-bottom:16px;"><i class="fas fa-concierge-bell ml-2"></i>منوی سرویس اتاق</div><div id="rs-list" style="color:#94a3b8;">در حال بارگذاری...</div></div><script>(async function(){const r=await fetch("/api/v1/hotel/room-service");const d=await r.json();const items=d.data||[];document.getElementById("rs-list").innerHTML=items.map(i=>`<div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.05);"><span style="color:#e2e8f0;">\${i.name}</span><span style="color:#d4af37;font-weight:700;">\${Number(i.price).toLocaleString()} تومان</span></div>`).join("")||"آیتمی ثبت نشده";})();</script>'; }
    private function renderAttractions(array $s): string { return '<div style="color:#fff;padding:20px;background:#06060f;width:100%;height:100%;direction:rtl;font-family:Segoe UI,sans-serif;"><div style="font-size:24px;font-weight:700;color:#d4af37;margin-bottom:16px;"><i class="fas fa-map-location-dot ml-2"></i>جاذبه‌های محلی</div><div id="attr-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">در حال بارگذاری...</div></div><script>(async function(){const r=await fetch("/api/v1/hotel/attractions");const d=await r.json();const items=d.data||[];const el=document.getElementById("attr-list");el.innerHTML=items.map(a=>`<div style="background:rgba(255,255,255,0.04);border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,0.07);">${a.image?`<img src="\${a.image}" style="width:100%;height:140px;object-fit:cover;">`:`<div style="width:100%;height:140px;background:#1e2030;display:flex;align-items:center;justify-content:center;"><i class="fas fa-image" style="color:#475569;font-size:32px;"></i></div>`}<div style="padding:14px;"><div style="font-size:15px;font-weight:700;color:#fff;">${a.name}</div>${a.distance?`<div style="font-size:12px;color:#d4af37;margin-top:4px;"><i class="fas fa-location-dot ml-1"></i>${a.distance}</div>`:""}</div></div>`).join("")||"<div style=\"color:#475569\">جاذبه‌ای ثبت نشده</div>"})();</script>'; }
    private function renderPromo(array $s): string { return '<div style="width:100%;height:100%;background:linear-gradient(135deg,#06060f,#0a0a1e);display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:Segoe UI,sans-serif;direction:rtl;"><i class="fas fa-percent" style="font-size:80px;color:#d4af37;opacity:0.3;margin-bottom:24px;"></i><div style="font-size:36px;font-weight:800;color:#fff;">پیشنهادات ویژه</div><div style="font-size:18px;color:#d4af37;margin-top:8px;">Special Offers</div></div>'; }
}
