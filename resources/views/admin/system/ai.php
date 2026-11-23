<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<?php /** @var array $status @var array $problems */ ?>
<style>
  .ai-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:16px;margin-bottom:14px;}
  .ai-h{font-size:13px;font-weight:700;color:#fff;margin-bottom:10px;}
  .ai-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
  .ai-note{font-size:12px;color:#8593a8;line-height:1.95;}
  .ai-sug{border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:12px;margin-top:10px;}
  .ai-answer{white-space:pre-wrap;font-size:13.5px;line-height:2.1;color:#e2e8f0;}
  .ai-ltr{direction:ltr;unicode-bidi:isolate;text-align:left;}
</style>

<div style="margin-bottom:18px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-wand-magic-sparkles" style="color:#a855f7;margin-left:10px;"></i>دستیار هوشمند
  </h1>
  <p class="ai-note" style="margin-top:4px;">
    وضعیت سامانه را می‌خواند، مشکل را به زبان ساده می‌گوید و در صورت لزوم تغییر تنظیمات پیشنهاد می‌دهد.
  </p>
</div>

<div class="ai-card" style="border-color:rgba(168,85,247,.25);background:rgba(168,85,247,.05);">
  <div class="ai-note">
    <b style="color:#d8b4fe;">این دستیار چه کاری نمی‌کند:</b>
    هیچ فرمانی اجرا نمی‌کند (نه شل، نه SQL، نه ویرایش کد) و هیچ تغییری را خودکار اعمال نمی‌کند.
    فقط از فهرست محدودی از تنظیمات <b>پیشنهاد</b> می‌دهد و هر کدام را شما جداگانه تأیید می‌کنید.
    کلید سرویس در فایل <code>.env</code> می‌ماند و هرگز در این صفحه نمایش داده نمی‌شود.
    پیش از ارسال، رمز و کلید از متن لاگ پاک می‌شود.
  </div>
</div>

<div class="ai-card">
  <div class="ai-h"><i class="fas fa-plug ml-1" style="color:#22c55e;"></i>اتصال</div>
  <?php if (!$status['enabled']): ?>
    <div class="ai-note" style="color:#fbbf24;">
      تنظیم نشده. در فایل <code>.env</code> سرور این‌ها را بگذارید و صفحه را تازه کنید:
      <div class="ai-ltr" style="margin-top:8px;font-family:ui-monospace,monospace;font-size:11.5px;">
        AI_ENABLED=true<br>AI_BASE_URL=https://…/v1<br>AI_API_KEY=…<br>AI_MODEL=
      </div>
    </div>
  <?php else: ?>
    <div class="ai-note">
      آدرس: <span class="ai-ltr"><?= e($status['base_url']) ?></span><br>
      کلید: <span class="ai-ltr"><?= e($status['key_hint']) ?></span>
    </div>
    <div class="ai-row" style="margin-top:12px;">
      <select id="ai-model" class="form-input ai-ltr" style="max-width:320px;">
        <option value=""><?= $status['model'] !== '' ? e($status['model']) : '— مدل را انتخاب کنید —' ?></option>
      </select>
      <button class="btn-ghost text-sm" onclick="loadModels()"><i class="fas fa-rotate ml-1"></i>دریافت فهرست مدل‌ها</button>
      <button class="btn-primary text-sm" onclick="saveModel()">ذخیرهٔ مدل</button>
      <span id="ai-mstat" class="ai-note"></span>
    </div>
  <?php endif; ?>
</div>

<?php if ($problems): ?>
<div class="ai-card" style="border-color:rgba(245,158,11,.3);">
  <div class="ai-h"><i class="fas fa-triangle-exclamation ml-1" style="color:#f59e0b;"></i>مشکل‌هایی که خود سامانه می‌بیند</div>
  <?php foreach ($problems as $p): ?>
    <div class="ai-note">• <?= e($p) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($status['enabled']): ?>
<div class="ai-card">
  <div class="ai-h"><i class="fas fa-comments ml-1" style="color:#60a5fa;"></i>پرسش و عیب‌یابی</div>
  <div class="ai-row">
    <input id="ai-q" class="form-input" style="flex:1;min-width:260px;"
           placeholder="مثلا: چرا تابلوی لابی تصویر ندارد؟ (خالی بگذارید تا کل وضعیت بررسی شود)">
    <button class="btn-primary text-sm" id="ai-go" onclick="ask()"><i class="fas fa-paper-plane ml-1"></i>بررسی</button>
  </div>
  <div id="ai-out" style="margin-top:14px;"></div>
</div>
<?php endif; ?>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const $ = id => document.getElementById(id);
const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

async function call(url, body) {
  const r = await fetch(url, {
    method: body ? 'POST' : 'GET', credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF,
               ...(body ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {}) },
    body: body || undefined
  });
  const d = await r.json().catch(() => ({}));
  if (!r.ok || d.success === false) throw new Error(d.message || 'خطا');
  return d.data;
}

async function loadModels() {
  $('ai-mstat').textContent = 'در حال دریافت…';
  try {
    const list = await call('/admin/system/ai/models');
    const cur = <?= json_encode($status['model'] ?? '') ?>;
    $('ai-model').innerHTML = '<option value="">— انتخاب کنید —</option>' +
      list.map(m => `<option value="${esc(m)}"${m === cur ? ' selected' : ''}>${esc(m)}</option>`).join('');
    $('ai-mstat').textContent = list.length + ' مدل';
  } catch (e) { $('ai-mstat').textContent = '✗ ' + e.message; }
}

async function saveModel() {
  const m = $('ai-model').value;
  if (!m) { $('ai-mstat').textContent = 'اول یک مدل انتخاب کنید'; return; }
  try {
    await call('/admin/system/ai/model', new URLSearchParams({ model: m }));
    $('ai-mstat').textContent = '✓ ذخیره شد';
  } catch (e) { $('ai-mstat').textContent = '✗ ' + e.message; }
}

async function ask() {
  const btn = $('ai-go'); btn.disabled = true;
  $('ai-out').innerHTML = '<div class="ai-note"><i class="fas fa-spinner fa-spin ml-1"></i>در حال بررسی… (ممکن است چند ثانیه طول بکشد)</div>';
  try {
    const d = await call('/admin/system/ai/ask', new URLSearchParams({ question: $('ai-q').value }));
    let html = '<div class="ai-answer">' + esc(d.answer) + '</div>';
    if (d.suggestions && d.suggestions.length) {
      html += '<div class="ai-h" style="margin-top:16px;">تغییرهای پیشنهادی</div>' +
        '<div class="ai-note">هیچ‌کدام اعمال نشده‌اند. هر کدام را که خواستید تأیید کنید.</div>';
      d.suggestions.forEach((s, i) => {
        html += `<div class="ai-sug" id="sug-${i}">
          <div style="font-size:13px;color:#fff;">${esc(s.label)} → <b>${esc(s.value)}</b></div>
          <div class="ai-note ai-ltr" style="font-size:11px;">${esc(s.key)} #${esc(s.row_id)}</div>
          ${s.why ? `<div class="ai-note">${esc(s.why)}</div>` : ''}
          <button class="btn-ghost text-sm" style="margin-top:8px;" onclick='applySug(${i}, ${JSON.stringify(s).replace(/'/g, "&#39;")})'>اعمال این تغییر</button>
          <span class="ai-note" id="sug-st-${i}"></span></div>`;
      });
    }
    html += '<div class="ai-note" style="margin-top:12px;opacity:.75;">پاسخ از مدل: <b>' + esc(d.raw_model) + '</b>';
    if (d.tried && d.tried.length) {
      html += ' — این‌ها جواب ندادند و رد شدند: ' +
        d.tried.map(x => esc(x.model) + ' (' + esc(x.why) + ')').join('، ');
    }
    html += '</div>';
    $('ai-out').innerHTML = html;
  } catch (e) {
    $('ai-out').innerHTML = '<div class="ai-note" style="color:#f87171;">✗ ' + esc(e.message) + '</div>';
  }
  btn.disabled = false;
}

async function applySug(i, s) {
  const st = $('sug-st-' + i); st.textContent = ' در حال اعمال…';
  try {
    const d = await call('/admin/system/ai/apply', new URLSearchParams({ key: s.key, row_id: s.row_id, value: s.value }));
    st.textContent = ' ✓ ' + (d.message || 'اعمال شد');
  } catch (e) { st.textContent = ' ✗ ' + e.message; }
}
</script>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
