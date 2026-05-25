<?php
use App\Core\Auth;
include VIEWS_PATH . '/partials/layout.php';

// group items by category
$byCategory = [];
foreach ($items as $item) {
    $cid = $item['category_id'] ?? 0;
    $byCategory[$cid][] = $item;
}
$totalItems     = count($items);
$availableItems = count(array_filter($items, fn($i) => $i['is_available']));
$catCount       = count($categories);
?>

<!-- Header -->
<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-bold text-white flex items-center gap-2">
      <i class="fas fa-utensils" style="color:#f97316;"></i> منوی رستوران
    </h1>
    <p class="text-sm text-slate-500 mt-1">مدیریت دسته‌بندی‌ها و آیتم‌های منو</p>
  </div>
  <div class="flex gap-2">
    <button onclick="openModal('catModal')" class="btn-ghost text-sm gap-2">
      <i class="fas fa-folder-plus text-orange-400"></i> دسته جدید
    </button>
    <button onclick="openModal('itemModal')" class="btn-primary text-sm gap-2">
      <i class="fas fa-plus"></i> آیتم جدید
    </button>
  </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-3 gap-4 mb-6">
  <div class="stat-card flex items-center gap-4">
    <div style="width:44px;height:44px;background:rgba(249,115,22,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;">
      <i class="fas fa-folder" style="color:#f97316;font-size:18px;"></i>
    </div>
    <div><div class="text-2xl font-bold text-white"><?= $catCount ?></div><div class="text-xs text-slate-500">دسته‌بندی</div></div>
  </div>
  <div class="stat-card flex items-center gap-4">
    <div style="width:44px;height:44px;background:rgba(59,130,246,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;">
      <i class="fas fa-list" style="color:#60a5fa;font-size:18px;"></i>
    </div>
    <div><div class="text-2xl font-bold text-white"><?= $totalItems ?></div><div class="text-xs text-slate-500">کل آیتم‌ها</div></div>
  </div>
  <div class="stat-card flex items-center gap-4">
    <div style="width:44px;height:44px;background:rgba(34,197,94,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;">
      <i class="fas fa-check-circle" style="color:#4ade80;font-size:18px;"></i>
    </div>
    <div><div class="text-2xl font-bold text-white"><?= $availableItems ?></div><div class="text-xs text-slate-500">موجود</div></div>
  </div>
</div>

