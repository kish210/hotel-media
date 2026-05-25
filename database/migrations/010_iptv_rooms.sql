-- ── IPTV Rooms & PMS Integration ────────────────────────────────
-- اتاق‌های هتل/واحدها، پیام‌رسانی، یکپارچه‌سازی PMS

CREATE TABLE IF NOT EXISTS iptv_rooms (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  tenant_id     INT UNSIGNED  NOT NULL,
  group_id      INT UNSIGNED  DEFAULT NULL,
  room_number   VARCHAR(20)   NOT NULL COMMENT 'شماره اتاق: 101، A-205',
  room_name     VARCHAR(100)  DEFAULT NULL COMMENT 'نام اتاق: Suite Deluxe',
  floor         TINYINT       DEFAULT NULL,
  room_type     VARCHAR(50)   DEFAULT NULL COMMENT 'single|double|suite|vip',
  status        ENUM('available','occupied','maintenance') NOT NULL DEFAULT 'available',
  guest_name    VARCHAR(100)  DEFAULT NULL,
  guest_lang    VARCHAR(10)   NOT NULL DEFAULT 'fa',
  check_in_at   DATETIME      DEFAULT NULL,
  check_out_at  DATETIME      DEFAULT NULL,
  pms_room_id   VARCHAR(50)   DEFAULT NULL COMMENT 'کد اتاق در سیستم PMS',
  notes         VARCHAR(300)  DEFAULT NULL,
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_room_tenant (tenant_id, room_number),
  KEY idx_group   (group_id),
  KEY idx_tenant  (tenant_id),
  KEY idx_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS iptv_room_messages (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  tenant_id     INT UNSIGNED  NOT NULL,
  room_id       INT UNSIGNED  DEFAULT NULL COMMENT 'NULL = پخش به همه اتاق‌ها',
  title         VARCHAR(200)  DEFAULT NULL,
  body          TEXT          NOT NULL,
  msg_type      ENUM('info','welcome','urgent','promo','custom') NOT NULL DEFAULT 'info',
  display_mode  ENUM('banner','popup','ticker')                  NOT NULL DEFAULT 'banner',
  priority      TINYINT UNSIGNED NOT NULL DEFAULT 5,
  expires_at    DATETIME      DEFAULT NULL,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_room   (room_id),
  KEY idx_tenant (tenant_id),
  KEY idx_active (is_active, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pms_integrations (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  tenant_id     INT UNSIGNED  NOT NULL,
  name          VARCHAR(100)  NOT NULL COMMENT 'نام سیستم PMS',
  api_key       VARCHAR(64)   NOT NULL,
  pms_type      VARCHAR(50)   DEFAULT 'custom' COMMENT 'opera|fidelio|hotelogix|custom',
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  last_used_at  DATETIME      DEFAULT NULL,
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_api_key (api_key),
  KEY idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ستون iptv_room_id به جدول screens
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='iptv_room_id')=0,
  'ALTER TABLE screens ADD COLUMN iptv_room_id INT UNSIGNED DEFAULT NULL AFTER iptv_menu_id',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
