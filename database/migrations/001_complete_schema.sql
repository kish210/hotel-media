-- ============================================================
-- SignageCMS Complete Database Schema
-- Version: 1.0.0  |  Engine: InnoDB  |  Charset: utf8mb4
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `signage_cms`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `signage_cms`;

-- ─────────────────────────────────────────
-- TENANTS (Multi-tenant support)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tenants` (
  `id`          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(100)      NOT NULL UNIQUE,
  `name`        VARCHAR(255)      NOT NULL,
  `domain`      VARCHAR(255)      DEFAULT NULL,
  `logo`        VARCHAR(500)      DEFAULT NULL,
  `plan`        ENUM('free','basic','pro','enterprise') NOT NULL DEFAULT 'free',
  `storage_limit` BIGINT          NOT NULL DEFAULT 5368709120, -- 5 GB
  `screen_limit` INT              NOT NULL DEFAULT 5,
  `settings`    JSON              DEFAULT NULL,
  `is_active`   TINYINT(1)        NOT NULL DEFAULT 1,
  `trial_ends_at` TIMESTAMP       DEFAULT NULL,
  `created_at`  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenants_slug` (`slug`),
  KEY `idx_tenants_domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- USERS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`     INT UNSIGNED    NOT NULL,
  `name`          VARCHAR(255)    NOT NULL,
  `email`         VARCHAR(255)    NOT NULL,
  `password`      VARCHAR(255)    NOT NULL,
  `role`          ENUM('super_admin','admin','manager','editor','viewer') NOT NULL DEFAULT 'editor',
  `avatar`        VARCHAR(500)    DEFAULT NULL,
  `phone`         VARCHAR(20)     DEFAULT NULL,
  `language`      VARCHAR(10)     NOT NULL DEFAULT 'fa',
  `timezone`      VARCHAR(50)     NOT NULL DEFAULT 'Asia/Tehran',
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `email_verified_at` TIMESTAMP   DEFAULT NULL,
  `last_login_at` TIMESTAMP       DEFAULT NULL,
  `last_login_ip` VARCHAR(45)     DEFAULT NULL,
  `two_factor_secret` VARCHAR(255) DEFAULT NULL,
  `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `remember_token` VARCHAR(100)   DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`    TIMESTAMP       DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email_tenant` (`email`, `tenant_id`),
  KEY `idx_users_tenant` (`tenant_id`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_active` (`is_active`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- USER SESSIONS & TOKENS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_tokens` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED    NOT NULL,
  `token_hash`  VARCHAR(255)    NOT NULL,
  `type`        ENUM('access','refresh','reset','verify','api') NOT NULL DEFAULT 'access',
  `device_info` JSON            DEFAULT NULL,
  `ip_address`  VARCHAR(45)     DEFAULT NULL,
  `expires_at`  TIMESTAMP       NOT NULL,
  `revoked_at`  TIMESTAMP       DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tokens_user` (`user_id`),
  KEY `idx_tokens_hash` (`token_hash`(64)),
  KEY `idx_tokens_expires` (`expires_at`),
  CONSTRAINT `fk_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- LOCATIONS (Restaurant branches)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `locations` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED    NOT NULL,
  `name`        VARCHAR(255)    NOT NULL,
  `address`     TEXT            DEFAULT NULL,
  `city`        VARCHAR(100)    DEFAULT NULL,
  `country`     VARCHAR(100)    DEFAULT NULL,
  `lat`         DECIMAL(10,8)   DEFAULT NULL,
  `lng`         DECIMAL(11,8)   DEFAULT NULL,
  `timezone`    VARCHAR(50)     NOT NULL DEFAULT 'Asia/Tehran',
  `phone`       VARCHAR(20)     DEFAULT NULL,
  `manager_id`  INT UNSIGNED    DEFAULT NULL,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `meta`        JSON            DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_locations_tenant` (`tenant_id`),
  CONSTRAINT `fk_locations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- SCREENS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `screens` (
  `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`         INT UNSIGNED    NOT NULL,
  `location_id`       INT UNSIGNED    DEFAULT NULL,
  `name`              VARCHAR(255)    NOT NULL,
  `code`              VARCHAR(8)      NOT NULL UNIQUE,
  `activation_code`   VARCHAR(6)      DEFAULT NULL,
  `activation_expires_at` TIMESTAMP   DEFAULT NULL,
  `description`       TEXT            DEFAULT NULL,
  `orientation`       ENUM('landscape','portrait') NOT NULL DEFAULT 'landscape',
  `resolution`        VARCHAR(20)     DEFAULT '1920x1080',
  `status`            ENUM('active','inactive','pending','error') NOT NULL DEFAULT 'pending',
  `is_online`         TINYINT(1)      NOT NULL DEFAULT 0,
  `last_seen_at`      TIMESTAMP       DEFAULT NULL,
  `last_ip`           VARCHAR(45)     DEFAULT NULL,
  `current_playlist_id` INT UNSIGNED  DEFAULT NULL,
  `device_info`       JSON            DEFAULT NULL,
  `tags`              JSON            DEFAULT NULL,
  `screenshot_url`    VARCHAR(500)    DEFAULT NULL,
  `screenshot_at`     TIMESTAMP       DEFAULT NULL,
  `brightness`        TINYINT UNSIGNED NOT NULL DEFAULT 100,
  `volume`            TINYINT UNSIGNED NOT NULL DEFAULT 70,
  `reboot_requested`  TINYINT(1)      NOT NULL DEFAULT 0,
  `refresh_requested` TINYINT(1)      NOT NULL DEFAULT 0,
  `emergency_broadcast` TEXT          DEFAULT NULL,
  `settings`          JSON            DEFAULT NULL,
  `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_screens_tenant` (`tenant_id`),
  KEY `idx_screens_code` (`code`),
  KEY `idx_screens_location` (`location_id`),
  KEY `idx_screens_status` (`status`),
  KEY `idx_screens_online` (`is_online`),
  CONSTRAINT `fk_screens_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_screens_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- MEDIA FILES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `media` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`     INT UNSIGNED    NOT NULL,
  `uploaded_by`   INT UNSIGNED    DEFAULT NULL,
  `name`          VARCHAR(255)    NOT NULL,
  `original_name` VARCHAR(255)    NOT NULL,
  `type`          ENUM('image','video','url','html','pdf') NOT NULL,
  `mime_type`     VARCHAR(100)    DEFAULT NULL,
  `file_path`     VARCHAR(500)    DEFAULT NULL,
  `url`           VARCHAR(1000)   DEFAULT NULL,
  `thumbnail_path` VARCHAR(500)   DEFAULT NULL,
  `file_size`     BIGINT UNSIGNED DEFAULT 0,
  `duration`      INT UNSIGNED    DEFAULT NULL COMMENT 'in seconds',
  `width`         INT UNSIGNED    DEFAULT NULL,
  `height`        INT UNSIGNED    DEFAULT NULL,
  `tags`          JSON            DEFAULT NULL,
  `folder`        VARCHAR(255)    DEFAULT NULL,
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `meta`          JSON            DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`    TIMESTAMP       DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_media_tenant` (`tenant_id`),
  KEY `idx_media_type` (`type`),
  KEY `idx_media_uploader` (`uploaded_by`),
  CONSTRAINT `fk_media_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_media_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- LAYOUTS (Zone-based screen templates)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `layouts` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED    NOT NULL,
  `created_by`  INT UNSIGNED    NOT NULL,
  `name`        VARCHAR(255)    NOT NULL,
  `description` TEXT            DEFAULT NULL,
  `thumbnail`   VARCHAR(500)    DEFAULT NULL,
  `canvas_width`  INT UNSIGNED  NOT NULL DEFAULT 1920,
  `canvas_height` INT UNSIGNED  NOT NULL DEFAULT 1080,
  `zones`       JSON            NOT NULL COMMENT 'Array of zone definitions',
  `background_color` VARCHAR(7) DEFAULT '#000000',
  `background_image` VARCHAR(500) DEFAULT NULL,
  `is_template` TINYINT(1)      NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_layouts_tenant` (`tenant_id`),
  CONSTRAINT `fk_layouts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- PLAYLISTS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `playlists` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`     INT UNSIGNED    NOT NULL,
  `created_by`    INT UNSIGNED    NOT NULL,
  `layout_id`     INT UNSIGNED    DEFAULT NULL,
  `name`          VARCHAR(255)    NOT NULL,
  `description`   TEXT            DEFAULT NULL,
  `default_duration` INT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'seconds per item',
  `transition`    ENUM('none','fade','slide','zoom','flip') NOT NULL DEFAULT 'fade',
  `transition_duration` DECIMAL(3,1) NOT NULL DEFAULT 0.5,
  `loop`          TINYINT(1)      NOT NULL DEFAULT 1,
  `shuffle`       TINYINT(1)      NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `tags`          JSON            DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_playlists_tenant` (`tenant_id`),
  KEY `idx_playlists_layout` (`layout_id`),
  CONSTRAINT `fk_playlists_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_playlists_layout` FOREIGN KEY (`layout_id`) REFERENCES `layouts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- PLAYLIST ITEMS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `playlist_items` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `playlist_id`   INT UNSIGNED    NOT NULL,
  `media_id`      INT UNSIGNED    DEFAULT NULL,
  `zone_id`       VARCHAR(50)     DEFAULT NULL COMMENT 'layout zone identifier',
  `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `duration`      INT UNSIGNED    NOT NULL DEFAULT 10 COMMENT 'seconds',
  `start_at`      TIME            DEFAULT NULL COMMENT 'daily start time',
  `end_at`        TIME            DEFAULT NULL COMMENT 'daily end time',
  `volume`        TINYINT UNSIGNED NOT NULL DEFAULT 100,
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `settings`      JSON            DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_items_playlist` (`playlist_id`),
  KEY `idx_items_media` (`media_id`),
  KEY `idx_items_order` (`sort_order`),
  CONSTRAINT `fk_items_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- SCHEDULES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `schedules` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`     INT UNSIGNED    NOT NULL,
  `screen_id`     INT UNSIGNED    DEFAULT NULL,
  `playlist_id`   INT UNSIGNED    NOT NULL,
  `name`          VARCHAR(255)    NOT NULL,
  `type`          ENUM('once','daily','weekly','monthly','always') NOT NULL DEFAULT 'always',
  `start_date`    DATE            DEFAULT NULL,
  `end_date`      DATE            DEFAULT NULL,
  `start_time`    TIME            DEFAULT NULL,
  `end_time`      TIME            DEFAULT NULL,
  `weekdays`      JSON            DEFAULT NULL COMMENT '[0=Sun,1=Mon,...,6=Sat]',
  `priority`      TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_schedules_screen` (`screen_id`),
  KEY `idx_schedules_playlist` (`playlist_id`),
  KEY `idx_schedules_tenant` (`tenant_id`),
  KEY `idx_schedules_dates` (`start_date`, `end_date`),
  CONSTRAINT `fk_schedules_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_schedules_screen` FOREIGN KEY (`screen_id`) REFERENCES `screens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_schedules_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- SCREEN GROUPS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `screen_groups` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED    NOT NULL,
  `name`        VARCHAR(255)    NOT NULL,
  `description` TEXT            DEFAULT NULL,
  `color`       VARCHAR(7)      DEFAULT '#3B82F6',
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_groups_tenant` (`tenant_id`),
  CONSTRAINT `fk_groups_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `screen_group_members` (
  `group_id`    INT UNSIGNED    NOT NULL,
  `screen_id`   INT UNSIGNED    NOT NULL,
  PRIMARY KEY (`group_id`, `screen_id`),
  CONSTRAINT `fk_sgm_group` FOREIGN KEY (`group_id`) REFERENCES `screen_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sgm_screen` FOREIGN KEY (`screen_id`) REFERENCES `screens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- HEARTBEATS (Screen telemetry)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `heartbeats` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `screen_id`       INT UNSIGNED    NOT NULL,
  `ip_address`      VARCHAR(45)     NOT NULL,
  `cpu_usage`       DECIMAL(5,2)    DEFAULT NULL,
  `memory_usage`    DECIMAL(5,2)    DEFAULT NULL,
  `disk_usage`      DECIMAL(5,2)    DEFAULT NULL,
  `uptime`          INT UNSIGNED    DEFAULT NULL COMMENT 'seconds',
  `current_item`    VARCHAR(255)    DEFAULT NULL,
  `player_version`  VARCHAR(20)     DEFAULT NULL,
  `errors`          JSON            DEFAULT NULL,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_heartbeats_screen` (`screen_id`),
  KEY `idx_heartbeats_created` (`created_at`),
  CONSTRAINT `fk_heartbeats_screen` FOREIGN KEY (`screen_id`) REFERENCES `screens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- NOTIFICATIONS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED    NOT NULL,
  `user_id`     INT UNSIGNED    DEFAULT NULL,
  `type`        VARCHAR(100)    NOT NULL,
  `title`       VARCHAR(255)    NOT NULL,
  `body`        TEXT            NOT NULL,
  `data`        JSON            DEFAULT NULL,
  `severity`    ENUM('info','warning','error','critical') NOT NULL DEFAULT 'info',
  `read_at`     TIMESTAMP       DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_tenant` (`tenant_id`),
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_read` (`read_at`),
  CONSTRAINT `fk_notif_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- ACTIVITY LOGS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED    NOT NULL,
  `user_id`     INT UNSIGNED    DEFAULT NULL,
  `screen_id`   INT UNSIGNED    DEFAULT NULL,
  `action`      VARCHAR(100)    NOT NULL,
  `subject_type` VARCHAR(100)   DEFAULT NULL,
  `subject_id`  INT UNSIGNED    DEFAULT NULL,
  `description` TEXT            DEFAULT NULL,
  `old_values`  JSON            DEFAULT NULL,
  `new_values`  JSON            DEFAULT NULL,
  `ip_address`  VARCHAR(45)     DEFAULT NULL,
  `user_agent`  VARCHAR(500)    DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_tenant` (`tenant_id`),
  KEY `idx_logs_user` (`user_id`),
  KEY `idx_logs_action` (`action`),
  KEY `idx_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- MENU BOARDS (Restaurant feature)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `menu_categories` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED    NOT NULL,
  `location_id` INT UNSIGNED    DEFAULT NULL,
  `name`        VARCHAR(255)    NOT NULL,
  `name_en`     VARCHAR(255)    DEFAULT NULL,
  `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `icon`        VARCHAR(100)    DEFAULT NULL,
  `color`       VARCHAR(7)      DEFAULT '#FF6B35',
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_menucat_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `menu_items` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`     INT UNSIGNED    NOT NULL,
  `category_id`   INT UNSIGNED    NOT NULL,
  `name`          VARCHAR(255)    NOT NULL,
  `name_en`       VARCHAR(255)    DEFAULT NULL,
  `description`   TEXT            DEFAULT NULL,
  `price`         DECIMAL(12,2)   NOT NULL DEFAULT 0,
  `original_price` DECIMAL(12,2)  DEFAULT NULL,
  `currency`      VARCHAR(3)      NOT NULL DEFAULT 'IRR',
  `image`         VARCHAR(500)    DEFAULT NULL,
  `calories`      INT UNSIGNED    DEFAULT NULL,
  `is_special`    TINYINT(1)      NOT NULL DEFAULT 0,
  `is_available`  TINYINT(1)      NOT NULL DEFAULT 1,
  `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `tags`          JSON            DEFAULT NULL COMMENT '["vegan","spicy","popular"]',
  `available_from` TIME           DEFAULT NULL,
  `available_to`  TIME            DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_menu_tenant` (`tenant_id`),
  KEY `idx_menu_category` (`category_id`),
  CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- CAMPAIGNS (Promotions / Emergency Broadcasts)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `campaigns` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED    NOT NULL,
  `created_by`  INT UNSIGNED    NOT NULL,
  `name`        VARCHAR(255)    NOT NULL,
  `type`        ENUM('promo','emergency','announcement','weather') NOT NULL DEFAULT 'promo',
  `content`     TEXT            NOT NULL,
  `media_id`    INT UNSIGNED    DEFAULT NULL,
  `target`      ENUM('all','group','screen') NOT NULL DEFAULT 'all',
  `target_ids`  JSON            DEFAULT NULL,
  `priority`    TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `start_at`    TIMESTAMP       DEFAULT NULL,
  `end_at`      TIMESTAMP       DEFAULT NULL,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaigns_tenant` (`tenant_id`),
  KEY `idx_campaigns_type` (`type`),
  KEY `idx_campaigns_dates` (`start_at`, `end_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- API KEYS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED    NOT NULL,
  `user_id`     INT UNSIGNED    NOT NULL,
  `name`        VARCHAR(255)    NOT NULL,
  `key_hash`    VARCHAR(255)    NOT NULL UNIQUE,
  `key_prefix`  VARCHAR(10)     NOT NULL,
  `permissions` JSON            DEFAULT NULL,
  `last_used_at` TIMESTAMP      DEFAULT NULL,
  `expires_at`  TIMESTAMP       DEFAULT NULL,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_apikeys_tenant` (`tenant_id`),
  KEY `idx_apikeys_hash` (`key_hash`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- RATE LIMITING
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`         VARCHAR(255)    NOT NULL,
  `attempts`    INT UNSIGNED    NOT NULL DEFAULT 1,
  `reset_at`    TIMESTAMP       NOT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rate_key` (`key`),
  KEY `idx_rate_reset` (`reset_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ─────────────────────────────────────────
-- MODULE SYSTEM (added in v1.1)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `modules` (
  `id`           VARCHAR(50) NOT NULL,
  `tenant_id`    INT UNSIGNED NOT NULL,
  `name`         VARCHAR(255) NOT NULL,
  `version`      VARCHAR(20) DEFAULT '1.0.0',
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `settings`     JSON DEFAULT NULL,
  `installed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pre-install modules for demo tenant
INSERT IGNORE INTO `modules` (`id`,`tenant_id`,`name`,`version`,`is_active`) VALUES
('fids',      1, 'سامانه اطلاع‌رسانی پرواز (FIDS)', '1.2.0', 1),
('hotel',     1, 'اطلاع‌رسانی هتل', '1.1.0', 1),
('menu',      1, 'منوی رستوران', '2.0.0', 1),
('transport', 1, 'حمل‌ونقل عمومی', '1.0.0', 0),
('retail',    1, 'فروشگاه و خرده‌فروشی', '1.0.0', 0),
('corporate', 1, 'اطلاع‌رسانی سازمانی', '1.0.0', 0);

-- ═══════════════════════════════════════════════════════
-- MODULE TABLES — نصب همه ماژول‌ها
-- ═══════════════════════════════════════════════════════

-- ─── FIDS Module ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fids_airlines` (
    `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`     VARCHAR(5) NOT NULL UNIQUE,
    `name_fa`  VARCHAR(100) NOT NULL,
    `name_en`  VARCHAR(100) NOT NULL,
    `logo_url` VARCHAR(500) DEFAULT NULL,
    `country`  VARCHAR(50) DEFAULT NULL,
    `color`    VARCHAR(7) DEFAULT '#FFFFFF',
    PRIMARY KEY (`id`),
    KEY `idx_airline_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `fids_airlines` (`code`,`name_fa`,`name_en`,`color`) VALUES
('IR','هواپیمایی ایران ایر','Iran Air','#0B6EA9'),
('W5','هواپیمایی ماهان','Mahan Air','#C8102E'),
('EP','ایران ایرتور','Iran Airtour','#00A86B'),
('B9','معراج ایرلاینز','Meraj Airlines','#1B3A6B'),
('QB','قشم ایر','Qeshm Air','#FF6B00'),
('I3','آتا ایرلاینز','ATA Airlines','#003DA5'),
('TK','ترکیش ایرلاینز','Turkish Airlines','#E81932'),
('EK','امارات','Emirates','#D71921'),
('QR','قطر ایرویز','Qatar Airways','#5C0632'),
('FZ','فلای دبی','Fly Dubai','#FF0000');

CREATE TABLE IF NOT EXISTS `fids_flights` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`      INT UNSIGNED NOT NULL,
    `flight_number`  VARCHAR(20) NOT NULL,
    `airline_code`   VARCHAR(5) NOT NULL,
    `airline_name`   VARCHAR(100) NOT NULL,
    `airline_name_en` VARCHAR(100) DEFAULT NULL,
    `airline_logo`   VARCHAR(500) DEFAULT NULL,
    `type`           ENUM('departure','arrival') NOT NULL DEFAULT 'departure',
    `origin`         VARCHAR(100) DEFAULT NULL,
    `origin_code`    VARCHAR(5) DEFAULT NULL,
    `destination`    VARCHAR(100) DEFAULT NULL,
    `destination_code` VARCHAR(5) DEFAULT NULL,
    `scheduled_time` DATETIME NOT NULL,
    `estimated_time` DATETIME DEFAULT NULL,
    `actual_time`    DATETIME DEFAULT NULL,
    `terminal`       VARCHAR(20) DEFAULT NULL,
    `gate`           VARCHAR(20) DEFAULT NULL,
    `belt`           VARCHAR(20) DEFAULT NULL,
    `status`         ENUM('scheduled','boarding','departed','arrived','delayed','cancelled','diverted','gate_change') NOT NULL DEFAULT 'scheduled',
    `status_fa`      VARCHAR(50) DEFAULT NULL,
    `delay_minutes`  SMALLINT DEFAULT 0,
    `aircraft_type`  VARCHAR(50) DEFAULT NULL,
    `remarks`        TEXT DEFAULT NULL,
    `remarks_en`     TEXT DEFAULT NULL,
    `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fids_tenant` (`tenant_id`),
    KEY `idx_fids_type`   (`type`),
    KEY `idx_fids_time`   (`scheduled_time`),
    KEY `idx_fids_status` (`status`),
    KEY `idx_fids_gate`   (`gate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Hotel Module ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `hotel_info` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `hotel_name`    VARCHAR(255) NOT NULL DEFAULT 'هتل ما',
    `hotel_name_en` VARCHAR(255) DEFAULT NULL,
    `slogan`        VARCHAR(500) DEFAULT NULL,
    `logo`          VARCHAR(500) DEFAULT NULL,
    `address`       TEXT DEFAULT NULL,
    `phone`         VARCHAR(20) DEFAULT NULL,
    `email`         VARCHAR(255) DEFAULT NULL,
    `website`       VARCHAR(500) DEFAULT NULL,
    `checkin_time`  TIME DEFAULT '14:00:00',
    `checkout_time` TIME DEFAULT '12:00:00',
    `wifi_name`     VARCHAR(100) DEFAULT NULL,
    `wifi_pass`     VARCHAR(100) DEFAULT NULL,
    `stars`         TINYINT UNSIGNED DEFAULT 5,
    `settings`      JSON DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_hotel_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hotel_events` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `title`       VARCHAR(255) NOT NULL,
    `title_en`    VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `location`    VARCHAR(255) DEFAULT NULL,
    `hall_name`   VARCHAR(100) DEFAULT NULL,
    `floor`       VARCHAR(20) DEFAULT NULL,
    `start_at`    DATETIME NOT NULL,
    `end_at`      DATETIME DEFAULT NULL,
    `organizer`   VARCHAR(255) DEFAULT NULL,
    `capacity`    INT UNSIGNED DEFAULT NULL,
    `type`        ENUM('conference','wedding','seminar','party','exhibition','other') DEFAULT 'conference',
    `image`       VARCHAR(500) DEFAULT NULL,
    `color`       VARCHAR(7) DEFAULT '#d4af37',
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_hotel_events_tenant` (`tenant_id`),
    KEY `idx_hotel_events_date`   (`start_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hotel_amenities` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `name_en`     VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `icon`        VARCHAR(100) DEFAULT 'fas fa-star',
    `floor`       VARCHAR(20) DEFAULT NULL,
    `hours`       VARCHAR(100) DEFAULT NULL,
    `phone`       VARCHAR(20) DEFAULT NULL,
    `sort_order`  SMALLINT UNSIGNED DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_hotel_amenities_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hotel_room_service` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `category`     VARCHAR(100) NOT NULL,
    `category_en`  VARCHAR(100) DEFAULT NULL,
    `name`         VARCHAR(255) NOT NULL,
    `name_en`      VARCHAR(255) DEFAULT NULL,
    `description`  TEXT DEFAULT NULL,
    `price`        DECIMAL(12,2) NOT NULL DEFAULT 0,
    `currency`     VARCHAR(3) DEFAULT 'IRR',
    `image`        VARCHAR(500) DEFAULT NULL,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `available_from` TIME DEFAULT NULL,
    `available_to`   TIME DEFAULT NULL,
    `sort_order`   SMALLINT UNSIGNED DEFAULT 0,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_hotel_rs_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hotel_attractions` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `name_en`     VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `category`    VARCHAR(100) DEFAULT NULL,
    `distance`    VARCHAR(50) DEFAULT NULL,
    `image`       VARCHAR(500) DEFAULT NULL,
    `map_url`     VARCHAR(1000) DEFAULT NULL,
    `sort_order`  SMALLINT UNSIGNED DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_hotel_attr_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Corporate Module ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `corp_kpi` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `value`       VARCHAR(100) NOT NULL,
    `target`      VARCHAR(100) DEFAULT NULL,
    `unit`        VARCHAR(50) DEFAULT NULL,
    `change_pct`  DECIMAL(5,2) DEFAULT NULL,
    `icon`        VARCHAR(100) DEFAULT 'fas fa-chart-line',
    `color`       VARCHAR(7) DEFAULT '#6366f1',
    `sort_order`  SMALLINT UNSIGNED DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_corp_kpi_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_news` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `title`       VARCHAR(500) NOT NULL,
    `body`        TEXT DEFAULT NULL,
    `image`       VARCHAR(500) DEFAULT NULL,
    `category`    VARCHAR(100) DEFAULT NULL,
    `priority`    TINYINT UNSIGNED DEFAULT 5,
    `is_pinned`   TINYINT(1) DEFAULT 0,
    `published_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at`  TIMESTAMP NULL DEFAULT NULL,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_corp_news_tenant` (`tenant_id`),
    KEY `idx_corp_news_pub`    (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_departments` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `floor`       VARCHAR(20) DEFAULT NULL,
    `room`        VARCHAR(20) DEFAULT NULL,
    `phone`       VARCHAR(20) DEFAULT NULL,
    `manager`     VARCHAR(255) DEFAULT NULL,
    `icon`        VARCHAR(100) DEFAULT 'fas fa-door-open',
    `sort_order`  SMALLINT UNSIGNED DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_corp_dept_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Retail Module ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `retail_products` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `category`    VARCHAR(100) NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `name_en`     VARCHAR(255) DEFAULT NULL,
    `price`       DECIMAL(15,2) NOT NULL DEFAULT 0,
    `old_price`   DECIMAL(15,2) DEFAULT NULL,
    `currency`    VARCHAR(10) NOT NULL DEFAULT 'تومان',
    `unit`        VARCHAR(50) DEFAULT NULL,
    `image`       VARCHAR(500) DEFAULT NULL,
    `barcode`     VARCHAR(100) DEFAULT NULL,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `is_offer`    TINYINT(1) NOT NULL DEFAULT 0,
    `offer_ends`  DATETIME DEFAULT NULL,
    `stock`       INT DEFAULT NULL,
    `sort_order`  SMALLINT UNSIGNED DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_retail_tenant`   (`tenant_id`),
    KEY `idx_retail_featured` (`is_featured`),
    KEY `idx_retail_offer`    (`is_offer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `retail_queue` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `counter`      VARCHAR(50) NOT NULL,
    `ticket_number` INT UNSIGNED NOT NULL,
    `called_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status`       ENUM('waiting','serving','done') DEFAULT 'serving',
    PRIMARY KEY (`id`),
    KEY `idx_queue_tenant` (`tenant_id`),
    KEY `idx_queue_called` (`called_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Transport Module ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transport_schedules` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `type`         ENUM('bus','metro','train','tram') NOT NULL DEFAULT 'bus',
    `line`         VARCHAR(100) NOT NULL,
    `direction`    VARCHAR(255) NOT NULL,
    `station`      VARCHAR(255) NOT NULL,
    `departure`    TIME NOT NULL,
    `frequency_min` SMALLINT UNSIGNED DEFAULT NULL,
    `days`         JSON DEFAULT NULL,
    `notes`        TEXT DEFAULT NULL,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_trans_tenant` (`tenant_id`),
    KEY `idx_trans_type`   (`type`),
    KEY `idx_trans_dep`    (`departure`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Module install records ───────────────────────────────
INSERT IGNORE INTO `hotel_amenities` (`tenant_id`,`name`,`name_en`,`icon`,`floor`,`hours`,`sort_order`) VALUES
(1,'رستوران اصلی','Main Restaurant','fas fa-utensils','طبقه همکف','07:00 - 23:00',1),
(1,'استخر','Swimming Pool','fas fa-person-swimming','طبقه پنجم','06:00 - 22:00',2),
(1,'سالن ورزشی','Fitness Center','fas fa-dumbbell','طبقه زیرزمین','06:00 - 22:00',3),
(1,'اسپا','Spa & Wellness','fas fa-spa','طبقه پنجم','09:00 - 21:00',4),
(1,'پارکینگ','Parking','fas fa-parking','زیرزمین','24 ساعت',7),
(1,'دسترسی به اینترنت','Free WiFi','fas fa-wifi','همه طبقات','24 ساعت',8);

INSERT IGNORE INTO `corp_kpi` (`tenant_id`,`name`,`value`,`target`,`unit`,`change_pct`,`icon`,`color`,`sort_order`) VALUES
(1,'فروش ماهانه','۱۲۵,۰۰۰','۱۵۰,۰۰۰','میلیون تومان',8.3,'fas fa-chart-line','#22c55e',1),
(1,'تعداد مشتریان','۴,۸۲۰','۵,۰۰۰','نفر',2.1,'fas fa-users','#6366f1',2),
(1,'رضایت مشتری','۹۲','۹۵','درصد',-1.2,'fas fa-smile','#f59e0b',3);

INSERT IGNORE INTO `modules` (`id`,`tenant_id`,`name`,`version`,`is_active`) VALUES
('fids',1,'سامانه اطلاع‌رسانی پرواز (FIDS)','1.2.0',1),
('hotel',1,'اطلاع‌رسانی هتل','1.1.0',1),
('menu',1,'منوی رستوران','2.0.0',1),
('transport',1,'حمل‌ونقل عمومی','1.0.0',0),
('retail',1,'فروشگاه و خرده‌فروشی','1.0.0',0),
('corporate',1,'اطلاع‌رسانی سازمانی','1.0.0',0);
