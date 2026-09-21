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

-- ‏001_complete_schema این جدول را بدون type / sort_order / is_active می‌سازد،
-- پس CREATE بالا روی نصب موجود no-op است و ستون‌ها باید جداگانه اضافه شوند.
-- بدون این بلاک، INSERT پایین روی دیتابیس تازه با «Unknown column 'type'» شکست می‌خورد.
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screen_groups' AND COLUMN_NAME='type')=0,
  "ALTER TABLE `screen_groups` ADD COLUMN `type` ENUM('signage','iptv') NOT NULL DEFAULT 'signage'",
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screen_groups' AND COLUMN_NAME='sort_order')=0,
  'ALTER TABLE `screen_groups` ADD COLUMN `sort_order` SMALLINT UNSIGNED DEFAULT 0',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screen_groups' AND COLUMN_NAME='is_active')=0,
  'ALTER TABLE `screen_groups` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- داده نمونه گروه‌ها
INSERT IGNORE INTO `screen_groups` (id, tenant_id, name, type, color) VALUES
(1, 1, 'لابی و ورودی', 'signage', '#f97316'),
(2, 1, 'رستوران', 'signage', '#22c55e'),
(3, 1, 'کانال‌های IPTV', 'iptv', '#ef4444'),
(4, 1, 'اتاق‌های هتل', 'iptv', '#a855f7');
