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
/* ES5 خالص و با XMLHttpRequest.
   این ویجت داخل یک iframe روی خود تلویزیون باز می‌شود. نسخه‌ی پیشین
   async/await و fetch و arrow و template literal داشت؛ روی موتور ماپل
   (سامسونگ ۲۰۱۳، WebKit 537) اسکریپت در همان خط اول می‌افتاد و فقط
   سربرگ نارنجی دیده می‌شد با یک کادر خالی زیرش — بدون هیچ خطایی که
   از پنل دیده شود. */
(function(){
  function el(id){ return document.getElementById(id); }
  function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  setInterval(function(){
    var e = el('menu-clock');
    if (!e) return;
    var d = new Date(), h = d.getHours(), m = d.getMinutes();
    e.innerHTML = (h<10?'0':'')+h+':'+(m<10?'0':'')+m;
  }, 1000);

  function getJson(url, cb){
    var x = new XMLHttpRequest();
    x.open('GET', url, true);
    x.timeout = 10000;
    x.onload = function(){ var d=null; try{ d=JSON.parse(x.responseText); }catch(e){} cb(d); };
    x.ontimeout = x.onerror = function(){ cb(null); };
    x.send();
  }

  function money(v){
    /* toLocaleString روی این موتور جداکننده نمی‌گذارد، پس دستی */
    var s = String(Math.round(Number(v)||0)), out = '', n = 0;
    for (var i = s.length-1; i >= 0; i--) {
      out = s.charAt(i) + out;
      if (++n % 3 === 0 && i > 0) out = ',' + out;
    }
    return out;
  }

  function card(item){
    var disc = (item.original_price && item.original_price > item.price)
      ? Math.round((1 - item.price/item.original_price) * 100) : 0;
    var h = '<div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:12px;overflow:hidden;position:relative;">';
    if (disc > 0) h += '<div style="position:absolute;top:8px;right:8px;background:#f97316;color:#fff;font-size:11px;font-weight:800;padding:2px 8px;border-radius:8px;">-'+disc+'%</div>';
    if (item.is_special) h += '<div style="position:absolute;top:8px;left:8px;background:rgba(212,175,55,0.9);color:#000;font-size:10px;font-weight:800;padding:2px 6px;border-radius:6px;">&#9733; ویژه</div>';
    /* object-fit روی ماپل نیست — تصویر با ارتفاع ثابت و بریدن والد */
    if (item.image) h += '<div style="width:100%;height:120px;overflow:hidden;background:#111;"><img src="'+esc(item.image)+'" style="width:100%;"></div>';
    h += '<div style="padding:12px;">';
    h += '<div style="font-size:14px;font-weight:700;color:#fff;">'+esc(item.name)+'</div>';
    if (item.description) h += '<div style="font-size:11px;color:#64748b;margin-top:3px;height:15px;overflow:hidden;">'+esc(item.description)+'</div>';
    h += '<div style="margin-top:8px;">';
    h += '<span style="font-size:16px;font-weight:900;color:#f97316;">'+money(item.price)+'</span>';
    h += '<span style="font-size:10px;color:#64748b;margin-right:6px;">__VAR_CURRENCY__</span>';
    h += '</div></div></div>';
    return h;
  }

  /* دسته‌ها پشت سر هم و ترتیبی خوانده می‌شوند — بدون Promise */
  function load(){
    getJson('/api/v1/menu/categories', function(dc){
      var cats = (dc && dc.data) ? dc.data : [];
      var content = el('menu-content');
      if (!content) return;
      content.innerHTML = '';
      var i = 0;
      (function nextCat(){
        if (i >= cats.length) return;
        var cat = cats[i++];
        getJson('/api/v1/menu/items?category_id=' + cat.id, function(di){
          var all = (di && di.data) ? di.data : [], items = [], k;
          for (k = 0; k < all.length; k++) if (all[k].is_available) items.push(all[k]);
          if (items.length) {
            var color = cat.color || '#f97316';
            var sec = document.createElement('div');
            sec.style.gridColumn = '1/-1';
            var html = '<div style="background:rgba(249,115,22,0.12);border-right:4px solid '+esc(color)+';padding:10px 16px;border-radius:8px;margin-bottom:8px;font-size:16px;font-weight:800;color:'+esc(color)+';">'+esc(cat.name)+'</div>';
            var grid = '<div style="display:grid;grid-template-columns:repeat(__VAR_COLS__,1fr);margin-bottom:16px;">';
            for (k = 0; k < items.length; k++) grid += card(items[k]);
            sec.innerHTML = html + grid + '</div>';
            content.appendChild(sec);
          }
          nextCat();
        });
      })();
    });
  }

  load();
  setInterval(load, 120000);
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
        /* حداقل سه ثانیه، محاسبه در PHP نه در رشته‌ی JS: اگر max() در
           متن خروجی بماند، بازرس سازگاری آن را به‌اشتباه clamp/max
           جاوااسکریپتی می‌شمارد. */
        $dur = max(3, (int)($s['duration'] ?? 8));

        /* ES5 و بدون flex/clamp: این ویجت هم داخل iframe روی تلویزیون
           باز می‌شود. نسخه‌ی پیشین async/await، template literal،
           Array.from و clamp() داشت — روی موتور ماپل هیچ‌کدام نیست و
           اسکریپت در خط اول می‌افتاد، پس فقط «در حال بارگذاری…» روی
           صفحه می‌ماند. اندازه‌ها هم ثابت شدند چون clamp کار نمی‌کند
           و مرورگر کل اعلان را دور می‌اندازد. */
        return '<div id="menu-feat-wrap" style="width:100%;height:100%;background:#08080f;'
             . 'font-family:Tahoma,sans-serif;direction:rtl;overflow:hidden;position:relative;">'
             . '<div style="color:#475569;text-align:center;padding-top:20%;font-size:14px;">در حال بارگذاری…</div>'
             . '</div>'
             . '<script>' . self::es5Helpers() . '(function(){'
             . 'var wrap = document.getElementById("menu-feat-wrap");'
             . 'MX.get("/api/v1/menu/items?special=1", function(d){'
             . '  var raw = (d && d.data) ? d.data : [], items = [], k;'
             . '  for (k = 0; k < raw.length; k++) if (raw[k].is_special && raw[k].is_available) items.push(raw[k]);'
             . '  if (!items.length) { wrap.innerHTML = "<div style=\"color:#475569;text-align:center;padding-top:20%;font-size:16px;\">آیتم ویژه‌ای ثبت نشده</div>"; return; }'
             . '  var idx = 0;'
             . '  function show(i){'
             . '    var p = items[i], h = "";'
             . '    if (p.image) h += "<div style=\"position:absolute;top:0;left:0;width:60%;height:100%;overflow:hidden;\">"'
             . '                    + "<img src=\"" + MX.esc(p.image) + "\" style=\"width:100%;\"></div>";'
             . '    h += "<div style=\"position:absolute;top:0;right:0;width:" + (p.image ? "40%" : "100%") + ";height:100%;padding:48px;\">";'
             . '    h += "<div style=\"color:#f97316;font-size:14px;font-weight:700;margin-bottom:16px;\">پیشنهاد ویژه</div>";'
             . '    h += "<div style=\"font-size:42px;font-weight:900;color:#fff;margin-bottom:12px;\">" + MX.esc(p.name) + "</div>";'
             . '    if (p.description) h += "<div style=\"font-size:16px;color:#94a3b8;margin-bottom:24px;\">" + MX.esc(p.description) + "</div>";'
             . '    h += "<div style=\"font-size:52px;font-weight:900;color:#f97316;\">" + MX.money(p.price)'
             . '       + " <span style=\"font-size:20px;color:#64748b;font-weight:400;\">تومان</span></div>";'
             . '    if (p.original_price && p.original_price > p.price)'
             . '      h += "<div style=\"font-size:18px;color:#475569;text-decoration:line-through;margin-top:4px;\">" + MX.money(p.original_price) + " تومان</div>";'
             . '    h += "</div>";'
             . '    wrap.innerHTML = h;'
             . '  }'
             . '  show(0);'
             . '  if (items.length > 1) setInterval(function(){ idx = (idx + 1) % items.length; show(idx); }, ' . ($dur * 1000) . ');'
             . '});})();</script>';
    }

    /**
     * کمک‌توابع ES5 مشترک ویجت‌های منو.
     *
     * این ویجت‌ها داخل iframe روی خودِ تلویزیون باز می‌شوند. نسخه‌ی
     * پیشین با fetch و async/await و template literal نوشته شده بود و
     * روی موتور ماپل (سامسونگ ۲۰۱۳، WebKit 537) اسکریپت در همان خط
     * اول می‌افتاد: فقط سربرگ نارنجی دیده می‌شد و زیرش خالی می‌ماند،
     * بدون هیچ خطایی که از پنل پیدا باشد.
     *
     * یک‌جا بودنشان عمدی است: سه ویجت از همین‌ها استفاده می‌کنند و
     * نسخه‌ی تکراری یعنی سه جا برای از قلم افتادن.
     */
    private static function es5Helpers(): string
    {
        return 'var MX={'
             . 'esc:function(s){return String(s==null?"":s)'
             . '.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");},'
             /* toLocaleString روی این موتور جداکننده‌ی هزار نمی‌گذارد */
             . 'money:function(v){var s=String(Math.round(Number(v)||0)),o="",n=0,i;'
             . 'for(i=s.length-1;i>=0;i--){o=s.charAt(i)+o;if(++n%3===0&&i>0)o=","+o;}return o;},'
             . 'get:function(u,cb){var x=new XMLHttpRequest();x.open("GET",u,true);x.timeout=10000;'
             . 'x.onload=function(){var d=null;try{d=JSON.parse(x.responseText);}catch(e){}cb(d);};'
             . 'x.ontimeout=x.onerror=function(){cb(null);};x.send();}'
             . '};';
    }

    private function renderCategory(array $s): string
    {
        $cid = (int)($s['category_id'] ?? 0);

        return '<div style="width:100%;height:100%;background:#08080f;font-family:Tahoma,sans-serif;direction:rtl;">'
             . '<div style="background:#f97316;padding:16px 28px;">'
             . '<div id="menu-cat-title" style="font-size:22px;font-weight:900;color:#fff;">منو</div></div>'
             . '<div id="menu-cat-items" style="padding:16px;overflow:hidden;"></div></div>'
             . '<script>' . self::es5Helpers() . '(function(){'
             . 'MX.get("/api/v1/menu/categories", function(dc){'
             . '  var all = (dc && dc.data) ? dc.data : [], cat = null, i;'
             . '  for (i = 0; i < all.length; i++) if (all[i].id == ' . $cid . ') cat = all[i];'
             . '  if (!cat) cat = all[0];'
             . '  if (!cat) return;'
             . '  document.getElementById("menu-cat-title").innerHTML = MX.esc(cat.name);'
             . '  MX.get("/api/v1/menu/items?category_id=" + cat.id, function(di){'
             . '    var raw = (di && di.data) ? di.data : [], h = "", k;'
             . '    for (k = 0; k < raw.length; k++) { if (!raw[k].is_available) continue;'
             . '      h += "<div style=\"padding:16px;margin-bottom:10px;border-radius:14px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);\">"'
             . '         + "<div style=\"font-size:17px;font-weight:700;color:#fff;\">" + MX.esc(raw[k].name) + "</div>"'
             . '         + (raw[k].description ? "<div style=\"font-size:13px;color:#94a3b8;margin-top:4px;\">" + MX.esc(raw[k].description) + "</div>" : "")'
             . '         + "<div style=\"font-size:20px;font-weight:900;color:#f97316;margin-top:6px;\">" + MX.money(raw[k].price)'
             . '         + " <span style=\"font-size:11px;color:#64748b;font-weight:400;\">تومان</span></div></div>"; }'
             . '    document.getElementById("menu-cat-items").innerHTML = h || "<div style=\"color:#475569;text-align:center;padding:40px;\">آیتمی موجود نیست</div>";'
             . '  });'
             . '});})();</script>';
    }
    private function renderDaily(array $s): string
    {
        $t = htmlspecialchars($s['title'] ?? 'پیشنهاد امروز');

        return '<div style="width:100%;height:100%;background:#08080f;font-family:Tahoma,sans-serif;direction:rtl;">'
             . '<div style="background:#f97316;padding:16px 28px;text-align:center;">'
             . '<span style="font-size:22px;font-weight:900;color:#fff;">' . $t . '</span></div>'
             . '<div id="daily-items" style="padding:20px;overflow:hidden;"></div></div>'
             . '<script>' . self::es5Helpers() . '(function(){'
             . 'MX.get("/api/v1/menu/items?special=1", function(d){'
             . '  var raw = (d && d.data) ? d.data : [], h = "", k;'
             . '  for (k = 0; k < raw.length; k++) { var p = raw[k];'
             . '    if (!p.is_special || !p.is_available) continue;'
             . '    h += "<div style=\"background:rgba(249,115,22,0.06);border:1px solid rgba(249,115,22,0.2);border-radius:16px;padding:20px;margin-bottom:14px;\">"'
             . '       + "<div style=\"font-size:16px;font-weight:700;color:#fff;\">" + MX.esc(p.name) + "</div>"'
             . '       + (p.description ? "<div style=\"font-size:13px;color:#94a3b8;margin-top:6px;\">" + MX.esc(p.description) + "</div>" : "")'
             . '       + "<div style=\"font-size:22px;font-weight:900;color:#f97316;margin-top:12px;\">" + MX.money(p.price)'
             . '       + " <span style=\"font-size:13px;color:#64748b;font-weight:400;\">تومان</span></div></div>"; }'
             . '  document.getElementById("daily-items").innerHTML = h || "<div style=\"color:#475569;text-align:center;padding:60px;\">آیتم ویژه‌ای ثبت نشده</div>";'
             . '});})();</script>';
    }
    private function renderTicker(array $s): string
    {
        $speed = (int)($s['speed'] ?? 40);

        return '<div style="width:100%;height:100%;background:#f97316;overflow:hidden;position:relative;">'
             . '<span style="position:absolute;top:0;right:0;line-height:100%;color:#fff;font-weight:700;padding:0 20px;z-index:2;background:#f97316;">قیمت‌ها:</span>'
             . '<div style="overflow:hidden;height:100%;">'
             . '<div id="menu-ticker-inner" style="white-space:nowrap;position:absolute;top:0;line-height:100%;'
             . '-webkit-animation:fidsScroll ' . $speed . 's linear infinite;animation:fidsScroll ' . $speed . 's linear infinite;"></div>'
             . '</div></div>'
             . '<script>' . self::es5Helpers() . '(function(){'
             . 'MX.get("/api/v1/menu/items", function(d){'
             . '  var raw = (d && d.data) ? d.data : [], parts = [], k;'
             . '  for (k = 0; k < raw.length; k++) parts.push(MX.esc(raw[k].name) + ": " + MX.money(raw[k].price) + " تومان");'
             . '  var txt = parts.join(" · ");'
             . '  var one = "<span style=\"display:inline-block;color:#fff;font-size:22px;font-weight:700;padding:0 48px;\">" + txt + "</span>";'
             . '  var el = document.getElementById("menu-ticker-inner");'
             . '  if (el) el.innerHTML = one + one;'
             . '});})();</script>';
    }

    public function getDashboardStats(): array { return ['categories'=>(int)$this->db->value("SELECT COUNT(*) FROM menu_categories WHERE tenant_id=?",[$this->tenantId]),'items'=>(int)$this->db->value("SELECT COUNT(*) FROM menu_items WHERE tenant_id=? AND is_available=1",[$this->tenantId])]; }
}