<!-- Main layout: categories sidebar + items grid -->
<div style="display:grid;grid-template-columns:240px 1fr;gap:16px;align-items:start;">

  <!-- Categories sidebar -->
  <div class="card p-0 overflow-hidden">
    <div style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:space-between;">
      <span style="font-size:13px;font-weight:700;color:#fff;">دسته‌بندی‌ها</span>
      <button onclick="openModal('catModal')" style="width:26px;height:26px;background:rgba(249,115,22,.15);border:none;border-radius:8px;color:#f97316;cursor:pointer;font-size:13px;">
        <i class="fas fa-plus"></i>
      </button>
    </div>
    <div id="catList" style="padding:8px;">
      <button class="cat-btn active" data-cat="all" onclick="filterByCat('all',this)"
        style="width:100%;display:flex;align-items:center;gap:10px;padding:9px 12px;border:none;border-radius:10px;cursor:pointer;font-family:Vazirmatn,sans-serif;font-size:13px;font-weight:500;background:rgba(249,115,22,.12);color:#f97316;margin-bottom:2px;text-align:right;">
        <i class="fas fa-th-large" style="width:16px;text-align:center;"></i>
        همه آیتم‌ها
        <span style="margin-right:auto;background:rgba(249,115,22,.2);color:#f97316;font-size:10px;padding:1px 7px;border-radius:10px;"><?= $totalItems ?></span>
      </button>
      <?php foreach ($categories as $cat): ?>
      <?php $cnt = count($byCategory[$cat['id']] ?? []); ?>
      <button class="cat-btn" data-cat="<?= $cat['id'] ?>" onclick="filterByCat(<?= $cat['id'] ?>,this)"
        style="width:100%;display:flex;align-items:center;gap:10px;padding:9px 12px;border:none;border-radius:10px;cursor:pointer;font-family:Vazirmatn,sans-serif;font-size:13px;font-weight:500;background:transparent;color:#94a3b8;margin-bottom:2px;text-align:right;"
        onmouseover="this.style.background='rgba(255,255,255,0.05)'"
        onmouseout="if(!this.classList.contains('active'))this.style.background='transparent'">
        <i class="<?= e($cat['icon'] ?? 'fas fa-circle') ?>" style="width:16px;text-align:center;color:<?= e($cat['color'] ?? '#f97316') ?>;"></i>
        <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($cat['name']) ?></span>
        <span style="background:rgba(255,255,255,0.07);color:#64748b;font-size:10px;padding:1px 7px;border-radius:10px;"><?= $cnt ?></span>
        <span onclick="event.stopPropagation();openEditCat(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES) ?>)"
          style="color:#475569;font-size:11px;padding:2px 4px;border-radius:6px;" title="ویرایش">
          <i class="fas fa-pen"></i>
        </span>
      </button>
      <?php endforeach; ?>
      <?php if (empty($categories)): ?>
      <div style="text-align:center;padding:24px 12px;color:#475569;font-size:12px;">
        <i class="fas fa-folder-open" style="font-size:28px;opacity:.3;display:block;margin-bottom:8px;"></i>
        هنوز دسته‌ای نیست
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Items grid -->
  <div>
    <div id="itemsGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;">
      <?php foreach ($items as $item): ?>
      <div class="item-card card p-0 overflow-hidden hover:border-white/15 transition-all"
        data-cat="<?= $item['category_id'] ?? 0 ?>"
        style="border:1px solid rgba(255,255,255,0.06);">

        <!-- Image / placeholder -->
        <?php if ($item['image']): ?>
        <div style="height:140px;overflow:hidden;">
          <img src="<?= e($item['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
        </div>
        <?php else: ?>
        <div style="height:80px;background:linear-gradient(135deg,rgba(249,115,22,.08),rgba(249,115,22,.03));display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-utensils" style="font-size:28px;color:rgba(249,115,22,.25);"></i>
        </div>
        <?php endif; ?>

        <div style="padding:14px;">
          <div style="display:flex;align-items:start;justify-content:space-between;gap:8px;margin-bottom:6px;">
            <div style="flex:1;">
              <div style="font-size:14px;font-weight:700;color:#fff;line-height:1.3;"><?= e($item['name']) ?></div>
              <?php if ($item['name_en']): ?>
              <div style="font-size:11px;color:#475569;margin-top:2px;"><?= e($item['name_en']) ?></div>
              <?php endif; ?>
            </div>
            <?php if (!empty($item['badge'])): ?>
            <span style="background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3);font-size:10px;padding:2px 8px;border-radius:20px;white-space:nowrap;"><?= e($item['badge']) ?></span>
            <?php endif; ?>
          </div>

          <?php if ($item['description']): ?>
          <p style="font-size:12px;color:#64748b;margin-bottom:10px;line-height:1.5;"><?= e(mb_substr($item['description'], 0, 80)) ?><?= mb_strlen($item['description']) > 80 ? '...' : '' ?></p>
          <?php endif; ?>

          <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
              <span style="font-size:16px;font-weight:800;color:#f97316;"><?= number_format((float)$item['price']) ?></span>
              <span style="font-size:11px;color:#475569;margin-right:3px;">تومان</span>
              <?php if (!empty($item['original_price']) && $item['original_price'] > 0): ?>
              <span style="font-size:11px;color:#475569;text-decoration:line-through;margin-right:6px;"><?= number_format((float)$item['original_price']) ?></span>
              <?php endif; ?>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
              <!-- availability toggle -->
              <form method="POST" action="/admin/modules/menu/items/<?= $item['id'] ?>">
                <?= csrf_input() ?>
                <input type="hidden" name="category_id"  value="<?= $item['category_id'] ?? '' ?>">
                <input type="hidden" name="name"         value="<?= e($item['name']) ?>">
                <input type="hidden" name="price"        value="<?= $item['price'] ?>">
                <input type="hidden" name="is_available" value="<?= $item['is_available'] ? 0 : 1 ?>">
                <input type="hidden" name="is_active"    value="<?= $item['is_active'] ?>">
                <button type="submit" title="<?= $item['is_available'] ? 'غیرموجود' : 'موجود' ?>"
                  style="background:<?= $item['is_available'] ? 'rgba(34,197,94,.12)' : 'rgba(239,68,68,.12)' ?>;
                         color:<?= $item['is_available'] ? '#4ade80' : '#f87171' ?>;
                         border:1px solid <?= $item['is_available'] ? 'rgba(34,197,94,.3)' : 'rgba(239,68,68,.3)' ?>;
                         border-radius:8px;padding:4px 10px;font-size:11px;cursor:pointer;font-family:Vazirmatn,sans-serif;">
                  <?= $item['is_available'] ? 'موجود' : 'ناموجود' ?>
                </button>
              </form>
              <!-- edit -->
              <button onclick='openEditItem(<?= htmlspecialchars(json_encode($item), ENT_QUOTES) ?>)'
                style="width:30px;height:30px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:8px;color:#94a3b8;cursor:pointer;font-size:12px;">
                <i class="fas fa-pen"></i>
              </button>
              <!-- delete -->
              <form method="POST" action="/admin/modules/menu/items/<?= $item['id'] ?>/delete" onsubmit="return confirm('حذف شود؟')">
                <?= csrf_input() ?>
                <button type="submit" style="width:30px;height:30px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:8px;color:#f87171;cursor:pointer;font-size:12px;">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </div>
          </div>

          <!-- category tag -->
          <?php if ($item['cat_name']): ?>
          <div style="margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,.05);">
            <span style="font-size:11px;color:#475569;background:rgba(255,255,255,.05);padding:2px 8px;border-radius:6px;">
              <?= e($item['cat_name']) ?>
            </span>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (empty($items)): ?>
      <div style="grid-column:1/-1;text-align:center;padding:60px 24px;color:#475569;">
        <i class="fas fa-utensils" style="font-size:48px;opacity:.2;display:block;margin-bottom:16px;"></i>
        <p style="font-size:14px;">هنوز آیتمی ندارید</p>
        <button onclick="openModal('itemModal')" class="btn-primary text-sm mt-4">
          <i class="fas fa-plus ml-2"></i> اولین آیتم را اضافه کنید
        </button>
      </div>
      <?php endif; ?>
    </div>

    <!-- empty state for filtered -->
    <div id="emptyFilter" style="display:none;text-align:center;padding:60px;color:#475569;">
      <i class="fas fa-search" style="font-size:36px;opacity:.2;display:block;margin-bottom:12px;"></i>
      <p>آیتمی در این دسته وجود ندارد</p>
    </div>
  </div>
