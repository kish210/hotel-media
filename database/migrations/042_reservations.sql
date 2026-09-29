-- ── رزرو رستوران و امکانات — TODO ۲.۱۳ و ۲.۱۴ ────────────────────
-- فرقش با سفارش غذا: سفارش «الان بیاورید» است؛ رزرو «فلان ساعت برای
-- این تعداد جا نگه دارید». پس دو چیز لازم دارد که guest_requests
-- ندارد: بازه‌ی زمانی و ظرفیت.
--
-- رزرو روی «محل» (venues از ۰۲۶) می‌نشیند نه روی جدول جدا: رستوران،
-- کافی‌شاپ، استخر، باشگاه و اسپا همین حالا محل‌اند و ساعت کاری و
-- ظرفیت دارند. یک مفهوم دوم برای همان چیز یعنی دو جا برای به‌روز
-- نگه‌داشتن ساعت کاری.

-- ── ۱) تنظیمات رزرو روی محل ────────────────────────────────────────
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='venues' AND COLUMN_NAME='bookable')=0,
  "ALTER TABLE venues
     ADD COLUMN bookable      TINYINT(1)        NOT NULL DEFAULT 0 COMMENT 'مهمان از تلویزیون رزرو می‌کند',
     ADD COLUMN slot_minutes  SMALLINT UNSIGNED NOT NULL DEFAULT 60 COMMENT 'طول هر نوبت — میز رستوران معمولا ۹۰',
     ADD COLUMN max_party     TINYINT UNSIGNED  NOT NULL DEFAULT 8 COMMENT 'بیشترین نفر در یک رزرو',
     ADD COLUMN booking_price DECIMAL(12,2)     NOT NULL DEFAULT 0 COMMENT 'هزینه‌ی هر نفر در هر نوبت — ۰ = رایگان',
     ADD COLUMN days_ahead    TINYINT UNSIGNED  NOT NULL DEFAULT 7 COMMENT 'چند روز جلوتر قابل رزرو است'",
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── ۲) رزروها ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reservations (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id     INT UNSIGNED NOT NULL,
  venue_id      INT UNSIGNED NOT NULL,
  room_id       INT UNSIGNED DEFAULT NULL COMMENT 'NULL = مهمان بیرونی که پذیرش ثبت کرده',
  guest_name    VARCHAR(100) DEFAULT NULL COMMENT 'کپی در لحظه‌ی ثبت',
  party_size    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  start_at      DATETIME     NOT NULL,
  end_at        DATETIME     NOT NULL,
  status        ENUM('pending','confirmed','completed','no_show','cancelled')
                             NOT NULL DEFAULT 'pending',
  note          VARCHAR(300) DEFAULT NULL COMMENT 'یادداشت مهمان — صندلی کودک، کنار پنجره',
  staff_note    VARCHAR(300) DEFAULT NULL,
  price         DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'کل مبلغ در لحظه‌ی ثبت',
  charge_id     INT UNSIGNED DEFAULT NULL COMMENT 'room_charges.id بعد از تایید',
  source        ENUM('tv','panel') NOT NULL DEFAULT 'tv',
  created_by    INT UNSIGNED DEFAULT NULL,
  confirmed_at  DATETIME     DEFAULT NULL,
  cancelled_at  DATETIME     DEFAULT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  -- جستجوی ظرفیت: «چه رزروهایی از این محل با این بازه هم‌پوشانی دارند»
  KEY idx_venue_time (venue_id, start_at, end_at),
  KEY idx_tenant_day (tenant_id, start_at),
  KEY idx_room       (room_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- قلم صورتحساب رزرو باید از بقیه جدا شناخته شود تا با لغو باطل شود
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='room_charges' AND COLUMN_NAME='source' AND COLUMN_TYPE LIKE '%''reservation''%')=0,
  (SELECT CONCAT('ALTER TABLE room_charges MODIFY COLUMN source ',
                 REPLACE(COLUMN_TYPE, ')', ',''reservation'')'),
                 ' NOT NULL DEFAULT ''manual''')
     FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='room_charges' AND COLUMN_NAME='source'),
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- آیتم منوی تلویزیون برای باز کردن صفحه‌ی رزرو
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_menu_items' AND COLUMN_NAME='type' AND COLUMN_TYPE LIKE '%''reserve''%')=0,
  (SELECT CONCAT('ALTER TABLE iptv_menu_items MODIFY COLUMN type ',
                 REPLACE(COLUMN_TYPE, ')', ',''reserve'')'),
                 ' NOT NULL DEFAULT ''live''')
     FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_menu_items' AND COLUMN_NAME='type'),
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
