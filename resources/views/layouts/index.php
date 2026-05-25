<?php
use App\Core\Auth;
$layouts    = $layouts    ?? [];
$editLayout = $editLayout ?? null;
include VIEWS_PATH . '/partials/layout.php';
?>

<div class="flex items-center justify-between mb-5">
  <h1 class="text-xl font-bold text-white flex items-center gap-2">
    <i class="fas fa-table-columns text-cyan-400"></i> طراح چیدمان
  </h1>
  <button onclick="openNewLayout()" class="btn-primary text-sm flex items-center gap-2">
    <i class="fas fa-plus text-xs"></i> چیدمان جدید
  </button>
</div>

<!-- Layouts list -->
<?php if (empty($layouts)): ?>
<div class="card text-center py-16 mb-6">
  <i class="fas fa-table-columns text-5xl text-slate-700 mb-4 block"></i>
  <p class="text-slate-500 mb-2">چیدمانی ساخته نشده</p>
  <p class="text-slate-600 text-sm">چیدمان تعیین می‌کند محتوا در چه بخش‌هایی از صفحه نمایش داده شود</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
  <?php foreach ($layouts as $l): ?>
  <div class="card hover:border-white/15 transition-all cursor-pointer" style="border:1px solid rgba(255,255,255,0.07);"
       onclick="editLayout(<?= htmlspecialchars(json_encode($l)) ?>)">
    <div class="flex items-start justify-between mb-3">
      <div>
        <h3 class="font-bold text-white text-sm"><?= e($l['name']) ?></h3>
        <span class="text-xs text-slate-500 font-mono"><?= e($l['canvas_width']) ?>×<?= e($l['canvas_height']) ?></span>
      </div>
      <span class="text-xs px-2 py-1 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
        <?= count(json_decode($l['zones'] ?? '[]', true)) ?> زون
      </span>
    </div>

    <!-- Mini preview -->
    <div style="background:#0a0a14;border:1px solid rgba(255,255,255,0.06);border-radius:8px;height:90px;position:relative;overflow:hidden;">
      <?php
      $zones = json_decode($l['zones'] ?? '[]', true) ?: [];
      $cw = max(1, (int)$l['canvas_width']);
      $ch = max(1, (int)$l['canvas_height']);
      $colors = ['#f97316','#3b82f6','#22c55e','#a855f7','#ec4899','#f59e0b'];
      foreach ($zones as $zi => $z):
        $left   = round((($z['x']  ?? 0) / $cw) * 100, 2);
        $top    = round((($z['y']  ?? 0) / $ch) * 100, 2);
        $width  = round((($z['width']  ?? $cw) / $cw) * 100, 2);
        $height = round((($z['height'] ?? $ch) / $ch) * 100, 2);
        $color  = $colors[$zi % count($colors)];
      ?>
      <div style="position:absolute;left:<?=$left?>%;top:<?=$top?>%;width:<?=$width?>%;height:<?=$height?>%;
                  background:<?=$color?>22;border:1px solid <?=$color?>55;border-radius:3px;
                  display:flex;align-items:center;justify-content:center;">
        <span style="font-size:8px;color:<?=$color?>;font-weight:700;overflow:hidden;white-space:nowrap;padding:0 2px;">
          <?= e($z['name'] ?? 'زون '.($zi+1)) ?>
        </span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($zones)): ?>
      <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#2d2d40;font-size:11px;">
        بدون زون
      </div>
      <?php endif; ?>
    </div>

    <div class="flex gap-2 mt-3 pt-3 border-t border-white/5">
      <button onclick="event.stopPropagation();editLayout(<?= htmlspecialchars(json_encode($l)) ?>)"
        class="btn-ghost text-xs flex-1 flex items-center justify-center gap-1">
        <i class="fas fa-pencil text-blue-400 text-xs"></i> ویرایش
      </button>
      <form method="POST" action="/admin/layouts/<?=$l['id']?>/delete" class="inline" onclick="event.stopPropagation()">
        <?= csrf_field() ?>
        <button type="submit" class="btn-danger text-xs px-3 py-1.5" onclick="return confirm('حذف شود؟')">
          <i class="fas fa-trash text-xs"></i>
        </button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Layout Designer -->