</div>


<!-- ═══ Modal: Add Category ═══════════════════════════════════════════════ -->
<div id="catModal" class="modal-overlay hidden">
  <div class="modal" style="max-width:440px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <h3 style="font-size:16px;font-weight:700;color:#fff;" id="catModalTitle">دسته جدید</h3>
      <button onclick="closeModal('catModal')" style="background:none;border:none;color:#475569;font-size:18px;cursor:pointer;"><i class="fas fa-xmark"></i></button>
    </div>
    <form id="catForm" method="POST" action="/admin/modules/menu/categories">
      <?= csrf_input() ?>
      <input type="hidden" name="_method_cat" value="store">
      <div style="display:grid;gap:14px;">
        <div>
          <label class="form-label">نام دسته (فارسی) *</label>
          <input name="name" class="form-input" placeholder="مثال: غذای اصلی" required>
        </div>
        <div>
          <label class="form-label">نام دسته (انگلیسی)</label>
          <input name="name_en" class="form-input" placeholder="Main Course">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label class="form-label">آیکون FontAwesome</label>
            <input name="icon" class="form-input" placeholder="fas fa-utensils" value="fas fa-utensils">
          </div>
          <div>
            <label class="form-label">رنگ</label>
            <input name="color" type="color" class="form-input" style="height:42px;padding:4px;" value="#f97316">
          </div>
        </div>
        <div>
          <label class="form-label">ترتیب نمایش</label>
          <input name="sort_order" type="number" class="form-input" value="0" min="0">
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:20px;">
        <button type="submit" class="btn-primary flex-1">ذخیره دسته</button>
        <button type="button" onclick="closeModal('catModal')" class="btn-ghost">انصراف</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ Modal: Add/Edit Item ══════════════════════════════════════════════ -->
