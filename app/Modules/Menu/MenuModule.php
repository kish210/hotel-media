<?php
declare(strict_types=1);
namespace App\Modules\Menu;
use App\Modules\Core\BaseModule;

class MenuModule extends BaseModule
{
    public function id(): string          { return 'menu'; }
    public function name(): string        { return 'منوی رستوران'; }
    public function nameEn(): string      { return 'Restaurant Menu Board'; }
    public function description(): string { return 'منوی دیجیتال رستوران با قیمت‌های پویا، تصاویر، آیتم‌های ویژه و تایمر'; }
    public function version(): string     { return '2.0.0'; }
    public function icon(): string        { return 'fas fa-utensils'; }
    public function color(): string       { return '#f97316'; }
    public function category(): string    { return 'hospitality'; }

    public function zoneTypes(): array
    {
        return [
            ['id'=>'menu_full',     'label'=>'منوی کامل',        'icon'=>'fas fa-book-open',   'defaultSize'=>['w'=>1920,'h'=>1080],
             'settings'=>[['key'=>'cols','label'=>'ستون','type'=>'number','default'=>3],['key'=>'show_images','label'=>'نمایش تصویر','type'=>'bool','default'=>true],['key'=>'show_specials','label'=>'آیتم ویژه','type'=>'bool','default'=>true],['key'=>'currency','label'=>'واحد','type'=>'text','default'=>'تومان']]],
            ['id'=>'menu_category', 'label'=>'یک دسته منو',      'icon'=>'fas fa-list',        'defaultSize'=>['w'=>1920,'h'=>1080],
             'settings'=>[['key'=>'category_id','label'=>'دسته','type'=>'number','default'=>0]]],
            ['id'=>'menu_featured', 'label'=>'آیتم‌های ویژه',   'icon'=>'fas fa-star',        'defaultSize'=>['w'=>1920,'h'=>600],
             'settings'=>[['key'=>'duration','label'=>'مدت نمایش (ثانیه)','type'=>'number','default'=>8]]],
            ['id'=>'menu_daily',    'label'=>'منوی روز',         'icon'=>'fas fa-sun',         'defaultSize'=>['w'=>1920,'h'=>1080],
             'settings'=>[['key'=>'title','label'=>'عنوان','type'=>'text','default'=>'پیشنهاد امروز']]],
            ['id'=>'menu_ticker',   'label'=>'تیکر قیمت',        'icon'=>'fas fa-text-width',  'defaultSize'=>['w'=>1920,'h'=>80],
             'settings'=>[['key'=>'speed','label'=>'سرعت اسکرول','type'=>'number','default'=>40]]],
        ];
    }

    public function migrations(): array { return []; } // uses existing menu tables

    public function renderPlayerWidget(string $zoneType, array $settings = []): string
    {
        return match($zoneType) {
            'menu_full'     => $this->renderFullMenu($settings),
            'menu_category' => $this->renderCategory($settings),
            'menu_featured' => $this->renderFeatured($settings),
            'menu_daily'    => $this->renderDaily($settings),
            'menu_ticker'   => $this->renderTicker($settings),
            default         => '<div>نامعتبر</div>',
        };
    }

