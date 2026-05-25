<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<?php
// ── تعریف نوع‌ها و آیکون‌ها ──────────────────────────────────────
$ITEM_TYPES = [
  'live'      => ['پخش زنده',     'fas fa-satellite-dish', '#ef4444'],
  'vod'       => ['فیلم‌ها',      'fas fa-film',           '#ec4899'],
  'news'      => ['اخبار',        'fas fa-newspaper',      '#3b82f6'],
  'info'      => ['اطلاع‌رسانی',  'fas fa-circle-info',    '#8b5cf6'],
  'weather'   => ['آب و هوا',     'fas fa-cloud-sun',      '#06b6d4'],
  'fids'      => ['پروازها',      'fas fa-plane',          '#0ea5e9'],
  'hotel'     => ['خدمات هتل',    'fas fa-hotel',          '#f59e0b'],
  'corporate' => ['سازمانی',      'fas fa-building-columns','#6366f1'],
  'retail'    => ['فروشگاه',      'fas fa-store',          '#10b981'],
  'url'       => ['لینک سفارشی',  'fas fa-link',           '#64748b'],
  'custom'    => ['سفارشی',       'fas fa-grip-dots',      '#f97316'],
];

// گروه‌های IPTV و منوهاشون رو از PHP variable دریافت می‌کنیم
$iptvGroups = $iptvGroups ?? [];
$allMenus   = $allMenus   ?? [];
?>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <div style="display:flex;align-items:center;gap:12px;">
    <a href="/admin/iptv" class="btn-ghost text-sm px-3"><i class="fas fa-arrow-right text-xs"></i></a>
    <h1 style="font-size:20px;font-weight:800;color:#fff;">
      <i class="fas fa-bars" style="color:#ef4444;margin-left:10px;"></i>منوهای IPTV
    </h1>
  </div>
  <button onclick="openCreateMenu()" class="btn-primary text-sm flex items-center gap-1.5">
    <i class="fas fa-plus text-xs"></i> منوی جدید
  </button>
</div>

<!-- راهنمای سریع -->
<div style="background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.15);border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:flex-start;gap:14px;">
  <i class="fas fa-circle-info" style="color:#f87171;font-size:18px;margin-top:2px;"></i>
  <div style="font-size:12px;color:#94a3b8;line-height:1.8;">
    <strong style="color:#fff;">منوی IPTV</strong> — وقتی یه صفحه نمایش روی حالت IPTV تنظیمه، به جای پلی‌لیست یه <em>منو</em> بهش نسبت داده می‌شه.<br>
    منو شامل آیتم‌هایی مثل <span style="color:#ef4444;">📡 پخش زنده</span>، <span style="color:#ec4899;">🎬 فیلم‌ها</span>، <span style="color:#3b82f6;">📰 اخبار</span> و ... هست که کاربر در TV انتخاب می‌کنه.
  </div>
</div>

