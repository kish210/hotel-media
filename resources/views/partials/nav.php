<?php
/**
 * ناوبری پنل — معماری اطلاعات محصول
 *
 * چرا جدا از layout.php: منو بیش از ۲۰۰ خط از آن فایل را گرفته بود و هر
 * تغییر ساختاری، کل قالب را در معرض خطا می‌گذاشت.
 *
 * دو قاعده که چیدمان زیر از آن‌ها درآمده:
 *
 *  ۱. مفهوم فنی در منوی اصلی نمی‌آید. «TVHeadend»، «ترنسکد»، «درایور»
 *     برای مدیر هتل معنا ندارند — این‌ها زیر «زیرساخت» در پایین و فقط
 *     برای نقش‌هایی که واقعا به آن‌ها دسترسی دارند دیده می‌شوند.
 *
 *  ۲. تلویزیون اتاق و تابلوی عمومی دو تجربه‌ی جدا هستند، هرچند زیرساخت
 *     مشترک دارند: تابلو کار مارکتینگ است، تلویزیون اتاق کار IT هتل.
 *
 * هر لینک اینجا به یک روت واقعا موجود اشاره می‌کند. آیتم‌هایی که در
 * نقشه‌ی محصول هستند ولی صفحه‌شان ساخته نشده (Zone، گروه کانال، قالب‌ها)
 * عمدا اضافه نشده‌اند تا لینک مرده نسازیم؛ در فاز خودشان می‌آیند.
 *
 * @var array $authUser
 */
$role    = $authUser['role'] ?? '';
$isSuper = $role === 'super_admin';
/* ترنسکد و منابع پخش را خودِ کنترلرشان برای admin هم باز می‌گذارند،
   پس گیت منو هم همان است — وگرنه دسترسی موجود را از admin می‌گرفتیم. */
$isTech  = in_array($role, ['super_admin', 'admin'], true);
?>

<!-- ── اصلی ── -->
<div class="sidebar-section"><?= __('nav.section.main') ?></div>
<a href="/admin/dashboard" class="sidebar-link <?= isActive('/admin/dashboard') ?>">
  <span class="icon"><i class="fas fa-gauge"></i></span> <?= __('nav.dashboard') ?>
</a>
<a href="/admin/screens/monitor" class="sidebar-link <?= isActive('/admin/screens/monitor') ?>">
  <span class="icon"><i class="fas fa-display" style="color:#4ade80;"></i></span> مانیتورینگ
</a>

<!-- ══ تلویزیون و صفحه‌ها ══ -->
<div class="sidebar-section">
  <i class="fas fa-tv" style="margin-left:5px;opacity:.6"></i> تلویزیون و صفحه‌ها
</div>
<a href="/admin/screens" class="sidebar-link <?= isActive('/admin/screens') && !isActive('/admin/screens/monitor') ? 'active' : '' ?>">
  <span class="icon"><i class="fas fa-table-list"></i></span> همه‌ی صفحه‌ها
</a>
<?php /* همان فهرست با تب از پیش انتخاب‌شده — تب‌ها از قبل در صفحه بودند
         و اینجا فقط میان‌بر می‌شوند. */ ?>
<a href="/admin/screens?tab=iptv" class="sidebar-link">
  <span class="icon"><i class="fas fa-door-open"></i></span> تلویزیون اتاق‌ها
</a>
<a href="/admin/screens?tab=signage" class="sidebar-link">
  <span class="icon"><i class="fas fa-panorama"></i></span> صفحه‌های عمومی
</a>
<?php /* محل‌ها: Zone واقعی محصول. برای همه‌ی نقش‌ها باز است چون انتشار
         پلی‌لیست روی یک محل، کارِ روزمره‌ی اپراتور است نه کار فنی. */ ?>
<a href="/admin/zones" class="sidebar-link <?= isActive('/admin/zones') ?>">
  <span class="icon"><i class="fas fa-location-dot"></i></span> محل‌ها (Zone)
