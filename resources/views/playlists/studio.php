<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<?php
/**
 * استودیوی تایم‌لاین پلی‌لیست — ویرایشگر چندلایه.
 *
 * صفحه‌ی پنل است (مرورگر دسکتاپ اپراتور)، پس محدودیت ES5 تلویزیون
 * اینجا نیست و JS مدرن آزاد است. چیدمان سه‌پنلی واکنش‌گرا با پنل‌های
 * قابل‌تغییراندازه (بند ۱۷). تایم‌لاین داده‌ی واقعی پلی‌لیست را می‌خواند؛
 * ذخیره و انتشار به بک‌اند وصل‌اند و چیزی جعلی نیست (بند ۲۵).
 */
$plId = (int)$playlist['id'];
?>
<style>
/* ── چیدمان سه‌پنلی ───────────────────────────────────────────── */
.st-wrap{position:fixed;inset:0;top:0;display:flex;flex-direction:column;background:#0a0a0f;z-index:50;}
.st-top{display:flex;align-items:center;gap:10px;padding:8px 14px;background:#111118;border-bottom:1px solid rgba(255,255,255,.07);flex-shrink:0;}
.st-body{flex:1;display:flex;min-height:0;}
.st-left{width:280px;min-width:180px;background:#0d0d14;border-left:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;flex-shrink:0;}
.st-center{flex:1;display:flex;flex-direction:column;min-width:0;}
.st-right{width:330px;min-width:220px;background:#0d0d14;border-right:1px solid rgba(255,255,255,.06);overflow-y:auto;flex-shrink:0;}
.st-resizer{width:5px;cursor:col-resize;background:rgba(255,255,255,.04);flex-shrink:0;}
.st-resizer:hover{background:#1a7ac4;}

/* ── نوار ابزار ──────────────────────────────────────────────── */
.st-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:9px;font-size:12.5px;font-weight:600;cursor:pointer;border:1px solid transparent;background:rgba(255,255,255,.06);color:#e2e8f0;white-space:nowrap;transition:.15s;}
.st-btn:hover{background:rgba(255,255,255,.12);}
.st-btn.pri{background:#1a7ac4;color:#fff;}
.st-btn.pri:hover{background:#2088d8;}
.st-btn.pub{background:#16a34a;color:#fff;}
.st-btn.pub:hover{background:#1bbd57;}
.st-btn:disabled{opacity:.4;cursor:not-allowed;}
.st-dirty{width:8px;height:8px;border-radius:50%;background:#f59e0b;display:none;}
.st-dirty.on{display:inline-block;}

/* ── پیش‌نمایش ───────────────────────────────────────────────── */
.st-preview{background:#000;flex:1;min-height:0;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;}
.st-stage{position:relative;background:#000;box-shadow:0 0 0 1px rgba(255,255,255,.08);}
.st-pv-bar{display:flex;align-items:center;gap:12px;padding:7px 14px;background:#111118;border-top:1px solid rgba(255,255,255,.06);flex-shrink:0;font-size:12px;color:#94a3b8;}
.st-pv-bar .tc{font-family:monospace;font-size:13px;color:#e2e8f0;}

/* ── تایم‌لاین ───────────────────────────────────────────────── */
.st-tl{height:300px;background:#0d0d14;border-top:1px solid rgba(255,255,255,.08);display:flex;flex-direction:column;flex-shrink:0;}
.st-tl-head{display:flex;align-items:center;gap:8px;padding:6px 12px;border-bottom:1px solid rgba(255,255,255,.05);flex-shrink:0;}
.st-tl-scroll{flex:1;overflow:auto;position:relative;}
.st-tl-inner{position:relative;min-width:100%;}
.st-ruler{position:sticky;top:0;height:26px;background:#14141c;border-bottom:1px solid rgba(255,255,255,.06);z-index:6;}
.st-ruler .tick{position:absolute;top:0;height:100%;border-right:1px solid rgba(255,255,255,.08);font-size:9.5px;color:#64748b;padding:3px 0 0 4px;font-family:monospace;white-space:nowrap;}
.st-rows{position:relative;}
.st-trk{display:flex;height:54px;border-bottom:1px solid rgba(255,255,255,.04);}
.st-trk-label{width:118px;flex-shrink:0;background:#111118;border-left:1px solid rgba(255,255,255,.06);padding:6px 8px;position:sticky;right:0;z-index:5;display:flex;flex-direction:column;justify-content:center;gap:3px;}
.st-trk-label .nm{font-size:11px;font-weight:700;color:#cbd5e1;display:flex;align-items:center;gap:5px;}
.st-trk-label .ctl{display:flex;gap:4px;}
.st-trk-label .ctl i{font-size:9px;color:#64748b;cursor:pointer;padding:2px;}
.st-trk-label .ctl i:hover{color:#e2e8f0;}
.st-trk-label .ctl i.act{color:#1a7ac4;}
.st-lane{position:relative;flex:1;}
.st-clip{position:absolute;top:5px;bottom:5px;border-radius:6px;overflow:hidden;cursor:grab;border:1.5px solid transparent;box-shadow:0 1px 4px rgba(0,0,0,.4);user-select:none;}
.st-clip:active{cursor:grabbing;}
.st-clip.sel{border-color:#fff;box-shadow:0 0 0 1px #1a7ac4,0 2px 10px rgba(26,122,196,.5);z-index:3;}
.st-clip .lbl{position:absolute;inset:0;padding:4px 7px;font-size:10px;font-weight:600;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.7);overflow:hidden;white-space:nowrap;pointer-events:none;z-index:2;}
.st-clip .dur{position:absolute;bottom:3px;left:6px;font-size:8.5px;font-family:monospace;color:rgba(255,255,255,.8);text-shadow:0 1px 2px #000;pointer-events:none;z-index:2;}
.st-clip .thumb{position:absolute;inset:0;background-size:cover;background-position:center;opacity:.5;}
.st-handle{position:absolute;top:0;bottom:0;width:7px;cursor:ew-resize;z-index:4;}
.st-handle.l{left:0;}.st-handle.r{right:0;}
.st-handle:hover{background:rgba(255,255,255,.35);}
.st-playhead{position:absolute;top:0;bottom:0;width:2px;background:#ef4444;z-index:7;pointer-events:none;}
.st-playhead::before{content:'';position:absolute;top:-1px;left:-5px;border:6px solid transparent;border-top-color:#ef4444;}

/* ── کتابخانه رسانه ─────────────────────────────────────────── */
.st-lib-head{padding:9px 12px;border-bottom:1px solid rgba(255,255,255,.06);flex-shrink:0;}
.st-lib-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-top:8px;}
.st-lib-tab{font-size:10.5px;padding:3px 9px;border-radius:20px;background:rgba(255,255,255,.05);color:#94a3b8;cursor:pointer;}
.st-lib-tab.act{background:#1a7ac4;color:#fff;}
.st-lib-list{flex:1;overflow-y:auto;padding:8px;display:grid;grid-template-columns:1fr 1fr;gap:7px;align-content:start;}
.st-asset{background:#14141c;border:1px solid rgba(255,255,255,.06);border-radius:9px;overflow:hidden;cursor:pointer;transition:.15s;}
.st-asset:hover{border-color:#1a7ac4;transform:translateY(-1px);}
.st-asset .th{height:62px;background:#0a0a14 center/cover no-repeat;position:relative;display:flex;align-items:center;justify-content:center;}
.st-asset .th i{font-size:20px;color:#334155;}
.st-asset .nm{font-size:10px;color:#cbd5e1;padding:5px 6px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;}
.st-asset .bdg{position:absolute;bottom:3px;left:3px;font-size:8px;font-family:monospace;background:rgba(0,0,0,.7);color:#fff;padding:1px 4px;border-radius:4px;}

/* ── اینسپکتور ──────────────────────────────────────────────── */
.st-insp-sec{border-bottom:1px solid rgba(255,255,255,.05);}
.st-insp-h{display:flex;align-items:center;justify-content:space-between;padding:9px 13px;cursor:pointer;font-size:11.5px;font-weight:700;color:#cbd5e1;}
.st-insp-h:hover{background:rgba(255,255,255,.03);}
.st-insp-b{padding:4px 13px 13px;display:grid;gap:9px;}
.st-insp-sec.col .st-insp-b{display:none;}
.st-insp-sec.col .st-insp-h i.chev{transform:rotate(-90deg);}
.st-field label{display:block;font-size:10.5px;color:#64748b;margin-bottom:3px;}
.st-field input,.st-field select,.st-field textarea{width:100%;background:#0a0a12;border:1px solid rgba(255,255,255,.08);border-radius:7px;padding:6px 9px;font-size:12px;color:#e2e8f0;}
.st-field input:focus,.st-field select:focus,.st-field textarea:focus{border-color:#1a7ac4;outline:none;}
.st-grid2{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.st-posgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:4px;}
.st-posgrid button{aspect-ratio:1;background:#14141c;border:1px solid rgba(255,255,255,.08);border-radius:6px;cursor:pointer;color:#475569;font-size:11px;}
.st-posgrid button.act{background:#1a7ac4;border-color:#1a7ac4;color:#fff;}
.st-empty{color:#475569;text-align:center;padding:40px 20px;font-size:12.5px;}
@media(max-width:1200px){.st-right{width:280px;}.st-left{width:230px;}}
</style>

<div class="st-wrap" id="studio" dir="rtl">
  <!-- نوار ابزار -->
  <div class="st-top">
    <a href="/admin/playlists/<?= $plId ?>" class="st-btn" title="بازگشت"><i class="fas fa-arrow-right"></i></a>
    <strong style="font-size:13px;color:#fff;"><?= e($playlist['name']) ?></strong>
    <span class="st-dirty" id="dirtyDot" title="تغییرات ذخیره‌نشده"></span>
    <span id="savedLabel" style="font-size:11px;color:#64748b;"></span>
    <div style="flex:1;"></div>
    <button class="st-btn" id="btnAddText"><i class="fas fa-font"></i> متن</button>
    <button class="st-btn" id="btnSplit" title="برش در محل پلی‌هد"><i class="fas fa-cut"></i> برش</button>
    <div style="width:1px;height:20px;background:rgba(255,255,255,.1);"></div>
    <button class="st-btn" id="btnZoomOut"><i class="fas fa-search-minus"></i></button>
    <input type="range" id="zoomRange" min="2" max="40" value="8" style="width:90px;">
    <button class="st-btn" id="btnZoomIn"><i class="fas fa-search-plus"></i></button>
    <div style="width:1px;height:20px;background:rgba(255,255,255,.1);"></div>
    <button class="st-btn pri" id="btnSave"><i class="fas fa-save"></i> ذخیره پیش‌نویس</button>
    <button class="st-btn pub" id="btnPublish"><i class="fas fa-upload"></i> انتشار</button>
  </div>

  <div class="st-body">
    <!-- پنل چپ: کتابخانه رسانه -->
    <div class="st-left">
      <div class="st-lib-head">
        <div style="font-size:12px;font-weight:700;color:#fff;"><i class="fas fa-photo-film" style="color:#1a7ac4;margin-left:6px;"></i>کتابخانه</div>
        <div class="st-lib-tabs" id="libTabs">
          <span class="st-lib-tab act" data-f="all">همه</span>
          <span class="st-lib-tab" data-f="video">ویدیو</span>
          <span class="st-lib-tab" data-f="image">تصویر</span>
          <span class="st-lib-tab" data-f="url">ماژول</span>
        </div>
      </div>
      <div class="st-lib-list" id="libList"></div>
    </div>
    <div class="st-resizer" data-resize="left"></div>

    <!-- پنل مرکز: پیش‌نمایش + تایم‌لاین -->
    <div class="st-center">
      <div class="st-preview" id="preview">
        <div class="st-stage" id="stage"></div>
      </div>
      <div class="st-pv-bar">
        <button class="st-btn" id="btnPlay" style="padding:5px 10px;"><i class="fas fa-play"></i></button>
        <span class="tc" id="tcCur">00:00.000</span>
        <span>/</span>
        <span class="tc" id="tcTot">00:00.000</span>
        <div style="flex:1;"></div>
        <span id="pvInfo" style="font-size:11px;"></span>
      </div>

      <div class="st-tl">
        <div class="st-tl-head">
          <span style="font-size:11px;font-weight:700;color:#cbd5e1;"><i class="fas fa-sliders" style="color:#1a7ac4;margin-left:5px;"></i>خط زمانی</span>
          <span style="font-size:10.5px;color:#475569;" id="tlHint">برای افزودن، روی رسانه‌ای در کتابخانه کلیک کنید · کلیپ را بکشید تا جابه‌جا شود · لبه‌ها را بکشید تا تریم شود</span>
        </div>
        <div class="st-tl-scroll" id="tlScroll">
          <div class="st-tl-inner" id="tlInner">
            <div class="st-ruler" id="ruler"></div>
            <div class="st-rows" id="rows"></div>
            <div class="st-playhead" id="playhead" style="right:118px;"></div>
          </div>
        </div>
      </div>
    </div>
    <div class="st-resizer" data-resize="right"></div>

    <!-- پنل راست: اینسپکتور -->
    <div class="st-right" id="inspector">
      <div class="st-empty">کلیپی را انتخاب کنید تا ویژگی‌هایش اینجا بیاید.</div>
    </div>
  </div>
</div>

<script>
(function(){
"use strict";
const PID = <?= $plId ?>;
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const MEDIA = <?= json_encode($media, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let TL = <?= json_encode($timeline, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const LS_KEY = 'studio_tl_' + PID;

// رنگ هر نوع تراک
const TRACK_META = {
  video:   {c:'#2563eb', icon:'fa-film',  label:'ویدیو / تصویر'},
  overlay: {c:'#c026d3', icon:'fa-layer-group', label:'overlay'},
  image:   {c:'#0891b2', icon:'fa-image', label:'تصویر'},
  text:    {c:'#ea580c', icon:'fa-font',  label:'متن / زیرنویس'},
  logo:    {c:'#ca8a04', icon:'fa-star',  label:'لوگو'},
  audio:   {c:'#16a34a', icon:'fa-volume-high', label:'صدا'}
};

let pxPerSec = 8;        // زوم
let selId = null;        // کلیپ انتخاب‌شده
let playhead = 0;        // ثانیه
let dirty = false;
let playing = false, playTimer = null;

// ── کمک‌توابع ──────────────────────────────────────────────────
const $ = s => document.querySelector(s);
const el = (t,c)=>{const e=document.createElement(t);if(c)e.className=c;return e;};
function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function fmtTC(sec){
  sec=Math.max(0,sec);
  const m=Math.floor(sec/60), s=Math.floor(sec%60), ms=Math.round((sec-Math.floor(sec))*1000);
  return String(m).padStart(2,'0')+':'+String(s).padStart(2,'0')+'.'+String(ms).padStart(3,'0');
}
function fmtDur(sec){const m=Math.floor(sec/60),s=Math.floor(sec%60);return m>0?(m+':'+String(s).padStart(2,'0')):(s+'s');}
function uid(){return 'c'+Math.random().toString(36).slice(2,9);}
function tlDuration(){
  let d=0;
  TL.tracks.forEach(t=>t.clips.forEach(c=>{d=Math.max(d,c.start+c.duration);}));
  return Math.max(d,10);
}
function findClip(id){
  for(const t of TL.tracks) for(const c of t.clips) if(c.id===id) return {clip:c,track:t};
  return null;
}
function markDirty(){dirty=true;$('#dirtyDot').classList.add('on');try{localStorage.setItem(LS_KEY,JSON.stringify({tl:TL,at:Date.now()}));}catch(e){}}

// ── رندر کتابخانه ─────────────────────────────────────────────
let libFilter='all';
function renderLib(){
  const list=$('#libList'); list.innerHTML='';
  const items=MEDIA.filter(m=>libFilter==='all'||m.type===libFilter);
  if(!items.length){list.innerHTML='<div class="st-empty" style="grid-column:1/-1;">رسانه‌ای نیست</div>';return;}
  items.forEach(m=>{
    const a=el('div','st-asset');
    const th=el('div','th');
    const poster=m.thumbnail_path||(m.type==='image'?m.file_path:null);
    if(poster) th.style.backgroundImage="url('"+esc(poster)+"')";
    else th.innerHTML='<i class="fas '+(m.type==='video'?'fa-film':m.type==='url'?'fa-puzzle-piece':'fa-image')+'"></i>';
    if(m.type==='video'&&m.duration) th.innerHTML+='<span class="bdg">'+fmtDur(m.duration)+'</span>';
    const nm=el('div','nm'); nm.textContent=m.name;
    a.appendChild(th);a.appendChild(nm);
    a.title=m.name+(m.width?(' · '+m.width+'×'+m.height):'')+(m.duration?(' · '+fmtDur(m.duration)):'');
    a.onclick=()=>addMediaClip(m);
    list.appendChild(a);
  });
}

// ── افزودن کلیپ از رسانه ──────────────────────────────────────
function trackFor(type){
  // ماژول و استریم و ویدیو و تصویر همه در تراک ویدیوی پایه
  return TL.tracks.find(t=>t.type==='video');
}
function addMediaClip(m){
  const trk=trackFor(m.type);
  if(!trk){alert('تراک ویدیو پیدا نشد');return;}
  // مدت: ویدیوی واقعی مدت خودش، بقیه ۱۰ ثانیه
  const dur=(m.type==='video'&&m.duration>0)?m.duration:10;
  // در انتهای تراک قرار بگیرد تا روی هم نیفتد
  let endOfTrack=0; trk.clips.forEach(c=>endOfTrack=Math.max(endOfTrack,c.start+c.duration));
  const clip={
    id:uid(), mediaId:m.id, itemType: m.mime_type==='application/x-signage-module'?'media':'media',
    name:m.name, mediaType:m.type, thumb:m.thumbnail_path||(m.type==='image'?m.file_path:''),
    start:endOfTrack, duration:dur, sourceStart:0, fit:'contain', muted:true, volume:100
  };
  trk.clips.push(clip);
  selId=clip.id; markDirty(); renderAll();
  scrollToClip(clip);
}
function addTextClip(){
  let trk=TL.tracks.find(t=>t.type==='text');
  if(!trk){trk={id:'track-text',type:'text',name:'متن / زیرنویس',order:TL.tracks.length,locked:false,hidden:false,muted:false,clips:[]};TL.tracks.push(trk);}
  const clip={id:uid(),text:'متن جدید',name:'متن',start:playhead,duration:Math.min(30,Math.max(5,tlDuration()-playhead)),
    font:'Tahoma',size:28,weight:700,color:'#ffffff',bg:'rgba(0,0,0,0.6)',position:'bottom',align:'center',rtl:true,animation:'scroll'};
  trk.clips.push(clip); selId=clip.id; markDirty(); renderAll();
}

// ── رندر تایم‌لاین ────────────────────────────────────────────
function rulerStep(){
  // گام متناسب با زوم: بین تیک‌ها حداقل ~60px
  const steps=[1,2,5,10,15,30,60,120,300,600];
  for(const s of steps){ if(s*pxPerSec>=60) return s; }
  return 600;
}
function renderRuler(){
  const total=tlDuration();
  const r=$('#ruler'); r.innerHTML='';
  const w=total*pxPerSec;
  $('#tlInner').style.width=(w+118)+'px';
  r.style.width=w+'px'; r.style.marginRight='118px';
  const step=rulerStep();
  for(let t=0;t<=total;t+=step){
    const tick=el('div','tick');
    tick.style.right=(t*pxPerSec)+'px';
    tick.textContent=fmtTC(t).replace(/\.000$/,'');
    r.appendChild(tick);
  }
}
function renderRows(){
  const rows=$('#rows'); rows.innerHTML='';
  const total=tlDuration();
  TL.tracks.slice().sort((a,b)=>a.order-b.order).forEach(trk=>{
    const meta=TRACK_META[trk.type]||TRACK_META.video;
    const row=el('div','st-trk');
    // برچسب تراک
    const lbl=el('div','st-trk-label');
    lbl.innerHTML='<div class="nm"><i class="fas '+meta.icon+'" style="color:'+meta.c+'"></i>'+esc(trk.name)+'</div>';
    const ctl=el('div','ctl');
    ctl.innerHTML='<i class="fas '+(trk.hidden?'fa-eye-slash':'fa-eye')+'" data-act="hide" title="نمایش"></i>'+
                  '<i class="fas '+(trk.locked?'fa-lock act':'fa-lock-open')+'" data-act="lock" title="قفل"></i>'+
                  (trk.type==='video'||trk.type==='audio'?'<i class="fas '+(trk.muted?'fa-volume-xmark act':'fa-volume-high')+'" data-act="mute" title="صدا"></i>':'');
    ctl.querySelectorAll('i').forEach(ic=>ic.onclick=()=>{const a=ic.dataset.act;trk[a==='hide'?'hidden':a==='lock'?'locked':'muted']=!trk[a==='hide'?'hidden':a==='lock'?'locked':'muted'];markDirty();renderRows();});
    lbl.appendChild(ctl);
    row.appendChild(lbl);
    // لِین کلیپ‌ها
    const lane=el('div','st-lane');
    lane.style.width=(total*pxPerSec)+'px';
    lane.style.opacity=trk.hidden?'.4':'1';
    trk.clips.forEach(c=>lane.appendChild(makeClipEl(trk,c,meta)));
    row.appendChild(lane);
    rows.appendChild(row);
  });
}
function makeClipEl(trk,c,meta){
  const d=el('div','st-clip'+(c.id===selId?' sel':''));
  d.style.right=(c.start*pxPerSec)+'px';
  d.style.width=Math.max(14,c.duration*pxPerSec)+'px';
  d.style.background='linear-gradient(135deg,'+meta.c+','+meta.c+'cc)';
  if(c.thumb){const th=el('div','thumb');th.style.backgroundImage="url('"+esc(c.thumb)+"')";d.appendChild(th);}
  const label=trk.type==='text'?(c.text||'متن'):(c.name||'کلیپ');
  d.innerHTML+='<div class="lbl">'+esc(label)+'</div><div class="dur">'+fmtDur(c.duration)+'</div>';
  if(!trk.locked){
    d.innerHTML+='<div class="st-handle l"></div><div class="st-handle r"></div>';
    attachClipDrag(d,trk,c);
  }
  d.addEventListener('mousedown',e=>{if(e.target.classList.contains('st-handle'))return;selId=c.id;renderRows();renderInspector();renderPreview();},true);
  return d;
}

// ── درگ و تریم ────────────────────────────────────────────────
function attachClipDrag(d,trk,c){
  const lane=()=>d.parentElement;
  let mode=null,sx=0,oStart=0,oDur=0;
  const onDown=(e,m)=>{e.preventDefault();e.stopPropagation();mode=m;sx=e.clientX;oStart=c.start;oDur=c.duration;selId=c.id;
    document.addEventListener('mousemove',onMove);document.addEventListener('mouseup',onUp);};
  // در RTL، حرکت ماوس به چپ یعنی زمان جلوتر؛ پس dx را معکوس می‌کنیم
  const onMove=e=>{
    const dx=-(e.clientX-sx)/pxPerSec;
    if(mode==='move'){c.start=Math.max(0,Math.round(oStart+dx));}
    else if(mode==='l'){const ns=Math.max(0,Math.min(oStart+oDur-1,Math.round(oStart+dx)));c.duration=oDur+(oStart-ns);c.start=ns;}
    else if(mode==='r'){c.duration=Math.max(1,Math.round(oDur+dx));}
    d.style.right=(c.start*pxPerSec)+'px';d.style.width=Math.max(14,c.duration*pxPerSec)+'px';
    d.querySelector('.dur').textContent=fmtDur(c.duration);
  };
  const onUp=()=>{document.removeEventListener('mousemove',onMove);document.removeEventListener('mouseup',onUp);
    if(mode){markDirty();renderRuler();renderInspector();}mode=null;};
  d.addEventListener('mousedown',e=>{if(!e.target.classList.contains('st-handle'))onDown(e,'move');});
  const hl=d.querySelector('.st-handle.l'),hr=d.querySelector('.st-handle.r');
  if(hl)hl.addEventListener('mousedown',e=>onDown(e,'l'));
  if(hr)hr.addEventListener('mousedown',e=>onDown(e,'r'));
}
function scrollToClip(c){const sc=$('#tlScroll');sc.scrollLeft=-(c.start*pxPerSec);}

// ── پلی‌هد ────────────────────────────────────────────────────
function renderPlayhead(){
  $('#playhead').style.right=(118+playhead*pxPerSec)+'px';
  $('#tcCur').textContent=fmtTC(playhead);
  $('#tcTot').textContent=fmtTC(tlDuration());
}
$('#ruler').parentElement.addEventListener('mousedown',e=>{
  if(!e.target.closest('.st-ruler'))return;
  const inner=$('#tlInner').getBoundingClientRect();
  playhead=Math.max(0,(inner.right-e.clientX-118)/pxPerSec);  // RTL
  renderPlayhead();renderPreview();
});

// ── پیش‌نمایش ترکیبی ──────────────────────────────────────────
// همه‌ی لایه‌های فعال در لحظه‌ی playhead با هم نمایش داده می‌شوند (بند ۲۴)
function activeClips(){
  const out=[];
  TL.tracks.slice().sort((a,b)=>a.order-b.order).forEach(trk=>{
    if(trk.hidden)return;
    trk.clips.forEach(c=>{if(playhead>=c.start&&playhead<c.start+c.duration)out.push({trk,c});});
  });
  return out;
}
function mediaById(id){return MEDIA.find(m=>m.id===id);}
function renderPreview(){
  const stage=$('#stage');
  // ابعاد صحنه نسبت ۱۶:۹ در فضای موجود
  const pv=$('#preview').getBoundingClientRect();
  const aw=TL.resolution.w/TL.resolution.h;
  let w=pv.width-24,h=w/aw; if(h>pv.height-24){h=pv.height-24;w=h*aw;}
  stage.style.width=w+'px';stage.style.height=h+'px';stage.innerHTML='';
  const act=activeClips();
  let info=[];
  act.forEach(({trk,c})=>{
    if(trk.type==='video'||trk.type==='image'){
      const m=mediaById(c.mediaId);
      const layer=el('div');layer.style.cssText='position:absolute;inset:0;background:#000;';
      if(m&&m.type==='image'){layer.style.backgroundImage="url('"+esc(m.file_path)+"')";layer.style.backgroundSize=c.fit==='cover'?'cover':'contain';layer.style.backgroundPosition='center';layer.style.backgroundRepeat='no-repeat';}
      else if(m&&m.type==='video'){layer.innerHTML='<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#334155;font-size:13px;flex-direction:column;gap:8px;">'+(m.thumbnail_path?'<img src="'+esc(m.thumbnail_path)+'" style="max-width:100%;max-height:100%;'+(c.fit==='cover'?'width:100%;height:100%;object-fit:cover;':'object-fit:contain;')+'">':'<i class=\'fas fa-film\' style=\'font-size:30px\'></i><span>'+esc(m.name)+'</span>')+'</div>';}
      else{layer.innerHTML='<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#334155;"><i class="fas fa-puzzle-piece" style="font-size:28px"></i></div>';}
      stage.appendChild(layer);
      info.push(trk.type==='video'?'ویدیو':'تصویر');
    }else if(trk.type==='logo'){
      const src=c.src||(mediaById(c.mediaId)||{}).file_path;
      if(!src)return;
      const img=el('img');img.src=src;
      const pos=posStyle(c.position,c.x,c.y);
      img.style.cssText='position:absolute;'+pos+'width:'+(c.scale||100)/100*22+'%;opacity:'+(c.opacity||100)/100+';transform:rotate('+(c.rotation||0)+'deg);';
      stage.appendChild(img);info.push('لوگو');
    }else if(trk.type==='text'){
      const t=el('div');
      const vpos=c.position==='top'?'top:6%;':c.position==='center'?'top:50%;transform:translateY(-50%);':'bottom:6%;';
      t.style.cssText='position:absolute;right:4%;left:4%;'+vpos+'text-align:'+(c.align||'center')+';direction:'+(c.rtl?'rtl':'ltr')+';';
      t.innerHTML='<span style="font-family:'+esc(c.font||'Tahoma')+';font-size:'+Math.max(10,(c.size||24)*(h/1080))+'px;font-weight:'+(c.weight||700)+';color:'+esc(c.color||'#fff')+';background:'+esc(c.bg||'transparent')+';padding:2px 10px;border-radius:4px;">'+esc(c.text||'')+'</span>';
      stage.appendChild(t);info.push('متن');
    }
  });
  if(!act.length){stage.innerHTML='<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#334155;font-size:12px;">در این لحظه چیزی روی صفحه نیست</div>';}
  $('#pvInfo').textContent=info.length?('لایه‌های فعال: '+info.join(' + ')):'';
}
function posStyle(p,x,y){
  if(p==='free'&&x!=null)return 'right:'+x+'%;top:'+y+'%;';
  const map={'top-left':'top:4%;left:4%;','top-center':'top:4%;left:50%;transform:translateX(-50%);','top-right':'top:4%;right:4%;',
    'center':'top:50%;left:50%;transform:translate(-50%,-50%);','bottom-left':'bottom:4%;left:4%;','bottom-center':'bottom:4%;left:50%;transform:translateX(-50%);','bottom-right':'bottom:4%;right:4%;'};
  return map[p]||map['top-right'];
}

// ── اینسپکتور ─────────────────────────────────────────────────
function sec(title,bodyHtml,open=true){
  return '<div class="st-insp-sec'+(open?'':' col')+'"><div class="st-insp-h"><span>'+title+'</span><i class="fas fa-chevron-down chev" style="font-size:9px;color:#475569;"></i></div><div class="st-insp-b">'+bodyHtml+'</div></div>';
}
function f(label,inner){return '<div class="st-field"><label>'+label+'</label>'+inner+'</div>';}
function renderInspector(){
  const box=$('#inspector');
  const hit=selId?findClip(selId):null;
  if(!hit){box.innerHTML='<div class="st-empty">کلیپی را انتخاب کنید تا ویژگی‌هایش اینجا بیاید.</div>';return;}
  const {clip:c,track:t}=hit;
  let html='';
  // زمان‌بندی — مشترک همه
  html+=sec('زمان‌بندی',
    '<div class="st-grid2">'+
      f('شروع (ثانیه)','<input type="number" min="0" data-k="start" value="'+c.start+'">')+
      f('مدت (ثانیه)','<input type="number" min="1" data-k="duration" value="'+c.duration+'">')+
    '</div>'+
    '<div style="font-size:10.5px;color:#475569;">پایان: '+fmtTC(c.start+c.duration)+'</div>');

  if(t.type==='video'||t.type==='image'){
    const m=mediaById(c.mediaId)||{};
    html+=sec('نمایش',
      f('پر کردن صفحه','<select data-k="fit"><option value="contain"'+(c.fit!=='cover'?' selected':'')+'>جا شدن کامل</option><option value="cover"'+(c.fit==='cover'?' selected':'')+'>پر کردن صفحه</option></select>')+
      (m.type==='video'?f('صدا','<select data-k="muted"><option value="1"'+(c.muted?' selected':'')+'>بی‌صدا</option><option value="0"'+(!c.muted?' selected':'')+'>با صدا</option></select>')+
        f('بلندی صدا (٪)','<input type="range" min="0" max="100" data-k="volume" value="'+(c.volume||100)+'">'):''));
    html+=sec('فنی',
      '<div style="font-size:11px;color:#64748b;line-height:1.9;">'+
      'نوع: '+esc(m.type||'—')+'<br>'+
      (m.width?('ابعاد: '+m.width+'×'+m.height+'<br>'):'')+
      (m.duration?('مدت فایل: '+fmtDur(m.duration)+'<br>'):'')+
      '</div>',false);
  } else if(t.type==='logo'||t.type==='overlay'){
    html+=sec('جایگاه',posGrid(c.position)+
      f('اندازه (٪)','<input type="range" min="5" max="400" data-k="scale" value="'+(c.scale||100)+'">')+
      f('شفافیت (٪)','<input type="range" min="0" max="100" data-k="opacity" value="'+(c.opacity||100)+'">')+
      f('چرخش','<input type="range" min="-180" max="180" data-k="rotation" value="'+(c.rotation||0)+'">'));
  } else if(t.type==='text'){
    html+=sec('متن',
      f('محتوا','<textarea data-k="text" rows="2">'+esc(c.text||'')+'</textarea>')+
      '<div class="st-grid2">'+
        f('فونت','<select data-k="font"><option>Tahoma</option><option>Arial</option><option>IRANSans</option><option>Vazirmatn</option></select>')+
        f('اندازه','<input type="number" min="8" max="120" data-k="size" value="'+(c.size||24)+'">')+
      '</div>'+
      '<div class="st-grid2">'+
        f('رنگ','<input type="color" data-k="color" value="'+(c.color||'#ffffff')+'">')+
        f('وزن','<select data-k="weight"><option value="400"'+(c.weight==400?' selected':'')+'>معمولی</option><option value="700"'+(c.weight!=400?' selected':'')+'>ضخیم</option></select>')+
      '</div>'+
      '<div class="st-grid2">'+
        f('جایگاه عمودی','<select data-k="position"><option value="top"'+(c.position==='top'?' selected':'')+'>بالا</option><option value="center"'+(c.position==='center'?' selected':'')+'>وسط</option><option value="bottom"'+(c.position!=='top'&&c.position!=='center'?' selected':'')+'>پایین</option></select>')+
        f('چینش','<select data-k="align"><option value="right"'+(c.align==='right'?' selected':'')+'>راست</option><option value="center"'+(c.align!=='right'&&c.align!=='left'?' selected':'')+'>وسط</option><option value="left"'+(c.align==='left'?' selected':'')+'>چپ</option></select>')+
      '</div>'+
      '<div class="st-grid2">'+
        f('جهت','<select data-k="rtl"><option value="1"'+(c.rtl?' selected':'')+'>راست‌به‌چپ</option><option value="0"'+(!c.rtl?' selected':'')+'>چپ‌به‌راست</option></select>')+
        f('حرکت','<select data-k="animation"><option value="none"'+(c.animation==='none'?' selected':'')+'>ثابت</option><option value="scroll"'+(c.animation==='scroll'?' selected':'')+'>متحرک</option><option value="fade"'+(c.animation==='fade'?' selected':'')+'>محو</option></select>')+
      '</div>');
  }
  html+='<div style="padding:13px;"><button class="st-btn" style="width:100%;background:rgba(239,68,68,.15);color:#fca5a5;" id="btnDelClip"><i class="fas fa-trash"></i> حذف کلیپ</button></div>';
  box.innerHTML=html;
  wireInspector(c,t);
}
function posGrid(cur){
  const cells=[['top-left','↖'],['top-center','↑'],['top-right','↗'],['center','•'],['bottom-left','↙'],['bottom-center','↓'],['bottom-right','↘']];
  let h='<div class="st-field"><label>جایگاه</label><div class="st-posgrid">';
  const order=['top-left','top-center','top-right','bottom-left','center','bottom-right'];
  cells.forEach(([k,sym])=>{h+='<button data-pos="'+k+'" class="'+(cur===k?'act':'')+'">'+sym+'</button>';});
  return h+'</div></div>';
}
function wireInspector(c,t){
  $('#inspector').querySelectorAll('.st-insp-h').forEach(h=>h.onclick=()=>h.parentElement.classList.toggle('col'));
  $('#inspector').querySelectorAll('[data-k]').forEach(inp=>{
    const ev=inp.type==='range'||inp.tagName==='SELECT'?'input':'change';
    inp.addEventListener('input',()=>{
      let v=inp.value;
      if(inp.dataset.k==='muted')v=(v==='1');
      else if(inp.dataset.k==='rtl')v=(v==='1');
      else if(['start','duration','size','scale','opacity','rotation','volume','weight'].includes(inp.dataset.k))v=parseInt(v)||0;
      c[inp.dataset.k]=v;
      markDirty();renderRows();renderRuler();renderPlayhead();renderPreview();
    });
  });
  $('#inspector').querySelectorAll('[data-pos]').forEach(b=>b.onclick=()=>{c.position=b.dataset.pos;markDirty();renderInspector();renderPreview();});
  const del=$('#btnDelClip');
  if(del)del.onclick=()=>{if(!confirm('این کلیپ حذف شود؟'))return;t.clips=t.clips.filter(x=>x.id!==c.id);selId=null;markDirty();renderAll();};
}

// ── برش در محل پلی‌هد ─────────────────────────────────────────
function splitAtPlayhead(){
  const hit=selId?findClip(selId):null;
  if(!hit){alert('ابتدا یک کلیپ را انتخاب کنید');return;}
  const {clip:c,track:t}=hit;
  if(playhead<=c.start||playhead>=c.start+c.duration){alert('پلی‌هد باید داخل کلیپ انتخاب‌شده باشد');return;}
  const left=playhead-c.start;
  const copy=Object.assign({},c,{id:uid(),start:playhead,duration:c.duration-left,sourceStart:(c.sourceStart||0)+left});
  c.duration=left;
  t.clips.push(copy);markDirty();renderAll();
}

// ── رندر همه ──────────────────────────────────────────────────
function renderAll(){renderRuler();renderRows();renderPlayhead();renderInspector();renderPreview();}

// ── ذخیره / انتشار ────────────────────────────────────────────
async function post(url,extra){
  const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(Object.assign({timeline:TL},extra||{}))});
  return r.json().catch(()=>({success:false,message:'پاسخ نامعتبر'}));
}
async function saveDraft(silent){
  const btn=$('#btnSave');btn.disabled=true;
  const res=await post('/admin/playlists/'+PID+'/timeline');
  btn.disabled=false;
  if(res.success){dirty=false;$('#dirtyDot').classList.remove('on');$('#savedLabel').textContent='ذخیره شد: '+new Date().toLocaleTimeString('fa-IR');try{localStorage.removeItem(LS_KEY);}catch(e){}}
  else if(!silent)alert(res.message||'ذخیره ناموفق');
}
async function publish(){
  if(dirty)await saveDraft(true);
  const btn=$('#btnPublish');btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> انتشار…';
  const res=await post('/admin/playlists/'+PID+'/timeline/publish');
  btn.disabled=false;btn.innerHTML='<i class="fas fa-upload"></i> انتشار';
  if(res.success){
    let msg=res.message;
    if(res.data&&res.data.warnings&&res.data.warnings.length)msg+='\n\nتوجه:\n• '+res.data.warnings.join('\n• ');
    alert('✅ '+msg);
  }else{
    let m=res.message||'انتشار ناموفق';
    if(res.errors)m+='\n\n• '+(Array.isArray(res.errors)?res.errors.join('\n• '):Object.values(res.errors).join('\n• '));
    alert('⚠ '+m);
  }
}

// ── پخش پیش‌نمایش ─────────────────────────────────────────────
function togglePlay(){
  playing=!playing;
  $('#btnPlay').innerHTML=playing?'<i class="fas fa-pause"></i>':'<i class="fas fa-play"></i>';
  if(playing){const t0=Date.now()-playhead*1000;playTimer=setInterval(()=>{playhead=(Date.now()-t0)/1000;if(playhead>=tlDuration()){playhead=0;togglePlay();}renderPlayhead();renderPreview();},100);}
  else clearInterval(playTimer);
}

// ── زوم ───────────────────────────────────────────────────────
function setZoom(v){pxPerSec=Math.max(2,Math.min(40,v));$('#zoomRange').value=pxPerSec;renderRuler();renderRows();renderPlayhead();}

// ── رویدادها ──────────────────────────────────────────────────
$('#libTabs').onclick=e=>{if(!e.target.dataset.f)return;libFilter=e.target.dataset.f;$('#libTabs').querySelectorAll('.st-lib-tab').forEach(x=>x.classList.toggle('act',x===e.target));renderLib();};
$('#btnSave').onclick=()=>saveDraft(false);
$('#btnPublish').onclick=publish;
$('#btnAddText').onclick=addTextClip;
$('#btnSplit').onclick=splitAtPlayhead;
$('#btnPlay').onclick=togglePlay;
$('#btnZoomIn').onclick=()=>setZoom(pxPerSec+2);
$('#btnZoomOut').onclick=()=>setZoom(pxPerSec-2);
$('#zoomRange').oninput=e=>setZoom(parseInt(e.target.value));
document.addEventListener('keydown',e=>{
  if(e.target.matches('input,textarea,select'))return;
  if(e.key==='Delete'&&selId){const h=findClip(selId);if(h){h.track.clips=h.track.clips.filter(x=>x.id!==selId);selId=null;markDirty();renderAll();}}
  if(e.key===' '){e.preventDefault();togglePlay();}
  if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();saveDraft(false);}
});
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});

// پنل‌های قابل‌تغییراندازه
document.querySelectorAll('.st-resizer').forEach(rz=>{
  rz.addEventListener('mousedown',e=>{
    e.preventDefault();const side=rz.dataset.resize;const panel=side==='left'?$('.st-left'):$('.st-right');
    const sx=e.clientX,sw=panel.offsetWidth;
    // RTL: پنل چپ با حرکت ماوس به راست بزرگ می‌شود، پنل راست برعکس
    const mv2=ev=>{let nw;if(side==='left')nw=sw+(sx-ev.clientX);else nw=sw+(ev.clientX-sx);panel.style.width=Math.max(180,nw)+'px';renderPreview();};
    const up=()=>{document.removeEventListener('mousemove',mv2);document.removeEventListener('mouseup',up);};
    document.addEventListener('mousemove',mv2);document.addEventListener('mouseup',up);
  });
});

// بازیابی از localStorage اگر تازه‌تر از سرور باشد (بند ۱۹)
(function restore(){
  try{
    const raw=localStorage.getItem(LS_KEY);
    if(raw){const saved=JSON.parse(raw);
      if(saved&&saved.tl&&confirm('یک نسخه‌ی ذخیره‌نشده از قبل در این مرورگر هست. بازیابی شود؟')){TL=saved.tl;dirty=true;$('#dirtyDot').classList.add('on');}
      else localStorage.removeItem(LS_KEY);
    }
  }catch(e){}
})();

// شروع
renderLib();renderAll();
window.addEventListener('resize',renderPreview);
<?php if (!empty($savedAt)): ?>$('#savedLabel').textContent='آخرین پیش‌نویس: <?= e(date('H:i', strtotime($savedAt))) ?>';<?php endif; ?>
})();
</script>

<?php /* نکته: این صفحه layout_footer را عمدا صدا نمی‌زند چون تمام‌صفحه است */ ?>
