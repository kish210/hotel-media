-- ── کنترل دسترسی کانال و محتوای جانبی ──────────────────────────
-- نقشه‌راه: ۱.۱۷ (سطح دسترسی کانال)، ۳.۴ (قفل والدین)،
--           ۱.۹ (اخبار)، ۱.۱۵ (رادیو)، ۱.۱۶ (قرآن)، ۲.۱۵ (دفترچه تلفن)

-- ══════════════════════════════════════════════════════════════
--  ۱) دسترسی کانال بر اساس سطح اتاق
-- ══════════════════════════════════════════════════════════════
-- کانال‌های VIP فقط برای سوئیت. «پخش بر اساس سطوح دسترسی» کاماسیستم.

-- سطح دسترسی مورد نیاز هر کانال: ۰ = همه، عدد بالاتر = محدودتر
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_channels' AND COLUMN_NAME='access_level')=0,
  'ALTER TABLE iptv_channels ADD COLUMN access_level TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT "۰ = همه اتاق‌ها"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- رده‌ی سنی — کانالی که قفل والدین می‌خواهد
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_channels' AND COLUMN_NAME='is_adult')=0,
  'ALTER TABLE iptv_channels ADD COLUMN is_adult TINYINT(1) NOT NULL DEFAULT 0 COMMENT "نیاز به رمز والدین"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- کانال فقط‌صوتی (رادیو) — تصویر پس‌زمینه دارد نه ویدیو
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_channels' AND COLUMN_NAME='is_radio')=0,
  'ALTER TABLE iptv_channels ADD COLUMN is_radio TINYINT(1) NOT NULL DEFAULT 0 COMMENT "کانال رادیویی"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- سطح دسترسی اتاق — سوئیت عدد بالاتر می‌گیرد
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_rooms' AND COLUMN_NAME='access_level')=0,
  'ALTER TABLE iptv_rooms ADD COLUMN access_level TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT "سطح دسترسی اتاق"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- قفل والدین هر اتاق — رمز ۴ رقمی که مهمان خودش می‌گذارد
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_rooms' AND COLUMN_NAME='parental_pin')=0,
  'ALTER TABLE iptv_rooms ADD COLUMN parental_pin VARCHAR(255) DEFAULT NULL COMMENT "hash رمز والدین — هرگز متن ساده"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_rooms' AND COLUMN_NAME='parental_enabled')=0,
  'ALTER TABLE iptv_rooms ADD COLUMN parental_enabled TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ══════════════════════════════════════════════════════════════
--  ۲) محتوای جانبی
-- ══════════════════════════════════════════════════════════════
-- اخبار، قرآن، کتاب، دفترچه تلفن و مقصدهای گردشگری یک الگوی مشترک
-- دارند: فهرست آیتم با متن، صوت یا تصویر. به‌جای پنج جدول جدا، یک جدول
-- با نوع محتوا — نگهداری و نمایش در پلیر هر دو ساده‌تر می‌شود.
CREATE TABLE IF NOT EXISTS content_items (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id    INT UNSIGNED NOT NULL,
  kind         ENUM('news','quran','book','directory','info','prayer_audio')
                            NOT NULL DEFAULT 'info',
  category     VARCHAR(80)  DEFAULT NULL COMMENT 'گروه‌بندی داخل هر نوع',
  title        VARCHAR(250) NOT NULL,
  title_en     VARCHAR(250) DEFAULT NULL,
  subtitle     VARCHAR(250) DEFAULT NULL,
  body         MEDIUMTEXT   DEFAULT NULL COMMENT 'متن کامل — برای خبر و قرآن',
  image_url    VARCHAR(500) DEFAULT NULL,
  audio_url    VARCHAR(500) DEFAULT NULL COMMENT 'تلاوت یا کتاب صوتی',
  file_url     VARCHAR(500) DEFAULT NULL COMMENT 'PDF کتاب یا روزنامه',
  extra        VARCHAR(250) DEFAULT NULL COMMENT 'شماره داخلی، نویسنده، منبع خبر',
  lang         VARCHAR(10)  NOT NULL DEFAULT 'fa',
  sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  published_at DATETIME     DEFAULT NULL COMMENT 'برای خبر',
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_kind    (tenant_id, kind, is_active, sort_order),
  KEY idx_pubdate (tenant_id, kind, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- منبع RSS برای خبر — تا اپراتور خبرها را دستی وارد نکند
CREATE TABLE IF NOT EXISTS news_feeds (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id     INT UNSIGNED NOT NULL,
  name          VARCHAR(120) NOT NULL,
  url           VARCHAR(500) NOT NULL,
  category      VARCHAR(80)  DEFAULT NULL,
  lang          VARCHAR(10)  NOT NULL DEFAULT 'fa',
  max_items     TINYINT UNSIGNED NOT NULL DEFAULT 20,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_sync_at  DATETIME     DEFAULT NULL,
  last_sync_msg VARCHAR(300) DEFAULT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tenant (tenant_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════════════════════
--  ۳) بیدارباش روی تلویزیون
-- ══════════════════════════════════════════════════════════════
-- ثبت بیدارباش قبلا انجام شده بود ولی چیزی روی تلویزیون نشان داده
-- نمی‌شد. این ستون‌ها تحویل واقعی را کامل می‌کنند.
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='guest_requests' AND COLUMN_NAME='delivered_at')=0,
  'ALTER TABLE guest_requests ADD COLUMN delivered_at DATETIME DEFAULT NULL COMMENT "زمان نمایش روی تلویزیون"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='guest_requests' AND COLUMN_NAME='acknowledged_at')=0,
  'ALTER TABLE guest_requests ADD COLUMN acknowledged_at DATETIME DEFAULT NULL COMMENT "مهمان بیدارباش را بست"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ══════════════════════════════════════════════════════════════
--  ۴) انواع جدید آیتم منو
-- ══════════════════════════════════════════════════════════════
ALTER TABLE iptv_menu_items
  MODIFY COLUMN type ENUM(
    'live','vod','news','info','weather','fids','hotel','corporate','retail',
    'url','custom','radio','quran','book','directory','folio','services','epg'
  ) NOT NULL DEFAULT 'live';

-- ══════════════════════════════════════════════════════════════
--  ۵) داده نمونه — دفترچه تلفن
-- ══════════════════════════════════════════════════════════════
INSERT INTO content_items (tenant_id, kind, title, title_en, extra, sort_order)
SELECT tenant_id, kind, title, title_en, extra, sort_order FROM (
  SELECT 1 AS tenant_id, 'directory' AS kind, 'پذیرش' AS title,
         'Reception' AS title_en, '9' AS extra, 1 AS sort_order UNION ALL
  SELECT 1, 'directory', 'روم‌سرویس',  'Room Service',  '10', 2 UNION ALL
  SELECT 1, 'directory', 'خانه‌داری',   'Housekeeping',  '11', 3 UNION ALL
  SELECT 1, 'directory', 'رستوران',    'Restaurant',    '12', 4 UNION ALL
  SELECT 1, 'directory', 'اورژانس',    'Emergency',     '112', 5
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM content_items WHERE tenant_id = 1 AND kind = 'directory');
