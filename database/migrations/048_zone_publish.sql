-- ═══════════════════════════════════════════════════════════════════
-- 048 — انتشار پلی‌لیست روی یک Zone (فاز ۲ بازسازی)
-- ═══════════════════════════════════════════════════════════════════
-- وضعیت پیش از این تغییر، از روی خودِ اسکیما:
--
--   • `screens.venue_id` از قبل وجود داشت، ولی هیچ صفحه‌ای در پنل آن
--     را پر نمی‌کرد و هیچ کوئری‌ای آن را نمی‌خواند. یعنی مفهوم Zone در
--     دیتابیس بود و در محصول نبود.
--   • `schedules` فقط `screen_id` داشت. پس «این پلی‌لیست روی همهٔ
--     صفحه‌های لابی» قابل گفتن نبود؛ اپراتور باید برای هر صفحه یک
--     برنامهٔ جدا می‌ساخت و بعد یادش می‌رفت یکی را به‌روز کند.
--
-- جدول Zone تازه‌ای ساخته نمی‌شود: `venues` همین حالا محل‌های درون
-- هتل است (`kind`, `floor`, ظرفیت، ساعت کار) و پنج ردیف واقعی دارد.
-- ساختن یک جدول موازی یعنی دو جای حقیقت برای یک چیز.
--
-- ‏`screen_id IS NULL AND venue_id IS NULL AND group_id IS NULL` همان
-- برنامهٔ همگانی قبلی است، پس رفتار موجود عوض نمی‌شود.
-- ═══════════════════════════════════════════════════════════════════

ALTER TABLE `schedules`
  ADD COLUMN IF NOT EXISTS `venue_id` INT UNSIGNED DEFAULT NULL AFTER `screen_id`,
  ADD COLUMN IF NOT EXISTS `group_id` INT UNSIGNED DEFAULT NULL AFTER `venue_id`;

-- کوئری انتخاب پلی‌لیست در هر heartbeat اجرا می‌شود؛ بی ایندکس،
-- هر تلویزیون هر چند ثانیه یک پیمایش کامل جدول می‌خواهد
ALTER TABLE `schedules` ADD INDEX IF NOT EXISTS `idx_sched_venue` (`venue_id`, `is_active`);
ALTER TABLE `schedules` ADD INDEX IF NOT EXISTS `idx_sched_group` (`group_id`, `is_active`);
ALTER TABLE `screens`   ADD INDEX IF NOT EXISTS `idx_screens_venue` (`tenant_id`, `venue_id`);
