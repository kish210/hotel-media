-- ════════════════════════════════════════════════════════════════════
-- نوع آیتم منو: اتصال دستگاه مهمان
-- ════════════════════════════════════════════════════════════════════
--
-- مهمان می‌خواهد موبایل یا کنسول بازی‌اش را به تلویزیون وصل کند. در
-- اتاق هتل منوی خود تلویزیون قفل است (که درست هم هست — مهمان نباید
-- به تنظیمات برسد)، پس تنها راه باقی‌مانده یک آیتم در همین پورتال است.
--
-- ستون type یک ENUM است و بدون این مهاجرت، مقدار 'input' سر درج رد
-- می‌شد و اپراتور نمی‌توانست چنین آیتمی بسازد.
--
-- این مهاجرت idempotent است: اگر 'input' از قبل در فهرست باشد ALTER
-- اجرا نمی‌شود.

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'iptv_menu_items'
    AND COLUMN_NAME  = 'type'
    AND COLUMN_TYPE LIKE '%''input''%'
);

SET @sql := IF(@has = 0,
  "ALTER TABLE iptv_menu_items
     MODIFY COLUMN type ENUM(
       'live','vod','news','info','weather','fids','hotel','corporate',
       'retail','url','custom','radio','quran','book','directory',
       'folio','services','epg','input'
     ) NOT NULL DEFAULT 'live'",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
