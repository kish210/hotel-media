CREATE TABLE IF NOT EXISTS `apk_versions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `version_code`  INT UNSIGNED NOT NULL,
    `version_name`  VARCHAR(20) NOT NULL,
    `apk_filename`  VARCHAR(255) NOT NULL,
    `file_size`     BIGINT UNSIGNED DEFAULT 0,
    `changelog`     TEXT DEFAULT NULL,
    `force_update`  TINYINT(1) NOT NULL DEFAULT 0,
    `min_version`   INT UNSIGNED DEFAULT 0,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active` (`is_active`, `version_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
