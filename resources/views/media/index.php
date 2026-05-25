<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<div class="flex items-center justify-between mb-5">
  <h1 class="text-xl font-bold text-white flex items-center gap-2">
    <i class="fas fa-photo-film text-orange-400"></i> کتابخانه رسانه
    <span style="font-size:13px;font-weight:400;color:#475569;">(<?= $total ?> فایل)</span>
  </h1>
  <div class="flex gap-2">
    <button onclick="document.getElementById('urlModal').classList.remove('hidden')"
      class="btn-ghost text-sm flex items-center gap-1.5">
      <i class="fas fa-link text-blue-400 text-xs"></i> افزودن URL
    </button>
    <label for="uploadInput" class="btn-primary text-sm flex items-center gap-1.5 cursor-pointer">
      <i class="fas fa-upload text-xs"></i> آپلود فایل
    </label>
    <input id="uploadInput" type="file" class="sr-only" multiple
      accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm"
      onchange="uploadFiles(this.files)">
  </div>
</div>

<!-- آپلود progress -->
<div id="uploadProgress" style="display:none;margin-bottom:16px;">
  <div style="background:#16161f;border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:14px 18px;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
      <i class="fas fa-circle-notch fa-spin text-orange-400"></i>
      <span id="uploadStatus" style="font-size:13px;color:#94a3b8;">در حال آپلود...</span>
    </div>
    <div style="background:rgba(255,255,255,0.06);border-radius:4px;height:6px;overflow:hidden;">
      <div id="uploadBar" style="height:100%;background:linear-gradient(90deg,#f97316,#c2570b);width:0%;transition:width 0.3s;border-radius:4px;"></div>
    </div>
  </div>
</div>

<!-- فیلتر -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
  <?php foreach (['all'=>'همه','image'=>'🖼 تصاویر','video'=>'🎬 ویدیوها','url'=>'🔗 لینک‌ها'] as $type=>$label): ?>
  <a href="<?= $type==='all'?'/admin/media':'/admin/media?type='.$type ?>"
    class="btn-ghost text-xs px-3 py-2 <?= (($_GET['type']??'all')===$type)?'bg-orange-500/10 text-orange-400 border-orange-500/30':'' ?>">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- گرید رسانه‌ها -->
<?php if (empty($media)): ?>
<div class="card text-center py-16">
  <i class="fas fa-photo-film text-5xl text-slate-700 mb-4 block"></i>
  <p class="text-slate-500 mb-4">هیچ فایلی آپلود نشده</p>
  <label for="uploadInput" class="btn-primary text-sm cursor-pointer">
    <i class="fas fa-upload text-xs ml-1"></i> آپلود اولین فایل
  </label>
