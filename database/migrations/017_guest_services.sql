-- ── Guest Services — خدمات مهمان ────────────────────────────────
-- سفارش روم‌سرویس، خانه‌داری، خشک‌شویی، تاکسی، بیدارباش، نظرسنجی
-- فاز ۱ نقشه‌راه: docs/TODO.md

-- کاتالوگ خدمات قابل درخواست (هر tenant سرویس‌های خودش را تعریف می‌کند)
CREATE TABLE IF NOT EXISTS guest_services (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  tenant_id     INT UNSIGNED  NOT NULL,
  category      ENUM('room_service','housekeeping','laundry','taxi','breakfast','maintenance','other')
                              NOT NULL DEFAULT 'other',
  name_fa       VARCHAR(120)  NOT NULL,
  name_en       VARCHAR(120)  DEFAULT NULL,
  description   VARCHAR(400)  DEFAULT NULL,
  icon          VARCHAR(60)   DEFAULT NULL COMMENT 'نام آیکون در رابط پلیر',
  image         VARCHAR(255)  DEFAULT NULL,
  price         DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '۰ = رایگان',
  currency      VARCHAR(10)   NOT NULL DEFAULT 'IRR',
  unit          VARCHAR(30)   DEFAULT NULL COMMENT 'عدد، پرس، کیلو',
  is_orderable  TINYINT(1)    NOT NULL DEFAULT 1 COMMENT '۰ = فقط درخواست بدون تعداد',
  available_from TIME         DEFAULT NULL COMMENT 'بازه سرویس‌دهی، مثلا صبحانه ۰۶:۰۰',
  available_to  TIME          DEFAULT NULL,
  sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tenant_cat (tenant_id, category),
  KEY idx_active     (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- درخواست‌ها/سفارش‌های ثبت‌شده توسط مهمان
CREATE TABLE IF NOT EXISTS guest_requests (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  tenant_id     INT UNSIGNED  NOT NULL,
  room_id       INT UNSIGNED  NOT NULL,
  category      ENUM('room_service','housekeeping','laundry','taxi','breakfast','maintenance','wakeup','feedback','other')
                              NOT NULL DEFAULT 'other',
  status        ENUM('pending','accepted','in_progress','done','cancelled')
                              NOT NULL DEFAULT 'pending',
  guest_name    VARCHAR(100)  DEFAULT NULL COMMENT 'کپی از اتاق در لحظه ثبت',
  guest_lang    VARCHAR(10)   NOT NULL DEFAULT 'fa',
  note          VARCHAR(500)  DEFAULT NULL COMMENT 'یادداشت مهمان',
  scheduled_at  DATETIME      DEFAULT NULL COMMENT 'زمان بیدارباش یا تحویل درخواستی',
  total_price   DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency      VARCHAR(10)   NOT NULL DEFAULT 'IRR',
  assigned_to   INT UNSIGNED  DEFAULT NULL COMMENT 'users.id کارمند مسئول',
  staff_note    VARCHAR(500)  DEFAULT NULL,
  rating        TINYINT UNSIGNED DEFAULT NULL COMMENT '۱ تا ۵ — برای category=feedback',
  source        ENUM('tv','panel','api') NOT NULL DEFAULT 'tv',
  accepted_at   DATETIME      DEFAULT NULL,
  done_at       DATETIME      DEFAULT NULL,
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tenant_status (tenant_id, status),
  KEY idx_room     (room_id),
  KEY idx_category (category),
  KEY idx_created  (created_at),
  KEY idx_assigned (assigned_to),
  KEY idx_sched    (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- اقلام هر سفارش (روم‌سرویس/خشک‌شویی چند قلمی است)
CREATE TABLE IF NOT EXISTS guest_request_items (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  request_id    INT UNSIGNED  NOT NULL,
  service_id    INT UNSIGNED  DEFAULT NULL COMMENT 'NULL اگر سرویس بعدا حذف شود',
  menu_item_id  INT UNSIGNED  DEFAULT NULL COMMENT 'ارجاع به iptv_menu_items در صورت سفارش از منو',
  name_snapshot VARCHAR(160)  NOT NULL COMMENT 'نام در لحظه سفارش',
  qty           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  unit_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total    DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_request (request_id),
  KEY idx_service (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- تاریخچه تغییر وضعیت (برای SLA و پیگیری)
CREATE TABLE IF NOT EXISTS guest_request_log (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id   INT UNSIGNED NOT NULL,
  from_status  VARCHAR(20)  DEFAULT NULL,
  to_status    VARCHAR(20)  NOT NULL,
  user_id      INT UNSIGNED DEFAULT NULL,
  note         VARCHAR(300) DEFAULT NULL,
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- داده اولیه نمونه برای tenant 1 (فقط اگر خالی باشد)
INSERT INTO guest_services (tenant_id, category, name_fa, name_en, icon, price, unit, sort_order)
SELECT tenant_id, category, name_fa, name_en, icon, price, unit, sort_order FROM (
  SELECT 1 AS tenant_id, 'housekeeping' AS category, 'نظافت اتاق' AS name_fa,
         'Room Cleaning' AS name_en, 'broom' AS icon, 0 AS price,
         NULL AS unit, 1 AS sort_order UNION ALL
  SELECT 1, 'housekeeping', 'حوله اضافه',      'Extra Towels',    'towel',  0, 'عدد', 2 UNION ALL
  SELECT 1, 'housekeeping', 'ملحفه اضافه',     'Extra Bedsheets', 'bed',    0, 'عدد', 3 UNION ALL
  SELECT 1, 'maintenance',  'درخواست تعمیرات', 'Maintenance',     'wrench', 0, NULL, 4 UNION ALL
  SELECT 1, 'laundry',      'شست‌وشوی پیراهن', 'Shirt Wash',      'shirt',  150000, 'عدد', 5 UNION ALL
  SELECT 1, 'laundry',      'اتوکشی شلوار',    'Trouser Ironing', 'iron',   120000, 'عدد', 6 UNION ALL
  SELECT 1, 'taxi',         'درخواست تاکسی',   'Taxi Request',    'car',    0, NULL, 7 UNION ALL
  SELECT 1, 'breakfast',    'صبحانه کامل',     'Full Breakfast',  'coffee', 450000, 'پرس', 8
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM guest_services WHERE tenant_id = 1);
