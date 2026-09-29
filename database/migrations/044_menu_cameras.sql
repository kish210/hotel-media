-- ============================================================
--  ۰۴۴ — کاشی «دوربین‌های هتل» روی منوی تلویزیون اتاق (TODO ۵.۷)
--
--  صفحه‌ی /tv/guest/{code}/cameras حالا هست ولی نوع منویی برایش نبود،
--  پس اپراتور نمی‌توانست کاشی‌اش را روی منو بگذارد.
-- ============================================================

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_menu_items' AND COLUMN_NAME='type' AND COLUMN_TYPE LIKE '%''cameras''%')=0,
  (SELECT CONCAT('ALTER TABLE iptv_menu_items MODIFY COLUMN type ',
                 REPLACE(COLUMN_TYPE, ')', ',''cameras'')'),
                 ' NOT NULL DEFAULT ''live''')
     FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_menu_items' AND COLUMN_NAME='type'),
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
