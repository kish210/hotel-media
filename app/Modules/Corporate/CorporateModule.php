<?php
declare(strict_types=1);
namespace App\Modules\Corporate;
use App\Modules\Core\BaseModule;

class CorporateModule extends BaseModule
{
    public function id(): string          { return 'corporate'; }
    public function name(): string        { return 'اطلاع‌رسانی سازمانی'; }
    public function nameEn(): string      { return 'Corporate Information'; }
    public function description(): string { return 'اخبار سازمانی، KPI، لابی‌بورد، راهنمای ساختمان و اعلانات داخلی'; }
    public function version(): string     { return '1.0.0'; }
    public function icon(): string        { return 'fas fa-building-columns'; }
    public function color(): string       { return '#6366f1'; }
    public function category(): string    { return 'corporate'; }

    public function zoneTypes(): array
    {
        return [
            ['id'=>'corp_lobby',      'label'=>'لابی‌بورد',         'icon'=>'fas fa-display',              'defaultSize'=>['w'=>1920,'h'=>1080],
             'settings'=>[['key'=>'company','label'=>'نام شرکت','type'=>'text','default'=>'شرکت ما'],['key'=>'bg_style','label'=>'استایل','type'=>'select','options'=>['dark'=>'تاریک','corp'=>'سازمانی','minimal'=>'مینیمال'],'default'=>'corp']]],
            ['id'=>'corp_kpi',        'label'=>'داشبورد KPI',        'icon'=>'fas fa-chart-line',           'defaultSize'=>['w'=>1920,'h'=>1080],
             'settings'=>[['key'=>'refresh_min','label'=>'بروزرسانی (دقیقه)','type'=>'number','default'=>5]]],
            ['id'=>'corp_news',       'label'=>'اخبار سازمانی',      'icon'=>'fas fa-newspaper',            'defaultSize'=>['w'=>1920,'h'=>600],
             'settings'=>[['key'=>'auto_scroll','label'=>'اسکرول خودکار','type'=>'bool','default'=>true],['key'=>'show_images','label'=>'نمایش تصویر','type'=>'bool','default'=>true]]],
            ['id'=>'corp_directory',  'label'=>'راهنمای ساختمان',   'icon'=>'fas fa-map-signs',            'defaultSize'=>['w'=>1080,'h'=>1920],
             'settings'=>[['key'=>'building','label'=>'نام ساختمان','type'=>'text','default'=>'ساختمان اصلی']]],
            ['id'=>'corp_visitor',    'label'=>'سیستم بازدیدکننده', 'icon'=>'fas fa-id-card',              'defaultSize'=>['w'=>1920,'h'=>1080],
             'settings'=>[['key'=>'company','label'=>'نام شرکت','type'=>'text','default'=>'شرکت ما']]],
            ['id'=>'corp_countdown',  'label'=>'شمارش معکوس رویداد','icon'=>'fas fa-hourglass-half',       'defaultSize'=>['w'=>1920,'h'=>1080],
             'settings'=>[['key'=>'event_name','label'=>'نام رویداد','type'=>'text','default'=>'رویداد بزرگ'],['key'=>'event_date','label'=>'تاریخ','type'=>'datetime-local','default'=>'']]],
            ['id'=>'corp_social',     'label'=>'فید شبکه اجتماعی',  'icon'=>'fas fa-hashtag',              'defaultSize'=>['w'=>600,'h'=>1080],
             'settings'=>[['key'=>'hashtag','label'=>'هشتگ','type'=>'text','default'=>'#company']]],
        ];
    }

