-- ════════════════════════════════════════════════════════════════════
-- تابلوی پرواز برای تابلوهای هتل + ساختار هتل زنجیره‌ای
-- ════════════════════════════════════════════════════════════════════
--
-- ── تابلوی پرواز ─────────────────────────────────────────────────────
-- هتل نزدیک فرودگاه فهرست پروازهای ورودی و خروجی را در لابی نشان
-- می‌دهد. این یک ماژول جدا نیست — فقط یک نوع محتوای تابلو، مثل
-- تابلوی رویداد و اخبار. پس همان جریان موجود را می‌گیرد: در پلی‌لیست
-- قرار می‌گیرد، همان رندرِ tv-signage.css را دارد و از همان مسیر
-- SignageContentService می‌آید.
--
-- ماژول FIDS قبلی برای فرودگاه نوشته شده بود — دروازه، تسمه، نوع
-- هواپیما، تاخیر. هتل به هیچ‌کدام نیاز ندارد؛ فقط شماره پرواز، ایرلاین،
-- مقصد، ساعت و وضعیت. جدول اینجا عمدا کوچک است.
--
-- ── هتل زنجیره‌ای ────────────────────────────────────────────────────
-- جدول locations از قبل هست ولی برای «شعبه» ساخته شده (شهر، آدرس،
-- مختصات) — همان چیزی که هتل زنجیره‌ای لازم دارد. چیزی که کم بود
-- اتصال صفحه‌نمایش و اتاق به شعبه است، تا اپراتور شعبه فقط دستگاه‌های
-- خودش را ببیند.
--
-- این مهاجرت idempotent است.

-- ── پروازها ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS flights (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id      INT UNSIGNED NOT NULL DEFAULT 1,
  -- شعبه: هتل زنجیره‌ای ممکن است شعبه‌های نزدیک فرودگاه‌های مختلف داشته باشد
  location_id    INT UNSIGNED DEFAULT NULL,

  direction      ENUM('arrival','departure') NOT NULL,
  flight_number  VARCHAR(20)  NOT NULL,
  airline        VARCHAR(100) NOT NULL,
  airline_logo   VARCHAR(500) DEFAULT NULL,

  -- مبدا برای ورودی، مقصد برای خروجی — یک ستون، چون هر پرواز فقط
  -- یکی‌شان را لازم دارد و دو ستون نیمه‌خالی فقط اشتباه می‌آورد
  city           VARCHAR(100) NOT NULL,
  city_en        VARCHAR(100) DEFAULT NULL,

  scheduled_at   DATETIME NOT NULL,
  estimated_at   DATETIME DEFAULT NULL COMMENT 'اگر تاخیر داشته باشد',

  status         ENUM('scheduled','boarding','departed','arrived','delayed','cancelled')
                 NOT NULL DEFAULT 'scheduled',
  terminal       VARCHAR(20) DEFAULT NULL,

  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  source         VARCHAR(40) DEFAULT 'manual' COMMENT 'manual یا نام سامانه‌ی مبدا',
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  -- تابلو همیشه «پروازهای امروز، مرتب بر اساس ساعت» می‌خواهد
  KEY idx_board (tenant_id, direction, scheduled_at, is_active),
  KEY idx_location (location_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── افزودن flight_board به انواع محتوای تابلو ────────────────────────
SET @cur := (
  SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'playlist_items'
    AND COLUMN_NAME  = 'item_type'
);
SET @sql := IF(@cur IS NOT NULL AND LOCATE('flight_board', @cur) = 0,
  "ALTER TABLE playlist_items MODIFY COLUMN item_type
     ENUM('media','event_board','menu_board','news','directory','info_bar',
          'venue_info','live_tv','weather','flight_board')
     NOT NULL DEFAULT 'media'",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── شعبه روی صفحه‌نمایش ──────────────────────────────────────────────
-- بدون این، اپراتور شعبه‌ی «هتل کیش» صفحه‌نمایش‌های «هتل مشهد» را هم
-- می‌بیند و ممکن است اشتباهی روی آن‌ها پخش کند.
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'screens' AND COLUMN_NAME = 'location_id'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE screens ADD COLUMN location_id INT UNSIGNED DEFAULT NULL
     COMMENT "شعبه در هتل زنجیره‌ای" AFTER tenant_id,
   ADD KEY idx_location (location_id)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── شعبه روی اتاق ────────────────────────────────────────────────────
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'iptv_rooms' AND COLUMN_NAME = 'location_id'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE iptv_rooms ADD COLUMN location_id INT UNSIGNED DEFAULT NULL
     COMMENT "شعبه در هتل زنجیره‌ای" AFTER tenant_id,
   ADD KEY idx_location (location_id)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── ساختمان و بال ────────────────────────────────────────────────────
-- «طبقه» از قبل هست ولی هتل بزرگ ساختمان و بال هم دارد: اتاق ۳۰۱ در
-- برج شرقی با ۳۰۱ در برج غربی یکی نیست.
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'iptv_rooms' AND COLUMN_NAME = 'building'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE iptv_rooms
     ADD COLUMN building VARCHAR(50) DEFAULT NULL COMMENT "ساختمان یا برج" AFTER floor,
     ADD COLUMN wing     VARCHAR(50) DEFAULT NULL COMMENT "بال یا بلوک"    AFTER building',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── شعبه روی گروه ────────────────────────────────────────────────────
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'screen_groups' AND COLUMN_NAME = 'location_id'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE screen_groups ADD COLUMN location_id INT UNSIGNED DEFAULT NULL
     COMMENT "شعبه در هتل زنجیره‌ای" AFTER tenant_id,
   ADD KEY idx_location (location_id)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
