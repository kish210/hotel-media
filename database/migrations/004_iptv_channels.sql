CREATE TABLE IF NOT EXISTS `iptv_channels` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`    INT UNSIGNED NOT NULL DEFAULT 1,
    `name`         VARCHAR(255) NOT NULL,
    `name_en`      VARCHAR(255) DEFAULT NULL,
    `stream_url`   TEXT NOT NULL,
    `logo_url`     VARCHAR(500) DEFAULT NULL,
    `category`     VARCHAR(100) DEFAULT 'general',
    `protocol`     VARCHAR(20) DEFAULT 'hls',
    `epg_id`       VARCHAR(100) DEFAULT NULL,
    `sort_order`   SMALLINT UNSIGNED DEFAULT 0,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant` (`tenant_id`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
