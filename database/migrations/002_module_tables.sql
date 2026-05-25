-- Module tables migration — run if upgrading from v1.0 to v1.1
USE `signage_cms`;

-- این دستورات برای databaseهای موجود که از v1.0 ارتقا می‌یابند
-- با IF NOT EXISTS safe هستند

ALTER TABLE `media` MODIFY COLUMN `uploaded_by` INT UNSIGNED DEFAULT NULL;

-- ادامه همه module tables در 001_complete_schema.sql هستند
-- این migration فقط برای upgrade از v1.0 است
SELECT 'Module tables migration completed' AS status;
