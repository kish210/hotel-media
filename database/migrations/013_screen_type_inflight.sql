-- ── 013 Add 'inflight' to screens.screen_type ENUM ───────────────────────────
-- MySQL 8 compatible

SET @db = DATABASE();

-- Only ALTER if 'inflight' is not already in the ENUM
SET @col_type = (
    SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME   = 'screens'
      AND COLUMN_NAME  = 'screen_type'
);

SET @q = IF(
    LOCATE('inflight', @col_type) = 0,
    "ALTER TABLE screens MODIFY COLUMN screen_type ENUM('signage','iptv','inflight') NOT NULL DEFAULT 'signage'",
    'SELECT 1'
);
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
