<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<?php $pdfReady = $pdfReady ?? false; ?>
<style>
  .ct-tab{padding:7px 14px;border-radius:10px;font-size:13px;color:#94a3b8;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);cursor:pointer;}
  .ct-tab.on{background:rgba(59,130,246,.15);color:#fff;border-color:rgba(59,130,246,.5);}
  .ct-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:14px;}
  .ct-table{width:100%;border-collapse:collapse;font-size:12px;}
  .ct-table th{color:#64748b;text-align:right;padding:8px;border-bottom:1px solid rgba(255,255,255,.07);}
  .ct-table td{color:#cbd5e1;padding:8px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;}
  .ct-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;}
  .ct-grid label{font-size:11px;color:#94a3b8;display:block;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
  <div>
    <h1 style="font-size:20px;font-weight:800;color:#fff;"><i class="fas fa-book-open" style="color:#14b8a6;margin-left:10px;"></i>محتوای اتاق</h1>
    <p style="font-size:12px;color:#64748b;margin-top:4px;">آنچه مهمان از کاشی‌های «اخبار»، «قرآن»، «کتابخانه» و «دفترچه تلفن» روی تلویزیون می‌بیند</p>
  </div>
  <button class="btn-primary text-sm" onclick="openEd()"><i class="fas fa-plus text-xs ml-1"></i>مورد جدید</button>
</div>

<?php if (!$pdfReady): ?>
<div class="ct-card" style="margin-bottom:12px;border-color:rgba(245,158,11,.4);color:#fbbf24;font-size:12px;">
  <i class="fas fa-triangle-exclamation ml-1"></i>
  کتاب PDF روی تلویزیون نمایش داده نمی‌شود چون ابزار poppler-utils روی سرور نیست:
  <code style="direction:ltr;display:inline-block;">sudo apt install poppler-utils</code> — کتاب متنی مشکلی ندارد.
</div>
<?php endif; ?>

<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;">
  <span class="ct-tab on" data-k="news" onclick="tab('news')">اخبار</span>
  <span class="ct-tab" data-k="quran" onclick="tab('quran')">قرآن</span>
  <span class="ct-tab" data-k="book" onclick="tab('book')">کتابخانه</span>
  <span class="ct-tab" data-k="directory" onclick="tab('directory')">دفترچه تلفن</span>
</div>

<div class="ct-card" style="overflow-x:auto;">
  <table class="ct-table"><thead><tr id="head"></tr></thead><tbody id="rows"></tbody></table>
</div>

<div id="ed" class="hidden" style="position:fixed;top:0;right:0;bottom:0;left:0;background:rgba(0,0,0,.72);z-index:90;overflow-y:auto;padding:24px;">
  <div class="ct-card" style="background:#0f172a;max-width:760px;margin:0 auto;">
    <h3 id="edTitle" style="color:#fff;font-weight:800;margin-bottom:12px;"></h3>
    <div class="ct-grid">
      <label style="grid-column:1/-1;">عنوان<input id="f_title" class="form-input" maxlength="250"></label>
      <label>عنوان انگلیسی<input id="f_title_en" class="form-input" maxlength="250"></label>
      <label id="l_sub">زیرعنوان<input id="f_subtitle" class="form-input" maxlength="250"></label>
      <label id="l_extra"><span id="extraLbl"></span><input id="f_extra" class="form-input" maxlength="250"></label>
      <label>دسته<input id="f_category" class="form-input" maxlength="80"></label>
      <label>ترتیب<input id="f_sort" type="number" class="form-input" value="0"></label>
      <label style="padding-top:18px;"><input type="checkbox" id="f_active" checked> نمایش روی تلویزیون</label>
      <label id="l_body" style="grid-column:1/-1;">متن<textarea id="f_body" class="form-input" rows="8"></textarea></label>
      <div id="w_file" style="grid-column:1/-1;font-size:12px;color:#94a3b8;">
        فایل PDF (کتاب یا روزنامه): <span id="v_file" class="tc-mono" style="direction:ltr;"></span>
        <label class="btn-ghost text-xs px-2" style="display:inline-block;cursor:pointer;">بارگذاری<input type="file" accept="application/pdf" style="display:none" onchange="up(this,'pdf','file_url')"></label>
        <button class="btn-ghost text-xs px-2" onclick="clr('file_url')">حذف</button>
      </div>
      <div id="w_audio" style="grid-column:1/-1;font-size:12px;color:#94a3b8;">
        صوت (تلاوت یا کتاب صوتی): <span id="v_audio" style="direction:ltr;"></span>
        <label class="btn-ghost text-xs px-2" style="display:inline-block;cursor:pointer;">بارگذاری<input type="file" accept="audio/*" style="display:none" onchange="up(this,'audio','audio_url')"></label>
        <button class="btn-ghost text-xs px-2" onclick="clr('audio_url')">حذف</button>
      </div>
      <div id="w_image" style="grid-column:1/-1;font-size:12px;color:#94a3b8;">
        تصویر: <span id="v_image" style="direction:ltr;"></span>
        <label class="btn-ghost text-xs px-2" style="display:inline-block;cursor:pointer;">بارگذاری<input type="file" accept="image/*" style="display:none" onchange="up(this,'image','image_url')"></label>
        <button class="btn-ghost text-xs px-2" onclick="clr('image_url')">حذف</button>
      </div>
      <div id="upMsg" style="grid-column:1/-1;font-size:12px;color:#60a5fa;"></div>
    </div>
    <div id="edErr" style="color:#f87171;font-size:12px;margin-top:10px;"></div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;">
      <button class="btn-ghost text-sm px-3" onclick="document.getElementById('ed').classList.add('hidden')">انصراف</button>
      <button class="btn-primary text-sm px-4" onclick="save()">ذخیره</button>
    </div>
  </div>
</div>

<script>
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const EXTRA = { news: 'منبع خبر', quran: 'قاری', book: 'نویسنده', directory: 'شماره‌ی داخلی' };
const FIELDS = { news: ['sub', 'body', 'image', 'file'], quran: ['sub', 'body', 'audio'], book: ['sub', 'body', 'file', 'audio', 'image'], directory: ['sub'] };
let KIND = 'news', ROWS = [], editing = null, urls = {};

function tab(k) {
  KIND = k;
  document.querySelectorAll('.ct-tab').forEach(t => t.classList.toggle('on', t.dataset.k === k));
  load();
}
async function load() {
  const d = await api('/api/v1/content?kind=' + KIND); ROWS = d.data || [];
  $('head').innerHTML = `<th>عنوان</th><th>${EXTRA[KIND]}</th><th>فایل</th><th>وضعیت</th><th></th>`;
  $('rows').innerHTML = ROWS.length ? ROWS.map(r => `<tr>
      <td><b style="color:#fff">${esc(r.title)}</b><div style="color:#64748b">${esc(r.subtitle || '')}</div></td>
      <td>${esc(r.extra || '')}</td>
      <td>${r.file_url ? '📄 PDF ' : ''}${r.audio_url ? '🔊 صوت ' : ''}${r.image_url ? '🖼 ' : ''}${r.body ? '📝 متن' : ''}</td>
      <td>${+r.is_active ? '<span style="color:#4ade80">فعال</span>' : '<span style="color:#64748b">مخفی</span>'}</td>
      <td style="white-space:nowrap"><button class="btn-ghost text-xs px-2" onclick="openEd(${r.id})"><i class="fas fa-pen"></i></button>
          <button class="btn-ghost text-xs px-2" onclick="del(${r.id})"><i class="fas fa-trash" style="color:#f87171"></i></button></td></tr>`).join('')
    : '<tr><td colspan="5" style="text-align:center;color:#475569;padding:24px;">چیزی اضافه نشده</td></tr>';
}
function openEd(id) {
  editing = id ? ROWS.find(r => r.id == id) : null;
  const r = editing || {};
  $('edTitle').textContent = (editing ? 'ویرایش' : 'مورد جدید') + ' — ' + document.querySelector('.ct-tab.on').textContent;
  $('f_title').value = r.title || ''; $('f_title_en').value = r.title_en || ''; $('f_subtitle').value = r.subtitle || '';
  $('f_extra').value = r.extra || ''; $('f_category').value = r.category || ''; $('f_sort').value = r.sort_order || 0;
  $('f_active').checked = editing ? !!+r.is_active : true; $('f_body').value = r.body || '';
  $('extraLbl').textContent = EXTRA[KIND];
  urls = { file_url: r.file_url || '', audio_url: r.audio_url || '', image_url: r.image_url || '' };
  const f = FIELDS[KIND];
  $('l_body').style.display = f.includes('body') ? '' : 'none';
  $('w_file').style.display = f.includes('file') ? '' : 'none';
  $('w_audio').style.display = f.includes('audio') ? '' : 'none';
  $('w_image').style.display = f.includes('image') ? '' : 'none';
  $('edErr').textContent = ''; $('upMsg').textContent = ''; paintUrls();
  $('ed').classList.remove('hidden');
}
function paintUrls() {
  $('v_file').textContent = urls.file_url || '—'; $('v_audio').textContent = urls.audio_url || '—'; $('v_image').textContent = urls.image_url || '—';
}
function clr(k) { urls[k] = ''; paintUrls(); }
function up(input, type, key) {
  const f = input.files[0]; if (!f) return;
  const fd = new FormData(); fd.append('file', f); fd.append('type', type);
  const x = new XMLHttpRequest();
  x.open('POST', '/api/v1/content/upload'); x.setRequestHeader('Accept', 'application/json'); x.setRequestHeader('X-CSRF-Token', CSRF);
  x.upload.onprogress = e => { if (e.lengthComputable) $('upMsg').textContent = `بارگذاری ${Math.round(e.loaded / e.total * 100)}٪ — ${f.name}`; };
  x.onload = () => {
    let d = {}; try { d = JSON.parse(x.responseText); } catch (_) {}
    input.value = '';
    if (x.status >= 200 && x.status < 300) {
      urls[key] = d.data.url; paintUrls();
      $('upMsg').textContent = 'بارگذاری شد' + (d.data.pages ? ` — ${d.data.pages} صفحه` : '');
    } else $('upMsg').textContent = d.message || 'بارگذاری ناموفق بود';
  };
  x.send(fd);
}
async function save() {
  const body = { kind: KIND, title: $('f_title').value.trim(), title_en: $('f_title_en').value.trim(),
    subtitle: $('f_subtitle').value.trim(), extra: $('f_extra').value.trim(), category: $('f_category').value.trim(),
    sort_order: Number($('f_sort').value || 0), is_active: $('f_active').checked ? 1 : 0, body: $('f_body').value,
    file_url: urls.file_url, audio_url: urls.audio_url, image_url: urls.image_url };
  try {
    editing ? await api('/api/v1/content/' + editing.id, 'PUT', body) : await api('/api/v1/content', 'POST', body);
    $('ed').classList.add('hidden'); showToast('success', 'ذخیره شد'); load();
  } catch (e) { $('edErr').textContent = e.message; }
}
async function del(id) {
  if (!confirm('حذف شود؟')) return;
  try { await api('/api/v1/content/' + id, 'DELETE'); load(); } catch (e) { showToast('error', e.message); }
}
async function api(url, method = 'GET', body = null) {
  const o = { method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' } };
  if (body) o.body = JSON.stringify(body);
  const r = await fetch(url, o); let d = {}; try { d = await r.json(); } catch (_) {}
  if (!r.ok || d.success === false) throw new Error(d.message || `خطا (${r.status})`);
  return d;
}
load();
</script>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
