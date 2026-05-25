<?php include VIEWS_PATH . '/partials/layout.php'; ?>

<?php
$STATUS_LABELS = ['available'=>'خالی','occupied'=>'اشغال','maintenance'=>'تعمیر'];
$STATUS_COLORS = ['available'=>'#22c55e','occupied'=>'#ef4444','maintenance'=>'#f59e0b'];
$TYPE_LABELS   = ['single'=>'یک تخته','double'=>'دو تخته','suite'=>'سوئیت','vip'=>'VIP'];
$MSG_TYPES     = ['info'=>['اطلاع‌رسانی','#3b82f6'],'welcome'=>['خوش‌آمدگویی','#22c55e'],'urgent'=>['فوری','#ef4444'],'promo'=>['تبلیغاتی','#f59e0b'],'custom'=>['سفارشی','#8b5cf6']];
$MSG_MODES     = ['banner'=>'نوار','popup'=>'پاپ‌آپ','ticker'=>'تیکر'];
$rooms       = $rooms       ?? [];
$iptvGroups  = $iptvGroups  ?? [];
$pmsIntegrations = $pmsIntegrations ?? [];
?>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <div style="display:flex;align-items:center;gap:12px;">
    <a href="/admin/iptv" class="btn-ghost text-sm px-3"><i class="fas fa-arrow-right text-xs"></i></a>
    <h1 style="font-size:20px;font-weight:800;color:#fff;">
      <i class="fas fa-door-open" style="color:#ef4444;margin-left:10px;"></i>اتاق‌های IPTV
    </h1>
    <span style="font-size:11px;color:#475569;background:rgba(255,255,255,.05);padding:2px 10px;border-radius:10px;"><?= count($rooms) ?> اتاق</span>
  </div>
  <div style="display:flex;gap:8px;">
    <button onclick="openPmsModal()" class="btn-ghost text-sm px-3">
      <i class="fas fa-plug text-xs ml-1"></i>یکپارچه‌سازی PMS
    </button>
    <button onclick="openBroadcastModal()" class="btn-ghost text-sm px-3">
      <i class="fas fa-bullhorn text-xs ml-1"></i>پیام به همه
    </button>
    <button onclick="openCreateRoom()" class="btn-primary text-sm">
      <i class="fas fa-plus text-xs ml-1"></i>اتاق جدید
    </button>
  </div>
</div>

<!-- آمار سریع -->
<?php
$totalRooms     = count($rooms);
$occupiedRooms  = count(array_filter($rooms, fn($r) => $r['status']==='occupied'));
$availableRooms = count(array_filter($rooms, fn($r) => $r['status']==='available'));
$maintenRooms   = count(array_filter($rooms, fn($r) => $r['status']==='maintenance'));
?>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px;">
  <?php foreach ([
    ['fas fa-door-open','کل اتاق‌ها',$totalRooms,'#6366f1'],
    ['fas fa-user-check','اشغال',$occupiedRooms,'#ef4444'],
    ['fas fa-door-closed','خالی',$availableRooms,'#22c55e'],
    ['fas fa-tools','تعمیرات',$maintenRooms,'#f59e0b'],
  ] as [$icon,$label,$val,$color]): ?>
  <div class="card" style="padding:14px;display:flex;align-items:center;gap:12px;">
    <div style="width:38px;height:38px;border-radius:10px;background:<?= $color ?>22;border:1px solid <?= $color ?>44;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="<?= $icon ?>" style="color:<?= $color ?>;font-size:16px;"></i>
    </div>
    <div>
      <div style="font-size:20px;font-weight:800;color:#fff;"><?= $val ?></div>
      <div style="font-size:10px;color:#475569;"><?= $label ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- دو ستون -->