<!-- بدنه اصلی: دو ستون -->
<div style="display:grid;grid-template-columns:300px 1fr;gap:16px;align-items:start;">

  <!-- ─── ستون چپ: گروه‌ها + منوها ────────────────────────────── -->
  <div>
    <div class="card" style="padding:0;overflow:hidden;">
      <div style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:13px;font-weight:700;color:#fff;">گروه‌ها و منوها</span>
        <span style="font-size:10px;color:#475569;"><?= count($allMenus) ?> منو</span>
      </div>

      <div id="menu-tree" style="padding:8px;">

        <?php if (empty($iptvGroups) && empty($allMenus)): ?>
        <div style="text-align:center;padding:30px 16px;color:#475569;font-size:12px;">
          <i class="fas fa-satellite-dish" style="font-size:32px;margin-bottom:12px;display:block;opacity:.3;"></i>
          هنوز گروه یا منویی نیست<br>
          <button onclick="openCreateMenu()" style="margin-top:12px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:6px 14px;border-radius:8px;cursor:pointer;font-size:11px;font-family:inherit;">
            + منوی جدید
          </button>
        </div>
        <?php else: ?>

        <?php
        // گروه‌بندی منوها بر اساس group_id
        $grouped = [];
        foreach ($allMenus as $m) {
            $gid = $m['group_id'] ?? 0;
            $grouped[$gid][] = $m;
        }
        ?>

        <!-- منوهای بدون گروه -->
        <?php if (!empty($grouped[0])): ?>
        <div style="margin-bottom:12px;">
          <div style="font-size:10px;font-weight:700;color:#475569;padding:4px 8px;letter-spacing:.6px;text-transform:uppercase;">بدون گروه</div>
          <?php foreach ($grouped[0] as $m): ?>
          <div class="menu-row" data-id="<?= $m['id'] ?>"
               onclick="selectMenu(<?= $m['id'] ?>, <?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)"
               style="display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:9px;cursor:pointer;transition:background .15s;margin-bottom:2px;">
            <i class="fas fa-bars" style="color:#64748b;font-size:12px;width:14px;text-align:center;"></i>
            <span style="flex:1;font-size:12px;color:#94a3b8;"><?= e($m['name']) ?></span>
            <span style="font-size:10px;color:#475569;background:rgba(255,255,255,.05);padding:1px 6px;border-radius:10px;"><?= $m['item_count'] ?? 0 ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- منوهای با گروه -->
        <?php foreach ($iptvGroups as $g): ?>
        <?php $gMenus = $grouped[$g['id']] ?? []; ?>
        <div style="margin-bottom:12px;">
          <div style="display:flex;align-items:center;gap:6px;padding:6px 8px;">
            <span style="width:8px;height:8px;border-radius:50%;background:<?= e($g['color'] ?? '#f97316') ?>;flex-shrink:0;"></span>
            <span style="font-size:11px;font-weight:700;color:#cbd5e1;"><?= e($g['name']) ?></span>
            <button onclick="openCreateMenu(<?= $g['id'] ?>, '<?= e(addslashes($g['name'])) ?>')"
              style="margin-right:auto;background:none;border:none;color:#475569;cursor:pointer;font-size:11px;padding:2px 5px;border-radius:6px;font-family:inherit;"
              title="منوی جدید برای این گروه">
              <i class="fas fa-plus"></i>
            </button>
          </div>
          <?php if (empty($gMenus)): ?>
          <div style="padding:6px 16px;font-size:11px;color:#334155;font-style:italic;">هنوز منویی ندارد</div>
          <?php else: ?>
          <?php foreach ($gMenus as $m): ?>
          <div class="menu-row" data-id="<?= $m['id'] ?>"
               onclick="selectMenu(<?= $m['id'] ?>, <?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)"
               style="display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:9px;cursor:pointer;transition:background .15s;margin-bottom:2px;">
            <i class="fas fa-bars" style="color:#64748b;font-size:12px;width:14px;text-align:center;"></i>
            <span style="flex:1;font-size:12px;color:#94a3b8;"><?= e($m['name']) ?></span>
            <span style="font-size:10px;color:#475569;background:rgba(255,255,255,.05);padding:1px 6px;border-radius:10px;"><?= $m['item_count'] ?? 0 ?></span>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>
      </div>
    </div>

    <!-- ساخت گروه جدید (لینک به صفحات صفحه‌نمایش) -->
    <div style="text-align:center;margin-top:8px;">
      <a href="/admin/screens?tab=iptv" style="font-size:11px;color:#475569;text-decoration:none;">
        <i class="fas fa-folder text-xs ml-1"></i> مدیریت گروه‌های IPTV ←
      </a>
    </div>
  </div>

  <!-- ─── ستون راست: جزئیات منو + آیتم‌ها ─────────────────────── -->
  <div id="menu-detail">

    <!-- حالت خالی -->
    <div id="empty-state" class="card" style="text-align:center;padding:60px 20px;color:#334155;">
      <i class="fas fa-bars" style="font-size:48px;margin-bottom:16px;display:block;opacity:.2;"></i>
      <div style="font-size:14px;color:#475569;">یک منو از لیست سمت راست انتخاب کنید</div>
      <div style="font-size:12px;color:#334155;margin-top:6px;">یا یک منوی جدید بسازید</div>
    </div>

    <!-- پنل جزئیات (وقتی منویی انتخاب شده) -->
    <div id="detail-panel" style="display:none;">

      <!-- هدر منو -->
      <div class="card" style="margin-bottom:12px;">
        <div style="display:flex;align-items:center;gap:12px;">
          <div>
            <h2 id="detail-name" style="font-size:16px;font-weight:800;color:#fff;"></h2>
            <div id="detail-meta" style="font-size:11px;color:#475569;margin-top:3px;"></div>
          </div>
          <div style="margin-right:auto;display:flex;gap:8px;">
            <button onclick="openEditMenu()" class="btn-ghost text-xs px-3">
              <i class="fas fa-pen text-xs ml-1"></i>ویرایش
            </button>
            <button onclick="deleteMenu()" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:6px 12px;border-radius:9px;font-size:12px;cursor:pointer;font-family:inherit;">
              <i class="fas fa-trash text-xs ml-1"></i>حذف
            </button>
          </div>
        </div>
      </div>

      <!-- تب‌ها -->
      <div style="display:flex;gap:4px;margin-bottom:12px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:4px;">
        <button id="tab-items-btn" onclick="switchTab('items')"
          style="flex:1;padding:8px 0;border-radius:8px;border:none;font-size:12px;font-weight:700;cursor:pointer;font-family:'Vazirmatn',sans-serif;transition:all .15s;background:rgba(239,68,68,.15);color:#f87171;">
          <i class="fas fa-list text-xs ml-1"></i>آیتم‌ها
        </button>
        <button id="tab-appear-btn" onclick="switchTab('appear')"
          style="flex:1;padding:8px 0;border-radius:8px;border:none;font-size:12px;font-weight:700;cursor:pointer;font-family:'Vazirmatn',sans-serif;transition:all .15s;background:transparent;color:#475569;">
          <i class="fas fa-palette text-xs ml-1"></i>ظاهر
        </button>
      </div>

      <!-- ════ تب آیتم‌ها ════ -->
      <div id="tab-items">

        <!-- پیش‌نمایش منو روی TV -->
        <div class="card" style="margin-bottom:12px;background:linear-gradient(135deg,#0a0a14,#111118);">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <span style="font-size:12px;font-weight:700;color:#fff;"><i class="fas fa-tv text-xs ml-1.5 text-red-400"></i>پیش‌نمایش (TV)</span>
            <span style="font-size:10px;color:#475569;">ظاهر منو روی صفحه TV</span>
          </div>
          <div id="tv-preview" style="display:flex;flex-wrap:wrap;gap:8px;min-height:80px;"></div>
        </div>

        <!-- لیست آیتم‌ها -->
        <div class="card" style="padding:0;overflow:hidden;">
          <div style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;gap:8px;">
            <span style="font-size:13px;font-weight:700;color:#fff;">آیتم‌های منو</span>
            <span id="items-count" style="font-size:10px;color:#475569;background:rgba(255,255,255,.05);padding:1px 8px;border-radius:10px;"></span>
            <button onclick="openAddItem()" class="btn-primary text-xs px-3" style="margin-right:auto;padding:6px 14px;">
              <i class="fas fa-plus text-xs ml-1"></i>افزودن آیتم
            </button>
          </div>

          <div id="items-list" style="padding:8px;">
            <!-- آیتم‌ها اینجا render می‌شن -->
          </div>

          <!-- قالب‌های سریع -->
          <div style="padding:10px 14px;border-top:1px solid rgba(255,255,255,.06);">
            <div style="font-size:10px;color:#475569;margin-bottom:8px;">
              <i class="fas fa-bolt text-yellow-400 text-xs ml-1"></i>افزودن سریع:
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
              <?php foreach ($ITEM_TYPES as $type => [$label, $icon, $color]): ?>
              <button onclick="quickAdd('<?= $type ?>')"
                style="padding:4px 10px;border-radius:20px;border:1px solid <?= $color ?>33;
                       background:<?= $color ?>11;color:<?= $color ?>;font-size:11px;cursor:pointer;
                       font-family:'Vazirmatn',sans-serif;display:flex;align-items:center;gap:5px;">
                <i class="<?= $icon ?> text-xs"></i><?= $label ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

      </div><!-- /tab-items -->

      <!-- ════ تب ظاهر ════ -->
      <div id="tab-appear" style="display:none;">

        <!-- تصویر پس‌زمینه -->
        <div class="card" style="margin-bottom:12px;">
          <div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:14px;">
            <i class="fas fa-image text-red-400 text-xs ml-1.5"></i>تصویر پس‌زمینه
          </div>

          <!-- پیش‌نمایش -->
          <div id="bg-preview-wrap" style="display:none;margin-bottom:12px;position:relative;border-radius:10px;overflow:hidden;aspect-ratio:16/5;">
            <img id="bg-preview" src="" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
            <button onclick="removeImg('bg')"
              style="position:absolute;top:6px;left:6px;background:rgba(0,0,0,.7);border:1px solid rgba(255,255,255,.15);color:#fff;width:28px;height:28px;border-radius:7px;cursor:pointer;font-size:11px;">
              <i class="fas fa-trash"></i>
            </button>
          </div>

          <label id="bg-upload-btn"
            style="display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;
                   border:2px dashed rgba(239,68,68,.3);border-radius:10px;cursor:pointer;
                   color:#94a3b8;font-size:12px;transition:all .15s;"
            onmouseenter="this.style.borderColor='rgba(239,68,68,.6)';this.style.color='#f87171'"
            onmouseleave="this.style.borderColor='rgba(239,68,68,.3)';this.style.color='#94a3b8'">
            <i class="fas fa-cloud-upload-alt" style="font-size:16px;"></i>
            <span id="bg-upload-txt">انتخاب تصویر پس‌زمینه (JPG/PNG/WebP، حداکثر ۵MB)</span>
            <input type="file" id="bg-file" accept="image/*" style="display:none;" onchange="uploadImg('bg', this)">
          </label>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px;">
            <div>
              <label class="form-label">تیرگی پس‌زمینه (<?= '0' ?>–<?= '1' ?>)</label>
              <input type="range" id="ap-bg-dim" min="0" max="1" step="0.05" value="0.55"
                style="width:100%;" oninput="document.getElementById('ap-bg-dim-val').textContent=this.value;debounceSave()">
              <div style="font-size:10px;color:#475569;margin-top:3px;">
                مقدار: <span id="ap-bg-dim-val">0.55</span>
              </div>
            </div>
            <div>
              <label class="form-label">بلور پس‌زمینه (0–20px)</label>
              <input type="range" id="ap-bg-blur" min="0" max="20" step="1" value="0"
                style="width:100%;" oninput="document.getElementById('ap-bg-blur-val').textContent=this.value+'px';debounceSave()">
              <div style="font-size:10px;color:#475569;margin-top:3px;">
                مقدار: <span id="ap-bg-blur-val">0px</span>
              </div>
            </div>
          </div>
        </div>

        <!-- لوگو -->
        <div class="card" style="margin-bottom:12px;">
          <div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:14px;">
            <i class="fas fa-trademark text-yellow-400 text-xs ml-1.5"></i>لوگو
          </div>

          <!-- پیش‌نمایش -->
          <div id="logo-preview-wrap" style="display:none;margin-bottom:12px;display:none;align-items:center;gap:12px;padding:12px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:10px;">
            <img id="logo-preview" src="" alt="" style="height:48px;object-fit:contain;max-width:160px;">
            <button onclick="removeImg('logo')"
              style="margin-right:auto;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:6px 10px;border-radius:7px;cursor:pointer;font-size:11px;font-family:inherit;">
              <i class="fas fa-trash text-xs ml-1"></i>حذف
            </button>
          </div>

          <label id="logo-upload-btn"
            style="display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;
                   border:2px dashed rgba(245,158,11,.3);border-radius:10px;cursor:pointer;
                   color:#94a3b8;font-size:12px;transition:all .15s;"
            onmouseenter="this.style.borderColor='rgba(245,158,11,.6)';this.style.color='#fbbf24'"
            onmouseleave="this.style.borderColor='rgba(245,158,11,.3)';this.style.color='#94a3b8'">
            <i class="fas fa-image" style="font-size:16px;"></i>
            <span id="logo-upload-txt">انتخاب لوگو (PNG/WebP با پس‌زمینه شفاف بهتره)</span>
            <input type="file" id="logo-file" accept="image/*" style="display:none;" onchange="uploadImg('logo', this)">
          </label>
        </div>

        <!-- خوش‌آمدگویی + رنگ accent -->
        <div class="card" style="margin-bottom:12px;">
          <div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:14px;">
            <i class="fas fa-text text-purple-400 text-xs ml-1.5"></i>متن و رنگ
          </div>
          <div style="display:grid;gap:12px;">
            <div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end;">
              <div>
                <label class="form-label">عنوان خوش‌آمدگویی</label>
                <input type="text" id="ap-welcome-title" class="form-input" placeholder="مثلاً: خوش آمدید"
                  oninput="debounceSave()">
              </div>
              <div>
                <label class="form-label">رنگ اصلی (Accent)</label>
                <input type="color" id="ap-accent" class="form-input" value="#ef4444"
                  style="height:42px;padding:3px;width:60px;" oninput="debounceSave()">
              </div>
            </div>
            <div>
              <label class="form-label">زیرعنوان</label>
              <input type="text" id="ap-welcome-sub" class="form-input" placeholder="مثلاً: لطفاً یک بخش را انتخاب کنید"
                oninput="debounceSave()">
            </div>
          </div>
        </div>

        <!-- تیکر -->
        <div class="card" style="margin-bottom:12px;">
          <div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:14px;">
            <i class="fas fa-ticket text-blue-400 text-xs ml-1.5"></i>تیکر (نوار پایین)
          </div>
          <div style="display:grid;gap:12px;">
            <div>
              <label class="form-label">متن تیکر <span style="color:#475569;font-weight:400;">(خالی = بدون تیکر)</span></label>
              <textarea id="ap-ticker-text" class="form-input" rows="2" placeholder="متن پیام‌رسانی که در نوار پایین حرکت می‌کند..."
                style="resize:vertical;" oninput="debounceSave()"></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
              <div>
                <label class="form-label">رنگ متن</label>
                <input type="color" id="ap-ticker-color" value="#ffffff" class="form-input"
                  style="height:42px;padding:3px;" oninput="debounceSave()">
              </div>
              <div>
                <label class="form-label">رنگ پس‌زمینه</label>
                <input type="color" id="ap-ticker-bg" value="#000000" class="form-input"
                  style="height:42px;padding:3px;" oninput="debounceSave()">
              </div>
              <div>
                <label class="form-label">سرعت (<?= '5' ?>–100)</label>
                <input type="range" id="ap-ticker-speed" min="5" max="100" step="5" value="40"
                  style="width:100%;margin-top:12px;" oninput="document.getElementById('ap-ticker-speed-val').textContent=this.value;debounceSave()">
                <div style="font-size:10px;color:#475569;">
                  <span id="ap-ticker-speed-val">40</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- دکمه ذخیره دستی -->
        <div style="display:flex;align-items:center;gap:10px;">
          <button onclick="saveAppearance()" class="btn-primary" style="padding:9px 20px;">
            <i class="fas fa-save text-xs ml-1"></i>ذخیره ظاهر
          </button>
          <span id="ap-save-status" style="font-size:11px;color:#475569;"></span>
        </div>

      </div><!-- /tab-appear -->

    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!--  MODAL: ایجاد/ویرایش منو                                     -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="menuModal" class="modal-overlay hidden">
  <div class="modal" style="max-width:440px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <h3 id="menuModal-title" style="font-size:15px;font-weight:800;color:#fff;">منوی جدید</h3>
      <button onclick="closeModal('menuModal')" style="background:none;border:none;color:#475569;cursor:pointer;font-size:16px;">✕</button>
    </div>

    <div style="display:flex;flex-direction:column;gap:14px;">
      <div>
        <label class="form-label">نام منو *</label>
        <input type="text" id="m-name" class="form-input" placeholder="مثلاً: منوی اصلی هتل">
      </div>
      <div>
        <label class="form-label">توضیحات</label>
        <input type="text" id="m-desc" class="form-input" placeholder="اختیاری">
      </div>
      <div>
        <label class="form-label">گروه IPTV</label>
        <select id="m-group" class="form-input">
          <option value="">— بدون گروه —</option>
          <?php foreach ($iptvGroups as $g): ?>
          <option value="<?= $g['id'] ?>"><?= e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- قالب‌های پیش‌فرض فقط در ایجاد -->
      <div id="templates-row">
        <label class="form-label">قالب آماده (اختیاری)</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <?php
          $templates = [
            'hotel'     => ['هتل',        ['live','vod','weather','hotel','info']],
            'corporate' => ['سازمانی',    ['live','news','corporate','info','url']],
            'retail'    => ['فروشگاه',    ['live','retail','vod','weather','info']],
            'airport'   => ['فرودگاه',    ['fids','live','weather','info','news']],
            'empty'     => ['خالی (دستی)', []],
          ];
          foreach ($templates as $key => [$tname, $types]):
          ?>
          <button type="button" onclick="selectTemplate('<?= $key ?>')"
            id="tpl-<?= $key ?>"
            style="padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.08);
                   background:rgba(255,255,255,.03);color:#94a3b8;font-size:12px;cursor:pointer;
                   text-align:right;font-family:'Vazirmatn',sans-serif;transition:all .15s;">
            <?= $tname ?>
            <div style="font-size:9px;color:#475569;margin-top:3px;"><?= implode(' · ', array_map(fn($t) => $ITEM_TYPES[$t][0], $types)) ?: 'بدون آیتم' ?></div>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:20px;">
      <button onclick="saveMenu()" class="btn-primary" style="flex:1;padding:10px;">
        <i class="fas fa-save text-xs ml-1"></i>ذخیره
      </button>
      <button onclick="closeModal('menuModal')" class="btn-ghost" style="padding:10px 20px;">لغو</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!--  MODAL: افزودن/ویرایش آیتم                                   -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div id="itemModal" class="modal-overlay hidden">
  <div class="modal" style="max-width:480px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <h3 id="itemModal-title" style="font-size:15px;font-weight:800;color:#fff;">افزودن آیتم</h3>
      <button onclick="closeModal('itemModal')" style="background:none;border:none;color:#475569;cursor:pointer;font-size:16px;">✕</button>
    </div>

    <input type="hidden" id="item-id" value="">

    <div style="display:flex;flex-direction:column;gap:14px;">
      <!-- نوع آیتم -->
      <div>
        <label class="form-label">نوع آیتم *</label>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;" id="type-picker">
          <?php foreach ($ITEM_TYPES as $type => [$label, $icon, $color]): ?>
          <button type="button" onclick="pickType('<?= $type ?>')" id="tp-<?= $type ?>"
            style="padding:10px 6px;border-radius:10px;text-align:center;cursor:pointer;
                   border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03);
                   font-family:'Vazirmatn',sans-serif;transition:all .15s;">
            <i class="<?= $icon ?>" style="color:<?= $color ?>;font-size:16px;display:block;margin-bottom:4px;"></i>
            <div style="font-size:9px;color:#64748b;"><?= $label ?></div>
          </button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" id="item-type" value="live">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label class="form-label">عنوان نمایشی *</label>
          <input type="text" id="item-label" class="form-input" placeholder="مثلاً: پخش زنده">
        </div>
        <div>
          <label class="form-label">رنگ</label>
          <input type="color" id="item-color" class="form-input" style="height:42px;padding:3px;" value="#ef4444">
        </div>
      </div>

      <div>
        <label class="form-label">آیکون (Font Awesome)</label>
        <input type="text" id="item-icon" class="form-input" placeholder="fas fa-satellite-dish">
        <div style="font-size:10px;color:#475569;margin-top:4px;">
          <a href="https://fontawesome.com/icons" target="_blank" style="color:#60a5fa;">لیست آیکون‌ها →</a>
        </div>
      </div>

      <!-- آدرس URL (فقط برای نوع url) -->
      <div id="url-row" style="display:none;">
        <label class="form-label">آدرس URL</label>
        <input type="url" id="item-url" class="form-input" placeholder="https://...">
      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:20px;">
      <button onclick="saveItem()" class="btn-primary" style="flex:1;padding:10px;">
        <i class="fas fa-save text-xs ml-1"></i>ذخیره
      </button>
      <button onclick="closeModal('itemModal')" class="btn-ghost" style="padding:10px 20px;">لغو</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div id="toast" class="toast hidden"></div>