<div id="designerWrap" class="card p-0 overflow-hidden" style="<?= $editLayout ? '' : 'display:none;' ?>">
  <div style="background:#111118;border-bottom:1px solid rgba(255,255,255,0.07);padding:14px 20px;display:flex;align-items:center;gap:12px;">
    <i class="fas fa-drafting-compass text-cyan-400"></i>
    <span id="designerTitle" class="font-bold text-white text-sm">طراح چیدمان</span>
    <div style="margin-right:auto;display:flex;gap:8px;">
      <input id="layoutName" type="text" class="form-input text-sm" style="width:200px;padding:6px 12px;"
        placeholder="نام چیدمان *" value="<?= e($editLayout['name'] ?? '') ?>">
      <select id="canvasSize" class="form-input text-sm" style="width:160px;padding:6px 10px;" onchange="setCanvasSize(this.value)">
        <option value="1920x1080" <?= ($editLayout['canvas_width']??1920)==1920 ? 'selected' : '' ?>>1920×1080 (FHD)</option>
        <option value="3840x2160" <?= ($editLayout['canvas_width']??1920)==3840 ? 'selected' : '' ?>>3840×2160 (4K)</option>
        <option value="1280x720"  <?= ($editLayout['canvas_width']??1920)==1280 ? 'selected' : '' ?>>1280×720 (HD)</option>
        <option value="1080x1920" <?= ($editLayout['canvas_width']??1920)==1080 ? 'selected' : '' ?>>1080×1920 (Portrait)</option>
      </select>
    </div>
    <button onclick="addZone()" class="btn-ghost text-xs px-3 py-2 flex items-center gap-1">
      <i class="fas fa-plus text-cyan-400 text-xs"></i> زون جدید
    </button>
    <button onclick="saveLayout()" class="btn-primary text-xs px-4 py-2">
      <i class="fas fa-save text-xs ml-1"></i> ذخیره
    </button>
    <button onclick="closeDesigner()" class="btn-ghost text-xs px-3 py-2">
      <i class="fas fa-xmark text-slate-400"></i>
    </button>
  </div>

  <div style="display:flex;height:600px;">
    <!-- Sidebar: zone list -->
    <div style="width:220px;background:#0d0d16;border-left:1px solid rgba(255,255,255,0.06);overflow-y:auto;flex-shrink:0;">
      <div style="padding:12px 14px;font-size:11px;font-weight:700;color:#475569;letter-spacing:0.5px;text-transform:uppercase;border-bottom:1px solid rgba(255,255,255,0.05);">
        زون‌ها
      </div>
      <div id="zoneList"></div>
    </div>

    <!-- Canvas -->
    <div style="flex:1;background:#06060f;overflow:auto;display:flex;align-items:center;justify-content:center;padding:24px;">
      <div id="canvas" style="position:relative;background:#111;border:2px solid rgba(255,255,255,0.1);border-radius:4px;"
           onclick="deselectAll()">
      </div>
    </div>

    <!-- Zone properties -->
    <div id="zoneProps" style="width:220px;background:#0d0d16;border-right:1px solid rgba(255,255,255,0.06);overflow-y:auto;flex-shrink:0;display:none;">
      <div style="padding:12px 14px;font-size:11px;font-weight:700;color:#475569;letter-spacing:0.5px;text-transform:uppercase;border-bottom:1px solid rgba(255,255,255,0.05);">
        تنظیمات زون
      </div>
      <div style="padding:12px;">
        <label class="form-label">نام زون</label>
        <input id="propName" type="text" class="form-input mb-3" style="font-size:13px;padding:7px 10px;" oninput="updateProp('name',this.value)">

        <label class="form-label">X (پیکسل)</label>
        <input id="propX" type="number" class="form-input mb-3" style="font-size:13px;padding:7px 10px;" oninput="updateProp('x',+this.value)">

        <label class="form-label">Y (پیکسل)</label>
        <input id="propY" type="number" class="form-input mb-3" style="font-size:13px;padding:7px 10px;" oninput="updateProp('y',+this.value)">

        <label class="form-label">عرض (پیکسل)</label>
        <input id="propW" type="number" class="form-input mb-3" style="font-size:13px;padding:7px 10px;" oninput="updateProp('width',+this.value)">

        <label class="form-label">ارتفاع (پیکسل)</label>
        <input id="propH" type="number" class="form-input mb-3" style="font-size:13px;padding:7px 10px;" oninput="updateProp('height',+this.value)">

        <label class="form-label">رنگ</label>
        <input id="propColor" type="color" class="form-input mb-3" style="height:36px;padding:3px;" oninput="updateProp('color',this.value)">

        <button onclick="deleteSelectedZone()" class="btn-danger text-xs w-full py-2 mt-2">
          <i class="fas fa-trash text-xs ml-1"></i> حذف زون
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Hidden form for save -->
<form id="layoutForm" method="POST" action="/admin/layouts" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" id="fLayoutId"     name="_layout_id">
  <input type="hidden" id="fLayoutName"   name="name">
  <input type="hidden" id="fCanvasWidth"  name="canvas_width"  value="1920">
  <input type="hidden" id="fCanvasHeight" name="canvas_height" value="1080">
  <input type="hidden" id="fZones"        name="zones"         value="[]">
