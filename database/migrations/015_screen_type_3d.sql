-- ── 015 Add 'monitor_3d' screen type + config table ───────────────────────
-- MySQL 8 compatible

SET @db = DATABASE();

-- ── 1. Add monitor_3d to ENUM ──────────────────────────────────────────────
SET @col_type = (
    SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME   = 'screens'
      AND COLUMN_NAME  = 'screen_type'
);

SET @q = IF(
    LOCATE('monitor_3d', @col_type) = 0,
    "ALTER TABLE screens MODIFY COLUMN screen_type ENUM('signage','iptv','inflight','monitor_3d') NOT NULL DEFAULT 'signage'",
    'SELECT 1'
);
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 2. Config table for 3D monitors ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `monitor_3d_configs` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `screen_id`     INT UNSIGNED NOT NULL,
    `tenant_id`     INT UNSIGNED NOT NULL DEFAULT 1,
    `format_3d`     ENUM('normal','sbs','top_bottom','hologram','anaglyphic') NOT NULL DEFAULT 'normal',
    `depth_level`   TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1-10',
    `depth_color`   VARCHAR(20)  NOT NULL DEFAULT '#00e5ff',
    `is_outdoor`    TINYINT(1)   NOT NULL DEFAULT 0,
    `bg_color`      VARCHAR(20)  NOT NULL DEFAULT '#000000',
    `auto_rotate`   TINYINT(1)   NOT NULL DEFAULT 0,
    `rotate_speed`  INT UNSIGNED NOT NULL DEFAULT 5,
    `aspect_ratio`  VARCHAR(20)  NOT NULL DEFAULT '16:9',
    `parallax_intensity` TINYINT UNSIGNED NOT NULL DEFAULT 6 COMMENT '1-10',
    `show_depth_badge`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_screen_id` (`screen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
