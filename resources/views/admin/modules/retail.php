<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<div class="flex items-center justify-between mb-6">
  <h1 class="text-xl font-bold text-white flex items-center gap-2"><i class="fas fa-store text-pink-400"></i> مدیریت فروشگاه</h1>
  <div class="flex gap-2">
    <a href="/admin/modules" class="btn-ghost text-sm px-4"><i class="fas fa-arrow-right text-xs ml-1"></i> ماژول‌ها</a>
    <button onclick="document.getElementById('addProductModal').classList.remove('hidden')" class="btn-primary text-sm flex items-center gap-2"><i class="fas fa-plus text-xs"></i> محصول جدید</button>
  </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-3 gap-4 mb-6">
  <div class="stat-card border border-pink-500/20 bg-pink-500/5 text-center">
    <div class="text-3xl font-bold text-white"><?=count($products)?></div>
    <div class="text-xs text-slate-500 mt-1">کل محصولات</div>
  </div>
  <div class="stat-card border border-yellow-500/20 bg-yellow-500/5 text-center">
    <div class="text-3xl font-bold text-white"><?=count(array_filter($products,fn($p)=>$p['is_offer']))?></div>
    <div class="text-xs text-slate-500 mt-1">دارای آفر</div>
  </div>
  <div class="stat-card border border-orange-500/20 bg-orange-500/5 text-center">
    <div class="text-3xl font-bold text-white"><?=count(array_filter($products,fn($p)=>$p['is_featured']))?></div>
    <div class="text-xs text-slate-500 mt-1">محصول ویژه</div>
  </div>
</div>

<div class="card overflow-hidden">
  <table class="w-full text-sm">
    <thead><tr class="border-b border-white/5 text-xs text-slate-500 uppercase">
      <th class="text-right p-3">محصول</th><th class="text-right p-3">دسته</th><th class="text-right p-3">قیمت</th><th class="text-right p-3">آفر</th><th class="text-right p-3">ویژه</th><th class="p-3"></th>
    </tr></thead>
    <tbody>
      <?php if (empty($products)): ?>
      <tr><td colspan="6" class="text-center py-12 text-slate-600"><i class="fas fa-box text-4xl mb-3 block opacity-20"></i>محصولی ثبت نشده</td></tr>
      <?php else: foreach ($products as $p): ?>
      <tr class="border-b border-white/3 table-row">
        <td class="p-3">
          <div class="flex items-center gap-3">
            <?php if ($p['image']): ?><img src="<?=e($p['image'])?>" class="w-10 h-10 rounded-lg object-cover"><?php else: ?>
            <div class="w-10 h-10 rounded-lg bg-pink-500/10 flex items-center justify-center"><i class="fas fa-box text-pink-400 text-sm"></i></div>
            <?php endif; ?>
            <div>
              <p class="font-medium text-white"><?=e($p['name'])?></p>
              <?php if ($p['name_en']): ?><p class="text-xs text-slate-600"><?=e($p['name_en'])?></p><?php endif; ?>
            </div>
          </div>
        </td>
        <td class="p-3 text-slate-400"><?=e($p['category'])?></td>
        <td class="p-3">
          <span class="font-bold text-pink-400"><?=number_format($p['price'])?></span>
          <span class="text-xs text-slate-600"> <?=e($p['currency']??'تومان')?></span>
          <?php if ($p['old_price']): ?>
          <div class="text-xs text-slate-600 line-through"><?=number_format($p['old_price'])?></div>
          <?php endif; ?>
        </td>
        <td class="p-3"><?php if ($p['is_offer']): ?><span class="badge-online px-2 py-0.5 rounded-full text-xs border">آفر</span><?php else: ?><span class="text-slate-700 text-xs">—</span><?php endif; ?></td>
        <td class="p-3"><?php if ($p['is_featured']): ?><span class="text-yellow-400 text-xs"><i class="fas fa-star"></i> ویژه</span><?php else: ?><span class="text-slate-700 text-xs">—</span><?php endif; ?></td>
        <td class="p-3">
          <button onclick="editProduct(<?=htmlspecialchars(json_encode($p))?>,this)" class="btn-ghost text-xs px-2 py-1.5"><i class="fas fa-pencil text-blue-400 text-xs"></i></button>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- Add Product Modal -->