<style>
.menu-row:hover { background:rgba(239,68,68,.08) !important; }
.menu-row.selected { background:rgba(239,68,68,.12) !important; }
.menu-row.selected span { color:#f87171 !important; }
.item-card { display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;
             background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);
             margin-bottom:6px;transition:background .15s; }
.item-card:hover { background:rgba(255,255,255,.05); }
.item-card .drag-handle { cursor:grab;color:#334155;font-size:12px; }
.tp-active { background:rgba(239,68,68,.12) !important;border-color:var(--tpc) !important; }
.tpl-active { background:rgba(249,115,22,.1) !important;border-color:#f97316 !important;color:#f97316 !important; }
</style>

<script>
const ITEM_TYPES = <?= json_encode(array_map(fn($v) => ['label'=>$v[0],'icon'=>$v[1],'color'=>$v[2]], $ITEM_TYPES), JSON_UNESCAPED_UNICODE) ?>;
const TEMPLATES  = <?= json_encode([
  'hotel'     => ['live','vod','weather','hotel','info'],
  'corporate' => ['live','news','corporate','info','url'],
  'retail'    => ['live','retail','vod','weather','info'],
  'airport'   => ['fids','live','weather','info','news'],
  'empty'     => [],
], JSON_UNESCAPED_UNICODE) ?>;

let currentMenu = null;
let currentItems = [];
let menuModalMode = 'create'; // 'create' | 'edit'
let selectedTemplate = 'empty';

// ── انتخاب منو ──────────────────────────────────────────────────
function selectMenu(id, menuData) {
  // highlight
  document.querySelectorAll('.menu-row').forEach(r => r.classList.remove('selected'));
  const row = document.querySelector(`.menu-row[data-id="${id}"]`);
  if (row) row.classList.add('selected');

  currentMenu = menuData;
  document.getElementById('empty-state').style.display = 'none';
  document.getElementById('detail-panel').style.display = 'block';

  // هدر
  document.getElementById('detail-name').textContent = menuData.name;
  const grpName = row?.closest('[data-group]')?.dataset.group || menuData.group_name || '';
  document.getElementById('detail-meta').textContent =
    (grpName ? 'گروه: ' + grpName + ' · ' : '') +
    (menuData.description || '');

  loadItems(id);
}

// ── بارگذاری آیتم‌ها ────────────────────────────────────────────
async function loadItems(menuId) {
  try {
    const r = await apiFetch(`/api/v1/iptv/menus/${menuId}`);
    currentMenu  = r.data;                // بروزرسانی با داده کامل شامل appearance
    currentItems = r.data.items || [];
    renderItems();
    renderPreview();
    populateAppearance(r.data);
  } catch(e) { showToast('خطا: ' + e.message, 'error'); }
}

function renderItems() {
  const list = document.getElementById('items-list');
  document.getElementById('items-count').textContent = currentItems.length + ' آیتم';

  if (!currentItems.length) {
    list.innerHTML = '<div style="text-align:center;padding:24px;color:#334155;font-size:12px;"><i class="fas fa-inbox" style="font-size:28px;opacity:.3;display:block;margin-bottom:8px;"></i>هنوز آیتمی ندارد</div>';
    return;
  }

  list.innerHTML = currentItems.map((item, idx) => `
    <div class="item-card" data-item-id="${item.id}" draggable="true"
         ondragstart="dragStart(event,${idx})" ondragover="dragOver(event)" ondrop="dragDrop(event,${idx})">
      <i class="fas fa-grip-vertical drag-handle"></i>
      <div style="width:34px;height:34px;border-radius:9px;background:${item.color}22;border:1px solid ${item.color}44;
                  display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="${item.icon}" style="color:${item.color};font-size:14px;"></i>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:13px;font-weight:600;color:#fff;">${escHtml(item.label)}</div>
        <div style="font-size:10px;color:#475569;">${ITEM_TYPES[item.type]?.label || item.type}${item.target_url ? ' · '+escHtml(item.target_url) : ''}</div>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0;">
        <button onclick="openEditItem(${item.id})" style="background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.3);color:#818cf8;width:28px;height:28px;border-radius:7px;cursor:pointer;font-size:11px;">
          <i class="fas fa-pen"></i>
        </button>
        <button onclick="deleteItem(${item.id})" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;width:28px;height:28px;border-radius:7px;cursor:pointer;font-size:11px;">
          <i class="fas fa-trash"></i>
        </button>
      </div>
    </div>
  `).join('');
}

function renderPreview() {
  const preview = document.getElementById('tv-preview');
  if (!currentItems.length) {
    preview.innerHTML = '<div style="color:#334155;font-size:11px;padding:10px;">هنوز آیتمی ندارد — برای پیش‌نمایش آیتم اضافه کنید</div>';
    return;
  }
  preview.innerHTML = currentItems.map(item => `
    <div style="display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 18px;
                border-radius:12px;background:${item.color}15;border:1px solid ${item.color}33;
                cursor:default;min-width:90px;text-align:center;">
      <i class="${item.icon}" style="color:${item.color};font-size:22px;"></i>
      <span style="font-size:11px;font-weight:600;color:#cbd5e1;">${escHtml(item.label)}</span>
    </div>
  `).join('');
}

// ── Drag & Drop برای مرتب‌سازی ──────────────────────────────────
let dragIdx = null;
function dragStart(e, idx) { dragIdx = idx; e.dataTransfer.effectAllowed = 'move'; }
function dragOver(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; }
async function dragDrop(e, targetIdx) {
  e.preventDefault();
  if (dragIdx === null || dragIdx === targetIdx) return;
  const moved = currentItems.splice(dragIdx, 1)[0];
  currentItems.splice(targetIdx, 0, moved);
  renderItems();
  renderPreview();
  dragIdx = null;
  // ذخیره ترتیب جدید
  await apiFetch(`/api/v1/iptv/menus/${currentMenu.id}/items/sort`, 'POST',
    { ids: currentItems.map(i => i.id) });
}

// ── باز کردن modal ایجاد منو ─────────────────────────────────────
function openCreateMenu(groupId = null, groupName = null) {
  menuModalMode = 'create';
  selectedTemplate = 'empty';
  document.getElementById('menuModal-title').textContent = 'منوی جدید';
  document.getElementById('m-name').value = '';
  document.getElementById('m-desc').value = '';
  document.getElementById('m-group').value = groupId ?? '';
  document.getElementById('templates-row').style.display = '';
  document.querySelectorAll('[id^="tpl-"]').forEach(b => b.classList.remove('tpl-active'));
  document.getElementById('tpl-empty').classList.add('tpl-active');
  document.getElementById('menuModal').classList.remove('hidden');
  setTimeout(() => document.getElementById('m-name').focus(), 80);
}

function openEditMenu() {
  if (!currentMenu) return;
  menuModalMode = 'edit';
  document.getElementById('menuModal-title').textContent = 'ویرایش منو';
  document.getElementById('m-name').value  = currentMenu.name;
  document.getElementById('m-desc').value  = currentMenu.description || '';
  document.getElementById('m-group').value = currentMenu.group_id || '';
  document.getElementById('templates-row').style.display = 'none';
  document.getElementById('menuModal').classList.remove('hidden');
}

function selectTemplate(key) {
  selectedTemplate = key;
  document.querySelectorAll('[id^="tpl-"]').forEach(b => b.classList.remove('tpl-active'));
  document.getElementById('tpl-' + key).classList.add('tpl-active');
}

async function saveMenu() {
  const name = document.getElementById('m-name').value.trim();
  if (!name) { showToast('نام منو الزامی است', 'error'); return; }

  const payload = {
    name,
    description: document.getElementById('m-desc').value.trim(),
    group_id:    document.getElementById('m-group').value || '',
  };

  const btn = document.querySelector('#menuModal .btn-primary');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs ml-1"></i>در حال ذخیره...'; }

  try {
    if (menuModalMode === 'create') {
      const tplTypes = TEMPLATES[selectedTemplate] || [];
      if (tplTypes.length) {
        payload.items = tplTypes.map((type, i) => ({
          type,
          label: ITEM_TYPES[type]?.label || type,
          icon:  getDefaultIcon(type),
          color: getDefaultColor(type),
          sort_order: i,
        }));
      }
      await apiFetch('/api/v1/iptv/menus', 'POST', payload);
      showToast('منو ایجاد شد ✓', 'success');
      setTimeout(() => location.reload(), 800);
    } else {
      const r = await apiFetch(`/api/v1/iptv/menus/${currentMenu.id}`, 'PUT', payload);
      showToast('منو بروز شد ✓', 'success');
      closeModal('menuModal');
      currentMenu = r.data;
      document.getElementById('detail-name').textContent = currentMenu.name;
      const nameSpan = document.querySelector(`.menu-row[data-id="${currentMenu.id}"] span`);
      if (nameSpan) nameSpan.textContent = currentMenu.name;
    }
  } catch(e) {
    console.error('saveMenu error:', e);
    showToast('خطا: ' + e.message, 'error');
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save text-xs ml-1"></i>ذخیره'; }
  }
}

async function deleteMenu() {
  if (!currentMenu) return;
  if (!confirm(`منوی «${currentMenu.name}» و تمام آیتم‌هایش حذف شود؟`)) return;
  try {
    await apiFetch(`/api/v1/iptv/menus/${currentMenu.id}`, 'DELETE');
    showToast('منو حذف شد', 'success');
    location.reload();
  } catch(e) { showToast('خطا: ' + e.message, 'error'); }
}

// ── Modal آیتم ──────────────────────────────────────────────────
function openAddItem() {
  document.getElementById('itemModal-title').textContent = 'افزودن آیتم';
  document.getElementById('item-id').value    = '';
  document.getElementById('item-label').value = '';
  document.getElementById('item-url').value   = '';
  document.getElementById('item-color').value = '#ef4444';
  document.getElementById('item-icon').value  = 'fas fa-satellite-dish';
  pickType('live');
  document.getElementById('itemModal').classList.remove('hidden');
  setTimeout(() => document.getElementById('item-label').focus(), 80);
}

function openEditItem(itemId) {
  const item = currentItems.find(i => i.id == itemId);
  if (!item) return;
  document.getElementById('itemModal-title').textContent = 'ویرایش آیتم';
  document.getElementById('item-id').value    = item.id;
  document.getElementById('item-label').value = item.label;
  document.getElementById('item-url').value   = item.target_url || '';
  document.getElementById('item-color').value = item.color;
  document.getElementById('item-icon').value  = item.icon;
  pickType(item.type);
  document.getElementById('itemModal').classList.remove('hidden');
}

function pickType(type) {
  document.querySelectorAll('[id^="tp-"]').forEach(b => {
    b.classList.remove('tp-active');
    b.style.removeProperty('--tpc');
  });
  const btn = document.getElementById('tp-' + type);
  if (btn) {
    const color = ITEM_TYPES[type]?.color || '#f97316';
    btn.style.setProperty('--tpc', color);
    btn.classList.add('tp-active');
    btn.style.borderColor = color;
    btn.style.background  = color + '15';
  }
  document.getElementById('item-type').value  = type;
  document.getElementById('item-icon').value  = getDefaultIcon(type);
  document.getElementById('item-color').value = getDefaultColor(type);
  // نشان دادن URL فقط برای نوع url
  document.getElementById('url-row').style.display = type === 'url' ? '' : 'none';
}

function quickAdd(type) {
  if (!currentMenu) { showToast('ابتدا یک منو انتخاب کنید', 'error'); return; }
  openAddItem();
  pickType(type);
  document.getElementById('item-label').value = ITEM_TYPES[type]?.label || type;
}

async function saveItem() {
  const label = document.getElementById('item-label').value.trim();
  if (!label) { showToast('عنوان الزامی است', 'error'); return; }

  const itemId  = document.getElementById('item-id').value;
  const typeVal = document.getElementById('item-type').value;
  const payload = {
    type:       typeVal,
    label,
    icon:       document.getElementById('item-icon').value.trim() || getDefaultIcon(typeVal),
    color:      document.getElementById('item-color').value,
    target_url: document.getElementById('item-url').value.trim() || null,
  };

  const btn = document.querySelector('#itemModal .btn-primary');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs ml-1"></i>'; }

  try {
    if (itemId) {
      await apiFetch(`/api/v1/iptv/menus/${currentMenu.id}/items/${itemId}`, 'PUT', payload);
      showToast('آیتم بروز شد ✓', 'success');
    } else {
      await apiFetch(`/api/v1/iptv/menus/${currentMenu.id}/items`, 'POST', payload);
      showToast('آیتم اضافه شد ✓', 'success');
    }
    closeModal('itemModal');
    await loadItems(currentMenu.id);
  } catch(e) {
    console.error('saveItem error:', e);
    showToast('خطا در ذخیره: ' + e.message, 'error');
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save text-xs ml-1"></i>ذخیره'; }
  }
}

async function deleteItem(itemId) {
  if (!confirm('آیتم حذف شود؟')) return;
  try {
    await apiFetch(`/api/v1/iptv/menus/${currentMenu.id}/items/${itemId}`, 'DELETE');
    showToast('آیتم حذف شد', 'success');
    await loadItems(currentMenu.id);
  } catch(e) {
    console.error('deleteItem error:', e);
    showToast('خطا: ' + e.message, 'error');
  }
}

// ── Helpers ─────────────────────────────────────────────────────
function getDefaultIcon(type) {
  const map = {live:'fas fa-satellite-dish',vod:'fas fa-film',news:'fas fa-newspaper',
    info:'fas fa-circle-info',weather:'fas fa-cloud-sun',fids:'fas fa-plane',
    hotel:'fas fa-hotel',corporate:'fas fa-building-columns',retail:'fas fa-store',
    url:'fas fa-link',custom:'fas fa-grip-dots'};
  return map[type] || 'fas fa-play';
}
function getDefaultColor(type) {
  const map = {live:'#ef4444',vod:'#ec4899',news:'#3b82f6',info:'#8b5cf6',
    weather:'#06b6d4',fids:'#0ea5e9',hotel:'#f59e0b',corporate:'#6366f1',
    retail:'#10b981',url:'#64748b',custom:'#f97316'};
  return map[type] || '#f97316';
}
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
async function apiFetch(url, method = 'GET', body = null) {
  const opts = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'Accept':       'application/json',
    },
    credentials: 'same-origin',
  };
  if (body) opts.body = JSON.stringify(body);
  const r = await fetch(url, opts);
  let data = {};
  try { data = await r.json(); } catch(_) {}
  if (!r.ok) {
    const msg = data.message || data.error || `خطای سرور (${r.status})`;
    console.error(`[API ${method} ${url}]`, r.status, data);
    throw new Error(msg);
  }
  return data;
}
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.className = `toast toast-${type}`;
  t.innerHTML = `<i class="fas fa-${type==='success'?'check':'exclamation'}-circle"></i> ${msg}`;
  t.classList.remove('hidden');
  setTimeout(() => t.classList.add('hidden'), 3000);
}