</form>

<script>
// ─── State ────────────────────────────────────────────────────────────
let zones    = [];
let selected = null;
let editId   = <?= $editLayout ? $editLayout['id'] : 'null' ?>;
let cw = <?= $editLayout['canvas_width']  ?? 1920 ?>;
let ch = <?= $editLayout['canvas_height'] ?? 1080 ?>;
const COLORS = ['#f97316','#3b82f6','#22c55e','#a855f7','#ec4899','#f59e0b','#14b8a6','#0ea5e9'];
const SCALE  = 0.35;

// ─── Init ─────────────────────────────────────────────────────────────
<?php if ($editLayout): ?>
zones = <?= $editLayout['zones'] ?? '[]' ?>;
if (!Array.isArray(zones)) zones = [];
<?php endif; ?>

document.addEventListener('DOMContentLoaded', () => {
  <?php if ($editLayout): ?>
  document.getElementById('designerWrap').style.display = '';
  document.getElementById('designerTitle').textContent = 'ویرایش: <?= e($editLayout['name'] ?? '') ?>';
  <?php endif; ?>
  renderAll();
});

// ─── Canvas rendering ─────────────────────────────────────────────────
function renderAll() {
  const canvas = document.getElementById('canvas');
  canvas.style.width  = Math.round(cw * SCALE) + 'px';
  canvas.style.height = Math.round(ch * SCALE) + 'px';

  // Clear zone elements only
  canvas.querySelectorAll('.zone-el').forEach(el => el.remove());

  zones.forEach((z, i) => {
    const el = document.createElement('div');
    el.className = 'zone-el';
    el.dataset.idx = i;
    const color = z.color || COLORS[i % COLORS.length];
    el.style.cssText = `
      position:absolute;
      left:${Math.round(z.x * SCALE)}px;
      top:${Math.round(z.y * SCALE)}px;
      width:${Math.round(z.width * SCALE)}px;
      height:${Math.round(z.height * SCALE)}px;
      background:${color}22;
      border:2px solid ${color}${selected === i ? 'ff' : '77'};
      border-radius:3px;
      cursor:move;
      display:flex;align-items:center;justify-content:center;
      box-shadow:${selected === i ? '0 0 0 2px #fff3' : 'none'};
      transition:box-shadow 0.15s;
      user-select:none;
    `;
    el.innerHTML = `<span style="font-size:10px;color:${color};font-weight:700;pointer-events:none;overflow:hidden;max-width:90%;text-align:center;">${z.name || 'زون '+(i+1)}</span>`;

    // Drag
    el.addEventListener('mousedown', e => { e.stopPropagation(); selectZone(i); startDrag(e, i); });
    canvas.appendChild(el);
  });

  renderZoneList();
  if (selected !== null) showProps(selected);
}

function renderZoneList() {
  const list = document.getElementById('zoneList');
  list.innerHTML = zones.map((z, i) => {
    const color = z.color || COLORS[i % COLORS.length];
    return `<div onclick="selectZone(${i})" style="display:flex;align-items:center;gap:8px;padding:9px 14px;cursor:pointer;border-bottom:1px solid rgba(255,255,255,0.04);background:${selected===i?'rgba(255,255,255,0.05)':''};">
      <div style="width:10px;height:10px;border-radius:3px;background:${color};flex-shrink:0;"></div>
      <span style="font-size:12px;color:${selected===i?'#fff':'#94a3b8'};flex:1;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">${z.name || 'زون '+(i+1)}</span>
      <span style="font-size:9px;color:#475569;font-family:monospace;">${z.width}×${z.height}</span>
    </div>`;
  }).join('') || '<p style="color:#475569;font-size:12px;text-align:center;padding:20px;">زونی نیست</p>';
}

// ─── Zone select ──────────────────────────────────────────────────────
function selectZone(i) {
  selected = i;
  document.getElementById('zoneProps').style.display = '';
  showProps(i);
  renderAll();
}

function deselectAll() {
  selected = null;
  document.getElementById('zoneProps').style.display = 'none';
  renderAll();
}

function showProps(i) {
  const z = zones[i];
  if (!z) return;
  document.getElementById('propName').value  = z.name  || '';
  document.getElementById('propX').value     = z.x     || 0;
  document.getElementById('propY').value     = z.y     || 0;
  document.getElementById('propW').value     = z.width  || 400;
  document.getElementById('propH').value     = z.height || 300;
  document.getElementById('propColor').value = z.color  || COLORS[i % COLORS.length];
}