<div id="addProductModal" class="modal-overlay hidden">
  <div class="modal max-w-lg" style="max-height:90vh;overflow-y:auto;">
    <div class="flex items-center justify-between mb-4"><h3 class="font-bold text-white" id="productModalTitle"><i class="fas fa-box text-pink-400 ml-2"></i> محصول جدید</h3><button onclick="closeProductModal()" class="text-slate-500 hover:text-white"><i class="fas fa-xmark"></i></button></div>
    <form id="productForm" method="POST" action="/admin/modules/retail/products" class="space-y-3">
      <?= csrf_field() ?>
      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2"><label class="form-label">نام محصول *</label><input type="text" name="name" id="pName" class="form-input" required></div>
        <div><label class="form-label">نام انگلیسی</label><input type="text" name="name_en" id="pNameEn" class="form-input"></div>
        <div><label class="form-label">دسته‌بندی *</label><input type="text" name="category" id="pCat" class="form-input" required placeholder="مثلاً: لبنیات"></div>
        <div><label class="form-label">قیمت *</label><input type="number" name="price" id="pPrice" class="form-input" required min="0"></div>
        <div><label class="form-label">قیمت قبل از تخفیف</label><input type="number" name="old_price" id="pOld" class="form-input" min="0"></div>
        <div><label class="form-label">واحد پول</label><input type="text" name="currency" id="pCurr" class="form-input" value="تومان"></div>
        <div><label class="form-label">واحد اندازه</label><input type="text" name="unit" id="pUnit" class="form-input" placeholder="کیلوگرم / عدد"></div>
        <div><label class="form-label">پایان آفر</label><input type="datetime-local" name="offer_ends" id="pOfferEnds" class="form-input"></div>
        <div><label class="form-label">تصویر URL</label><input type="url" name="image" id="pImg" class="form-input"></div>
        <div class="col-span-2 flex gap-6">
          <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_offer" id="pOffer" value="1" class="accent-pink-500 w-4 h-4"><span class="text-sm text-slate-400">دارای آفر</span></label>
          <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="is_featured" id="pFeatured" value="1" class="accent-yellow-500 w-4 h-4"><span class="text-sm text-slate-400">محصول ویژه</span></label>
        </div>
      </div>
      <div class="flex gap-3 pt-2 border-t border-white/5">
        <button type="submit" class="btn-primary flex-1 py-2.5" id="productSubmitBtn">ثبت محصول</button>
        <button type="button" onclick="closeProductModal()" class="btn-ghost px-5">لغو</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
function closeProductModal() { document.getElementById('addProductModal').classList.add('hidden'); }
function editProduct(p) {
  document.getElementById('productModalTitle').innerHTML = '<i class="fas fa-pencil text-blue-400 ml-2"></i> ویرایش محصول';
  document.getElementById('productForm').action = '/admin/modules/retail/products/' + p.id;
  document.getElementById('productSubmitBtn').textContent = 'ذخیره تغییرات';
  ['pName','pNameEn','pCat','pPrice','pOld','pCurr','pUnit','pImg'].forEach(id => {
    const map = {pName:'name',pNameEn:'name_en',pCat:'category',pPrice:'price',pOld:'old_price',pCurr:'currency',pUnit:'unit',pImg:'image'};
    const el = document.getElementById(id);
    if (el) el.value = p[map[id]] || '';
  });
  document.getElementById('pOffer').checked = !!p.is_offer;
  document.getElementById('pFeatured').checked = !!p.is_featured;
  if (p.offer_ends) document.getElementById('pOfferEnds').value = p.offer_ends.replace(' ','T').slice(0,16);
  document.getElementById('addProductModal').classList.remove('hidden');
}
JS;
?>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