<div id="itemModal" class="modal-overlay hidden">
  <div class="modal" style="max-width:560px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <h3 style="font-size:16px;font-weight:700;color:#fff;" id="itemModalTitle">آیتم جدید</h3>
      <button onclick="closeModal('itemModal')" style="background:none;border:none;color:#475569;font-size:18px;cursor:pointer;"><i class="fas fa-xmark"></i></button>
    </div>
    <form id="itemForm" method="POST" action="/admin/menu/items">
      <?= csrf_input() ?>
      <div style="display:grid;gap:14px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label class="form-label">نام آیتم (فارسی) *</label>
            <input name="name" id="iName" class="form-input" placeholder="مثال: کباب برگ" required>
          </div>
          <div>
            <label class="form-label">نام (انگلیسی)</label>
            <input name="name_en" id="iNameEn" class="form-input" placeholder="Kabab Barg">
          </div>
        </div>
        <div>
          <label class="form-label">دسته‌بندی</label>
          <select name="category_id" id="iCatId" class="form-input">
            <option value="">— بدون دسته —</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">توضیحات</label>
          <textarea name="description" id="iDesc" class="form-input" rows="2" placeholder="توضیح مختصر..."></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label class="form-label">قیمت (تومان) *</label>
            <input name="price" id="iPrice" type="number" class="form-input" placeholder="150000" min="0" required>
          </div>
          <div>
            <label class="form-label">قیمت قبل (تومان)</label>
            <input name="old_price" id="iOldPrice" type="number" class="form-input" placeholder="200000" min="0">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label class="form-label">آدرس تصویر (URL)</label>
            <input name="image_url" id="iImg" class="form-input" placeholder="https://...">
          </div>
          <div>
            <label class="form-label">برچسب ویژه</label>
            <input name="badge" id="iBadge" class="form-input" placeholder="تخفیف ویژه / جدید">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px;background:rgba(255,255,255,.03);border-radius:10px;border:1px solid rgba(255,255,255,.06);">
            <input type="checkbox" name="is_available" id="iAvail" value="1" checked style="accent-color:#f97316;width:16px;height:16px;">
            <span style="font-size:13px;color:#94a3b8;">موجود است</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px;background:rgba(255,255,255,.03);border-radius:10px;border:1px solid rgba(255,255,255,.06);">
            <input type="checkbox" name="is_active" id="iActive" value="1" checked style="accent-color:#f97316;width:16px;height:16px;">
            <span style="font-size:13px;color:#94a3b8;">فعال</span>
          </label>
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:20px;">
        <button type="submit" class="btn-primary flex-1">ذخیره آیتم</button>
        <button type="button" onclick="closeModal('itemModal')" class="btn-ghost">انصراف</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'

// ── Modal helpers ──────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.add('hidden'); });
});

// ── Category filter ────────────────────────────────────────────────────────
function filterByCat(catId, btn) {
  // highlight
  document.querySelectorAll('.cat-btn').forEach(b => {
    b.classList.remove('active');
    b.style.background = 'transparent';
    b.style.color = '#94a3b8';
  });
  btn.classList.add('active');
  btn.style.background = 'rgba(249,115,22,.12)';
  btn.style.color = '#f97316';

  // show/hide items
  const cards = document.querySelectorAll('.item-card');
  let visible = 0;
  cards.forEach(c => {
    const show = catId === 'all' || c.dataset.cat == catId;
    c.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('emptyFilter').style.display = visible === 0 ? 'block' : 'none';
}

// ── Edit category ──────────────────────────────────────────────────────────
function openEditCat(cat) {
  const f = document.getElementById('catForm');
  f.action = '/admin/modules/menu/categories/' + cat.id;
  document.getElementById('catModalTitle').textContent = 'ویرایش دسته: ' + cat.name;
  f.querySelector('[name=name]').value       = cat.name || '';
  f.querySelector('[name=name_en]').value    = cat.name_en || '';
  f.querySelector('[name=icon]').value       = cat.icon || 'fas fa-utensils';
  f.querySelector('[name=color]').value      = cat.color || '#f97316';
  f.querySelector('[name=sort_order]').value = cat.sort_order || 0;
  openModal('catModal');
}
document.getElementById('catModal').querySelector('button[onclick*="catModal"]').addEventListener('click', () => {
  document.getElementById('catForm').action = '/admin/menu/categories';
  document.getElementById('catModalTitle').textContent = 'دسته جدید';
  document.getElementById('catForm').reset();
});

// ── Edit item ──────────────────────────────────────────────────────────────
function openEditItem(item) {
  const f = document.getElementById('itemForm');
  f.action = '/admin/modules/menu/items/' + item.id;
  document.getElementById('itemModalTitle').textContent = 'ویرایش: ' + item.name;
  document.getElementById('iName').value     = item.name || '';
  document.getElementById('iNameEn').value   = item.name_en || '';
  document.getElementById('iCatId').value    = item.category_id || '';
  document.getElementById('iDesc').value     = item.description || '';
  document.getElementById('iPrice').value    = item.price || '';
  document.getElementById('iOldPrice').value = item.original_price || '';
  document.getElementById('iImg').value      = item.image || '';
  document.getElementById('iBadge').value    = item.badge || '';
  document.getElementById('iAvail').checked  = item.is_available == 1;
  document.getElementById('iActive').checked = item.is_active == 1;
  openModal('itemModal');
}

JS;
?>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