// ══════════════════════════════════════════════════════════════
//  تب‌ها
// ══════════════════════════════════════════════════════════════
function switchTab(tab) {
  const isItems = tab === 'items';
  document.getElementById('tab-items').style.display  = isItems ? '' : 'none';
  document.getElementById('tab-appear').style.display = isItems ? 'none' : '';
  const ib = document.getElementById('tab-items-btn');
  const ab = document.getElementById('tab-appear-btn');
  ib.style.background = isItems ? 'rgba(239,68,68,.15)' : 'transparent';
  ib.style.color      = isItems ? '#f87171' : '#475569';
  ab.style.background = isItems ? 'transparent' : 'rgba(239,68,68,.15)';
  ab.style.color      = isItems ? '#475569' : '#f87171';
}

// ══════════════════════════════════════════════════════════════
//  Appearance — پر کردن فرم
// ══════════════════════════════════════════════════════════════
function populateAppearance(menu) {
  setImagePreview('bg',   menu.bg_image  || null);
  setImagePreview('logo', menu.logo_url  || null);
  document.getElementById('ap-accent').value               = menu.accent_color || '#ef4444';
  const dim = parseFloat(menu.bg_dim ?? 0.55);
  document.getElementById('ap-bg-dim').value               = dim;
  document.getElementById('ap-bg-dim-val').textContent     = dim;
  const blur = parseInt(menu.bg_blur ?? 0);
  document.getElementById('ap-bg-blur').value              = blur;
  document.getElementById('ap-bg-blur-val').textContent    = blur + 'px';
  document.getElementById('ap-welcome-title').value        = menu.welcome_title || '';
  document.getElementById('ap-welcome-sub').value          = menu.welcome_sub   || '';
  document.getElementById('ap-ticker-text').value          = menu.ticker_text   || '';
  document.getElementById('ap-ticker-color').value         = menu.ticker_color  || '#ffffff';
  document.getElementById('ap-ticker-bg').value            = menu.ticker_bg     || '#000000';
  const spd = parseInt(menu.ticker_speed ?? 40);
  document.getElementById('ap-ticker-speed').value         = spd;
  document.getElementById('ap-ticker-speed-val').textContent = spd;
  document.getElementById('ap-save-status').textContent    = '';
}

