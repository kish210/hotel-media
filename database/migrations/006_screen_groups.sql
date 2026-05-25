-- گروه‌بندی صفحات نمایش
CREATE TABLE IF NOT EXISTS `screen_groups` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`   INT UNSIGNED NOT NULL DEFAULT 1,
    `name`        VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `type`        ENUM('signage','iptv') NOT NULL DEFAULT 'signage',
    `color`       VARCHAR(7) DEFAULT '#f97316',
    `sort_order`  SMALLINT UNSIGNED DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- اضافه کردن ستون‌ها به screens (installer از addColIfMissing استفاده می‌کنه)
-- این فایل به عنوان reference نگه داشته می‌شه؛ installer جداگانه ستون‌ها رو چک می‌کنه

-- داده نمونه گروه‌ها
INSERT IGNORE INTO `screen_groups` (id, tenant_id, name, type, color) VALUES
(1, 1, 'لابی و ورودی', 'signage', '#f97316'),
(2, 1, 'رستوران', 'signage', '#22c55e'),
(3, 1, 'کانال‌های IPTV', 'iptv', '#ef4444'),
(4, 1, 'اتاق‌های هتل', 'iptv', '#a855f7');
