<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<?php /** @var bool $enabled @var array $changes @var array $health */ ?>
<style>
  .oh-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:16px;margin-bottom:14px;}
  .oh-h{font-size:13px;font-weight:700;color:#fff;margin-bottom:10px;}
  .oh-note{font-size:12px;color:#8593a8;line-height:1.95;}
  .oh-log{max-height:420px;overflow-y:auto;display:flex;flex-direction:column;gap:10px;}
  .oh-msg{border-radius:12px;padding:11px 13px;font-size:13px;line-height:2;}
  .oh-me{background:rgba(64,152,219,.12);border:1px solid rgba(64,152,219,.3);align-self:flex-end;max-width:88%;}
  .oh-it{background:rgba(168,85,247,.08);border:1px solid rgba(168,85,247,.25);white-space:pre-wrap;max-width:92%;}
  .oh-step{font-size:11.5px;color:#8593a8;border-right:2px solid rgba(255,255,255,.12);padding:4px 8px;margin:2px 0;}
  .oh-chg{border:1px solid rgba(34,197,94,.25);background:rgba(34,197,94,.06);border-radius:10px;padding:9px 11px;margin-top:8px;font-size:12px;}
  .oh-ltr{direction:ltr;unicode-bidi:isolate;}
</style>

<div style="margin-bottom:18px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-hand" style="color:#22c55e;margin-left:10px;"></i>OpenHand — عامل اجرایی
  </h1>
  <p class="oh-note" style="margin-top:4px;">
    دستور را فارسی بنویسید؛ عامل خودش کار را انجام می‌دهد. پیش از هر تغییر پشتیبان گرفته می‌شود
    و اگر سامانه مشکل پیدا کند همان تغییر خودکار برمی‌گردد.
  </p>
</div>

<?php if (!$health['ok']): ?>
<div class="oh-card" style="border-color:rgba(245,158,11,.35);background:rgba(245,158,11,.06);">
  <div class="oh-h" style="color:#fbbf24;"><i class="fas fa-triangle-exclamation ml-1"></i>وضعیت فعلی سامانه سالم نیست</div>
  <div class="oh-note"><?= e($health['reason']) ?></div>
  <div class="oh-note" style="opacity:.8;">تا رفع این مورد، تغییرهای نوشتنی هم بررسی سلامت را رد می‌کنند و خودکار برمی‌گردند.</div>
</div>
<?php endif; ?>

<?php if (!$enabled): ?>
<div class="oh-card" style="border-color:rgba(245,158,11,.35);">
  <div class="oh-h">اتصال مدل تنظیم نشده</div>
  <div class="oh-note">
    در فایل <code>.env</code> سرور این‌ها لازم است و بعد صفحه تازه شود:
    <div class="oh-ltr" style="margin-top:8px;font-family:ui-monospace,monospace;font-size:11.5px;">
      AI_ENABLED=true<br>AI_BASE_URL=https://…/v1<br>AI_API_KEY=…<br>AI_MODEL=
    </div>
    (همان کلیدی که «دستیار هوشمند» استفاده می‌کند.)
  </div>
</div>
<?php else: ?>

<div class="oh-card">
  <div class="oh-h"><i class="fas fa-comments ml-1" style="color:#60a5fa;"></i>دستور</div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <input id="oh-in" class="form-input" style="flex:1;min-width:280px;"
           placeholder="مثلا: پلی‌لیستی به نام «منوی صبحانه» بساز و عکس منو را داخلش بگذار">
    <button class="btn-primary text-sm" id="oh-go" onclick="ohSend()"><i class="fas fa-paper-plane ml-1"></i>اجرا</button>
    <button class="btn-ghost text-sm" onclick="ohReset()" title="پاک‌کردن گفتگو"><i class="fas fa-eraser"></i></button>
  </div>
  <div class="oh-note" style="margin-top:8px;">
    عامل فقط کاری را می‌کند که شما می‌گویید؛ اگر ابهام باشد می‌پرسد.
  </div>
  <div id="oh-log" class="oh-log" style="margin-top:14px;"></div>
</div>

<div class="oh-card">
  <div class="oh-h"><i class="fas fa-clock-rotate-left ml-1" style="color:#f59e0b;"></i>تغییرهای اخیر</div>
  <div id="oh-changes">
    <?php if (!$changes): ?>
      <div class="oh-note">هنوز تغییری ثبت نشده است.</div>
    <?php else: ?>
      <?php foreach ($changes as $c): ?>
        <?php
          $st = (string)($c['status'] ?? '');
          $label = [
            'applied'  => ['اعمال شد', '#22c55e'],
            'rolled_back' => ['برگشت داده شد', '#f59e0b'],
            'failed'   => ['ناموفق', '#f87171'],
            'failed_rollback_incomplete' => ['بازگشت ناقص', '#f87171'],
          ][$st] ?? [$st, '#8593a8'];
          $canUndo = in_array($st, ['applied', 'failed_rollback_incomplete'], true);
        ?>
        <div class="oh-chg" style="border-color:<?= e($label[1]) ?>44;background:<?= e($label[1]) ?>11;">
          <div style="color:#fff;"><?= e($c['label']) ?></div>
          <div class="oh-note oh-ltr" style="font-size:10.5px;opacity:.75;"><?= e($c['id']) ?> · <?= e($c['at']) ?></div>
          <div class="oh-note" style="color:<?= e($label[1]) ?>;"><?= e($label[0]) ?><?= $c['message'] !== '' ? ' — ' . e($c['message']) : '' ?></div>
          <?php if ($canUndo): ?>
            <button class="btn-ghost text-sm" style="margin-top:6px;" onclick="ohUndo('<?= e($c['id']) ?>', this)">
              <i class="fas fa-rotate-left ml-1"></i>بازگردانی
            </button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
const OH_CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const ohEsc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

async function ohCall(url, body) {
  const r = await fetch(url, {
    method: body ? 'POST' : 'GET', credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'X-CSRF-Token': OH_CSRF,
               ...(body ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {}) },
    body: body || undefined
  });
  const d = await r.json().catch(() => ({}));
  if (!r.ok || d.success === false) throw new Error(d.message || 'خطا');
  return d.data;
}

function ohAdd(html, cls) {
  const box = document.getElementById('oh-log');
  const div = document.createElement('div');
  div.className = 'oh-msg ' + cls;
  div.innerHTML = html;
  box.appendChild(div);
  box.scrollTop = box.scrollHeight;
  return div;
}

async function ohSend() {
  const inp = document.getElementById('oh-in');
  const msg = inp.value.trim();
  if (!msg) return;

  const btn = document.getElementById('oh-go');
  btn.disabled = true;
  inp.value = '';
  ohAdd(ohEsc(msg), 'oh-me');
  const wait = ohAdd('<i class="fas fa-spinner fa-spin ml-1"></i>در حال انجام…', 'oh-it');

  try {
    const d = await ohCall('/admin/system/agent/chat', new URLSearchParams({ message: msg }));

    let steps = '';
    (d.steps || []).forEach(s => {
      steps += `<div class="oh-step">${ohEsc(s.tool)} — ${s.ok ? '✓' : '✗'} ${ohEsc(s.message)}</div>`;
    });

    let chg = '';
    if (d.steps && d.steps.some(s => s.change_id)) {
      chg = '<div class="oh-note" style="margin-top:8px;opacity:.85;">شناسهٔ تغییر ثبت شد؛ از بخش «تغییرهای اخیر» می‌توانید برگردانید.</div>';
    }

    wait.innerHTML = ohEsc(d.answer) + (steps ? '<div style="margin-top:10px;">' + steps + '</div>' : '') + chg +
      `<div class="oh-note" style="margin-top:8px;opacity:.6;">مدل: ${ohEsc(d.model)}</div>`;

    if (d.steps && d.steps.some(s => s.change_id)) await ohReload();
  } catch (e) {
    wait.innerHTML = '<span style="color:#f87171;">✗ ' + ohEsc(e.message) + '</span>';
  }
  btn.disabled = false;
  inp.focus();
}

async function ohUndo(id, btn) {
  if (!confirm('این تغییر برگردانده شود؟')) return;
  btn.disabled = true;
  try {
    const d = await ohCall('/admin/system/agent/rollback', new URLSearchParams({ change_id: id }));
    btn.textContent = '✓ ' + (d && d.message ? d.message : 'برگشت داده شد');
    await ohReload();
  } catch (e) {
    btn.textContent = '✗ ' + e.message;
    btn.disabled = false;
  }
}

async function ohReload() {
  const box = document.getElementById('oh-changes');
  try {
    const list = await ohCall('/admin/system/agent/changes');
    if (!list.length) { box.innerHTML = '<div class="oh-note">هنوز تغییری ثبت نشده است.</div>'; return; }
    box.innerHTML = list.map(c => {
      const can = ['applied', 'failed_rollback_incomplete'].includes(c.status);
      return `<div class="oh-chg">
        <div style="color:#fff;">${ohEsc(c.label)}</div>
        <div class="oh-note oh-ltr" style="font-size:10.5px;opacity:.75;">${ohEsc(c.id)}</div>
        <div class="oh-note">${ohEsc(c.status)}${c.message ? ' — ' + ohEsc(c.message) : ''}</div>
        ${can ? `<button class="btn-ghost text-sm" style="margin-top:6px;" onclick="ohUndo('${ohEsc(c.id)}', this)"><i class="fas fa-rotate-left ml-1"></i>بازگردانی</button>` : ''}
      </div>`;
    }).join('');
  } catch (e) { /* بی‌صدا: فهرست فقط اطلاعاتی است */ }
}

async function ohReset() {
  try { await ohCall('/admin/system/agent/reset', new URLSearchParams({})); document.getElementById('oh-log').innerHTML = ''; }
  catch (e) { alert(e.message); }
}

document.getElementById('oh-in')?.addEventListener('keydown', e => { if (e.key === 'Enter') ohSend(); });
</script>
<?php endif; ?>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