</div>
<?php else: ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;" id="mediaGrid">
  <?php foreach ($media as $m): ?>
  <div class="media-card" style="background:#16161f;border:1px solid rgba(255,255,255,0.07);border-radius:14px;overflow:hidden;transition:all 0.2s;"
    onmouseenter="this.style.borderColor='rgba(249,115,22,0.4)'"
    onmouseleave="this.style.borderColor='rgba(255,255,255,0.07)'">

    <!-- تامبنیل -->
    <div style="height:110px;background:#0a0a14;overflow:hidden;position:relative;cursor:pointer;"
         onclick="previewMedia('<?= e($m['file_path']) ?>','<?= e($m['type']) ?>','<?= e($m['name']) ?>')">
      <?php if ($m['type'] === 'image'): ?>
        <img
          src="<?= e($m['thumbnail_path'] ?? $m['file_path']) ?>"
          alt="<?= e($m['name']) ?>"
          loading="lazy"
          style="width:100%;height:100%;object-fit:cover;transition:transform 0.3s;"
          onmouseenter="this.style.transform='scale(1.05)'"
          onmouseleave="this.style.transform='scale(1)'"
          onerror="this.src='/assets/img/no-thumb.svg'">
      <?php elseif ($m['type'] === 'video'): ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1a0a2e,#0a0a14);">
          <div style="text-align:center;">
            <i class="fas fa-play-circle" style="font-size:36px;color:#a855f7;"></i>
            <div style="font-size:9px;color:#64748b;margin-top:4px;font-family:monospace;"><?= e(strtoupper(pathinfo($m['original_name']??'',PATHINFO_EXTENSION))) ?></div>
          </div>
        </div>
      <?php else: ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#0a1428;">
          <div style="text-align:center;">
            <i class="fas fa-globe" style="font-size:32px;color:#3b82f6;"></i>
            <div style="font-size:9px;color:#475569;margin-top:4px;">URL</div>
          </div>
        </div>
      <?php endif; ?>

      <!-- hover overlay -->
      <div style="position:absolute;inset:0;background:rgba(0,0,0,0);transition:background 0.2s;display:flex;align-items:center;justify-content:center;gap:10px;"
           class="media-overlay"
           onmouseenter="this.style.background='rgba(0,0,0,0.5)';this.querySelectorAll('button').forEach(b=>b.style.opacity='1')"
           onmouseleave="this.style.background='rgba(0,0,0,0)';this.querySelectorAll('button').forEach(b=>b.style.opacity='0')">
        <button onclick="event.stopPropagation();previewMedia('<?= e($m['file_path']) ?>','<?= e($m['type']) ?>','<?= e($m['name']) ?>')"
          style="opacity:0;transition:opacity 0.2s;background:rgba(255,255,255,0.15);border:none;border-radius:8px;padding:7px 10px;color:#fff;cursor:pointer;">
          <i class="fas fa-eye text-sm"></i>
        </button>
        <button onclick="event.stopPropagation();deleteMedia(<?= $m['id'] ?>,this)"
          style="opacity:0;transition:opacity 0.2s;background:rgba(239,68,68,0.2);border:1px solid rgba(239,68,68,0.4);border-radius:8px;padding:7px 10px;color:#f87171;cursor:pointer;">
          <i class="fas fa-trash text-xs"></i>
        </button>
      </div>
    </div>

    <!-- اطلاعات -->
    <div style="padding:9px 10px;">
      <p style="font-size:12px;font-weight:600;color:#e2e8f0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;margin-bottom:3px;">
        <?= e($m['name']) ?>
      </p>
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:10px;color:#475569;font-family:monospace;background:rgba(255,255,255,0.05);padding:1px 5px;border-radius:4px;">
          <?= strtoupper($m['type']) ?>
        </span>
        <?php if ($m['file_size'] > 0): ?>
        <span style="font-size:10px;color:#475569;">
          <?= formatBytes((int)$m['file_size']) ?>
        </span>
        <?php endif; ?>
      </div>
    </div>

  </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<!-- Preview Modal -->
<div id="previewModal" class="modal-overlay hidden" onclick="this.classList.add('hidden')">
  <div onclick="event.stopPropagation()" style="max-width:90vw;max-height:90vh;display:flex;flex-direction:column;align-items:center;gap:12px;">
    <div style="display:flex;align-items:center;justify-content:space-between;width:100%;padding:0 4px;">
      <span id="previewTitle" style="color:#fff;font-size:14px;font-weight:600;"></span>
      <button onclick="document.getElementById('previewModal').classList.add('hidden')"
        style="background:rgba(255,255,255,0.1);border:none;border-radius:8px;padding:6px 12px;color:#fff;cursor:pointer;font-size:16px;">
        &times;
      </button>
    </div>
    <div id="previewContent" style="max-width:90vw;max-height:80vh;overflow:hidden;border-radius:12px;"></div>
  </div>
</div>

<!-- URL Modal -->
<div id="urlModal" class="modal-overlay hidden">
  <div class="modal max-w-sm">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-bold text-white"><i class="fas fa-link text-blue-400 ml-2"></i> افزودن لینک</h3>
      <button onclick="document.getElementById('urlModal').classList.add('hidden')" class="text-slate-500 hover:text-white">&times;</button>
    </div>
    <form method="POST" action="/admin/media/url" class="space-y-3">
      <?= csrf_field() ?>
      <div><label class="form-label">آدرس URL *</label>
        <input type="url" name="url" class="form-input" required placeholder="https://example.com/page"></div>
      <div><label class="form-label">نام نمایشی</label>
        <input type="text" name="name" class="form-input" placeholder="اختیاری"></div>
      <div><label class="form-label">مدت نمایش (ثانیه)</label>
        <input type="number" name="duration" class="form-input" value="30" min="1"></div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn-primary flex-1">افزودن</button>
        <button type="button" onclick="document.getElementById('urlModal').classList.add('hidden')" class="btn-ghost px-5">لغو</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