function updateProp(key, val) {
  if (selected === null) return;
  zones[selected][key] = val;
  renderAll();
}

// ─── Add / Delete zone ────────────────────────────────────────────────
function addZone() {
  const i = zones.length;
  zones.push({
    name: 'زون ' + (i + 1),
    x: 50, y: 50,
    width: Math.round(cw / 2),
    height: Math.round(ch / 2),
    color: COLORS[i % COLORS.length],
  });
  selectZone(i);
}

function deleteSelectedZone() {
  if (selected === null) return;
  if (!confirm('این زون حذف شود؟')) return;
  zones.splice(selected, 1);
  selected = zones.length > 0 ? 0 : null;
  if (selected !== null) showProps(0);
  else document.getElementById('zoneProps').style.display = 'none';
  renderAll();
}

// ─── Drag ─────────────────────────────────────────────────────────────
function startDrag(e, idx) {
  const z = zones[idx];
  const startX = e.clientX, startY = e.clientY;
  const origX = z.x, origY = z.y;

  function onMove(e) {
    z.x = Math.max(0, Math.min(cw - z.width,  origX + Math.round((e.clientX - startX) / SCALE)));
    z.y = Math.max(0, Math.min(ch - z.height, origY + Math.round((e.clientY - startY) / SCALE)));
    renderAll();
  }
  function onUp() {
    document.removeEventListener('mousemove', onMove);
    document.removeEventListener('mouseup', onUp);
  }
  document.addEventListener('mousemove', onMove);
  document.addEventListener('mouseup', onUp);
}

// ─── Canvas size ─────────────────────────────────────────────────────
function setCanvasSize(val) {
  const [w, h] = val.split('x').map(Number);
  cw = w; ch = h;
  document.getElementById('fCanvasWidth').value  = w;
  document.getElementById('fCanvasHeight').value = h;
  renderAll();
}

// ─── Open / Close Designer ───────────────────────────────────────────
function openNewLayout() {
  editId = null;
  zones  = [];
  selected = null;
  cw = 1920; ch = 1080;
  document.getElementById('layoutName').value      = '';
  document.getElementById('canvasSize').value      = '1920x1080';
  document.getElementById('designerTitle').textContent = 'چیدمان جدید';
  document.getElementById('layoutForm').action     = '/admin/layouts';
  document.getElementById('fLayoutId').name        = '';
  document.getElementById('fLayoutId').value       = '';
  document.getElementById('zoneProps').style.display = 'none';
  document.getElementById('designerWrap').style.display = '';
  renderAll();
  document.getElementById('layoutName').focus();
  document.getElementById('designerWrap').scrollIntoView({behavior:'smooth'});
}

function editLayout(data) {
  editId   = data.id;
  cw       = data.canvas_width  || 1920;
  ch       = data.canvas_height || 1080;
  zones    = JSON.parse(data.zones || '[]');
  selected = null;
  if (!Array.isArray(zones)) zones = [];

  document.getElementById('layoutName').value      = data.name;
  document.getElementById('designerTitle').textContent = 'ویرایش: ' + data.name;
  document.getElementById('layoutForm').action     = '/admin/layouts/' + data.id;
  document.getElementById('fLayoutId').name        = '_layout_id';
  document.getElementById('fLayoutId').value       = data.id;
  document.getElementById('zoneProps').style.display = 'none';
  document.getElementById('designerWrap').style.display = '';

  const sizeKey = cw + 'x' + ch;
  const sel = document.getElementById('canvasSize');
  if ([...sel.options].some(o => o.value === sizeKey)) sel.value = sizeKey;

  renderAll();
  document.getElementById('designerWrap').scrollIntoView({behavior:'smooth'});
}

function closeDesigner() {
  document.getElementById('designerWrap').style.display = 'none';
  selected = null;
}

// ─── Save ─────────────────────────────────────────────────────────────
function saveLayout() {
  const name = document.getElementById('layoutName').value.trim();
  if (!name) { alert('نام چیدمان الزامی است'); document.getElementById('layoutName').focus(); return; }

  document.getElementById('fLayoutName').value   = name;
  document.getElementById('fCanvasWidth').value  = cw;
  document.getElementById('fCanvasHeight').value = ch;
  document.getElementById('fZones').value        = JSON.stringify(zones);

  const form   = document.getElementById('layoutForm');
  const action = editId ? '/admin/layouts/' + editId : '/admin/layouts';
  form.action  = action;

  form.submit();
}
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
