<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<style>
/* ── VOD File Manager ─────────────────────────────── */
.vod-wrap   { display:flex; gap:0; height:calc(100vh - 90px); overflow:hidden; }
.vod-side   { width:220px; flex-shrink:0; background:#111318; border-left:1px solid rgba(255,255,255,.06);
              overflow-y:auto; display:flex; flex-direction:column; }
.vod-main   { flex:1; display:flex; flex-direction:column; overflow:hidden; }
.vod-toolbar{ padding:10px 14px; background:#16161f; border-bottom:1px solid rgba(255,255,255,.06);
              display:flex; align-items:center; gap:8px; flex-wrap:wrap; flex-shrink:0; }
.vod-grid   { flex:1; overflow-y:auto; padding:14px; }

/* sidebar */
.side-header{ padding:12px 14px 6px; font-size:11px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.5px; }
.cat-item   { display:flex; align-items:center; gap:8px; padding:7px 14px; cursor:pointer;
              font-size:13px; color:#94a3b8; border-right:3px solid transparent; transition:.15s; }
.cat-item:hover{ background:rgba(255,255,255,.04); color:#e2e8f0; }
.cat-item.active{ background:rgba(124,58,237,.1); color:#a78bfa; border-right-color:#7c3aed; }
.cat-dot    { width:8px;height:8px;border-radius:50%;flex-shrink:0; }
.cat-count  { margin-right:auto; background:rgba(255,255,255,.07); border-radius:20px;
              padding:1px 7px; font-size:10px; }

/* cards grid */
.video-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:12px; }
.video-list { display:flex; flex-direction:column; gap:6px; }

.vcard { background:#16161f; border:1px solid rgba(255,255,255,.06); border-radius:12px;
         overflow:hidden; cursor:pointer; transition:.15s; position:relative; }
.vcard:hover { border-color:rgba(124,58,237,.4); transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.4); }
.vcard.selected { border-color:#7c3aed; background:rgba(124,58,237,.08); }
.vcard-thumb { aspect-ratio:16/9; background:#0a0a10; position:relative; overflow:hidden; }
.vcard-thumb img { width:100%;height:100%;object-fit:cover; }
.vcard-thumb .no-thumb { display:flex;align-items:center;justify-content:center;height:100%;color:#374151; }
.vcard-dur  { position:absolute;bottom:5px;right:5px;background:rgba(0,0,0,.8);color:#fff;
              font-size:10px;padding:2px 5px;border-radius:4px;font-family:monospace; }
.vcard-body { padding:9px 10px; }
.vcard-title{ font-size:12px;font-weight:600;color:#e2e8f0;line-height:1.4;
              overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical; }
.vcard-meta { font-size:10px;color:#475569;margin-top:4px;display:flex;gap:6px;flex-wrap:wrap; }
.vcard-sel  { position:absolute;top:7px;left:7px;width:20px;height:20px;border-radius:6px;
              border:2px solid rgba(255,255,255,.3);background:rgba(0,0,0,.5);
              display:flex;align-items:center;justify-content:center;z-index:2; }
.vcard.selected .vcard-sel { background:#7c3aed;border-color:#7c3aed; }

/* list row */
.vrow { display:flex;align-items:center;gap:10px;background:#16161f;border:1px solid rgba(255,255,255,.06);
        border-radius:10px;padding:8px 12px;cursor:pointer;transition:.15s; }
.vrow:hover { border-color:rgba(124,58,237,.3); }
.vrow.selected { border-color:#7c3aed;background:rgba(124,58,237,.06); }
.vrow-thumb { width:60px;height:34px;border-radius:6px;object-fit:cover;background:#0a0a10;flex-shrink:0; }
.vrow-title { flex:1;font-size:13px;color:#e2e8f0;font-weight:500;overflow:hidden;white-space:nowrap;text-overflow:ellipsis; }
.vrow-meta  { display:flex;gap:14px;align-items:center;flex-shrink:0; }
.vrow-badge { font-size:10px;padding:2px 8px;border-radius:20px;background:rgba(124,58,237,.15);color:#a78bfa; }

/* upload area */
.drop-zone { border:2px dashed rgba(124,58,237,.3);border-radius:14px;padding:32px 20px;text-align:center;
             background:rgba(124,58,237,.03);transition:.2s;cursor:pointer; }
.drop-zone.drag-over { border-color:#7c3aed;background:rgba(124,58,237,.08); }
.drop-zone:hover { border-color:rgba(124,58,237,.5); }

/* progress */
.upload-queue { display:flex;flex-direction:column;gap:6px;margin-top:12px;max-height:200px;overflow-y:auto; }
.upload-item  { background:#16161f;border-radius:8px;padding:8px 12px;font-size:12px; }
.prog-bar     { height:4px;background:#1e293b;border-radius:4px;margin-top:6px;overflow:hidden; }
.prog-fill    { height:100%;background:linear-gradient(90deg,#7c3aed,#a78bfa);border-radius:4px;transition:width .3s; }

/* player modal */
.modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:200;display:flex;
                 align-items:center;justify-content:center;backdrop-filter:blur(4px); }
.modal-box  { background:#16161f;border:1px solid rgba(255,255,255,.1);border-radius:16px;
              width:min(900px,95vw);max-height:92vh;overflow:hidden;display:flex;flex-direction:column; }
.modal-head { padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;gap:10px; }
.modal-body { overflow-y:auto;flex:1; }

/* stats bar */
.stat-chip { display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.04);
             border-radius:8px;padding:5px 12px;font-size:12px;color:#94a3b8; }
.stat-chip span { color:#e2e8f0;font-weight:700; }

/* inputs */
.inp { background:#0f172a;border:1px solid #334155;border-radius:8px;color:#e2e8f0;
       padding:7px 12px;font-size:12px;outline:none;font-family:inherit; }
.inp:focus { border-color:#7c3aed; }
.inp-sm { padding:5px 10px;font-size:11px; }
select.inp { cursor:pointer; }
</style>

<!-- ── Header ─────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
  <h1 style="font-size:20px;font-weight:800;color:#fff;">
    <i class="fas fa-film" style="color:#7c3aed;margin-left:10px;"></i>مدیریت VOD
  </h1>
  <div style="display:flex;gap:8px;">
    <button onclick="openAddCatModal()" class="btn-ghost text-sm">
      <i class="fas fa-folder-plus text-xs text-purple-400"></i> دسته‌بندی جدید
    </button>
    <button onclick="openUrlModal()" class="btn-ghost text-sm">
      <i class="fas fa-link text-xs text-blue-400"></i> افزودن URL
    </button>
    <button onclick="openUploadModal()" class="btn-primary text-sm">
      <i class="fas fa-cloud-upload-alt text-xs"></i> آپلود ویدیو
    </button>
  </div>
</div>

<!-- ── Stats Bar ─────────────────────────────────── -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;" id="statsBar">
  <?php foreach([
    ['fas fa-film','#7c3aed', $stats['total'] ?? 0, 'کل ویدیو'],
    ['fas fa-upload','#22c55e', $stats['uploads'] ?? 0, 'آپلود شده'],
    ['fas fa-folder','#f59e0b', $stats['categories'] ?? 0, 'دسته‌بندی'],
    ['fas fa-hdd','#3b82f6', $stats['total_size_fmt'] ?? '0 B', 'فضای مصرفی'],
    ['fas fa-eye','#ef4444', number_format($stats['total_views'] ?? 0), 'بازدید کل'],
  ] as [$ic,$c,$v,$l]): ?>
  <div class="stat-chip"><i class="<?=$ic?>" style="color:<?=$c?>;font-size:13px;"></i><span><?=$v?></span><?=$l?></div>
  <?php endforeach; ?>
</div>

<!-- ── Main Layout ─────────────────────────────────── -->
<div class="vod-wrap" style="border:1px solid rgba(255,255,255,.06);border-radius:14px;overflow:hidden;">

  <!-- Sidebar: دسته‌بندی‌ها -->
  <div class="vod-side">
    <div class="side-header">دسته‌بندی‌ها</div>

    <div class="cat-item active" data-cat="" onclick="filterCat(this,'')">
      <i class="fas fa-th-large" style="font-size:12px;color:#7c3aed;"></i>
      همه ویدیوها
      <span class="cat-count" id="total-count"><?= $stats['total'] ?? 0 ?></span>
    </div>
    <div class="cat-item" data-cat="null" onclick="filterCat(this,'null')">
      <i class="fas fa-question-circle" style="font-size:12px;color:#64748b;"></i>
      دسته‌بندی‌نشده
      <span class="cat-count" id="uncategorized-count">—</span>
    </div>

    <div id="sidebarCats">
    <?php foreach($categories as $cat): ?>
    <div class="cat-item" data-cat="<?=$cat['id']?>" onclick="filterCat(this,'<?=$cat['id']?>')">
      <span class="cat-dot" style="background:<?=e($cat['color'])?>"></span>
      <?=e($cat['name'])?>
      <span class="cat-count"><?=$cat['video_count']?></span>
    </div>
    <?php endforeach; ?>
    </div>

    <div style="margin-top:auto;padding:12px 14px;border-top:1px solid rgba(255,255,255,.05);">
      <button onclick="openAddCatModal()" style="width:100%;padding:7px;background:rgba(124,58,237,.1);border:1px dashed rgba(124,58,237,.3);border-radius:8px;color:#a78bfa;font-size:12px;cursor:pointer;">
        <i class="fas fa-plus text-xs"></i> دسته‌بندی جدید
      </button>
    </div>
  </div>

  <!-- Main Area -->
  <div class="vod-main">

    <!-- Toolbar -->
    <div class="vod-toolbar">
      <!-- جستجو -->
      <div style="position:relative;flex:1;min-width:180px;">
        <i class="fas fa-search" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#475569;font-size:11px;"></i>
        <input type="text" id="searchInput" placeholder="جستجوی ویدیو ..." class="inp inp-sm" style="width:100%;padding-right:30px;"
          oninput="debounceSearch(this.value)">
      </div>

      <!-- فیلتر نوع -->
      <select id="typeFilter" class="inp inp-sm" onchange="loadVideos()" style="width:120px;">
        <option value="">همه انواع</option>
        <option value="upload">آپلودشده</option>
        <option value="url">URL خارجی</option>
        <option value="youtube">YouTube</option>
        <option value="vimeo">Vimeo</option>
      </select>

      <!-- مرتب‌سازی -->
      <select id="sortSelect" class="inp inp-sm" onchange="loadVideos()" style="width:120px;">
        <option value="newest">جدیدترین</option>
        <option value="oldest">قدیمی‌ترین</option>
        <option value="name">نام</option>
        <option value="size">حجم</option>
        <option value="views">بازدید</option>
      </select>

      <div style="display:flex;gap:4px;background:rgba(255,255,255,.05);border-radius:8px;padding:3px;">
        <button id="btnGrid" onclick="setView('grid')" title="نمای کارتی"
          style="padding:4px 8px;border-radius:6px;background:#7c3aed;border:none;color:#fff;cursor:pointer;font-size:11px;">
          <i class="fas fa-th"></i>
        </button>
        <button id="btnList" onclick="setView('list')" title="نمای لیستی"
          style="padding:4px 8px;border-radius:6px;background:transparent;border:none;color:#64748b;cursor:pointer;font-size:11px;">
          <i class="fas fa-list"></i>
        </button>
      </div>

      <!-- دکمه‌های bulk -->
      <div id="bulkBar" style="display:none;gap:6px;align-items:center;">
        <span id="selCount" style="font-size:12px;color:#a78bfa;"></span>
        <button onclick="bulkDelete()" style="padding:5px 12px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:8px;color:#f87171;font-size:11px;cursor:pointer;">
          <i class="fas fa-trash text-xs"></i> حذف انتخاب‌شده‌ها
        </button>
        <button onclick="clearSelection()" style="padding:5px 10px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#94a3b8;font-size:11px;cursor:pointer;">
          لغو
        </button>
      </div>
    </div>

    <!-- Video Grid/List -->
    <div class="vod-grid" id="videoArea">
      <div id="videoContainer" class="video-grid"></div>
      <!-- pagination -->
      <div id="pagination" style="display:flex;justify-content:center;gap:6px;margin-top:16px;"></div>
      <!-- empty -->
      <div id="emptyState" style="display:none;text-align:center;padding:60px 20px;color:#475569;">
        <i class="fas fa-film" style="font-size:48px;margin-bottom:12px;opacity:.3;"></i>
        <div style="font-size:14px;">ویدیویی پیدا نشد</div>
        <button onclick="openUploadModal()" style="margin-top:14px;padding:8px 20px;background:#7c3aed;border:none;border-radius:8px;color:#fff;cursor:pointer;font-size:13px;">
          اولین ویدیو را آپلود کنید
        </button>
      </div>
      <!-- loading -->
      <div id="loadingState" style="text-align:center;padding:60px 20px;color:#475569;">
        <i class="fas fa-spinner fa-spin" style="font-size:32px;margin-bottom:12px;color:#7c3aed;"></i>
        <div style="font-size:13px;">در حال بارگذاری ...</div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════ -->
<!-- MODALS                                              -->
<!-- ═══════════════════════════════════════════════════ -->

<!-- آپلود ویدیو -->
<div id="uploadModal" style="display:none;" class="modal-overlay" onclick="if(event.target===this)closeUploadModal()">
  <div class="modal-box" style="width:min(600px,95vw);">
    <div class="modal-head">
      <i class="fas fa-cloud-upload-alt" style="color:#7c3aed;font-size:18px;"></i>
      <span style="font-size:15px;font-weight:700;color:#fff;">آپلود ویدیو</span>
      <button onclick="closeUploadModal()" style="margin-right:auto;background:none;border:none;color:#64748b;cursor:pointer;font-size:18px;">✕</button>
    </div>
    <div class="modal-body" style="padding:20px;">

      <!-- Drop Zone -->
      <div class="drop-zone" id="dropZone"
           onclick="document.getElementById('fileInput').click()"
           ondragover="event.preventDefault();this.classList.add('drag-over')"
           ondragleave="this.classList.remove('drag-over')"
           ondrop="handleDrop(event)">
        <i class="fas fa-cloud-upload-alt" style="font-size:40px;color:#7c3aed;margin-bottom:12px;"></i>
        <div style="font-size:14px;font-weight:600;color:#e2e8f0;margin-bottom:6px;">فایل‌ها را اینجا رها کنید</div>
        <div style="font-size:12px;color:#64748b;">یا کلیک کنید برای انتخاب</div>
        <div style="font-size:11px;color:#475569;margin-top:8px;">MP4، WebM، MKV، AVI، MOV — حداکثر 500MB</div>
        <input type="file" id="fileInput" multiple accept="video/*" style="display:none;" onchange="handleFileSelect(this.files)">
      </div>

      <!-- Upload Queue -->
      <div class="upload-queue" id="uploadQueue"></div>

      <!-- تنظیمات آپلود -->
      <div style="margin-top:14px;display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div>
          <label style="display:block;font-size:11px;color:#64748b;margin-bottom:4px;">دسته‌بندی پیش‌فرض</label>
          <select id="uploadCatId" class="inp" style="width:100%;">
            <option value="">بدون دسته‌بندی</option>
            <?php foreach($categories as $cat): ?>
            <option value="<?=$cat['id']?>"><?=e($cat['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex;align-items:flex-end;">
          <button onclick="startUpload()" id="startUploadBtn" style="width:100%;padding:9px;background:#7c3aed;border:none;border-radius:8px;color:#fff;font-size:13px;font-weight:600;cursor:pointer;"
            disabled>آپلود</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- افزودن URL -->
<div id="urlModal" style="display:none;" class="modal-overlay" onclick="if(event.target===this)closeUrlModal()">
  <div class="modal-box" style="width:min(480px,95vw);">
    <div class="modal-head">
      <i class="fas fa-link" style="color:#3b82f6;font-size:18px;"></i>
      <span style="font-size:15px;font-weight:700;color:#fff;">افزودن ویدیوی URL</span>
      <button onclick="closeUrlModal()" style="margin-right:auto;background:none;border:none;color:#64748b;cursor:pointer;font-size:18px;">✕</button>
    </div>
    <div class="modal-body" style="padding:20px;display:flex;flex-direction:column;gap:12px;">
      <div>
        <label style="display:block;font-size:11px;color:#64748b;margin-bottom:4px;">آدرس ویدیو *</label>
        <input type="url" id="urlInput" class="inp" style="width:100%;" placeholder="https://... یا rtsp://...">
        <div style="font-size:10px;color:#475569;margin-top:4px;">YouTube, Vimeo, HLS, MP4, RTSP پشتیبانی می‌شود</div>
      </div>
      <div>
        <label style="display:block;font-size:11px;color:#64748b;margin-bottom:4px;">عنوان</label>
        <input type="text" id="urlTitle" class="inp" style="width:100%;" placeholder="عنوان ویدیو">
      </div>
      <div>
        <label style="display:block;font-size:11px;color:#64748b;margin-bottom:4px;">دسته‌بندی</label>
        <select id="urlCatId" class="inp" style="width:100%;">
          <option value="">بدون دسته‌بندی</option>
          <?php foreach($categories as $cat): ?>
          <option value="<?=$cat['id']?>"><?=e($cat['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:4px;">
        <button onclick="closeUrlModal()" style="padding:9px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#94a3b8;font-size:13px;cursor:pointer;">انصراف</button>
        <button onclick="submitUrl()" style="padding:9px;background:#3b82f6;border:none;border-radius:8px;color:#fff;font-size:13px;font-weight:600;cursor:pointer;">افزودن</button>
      </div>
    </div>
  </div>
</div>

<!-- دسته‌بندی جدید -->
<div id="addCatModal" style="display:none;" class="modal-overlay" onclick="if(event.target===this)closeAddCatModal()">
  <div class="modal-box" style="width:min(400px,95vw);">
    <div class="modal-head">
      <i class="fas fa-folder-plus" style="color:#f59e0b;font-size:18px;"></i>
      <span style="font-size:15px;font-weight:700;color:#fff;">دسته‌بندی جدید</span>
      <button onclick="closeAddCatModal()" style="margin-right:auto;background:none;border:none;color:#64748b;cursor:pointer;font-size:18px;">✕</button>
    </div>
    <div class="modal-body" style="padding:20px;display:flex;flex-direction:column;gap:12px;">
      <div>
        <label style="display:block;font-size:11px;color:#64748b;margin-bottom:4px;">نام دسته‌بندی *</label>
        <input type="text" id="catName" class="inp" style="width:100%;" placeholder="مثلاً: فیلم‌های آموزشی">
      </div>
      <div>
        <label style="display:block;font-size:11px;color:#64748b;margin-bottom:4px;">توضیحات</label>
        <input type="text" id="catDesc" class="inp" style="width:100%;" placeholder="اختیاری">
      </div>
      <div style="display:flex;align-items:center;gap:10px;">
        <label style="font-size:11px;color:#64748b;">رنگ:</label>
        <input type="color" id="catColor" value="#7c3aed" style="width:40px;height:32px;border:none;background:none;cursor:pointer;border-radius:6px;">
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <?php foreach(['#7c3aed','#3b82f6','#22c55e','#f59e0b','#ef4444','#ec4899','#64748b'] as $c): ?>
          <div onclick="document.getElementById('catColor').value='<?=$c?>'" style="width:18px;height:18px;border-radius:50%;background:<?=$c?>;cursor:pointer;"></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:4px;">
        <button onclick="closeAddCatModal()" style="padding:9px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#94a3b8;font-size:13px;cursor:pointer;">انصراف</button>
        <button onclick="submitCategory()" style="padding:9px;background:#f59e0b;border:none;border-radius:8px;color:#000;font-size:13px;font-weight:700;cursor:pointer;">ایجاد</button>
      </div>
    </div>
  </div>
</div>

<!-- ویرایش ویدیو -->
<div id="editModal" style="display:none;" class="modal-overlay" onclick="if(event.target===this)closeEditModal()">
  <div class="modal-box" style="width:min(500px,95vw);">
    <div class="modal-head">
      <i class="fas fa-edit" style="color:#22c55e;font-size:18px;"></i>
      <span style="font-size:15px;font-weight:700;color:#fff;">ویرایش ویدیو</span>
      <button onclick="closeEditModal()" style="margin-right:auto;background:none;border:none;color:#64748b;cursor:pointer;font-size:18px;">✕</button>
    </div>
    <div class="modal-body" style="padding:20px;display:flex;flex-direction:column;gap:12px;">
      <input type="hidden" id="editId">
      <div>
        <label style="display:block;font-size:11px;color:#64748b;margin-bottom:4px;">عنوان *</label>
        <input type="text" id="editTitle" class="inp" style="width:100%;">
      </div>
      <div>
        <label style="display:block;font-size:11px;color:#64748b;margin-bottom:4px;">توضیحات</label>
        <textarea id="editDesc" class="inp" style="width:100%;height:70px;resize:vertical;" placeholder="اختیاری"></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div>
          <label style="display:block;font-size:11px;color:#64748b;margin-bottom:4px;">دسته‌بندی</label>
          <select id="editCatId" class="inp" style="width:100%;">
            <option value="">بدون دسته</option>
            <?php foreach($categories as $cat): ?>
            <option value="<?=$cat['id']?>"><?=e($cat['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:11px;color:#64748b;margin-bottom:4px;">سال</label>
          <input type="number" id="editYear" class="inp" style="width:100%;" placeholder="مثلاً 1402" min="1300" max="1500">
        </div>
      </div>
      <div>
        <label style="display:block;font-size:11px;color:#64748b;margin-bottom:4px;">تگ‌ها (جداشده با ویرگول)</label>
        <input type="text" id="editTags" class="inp" style="width:100%;" placeholder="آموزش, تکنولوژی, ...">
      </div>
      <!-- آپلود تامبنیل -->
      <div style="border-top:1px solid rgba(255,255,255,.07);padding-top:12px;">
        <label style="display:block;font-size:11px;color:#64748b;margin-bottom:8px;">تامبنیل سفارشی</label>
        <div style="display:flex;gap:8px;align-items:center;">
          <img id="editThumbPreview" src="" style="width:80px;height:45px;object-fit:cover;border-radius:6px;background:#0a0a10;display:none;">
          <label style="flex:1;padding:7px 12px;background:rgba(255,255,255,.04);border:1px dashed rgba(255,255,255,.15);border-radius:8px;cursor:pointer;font-size:12px;color:#94a3b8;text-align:center;">
            <i class="fas fa-image text-xs"></i> انتخاب تصویر
            <input type="file" id="thumbFile" accept="image/*" style="display:none;" onchange="previewThumb(this)">
          </label>
        </div>
      </div>
      <div style="display:flex;gap-8px;justify-content:space-between;margin-top:4px;">
        <div style="display:flex;gap:6px;">
          <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#94a3b8;cursor:pointer;">
            <input type="checkbox" id="editFeatured" style="width:14px;height:14px;"> ویژه
          </label>
          <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#94a3b8;cursor:pointer;margin-right:10px;">
            <input type="checkbox" id="editActive" checked style="width:14px;height:14px;"> فعال
          </label>
        </div>
        <div style="display:flex;gap:8px;">
          <button onclick="closeEditModal()" style="padding:8px 16px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#94a3b8;font-size:12px;cursor:pointer;">انصراف</button>
          <button onclick="submitEdit()" style="padding:8px 16px;background:#22c55e;border:none;border-radius:8px;color:#000;font-size:12px;font-weight:700;cursor:pointer;">ذخیره</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Player Modal -->
<div id="playerModal" style="display:none;" class="modal-overlay" onclick="if(event.target===this)closePlayer()">
  <div class="modal-box" style="width:min(900px,95vw);">
    <div class="modal-head">
      <i class="fas fa-play-circle" style="color:#7c3aed;font-size:18px;"></i>
      <span id="playerTitle" style="font-size:15px;font-weight:700;color:#fff;flex:1;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"></span>
      <div style="display:flex;gap:8px;">
        <button id="playerEditBtn" onclick="openEditFromPlayer()" style="padding:5px 12px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);border-radius:8px;color:#86efac;font-size:11px;cursor:pointer;">
          <i class="fas fa-edit text-xs"></i> ویرایش
        </button>
        <button id="playerDeleteBtn" onclick="deleteFromPlayer()" style="padding:5px 12px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:8px;color:#f87171;font-size:11px;cursor:pointer;">
          <i class="fas fa-trash text-xs"></i> حذف
        </button>
        <button onclick="closePlayer()" style="background:none;border:none;color:#64748b;cursor:pointer;font-size:18px;">✕</button>
      </div>
    </div>
    <div class="modal-body">
      <!-- video player -->
      <div style="background:#000;aspect-ratio:16/9;">
        <video id="playerVideo" controls style="width:100%;height:100%;display:block;" playsinline></video>
        <div id="playerYoutubeWrap" style="display:none;width:100%;height:100%;">
          <iframe id="playerYoutubeFrame" style="width:100%;height:100%;border:none;" allowfullscreen></iframe>
        </div>
      </div>
      <!-- info -->
      <div id="playerInfo" style="padding:14px 18px;display:flex;flex-wrap:wrap;gap:10px;border-top:1px solid rgba(255,255,255,.06);">
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════ -->
<!-- JAVASCRIPT                                          -->
<!-- ═══════════════════════════════════════════════════ -->
<script>
// ── State ────────────────────────────────────────────
let currentCat = '', viewMode = 'grid', page = 1, totalPages = 1;
let selectedIds = new Set();
let currentPlayerId = null;
let pendingFiles = [];
let searchTimer;

// ── Init ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => { loadVideos(); });

// ── Load Videos ──────────────────────────────────────
function loadVideos(pg = 1) {
  page = pg;
  const search = document.getElementById('searchInput').value.trim();
  const type   = document.getElementById('typeFilter').value;
  const sort   = document.getElementById('sortSelect').value;

  const params = new URLSearchParams({ page, per_page: 24, sort });
  if (currentCat === 'null') params.set('category_id', 'null');
  else if (currentCat)       params.set('category_id', currentCat);
  if (search) params.set('search', search);
  if (type)   params.set('type', type);

  showLoading(true);
  apiFetch('/api/v1/vod/videos?' + params)
    .then(r => {
      showLoading(false);
      if (!r.success) return;
      totalPages = r.meta?.pages || 1;
      renderVideos(r.data || [], r.meta);
    })
    .catch(() => showLoading(false));
}

function renderVideos(videos, meta) {
  const c = document.getElementById('videoContainer');
  const empty = document.getElementById('emptyState');
  const pag   = document.getElementById('pagination');

  if (!videos.length) {
    c.innerHTML = '';
    empty.style.display = 'block';
    pag.innerHTML = '';
    return;
  }
  empty.style.display = 'none';

  if (viewMode === 'grid') {
    c.className = 'video-grid';
    c.innerHTML = videos.map(v => cardHtml(v)).join('');
  } else {
    c.className = 'video-list';
    c.innerHTML = videos.map(v => rowHtml(v)).join('');
  }

  // pagination
  pag.innerHTML = '';
  if (totalPages > 1) {
    for (let i = 1; i <= totalPages; i++) {
      const btn = document.createElement('button');
      btn.textContent = i;
      btn.style.cssText = `padding:5px 10px;border-radius:7px;font-size:12px;cursor:pointer;border:1px solid rgba(255,255,255,.1);${i===page?'background:#7c3aed;color:#fff;border-color:#7c3aed':'background:rgba(255,255,255,.05);color:#94a3b8'}`;
      btn.onclick = () => loadVideos(i);
      pag.appendChild(btn);
    }
  }
}

function cardHtml(v) {
  const sel     = selectedIds.has(v.id) ? 'selected' : '';
  const thumb   = v.thumbnail ? `<img src="${esc(v.thumbnail)}" loading="lazy">` : `<div class="no-thumb"><i class="fas fa-film" style="font-size:28px;"></i></div>`;
  const dur     = v.duration_fmt ? `<div class="vcard-dur">${esc(v.duration_fmt)}</div>` : '';
  const typeIcon = { upload:'fa-upload', url:'fa-link', youtube:'fa-youtube fab', vimeo:'fa-vimeo-v fab' }[v.type] || 'fa-play';
  const sizeFmt  = formatSize(v.file_size || 0);
  const catBadge = v.category_name ? `<span style="background:${v.category_color||'#7c3aed'}22;color:${v.category_color||'#7c3aed'};padding:1px 6px;border-radius:20px;">${esc(v.category_name)}</span>` : '';
  return `<div class="vcard ${sel}" id="vcard-${v.id}" onclick="handleCardClick(event,${v.id})" data-id="${v.id}">
    <div class="vcard-sel" onclick="event.stopPropagation();toggleSelect(${v.id})">
      ${selectedIds.has(v.id)?'<i class="fas fa-check" style="font-size:9px;color:#fff;"></i>':''}
    </div>
    <div class="vcard-thumb">${thumb}${dur}</div>
    <div class="vcard-body">
      <div class="vcard-title">${esc(v.title)}</div>
      <div class="vcard-meta">
        <i class="fas ${typeIcon}" style="font-size:9px;"></i>
        ${v.type==='upload'&&v.file_size ? sizeFmt : ''}
        ${v.views ? `<span><i class="fas fa-eye" style="font-size:9px;"></i> ${v.views}</span>` : ''}
        ${catBadge}
      </div>
    </div>
  </div>`;
}

function rowHtml(v) {
  const sel   = selectedIds.has(v.id) ? 'selected' : '';
  const thumb = v.thumbnail ? `<img class="vrow-thumb" src="${esc(v.thumbnail)}" loading="lazy">` : `<div class="vrow-thumb" style="display:flex;align-items:center;justify-content:center;"><i class="fas fa-film" style="color:#374151;"></i></div>`;
  const sizeFmt = v.file_size ? ` · ${formatSize(v.file_size)}` : '';
  return `<div class="vrow ${sel}" id="vcard-${v.id}" onclick="handleCardClick(event,${v.id})" data-id="${v.id}">
    <div onclick="event.stopPropagation();toggleSelect(${v.id})" style="width:18px;height:18px;border-radius:5px;border:2px solid rgba(255,255,255,.2);background:${selectedIds.has(v.id)?'#7c3aed':'rgba(0,0,0,.3)'};flex-shrink:0;display:flex;align-items:center;justify-content:center;cursor:pointer;">
      ${selectedIds.has(v.id)?'<i class="fas fa-check" style="font-size:9px;color:#fff;"></i>':''}
    </div>
    ${thumb}
    <div class="vrow-title">${esc(v.title)}</div>
    <div class="vrow-meta" style="font-size:11px;color:#475569;">
      ${v.duration_fmt ? `<span style="font-family:monospace;">${v.duration_fmt}</span>` : ''}
      ${v.category_name ? `<span class="vrow-badge">${esc(v.category_name)}</span>` : ''}
      <span>${formatDate(v.created_at)}${sizeFmt}</span>
    </div>
    <div style="display:flex;gap:6px;" onclick="event.stopPropagation()">
      <button onclick="openEdit(${v.id})" title="ویرایش" style="padding:4px 8px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);border-radius:6px;color:#86efac;font-size:10px;cursor:pointer;"><i class="fas fa-edit"></i></button>
      <button onclick="deleteVideo(${v.id})" title="حذف" style="padding:4px 8px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:6px;color:#f87171;font-size:10px;cursor:pointer;"><i class="fas fa-trash"></i></button>
    </div>
  </div>`;
}

// ── Sidebar filter ────────────────────────────────────
function filterCat(el, cat) {
  document.querySelectorAll('.cat-item').forEach(e => e.classList.remove('active'));
  el.classList.add('active');
  currentCat = cat;
  selectedIds.clear();
  updateBulkBar();
  loadVideos();
}

// ── View toggle ───────────────────────────────────────
function setView(v) {
  viewMode = v;
  document.getElementById('btnGrid').style.background = v==='grid' ? '#7c3aed' : 'transparent';
  document.getElementById('btnList').style.background = v==='list' ? '#7c3aed' : 'transparent';
  document.getElementById('btnGrid').style.color = v==='grid' ? '#fff' : '#64748b';
  document.getElementById('btnList').style.color = v==='list' ? '#fff' : '#64748b';
  loadVideos(page);
}

// ── Selection ─────────────────────────────────────────
function toggleSelect(id) {
  if (selectedIds.has(id)) selectedIds.delete(id);
  else selectedIds.add(id);
  const card = document.getElementById('vcard-' + id);
  if (card) {
    card.classList.toggle('selected', selectedIds.has(id));
    const sel = card.querySelector('.vcard-sel, [onclick*="toggleSelect"]');
    if (sel) sel.innerHTML = selectedIds.has(id) ? '<i class="fas fa-check" style="font-size:9px;color:#fff;"></i>' : '';
  }
  updateBulkBar();
}

function clearSelection() {
  selectedIds.clear();
  document.querySelectorAll('.vcard.selected,.vrow.selected').forEach(e => e.classList.remove('selected'));
  updateBulkBar();
}

function updateBulkBar() {
  const bar = document.getElementById('bulkBar');
  const cnt = document.getElementById('selCount');
  bar.style.display = selectedIds.size ? 'flex' : 'none';
  if (cnt) cnt.textContent = selectedIds.size + ' انتخاب‌شده';
}

function handleCardClick(e, id) {
  if (e.ctrlKey || e.metaKey || e.shiftKey) { toggleSelect(id); return; }
  openPlayer(id);
}

// ── Player ────────────────────────────────────────────
function openPlayer(id) {
  currentPlayerId = id;
  apiFetch('/api/v1/vod/videos/' + id).then(r => {
    if (!r.success) return;
    const v = r.data;
    document.getElementById('playerModal').style.display = 'flex';
    document.getElementById('playerTitle').textContent  = v.title;
    document.getElementById('playerDeleteBtn').dataset.id = id;

    const vid  = document.getElementById('playerVideo');
    const yt   = document.getElementById('playerYoutubeWrap');
    const ytFr = document.getElementById('playerYoutubeFrame');

    if (v.type === 'youtube') {
      vid.style.display = 'none'; yt.style.display = 'block';
      const ytId = extractYoutubeId(v.stream_url);
      ytFr.src = `https://www.youtube.com/embed/${ytId}?autoplay=1`;
    } else {
      vid.style.display = 'block'; yt.style.display = 'none';
      vid.src = v.file_path || v.stream_url || '';
      vid.play().catch(() => {});
    }

    // info chips
    const info = document.getElementById('playerInfo');
    const chips = [
      v.duration_fmt   ? `<span style="background:rgba(124,58,237,.1);color:#a78bfa;padding:3px 10px;border-radius:20px;font-size:11px;"><i class="fas fa-clock" style="font-size:9px;"></i> ${v.duration_fmt}</span>` : '',
      v.category_name  ? `<span style="background:rgba(255,255,255,.06);color:#94a3b8;padding:3px 10px;border-radius:20px;font-size:11px;"><i class="fas fa-folder" style="font-size:9px;"></i> ${esc(v.category_name)}</span>` : '',
      v.file_size      ? `<span style="background:rgba(255,255,255,.06);color:#94a3b8;padding:3px 10px;border-radius:20px;font-size:11px;"><i class="fas fa-hdd" style="font-size:9px;"></i> ${formatSize(v.file_size)}</span>` : '',
      v.width&&v.height ? `<span style="background:rgba(255,255,255,.06);color:#94a3b8;padding:3px 10px;border-radius:20px;font-size:11px;">${v.width}×${v.height}</span>` : '',
      `<span style="background:rgba(255,255,255,.06);color:#94a3b8;padding:3px 10px;border-radius:20px;font-size:11px;"><i class="fas fa-eye" style="font-size:9px;"></i> ${v.views} بازدید</span>`,
    ].filter(Boolean);
    info.innerHTML = chips.join('');
  });
}

function closePlayer() {
  document.getElementById('playerModal').style.display = 'none';
  const vid = document.getElementById('playerVideo');
  vid.pause(); vid.src = '';
  document.getElementById('playerYoutubeFrame').src = '';
  currentPlayerId = null;
}

function openEditFromPlayer() { closePlayer(); openEdit(currentPlayerId || document.getElementById('playerDeleteBtn').dataset.id); }
function deleteFromPlayer()   { const id = document.getElementById('playerDeleteBtn').dataset.id; closePlayer(); deleteVideo(id); }

// ── Upload ────────────────────────────────────────────
function openUploadModal()  { document.getElementById('uploadModal').style.display = 'flex'; }
function closeUploadModal() { document.getElementById('uploadModal').style.display = 'none'; pendingFiles = []; document.getElementById('uploadQueue').innerHTML = ''; document.getElementById('startUploadBtn').disabled = true; }

function handleDrop(e) {
  e.preventDefault();
  document.getElementById('dropZone').classList.remove('drag-over');
  handleFileSelect(e.dataTransfer.files);
}

function handleFileSelect(files) {
  const videoExts = /\.(mp4|webm|mkv|avi|mov|flv|wmv|mpeg|mpg|3gp|ts)$/i;
  Array.from(files).forEach(f => {
    if (!f.type.startsWith('video/') && !videoExts.test(f.name)) return;
    if (pendingFiles.find(p => p.name === f.name)) return;
    pendingFiles.push(f);
    renderQueueItem(f);
  });
  document.getElementById('startUploadBtn').disabled = pendingFiles.length === 0;
}

function renderQueueItem(file) {
  const q   = document.getElementById('uploadQueue');
  const id  = 'q_' + Math.random().toString(36).slice(2);
  file._qid = id;
  const div = document.createElement('div');
  div.className = 'upload-item'; div.id = id;
  div.innerHTML = `
    <div style="display:flex;align-items:center;gap:8px;">
      <i class="fas fa-film" style="color:#7c3aed;font-size:12px;flex-shrink:0;"></i>
      <span style="flex:1;color:#e2e8f0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">${esc(file.name)}</span>
      <span style="color:#64748b;font-size:10px;flex-shrink:0;">${formatSize(file.size)}</span>
      <span class="q-status" style="font-size:10px;color:#475569;">در صف</span>
    </div>
    <div class="prog-bar"><div class="prog-fill" id="${id}_prog" style="width:0%"></div></div>`;
  q.appendChild(div);
}

async function startUpload() {
  if (!pendingFiles.length) return;
  const btn   = document.getElementById('startUploadBtn');
  const catId = document.getElementById('uploadCatId').value;
  btn.disabled = true; btn.textContent = 'در حال آپلود ...';

  for (const file of pendingFiles) {
    await uploadSingleFile(file, catId);
  }

  btn.textContent = 'آپلود';
  pendingFiles = [];
  setTimeout(() => { closeUploadModal(); loadVideos(); }, 1000);
}

async function uploadSingleFile(file, catId) {
  const id     = file._qid;
  const status = document.querySelector(`#${id} .q-status`);
  const prog   = document.getElementById(id + '_prog');
  if (status) status.textContent = 'در حال آپلود ...';

  const fd = new FormData();
  fd.append('file', file);
  if (catId) fd.append('category_id', catId);

  return new Promise(resolve => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/api/v1/vod/upload');
    xhr.setRequestHeader('X-CSRF-Token', document.querySelector('meta[name=csrf-token]')?.content || '');

    xhr.upload.onprogress = e => {
      if (e.lengthComputable && prog) {
        prog.style.width = Math.round(e.loaded / e.total * 100) + '%';
      }
    };
    xhr.onload = () => {
      const ok = xhr.status < 300;
      if (status) status.style.color = ok ? '#22c55e' : '#ef4444';
      if (status) status.textContent  = ok ? '✓ آپلود شد' : '✗ خطا';
      if (prog)   prog.style.background = ok ? '#22c55e' : '#ef4444';
      resolve();
    };
    xhr.onerror = () => { if (status) status.textContent = '✗ خطای شبکه'; resolve(); };
    xhr.send(fd);
  });
}

// ── URL ───────────────────────────────────────────────
function openUrlModal()  { document.getElementById('urlModal').style.display = 'flex'; }
function closeUrlModal() { document.getElementById('urlModal').style.display = 'none'; }

function submitUrl() {
  const url   = document.getElementById('urlInput').value.trim();
  const title = document.getElementById('urlTitle').value.trim();
  const catId = document.getElementById('urlCatId').value;
  if (!url) { alert('آدرس URL الزامی است'); return; }

  const fd = new FormData();
  fd.append('stream_url', url);
  if (title) fd.append('title', title);
  if (catId) fd.append('category_id', catId);

  apiFetch('/api/v1/vod/videos', 'POST', fd).then(r => {
    if (r.success) { closeUrlModal(); loadVideos(); }
    else alert(r.message || 'خطا');
  });
}

// ── Category ──────────────────────────────────────────
function openAddCatModal()  { document.getElementById('addCatModal').style.display = 'flex'; }
function closeAddCatModal() { document.getElementById('addCatModal').style.display = 'none'; }

function submitCategory() {
  const name = document.getElementById('catName').value.trim();
  if (!name) { alert('نام الزامی است'); return; }
  const fd = new FormData();
  fd.append('name', name);
  fd.append('description', document.getElementById('catDesc').value);
  fd.append('color', document.getElementById('catColor').value);
  apiFetch('/api/v1/vod/categories', 'POST', fd).then(r => {
    if (r.success) {
      closeAddCatModal();
      // اضافه کردن به sidebar
      const cat = r.data;
      const div = document.createElement('div');
      div.className = 'cat-item'; div.dataset.cat = cat.id;
      div.onclick   = () => filterCat(div, String(cat.id));
      div.innerHTML = `<span class="cat-dot" style="background:${cat.color}"></span>${esc(cat.name)}<span class="cat-count">0</span>`;
      document.getElementById('sidebarCats').appendChild(div);
    } else alert(r.message || 'خطا');
  });
}

// ── Edit ──────────────────────────────────────────────
function openEdit(id) {
  apiFetch('/api/v1/vod/videos/' + id).then(r => {
    if (!r.success) return;
    const v = r.data;
    document.getElementById('editModal').style.display = 'flex';
    document.getElementById('editId').value    = v.id;
    document.getElementById('editTitle').value = v.title || '';
    document.getElementById('editDesc').value  = v.description || '';
    document.getElementById('editCatId').value = v.category_id || '';
    document.getElementById('editYear').value  = v.year || '';
    document.getElementById('editTags').value  = Array.isArray(v.tags) ? v.tags.join(', ') : (v.tags || '');
    document.getElementById('editFeatured').checked = !!v.is_featured;
    document.getElementById('editActive').checked   = !!v.is_active;
    const tp = document.getElementById('editThumbPreview');
    if (v.thumbnail) { tp.src = v.thumbnail; tp.style.display = 'block'; }
    else tp.style.display = 'none';
    document.getElementById('thumbFile').value = '';
  });
}

function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }

function previewThumb(input) {
  const f = input.files[0];
  if (!f) return;
  const tp = document.getElementById('editThumbPreview');
  tp.src = URL.createObjectURL(f); tp.style.display = 'block';
}

async function submitEdit() {
  const id  = document.getElementById('editId').value;
  const thumbFile = document.getElementById('thumbFile').files[0];

  // آپلود تامبنیل اگر انتخاب شده
  if (thumbFile) {
    const fd = new FormData(); fd.append('thumbnail', thumbFile);
    await apiFetch('/api/v1/vod/videos/' + id + '/thumbnail', 'POST', fd);
  }

  const fd = new FormData();
  fd.append('title',       document.getElementById('editTitle').value);
  fd.append('description', document.getElementById('editDesc').value);
  fd.append('category_id', document.getElementById('editCatId').value);
  fd.append('year',        document.getElementById('editYear').value);
  fd.append('tags',        document.getElementById('editTags').value);
  fd.append('is_featured', document.getElementById('editFeatured').checked ? '1' : '0');
  fd.append('is_active',   document.getElementById('editActive').checked   ? '1' : '0');

  apiFetch('/api/v1/vod/videos/' + id, 'PUT', fd).then(r => {
    if (r.success) { closeEditModal(); loadVideos(page); }
    else alert(r.message || 'خطا');
  });
}

// ── Delete ────────────────────────────────────────────
function deleteVideo(id) {
  if (!confirm('این ویدیو و فایل آن حذف می‌شود. ادامه می‌دهید؟')) return;
  apiFetch('/api/v1/vod/videos/' + id, 'DELETE').then(r => {
    if (r.success) loadVideos(page);
    else alert(r.message || 'خطا در حذف');
  });
}

function bulkDelete() {
  if (!selectedIds.size) return;
  if (!confirm(selectedIds.size + ' ویدیو حذف می‌شوند. ادامه می‌دهید؟')) return;
  const fd = new FormData();
  selectedIds.forEach(id => fd.append('ids[]', id));
  apiFetch('/api/v1/vod/videos/bulk-delete', 'POST', fd).then(r => {
    if (r.success) { selectedIds.clear(); updateBulkBar(); loadVideos(page); }
    else alert(r.message || 'خطا');
  });
}

// ── Search debounce ───────────────────────────────────
function debounceSearch(v) {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadVideos(), 400);
}

// ── Helpers ───────────────────────────────────────────
function showLoading(show) {
  document.getElementById('loadingState').style.display = show ? 'block' : 'none';
  document.getElementById('videoContainer').style.display = show ? 'none' : '';
}

function apiFetch(url, method='GET', body=null) {
  const opts = { method, headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]')?.content || '' } };
  if (body) opts.body = body;
  return fetch(url, opts).then(r => r.json()).catch(() => ({ success:false }));
}

function formatSize(b) {
  if (!b) return '';
  if (b >= 1073741824) return (b/1073741824).toFixed(1) + ' GB';
  if (b >= 1048576)    return (b/1048576).toFixed(1) + ' MB';
  if (b >= 1024)       return Math.round(b/1024) + ' KB';
  return b + ' B';
}

function formatDate(d) {
  if (!d) return '';
  const dt = new Date(d.replace(' ','T'));
  return dt.toLocaleDateString('fa-IR', { year:'numeric', month:'short', day:'numeric' });
}

function esc(s) { const d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

function extractYoutubeId(url) {
  const m = url.match(/(?:v=|youtu\.be\/)([^&\s]+)/);
  return m ? m[1] : '';
}
</script>