<div style="display:grid;grid-template-columns:300px 1fr;gap:16px;align-items:start;">

  <!-- ─── ستون چپ: لیست اتاق‌ها ─────────────────────────────── -->
  <div>
    <div class="card" style="padding:0;overflow:hidden;">
      <div style="padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;gap:8px;align-items:center;">
        <input type="text" id="room-search" placeholder="جستجوی اتاق..." class="form-input"
          style="flex:1;padding:6px 10px;font-size:12px;" oninput="filterRooms(this.value)">
        <select id="room-status-filter" class="form-input" style="width:90px;padding:6px 8px;font-size:11px;" onchange="filterRooms()">
          <option value="">همه</option>
          <option value="available">خالی</option>
          <option value="occupied">اشغال</option>
          <option value="maintenance">تعمیر</option>
        </select>
      </div>
      <div id="room-tree" style="padding:8px;max-height:calc(100vh - 300px);overflow-y:auto;">
        <?php if (empty($rooms)): ?>
        <div style="text-align:center;padding:30px 16px;color:#475569;font-size:12px;">
          <i class="fas fa-door-open" style="font-size:28px;opacity:.2;display:block;margin-bottom:10px;"></i>
          هنوز اتاقی ثبت نشده<br>
          <button onclick="openCreateRoom()" style="margin-top:10px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:5px 14px;border-radius:8px;cursor:pointer;font-size:11px;font-family:inherit;">+ اتاق جدید</button>
        </div>
        <?php else: ?>
        <?php
        // گروه‌بندی بر اساس floor
        $byFloor = [];
        foreach ($rooms as $r) {
            $key = $r['floor'] !== null ? 'طبقه ' . $r['floor'] : 'بدون طبقه';
            $byFloor[$key][] = $r;
        }
        ?>
        <?php foreach ($byFloor as $floorLabel => $floorRooms): ?>
        <div class="floor-group" style="margin-bottom:10px;">
          <div style="font-size:10px;font-weight:700;color:#475569;padding:3px 8px;letter-spacing:.5px;text-transform:uppercase;"><?= e($floorLabel) ?></div>
          <?php foreach ($floorRooms as $r): ?>
          <?php $sc = $STATUS_COLORS[$r['status']] ?? '#475569'; ?>
          <div class="room-row" data-id="<?= $r['id'] ?>" data-status="<?= $r['status'] ?>" data-search="<?= strtolower(e($r['room_number'] . ' ' . $r['room_name'] . ' ' . $r['guest_name'])) ?>"
               onclick="selectRoom(<?= $r['id'] ?>, <?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)"
               style="display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:9px;cursor:pointer;transition:background .15s;margin-bottom:2px;">
            <span style="width:8px;height:8px;border-radius:50%;background:<?= $sc ?>;flex-shrink:0;box-shadow:0 0 6px <?= $sc ?>66;"></span>
            <span style="font-size:13px;font-weight:700;color:#fff;min-width:40px;"><?= e($r['room_number']) ?></span>
            <span style="flex:1;font-size:11px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?= $r['guest_name'] ? e($r['guest_name']) : ($r['room_name'] ? e($r['room_name']) : ($TYPE_LABELS[$r['room_type']] ?? '')) ?>
            </span>
            <?php if ($r['active_msgs'] > 0): ?>
            <span style="font-size:9px;background:#ef444422;border:1px solid #ef444433;color:#f87171;padding:1px 6px;border-radius:10px;"><?= $r['active_msgs'] ?></span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ─── ستون راست: جزئیات ─────────────────────────────────── -->
  <div id="room-detail">

    <div id="empty-state" class="card" style="text-align:center;padding:60px 20px;color:#334155;">
      <i class="fas fa-door-open" style="font-size:48px;margin-bottom:16px;display:block;opacity:.15;"></i>
      <div style="font-size:14px;color:#475569;">یک اتاق از لیست انتخاب کنید</div>
    </div>

    <div id="detail-panel" style="display:none;">

      <!-- هدر اتاق -->
      <div class="card" style="margin-bottom:12px;">
        <div style="display:flex;align-items:center;gap:14px;">
          <div id="room-status-badge" style="width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:20px;"></div>
          <div style="flex:1;">
            <div style="display:flex;align-items:center;gap:10px;">
              <h2 id="detail-room-num" style="font-size:20px;font-weight:900;color:#fff;"></h2>
              <span id="detail-room-name" style="font-size:12px;color:#64748b;"></span>
              <span id="detail-status-tag" style="font-size:10px;font-weight:700;padding:2px 10px;border-radius:10px;"></span>
            </div>
            <div id="detail-guest" style="font-size:12px;color:#94a3b8;margin-top:2px;display:none;"></div>
          </div>
          <div style="display:flex;gap:8px;">
            <button onclick="openEditRoom()" class="btn-ghost text-xs px-3"><i class="fas fa-pen text-xs ml-1"></i>ویرایش</button>
            <button onclick="deleteRoom()" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:6px 12px;border-radius:9px;font-size:12px;cursor:pointer;font-family:inherit;"><i class="fas fa-trash text-xs ml-1"></i>حذف</button>
          </div>
        </div>
      </div>

      <!-- تب‌ها -->
      <div style="display:flex;gap:4px;margin-bottom:12px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:4px;">
        <?php foreach ([['info','fas fa-circle-info','اطلاعات'],['guest','fas fa-user','مهمان'],['msgs','fas fa-envelope','پیام‌ها'],['tv','fas fa-tv','تلویزیون']] as [$tabKey,$icon,$label]): ?>
        <button id="dtab-<?= $tabKey ?>-btn" onclick="switchDTab('<?= $tabKey ?>')"
          style="flex:1;padding:8px 0;border-radius:8px;border:none;font-size:11px;font-weight:700;cursor:pointer;font-family:'Vazirmatn',sans-serif;transition:all .15s;background:transparent;color:#475569;">
          <i class="<?= $icon ?> text-xs ml-1"></i><?= $label ?>
        </button>
        <?php endforeach; ?>
      </div>

      <!-- ════ تب اطلاعات ════ -->
      <div id="dtab-info" class="card" style="margin-bottom:12px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
          <div><label class="form-label">شماره اتاق</label><input type="text" id="inf-number" class="form-input" oninput="debounceSaveRoom()"></div>
          <div><label class="form-label">نام اتاق</label><input type="text" id="inf-name" class="form-input" placeholder="اختیاری" oninput="debounceSaveRoom()"></div>
          <div>
            <label class="form-label">طبقه</label>
            <input type="number" id="inf-floor" class="form-input" min="0" max="99" placeholder="مثلاً 1" oninput="debounceSaveRoom()">
          </div>
          <div>
            <label class="form-label">نوع اتاق</label>
            <select id="inf-type" class="form-input" onchange="debounceSaveRoom()">
              <option value="">— انتخاب —</option>
              <?php foreach ($TYPE_LABELS as $k => $v): ?>
              <option value="<?= $k ?>"><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">وضعیت</label>
            <select id="inf-status" class="form-input" onchange="debounceSaveRoom()">
              <?php foreach ($STATUS_LABELS as $k => $v): ?>
              <option value="<?= $k ?>"><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">کد PMS <span style="color:#475569;font-size:10px;">(اختیاری)</span></label>
            <input type="text" id="inf-pms-id" class="form-input" placeholder="شناسه در سیستم PMS" oninput="debounceSaveRoom()">
          </div>
          <div style="grid-column:1/-1;">
            <label class="form-label">یادداشت</label>
            <input type="text" id="inf-notes" class="form-input" placeholder="اختیاری" oninput="debounceSaveRoom()">
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;margin-top:12px;">
          <button onclick="saveRoom()" class="btn-primary text-xs px-4" style="padding:8px 16px;"><i class="fas fa-save text-xs ml-1"></i>ذخیره</button>
          <span id="room-save-status" style="font-size:11px;color:#475569;"></span>
        </div>
      </div>

      <!-- ════ تب مهمان ════ -->
      <div id="dtab-guest" style="display:none;">
        <div class="card" style="margin-bottom:12px;">
          <div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:14px;">
            <i class="fas fa-user-check text-green-400 text-xs ml-1.5"></i>ورود مهمان (Check-in)
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label class="form-label">نام مهمان</label>
              <input type="text" id="ci-name" class="form-input" placeholder="نام و نام خانوادگی">
            </div>
            <div>
              <label class="form-label">زبان مهمان</label>
              <select id="ci-lang" class="form-input">
                <option value="fa">فارسی</option>
                <option value="en">English</option>
                <option value="ar">عربی</option>
                <option value="tr">Türkçe</option>
                <option value="ru">Русский</option>
              </select>
            </div>
            <div>
              <label class="form-label">تاریخ ورود</label>
              <input type="datetime-local" id="ci-in" class="form-input">
            </div>
            <div>
              <label class="form-label">تاریخ خروج برنامه‌ریزی</label>
              <input type="datetime-local" id="ci-out" class="form-input">
            </div>
            <div style="grid-column:1/-1;display:flex;align-items:center;gap:8px;">
              <input type="checkbox" id="ci-welcome" checked style="width:16px;height:16px;accent-color:#ef4444;">
              <label for="ci-welcome" style="font-size:12px;color:#94a3b8;cursor:pointer;">نمایش پیام خوش‌آمدگویی روی TV</label>
            </div>
          </div>
          <div style="display:flex;gap:8px;margin-top:14px;">
            <button onclick="doCheckin()" class="btn-primary" style="padding:9px 20px;">
              <i class="fas fa-sign-in-alt text-xs ml-1"></i>ثبت ورود
            </button>
            <button id="checkout-btn" onclick="doCheckout()"
              style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:9px 18px;border-radius:9px;font-size:12px;cursor:pointer;font-family:inherit;display:none;">
              <i class="fas fa-sign-out-alt text-xs ml-1"></i>ثبت خروج
            </button>
          </div>
        </div>

        <!-- اطلاعات مهمان فعلی -->
        <div id="current-guest-card" class="card" style="display:none;background:rgba(34,197,94,.05);border:1px solid rgba(34,197,94,.15);">
          <div style="font-size:12px;font-weight:700;color:#22c55e;margin-bottom:10px;"><i class="fas fa-user text-xs ml-1"></i>مهمان فعلی</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;color:#94a3b8;">
            <div>نام: <strong id="cg-name" style="color:#fff;"></strong></div>
            <div>زبان: <strong id="cg-lang" style="color:#fff;"></strong></div>
            <div>ورود: <strong id="cg-in" style="color:#fff;font-size:11px;"></strong></div>
            <div>خروج: <strong id="cg-out" style="color:#fff;font-size:11px;"></strong></div>
          </div>
        </div>
      </div>

      <!-- ════ تب پیام‌ها ════ -->
      <div id="dtab-msgs" style="display:none;">
        <!-- فرم ارسال پیام -->
        <div class="card" style="margin-bottom:12px;">
          <div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:12px;">
            <i class="fas fa-paper-plane text-blue-400 text-xs ml-1.5"></i>ارسال پیام جدید
          </div>
          <div style="display:grid;gap:10px;">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
              <div>
                <label class="form-label">نوع</label>
                <select id="msg-type" class="form-input" style="font-size:12px;" onchange="updateMsgAccent()">
                  <?php foreach ($MSG_TYPES as $k => [$v,$c]): ?>
                  <option value="<?= $k ?>"><?= $v ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="form-label">نمایش</label>
                <select id="msg-mode" class="form-input" style="font-size:12px;">
                  <?php foreach ($MSG_MODES as $k => $v): ?>
                  <option value="<?= $k ?>"><?= $v ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="form-label">انقضا</label>
                <select id="msg-expires" class="form-input" style="font-size:12px;">
                  <option value="1">۱ ساعت</option>
                  <option value="4" selected>۴ ساعت</option>
                  <option value="24">۲۴ ساعت</option>
                  <option value="168">۷ روز</option>
                  <option value="0">بدون انقضا</option>
                </select>
              </div>
            </div>
            <div>
              <label class="form-label">عنوان <span style="color:#475569;font-size:10px;">(اختیاری)</span></label>
              <input type="text" id="msg-title" class="form-input" placeholder="مثلاً: اطلاعیه مهم">
            </div>
            <div>
              <label class="form-label">متن پیام *</label>
              <textarea id="msg-body" class="form-input" rows="3" style="resize:vertical;" placeholder="متن پیامی که روی تلویزیون نمایش داده می‌شود..."></textarea>
            </div>
            <button onclick="sendMsg()" class="btn-primary" style="padding:9px;">
              <i class="fas fa-paper-plane text-xs ml-1"></i>ارسال پیام
            </button>
          </div>
        </div>

        <!-- لیست پیام‌ها -->
        <div class="card" style="padding:0;overflow:hidden;">
          <div style="padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.06);font-size:12px;font-weight:700;color:#fff;">
            <i class="fas fa-list text-xs ml-1 text-blue-400"></i>پیام‌های اخیر
          </div>
          <div id="msgs-list" style="padding:8px;max-height:300px;overflow-y:auto;"></div>
        </div>
      </div>

      <!-- ════ تب تلویزیون ════ -->
      <div id="dtab-tv" style="display:none;">
        <div class="card">
          <div style="font-size:13px;font-weight:700;color:#fff;margin-bottom:12px;">
            <i class="fas fa-tv text-red-400 text-xs ml-1.5"></i>تلویزیون متصل
          </div>
          <div id="tv-info">
            <div style="text-align:center;padding:20px;color:#334155;font-size:12px;">
              <i class="fas fa-tv" style="font-size:32px;opacity:.2;display:block;margin-bottom:8px;"></i>
              هنوز تلویزیونی به این اتاق وصل نشده
            </div>
          </div>
          <div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.06);">
            <div style="font-size:11px;color:#475569;">
              <i class="fas fa-info-circle text-xs ml-1"></i>
              برای اتصال تلویزیون، در صفحه <a href="/admin/screens" style="color:#60a5fa;">مدیریت صفحه‌نمایش‌ها</a> گزینه «اتاق IPTV» را تنظیم کنید.
            </div>
          </div>
        </div>
      </div>

    </div><!-- /detail-panel -->
  </div>
