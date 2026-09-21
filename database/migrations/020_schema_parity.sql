-- ── هم‌ترازی اسکیما با نصب‌کننده ────────────────────────────────
-- این ستون‌ها و ایندکس‌ها تا امروز فقط در public/install.php ساخته می‌شدند
-- (بخش $extraCols)، نه در هیچ migration. نتیجه: نصبی که با
-- `php artisan db:migrate` انجام شود — همان کاری که install.sh می‌کند —
-- جدول screens را بدون iptv_menu_id و screen_type می‌سازد و پورتال،
-- منوی IPTV و صفحات نوع iptv از کار می‌افتند.
--
-- همه‌ی بلوک‌ها idempotent هستند و روی نصب‌های موجود بی‌اثرند.

-- ── screens ──────────────────────────────────────────────────────
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='screen_type')=0,
  "ALTER TABLE screens ADD COLUMN screen_type ENUM('signage','iptv') NOT NULL DEFAULT 'signage'",
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='group_id')=0,
  'ALTER TABLE screens ADD COLUMN group_id INT UNSIGNED DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='iptv_channel_id')=0,
  'ALTER TABLE screens ADD COLUMN iptv_channel_id INT UNSIGNED DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='iptv_menu_id')=0,
  'ALTER TABLE screens ADD COLUMN iptv_menu_id INT UNSIGNED DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='iptv_settings')=0,
  'ALTER TABLE screens ADD COLUMN iptv_settings JSON DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='pending_commands')=0,
  'ALTER TABLE screens ADD COLUMN pending_commands JSON DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND COLUMN_NAME='emergency_broadcast')=0,
  'ALTER TABLE screens ADD COLUMN emergency_broadcast TEXT DEFAULT NULL',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── playlists ────────────────────────────────────────────────────
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='playlists' AND COLUMN_NAME='default_duration')=0,
  'ALTER TABLE playlists ADD COLUMN default_duration SMALLINT UNSIGNED NOT NULL DEFAULT 10',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='playlists' AND COLUMN_NAME='transition')=0,
  "ALTER TABLE playlists ADD COLUMN transition VARCHAR(20) DEFAULT 'fade'",
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ── ایندکس‌ها ────────────────────────────────────────────────────
SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND INDEX_NAME='idx_screen_type')=0,
  'ALTER TABLE screens ADD INDEX idx_screen_type (screen_type)',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND INDEX_NAME='idx_group_id')=0,
  'ALTER TABLE screens ADD INDEX idx_group_id (group_id)',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND INDEX_NAME='idx_iptv_menu')=0,
  'ALTER TABLE screens ADD INDEX idx_iptv_menu (iptv_menu_id)',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='screens' AND INDEX_NAME='idx_iptv_room')=0,
  'ALTER TABLE screens ADD INDEX idx_iptv_room (iptv_room_id)',
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
