<?php
declare(strict_types=1);

namespace App\Modules\FIDS;

use App\Modules\Core\BaseModule;
use App\Services\AirportIrFetcher;

/**
 * FIDS — Flight Information Display System
 * پروازها، پایانه‌ها، دروازه‌ها، وضعیت‌های پرواز
 */
class FIDSModule extends BaseModule
{
    public function id(): string          { return 'fids'; }
    public function name(): string        { return 'سامانه اطلاع‌رسانی پرواز (FIDS)'; }
    public function nameEn(): string      { return 'Flight Information Display System'; }
    public function description(): string { return 'نمایش اطلاعات پروازهای ورودی، خروجی، وضعیت، دروازه و شماره پرواز در فرودگاه‌ها و هتل‌ها'; }
    public function version(): string     { return '1.2.0'; }
    public function icon(): string        { return 'fas fa-plane'; }
    public function color(): string       { return '#0ea5e9'; }
    public function category(): string    { return 'transport'; }
    public function hasScheduler(): bool  { return true; }

    public function zoneTypes(): array
    {
        // Build airport options list for settings dropdown
        $airportOptions = [];
        foreach (AirportIrFetcher::AIRPORTS as $id => $info) {
            $airportOptions[(string)$id] = $info['name'];
        }

        return [
            // ── LIVE zone — data from fids.airport.ir ─────────────────────
            [
                'id'          => 'fids_live_board',
                'label'       => 'تابلو زنده فرودگاه (airport.ir)',
                'label_en'    => 'Live Airport Board (airport.ir)',
                'icon'        => 'fas fa-satellite-dish',
                'description' => 'نمایش زنده پروازها از سامانه فرودگاه‌های کشور — fids.airport.ir',
                'defaultSize' => ['w' => 1920, 'h' => 1080],
                'settings'    => [
                    [
                        'key'     => 'airport_id',
                        'label'   => 'فرودگاه / شهر',
                        'type'    => 'select',
                        'options' => $airportOptions,
                        'default' => '2',
                    ],
                    [
                        'key'     => 'direction',
                        'label'   => 'نوع پرواز',
                        'type'    => 'select',
                        'options' => ['arrival'=>'ورودی (Arrivals)', 'departure'=>'خروجی (Departures)', 'all'=>'هر دو'],
                        'default' => 'departure',
                    ],
                    [
                        'key'     => 'route_type',
                        'label'   => 'مسیر پرواز',
                        'type'    => 'select',
                        'options' => ['domestic'=>'داخلی', 'international'=>'خارجی', 'all'=>'داخلی + خارجی'],
                        'default' => 'domestic',
                    ],
                    ['key'=>'rows',         'label'=>'تعداد سطر',            'type'=>'number', 'default'=>14],
                    ['key'=>'color_scheme', 'label'=>'تم رنگی',             'type'=>'select',
                     'options'=>['dark'=>'تاریک','airport'=>'فرودگاهی','navy'=>'آبی تیره'], 'default'=>'dark'],
                    ['key'=>'lang',         'label'=>'زبان',                  'type'=>'select',
                     'options'=>['fa'=>'فارسی','en'=>'English','both'=>'دو‌زبانه'], 'default'=>'fa'],
                    ['key'=>'refresh_sec',  'label'=>'بروزرسانی (ثانیه)',    'type'=>'number', 'default'=>60],
                    ['key'=>'show_route_badge', 'label'=>'نشان داخلی/خارجی','type'=>'bool',   'default'=>true],
                ],
            ],
            [
                'id'          => 'fids_departures',
                'label'       => 'پروازهای عزیمت',
                'label_en'    => 'Departures',
                'icon'        => 'fas fa-plane-departure',
                'description' => 'جدول پروازهای خروجی با وضعیت زنده',
                'defaultSize' => ['w' => 1920, 'h' => 1080],
                'preview'     => '/assets/modules/fids/preview_departures.png',
                'settings'    => [
                    ['key'=>'rows',          'label'=>'تعداد سطر',       'type'=>'number', 'default'=>12],
                    ['key'=>'show_logo',     'label'=>'نشان ایرلاین',    'type'=>'bool',   'default'=>true],
                    ['key'=>'lang',          'label'=>'زبان',             'type'=>'select', 'options'=>['fa'=>'فارسی','en'=>'English','both'=>'دو‌زبانه'], 'default'=>'both'],
                    ['key'=>'auto_scroll',   'label'=>'اسکرول خودکار',   'type'=>'bool',   'default'=>true],
                    ['key'=>'color_scheme',  'label'=>'تم رنگی',         'type'=>'select', 'options'=>['dark'=>'تاریک','airport'=>'فرودگاهی','navy'=>'آبی تیره'], 'default'=>'dark'],
                    ['key'=>'refresh_sec',   'label'=>'بروزرسانی (ثانیه)', 'type'=>'number', 'default'=>30],
                ],
            ],
            [
                'id'          => 'fids_arrivals',
                'label'       => 'پروازهای ورود',
                'label_en'    => 'Arrivals',
                'icon'        => 'fas fa-plane-arrival',
                'description' => 'جدول پروازهای ورودی',
                'defaultSize' => ['w' => 1920, 'h' => 1080],
                'settings'    => [
                    ['key'=>'rows',         'label'=>'تعداد سطر',    'type'=>'number', 'default'=>12],
                    ['key'=>'show_logo',    'label'=>'نشان ایرلاین', 'type'=>'bool',   'default'=>true],
                    ['key'=>'lang',         'label'=>'زبان',          'type'=>'select', 'options'=>['fa'=>'فارسی','en'=>'English','both'=>'دو‌زبانه'], 'default'=>'both'],
                    ['key'=>'color_scheme', 'label'=>'تم رنگی',      'type'=>'select', 'options'=>['dark'=>'تاریک','airport'=>'فرودگاهی'], 'default'=>'dark'],
                ],
            ],
            [
                'id'          => 'fids_gate',
                'label'       => 'اطلاعات دروازه (Gate)',
                'label_en'    => 'Gate Display',
                'icon'        => 'fas fa-door-open',
                'description' => 'نمایش اطلاعات یک دروازه خاص در فرودگاه',
                'defaultSize' => ['w' => 1920, 'h' => 1080],
                'settings'    => [
                    ['key'=>'gate_id',    'label'=>'شماره دروازه', 'type'=>'text',   'default'=>'A1'],
                    ['key'=>'show_map',   'label'=>'نقشه ترمینال', 'type'=>'bool',   'default'=>false],
                    ['key'=>'lang',       'label'=>'زبان',          'type'=>'select', 'options'=>['fa'=>'فارسی','en'=>'English','both'=>'دو‌زبانه'], 'default'=>'both'],
                ],
            ],
            [
                'id'          => 'fids_splitflap',
                'label'       => 'تابلو کلاسیک (Split-Flap)',
                'label_en'    => 'Split-Flap Board',
                'icon'        => 'fas fa-sliders',
                'description' => 'شبیه‌سازی تابلوی ورق‌خور کلاسیک فرودگاهی',
                'defaultSize' => ['w' => 1920, 'h' => 1080],
                'settings'    => [
                    ['key'=>'rows',       'label'=>'تعداد سطر', 'type'=>'number', 'default'=>8],
                    ['key'=>'flip_speed', 'label'=>'سرعت ورق', 'type'=>'number', 'default'=>80],
                ],
            ],
        ];
    }

