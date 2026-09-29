<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<?php
$role   = $role ?? '';
$manage = in_array($role, ['super_admin', 'admin', 'manager'], true);
$redeem = $manage || $role === 'editor';
?>

<style>
  .of-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:14px;}
  .of-table{width:100%;border-collapse:collapse;font-size:12px;}
  .of-table th{color:#64748b;font-weight:600;text-align:right;padding:8px;border-bottom:1px solid rgba(255,255,255,.06);}
  .of-table td{color:#cbd5e1;padding:8px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;}
  .of-badge{font-size:10px;padding:2px 8px;border-radius:8px;white-space:nowrap;}
  .of-code{font-family:monospace;direction:ltr;display:inline-block;letter-spacing:1px;color:#fbbf24;}
  .of-lbl{font-size:12px;color:#94a3b8;display:block;}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-ticket" style="color:#e11d48;margin-left:10px;"></i>تخفیف کسب‌وکارهای اطراف
  </h1>
  <?php if ($manage): ?>
  <button class="btn-primary text-sm px-3" onclick="openEdit(null)"><i class="fas fa-plus text-xs ml-1"></i>تخفیف تازه</button>
  <?php endif; ?>
</div>

<div class="of-card" style="margin-bottom:16px;font-size:12px;color:#94a3b8;line-height:2;">
  <i class="fas fa-circle-info ml-1" style="color:#14b8a6"></i>
  مهمان از تلویزیون اتاق (کاشی «تخفیف‌های اطراف» در منوی مهمان) یک <b style="color:#fff">کد شخصی</b> می‌گیرد و در محل نشان می‌دهد.
  کسب‌وکار کد را با <b style="color:#fff">لینک اختصاصی خودش</b> تأیید می‌کند — حساب پنل لازم ندارد؛ پذیرش هم از همین صفحه می‌تواند تأیید کند.
  هر کد فقط یک بار پذیرفته می‌شود، پس تعداد «استفاده‌شده» همان معرفی‌های موفق است.
</div>

<?php if ($redeem): ?>
<div class="of-card" style="margin-bottom:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
  <span style="color:#fff;font-weight:700;font-size:13px;">تأیید کد مهمان</span>
  <input id="rCode" class="form-input" style="width:180px;direction:ltr;text-align:center;letter-spacing:2px;text-transform:uppercase;" placeholder="ABCD-2345" maxlength="12"
         onkeydown="if(event.key==='Enter')redeemCode()">
  <button class="btn-primary text-sm px-3" onclick="redeemCode()">تأیید و ثبت استفاده</button>
  <span id="rMsg" style="font-size:12px;"></span>
</div>
<?php endif; ?>

<div class="of-card" style="margin-bottom:16px;overflow-x:auto;">
  <table class="of-table">
    <thead><tr>
      <th>کسب‌وکار</th><th>تخفیف</th><th>اعتبار</th><th>سقف</th><th>کد صادرشده</th><th>استفاده‌شده</th><th>وضعیت</th><th></th>
    </tr></thead>
    <tbody id="offers"><tr><td colspan="8" style="text-align:center;color:#64748b;padding:20px;">در حال بارگذاری…</td></tr></tbody>
  </table>
</div>

<div class="of-card" style="overflow-x:auto;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:10px;flex-wrap:wrap;">
    <span style="color:#fff;font-weight:700;font-size:13px;">کدهای صادرشده</span>
    <select id="claimFilter" class="form-input" style="width:auto;" onchange="loadClaims()"><option value="">همه‌ی تخفیف‌ها</option></select>
  </div>
  <table class="of-table">
    <thead><tr><th>کد</th><th>تخفیف</th><th>اتاق</th><th>مهمان</th><th>صدور</th><th>وضعیت</th><th></th></tr></thead>
    <tbody id="claims"></tbody>
  </table>
</div>

<?php if ($manage): ?>
<div id="editModal" class="hidden" style="position:fixed;top:0;right:0;bottom:0;left:0;background:rgba(0,0,0,.7);z-index:90;display:flex;align-items:center;justify-content:center;padding:20px;">
  <div class="of-card" style="background:#0f172a;width:100%;max-width:560px;max-height:92vh;overflow-y:auto;">
    <h3 id="eTitle" style="color:#fff;font-weight:800;margin-bottom:14px;">تخفیف تازه</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
      <label class="of-lbl">نام کسب‌وکار *<input id="f_business_name" class="form-input" maxlength="150"></label>
      <label class="of-lbl">دسته
        <select id="f_category" class="form-input">
          <option value="food">رستوران و کافه</option><option value="shopping">خرید</option><option value="tour">تور و گردش</option>
          <option value="beauty">آرایش و اسپا</option><option value="fun">تفریح</option><option value="other">سایر</option>
        </select>
      </label>
      <label class="of-lbl" style="grid-column:1/-1;">عنوان تخفیف *<input id="f_title" class="form-input" maxlength="200" placeholder="۲۰٪ تخفیف شام برای مهمانان هتل"></label>
      <label class="of-lbl" style="grid-column:1/-1;">توضیح<textarea id="f_description" class="form-input" rows="3"></textarea></label>
      <label class="of-lbl" style="grid-column:1/-1;">شرایط<input id="f_terms" class="form-input" maxlength="500" placeholder="فقط شام، حداقل خرید ۵۰۰ هزار تومان"></label>
      <label class="of-lbl" style="grid-column:1/-1;">نشانی<input id="f_address" class="form-input" maxlength="300"></label>
      <label class="of-lbl">تلفن<input id="f_phone" class="form-input" maxlength="40" style="direction:ltr;"></label>
      <label class="of-lbl">فاصله<input id="f_distance" class="form-input" maxlength="50" placeholder="۵ دقیقه پیاده"></label>
      <label class="of-lbl" style="grid-column:1/-1;">تصویر (آدرس /uploads/… یا https)<input id="f_image" class="form-input" maxlength="500" style="direction:ltr;"></label>
      <label class="of-lbl">از تاریخ<input id="f_valid_from" type="date" class="form-input"></label>
      <label class="of-lbl">تا تاریخ<input id="f_valid_to" type="date" class="form-input"></label>
      <label class="of-lbl">کد برای هر اقامت<input id="f_per_stay_limit" type="number" min="1" max="20" class="form-input" value="1"></label>
      <label class="of-lbl">سقف کل کدها<input id="f_total_limit" type="number" min="1" class="form-input" placeholder="خالی = بی‌سقف"></label>
      <label class="of-lbl">ترتیب<input id="f_sort_order" type="number" min="0" class="form-input" value="0"></label>
      <label class="of-lbl" style="display:flex;align-items:center;gap:6px;margin-top:18px;"><input id="f_is_active" type="checkbox" checked> فعال</label>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
      <button class="btn-ghost text-sm px-3" onclick="closeModal('editModal')">انصراف</button>
      <button class="btn-primary text-sm px-3" onclick="saveOffer()">ذخیره</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
const MANAGE = <?= $manage ? 'true' : 'false' ?>;
const CAN_REDEEM = <?= $redeem ? 'true' : 'false' ?>;
const CAT = { food: 'رستوران و کافه', shopping: 'خرید', tour: 'تور و گردش', beauty: 'آرایش و اسپا', fun: 'تفریح', other: 'سایر' };
const CST = { issued: ['صادرشده', '#3b82f6'], redeemed: ['استفاده شد', '#22c55e'], void: ['باطل', '#64748b'] };
const FIELDS = ['business_name','category','title','description','terms','address','phone','distance','image',
                'valid_from','valid_to','per_stay_limit','total_limit','sort_order'];
let offers = [], editing = null;

function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function badge(t, c) { return `<span class="of-badge" style="background:${c}22;color:${c}">${esc(t)}</span>`; }
function partnerUrl(o) { return location.origin + '/partner/offer/' + o.partner_token; }

async function loadOffers() {
  const d = await apiFetch('/api/v1/offers');
  offers = d.data || [];
  const today = new Date().toISOString().slice(0, 10);
  const tb = document.getElementById('offers');
  tb.innerHTML = offers.length ? offers.map(o => {
    const expired = o.valid_to && o.valid_to < today;
    const st = !+o.is_active ? badge('غیرفعال', '#64748b') : expired ? badge('منقضی', '#f59e0b') : badge('فعال', '#22c55e');
    const valid = (o.valid_from || '…') + ' تا ' + (o.valid_to || '…');
    return `<tr>
      <td><b style="color:#fff">${esc(o.business_name)}</b><div style="color:#64748b">${esc(CAT[o.category] || '')}</div></td>
      <td>${esc(o.title)}</td>
      <td style="white-space:nowrap">${o.valid_from || o.valid_to ? esc(valid) : 'همیشه'}</td>
      <td>${o.per_stay_limit} در هر اقامت${o.total_limit ? '<br>کل ' + o.total_limit : ''}</td>
      <td>${o.issued}</td>
      <td><b style="color:#22c55e">${o.redeemed}</b></td>
      <td>${st}</td>
      <td style="white-space:nowrap">
        ${MANAGE ? `<button class="btn-ghost text-xs px-2" onclick="openEdit(${o.id})"><i class="fas fa-pen"></i></button>
        <button class="btn-ghost text-xs px-2" title="کپی لینک تأیید برای کسب‌وکار" onclick="copyLink(${o.id})"><i class="fas fa-link"></i></button>
        <button class="btn-ghost text-xs px-2" title="لینک تازه (لینک قبلی باطل می‌شود)" onclick="rotate(${o.id})"><i class="fas fa-rotate"></i></button>` : ''}
      </td></tr>`;
  }).join('') : `<tr><td colspan="8" style="text-align:center;color:#64748b;padding:20px;">هنوز تخفیفی ثبت نشده${MANAGE ? ' — «تخفیف تازه» را بزنید' : ''}</td></tr>`;

  const sel = document.getElementById('claimFilter'), keep = sel.value;
  sel.innerHTML = '<option value="">همه‌ی تخفیف‌ها</option>' +
    offers.map(o => `<option value="${o.id}">${esc(o.business_name)} — ${esc(o.title)}</option>`).join('');
  sel.value = keep;
}

async function loadClaims() {
  const f = document.getElementById('claimFilter').value;
  const d = await apiFetch('/api/v1/offers/claims' + (f ? '?offer_id=' + f : ''));
  const rows = d.data || [];
  document.getElementById('claims').innerHTML = rows.length ? rows.map(c => {
    const s = CST[c.status] || [c.status, '#64748b'];
    const via = c.redeemed_via === 'partner' ? ' (کسب‌وکار)' : c.redeemed_via === 'panel' ? ' (پذیرش)' : '';
    return `<tr>
      <td><span class="of-code">${esc(c.code)}</span></td>
      <td>${esc(c.business_name)}<div style="color:#64748b">${esc(c.title)}</div></td>
      <td>${esc(c.room_number || '—')}</td>
      <td>${esc(c.guest_name || '')}</td>
      <td style="white-space:nowrap">${esc(String(c.created_at).slice(0, 16))}</td>
      <td>${badge(s[0] + via, s[1])}${c.redeemed_at ? '<div style="color:#64748b">' + esc(String(c.redeemed_at).slice(0, 16)) + '</div>' : ''}</td>
      <td>${MANAGE && c.status === 'issued' ? `<button class="btn-ghost text-xs px-2" onclick="voidClaim(${c.id})">باطل</button>` : ''}</td>
    </tr>`;
  }).join('') : '<tr><td colspan="7" style="text-align:center;color:#64748b;padding:16px;">کدی صادر نشده</td></tr>';
}

async function redeemCode() {
  const inp = document.getElementById('rCode'), out = document.getElementById('rMsg');
  const code = inp.value.trim();
  if (!code) return;
  try {
    const d = await apiFetch('/api/v1/offers/redeem', 'POST', { code });
    out.innerHTML = `<b style="color:#22c55e">✓ ${esc(d.message)}</b> — ${esc(d.data.business_name)}: ${esc(d.data.title)}`;
    inp.value = '';
    loadOffers(); loadClaims();
  } catch (e) {
    out.innerHTML = `<b style="color:#f87171">✗ ${esc(e.message)}</b>`;
  }
}

function openEdit(id) {
  editing = id;
  const o = id ? offers.find(x => x.id == id) : null;
  document.getElementById('eTitle').textContent = o ? 'ویرایش تخفیف' : 'تخفیف تازه';
  FIELDS.forEach(k => { document.getElementById('f_' + k).value = o ? (o[k] ?? '') : ({ category: 'food', per_stay_limit: 1, sort_order: 0 }[k] ?? ''); });
  document.getElementById('f_is_active').checked = o ? !!+o.is_active : true;
  document.getElementById('editModal').classList.remove('hidden');
}

async function saveOffer() {
  const body = {};
  FIELDS.forEach(k => { body[k] = document.getElementById('f_' + k).value; });
  body.is_active = document.getElementById('f_is_active').checked ? 1 : 0;
  try {
    const d = editing ? await apiFetch('/api/v1/offers/' + editing, 'PUT', body) : await apiFetch('/api/v1/offers', 'POST', body);
    showToast('success', d.message);
    closeModal('editModal');
    loadOffers();
  } catch (e) { showToast('error', e.message); }
}

async function copyLink(id) {
  const o = offers.find(x => x.id == id);
  const url = partnerUrl(o);
  try { await navigator.clipboard.writeText(url); showToast('success', 'لینک کپی شد؛ برای ' + o.business_name + ' بفرستید'); }
  catch (_) { prompt('لینک تأیید کد برای کسب‌وکار:', url); }
}

async function rotate(id) {
  if (!confirm('لینک تازه ساخته شود؟ لینک فعلی کسب‌وکار دیگر کار نمی‌کند.')) return;
  try { const d = await apiFetch('/api/v1/offers/' + id + '/rotate', 'POST', {}); showToast('success', d.message); await loadOffers(); copyLink(id); }
  catch (e) { showToast('error', e.message); }
}

async function voidClaim(id) {
  if (!confirm('این کد باطل شود؟ مهمان دیگر نمی‌تواند از آن استفاده کند.')) return;
  try { const d = await apiFetch('/api/v1/offers/claims/' + id + '/void', 'POST', {}); showToast('success', d.message); loadOffers(); loadClaims(); }
  catch (e) { showToast('error', e.message); }
}

async function apiFetch(url, method = 'GET', body = null) {
  const opts = { method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, credentials: 'same-origin' };
  if (body) opts.body = JSON.stringify(body);
  const r = await fetch(url, opts);
  let d = {};
  try { d = await r.json(); } catch (_) {}
  if (!r.ok) throw new Error(d.message || `خطا (${r.status})`);
  return d;
}
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

loadOffers().then(loadClaims).catch(e => showToast('error', e.message));
setInterval(() => { if (!document.hidden) { loadOffers(); loadClaims(); } }, 30000);
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
