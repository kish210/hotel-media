-- ════════════════════════════════════════════════════════════════════
-- منبع خودکار پرواز، و برندینگ تابلوی تبلیغات
-- ════════════════════════════════════════════════════════════════════
--
-- ۱) جدول flights در ۰۳۲ ساخته شد ولی هیچ کلید یکتایی ندارد. تا وقتی
--    اپراتور دستی وارد می‌کرد مهم نبود؛ از لحظه‌ای که همگام‌سازِ هر ۵
--    دقیقه اجرا شود، بدون کلید یکتا هر اجرا همان پروازها را دوباره درج
--    می‌کند و تابلو در چند ساعت پر از ردیف تکراری می‌شود.
--
-- ۲) لوگو و زیرنویس روی خودِ پلی‌لیست می‌نشینند، نه در تنظیمات عمومی.
--    دلیلش این است که هتل بیش از یک پلی‌لیست دارد (لابی، رستوران،
--    اتاق) و لوگو یا متن هرکدام می‌تواند فرق کند؛ اگر عمومی بود،
--    تعویضش برای یک تابلو همه‌ی تابلوها را عوض می‌کرد.
--
-- این مهاجرت idempotent است.

-- ── flights: شناسه‌ی مبدا ─────────────────────────────────────────
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='flights'
                AND COLUMN_NAME='source_ref');
SET @sql := IF(@has = 0,
  "ALTER TABLE flights ADD COLUMN source_ref VARCHAR(40) DEFAULT NULL
     COMMENT 'شناسه‌ی پرواز در سامانه‌ی مبدا — کلید همگام‌سازی'",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── flights: وضعیت به زبان مبدا ───────────────────────────────────
-- ENUM ما شش حالت دارد ولی فرودگاه متن دقیق‌تری می‌دهد
-- («پایان تحویل بار (۲۱:۵۱)»). آن متن برای مهمان مفیدتر از برچسب کلی
-- ماست، پس هم نگه داشته می‌شود هم نگاشت ENUM.
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='flights'
                AND COLUMN_NAME='status_text');
SET @sql := IF(@has = 0,
  "ALTER TABLE flights ADD COLUMN status_text VARCHAR(160) DEFAULT NULL
     COMMENT 'وضعیت به همان عبارتی که مبدا داده'",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── flights: کلید یکتای همگام‌سازی ────────────────────────────────
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='flights'
                AND INDEX_NAME='uniq_source');
SET @sql := IF(@has = 0,
  'ALTER TABLE flights ADD UNIQUE KEY uniq_source (tenant_id, source, source_ref)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── پلی‌لیست: لوگوی همیشگی گوشه‌ی تابلو ───────────────────────────
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='playlists'
                AND COLUMN_NAME='logo_path');
SET @sql := IF(@has = 0,
  "ALTER TABLE playlists ADD COLUMN logo_path VARCHAR(500) DEFAULT NULL
     COMMENT 'لوگوی بالا-راست تابلو؛ خالی یعنی لوگوی مستاجر'",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── پلی‌لیست: متن زیرنویس ─────────────────────────────────────────
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='playlists'
                AND COLUMN_NAME='ticker_text');
SET @sql := IF(@has = 0,
  "ALTER TABLE playlists ADD COLUMN ticker_text TEXT DEFAULT NULL
     COMMENT 'متن نوار پایین تابلو'",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── پلی‌لیست: نمایش دوره‌ای دمای هوا ──────────────────────────────
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='playlists'
                AND COLUMN_NAME='weather_every');
SET @sql := IF(@has = 0,
  "ALTER TABLE playlists
     ADD COLUMN weather_enabled TINYINT(1) NOT NULL DEFAULT 1,
     ADD COLUMN weather_every   SMALLINT UNSIGNED NOT NULL DEFAULT 240
       COMMENT 'ثانیه — هر چند وقت یک‌بار ظاهر شود',
     ADD COLUMN weather_show    SMALLINT UNSIGNED NOT NULL DEFAULT 60
       COMMENT 'ثانیه — چقدر بماند'",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── متن نمونه ─────────────────────────────────────────────────────
-- عمدا متن پیش‌فرض گذاشته می‌شود: اپراتور باید روی تابلو چیزی ببیند که
-- قابل ویرایش بودنش را نشان دهد، نه نواری خالی که فکر کند خراب است.
UPDATE playlists
   SET ticker_text = 'به هتل خوش آمدید — برای رزرو ترانسفر فرودگاه با پذیرش تماس بگیرید (داخلی ۹)'
 WHERE ticker_text IS NULL OR ticker_text = '';
