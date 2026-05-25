-- ============================================================
-- Digital Signage CMS - Complete Database Schema
-- Version: 1.0.0
-- Engine: InnoDB | Charset: utf8mb4
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ============================================================
-- DATABASE
-- ============================================================
CREATE DATABASE IF NOT EXISTS `signage_cms`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `signage_cms`;

-- ============================================================
-- TENANTS (Multi-tenant support)
-- ============================================================
CREATE TABLE `tenants` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(120) NOT NULL,
  `slug`          VARCHAR(80)  NOT NULL UNIQUE,
  `plan`          ENUM('free','starter','pro','enterprise') NOT NULL DEFAULT 'starter',
  `storage_limit` BIGINT UNSIGNED NOT NULL DEFAULT 10737418240 COMMENT '10 GB in bytes',
  `screen_limit`  SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `settings`      JSON DEFAULT NULL COMMENT 'Tenant-level config overrides',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_slug` (`slug`),
  INDEX `idx_plan` (`plan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE `users` (
  `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id`         INT UNSIGNED NOT NULL,
  `name`              VARCHAR(120) NOT NULL,
  `email`             VARCHAR(180) NOT NULL,
  `password_hash`     VARCHAR(255) NOT NULL,
  `role`              ENUM('super_admin','restaurant_manager','content_editor') NOT NULL DEFAULT 'content_editor',
  `avatar`            VARCHAR(255) DEFAULT NULL,
  `timezone`          VARCHAR(60) NOT NULL DEFAULT 'Asia/Tehran',
  `locale`            VARCHAR(10) NOT NULL DEFAULT 'fa',
  `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `last_login_at`     TIMESTAMP NULL DEFAULT NULL,
  `last_login_ip`     VARCHAR(45) DEFAULT NULL,
  `two_factor_secret` VARCHAR(32) DEFAULT NULL,
  `preferences`       JSON DEFAULT NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_tenant_email` (`tenant_id`, `email`),
  INDEX `idx_tenant_role` (`tenant_id`, `role`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- REFRESH TOKENS (JWT)
-- ============================================================
CREATE TABLE `refresh_tokens` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL UNIQUE COMMENT 'SHA-256 of token',
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `revoked_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_token` (`user_id`, `token_hash`),
  INDEX `idx_expires` (`expires_at`),
  CONSTRAINT `fk_rt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LOCATIONS (Restaurant / Branch)
-- ============================================================
CREATE TABLE `locations` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id`  INT UNSIGNED NOT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `address`    TEXT DEFAULT NULL,
  `city`       VARCHAR(80) DEFAULT NULL,
  `country`    VARCHAR(80) DEFAULT 'Iran',
  `lat`        DECIMAL(10,8) DEFAULT NULL,
  `lng`        DECIMAL(11,8) DEFAULT NULL,
  `timezone`   VARCHAR(60) NOT NULL DEFAULT 'Asia/Tehran',
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `meta`       JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tenant_location` (`tenant_id`),
  CONSTRAINT `fk_loc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SCREENS
-- ============================================================
CREATE TABLE `screens` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id`       INT UNSIGNED NOT NULL,
  `location_id`     INT UNSIGNED DEFAULT NULL,
  `name`            VARCHAR(120) NOT NULL,
  `unique_code`     VARCHAR(16) NOT NULL UNIQUE COMMENT 'Device pairing code',
  `activation_code` VARCHAR(8) DEFAULT NULL COMMENT 'Temp 8-char pairing PIN',
  `status`          ENUM('online','offline','pairing','maintenance') NOT NULL DEFAULT 'pairing',
  `orientation`     ENUM('landscape','portrait') NOT NULL DEFAULT 'landscape',
  `resolution`      VARCHAR(20) DEFAULT '1920x1080',
  `tags`            JSON DEFAULT NULL,
  `ip_address`      VARCHAR(45) DEFAULT NULL,
  `mac_address`     VARCHAR(17) DEFAULT NULL,
  `os_info`         VARCHAR(120) DEFAULT NULL,
  `app_version`     VARCHAR(20) DEFAULT NULL,
  `current_playlist_id` INT UNSIGNED DEFAULT NULL,
  `screenshot_url`  VARCHAR(255) DEFAULT NULL,
  `screenshot_at`   TIMESTAMP NULL DEFAULT NULL,
  `last_seen_at`    TIMESTAMP NULL DEFAULT NULL,
  `last_heartbeat_at` TIMESTAMP NULL DEFAULT NULL,
  `notes`           TEXT DEFAULT NULL,
  `settings`        JSON DEFAULT NULL COMMENT 'Screen-level overrides: brightness, volume, etc.',
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tenant_status` (`tenant_id`, `status`),
  INDEX `idx_unique_code` (`unique_code`),
  INDEX `idx_location` (`location_id`),
  CONSTRAINT `fk_scr_tenant`   FOREIGN KEY (`tenant_id`)   REFERENCES `tenants`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_scr_location` FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SCREEN COMMANDS (Remote control queue)
-- ============================================================
CREATE TABLE `screen_commands` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `screen_id`   INT UNSIGNED NOT NULL,
  `issued_by`   INT UNSIGNED DEFAULT NULL,
  `command`     ENUM('refresh','reboot','screenshot','mute','unmute','brightness','volume','emergency_broadcast','clear_cache') NOT NULL,
  `payload`     JSON DEFAULT NULL,
  `status`      ENUM('pending','delivered','executed','failed') NOT NULL DEFAULT 'pending',
  `delivered_at` TIMESTAMP NULL DEFAULT NULL,
  `executed_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at`  TIMESTAMP NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 10 MINUTE),
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_screen_pending` (`screen_id`, `status`),
  CONSTRAINT `fk_cmd_screen` FOREIGN KEY (`screen_id`) REFERENCES `screens`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cmd_user`   FOREIGN KEY (`issued_by`) REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MEDIA LIBRARY
-- ============================================================
CREATE TABLE `media` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id`    INT UNSIGNED NOT NULL,
  `uploaded_by`  INT UNSIGNED DEFAULT NULL,
  `type`         ENUM('image','video','url','html','stream') NOT NULL,
  `name`         VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) DEFAULT NULL,
  `file_path`    VARCHAR(512) DEFAULT NULL COMMENT 'Relative path from storage root',
  `url`          VARCHAR(512) DEFAULT NULL COMMENT 'For type=url/stream',
  `thumbnail_path` VARCHAR(512) DEFAULT NULL,
  `mime_type`    VARCHAR(80) DEFAULT NULL,
  `file_size`    BIGINT UNSIGNED DEFAULT 0,
  `duration`     INT UNSIGNED DEFAULT 10 COMMENT 'Seconds — for images/URLs',
  `width`        SMALLINT UNSIGNED DEFAULT NULL,
  `height`       SMALLINT UNSIGNED DEFAULT NULL,
  `meta`         JSON DEFAULT NULL,
  `tags`         JSON DEFAULT NULL,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tenant_type` (`tenant_id`, `type`),
  INDEX `idx_tenant_active` (`tenant_id`, `is_active`),
  CONSTRAINT `fk_media_tenant` FOREIGN KEY (`tenant_id`)   REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_media_user`   FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LAYOUTS (Zone designer)
-- ============================================================
CREATE TABLE `layouts` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id`   INT UNSIGNED NOT NULL,
  `created_by`  INT UNSIGNED DEFAULT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `canvas_w`    SMALLINT UNSIGNED NOT NULL DEFAULT 1920,
  `canvas_h`    SMALLINT UNSIGNED NOT NULL DEFAULT 1080,
  `zones`       JSON NOT NULL COMMENT 'Array of zone objects with position/size/type',
  `thumbnail`   VARCHAR(255) DEFAULT NULL,
  `is_default`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tenant_layout` (`tenant_id`),
  CONSTRAINT `fk_layout_tenant` FOREIGN KEY (`tenant_id`)  REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_layout_user`   FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PLAYLISTS
-- ============================================================
CREATE TABLE `playlists` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id`   INT UNSIGNED NOT NULL,
  `created_by`  INT UNSIGNED DEFAULT NULL,
  `layout_id`   INT UNSIGNED DEFAULT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `loop`        TINYINT(1) NOT NULL DEFAULT 1,
  `shuffle`     TINYINT(1) NOT NULL DEFAULT 0,
  `transition`  ENUM('none','fade','slide','zoom','flip') NOT NULL DEFAULT 'fade',
  `transition_duration` SMALLINT UNSIGNED NOT NULL DEFAULT 500 COMMENT 'ms',
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tenant_playlist` (`tenant_id`),
  CONSTRAINT `fk_pl_tenant` FOREIGN KEY (`tenant_id`)  REFERENCES `tenants`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_pl_user`   FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL,
  CONSTRAINT `fk_pl_layout` FOREIGN KEY (`layout_id`)  REFERENCES `layouts`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PLAYLIST ITEMS
-- ============================================================
CREATE TABLE `playlist_items` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `playlist_id`    INT UNSIGNED NOT NULL,
  `media_id`       INT UNSIGNED NOT NULL,
  `zone_id`        VARCHAR(36) DEFAULT NULL COMMENT 'Zone UUID from layout',
  `sort_order`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `duration`       INT UNSIGNED DEFAULT NULL COMMENT 'Override media default duration (seconds)',
  `start_date`     DATE DEFAULT NULL,
  `end_date`       DATE DEFAULT NULL,
  `start_time`     TIME DEFAULT NULL,
  `end_time`       TIME DEFAULT NULL,
  `weekdays`       TINYINT UNSIGNED DEFAULT 127 COMMENT 'Bitmask: Mon=1,Tue=2,Wed=4,Thu=8,Fri=16,Sat=32,Sun=64',
  `conditions`     JSON DEFAULT NULL COMMENT 'Advanced scheduling conditions',
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_playlist_order` (`playlist_id`, `sort_order`),
  INDEX `idx_media` (`media_id`),
  CONSTRAINT `fk_pi_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `playlists`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pi_media`    FOREIGN KEY (`media_id`)    REFERENCES `media`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SCREEN PLAYLIST ASSIGNMENTS
-- ============================================================
CREATE TABLE `screen_playlists` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `screen_id`    INT UNSIGNED NOT NULL,
  `playlist_id`  INT UNSIGNED NOT NULL,
  `priority`     TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Higher = takes precedence',
  `start_date`   DATE DEFAULT NULL,
  `end_date`     DATE DEFAULT NULL,
  `start_time`   TIME DEFAULT NULL,
  `end_time`     TIME DEFAULT NULL,
  `weekdays`     TINYINT UNSIGNED DEFAULT 127,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_screen_playlist` (`screen_id`, `playlist_id`),
  CONSTRAINT `fk_sp_screen`   FOREIGN KEY (`screen_id`)   REFERENCES `screens`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_sp_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `playlists`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CAMPAIGNS (High-level scheduling wrapper)
-- ============================================================
CREATE TABLE `campaigns` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id`   INT UNSIGNED NOT NULL,
  `created_by`  INT UNSIGNED DEFAULT NULL,
  `name`        VARCHAR(160) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `type`        ENUM('standard','promotional','emergency','menu_board') NOT NULL DEFAULT 'standard',
  `status`      ENUM('draft','scheduled','active','paused','expired','cancelled') NOT NULL DEFAULT 'draft',
  `priority`    TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `start_at`    DATETIME NOT NULL,
  `end_at`      DATETIME NOT NULL,
  `budget`      DECIMAL(12,2) DEFAULT NULL,
  `target_tags` JSON DEFAULT NULL COMMENT 'Target screens with these tags',
  `settings`    JSON DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tenant_status` (`tenant_id`, `status`),
  INDEX `idx_schedule` (`start_at`, `end_at`),
  CONSTRAINT `fk_camp_tenant` FOREIGN KEY (`tenant_id`)  REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_camp_user`   FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MENU BOARDS
-- ============================================================
CREATE TABLE `menu_boards` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id`   INT UNSIGNED NOT NULL,
  `location_id` INT UNSIGNED DEFAULT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `currency`    VARCHAR(5) NOT NULL DEFAULT 'IRR',
  `layout`      ENUM('grid','list','featured','split') NOT NULL DEFAULT 'grid',
  `theme`       JSON DEFAULT NULL COMMENT 'Colors, fonts, background image',
  `show_qr`     TINYINT(1) NOT NULL DEFAULT 1,
  `qr_url`      VARCHAR(512) DEFAULT NULL,
  `footer_text` VARCHAR(255) DEFAULT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tenant_menu` (`tenant_id`),
  CONSTRAINT `fk_mb_tenant`   FOREIGN KEY (`tenant_id`)   REFERENCES `tenants`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_mb_location` FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MENU CATEGORIES
-- ============================================================
CREATE TABLE `menu_categories` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `menu_board_id` INT UNSIGNED NOT NULL,
  `name`          VARCHAR(120) NOT NULL,
  `name_en`       VARCHAR(120) DEFAULT NULL,
  `icon`          VARCHAR(255) DEFAULT NULL,
  `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_board_cat` (`menu_board_id`, `sort_order`),
  CONSTRAINT `fk_mcat_board` FOREIGN KEY (`menu_board_id`) REFERENCES `menu_boards`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MENU ITEMS
-- ============================================================
CREATE TABLE `menu_items` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NOT NULL,
  `name`        VARCHAR(160) NOT NULL,
  `name_en`     VARCHAR(160) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `price`       DECIMAL(14,0) NOT NULL DEFAULT 0,
  `old_price`   DECIMAL(14,0) DEFAULT NULL COMMENT 'For showing discount',
  `image_id`    INT UNSIGNED DEFAULT NULL,
  `badge`       VARCHAR(60) DEFAULT NULL COMMENT 'e.g. NEW, HOT, SPICY',
  `badge_color` VARCHAR(7) DEFAULT '#FF0000',
  `calories`    SMALLINT UNSIGNED DEFAULT NULL,
  `allergens`   JSON DEFAULT NULL,
  `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `available_from` TIME DEFAULT NULL,
  `available_to`   TIME DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_category_order` (`category_id`, `sort_order`),
  CONSTRAINT `fk_mi_category` FOREIGN KEY (`category_id`) REFERENCES `menu_categories`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mi_image`    FOREIGN KEY (`image_id`)    REFERENCES `media`(`id`)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WIDGETS (Ticker, Clock, Weather, RSS)
-- ============================================================
CREATE TABLE `widgets` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id`  INT UNSIGNED NOT NULL,
  `type`       ENUM('clock','weather','rss','ticker','qr_code','iframe','custom_html') NOT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `config`     JSON NOT NULL COMMENT 'Widget-specific settings',
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tenant_widget` (`tenant_id`, `type`),
  CONSTRAINT `fk_widget_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE `notifications` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id`  INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `type`       ENUM('screen_offline','screen_online','storage_warning','campaign_expired','campaign_starting','system','emergency') NOT NULL,
  `title`      VARCHAR(255) NOT NULL,
  `body`       TEXT DEFAULT NULL,
  `data`       JSON DEFAULT NULL COMMENT 'Extra context: screen_id, campaign_id, etc.',
  `channel`    SET('in_app','email','sms','webhook') NOT NULL DEFAULT 'in_app',
  `read_at`    TIMESTAMP NULL DEFAULT NULL,
  `sent_at`    TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_tenant_unread` (`tenant_id`, `read_at`),
  INDEX `idx_user_notify` (`user_id`, `read_at`),
  CONSTRAINT `fk_notif_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ACTIVITY LOGS
-- ============================================================
CREATE TABLE `activity_logs` (
  `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id`   INT UNSIGNED DEFAULT NULL,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `screen_id`   INT UNSIGNED DEFAULT NULL,
  `action`      VARCHAR(80) NOT NULL COMMENT 'e.g. user.login, screen.paired, playlist.updated',
  `subject_type` VARCHAR(60) DEFAULT NULL,
  `subject_id`  INT UNSIGNED DEFAULT NULL,
  `properties`  JSON DEFAULT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `user_agent`  TEXT DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_tenant_action` (`tenant_id`, `action`, `created_at`),
  INDEX `idx_user_log` (`user_id`, `created_at`),
  INDEX `idx_screen_log` (`screen_id`, `created_at`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SCREEN HEARTBEATS (time-series metrics)
-- ============================================================
CREATE TABLE `screen_heartbeats` (
  `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `screen_id`   INT UNSIGNED NOT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `cpu`         TINYINT UNSIGNED DEFAULT NULL COMMENT 'CPU % 0-100',
  `memory`      TINYINT UNSIGNED DEFAULT NULL COMMENT 'Memory % 0-100',
  `disk`        TINYINT UNSIGNED DEFAULT NULL COMMENT 'Disk % 0-100',
  `temperature` TINYINT DEFAULT NULL COMMENT 'Celsius',
  `current_media_id` INT UNSIGNED DEFAULT NULL,
  `uptime`      INT UNSIGNED DEFAULT NULL COMMENT 'Seconds',
  `bandwidth_rx` INT UNSIGNED DEFAULT NULL COMMENT 'Bytes/s',
  `bandwidth_tx` INT UNSIGNED DEFAULT NULL COMMENT 'Bytes/s',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_screen_time` (`screen_id`, `created_at`),
  CONSTRAINT `fk_hb_screen` FOREIGN KEY (`screen_id`) REFERENCES `screens`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- API KEYS (For external integrations / Android TV)
-- ============================================================
CREATE TABLE `api_keys` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id`   INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `key_hash`    VARCHAR(64) NOT NULL UNIQUE COMMENT 'SHA-256 of API key',
  `key_prefix`  VARCHAR(8) NOT NULL COMMENT 'First 8 chars for display',
  `permissions` JSON DEFAULT NULL COMMENT 'Scopes: read, write, admin',
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at`  TIMESTAMP NULL DEFAULT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_tenant_key` (`tenant_id`),
  CONSTRAINT `fk_ak_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ak_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WEBSOCKET CONNECTIONS (tracking)
-- ============================================================
CREATE TABLE `ws_connections` (
  `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `connection_id` VARCHAR(64) NOT NULL UNIQUE,
  `tenant_id`    INT UNSIGNED DEFAULT NULL,
  `user_id`      INT UNSIGNED DEFAULT NULL,
  `screen_id`    INT UNSIGNED DEFAULT NULL,
  `type`         ENUM('admin','screen','mobile') NOT NULL DEFAULT 'admin',
  `connected_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `disconnected_at` TIMESTAMP NULL DEFAULT NULL,
  INDEX `idx_tenant_ws` (`tenant_id`, `disconnected_at`),
  INDEX `idx_screen_ws` (`screen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- RATE LIMITING
-- ============================================================
CREATE TABLE `rate_limits` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key`        VARCHAR(128) NOT NULL COMMENT 'IP:endpoint or user_id:endpoint',
  `hits`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `window_start` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_key` (`key`),
  INDEX `idx_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
