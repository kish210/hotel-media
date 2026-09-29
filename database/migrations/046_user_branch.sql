-- ============================================================
--  ۰۴۶ — شعبه‌ی کاربر (TODO ۵.۱۴)
--
--  کاربری که location_id دارد فقط صفحه‌نمایش، اتاق، گروه و دستگاه
--  همان شعبه را در پنل می‌بیند و ویرایش می‌کند. خالی = همه‌ی شعبه‌ها
--  (مدیر زنجیره)، که می‌تواند از بالای پنل یک شعبه را برای دیدن
--  انتخاب کند.
-- ============================================================
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='location_id')=0,
  'ALTER TABLE users ADD COLUMN location_id INT UNSIGNED DEFAULT NULL COMMENT ''شعبه؛ خالی = همه'' AFTER role, ADD KEY idx_user_location (location_id)',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
