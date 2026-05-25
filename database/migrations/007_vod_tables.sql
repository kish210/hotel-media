-- ============================================================
-- VOD (Video on Demand) Tables — SignageCMS v1.4
-- ============================================================
USE `signage_cms`;

-- ─── دسته‌بندی‌های VOD ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `vod_categories` (
  `id`          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED      NOT NULL DEFAULT 1,
  `parent_id`   INT UNSIGNED      DEFAULT NULL,
  `name`        VARCHAR(255)      NOT NULL,
  `slug`        VARCHAR(255)      NOT NULL,
  `description` TEXT              DEFAULT NULL,
  `cover`       VARCHAR(500)      DEFAULT NULL,
  `color`       VARCHAR(7)        DEFAULT '#7c3aed',
  `sort_order`  SMALLINT UNSIGNED DEFAULT 0,
  `is_active`   TINYINT(1)        NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vod_cat_tenant`   (`tenant_id`),
  KEY `idx_vod_cat_parent`   (`parent_id`),
  KEY `idx_vod_cat_slug`     (`slug`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ویدیوهای VOD ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `vod_videos` (
  `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`      INT UNSIGNED    NOT NULL DEFAULT 1,
  `category_id`    INT UNSIGNED    DEFAULT NULL,
  `title`          VARCHAR(500)    NOT NULL,
  `title_en`       VARCHAR(500)    DEFAULT NULL,
  `description`    TEXT            DEFAULT NULL,
  `type`           ENUM('upload','url','youtube','vimeo') NOT NULL DEFAULT 'upload',
  -- فایل آپلود شده
  `file_path`      VARCHAR(1000)   DEFAULT NULL,
  `file_name`      VARCHAR(500)    DEFAULT NULL,
  `file_size`      BIGINT UNSIGNED DEFAULT 0,
  `mime_type`      VARCHAR(100)    DEFAULT 'video/mp4',
  -- URL خارجی
  `stream_url`     VARCHAR(2000)   DEFAULT NULL,
  -- تامبنیل
  `thumbnail`      VARCHAR(500)    DEFAULT NULL,
  `thumbnail_auto` TINYINT(1)      NOT NULL DEFAULT 0,
  -- متادیتا
  `duration`       INT UNSIGNED    DEFAULT NULL COMMENT 'seconds',
  `duration_fmt`   VARCHAR(20)     DEFAULT NULL COMMENT 'HH:MM:SS',
  `width`          SMALLINT UNSIGNED DEFAULT NULL,
  `height`         SMALLINT UNSIGNED DEFAULT NULL,
  `codec`          VARCHAR(50)     DEFAULT NULL,
  `bitrate`        INT UNSIGNED    DEFAULT NULL,
  `tags`           JSON            DEFAULT NULL,
  `year`           SMALLINT        DEFAULT NULL,
  `language`       VARCHAR(10)     DEFAULT 'fa',
  `rating`         TINYINT UNSIGNED DEFAULT NULL COMMENT '1-5',
  -- وضعیت
  `status`         ENUM('processing','ready','error') NOT NULL DEFAULT 'ready',
  `views`          INT UNSIGNED    NOT NULL DEFAULT 0,
  `sort_order`     SMALLINT UNSIGNED DEFAULT 0,
  `is_featured`    TINYINT(1)      NOT NULL DEFAULT 0,
  `is_active`      TINYINT(1)      NOT NULL DEFAULT 1,
  `uploaded_by`    INT UNSIGNED    DEFAULT NULL,
  `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vod_tenant`    (`tenant_id`),
  KEY `idx_vod_category`  (`category_id`),
  KEY `idx_vod_type`      (`type`),
  KEY `idx_vod_status`    (`status`),
  KEY `idx_vod_featured`  (`is_featured`),
  KEY `idx_vod_active`    (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── داده نمونه ────────────────────────────────────────────────
INSERT IGNORE INTO `vod_categories` (id,tenant_id,name,slug,color,sort_order) VALUES
(1,1,'فیلم‌های آموزشی',  'education','#3b82f6',1),
(2,1,'تبلیغات و پروموشن','promo',     '#f59e0b',2),
(3,1,'اخبار و اطلاعیه',  'news',      '#ef4444',3),
(4,1,'سرگرمی',           'entertainment','#a855f7',4),
(5,1,'بایگانی',          'archive',   '#64748b',5);
