-- ── EPG — راهنمای الکترونیکی برنامه‌ها ──────────────────────────
-- فاز ۲ نقشه‌راه: docs/TODO.md (۱.۲)
-- منبع داده: TVHeadend (/api/epg/events/grid) یا فایل/آدرس XMLTV

CREATE TABLE IF NOT EXISTS epg_programs (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  tenant_id     INT UNSIGNED  NOT NULL,
  channel_id    INT UNSIGNED  DEFAULT NULL COMMENT 'iptv_channels.id — پس از تطبیق',
  channel_key   VARCHAR(120)  NOT NULL COMMENT 'کلید منبع: tvh uuid یا xmltv channel id',
  title         VARCHAR(300)  NOT NULL,
  subtitle      VARCHAR(300)  DEFAULT NULL,
  description   TEXT          DEFAULT NULL,
  category      VARCHAR(80)   DEFAULT NULL COMMENT 'فیلم، خبر، ورزش، کودک',
  lang          VARCHAR(10)   NOT NULL DEFAULT 'fa',
  starts_at     DATETIME      NOT NULL,
  ends_at       DATETIME      NOT NULL,
  season        SMALLINT UNSIGNED DEFAULT NULL,
  episode       SMALLINT UNSIGNED DEFAULT NULL,
  image         VARCHAR(500)  DEFAULT NULL,
  rating        VARCHAR(20)   DEFAULT NULL COMMENT 'رده سنی',
  source_id     INT UNSIGNED  DEFAULT NULL COMMENT 'epg_sources.id',
  external_id   VARCHAR(120)  DEFAULT NULL COMMENT 'شناسه رویداد در منبع',
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  -- یک برنامه در یک کانال و یک زمان شروع، یکتا است (برای sync تکراری)
  UNIQUE KEY uniq_event (tenant_id, channel_key, starts_at),
  KEY idx_channel_time (channel_id, starts_at, ends_at),
  KEY idx_tenant_time  (tenant_id, starts_at),
  KEY idx_ends         (ends_at),
  KEY idx_source       (source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- منابع EPG (هر tenant می‌تواند چند منبع داشته باشد)
CREATE TABLE IF NOT EXISTS epg_sources (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id      INT UNSIGNED NOT NULL,
  name           VARCHAR(120) NOT NULL,
  source_type    ENUM('tvheadend','xmltv_url','xmltv_file') NOT NULL DEFAULT 'tvheadend',
  tvh_source_id  INT UNSIGNED DEFAULT NULL COMMENT 'tvheadend_sources.id وقتی نوع tvheadend است',
  url            VARCHAR(500) DEFAULT NULL COMMENT 'آدرس XMLTV',
  days_ahead     TINYINT UNSIGNED NOT NULL DEFAULT 7 COMMENT 'چند روز جلوتر دریافت شود',
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  last_sync_at   DATETIME     DEFAULT NULL,
  last_sync_msg  VARCHAR(300) DEFAULT NULL,
  last_count     INT UNSIGNED NOT NULL DEFAULT 0,
  created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tenant (tenant_id),
  KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- نگاشت کلید کانالِ منبع به کانال داخلی، وقتی خودکار تطبیق نمی‌شود
CREATE TABLE IF NOT EXISTS epg_channel_map (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id    INT UNSIGNED NOT NULL,
  channel_key  VARCHAR(120) NOT NULL COMMENT 'کلید در منبع EPG',
  channel_id   INT UNSIGNED NOT NULL COMMENT 'iptv_channels.id',
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_map (tenant_id, channel_key),
  KEY idx_channel (channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ‏iptv_channels از قبل ستون epg_id دارد (004) — همان کلید تطبیق است و
-- ستون جدیدی ساخته نمی‌شود. فقط ایندکس لازم است تا JOIN تطبیق سریع باشد.
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iptv_channels' AND INDEX_NAME='idx_epg_id')=0,
  'ALTER TABLE iptv_channels ADD INDEX idx_epg_id (epg_id)',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