    private function renderFullMenu(array $s): string
    {
        $cols     = max(1, min(4, (int)($s['cols'] ?? 3)));
        $currency = htmlspecialchars($s['currency'] ?? 'تومان');
        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#08080f;font-family:'Segoe UI',Tahoma,sans-serif;direction:rtl;overflow:hidden;display:flex;flex-direction:column;">
  <div style="background:linear-gradient(135deg,#f97316,#c2570b);padding:16px 28px;display:flex;align-items:center;gap:12px;">
    <i class="fas fa-utensils" style="font-size:22px;color:#fff;"></i>
    <div style="font-size:22px;font-weight:900;color:#fff;">منوی ما</div>
    <div id="menu-clock" style="margin-right:auto;font-size:20px;font-weight:700;color:#fff;font-family:monospace;"></div>
  </div>
  <div id="menu-content" style="flex:1;overflow-y:auto;padding:16px;scrollbar-width:none;display:grid;grid-template-columns:repeat(__VAR_COLS__,1fr);gap:12px;align-content:start;"></div>
</div>
<script>
(function(){
  setInterval(()=>{const e=document.getElementById('menu-clock');if(e)e.textContent=new Date().toLocaleTimeString('fa-IR',{hour:"2-digit",minute:"2-digit"});},1000);
  async function load(){
    const rc=await fetch('/api/v1/menu/categories');
    const dc=await rc.json();
    const cats=dc.data||[];
    const content=document.getElementById('menu-content');
    content.innerHTML='';
    for(const cat of cats){
      const ri=await fetch('/api/v1/menu/items?category_id='+cat.id);
      const di=await ri.json();
      const items=(di.data||[]).filter(i=>i.is_available);
      if(!items.length) continue;
      const section=document.createElement('div');
      section.style.cssText='grid-column:1/-1;';
      section.innerHTML=`<div style="background:rgba(249,115,22,0.12);border-right:4px solid \\${cat.color||'#f97316'};padding:10px 16px;border-radius:8px;margin-bottom:8px;font-size:16px;font-weight:800;color:\\${cat.color||'#f97316'};">\\${cat.name}</div>`;
      const grid=document.createElement('div');
      grid.style.cssText=`display:grid;grid-template-columns:repeat(__VAR_COLS__,1fr);gap:10px;margin-bottom:16px;`;
      grid.innerHTML=items.map(item=>{
        const disc=item.original_price&&item.original_price>item.price?Math.round((1-item.price/item.original_price)*100):0;
        return `<div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:12px;overflow:hidden;position:relative;">
          \${disc>0?`<div style="position:absolute;top:8px;right:8px;background:linear-gradient(135deg,#f97316,#c2570b);color:#fff;font-size:11px;font-weight:800;padding:2px 8px;border-radius:8px;">-\${disc}%</div>`:''}
          \${item.is_special?`<div style="position:absolute;top:8px;left:8px;background:rgba(212,175,55,0.9);color:#000;font-size:10px;font-weight:800;padding:2px 6px;border-radius:6px;">★ ویژه</div>`:''}
          \${item.image?`<img src="\${item.image}" style="width:100%;height:120px;object-fit:cover;">`:''}
          <div style="padding:12px;">
            <div style="font-size:14px;font-weight:700;color:#fff;">\${item.name}</div>
            \${item.description?`<div style="font-size:11px;color:#64748b;margin-top:3px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;">\${item.description}</div>`:''}
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-top:8px;">
              <span style="font-size:16px;font-weight:900;color:#f97316;">\${Number(item.price).toLocaleString()}</span>
              <span style="font-size:10px;color:#64748b;">__VAR_CURRENCY__</span>
            </div>
          </div>
        </div>`;
      }).join('');
      section.appendChild(grid);
      content.appendChild(section);
    }
  }
  load();setInterval(load,120000);
})();
</script>
HTML;
        return str_replace(
            ['__VAR_COLS__', '__VAR_CURRENCY__'],
            [$cols, $currency],
            $__tpl
        );

        return str_replace(
            ['__VAR_COLS__', '__VAR_CURRENCY__'],
            [$cols, $currency],
            $__tpl
        );
    }

