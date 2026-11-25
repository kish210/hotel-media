-- ═══════════════════════════════════════════════════════════════════
-- 060 — تله‌تکست و مهاجرت دادهٔ سیستم قدیمی (VINAK / mms)
-- ═══════════════════════════════════════════════════════════════════
-- همهٔ امکانات این نسخه باز است؛ هیچ کلید، فعال‌سازی یا سقفی لازم
-- نیست. تنها چیزی که از سیستم قدیمی می‌آید داده است، نه قفل.
--
-- روادار است: اجرای دوباره جدول تکراری نمی‌سازد و ستون موجود را
-- دست نمی‌زند.
-- ═══════════════════════════════════════════════════════════════════

-- ── تله‌تکست ────────────────────────────────────────────────────────
-- صفحه‌ها عمدا «متن ۴۰×۲۳» ذخیره می‌شوند، نه HTML: تلویزیون‌های ۲۰۱۳
-- فونت و چیدمان HTML را جور دیگری رندر می‌کنند و متن ساده روی همه
-- یکسان است. همان محدودیتی که سیستم قدیمی داشت (۴۰ کاراکتر در خط).
CREATE TABLE IF NOT EXISTS `teletext_pages` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED NOT NULL DEFAULT 1,
  `page_no`     SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  `lang`        VARCHAR(5)   NOT NULL DEFAULT 'fa' COMMENT 'fa|en|ar',
  `title`       VARCHAR(120) DEFAULT NULL,
  `body`        TEXT         NOT NULL,
  `color`       VARCHAR(7)   DEFAULT NULL,
  `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teletext_page` (`tenant_id`, `page_no`, `lang`),
  KEY `idx_teletext_sort` (`tenant_id`, `is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── تنظیمات وارد‌شده از سیستم قدیمی ────────────────────────────────
-- کلید/مقدار، نه ستون‌های ثابت: configs.json قدیمی ۱۳ بخش و ده‌ها
-- کلید داشت و هر نسخه چند تای دیگر اضافه می‌کرد. ستون‌دار کردنش یعنی
-- با هر نسخهٔ سیستمی یک مهاجرت تازه.
CREATE TABLE IF NOT EXISTS `legacy_settings` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED NOT NULL DEFAULT 1,
  `skey`       VARCHAR(120) NOT NULL,
  `sval`       TEXT         DEFAULT NULL,
  `source`     VARCHAR(40)  NOT NULL DEFAULT 'legacy',
  `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_legacy_setting` (`tenant_id`, `skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── سابقهٔ مهاجرت ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `legacy_import_runs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED NOT NULL DEFAULT 1,
  `source`     VARCHAR(300) NOT NULL,
  `mode`       VARCHAR(30)  NOT NULL DEFAULT 'merge' COMMENT 'merge|replace|dry',
  `channels`   INT UNSIGNED NOT NULL DEFAULT 0,
  `rooms`      INT UNSIGNED NOT NULL DEFAULT 0,
  `vod`        INT UNSIGNED NOT NULL DEFAULT 0,
  `logos`      INT UNSIGNED NOT NULL DEFAULT 0,
  `teletext`   INT UNSIGNED NOT NULL DEFAULT 0,
  `summary`    TEXT         DEFAULT NULL COMMENT 'JSON گزارش کامل',
  `ok`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_legacy_runs` (`tenant_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ستون‌های ردیابی ─────────────────────────────────────────────────
-- legacy_ref مقدار اصلی سیستم قدیمی را نگه می‌دارد (مثل «DVBT0:101»)
-- تا واردکردن دوباره، کانال یا اتاق تکراری نسازد و بشود فهمید هر
-- ردیف از کجا آمده.
SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'iptv_channels'
               AND COLUMN_NAME = 'legacy_ref');
SET @q = IF(@has = 0,
  'ALTER TABLE `iptv_channels` ADD COLUMN `legacy_ref` VARCHAR(64) DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'iptv_rooms'
               AND COLUMN_NAME = 'legacy_ref');
SET @q = IF(@has = 0,
  'ALTER TABLE `iptv_rooms` ADD COLUMN `legacy_ref` VARCHAR(64) DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'iptv_rooms'
               AND COLUMN_NAME = 'legacy_ip');
SET @q = IF(@has = 0,
  'ALTER TABLE `iptv_rooms` ADD COLUMN `legacy_ip` VARCHAR(45) DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @has = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'iptv_channels'
               AND INDEX_NAME = 'idx_legacy_ref');
SET @q = IF(@has = 0,
  'ALTER TABLE `iptv_channels` ADD INDEX `idx_legacy_ref` (`tenant_id`, `legacy_ref`)',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- صفحهٔ پیش‌فرض تله‌تکست — پنل خالی «خراب» به نظر می‌رسد و اپراتور
-- نمی‌داند چه بنویسد. یک صفحهٔ راهنما بهتر از هیچ.
INSERT IGNORE INTO `teletext_pages` (`tenant_id`, `page_no`, `lang`, `title`, `body`, `sort_order`)
VALUES (1, 100, 'fa', 'راهنمای هتل',
        'به هتل خوش آمدید\n\nبرای دیدن صفحات دیگر شمارهٔ صفحه را\nبا کلیدهای عددی ریموت بزنید.\n\nصفحه ۱۰۱  اطلاعات هتل\nصفحه ۱۰۲  رستوران و ساعات سرویس\nصفحه ۱۰۳  خدمات اتاق', 0);
