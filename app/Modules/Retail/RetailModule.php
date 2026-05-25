<?php
declare(strict_types=1);
namespace App\Modules\Retail;
use App\Modules\Core\BaseModule;

class RetailModule extends BaseModule
{
    public function id(): string          { return 'retail'; }
    public function name(): string        { return 'فروشگاه و خرده‌فروشی'; }
    public function nameEn(): string      { return 'Retail & Shopping'; }
    public function description(): string { return 'نمایش قیمت‌ها، تخفیف‌ها، محصولات ویژه و تابلوی قیمت فروشگاه'; }
    public function version(): string     { return '1.0.0'; }
    public function icon(): string        { return 'fas fa-store'; }
    public function color(): string       { return '#ec4899'; }
    public function category(): string    { return 'retail'; }

    public function zoneTypes(): array
    {
        return [
            ['id'=>'retail_priceboard',  'label'=>'تابلوی قیمت',    'icon'=>'fas fa-tag',          'defaultSize'=>['w'=>1920,'h'=>1080],
             'settings'=>[['key'=>'title','label'=>'عنوان','type'=>'text','default'=>'لیست قیمت'],['key'=>'currency','label'=>'واحد پول','type'=>'text','default'=>'تومان'],['key'=>'cols','label'=>'ستون','type'=>'number','default'=>2]]],
            ['id'=>'retail_offers',      'label'=>'تخفیف و آفر',    'icon'=>'fas fa-percent',       'defaultSize'=>['w'=>1920,'h'=>1080],
             'settings'=>[['key'=>'countdown','label'=>'شمارش معکوس','type'=>'bool','default'=>true]]],
            ['id'=>'retail_featured',    'label'=>'محصول ویژه',     'icon'=>'fas fa-star',          'defaultSize'=>['w'=>1920,'h'=>1080],
             'settings'=>[['key'=>'show_qr','label'=>'QR کد','type'=>'bool','default'=>true],['key'=>'duration','label'=>'مدت نمایش (ثانیه)','type'=>'number','default'=>15]]],
            ['id'=>'retail_currency',    'label'=>'نرخ ارز',         'icon'=>'fas fa-coins',         'defaultSize'=>['w'=>600,'h'=>400],
             'settings'=>[['key'=>'currencies','label'=>'ارزها','type'=>'text','default'=>'USD,EUR,GBP,AED,TRY']]],
            ['id'=>'retail_queue',       'label'=>'صف نوبت',         'icon'=>'fas fa-ticket',        'defaultSize'=>['w'=>1920,'h'=>1080],
             'settings'=>[['key'=>'service_name','label'=>'نام سرویس','type'=>'text','default'=>'باجه ۱']]],
        ];
    }