// ─── آپلود فایل ───────────────────────────────────────────────────────
async function uploadFiles(files) {
  if (!files.length) return;
  const token = localStorage.getItem('signage_token') || '';
  const prog  = document.getElementById('uploadProgress');
  const bar   = document.getElementById('uploadBar');
  const status= document.getElementById('uploadStatus');

  prog.style.display = '';
  let done = 0, failed = 0;

  for (const file of files) {
    status.textContent = `آپلود ${done+1} از ${files.length}: ${file.name}`;
    const fd = new FormData();
    fd.append('file', file);

    try {
      const r = await fetch('/api/v1/media/upload', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token },
        body: fd
      });
      const d = await r.json();
      if (d.success) {
        done++;
        addToGrid(d.data);
      } else {
        failed++;
        showToast('error', d.message || 'خطا در آپلود ' + file.name);
      }
    } catch(e) {
      failed++;
      showToast('error', 'خطا: ' + file.name);
    }

    bar.style.width = Math.round(((done + failed) / files.length) * 100) + '%';
  }

  prog.style.display = 'none';
  document.getElementById('uploadInput').value = '';

  if (done > 0) showToast('success', `${done} فایل آپلود شد`);
  if (failed > 0) showToast('error', `${failed} فایل با خطا روبرو شد`);
}

// ─── اضافه کردن به گرید بدون reload ────────────────────────────────────
function addToGrid(m) {
  const grid = document.getElementById('mediaGrid');
  if (!grid) { location.reload(); return; }

  const card = document.createElement('div');
  card.className = 'media-card';
  card.style.cssText = 'background:#16161f;border:1px solid rgba(255,255,255,0.07);border-radius:14px;overflow:hidden;';

  const thumb = m.type === 'image'
    ? `<img src="${m.thumbnail_path || m.file_path}" style="width:100%;height:110px;object-fit:cover;" onerror="this.src='/assets/img/no-thumb.svg'">`
    : `<div style="height:110px;display:flex;align-items:center;justify-content:center;background:#1a0a2e;"><i class="fas fa-play-circle" style="font-size:36px;color:#a855f7;"></i></div>`;

  card.innerHTML = `
    <div style="position:relative;overflow:hidden;">${thumb}</div>
    <div style="padding:9px 10px;">
      <p style="font-size:12px;font-weight:600;color:#e2e8f0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
        ${m.name}
      </p>
    </div>`;

  grid.prepend(card);
}

// ─── پیش‌نمایش ──────────────────────────────────────────────────────────
function previewMedia(path, type, name) {
  document.getElementById('previewTitle').textContent = name;
  const pc = document.getElementById('previewContent');

  if (type === 'image') {
    pc.innerHTML = `<img src="${path}" style="max-width:85vw;max-height:80vh;object-fit:contain;border-radius:8px;">`;
  } else if (type === 'video') {
    pc.innerHTML = `<video src="${path}" controls autoplay style="max-width:85vw;max-height:80vh;border-radius:8px;background:#000;"></video>`;
  } else {
    pc.innerHTML = `<iframe src="${path}" style="width:85vw;height:70vh;border:none;border-radius:8px;"></iframe>`;
  }

  document.getElementById('previewModal').classList.remove('hidden');
}

// ─── حذف ────────────────────────────────────────────────────────────────
async function deleteMedia(id, btn) {
  if (!confirm('این رسانه حذف شود؟')) return;
  const token = localStorage.getItem('signage_token') || '';
  const card  = btn.closest('.media-card');

  try {
    const r = await fetch('/api/v1/media/' + id, {
      method: 'DELETE',
      headers: { 'Authorization': 'Bearer ' + token }
    });
    const d = await r.json();
    if (d.success) {
      card?.remove();
      showToast('success', 'رسانه حذف شد');
    } else {
      showToast('error', d.message);
    }
  } catch(e) {
    showToast('error', 'خطا در حذف');
  }
}

// ─── Drag & Drop ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  body.addEventListener('dragover', e => { e.preventDefault(); });
  body.addEventListener('drop', e => {
    e.preventDefault();
    const files = e.dataTransfer?.files;
    if (files?.length) uploadFiles(files);
  });
});
JS;
?>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