</a>
<?php if ($isSuper): ?>
<a href="/admin/property/groups" class="sidebar-link <?= isActive('/admin/property/groups') ?>">
  <span class="icon"><i class="fas fa-object-group"></i></span> گروه‌های صفحه
</a>
<?php endif; ?>
<?php if (modOn('iptv')): ?>
<a href="/admin/devices" class="sidebar-link <?= isActive('/admin/devices') ?>">
  <span class="icon"><i class="fas fa-satellite-dish"></i></span> ثبت و فرمان دستگاه
</a>
<?php endif; ?>

<!-- ══ محتوا — مشترک بین تابلو و اتاق ══ -->
<div class="sidebar-section">
  <i class="fas fa-photo-film" style="margin-left:5px;opacity:.6"></i> محتوا
</div>
<a href="/admin/media" class="sidebar-link <?= isActive('/admin/media') ?>">
  <span class="icon"><i class="fas fa-folder-open"></i></span> کتابخانه‌ی رسانه
</a>
<a href="/admin/media?type=image" class="sidebar-link">
  <span class="icon"><i class="fas fa-image"></i></span> تصاویر
</a>
<a href="/admin/media?type=video" class="sidebar-link">
  <span class="icon"><i class="fas fa-film"></i></span> ویدیوها
</a>
<a href="/admin/playlists" class="sidebar-link <?= isActive('/admin/playlists') ?>">
  <span class="icon"><i class="fas fa-list"></i></span> پلی‌لیست‌ها
</a>
<a href="/admin/schedules" class="sidebar-link <?= isActive('/admin/schedules') ?>">
  <span class="icon"><i class="fas fa-calendar"></i></span> زمان‌بندی
</a>
<?php /* به `/admin/messages` اشاره می‌کند، نه `/admin/campaigns`.
         آن صفحه روی جدول `campaigns` بود که هیچ صفحه‌ای نمی‌خواندش و
         دکمهٔ «پخش»اش فقط پیام موفقیت فلش می‌کرد. این یکی واقعا در هر
         heartbeat به تلویزیون می‌رسد. */ ?>
<a href="/admin/messages" class="sidebar-link <?= isActive('/admin/messages') ?>">
  <span class="icon"><i class="fas fa-bullhorn"></i></span> تبلیغات و اعلان‌ها
</a>

<!-- ══ تلویزیون زنده ══ -->
<?php if (modOn('iptv')): ?>
<div class="sidebar-section">
  <i class="fas fa-tower-broadcast" style="margin-left:5px;opacity:.6"></i> تلویزیون زنده
</div>
<a href="/admin/iptv" class="sidebar-link <?= isActive('/admin/iptv')
      && !isActive('/admin/iptv/menus') && !isActive('/admin/iptv/rooms')
      && !isActive('/admin/iptv/tvheadend') && !isActive('/admin/iptv/dvr') ? 'active' : '' ?>">
  <span class="icon"><i class="fas fa-list-ol"></i></span> کانال‌ها
</a>
<a href="/admin/epg" class="sidebar-link <?= isActive('/admin/epg') ?>">
  <span class="icon"><i class="fas fa-calendar-days"></i></span> راهنمای برنامه‌ها
</a>
<a href="/admin/iptv/dvr" class="sidebar-link <?= isActive('/admin/iptv/dvr') ?>">
  <span class="icon"><i class="fas fa-record-vinyl"></i></span> ضبط و بازگشت به عقب
</a>
<a href="/admin/cameras" class="sidebar-link <?= isActive('/admin/cameras') ?>">
  <span class="icon"><i class="fas fa-video"></i></span> دوربین مداربسته
</a>
<?php endif; ?>

<!-- ══ فیلم و سریال ══ -->
<?php if (modOn('vod')): ?>
<div class="sidebar-section">
  <i class="fas fa-clapperboard" style="margin-left:5px;opacity:.6"></i> فیلم و سریال
