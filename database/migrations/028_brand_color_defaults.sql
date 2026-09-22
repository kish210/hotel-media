-- ════════════════════════════════════════════════════════════════════
-- رنگ پیش‌فرض برند — نارنجی قدیمی به آبی سما
-- ════════════════════════════════════════════════════════════════════
--
-- رابط کاربری به آبی برند (#1a7ac4، برگرفته از لوگوی سماع رایانه کیش)
-- منتقل شد، ولی دو ستون هنوز پیش‌فرض نارنجی #f97316 داشتند. یعنی هر
-- آیتم منوی جدید و هر پیام جدیدی که اپراتور می‌ساخت، نارنجی بیرون
-- می‌آمد و بین بقیه‌ی رابط وصله می‌شد.
--
-- مقدارهای موجود که اپراتور عمدا انتخاب کرده دست نمی‌خورند؛ فقط
-- آن‌هایی که هنوز دقیقا همان نارنجیِ پیش‌فرض قدیمی‌اند منتقل می‌شوند،
-- چون آن‌ها انتخاب نبوده‌اند — فقط پیش‌فرض بوده‌اند.
--
-- این مهاجرت idempotent است.

-- ── آیتم‌های منوی IPTV ────────────────────────────────────────────────
SET @t := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'iptv_menu_items'
    AND COLUMN_NAME  = 'color'
);
SET @sql := IF(@t = 1,
  'ALTER TABLE iptv_menu_items
     MODIFY COLUMN color VARCHAR(20) NOT NULL DEFAULT "#1a7ac4"',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE iptv_menu_items SET color = '#1a7ac4' WHERE color = '#f97316';

-- ── پیام‌های صفحه ─────────────────────────────────────────────────────
SET @t := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'screen_messages'
    AND COLUMN_NAME  = 'accent_color'
);
SET @sql := IF(@t = 1,
  'ALTER TABLE screen_messages
     MODIFY COLUMN accent_color VARCHAR(20) NOT NULL DEFAULT "#1a7ac4"',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE screen_messages SET accent_color = '#1a7ac4' WHERE accent_color = '#f97316';
