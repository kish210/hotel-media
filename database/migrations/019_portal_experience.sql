-- ── تجربه‌ی مهمان روی تلویزیون ──────────────────────────────────
-- فاز ۳ نقشه‌راه: docs/TODO.md (۳.۱، ۳.۲، ۳.۵، ۳.۶، ۳.۷)
-- ‏iptv_menus از قبل bg_image / logo_url / accent_color / ticker دارد (009)؛
-- این migration فقط چیزهای نداشته را اضافه می‌کند.

-- ── ۱) میانبر عددی روی ریموت ─────────────────────────────────────
-- مهمان عدد را روی ریموت می‌زند و مستقیم به آن بخش می‌رود.
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_menu_items' AND COLUMN_NAME='shortcut_key')=0,
  'ALTER TABLE iptv_menu_items ADD COLUMN shortcut_key TINYINT UNSIGNED DEFAULT NULL COMMENT "عدد ۰ تا ۹ روی ریموت"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── ۲) برچسب دوزبانه برای آیتم‌های منو ───────────────────────────
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_menu_items' AND COLUMN_NAME='label_en')=0,
  'ALTER TABLE iptv_menu_items ADD COLUMN label_en VARCHAR(100) DEFAULT NULL AFTER label',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── ۳) تنظیمات هدر/فوتر زنده روی منو ─────────────────────────────
-- کدام ویجت‌ها روی نوار بالا/پایین دیده شوند: ["clock","weather","currency","prayer"]
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_menus' AND COLUMN_NAME='header_widgets')=0,
  'ALTER TABLE iptv_menus ADD COLUMN header_widgets VARCHAR(200) DEFAULT "clock,weather" COMMENT "ویجت‌های نوار بالا، با کاما"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_menus' AND COLUMN_NAME='show_guest_name')=0,
  'ALTER TABLE iptv_menus ADD COLUMN show_guest_name TINYINT(1) NOT NULL DEFAULT 1 COMMENT "خوش‌آمدگویی با نام مهمان"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- شهر مرجع برای آب‌وهوا و اوقات شرعی
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_menus' AND COLUMN_NAME='city')=0,
  'ALTER TABLE iptv_menus ADD COLUMN city VARCHAR(80) DEFAULT NULL COMMENT "شهر برای آب‌وهوا و اوقات شرعی"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── ۴) اسلایدشوی پس‌زمینه ────────────────────────────────────────
-- ‏bg_image تک‌تصویر است؛ این جدول چند تصویر چرخشی می‌دهد.
CREATE TABLE IF NOT EXISTS iptv_menu_backgrounds (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  menu_id    INT UNSIGNED NOT NULL,
  image_url  VARCHAR(500) NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_menu (menu_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── ۵) کش داده‌های زنده‌ی هدر ────────────────────────────────────
-- آب‌وهوا، نرخ ارز و اوقات شرعی از سرویس بیرونی می‌آیند. بدون کش،
-- هر تلویزیون در هر بار بارگذاری یک درخواست بیرونی می‌سازد و سرویس
-- مبدأ ما را محدود می‌کند. یک ردیف به ازای هر (tenant, نوع, کلید).
CREATE TABLE IF NOT EXISTS portal_live_cache (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id  INT UNSIGNED NOT NULL,
  kind       ENUM('weather','currency','prayer') NOT NULL,
  cache_key  VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'معمولا نام شهر',
  payload    TEXT         NOT NULL COMMENT 'JSON',
  fetched_at DATETIME     NOT NULL,
  expires_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_entry (tenant_id, kind, cache_key),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── ۶) ترجمه‌ی رابط پلیر ─────────────────────────────────────────
-- ‏guest_lang روی اتاق ذخیره می‌شود ولی متنی برای ترجمه نبود.
CREATE TABLE IF NOT EXISTS portal_translations (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id   INT UNSIGNED NOT NULL,
  lang        VARCHAR(10)  NOT NULL,
  trans_key   VARCHAR(120) NOT NULL,
  trans_value VARCHAR(500) NOT NULL,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_key (tenant_id, lang, trans_key),
  KEY idx_lang (tenant_id, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- متن‌های پایه‌ی پلیر به فارسی، انگلیسی و عربی (فقط اگر خالی باشد)
INSERT INTO portal_translations (tenant_id, lang, trans_key, trans_value)
SELECT tenant_id, lang, trans_key, trans_value FROM (
  SELECT 1 AS tenant_id, 'fa' AS lang, 'welcome'       AS trans_key, 'خوش آمدید'            AS trans_value UNION ALL
  SELECT 1, 'fa', 'live_tv',      'تلویزیون زنده'   UNION ALL
  SELECT 1, 'fa', 'movies',       'فیلم و سریال'    UNION ALL
  SELECT 1, 'fa', 'services',     'خدمات اتاق'      UNION ALL
  SELECT 1, 'fa', 'now_playing',  'در حال پخش'      UNION ALL
  SELECT 1, 'fa', 'up_next',      'بعدی'            UNION ALL
  SELECT 1, 'fa', 'press_number', 'شماره را بزنید'  UNION ALL
  SELECT 1, 'en', 'welcome',      'Welcome'         UNION ALL
  SELECT 1, 'en', 'live_tv',      'Live TV'         UNION ALL
  SELECT 1, 'en', 'movies',       'Movies & Series' UNION ALL
  SELECT 1, 'en', 'services',     'Room Services'   UNION ALL
  SELECT 1, 'en', 'now_playing',  'Now Playing'     UNION ALL
  SELECT 1, 'en', 'up_next',      'Up Next'         UNION ALL
  SELECT 1, 'en', 'press_number', 'Press a number'  UNION ALL
  SELECT 1, 'ar', 'welcome',      'أهلا وسهلا'      UNION ALL
  SELECT 1, 'ar', 'live_tv',      'البث المباشر'    UNION ALL
  SELECT 1, 'ar', 'movies',       'أفلام ومسلسلات'  UNION ALL
  SELECT 1, 'ar', 'services',     'خدمات الغرفة'    UNION ALL
  SELECT 1, 'ar', 'now_playing',  'يعرض الآن'       UNION ALL
  SELECT 1, 'ar', 'up_next',      'التالي'          UNION ALL
  SELECT 1, 'ar', 'press_number', 'اضغط رقما'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM portal_translations WHERE tenant_id = 1);