</div>
<a href="/admin/vod" class="sidebar-link <?= isActive('/admin/vod') ?>">
  <span class="icon"><i class="fas fa-film"></i></span> کتابخانه‌ی فیلم
</a>
<?php endif; ?>

<!-- ══ تجربه‌ی مهمان ══ -->
<?php if (modOn('iptv')): ?>
<div class="sidebar-section">
  <i class="fas fa-concierge-bell" style="margin-left:5px;opacity:.6"></i> تجربه‌ی مهمان
</div>
<a href="/admin/iptv/menus" class="sidebar-link <?= isActive('/admin/iptv/menus') ?>">
  <span class="icon"><i class="fas fa-bars"></i></span> منوی تلویزیون اتاق
</a>
<a href="/admin/iptv/rooms" class="sidebar-link <?= isActive('/admin/iptv/rooms') ?>">
  <span class="icon"><i class="fas fa-bed"></i></span> اتاق‌ها و مهمان‌ها
</a>
<a href="/admin/guest-services" class="sidebar-link <?= isActive('/admin/guest-services') ?>">
  <span class="icon"><i class="fas fa-bell-concierge"></i></span> خدمات مهمان
</a>
<a href="/admin/reservations" class="sidebar-link <?= isActive('/admin/reservations') ?>">
  <span class="icon"><i class="fas fa-calendar-check"></i></span> رزرو رستوران و امکانات
</a>
<a href="/admin/offers" class="sidebar-link <?= isActive('/admin/offers') ?>">
  <span class="icon"><i class="fas fa-ticket"></i></span> تخفیف کسب‌وکارهای اطراف
</a>
<a href="/admin/messages" class="sidebar-link <?= isActive('/admin/messages') ?>">
  <span class="icon"><i class="fas fa-message"></i></span> پیام به اتاق
</a>
<?php endif; ?>

<!-- ══ تابلو ══ -->
<div class="sidebar-section">
  <i class="fas fa-display" style="margin-left:5px;opacity:.6"></i> تابلو
</div>
<a href="/admin/layouts" class="sidebar-link <?= isActive('/admin/layouts') ?>">
  <span class="icon"><i class="fas fa-table-cells-large"></i></span> چیدمان‌ها
</a>
<?php if (modOn('menu')): ?>
<a href="/admin/modules/menu" class="sidebar-link <?= isActive('/admin/modules/menu') ?>">
  <span class="icon"><i class="fas fa-utensils"></i></span> منوی رستوران
</a>
<?php endif; ?>
<?php if (modOn('hotel')): ?>
<a href="/admin/modules/hotel" class="sidebar-link <?= isActive('/admin/modules/hotel') ?>">
  <span class="icon"><i class="fas fa-circle-info"></i></span> اطلاعات و رویدادها
</a>
<a href="/admin/content" class="sidebar-link <?= isActive('/admin/content') ?>">
  <span class="icon"><i class="fas fa-book-open"></i></span> خبر، قرآن، کتاب، دفترچه
</a>
<?php endif; ?>
<a href="/admin/monitor3d" class="sidebar-link <?= isActive('/admin/monitor3d') ?>">
  <span class="icon"><i class="fas fa-cube"></i></span> مانیتور سه‌بعدی
</a>

<!-- ── مدیریت ── -->
<div class="sidebar-section"><?= __('nav.section.admin') ?></div>
<a href="/admin/users" class="sidebar-link <?= isActive('/admin/users') ?>">
  <span class="icon"><i class="fas fa-users"></i></span> کاربران
</a>
<a href="/admin/reports" class="sidebar-link <?= isActive('/admin/reports') ?>">
  <span class="icon"><i class="fas fa-chart-bar"></i></span> گزارش‌ها
</a>
<?php /* ساختار هتل یک‌بار موقع راه‌اندازی تعریف می‌شود، نه هر روز. */ ?>
<?php if ($isSuper): ?>
<a href="/admin/property/rooms" class="sidebar-link <?= isActive('/admin/property/rooms') ?>">
  <span class="icon"><i class="fas fa-door-closed"></i></span> تعریف اتاق‌ها