function setImagePreview(type, url) {
  if (type === 'bg') {
    const wrap = document.getElementById('bg-preview-wrap');
    const img  = document.getElementById('bg-preview');
    if (url) { img.src = url; wrap.style.display = 'block'; }
    else      { wrap.style.display = 'none'; img.src = ''; }
  } else {
    const wrap = document.getElementById('logo-preview-wrap');
    const img  = document.getElementById('logo-preview');
    if (url) { img.src = url; wrap.style.display = 'flex'; }
    else      { wrap.style.display = 'none'; img.src = ''; }
  }
}

// ══════════════════════════════════════════════════════════════
//  آپلود تصویر
// ══════════════════════════════════════════════════════════════
async function uploadImg(type, input) {
  if (!currentMenu || !input.files[0]) return;
  const txtId  = type === 'bg' ? 'bg-upload-txt' : 'logo-upload-txt';
  const origTx = document.getElementById(txtId).textContent;
  document.getElementById(txtId).textContent = 'در حال آپلود...';

  const form = new FormData();
  form.append('file', input.files[0]);
  form.append('type', type);

  try {
    const resp = await fetch(`/api/v1/iptv/menus/${currentMenu.id}/upload-image`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      body: form,
    });
    const d = await resp.json();
    if (!resp.ok) throw new Error(d.message || `خطا (${resp.status})`);

    const col = type === 'bg' ? 'bg_image' : 'logo_url';
    currentMenu[col] = d.data.url;
    setImagePreview(type, d.data.url);
    showToast('تصویر آپلود شد ✓', 'success');
  } catch(e) {
    showToast('خطا در آپلود: ' + e.message, 'error');
  } finally {
    document.getElementById(txtId).textContent = origTx;
    input.value = '';
  }
}

