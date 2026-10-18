-- ── 057: زیرنویس متحرک + تیزر شروع ─────────────────────────────────────
--
-- ۱) زیرنویس: `screen_messages` از قبل هدف‌گیری گروه/صفحه و زمان‌بندی دارد،
--    ولی سبکِ «نوار متحرک پایین صفحه» نداشت. به‌جای جدول تازه، یک سبک تازه
--    اضافه می‌شود تا زمان‌بندی، هدف و چندزبانه بودن همان‌جا بماند.
--    هر دو پلیر (تابلو و اتاق) از همین جدول می‌خوانند، پس زیرنویس برای هر
--    دو بخش با یک فرم ساخته می‌شود.
--
-- ۲) تیزر: چند ویدیو/عکس که تلویزیون **هنگام روشن‌شدن** یک‌بار پخش می‌کند و
--    بعد سراغ محتوای عادی می‌رود (پلی‌لیست تابلو یا منوی اتاق). جدول جدا
--    است نه پلی‌لیست، چون پلی‌لیست حلقه‌ای و زمان‌بندی‌شده است و تیزر
--    یک‌بار در شروع اجرا می‌شود.

SET @db = DATABASE();

-- ۱) سبک «ticker»
SET @col = (SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'screen_messages' AND COLUMN_NAME = 'style');
SET @q = IF(@col IS NOT NULL AND LOCATE('ticker', @col) = 0,
    "ALTER TABLE screen_messages MODIFY COLUMN `style` ENUM('overlay','fullscreen','popup','banner','ticker') NOT NULL DEFAULT 'overlay'",
    'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- سرعت حرکت زیرنویس (ثانیه برای یک دور کامل). فقط برای سبک ticker معنا دارد.
SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'screen_messages' AND COLUMN_NAME = 'ticker_speed');
SET @q = IF(@has = 0,
    "ALTER TABLE screen_messages ADD COLUMN ticker_speed SMALLINT UNSIGNED NOT NULL DEFAULT 40",
    'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ۲) تیزر شروع
CREATE TABLE IF NOT EXISTS `tv_intro_items` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   INT UNSIGNED NOT NULL DEFAULT 1,
    `name`        VARCHAR(200) NOT NULL,
    `media_id`    INT UNSIGNED DEFAULT NULL COMMENT 'عکس یا ویدیو از کتابخانه',
    `url`         VARCHAR(1000) DEFAULT NULL COMMENT 'به‌جای رسانه: آدرس مستقیم',
    `duration`    SMALLINT UNSIGNED NOT NULL DEFAULT 8 COMMENT 'ثانیه؛ ویدیو تا پایان خودش',
    -- هدف: همان قرارداد screen_messages تا اپراتور دو مدل جدا یاد نگیرد
    `target`      ENUM('all','screen','group') NOT NULL DEFAULT 'all',
    `target_ids`  JSON DEFAULT NULL,
    -- کدام بخش: تابلو، اتاق، یا هر دو
    `scope`       ENUM('both','signage','iptv') NOT NULL DEFAULT 'both',
    `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_active` (`tenant_id`, `is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