</a>
<a href="/admin/property/locations" class="sidebar-link <?= isActive('/admin/property/locations') ?>">
  <span class="icon"><i class="fas fa-building"></i></span> تعریف شعبه‌ها
</a>
<?php endif; ?>
<a href="/admin/modules" class="sidebar-link <?= isActive('/admin/modules') ?>">
  <span class="icon"><i class="fas fa-puzzle-piece"></i></span>
  ماژول‌ها
  <?php if (count($GLOBALS['_activeModules'] ?? []) > 0): ?>
    <span class="mod-badge"><?= count($GLOBALS['_activeModules']) ?></span>
  <?php endif; ?>
</a>
<a href="/admin/settings" class="sidebar-link <?= isActive('/admin/settings') ?>">
  <span class="icon"><i class="fas fa-gear"></i></span> تنظیمات
</a>
<a href="/admin/help" class="sidebar-link <?= isActive('/admin/help') ?>">
  <span class="icon"><i class="fas fa-circle-question" style="color:#818cf8;"></i></span> راهنما
</a>
<a href="/docs" class="sidebar-link" target="_blank">
  <span class="icon"><i class="fas fa-book" style="color:#60a5fa;"></i></span> مستندات
</a>

<!-- ══ زیرساخت — مفاهیم فنی، پایین فهرست و فقط برای نقش فنی ══ -->
<?php if ($isTech): ?>
<div class="sidebar-section">
  <i class="fas fa-server" style="margin-left:5px;opacity:.6"></i> زیرساخت
</div>
<?php if (modOn('iptv')): ?>
<a href="/admin/iptv/tvheadend" class="sidebar-link <?= isActive('/admin/iptv/tvheadend') ?>">
  <span class="icon"><i class="fas fa-tower-cell"></i></span> منابع پخش زنده
</a>
<?php endif; ?>
<?php if (modOn('vod')): ?>
<a href="/admin/transcoder" class="sidebar-link <?= isActive('/admin/transcoder') ?>">
  <span class="icon"><i class="fas fa-wand-magic-sparkles"></i></span> پردازش رسانه
</a>
<?php endif; ?>
<a href="/admin/app" class="sidebar-link <?= isActive('/admin/app') ?>">
  <span class="icon"><i class="fab fa-android" style="color:#4ade80;"></i></span> اپ Android
</a>
<?php endif; ?>

<?php if ($isSuper): ?>
<a href="/admin/system/update" class="sidebar-link <?= isActive('/admin/system/update') ?>">
  <span class="icon"><i class="fas fa-cloud-arrow-down" style="color:#4098db;"></i></span>
  به‌روزرسانی سیستم
</a>
<a href="/admin/system/drivers" class="sidebar-link <?= isActive('/admin/system/drivers') ?>">
  <span class="icon"><i class="fas fa-microchip" style="color:#f59e0b;"></i></span> درایورها
</a>
<?php /* دو صفحه‌ی عمدا جدا: این «الان چه خراب است» را می‌گوید و
         لاگ «چه اتفاقی افتاده بود» را. قاطی‌کردنشان یعنی برای دیدن
         وضعیت سرویس باید لای هزار خط لاگ گشت. */ ?>
<a href="/admin/system/diagnostics" class="sidebar-link <?= isActive('/admin/system/diagnostics') ?>">
  <span class="icon"><i class="fas fa-stethoscope" style="color:#22c55e;"></i></span> وضعیت سرور و شبکه
</a>
<a href="/admin/system/logs" class="sidebar-link <?= isActive('/admin/system/logs') ?>">
  <span class="icon"><i class="fas fa-bug" style="color:#f59e0b;"></i></span> لاگ و عیب‌یابی
</a>
<?php endif; ?>

<!-- فضای پایین -->
<div style="height:24px;"></div>