// ══════════════════════════════════════════════════════════════
//  حذف تصویر
// ══════════════════════════════════════════════════════════════
async function removeImg(type) {
  if (!currentMenu || !confirm('تصویر حذف شود؟')) return;
  try {
    await apiFetch(`/api/v1/iptv/menus/${currentMenu.id}/remove-image`, 'POST', { type });
    const col = type === 'bg' ? 'bg_image' : 'logo_url';
    currentMenu[col] = null;
    setImagePreview(type, null);
    showToast('تصویر حذف شد', 'success');
  } catch(e) { showToast('خطا: ' + e.message, 'error'); }
}

// ══════════════════════════════════════════════════════════════
//  ذخیره ظاهر
// ══════════════════════════════════════════════════════════════
let apSaveTimer = null;
function debounceSave() {
  clearTimeout(apSaveTimer);
  document.getElementById('ap-save-status').textContent = '...';
  apSaveTimer = setTimeout(saveAppearance, 1200);
}

async function saveAppearance() {
  if (!currentMenu) return;
  clearTimeout(apSaveTimer);
  const payload = {
    accent_color:  document.getElementById('ap-accent').value,
    bg_dim:        parseFloat(document.getElementById('ap-bg-dim').value),
    bg_blur:       parseInt(document.getElementById('ap-bg-blur').value),
    welcome_title: document.getElementById('ap-welcome-title').value.trim(),
    welcome_sub:   document.getElementById('ap-welcome-sub').value.trim(),
    ticker_text:   document.getElementById('ap-ticker-text').value.trim(),
    ticker_color:  document.getElementById('ap-ticker-color').value,
    ticker_bg:     document.getElementById('ap-ticker-bg').value,
    ticker_speed:  parseInt(document.getElementById('ap-ticker-speed').value),
  };
  const statusEl = document.getElementById('ap-save-status');
  statusEl.textContent = 'در حال ذخیره...';
  statusEl.style.color = '#94a3b8';
  try {
    await apiFetch(`/api/v1/iptv/menus/${currentMenu.id}`, 'PUT', payload);
    Object.assign(currentMenu, payload);
    statusEl.textContent = '✓ ذخیره شد';
    statusEl.style.color = '#22c55e';
    showToast('تنظیمات ظاهر ذخیره شد ✓', 'success');
    setTimeout(() => { statusEl.textContent = ''; }, 3000);
  } catch(e) {
    statusEl.textContent = 'خطا!';
    statusEl.style.color = '#ef4444';
    showToast('خطا: ' + e.message, 'error');
  }
}
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