    private function renderFeatured(array $s): string
    {
        $dur = (int)($s['duration'] ?? 8);
        $__tpl = <<<'HTML'
<div style="width:100%;height:100%;background:#08080f;font-family:'Segoe UI',sans-serif;direction:rtl;overflow:hidden;position:relative;" id="menu-feat-wrap">
  <div style="color:#475569;position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:14px;">در حال بارگذاری...</div>
</div>
<script>
(async function(){
  const r=await fetch('/api/v1/menu/items?special=1');
  const d=await r.json();
  const items=(d.data||[]).filter(i=>i.is_special&&i.is_available);
  if(!items.length){document.getElementById('menu-feat-wrap').innerHTML='<div style="color:#475569;display:flex;align-items:center;justify-content:center;height:100%;font-size:16px;"><i class="fas fa-star" style="margin-left:8px;color:#f97316;"></i>آیتم ویژه‌ای ثبت نشده</div>';return;}
  let idx=0;
  function show(i){
    const p=items[i];
    document.getElementById('menu-feat-wrap').innerHTML=`
      <div style="display:flex;height:100%;gap:0;animation:fadeIn 0.6s ease;">
        \${p.image?`<div style="flex:1;background:url('\\${p.image}') center/cover no-repeat;position:relative;"><div style="position:absolute;inset:0;background:linear-gradient(90deg,transparent 40%,#08080f);"></div></div>`:''}
        <div style="width:\\${p.image?'40%':'100%'};padding:48px;display:flex;flex-direction:column;justify-content:center;">
          <div style="color:#f97316;font-size:14px;font-weight:700;letter-spacing:3px;margin-bottom:16px;">✦ پیشنهاد ویژه ✦</div>
          <h2 style="font-size:clamp(28px,4vw,52px);font-weight:900;color:#fff;margin:0 0 12px;">\${p.name}</h2>
          \${p.description?`<p style="font-size:16px;color:#94a3b8;margin:0 0 24px;line-height:1.7;">\${p.description}</p>`:''}
          <div style="font-size:clamp(36px,5vw,64px);font-weight:900;color:#f97316;">\\${Number(p.price).toLocaleString()} <span style="font-size:20px;color:#64748b;font-weight:400;">تومان</span></div>
          \${p.original_price&&p.original_price>p.price?`<div style="font-size:18px;color:#475569;text-decoration:line-through;margin-top:4px;">\${Number(p.original_price).toLocaleString()} تومان</div>`:''}
          <div style="margin-top:24px;display:flex;gap:8px;">\${Array.from({length:items.length},(_,j)=>`<div style="width:\${j===i?'24px':'8px'};height:8px;border-radius:4px;background:\${j===i?'#f97316':'rgba(255,255,255,0.2)'};transition:all 0.3s;"></div>`).join('')}</div>
        </div>
      </div>`;
  }
  show(0);
  setInterval(()=>{idx=(idx+1)%items.length;show(idx);},(parseInt('__VAR_DUR__')||8)*1000);
})();
</script>
HTML;
        return str_replace(
            ['__VAR_DUR__'],
            [$dur],
            $__tpl
        );

        return str_replace(
            ['__VAR_DUR__'],
            [$dur],
            $__tpl
        );
    }