    public function migrations(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS `corp_news` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id`   INT UNSIGNED NOT NULL,
                `title`       VARCHAR(500) NOT NULL,
                `body`        TEXT DEFAULT NULL,
                `image`       VARCHAR(500) DEFAULT NULL,
                `category`    VARCHAR(100) DEFAULT NULL,
                `priority`    TINYINT UNSIGNED DEFAULT 5,
                `is_pinned`   TINYINT(1) DEFAULT 0,
                `published_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `expires_at`  TIMESTAMP DEFAULT NULL,
                `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                KEY `idx_corp_news_tenant` (`tenant_id`),
                KEY `idx_corp_news_pub`    (`published_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `corp_kpi` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id`   INT UNSIGNED NOT NULL,
                `name`        VARCHAR(255) NOT NULL,
                `value`       VARCHAR(100) NOT NULL,
                `target`      VARCHAR(100) DEFAULT NULL,
                `unit`        VARCHAR(50) DEFAULT NULL,
                `change_pct`  DECIMAL(5,2) DEFAULT NULL,
                `icon`        VARCHAR(100) DEFAULT 'fas fa-chart-line',
                `color`       VARCHAR(7) DEFAULT '#6366f1',
                `sort_order`  SMALLINT UNSIGNED DEFAULT 0,
                `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
                `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_corp_kpi_tenant` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `corp_departments` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id`   INT UNSIGNED NOT NULL,
                `name`        VARCHAR(255) NOT NULL,
                `floor`       VARCHAR(20) DEFAULT NULL,
                `room`        VARCHAR(20) DEFAULT NULL,
                `phone`       VARCHAR(20) DEFAULT NULL,
                `manager`     VARCHAR(255) DEFAULT NULL,
                `icon`        VARCHAR(100) DEFAULT 'fas fa-door-open',
                `sort_order`  SMALLINT UNSIGNED DEFAULT 0,
                `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                KEY `idx_corp_dept_tenant` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "INSERT IGNORE INTO `corp_kpi` (`tenant_id`,`name`,`value`,`target`,`unit`,`change_pct`,`icon`,`color`,`sort_order`) VALUES
                (1,'فروش ماهانه','۱۲۵,۰۰۰','۱۵۰,۰۰۰','میلیون تومان',8.3,'fas fa-chart-line','#22c55e',1),
                (1,'تعداد مشتریان','۴,۸۲۰','۵,۰۰۰','نفر',2.1,'fas fa-users','#6366f1',2),
                (1,'رضایت مشتری','۹۲','۹۵','درصد',-1.2,'fas fa-smile','#f59e0b',3),
                (1,'سفارشات امروز','۲۳۴',NULL,'سفارش',15.4,'fas fa-shopping-cart','#0ea5e9',4)",
        ];
    }

    public function renderPlayerWidget(string $zoneType, array $settings = []): string
    {
        return match($zoneType) {
            'corp_lobby'     => $this->renderLobby($settings),
            'corp_kpi'       => $this->renderKPI($settings),
            'corp_news'      => $this->renderNews($settings),
            'corp_directory' => $this->renderBuildingDir($settings),
            'corp_visitor'   => $this->renderVisitor($settings),
            'corp_countdown' => $this->renderCountdown($settings),
            'corp_social'    => $this->renderSocial($settings),
            default          => '<div>نامعتبر</div>',
        };
    }

    private function renderLobby(array $s): string
    {
        $company  = htmlspecialchars($s['company'] ?? 'شرکت ما');
        $style    = $s['bg_style'] ?? 'corp';
        $bgs = ['dark'=>'#07070f','corp'=>'#050a1f','minimal'=>'#0f0f0f'];
        $bg  = $bgs[$style] ?? $bgs['dark'];
        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:__VAR_BG__;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:'Segoe UI',Tahoma,sans-serif;direction:rtl;position:relative;overflow:hidden;">
  <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 50% 30%,rgba(99,102,241,0.12),transparent 60%);"></div>
  <div style="position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#6366f1,#0ea5e9,#6366f1);"></div>
  <div style="position:relative;text-align:center;">
    <div style="width:80px;height:80px;border-radius:20px;background:linear-gradient(135deg,#6366f1,#0ea5e9);display:flex;align-items:center;justify-content:center;margin:0 auto 24px;">
      <i class="fas fa-building-columns" style="font-size:36px;color:#fff;"></i>
    </div>
    <h1 style="font-size:clamp(32px,5vw,72px);font-weight:900;color:#fff;margin:0;">__VAR_COMPANY__</h1>
    <div style="width:100px;height:2px;background:linear-gradient(90deg,transparent,#6366f1,transparent);margin:20px auto;"></div>
    <p style="font-size:clamp(16px,2vw,24px);color:#94a3b8;margin:0;letter-spacing:4px;">خوش آمدید · WELCOME</p>
  </div>
  <div id="corp-clock" style="margin-top:40px;font-size:clamp(40px,6vw,80px);font-weight:700;color:#fff;font-family:monospace;letter-spacing:3px;"></div>
  <div id="corp-date" style="font-size:clamp(14px,2vw,22px);color:#64748b;margin-top:8px;"></div>
  <div style="position:absolute;bottom:24px;left:0;right:0;display:flex;justify-content:center;gap:32px;" id="corp-kpi-mini"></div>
