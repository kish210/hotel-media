-- ── 055: تم ظاهری برای منوی IPTV و گروه‌های صفحه (IPTV و signage) ────────
-- مقدار خالی = ارث‌بری. اولویت در پلیر: تم منو، بعد تم گروه صفحه، بعد تم
-- گروهِ منو، و در آخر classic. ستون متنی کوتاه است نه ENUM: افزودن تم تازه
-- migration نمی‌خواهد و مقدار ناشناخته در پلیر به classic برمی‌گردد.
-- تم‌ها: public/assets/css/tv-themes.css (IPTV) و signage-themes.css.
SET @db = DATABASE();

SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'iptv_menus' AND COLUMN_NAME = 'theme');
SET @q = IF(@has = 0,
    "ALTER TABLE iptv_menus ADD COLUMN theme VARCHAR(20) NOT NULL DEFAULT ''",
    'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'screen_groups' AND COLUMN_NAME = 'theme');
SET @q = IF(@has = 0,
    "ALTER TABLE screen_groups ADD COLUMN theme VARCHAR(20) NOT NULL DEFAULT ''",
    'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
