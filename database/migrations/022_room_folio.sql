-- ── صورتحساب اتاق ───────────────────────────────────────────────
-- فاز ۴ و ۵ نقشه‌راه: docs/TODO.md (۲.۸، ۲.۹، ۲.۱۲، ۴.۲، ۵.۳)
-- مینی‌بار، محتوای پولی، مشاهده صورتحساب روی TV و خروج سریع همگی
-- به یک دفتر بدهکاری مشترک نیاز دارند. این همان است.

-- ── ۱) اقلام صورتحساب ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS room_charges (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  tenant_id     INT UNSIGNED  NOT NULL,
  room_id       INT UNSIGNED  NOT NULL,
  -- اقامت را با زمان ورود مشخص می‌کنیم تا صورتحساب مهمان قبلی
  -- هرگز به مهمان بعدی نشان داده نشود
  stay_started_at DATETIME    NOT NULL,
  source        ENUM('minibar','room_service','laundry','ppv','service','manual','pms')
                              NOT NULL DEFAULT 'manual',
  reference_id  INT UNSIGNED  DEFAULT NULL COMMENT 'guest_requests.id یا vod_videos.id',
  title         VARCHAR(200)  NOT NULL COMMENT 'متنی که مهمان می‌بیند',
  qty           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  unit_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount        DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'qty × unit_price',
  currency      VARCHAR(10)   NOT NULL DEFAULT 'IRR',
  status        ENUM('posted','void','settled') NOT NULL DEFAULT 'posted',
  -- وضعیت ارسال به سیستم هتلداری
  pms_status    ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  pms_ref       VARCHAR(80)   DEFAULT NULL,
  pms_error     VARCHAR(300)  DEFAULT NULL,
  note          VARCHAR(300)  DEFAULT NULL,
  created_by    INT UNSIGNED  DEFAULT NULL COMMENT 'users.id — خالی یعنی خودکار',
  voided_by     INT UNSIGNED  DEFAULT NULL,
  voided_at     DATETIME      DEFAULT NULL,
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_stay       (tenant_id, room_id, stay_started_at),
  KEY idx_status     (tenant_id, status),
  KEY idx_pms        (pms_status),
  KEY idx_source_ref (source, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── ۲) اقلام مینی‌بار ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS minibar_items (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  tenant_id   INT UNSIGNED  NOT NULL,
  name_fa     VARCHAR(120)  NOT NULL,
  name_en     VARCHAR(120)  DEFAULT NULL,
  price       DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency    VARCHAR(10)   NOT NULL DEFAULT 'IRR',
  image       VARCHAR(255)  DEFAULT NULL,
  par_level   TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'موجودی استاندارد هر اتاق',
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active   TINYINT(1)    NOT NULL DEFAULT 1,
  created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tenant (tenant_id, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── ۳) شمارش مینی‌بار توسط خانه‌داری ─────────────────────────────
-- خانه‌دار موجودی فعلی را ثبت می‌کند؛ تفاضل با موجودی استاندارد،
-- مصرف است و خودکار روی صورتحساب می‌نشیند.
CREATE TABLE IF NOT EXISTS minibar_checks (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id     INT UNSIGNED NOT NULL,
  room_id       INT UNSIGNED NOT NULL,
  item_id       INT UNSIGNED NOT NULL,
  found_qty     TINYINT UNSIGNED NOT NULL COMMENT 'چند عدد در یخچال بود',
  consumed_qty  TINYINT UNSIGNED NOT NULL COMMENT 'par_level منهای found_qty',
  charge_id     INT UNSIGNED DEFAULT NULL COMMENT 'room_charges.id اگر ثبت شد',
  checked_by    INT UNSIGNED DEFAULT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_room  (tenant_id, room_id, created_at),
  KEY idx_item  (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── ۴) خرید محتوای پولی ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ppv_purchases (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  tenant_id    INT UNSIGNED  NOT NULL,
  room_id      INT UNSIGNED  NOT NULL,
  video_id     INT UNSIGNED  NOT NULL,
  charge_id    INT UNSIGNED  DEFAULT NULL,
  price        DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency     VARCHAR(10)   NOT NULL DEFAULT 'IRR',
  -- دسترسی زمان‌دار: مهمان بعد از خرید N ساعت فرصت تماشا دارد
  expires_at   DATETIME      NOT NULL,
  cancelled_at DATETIME      DEFAULT NULL,
  created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_room_video (tenant_id, room_id, video_id),
  KEY idx_expires    (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── ۵) درخواست خروج سریع ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS checkout_requests (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  tenant_id    INT UNSIGNED  NOT NULL,
  room_id      INT UNSIGNED  NOT NULL,
  guest_name   VARCHAR(100)  DEFAULT NULL,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'مبلغ در لحظه درخواست',
  currency     VARCHAR(10)   NOT NULL DEFAULT 'IRR',
  status       ENUM('requested','confirmed','rejected') NOT NULL DEFAULT 'requested',
  note         VARCHAR(300)  DEFAULT NULL,
  handled_by   INT UNSIGNED  DEFAULT NULL,
  handled_at   DATETIME      DEFAULT NULL,
  created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_open (tenant_id, room_id, status),
  KEY idx_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── ۶) قیمت و دسترسی روی ویدیوها ────────────────────────────────
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vod_videos' AND COLUMN_NAME='price')=0,
  'ALTER TABLE vod_videos ADD COLUMN price DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT "۰ = رایگان"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vod_videos' AND COLUMN_NAME='access_hours')=0,
  'ALTER TABLE vod_videos ADD COLUMN access_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24 COMMENT "مدت دسترسی بعد از خرید"',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- واحد پول روی خود ویدیو، تا هتلی که محتوای ارزی می‌فروشد هم پوشش داده شود
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vod_videos' AND COLUMN_NAME='currency')=0,
  "ALTER TABLE vod_videos ADD COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'IRR'",
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── ۷) اقلام نمونه مینی‌بار ─────────────────────────────────────
INSERT INTO minibar_items (tenant_id, name_fa, name_en, price, par_level, sort_order)
SELECT tenant_id, name_fa, name_en, price, par_level, sort_order FROM (
  SELECT 1 AS tenant_id, 'آب معدنی کوچک' AS name_fa, 'Small Water' AS name_en,
         50000 AS price, 2 AS par_level, 1 AS sort_order UNION ALL
  SELECT 1, 'نوشابه قوطی',   'Soft Drink Can',  80000, 2, 2 UNION ALL
  SELECT 1, 'آبمیوه',        'Juice',          100000, 2, 3 UNION ALL
  SELECT 1, 'چیپس',          'Chips',           70000, 1, 4 UNION ALL
  SELECT 1, 'شکلات',         'Chocolate',       90000, 2, 5 UNION ALL
  SELECT 1, 'آجیل',          'Mixed Nuts',     150000, 1, 6
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM minibar_items WHERE tenant_id = 1);