</div>
<script>
(function(){
  setInterval(()=>{
    const c=document.getElementById('corp-clock'),d=document.getElementById('corp-date');
    if(c)c.textContent=new Date().toLocaleTimeString('fa-IR',{hour:"2-digit",minute:"2-digit",second:"2-digit"});
    if(d)d.textContent=new Date().toLocaleDateString('fa-IR',{weekday:"long",year:"numeric",month:"long",day:"numeric"});
  },1000);
  fetch('/api/v1/corporate/kpi').then(r=>r.json()).then(d=>{
    const kpis=(d.data||[]).slice(0,4);
    const el=document.getElementById('corp-kpi-mini');
    if(el)el.innerHTML=kpis.map(k=>`<div style="background:rgba(255,255,255,0.04);border:1px solid rgba(99,102,241,0.2);border-radius:14px;padding:12px 20px;text-align:center;"><div style="font-size:11px;color:#64748b;">\${k.name}</div><div style="font-size:22px;font-weight:800;color:\\${k.color||'#6366f1'};margin-top:4px;">\${k.value}\${k.unit?` \${k.unit}`:''}</div></div>`).join('');
  }).catch(()=>{});
})();
</script>
HTML;
        return str_replace(
            ['__VAR_BG__', '__VAR_COMPANY__'],
            [$bg, $company],
            $__tpl
        );

        return str_replace(
            ['__VAR_BG__', '__VAR_COMPANY__'],
            [$bg, $company],
            $__tpl
        );
    }

    private function renderKPI(array $s): string
    {
        $kpiRefresh = (int)($s['refresh_min'] ?? 5);

        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#07070f;font-family:'Segoe UI',sans-serif;direction:rtl;display:flex;flex-direction:column;">
  <div style="background:rgba(99,102,241,0.15);border-bottom:2px solid rgba(99,102,241,0.4);padding:16px 28px;display:flex;align-items:center;gap:12px;">
    <i class="fas fa-chart-line" style="color:#6366f1;font-size:22px;"></i>
    <div style="font-size:20px;font-weight:800;color:#fff;">داشبورد عملکرد</div>
    <div style="font-size:13px;color:#64748b;margin-right:6px;">Key Performance Indicators</div>
    <div id="kpi-clock" style="margin-right:auto;font-size:18px;color:#fff;font-family:monospace;"></div>
  </div>
  <div id="kpi-grid" style="flex:1;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;padding:20px;align-content:start;overflow:auto;"></div>
</div>
<script>
(function(){
  const KPI_REFRESH=__VAR_KPIREFRESH__;
  setInterval(()=>{const e=document.getElementById('kpi-clock');if(e)e.textContent=new Date().toLocaleTimeString('fa-IR',{hour:"2-digit",minute:"2-digit"});},1000);
  async function load(){
    const r=await fetch('/api/v1/corporate/kpi');
    const d=await r.json();
    const kpis=d.data||[];
    const el=document.getElementById('kpi-grid');
    el.innerHTML=kpis.map(k=>{
      const up=k.change_pct>0,neutral=k.change_pct===null||k.change_pct===0;
      const chColor=neutral?'#64748b':up?'#22c55e':'#ef4444';
      const chIcon=neutral?'minus':up?'arrow-up':'arrow-down';
      const pct=k.target?Math.min(100,Math.round((parseFloat(k.value)||0)/(parseFloat(k.target)||1)*100)):null;
      return `<div style="background:rgba(255,255,255,0.03);border:1px solid \\${k.color||'#6366f1'}22;border-radius:18px;padding:22px;border-top:3px solid \\${k.color||'#6366f1'};">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;">
          <div style="width:44px;height:44px;border-radius:12px;background:\\${k.color||'#6366f1'}18;display:flex;align-items:center;justify-content:center;border:1px solid \\${k.color||'#6366f1'}33;">
            <i class="\${k.icon||'fas fa-chart-bar'}" style="color:\\${k.color||'#6366f1'};font-size:18px;"></i>
          </div>
          <div style="font-size:12px;color:\${chColor};background:\${chColor}18;border:1px solid \${chColor}33;padding:3px 8px;border-radius:8px;">
            <i class="fas fa-\${chIcon}" style="font-size:9px;margin-left:3px;"></i>\${neutral?'—':Math.abs(k.change_pct||0)+'%'}
          </div>
        </div>
        <div style="font-size:13px;color:#94a3b8;margin-bottom:6px;">\${k.name}</div>
        <div style="font-size:32px;font-weight:900;color:#fff;">\${k.value}<span style="font-size:16px;color:#64748b;font-weight:400;margin-right:6px;">\${k.unit||''}</span></div>
        \${k.target?`<div style="margin-top:12px;"><div style="display:flex;justify-content:space-between;font-size:11px;color:#475569;margin-bottom:4px;"><span>هدف: \${k.target}</span><span>\${pct}%</span></div><div style="background:rgba(255,255,255,0.06);border-radius:3px;height:5px;overflow:hidden;"><div style="height:100%;width:\${pct}%;background:\\${k.color||'#6366f1'};border-radius:3px;transition:width 0.8s ease;"></div></div></div>`:''}
      </div>`;
    }).join('');
  }
  load();setInterval(load,KPI_REFRESH*60000);
})();
</script>
HTML;
        return str_replace(
            ['__VAR_KPIREFRESH__'],
            [$kpiRefresh],
            $__tpl
        );

        return str_replace(
            ['__VAR_KPIREFRESH__'],
            [$kpiRefresh],
            $__tpl
        );
    }

    private function renderNews(array $s): string
    {
        $autoScroll = ($s['auto_scroll'] ?? true) ? 'true' : 'false';

        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#07070f;font-family:'Segoe UI',sans-serif;direction:rtl;display:flex;flex-direction:column;overflow:hidden;">
  <div style="background:rgba(99,102,241,0.15);border-bottom:2px solid rgba(99,102,241,0.4);padding:14px 24px;display:flex;align-items:center;gap:10px;">
    <i class="fas fa-newspaper" style="color:#6366f1;font-size:18px;"></i>
    <div style="font-size:18px;font-weight:800;color:#fff;">اخبار و اطلاعیه‌های سازمانی</div>
  </div>
  <div id="corp-news-list" style="flex:1;overflow-y:auto;padding:16px;scrollbar-width:none;"></div>
</div>
<script>
(async function(){
  const NEWS_AUTO_SCROLL=__VAR_AUTOSCROLL__;
  const r=await fetch('/api/v1/corporate/news');
  const d=await r.json();
  const items=d.data||[];
  const el=document.getElementById('corp-news-list');
  if(!items.length){el.innerHTML='<div style="color:#475569;text-align:center;padding:60px;">اخباری وجود ندارد</div>';return;}
  el.innerHTML=items.map(n=>`
    <div style="display:flex;gap:16px;padding:16px;margin-bottom:10px;border-radius:14px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);\\${n.is_pinned?'border-color:rgba(99,102,241,0.4);':''}">
      \${n.image?`<img src="\${n.image}" style="width:80px;height:80px;object-fit:cover;border-radius:10px;flex-shrink:0;">`:''}
      <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
          \${n.is_pinned?'<span style="background:rgba(99,102,241,0.2);color:#818cf8;border:1px solid rgba(99,102,241,0.4);padding:2px 8px;border-radius:8px;font-size:10px;font-weight:700;">📌 سنجاق</span>':''}
          \${n.category?`<span style="background:rgba(255,255,255,0.05);color:#64748b;padding:2px 8px;border-radius:8px;font-size:10px;">\${n.category}</span>`:''}
        </div>
        <div style="font-size:16px;font-weight:700;color:#fff;">\${n.title}</div>
        \${n.body?`<div style="font-size:13px;color:#94a3b8;margin-top:6px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">\${n.body}</div>`:''}
        <div style="font-size:11px;color:#475569;margin-top:8px;">\${new Date(n.published_at).toLocaleDateString('fa-IR',{year:"numeric",month:"long",day:"numeric"})}</div>
      </div>
    </div>
  `).join('');
  // Auto-scroll
  if(NEWS_AUTO_SCROLL){const el2=document.getElementById('corp-news-list');let pos=0;setInterval(()=>{pos+=0.5;if(pos>=el2.scrollHeight-el2.clientHeight)pos=0;el2.scrollTop=pos;},50);}
})();
</script>
HTML;
        return str_replace(
            ['__VAR_AUTOSCROLL__'],
            [$autoScroll],
            $__tpl
        );

        return str_replace(
            ['__VAR_AUTOSCROLL__'],
            [$autoScroll],
            $__tpl
        );
    }

    private function renderCountdown(array $s): string
    {
        $eventName = htmlspecialchars($s['event_name'] ?? 'رویداد بزرگ');
        $eventDate = $s['event_date'] ?? '';
        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#07070f;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:'Segoe UI',sans-serif;direction:rtl;">
  <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 50% 60%,rgba(99,102,241,0.1),transparent 70%);"></div>
  <div style="position:relative;text-align:center;">
    <i class="fas fa-hourglass-half" style="font-size:48px;color:#6366f1;margin-bottom:20px;display:block;animation:spin 4s linear infinite;"></i>
    <div style="font-size:clamp(24px,4vw,48px);font-weight:700;color:#fff;margin-bottom:8px;">__VAR_EVENTNAME__</div>
    <div style="font-size:14px;color:#64748b;margin-bottom:40px;letter-spacing:2px;">تا رویداد باقی مانده</div>
    <div id="countdown-wrap" style="display:flex;gap:24px;justify-content:center;"></div>
    <div id="cd-event-date" style="margin-top:24px;font-size:14px;color:#64748b;"></div>
  </div>