</div>

<!-- ══ MODAL: ایجاد/ویرایش اتاق ══ -->
<div id="roomModal" class="modal-overlay hidden">
  <div class="modal" style="max-width:460px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <h3 id="roomModal-title" style="font-size:15px;font-weight:800;color:#fff;">اتاق جدید</h3>
      <button onclick="closeModal('roomModal')" style="background:none;border:none;color:#475569;cursor:pointer;font-size:16px;">✕</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div><label class="form-label">شماره اتاق *</label><input type="text" id="rm-number" class="form-input" placeholder="مثلاً 101"></div>
      <div><label class="form-label">نام اتاق</label><input type="text" id="rm-name" class="form-input" placeholder="اختیاری"></div>
      <div><label class="form-label">طبقه</label><input type="number" id="rm-floor" class="form-input" min="0" max="99" placeholder="مثلاً 1"></div>
      <div>
        <label class="form-label">نوع</label>
        <select id="rm-type" class="form-input">
          <option value="">— انتخاب —</option>
          <?php foreach ($TYPE_LABELS as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">گروه IPTV</label>
        <select id="rm-group" class="form-input">
          <option value="">— بدون گروه —</option>
          <?php foreach ($iptvGroups as $g): ?><option value="<?= $g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label class="form-label">کد PMS</label><input type="text" id="rm-pms-id" class="form-input" placeholder="اختیاری"></div>
      <div style="grid-column:1/-1;"><label class="form-label">یادداشت</label><input type="text" id="rm-notes" class="form-input" placeholder="اختیاری"></div>
    </div>
    <div style="display:flex;gap:8px;margin-top:20px;">
      <button onclick="saveNewRoom()" class="btn-primary" style="flex:1;padding:10px;"><i class="fas fa-save text-xs ml-1"></i>ذخیره</button>
      <button onclick="closeModal('roomModal')" class="btn-ghost" style="padding:10px 20px;">لغو</button>
    </div>
  </div>
</div>

<!-- ══ MODAL: پیام به همه ══ -->
<div id="broadcastModal" class="modal-overlay hidden">
  <div class="modal" style="max-width:480px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <h3 style="font-size:15px;font-weight:800;color:#fff;"><i class="fas fa-bullhorn text-yellow-400 ml-1.5"></i>پیام به همه اتاق‌ها</h3>
      <button onclick="closeModal('broadcastModal')" style="background:none;border:none;color:#475569;cursor:pointer;font-size:16px;">✕</button>
    </div>
    <div style="display:grid;gap:12px;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div><label class="form-label">نوع</label>
          <select id="bc-type" class="form-input">
            <?php foreach ($MSG_TYPES as $k=>[$v,$c]): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="form-label">نمایش</label>
          <select id="bc-mode" class="form-input">
            <?php foreach ($MSG_MODES as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div><label class="form-label">عنوان</label><input type="text" id="bc-title" class="form-input" placeholder="اختیاری"></div>
      <div><label class="form-label">متن پیام *</label><textarea id="bc-body" class="form-input" rows="3" style="resize:vertical;"></textarea></div>
    </div>
    <div style="display:flex;gap:8px;margin-top:20px;">
      <button onclick="doBroadcast()" class="btn-primary" style="flex:1;padding:10px;"><i class="fas fa-bullhorn text-xs ml-1"></i>ارسال به همه</button>
      <button onclick="closeModal('broadcastModal')" class="btn-ghost" style="padding:10px 20px;">لغو</button>
    </div>
  </div>
</div>

<!-- ══ MODAL: PMS ══ -->
<div id="pmsModal" class="modal-overlay hidden">
  <div class="modal" style="max-width:600px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <h3 style="font-size:15px;font-weight:800;color:#fff;"><i class="fas fa-plug text-purple-400 ml-1.5"></i>یکپارچه‌سازی PMS</h3>
      <button onclick="closeModal('pmsModal')" style="background:none;border:none;color:#475569;cursor:pointer;font-size:16px;">✕</button>
    </div>

    <!-- API doc -->
    <div style="background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.15);border-radius:12px;padding:14px 16px;margin-bottom:16px;">
      <div style="font-size:11px;font-weight:700;color:#818cf8;margin-bottom:10px;"><i class="fas fa-book text-xs ml-1"></i>مستندات API</div>
      <div style="font-size:11px;color:#64748b;line-height:2;">
        <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;color:#a5b4fc;">POST <?= rtrim($_SERVER['HTTP_HOST'] ? '//' . $_SERVER['HTTP_HOST'] : '', '/') ?>/api/v1/pms/checkin</code> — ورود مهمان<br>
        <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;color:#a5b4fc;">POST <?= rtrim($_SERVER['HTTP_HOST'] ? '//' . $_SERVER['HTTP_HOST'] : '', '/') ?>/api/v1/pms/checkout</code> — خروج مهمان<br>
        <code style="background:rgba(0,0,0,.3);padding:2px 6px;border-radius:4px;color:#a5b4fc;">POST <?= rtrim($_SERVER['HTTP_HOST'] ? '//' . $_SERVER['HTTP_HOST'] : '', '/') ?>/api/v1/pms/message</code> — ارسال پیام<br>
        هدر احراز هویت: <code style="color:#fbbf24;">X-PMS-Key: {api_key}</code>
      </div>
    </div>

    <!-- لیست کلیدها -->
    <div id="pms-keys-list" style="margin-bottom:14px;"></div>

    <!-- فرم ایجاد کلید -->
    <div style="display:flex;gap:8px;">
      <input type="text" id="pms-name-input" class="form-input" placeholder="نام سیستم PMS (مثلاً: Fidelio، Opera)" style="flex:1;">
      <select id="pms-type-input" class="form-input" style="width:130px;">
        <option value="custom">Custom</option>
        <option value="opera">Opera</option>
        <option value="fidelio">Fidelio</option>
        <option value="hotelogix">Hotelogix</option>
        <option value="protel">Protel</option>
      </select>
      <button onclick="createPmsKey()" class="btn-primary text-xs px-4"><i class="fas fa-key text-xs ml-1"></i>ایجاد کلید</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div id="toast" class="toast hidden"></div>

<style>
.room-row:hover { background:rgba(239,68,68,.08) !important; }
.room-row.selected { background:rgba(239,68,68,.12) !important; }
.room-row.selected span { color:#f87171 !important; }
.dtab-active { background:rgba(239,68,68,.15) !important; color:#f87171 !important; }
.msg-card { display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border-radius:10px;
            background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);margin-bottom:6px; }
</style>

<script>
const STATUS_COLORS = <?= json_encode($STATUS_COLORS) ?>;
const STATUS_LABELS = <?= json_encode($STATUS_LABELS) ?>;
const MSG_TYPES     = <?= json_encode(array_map(fn($v)=>['label'=>$v[0],'color'=>$v[1]], $MSG_TYPES)) ?>;
const MSG_MODES     = <?= json_encode($MSG_MODES) ?>;

let currentRoom = null;
let pmsIntegrations = <?= json_encode($pmsIntegrations) ?>;
let roomSaveTimer = null;

// ══ انتخاب اتاق ══════════════════════════════════════════════════
function selectRoom(id, roomData) {
  document.querySelectorAll('.room-row').forEach(r => r.classList.remove('selected'));
  document.querySelector(`.room-row[data-id="${id}"]`)?.classList.add('selected');

  document.getElementById('empty-state').style.display  = 'none';
  document.getElementById('detail-panel').style.display = 'block';
  currentRoom = roomData;
  renderRoomHeader(roomData);
  fillInfoTab(roomData);
  fillGuestTab(roomData);
  switchDTab('info');
  loadMessages(id);
}

function renderRoomHeader(r) {
  const sc = STATUS_COLORS[r.status] || '#475569';
  const icons = {available:'fas fa-door-closed',occupied:'fas fa-user',maintenance:'fas fa-tools'};
  document.getElementById('room-status-badge').innerHTML = `<i class="${icons[r.status]||'fas fa-door-open'}" style="color:${sc};font-size:18px;"></i>`;
  document.getElementById('room-status-badge').style.background = sc + '22';
  document.getElementById('room-status-badge').style.border     = `1px solid ${sc}44`;
  document.getElementById('detail-room-num').textContent  = r.room_number;
  document.getElementById('detail-room-name').textContent = r.room_name || '';
  const tag = document.getElementById('detail-status-tag');
  tag.textContent   = STATUS_LABELS[r.status] || r.status;
  tag.style.background  = sc + '22';
  tag.style.color       = sc;
  tag.style.border      = `1px solid ${sc}44`;
  const guestEl = document.getElementById('detail-guest');
  if (r.guest_name) {
    guestEl.textContent  = '👤 ' + r.guest_name;
    guestEl.style.display = 'block';
  } else {
    guestEl.style.display = 'none';
  }
  // TV tab
  const tvInfo = document.getElementById('tv-info');
  if (r.screen_name) {
    tvInfo.innerHTML = `
      <div style="display:flex;align-items:center;gap:12px;padding:10px;background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.1);border-radius:10px;">
        <i class="fas fa-tv" style="color:#ef4444;font-size:22px;"></i>
        <div><div style="font-size:14px;font-weight:700;color:#fff;">${escHtml(r.screen_name)}</div>
        <div style="font-size:11px;color:#64748b;font-family:monospace;">${escHtml(r.screen_code||'')}</div></div>
        <a href="/admin/screens" style="margin-right:auto;font-size:11px;color:#60a5fa;">تنظیمات ←</a>
      </div>`;
  } else {
    tvInfo.innerHTML = '<div style="text-align:center;padding:20px;color:#334155;font-size:12px;"><i class="fas fa-tv" style="font-size:32px;opacity:.2;display:block;margin-bottom:8px;"></i>هنوز تلویزیونی به این اتاق وصل نشده</div>';
  }
}

function fillInfoTab(r) {
  document.getElementById('inf-number').value  = r.room_number || '';
  document.getElementById('inf-name').value    = r.room_name   || '';
  document.getElementById('inf-floor').value   = r.floor       ?? '';
  document.getElementById('inf-type').value    = r.room_type   || '';
  document.getElementById('inf-status').value  = r.status      || 'available';
  document.getElementById('inf-pms-id').value  = r.pms_room_id || '';
  document.getElementById('inf-notes').value   = r.notes       || '';
  document.getElementById('room-save-status').textContent = '';
}

function fillGuestTab(r) {
  document.getElementById('ci-name').value = r.guest_name    || '';
  document.getElementById('ci-lang').value = r.guest_lang    || 'fa';
  document.getElementById('ci-in').value   = r.check_in_at  ? r.check_in_at.slice(0,16)  : '';
  document.getElementById('ci-out').value  = r.check_out_at ? r.check_out_at.slice(0,16) : '';
  document.getElementById('checkout-btn').style.display = r.status === 'occupied' ? '' : 'none';
  const cgCard = document.getElementById('current-guest-card');
  if (r.guest_name) {
    cgCard.style.display = 'block';
    document.getElementById('cg-name').textContent = r.guest_name;
    document.getElementById('cg-lang').textContent = r.guest_lang || 'fa';
    document.getElementById('cg-in').textContent   = r.check_in_at  ? r.check_in_at  : '—';
    document.getElementById('cg-out').textContent  = r.check_out_at ? r.check_out_at : '—';
  } else {
    cgCard.style.display = 'none';
  }
}

// ══ تب‌ها ═════════════════════════════════════════════════════════
function switchDTab(tab) {
  ['info','guest','msgs','tv'].forEach(t => {
    const el  = document.getElementById('dtab-' + t);
    const btn = document.getElementById('dtab-' + t + '-btn');
    if (el)  el.style.display  = t === tab ? '' : 'none';
    if (btn) { btn.classList.toggle('dtab-active', t === tab); btn.style.color = t === tab ? '#f87171' : '#475569'; }
  });
  if (tab === 'msgs') loadMessages(currentRoom?.id);
}

// ══ ذخیره اتاق ════════════════════════════════════════════════════
let rSaveTimer = null;
function debounceSaveRoom() {
  clearTimeout(rSaveTimer);
  document.getElementById('room-save-status').textContent = '...';
  rSaveTimer = setTimeout(saveRoom, 1000);
}

async function saveRoom() {
  if (!currentRoom) return;
  clearTimeout(rSaveTimer);
  const payload = {
    room_number: document.getElementById('inf-number').value.trim(),
    room_name:   document.getElementById('inf-name').value.trim(),
    floor:       document.getElementById('inf-floor').value !== '' ? parseInt(document.getElementById('inf-floor').value) : null,
    room_type:   document.getElementById('inf-type').value    || null,
    status:      document.getElementById('inf-status').value,
    pms_room_id: document.getElementById('inf-pms-id').value.trim() || null,
    notes:       document.getElementById('inf-notes').value.trim()  || null,
  };
  if (!payload.room_number) { showToast('شماره اتاق الزامی است', 'error'); return; }
  const statusEl = document.getElementById('room-save-status');
  statusEl.textContent = 'در حال ذخیره...';
  try {
    const r = await apiFetch(`/api/v1/iptv/rooms/${currentRoom.id}`, 'PUT', payload);
    currentRoom = r.data;
    Object.assign(currentRoom, payload);
    renderRoomHeader(currentRoom);
    statusEl.textContent = '✓ ذخیره شد'; statusEl.style.color = '#22c55e';
    updateRoomRow(currentRoom);
    setTimeout(() => { statusEl.textContent = ''; }, 3000);
  } catch(e) { statusEl.textContent = 'خطا!'; statusEl.style.color = '#ef4444'; showToast('خطا: ' + e.message, 'error'); }
}

// ══ ورود / خروج مهمان ════════════════════════════════════════════
async function doCheckin() {
  if (!currentRoom) return;
  const payload = {
    guest_name:   document.getElementById('ci-name').value.trim() || null,
    guest_lang:   document.getElementById('ci-lang').value,
    check_in_at:  document.getElementById('ci-in').value  || null,
    check_out_at: document.getElementById('ci-out').value || null,
    send_welcome: document.getElementById('ci-welcome').checked,
  };
  try {
    const r = await apiFetch(`/api/v1/iptv/rooms/${currentRoom.id}/checkin`, 'POST', payload);
    currentRoom = r.data;
    renderRoomHeader(currentRoom);
    fillGuestTab(currentRoom);
    updateRoomRow(currentRoom);
    showToast('ورود مهمان ثبت شد ✓', 'success');
  } catch(e) { showToast('خطا: ' + e.message, 'error'); }
}

async function doCheckout() {
  if (!currentRoom || !confirm(`خروج مهمان از اتاق ${currentRoom.room_number} ثبت شود؟`)) return;
  try {
    await apiFetch(`/api/v1/iptv/rooms/${currentRoom.id}/checkout`, 'POST', {});
    currentRoom.status = 'available'; currentRoom.guest_name = null;
    currentRoom.check_in_at = null; currentRoom.check_out_at = null;
    renderRoomHeader(currentRoom);
    fillGuestTab(currentRoom);
    fillInfoTab(currentRoom);
    updateRoomRow(currentRoom);
    showToast('خروج ثبت شد', 'success');
  } catch(e) { showToast('خطا: ' + e.message, 'error'); }
}

// ══ پیام‌ها ═══════════════════════════════════════════════════════
async function loadMessages(roomId) {
  if (!roomId) return;
  try {
    const r = await apiFetch(`/api/v1/iptv/rooms/${roomId}/messages`);
    renderMessages(r.data || []);
  } catch(_) {}
}

function renderMessages(msgs) {
  const el = document.getElementById('msgs-list');
  if (!msgs.length) {
    el.innerHTML = '<div style="text-align:center;padding:20px;color:#334155;font-size:12px;"><i class="fas fa-inbox" style="opacity:.2;font-size:24px;display:block;margin-bottom:6px;"></i>هنوز پیامی ندارد</div>';
    return;
  }
  const ICONS = {info:'fas fa-info-circle',welcome:'fas fa-hand-wave',urgent:'fas fa-exclamation-triangle',promo:'fas fa-tag',custom:'fas fa-comment'};
  el.innerHTML = msgs.map(m => {
    const tc = MSG_TYPES[m.msg_type]?.color || '#475569';
    const exp = m.expires_at ? new Date(m.expires_at) : null;
    const expired = exp && exp < new Date();
    return `<div class="msg-card" style="opacity:${expired||!m.is_active?'.4':'1'}">
      <div style="width:32px;height:32px;border-radius:8px;background:${tc}22;border:1px solid ${tc}33;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="${ICONS[m.msg_type]||'fas fa-comment'}" style="color:${tc};font-size:13px;"></i>
      </div>
      <div style="flex:1;min-width:0;">
        ${m.title ? `<div style="font-size:12px;font-weight:700;color:#fff;">${escHtml(m.title)}</div>` : ''}
        <div style="font-size:12px;color:#94a3b8;">${escHtml(m.body)}</div>
        <div style="font-size:10px;color:#334155;margin-top:2px;">${MSG_TYPES[m.msg_type]?.label||m.msg_type} · ${MSG_MODES[m.display_mode]||m.display_mode}${expired?' · <span style="color:#ef4444">منقضی</span>':!m.is_active?' · <span style="color:#f59e0b">غیرفعال</span>':''}</div>
      </div>
      <div style="display:flex;gap:4px;flex-shrink:0;">
        ${m.is_active&&!expired ? `<button onclick="deactivateMsg(${m.id})" style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:#fbbf24;width:26px;height:26px;border-radius:7px;cursor:pointer;font-size:10px;" title="غیرفعال کن"><i class="fas fa-eye-slash"></i></button>` : ''}
        <button onclick="deleteMsg(${m.id})" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;width:26px;height:26px;border-radius:7px;cursor:pointer;font-size:10px;"><i class="fas fa-trash"></i></button>
      </div>
    </div>`;
  }).join('');
}

async function sendMsg() {
  if (!currentRoom) return;
  const body = document.getElementById('msg-body').value.trim();
  if (!body) { showToast('متن پیام الزامی است', 'error'); return; }
  const hrs = parseInt(document.getElementById('msg-expires').value);
  const payload = {
    title:        document.getElementById('msg-title').value.trim() || null,
    body,
    msg_type:     document.getElementById('msg-type').value,
    display_mode: document.getElementById('msg-mode').value,
    expires_at:   hrs > 0 ? new Date(Date.now() + hrs*3600000).toISOString().slice(0,19).replace('T',' ') : null,
  };
  try {
    await apiFetch(`/api/v1/iptv/rooms/${currentRoom.id}/message`, 'POST', payload);
    document.getElementById('msg-body').value  = '';
    document.getElementById('msg-title').value = '';
    showToast('پیام ارسال شد ✓', 'success');
    loadMessages(currentRoom.id);
  } catch(e) { showToast('خطا: ' + e.message, 'error'); }
}

async function deactivateMsg(id) {
  try {
    await apiFetch(`/api/v1/iptv/room-messages/${id}/deactivate`, 'POST', {});
    showToast('پیام غیرفعال شد', 'success');
    loadMessages(currentRoom.id);
  } catch(e) { showToast('خطا: ' + e.message, 'error'); }
}

async function deleteMsg(id) {
  if (!confirm('پیام حذف شود؟')) return;
  try {
    await apiFetch(`/api/v1/iptv/room-messages/${id}`, 'DELETE');
    showToast('پیام حذف شد', 'success');
    loadMessages(currentRoom.id);
  } catch(e) { showToast('خطا: ' + e.message, 'error'); }
}

// ══ Broadcast ═════════════════════════════════════════════════════
async function doBroadcast() {
  const body = document.getElementById('bc-body').value.trim();
  if (!body) { showToast('متن پیام الزامی است', 'error'); return; }
  try {
    await apiFetch('/api/v1/iptv/rooms/broadcast', 'POST', {
      title:        document.getElementById('bc-title').value.trim() || null,
      body,
      msg_type:     document.getElementById('bc-type').value,
      display_mode: document.getElementById('bc-mode').value,
    });
    closeModal('broadcastModal');
    document.getElementById('bc-body').value  = '';
    document.getElementById('bc-title').value = '';
    showToast('پیام برای همه اتاق‌ها ارسال شد ✓', 'success');
  } catch(e) { showToast('خطا: ' + e.message, 'error'); }
}

// ══ ایجاد اتاق جدید ══════════════════════════════════════════════
function openCreateRoom() {
  ['rm-number','rm-name','rm-floor','rm-pms-id','rm-notes'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  document.getElementById('rm-type').value  = '';
  document.getElementById('rm-group').value = '';
  document.getElementById('roomModal-title').textContent = 'اتاق جدید';
  document.getElementById('roomModal').classList.remove('hidden');
  setTimeout(() => document.getElementById('rm-number').focus(), 80);
}

function openEditRoom() {
  if (!currentRoom) return;
  document.getElementById('rm-number').value = currentRoom.room_number || '';
  document.getElementById('rm-name').value   = currentRoom.room_name   || '';
  document.getElementById('rm-floor').value  = currentRoom.floor       ?? '';
  document.getElementById('rm-type').value   = currentRoom.room_type   || '';
  document.getElementById('rm-group').value  = currentRoom.group_id    || '';
  document.getElementById('rm-pms-id').value = currentRoom.pms_room_id || '';
  document.getElementById('rm-notes').value  = currentRoom.notes       || '';
  document.getElementById('roomModal-title').textContent = 'ویرایش اتاق';
  document.getElementById('roomModal').classList.remove('hidden');
}

async function saveNewRoom() {
  const num = document.getElementById('rm-number').value.trim();
  if (!num) { showToast('شماره اتاق الزامی است', 'error'); return; }
  const payload = {
    room_number: num,
    room_name:   document.getElementById('rm-name').value.trim()  || null,
    floor:       document.getElementById('rm-floor').value !== '' ? parseInt(document.getElementById('rm-floor').value) : null,
    room_type:   document.getElementById('rm-type').value  || null,
    group_id:    document.getElementById('rm-group').value || null,
    pms_room_id: document.getElementById('rm-pms-id').value.trim() || null,
    notes:       document.getElementById('rm-notes').value.trim()  || null,
  };
  const isEdit = document.getElementById('roomModal-title').textContent.includes('ویرایش') && currentRoom;
  try {
    if (isEdit) {
      await apiFetch(`/api/v1/iptv/rooms/${currentRoom.id}`, 'PUT', payload);
      showToast('اتاق بروز شد ✓', 'success');
    } else {
      await apiFetch('/api/v1/iptv/rooms', 'POST', payload);
      showToast('اتاق ایجاد شد ✓', 'success');
    }
    closeModal('roomModal');
    setTimeout(() => location.reload(), 700);
  } catch(e) { showToast('خطا: ' + e.message, 'error'); }
}

async function deleteRoom() {
  if (!currentRoom || !confirm(`اتاق ${currentRoom.room_number} حذف شود؟`)) return;
  try {
    await apiFetch(`/api/v1/iptv/rooms/${currentRoom.id}`, 'DELETE');
    showToast('اتاق حذف شد', 'success');
    location.reload();
  } catch(e) { showToast('خطا: ' + e.message, 'error'); }
}

// ══ PMS ══════════════════════════════════════════════════════════
function openPmsModal() {
  renderPmsKeys();
  document.getElementById('pmsModal').classList.remove('hidden');
}

function renderPmsKeys() {
  const el = document.getElementById('pms-keys-list');
  if (!pmsIntegrations.length) {
    el.innerHTML = '<div style="text-align:center;color:#334155;font-size:12px;padding:10px;">هنوز کلیدی ایجاد نشده</div>';
    return;
  }
  el.innerHTML = pmsIntegrations.map(p => `
    <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:10px;margin-bottom:6px;">
      <div style="flex:1;min-width:0;">
        <div style="font-size:12px;font-weight:700;color:#fff;">${escHtml(p.name)} <span style="font-size:10px;color:#6366f1;background:rgba(99,102,241,.1);padding:1px 8px;border-radius:10px;margin-right:6px;">${p.pms_type||'custom'}</span></div>
        <div style="font-size:11px;font-family:monospace;color:#64748b;margin-top:3px;overflow:hidden;text-overflow:ellipsis;">${escHtml(p.api_key)}</div>
        ${p.last_used_at ? `<div style="font-size:10px;color:#334155;">آخرین استفاده: ${p.last_used_at}</div>` : ''}
      </div>
      <button onclick="copyKey('${escHtml(p.api_key)}')" style="background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.3);color:#818cf8;padding:6px 10px;border-radius:7px;cursor:pointer;font-size:11px;font-family:inherit;flex-shrink:0;">
        <i class="fas fa-copy text-xs ml-1"></i>کپی
      </button>
      <button onclick="deletePmsKey(${p.id})" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;width:28px;height:28px;border-radius:7px;cursor:pointer;font-size:11px;flex-shrink:0;">
        <i class="fas fa-trash"></i>
      </button>
    </div>
  `).join('');
}

async function createPmsKey() {
  const name = document.getElementById('pms-name-input').value.trim();
  if (!name) { showToast('نام سیستم الزامی است', 'error'); return; }
  try {
    const r = await apiFetch('/api/v1/iptv/pms', 'POST', {
      name, pms_type: document.getElementById('pms-type-input').value
    });
    pmsIntegrations.unshift({ id: r.data.id, name, api_key: r.data.api_key, pms_type: document.getElementById('pms-type-input').value, last_used_at: null });
    renderPmsKeys();
    document.getElementById('pms-name-input').value = '';
    showToast('کلید API ایجاد شد ✓', 'success');
    await navigator.clipboard.writeText(r.data.api_key);
    showToast('کلید کپی شد 📋', 'success');
  } catch(e) { showToast('خطا: ' + e.message, 'error'); }
}

async function deletePmsKey(id) {
  if (!confirm('این کلید API حذف شود؟ سیستم‌های متصل دیگر کار نخواهند کرد.')) return;
  try {
    await apiFetch(`/api/v1/iptv/pms/${id}`, 'DELETE');
    pmsIntegrations = pmsIntegrations.filter(p => p.id != id);
    renderPmsKeys();
    showToast('کلید حذف شد', 'success');
  } catch(e) { showToast('خطا: ' + e.message, 'error'); }
}

async function copyKey(key) {
  await navigator.clipboard.writeText(key);
  showToast('کلید API کپی شد 📋', 'success');
}

// ══ فیلتر اتاق‌ها ════════════════════════════════════════════════
function filterRooms(q) {
  const sq = (q ?? document.getElementById('room-search').value).toLowerCase();
  const sf = document.getElementById('room-status-filter').value;
  document.querySelectorAll('.room-row').forEach(row => {
    const matchQ = !sq || row.dataset.search.includes(sq);
    const matchS = !sf  || row.dataset.status === sf;
    row.style.display = matchQ && matchS ? '' : 'none';
  });
  document.querySelectorAll('.floor-group').forEach(g => {
    const visible = [...g.querySelectorAll('.room-row')].some(r => r.style.display !== 'none');
    g.style.display = visible ? '' : 'none';
  });
}

function updateRoomRow(r) {
  const row = document.querySelector(`.room-row[data-id="${r.id}"]`);
  if (!row) return;
  row.dataset.status = r.status;
  row.dataset.search = (r.room_number + ' ' + (r.room_name||'') + ' ' + (r.guest_name||'')).toLowerCase();
  const dot = row.querySelector('span:first-child');
  const sc  = STATUS_COLORS[r.status] || '#475569';
  if (dot) { dot.style.background = sc; dot.style.boxShadow = `0 0 6px ${sc}66`; }
  const txt = row.querySelectorAll('span')[2];
  if (txt) txt.textContent = r.guest_name || r.room_name || '';
}

// ══ Helpers ════════════════════════════════════════════════════════
function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
async function apiFetch(url, method='GET', body=null) {
  const opts = { method, headers:{'Content-Type':'application/json','Accept':'application/json'}, credentials:'same-origin' };
  if (body) opts.body = JSON.stringify(body);
  const r = await fetch(url, opts);
  let d = {};
  try { d = await r.json(); } catch(_) {}
  if (!r.ok) throw new Error(d.message || `خطا (${r.status})`);
  return d;
}
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
function openBroadcastModal() { document.getElementById('broadcastModal').classList.remove('hidden'); }
function showToast(msg, type='success') {
  const t = document.getElementById('toast');
  t.className = `toast toast-${type}`;
  t.innerHTML = `<i class="fas fa-${type==='success'?'check':'exclamation'}-circle"></i> ${msg}`;
  t.classList.remove('hidden');
  setTimeout(() => t.classList.add('hidden'), 3500);
}
</script>

<?php include VIEWS_PATH . '/partials/layout_footer.php'; ?>