    public function migrations(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS `fids_flights` (
                `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id`      INT UNSIGNED NOT NULL,
                `flight_number`  VARCHAR(20) NOT NULL,
                `airline_code`   VARCHAR(5) NOT NULL,
                `airline_name`   VARCHAR(100) NOT NULL,
                `airline_name_en` VARCHAR(100) DEFAULT NULL,
                `airline_logo`   VARCHAR(500) DEFAULT NULL,
                `type`           ENUM('departure','arrival') NOT NULL DEFAULT 'departure',
                `origin`         VARCHAR(100) DEFAULT NULL,
                `origin_code`    VARCHAR(5) DEFAULT NULL,
                `destination`    VARCHAR(100) DEFAULT NULL,
                `destination_code` VARCHAR(5) DEFAULT NULL,
                `scheduled_time` DATETIME NOT NULL,
                `estimated_time` DATETIME DEFAULT NULL,
                `actual_time`    DATETIME DEFAULT NULL,
                `terminal`       VARCHAR(20) DEFAULT NULL,
                `gate`           VARCHAR(20) DEFAULT NULL,
                `belt`           VARCHAR(20) DEFAULT NULL,
                `status`         ENUM('scheduled','boarding','departed','arrived','delayed','cancelled','diverted','gate_change') NOT NULL DEFAULT 'scheduled',
                `status_fa`      VARCHAR(50) DEFAULT NULL,
                `delay_minutes`  SMALLINT DEFAULT 0,
                `aircraft_type`  VARCHAR(50) DEFAULT NULL,
                `remarks`        TEXT DEFAULT NULL,
                `remarks_en`     TEXT DEFAULT NULL,
                `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
                `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_fids_tenant` (`tenant_id`),
                KEY `idx_fids_type`   (`type`),
                KEY `idx_fids_time`   (`scheduled_time`),
                KEY `idx_fids_status` (`status`),
                KEY `idx_fids_gate`   (`gate`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `fids_airlines` (
                `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `code`     VARCHAR(5) NOT NULL UNIQUE,
                `name_fa`  VARCHAR(100) NOT NULL,
                `name_en`  VARCHAR(100) NOT NULL,
                `logo_url` VARCHAR(500) DEFAULT NULL,
                `country`  VARCHAR(50) DEFAULT NULL,
                `color`    VARCHAR(7) DEFAULT '#FFFFFF',
                PRIMARY KEY (`id`),
                KEY `idx_airline_code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "INSERT IGNORE INTO `fids_airlines` (`code`,`name_fa`,`name_en`,`color`) VALUES
                ('IR','هواپیمایی ایران ایر','Iran Air','#0B6EA9'),
                ('W5','هواپیمایی ماهان','Mahan Air','#C8102E'),
                ('EP','ایران ایرتور','Iran Airtour','#00A86B'),
                ('B9','معراج ایرلاینز','Meraj Airlines','#1B3A6B'),
                ('QB','قشم ایر','Qeshm Air','#FF6B00'),
                ('I3','آتا ایرلاینز','ATA Airlines','#003DA5'),
                ('TK','ترکیش ایرلاینز','Turkish Airlines','#E81932'),
                ('EK','امارات','Emirates','#D71921'),
                ('QR','قطر ایرویز','Qatar Airways','#5C0632'),
                ('FZ','فلای دبی','Fly Dubai','#FF0000')",
        ];
    }

    public function getDashboardStats(): array
    {
        $today  = date('Y-m-d');
        $active = $this->db->row(
            "SELECT COUNT(*) AS total,
             SUM(type='departure') AS dep,
             SUM(type='arrival') AS arr,
             SUM(status='delayed') AS delayed_count,
             SUM(status='cancelled') AS cancelled_count,
             SUM(status='boarding') AS boarding_count_count
             FROM fids_flights WHERE tenant_id=? AND DATE(scheduled_time)=? AND is_active=1",
            [$this->tenantId, $today]
        );
        return $active ?? [];
    }

    public function renderPlayerWidget(string $zoneType, array $settings = []): string
    {
        if ($zoneType === 'fids_live_board') {
            return $this->renderLiveBoard($settings);
        }

        $tenantId = $this->tenantId;
        $rows     = (int)($settings['rows'] ?? 12);
        $showLogo = (bool)($settings['show_logo'] ?? true);
        $lang     = $settings['lang'] ?? 'both';
        $theme    = $settings['color_scheme'] ?? 'dark';
        $refreshSec = (int)($settings['refresh_sec'] ?? 30);
        $type     = str_contains($zoneType, 'arrival') ? 'arrival' : 'departure';

        if ($zoneType === 'fids_splitflap') {
            return $this->renderSplitFlap($settings);
        }
        if ($zoneType === 'fids_gate') {
            return $this->renderGateDisplay($settings);
        }

        $themes = [
            'dark'     => ['bg'=>'#08080f','header'=>'#0ea5e9','row_odd'=>'#0d1117','row_even'=>'#111827','text'=>'#f1f5f9','sub'=>'#94a3b8'],
            'airport'  => ['bg'=>'#001427','header'=>'#f59e0b','row_odd'=>'#002244','row_even'=>'#001a36','text'=>'#ffffff','sub'=>'#93c5fd'],
            'navy'     => ['bg'=>'#0a0e27','header'=>'#6366f1','row_odd'=>'#0f1535','row_even'=>'#111838','text'=>'#e2e8f0','sub'=>'#818cf8'],
        ];
        $t = $themes[$theme] ?? $themes['dark'];

        // Extract همه variables قبل از heredoc
        $tBg       = $t['bg']       ?? '#080f1e';
        $tHeader   = $t['header']   ?? '#0ea5e9';
        $tText     = $t['text']     ?? '#e2e8f0';
        $tSub      = $t['sub']      ?? '#64748b';
        $tRowOdd   = $t['row_odd']  ?? '#0d1117';
        $tRowEven  = $t['row_even'] ?? '#111827';
        $tBorder   = $t['border']   ?? '#1e3a5f';
        $rowOdd     = $tRowOdd;
        $rowEven    = $tRowEven;
        $showLogoJs = $showLogo ? 'true' : 'false';
        $gridCols   = $this->gridCols($showLogo);
        $langBothHide = ($lang !== 'both') ? 'display:none' : '';
        $logoDisplay  = $showLogo ? 'flex' : 'none';

        $__tpl = <<<'HTML'
<div class="fids-board" style="width:100%;height:100%;background:__VAR_TBG__;font-family:'Segoe UI',sans-serif;overflow:hidden;direction:rtl;" data-type="__VAR_TYPE__" data-rows="__VAR_ROWS__" data-refresh="__VAR_REFRESHSEC__">
  <!-- Header -->
  <div style="background:__VAR_THEADER__22;border-bottom:2px solid __VAR_THEADER__;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:14px;">
      <i class="fas fa-plane-__VAR_TYPE__" style="font-size:28px;color:__VAR_THEADER__;"></i>
      <div>
        <div style="font-size:22px;font-weight:800;color:#fff;">
          {$this->typeLabel($type, $lang)}
        </div>
        <div style="font-size:13px;color:__VAR_TSUB__;">Flight Information Display System</div>
      </div>
    </div>
    <div style="text-align:left;">
      <div id="fids-clock-__VAR_TYPE__" style="font-size:28px;font-weight:700;color:#fff;font-variant-numeric:tabular-nums;font-family:monospace;"></div>
      <div id="fids-date-__VAR_TYPE__"  style="font-size:13px;color:__VAR_TSUB__;"></div>
    </div>
  </div>

  <!-- Column Headers -->
  <div style="display:grid;grid-template-columns:{$this->gridCols($showLogo)};background:__VAR_THEADER__33;padding:8px 24px;gap:8px;font-size:12px;font-weight:700;color:__VAR_THEADER__;letter-spacing:0.5px;text-transform:uppercase;">
    {$this->renderHeaders($type, $lang, $showLogo)}
  </div>

  <!-- Rows -->
  <div id="fids-rows-__VAR_TYPE__" style="flex:1;overflow:hidden;">
    <div style="text-align:center;padding:40px;color:__VAR_TSUB__;font-size:16px;">
      <i class="fas fa-circle-notch fa-spin" style="font-size:32px;margin-bottom:12px;display:block;color:__VAR_THEADER__;"></i>
      در حال بارگذاری اطلاعات پرواز...
    </div>
  </div>

  <!-- Footer ticker -->
  <div style="background:__VAR_THEADER__15;border-top:1px solid __VAR_THEADER__33;padding:8px 24px;display:flex;align-items:center;gap:16px;">
    <span style="color:__VAR_THEADER__;font-size:12px;font-weight:700;white-space:nowrap;"><i class="fas fa-info-circle ml-1"></i> اطلاعیه:</span>
    <div id="fids-ticker-__VAR_TYPE__" style="overflow:hidden;flex:1;">
      <div class="fids-ticker-inner" style="white-space:nowrap;color:__VAR_TSUB__;font-size:13px;animation:fidsScroll 25s linear infinite;">
        به سامانه اطلاع‌رسانی پرواز خوش آمدید · لطفاً اطلاعات پرواز خود را قبل از حرکت بررسی کنید · Welcome to Flight Information Display System
      </div>
    </div>
    <span style="color:__VAR_TSUB__;font-size:11px;white-space:nowrap;">بروزرسانی هر __VAR_REFRESHSEC__ث</span>
  </div>
</div>

<style>
@keyframes fidsScroll { from{transform:translateX(0)} to{transform:translateX(-50%)} }
.fids-row-enter { animation: fidsRowIn 0.4s ease; }
@keyframes fidsRowIn { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
.fids-status-boarding  { color:#22c55e;background:rgba(34,197,94,0.15);  border:1px solid rgba(34,197,94,0.4);  }
.fids-status-delayed   { color:#f59e0b;background:rgba(245,158,11,0.15); border:1px solid rgba(245,158,11,0.4); }
.fids-status-cancelled { color:#ef4444;background:rgba(239,68,68,0.15);  border:1px solid rgba(239,68,68,0.4);  }
.fids-status-departed,.fids-status-arrived { color:#94a3b8;background:rgba(148,163,184,0.1); border:1px solid rgba(148,163,184,0.2); }
.fids-status-scheduled,.fids-status-gate_change { color:#0ea5e9;background:rgba(14,165,233,0.15); border:1px solid rgba(14,165,233,0.3); }
</style>

<script>
(function() {
  const TYPE = '__VAR_TYPE__', ROWS = __VAR_ROWS__, REFRESH = __VAR_REFRESHSEC__000;
  const FIDS_SHOW_LOGO = __VAR_SHOWLOGOJS__;
  const ROW_ODD  = '__VAR_ROWODD__';
  const ROW_EVEN = '__VAR_ROWEVEN__';
  const SHOW_LOGO = FIDS_SHOW_LOGO;
  const LANG = '__VAR_LANG__';
  // colors defined as JS constants below
  const statusMap = {
    'scheduled':'زمان‌بندی','boarding':'سوار شوید','departed':'پرواز کرد',
    'arrived':'فرود آمد','delayed':'تأخیر','cancelled':'لغو شد','diverted':'مسیر تغییر','gate_change':'تغییر دروازه'
  };

  function updateClock() {
    const now = new Date();
    const el  = document.getElementById('fids-clock-' + TYPE);
    const de  = document.getElementById('fids-date-'  + TYPE);
    if (el) el.textContent = now.toLocaleTimeString('fa-IR');
    if (de) de.textContent = now.toLocaleDateString('fa-IR', {weekday:"long",year:"numeric",month:"long",day:"numeric"});
  }
  setInterval(updateClock, 1000); updateClock();

  async function loadFlights() {
    try {
      const r = await fetch('/api/v1/fids/flights?type=' + TYPE + '&limit=' + ROWS);
      const d = await r.json();
      renderRows(d.data || []);
    } catch(e) {
      document.getElementById('fids-rows-' + TYPE).innerHTML =
        '<div style="text-align:center;padding:40px;color:#ef4444;font-size:14px;"><i class="fas fa-exclamation-triangle" style="display:block;margin-bottom:8px;"></i>خطا در بارگذاری اطلاعات</div>';
    }
  }

  function renderRows(flights) {
    const container = document.getElementById('fids-rows-' + TYPE);
    if (!flights.length) {
      container.innerHTML = '<div style="text-align:center;padding:60px;color:#475569;font-size:15px;"><i class="fas fa-plane" style="font-size:48px;opacity:0.2;display:block;margin-bottom:12px;"></i>پروازی ثبت نشده</div>';
      return;
    }
    container.innerHTML = flights.map((f, i) => {
      const sched  = new Date(f.scheduled_time);
      const est    = f.estimated_time ? new Date(f.estimated_time) : null;
      const timeStr = sched.toLocaleTimeString('fa-IR', {hour:"2-digit",minute:"2-digit"});
      const estStr  = est ? est.toLocaleTimeString('fa-IR', {hour:"2-digit",minute:"2-digit"}) : timeStr;
      const delayed = f.delay_minutes > 0;
      const bg = i % 2 === 0 ? ROW_ODD : ROW_EVEN;
      const statusCls = 'fids-status-' + f.status.replace('_','-');
      const dest = LANG === 'en' ? (f.destination || f.origin || '—') :
                   LANG === 'fa' ? (f.destination || f.origin || '—') :
                   (f.destination || f.origin || '—');

      return \`<div class="fids-row-enter" style="display:grid;grid-template-columns:__VAR_GRIDCOLS__;background:\${bg};padding:13px 24px;gap:8px;border-bottom:1px solid rgba(255,255,255,0.04);align-items:center;min-height:62px;transition:background 0.3s;">
        \\${SHOW_LOGO ? `<div style="display:flex;align-items:center;gap:6px;"><img src="\${f.airline_logo||'/assets/modules/fids/airline_default.svg'}" style="height:28px;object-fit:contain;max-width:48px;" onerror="this.src='/assets/modules/fids/airline_default.svg'"><span style="font-size:10px;color:#64748b;\__VAR_LANGBOTHHIDE__">\${f.airline_code}</span></div>\` : ''}
        <div style="font-size:18px;font-weight:800;color:#fff;letter-spacing:1px;font-family:monospace;">\${f.flight_number}</div>
        <div>
          <div style="font-size:15px;font-weight:600;color:#f1f5f9;">\${dest}</div>
          \${LANG==='both' && f.destination_code ? \`<div style="font-size:11px;color:#64748b;font-family:monospace;">\${f.destination_code||f.origin_code||''}</div>\` : ''}
        </div>
        <div>
          <div style="font-size:17px;font-weight:700;color:\${delayed?'#f59e0b':'#fff'};font-family:monospace;">\${delayed?estStr:timeStr}</div>
          \${delayed ? \`<div style="font-size:11px;color:#f59e0b;">تأخیر +\${f.delay_minutes}دقیقه</div>\` : ''}
        </div>
        <div style="font-size:14px;color:#94a3b8;">\${f.terminal||'—'}</div>
        <div style="font-size:18px;font-weight:800;color:#0ea5e9;letter-spacing:1px;">\${f.gate||'—'}</div>
        <div><span class="\${statusCls}" style="padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;">\${statusMap[f.status]||f.status}</span></div>
      </div>\`;
    }).join('');
  }

  loadFlights();
  setInterval(loadFlights, REFRESH);
})();
</script>
HTML;
        return str_replace(
            ['__VAR_TBG__', '__VAR_TYPE__', '__VAR_ROWS__', '__VAR_REFRESHSEC__', '__VAR_THEADER__', '__VAR_TSUB__', '__VAR_SHOWLOGOJS__', '__VAR_ROWODD__', '__VAR_ROWEVEN__', '__VAR_LANG__', '__VAR_GRIDCOLS__', '__VAR_LANGBOTHHIDE__'],
            [$tBg, $type, $rows, $refreshSec, $tHeader, $tSub, $showLogoJs, $rowOdd, $rowEven, $lang, $gridCols, $langBothHide],
            $__tpl
        );
    }

    // ── Live Board renderer (fids.airport.ir data) ──────────────────────────

    private function renderLiveBoard(array $s): string
    {
        $airportId  = (int)($s['airport_id'] ?? 2);
        $direction  = in_array($s['direction'] ?? '', ['arrival','departure','all']) ? ($s['direction'] ?? 'departure') : 'departure';
        $routeType  = in_array($s['route_type'] ?? '', ['domestic','international','all']) ? ($s['route_type'] ?? 'domestic') : 'domestic';
        $rows       = max(5, min(30, (int)($s['rows'] ?? 14)));
        $theme      = $s['color_scheme'] ?? 'dark';
        $lang       = $s['lang'] ?? 'fa';
        $refreshSec = max(30, (int)($s['refresh_sec'] ?? 60));
        $showBadge  = (bool)($s['show_route_badge'] ?? true);

        // Airport info
        $airports   = AirportIrFetcher::AIRPORTS;
        $airport    = $airports[$airportId] ?? ['name' => 'فرودگاه'];
        $airportName = $airport['name'];

        // Theme colours
        $themes = [
            'dark'    => ['bg'=>'#08080f','hdr'=>'#0ea5e9','odd'=>'#0d1117','even'=>'#111827','text'=>'#f1f5f9','sub'=>'#94a3b8','badge'=>'#1e3a5f'],
            'airport' => ['bg'=>'#001427','hdr'=>'#f59e0b','odd'=>'#002244','even'=>'#001a36','text'=>'#ffffff','sub'=>'#93c5fd','badge'=>'#003366'],
            'navy'    => ['bg'=>'#0a0e27','hdr'=>'#6366f1','odd'=>'#0f1535','even'=>'#111838','text'=>'#e2e8f0','sub'=>'#818cf8','badge'=>'#1e2255'],
        ];
        $t  = $themes[$theme] ?? $themes['dark'];
        $tBg    = $t['bg'];
        $tHdr   = $t['hdr'];
        $tOdd   = $t['odd'];
        $tEven  = $t['even'];
        $tText  = $t['text'];
        $tSub   = $t['sub'];
        $tBadge = $t['badge'];

        // Direction label
        $dirLabels = [
            'arrival'   => ['fa' => 'پروازهای ورودی',  'en' => 'ARRIVALS',   'icon' => 'fa-plane-arrival'],
            'departure' => ['fa' => 'پروازهای خروجی', 'en' => 'DEPARTURES', 'icon' => 'fa-plane-departure'],
            'all'       => ['fa' => 'اطلاعات پروازها', 'en' => 'FLIGHTS',    'icon' => 'fa-plane'],
        ];
        $routeLabels = [
            'domestic'      => ['fa' => 'داخلی',         'en' => 'DOMESTIC'],
            'international' => ['fa' => 'خارجی',         'en' => 'INTL'],
            'all'           => ['fa' => 'داخلی + خارجی', 'en' => 'ALL'],
        ];
        $dl = $dirLabels[$direction] ?? $dirLabels['all'];
        $rl = $routeLabels[$routeType] ?? $routeLabels['all'];

        $dirLabelFa  = $dl['fa'];
        $dirLabelEn  = $dl['en'];
        $dirIcon     = $dl['icon'];
        $routeLabelFa = $rl['fa'];
        $routeLabelEn = $rl['en'];

        $showBadgeJs   = $showBadge ? 'true' : 'false';
        $showRouteCol  = ($direction === 'all') ? 'table-cell' : 'none';  // show direction column when "all"

        $apiUrl = '/api/v1/fids/live?airport_id=' . $airportId
                . '&type=' . $direction
                . '&route=' . $routeType
                . '&limit=' . $rows;

        $__tpl = <<<'HTML'
<div id="fids-live-wrap" style="width:100%;height:100%;background:__BG__;font-family:'Segoe UI',Tahoma,sans-serif;direction:rtl;display:flex;flex-direction:column;overflow:hidden;">

  <!-- ─── Header ──────────────────────────────────────────────────────── -->
  <div style="background:linear-gradient(135deg,__HDR__22,__HDR__44);border-bottom:2px solid __HDR__;padding:12px 28px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
    <div style="display:flex;align-items:center;gap:16px;">
      <i class="fas __DIRICON__" style="font-size:32px;color:__HDR__;filter:drop-shadow(0 0 8px __HDR__88);"></i>
      <div>
        <div style="font-size:24px;font-weight:900;color:#fff;line-height:1.1;">__DIRLABELFA__ · <span style="color:__HDR__;font-size:18px;letter-spacing:2px;">__DIRLABELEN__</span></div>
        <div style="font-size:13px;color:__SUB__;margin-top:2px;">__AIRPORTNAME__ &nbsp;·&nbsp; __ROUTELABELFA__</div>
      </div>
    </div>
    <div style="text-align:left;display:flex;flex-direction:column;align-items:flex-end;gap:2px;">
      <div id="fids-live-clock" style="font-size:32px;font-weight:700;color:#fff;font-family:monospace;letter-spacing:2px;"></div>
      <div id="fids-live-date"  style="font-size:12px;color:__SUB__;"></div>
      <div style="font-size:10px;color:__HDR__;opacity:0.7;">بروزرسانی هر __REFRESHSEC__ ثانیه</div>
    </div>
  </div>

  <!-- ─── Column headers ──────────────────────────────────────────────── -->
  <div style="display:grid;grid-template-columns:__GRIDCOLS__;background:__HDR__22;padding:8px 20px;gap:6px;font-size:11px;font-weight:700;color:__HDR__;letter-spacing:0.5px;text-transform:uppercase;border-bottom:1px solid __HDR__44;flex-shrink:0;" id="fids-live-thead">
    <div>ایرلاین</div>
    <div>شماره پرواز</div>
    <div id="fids-place-col">__PLACELABEL__</div>
    <div>زمان برنامه‌ای</div>
    <div>زمان واقعی</div>
    <div style="display:__ROUTECOLDISPLAY__;">نوع</div>
    <div>وضعیت</div>
  </div>

  <!-- ─── Flight rows ──────────────────────────────────────────────────── -->
  <div id="fids-live-rows" style="flex:1;overflow:hidden;position:relative;">
    <div style="display:flex;align-items:center;justify-content:center;height:100%;flex-direction:column;gap:16px;">
      <i class="fas fa-satellite-dish" style="font-size:48px;color:__HDR__;opacity:0.5;animation:fids-pulse 1.5s ease-in-out infinite;"></i>
      <div style="color:__SUB__;font-size:15px;">در حال دریافت اطلاعات از سامانه فرودگاه...</div>
    </div>
  </div>

  <!-- ─── Footer ───────────────────────────────────────────────────────── -->
  <div style="background:__HDR__11;border-top:1px solid __HDR__33;padding:7px 20px;display:flex;align-items:center;gap:12px;flex-shrink:0;">
    <i class="fas fa-circle" style="font-size:8px;color:#22c55e;animation:fids-blink 1s ease-in-out infinite;"></i>
    <span style="font-size:11px;color:__SUB__;">منبع: سامانه اطلاع‌رسانی فرودگاه‌های کشور · fids.airport.ir</span>
    <span id="fids-live-count" style="margin-right:auto;font-size:11px;color:__HDR__;"></span>
    <span id="fids-live-lastup" style="font-size:10px;color:__SUB__;opacity:0.6;"></span>
  </div>
</div>

<style>
@keyframes fids-pulse { 0%,100%{opacity:0.4;transform:scale(1)} 50%{opacity:0.9;transform:scale(1.05)} }
@keyframes fids-blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
@keyframes fids-row-in { from{opacity:0;transform:translateX(10px)} to{opacity:1;transform:translateX(0)} }
.fids-live-row { animation: fids-row-in 0.35s ease forwards; }
.fids-s-boarding  { color:#22c55e;background:rgba(34,197,94,0.15);  border:1px solid rgba(34,197,94,0.4); }
.fids-s-arrived,.fids-s-departed { color:#64748b;background:rgba(100,116,139,0.1); border:1px solid rgba(100,116,139,0.25); }
.fids-s-delayed   { color:#f59e0b;background:rgba(245,158,11,0.15); border:1px solid rgba(245,158,11,0.4); }
.fids-s-cancelled { color:#ef4444;background:rgba(239,68,68,0.15);  border:1px solid rgba(239,68,68,0.4); }
.fids-s-scheduled,.fids-s-default { color:__HDR__;background:rgba(14,165,233,0.1); border:1px solid rgba(14,165,233,0.25); }
.fids-r-int { color:#a78bfa;background:rgba(167,139,250,0.12);border:1px solid rgba(167,139,250,0.3); }
.fids-r-dom { color:#34d399;background:rgba(52,211,153,0.12);border:1px solid rgba(52,211,153,0.3); }
</style>

<script>
(function () {
  const API        = '__APIURL__';
  const REFRESH    = __REFRESHSEC__ * 1000;
  const DIRECTION  = '__DIRECTION__';
  const SHOW_BADGE = __SHOWBADGEJS__;
  const BG_ODD     = '__ODD__';
  const BG_EVEN    = '__EVEN__';
  const HDR_COLOR  = '__HDR__';
  const SHOW_ROUTE_COL = '__ROUTECOLDISPLAY__' !== 'none';
  const LANG       = '__LANG__';

  const GRID_COLS  = SHOW_ROUTE_COL
    ? '120px 110px 1fr 100px 100px 80px 140px'
    : '120px 110px 1fr 100px 100px 140px';

  // Apply grid to header
  document.getElementById('fids-live-thead').style.gridTemplateColumns = GRID_COLS;

  const statusMap = {
    scheduled:'زمان‌بندی', boarding:'سوار شوید', departed:'پرواز کرد',
    arrived:'فرود آمد', delayed:'تأخیر', cancelled:'لغو', diverted:'مسیر تغییر'
  };
  const routeMap = { domestic:'داخلی', international:'خارجی' };

  // ── Clock ──────────────────────────────────────────────────────────────
  function updateClock() {
    const now = new Date();
    const cl  = document.getElementById('fids-live-clock');
    const dt  = document.getElementById('fids-live-date');
    if (cl) cl.textContent = now.toLocaleTimeString('fa-IR', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    if (dt) dt.textContent = now.toLocaleDateString('fa-IR', {weekday:'long',year:'numeric',month:'long',day:'numeric'});
  }
  setInterval(updateClock, 1000);
  updateClock();

  // ── Fetch ──────────────────────────────────────────────────────────────
  async function loadFlights() {
    try {
      const r = await fetch(API + '&_t=' + Date.now());
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const d = await r.json();
      renderRows(d.data || [], d.airport || '');
      const lup = document.getElementById('fids-live-lastup');
      if (lup) lup.textContent = 'آخرین بروزرسانی: ' + new Date().toLocaleTimeString('fa-IR');
    } catch (e) {
      document.getElementById('fids-live-rows').innerHTML =
        '<div style="display:flex;align-items:center;justify-content:center;height:100%;flex-direction:column;gap:12px;">'
        + '<i class="fas fa-exclamation-triangle" style="font-size:40px;color:#ef4444;opacity:0.7;"></i>'
        + '<div style="color:#ef4444;font-size:14px;">خطا در دریافت اطلاعات. بعداً دوباره تلاش می‌شود.</div>'
        + '</div>';
    }
  }

  // ── Render ─────────────────────────────────────────────────────────────
  function renderRows(flights, airport) {
    const cnt = document.getElementById('fids-live-count');
    if (cnt) cnt.textContent = flights.length + ' پرواز';

    const wrap = document.getElementById('fids-live-rows');
    if (!flights.length) {
      wrap.innerHTML =
        '<div style="display:flex;align-items:center;justify-content:center;height:100%;flex-direction:column;gap:16px;opacity:0.5;">'
        + '<i class="fas fa-plane" style="font-size:56px;color:' + HDR_COLOR + ';"></i>'
        + '<div style="color:#475569;font-size:16px;">پروازی ثبت نشده است</div>'
        + '</div>';
      return;
    }

    wrap.innerHTML = flights.map((f, i) => {
      const bg       = i % 2 === 0 ? BG_ODD : BG_EVEN;
      const sCls     = 'fids-s-' + (f.status || 'default').replace('_','-');
      const rCls     = f.route === 'international' ? 'fids-r-int' : 'fids-r-dom';
      const rLabel   = routeMap[f.route] || f.route || '';
      const place    = DIRECTION === 'arrival'   ? (f.origin      || '—') :
                       DIRECTION === 'departure' ? (f.destination || '—') :
                       (f.destination || f.origin || '—');
      const schedT   = f.scheduled_time ? f.scheduled_time.substring(11,16) : '--:--';
      const actT     = f.actual_time    ? f.actual_time.substring(11,16)    : '';
      const delayed  = f.status === 'delayed';
      const sLabel   = statusMap[f.status] || f.status_fa || f.status || '';
      const routeCell = SHOW_ROUTE_COL
        ? \`<div><span class="\${rCls}" style="padding:3px 8px;border-radius:12px;font-size:11px;font-weight:700;">\${rLabel}</span></div>\`
        : '';

      return \`<div class="fids-live-row" style="display:grid;grid-template-columns:\${GRID_COLS};background:\${bg};padding:12px 20px;gap:6px;border-bottom:1px solid rgba(255,255,255,0.03);align-items:center;min-height:58px;transition:background 0.2s;" data-idx="\${i}">
        <div style="font-size:13px;color:#94a3b8;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="\${f.airline_name||''}">\${(f.airline_name||f.airline_code||'—').substring(0,18)}</div>
        <div style="font-size:19px;font-weight:900;color:#fff;letter-spacing:1px;font-family:monospace;">\${f.flight_number||'—'}</div>
        <div style="font-size:15px;font-weight:600;color:#e2e8f0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">\${place}</div>
        <div style="font-size:17px;font-weight:700;color:\${delayed?'#f59e0b':'#fff'};font-family:monospace;">\${schedT}</div>
        <div style="font-size:15px;color:\${actT?'#34d399':'#475569'};font-family:monospace;">\${actT||'—'}</div>
        \${routeCell}
        <div><span class="\${sCls}" style="padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap;">\${sLabel}</span></div>
      </div>\`;
    }).join('');
  }

  loadFlights();
  setInterval(loadFlights, REFRESH);
})();
</script>
HTML;

        $placeLabel      = match($direction) { 'arrival'=>'مبدا', 'departure'=>'مقصد', default=>'مبدا/مقصد' };
        $gridColsDefault = $direction === 'all'
            ? '120px 110px 1fr 100px 100px 80px 140px'
            : '120px 110px 1fr 100px 100px 140px';

        return str_replace(
            [
                '__BG__', '__HDR__', '__ODD__', '__EVEN__', '__TEXT__', '__SUB__', '__BADGE__',
                '__AIRPORTNAME__', '__DIRICON__', '__DIRLABELFA__', '__DIRLABELEN__',
                '__ROUTELABELFA__', '__ROUTELABELEN__',
                '__PLACELABEL__', '__GRIDCOLS__', '__ROUTECOLDISPLAY__',
                '__REFRESHSEC__', '__APIURL__', '__DIRECTION__', '__SHOWBADGEJS__', '__LANG__',
            ],
            [
                $tBg, $tHdr, $tOdd, $tEven, $tText, $tSub, $tBadge,
                $airportName, $dirIcon, $dirLabelFa, $dirLabelEn,
                $routeLabelFa, $routeLabelEn,
                $placeLabel, $gridColsDefault, $showRouteCol,
                (string)$refreshSec, $apiUrl, $direction, $showBadgeJs, $lang,
            ],
            $__tpl
        );
    }

    private function typeLabel(string $type, string $lang): string
    {
        if ($lang === 'en') return $type === 'departure' ? 'DEPARTURES' : 'ARRIVALS';
        if ($lang === 'fa') return $type === 'departure' ? 'پروازهای عزیمت' : 'پروازهای ورود';
        return $type === 'departure' ? 'پروازهای عزیمت · Departures' : 'پروازهای ورود · Arrivals';
    }

    private function gridCols(bool $showLogo): string
    {
        return $showLogo ? '60px 100px 1fr 100px 80px 80px 130px' : '100px 1fr 100px 80px 80px 130px';
    }

    private function renderHeaders(string $type, string $lang, bool $showLogo): string
    {
        $cols = $showLogo ? '<div>ایرلاین</div>' : '';
        $cols .= '<div>شماره پرواز</div>';
        $cols .= '<div>' . ($type === 'departure' ? 'مقصد' : 'مبدا') . '</div>';
        $cols .= '<div>زمان</div><div>ترمینال</div><div>دروازه</div><div>وضعیت</div>';
        return $cols;
    }

    private function renderSplitFlap(array $settings): string
    {
        $rows  = (int)($settings['rows'] ?? 8);
        $speed = (int)($settings['flip_speed'] ?? 80);
        $animDuration = $speed . 'ms';
        $apiUrl = '/api/v1/fids/flights?limit=' . $rows;
        // CSS keyframes style (pre-built to avoid PHP parse issues)
        $sfCss = <<<'CSS'
    .sf-board { font-family: 'Courier New', monospace; }
    .sf-row { display: grid; grid-template-columns: 80px 100px 1fr 90px 70px 120px; gap: 6px; padding: 4px 0; }
    .sf-cell { background: #1a1a2e; border: 1px solid #2d2d44; border-radius: 4px; padding: 8px 12px; }
    .sf-char { display: inline-block; color: #f59e0b; font-size: 22px; font-weight: 700; letter-spacing: 2px; }
    .sf-header .sf-cell { background: #0f0f1e; color: #64748b; font-size: 11px; padding: 6px 12px; }
    @keyframes flip { from { transform: scaleY(1); } 50% { transform: scaleY(0); } to { transform: scaleY(1); } }
CSS;
        $sfCss .= "    .sf-flip { animation: flip {$animDuration} ease; }
";
        $__tpl = <<<'HTML'
<div id="splitflap" style="width:100%;height:100%;background:#111;display:flex;flex-direction:column;justify-content:center;padding:20px;gap:4px;">
  <style>
__VAR_SFCSS__  </style>
  <div class="sf-board" id="sfBoard">
    <div class="sf-row sf-header">
      <div class="sf-cell">FLIGHT</div>
      <div class="sf-cell">TIME</div>
      <div class="sf-cell">DESTINATION</div>
      <div class="sf-cell">GATE</div>
      <div class="sf-cell">TERM</div>
      <div class="sf-cell">STATUS</div>
    </div>
    <div id="sf-rows"></div>
  </div>
</div>
<script>
(function() {
  const SPEED = __VAR_SPEED__;
  async function load() {
    const r = await fetch('__VAR_APIURL__&type=departure');
    const d = await r.json();
    render(d.data || []);
  }
  function render(flights) {
    document.getElementById('sf-rows').innerHTML = flights.map(f => {
      const time = new Date(f.scheduled_time).toLocaleTimeString('en',{hour:'2-digit',minute:'2-digit',hour12:false});
      const statusColor = {boarding:"#22c55e",delayed:"#f59e0b",cancelled:"#ef4444",departed:"#475569"}[f.status]||'#0ea5e9';
      return \`<div class="sf-row" style="border-top:1px solid #1e293b;">
        <div class="sf-cell"><span class="sf-char">\${f.flight_number}</span></div>
        <div class="sf-cell"><span class="sf-char">\${time}</span></div>
        <div class="sf-cell"><span class="sf-char" style="color:#e2e8f0;font-size:18px;">\\${(f.destination||f.origin||'').substring(0,16)}</span></div>
        <div class="sf-cell"><span class="sf-char" style="color:#0ea5e9;">\\${f.gate||'--'}</span></div>
        <div class="sf-cell"><span class="sf-char" style="color:#94a3b8;">\${f.terminal||'-'}</span></div>
        <div class="sf-cell"><span class="sf-char" style="color:\${statusColor};font-size:14px;">\${f.status.toUpperCase()}</span></div>
      </div>\`;
    }).join('');
  }
  load(); setInterval(load, 30000);
})();
</script>
HTML;
        return str_replace(
            ['__VAR_SFCSS__', '__VAR_SPEED__', '__VAR_APIURL__'],
            [$sfCss, $speed, $apiUrl],
            $__tpl
        );
    }

    private function renderGateDisplay(array $settings): string
    {
        $gate = $settings['gate_id'] ?? 'A1';
        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#080820;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:'Segoe UI',sans-serif;direction:rtl;" id="gate-display">
  <div style="font-size:16px;color:#64748b;letter-spacing:4px;text-transform:uppercase;margin-bottom:8px;">GATE · دروازه</div>
  <div style="font-size:140px;font-weight:900;color:#0ea5e9;line-height:1;font-family:monospace;text-shadow:0 0 40px rgba(14,165,233,0.5);">__VAR_GATE__</div>
  <div style="width:80%;height:2px;background:linear-gradient(90deg,transparent,#0ea5e9,transparent);margin:24px 0;"></div>
  <div id="gate-flight-num"  style="font-size:48px;font-weight:800;color:#fff;letter-spacing:4px;font-family:monospace;">---</div>
  <div id="gate-destination" style="font-size:28px;color:#94a3b8;margin-top:8px;">در انتظار اطلاعات...</div>
  <div style="display:flex;gap:48px;margin-top:32px;">
    <div style="text-align:center;"><div style="font-size:13px;color:#475569;margin-bottom:4px;">زمان پرواز</div><div id="gate-time" style="font-size:28px;font-weight:700;color:#f59e0b;font-family:monospace;">--:--</div></div>
    <div style="text-align:center;"><div style="font-size:13px;color:#475569;margin-bottom:4px;">وضعیت</div><div id="gate-status" style="font-size:20px;font-weight:700;color:#22c55e;padding:6px 16px;border-radius:20px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);">---</div></div>
  </div>
</div>
<script>
(async function() {
  const r = await fetch('/api/v1/fids/flights?gate=__VAR_GATE__&limit=1');
  const d = await r.json();
  const f = (d.data||[])[0];
  if (!f) return;
  document.getElementById('gate-flight-num').textContent  = f.flight_number;
  document.getElementById('gate-destination').textContent = f.destination || f.origin || '—';
  document.getElementById('gate-time').textContent = new Date(f.estimated_time||f.scheduled_time).toLocaleTimeString('fa-IR',{hour:"2-digit",minute:"2-digit"});
  const statusMap = {boarding:"سوار شوید",scheduled:"زمان‌بندی",delayed:"تأخیر",cancelled:"لغو",departed:"پرواز کرد"};
  document.getElementById('gate-status').textContent = statusMap[f.status]||f.status;
})();
</script>
HTML;
        return str_replace(
            ['__VAR_GATE__'],
            [$gate],
            $__tpl
        );
    }
}