</div>
<script>
(function(){
  const target=new Date('__VAR_EVENTDATE__' || (Date.now()+86400000));
  const names=['روز','ساعت','دقیقه','ثانیه'];
  document.getElementById('cd-event-date').textContent=target.toLocaleDateString('fa-IR',{year:"numeric",month:"long",day:"numeric",hour:"2-digit",minute:"2-digit"});
  function tick(){
    const diff=target-Date.now();
    if(diff<=0){document.getElementById('countdown-wrap').innerHTML='<div style="font-size:48px;color:#6366f1;font-weight:900;">🎉 رویداد آغاز شد!</div>';return;}
    const parts=[Math.floor(diff/86400000),Math.floor((diff%86400000)/3600000),Math.floor((diff%3600000)/60000),Math.floor((diff%60000)/1000)];
    document.getElementById('countdown-wrap').innerHTML=parts.map((v,i)=>`
      <div style="background:rgba(99,102,241,0.1);border:2px solid rgba(99,102,241,0.3);border-radius:20px;padding:24px 32px;text-align:center;min-width:120px;">
        <div style="font-size:clamp(36px,6vw,72px);font-weight:900;color:#fff;font-family:monospace;">\\${String(v).padStart(2,'0')}</div>
        <div style="font-size:13px;color:#6366f1;margin-top:8px;font-weight:600;">\${names[i]}</div>
      </div>`).join('');
  }
  tick();setInterval(tick,1000);
})();
</script>
HTML;
        return str_replace(
            ['__VAR_EVENTNAME__', '__VAR_EVENTDATE__'],
            [$eventName, $eventDate],
            $__tpl
        );

        return str_replace(
            ['__VAR_EVENTNAME__', '__VAR_EVENTDATE__'],
            [$eventName, $eventDate],
            $__tpl
        );
    }

    private function renderBuildingDir(array $s): string { return '<div style="width:100%;height:100%;background:#07070f;font-family:Segoe UI,sans-serif;direction:rtl;display:flex;flex-direction:column;"><div style="background:rgba(99,102,241,0.15);border-bottom:2px solid rgba(99,102,241,0.4);padding:16px 20px;text-align:center;"><i class="fas fa-map-signs" style="color:#6366f1;font-size:20px;margin-left:8px;"></i><span style="font-size:18px;font-weight:800;color:#fff;">راهنمای ساختمان · '.htmlspecialchars($s['building']??'ساختمان').'</span></div><div id="bdir" style="flex:1;overflow-y:auto;padding:10px;scrollbar-width:none;"></div></div><script>(async function(){const r=await fetch("/api/v1/corporate/departments");const d=await r.json();const items=d.data||[];const colors=["#6366f1","#0ea5e9","#22c55e","#f59e0b","#ec4899","#f97316"];document.getElementById("bdir").innerHTML=items.map((dep,i)=>`<div style="display:flex;align-items:center;gap:12px;padding:12px 14px;margin-bottom:6px;border-radius:12px;background:rgba(255,255,255,0.03);border-right:3px solid ${colors[i%colors.length]};"><i class="${dep.icon||"fas fa-door-open"}" style="color:${colors[i%colors.length]};font-size:18px;width:22px;text-align:center;"></i><div style="flex:1;"><div style="font-size:15px;font-weight:700;color:#fff;">${dep.name}</div>${dep.manager?`<div style="font-size:11px;color:#64748b;">${dep.manager}</div>`:""}</div><div style="text-align:left;">${dep.floor?`<div style="font-size:12px;color:#94a3b8;">طبقه ${dep.floor}</div>`:""}${dep.room?`<div style="font-size:12px;color:#64748b;">اتاق ${dep.room}</div>`:""}</div>${dep.phone?`<div style="font-size:16px;font-weight:700;color:${colors[i%colors.length]};font-family:monospace;">${dep.phone}</div>`:""}</div>`).join("")||"<div style=\"color:#475569;text-align:center;padding:40px;\">اطلاعاتی ثبت نشده</div>"})();</script>'; }
    private function renderVisitor(array $s): string { $c=htmlspecialchars($s['company']??'شرکت'); return "<div style=\"width:100%;height:100%;background:#07070f;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:'Segoe UI',sans-serif;direction:rtl;gap:24px;\"><i class=\"fas fa-id-card\" style=\"font-size:64px;color:#6366f1;\"></i><div style=\"font-size:36px;font-weight:900;color:#fff;\">{$c}</div><div style=\"font-size:18px;color:#94a3b8;\">سیستم مدیریت بازدیدکننده</div><div style=\"background:rgba(99,102,241,0.1);border:2px solid rgba(99,102,241,0.3);border-radius:20px;padding:24px 40px;text-align:center;\"><div style=\"font-size:14px;color:#64748b;margin-bottom:8px;\">برای ثبت ورود با پذیرش تماس بگیرید</div><div style=\"font-size:48px;font-weight:900;color:#6366f1;font-family:monospace;\">0</div></div></div>"; }
    private function renderSocial(array $s): string { $ht=htmlspecialchars($s['hashtag']??'#company'); return "<div style=\"width:100%;height:100%;background:#07070f;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:'Segoe UI',sans-serif;direction:rtl;\"><i class=\"fas fa-hashtag\" style=\"font-size:64px;color:#6366f1;margin-bottom:20px;\"></i><div style=\"font-size:32px;font-weight:900;color:#fff;\">{$ht}</div><div style=\"font-size:14px;color:#64748b;margin-top:8px;\">فید شبکه اجتماعی</div></div>"; }

    public function getDashboardStats(): array { return ['news'=>(int)$this->db->value("SELECT COUNT(*) FROM corp_news WHERE tenant_id=? AND is_active=1",[$this->tenantId]),'kpis'=>(int)$this->db->value("SELECT COUNT(*) FROM corp_kpi WHERE tenant_id=? AND is_active=1",[$this->tenantId])]; }
}