    private function renderCategory(array $s): string { $cid=(int)($s['category_id']??0); return "<div style=\"width:100%;height:100%;background:#08080f;font-family:'Segoe UI',sans-serif;direction:rtl;display:flex;flex-direction:column;\"><div style=\"background:linear-gradient(135deg,#f97316,#c2570b);padding:16px 28px;\"><div id=\"menu-cat-title\" style=\"font-size:22px;font-weight:900;color:#fff;\">منو</div></div><div id=\"menu-cat-items\" style=\"flex:1;overflow-y:auto;padding:16px;scrollbar-width:none;\"></div></div><script>(async function(){const rc=await fetch('/api/v1/menu/categories');const dc=await rc.json();const cat=(dc.data||[]).find(c=>c.id=={$cid})||(dc.data||[])[0];if(!cat)return;document.getElementById('menu-cat-title').textContent=cat.name;const ri=await fetch('/api/v1/menu/items?category_id='+cat.id);const di=await ri.json();const items=(di.data||[]).filter(i=>i.is_available);document.getElementById('menu-cat-items').innerHTML=items.map(item=>`<div style=\"display:flex;gap:16px;padding:16px;margin-bottom:10px;border-radius:14px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);\"><div style=\"flex:1;\"><div style=\"font-size:17px;font-weight:700;color:#fff;\">\${item.name}</div>\${item.description?`<div style=\"font-size:13px;color:#94a3b8;margin-top:4px;\">\${item.description}</div>`:''}</div><div style=\"text-align:left;\"><div style=\"font-size:20px;font-weight:900;color:#f97316;\">\${Number(item.price).toLocaleString()}</div><div style=\"font-size:11px;color:#64748b;\">تومان</div></div></div>`).join('')||'<div style=\"color:#475569;text-align:center;padding:40px;\">آیتمی موجود نیست</div>';})();</script>"; }
    private function renderDaily(array $s): string { $t=htmlspecialchars($s['title']??'پیشنهاد امروز'); return "<div style=\"width:100%;height:100%;background:#08080f;font-family:'Segoe UI',sans-serif;direction:rtl;display:flex;flex-direction:column;\"><div style=\"background:linear-gradient(135deg,#f97316,#c2570b);padding:16px 28px;text-align:center;\"><i class=\"fas fa-sun\" style=\"font-size:20px;color:#fff;margin-left:8px;\"></i><span style=\"font-size:22px;font-weight:900;color:#fff;\">{$t}</span></div><div id=\"daily-items\" style=\"flex:1;overflow-y:auto;padding:20px;scrollbar-width:none;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;align-content:start;\"></div></div><script>(async function(){const r=await fetch('/api/v1/menu/items?special=1');const d=await r.json();const items=(d.data||[]).filter(i=>i.is_special&&i.is_available);const el=document.getElementById('daily-items');el.innerHTML=items.map(p=>`<div style=\"background:rgba(249,115,22,0.06);border:1px solid rgba(249,115,22,0.2);border-radius:16px;padding:20px;\"><div style=\"font-size:16px;font-weight:700;color:#fff;\">\${p.name}</div>\${p.description?`<div style=\"font-size:13px;color:#94a3b8;margin-top:6px;\">\${p.description}</div>`:''}<div style=\"font-size:22px;font-weight:900;color:#f97316;margin-top:12px;\">\${Number(p.price).toLocaleString()} <span style=\"font-size:13px;color:#64748b;font-weight:400;\">تومان</span></div></div>`).join('')||'<div style=\"color:#475569;text-align:center;grid-column:1/-1;padding:60px;\">آیتم ویژه‌ای ثبت نشده</div>';})();</script>"; }
    private function renderTicker(array $s): string { $speed=(int)($s['speed']??40); return "<div style=\"width:100%;height:100%;background:#f97316;display:flex;align-items:center;overflow:hidden;\"><span style=\"white-space:nowrap;color:#fff;font-weight:700;padding:0 20px;flex-shrink:0;\"><i class=\"fas fa-utensils ml-2\"></i>قیمت‌ها:</span><div style=\"overflow:hidden;flex:1;\"><div id=\"menu-ticker-inner\" style=\"white-space:nowrap;animation:fidsScroll {$speed}s linear infinite;\"></div></div></div><script>(async function(){const r=await fetch('/api/v1/menu/items');const d=await r.json();const items=d.data||[];const txt=items.map(i=>i.name+': '+Number(i.price).toLocaleString()+' تومان').join(' · ');const el=document.getElementById('menu-ticker-inner');el.innerHTML=[txt,txt].map(t=>`<span style=\"display:inline-block;color:#fff;font-size:22px;font-weight:700;padding:0 48px;\">\${t}</span>`).join('');})();</script>"; }

    public function getDashboardStats(): array { return ['categories'=>(int)$this->db->value("SELECT COUNT(*) FROM menu_categories WHERE tenant_id=?",[$this->tenantId]),'items'=>(int)$this->db->value("SELECT COUNT(*) FROM menu_items WHERE tenant_id=? AND is_available=1",[$this->tenantId])]; }
}
