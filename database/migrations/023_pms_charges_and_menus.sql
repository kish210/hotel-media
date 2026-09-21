-- ── اتصال خروجی به PMS + منوهای تصویری ─────────────────────────
-- ‏pms_integrations تا امروز فقط ورودی بود: سیستم هتلداری به ما checkin
-- و checkout می‌فرستاد. برای اینکه مینی‌بار و صورتحساب معنا پیدا کنند،
-- باید بتوانیم اقلام را به PMS **بفرستیم**.

-- ── ۱) تنظیمات ارسال به PMS ─────────────────────────────────────
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pms_integrations' AND COLUMN_NAME='charge_url')=0,
  'ALTER TABLE pms_integrations ADD COLUMN charge_url VARCHAR(500) DEFAULT NULL COMMENT "آدرس ثبت قلم در PMS"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pms_integrations' AND COLUMN_NAME='auth_type')=0,
  "ALTER TABLE pms_integrations ADD COLUMN auth_type ENUM('none','bearer','basic','header') NOT NULL DEFAULT 'none'",
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pms_integrations' AND COLUMN_NAME='auth_secret')=0,
  'ALTER TABLE pms_integrations ADD COLUMN auth_secret VARCHAR(500) DEFAULT NULL COMMENT "توکن یا user:pass"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pms_integrations' AND COLUMN_NAME='auth_header')=0,
  'ALTER TABLE pms_integrations ADD COLUMN auth_header VARCHAR(80) DEFAULT NULL COMMENT "نام هدر وقتی auth_type=header"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pms_integrations' AND COLUMN_NAME='push_charges')=0,
  'ALTER TABLE pms_integrations ADD COLUMN push_charges TINYINT(1) NOT NULL DEFAULT 0 COMMENT "۱ = اقلام خودکار ارسال شوند"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- نگاشت نام فیلدها: هر PMS اسم دیگری برای «شماره اتاق» و «مبلغ» دارد
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pms_integrations' AND COLUMN_NAME='field_map')=0,
  'ALTER TABLE pms_integrations ADD COLUMN field_map TEXT DEFAULT NULL COMMENT "JSON نگاشت نام فیلدها"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pms_integrations' AND COLUMN_NAME='last_push_at')=0,
  'ALTER TABLE pms_integrations ADD COLUMN last_push_at DATETIME DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pms_integrations' AND COLUMN_NAME='last_push_msg')=0,
  'ALTER TABLE pms_integrations ADD COLUMN last_push_msg VARCHAR(300) DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── ۲) تلاش‌های ارسال ───────────────────────────────────────────
-- بدون این، وقتی PMS هتل چند ساعت قطع است معلوم نیست چه چیزی نرسیده.
CREATE TABLE IF NOT EXISTS pms_push_log (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id   INT UNSIGNED NOT NULL,
  charge_id   INT UNSIGNED NOT NULL,
  pms_id      INT UNSIGNED DEFAULT NULL,
  attempt     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  http_status SMALLINT UNSIGNED DEFAULT NULL,
  ok          TINYINT(1)   NOT NULL DEFAULT 0,
  response    VARCHAR(500) DEFAULT NULL,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_charge (charge_id),
  KEY idx_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- شمارنده تلاش روی خود قلم، تا ارسال بی‌پایان تکرار نشود
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='room_charges' AND COLUMN_NAME='pms_attempts')=0,
  'ALTER TABLE room_charges ADD COLUMN pms_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── ۳) منوهای تصویری ────────────────────────────────────────────
-- ‏IT هتل عکس منوی چاپی رستوران، روم‌سرویس یا خشک‌شویی را بارگذاری
-- می‌کند و همان روی تلویزیون اتاق نمایش داده می‌شود. ساده‌ترین راه برای
-- هتلی که نمی‌خواهد تک‌تک اقلام را وارد سیستم کند.
CREATE TABLE IF NOT EXISTS menu_boards (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id   INT UNSIGNED NOT NULL,
  category    ENUM('restaurant','room_service','laundry','minibar','breakfast','spa','other')
                           NOT NULL DEFAULT 'restaurant',
  title       VARCHAR(160) NOT NULL,
  title_en    VARCHAR(160) DEFAULT NULL,
  description VARCHAR(400) DEFAULT NULL,
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tenant (tenant_id, category, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- منوی چندصفحه‌ای: هر صفحه یک تصویر
CREATE TABLE IF NOT EXISTS menu_board_pages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  board_id    INT UNSIGNED NOT NULL,
  image_url   VARCHAR(500) NOT NULL,
  caption     VARCHAR(200) DEFAULT NULL,
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  width       SMALLINT UNSIGNED DEFAULT NULL,
  height      SMALLINT UNSIGNED DEFAULT NULL,
  file_size   INT UNSIGNED DEFAULT NULL,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_board (board_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
