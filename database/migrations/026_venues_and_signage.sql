-- ── محیط‌های عمومی هتل ──────────────────────────────────────────
-- مسئله‌ای که حل می‌کند:
--   تا امروز صفحه‌نمایش فقط یک «نام» داشت. سیستم نمی‌دانست این صفحه جلوی
--   سالن کنفرانس A است یا در لابی. نتیجه: برای هر صفحه باید یک پلی‌لیست
--   جدا ساخته می‌شد و تابلوی رویداد نمی‌توانست خودکار برنامه‌ی همان سالن
--   را نشان دهد.
--
--   و مهم‌تر: هرچه برای تلویزیون اتاق ساختیم — منوی تصویری، اخبار،
--   دفترچه تلفن، نوار زنده — برای صفحه‌های محیط عمومی در دسترس نبود.

-- ── ۱) محل‌ها ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS venues (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id    INT UNSIGNED NOT NULL,
  kind         ENUM('lobby','restaurant','cafe','hall','pool','spa','gym',
                    'elevator','corridor','reception','shop','other')
                            NOT NULL DEFAULT 'other',
  name         VARCHAR(120) NOT NULL COMMENT 'سالن کنفرانس A، رستوران اصلی',
  name_en      VARCHAR(120) DEFAULT NULL,
  floor        VARCHAR(20)  DEFAULT NULL,
  description  VARCHAR(400) DEFAULT NULL,
  image_url    VARCHAR(500) DEFAULT NULL,
  capacity     SMALLINT UNSIGNED DEFAULT NULL COMMENT 'برای سالن',
  open_from    TIME         DEFAULT NULL COMMENT 'ساعت کاری — برای رستوران و استخر',
  open_to      TIME         DEFAULT NULL,
  -- منوی تصویری پیش‌فرض این محل: صفحه‌ی رستوران خودکار منوی رستوران را نشان دهد
  menu_board_id INT UNSIGNED DEFAULT NULL,
  sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tenant (tenant_id, is_active, sort_order),
  KEY idx_kind   (tenant_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- صفحه‌نمایش به محل وصل می‌شود
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='venue_id')=0,
  'ALTER TABLE screens ADD COLUMN venue_id INT UNSIGNED DEFAULT NULL COMMENT "محل نصب — برای صفحات محیط عمومی"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND INDEX_NAME='idx_venue')=0,
  'ALTER TABLE screens ADD INDEX idx_venue (venue_id)',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- رویداد به محل وصل می‌شود تا تابلوی جلوی هر سالن خودکار پر شود
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_events' AND COLUMN_NAME='venue_id')=0,
  'ALTER TABLE hotel_events ADD COLUMN venue_id INT UNSIGNED DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_events' AND INDEX_NAME='idx_event_venue')=0,
  'ALTER TABLE hotel_events ADD INDEX idx_event_venue (venue_id, start_at)',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- وضعیت رویداد — تابلوی در ورودی باید «لغو شد» را هم نشان دهد
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_events' AND COLUMN_NAME='status')=0,
  "ALTER TABLE hotel_events ADD COLUMN status ENUM('scheduled','ongoing','ended','cancelled') NOT NULL DEFAULT 'scheduled'",
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── ۲) آیتم‌های پویا در پلی‌لیست ─────────────────────────────────
-- ‏playlist_items تا امروز فقط media_id داشت، یعنی هر آیتم باید یک فایل
-- آپلودشده می‌بود. محتوای پویا (تابلوی رویداد، منوی تصویری، اخبار) فایل
-- نیست و هر بار باید از دیتابیس ساخته شود.
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='playlist_items' AND COLUMN_NAME='item_type')=0,
  "ALTER TABLE playlist_items ADD COLUMN item_type ENUM('media','event_board','menu_board','news','directory','info_bar','venue_info','live_tv','weather') NOT NULL DEFAULT 'media' AFTER playlist_id",
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ارجاع به منبع محتوا: menu_board_id، venue_id یا channel_id
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='playlist_items' AND COLUMN_NAME='ref_id')=0,
  'ALTER TABLE playlist_items ADD COLUMN ref_id INT UNSIGNED DEFAULT NULL AFTER media_id',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ‏media_id برای آیتم پویا خالی است؛ کلید خارجی موجود NULL را می‌پذیرد
-- پس تغییری لازم نیست.

-- ── ۳) محل‌های نمونه ────────────────────────────────────────────
INSERT INTO venues (tenant_id, kind, name, name_en, floor, sort_order)
SELECT tenant_id, kind, name, name_en, floor, sort_order FROM (
  SELECT 1 AS tenant_id, 'lobby'      AS kind, 'لابی اصلی'       AS name,
         'Main Lobby' AS name_en, 'همکف' AS floor, 1 AS sort_order UNION ALL
  SELECT 1, 'reception',  'پذیرش',          'Reception',       'همکف', 2 UNION ALL
  SELECT 1, 'restaurant', 'رستوران اصلی',   'Main Restaurant', 'همکف', 3 UNION ALL
  SELECT 1, 'hall',       'سالن کنفرانس A', 'Conference Hall A','۱',   4 UNION ALL
  SELECT 1, 'pool',       'استخر',          'Pool',            'منفی ۱', 5
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM venues WHERE tenant_id = 1);