    public function migrations(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS `retail_products` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id`   INT UNSIGNED NOT NULL,
                `category`    VARCHAR(100) NOT NULL,
                `name`        VARCHAR(255) NOT NULL,
                `name_en`     VARCHAR(255) DEFAULT NULL,
                `price`       DECIMAL(15,2) NOT NULL DEFAULT 0,
                `old_price`   DECIMAL(15,2) DEFAULT NULL,
                `currency`    VARCHAR(10) NOT NULL DEFAULT 'تومان',
                `unit`        VARCHAR(50) DEFAULT NULL,
                `image`       VARCHAR(500) DEFAULT NULL,
                `barcode`     VARCHAR(100) DEFAULT NULL,
                `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
                `is_offer`    TINYINT(1) NOT NULL DEFAULT 0,
                `offer_ends`  DATETIME DEFAULT NULL,
                `stock`       INT DEFAULT NULL,
                `sort_order`  SMALLINT UNSIGNED DEFAULT 0,
                `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
                `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_retail_tenant`   (`tenant_id`),
                KEY `idx_retail_featured` (`is_featured`),
                KEY `idx_retail_offer`    (`is_offer`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `retail_queue` (
                `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id`    INT UNSIGNED NOT NULL,
                `counter`      VARCHAR(50) NOT NULL,
                `ticket_number` INT UNSIGNED NOT NULL,
                `called_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `status`       ENUM('waiting','serving','done') DEFAULT 'serving',
                PRIMARY KEY (`id`),
                KEY `idx_queue_tenant` (`tenant_id`),
                KEY `idx_queue_called` (`called_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }

    public function renderPlayerWidget(string $zoneType, array $settings = []): string
    {
        return match($zoneType) {
            'retail_priceboard' => $this->renderPriceBoard($settings),
            'retail_offers'     => $this->renderOffers($settings),
            'retail_featured'   => $this->renderFeatured($settings),
            'retail_currency'   => $this->renderCurrency($settings),
            'retail_queue'      => $this->renderQueue($settings),
            default             => '<div>نامعتبر</div>',
        };
    }

    private function renderPriceBoard(array $s): string
    {
        $title    = htmlspecialchars($s['title'] ?? 'لیست قیمت');
        $currency = htmlspecialchars($s['currency'] ?? 'تومان');
        $cols     = max(1, min(4, (int)($s['cols'] ?? 2)));
        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#080810;font-family:'Segoe UI',sans-serif;direction:rtl;display:flex;flex-direction:column;">
  <div style="background:linear-gradient(135deg,#ec4899,#a21caf);padding:20px 32px;display:flex;align-items:center;gap:14px;">
    <i class="fas fa-tag" style="font-size:26px;color:#fff;"></i>
    <div style="font-size:24px;font-weight:900;color:#fff;">__VAR_TITLE__</div>
    <div style="margin-right:auto;font-size:13px;color:rgba(255,255,255,0.7);">واحد: __VAR_CURRENCY__</div>
    <div id="pb-clock" style="font-size:20px;font-weight:700;color:#fff;font-family:monospace;margin-right:16px;"></div>
  </div>
  <div id="pb-content" style="flex:1;overflow:auto;padding:16px 24px;display:grid;grid-template-columns:repeat(__VAR_COLS__,1fr);gap:12px;align-content:start;">
    <div style="color:#475569;text-align:center;grid-column:1/-1;padding:40px;">در حال بارگذاری...</div>
  </div>
</div>
<script>
(function(){
  setInterval(()=>{const e=document.getElementById('pb-clock');if(e)e.textContent=new Date().toLocaleTimeString('fa-IR',{hour:"2-digit",minute:"2-digit"});},1000);
  async function load(){
    const r=await fetch('/api/v1/retail/products?active=1');
    const d=await r.json();
    const items=d.data||[];
    const el=document.getElementById('pb-content');
    if(!items.length){el.innerHTML='<div style="color:#475569;text-align:center;grid-column:1/-1;padding:40px;">محصولی ثبت نشده</div>';return;}
    const cats={};
    items.forEach(p=>{if(!cats[p.category])cats[p.category]=[];cats[p.category].push(p);});
    el.innerHTML=Object.entries(cats).map(([cat,prods])=>`
      <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(236,72,153,0.2);border-radius:16px;overflow:hidden;">
        <div style="background:rgba(236,72,153,0.2);padding:10px 16px;font-size:14px;font-weight:800;color:#f9a8d4;border-bottom:1px solid rgba(236,72,153,0.2);">\${cat}</div>
        <div style="padding:8px;">
          \\${prods.map(p=>{
            const hasOffer=p.old_price&&p.old_price>p.price;
            const disc=hasOffer?Math.round((1-p.price/p.old_price)*100):0;
            return `<div style="display:flex;justify-content:space-between;align-items:center;padding:9px 8px;border-bottom:1px solid rgba(255,255,255,0.04);">
              <span style="font-size:14px;color:#e2e8f0;">\${p.name}\${p.unit?` / \${p.unit}`:''}</span>
              <div style="text-align:left;">
                \\${hasOffer?`<span style="font-size:11px;color:#94a3b8;text-decoration:line-through;display:block;">\\${Number(p.old_price).toLocaleString()}</span>`:''}
                <span style="font-size:16px;font-weight:800;color:\${hasOffer?'#f59e0b':'#ec4899'};">\\${Number(p.price).toLocaleString()}</span>
                \\${hasOffer?`<span style="font-size:10px;background:rgba(245,158,11,0.2);color:#f59e0b;padding:1px 5px;border-radius:8px;margin-right:4px;">-\${disc}%</span>`:''}
              </div>
            </div>`;
          }).join('')}
        </div>
      </div>
    `).join('');
  }
  load();setInterval(load,60000);
})();
</script>
HTML;
        return str_replace(
            ['__VAR_TITLE__', '__VAR_CURRENCY__', '__VAR_COLS__'],
            [$title, $currency, $cols],
            $__tpl
        );

        return str_replace(
            ['__VAR_TITLE__', '__VAR_CURRENCY__', '__VAR_COLS__'],
            [$title, $currency, $cols],
            $__tpl
        );
    }

    private function renderOffers(array $s): string
    {
        $countdown = !isset($s['countdown']) || $s['countdown'];
        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#080810;font-family:'Segoe UI',sans-serif;direction:rtl;display:flex;flex-direction:column;overflow:hidden;">
  <div style="background:linear-gradient(135deg,#ec4899,#f97316);padding:18px 32px;display:flex;align-items:center;gap:14px;">
    <i class="fas fa-fire" style="font-size:28px;color:#fff;animation:pulse 1.5s infinite;"></i>
    <div style="font-size:24px;font-weight:900;color:#fff;">تخفیف‌های ویژه امروز</div>
    <div style="font-size:14px;color:rgba(255,255,255,0.8);margin-right:8px;">Today's Special Offers</div>
  </div>
  <div id="offers-list" style="flex:1;display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;padding:20px;overflow:auto;align-content:start;"></div>
</div>
<script>
(async function(){
  const r=await fetch('/api/v1/retail/products?offer=1');
  const d=await r.json();
  const items=d.data||[];
  const el=document.getElementById('offers-list');
  if(!items.length){el.innerHTML='<div style="color:#475569;text-align:center;grid-column:1/-1;padding:60px;"><i class="fas fa-percent" style="font-size:48px;opacity:0.2;display:block;margin-bottom:12px;"></i>آفری موجود نیست</div>';return;}
  el.innerHTML=items.map(p=>{
    const disc=p.old_price?Math.round((1-p.price/p.old_price)*100):0;
    const endDate=p.offer_ends?new Date(p.offer_ends):null;
    return `<div style="background:rgba(255,255,255,0.04);border:1px solid rgba(236,72,153,0.3);border-radius:20px;overflow:hidden;position:relative;">
      <div style="position:absolute;top:12px;right:12px;background:linear-gradient(135deg,#ec4899,#f97316);color:#fff;font-size:18px;font-weight:900;padding:6px 14px;border-radius:12px;">-\${disc}%</div>
      \${p.image?`<img src="\${p.image}" style="width:100%;height:180px;object-fit:cover;">`:`<div style="height:120px;background:linear-gradient(135deg,rgba(236,72,153,0.1),rgba(249,115,22,0.1));display:flex;align-items:center;justify-content:center;"><i class="fas fa-tag" style="font-size:48px;color:rgba(236,72,153,0.3);"></i></div>`}
      <div style="padding:16px;">
        <div style="font-size:17px;font-weight:700;color:#fff;">\${p.name}</div>
        <div style="display:flex;align-items:baseline;gap:8px;margin-top:8px;">
          <span style="font-size:24px;font-weight:900;color:#f59e0b;">\\${Number(p.price).toLocaleString()}</span>
          <span style="font-size:14px;color:#94a3b8;">تومان</span>
          <span style="font-size:14px;color:#64748b;text-decoration:line-through;">\\${Number(p.old_price).toLocaleString()}</span>
        </div>
        \${endDate&&__VAR_COUNTDOWN__?`<div id="cd-\${p.id}" style="font-size:12px;color:#ec4899;margin-top:8px;"><i class="fas fa-clock ml-1"></i>پایان آفر: <span class="cd" data-end="\${endDate.getTime()}"></span></div>`:''}
      </div>
    </div>`;
  }).join('');
  // Countdown
  setInterval(()=>{
    document.querySelectorAll('.cd').forEach(el=>{
      const diff=parseInt(el.dataset.end)-Date.now();
      if(diff<=0){el.textContent='منقضی شد';return;}
      const h=Math.floor(diff/3600000),m=Math.floor((diff%3600000)/60000),s=Math.floor((diff%60000)/1000);
      el.textContent=`\${h}:\${String(m).padStart(2,'0')}:\${String(s).padStart(2,'0')}`;
    });
  },1000);
})();
</script>
HTML;
        return str_replace(
            ['__VAR_COUNTDOWN__'],
            [$countdown],
            $__tpl
        );

        return str_replace(
            ['__VAR_COUNTDOWN__'],
            [$countdown],
            $__tpl
        );
    }

    private function renderFeatured(array $s): string
    {
        // Extract variables before heredoc to avoid ?? parse errors
        $showQr   = !empty($s['show_qr']) ? 'true' : 'false';
        $duration = (int)($s['duration'] ?? 15);

        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#080810;font-family:'Segoe UI',sans-serif;direction:rtl;display:flex;align-items:center;justify-content:center;overflow:hidden;" id="featured-wrap">
  <div style="color:#475569;position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:14px;"><i class="fas fa-circle-notch fa-spin" style="font-size:32px;margin-left:12px;color:#ec4899;"></i>در حال بارگذاری...</div>
</div>
<script>
(async function(){
  const SHOW_QR = __VAR_SHOWQR__;
  const DURATION = __VAR_DURATION__;
  const r=await fetch('/api/v1/retail/products?featured=1');
  const d=await r.json();
  const items=(d.data||[]).filter(i=>i.is_featured&&i.is_available);
  if(!items.length){
    document.getElementById('featured-wrap').innerHTML='<div style="color:#475569;text-align:center;"><i class="fas fa-star" style="font-size:48px;opacity:0.2;display:block;margin-bottom:12px;"></i>محصول ویژه‌ای ثبت نشده</div>';
    return;
  }
  let idx=0;
  function show(i){
    const p=items[i];
    const disc=p.old_price?Math.round((1-p.price/p.old_price)*100):0;
    const qrHtml=SHOW_QR&&p.barcode?`<div style="margin-top:24px;display:flex;align-items:center;gap:16px;"><img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=\\${encodeURIComponent(p.barcode)}&color=ec4899&bgcolor=080810" style="border-radius:10px;"><div style="font-size:13px;color:#64748b;">برای اطلاعات بیشتر<br>QR را اسکن کنید</div></div>`:'';
    const dots=Array.from({length:items.length},(_,j)=>`<div style="width:\\${j===i?'24px':'8px'};height:8px;border-radius:4px;background:\${j===i?'#ec4899':'rgba(255,255,255,0.2)'};transition:all 0.3s;"></div>`).join('');
    document.getElementById('featured-wrap').innerHTML=`
      <div style="display:flex;gap:80px;align-items:center;padding:60px;max-width:1600px;width:100%;animation:fadeIn 0.6s ease;">
        <div style="flex:1;text-align:center;">\${p.image?`<img src="\${p.image}" style="max-width:100%;max-height:500px;object-fit:contain;border-radius:24px;box-shadow:0 0 80px rgba(236,72,153,0.3);">`:`<div style="width:400px;height:400px;border-radius:24px;background:rgba(236,72,153,0.1);border:2px solid rgba(236,72,153,0.3);display:flex;align-items:center;justify-content:center;"><i class="fas fa-box" style="font-size:120px;color:rgba(236,72,153,0.3);"></i></div>`}</div>
        <div style="flex:1;">
          \${disc>0?`<div style="background:linear-gradient(135deg,#ec4899,#f97316);color:#fff;font-size:20px;font-weight:900;padding:8px 20px;border-radius:14px;display:inline-block;margin-bottom:16px;">-\${disc}% تخفیف ویژه</div>`:'<div style="color:#ec4899;font-size:16px;font-weight:700;margin-bottom:16px;letter-spacing:2px;">✦ محصول ویژه ✦</div>'}
          <div style="font-size:clamp(28px,4vw,52px);font-weight:900;color:#fff;line-height:1.2;">\${p.name}</div>
          \${p.name_en?`<div style="font-size:18px;color:#64748b;margin-top:6px;">\${p.name_en}</div>`:''}
          <div style="display:flex;align-items:baseline;gap:12px;margin-top:28px;">
            <span style="font-size:clamp(36px,6vw,72px);font-weight:900;color:#f59e0b;">\\${Number(p.price).toLocaleString()}</span>
            <span style="font-size:20px;color:#94a3b8;">\${p.currency||'تومان'}</span>
            \${p.old_price?`<span style="font-size:24px;color:#475569;text-decoration:line-through;">\\${Number(p.old_price).toLocaleString()}</span>`:''}
          </div>
          \${qrHtml}
          <div style="margin-top:24px;display:flex;gap:8px;">\${dots}</div>
        </div>
      </div>`;
  }
  show(0);
  if(items.length>1) setInterval(()=>{idx=(idx+1)%items.length;show(idx);},DURATION*1000);
})();
</script>
HTML;
        return str_replace(
            ['__VAR_SHOWQR__', '__VAR_DURATION__'],
            [$showQr, $duration],
            $__tpl
        );

        return str_replace(
            ['__VAR_SHOWQR__', '__VAR_DURATION__'],
            [$showQr, $duration],
            $__tpl
        );
    }


    private function renderQueue(array $s): string
    {
        $service = htmlspecialchars($s['service_name'] ?? 'باجه');
        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#080810;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:'Segoe UI',sans-serif;direction:rtl;gap:24px;">
  <div style="font-size:20px;color:#94a3b8;letter-spacing:4px;text-transform:uppercase;">QUEUE SYSTEM · سیستم نوبت‌دهی</div>
  <div style="background:rgba(236,72,153,0.1);border:2px solid rgba(236,72,153,0.4);border-radius:28px;padding:40px 80px;text-align:center;">
    <div style="font-size:18px;color:#ec4899;font-weight:600;margin-bottom:12px;">__VAR_SERVICE__</div>
    <div style="font-size:14px;color:#64748b;margin-bottom:8px;">نوبت در حال سرویس</div>
    <div id="queue-number" style="font-size:120px;font-weight:900;color:#fff;font-family:monospace;line-height:1;text-shadow:0 0 40px rgba(236,72,153,0.4);">—</div>
  </div>
  <div style="display:flex;gap:32px;">
    <div style="text-align:center;"><div style="font-size:12px;color:#475569;margin-bottom:4px;">در انتظار</div><div id="queue-waiting" style="font-size:28px;font-weight:700;color:#f59e0b;font-family:monospace;">—</div></div>
    <div style="width:1px;background:rgba(255,255,255,0.1);"></div>
    <div style="text-align:center;"><div style="font-size:12px;color:#475569;margin-bottom:4px;">ساعت</div><div id="q-clock" style="font-size:28px;font-weight:700;color:#94a3b8;font-family:monospace;"></div></div>
  </div>
</div>
<script>
(function(){
  setInterval(()=>{const e=document.getElementById('q-clock');if(e)e.textContent=new Date().toLocaleTimeString('fa-IR',{hour:"2-digit",minute:"2-digit"});},1000);
  async function load(){
    try{
      const r=await fetch('/api/v1/retail/queue?counter='+encodeURIComponent('__VAR_SERVICE__'));
      const d=await r.json();
      if(d.data){document.getElementById('queue-number').textContent=d.data.current||'—';document.getElementById('queue-waiting').textContent=d.data.waiting||0;}
    }catch(e){}
  }
  load();setInterval(load,5000);
})();
</script>
HTML;
        return str_replace(
            ['__VAR_SERVICE__'],
            [$service],
            $__tpl
        );

        return str_replace(
            ['__VAR_SERVICE__'],
            [$service],
            $__tpl
        );
    }

    public function getDashboardStats(): array { return ['products'=>(int)$this->db->value("SELECT COUNT(*) FROM retail_products WHERE tenant_id=? AND is_active=1",[$this->tenantId]),'offers'=>(int)$this->db->value("SELECT COUNT(*) FROM retail_products WHERE tenant_id=? AND is_offer=1 AND is_active=1",[$this->tenantId])]; }
}
