<?php include VIEWS_PATH . '/partials/layout.php'; ?>
<div class="flex items-center gap-3 mb-6">
  <a href="/admin/screens" class="btn-ghost text-sm px-3"><i class="fas fa-arrow-right text-xs"></i></a>
  <h1 class="text-xl font-bold text-white">صفحه نمایش جدید</h1>
</div>
<div class="card max-w-xl">
  <form method="POST" action="/admin/screens" class="space-y-4">
    <?= csrf_field() ?>
    <div><label class="form-label">نام صفحه *</label>
    <input type="text" name="name" class="form-input" required placeholder="مثلاً: تابلوی ورودی ساختمان"></div>

    <div><label class="form-label">توضیحات</label>
    <input type="text" name="description" class="form-input" placeholder="توضیح اختیاری"></div>
  <div>
    <label class="form-label">نوع صفحه</label>
    <select name="screen_type" class="form-input" id="screen_type_sel"
            onchange="toggleIptvCreate(this.value)">
      <option value="signage"  <?= ($_GET["type"]??"signage")==="signage"?"selected":"" ?>>📺 Signage — محتوای دیجیتال</option>
      <option value="iptv"     <?= ($_GET["type"]??"")==="iptv"?"selected":"" ?>>📡 IPTV — کانال زنده</option>
      <option value="inflight" <?= ($_GET["type"]??"")==="inflight"?"selected":"" ?>>✈ In-Flight — داخل هواپیما</option>
    </select>
  </div>

    <div class="grid grid-cols-2 gap-4">
      <div><label class="form-label">جهت نمایش</label>
      <select name="orientation" class="form-input">
        <option value="landscape">افقی (Landscape)</option>
        <option value="portrait">عمودی (Portrait)</option>
      </select></div>
      <div><label class="form-label">رزولوشن</label>
      <select name="resolution" class="form-input">
        <option value="1920x1080">1920×1080 (Full HD)</option>
        <option value="3840x2160">3840×2160 (4K)</option>
        <option value="1280x720">1280×720 (HD)</option>
        <option value="1080x1920">1080×1920 (Portrait FHD)</option>
      </select></div>
    </div>

    <div><label class="form-label">موقعیت</label>
    <select name="location_id" class="form-input">
      <option value="">— بدون موقعیت —</option>
      <?php foreach ($locations ?? [] as $loc): ?>
      <option value="<?=$loc['id']?>"><?=e($loc['name'])?></option>
      <?php endforeach; ?>
    </select></div>

    <!-- ── IPTV: گروه + منو ───────────────────────────────────────── -->
    <?php $createIsIptv = ($_GET['type'] ?? 'signage') === 'iptv'; ?>
    <div id="iptv-create-section" style="<?= $createIsIptv ? '' : 'display:none;' ?>
         background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.15);
         border-radius:12px;padding:14px;margin-top:4px;">
      <div style="font-size:11px;font-weight:700;color:#f87171;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
        <i class="fas fa-satellite-dish text-xs"></i>تنظیمات IPTV
      </div>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <div>
          <label class="form-label">گروه IPTV</label>
          <select name="group_id" class="form-input">
            <option value="">— بدون گروه —</option>
            <?php foreach ($groups ?? [] as $g): if ($g['type'] !== 'iptv') continue; ?>
            <option value="<?=$g['id']?>"><?=e($g['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">منوی IPTV</label>
          <select name="iptv_menu_id" class="form-input">
            <option value="">— بدون منو (بعداً انتخاب کنید) —</option>
            <?php foreach ($iptvMenus ?? [] as $menu): ?>
            <option value="<?=$menu['id']?>"><?=e($menu['name'])?><?= $menu['group_name'] ? ' ('.$menu['group_name'].')' : '' ?></option>
            <?php endforeach; ?>
          </select>
          <div style="font-size:10px;color:#475569;margin-top:4px;">
            <a href="/admin/iptv/menus" target="_blank" style="color:#f87171;text-decoration:none;">
              <i class="fas fa-external-link text-xs ml-1"></i>مدیریت منوها
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- ── In-Flight: پرواز ──────────────────────────────────── -->
    <?php $createIsInflight = ($_GET['type'] ?? 'signage') === 'inflight'; ?>
    <div id="inflight-create-section" style="<?= $createIsInflight ? '' : 'display:none;' ?>
         background:rgba(0,180,216,.04);border:1px solid rgba(0,180,216,.2);
         border-radius:12px;padding:14px;margin-top:4px;">
      <h3 style="font-size:12px;font-weight:700;color:#00b4d8;margin-bottom:10px;">✈ پرواز In-Flight</h3>
      <label class="form-label">انتخاب پرواز (اختیاری — بعداً قابل تغییر)</label>
      <select name="inflight_flight_id" class="form-input">
        <option value="">— بدون پرواز —</option>
        <?php
        // load flights for create form
        $tid_create = \App\Core\Auth::tenantId();
        $flights_create = [];
        try {
            global $db;
            if (!isset($db)) {
                $db = \App\Core\Database::getInstance();
            }
            $flights_create = $db->rows("SELECT id, flight_number, airline_name, origin_iata, dest_iata FROM inflight_flights WHERE tenant_id=? AND is_active=1 ORDER BY flight_number", [$tid_create]) ?: [];
        } catch (\Throwable $e) {}
        foreach ($flights_create as $fl):
        ?>
        <option value="<?= $fl['id'] ?>">
          <?= htmlspecialchars($fl['flight_number']) ?>
          <?= $fl['origin_iata'] ? ' ('.$fl['origin_iata'].' → '.($fl['dest_iata']??'?').')' : '' ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="btn-primary flex-1 py-3">ایجاد صفحه</button>
      <a href="/admin/screens" class="btn-ghost px-6">لغو</a>
    </div>
  </form>
</div>
<script>
function toggleIptvCreate(type) {
  const sec = document.getElementById('iptv-create-section');
  if (sec) sec.style.display = type === 'iptv' ? '' : 'none';
  const ifsec = document.getElementById('inflight-create-section');
  if (ifsec) ifsec.style.display = type === 'inflight' ? '' : 'none';
}
toggleIptvCreate(document.getElementById('screen_type_sel').value);
</script>
<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
